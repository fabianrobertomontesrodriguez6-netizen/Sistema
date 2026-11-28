<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Nombre de una persona (registro, "Me interesa", admin).
 *
 * - No puede empezar con espacios ("  Fabian" no vale).
 * - Solo letras (con acentos y ñ, de cualquier idioma) separadas por un espacio,
 *   un apóstrofo o un guion: "José María", "O'Brien", "Ana-Lucía".
 *   Sin números ni caracteres especiales (@ # $ % < > ! ...).
 *
 * Ojo: el campo del formulario debe llevar 'trim' => false; si no, Symfony quita
 * los espacios antes de validar y este control nunca los ve.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class NombrePersona extends Compound
{
    /** Letras unidas por un solo espacio, apóstrofo o guion. Se toleran espacios al final (el setter los quita). */
    public const PATRON = "/^\\p{L}+(?:[ '’\\-]\\p{L}+)*\\s*$/u";

    protected function getConstraints(array $options): array
    {
        return [
            new Assert\Regex(pattern: '/^\s/u', match: false, message: 'nombre.espacio_inicial'),
            new Assert\Regex(pattern: self::PATRON, message: 'nombre.caracteres_invalidos'),
        ];
    }
}
