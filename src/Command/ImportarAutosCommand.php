<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalogo\CatalogoAutos;
use App\Catalogo\CatalogoInvalidoException;
use App\Catalogo\FuenteJson;
use App\Entity\Vehiculo;
use App\Repository\VehiculoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:importar-autos',
    description: 'Carga los autos de data/autos.json en la base de datos',
)]
final class ImportarAutosCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehiculoRepository $vehiculos,
        private readonly Packages $packages,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $carpetaProyecto,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('archivo', InputArgument::OPTIONAL, 'JSON con la forma {"autos": [...]}', 'data/autos.json')
            ->addOption('reemplazar', null, InputOption::VALUE_NONE, 'Borra los autos que ya hay en la base antes de importar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $archivo = (string) $input->getArgument('archivo');
        $ruta = str_starts_with($archivo, '/') ? $archivo : $this->carpetaProyecto.'/'.$archivo;
        $fuente = new FuenteJson($ruta);

        // Se valida todo antes de tocar la base: si hay un error no se importa nada.
        $catalogo = new CatalogoAutos($fuente, $this->carpetaProyecto.'/assets/images', $this->carpetaProyecto.'/public', $this->packages);
        try {
            $catalogo->todos();
        } catch (CatalogoInvalidoException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $existentes = $this->vehiculos->count([]);
        if ($existentes > 0 && !$input->getOption('reemplazar')) {
            $io->warning(\sprintf('La base ya tiene %d autos. Usa --reemplazar para borrarlos e importar de nuevo.', $existentes));

            return Command::FAILURE;
        }

        if ($existentes > 0) {
            foreach ($this->vehiculos->findAll() as $vehiculo) {
                $this->em->remove($vehiculo);
            }
            $this->em->flush();
        }

        $filas = $fuente->filas();
        foreach ($filas as $fila) {
            $this->em->persist(Vehiculo::desdeFila($fila));
        }
        $this->em->flush();

        $io->success(\sprintf('Importados %d autos desde %s.', \count($filas), $archivo));

        return Command::SUCCESS;
    }
}
