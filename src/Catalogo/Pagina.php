<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * Una página de resultados del listado.
 *
 * @template T
 */
final readonly class Pagina
{
    /**
     * @param list<T> $elementos los de esta página
     * @param int     $total     resultados en todas las páginas
     */
    public function __construct(
        public array $elementos,
        public int $numero,
        public int $porPagina,
        public int $total,
    ) {
    }

    /**
     * @template E
     *
     * @param list<E> $todos
     *
     * @return self<E>
     */
    public static function de(array $todos, int $numero, int $porPagina): self
    {
        $paginas = max(1, (int) ceil(\count($todos) / $porPagina));
        $numero = min(max(1, $numero), $paginas);

        return new self(\array_slice($todos, ($numero - 1) * $porPagina, $porPagina), $numero, $porPagina, \count($todos));
    }

    public function paginas(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }

    public function desde(): int
    {
        return 0 === $this->total ? 0 : ($this->numero - 1) * $this->porPagina + 1;
    }

    public function hasta(): int
    {
        return min($this->numero * $this->porPagina, $this->total);
    }

    /**
     * Números a mostrar en la paginación, con null donde van "…": [1, null, 4, 5, 6, null, 12].
     *
     * @return list<int|null>
     */
    public function enlaces(): array
    {
        $paginas = $this->paginas();
        $mostrar = array_unique([1, $paginas, ...range(max(1, $this->numero - 1), min($paginas, $this->numero + 1))]);
        sort($mostrar);

        $enlaces = [];
        $anterior = 0;
        foreach ($mostrar as $numero) {
            if ($numero - $anterior > 1) {
                $enlaces[] = null;
            }
            $enlaces[] = $numero;
            $anterior = $numero;
        }

        return $enlaces;
    }
}
