<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4">Nouveau mot de passe</h2>
        <?php if (!empty($_SESSION['reset_form_valid'])): ?>
            <?php if (isset($_SESSION['reset_error'])): ?>
                <p class="alert-savane error" role="alert"><?php echo htmlspecialchars($_SESSION['reset_error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php unset($_SESSION['reset_error']); ?>
            <?php endif; ?>
            <form action="/?page=reset_password" method="POST">
                <?php echo Security::csrfField(); ?>
                <div class="mb-3">
                    <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                    <input type="password" id="newPassword" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="passwordHelp" required>
                    <p id="passwordHelp">Au moins 8 caractères, dont une lettre et un chiffre.</p>
                </div>
                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="confirmPassword" name="password_confirmation" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-savane w-100">Enregistrer le mot de passe</button>
            </form>
        <?php else: ?>
            <p class="alert-savane error" role="alert">Ce lien est invalide, expiré ou a déjà été utilisé.</p>
            <a href="/?page=forgot_password" data-page="forgot_password">Demander un nouveau lien</a>
        <?php endif; ?>
        <p class="text-center mt-4"><a href="/?page=login" data-page="login">Retour à la connexion</a></p>
    </div>
</div>
