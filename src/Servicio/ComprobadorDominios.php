<?php

declare(strict_types=1);

namespace App\Servicio;

/**
 * Dice si un dominio de correo existe y puede recibir correo.
 * En la web se usa ComprobadorDominiosDns; en los tests, uno falso (sin red).
 */
interface ComprobadorDominios
{
    /**
     * @param string $dominio en minúsculas y en ASCII (punycode si tenía acentos)
     *
     * @return bool|null true: existe y recibe correo; false: no existe o no recibe correo;
     *                   null: no se pudo comprobar (el DNS no responde)
     */
    public function recibeCorreo(string $dominio): ?bool;
}
