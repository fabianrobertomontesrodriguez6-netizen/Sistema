import { Controller } from '@hotwired/stimulus';

/*
 * Botón de modo claro / oscuro.
 * Sin elección guardada se sigue el modo del sistema (prefers-color-scheme).
 * La elección se guarda en localStorage("tema") y se aplica en <html data-tema="...">;
 * base.html.twig la aplica antes de pintar la página para que no parpadee.
 */
const sistemaOscuro = window.matchMedia('(prefers-color-scheme: dark)');

export default class extends Controller {
    connect() {
        // Al navegar con Turbo se vuelve a aplicar la elección guardada.
        const guardado = leerGuardado();
        if (guardado) document.documentElement.dataset.tema = guardado;

        this.actualizar = this.actualizar.bind(this);
        sistemaOscuro.addEventListener('change', this.actualizar);
        this.actualizar();
    }

    disconnect() {
        sistemaOscuro.removeEventListener('change', this.actualizar);
    }

    alternar() {
        const nuevo = this.esOscuro() ? 'claro' : 'oscuro';
        document.documentElement.dataset.tema = nuevo;
        try {
            localStorage.setItem('tema', nuevo);
        } catch (e) {
            // Sin localStorage (modo privado estricto): el cambio dura hasta recargar.
        }
        this.actualizar();
    }

    esOscuro() {
        const tema = document.documentElement.dataset.tema;
        return tema ? tema === 'oscuro' : sistemaOscuro.matches;
    }

    actualizar() {
        this.element.setAttribute('aria-pressed', String(this.esOscuro()));
    }
}

function leerGuardado() {
    try {
        const tema = localStorage.getItem('tema');
        return tema === 'claro' || tema === 'oscuro' ? tema : null;
    } catch (e) {
        return null;
    }
}
