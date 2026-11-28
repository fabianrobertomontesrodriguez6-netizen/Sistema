"""Une data/marcas/*.json (un archivo por marca) en data/autos.json, con descripciones y textos
alternativos generados en 3 idiomas (es, en, pt). Los autos que ya estaban en data/autos.json conservan su descripción.
Uso: python3 data/combinar_marcas.py .   (desde la raíz del proyecto)"""
import json, sys, os, re
raiz = sys.argv[1]
L = ['es', 'en', 'pt']

def leer_valores(l):
    d = {}
    for linea in open(f'{raiz}/translations/valores.{l}.yaml', encoding='utf-8'):
        if linea.startswith('"'):
            k, v = linea.rstrip('\n').split('": ', 1)
            d[json.loads(k + '"')] = json.loads(v)
    return d
V = {l: leer_valores(l) for l in L}

def num(n, l):
    s = f'{n:,}'
    return s.replace(',', '.') if l in ('es', 'pt') else s

def dec(x, l):
    s = f'{x:.1f}'
    return s.replace('.', ',') if l in ('es', 'pt') else s

def descripcion(a, l):
    t = lambda k: V[l].get(a[k], a[k]) if a.get(k) else None
    m = re.fullmatch(r'EV (\d+(?:\.\d+)?) kWh', a['motor'])
    if m:  # eléctrico puro: "motor eléctrico con batería de 93 kWh"
        kwh = m.group(1)
        motor = {'es': f'motor eléctrico con batería de {kwh} kWh', 'en': f'electric motor with a {kwh} kWh battery',
                 'pt': f'motor elétrico com bateria de {kwh} kWh'}[l]
    else:
        motor = {'es': f"motor {a['motor']}", 'en': f"{a['motor']} engine", 'pt': f"motor {a['motor']}"}[l]
    nombre = ' '.join(p for p in [a['marca'], a['modelo'], a.get('version') or ''] if p).strip()
    ac, vm = a.get('aceleracion'), a.get('velocidad_maxima')
    carroceria_en = t('carroceria') if t('carroceria').isupper() else t('carroceria').lower()  # "SUV" se queda en mayúsculas
    if l == 'es':
        s = f"{t('carroceria')} {nombre} de {a['anio']}: {motor}, {num(a['potencia'], l)} hp y {num(a['torque'], l)} Nm, transmisión {t('transmision').lower()} y tracción {t('traccion').lower()}."
        if ac and vm: s += f" Acelera de 0 a 100 km/h en {dec(ac, l)} s y alcanza {num(vm, l)} km/h."
        elif vm: s += f" Velocidad máxima: {num(vm, l)} km/h."
    elif l == 'en':
        s = f"{a['anio']} {nombre} {carroceria_en}: {motor}, {num(a['potencia'], l)} hp and {num(a['torque'], l)} Nm, {t('transmision').lower()} transmission and {t('traccion').lower()}."
        if ac and vm: s += f" 0–100 km/h in {dec(ac, l)} s and a top speed of {num(vm, l)} km/h."
        elif vm: s += f" Top speed: {num(vm, l)} km/h."
    else:
        s = f"{t('carroceria')} {nombre} de {a['anio']}: {motor}, {num(a['potencia'], l)} cv e {num(a['torque'], l)} Nm, transmissão {t('transmision').lower()} e tração {t('traccion').lower()}."
        if ac and vm: s += f" Faz de 0 a 100 km/h em {dec(ac, l)} s e chega a {num(vm, l)} km/h."
        elif vm: s += f" Velocidade máxima: {num(vm, l)} km/h."
    return s

def alt(a, l):
    n = f"{a['marca']} {a['modelo']} {a['anio']}"
    return {'es': f'{n}, vista exterior', 'en': f'{n}, exterior view', 'pt': f'{n}, vista externa'}[l]

def alt_video(a, l):
    p = {'es': 'Anuncio oficial', 'en': 'Official ad', 'pt': 'Anúncio oficial'}[l]
    return f"{p}: {a.get('video_titulo') or a['marca'] + ' ' + a['modelo']}"

# Los 8 autos del principio, escritos a mano: conservan su descripción y su potencia.
A_MANO = {'bmw-m4-2022', 'chevrolet-camaro-ss-2020', 'fiat-500-2019', 'ford-expedition-2021', 'ford-mustang-gt-2022',
          'lamborghini-huracan-2019', 'mclaren-720s-2020', 'porsche-panamera-2021'}
originales = {a['slug']: a for a in json.load(open(f'{raiz}/data/autos.json', encoding='utf-8'))['autos'] if a['slug'] in A_MANO} if os.path.exists(f'{raiz}/data/autos.json') else {}
CAMPOS = ['slug', 'marca', 'modelo', 'version', 'anio', 'precio', 'kilometraje', 'combustible', 'transmision', 'carroceria',
          'motor', 'potencia', 'torque', 'traccion', 'aceleracion', 'velocidad_maxima']
autos, problemas = [], []
for archivo in sorted(os.listdir(f'{raiz}/data/marcas')):
    for a in json.load(open(f'{raiz}/data/marcas/{archivo}', encoding='utf-8')):
        n = {k: a[k] for k in CAMPOS if a.get(k) not in (None, '')}
        orig = originales.get(a['slug'])
        # Porsche y Lamborghini venían en PS/CV; la web muestra hp (1 PS = 0,98632 hp).
        if a['marca'] in ('Porsche', 'Lamborghini') and not orig:
            n['potencia'] = a['potencia'] = round(a['potencia'] * 0.98632)
        if a['combustible'] not in V['en'] or a['carroceria'] not in V['en'] or a['transmision'] not in V['en'] or a['traccion'] not in V['en']:
            problemas.append(f"{a['slug']}: valor sin traducción")
        orig = originales.get(a['slug'])
        # Los 8 autos originales conservan su descripción escrita a mano.
        n['descripcion'] = orig['descripcion'] if orig else {l: descripcion(a, l) for l in L}
        medio = {'tipo': 'imagen', 'src': a['imagen'], 'alt': {l: alt(a, l) for l in L}}
        if a.get('credito'): medio['credito'] = a['credito']
        if orig and not a.get('credito'):  # foto original propia (Fiat 500): conserva su alt
            medio['alt'] = orig['medios'][0]['alt']
        n['medios'] = [medio]
        if a.get('video'):
            n['medios'].append({'tipo': 'video', 'src': a['video'], 'alt': {l: alt_video(a, l) for l in L},
                                'credito': f"Video: {a['video_canal']} · YouTube"})
        if not os.path.isfile(f"{raiz}/assets/images/{a['imagen']}"): problemas.append(f"{a['slug']}: falta {a['imagen']}")
        autos.append(n)
slugs = [a['slug'] for a in autos]
assert len(slugs) == len(set(slugs)), 'slugs repetidos'
faltan = A_MANO - set(slugs)
if faltan: problemas.append(f'autos originales que ya no están: {sorted(faltan)}')
json.dump({'autos': autos}, open(f'{raiz}/data/autos.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=4)
open(f'{raiz}/data/autos.json', 'a').write('\n')
from collections import Counter
print(len(autos), 'autos', dict(Counter(a['marca'] for a in autos)), '| con video:', sum(len(a['medios']) > 1 for a in autos))
print('\n'.join(problemas) or 'sin problemas')
