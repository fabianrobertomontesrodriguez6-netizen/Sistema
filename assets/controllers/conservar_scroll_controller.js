import { Controller } from '@hotwired/stimulus';

/*
 * Al filtrar en "Busca tu modelo" la página no vuelve arriba: se queda donde estaba.
 *
 * Turbo carga la página nueva y sube al inicio (como un enlace normal). Este controlador
 * guarda la posición justo antes de enviar el formulario (o de "Quitar filtros") y, en
 * cuanto Turbo termina de cargar, la recupera. Así se mantiene el historial (Atrás deshace
 * el filtro) y se actualiza todo: resultados, selects y el enlace "Quitar filtros".
 *
 * Solo se recupera si se sigue en la misma ruta; si no, se descarta.
 */
const CLAVE = 'autoplaza:conservar-scroll';

export default class extends Controller {
    guardar() {
        sessionStorage.setItem(CLAVE, JSON.stringify({ ruta: location.pathname, y: window.scrollY }));
    }
}

// turbo:load llega después de que Turbo haya movido la página arriba.
document.addEventListener('turbo:load', () => {
    const guardado = sessionStorage.getItem(CLAVE);
    if (!guardado) return;
    sessionStorage.removeItem(CLAVE);

    const { ruta, y } = JSON.parse(guardado);
    if (ruta === location.pathname) {
        window.scrollTo({ top: y, behavior: 'instant' });
    }
});
