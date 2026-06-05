<?php

declare(strict_types=1);

namespace KopiBot\Domains\Branch;

class BranchService
{
    public function __construct(
        private BranchRepository $repository = new BranchRepository()
    ) {}

    public function getBranch(string $branchCode): ?array
    {
        return $this->repository->findByCode($branchCode);
    }
}
