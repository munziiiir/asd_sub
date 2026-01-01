document.addEventListener('DOMContentLoaded', () => {
    if (window.reservationsIndexModalInitialized) return;

    const modal = document.getElementById('cancelModal');
    const form = document.getElementById('cancelForm');
    const text = document.getElementById('cancelModalText');

    if (!modal || !form || !text) {
        return;
    }

    document.querySelectorAll('[data-modal-target="cancelModal"]').forEach((button) => {
        button.addEventListener('click', () => {
            const code = button.dataset.reservationCode ?? '';
            const url = button.dataset.cancelUrl ?? '#';

            text.textContent = `Are you sure you want to mark reservation ${code} as cancelled? This action cannot be undone.`;
            form.action = url;
            modal.showModal();
        });
    });

    window.reservationsIndexModalInitialized = true;
});
