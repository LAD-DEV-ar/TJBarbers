<main class="auth-page">
  <section class="auth-card">
    <h1 class="title">Recuperar contraseña</h1>

    <?php // partial de alertas en layout ?>

    <form method="POST" action="/olvide" class="form-auth">
      <div class="field">
        <label for="email">Ingresa tu email</label>
        <input id="email" name="email" type="email" required />
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Enviar instrucciones</button>
        <a class="btn--link" href="/">Volver al login</a>
      </div>
    </form>
  </section>
</main>
