<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalogo\CatalogoAutos;
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
     * "Busca tu modelo": todos los autos, con barra de búsqueda y filtros.
     */
    #[Route('/autos', name: 'auto_listado', methods: ['GET'])]
    public function listado(Request $request): Response
    {
        // Solo se aceptan valores que existen en el catálogo; cualquier otro se ignora.
        $marca = $this->opcionValida($request->query->getString('marca'), $this->catalogo->marcas());
        $carroceria = $this->opcionValida($request->query->getString('carroceria'), $this->catalogo->carrocerias());
        $orden = $this->opcionValida($request->query->getString('orden'), array_keys(CatalogoAutos::ORDENES)) ?? 'recientes';
        $busqueda = mb_substr(trim($request->query->getString('q')), 0, 100);

        return $this->render('auto/listado.html.twig', [
            'autos' => $this->catalogo->filtrar($marca, $carroceria, $orden, $busqueda),
            'total' => \count($this->catalogo->todos()),
            'marcas' => $this->catalogo->marcas(),
            'carrocerias' => $this->catalogo->carrocerias(),
            'sugerencias' => $this->catalogo->sugerencias(),
            'ordenes' => CatalogoAutos::ORDENES,
            'filtros' => ['q' => $busqueda, 'marca' => $marca, 'carroceria' => $carroceria, 'orden' => $orden],
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
     * @param list<string> $permitidas
     */
    private function opcionValida(string $valor, array $permitidas): ?string
    {
        return \in_array($valor, $permitidas, true) ? $valor : null;
    }
}
