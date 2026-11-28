import { Controller } from '@hotwired/stimulus';

/*
 * Teléfono de "Me interesa": al elegir el país, el campo del número muestra
 * de ejemplo un número de ese país (data-ejemplo de cada <option>).
 * Sin JS el formulario funciona igual, solo sin el ejemplo.
 */
export default class extends Controller {
    static targets = ['pais', 'numero'];

    connect() {
        this.actualizar();
    }

    actualizar() {
        const opcion = this.paisTarget.selectedOptions[0];
        this.numeroTarget.placeholder = opcion?.dataset.ejemplo ?? '';
    }
}
