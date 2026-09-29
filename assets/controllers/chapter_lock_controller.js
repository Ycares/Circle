import { Controller } from '@hotwired/stimulus';

/*
 * Indicateur visuel uniquement : le filtrage réel des discussions spoilées est
 * fait côté serveur (ListVisibleDiscussionsUseCase). Ce contrôleur ne fait que
 * griser le lien et ajouter une icône de cadenas.
 */
export default class extends Controller {
    static values = { locked: Boolean };

    connect() {
        if (!this.lockedValue) {
            return;
        }

        this.element.classList.add('chapter-locked');
        this.element.setAttribute('aria-disabled', 'true');

        const icon = document.createElement('span');
        icon.className = 'chapter-lock-icon';
        icon.textContent = '🔒 ';
        this.element.prepend(icon);
    }
}
