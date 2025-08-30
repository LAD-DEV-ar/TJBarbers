<?php
// views/auth/recuperar.php
// Variables esperadas: $usuario (instancia encontrada por token), $alerts
// Asegurate que la ruta de este form incluye el token (ej: /recuperar?token=abc)
$token = $_GET['token'] ?? '';
?>
<main class="auth-page">
  <section class="auth-card">
    <h1 class="title">Crear nueva contraseña</h1>

    <form method="POST" action="/recuperar?token=<?= htmlspecialchars($token) ?>" class="form-auth">
      <div class="field">
        <label for="password">Nueva contraseña</label>
        <input id="password" name="password" type="password" required />
      </div>

      <div class="field">
        <label for="password2">Repetir contraseña</label>
        <input id="password2" name="password2" type="password" required />
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Actualizar contraseña</button>
        <a class="btn--link" href="/">Volver al login</a>
      </div>
    </form>
  </section>
</main>

<script>
document.querySelector('.form-auth').addEventListener('submit', function(e){
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password2').value;
  if (p1 !== p2) {
    e.preventDefault();
    alert('Las contraseñas no coinciden');
  }
});
</script>
