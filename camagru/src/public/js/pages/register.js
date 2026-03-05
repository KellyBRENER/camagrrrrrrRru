export function init() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', (e) => {
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