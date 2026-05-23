<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AthleteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AthleteRepository::class)]
#[ORM\Table(name: 'athlete')]
class Athlete implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::BIGINT)]
    private string $id;

    #[ORM\Column(length: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    private string $lastName = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profilePictureUrl = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $accessToken = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $refreshToken = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $tokenExpiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    /** @var Collection<int, Activity> */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'athlete', orphanRemoval: true)]
    private Collection $activities;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->activities = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->tokenExpiresAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getProfilePictureUrl(): ?string
    {
        return $this->profilePictureUrl;
    }

    public function setProfilePictureUrl(?string $url): self
    {
        $this->profilePictureUrl = $url;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getTokenExpiresAt(): \DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function updateTokens(string $accessToken, string $refreshToken, \DateTimeImmutable $expiresAt): self
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->tokenExpiresAt = $expiresAt;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isTokenExpired(): bool
    {
        return $this->tokenExpiresAt <= new \DateTimeImmutable('+1 minute');
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $at): self
    {
        $this->lastSyncedAt = $at;

        return $this;
    }

    /** @return Collection<int, Activity> */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // tokens stay in db; nothing to erase from memory
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->id) {
            throw new \LogicException('Athlete entity is missing its Strava ID.');
        }

        return $this->id;
    }
}
