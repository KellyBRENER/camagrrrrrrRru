<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4"><span class="paw-print">🐆</span> Connexion</h2>
        <?php if (isset($_GET['password_reset'])): ?>
            <p class="alert-savane success" role="status">Votre mot de passe a été modifié. Vous pouvez vous connecter.</p>
        <?php endif; ?>
        <form id="loginForm" action="/?page=login" method="POST">
            <?php echo Security::csrfField(); ?>
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-savane w-100">Se connecter</button>
        </form>
        <p id="loginError" class="alert-savane error mt-3" style="display: none;"></p>
        
        <div class="text-center mt-4">
            <a href="#" data-page="register">Pas encore de compte ? Rejoindre la meute !</a>
            <br>
            <a href="/?page=forgot_password" data-page="forgot_password">Mot de passe oublié ?</a>
            <br>
            <a href="#" data-page="resend_validation">Renvoyer le lien de validation</a>
        </div>
    </div>
</div>
