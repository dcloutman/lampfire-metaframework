/* Compiled from src/ts/admin.ts. Regenerate with: npx tsc -p tsconfig.json */
"use strict";
/**
 * Admin panel client-side behaviour.
 * Compiled to /js/admin.js.
 */
/** Attach confirmation prompts to forms that carry a data-confirm attribute. */
function initConfirmForms() {
    var forms = document.querySelectorAll("form[data-confirm]");
    forms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            var message = form.getAttribute("data-confirm");
            if (typeof message !== "string" || message.length === 0) {
                return;
            }
            var confirmed = window.confirm(message);
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
}
/** Run all initialisers after the DOM is ready. */
document.addEventListener("DOMContentLoaded", function () {
    initConfirmForms();
});
