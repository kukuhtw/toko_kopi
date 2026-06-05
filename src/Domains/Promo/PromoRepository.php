<?php

declare(strict_types=1);

namespace KopiBot\Domains\Promo;

use KopiBot\Core\Database;
use PDO;

class PromoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByCode(int $tenantId, string $promoCode): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM promos WHERE tenant_id = :tenant_id AND promo_code = :promo_code LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'promo_code' => strtoupper(trim($promoCode)),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function logRedemption(PromoDTO $dto, array $promo, float $discountAmount): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO promo_redemptions (tenant_id, branch_id, promo_id, promo_code, customer_id, order_id, discount_amount, created_at) VALUES (:tenant_id, :branch_id, :promo_id, :promo_code, :customer_id, :order_id, :discount_amount, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'promo_id' => $promo['id'],
            'promo_code' => $promo['promo_code'],
            'customer_id' => $dto->customerId,
            'order_id' => $dto->orderId,
            'discount_amount' => $discountAmount,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
