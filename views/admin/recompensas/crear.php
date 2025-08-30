<?php
// views/admin/recompensas/crear.php
// Variables: $tarjeta, $recompensa, $alerts
?>
<main class="admin-page container">
  <h1>Crear Recompensa — Tarjeta #<?= htmlspecialchars($tarjeta->id) ?></h1>

  <form method="POST" action="/admin/recompensas/crear" id="formCrearRecompensa">
    <input type="hidden" name="id_tarjeta" value="<?= (int)$tarjeta->id ?>">
    <div class="field">
      <label for="casilla">Casilla (número)</label>
      <!-- en el form de crear recompensa -->
      <input id="casilla" name="casilla" type="number" min="1" max="<?= (int)($tarjeta->casilla_max ?? 10) ?>" value="<?= htmlspecialchars($_POST['casilla'] ?? 1) ?>" class="input" required>
      <small class="small muted">Ingrese un valor entre 1 y <?= (int)($tarjeta->casilla_max ?? 10) ?>.</small>
    </div>
    <div class="field">
      <label for="descripcion">Descripción</label>
      <input id="descripcion" name="descripcion" type="text" maxlength="255" value="<?= htmlspecialchars($recompensa->descripcion ?? '') ?>" required>
    </div>

    <div class="actions">
      <button class="btn btn--primary" type="submit">Crear</button>
      <a class="btn btn--ghost" href="/admin/recompensas?tarjeta_id=<?= $tarjeta->id ?>">Cancelar</a>
    </div>
  </form>

  <script>
  // validación cliente simple
  document.getElementById('formCrearRecompensa').addEventListener('submit', function(e){
    const cas = Number(document.getElementById('casilla').value);
    const desc = document.getElementById('descripcion').value.trim();
    if (!cas || cas <= 0) { e.preventDefault(); alert('Casilla inválida'); return false; }
    if (!desc) { e.preventDefault(); alert('Descripcion requerida'); return false; }
  });
  </script>
</main>
