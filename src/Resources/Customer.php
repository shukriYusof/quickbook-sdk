<?php

namespace QuickBooks\SDK\Resources;

class Customer extends BaseResource
{
    protected function resourceName(): string
    {
        return 'customer';
    }

    /**
     * @return array<string, mixed>
     */
    public function active(): array
    {
        return $this->query('SELECT * FROM Customer WHERE Active = true');
    }

    /**
     * @return array<string, mixed>
     */
    public function findByEmail(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address provided: [{$email}].");
        }

        $safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $email);

        return $this->query("SELECT * FROM Customer WHERE PrimaryEmailAddr = '{$safe}'");
    }
}
