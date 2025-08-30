<?php
// views/admin/index.php
// Variables esperadas: $miTarjeta, $recompensas, $solicitudes, $alerts
$miTarjeta   = $miTarjeta ?? null;
$recompensas = $recompensas ?? [];
$solicitudes = $solicitudes ?? [];
$alerts      = $alerts ?? [];
?>
<main class="admin container">
  <header class="admin-header">
    <div class="admin-header__left">
      <h1>Panel de Administración</h1>
      <p class="muted">Gestiona tu tarjeta, registra visitas y atiende solicitudes de canje</p>
    </div>

    <nav class="admin-header__right" aria-label="acciones admin">
      <a class="btn btn--ghost" href="/administrar-tarjeta">Mi tarjeta</a>
      <a class="btn btn--ghost" href="/admin/recompensas">Recompensas</a>
      <a class="btn btn--ghost" href="/admin/solicitudes">Solicitudes (ver)</a>
      <a class="btn btn--ghost" href="/logout">Cerrar sesión</a>
    </nav>
  </header>

  <section class="grid-layout" role="region" aria-label="panel principal">
    <!-- LEFT -->
    <aside class="col-left" role="complementary">
      <div class="card">
        <h2 class="card-title">Mi tarjeta</h2>

        <?php if (empty($miTarjeta) || empty($miTarjeta->id)): ?>
        <div class="card">
          <h2 class="card-title">Crear tarjeta para tu barbería</h2>

          <p class="muted">No tienes una tarjeta asignada. Completa el formulario para crearla y se vinculará automáticamente a tu cuenta.</p>

          <!-- Si tu template de alertas ya imprime errores, se mostrarán arriba -->
          <form method="POST" action="/administrar-tarjeta" style="margin-top:12px;">
            <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>

            <div class="field">
              <label for="casilla_max">Cantidad de casillas</label>
              <input id="casilla_max" name="casilla_max" type="number" min="1" value="<?= htmlspecialchars($_POST['casilla_max'] ?? 10) ?>" class="input">
              <small class="small muted">Número de casillas que tendrá la tarjeta (p. ej. 10).</small>
            </div>

            <div class="field" style="margin-top:12px;">
              <label for="codigo_opcional">Código (opcional)</label>
              <input id="codigo_opcional" name="codigo_opcional" type="text" placeholder="Dejar vacío para generar uno único automáticamente" class="input" value="<?= htmlspecialchars($_POST['codigo_opcional'] ?? '') ?>">
              <small class="small muted">Puedes dejarlo vacío y el sistema generará un código seguro único (recomendado).</small>
            </div>

            <div style="margin-top:14px; display:flex; gap:.6rem; align-items:center;">
              <button class="btn btn--primary" type="submit">Crear tarjeta y vincular</button>
              <a class="btn btn--ghost" href="/tarjeta/vincular">Vincular mediante código/QR</a>
            </div>
          </form>
        </div>
      <?php else: ?>
          <?php
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $link = $scheme . '://' . $host . '/tarjeta/vincular?code=' . rawurlencode($miTarjeta->codigo);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($link);
          ?>
          <div class="tarjeta-compact">
            <img class="qr" src="<?= htmlspecialchars($qrUrl, ENT_QUOTES) ?>" alt="QR tarjeta">
            <div class="tarjeta-meta">
              <div><strong>ID</strong> <?= (int)$miTarjeta->id ?></div>
              <div class="muted" style="margin-top:.25rem;"><strong>Código</strong> <code class="wrap-code"><?= htmlspecialchars($miTarjeta->codigo) ?></code></div>
              <div class="muted" style="margin-top:.25rem;"><strong>Casillas</strong> <?= (int)($miTarjeta->casilla_max ?? 0) ?></div>
              <div style="margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                <a class="btn btn--ghost" href="/admin/recompensas">Gestionar recompensas</a>
                <a class="btn btn--ghost" href="/administrar-tarjeta/editar?id=<?= (int)$miTarjeta->id ?>">Editar</a>
              </div>
            </div>
          </div>

          <div class="link-copy" style="margin-top:.8rem;">
            <label class="small muted">Enlace para vincular clientes</label>
            <div style="display:flex; gap:.5rem; margin-top:.4rem;">
              <input id="miTarjetaLink" class="input-copy wrap-code" readonly value="<?= htmlspecialchars($link) ?>">
              <button id="copyLinkBtn" class="btn btn--ghost">Copiar</button>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="card" style="margin-top:16px;">
        <h3 class="card-title">Registrar visita</h3>
        <p class="muted" style="margin-top:0;">Indica ID de usuario o teléfono. El registro se procesará por POST (sin AJAX).</p>

        <form method="POST" action="/admin/registrar-visita" class="form-visit">
          <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>
          <div class="field">
            <label for="cliente_id">ID usuario (opcional)</label>
            <input id="cliente_id" name="cliente_id" type="number" placeholder="Ej: 12" class="input">
          </div>
          <div class="field">
            <label for="cliente_telefono">Teléfono (opcional)</label>
            <input id="cliente_telefono" name="cliente_telefono" type="text" placeholder="Ej: 1133344455" class="input">
          </div>

          <div style="display:flex; gap:.5rem; align-items:center; margin-top:.5rem;">
            <button class="btn btn--primary" type="submit">Registrar visita</button>
            <span class="small muted">Se procesará y verás el resultado al refrescar.</span>
          </div>
        </form>
      </div>

      <?php if (!empty($recompensas)): ?>
      <div class="card" style="margin-top:16px;">
        <h3 class="card-title">🎁 Recompensas (breve)</h3>
        <ul class="list-clean" style="margin-top:.5rem;">
          <?php foreach ($recompensas as $r): ?>
            <li class="recomp-item">Casilla <?= (int)$r->casilla ?> — <?= htmlspecialchars($r->descripcion) ?></li>
          <?php endforeach; ?>
        </ul>
        <div style="margin-top:.5rem;"><a class="btn btn--ghost" href="/admin/recompensas">Gestionar todas</a></div>
      </div>
      <?php endif; ?>
    </aside>

    <!-- RIGHT -->
    <main class="col-right" role="main">
      <div class="card header-actions" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <h2 class="card-title" style="margin:0;">Solicitudes de canje</h2>
          <p class="muted" style="margin:.25rem 0 0 0;">Lista de solicitudes para tu tarjeta (más nuevo → más viejo).</p>
        </div>

        <div style="display:flex; gap:.5rem; align-items:center;">
          <button id="btnRefresh" class="btn btn--ghost" type="button" onclick="location.reload()">Refrescar</button>
          <a class="btn btn--ghost" href="/admin/solicitudes">Ver página completa</a>
        </div>
      </div>

      <div class="card solicitudes-card" style="margin-top:12px;">
        <?php if (empty($solicitudes)): ?>
          <div class="empty-note">No hay solicitudes pendientes.</div>
        <?php else: ?>
          <div class="table-wrap" aria-live="polite">
            <table class="table" role="table" aria-label="Solicitudes">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Recompensa</th>
                  <th>Casilla</th>
                  <th>Solicitada</th>
                  <th>Estado</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($solicitudes as $s):
                    $id = is_array($s) ? ($s['id'] ?? 0) : ($s->id ?? 0);
                    $usuarioNombre = is_array($s) ? ($s['usuario_nombre'] ?? '') : ($s->usuario_nombre ?? '');
                    $usuarioId = is_array($s) ? ($s['id_usuario'] ?? 0) : ($s->id_usuario ?? 0);
                    $descripcion = is_array($s) ? ($s['descripcion'] ?? '') : ($s->descripcion ?? '');
                    $casilla = is_array($s) ? ($s['casilla'] ?? '') : ($s->casilla ?? '');
                    $creado = is_array($s) ? ($s['creado_en'] ?? '') : ($s->creado_en ?? '');
                    $fechaArgentina = date("d/m/Y H:i", strtotime($creado));
                    $estado = is_array($s) ? ($s['estado'] ?? 'pending') : ($s->estado ?? 'pending');
                ?>
                <tr>
                  <td style="min-width:160px;">
                    <div class="td-usuario"><?= htmlspecialchars($usuarioNombre ?: "ID {$usuarioId}") ?></div>
                    <div class="muted small">ID <?= (int)$usuarioId ?></div>
                  </td>
                  <td style="min-width:220px; max-width:320px;"><div class="wrap-text"><?= htmlspecialchars($descripcion) ?></div></td>
                  <td><?= (int)$casilla ?></td>
                  <td style="white-space:nowrap;"><?= htmlspecialchars($fechaArgentina) ?></td>
                  <td>
                    <?php if ($estado === 'approved'): ?>
                      <span class="badge badge--success">Aprobada</span>
                    <?php elseif ($estado === 'rejected'): ?>
                      <span class="badge badge--danger">Rechazada</span>
                    <?php else: ?>
                      <span class="badge badge--warning">Pendiente</span>
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap;">
                    <?php if ($estado === 'pending'): ?>
                      <form method="POST" action="/admin/solicitudes/aprobar" onsubmit="return confirm('Aprobar y registrar canje?')">
                        <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <button class="btn btn--primary" type="submit">Aprobar</button>
                      </form>
                    <?php else: ?>
                      <span class="muted small">Sin acciones</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </section>
</main>

<script>
(function(){
  const copyBtn = document.getElementById('copyLinkBtn');
  const input = document.getElementById('miTarjetaLink');
  if (copyBtn && input) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(input.value);
        copyBtn.textContent = 'Copiado';
        setTimeout(()=> copyBtn.textContent = 'Copiar', 1400);
      } catch (e) {
        alert('No se pudo copiar. Copia manualmente: ' + input.value);
      }
    });
  }
})();
</script>
