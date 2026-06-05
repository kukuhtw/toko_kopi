<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use KopiBot\Core\Request;
use KopiBot\Core\Router;
use KopiBot\Domains\Cart\CartItemDTO;
use KopiBot\Domains\Cart\CartService;
use KopiBot\Domains\Chatbot\ChatbotService;
use KopiBot\Domains\Chatbot\ChatMessageDTO;
use KopiBot\Domains\Product\ProductSearchService;
use KopiBot\Domains\Product\ProductService;

$router = new Router();

$router->get('/api/health', function (): array {
    return [
        'success' => true,
        'service' => 'KopiBot API',
        'status' => 'ok',
    ];
});

$router->get('/api/products', function (Request $request): array {
    $tenantId = (int) $request->input('tenant_id', 1);
    $branchId = (int) $request->input('branch_id', 1);
    $q = (string) $request->input('q', '');

    if ($q !== '') {
        return (new ProductSearchService())->search($tenantId, $branchId, $q);
    }

    return (new ProductService())->getMenu($tenantId, $branchId);
});

$router->post('/api/cart/add', function (Request $request): array {
    return (new CartService())->addItem(
        tenantId: (int) $request->input('tenant_id', 1),
        branchId: (int) $request->input('branch_id', 1),
        customerId: $request->input('customer_id') !== null ? (int) $request->input('customer_id') : null,
        sessionId: (string) $request->input('session_id', 'api-session'),
        item: new CartItemDTO(
            productId: (int) $request->input('product_id', 1),
            productName: (string) $request->input('product_name', 'Cappuccino'),
            qty: (int) $request->input('qty', 1),
            price: (float) $request->input('price', 25000)
        )
    );
});

$router->post('/api/cart/checkout', function (Request $request): array {
    return (new CartService())->checkout(
        tenantId: (int) $request->input('tenant_id', 1),
        branchId: (int) $request->input('branch_id', 1),
        customerId: (int) $request->input('customer_id', 1),
        sessionId: (string) $request->input('session_id', 'api-session'),
        customerName: (string) $request->input('customer_name', 'Customer'),
        customerEmail: $request->input('customer_email'),
        customerPhone: $request->input('customer_phone')
    );
});

$router->post('/api/chatbot/message', function (Request $request): array {
    return (new ChatbotService())->process(new ChatMessageDTO(
        tenantId: (int) $request->input('tenant_id', 1),
        branchId: (int) $request->input('branch_id', 1),
        channel: (string) $request->input('channel', 'api'),
        senderId: (string) $request->input('sender_id', 'guest'),
        message: (string) $request->input('message', ''),
        customerId: $request->input('customer_id') !== null ? (int) $request->input('customer_id') : null
    ));
});

$router->dispatch(new Request());
