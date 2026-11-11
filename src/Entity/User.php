<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'An account with this email already exists.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public const ROLE_CUSTOMER = 'ROLE_USER';
    public const ROLE_STAFF = 'ROLE_STAFF';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    /** Label => role, in the order shown in forms. */
    public const ROLE_CHOICES = ['Customer' => self::ROLE_CUSTOMER, 'Staff' => self::ROLE_STAFF, 'Admin' => self::ROLE_ADMIN];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Please enter an email address.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Please enter a name.')]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Disabled accounts cannot log in and are signed out on their next request. */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /** The single highest role, for display and the role dropdown. */
    public function getPrimaryRole(): string
    {
        return match (true) {
            \in_array(self::ROLE_ADMIN, $this->roles, true) => self::ROLE_ADMIN,
            \in_array(self::ROLE_STAFF, $this->roles, true) => self::ROLE_STAFF,
            default => self::ROLE_CUSTOMER,
        };
    }

    public function setPrimaryRole(string $role): static
    {
        $this->roles = self::ROLE_CUSTOMER === $role ? [] : [$role];

        return $this;
    }

    /**
     * Checked on every request: a password reset, role change or disabled account ends the session.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getUserIdentifier() === $this->getUserIdentifier()
            && $user->getPassword() === $this->getPassword()
            && $user->isActive() === $this->isActive()
            && $user->getRoles() === $this->getRoles();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }
}
