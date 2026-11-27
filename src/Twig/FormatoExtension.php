<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtro "numero": escribe un número con los separadores del idioma actual
 * (48.900 en español, 48,900 en inglés, etc.).
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
        return [new TwigFilter('numero', $this->numero(...))];
    }

    public function numero(int|float $valor): string
    {
        $idioma = $this->localeSwitcher->getLocale();
        $this->formateadores[$idioma] ??= new \NumberFormatter($idioma, \NumberFormatter::DECIMAL);

        return (string) $this->formateadores[$idioma]->format($valor);
    }
}
