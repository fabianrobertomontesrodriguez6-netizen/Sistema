<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VehiculoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un auto tal como está guardado en la base de datos (datos "en bruto").
 * App\Catalogo\CatalogoAutos lo valida y lo convierte en App\Catalogo\Auto para mostrarlo.
 *
 * "descripcion" es {idioma: texto}; "medios" es una lista de {tipo, src, alt: {idioma: texto}},
 * con la misma forma que en data/autos.json.
 */
#[ORM\Entity(repositoryClass: VehiculoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'vehiculo.slug_repetido')]
class Vehiculo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'vehiculo.slug_invalido')]
    #[Assert\Length(max: 120, maxMessage: 'campo.muy_largo')]
    private string $slug = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Length(max: 80, maxMessage: 'campo.muy_largo')]
    private string $marca = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    #[Assert\Length(max: 80, maxMessage: 'campo.muy_largo')]
    private string $modelo = '';

    #[ORM\Column]
    #[Assert\NotNull(message: 'campo.obligatorio')]
    #[Assert\Range(min: 1900, max: 2100, notInRangeMessage: 'vehiculo.anio_invalido')]
    private ?int $anio = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'campo.obligatorio')]
    #[Assert\PositiveOrZero(message: 'campo.numero_positivo')]
    private ?int $precio = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'campo.obligatorio')]
    #[Assert\PositiveOrZero(message: 'campo.numero_positivo')]
    private ?int $kilometraje = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    private string $combustible = '';

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    private string $transmision = '';

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank(message: 'campo.obligatorio')]
    private string $carroceria = '';

    // ---- Motor y prestaciones (opcionales) ----

    /** Texto técnico, igual en todos los idiomas: "5.0 L V8", "3.0 L I6 Biturbo"… */
    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80, maxMessage: 'campo.muy_largo')]
    private ?string $motor = null;

    /** Caballos de fuerza (hp). */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'campo.numero_positivo')]
    private ?int $potencia = null;

    /** Par motor en Nm. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'campo.numero_positivo')]
    private ?int $torque = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $traccion = null;

    /** Segundos de 0 a 100 km/h. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'campo.numero_positivo')]
    private ?float $aceleracion = null;

    /** km/h. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'campo.numero_positivo')]
    private ?int $velocidadMaxima = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $descripcion = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: Types::JSON)]
    private array $medios = [];

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    #[ORM\Column]
    private \DateTimeImmutable $actualizadoEn;

    public function __construct()
    {
        $this->creadoEn = $this->actualizadoEn = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function marcarActualizado(): void
    {
        $this->actualizadoEn = new \DateTimeImmutable();
    }

    /**
     * Crea un vehículo desde una fila con la forma de data/autos.json.
     *
     * @param array<string, mixed> $fila
     */
    public static function desdeFila(array $fila): self
    {
        $vehiculo = new self();
        $vehiculo->slug = (string) $fila['slug'];
        $vehiculo->marca = (string) $fila['marca'];
        $vehiculo->modelo = (string) $fila['modelo'];
        $vehiculo->anio = (int) $fila['anio'];
        $vehiculo->precio = (int) $fila['precio'];
        $vehiculo->kilometraje = (int) $fila['kilometraje'];
        $vehiculo->combustible = (string) $fila['combustible'];
        $vehiculo->transmision = (string) $fila['transmision'];
        $vehiculo->carroceria = (string) $fila['carroceria'];
        $vehiculo->motor = isset($fila['motor']) ? (string) $fila['motor'] : null;
        $vehiculo->potencia = isset($fila['potencia']) ? (int) $fila['potencia'] : null;
        $vehiculo->torque = isset($fila['torque']) ? (int) $fila['torque'] : null;
        $vehiculo->traccion = isset($fila['traccion']) ? (string) $fila['traccion'] : null;
        $vehiculo->aceleracion = isset($fila['aceleracion']) ? (float) $fila['aceleracion'] : null;
        $vehiculo->velocidadMaxima = isset($fila['velocidad_maxima']) ? (int) $fila['velocidad_maxima'] : null;
        $vehiculo->descripcion = \is_string($fila['descripcion']) ? ['es' => $fila['descripcion']] : (array) $fila['descripcion'];
        $vehiculo->medios = array_values((array) ($fila['medios'] ?? []));

        return $vehiculo;
    }

    /**
     * Devuelve el vehículo con la forma de data/autos.json (la que lee CatalogoAutos).
     *
     * @return array<string, mixed>
     */
    public function aFila(): array
    {
        return array_filter([
            'slug' => $this->slug,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'anio' => $this->anio,
            'precio' => $this->precio,
            'kilometraje' => $this->kilometraje,
            'combustible' => $this->combustible,
            'transmision' => $this->transmision,
            'carroceria' => $this->carroceria,
            'motor' => $this->motor,
            'potencia' => $this->potencia,
            'torque' => $this->torque,
            'traccion' => $this->traccion,
            'aceleracion' => $this->aceleracion,
            'velocidad_maxima' => $this->velocidadMaxima,
            'descripcion' => $this->descripcion,
            'medios' => $this->medios,
        ], static fn (mixed $valor): bool => null !== $valor);
    }

    public function getId(): ?int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(?string $slug): static { $this->slug = trim((string) $slug); return $this; }

    public function getMarca(): string { return $this->marca; }
    public function setMarca(?string $marca): static { $this->marca = trim((string) $marca); return $this; }

    public function getModelo(): string { return $this->modelo; }
    public function setModelo(?string $modelo): static { $this->modelo = trim((string) $modelo); return $this; }

    public function getNombre(): string { return trim(\sprintf('%s %s %s', $this->marca, $this->modelo, $this->anio ?? '')); }

    public function getAnio(): ?int { return $this->anio; }
    public function setAnio(?int $anio): static { $this->anio = $anio; return $this; }

    public function getPrecio(): ?int { return $this->precio; }
    public function setPrecio(?int $precio): static { $this->precio = $precio; return $this; }

    public function getKilometraje(): ?int { return $this->kilometraje; }
    public function setKilometraje(?int $kilometraje): static { $this->kilometraje = $kilometraje; return $this; }

    public function getCombustible(): string { return $this->combustible; }
    public function setCombustible(?string $combustible): static { $this->combustible = (string) $combustible; return $this; }

    public function getTransmision(): string { return $this->transmision; }
    public function setTransmision(?string $transmision): static { $this->transmision = (string) $transmision; return $this; }

    public function getCarroceria(): string { return $this->carroceria; }
    public function setCarroceria(?string $carroceria): static { $this->carroceria = (string) $carroceria; return $this; }

    public function getMotor(): ?string { return $this->motor; }
    public function setMotor(?string $motor): static { $this->motor = '' === trim((string) $motor) ? null : trim((string) $motor); return $this; }

    public function getPotencia(): ?int { return $this->potencia; }
    public function setPotencia(?int $potencia): static { $this->potencia = $potencia; return $this; }

    public function getTorque(): ?int { return $this->torque; }
    public function setTorque(?int $torque): static { $this->torque = $torque; return $this; }

    public function getTraccion(): ?string { return $this->traccion; }
    public function setTraccion(?string $traccion): static { $this->traccion = '' === (string) $traccion ? null : $traccion; return $this; }

    public function getAceleracion(): ?float { return $this->aceleracion; }
    public function setAceleracion(?float $aceleracion): static { $this->aceleracion = $aceleracion; return $this; }

    public function getVelocidadMaxima(): ?int { return $this->velocidadMaxima; }
    public function setVelocidadMaxima(?int $velocidadMaxima): static { $this->velocidadMaxima = $velocidadMaxima; return $this; }

    /** @return array<string, string> */
    public function getDescripcion(): array { return $this->descripcion; }

    /** @param array<string, ?string> $descripcion */
    public function setDescripcion(array $descripcion): static
    {
        $this->descripcion = self::limpiarTraducciones($descripcion);

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getMedios(): array { return $this->medios; }

    /** @param list<array<string, mixed>> $medios */
    public function setMedios(array $medios): static
    {
        $this->medios = array_values($medios);

        return $this;
    }

    public function getCreadoEn(): \DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): \DateTimeImmutable { return $this->actualizadoEn; }

    /**
     * Quita los idiomas vacíos de un {idioma: texto}.
     *
     * @param array<string, ?string> $textos
     *
     * @return array<string, string>
     */
    public static function limpiarTraducciones(array $textos): array
    {
        $limpios = [];
        foreach ($textos as $idioma => $texto) {
            if ('' !== trim((string) $texto)) {
                $limpios[$idioma] = trim((string) $texto);
            }
        }

        return $limpios;
    }
}
