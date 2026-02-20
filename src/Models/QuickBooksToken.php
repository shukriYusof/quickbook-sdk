<?php

namespace QuickBooks\SDK\Models;

use Illuminate\Database\Eloquent\Model;

class QuickBooksToken extends Model
{
    protected $fillable = [
        'qb_company_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('quickbooks.token_table', parent::getTable());
    }
}
