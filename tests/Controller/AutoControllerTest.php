<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Consulta;
use App\Entity\Vehiculo;
use App\Tests\Soporte\BaseDeDatosDePrueba;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Catálogo para un usuario que ya entró: búsqueda, catálogo por marca, detalle y "Me interesa".
 */
final class AutoControllerTest extends WebTestCase
{
    use BaseDeDatosDePrueba;

    private KernelBrowser $cliente;

    protected function setUp(): void
    {
        $this->cliente = static::createClient();
        $this->prepararBase($this->cliente);
        $this->entrarComo($this->cliente, self::CLIENTE);
    }

    public function testListadoMuestraTodosLosAutos(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Busca tu modelo');
        self::assertCount(8, $pagina->filter('.tarjeta'));
        self::assertSelectorExists('input[type="search"][name="q"]');
        self::assertSelectorExists('.busqueda a.boton-pequeno[href="/es/catalogo"]');
        self::assertSelectorExists('html[lang="es"]');
    }

    #[DataProvider('idiomas')]
    public function testListadoTraducido(string $idioma, string $encabezado, string $carroceria): void
    {
        $pagina = $this->cliente->request('GET', "/$idioma/autos");

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $encabezado);
        self::assertSelectorExists("html[lang=\"$idioma\"]");
        self::assertStringContainsString($carroceria, $pagina->filter('select[name="carroceria"]')->text());
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function idiomas(): iterable
    {
        yield ['en', 'Find your model', 'Sedan'];
        yield ['pt', 'Busque seu modelo', 'Sedã'];
    }

    public function testBarraDeBusquedaConAutocompletadoEnLinea(): void
    {
        $pagina = $this->cliente->request('GET', '/en/autos');

        // Sin listas desplegables: ni <datalist> ni listbox; la sugerencia va dentro de la barra.
        self::assertSelectorNotExists('datalist');
        self::assertSelectorNotExists('[role="listbox"]');
        $barra = $pagina->filter('.busqueda__barra[data-controller="autocompletar"]');
        self::assertCount(1, $barra);
        self::assertSelectorExists('#q[aria-autocomplete="inline"][data-autocompletar-target="entrada"]');
        self::assertSelectorExists('.autocompletado[aria-hidden="true"] [data-autocompletar-target="resto"][hidden]');
        self::assertSelectorTextContains('#q-ayuda', 'press Tab');

        $sugerencias = json_decode((string) $barra->attr('data-autocompletar-sugerencias-value'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertContains('Lamborghini', $sugerencias);
        self::assertContains('Lamborghini Huracán', $sugerencias);
        self::assertContains('Lamborghini', json_decode((string) $barra->attr('data-autocompletar-marcas-value'), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testBuscarPorMarcaMuestraTodosSusModelos(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos?q=lamborghini');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $pagina->filter('.tarjeta'));
        self::assertSelectorTextContains('.tarjeta__titulo', 'Lamborghini Huracán');
        self::assertSelectorTextContains('.resultado', '1 resultado');

        $pagina = $this->cliente->request('GET', '/es/autos?q=ford');
        self::assertCount(2, $pagina->filter('.tarjeta'));
    }

    public function testMasFiltrosYPaginacion(): void
    {
        // 8 autos de los tests: con 12 por página hay una sola página y no hay paginación.
        $pagina = $this->cliente->request('GET', '/es/autos');
        self::assertSelectorNotExists('.paginacion');
        self::assertSelectorTextContains('.resultado', 'Mostrando 1–8 de 8.');

        $pagina = $this->cliente->request('GET', '/es/autos?precio_min=50000&precio_max=100000&transmision=Automática');
        self::assertSame(
            ['BMW M4', 'Ford Expedition', 'Porsche Panamera'], // año desc; mismo año → por marca
            $pagina->filter('.tarjeta__titulo')->each(static fn ($h) => $h->text()),
        );
        self::assertSelectorExists('details.mas-filtros[open]');
        // Al filtrar (o quitar filtros) la página no vuelve arriba: lo hace conservar_scroll_controller.js.
        self::assertSelectorExists('form.busqueda[data-controller~="conservar-scroll"][data-action~="submit->conservar-scroll#guardar"]');
        self::assertSelectorExists('form.busqueda a[href="/es/autos"][data-action~="conservar-scroll#guardar"]');

        $pagina = $this->cliente->request('GET', '/es/autos?anio_min=2022&orden=precio_asc');
        self::assertSame(['Ford Mustang GT', 'BMW M4'], $pagina->filter('.tarjeta__titulo')->each(static fn ($h) => $h->text()));

        // Valores raros en la URL se ignoran.
        $this->cliente->request('GET', '/es/autos?precio_min=abc&combustible=Plutonio&pagina=-3');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.resultado', 'de 8.');
    }

    public function testPaginacionConservaLosFiltros(): void
    {
        $em = $this->cliente->getContainer()->get(EntityManagerInterface::class);
        for ($i = 1; $i <= 10; ++$i) {
            $em->persist(Vehiculo::desdeFila([
                'slug' => "fiat-panda-$i", 'marca' => 'Fiat', 'modelo' => 'Panda', 'anio' => 2000 + $i,
                'precio' => 5000 + $i, 'kilometraje' => 1000, 'combustible' => 'Gasolina',
                'transmision' => 'Manual', 'carroceria' => 'Hatchback', 'descripcion' => 'Panda.',
            ]));
        }
        $em->flush();

        $pagina = $this->cliente->request('GET', '/es/autos?orden=precio_asc');
        self::assertCount(12, $pagina->filter('.tarjeta'));
        self::assertSelectorTextContains('.resultado', 'Mostrando 1–12 de 18.');
        $siguiente = $pagina->filter('.paginacion a[rel="next"]')->attr('href');
        // Al cambiar de página se va a los resultados, no al inicio de la página.
        self::assertSame('/es/autos?orden=precio_asc&pagina=2#resultados', $siguiente);
        self::assertSelectorExists('#resultados');

        $pagina = $this->cliente->request('GET', $siguiente);
        self::assertCount(6, $pagina->filter('.tarjeta'));
        self::assertSelectorTextContains('.paginacion [aria-current]', '2');
        self::assertSame('McLaren 720S', $pagina->filter('.tarjeta__titulo')->last()->text(), 'Sigue ordenado por precio: el más caro al final.');
    }

    public function testFiltroPorMarca(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos?marca=Ford');

        self::assertResponseIsSuccessful();
        foreach ($pagina->filter('.tarjeta__titulo') as $titulo) {
            self::assertStringStartsWith('Ford', $titulo->textContent);
        }
    }

    public function testSelectorDeIdiomaConservaPaginaYFiltros(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos?marca=Ford');

        self::assertSame('/en/autos?marca=Ford', $pagina->filter('.idiomas a[hreflang="en"]')->attr('href'));
    }

    public function testCatalogoEnPestanasDeMarcaYModelo(): void
    {
        $pagina = $this->cliente->request('GET', '/es/catalogo');

        self::assertResponseIsSuccessful();
        $marcas = $pagina->filter('.pestanas__marcas [role="tab"]');
        self::assertSame(['bmw', 'chevrolet', 'fiat', 'ford', 'lamborghini', 'mclaren', 'porsche'], $marcas->each(static fn ($a) => $a->attr('data-clave')));

        // Sin elegir nada se abre la primera marca y su primer modelo; el resto de paneles está oculto.
        self::assertSame('true', $marcas->first()->attr('aria-selected'));
        self::assertCount(1, $pagina->filter('.pestanas__panel:not([hidden])'));
        self::assertSame('panel-bmw', $pagina->filter('.pestanas__panel:not([hidden])')->attr('id'));
        self::assertStringContainsString('BMW M4', $pagina->filter('#panel-bmw .modelos__panel:not([hidden])')->text());

        // Arreglo 6: las listas de marcas y modelos se recorren con una barra visible (antes estaba oculta)
        // y la barra de marcas no usa rounded-full (con dos filas recortaba la esquina de BMW).
        foreach (['.pestanas__marcas', '#panel-bmw .modelos__lista'] as $selector) {
            $clases = explode(' ', (string) $pagina->filter($selector)->attr('class'));
            self::assertContains('scroll-fino', $clases, $selector);
            self::assertNotContains('scroll-sin-barra', $clases, $selector);
        }
        self::assertNotContains('sm:rounded-full', explode(' ', (string) $pagina->filter('.pestanas__marcas')->attr('class')));
    }

    public function testCatalogoAbreLaMarcaYElModeloDeLaUrl(): void
    {
        $pagina = $this->cliente->request('GET', '/es/catalogo?marca=ford&modelo=expedition');

        self::assertSame('true', $pagina->filter('#pestana-ford')->attr('aria-selected'));
        self::assertSame('false', $pagina->filter('#pestana-bmw')->attr('aria-selected'));
        self::assertSame('panel-ford', $pagina->filter('.pestanas__panel:not([hidden])')->attr('id'));

        // Ford tiene dos modelos: pestañas dentro de la pestaña.
        self::assertSame(['expedition', 'mustang-gt'], $pagina->filter('#panel-ford .modelos__lista [role="tab"]')->each(static fn ($a) => $a->attr('data-clave')));
        self::assertSame('true', $pagina->filter('#pestana-ford-expedition')->attr('aria-selected'));
        self::assertNull($pagina->filter('#panel-ford-expedition')->attr('hidden'));
        self::assertNotNull($pagina->filter('#panel-ford-mustang-gt')->attr('hidden'));

        // Valores que no existen se ignoran.
        $pagina = $this->cliente->request('GET', '/es/catalogo?marca=ferrari');
        self::assertSame('panel-bmw', $pagina->filter('.pestanas__panel:not([hidden])')->attr('id'));
    }

    public function testDetalleConDatosDelMotor(): void
    {
        $this->cliente->request('GET', '/es/autos/ford-mustang-gt-2022');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ford Mustang GT');
        self::assertSelectorExists('[data-controller="galeria"] video');
        self::assertSelectorTextContains('.precio', '$48.900');
        self::assertSelectorTextContains('.ficha', '5.0 L V8');
        self::assertSelectorTextContains('.ficha', '450 hp');
        self::assertSelectorTextContains('.ficha', 'Trasera');
    }

    public function testDetalleTraducidoIncluyeContenidoDelAuto(): void
    {
        $pagina = $this->cliente->request('GET', '/en/autos/ford-mustang-gt-2022');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ficha', 'One owner');
        self::assertSelectorTextContains('.precio', '$48,900');
        self::assertSelectorTextContains('.especificaciones', 'Gasoline');
        self::assertSelectorTextContains('.ficha', 'Rear-wheel drive');
        self::assertSame('Black Ford Mustang GT, front view', $pagina->filter('.galeria img')->attr('alt'));
    }

    public function testConsultaMeInteresaSeGuardaYSeEnviaPorCorreo(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');

        // El formulario viene relleno con los datos del usuario.
        self::assertSame('Carlos Cliente', $pagina->filter('#consulta_nombre')->attr('value'));
        self::assertStringContainsString('BMW M4 2022', $pagina->filter('#consulta_mensaje')->text());

        $this->cliente->submit($pagina->selectButton('Enviar consulta')->form([
            'consulta[telefono][pais]' => 'AR',
            'consulta[telefono][numero]' => '11 5555-1234',
        ]));

        self::assertResponseRedirects('/es/autos/bmw-m4-2022#consulta');
        self::assertEmailCount(1);
        $correo = self::getMailerMessage();
        self::assertEmailHeaderSame($correo, 'Reply-To', 'Carlos Cliente <'.self::CLIENTE.'>');
        self::assertEmailHtmlBodyContains($correo, '+54 11 5555-1234');

        $this->cliente->followRedirect();
        self::assertSelectorTextContains('.aviso--exito', 'Gracias');

        $consultas = $this->cliente->getContainer()->get(EntityManagerInterface::class)->getRepository(Consulta::class)->findAll();
        self::assertCount(1, $consultas);
        self::assertSame('BMW M4 2022', $consultas[0]->getAutoNombre());
        self::assertSame(self::CLIENTE, $consultas[0]->getUsuario()?->getEmail());
        self::assertFalse($consultas[0]->isLeida());
        // Se guarda con el prefijo del país, en formato E.164.
        self::assertSame('+541155551234', $consultas[0]->getTelefono());
    }

    public function testConsultaTelefonoListaPaisesConPrefijo(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');

        self::assertSame('España (+34)', trim($pagina->filter('#consulta_telefono_pais option[value="ES"]')->text()));
        self::assertSame('612 34 56 78', $pagina->filter('#consulta_telefono_pais option[value="ES"]')->attr('data-ejemplo'));
        self::assertCount(1, $pagina->filter('#consulta [data-controller="telefono"] #consulta_telefono_numero'));

        $pagina = $this->cliente->request('GET', '/en/autos/bmw-m4-2022');
        self::assertSame('Spain (+34)', trim($pagina->filter('#consulta_telefono_pais option[value="ES"]')->text()));
    }

    public function testConsultaAceptaTelefonoConPrefijoEscritoSinElegirPais(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');
        $this->cliente->submit($pagina->selectButton('Enviar consulta')->form([
            'consulta[telefono][numero]' => '+34 612 34 56 78',
        ]));

        self::assertResponseRedirects('/es/autos/bmw-m4-2022#consulta');
        $consultas = $this->cliente->getContainer()->get(EntityManagerInterface::class)->getRepository(Consulta::class)->findAll();
        self::assertSame('+34612345678', $consultas[0]->getTelefono());
        self::assertEmailHtmlBodyContains(self::getMailerMessage(), '+34 612 34 56 78');
    }

    public function testConsultaSinTelefonoSeAcepta(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');
        $this->cliente->submit($pagina->selectButton('Enviar consulta')->form(['consulta[telefono][pais]' => 'ES']));

        self::assertResponseRedirects('/es/autos/bmw-m4-2022#consulta');
        $consultas = $this->cliente->getContainer()->get(EntityManagerInterface::class)->getRepository(Consulta::class)->findAll();
        self::assertNull($consultas[0]->getTelefono());
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function telefonosNoValidos(): iterable
    {
        yield 'cifras al azar' => ['AR', '123', 'no existe en el país elegido'];
        yield 'demasiado largo' => ['ES', '612 34 56 78 99 99', 'no existe en el país elegido'];
        yield 'número de otro país' => ['ES', '11 5555-1234', 'no existe en el país elegido'];
        yield 'prefijo inexistente' => [null, '+999 1234 5678', 'no existe en el país elegido'];
        yield 'sin país ni prefijo' => [null, '612 34 56 78', 'Elige el país del teléfono'];
        yield 'con letras' => ['US', '1-800-FLOWERS', 'solo puede tener números'];
    }

    #[DataProvider('telefonosNoValidos')]
    public function testConsultaRechazaTelefonoNoValido(?string $pais, string $numero, string $error): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');
        $formulario = $pagina->selectButton('Enviar consulta')->form(['consulta[telefono][numero]' => $numero]);
        if (null !== $pais) {
            $formulario['consulta[telefono][pais]'] = $pais;
        }
        $pagina = $this->cliente->submit($formulario);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#consulta fieldset', $error);
        // Lo escrito se conserva para poder corregirlo.
        self::assertSame($numero, $pagina->filter('#consulta_telefono_numero')->attr('value'));
        self::assertEmailCount(0);
    }

    public function testConsultaConErroresDevuelve422(): void
    {
        $pagina = $this->cliente->request('GET', '/pt/autos/bmw-m4-2022');

        $this->cliente->submit($pagina->filter('#consulta form, form[name="consulta"]')->form([
            'consulta[email]' => 'no-es-un-correo',
            'consulta[mensaje]' => '',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#consulta', 'Digite um e-mail válido.');
        self::assertSelectorTextContains('#consulta', 'Este campo é obrigatório.');
        self::assertEmailCount(0);
    }

    public function testConsultaRechazaNombreConEspaciosOCaracteresEspeciales(): void
    {
        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');
        $this->cliente->submit($pagina->selectButton('Enviar consulta')->form(['consulta[nombre]' => '  Fabian']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#consulta', 'El nombre no puede empezar con espacios.');

        $pagina = $this->cliente->request('GET', '/es/autos/bmw-m4-2022');
        $this->cliente->submit($pagina->selectButton('Enviar consulta')->form(['consulta[nombre]' => 'Fabian!!']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#consulta', 'sin números ni caracteres especiales');
        self::assertEmailCount(0);
    }

    public function testConsultaRechazaCorreoDeDominioInexistente(): void
    {
        $pagina = $this->cliente->request('GET', '/en/autos/bmw-m4-2022');
        $this->cliente->submit($pagina->filter('form[name="consulta"]')->form(['consulta[email]' => 'ana@gmial.invalid']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#consulta', 'The domain “gmial.invalid” does not exist or does not receive email.');
        self::assertEmailCount(0);
    }

    public function testAutoInexistenteDevuelve404(): void
    {
        $this->cliente->request('GET', '/es/autos/no-existe');

        self::assertResponseStatusCodeSame(404);
    }
}
