<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Consulta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consulta>
 */
class ConsultaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consulta::class);
    }

    /**
     * @return list<Consulta>
     */
    public function recientes(?int $limite = null): array
    {
        return $this->findBy([], ['leida' => 'ASC', 'creadaEn' => 'DESC'], $limite);
    }

    public function contarSinLeer(): int
    {
        return $this->count(['leida' => false]);
    }
}
