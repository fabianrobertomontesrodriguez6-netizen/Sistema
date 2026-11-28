import { Controller } from '@hotwired/stimulus';

/*
 * Autocompletado en línea de "Busca tu modelo": al escribir "Lambo" aparece "rghini"
 * en gris claro dentro de la misma barra, justo detrás del cursor (sin listas ni menús).
 *
 * - Tab o → (con el cursor al final) aceptan la sugerencia; en el móvil, tocar el texto gris.
 * - Enter busca lo escrito, como siempre. Escape quita la sugerencia hasta que se escriba otra cosa.
 * - No importan acentos ni mayúsculas ("huracan" completa "Huracán"); lo que ya se escribió
 *   no se toca mientras se escribe; al aceptar queda con su forma correcta ("hura" → "Huracán").
 * - Candidatas: marcas, "Marca Modelo" y también el modelo solo ("Hura" → "Huracán").
 *   Se elige la más corta, así "Lambo" completa la marca y no un modelo concreto.
 *
 * El texto gris es una capa encima de la entrada con la misma letra y el mismo relleno:
 * una copia invisible de lo escrito empuja la sugerencia hasta el sitio justo.
 * Sin JavaScript la barra funciona igual, sin sugerencias.
 */
export default class extends Controller {
    static targets = ['entrada', 'escrito', 'resto'];
    static values = { sugerencias: Array, marcas: Array };

    connect() {
        const candidatas = new Set(this.sugerenciasValue);
        // "Lamborghini Huracán" → también "Huracán" (por si se busca por el modelo).
        for (const texto of this.sugerenciasValue) {
            const marca = this.marcasValue.find((m) => texto.startsWith(`${m} `));
            if (marca) candidatas.add(texto.slice(marca.length + 1));
        }
        this.candidatas = [...candidatas]
            .map((texto) => ({ texto, clave: this.normalizar(texto) }))
            .sort((a, b) => a.texto.length - b.texto.length || a.texto.localeCompare(b.texto, undefined, { numeric: true }));
        this.completo = '';      // lo que quedaría al aceptar, con lo escrito tal cual + lo que falta
        this.elegida = '';       // la candidata con su forma correcta ("Huracán")
        this.descartadoPara = null; // texto para el que se pulsó Escape: no volver a sugerir
    }

    normalizar(texto) {
        return texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
    }

    // En cada tecla, al mover el cursor o al enfocar.
    actualizar() {
        const entrada = this.entradaTarget;
        const valor = entrada.value;
        const alFinal = entrada.selectionStart === valor.length && entrada.selectionEnd === valor.length;
        const consulta = this.normalizar(valor);

        this.completo = '';
        this.elegida = '';
        if (valor !== this.descartadoPara) this.descartadoPara = null;
        if (consulta.trim() !== '' && alFinal && document.activeElement === entrada && this.descartadoPara === null) {
            const encontrada = this.candidatas.find(({ clave }) => clave.length > consulta.length && clave.startsWith(consulta));
            // Solo si quitar los acentos no cambia la longitud (si no, el corte no cuadraría).
            if (encontrada && this.normalizar(encontrada.texto).length === encontrada.texto.length) {
                this.completo = valor + encontrada.texto.slice(valor.length);
                this.elegida = encontrada.texto;
            }
        }
        this.pintar();
    }

    pintar() {
        const valor = this.entradaTarget.value;
        const resto = this.completo ? this.completo.slice(valor.length) : '';
        this.escritoTarget.textContent = valor;
        this.restoTarget.textContent = resto;

        // Si el texto ya no cabe, la entrada se desplaza y la capa no coincidiría: mejor no mostrarla.
        const desborda = this.entradaTarget.scrollWidth > this.entradaTarget.clientWidth;
        this.restoTarget.hidden = resto === '' || desborda;
    }

    teclado(evento) {
        if (!this.completo || this.restoTarget.hidden) return;

        const entrada = this.entradaTarget;
        const alFinal = entrada.selectionStart === entrada.value.length;
        if ((evento.key === 'Tab' && !evento.shiftKey) || (evento.key === 'ArrowRight' && alFinal)) {
            evento.preventDefault();
            this.aceptar();
        } else if (evento.key === 'Escape') {
            evento.preventDefault();
            this.descartadoPara = entrada.value;
            this.quitar();
        }
    }

    aceptar() {
        if (!this.completo) return;
        const entrada = this.entradaTarget;
        // Con la forma correcta: "hura" + Tab → "Huracán" (no "huracán").
        entrada.value = this.elegida || this.completo;
        entrada.focus();
        entrada.setSelectionRange(entrada.value.length, entrada.value.length);
        this.actualizar(); // por si "Lamborghini" se puede seguir completando
    }

    quitar() {
        this.completo = '';
        this.elegida = '';
        this.pintar();
    }
}
