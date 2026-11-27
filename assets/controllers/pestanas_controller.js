import { Controller } from '@hotwired/stimulus';

/*
 * Pestañas accesibles (role="tablist"/"tab"/"tabpanel") sin recargar la página.
 * Se puede anidar: en el catálogo hay pestañas de marcas y, dentro de cada marca,
 * pestañas de modelos (Stimulus no mezcla los targets de controladores anidados).
 *
 * Las pestañas son enlaces (?marca=bmw): sin JavaScript también funcionan, recargando.
 * Con JavaScript se cambia el panel al momento y se actualiza la URL para poder
 * compartirla. "parametro" es el nombre en la URL; "limpiar" son parámetros a borrar
 * al cambiar (al cambiar de marca se olvida el modelo).
 */
export default class extends Controller {
    static targets = ['pestana', 'panel'];
    static values = { parametro: String, limpiar: String };

    seleccionar(evento) {
        evento.preventDefault();
        this.activar(this.pestanaTargets.indexOf(evento.currentTarget), true);
    }

    // Flechas, Inicio y Fin para moverse entre pestañas con el teclado.
    teclado(evento) {
        const total = this.pestanaTargets.length;
        const actual = this.pestanaTargets.indexOf(evento.currentTarget);
        const destino = {
            ArrowRight: actual + 1, ArrowDown: actual + 1,
            ArrowLeft: actual - 1, ArrowUp: actual - 1,
            Home: 0, End: total - 1,
        }[evento.key];

        if (destino === undefined) return;
        evento.preventDefault();
        this.activar((destino + total) % total, true);
        this.pestanaTargets[(destino + total) % total].focus();
    }

    activar(indice, actualizarUrl) {
        this.pestanaTargets.forEach((pestana, i) => {
            const activa = i === indice;
            pestana.setAttribute('aria-selected', String(activa));
            pestana.tabIndex = activa ? 0 : -1;
        });
        this.panelTargets.forEach((panel, i) => { panel.hidden = i !== indice; });

        if (actualizarUrl && this.parametroValue) {
            const url = new URL(window.location.href);
            url.searchParams.set(this.parametroValue, this.pestanaTargets[indice].dataset.clave);
            if (this.limpiarValue) url.searchParams.delete(this.limpiarValue);
            // replaceState: cambiar de pestaña no llena el historial del botón "atrás".
            window.history.replaceState(window.history.state, '', url);
        }
    }
}
