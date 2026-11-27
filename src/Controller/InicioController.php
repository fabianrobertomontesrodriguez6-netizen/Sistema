<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Página raíz "/": manda a la landing en el idioma que prefiere el navegador.
 * La ruta se declara en config/routes.yaml (sin prefijo de idioma).
 */
final class InicioController extends AbstractController
{
    /**
     * @param list<string> $idiomas
     */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $idiomas,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        // El primero de la lista (español) es el que se usa si no coincide ninguno.
        $idioma = $request->getPreferredLanguage($this->idiomas) ?? $this->idiomas[0];

        return $this->redirectToRoute('landing', ['_locale' => $idioma]);
    }
}
