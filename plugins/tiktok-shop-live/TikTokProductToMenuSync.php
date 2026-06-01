<?php

declare(strict_types=1);

final class TikTokProductToMenuSync
{
    public function __construct(private TikTokProductMapper $mapper) {}

    public function mapProducts(array $products): array
    {
        $result = [];
        foreach ($products as $product) {
            if (is_array($product)) {
                $result[] = $this->mapper->mapMenuItem($product);
            }
        }
        return $result;
    }
}
