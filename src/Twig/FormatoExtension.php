<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Intl\Languages;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtro "numero": escribe un número con los separadores del idioma actual
 * (48.900 en español, 48,900 en inglés, etc.).
 * Filtro "nombre_idioma": nombre de un idioma en el idioma actual ("zh" → "chino").
 */
final class FormatoExtension extends AbstractExtension
{
    /** @var array<string, \NumberFormatter> */
    private array $formateadores = [];

    public function __construct(
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('numero', $this->numero(...)),
            new TwigFilter('nombre_idioma', $this->nombreIdioma(...)),
        ];
    }

    public function numero(int|float $valor): string
    {
        $idioma = $this->localeSwitcher->getLocale();
        $this->formateadores[$idioma] ??= new \NumberFormatter($idioma, \NumberFormatter::DECIMAL);

        return (string) $this->formateadores[$idioma]->format($valor);
    }

    public function nombreIdioma(string $codigo): string
    {
        $idioma = $this->localeSwitcher->getLocale();

        return Languages::exists($codigo) ? Languages::getName($codigo, $idioma) : $codigo;
    }
}
