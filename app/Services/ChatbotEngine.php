<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{BranchModel, CustomerModel, CartModel, ConversationModel, MenuModel};
use App\Config\Database;
use App\Plugin\HookManager;
use App\Skills\{MenuSkill, PromoSkill, CartSkill, CheckoutSkill, OrderHistorySkill, SmallTalkSkill, RefusalSkill, SkillInterface};

class ChatbotEngine
{
    private BranchModel              $branchModel;
    private CustomerModel            $customerModel;
    private CartModel                $cartModel;
    private ConversationModel        $convModel;
    private IntentDetectorInterface  $detector;
    private ChatEntityExtractor      $entityExtractor;
    private array $detectorMeta = ['type' => 'rule-based', 'provider' => 'none', 'model' => ''];

    /** @var SkillInterface[] */
    private array $skills;

    private const CHECKOUT_STATES = [
        'awaiting_name', 'awaiting_email', 'awaiting_wa',
        'awaiting_fulfillment', 'awaiting_table',
        'awaiting_address', 'awaiting_postal', 'awaiting_confirmation',
    ];
    private const VARIANT_STATE        = 'awaiting_variant';
    private const TOPPING_STATE        = 'awaiting_toppings';
    private const REMOVE_VARIANT_STATE = 'awaiting_remove_variant';
    private const ITEM_NOTES_STATE     = 'awaiting_item_notes';

    private const ITEM_NOTES_ESCAPE = [
        'tanya_menu', 'tanya_promo', 'lihat_cart', 'clear_cart',
        'checkout', 'tambah_item', 'hapus_item', 'ubah_item', 'pakai_promo',
    ];

    private const CHECKOUT_ESCAPE_INTENTS = [
        'tanya_menu', 'tanya_promo', 'lihat_cart', 'clear_cart', 'hapus_item', 'pakai_promo',
        'tambah_item', 'ubah_item',
    ];

    public function __construct(?IntentDetectorInterface $detector = null)
    {
        $this->branchModel   = new BranchModel();
        $this->customerModel = new CustomerModel();
        $this->cartModel     = new CartModel();
        $this->convModel     = new ConversationModel();
        $this->detector      = $detector ?? $this->loadDetector();
        $this->entityExtractor = new ChatEntityExtractor();

        $rawSkills = HookManager::applyFilters('skills.registered', [
            ['skill' => new MenuSkill(),         'priority' => 10],
            ['skill' => new PromoSkill(),        'priority' => 20],
            ['skill' => new CartSkill(),         'priority' => 30],
            ['skill' => new CheckoutSkill(),     'priority' => 40],
            ['skill' => new OrderHistorySkill(), 'priority' => 50],
            ['skill' => new RefusalSkill(),      'priority' => 999],
        ]);
        $this->skills = $this->normalizeSkills((array) $rawSkills);
    }

    public function process(
        string $channel,
        int    $branchId,
        string $customerIdentifier,
        string $message
    ): array {
        $branch = $this->branchModel->find($branchId);
        if (!$branch || !$branch['is_active']) {
            return $this->errorResponse('Branch not found or inactive.');
        }

        $currency = $this->branchModel->getCurrency($branchId);
        $language = $this->branchModel->getLanguage($branchId);
        $timezone = $this->branchModel->getTimezone($branchId);
        $nowLocal = (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s');
        $customer = $channel === 'web'
            ? $this->customerModel->resolveWebCustomer($customerIdentifier)
            : $this->customerModel->findOrCreate($channel, $customerIdentifier);

        $sessionKey   = $this->buildSessionKey($channel, $branchId, $customerIdentifier);
        $conversation = $this->convModel->getOrCreate($branchId, $customer['id'], $channel, $sessionKey);

        if (method_exists($this->detector, 'setLoggingContext')) {
            $this->detector->setLoggingContext($branchId, $conversation['id']);
        }
        $convId       = $conversation['id'];
        $convCtx      = $this->convModel->getContext($convId);
        $cart         = $this->cartModel->getOrCreate($sessionKey, $branchId, $customer['id']);
        $cartItems    = $this->cartModel->getItems($cart['id']);

        // Action: pesan baru masuk — plugin bisa log, moderate, dsb.
        HookManager::doAction('chat.message_received', $message, $branchId, $channel);

        // Filter: plugin bisa modifikasi pesan sebelum diproses AI/rule-based
        $message = (string) HookManager::applyFilters('chat.before_ai', $message, $branchId, $channel);
        $entities = $this->entityExtractor->extract($message, $currency);

        $intent = $this->detector->detect($message, ['state' => $conversation['state']]);
        $intent = $this->normalizePendingStateIntent($intent, $message, (string)($conversation['state'] ?? 'idle'));
        $intent = $this->applyFollowUpHeuristics($intent, $message, $convCtx);
        $intent = $this->preferCheckoutEditIntent($intent, $message, $conversation['state']);
        $intent = $this->preferActiveCartIntent($intent, $message, $cartItems, $convCtx);
        $intent = $this->preferMenuSelectionIntent($intent, $message, $convCtx);
        if ($intent === 'out_of_scope' && SmallTalkSkill::isSmallTalk($message)) {
            $intent = 'small_talk';
        }

        // Action: intent sudah dideteksi
        HookManager::doAction('chat.intent_detected', $intent, $message, $branchId);

        $this->convModel->addMessage($convId, 'customer', $message, $intent);

        $greeting = $this->buildGreeting($customer, $intent, $language, $branchId);

        $context = [
            'channel'      => $channel,
            'branch_id'    => $branchId,
            'branch'       => $branch,
            'customer'     => $customer,
            'conversation' => $conversation,
            'cart'         => $cart,
            'intent'       => $intent,
            'message'      => $message,
            'entities'     => $entities,
            'language'     => $language,
            'currency'         => $currency,
            'ppn_rate'         => $this->branchModel->getPpnRate($branchId),
            'branch_timezone'  => $timezone,
            'now_local'        => $nowLocal,
            'conv_context'     => $convCtx,
        ];

        $result = $this->dispatch($context);

        if ($greeting) {
            $result['reply_message'] = $greeting . "\n\n" . ($result['reply_message'] ?? '');
        }

        $freshCart  = $this->cartModel->getBySession($sessionKey);
        $cartItems  = $freshCart ? $this->cartModel->getItems($freshCart['id']) : [];
        $cartTotal  = $freshCart ? $this->cartModel->getTotal($freshCart['id']) : 0.0;

        // Filter: plugin bisa modifikasi teks reply sebelum dikirim ke customer
        $result['reply_message'] = (string) HookManager::applyFilters(
            'chat.after_ai',
            $result['reply_message'] ?? '',
            $branchId,
            $intent,
            [
                'language'   => $language,
                'currency'   => $currency,
                'cart'       => $freshCart ?: [],
                'cart_items' => $cartItems,
                'cart_total' => $cartTotal,
                'now_local'  => $nowLocal,
                'customer'   => $customer,
            ]
        );

        $newState = $result['new_state']   ?? 'idle';
        $newCtx   = $result['conv_context'] ?? $convCtx;
        $this->convModel->updateState($convId, $newState, $newCtx);
        $this->convModel->addMessage($convId, 'bot', $result['reply_message'] ?? '', $intent);

        return [
            'reply_message' => $result['reply_message'] ?? '',
            'intent'        => $intent,
            'cart_state'    => ['cart' => $freshCart, 'items' => $cartItems],
            'action_result' => $result['action_result'] ?? null,
            'conversation'  => ['id' => $convId, 'state' => $newState],
            'detector'      => $this->detectorMeta,
            'entities'      => $entities,
        ];
    }

    private function dispatch(array $context): array
    {
        $intent = $context['intent'];
        $state = (string)($context['conversation']['state'] ?? '');

        // Jika customer mengirim order baru saat ada pending state, batalkan pending dan proses ulang.
        $pendingStates = [self::VARIANT_STATE, self::TOPPING_STATE, self::REMOVE_VARIANT_STATE, self::ITEM_NOTES_STATE];
        if ($intent === 'tambah_item'
            && in_array($state, $pendingStates, true)
            && $this->looksLikeFreshAddRequest((string)($context['message'] ?? ''), $state)) {
            $context['conv_context'] = array_diff_key(
                $context['conv_context'] ?? [],
                array_flip(['pending_variant_selection', 'pending_topping_selection', 'pending_note_cart_item_ids'])
            );
            $context['conversation']['state'] = 'idle';
            return $this->dispatchToSkill($context);
        }

        if (($context['conversation']['state'] ?? '') === self::ITEM_NOTES_STATE
            && !in_array($intent, self::ITEM_NOTES_ESCAPE, true)) {
            return $this->runSkill(new CartSkill(), $context);
        }

        // Saat memilih topping, nama-nama topping bisa salah dideteksi sebagai tanya_menu.
        // Gunakan escape list yang lebih ketat agar proses topping tidak terganggu.
        if (in_array(($context['conversation']['state'] ?? ''), [self::VARIANT_STATE, self::REMOVE_VARIANT_STATE], true)
            && !in_array($intent, ['tanya_menu', 'tanya_promo', 'lihat_cart', 'clear_cart', 'checkout'], true)) {
            return $this->runSkill(new CartSkill(), $context);
        }
        if (($context['conversation']['state'] ?? '') === self::TOPPING_STATE
            && !in_array($intent, ['lihat_cart', 'clear_cart', 'checkout'], true)) {
            return $this->runSkill(new CartSkill(), $context);
        }

        $inCheckout = in_array($context['conversation']['state'], self::CHECKOUT_STATES);
        if ($inCheckout && !in_array($intent, self::CHECKOUT_ESCAPE_INTENTS)) {
            return $this->runSkill(new CheckoutSkill(), $context);
        }

        // Escape intent during checkout: jawab dulu, lalu lanjut tanya field checkout
        if ($inCheckout && in_array($intent, self::CHECKOUT_ESCAPE_INTENTS, true)) {
            $escapeResult  = $this->dispatchToSkill($context);
            $checkoutSkill = new CheckoutSkill();
            $resumeResult  = $checkoutSkill->handle(array_merge($context, ['intent' => '__resume__']));
            return [
                'reply_message' => ($escapeResult['reply_message'] ?? '') . "\n\n" . ($resumeResult['reply'] ?? ''),
                'new_state'     => $resumeResult['state'] ?? (string)($context['conversation']['state'] ?? 'idle'),
                'action_result' => $escapeResult['action_result'] ?? null,
                'conv_context'  => $resumeResult['conv_context'] ?? $context['conv_context'],
            ];
        }

        // Follow-up "detail" after showing order history
        if (in_array($intent, ['out_of_scope', 'small_talk'])
            && !empty($context['conv_context']['last_orders'])
            && preg_match('/\bdetail\b/iu', $context['message'])) {
            $context['intent'] = 'tanya_status_order';
            $intent = 'tanya_status_order';
        }

        if ($intent === 'small_talk') {
            return $this->runSkill(new SmallTalkSkill(), $context);
        }

        return $this->dispatchToSkill($context);
    }

    private function dispatchToSkill(array $context): array
    {
        foreach ($this->skills as $skill) {
            if ($skill->canHandle($context['intent'])) {
                return $this->runSkill($skill, $context);
            }
        }

        $lang  = $context['language'];
        $reply = $lang === 'id'
            ? "Maaf, saya tidak mengerti. Ketik *menu* untuk melihat menu atau *bantuan* untuk petunjuk."
            : "Sorry, I didn't understand. Type *menu* to browse or *help* for assistance.";

        return ['reply_message' => $reply, 'new_state' => 'idle', 'action_result' => null, 'conv_context' => $context['conv_context']];
    }

    private function runSkill(SkillInterface $skill, array $context): array
    {
        $result = $skill->handle($context);
        return [
            'reply_message' => $result['reply'],
            'new_state'     => $result['state'],
            'action_result' => $result['action_result'] ?? null,
            'conv_context'  => $result['conv_context'] ?? $context['conv_context'],
        ];
    }

    private function buildGreeting(array $customer, string $intent, string $lang, int $branchId): string
    {
        if (empty($customer['name']) || $intent !== 'tambah_item') {
            return '';
        }

        $names = $this->getFavoriteNames($customer['id'], $branchId);
        if (empty($names)) {
            return '';
        }

        $itemList = implode(' dan ', $names);
        return $lang === 'id'
            ? "Halo kembali, {$customer['name']}! Favorit kamu biasanya {$itemList}. Mau yang sama lagi?"
            : "Welcome back, {$customer['name']}! Your usual favorites are {$itemList}. Want the same again?";
    }

    private function getFavoriteNames(int $customerId, int $branchId): array
    {
        $favIds = $this->customerModel->getFavoriteItems($customerId);
        if (empty($favIds)) { return []; }

        $menuModel = new MenuModel();
        $names     = [];
        foreach (array_slice($favIds, 0, 2) as $id) {
            $item = $menuModel->getItemForBranch($id, $branchId);
            if ($item) { $names[] = $item['name']; }
        }
        return $names;
    }

    private function loadDetector(): IntentDetectorInterface
    {
        $db   = Database::getInstance();
        $rows = $db->query(
            'SELECT setting_key, setting_val FROM app_settings
             WHERE setting_key IN ("llm_provider","llm_api_key","llm_model")'
        )->fetchAll();
        $cfg = array_column($rows, 'setting_val', 'setting_key');

        $provider = $cfg['llm_provider'] ?? 'none';
        $apiKey   = $cfg['llm_api_key']  ?? '';
        $model    = $cfg['llm_model']    ?? '';

        // Filter: plugin bisa mendaftarkan provider AI baru
        // Format: ['nama_provider' => IntentDetectorInterface instance]
        // $apiKey diteruskan agar plugin tidak perlu baca DB sendiri
        $customProviders = (array) HookManager::applyFilters('llm.providers', [], $provider, $model, $apiKey);
        if ($provider !== 'none' && isset($customProviders[$provider])
            && $customProviders[$provider] instanceof IntentDetectorInterface) {
            $this->detectorMeta = ['type' => 'llm', 'provider' => $provider, 'model' => $model];
            return $customProviders[$provider];
        }

        if ($provider !== 'none' && $apiKey !== '') {
            $this->detectorMeta = ['type' => 'llm', 'provider' => $provider, 'model' => $model];
            return new LlmIntentDetector($provider, $apiKey, $model);
        }

        $this->detectorMeta = ['type' => 'rule-based', 'provider' => 'none', 'model' => ''];
        return new IntentDetector();
    }

    private function buildSessionKey(string $channel, int $branchId, string $identifier): string
    {
        return hash('sha256', "{$channel}:{$branchId}:{$identifier}");
    }

    /**
     * @param array<int, mixed> $rawSkills
     * @return array<int, SkillInterface>
     */
    private function normalizeSkills(array $rawSkills): array
    {
        $normalized = [];

        foreach ($rawSkills as $index => $entry) {
            if ($entry instanceof SkillInterface) {
                $normalized[] = [
                    'skill'     => $entry,
                    'priority'  => 100,
                    'index'     => $index,
                ];
                continue;
            }

            if (is_array($entry) && ($entry['skill'] ?? null) instanceof SkillInterface) {
                $normalized[] = [
                    'skill'     => $entry['skill'],
                    'priority'  => (int) ($entry['priority'] ?? 100),
                    'index'     => $index,
                ];
            }
        }

        usort($normalized, function (array $a, array $b): int {
            $priorityCompare = $a['priority'] <=> $b['priority'];
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return $a['index'] <=> $b['index'];
        });

        return array_map(fn(array $row) => $row['skill'], $normalized);
    }

    private function applyFollowUpHeuristics(string $intent, string $message, array $convCtx): string
    {
        if ($intent !== 'out_of_scope') {
            return $intent;
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');
        $lastTopic = (string)($convCtx['last_topic'] ?? '');

        if ($lastTopic === 'menu' && preg_match('/\b(yang mana|mana yang|beda|bedanya|lebih murah|termurah|termahal|cocok|detail lagi|jelasin lagi|info lagi)\b/u', $lower)) {
            return 'tanya_menu';
        }

        if ($lastTopic === 'promo' && preg_match('/\b(kode|promo|diskon|voucher|syarat|minimal|min order|yang mana|mana yang|terbaik|best)\b/u', $lower)) {
            return 'tanya_promo';
        }

        if (in_array($lastTopic, ['cart', 'checkout'], true)) {
            if (preg_match('/\b(jadi|ubah|ganti|kurangi|tambahi|tambah)\b/u', $lower) && preg_match('/\b\d+\b/u', $lower)) {
                return 'ubah_item';
            }
            if (preg_match('/\b(hapus|remove|delete|batalin|batalkan item)\b/u', $lower)) {
                return 'hapus_item';
            }
            if (preg_match('/\b(kode|promo|voucher|pakai promo|use code|apply promo|pakai itu)\b/u', $lower)) {
                return 'pakai_promo';
            }
            if (preg_match('/\b(checkout|lanjut|continue|bayar|proceed|yes|ya)\b/u', $lower) && $lastTopic === 'checkout') {
                return 'konfirmasi_order';
            }
        }

        return $intent;
    }

    private function preferActiveCartIntent(string $intent, string $message, array $cartItems, array $convCtx): string
    {
        if ($intent !== 'tanya_status_order' || empty($cartItems)) {
            return $intent;
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');
        $lastTopic = (string)($convCtx['last_topic'] ?? '');

        if ($lastTopic === 'order_history' || !empty($convCtx['last_orders'])) {
            return $intent;
        }

        if (preg_match('/\bord-\d{8}-[a-z0-9]+\b/i', $lower) === 1) {
            return $intent;
        }

        $looksLikeCurrentCartQuestion = preg_match(
            '/\b(saya\s+pesan\s+apa|pesan\s+apa\s+saya|barusan\s+pesan\s+apa|baru\s+pesan\s+apa|sudah\s+pesan\s+apa|apa\s+pesanan\s+saya|pesanan\s+saya\s+apa|tadi\s+saya\s+pesan\s+apa)\b/u',
            $lower
        ) === 1;

        if ($looksLikeCurrentCartQuestion || in_array($lastTopic, ['cart', 'checkout'], true)) {
            return 'lihat_cart';
        }

        return $intent;
    }

    private function preferMenuSelectionIntent(string $intent, string $message, array $convCtx): string
    {
        if (!in_array($intent, ['konfirmasi_order', 'checkout', 'out_of_scope'], true)) {
            return $intent;
        }

        if (($convCtx['last_topic'] ?? '') !== 'menu' || empty($convCtx['last_menu_items'])) {
            return $intent;
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');
        $looksLikeSelectingShownMenu = preg_match(
            '/\b(oke\s+pesan|ok\s+pesan|pesan\s+ini|pesan\s+yang\s+ini|mau\s+yang\s+ini|ambil\s+ini|yang\s+ini\s+aja|yang\s+itu\s+aja|pesan\s+itu)\b/u',
            $lower
        ) === 1;

        return $looksLikeSelectingShownMenu ? 'tambah_item' : $intent;
    }

    private function preferCheckoutEditIntent(string $intent, string $message, string $state): string
    {
        if ($state !== 'awaiting_confirmation' || $intent !== 'konfirmasi_order') {
            return $intent;
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');

        $looksLikeNewOrder = preg_match(
            '/\b(mau|pesan|order|beli|minta|tambah|add)\b/u',
            $lower
        ) === 1;
        $hasQuantity = preg_match('/\b(\d+|satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\b/u', $lower) === 1;

        if ($looksLikeNewOrder && $hasQuantity) {
            return 'tambah_item';
        }

        if (preg_match('/\b(ubah|ganti|jadi|kurangi|tambahi)\b/u', $lower) === 1) {
            return 'ubah_item';
        }

        return $intent;
    }

    private function normalizePendingStateIntent(string $intent, string $message, string $state): string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        if ($state === self::VARIANT_STATE && $this->looksLikeVariantAnswer($lower)) {
            return 'tambah_item';
        }

        if ($state === self::TOPPING_STATE && $this->looksLikeToppingAnswer($lower)) {
            return 'tambah_item';
        }

        if ($state === self::REMOVE_VARIANT_STATE && $this->looksLikeVariantAnswer($lower)) {
            return 'hapus_item';
        }

        return $intent;
    }

    private function looksLikeVariantAnswer(string $lower): bool
    {
        return preg_match('/\b(small|medium|large|sm|md|lg|kecil|sedang|regular|reguler|besar)\b/u', $lower) === 1;
    }

    private function looksLikeToppingAnswer(string $lower): bool
    {
        if (preg_match('/\b(batal|cancel|tidak jadi|skip|lewati)\b/u', $lower) === 1) {
            return false;
        }

        return preg_match('/\b(topping|boba|oreo|keju|coklat|chocolate|mangga|strawberry|vanilla|matcha|caramel)\b/u', $lower) === 1;
    }

    private function looksLikeFreshAddRequest(string $message, string $state): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '') {
            return false;
        }

        if ($state === self::VARIANT_STATE && $this->looksLikeVariantAnswer($lower)) {
            return false;
        }

        if ($state === self::TOPPING_STATE && $this->looksLikeToppingAnswer($lower)) {
            return false;
        }

        return preg_match('/\b(mau|pesan|order|beli|minta|tambah|add)\b/u', $lower) === 1;
    }

    private function errorResponse(string $msg): array
    {
        return ['reply_message' => $msg, 'intent' => 'error', 'cart_state' => null, 'action_result' => null];
    }
}
