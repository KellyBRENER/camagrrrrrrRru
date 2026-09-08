<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4">Confirmer mon inscription</h2>
        <p>Cliquez sur le bouton pour activer votre compte Camagru.</p>
        <form action="/?page=verify" method="POST">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <button type="submit" class="btn-savane w-100">Activer mon compte</button>
        </form>
    </div>
</div>
