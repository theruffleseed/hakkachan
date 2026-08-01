import { Controller } from '@hotwired/stimulus';

/**
 * Progressive-enhancement fade-rise on scroll. Content is fully visible in
 * plain HTML/CSS; this only opts elements into the hidden-then-reveal state
 * once JS has actually run, so a JS failure never hides content.
 */
export default class extends Controller {
    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.element.classList.add('opacity-0', 'translate-y-4', 'transition', 'duration-700', 'ease-out');

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    this.element.classList.remove('opacity-0', 'translate-y-4');
                    this.observer.unobserve(this.element);
                }
            });
        }, { threshold: 0.15 });

        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
