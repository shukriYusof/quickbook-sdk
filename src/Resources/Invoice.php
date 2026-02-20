<?php

namespace QuickBooks\SDK\Resources;

class Invoice extends BaseResource
{
    protected function resourceName(): string
    {
        return 'invoice';
    }

    /**
     * @return array<string, mixed>
     */
    public function overdue(): array
    {
        return $this->query("SELECT * FROM Invoice WHERE Balance > '0'");
    }
}
