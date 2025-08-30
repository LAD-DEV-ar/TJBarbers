<?php
$alerts = $alerts ?? \Model\ActiveRecord::getAllAlertsAndClear();
$prefill = $prefill_code ?? '';
?>
<main class="container">

  <section class="card">
    <h1>Vincular Tarjeta</h1>
    <p>Si tienes un código de tarjeta, pégalo abajo o escanea el QR.</p>

    <form method="POST" action="/tarjeta/vincular">
      <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>
      <div class="field">
        <label for="codigo">Código</label>
        <input id="codigo" name="codigo" type="text" value="<?= htmlspecialchars($prefill) ?>">
      </div>
      <div class="actions">
        <button class="btn btn--primary" type="submit">Vincular</button>
      </div>
    </form>
  </section>
</main>
