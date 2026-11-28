<?php

declare(strict_types=1);

namespace App\Tests\Soporte;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sustituye a LibreTranslate en los tests (framework.http_client.mock_response_factory).
 * "Traduce" anteponiendo el idioma de destino: [es] texto. Detecta el portugués si el texto
 * contiene "Olá" y, si no, el inglés. Un mensaje con "FALLA" simula que el servidor no responde.
 */
final class LibreTranslateFalso
{
    /** @var list<array<string, mixed>> Peticiones recibidas (para comprobarlas en los tests). */
    public static array $peticiones = [];

    /** @param array<string, mixed> $opciones */
    public function __invoke(string $metodo, string $url, array $opciones): ResponseInterface
    {
        $cuerpo = json_decode((string) ($opciones['body'] ?? '{}'), true, flags: \JSON_THROW_ON_ERROR);
        self::$peticiones[] = $cuerpo;

        if (str_contains($cuerpo['q'], 'FALLA')) {
            return new MockResponse('', ['error' => 'Conexión rechazada']);
        }

        return new MockResponse(json_encode([
            'translatedText' => \sprintf('[%s] %s', $cuerpo['target'], $cuerpo['q']),
            'detectedLanguage' => ['language' => str_contains($cuerpo['q'], 'Olá') ? 'pt' : 'en', 'confidence' => 90],
        ], \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
