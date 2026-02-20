<?php

namespace QuickBooks\SDK\Resources;

use QuickBooks\SDK\QuickBooksClient;

abstract class BaseResource
{
    public function __construct(protected QuickBooksClient $client)
    {
    }

    abstract protected function resourceName(): string;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->client->request('GET', $this->resourceName());
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $id): array
    {
        return $this->client->request('GET', $this->resourceName() . '/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->request('POST', $this->resourceName(), ['json' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        return $this->client->request('POST', $this->resourceName(), ['json' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sparseUpdate(array $payload): array
    {
        $payload['sparse'] = true;
        return $this->update($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $query): array
    {
        return $this->client->query($query);
    }
}
