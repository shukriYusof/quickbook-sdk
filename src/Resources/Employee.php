<?php

namespace QuickBooks\SDK\Resources;

class Employee extends BaseResource
{
    protected function resourceName(): string
    {
        return 'employee';
    }

    /**
     * @return array<string, mixed>
     */
    public function active(): array
    {
        return $this->query('SELECT * FROM Employee WHERE Active = true');
    }

    /**
     * @return array<string, mixed>
     */
    public function findByName(string $name): array
    {
        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);

        return $this->query("SELECT * FROM Employee WHERE DisplayName = '{$safe}'");
    }
}
