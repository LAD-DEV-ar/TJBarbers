<?php
// views/admin/recompensas/index.php
// Variables esperadas: $tarjeta, $recompensas (array), $alerts
?>
<main class="admin-page container">
  <h1>Recompensas — Tarjeta #<?= htmlspecialchars($tarjeta->id) ?></h1>
  <div style="margin-bottom:1rem;">
    <a href="/admin/recompensas/crear?tarjeta_id=<?= $tarjeta->id ?>" class="btn btn--primary">Crear recompensa</a>
    <a href="/administrar-tarjeta" class="btn btn--ghost">Volver a Tarjetas</a>
  </div>

  <?php if (empty($recompensas)): ?>
    <div class="card">No hay recompensas configuradas para esta tarjeta.</div>
  <?php else: ?>
    <table style="width:100%; border-collapse:collapse;">
      <thead>
        <tr><th>Casilla</th><th>Descripción</th><th>Creado</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach($recompensas as $r): ?>
          <tr>
            <td><?= (int)$r->casilla ?></td>
            <td><?= htmlspecialchars($r->descripcion) ?></td>
            <td><?= htmlspecialchars($r->creado_en ?? '') ?></td>
            <td>
              <a href="/admin/recompensas/editar?id=<?= $r->id ?>">Editar</a> |
              <form method="POST" action="/admin/recompensas/eliminar" style="display:inline" onsubmit="return confirm('Eliminar recompensa?')">
                <input type="hidden" name="id" value="<?= $r->id ?>">
                <input type="hidden" name="tarjeta_id" value="<?= $tarjeta->id ?>">
                <button type="submit" class="link-button">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
