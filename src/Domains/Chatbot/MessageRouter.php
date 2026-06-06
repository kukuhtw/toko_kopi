<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

use KopiBot\Core\SkillRegistry;
use KopiBot\Domains\AI\ConversationContext;
use KopiBot\Domains\AI\ConversationOptionResolver;
use KopiBot\Domains\AI\ConversationStateMachine;
use KopiBot\Domains\AI\ProductRecommendationEngine;
use KopiBot\Domains\AI\ProductResolver;
use KopiBot\Domains\Cart\CartItemDTO;
use KopiBot\Domains\Cart\CartService;
use KopiBot\Domains\FAQ\FaqService;
use KopiBot\Domains\Product\ProductSearchService;
use KopiBot\Domains\Product\ProductService;
use KopiBot\Domains\Promo\PromoDTO;
use KopiBot\Domains\Promo\PromoService;

class MessageRouter
{
    public function __construct(
        private ResponseBuilder $responseBuilder = new ResponseBuilder(),
        private ProductService $productService = new ProductService(),
        private ProductSearchService $productSearchService = new ProductSearchService(),
        private FaqService $faqService = new FaqService(),
        private PromoService $promoService = new PromoService(),
        private CartService $cartService = new CartService(),
        private CartIntentParser $cartIntentParser = new CartIntentParser(),
        private ConversationStateMachine $stateMachine = new ConversationStateMachine(),
        private ConversationOptionResolver $optionResolver = new ConversationOptionResolver(),
        private ProductResolver $productResolver = new ProductResolver(),
        private ProductRecommendationEngine $recommendationEngine = new ProductRecommendationEngine()
    ) {}

    public function route(string $intent, ChatMessageDTO $message): array
    {
        $context = new ConversationContext($message->tenantId, $message->branchId, $message->channel, $message->senderId, $message->customerId);

        $selected = $this->optionResolver->resolveSelection($context, $message->message);
        if ($selected && isset($selected['product'])) {
            $product = $selected['product'];
            $this->stateMachine->waitForQty($context, (string)$product['name']);
            return $this->responseBuilder->text('Baik, Anda memilih ' . $product['name'] . '. Berapa jumlahnya?', [
                'intent_result' => $selected,
            ]);
        }

        $shortReply = $this->stateMachine->resolveShortReply($context, $message->message);
        if ($shortReply && ($shortReply['intent'] ?? '') === 'add_to_cart') {
            $this->stateMachine->clear($context);
            return $this->addResolvedItemToCart($message, (string)$shortReply['product_name'], (int)$shortReply['qty']);
        }

        if ($this->cartIntentParser->isCheckoutIntent($message->message)) {
            return $this->checkoutCart($message);
        }

        $skillResult = $this->trySkill($intent, $message, $context);
        if ($skillResult !== null) {
            return $skillResult;
        }

        return match ($intent) {
            IntentType::SHOW_MENU => $this->showMenu($message),
            IntentType::PRODUCT_SEARCH => $this->searchProduct($message),
            IntentType::ASK_FAQ => $this->answerFaq($message),
            IntentType::APPLY_PROMO => $this->applyPromo($message),
            IntentType::CREATE_ORDER => $this->addItemToCart($message),
            IntentType::CHECK_ORDER_STATUS => $this->responseBuilder->text('Silakan kirim nomor order Anda untuk saya cek statusnya.'),
            IntentType::TALK_TO_HUMAN => $this->responseBuilder->text('Baik, saya akan bantu teruskan ke admin atau CS.'),
            default => $this->responseBuilder->text('Maaf, saya belum memahami pesan Anda. Anda bisa bertanya tentang menu, promo, jam buka, atau cara order.'),
        };
    }

    private function trySkill(string $intent, ChatMessageDTO $message, ConversationContext $context): ?array
    {
        $skill = SkillRegistry::findForIntent($intent);
        if ($skill === null) {
            return null;
        }

        try {
            $result = $skill->handle([
                'intent' => $intent,
                'message' => $message->message,
                'tenant_id' => $message->tenantId,
                'branch_id' => $message->branchId,
                'channel' => $message->channel,
                'sender_id' => $message->senderId,
                'customer_id' => $message->customerId,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            error_log('[MessageRouter Skill] ' . $e->getMessage());
            return null;
        }

        $reply = trim((string)($result['reply'] ?? $result['message'] ?? ''));
        if ($reply === '') {
            return null;
        }

        return $this->responseBuilder->text($reply, [
            'intent_result' => $result,
            'skill' => get_class($skill),
        ]);
    }

    private function showMenu(ChatMessageDTO $message): array
    {
        $result = $this->productService->getMenu($message->tenantId, $message->branchId);
        $formatted = $this->recommendationEngine->formatOptions($result['data'] ?? []);

        return $this->responseBuilder->text("Berikut menu yang tersedia:\n" . $formatted, [
            'intent_result' => $result,
        ]);
    }

    private function searchProduct(ChatMessageDTO $message): array
    {
        $resolved = $this->productResolver->resolve($message->tenantId, $message->branchId, $message->message);
        $context = new ConversationContext($message->tenantId, $message->branchId, $message->channel, $message->senderId, $message->customerId);

        if ($resolved['status'] === 'resolved') {
            $product = $resolved['product'];
            $this->stateMachine->waitForQty($context, (string)$product['name']);
            return $this->responseBuilder->text('Saya menemukan ' . $product['name'] . '. Berapa jumlahnya?', [
                'intent_result' => $resolved,
            ]);
        }

        if ($resolved['status'] === 'ambiguous') {
            $options = $resolved['options'];
            $this->optionResolver->waitForProductOption($context, $options);
            return $this->responseBuilder->text("Saya menemukan beberapa produk. Pilih nomor produk:\n" . $this->recommendationEngine->formatOptions($options), [
                'intent_result' => $resolved,
            ]);
        }

        $recommendations = $this->recommendationEngine->recommendFromMenu($message->tenantId, $message->branchId, 5, $message->message);
        return $this->responseBuilder->text("Produk belum ditemukan. Rekomendasi menu:\n" . $this->recommendationEngine->formatOptions($recommendations), [
            'intent_result' => $resolved,
        ]);
    }

    private function answerFaq(ChatMessageDTO $message): array
    {
        $result = $this->faqService->answer($message->tenantId, $message->branchId, $message->message, $message->customerId);

        return $this->responseBuilder->text($result['answer'] ?? $result['message'] ?? 'Maaf, jawaban belum tersedia.', [
            'intent_result' => $result,
        ]);
    }

    private function applyPromo(ChatMessageDTO $message): array
    {
        $promoCode = strtoupper(trim(str_replace(['promo', 'voucher', 'diskon', 'kupon'], '', strtolower($message->message))));
        $promoCode = $promoCode !== '' ? $promoCode : 'HEMAT10';

        $result = $this->promoService->apply(new PromoDTO(
            tenantId: $message->tenantId,
            branchId: $message->branchId,
            promoCode: $promoCode,
            subtotal: 100000,
            customerId: $message->customerId
        ));

        return $this->responseBuilder->text($result->message, [
            'intent_result' => $result->toArray(),
        ]);
    }

    private function addItemToCart(ChatMessageDTO $message): array
    {
        $parsed = $this->cartIntentParser->parseAddItem($message->message);

        if (!$parsed) {
            return $this->searchProduct($message);
        }

        return $this->addResolvedItemToCart($message, (string)$parsed['product_name'], (int)$parsed['qty']);
    }

    private function addResolvedItemToCart(ChatMessageDTO $message, string $productName, int $qty): array
    {
        $resolved = $this->productResolver->resolve($message->tenantId, $message->branchId, $productName);
        $context = new ConversationContext($message->tenantId, $message->branchId, $message->channel, $message->senderId, $message->customerId);

        if ($resolved['status'] === 'ambiguous') {
            $this->optionResolver->waitForProductOption($context, $resolved['options']);
            return $this->responseBuilder->text("Saya menemukan beberapa produk. Pilih nomor produk:\n" . $this->recommendationEngine->formatOptions($resolved['options']), [
                'intent_result' => $resolved,
            ]);
        }

        if ($resolved['status'] !== 'resolved') {
            return $this->responseBuilder->text('Produk tersebut belum saya temukan di katalog. Coba cek menu atau tulis nama produk lain.', [
                'intent_result' => $resolved,
            ]);
        }

        $product = $resolved['product'];
        $sessionId = $message->channel . ':' . $message->senderId;

        $cart = $this->cartService->addItem(
            tenantId: $message->tenantId,
            branchId: $message->branchId,
            customerId: $message->customerId,
            sessionId: $sessionId,
            item: new CartItemDTO((int)$product['id'], (string)$product['name'], $qty, (float)$product['base_price'])
        );

        return $this->responseBuilder->text(sprintf('%s x%d sudah saya masukkan ke keranjang. Subtotal sementara Rp %s. Ketik checkout untuk lanjut pembayaran.', $product['name'], $qty, number_format((float)($cart['subtotal'] ?? 0), 0, ',', '.')), [
            'intent_result' => $cart,
        ]);
    }

    private function checkoutCart(ChatMessageDTO $message): array
    {
        if ($message->customerId === null) {
            return $this->responseBuilder->text('Untuk checkout, saya perlu customer_id terlebih dahulu. Di tahap MVP, kirim customer_id dari sistem atau login portal.');
        }

        $sessionId = $message->channel . ':' . $message->senderId;
        $checkout = $this->cartService->checkout($message->tenantId, $message->branchId, $message->customerId, $sessionId, 'Customer', null, null);

        if (empty($checkout['success'])) {
            return $this->responseBuilder->text($checkout['message'] ?? 'Checkout belum berhasil.', ['intent_result' => $checkout]);
        }

        return $this->responseBuilder->text('Order berhasil dibuat. Silakan lanjut pembayaran melalui link berikut: ' . ($checkout['payment']['checkout_url'] ?? ''), [
            'intent_result' => $checkout,
        ]);
    }
}
