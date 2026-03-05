//le user doit renseigner email valide, username, mdp robuste
// + envoie lien de confirmation unique par email

<div class="auth-container">
    <h2>Créer un compte</h2>
    <form id="registerForm">
        <input type="text" name="username" placeholder="Pseudo" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">S'inscrire</button>
    </form>
    <p id="registerMsg" style="color:red;"></p>
</div>