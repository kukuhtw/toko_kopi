<?php
class TikTokClient {
    private array $config;
    public function __construct(array $config){$this->config=$config;}
    public function getOrders(): array { return []; }
    public function getProducts(): array { return []; }
    public function updateStock(string $productId,int $stock): bool { return true; }
    public function updatePrice(string $productId,float $price): bool { return true; }
}
