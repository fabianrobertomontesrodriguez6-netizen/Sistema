<?php

declare(strict_types=1);

namespace App\Catalogo;

final readonly class Auto
{
    /**
     * @param list<Medio> $medios
     */
    public function __construct(
        public string $slug,
        public string $marca,
        public string $modelo,
        public int $anio,
        public int $precio,
        public int $kilometraje,
        public string $combustible,
        public string $transmision,
        public string $carroceria,
        public TextoTraducido $descripcion,
        public array $medios,
        // Motor y prestaciones: opcionales.
        public ?string $motor = null,
        public ?int $potencia = null,
        public ?int $torque = null,
        public ?string $traccion = null,
        public ?float $aceleracion = null,
        public ?int $velocidadMaxima = null,
        // Versión / generación: "xDrive40i (G05)", "STO"... ('' si no hay).
        public string $version = '',
    ) {
    }

    /**
     * Marca, modelo y versión sin el año: "BMW X5 xDrive40i (G05)".
     */
    public function titulo(): string
    {
        return trim(\sprintf('%s %s %s', $this->marca, $this->modelo, $this->version));
    }

    /**
     * Primer video (para el aviso "Video" y el anuncio en el detalle).
     */
    public function video(): ?Medio
    {
        foreach ($this->medios as $medio) {
            if (!$medio->esImagen()) {
                return $medio;
            }
        }

        return null;
    }

    public function tieneDatosMotor(): bool
    {
        return null !== $this->motor || null !== $this->potencia || null !== $this->torque
            || null !== $this->traccion || null !== $this->aceleracion || null !== $this->velocidadMaxima;
    }

    public function nombre(): string
    {
        return \sprintf('%s %s %d', $this->marca, $this->modelo, $this->anio);
    }

    /**
     * Primera imagen del auto, usada como foto principal en el listado.
     */
    public function portada(): ?Medio
    {
        foreach ($this->medios as $medio) {
            if ($medio->esImagen()) {
                return $medio;
            }
        }

        return null;
    }

    public function tieneVideo(): bool
    {
        foreach ($this->medios as $medio) {
            if (!$medio->esImagen()) {
                return true;
            }
        }

        return false;
    }
}
