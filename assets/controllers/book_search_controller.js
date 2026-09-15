import { Controller } from '@hotwired/stimulus';

/*
 * Recherche de livre en direct : débounce la saisie de l'utilisateur puis
 * soumet le formulaire de recherche, dont la réponse est affichée dans le
 * Turbo Frame "search-results" ciblé par le formulaire.
 */
export default class extends Controller {
    static targets = ['form'];
    static values = { delay: { type: Number, default: 300 } };

    #timeoutId = null;

    search() {
        window.clearTimeout(this.#timeoutId);
        this.#timeoutId = window.setTimeout(() => {
            this.formTarget.requestSubmit();
        }, this.delayValue);
    }

    disconnect() {
        window.clearTimeout(this.#timeoutId);
    }
}
