<?php
// views/admin/editar-tarjeta.php
// Variables esperadas: $tarjeta (Tarjeta), $alerts (opcional)
$tarjeta = $tarjeta ?? null;
$alerts = $alerts ?? [];
?>
<main class="admin-page container">
  <section class="card">
    <h2>Editar tarjeta #<?= htmlspecialchars($tarjeta->id ?? '') ?></h2>

    <form method="POST" action="/administrar-tarjeta/editar">
      <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>

      <input type="hidden" name="id" value="<?= htmlspecialchars($tarjeta->id ?? '') ?>">

      <div class="field">
        <label for="codigo">Código (opcional)</label>
        <input id="codigo" name="codigo" type="text" maxlength="77" value="<?= htmlspecialchars($tarjeta->codigo ?? '') ?>">
        <small class="small">Si dejas vacío, se mantiene el código actual. El código debe ser único.</small>
      </div>

      <div class="field">
        <label for="casilla_max">Cantidad de casillas</label>
        <input id="casilla_max" name="casilla_max" type="number" min="1" value="<?= htmlspecialchars($tarjeta->casilla_max ?? 10) ?>">
      </div>

      <div class="actions" style="margin-top:1rem;">
        <button class="btn btn--primary" type="submit">Guardar cambios</button>
        <a class="btn btn--ghost" href="/administrar-tarjeta" style="margin-left:.5rem;">Cancelar</a>
      </div>
    </form>

    <hr style="margin:1rem 0;">

    <div class="small muted">
      <p><strong>Notas:</strong></p>
      <ul>
        <li>Si reduces la cantidad de casillas y hay clientes que ya tienen más casillas completadas, esos registros serán ajustados al nuevo máximo.</li>
        <li>Si quieres cambiar el código QR del local, puedes editar el "Código" arriba (debe ser único).</li>
      </ul>
    </div>
  </section>
</main>
