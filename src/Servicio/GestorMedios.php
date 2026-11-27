<?php

declare(strict_types=1);

namespace App\Servicio;

use App\Catalogo\CatalogoAutos;
use App\Entity\Vehiculo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Prepara los medios que llegan del panel antes de guardarlos:
 * mueve los archivos subidos a public/uploads/autos/ y deja solo {tipo, src, alt}.
 */
final class GestorMedios
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $carpetaPublica,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $medios tal como los deja el formulario
     *
     * @return list<array{tipo: string, src: string, alt?: array<string, string>}>
     */
    public function procesar(array $medios, string $slug): array
    {
        $limpios = [];
        foreach ($medios as $medio) {
            $tipo = (string) ($medio['tipo'] ?? 'imagen');
            $src = trim((string) ($medio['src'] ?? ''));

            $archivo = $medio['archivo'] ?? null;
            if ($archivo instanceof UploadedFile) {
                [$tipo, $src] = $this->guardar($archivo, $slug);
            }

            if ('' === $src) {
                continue;
            }

            $limpio = ['tipo' => $tipo, 'src' => $src];
            $credito = trim((string) ($medio['credito'] ?? ''));
            if ('' !== $credito) {
                $limpio['credito'] = $credito;
            }
            $alt = Vehiculo::limpiarTraducciones((array) ($medio['alt'] ?? []));
            if ([] !== $alt) {
                $limpio['alt'] = $alt;
            }
            $limpios[] = $limpio;
        }

        return $limpios;
    }

    /**
     * @return array{string, string} [tipo, src]
     */
    private function guardar(UploadedFile $archivo, string $slug): array
    {
        $extension = strtolower($archivo->guessExtension() ?? $archivo->getClientOriginalExtension());
        $tipo = \in_array($extension, CatalogoAutos::EXTENSIONES_VIDEO, true) ? 'video' : 'imagen';

        // Nombre propio (no el del usuario) para evitar rutas raras y choques.
        $nombre = \sprintf('%s-%s.%s', $slug ?: 'auto', bin2hex(random_bytes(4)), $extension);
        $archivo->move($this->carpetaPublica.CatalogoAutos::CARPETA_SUBIDAS, $nombre);

        return [$tipo, CatalogoAutos::CARPETA_SUBIDAS.$nombre];
    }
}
