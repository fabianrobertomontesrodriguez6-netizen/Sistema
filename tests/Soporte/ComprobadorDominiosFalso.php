<?php

declare(strict_types=1);

namespace App\Tests\Soporte;

use App\Servicio\ComprobadorDominios;

/**
 * Sustituye al DNS en los tests: todo dominio existe salvo los que contienen "inexistente"
 * o terminan en ".invalid". Uno con "sindns" simula que el DNS no responde.
 */
final class ComprobadorDominiosFalso implements ComprobadorDominios
{
    public function recibeCorreo(string $dominio): ?bool
    {
        if (str_contains($dominio, 'sindns')) {
            return null;
        }

        return !str_contains($dominio, 'inexistente') && !str_ends_with($dominio, '.invalid');
    }
}
