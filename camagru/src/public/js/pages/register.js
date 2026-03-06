export function init() {
    const form = document.getElementById('registerForm');
    const msg = document.getElementById('registerMsg');
    
    if (!form) return;

    form.addEventListener('submit', (event) => {
        // 1. On active le visuel Bootstrap (bordures rouges/vertes)
        form.classList.add('was-validated');

        // 2. Vérification de la validité HTML5 (champs requis, format email)
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            return; // On arrête tout ici si le formulaire est invalide
        }

        // 3. Empêcher le rechargement de la page pour le traitement AJAX
        event.preventDefault();

        // 4. Vérification personnalisée du mot de passe (Regex)
        const password = form.password.value;
        const regex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
        
        if (!regex.test(password)) {
            msg.innerText = "Le mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.";
            return; // On arrête tout ici si le mot de passe est trop faible
        }

        // 5. Envoi des données si tout est OK
        const formData = new FormData(form);
        
        fetch('/?page=register', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Compte créé ! Un email de confirmation vous a été envoyé.");
                window.location.href = "/?page=login"; 
            } else {
                msg.innerText = data.message;
            }
        })
        .catch(err => {
            console.error("Erreur lors de l'inscription:", err);
            msg.innerText = "Une erreur serveur est survenue.";
        });
    });
}