<?php
// views/admin/recompensas/editar.php
// Variables: $tarjeta, $recompensa, $alerts
?>
<main class="admin-page container">
  <h1>Editar Recompensa — Tarjeta #<?= htmlspecialchars($tarjeta->id) ?></h1>

  <?php if (!empty($alerts)): foreach($alerts as $a): ?>
    <div class="alert <?= htmlspecialchars($a['type'] ?? 'info') ?>"><?= htmlspecialchars($a['message']) ?></div>
  <?php endforeach; endif; ?>

  <form method="POST" action="/admin/recompensas/editar" id="formEditarRecompensa">
    <input type="hidden" name="id" value="<?= (int)$recompensa->id ?>">
    <div class="field">
      <label for="casilla">Casilla (número)</label>
      <input id="casilla" name="casilla" type="number" min="1" value="<?= (int)$recompensa->casilla ?>" required>
    </div>
    <div class="field">
      <label for="descripcion">Descripción</label>
      <input id="descripcion" name="descripcion" type="text" maxlength="255" value="<?= htmlspecialchars($recompensa->descripcion) ?>" required>
    </div>

    <div class="actions">
      <button class="btn btn--primary" type="submit">Guardar cambios</button>
      <a class="btn btn--ghost" href="/admin/recompensas?tarjeta_id=<?= (int)$tarjeta->id ?>">Cancelar</a>
    </div>
  </form>

  <script>
  document.getElementById('formEditarRecompensa').addEventListener('submit', function(e){
    const cas = Number(document.getElementById('casilla').value);
    const desc = document.getElementById('descripcion').value.trim();
    if (!cas || cas <= 0) { e.preventDefault(); alert('Casilla inválida'); return false; }
    if (!desc) { e.preventDefault(); alert('Descripcion requerida'); return false; }
  });
  </script>
</main>
