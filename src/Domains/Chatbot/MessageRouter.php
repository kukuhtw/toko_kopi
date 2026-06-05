<?php

declare(strict_types=1);

namespace KopiBot\Domains\Chatbot;

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
        private CartIntentParser $cartIntentParser = new CartIntentParser()
    ) {}

    public function route(string $intent, ChatMessageDTO $message): array
    {
        if ($this->cartIntentParser->isCheckoutIntent($message->message)) {
            return $this->checkoutCart($message);
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

    private function addItemToCart(ChatMessageDTO $message): array
    {
        $parsed = $this->cartIntentParser->parseAddItem($message->message);

        if (!$parsed) {
            return $this->responseBuilder->text('Format order belum saya pahami. Contoh: pesan cappuccino 2');
        }

        $search = $this->productSearchService->search($message->tenantId, $message->branchId, $parsed['product_name']);

        if (($search['total'] ?? 0) <= 0) {
            return $this->responseBuilder->text('Produk tersebut belum saya temukan di katalog. Coba cek menu atau tulis nama produk lain.', [
                'intent_result' => $search,
            ]);
        }

        $product = $search['data'][0];
        $sessionId = $message->channel . ':' . $message->senderId;

        $cart = $this->cartService->addItem(
            tenantId: $message->tenantId,
            branchId: $message->branchId,
            customerId: $message->customerId,
            sessionId: $sessionId,
            item: new CartItemDTO(
                productId: (int) $product['id'],
                productName: (string) $product['name'],
                qty: (int) $parsed['qty'],
                price: (float) $product['base_price']
            )
        );

        return $this->responseBuilder->text(sprintf(
            '%s x%d sudah saya masukkan ke keranjang. Subtotal sementara Rp %s. Ketik checkout untuk lanjut pembayaran.',
            $product['name'],
            $parsed['qty'],
            number_format((float) ($cart['subtotal'] ?? 0), 0, ',', '.')
        ), [
            'intent_result' => $cart,
        ]);
    }

    private function checkoutCart(ChatMessageDTO $message): array
    {
        if ($message->customerId === null) {
            return $this->responseBuilder->text('Untuk checkout, saya perlu customer_id terlebih dahulu. Di tahap MVP, kirim customer_id dari sistem atau login portal.');
        }

        $sessionId = $message->channel . ':' . $message->senderId;
        $checkout = $this->cartService->checkout(
            tenantId: $message->tenantId,
            branchId: $message->branchId,
            customerId: $message->customerId,
            sessionId: $sessionId,
            customerName: 'Customer',
            customerEmail: null,
            customerPhone: null
        );

        if (empty($checkout['success'])) {
            return $this->responseBuilder->text($checkout['message'] ?? 'Checkout belum berhasil.', [
                'intent_result' => $checkout,
            ]);
        }

        $checkoutUrl = $checkout['payment']['checkout_url'] ?? '';

        return $this->responseBuilder->text('Order berhasil dibuat. Silakan lanjut pembayaran melalui link berikut: ' . $checkoutUrl, [
            'intent_result' => $checkout,
        ]);
    }
}
