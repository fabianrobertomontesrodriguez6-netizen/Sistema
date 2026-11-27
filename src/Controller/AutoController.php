<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalogo\CatalogoAutos;
use App\Catalogo\Filtros;
use App\Entity\Consulta;
use App\Entity\Usuario;
use App\Form\ConsultaType;
use App\Repository\VehiculoRepository;
use App\Servicio\NotificadorConsultas;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Catálogo: solo para usuarios que han entrado (normales o admin).
 */
#[IsGranted('ROLE_USER')]
final class AutoController extends AbstractController
{
    public function __construct(
        private readonly CatalogoAutos $catalogo,
    ) {
    }

    /**
     * "Busca tu modelo": todos los autos con búsqueda, filtros, orden y paginación.
     */
    #[Route('/autos', name: 'auto_listado', methods: ['GET'])]
    public function listado(Request $request): Response
    {
        $q = $request->query;
        $rangos = $this->catalogo->rangos();

        // Solo se aceptan valores que existen en el catálogo; cualquier otro se ignora.
        $filtros = new Filtros(
            texto: '' === ($texto = mb_substr(trim($q->getString('q')), 0, 100)) ? null : $texto,
            marca: $this->opcionValida($q->getString('marca'), $this->catalogo->marcas()),
            carroceria: $this->opcionValida($q->getString('carroceria'), $this->catalogo->carrocerias()),
            combustible: $this->opcionValida($q->getString('combustible'), $this->catalogo->valores(static fn ($auto) => $auto->combustible)),
            transmision: $this->opcionValida($q->getString('transmision'), $this->catalogo->valores(static fn ($auto) => $auto->transmision)),
            precioMin: $this->numero($q->getString('precio_min')),
            precioMax: $this->numero($q->getString('precio_max')),
            anioMin: $this->numero($q->getString('anio_min')),
            anioMax: $this->numero($q->getString('anio_max')),
            orden: $this->opcionValida($q->getString('orden'), array_keys(CatalogoAutos::ORDENES)) ?? 'recientes',
            pagina: max(1, $this->numero($q->getString('pagina')) ?? 1),
        );

        return $this->render('auto/listado.html.twig', [
            'pagina' => $this->catalogo->buscar($filtros),
            'total' => \count($this->catalogo->todos()),
            'marcas' => $this->catalogo->marcas(),
            'carrocerias' => $this->catalogo->carrocerias(),
            'combustibles' => $this->catalogo->valores(static fn ($auto) => $auto->combustible),
            'transmisiones' => $this->catalogo->valores(static fn ($auto) => $auto->transmision),
            'rangos' => $rangos,
            'sugerencias' => $this->catalogo->sugerencias(),
            'ordenes' => CatalogoAutos::ORDENES,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Catálogo seccionado por marca y modelo.
     */
    #[Route('/catalogo', name: 'catalogo', methods: ['GET'])]
    public function catalogo(Request $request): Response
    {
        $secciones = $this->catalogo->secciones();

        // Pestañas abiertas: las de la URL (?marca=bmw&modelo=m4) o las primeras.
        // Así se puede compartir el enlace y también funciona sin JavaScript.
        $marca = $this->opcionValida($request->query->getString('marca'), array_column($secciones, 'clave'))
            ?? ($secciones[0]['clave'] ?? null);
        $modelo = null;
        foreach ($secciones as $seccion) {
            if ($seccion['clave'] === $marca) {
                $modelo = $this->opcionValida($request->query->getString('modelo'), array_column($seccion['modelos'], 'clave'))
                    ?? $seccion['modelos'][0]['clave'];
            }
        }

        return $this->render('auto/catalogo.html.twig', [
            'secciones' => $secciones,
            'marca_activa' => $marca,
            'modelo_activo' => $modelo,
        ]);
    }

    /**
     * Detalle de un auto con el formulario "Me interesa".
     */
    #[Route('/autos/{slug}', name: 'auto_detalle', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    public function detalle(
        string $slug,
        Request $request,
        VehiculoRepository $vehiculos,
        EntityManagerInterface $em,
        NotificadorConsultas $notificador,
        TranslatorInterface $traductor,
    ): Response {
        $auto = $this->catalogo->buscarPorSlug($slug)
            ?? throw $this->createNotFoundException(\sprintf('No existe el auto "%s".', $slug));

        /** @var Usuario $usuario */
        $usuario = $this->getUser();
        $consulta = (new Consulta())
            ->setNombre($usuario->getNombre())
            ->setEmail($usuario->getEmail())
            ->setMensaje($traductor->trans('consulta.mensaje_defecto', ['auto' => $auto->nombre()]));

        $form = $this->createForm(ConsultaType::class, $consulta, [
            'action' => $this->generateUrl('auto_detalle', ['slug' => $slug]).'#consulta',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $consulta
                ->setVehiculo($vehiculos->findOneBy(['slug' => $slug]))
                ->setUsuario($usuario)
                ->setIdioma($request->getLocale());
            $em->persist($consulta);
            $em->flush();
            $notificador->avisar($consulta);

            $this->addFlash('exito', 'consulta.enviada');

            return $this->redirectToRoute('auto_detalle', ['slug' => $slug, '_fragment' => 'consulta']);
        }

        return $this->render('auto/detalle.html.twig', [
            'auto' => $auto,
            'form' => $form,
        ]);
    }

    /**
     * Un entero positivo escrito en la URL ("45000", "45.000", "45,000"); cualquier otra cosa = null.
     */
    private function numero(string $valor): ?int
    {
        $limpio = str_replace(['.', ',', ' '], '', trim($valor));

        return ctype_digit($limpio) && \strlen($limpio) <= 9 ? (int) $limpio : null;
    }

    /**
     * @param list<string> $permitidas
     */
    private function opcionValida(string $valor, array $permitidas): ?string
    {
        return \in_array($valor, $permitidas, true) ? $valor : null;
    }
}
