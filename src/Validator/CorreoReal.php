<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * El dominio del correo tiene que existir y recibir correo ("ana@gmial.con" no vale).
 * La forma del correo la sigue comprobando Assert\Email; esta solo mira el dominio.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class CorreoReal extends Constraint
{
    public string $message = 'correo.dominio_inexistente';

    #[HasNamedArguments]
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}
