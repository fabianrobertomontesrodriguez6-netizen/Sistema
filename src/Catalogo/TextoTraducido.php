<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * Un texto del catálogo en varios idiomas. El español ("es") es obligatorio
 * y se usa cuando falta la traducción pedida.
 */
final readonly class TextoTraducido
{
    public const IDIOMA_BASE = 'es';

    /**
     * @param array<string, string> $textos indexado por código de idioma
     */
    public function __construct(
        private array $textos,
    ) {
    }

    public static function soloEspanol(string $texto): self
    {
        return new self([self::IDIOMA_BASE => $texto]);
    }

    public function en(string $idioma): string
    {
        return $this->textos[$idioma] ?? $this->textos[self::IDIOMA_BASE] ?? '';
    }

    public function __toString(): string
    {
        return $this->en(self::IDIOMA_BASE);
    }
}
