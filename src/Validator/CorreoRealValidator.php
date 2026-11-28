<?php

declare(strict_types=1);

namespace App\Validator;

use App\Servicio\ComprobadorDominios;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CorreoRealValidator extends ConstraintValidator
{
    /** Nombre de dominio con al menos un punto (como el patrón de Assert\Email). */
    private const DOMINIO = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/';

    public function __construct(private readonly ComprobadorDominios $comprobador)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CorreoReal) {
            throw new UnexpectedTypeException($constraint, CorreoReal::class);
        }
        if (null === $value || '' === $value || !\is_string($value)) {
            return;
        }

        $arroba = strrpos($value, '@');
        if (false === $arroba) {
            return; // correo mal escrito: ya lo dice Assert\Email
        }
        $dominio = mb_strtolower(trim(substr($value, $arroba + 1)));
        $ascii = idn_to_ascii($dominio, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);
        if (false === $ascii || !preg_match(self::DOMINIO, $ascii)) {
            return; // tampoco es un dominio bien escrito: ya lo dice Assert\Email
        }

        if (false === $this->comprobador->recibeCorreo($ascii)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ dominio }}', $dominio)
                ->addViolation();
        }
    }
}
