import { Controller } from '@hotwired/stimulus';

/*
 * Affiche une confirmation avant l'envoi du formulaire de changement de livre en
 * cours d'un club, puisque cette action supprime définitivement les chapitres et
 * discussions existants (cf. règle métier du garde-spoiler).
 */
export default class extends Controller {
    static values = { active: Boolean };

    confirm(event) {
        if (!this.activeValue) {
            return;
        }

        const confirmed = window.confirm(
            'Changer le livre en cours supprimera définitivement les chapitres et discussions actuels. Continuer ?',
        );

        if (!confirmed) {
            event.preventDefault();
        }
    }
}
