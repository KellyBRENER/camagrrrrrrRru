<div class="savane-card">
    <h2><span class="paw-print">👤</span> Mon profil félin</h2>
    <p>Gérez vos informations et préférences</p>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-savane mb-4">
            <h3>Informations personnelles</h3>
            <form id="updateProfileForm">
                <div class="mb-3">
                    <label class="form-label">Pseudo</label>
                    <input type="text" name="username" class="form-control" placeholder="Votre pseudo">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="votre@email.com">
                </div>
                <button type="submit" class="btn-savane">Mettre à jour</button>
            </form>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="form-savane mb-4">
            <h3>Changer le mot de passe</h3>
            <form id="updatePasswordForm">
                <div class="mb-3">
                    <label class="form-label">Ancien mot de passe</label>
                    <input type="password" name="old_password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-control">
                </div>
                <button type="submit" class="btn-savane">Changer</button>
            </form>
        </div>
    </div>
</div>

<div class="form-savane">
    <h3>Préférences</h3>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
        <label class="form-check-label" for="emailNotifications">
            Recevoir les notifications par email
        </label>
    </div>
</div>

<?php
//le user se connecte avec pseudo + mdp
//mdp oublié, envoie lien de réinitialisation unique par email
//possibilité de modifier pseudo/email/mdp une fois connecté
//possibilité de désactiver les notif par mail (activée par défaut)
?>