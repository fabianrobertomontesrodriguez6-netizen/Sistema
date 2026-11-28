<?php

declare(strict_types=1);

namespace App\Servicio;

use Psr\Log\LoggerInterface;

/**
 * Comprueba el dominio en el DNS: vale si tiene registro MX o, si no tiene, una IP (A/AAAA),
 * que es a donde se entrega el correo según el RFC 5321. Un "MX nulo" (RFC 7505,
 * como el de example.com) significa que el dominio no recibe correo.
 *
 * Si el DNS no responde ni para un dominio conocido (equipo sin internet), devuelve null
 * para no bloquear todos los registros; se anota en el log.
 */
final class ComprobadorDominiosDns implements ComprobadorDominios
{
    /** Dominios que siempre existen: si tampoco se encuentran, el DNS no funciona. */
    private const REFERENCIA = ['gmail.com', 'outlook.com'];

    /** @var array<string, bool> resultados de esta petición */
    private array $cache = [];

    private ?bool $dnsFunciona = null;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function recibeCorreo(string $dominio): ?bool
    {
        if (!isset($this->cache[$dominio])) {
            $this->cache[$dominio] = $this->consultar($dominio);
        }
        if ($this->cache[$dominio]) {
            return true;
        }

        return $this->dnsFunciona() ? false : null;
    }

    private function consultar(string $dominio): bool
    {
        $mx = @dns_get_record($dominio, \DNS_MX);
        if (\is_array($mx) && [] !== $mx) {
            foreach ($mx as $registro) {
                $destino = rtrim((string) ($registro['target'] ?? ''), '.');
                if ('' !== $destino) {
                    return true;
                }
            }

            return false; // solo hay MX nulo: no recibe correo
        }

        return checkdnsrr($dominio, 'A') || checkdnsrr($dominio, 'AAAA');
    }

    private function dnsFunciona(): bool
    {
        if (null === $this->dnsFunciona) {
            $this->dnsFunciona = false;
            foreach (self::REFERENCIA as $referencia) {
                if (checkdnsrr($referencia, 'MX')) {
                    $this->dnsFunciona = true;
                    break;
                }
            }
            if (!$this->dnsFunciona) {
                $this->logger->warning('No se pudo comprobar el dominio de un correo: el DNS no responde. Se acepta sin comprobar.');
            }
        }

        return $this->dnsFunciona;
    }
}
