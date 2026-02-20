<?php

namespace QuickBooks\SDK\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property string      $qb_company_id
 * @property string|null $tenant_id
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $display_name
 * @property string|null $qb_realm_id
 * @property string|null $environment
 * @property bool        $is_active
 * @property \Carbon\Carbon|null $connected_at
 * @property \Carbon\Carbon|null $disconnected_at
 */
class QuickBooksCompany extends Model
{
    protected $fillable = [
        'qb_company_id',
        'tenant_id',
        'source_type',
        'source_id',
        'display_name',
        'qb_realm_id',
        'environment',
        'is_active',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('quickbooks.company_table', parent::getTable());
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    public static function registerSource(Model $source, ?string $tenantId = null, ?string $displayNameOrColumn = null): self
    {
        $attributes = [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'tenant_id' => $tenantId,
        ];

        $existing = static::query()->where($attributes)->first();
        if ($existing) {
            return $existing;
        }

        $record = new self();
        $record->fill($attributes);
        $record->qb_company_id = (string) Str::uuid();
        $record->display_name = self::resolveDisplayName($source, $displayNameOrColumn);
        $record->environment = config('quickbooks.environment', 'production');
        $record->is_active = true;
        $record->save();

        return $record;
    }

    public static function findBySource(Model $source, ?string $tenantId = null): ?self
    {
        return static::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    public static function findByQbCompanyId(string $qbCompanyId, ?string $tenantId = null): ?self
    {
        return static::query()
            ->where('qb_company_id', $qbCompanyId)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    private static function resolveDisplayName(Model $source, ?string $displayNameOrColumn): ?string
    {
        if ($displayNameOrColumn === null) {
            $labelColumn = config('quickbooks.company_model.label_column', 'name');
            $attributes = $source->getAttributes();
            if (array_key_exists($labelColumn, $attributes)) {
                return (string) $source->getAttribute($labelColumn);
            }
            return null;
        }

        $attributes = $source->getAttributes();
        if (array_key_exists($displayNameOrColumn, $attributes)) {
            return (string) $source->getAttribute($displayNameOrColumn);
        }

        return $displayNameOrColumn;
    }
}
