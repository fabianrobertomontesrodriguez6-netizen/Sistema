<?php

declare(strict_types=1);

namespace App\Catalogo;

enum TipoMedio: string
{
    case Imagen = 'imagen';
    case Video = 'video';
    case YouTube = 'youtube';
}
