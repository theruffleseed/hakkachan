import { Controller } from '@hotwired/stimulus';

/*
 * Re-ranges the guest count select to the seats actually left on the selected date.
 * Convenience only — ReservationController::checkout() re-checks pax against capacity.
 */
export default class extends Controller {
    static targets = ['date', 'pax'];
    static values = { min: Number };

    connect() {
        this.refresh();
    }

    refresh() {
        const selected = this.dateTarget.selectedOptions[0];
        const remaining = Number(selected?.dataset.remaining ?? this.minValue);
        const previous = Number(this.paxTarget.value);

        this.paxTarget.replaceChildren(
            ...Array.from({ length: Math.max(0, remaining - this.minValue + 1) }, (_, i) => {
                const option = new Option(String(this.minValue + i), String(this.minValue + i));
                return option;
            }),
        );

        this.paxTarget.value = previous >= this.minValue && previous <= remaining
            ? String(previous)
            : String(this.minValue);
    }
}
