<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Usuario;
use App\Tests\Soporte\BaseDeDatosDePrueba;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Landing con login, registro, salida y quién puede ver qué.
 */
final class SeguridadControllerTest extends WebTestCase
{
    use BaseDeDatosDePrueba;

    public function testRaizRedirigeALaLandingEnElIdiomaDelNavegador(): void
    {
        $cliente = static::createClient();

        $cliente->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'pt-BR,pt;q=0.9,en;q=0.8']);
        self::assertResponseRedirects('/pt');

        // El chino y el japonés ya no existen: se usa el siguiente idioma que acepta el navegador.
        $cliente->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'ja-JP,ja;q=0.9,en;q=0.8']);
        self::assertResponseRedirects('/en');

        $cliente->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertResponseRedirects('/es');
    }

    public function testLandingPublicaConFormularioDeEntrada(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $cliente->request('GET', '/es');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/es/entrar"] input[name="email"]');
        self::assertSelectorExists('a[href="/es/registro"]');
        // Sin sesión no hay menú del catálogo ni del panel.
        self::assertSelectorNotExists('.menu a');
    }

    public function testIdiomaNoSoportadoDevuelve404(): void
    {
        $cliente = static::createClient();
        $cliente->request('GET', '/fr');

        self::assertResponseStatusCodeSame(404);
    }

    public function testSinSesionElCatalogoYElPanelLlevanALaLanding(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);

        foreach (['/es/autos', '/es/catalogo', '/en/autos/ford-mustang-gt-2022', '/es/admin'] as $url) {
            $cliente->request('GET', $url);
            self::assertResponseRedirects(null, 302, $url);
            self::assertStringEndsWith('/'.substr($url, 1, 2), (string) $cliente->getResponse()->headers->get('Location'), $url);
        }
    }

    public function testEntrarConElFormularioDeLaLanding(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $pagina = $cliente->request('GET', '/es');

        $cliente->submit($pagina->selectButton('Entrar')->form([
            'email' => self::CLIENTE,
            'password' => self::PASSWORD,
        ]));

        self::assertResponseRedirects('/es/autos');
        $cliente->followRedirect();
        self::assertSelectorTextContains('.sesion__nombre', 'Carlos Cliente');
    }

    public function testContrasenaIncorrectaMuestraError(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $pagina = $cliente->request('GET', '/es');

        $cliente->submit($pagina->selectButton('Entrar')->form([
            'email' => self::CLIENTE,
            'password' => 'otra-clave',
        ]));
        $cliente->followRedirect();

        self::assertSelectorExists('.panel-entrada .aviso--error');
        self::assertSelectorNotExists('.sesion');
    }

    public function testRegistroCreaUnUsuarioNormalYEntra(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $pagina = $cliente->request('GET', '/pt/registro');

        $cliente->submit($pagina->selectButton('Criar conta')->form([
            'registro[nombre]' => 'Nueva Persona',
            'registro[email]' => 'Nueva@Ejemplo.com',
            'registro[plainPassword][first]' => 'una-clave-larga',
            'registro[plainPassword][second]' => 'una-clave-larga',
        ]));

        self::assertResponseRedirects('/pt/autos');
        $cliente->followRedirect();
        self::assertSelectorTextContains('.aviso--exito', 'Conta criada');
        self::assertSelectorNotExists('.menu__admin');

        $usuario = $cliente->getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Usuario::class)->findOneBy(['email' => 'nueva@ejemplo.com']);
        self::assertNotNull($usuario);
        self::assertSame(['ROLE_USER'], $usuario->getRoles());
    }

    public function testRegistroConErroresDevuelve422Traducido(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $pagina = $cliente->request('GET', '/en/registro');

        $cliente->submit($pagina->selectButton('Create account')->form([
            'registro[nombre]' => 'X',
            'registro[email]' => self::CLIENTE,
            'registro[plainPassword][first]' => 'corta',
            'registro[plainPassword][second]' => 'corta',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'An account with this email already exists.');
        self::assertSelectorTextContains('form', 'at least 8 characters');
    }

    public function testUsuarioNormalNoVeNiPuedeAbrirElPanel(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $this->entrarComo($cliente, self::CLIENTE);

        $cliente->request('GET', '/es/autos');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.menu__admin');

        $cliente->request('GET', '/es/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminVeElPanel(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $this->entrarComo($cliente, self::ADMIN);

        $cliente->request('GET', '/es/autos');
        self::assertSelectorExists('.menu__admin[href="/es/admin"]');

        $cliente->request('GET', '/es/admin');
        self::assertResponseIsSuccessful();
    }

    public function testConSesionLaLandingLlevaAlCatalogo(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $this->entrarComo($cliente, self::CLIENTE);

        $cliente->request('GET', '/en');
        self::assertResponseRedirects('/en/autos');
    }

    public function testSalir(): void
    {
        $cliente = static::createClient();
        $this->prepararBase($cliente);
        $this->entrarComo($cliente, self::CLIENTE);
        $pagina = $cliente->request('GET', '/es/autos');

        $cliente->submit($pagina->selectButton('Salir')->form());
        self::assertResponseRedirects('/es');

        $cliente->request('GET', '/es/autos');
        self::assertResponseRedirects();
    }
}
