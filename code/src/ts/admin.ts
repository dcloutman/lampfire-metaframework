/**
 * Admin panel client-side behaviour.
 * Compiled to /js/admin.js.
 */

/** Attach confirmation prompts to forms that carry a data-confirm attribute. */
function initConfirmForms(): void {
    const forms: NodeListOf<HTMLFormElement> = document.querySelectorAll("form[data-confirm]");

    forms.forEach((form: HTMLFormElement): void => {
        form.addEventListener("submit", (event: Event): void => {
            const message: string | null = form.getAttribute("data-confirm");
            if (typeof message !== "string" || message.length === 0) {
                return;
            }

            const confirmed: boolean = window.confirm(message);
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
}

/** Run all initialisers after the DOM is ready. */
document.addEventListener("DOMContentLoaded", (): void => {
    initConfirmForms();
});
