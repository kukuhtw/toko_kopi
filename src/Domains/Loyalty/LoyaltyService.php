<?php

declare(strict_types=1);

namespace KopiBot\Domains\Loyalty;

use Throwable;

class LoyaltyService
{
    public function __construct(
        private LoyaltyRepository $repository = new LoyaltyRepository()
    ) {}

    public function earnFromOrder(int $tenantId, int $branchId, int $customerId, int $orderId, float $grandTotal): array
    {
        $point = LoyaltyRule::calculateEarnPoint($grandTotal);

        if ($point <= 0) {
            return [
                'success' => true,
                'point_earned' => 0,
                'message' => 'No loyalty point earned for this order',
            ];
        }

        try {
            $this->repository->beginTransaction();

            $transactionId = $this->repository->addTransaction(new LoyaltyTransactionDTO(
                tenantId: $tenantId,
                branchId: $branchId,
                customerId: $customerId,
                point: $point,
                transactionType: LoyaltyTransactionType::EARN,
                referenceType: 'order',
                referenceId: $orderId,
                description: 'Earn point from paid order'
            ));

            $this->repository->addBalance($tenantId, $customerId, $point);
            $balance = $this->repository->getBalance($tenantId, $customerId);

            $this->repository->commit();

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'point_earned' => $point,
                'balance' => $balance,
            ];
        } catch (Throwable $e) {
            $this->repository->rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function redeem(int $tenantId, int $branchId, int $customerId, int $point, string $referenceType = 'order', int $referenceId = 0): array
    {
        $balance = $this->repository->getBalance($tenantId, $customerId);

        if ($point <= 0) {
            return ['success' => false, 'message' => 'Invalid point amount'];
        }

        if ($balance < $point) {
            return ['success' => false, 'message' => 'Point balance is not enough'];
        }

        try {
            $this->repository->beginTransaction();

            $transactionId = $this->repository->addTransaction(new LoyaltyTransactionDTO(
                tenantId: $tenantId,
                branchId: $branchId,
                customerId: $customerId,
                point: -$point,
                transactionType: LoyaltyTransactionType::REDEEM,
                referenceType: $referenceType,
                referenceId: $referenceId,
                description: 'Redeem loyalty point'
            ));

            $this->repository->reduceBalance($tenantId, $customerId, $point);
            $newBalance = $this->repository->getBalance($tenantId, $customerId);

            $this->repository->commit();

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'point_used' => $point,
                'balance' => $newBalance,
            ];
        } catch (Throwable $e) {
            $this->repository->rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
