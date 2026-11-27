<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:crear-admin',
    description: 'Crea el usuario administrador (o da permisos de admin a uno que ya existe)',
)]
final class CrearAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UsuarioRepository $usuarios,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Correo con el que entrará el admin')
            ->addArgument('nombre', InputArgument::OPTIONAL, 'Nombre que se muestra', 'Administrador');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $usuario = $this->usuarios->buscarPorEmail($email);
        $nuevo = null === $usuario;
        $usuario ??= (new Usuario())->setEmail($email)->setNombre((string) $input->getArgument('nombre'));

        // La contraseña se pide oculta para que no quede en el historial de la terminal.
        $password = $io->askHidden($nuevo ? 'Contraseña (mínimo 8 caracteres)' : 'Nueva contraseña (vacío = no cambiarla)');
        $password = (string) $password;

        if ($nuevo || '' !== $password) {
            if (mb_strlen($password) < 8) {
                $io->error('La contraseña debe tener al menos 8 caracteres.');

                return Command::FAILURE;
            }
            $usuario->setPassword($this->hasher->hashPassword($usuario, $password));
        }

        $usuario->setRoles([Usuario::ROL_ADMIN]);

        $errores = $this->validator->validate($usuario);
        if (\count($errores) > 0) {
            foreach ($errores as $error) {
                $io->error($error->getPropertyPath().': '.$error->getMessage());
            }

            return Command::FAILURE;
        }

        $this->em->persist($usuario);
        $this->em->flush();

        $io->success(\sprintf('%s es administrador. Ya puede entrar desde la landing.', $usuario->getEmail()));

        return Command::SUCCESS;
    }
}
