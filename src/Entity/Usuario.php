<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cuenta de la web. ROLE_USER ve el catálogo; ROLE_ADMIN además el panel.
 * Los usuarios se registran solos en /{_locale}/registro; el admin se crea con
 * "php bin/console app:crear-admin".
 */
#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'usuario.email_repetido')]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROL_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Email(message: 'campo.email_invalido')]
    #[Assert\Length(max: 180, maxMessage: 'campo.muy_largo')]
    private string $email = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Length(max: 120, maxMessage: 'campo.muy_largo')]
    private string $nombre = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = trim($nombre);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function esAdmin(): bool
    {
        return \in_array(self::ROL_ADMIN, $this->getRoles(), true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }

    /**
     * Guarda en la sesión un hash de la contraseña en lugar de la contraseña hasheada.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $datos = (array) $this;
        $datos["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $datos;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
