<div class="auth-container">
    <h2>Connexion</h2>
    <form id="loginForm">
        <div class="form-group">
            <label>Nom d'utilisateur</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Se connecter</button>
    </form>
    <p id="loginError" style="color:red;"></p>
</div>

<a href="#" data-page="register">Pas encore de compte ? S'inscrire</a>