export function init() {
    for (const id of ['updateProfileForm', 'updatePasswordForm', 'updateNotificationsForm']) {
        const form = document.getElementById(id);
        if (!form || form.dataset.initialized === 'true') continue;
        form.dataset.initialized = 'true';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const message = form.querySelector('[data-form-message]');
            if (button.disabled) return;
            button.disabled = true;
            message.hidden = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST', body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (response.status === 403) {
                    message.textContent = 'Votre session a expiré. Rechargez la page puis reconnectez-vous si nécessaire.';
                    message.hidden = false;
                    return;
                }
                const result = await response.json();
                if (!response.ok || !result.success) {
                    message.textContent = result.message || 'Impossible d’enregistrer les modifications.';
                    message.hidden = false;
                    return;
                }
                // Refresh the header, session configuration and all CSRF fields together.
                window.location.href = '/?page=profil';
            } catch {
                message.textContent = 'Impossible de joindre le serveur. Réessayez.';
                message.hidden = false;
            } finally {
                button.disabled = false;
            }
        });
    }
}
