
<main class="admin container">
  <h1>Solicitudes de canje</h1>
  <?php if (empty($solicitudes)): ?>
    <div class="card">No hay solicitudes pendientes.</div>
  <?php else: ?>
    <table class="table card">
      <thead><tr><th>Cliente</th><th>Recompensa</th><th>Casilla</th><th>Solicitada</th><th>Acción</th></tr></thead>
      <tbody>
        <?php foreach($solicitudes as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['usuario_nombre']) ?> (ID <?= (int)$s['id_usuario'] ?>)</td>
            <td><?= htmlspecialchars($s['descripcion']) ?></td>
            <td><?= (int)$s['casilla'] ?></td>
            <td><?= htmlspecialchars($s['creado_en']) ?></td>
            <td>
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
</main>
