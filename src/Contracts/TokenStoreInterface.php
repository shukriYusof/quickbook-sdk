<?php

namespace QuickBooks\SDK\Contracts;

interface TokenStoreInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $qbCompanyId): ?array;

    /**
     * @param array<string, mixed> $tokens
     */
    public function put(string $qbCompanyId, array $tokens): void;

    public function forget(string $qbCompanyId): void;

    public function has(string $qbCompanyId): bool;
}
