<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

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
        private PromoService $promoService = new PromoService()
    ) {}

    public function route(string $intent, ChatMessageDTO $message): array
    {
        return match ($intent) {
            IntentType::SHOW_MENU => $this->showMenu($message),
            IntentType::PRODUCT_SEARCH => $this->searchProduct($message),
            IntentType::ASK_FAQ => $this->answerFaq($message),
            IntentType::APPLY_PROMO => $this->applyPromo($message),
            IntentType::CREATE_ORDER => $this->createOrderPlaceholder($message),
            IntentType::CHECK_ORDER_STATUS => $this->responseBuilder->text('Silakan kirim nomor order Anda untuk saya cek statusnya.'),
            IntentType::TALK_TO_HUMAN => $this->responseBuilder->text('Baik, saya akan bantu teruskan ke admin atau CS.'),
            default => $this->responseBuilder->text('Maaf, saya belum memahami pesan Anda. Anda bisa bertanya tentang menu, promo, jam buka, atau cara order.'),
        };
    }

    private function showMenu(ChatMessageDTO $message): array
    {
        $result = $this->productService->getMenu($message->tenantId, $message->branchId);

        return $this->responseBuilder->data('Berikut menu yang tersedia.', $result['data'] ?? [], [
            'intent_result' => $result,
        ]);
    }

    private function searchProduct(ChatMessageDTO $message): array
    {
        $result = $this->productSearchService->search($message->tenantId, $message->branchId, $message->message);

        if (($result['total'] ?? 0) <= 0) {
            return $this->responseBuilder->text('Saya belum menemukan produk yang cocok. Coba tulis nama produk atau kategori yang lebih spesifik.', [
                'intent_result' => $result,
            ]);
        }

        return $this->responseBuilder->data('Saya menemukan beberapa produk yang mungkin cocok.', $result['data'], [
            'intent_result' => $result,
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

    private function createOrderPlaceholder(ChatMessageDTO $message): array
    {
        return $this->responseBuilder->text('Saya bisa bantu buat order. Untuk MVP awal, kirim format: pesan nama_produk qty. Contoh: pesan cappuccino 2', [
            'note' => 'Order creation from free text will be connected after cart parser is added.',
        ]);
    }
}
