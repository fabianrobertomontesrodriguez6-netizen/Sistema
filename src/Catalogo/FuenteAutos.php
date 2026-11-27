<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * De dónde salen los autos: la base de datos (la web) o un JSON (importación y tests).
 */
interface FuenteAutos
{
    /**
     * Filas con la forma de data/autos.json. La clave se usa en los mensajes de error
     * ("autos[3]: ..." o "autos[ford-mustang-gt-2022]: ...").
     *
     * @return array<int|string, mixed>
     */
    public function filas(): array;
}
