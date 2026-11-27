import { Controller } from '@hotwired/stimulus';

/*
 * Lista de formularios que se pueden agregar y quitar (CollectionType de Symfony).
 * Se usa en el panel para las fotos y videos de un auto.
 */
export default class extends Controller {
    static targets = ['lista', 'elemento'];
    static values = { prototipo: String, indice: Number };

    agregar() {
        const html = this.prototipoValue.replace(/__name__/g, String(this.indiceValue));
        this.indiceValue++;
        this.listaTarget.insertAdjacentHTML('beforeend', html);
        this.elementoTargets.at(-1)?.querySelector('input, select')?.focus();
    }

    quitar(evento) {
        evento.target.closest('[data-coleccion-target="elemento"]')?.remove();
    }
}
