<?php
$profile = $controller->profile;
$notice = $_SESSION['profile_notice'] ?? null;
unset($_SESSION['profile_notice']);
?>
<div class="savane-card">
    <h2><span class="paw-print">👤</span> Mon profil félin</h2>
    <p>Gérez vos informations et votre mot de passe.</p>
    <?php if ($notice): ?>
        <p class="alert-savane <?php echo $notice['success'] ? 'success' : 'error'; ?>" role="status"><?php echo htmlspecialchars($notice['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-savane mb-4">
            <h3>Informations personnelles</h3>
            <form id="updateProfileForm" action="/?page=profile_update" method="POST">
                <?php echo Security::csrfField(); ?>
                <div class="mb-3">
                    <label for="profileUsername" class="form-label">Pseudo</label>
                    <input id="profileUsername" type="text" name="username" class="form-control" maxlength="50" autocomplete="username" value="<?php echo htmlspecialchars($profile['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="profileEmail" class="form-label">Email</label>
                    <input id="profileEmail" type="email" name="email" class="form-control" maxlength="100" autocomplete="email" value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="profileCurrentPassword" class="form-label">Mot de passe actuel</label>
                    <input id="profileCurrentPassword" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                </div>
                <p data-form-message class="alert-savane error" role="alert" hidden></p>
                <button type="submit" class="btn-savane">Mettre à jour</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-savane mb-4">
            <h3>Changer le mot de passe</h3>
            <form id="updatePasswordForm" action="/?page=password_update" method="POST">
                <?php echo Security::csrfField(); ?>
                <div class="mb-3">
                    <label for="oldPassword" class="form-label">Mot de passe actuel</label>
                    <input id="oldPassword" type="password" name="old_password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label for="newProfilePassword" class="form-label">Nouveau mot de passe</label>
                    <input id="newProfilePassword" type="password" name="new_password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="profilePasswordHelp" required>
                    <p id="profilePasswordHelp">Au moins 8 caractères, dont une lettre et un chiffre.</p>
                </div>
                <div class="mb-3">
                    <label for="profilePasswordConfirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                    <input id="profilePasswordConfirmation" type="password" name="password_confirmation" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <p data-form-message class="alert-savane error" role="alert" hidden></p>
                <button type="submit" class="btn-savane">Changer le mot de passe</button>
            </form>
        </div>
    </div>
</div>

<div class="form-savane mb-4">
    <h3>Notifications par email</h3>
    <p>Recevez un email lorsqu’un commentaire ou un like est ajouté à l’un de vos montages.</p>
    <p>État actuel : <strong><?php echo $profile['email_notifications'] ? 'activées' : 'désactivées'; ?></strong></p>
    <form id="updateNotificationsForm" action="/?page=notifications_update" method="POST">
        <?php echo Security::csrfField(); ?>
        <input type="hidden" name="email_notifications" value="<?php echo $profile['email_notifications'] ? '0' : '1'; ?>">
        <p data-form-message class="alert-savane error" role="alert" hidden></p>
        <button type="submit" class="btn-savane"><?php echo $profile['email_notifications'] ? 'Désactiver les notifications' : 'Activer les notifications'; ?></button>
    </form>
</div>
