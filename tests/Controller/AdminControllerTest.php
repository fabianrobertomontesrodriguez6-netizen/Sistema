<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Consulta;
use App\Entity\Vehiculo;
use App\Tests\Soporte\BaseDeDatosDePrueba;
use App\Tests\Soporte\LibreTranslateFalso;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Panel de administración: autos (alta, edición, borrado), consultas y usuarios.
 */
final class AdminControllerTest extends WebTestCase
{
    use BaseDeDatosDePrueba;

    private KernelBrowser $cliente;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->cliente = static::createClient();
        $this->em = $this->prepararBase($this->cliente);
        $this->entrarComo($this->cliente, self::ADMIN);
    }

    public function testResumenYListados(): void
    {
        $this->cliente->request('GET', '/es/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.cifras', '8');

        $pagina = $this->cliente->request('GET', '/es/admin/autos');
        self::assertCount(8, $pagina->filter('.tabla tbody tr'));

        $pagina = $this->cliente->request('GET', '/es/admin/usuarios');
        self::assertCount(2, $pagina->filter('.tabla tbody tr'));
    }

    public function testCrearAutoConFotoSubidaYAparecerEnElCatalogo(): void
    {
        $pagina = $this->cliente->request('GET', '/es/admin/autos/nuevo');
        $formulario = $pagina->selectButton('Guardar')->form([
            'vehiculo[marca]' => 'Lamborghini',
            'vehiculo[modelo]' => 'Urus',
            'vehiculo[anio]' => '2023',
            'vehiculo[precio]' => '245000',
            'vehiculo[kilometraje]' => '5000',
            'vehiculo[combustible]' => 'Gasolina',
            'vehiculo[transmision]' => 'Automática',
            'vehiculo[carroceria]' => 'SUV',
            'vehiculo[motor]' => '4.0 L V8 Biturbo',
            'vehiculo[potencia]' => '657',
            'vehiculo[traccion]' => 'Integral',
            'vehiculo[descripcion][es]' => 'SUV superdeportivo.',
            'vehiculo[descripcion][en]' => 'Super SUV.',
        ]);

        // Un medio con un archivo subido (los campos de la colección se agregan con JS en el navegador).
        $foto = $this->fotoTemporal();
        $valores = $formulario->getPhpValues();
        $valores['vehiculo']['medios'][0] = ['tipo' => 'imagen', 'src' => '', 'alt' => ['es' => 'Urus amarillo']];
        $this->cliente->request('POST', $formulario->getUri(), $valores, [
            'vehiculo' => ['medios' => [0 => ['archivo' => $foto]]],
        ]);

        self::assertResponseRedirects('/es/admin/autos');

        $vehiculo = $this->em->getRepository(Vehiculo::class)->findOneBy(['slug' => 'lamborghini-urus-2023']);
        self::assertNotNull($vehiculo, 'El slug se genera solo con marca-modelo-año.');
        self::assertSame(['es' => 'SUV superdeportivo.', 'en' => 'Super SUV.'], $vehiculo->getDescripcion());
        $src = $vehiculo->getMedios()[0]['src'];
        self::assertStringStartsWith('/uploads/autos/lamborghini-urus-2023-', $src);
        $archivo = static::getContainer()->getParameter('kernel.project_dir').'/public'.$src;
        self::assertFileExists($archivo);

        try {
            $pagina = $this->cliente->request('GET', '/es/autos?q=lamborghini');
            self::assertCount(2, $pagina->filter('.tarjeta'));

            $this->cliente->request('GET', '/en/autos/lamborghini-urus-2023');
            self::assertSelectorTextContains('.ficha', 'Super SUV.');
            self::assertSelectorTextContains('.ficha', '657 hp');
            self::assertSelectorExists('.galeria img[src="'.$src.'"]');
        } finally {
            unlink($archivo);
        }
    }

    public function testNoGuardaUnAutoQueRomperiaElCatalogo(): void
    {
        $pagina = $this->cliente->request('GET', '/es/admin/autos/nuevo');
        $formulario = $pagina->selectButton('Guardar')->form([
            'vehiculo[marca]' => 'Fiat',
            'vehiculo[modelo]' => 'Panda',
            'vehiculo[anio]' => '2020',
            'vehiculo[precio]' => '9000',
            'vehiculo[kilometraje]' => '20000',
            'vehiculo[descripcion][es]' => 'Pequeño.',
        ]);
        $valores = $formulario->getPhpValues();
        $valores['vehiculo']['medios'][0] = ['tipo' => 'imagen', 'src' => 'autos/no-existe.jpg'];
        $this->cliente->request('POST', $formulario->getUri(), $valores);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.aviso--error', 'no existe el archivo assets/images/autos/no-existe.jpg');
        self::assertNull($this->em->getRepository(Vehiculo::class)->findOneBy(['modelo' => 'Panda']));
    }

    public function testEditarAuto(): void
    {
        $id = $this->em->getRepository(Vehiculo::class)->findOneBy(['slug' => 'fiat-500-2019'])->getId();
        $pagina = $this->cliente->request('GET', "/es/admin/autos/$id/editar");

        self::assertSame('Fiat', $pagina->filter('#vehiculo_marca')->attr('value'));
        self::assertCount(1, $pagina->filter('.medios .medio'));

        $this->cliente->submit($pagina->selectButton('Guardar')->form(['vehiculo[precio]' => '11500']));
        self::assertResponseRedirects('/es/admin/autos');

        $this->em->clear();
        $fiat = $this->em->getRepository(Vehiculo::class)->find($id);
        self::assertSame(11500, $fiat->getPrecio());
        self::assertSame('autos/fiat-500-frente.jpg', $fiat->getMedios()[0]['src'], 'Los medios existentes se conservan.');
    }

    public function testBorrarAutoConservaSusConsultas(): void
    {
        $vehiculo = $this->em->getRepository(Vehiculo::class)->findOneBy(['slug' => 'fiat-500-2019']);
        $consulta = (new Consulta())->setVehiculo($vehiculo)->setNombre('X')->setEmail('x@ejemplo.com')->setMensaje('Hola');
        $this->em->persist($consulta);
        $this->em->flush();
        $id = $vehiculo->getId();

        $pagina = $this->cliente->request('GET', '/es/admin/autos');
        $this->cliente->submit($pagina->filter("form[action=\"/es/admin/autos/$id/borrar\"]")->form());

        self::assertResponseRedirects('/es/admin/autos');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Vehiculo::class)->find($id));
        $consulta = $this->em->getRepository(Consulta::class)->findAll()[0];
        self::assertNull($consulta->getVehiculo());
        self::assertSame('Fiat 500 2019', $consulta->getAutoNombre());

        $this->cliente->request('GET', '/es/autos/fiat-500-2019');
        self::assertResponseStatusCodeSame(404);
    }

    public function testMarcarConsultaComoLeida(): void
    {
        $vehiculo = $this->em->getRepository(Vehiculo::class)->findOneBy(['slug' => 'bmw-m4-2022']);
        $this->em->persist((new Consulta())->setVehiculo($vehiculo)->setNombre('Lucía')->setEmail('lucia@ejemplo.com')->setMensaje('¿Acepta permuta?'));
        $this->em->flush();

        $pagina = $this->cliente->request('GET', '/es/admin/consultas');
        self::assertSelectorTextContains('.consulta-item', '¿Acepta permuta?');

        $this->cliente->submit($pagina->selectButton('Marcar como leída')->form());
        self::assertResponseRedirects('/es/admin/consultas');
        $this->cliente->followRedirect();
        self::assertSelectorExists('.consulta-item--leida');
    }

    public function testConsultasSeTraducenAlIdiomaDelPanelUnaSolaVez(): void
    {
        LibreTranslateFalso::$peticiones = [];
        $vehiculo = $this->em->getRepository(Vehiculo::class)->findOneBy(['slug' => 'bmw-m4-2022']);
        $this->em->persist((new Consulta())->setVehiculo($vehiculo)->setNombre('João')->setEmail('joao@ejemplo.com')->setMensaje('Olá, ainda está disponível?')->setIdioma('es'));
        $this->em->flush();

        $this->cliente->request('GET', '/es/admin/consultas');
        self::assertSelectorTextContains('.consulta-item__mensaje', '[es] Olá, ainda está disponível?');
        self::assertSelectorTextContains('.consulta-item__original summary', 'Traducido automáticamente del portugués');
        self::assertSelectorTextContains('.consulta-item__original', 'Olá, ainda está disponível?');
        self::assertSelectorNotExists('.aviso--error');

        // Guardada: la segunda vez no se vuelve a pedir.
        $this->cliente->request('GET', '/es/admin/consultas');
        self::assertCount(1, LibreTranslateFalso::$peticiones);
        self::assertSame(['q' => 'Olá, ainda está disponível?', 'source' => 'auto', 'target' => 'es', 'format' => 'text'], LibreTranslateFalso::$peticiones[0]);

        // Panel en portugués: el mensaje ya está en ese idioma y se muestra tal cual.
        $this->cliente->request('GET', '/pt/admin/consultas');
        self::assertSelectorTextSame('.consulta-item__mensaje', 'Olá, ainda está disponível?');
        self::assertSelectorNotExists('.consulta-item__original');
        self::assertCount(1, LibreTranslateFalso::$peticiones);
    }

    public function testSiElTraductorNoRespondeSeVeElOriginalYUnAviso(): void
    {
        $vehiculo = $this->em->getRepository(Vehiculo::class)->findOneBy(['slug' => 'bmw-m4-2022']);
        $this->em->persist((new Consulta())->setVehiculo($vehiculo)->setNombre('X')->setEmail('x@ejemplo.com')->setMensaje('FALLA hello'));
        $this->em->flush();

        $this->cliente->request('GET', '/en/admin/consultas');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.consulta-item__mensaje', 'FALLA hello');
        self::assertSelectorTextContains('.aviso--error', 'LibreTranslate');
    }

    private function fotoTemporal(): UploadedFile
    {
        $ruta = sys_get_temp_dir().'/urus-'.bin2hex(random_bytes(3)).'.png';
        // PNG de 1×1 píxel (sin depender de la extensión GD).
        file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        return new UploadedFile($ruta, 'urus.png', 'image/png', null, true);
    }
}
