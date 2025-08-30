<?php
// views/auth/login.php
// Variables esperadas (opcionales): $alerts, $email
?>
<main class="auth-page">
  <section class="auth-card">
    <h1 class="title">Iniciar sesión</h1>

    <form method="POST" action="/" class="form-auth" novalidate>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($email ?? '') ?>" />
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <div class="password-field">
          <input id="password" name="password" type="password" required />
          <button type="button" class="toggle-pass" aria-label="Mostrar contraseña">👁</button>
        </div>
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Entrar</button>
      </div>

      <div class="auth-footer">
        <a class="" href="/registro">Crear cuenta</a>
        <a class="" href="/olvide">¿Olvidaste tu contraseña?</a>
      </div>
    </form>
  </section>
</main>

<script>
document.addEventListener('click', (e) => {
  if (e.target.matches('.toggle-pass')) {
    const input = e.target.closest('.password-field').querySelector('input');
    if (input.type === 'password') {
      input.type = 'text';
      e.target.textContent = '🙈';
    } else {
      input.type = 'password';
      e.target.textContent = '👁';
    }
  }
});
</script>
