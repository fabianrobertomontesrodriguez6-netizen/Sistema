<?php

declare(strict_types=1);

namespace App\Validator;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TelefonoInternacionalValidator extends ConstraintValidator
{
    /** Lo único que se admite al escribir un teléfono (sin letras: libphonenumber las convertiría en cifras). */
    public const CARACTERES = '/^[0-9 +()\-.\/]*$/';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TelefonoInternacional) {
            throw new UnexpectedTypeException($constraint, TelefonoInternacional::class);
        }
        if (null === $value || '' === $value || !\is_string($value)) {
            return;
        }

        if (!preg_match(self::CARACTERES, $value)) {
            $this->context->buildViolation($constraint->caracteresMessage)->addViolation();

            return;
        }
        if (!str_starts_with(ltrim($value), '+')) {
            $this->context->buildViolation($constraint->sinPaisMessage)->addViolation();

            return;
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            $numero = $util->parse($value);
        } catch (NumberParseException) {
            $numero = null;
        }

        if (null === $numero || !$util->isValidNumber($numero)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
