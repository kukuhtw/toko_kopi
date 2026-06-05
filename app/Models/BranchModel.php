<?php

declare(strict_types=1);

namespace App\Models;

use KopiBot\Domains\Branch\BranchRepository;

class BranchModel
{
    private BranchRepository $repository;

    public function __construct(?BranchRepository $repository = null)
    {
        $this->repository = $repository ?? new BranchRepository();
    }

    public function findByCode(string $branchCode): ?array
    {
        return $this->repository->findByCode($branchCode);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->repository->findBySlug($slug);
    }

    public function getActive(): array
    {
        return $this->repository->getActive();
    }

    public function getSetting(int $branchId, string $key, ?string $default = null): ?string
    {
        return $this->repository->getSetting($branchId, $key, $default);
    }

    public function setSetting(int $branchId, string $key, string $value): void
    {
        $this->repository->setSetting($branchId, $key, $value);
    }

    public function getAllSettings(int $branchId): array
    {
        return $this->repository->getAllSettings($branchId);
    }

    public function getCurrency(int $branchId): string
    {
        return $this->repository->getCurrency($branchId);
    }

    public function getLanguage(int $branchId): string
    {
        return $this->repository->getLanguage($branchId);
    }

    public function getPpnRate(int $branchId): float
    {
        return $this->repository->getPpnRate($branchId);
    }

    public function getTimezone(int $branchId): string
    {
        return $this->repository->getTimezone($branchId);
    }

    public function getBusinessType(int $branchId): string
    {
        return $this->repository->getBusinessType($branchId);
    }
}
