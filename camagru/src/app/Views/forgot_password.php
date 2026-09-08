<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4">Mot de passe oublié</h2>
        <p>Indiquez l’adresse email de votre compte pour recevoir un lien de réinitialisation.</p>
        <?php if (isset($_GET['sent'])): ?>
            <p class="alert-savane success" role="status">Si cette adresse correspond à un compte activé, un lien de réinitialisation vous sera envoyé. Vérifiez aussi vos courriers indésirables. Vous pouvez demander un nouveau lien après une minute.</p>
        <?php endif; ?>
        <form action="/?page=forgot_password" method="POST">
            <?php echo Security::csrfField(); ?>
            <div class="mb-3">
                <label for="resetEmail" class="form-label">Email du compte</label>
                <input type="email" id="resetEmail" name="email" class="form-control" maxlength="100" autocomplete="email" required>
            </div>
            <button type="submit" class="btn-savane w-100">Recevoir le lien</button>
        </form>
        <p class="text-center mt-4"><a href="/?page=login" data-page="login">Retour à la connexion</a></p>
    </div>
</div>
