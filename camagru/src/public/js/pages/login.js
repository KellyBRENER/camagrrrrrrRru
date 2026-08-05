export function init() {
    const form = document.getElementById('loginForm');
    const errorMessage = document.getElementById('loginError');
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;
    
    if (!form || form.dataset.initialized === 'true') {
        return;
    }

    form.dataset.initialized = 'true';

    form.addEventListener('submit', (e) => {
        e.preventDefault(); // On empêche le rechargement de la page !

        if (errorMessage) {
            errorMessage.innerText = '';
            errorMessage.style.display = 'none';
        }

        if (submitButton) {
            submitButton.disabled = true;
        }

        // On récupère les données du formulaire
        const formData = new FormData(form);

        // On les envoie à la route login gérée par AuthController
        fetch('/?page=login', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async (res) => {
            const raw = await res.text();

            try {
                return JSON.parse(raw);
            } catch (err) {
                console.error('[LOGIN] Réponse serveur invalide', raw);
                throw new Error('Réponse serveur invalide');
            }
        })
        .then(data => {
            if (data.success) {
                // Si c'est bon, on recharge la page entière UNE SEULE FOIS 
                // pour mettre à jour la session et le header
                window.location.href = '/?page=home';
                return;
            }

            if (errorMessage) {
                errorMessage.innerText = data.message || 'Connexion impossible.';
                errorMessage.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('[LOGIN] Erreur de connexion', err);

            if (errorMessage) {
                errorMessage.innerText = 'Une erreur est survenue pendant la connexion.';
                errorMessage.style.display = 'block';
            }
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
            }
        });
    });
}
