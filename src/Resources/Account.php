<?php

namespace QuickBooks\SDK\Resources;

class Account extends BaseResource
{
    /**
     * Known QBO AccountType enum values.
     * Reference: https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/account
     */
    private const VALID_TYPES = [
        'Bank',
        'Other Current Asset',
        'Fixed Asset',
        'Other Asset',
        'Accounts Receivable',
        'Equity',
        'Expense',
        'Other Expense',
        'Cost of Goods Sold',
        'Accounts Payable',
        'Credit Card',
        'Long Term Liability',
        'Other Current Liability',
        'Income',
        'Other Income',
    ];

    protected function resourceName(): string
    {
        return 'account';
    }

    /**
     * @return array<string, mixed>
     */
    public function getByType(string $type): array
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid AccountType: [{$type}]. Valid types: " . implode(', ', self::VALID_TYPES) . '.'
            );
        }

        return $this->query("SELECT * FROM Account WHERE AccountType = '{$type}'");
    }
}
