<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Teléfono con el prefijo del país (+54, +34...) y un número que exista en ese país,
 * según libphonenumber ("+54 11 5555-1234" vale; "+54 123" o "5555-1234" sin país, no).
 * Se espera el número en formato E.164 ("+541155551234"), que es como lo guarda TelefonoType.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class TelefonoInternacional extends Constraint
{
    /** Hay letras u otros símbolos. */
    public string $caracteresMessage = 'telefono.caracteres_invalidos';
    /** Falta el prefijo del país. */
    public string $sinPaisMessage = 'telefono.sin_pais';
    /** El número no existe en ese país (muy corto, muy largo o con un formato imposible). */
    public string $message = 'telefono.no_valido';

    #[HasNamedArguments]
    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }
}
