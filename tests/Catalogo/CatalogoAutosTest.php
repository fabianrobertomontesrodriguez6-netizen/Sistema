<?php

declare(strict_types=1);

namespace App\Tests\Catalogo;

use App\Catalogo\CatalogoAutos;
use App\Catalogo\CatalogoInvalidoException;
use App\Catalogo\FuenteJson;
use App\Catalogo\TipoMedio;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

final class CatalogoAutosTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        $this->carpeta = sys_get_temp_dir().'/catalogo-'.bin2hex(random_bytes(4));
        mkdir($this->carpeta.'/images/autos', 0o777, true);
        touch($this->carpeta.'/images/autos/local.jpg');
    }

    protected function tearDown(): void
    {
        unlink($this->carpeta.'/images/autos/local.jpg');
        @unlink($this->carpeta.'/autos.json');
        rmdir($this->carpeta.'/images/autos');
        rmdir($this->carpeta.'/images');
        rmdir($this->carpeta);
    }

    public function testConvierteCadaTipoDeMedioEnSuUrl(): void
    {
        $catalogo = $this->catalogo([$this->auto('auto-a', medios: [
            ['tipo' => 'imagen', 'src' => 'autos/local.jpg'],
            ['tipo' => 'imagen', 'src' => 'https://ejemplo.com/foto?w=800'],
            ['tipo' => 'video', 'src' => 'https://youtu.be/dQw4w9WgXcQ'],
            ['tipo' => 'video', 'src' => 'https://ejemplo.com/video.mp4'],
        ])]);

        $medios = $catalogo->buscarPorSlug('auto-a')->medios;

        self::assertSame('images/autos/local.jpg', $medios[0]->url);
        self::assertSame('https://ejemplo.com/foto?w=800', $medios[1]->url);
        self::assertSame(TipoMedio::YouTube, $medios[2]->tipo);
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $medios[2]->url);
        self::assertSame(TipoMedio::Video, $medios[3]->tipo);
    }

    public function testFiltraYOrdena(): void
    {
        $catalogo = $this->catalogo([
            $this->auto('a', marca: 'Ford', precio: 300),
            $this->auto('b', marca: 'BMW', precio: 100),
            $this->auto('c', marca: 'Ford', precio: 200),
        ]);

        $slugs = array_map(
            static fn ($auto) => $auto->slug,
            $catalogo->filtrar('Ford', null, 'precio_asc'),
        );

        self::assertSame(['c', 'a'], $slugs);
        self::assertSame(['BMW', 'Ford'], $catalogo->marcas());
        self::assertNull($catalogo->buscarPorSlug('no-existe'));
    }

    public function testFallaSiUnArchivoLocalNoExiste(): void
    {
        $catalogo = $this->catalogo([$this->auto('a', medios: [['tipo' => 'imagen', 'src' => 'autos/falta.jpg']])]);

        $this->expectException(CatalogoInvalidoException::class);
        $this->expectExceptionMessage('no existe el archivo assets/images/autos/falta.jpg');
        $catalogo->todos();
    }

    public function testFallaSiFaltaUnCampo(): void
    {
        $auto = $this->auto('a');
        unset($auto['precio']);

        $this->expectException(CatalogoInvalidoException::class);
        $this->expectExceptionMessage('autos[0]: "precio" debe ser un número entero positivo.');
        $this->catalogo([$auto])->todos();
    }

    public function testTextosTraducidosConRespaldoEnEspanol(): void
    {
        $auto = $this->auto('a', medios: [['tipo' => 'imagen', 'src' => 'autos/local.jpg', 'alt' => 'Solo español']]);
        $auto['descripcion'] = ['es' => 'Hola', 'en' => 'Hello'];

        $leido = $this->catalogo([$auto])->buscarPorSlug('a');

        self::assertSame('Hello', $leido->descripcion->en('en'));
        self::assertSame('Hola', $leido->descripcion->en('ja'));
        self::assertSame('Solo español', $leido->medios[0]->alt->en('pt'));
    }

    public function testFallaSiFaltaElEspanol(): void
    {
        $auto = $this->auto('a');
        $auto['descripcion'] = ['en' => 'Hello'];

        $this->expectException(CatalogoInvalidoException::class);
        $this->expectExceptionMessage('autos[0]: falta "descripcion.es"');
        $this->catalogo([$auto])->todos();
    }

    public function testFallaConIdiomaDesconocido(): void
    {
        $auto = $this->auto('a');
        $auto['descripcion'] = ['es' => 'Hola', 'fr' => 'Bonjour'];

        $this->expectException(CatalogoInvalidoException::class);
        $this->expectExceptionMessage('tiene el idioma "fr", que no existe');
        $this->catalogo([$auto])->todos();
    }

    public function testBuscaPorMarcaOModeloSinImportarAcentosNiMayusculas(): void
    {
        $catalogo = $this->catalogo([
            $this->auto('a', marca: 'Lamborghini', modelo: 'Huracán'),
            $this->auto('b', marca: 'Lamborghini', modelo: 'Urus'),
            $this->auto('c', marca: 'Ford', modelo: 'Mustang'),
        ]);
        $slugs = static fn (array $autos): array => array_map(static fn ($auto) => $auto->slug, $autos);

        self::assertEqualsCanonicalizing(['a', 'b'], $slugs($catalogo->filtrar(null, null, texto: 'lamborghini')));
        self::assertSame(['a'], $slugs($catalogo->filtrar(null, null, texto: 'HURACAN')));
        self::assertSame(['a'], $slugs($catalogo->filtrar(null, null, texto: 'lamborghini huracán 2020')));
        self::assertSame([], $catalogo->filtrar(null, null, texto: 'ferrari'));
        self::assertCount(3, $catalogo->filtrar(null, null, texto: '  '));
    }

    public function testCatalogoSeccionadoPorMarca(): void
    {
        $catalogo = $this->catalogo([
            $this->auto('a', marca: 'Lamborghini', modelo: 'Urus'),
            $this->auto('b', marca: 'BMW', modelo: 'M4'),
            $this->auto('c', marca: 'Lamborghini', modelo: 'Huracán'),
        ]);

        $secciones = $catalogo->porMarca();

        self::assertSame(['BMW', 'Lamborghini'], array_keys($secciones));
        self::assertSame(['c', 'a'], array_map(static fn ($auto) => $auto->slug, $secciones['Lamborghini']));
        self::assertContains('Lamborghini Urus', $catalogo->sugerencias());

        $secciones = $catalogo->secciones();
        self::assertSame(['bmw', 'lamborghini'], array_column($secciones, 'clave'));
        self::assertSame(['huracan', 'urus'], array_column($secciones[1]['modelos'], 'clave'));
        self::assertSame('Huracán', $secciones[1]['modelos'][0]['nombre']);
        self::assertSame(2, $secciones[1]['total']);
    }

    public function testDatosDelMotorSonOpcionalesYSeValidan(): void
    {
        $conMotor = $this->auto('a') + ['motor' => '5.0 L V8', 'potencia' => 450, 'aceleracion' => 4.3, 'traccion' => 'Trasera'];
        $catalogo = $this->catalogo([$conMotor, $this->auto('b')]);

        $auto = $catalogo->buscarPorSlug('a');
        self::assertSame('5.0 L V8', $auto->motor);
        self::assertSame(450, $auto->potencia);
        self::assertSame(4.3, $auto->aceleracion);
        self::assertNull($auto->torque);
        self::assertTrue($auto->tieneDatosMotor());
        self::assertFalse($catalogo->buscarPorSlug('b')->tieneDatosMotor());

        $this->expectException(CatalogoInvalidoException::class);
        $this->expectExceptionMessage('"potencia" debe ser un número entero positivo');
        $this->catalogo([$this->auto('a') + ['potencia' => 'mucha']])->todos();
    }

    public function testArchivoSubidoDesdeElPanel(): void
    {
        mkdir($this->carpeta.'/public/uploads/autos', 0o777, true);
        touch($this->carpeta.'/public/uploads/autos/foto.jpg');

        try {
            $catalogo = $this->catalogo([$this->auto('a', medios: [['tipo' => 'imagen', 'src' => '/uploads/autos/foto.jpg']])]);
            self::assertSame('/uploads/autos/foto.jpg', $catalogo->buscarPorSlug('a')->medios[0]->url);

            $this->expectException(CatalogoInvalidoException::class);
            $this->expectExceptionMessage('no existe el archivo public/uploads/autos/falta.jpg');
            $this->catalogo([$this->auto('a', medios: [['tipo' => 'imagen', 'src' => '/uploads/autos/falta.jpg']])])->todos();
        } finally {
            unlink($this->carpeta.'/public/uploads/autos/foto.jpg');
            rmdir($this->carpeta.'/public/uploads/autos');
            rmdir($this->carpeta.'/public/uploads');
            rmdir($this->carpeta.'/public');
        }
    }

    public function testFallaConJsonMalEscrito(): void
    {
        file_put_contents($this->carpeta.'/autos.json', '{"autos": [');

        $this->expectException(CatalogoInvalidoException::class);
        $this->expectExceptionMessage('no es un JSON válido');
        $this->crear()->todos();
    }

    /**
     * @param list<array<string, mixed>> $autos
     */
    private function catalogo(array $autos): CatalogoAutos
    {
        file_put_contents($this->carpeta.'/autos.json', json_encode(['autos' => $autos], \JSON_THROW_ON_ERROR));

        return $this->crear();
    }

    private function crear(): CatalogoAutos
    {
        return new CatalogoAutos(
            new FuenteJson($this->carpeta.'/autos.json'),
            $this->carpeta.'/images',
            $this->carpeta.'/public',
            new Packages(new Package(new EmptyVersionStrategy())),
        );
    }

    /**
     * @param list<array<string, string>> $medios
     *
     * @return array<string, mixed>
     */
    private function auto(string $slug, string $marca = 'Ford', int $precio = 1000, array $medios = [], string $modelo = 'Modelo'): array
    {
        return [
            'slug' => $slug,
            'marca' => $marca,
            'modelo' => $modelo,
            'anio' => 2020,
            'precio' => $precio,
            'kilometraje' => 1000,
            'combustible' => 'Gasolina',
            'transmision' => 'Manual',
            'carroceria' => 'Sedán',
            'descripcion' => 'Descripción.',
            'medios' => $medios,
        ];
    }
}
