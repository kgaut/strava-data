<?php

declare(strict_types=1);

namespace App\OAuth;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

final class StravaResourceOwner implements ResourceOwnerInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function getId(): string
    {
        return isset($this->data['id']) ? (string) $this->data['id'] : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
