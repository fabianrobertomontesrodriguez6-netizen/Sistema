<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConsultaRepository;
use Doctrine\DBAL\Types\Types;
use App\Validator\CorreoReal;
use App\Validator\TelefonoInternacional;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Consulta "Me interesa" que un cliente envía desde el detalle de un auto.
 * Se ve en el panel de administración y, si hay correo configurado, también se envía por mail.
 */
#[ORM\Entity(repositoryClass: ConsultaRepository::class)]
class Consulta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Si se borra el auto, la consulta se conserva con su nombre en "autoNombre". */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Vehiculo $vehiculo = null;

    #[ORM\Column(length: 200)]
    private string $autoNombre = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Usuario $usuario = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Length(max: 120, maxMessage: 'campo.muy_largo')]
    private string $nombre = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Email(message: 'campo.email_invalido')]
    #[CorreoReal]
    #[Assert\Length(max: 180, maxMessage: 'campo.muy_largo')]
    private string $email = '';

    /** Formato E.164: "+541155551234" (prefijo del país + número). */
    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40, maxMessage: 'campo.muy_largo')]
    #[TelefonoInternacional]
    private ?string $telefono = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Length(max: 2000, maxMessage: 'campo.muy_largo')]
    private string $mensaje = '';

    #[ORM\Column(length: 5)]
    private string $idioma = 'es';

    /** Idioma en que está escrito el mensaje, según LibreTranslate (puede no coincidir con "idioma", el de la página). */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $idiomaMensaje = null;

    /**
     * Traducciones automáticas del mensaje, guardadas para no pedirlas otra vez: {idioma: texto}.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $traducciones = [];

    #[ORM\Column]
    private bool $leida = false;

    #[ORM\Column]
    private \DateTimeImmutable $creadaEn;

    public function __construct()
    {
        $this->creadaEn = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getVehiculo(): ?Vehiculo { return $this->vehiculo; }
    public function setVehiculo(?Vehiculo $vehiculo): static
    {
        $this->vehiculo = $vehiculo;
        if (null !== $vehiculo) {
            $this->autoNombre = $vehiculo->getNombre();
        }

        return $this;
    }

    public function getAutoNombre(): string { return $this->autoNombre; }

    public function getUsuario(): ?Usuario { return $this->usuario; }
    public function setUsuario(?Usuario $usuario): static { $this->usuario = $usuario; return $this; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(?string $nombre): static { $this->nombre = trim((string) $nombre); return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = trim((string) $email); return $this; }

    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $telefono): static { $this->telefono = '' === trim((string) $telefono) ? null : trim((string) $telefono); return $this; }

    public function getMensaje(): string { return $this->mensaje; }
    public function setMensaje(?string $mensaje): static
    {
        $mensaje = trim((string) $mensaje);
        if ($mensaje !== $this->mensaje) {  // las traducciones guardadas ya no valen
            $this->traducciones = [];
            $this->idiomaMensaje = null;
        }
        $this->mensaje = $mensaje;

        return $this;
    }

    public function getIdiomaMensaje(): ?string { return $this->idiomaMensaje; }
    public function setIdiomaMensaje(?string $idioma): static { $this->idiomaMensaje = $idioma; return $this; }

    /** Traducción guardada del mensaje al idioma dado, o null si no hay (o si el mensaje ya está en ese idioma). */
    public function getTraduccion(string $idioma): ?string { return $this->traducciones[$idioma] ?? null; }
    public function setTraduccion(string $idioma, string $texto): static { $this->traducciones[$idioma] = $texto; return $this; }

    public function getIdioma(): string { return $this->idioma; }
    public function setIdioma(string $idioma): static { $this->idioma = $idioma; return $this; }

    public function isLeida(): bool { return $this->leida; }
    public function setLeida(bool $leida): static { $this->leida = $leida; return $this; }

    public function getCreadaEn(): \DateTimeImmutable { return $this->creadaEn; }
}
