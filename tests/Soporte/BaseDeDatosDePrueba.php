<?php

declare(strict_types=1);

namespace App\Tests\Soporte;

use App\Catalogo\FuenteJson;
use App\Entity\Usuario;
use App\Entity\Vehiculo;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Deja la base de pruebas (var/test.db) limpia antes de cada test:
 * los 8 autos de data/autos.json, un admin y un cliente.
 */
trait BaseDeDatosDePrueba
{
    protected const ADMIN = 'admin@autoplaza.test';
    protected const CLIENTE = 'cliente@autoplaza.test';
    protected const PASSWORD = 'clave-segura-123';

    protected function prepararBase(KernelBrowser $cliente): EntityManagerInterface
    {
        $contenedor = $cliente->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $contenedor->get(EntityManagerInterface::class);

        $herramienta = new SchemaTool($em);
        $metadatos = $em->getMetadataFactory()->getAllMetadata();
        $herramienta->dropSchema($metadatos);
        $herramienta->createSchema($metadatos);

        $fuente = new FuenteJson($contenedor->getParameter('kernel.project_dir').'/data/autos.json');
        foreach ($fuente->filas() as $fila) {
            $em->persist(Vehiculo::desdeFila($fila));
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $contenedor->get(UserPasswordHasherInterface::class);
        foreach ([[self::ADMIN, 'Ana Admin', ['ROLE_ADMIN']], [self::CLIENTE, 'Carlos Cliente', []]] as [$email, $nombre, $roles]) {
            $usuario = (new Usuario())->setEmail($email)->setNombre($nombre)->setRoles($roles);
            $usuario->setPassword($hasher->hashPassword($usuario, self::PASSWORD));
            $em->persist($usuario);
        }

        $em->flush();
        $em->clear();

        return $em;
    }

    protected function entrarComo(KernelBrowser $cliente, string $email): Usuario
    {
        /** @var EntityManagerInterface $em */
        $em = $cliente->getContainer()->get(EntityManagerInterface::class);
        $usuario = $em->getRepository(Usuario::class)->findOneBy(['email' => $email]);
        $cliente->loginUser($usuario);

        return $usuario;
    }
}
