<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * Valores que ofrece el panel para combustible, transmisión, carrocería y tracción.
 * Se guardan en español y se traducen con translations/valores.*.yaml:
 * si se agrega uno aquí, añadirlo también en los 5 archivos.
 */
final class Valores
{
    public const COMBUSTIBLES = ['Gasolina', 'Diésel', 'Eléctrico', 'Híbrido'];
    public const TRANSMISIONES = ['Manual', 'Automática'];
    public const CARROCERIAS = ['Coupé', 'Sedán', 'SUV', 'Hatchback', 'Convertible', 'Pickup'];
    public const TRACCIONES = ['Trasera', 'Delantera', 'Integral', '4x4'];
}
