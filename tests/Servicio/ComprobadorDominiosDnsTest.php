<?php

declare(strict_types=1);

namespace App\Tests\Servicio;

use App\Servicio\ComprobadorDominiosDns;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Usa el DNS de verdad: se salta si el equipo no tiene internet.
 * Para no ejecutarlo: php bin/phpunit --exclude-group red
 */
#[Group('red')]
final class ComprobadorDominiosDnsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!checkdnsrr('gmail.com', 'MX')) {
            self::markTestSkipped('Sin DNS (no hay internet).');
        }
    }

    public function testDominiosReales(): void
    {
        $dns = new ComprobadorDominiosDns(new NullLogger());

        self::assertTrue($dns->recibeCorreo('gmail.com'));
        self::assertTrue($dns->recibeCorreo('outlook.com'));
    }

    public function testDominiosQueNoExistenONoRecibenCorreo(): void
    {
        $dns = new ComprobadorDominiosDns(new NullLogger());

        self::assertFalse($dns->recibeCorreo('no-existe-autoplaza-'.bin2hex(random_bytes(6)).'.com'));
        self::assertFalse($dns->recibeCorreo('gmial.con'));
        self::assertFalse($dns->recibeCorreo('example.com')); // "MX nulo": no recibe correo
    }
}
