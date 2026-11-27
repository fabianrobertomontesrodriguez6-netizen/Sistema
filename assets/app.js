import './stimulus_bootstrap.js';
/*
 * Archivo JavaScript principal. Se carga en todas las páginas mediante
 * la función importmap() de base.html.twig.
 */
import './styles/app.css';

/*
 * Protección CSRF "stateless" (config/packages/csrf.yaml): pone el token de doble envío
 * en cada formulario. Se importa aquí para que esté activa en TODAS las páginas; como
 * controlador Stimulus es "lazy" y solo cargaba en páginas con un formulario de Symfony,
 * así que formularios escritos a mano (entrar, salir) fallaban con 403 en otras páginas.
 */
import './controllers/csrf_protection_controller.js';
