<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * Se lanza cuando data/autos.json no se puede leer o tiene datos mal escritos.
 */
final class CatalogoInvalidoException extends \RuntimeException
{
}
