export function init() {
    const form = document.getElementById('registerForm');
    const msg = document.getElementById('registerMsg');

    if (!form) return;
    form.addEventListener('submit', (e) => {
        const password = form.password.value;
        
        // Regex : Au moins 8 carac, 1 lettre, 1 chiffre
        const regex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;

        if (!regex.test(password)) {
            e.preventDefault(); // On bloque l'envoi
            msg.innerText = "Le mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.";
            return;
        }
    
        e.preventDefault();
        const formData = new FormData(form);

        fetch('/?page=register', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Compte créé ! Connectez-vous.");
                // On utilise ta fonction globale pour changer de vue
                window.location.href = "/?page=login"; 
            } else {
                document.getElementById('registerMsg').innerText = data.message;
            }
        })
        .catch(err => console.error("Erreur:", err));
    });
}