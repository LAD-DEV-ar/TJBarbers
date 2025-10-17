<main class="admin-page container">
  <header class="admin-header">
    <div class="admin-header__left">
      <h1>Solicitudes de Canje</h1>
      <p class="muted">Aqui puedes ver y aceptar las solicitudes de tus clientes</p>
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
    <?php if (empty($solicitudes)): ?>
      <div class="empty-note">No hay solicitudes pendientes.</div>
    <?php else: ?>
      <div class="table-wrap" aria-live="polite">
        <table class="table" role="table" aria-label="Solicitudes">
        <thead><tr><th>Cliente</th><th>Recompensa</th><th>Casilla</th><th>Solicitada</th><th>Acción</th></tr></thead>
          <tbody>
            <?php foreach($solicitudes as $s): ?>
              <tr>
                <td data-label="Nombre"><?= htmlspecialchars($s['usuario_nombre']) ?> (ID <?= (int)$s['id_usuario'] ?>)</td>
                <td data-label="Canjeo"><?= htmlspecialchars($s['descripcion']) ?></td>
                <td data-label="Casilla"><?= (int)$s['casilla'] ?></td>
                <td data-label="Solicitado en"><?= htmlspecialchars($s['creado_en']) ?></td>
                <td data-label="Accion">
                  <form method="POST" action="/admin/solicitudes/aprobar" style="display:inline;">
                    <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn--primary" type="submit">Aprobar y canjear</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</main>
