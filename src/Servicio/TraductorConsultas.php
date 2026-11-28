<?php

declare(strict_types=1);

namespace App\Servicio;

use App\Entity\Consulta;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Traduce el mensaje de las consultas al idioma del panel con LibreTranslate (servidor local,
 * LIBRETRANSLATE_URL). El idioma del mensaje se detecta solo, porque un cliente puede escribir
 * en otro idioma que el de la página. Cada traducción se guarda en la consulta y se pide una sola vez.
 * Si LibreTranslate no responde, el panel muestra el mensaje original y un aviso.
 */
final class TraductorConsultas
{
    public function __construct(
        #[Autowire(service: 'libretranslate.client')]
        private readonly HttpClientInterface $cliente,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(LIBRETRANSLATE_API_KEY)%')]
        private readonly string $claveApi = '',
    ) {
    }

    /**
     * Deja traducidas (y guardadas) las consultas al idioma dado.
     *
     * @param iterable<Consulta> $consultas
     *
     * @return bool false si LibreTranslate no respondió (alguna consulta quedó sin traducir)
     */
    public function traducir(iterable $consultas, string $idioma): bool
    {
        $pendientes = [];
        foreach ($consultas as $consulta) {
            if (null === $consulta->getTraduccion($idioma) && $consulta->getIdiomaMensaje() !== $idioma) {
                $pendientes[] = $consulta;
            }
        }
        if ([] === $pendientes) {
            return true;
        }

        $disponible = true;
        foreach ($pendientes as $consulta) {
            $resultado = $this->pedir($consulta->getMensaje(), $idioma);
            if (null === $resultado) {
                $disponible = false;
                break;  // no esperar el tiempo de espera una vez por cada consulta
            }
            [$texto, $origen] = $resultado;
            $consulta->setIdiomaMensaje($origen ?? $consulta->getIdiomaMensaje());
            if ($origen !== $idioma) {
                $consulta->setTraduccion($idioma, $texto);
            }
        }
        $this->em->flush();

        return $disponible;
    }

    /**
     * @return array{string, ?string}|null [texto traducido, idioma detectado], o null si falló
     */
    private function pedir(string $texto, string $destino): ?array
    {
        $cuerpo = ['q' => $texto, 'source' => 'auto', 'target' => $destino, 'format' => 'text'];
        if ('' !== $this->claveApi) {
            $cuerpo['api_key'] = $this->claveApi;
        }

        try {
            $datos = $this->cliente->request('POST', '/translate', ['json' => $cuerpo])->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('LibreTranslate no pudo traducir una consulta: {error}', ['error' => $e->getMessage()]);

            return null;
        }

        if (!\is_string($datos['translatedText'] ?? null)) {
            $this->logger->warning('Respuesta inesperada de LibreTranslate.', ['respuesta' => $datos]);

            return null;
        }
        $origen = $datos['detectedLanguage']['language'] ?? null;

        return [$datos['translatedText'], \is_string($origen) ? $origen : null];
    }
}
