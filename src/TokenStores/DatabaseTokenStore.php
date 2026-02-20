<?php

namespace QuickBooks\SDK\TokenStores;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use QuickBooks\SDK\Contracts\TokenStoreInterface;

class DatabaseTokenStore implements TokenStoreInterface
{
    protected function tokenModel(): Model
    {
        $class = config('quickbooks.token_model');
        return new $class();
    }

    protected function baseQuery(): Builder
    {
        return $this->tokenModel()->newQuery();
    }

    protected function query(): Builder
    {
        return $this->baseQuery();
    }

    public function get(string $qbCompanyId): ?array
    {
        $record = $this->query()->where('qb_company_id', $qbCompanyId)->first();
        return $record ? $record->toArray() : null;
    }

    public function put(string $qbCompanyId, array $tokens): void
    {
        $this->query()->updateOrCreate(
            ['qb_company_id' => $qbCompanyId],
            [
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'access_token_expires_at' => $tokens['access_token_expires_at'] ?? null,
                'refresh_token_expires_at' => $tokens['refresh_token_expires_at'] ?? null,
            ]
        );
    }

    public function forget(string $qbCompanyId): void
    {
        $this->query()->where('qb_company_id', $qbCompanyId)->delete();
    }

    public function has(string $qbCompanyId): bool
    {
        return $this->query()->where('qb_company_id', $qbCompanyId)->exists();
    }
}
