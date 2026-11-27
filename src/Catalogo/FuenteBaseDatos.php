<?php

declare(strict_types=1);

namespace App\Catalogo;

use App\Entity\Vehiculo;
use App\Repository\VehiculoRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Fuente que usa la web: los autos guardados en la base de datos (tabla vehiculo).
 */
#[AsAlias(FuenteAutos::class)]
final class FuenteBaseDatos implements FuenteAutos
{
    public function __construct(
        private readonly VehiculoRepository $vehiculos,
    ) {
    }

    public function filas(): array
    {
        $filas = [];
        foreach ($this->vehiculos->todosOrdenados() as $vehiculo) {
            $filas[$vehiculo->getSlug()] = $vehiculo->aFila();
        }

        return $filas;
    }
}
