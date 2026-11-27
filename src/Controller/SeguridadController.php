<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalogo\CatalogoAutos;
use App\Entity\Usuario;
use App\Form\RegistroType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Landing (presentación + entrar), registro y salida.
 * Sin sesión solo se pueden ver estas páginas; el catálogo pide entrar (ver security.yaml).
 */
final class SeguridadController extends AbstractController
{
    #[Route('/', name: 'landing', methods: ['GET'])]
    public function landing(AuthenticationUtils $autenticacion, CatalogoAutos $catalogo): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('auto_listado');
        }

        // Unas fotos del inventario para la presentación (sin datos ni precios).
        $portadas = [];
        foreach ($catalogo->todos() as $auto) {
            if ($auto->portada()) {
                $portadas[] = $auto;
            }
        }

        return $this->render('seguridad/landing.html.twig', [
            'error' => $autenticacion->getLastAuthenticationError(),
            'ultimo_email' => $autenticacion->getLastUsername(),
            'portadas' => \array_slice($portadas, 0, 3),
            'total' => \count($catalogo->todos()),
        ]);
    }

    /**
     * El formulario de la landing envía aquí; lo procesa el firewall (form_login).
     */
    #[Route('/entrar', name: 'login_check', methods: ['POST'])]
    public function entrar(): never
    {
        throw new \LogicException('El firewall procesa esta ruta.');
    }

    /**
     * Lo procesa el firewall (logout).
     */
    #[Route('/salir', name: 'salir', methods: ['POST'])]
    public function salir(): never
    {
        throw new \LogicException('El firewall procesa esta ruta.');
    }

    #[Route('/registro', name: 'registro', methods: ['GET', 'POST'])]
    public function registro(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        Security $security,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('auto_listado');
        }

        $usuario = new Usuario();
        $form = $this->createForm(RegistroType::class, $usuario);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Los que se registran solos son siempre usuarios normales: solo ven el catálogo.
            $usuario->setRoles([]);
            $usuario->setPassword($hasher->hashPassword($usuario, (string) $form->get('plainPassword')->getData()));
            $em->persist($usuario);
            $em->flush();

            $security->login($usuario, 'form_login', 'main');
            $this->addFlash('exito', 'registro.bienvenida');

            return $this->redirectToRoute('auto_listado');
        }

        return $this->render('seguridad/registro.html.twig', ['form' => $form]);
    }
}
