<?php
$resendError = is_string($_GET['error'] ?? null) ? $_GET['error'] : '';
$resendSuccess = is_string($_GET['success'] ?? null) ? $_GET['success'] : '';
?>
<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4"><span class="paw-print">📩</span> Renvoyer le lien</h2>

        <form action="/?page=resend_validation" method="POST">
            <?php echo Security::csrfField(); ?>
            <div class="mb-3">
                <label for="resendEmail" class="form-label">Email du compte</label>
                <input type="email" class="form-control" id="resendEmail" name="email" placeholder="votre@email.com" required>
            </div>

            <button type="submit" class="btn-savane w-100">Renvoyer le lien de validation</button>
        </form>

        <p class="alert-savane error mt-3" role="alert" style="display: <?php echo $resendError ? 'block' : 'none'; ?>;">
            <?php echo htmlspecialchars($resendError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>

        <p class="alert-savane success mt-3" role="status" style="display: <?php echo $resendSuccess ? 'block' : 'none'; ?>;">
            <?php echo htmlspecialchars($resendSuccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </p>

        <div class="text-center mt-4">
            <a href="#" data-page="login">Retour à la connexion</a>
        </div>
    </div>
</div>
