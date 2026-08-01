import { Controller } from '@hotwired/stimulus';

const SCROLL_THRESHOLD = 40;

/** Solidifies the transparent-over-hero header once the page scrolls past the hero. */
export default class extends Controller {
    connect() {
        this.onScroll = this.onScroll.bind(this);
        this.onScroll();
        window.addEventListener('scroll', this.onScroll, { passive: true });
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }

    onScroll() {
        const scrolled = window.scrollY > SCROLL_THRESHOLD;
        this.element.classList.toggle('bg-bone/95', scrolled);
        this.element.classList.toggle('backdrop-blur', scrolled);
        this.element.classList.toggle('border-bone-dim', scrolled);
        this.element.classList.toggle('border-transparent', !scrolled);
        this.element.classList.toggle('bg-transparent', !scrolled);
        this.element.classList.toggle('text-charcoal', scrolled);
        this.element.classList.toggle('text-bone', !scrolled);
    }
}
