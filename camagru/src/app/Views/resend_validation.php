<?php
$resendError = $_GET['error'] ?? '';
$resendSuccess = $_GET['success'] ?? '';
?>
<div class="auth-container">
    <div class="form-savane">
        <h2 class="text-center mb-4"><span class="paw-print">📩</span> Renvoyer le lien</h2>

        <form action="/?page=resend_validation" method="POST">
            <div class="mb-3">
                <label for="resendEmail" class="form-label">Email du compte</label>
                <input type="email" class="form-control" id="resendEmail" name="email" placeholder="votre@email.com" required>
            </div>

            <button type="submit" class="btn-savane w-100">Renvoyer le lien de validation</button>
        </form>

        <p class="alert-savane error mt-3" role="alert" style="display: <?php echo $resendError ? 'block' : 'none'; ?>;">
            <?php echo htmlspecialchars($resendError); ?>
        </p>

        <p class="alert-savane success mt-3" role="status" style="display: <?php echo $resendSuccess ? 'block' : 'none'; ?>;">
            <?php echo htmlspecialchars($resendSuccess); ?>
        </p>

        <div class="text-center mt-4">
            <a href="#" data-page="login">Retour à la connexion</a>
        </div>
    </div>
</div>
