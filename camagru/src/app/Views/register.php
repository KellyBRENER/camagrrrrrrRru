<?php
//le user doit renseigner email valide, username, mdp robuste
// + envoie lien de confirmation unique par email
?>
<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4"><span class="paw-print">🦁</span> Rejoindre la meute</h2>
        
        <form id="registerForm" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Pseudo</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Votre pseudo félin" required>
                <div class="invalid-feedback">
                    Veuillez choisir un nom d'utilisateur.
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="votre@email.com" required>
                <div class="invalid-feedback">
                    Veuillez entrer un email valide (ex: nom@domaine.com).
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe robuste" required>
                <div class="invalid-feedback">
                    Le mot de passe est requis.
                </div>
            </div>

            <button type="submit" class="btn-savane w-100">S'inscrire</button>
        </form>
        
        <p id="registerMsg" class="alert-savane error mt-3" style="display: none;"></p>
        
        <div class="text-center mt-4">
            <a href="#" data-page="login">Déjà membre ? Se connecter</a>
        </div>
    </div>
</div>