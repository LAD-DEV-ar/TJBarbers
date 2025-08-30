<?php
// views/auth/registro.php
// Variables esperadas (opcionales): $usuario, $alerts
// $usuario->nombre, ->telefono, ->email
?>
<main class="auth-page">
  <section class="auth-card">
    <h1 class="title">Crear cuenta</h1>

    <?php // partial de alertas en layout ?>

    <form method="POST" action="/registro" class="form-auth" novalidate>
      <div class="field">
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" required value="<?= htmlspecialchars($usuario->nombre ?? '') ?>" />
      </div>

      <div class="field">
        <label for="telefono">Teléfono</label>
        <input id="telefono" name="telefono" type="text" value="<?= htmlspecialchars($usuario->telefono ?? '') ?>" />
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($usuario->email ?? '') ?>" />
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required />
      </div>

      <div class="field">
        <label for="password2">Repetir contraseña</label>
        <input id="password2" name="password2" type="password" required />
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Crear cuenta</button>
        <a class="btn--link" href="/">Volver al login</a>
      </div>
    </form>
  </section>
</main>

<style>
/* Reusar los mismos estilos que login; si los moviste a CSS global no hace falta repetir */
.auth-card { max-width:520px; }
</style>

<script>
// Validación simple en frontend: verificar coincidencia de contraseñas
document.querySelector('.form-auth').addEventListener('submit', function(e){
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password2').value;
  if (p1 !== p2) {
    e.preventDefault();
    alert('Las contraseñas no coinciden');
  }
});
</script>
