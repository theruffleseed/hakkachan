import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'button'];

    toggle() {
        const isHidden = this.panelTarget.classList.contains('hidden');
        this.panelTarget.classList.toggle('hidden', !isHidden);
        this.buttonTarget.setAttribute('aria-expanded', String(isHidden));
    }
}
