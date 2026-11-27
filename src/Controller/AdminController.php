<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalogo\CatalogoAutos;
use App\Catalogo\CatalogoInvalidoException;
use App\Entity\Consulta;
use App\Entity\Vehiculo;
use App\Form\VehiculoType;
use App\Repository\ConsultaRepository;
use App\Repository\UsuarioRepository;
use App\Repository\VehiculoRepository;
use App\Servicio\GestorMedios;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Panel de administración: solo para ROLE_ADMIN (también protegido en security.yaml).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehiculoRepository $vehiculos,
        private readonly ConsultaRepository $consultas,
    ) {
    }

    #[Route('', name: 'admin_inicio', methods: ['GET'])]
    public function inicio(UsuarioRepository $usuarios): Response
    {
        return $this->render('admin/inicio.html.twig', [
            'total_autos' => $this->vehiculos->count([]),
            'total_usuarios' => $usuarios->count([]),
            'sin_leer' => $this->consultas->contarSinLeer(),
            'consultas' => $this->consultas->recientes(5),
        ]);
    }

    // ---------- Autos ----------

    #[Route('/autos', name: 'admin_autos', methods: ['GET'])]
    public function autos(): Response
    {
        return $this->render('admin/autos.html.twig', ['vehiculos' => $this->vehiculos->todosOrdenados()]);
    }

    #[Route('/autos/nuevo', name: 'admin_auto_nuevo', methods: ['GET', 'POST'])]
    public function nuevo(Request $request, CatalogoAutos $catalogo, GestorMedios $medios): Response
    {
        return $this->formularioAuto(new Vehiculo(), $request, $catalogo, $medios);
    }

    #[Route('/autos/{id}/editar', name: 'admin_auto_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(Vehiculo $vehiculo, Request $request, CatalogoAutos $catalogo, GestorMedios $medios): Response
    {
        return $this->formularioAuto($vehiculo, $request, $catalogo, $medios);
    }

    #[Route('/autos/{id}/borrar', name: 'admin_auto_borrar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function borrar(Vehiculo $vehiculo, Request $request): Response
    {
        if ($this->isCsrfTokenValid('borrar-auto-'.$vehiculo->getId(), $request->getPayload()->getString('_token'))) {
            // Las consultas de este auto se conservan (guardan su nombre en "autoNombre").
            $this->em->createQuery('UPDATE '.Consulta::class.' c SET c.vehiculo = NULL WHERE c.vehiculo = :v')
                ->setParameter('v', $vehiculo)
                ->execute();
            $this->em->remove($vehiculo);
            $this->em->flush();
            $this->addFlash('exito', 'admin.auto.borrado');
        }

        return $this->redirectToRoute('admin_autos', status: Response::HTTP_SEE_OTHER);
    }

    private function formularioAuto(Vehiculo $vehiculo, Request $request, CatalogoAutos $catalogo, GestorMedios $medios): Response
    {
        $form = $this->createForm(VehiculoType::class, $vehiculo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehiculo->setMedios($medios->procesar($vehiculo->getMedios(), $vehiculo->getSlug()));

            // Misma validación que usa el catálogo al leer: así nunca se guarda un auto que rompa la web.
            try {
                $catalogo->validarFila($vehiculo->aFila(), $vehiculo->getNombre());
            } catch (CatalogoInvalidoException $e) {
                $form->addError(new FormError($e->getMessage()));
            }

            if ($form->isValid()) {
                $nuevo = null === $vehiculo->getId();
                $this->em->persist($vehiculo);
                $this->em->flush();
                $this->addFlash('exito', $nuevo ? 'admin.auto.creado' : 'admin.auto.guardado');

                return $this->redirectToRoute('admin_autos', status: Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/auto_form.html.twig', [
            'form' => $form,
            'vehiculo' => $vehiculo,
        ]);
    }

    // ---------- Consultas ----------

    #[Route('/consultas', name: 'admin_consultas', methods: ['GET'])]
    public function consultas(): Response
    {
        return $this->render('admin/consultas.html.twig', ['consultas' => $this->consultas->recientes()]);
    }

    #[Route('/consultas/{id}/leida', name: 'admin_consulta_leida', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function marcarLeida(Consulta $consulta, Request $request): Response
    {
        if ($this->isCsrfTokenValid('consulta-'.$consulta->getId(), $request->getPayload()->getString('_token'))) {
            $consulta->setLeida(!$consulta->isLeida());
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_consultas', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/consultas/{id}/borrar', name: 'admin_consulta_borrar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function borrarConsulta(Consulta $consulta, Request $request): Response
    {
        if ($this->isCsrfTokenValid('consulta-'.$consulta->getId(), $request->getPayload()->getString('_token'))) {
            $this->em->remove($consulta);
            $this->em->flush();
            $this->addFlash('exito', 'admin.consulta.borrada');
        }

        return $this->redirectToRoute('admin_consultas', status: Response::HTTP_SEE_OTHER);
    }

    // ---------- Usuarios ----------

    #[Route('/usuarios', name: 'admin_usuarios', methods: ['GET'])]
    public function usuarios(UsuarioRepository $usuarios): Response
    {
        return $this->render('admin/usuarios.html.twig', ['usuarios' => $usuarios->findBy([], ['creadoEn' => 'DESC'])]);
    }
}
