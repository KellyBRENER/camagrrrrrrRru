export function init() {
    const form = document.getElementById('registerForm');
    const msg = document.getElementById('registerMsg');

    const showMessage = (text) => {
        if (!msg) return;
        msg.innerText = text;
        msg.style.display = 'block';
    };
    
    if (!form) return;

    console.info('[REGISTER_FLOW][FRONT] register page init');

    form.addEventListener('submit', (event) => {
        console.info('[REGISTER_FLOW][FRONT] submit clicked');
        // 1. On active le visuel Bootstrap (bordures rouges/vertes)
        form.classList.add('was-validated');

        // 2. Vérification de la validité HTML5 (champs requis, format email)
        if (!form.checkValidity()) {
            console.warn('[REGISTER_FLOW][FRONT] html validation failed');
            event.preventDefault();
            event.stopPropagation();
            return; // On arrête tout ici si le formulaire est invalide
        }

        // 3. Empêcher le rechargement de la page pour le traitement AJAX
        event.preventDefault();

        // 4. Vérification personnalisée du mot de passe (Regex)
        const password = form.password.value;
        const regex = /^(?=.*[A-Za-z])(?=.*\d)[\s\S]{8,}$/;
        
        if (!regex.test(password) || new TextEncoder().encode(password).length > 72) {
            console.warn('[REGISTER_FLOW][FRONT] password validation failed');
            showMessage("Le mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.");
            return; // On arrête tout ici si le mot de passe est trop faible
        }

        // 5. Envoi des données si tout est OK
        const formData = new FormData(form);
        
        fetch('/?page=register', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async (res) => {
            console.info('[REGISTER_FLOW][FRONT] response received', { status: res.status });
            const raw = await res.text();
            try {
                return JSON.parse(raw);
            } catch (e) {
                throw new Error('Réponse serveur invalide');
            }
        })
        .then(data => {
            if (data.success) {
                console.info('[REGISTER_FLOW][FRONT] register success, redirecting');
                window.location.href = "/?page=registerinprogress";
            } else {
                console.warn('[REGISTER_FLOW][FRONT] register rejected', data);
                showMessage(data.message || "Impossible de créer le compte.");
            }
        })
        .catch(err => {
            console.error('[REGISTER_FLOW][FRONT] fetch error', err);
            showMessage("Une erreur serveur est survenue.");
        });
    });
}