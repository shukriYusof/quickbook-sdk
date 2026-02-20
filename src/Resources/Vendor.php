<?php

namespace QuickBooks\SDK\Resources;

class Vendor extends BaseResource
{
    protected function resourceName(): string
    {
        return 'vendor';
    }

    /**
     * @return array<string, mixed>
     */
    public function active(): array
    {
        return $this->query('SELECT * FROM Vendor WHERE Active = true');
    }

    /**
     * @return array<string, mixed>
     */
    public function findByName(string $name): array
    {
        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);

        return $this->query("SELECT * FROM Vendor WHERE DisplayName = '{$safe}'");
    }
}
