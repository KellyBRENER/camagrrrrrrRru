export function init() {
    const form = document.getElementById('loginForm');
    
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault(); // On empêche le rechargement de la page !
            
            // On récupère les données du formulaire
            const formData = new FormData(form);
            
            // On les envoie à un contrôleur PHP dédié au login
            fetch('/api/login.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Si c'est bon, on recharge la page entière UNE SEULE FOIS 
                    // pour mettre à jour la session et le header
                    window.location.href = '/?page=home';
                } else {
                    document.getElementById('loginError').innerText = data.message;
                }
            });
        });
    }
}