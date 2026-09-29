import { Controller } from '@hotwired/stimulus';

/*
 * Envoie la progression sélectionnée en PATCH dès la validation, puis recharge la
 * page pour refléter les nouveaux channels déverrouillés et les discussions
 * désormais visibles.
 */
export default class extends Controller {
    static targets = ['select'];
    static values = { url: String, token: String };

    update() {
        fetch(this.urlValue, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                declaredChapter: this.selectTarget.value,
                _token: this.tokenValue,
            }),
        }).then(() => window.location.reload());
    }
}
