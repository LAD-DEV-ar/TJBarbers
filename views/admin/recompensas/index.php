<?php
// views/admin/recompensas/index.php
// Variables esperadas: $tarjeta, $recompensas (array), $alerts
?>
<main class="admin-page container">
  <header class="admin-header">
    <div class="admin-header__left">
      <h1>Recompensas</h1>
      <p class="muted">Aqui puedes crear, editar y eliminar las recompensas existentes</p>
    </div>

    <nav class="admin-header__right" aria-label="acciones admin">
      <a class="btn btn--ghost" href="/administrar-tarjeta">Mi tarjeta</a>
      <a class="btn btn--ghost" href="/admin/recompensas">Recompensas</a>
      <a class="btn btn--ghost" href="/admin/solicitudes">Solicitudes</a>
      <a class="btn btn--ghost" href="/admin/clientes">Clientes</a>
      <a class="btn btn--ghost" href="/logout">Cerrar sesión</a>
    </nav>
  </header>

  <div class="card">
    <a href="/admin/recompensas/crear?tarjeta_id=<?= $tarjeta->id ?>" class="btn btn--primary">Crear una recompensa</a>
    <?php if (empty($recompensas)): ?>
      <div class="empty-note">No hay recompensas configuradas para esta tarjeta.</div>
    <?php else: ?>
      <div class="table-wrap" aria-live="polite">
        <table class="table" role="table" aria-label="Solicitudes">
          <thead>
            <tr><th>Casilla</th><th>Descripción</th><th>Creado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach($recompensas as $r): ?>
              <tr>
                <td data-label="Casilla"><?= (int)$r->casilla ?></td>
                <td data-label="Descripcion"><?= htmlspecialchars($r->descripcion) ?></td>
                <td data-label="Creado en"><?= htmlspecialchars(date("d/m/Y H:i", strtotime($r->creado_en))) ?></td>
                <td data-label="Acciones">
                  <a href="/admin/recompensas/editar?id=<?= $r->id ?>" class="btn btn--primary">Editar</a> -
                  <form method="POST" action="/admin/recompensas/eliminar" style="display:inline" onsubmit="return confirm('Eliminar recompensa?')">
                    <input type="hidden" name="id" value="<?= $r->id ?>">
                    <input type="hidden" name="tarjeta_id" value="<?= $tarjeta->id ?>">
                    <button type="submit" class="link-button btn btn--critic">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>
