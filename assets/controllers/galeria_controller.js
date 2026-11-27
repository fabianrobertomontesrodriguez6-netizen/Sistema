import { Controller } from '@hotwired/stimulus';

/*
 * Galería del detalle de un auto: al pulsar una miniatura muestra ese medio
 * en grande y pausa los videos que quedan ocultos.
 */
export default class extends Controller {
    static targets = ['slide', 'miniatura'];

    mostrar({ params: { indice } }) {
        this.slideTargets.forEach((slide, i) => {
            const visible = i === indice;
            slide.hidden = !visible;

            if (!visible) {
                slide.querySelector('video')?.pause();
                // Un iframe de YouTube se detiene recargando su src.
                const iframe = slide.querySelector('iframe');
                if (iframe) iframe.src = iframe.src;
            }
        });

        this.miniaturaTargets.forEach((boton, i) => boton.classList.toggle('activa', i === indice));
    }
}
