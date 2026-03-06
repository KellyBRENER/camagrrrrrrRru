<?php
//le user doit renseigner email valide, username, mdp robuste
// + envoie lien de confirmation unique par email
?>
<div class="auth-container container mt-5">
    <div class="card p-4 shadow-sm">
        <h2 class="text-center mb-4">Créer un compte</h2>
        
        <form id="registerForm" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Pseudo</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Pseudo" required>
                <div class="invalid-feedback">
                    Veuillez choisir un nom d'utilisateur.
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                <div class="invalid-feedback">
                    Veuillez entrer un email valide (ex: nom@domaine.com).
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                <div class="invalid-feedback">
                    Le mot de passe est requis.
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
        </form>
        
        <p id="registerMsg" class="mt-3 text-center" style="color:red;"></p>
    </div>
</div>