<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Consulta;
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
        yield ['zh', '查找您的车型', '轿车'];
        yield ['ja', 'モデルを探す', 'セダン'];
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
            'consulta[telefono]' => '+54 11 5555-1234',
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
    }

    public function testConsultaConErroresDevuelve422(): void
    {
        $pagina = $this->cliente->request('GET', '/ja/autos/bmw-m4-2022');

        $this->cliente->submit($pagina->filter('#consulta form, form[name="consulta"]')->form([
            'consulta[email]' => 'no-es-un-correo',
            'consulta[mensaje]' => '',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#consulta', '有効なメールアドレスを入力してください。');
        self::assertSelectorTextContains('#consulta', 'この項目は必須です。');
        self::assertEmailCount(0);
    }

    public function testAutoInexistenteDevuelve404(): void
    {
        $this->cliente->request('GET', '/es/autos/no-existe');

        self::assertResponseStatusCodeSame(404);
    }
}
