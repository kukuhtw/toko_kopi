<?php

declare(strict_types=1);

namespace App\Services;

use App\Agent\CustomerAgentKernel;
use App\Agent\Memory\CustomerMemoryStore;
use App\Config\Database;
use App\Models\{AgentTaskModel, BranchModel, CartModel, ConversationModel, CustomerModel};
use App\Plugin\HookManager;

class CustomerConversationService
{
    private BranchModel $branchModel;
    private CustomerModel $customerModel;
    private CartModel $cartModel;
    private ConversationModel $convModel;
    private AgentTaskModel $agentTaskModel;
    private IntentDetectorInterface $detector;
    private ChatbotEngine $chatbotEngine;
    private CustomerAgentKernel $agentKernel;
    private array $detectorMeta = ['type' => 'rule-based', 'provider' => 'none', 'model' => ''];

    public function __construct(
        ?IntentDetectorInterface $detector = null,
        ?ChatbotEngine $chatbotEngine = null,
        ?CustomerAgentKernel $agentKernel = null
    ) {
        $this->branchModel = new BranchModel();
        $this->customerModel = new CustomerModel();
        $this->cartModel = new CartModel();
        $this->convModel = new ConversationModel();
        $this->agentTaskModel = new AgentTaskModel();
        $this->detector = $detector ?? $this->loadDetector();
        $this->chatbotEngine = $chatbotEngine ?? new ChatbotEngine($this->detector);
        $this->agentKernel = $agentKernel ?? new CustomerAgentKernel(new CustomerMemoryStore());
    }

    public function process(string $channel, int $branchId, string $customerIdentifier, string $message): array
    {
        $branch = $this->branchModel->find($branchId);
        if (!$branch || !$branch['is_active']) {
            return $this->errorResponse('Branch not found or inactive.');
        }

        $currency = $this->branchModel->getCurrency($branchId);
        $language = $this->branchModel->getLanguage($branchId);
        $timezone = $this->branchModel->getTimezone($branchId);
        $nowLocal = (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s');
        $customer = $this->customerModel->findOrCreate($channel, $customerIdentifier);

        $sessionKey = $this->buildSessionKey($channel, $branchId, $customerIdentifier);
        $conversation = $this->convModel->getOrCreate($branchId, $customer['id'], $channel, $sessionKey);
        $convId = (int)$conversation['id'];
        $convCtx = $this->convModel->getContext($convId);
        $cart = $this->cartModel->getOrCreate($sessionKey, $branchId, (int)$customer['id']);
        $cartItems = $this->cartModel->getItems((int)$cart['id']);

        if (method_exists($this->detector, 'setLoggingContext')) {
            $this->detector->setLoggingContext($branchId, $convId);
        }

        HookManager::doAction('chat.message_received', $message, $branchId, $channel);
        $message = (string)HookManager::applyFilters('chat.before_ai', $message, $branchId, $channel);

        $intent = $this->detector->detect($message, ['state' => $conversation['state']]);
        if ($intent === 'out_of_scope' && \App\Skills\SmallTalkSkill::isSmallTalk($message)) {
            $intent = 'small_talk';
        }

        HookManager::doAction('chat.intent_detected', $intent, $message, $branchId);

        $agentContext = [
            'channel' => $channel,
            'branch_id' => $branchId,
            'branch' => $branch,
            'customer' => $customer,
            'conversation' => $conversation,
            'cart' => $cart,
            'cart_items' => $cartItems,
            'intent' => $intent,
            'message' => $message,
            'language' => $language,
            'currency' => $currency,
            'branch_timezone' => $timezone,
            'now_local' => $nowLocal,
            'conv_context' => $convCtx,
            'session_key' => $sessionKey,
        ];

        $agentResult = $this->agentKernel->handle($agentContext);
        if (($agentResult['mode'] ?? 'transactional') !== 'advisory') {
            $response = $this->chatbotEngine->process($channel, $branchId, $customerIdentifier, $message);
            $this->logAgentTask($agentContext, $convId, $agentResult, $response['reply_message'] ?? '');
            return $response;
        }

        $reply = trim((string)($agentResult['reply'] ?? ''));
        if ($reply === '') {
            $response = $this->chatbotEngine->process($channel, $branchId, $customerIdentifier, $message);
            $this->logAgentTask($agentContext, $convId, $agentResult, $response['reply_message'] ?? '');
            return $response;
        }

        $this->convModel->addMessage($convId, 'customer', $message, $intent);
        $agentConvCtx = $convCtx;
        $agentConvCtx['last_topic'] = $this->mapIntentToTopic($intent);
        $agentConvCtx['agent_last_mode'] = 'advisory';
        $agentConvCtx['agent_last_tools'] = array_map(
            static fn(array $call): string => (string)($call['tool'] ?? ''),
            (array)($agentResult['tool_calls'] ?? [])
        );
        $this->convModel->updateState($convId, 'idle', $agentConvCtx);
        $this->convModel->addMessage($convId, 'bot', $reply, $intent);
        $this->logAgentTask($agentContext, $convId, $agentResult, $reply);

        return [
            'reply_message' => $reply,
            'intent' => $intent,
            'cart_state' => ['cart' => $cart, 'items' => $cartItems],
            'action_result' => [
                'agent_mode' => 'advisory',
                'tool_calls' => $agentResult['tool_calls'] ?? [],
                'handoff' => $agentResult['handoff'] ?? null,
            ],
            'conversation' => ['id' => $convId, 'state' => 'idle'],
            'detector' => $this->detectorMeta,
        ];
    }

    private function loadDetector(): IntentDetectorInterface
    {
        $db = Database::getInstance();
        $rows = $db->query(
            'SELECT setting_key, setting_val FROM app_settings
             WHERE setting_key IN ("llm_provider","llm_api_key","llm_model")'
        )->fetchAll();
        $cfg = array_column($rows, 'setting_val', 'setting_key');

        $provider = $cfg['llm_provider'] ?? 'none';
        $apiKey = $cfg['llm_api_key'] ?? '';
        $model = $cfg['llm_model'] ?? '';

        $customProviders = (array)HookManager::applyFilters('llm.providers', [], $provider, $model, $apiKey);
        if ($provider !== 'none' && isset($customProviders[$provider]) && $customProviders[$provider] instanceof IntentDetectorInterface) {
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

    private function mapIntentToTopic(string $intent): string
    {
        return match ($intent) {
            'lihat_cart' => 'cart',
            'tanya_promo' => 'promo',
            'tanya_menu', 'tanya_harga' => 'menu',
            'checkout', 'konfirmasi_order' => 'checkout',
            default => 'general',
        };
    }

    private function errorResponse(string $msg): array
    {
        return ['reply_message' => $msg, 'intent' => 'error', 'cart_state' => null, 'action_result' => null];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $agentResult
     */
    private function logAgentTask(array $context, int $conversationId, array $agentResult, string $reply): void
    {
        if (!$this->agentTaskModel->isAvailable()) {
            return;
        }

        $message = trim((string)($context['message'] ?? ''));
        $goal = $message !== '' ? mb_substr($message, 0, 255, 'UTF-8') : 'Customer conversation';
        $mode = (string)($agentResult['mode'] ?? 'transactional');
        $handoff = (string)($agentResult['handoff'] ?? '');
        if ($handoff !== '' && $mode !== 'advisory') {
            $mode = 'transactional';
        }

        $summary = trim($reply);
        if ($summary === '') {
            $summary = $mode === 'advisory'
                ? 'Agent advisory executed without visible reply summary.'
                : 'Conversation routed to transactional chatbot engine.';
        }

        $taskId = $this->agentTaskModel->createTask([
            'scope' => 'customer',
            'entity_key' => $this->buildSessionKey(
                (string)($context['channel'] ?? 'web'),
                (int)($context['branch_id'] ?? 0),
                (string)($context['customer']['identifier'] ?? '')
            ),
            'channel' => $context['channel'] ?? null,
            'branch_id' => $context['branch_id'] ?? null,
            'conversation_id' => $conversationId,
            'intent' => $context['intent'] ?? null,
            'mode' => in_array($mode, ['advisory', 'transactional', 'handoff'], true) ? $mode : 'advisory',
            'status' => 'completed',
            'goal' => $goal,
            'summary' => mb_substr($summary, 0, 4000, 'UTF-8'),
        ]);

        if (!$taskId) {
            return;
        }

        $toolCalls = (array)($agentResult['tool_calls'] ?? []);
        if (empty($toolCalls)) {
            $this->agentTaskModel->addStep([
                'task_id' => $taskId,
                'step_index' => 0,
                'step_type' => 'routing',
                'tool_name' => $handoff !== '' ? $handoff : 'chatbot_engine',
                'input' => ['intent' => $context['intent'] ?? null],
                'output' => ['mode' => $mode, 'reply_preview' => mb_substr($summary, 0, 300, 'UTF-8')],
                'status' => 'completed',
            ]);
            return;
        }

        foreach (array_values($toolCalls) as $index => $call) {
            $this->agentTaskModel->addStep([
                'task_id' => $taskId,
                'step_index' => $index + 1,
                'step_type' => 'tool_call',
                'tool_name' => $call['tool'] ?? null,
                'input' => $call['input'] ?? [],
                'output' => $call['output'] ?? [],
                'status' => 'completed',
            ]);
        }
    }
}
