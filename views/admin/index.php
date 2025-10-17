<?php
// views/admin/index.php
// Variables esperadas: $miTarjeta, $recompensas, $solicitudes, $alerts
$miTarjeta   = $miTarjeta ?? null;
$recompensas = $recompensas ?? [];
$solicitudes = $solicitudes ?? [];
$alerts      = $alerts ?? [];
?>
<main class="admin container" role="main">
  <header class="admin-header">
    <div class="admin-header__left">
      <h1>Panel de Administración</h1>
      <p class="muted">Gestiona tu tarjeta, registra visitas y atiende solicitudes de canje</p>
    </div>

    <nav class="admin-header__right" aria-label="acciones admin">
      <a class="btn btn--ghost" href="/administrar-tarjeta">Mi tarjeta</a>
      <a class="btn btn--ghost" href="/admin/recompensas">Recompensas</a>
      <a class="btn btn--ghost" href="/admin/solicitudes">Solicitudes</a>
      <a class="btn btn--ghost" href="/admin/clientes">Clientes</a>
      <a class="btn btn--ghost" href="/logout">Cerrar sesión</a>
    </nav>
  </header>

  <section class="grid-layout" role="region" aria-label="panel principal">
    <!-- LEFT COLUMN -->
    <aside class="col-left" role="complementary">
      <div class="card">
        <h2 class="card-title">Mi tarjeta</h2>

        <?php if (empty($miTarjeta) || empty($miTarjeta->id)): ?>

          <div class="card">
            <h2 class="card-title">Crear tarjeta para tu barbería</h2>
            <p class="muted">No tienes una tarjeta asignada. Completa el formulario para crearla y se vinculará automáticamente a tu cuenta.</p>

            <!-- Formulario crear tarjeta -->
            <form method="POST" action="/administrar-tarjeta" class="form-create-tarjeta">
              <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>

              <div class="field">
                <label for="casilla_max">Cantidad de casillas</label>
                <input id="casilla_max" name="casilla_max" type="number" min="1" value="<?= htmlspecialchars($_POST['casilla_max'] ?? 10) ?>" class="input" />
                <small class="small muted">Número de casillas que tendrá la tarjeta (p. ej. 10).</small>
              </div>

              <div class="field">
                <label for="codigo_opcional">Código (opcional)</label>
                <input id="codigo_opcional" name="codigo_opcional" type="text" placeholder="Dejar vacío para generar uno único automáticamente" class="input" value="<?= htmlspecialchars($_POST['codigo_opcional'] ?? '') ?>" />
                <small class="small muted">Puedes dejarlo vacío y el sistema generará un código seguro único (recomendado).</small>
              </div>

              <div class="actions-row">
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
            <img class="qr" src="<?= htmlspecialchars($qrUrl, ENT_QUOTES) ?>" alt="QR tarjeta" />
            <div class="tarjeta-meta">
              <div><strong>ID</strong> <?= (int)$miTarjeta->id ?></div>
              <div class="muted"><strong>Código</strong> <code class="wrap-code"><?= htmlspecialchars($miTarjeta->codigo) ?></code></div>
              <div class="muted"><strong>Casillas</strong> <?= (int)($miTarjeta->casilla_max ?? 0) ?></div>
              <div class="actions-row">
                <a class="btn btn--ghost" href="/admin/recompensas">Gestionar recompensas</a>
                <a class="btn btn--ghost" href="/administrar-tarjeta/editar?id=<?= (int)$miTarjeta->id ?>">Editar</a>
              </div>
            </div>
          </div>

          <div class="link-copy">
            <label class="small muted" for="miTarjetaLink">Enlace para vincular clientes</label>
            <div class="copy-row">
              <input id="miTarjetaLink" class="input-copy wrap-code" readonly aria-readonly="true" value="<?= htmlspecialchars($link) ?>" />
              <button id="copyLinkBtn" class="btn btn--ghost" type="button" aria-controls="miTarjetaLink">Copiar</button>
            </div>
          </div>
          <!-- Registrar visita -->
      <div class="card" style="margin-top:16px;">
        <h3 class="card-title">Registrar visita</h3>
        <p class="muted">Indica ID de usuario o teléfono para registrar su visita y adelantar un casilla en el camino del usuario</p>

        <form method="POST" action="/admin/registrar-visita" class="form-visit">
          <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>

          <div class="field">
            <label for="cliente_id">ID usuario (opcional)</label>
            <input id="cliente_id" name="cliente_id" type="number" placeholder="Ej: 12" class="input" />
          </div>

          <div class="field">
            <label for="cliente_telefono">Teléfono (opcional)</label>
            <input id="cliente_telefono" name="cliente_telefono" type="text" placeholder="Ej: 1133344455" class="input" />
          </div>

          <div class="actions-row">
            <button class="btn btn--primary" type="submit">Registrar visita</button>
            <span class="small muted">Se procesará y verás el resultado al refrescar.</span>
          </div>
        </form>
      </div>

      <!-- Recompensas -->
      <div class="card" style="margin-top:16px;">
        <h3 class="card-title">🎁 Recompensas</h3>

        <?php if (!empty($recompensas)): ?>
          <ul class="list-clean" style="margin-top:.5rem;">
            <?php foreach ($recompensas as $r): ?>
              <li class="recomp-item">Casilla <?= (int)$r->casilla ?> — <?= htmlspecialchars($r->descripcion) ?></li>
            <?php endforeach; ?>
          </ul>

          <div style="margin-top:.5rem;">
            <a class="btn btn--ghost" href="/admin/recompensas">Gestionar todas</a>
          </div>
        <?php else: ?>
          <p class="muted">No tienes ninguna recompensa establecida para tu tarjeta de fidelidad</p>
          <p class="muted">Crea una: <a class="btn btn--ghost" href="/admin/recompensas">Recompensas</a></p>
        <?php endif; ?>
      </div>
    </aside>

    <!-- RIGHT COLUMN -->
    <div class="col-right" role="main">
      <div class="card header-actions">
        <div>
          <h2 class="card-title">Solicitudes de canje</h2>
          <p class="muted">Lista de solicitudes para tu tarjeta (más nuevo → más viejo).</p>
          <p class="muted">Si recien le acaban de solicitar una solicitud y no la ve, trate de refrescar la pagina</p>
        </div>

        <div class="actions-row">
          <button id="btnRefresh" class="btn btn--ghost" type="button">Refrescar</button>
          <a class="btn btn--ghost" href="/admin/solicitudes">Ver página completa</a>
        </div>
      </div>

      <div class="card solicitudes-card">
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
                <?php
                // Mejor: definimos columnas y renderizamos cada <td data-label="..."> para el modo mobile apilado
                $cols = [
                  ['label' => 'Cliente', 'cell' => function($s){
                    $usuarioNombre = is_array($s) ? ($s['usuario_nombre'] ?? '') : ($s->usuario_nombre ?? '');
                    $usuarioId = is_array($s) ? ($s['id_usuario'] ?? 0) : ($s->id_usuario ?? 0);
                    $display = htmlspecialchars($usuarioNombre ?: "ID {$usuarioId}");
                    $meta = '<div class="muted small">ID ' . (int)$usuarioId . '</div>';
                    return $display . $meta;
                  }],
                  ['label' => 'Recompensa', 'cell' => function($s){
                    $descripcion = is_array($s) ? ($s['descripcion'] ?? '') : ($s->descripcion ?? '');
                    return '<div class="wrap-text">' . htmlspecialchars($descripcion) . '</div>';
                  }],
                  ['label' => 'Casilla', 'cell' => function($s){
                    $casilla = is_array($s) ? ($s['casilla'] ?? '') : ($s->casilla ?? '');
                    return (int)$casilla;
                  }],
                  ['label' => 'Solicitada', 'cell' => function($s){
                    $creado = is_array($s) ? ($s['creado_en'] ?? '') : ($s->creado_en ?? '');
                    $fechaArgentina = $creado ? date("d/m/Y H:i", strtotime($creado)) : '';
                    return htmlspecialchars($fechaArgentina);
                  }],
                  ['label' => 'Estado', 'cell' => function($s){
                    $estado = is_array($s) ? ($s['estado'] ?? 'pending') : ($s->estado ?? 'pending');
                    if ($estado === 'approved') return '<span class="badge badge--success">Aprobada</span>';
                    if ($estado === 'rejected') return '<span class="badge badge--danger">Rechazada</span>';
                    return '<span class="badge badge--warning">Pendiente</span>';
                  }],
                  ['label' => 'Acción', 'cell' => function($s){
                    $estado = is_array($s) ? ($s['estado'] ?? 'pending') : ($s->estado ?? 'pending');
                    $id = is_array($s) ? ($s['id'] ?? 0) : ($s->id ?? 0);
                    if ($estado === 'pending') {
                      $csrf = function_exists('csrf_input_field') ? csrf_input_field() : '';
                      // usar data-confirm para que JS muestre confirmación accesible
                      return '<form method="POST" action="/admin/solicitudes/aprobar" data-confirm="Aprobar y registrar canje?">'
                           . $csrf
                           . '<input type="hidden" name="id" value="' . (int)$id . '">'
                           . '<button class="btn btn--primary" type="submit">Aprobar</button>'
                           . '</form>';
                    } else {
                      return '<span class="muted small">Sin acciones</span>';
                    }
                  }],
                ];

                foreach ($solicitudes as $s):
                  echo '<tr>';
                  foreach ($cols as $col) {
                    $cellHtml = call_user_func($col['cell'], $s);
                    $labelAttr = htmlspecialchars($col['label'], ENT_QUOTES);
                    echo '<td data-label="' . $labelAttr . '">' . $cellHtml . '</td>';
                  }
                  echo '</tr>';
                endforeach;
                ?>
              </tbody>
            </table>
          </div>

        <?php endif; ?>
      </div>
        <?php endif; ?>
      </div>

      
    </div>
  </section>

  <!-- Accessible live region for JS feedback -->
  <div id="adminLiveRegion" aria-live="polite" class="sr-only"></div>

  <!-- JS: copy link, refresh button and confirmation handling -->
  <script>
  (function () {
    'use strict';

    // Ensure DOM loaded
    document.addEventListener('click', function () {
      // no-op to ensure progressive enhancement ok
    });

    // Live region (already present in template)
    var live = document.getElementById('adminLiveRegion');

    function announce(msg) {
      if (!live) return;
      live.textContent = msg;
      // clear after a while so repeated messages are read
      setTimeout(function(){ live.textContent = ''; }, 1800);
    }

    // Copy link button
    var copyBtn = document.getElementById('copyLinkBtn');
    if (copyBtn) {
      copyBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var input = document.getElementById('miTarjetaLink');
        if (!input) { announce('Enlace no disponible'); return; }
        var text = input.value || input.getAttribute('value') || input.textContent || '';
        if (!text) { announce('El enlace está vacío'); return; }

        copyBtn.disabled = true;
        var prev = copyBtn.textContent;
        (function doCopy(){
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
              announce('Copiado al portapapeles');
              copyBtn.textContent = '¡Copiado!';
              setTimeout(function(){ copyBtn.textContent = prev; copyBtn.disabled = false; }, 1200);
            }).catch(function(err){
              fallbackCopy();
            });
          } else {
            fallbackCopy();
          }
        })();

        function fallbackCopy() {
          try {
            input.select && input.select();
            document.execCommand && document.execCommand('copy');
            announce('Copiado');
            copyBtn.textContent = '¡Copiado!';
          } catch (err) {
            announce('No fue posible copiar automáticamente. Selecciona y copia manualmente.');
          } finally {
            setTimeout(function(){ copyBtn.textContent = prev; copyBtn.disabled = false; }, 1200);
            if (window.getSelection) window.getSelection().removeAllRanges();
          }
        }
      });
    }

    // Refresh button (progressive enhancement)
    var btnRefresh = document.getElementById('btnRefresh');
    if (btnRefresh) {
      btnRefresh.addEventListener('click', function () {
        // disable briefly to avoid double clicks
        btnRefresh.disabled = true;
        setTimeout(function(){ btnRefresh.disabled = false; }, 1200);
        location.reload();
      });
    }

    // Confirmation handling for forms: data-confirm on form OR data-confirm on submit button
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM') return;

      var message = form.dataset.confirm || '';
      if (!message) {
        var activeSubmit = form.querySelector('button[type="submit"][data-confirm], input[type="submit"][data-confirm]');
        if (activeSubmit) message = activeSubmit.dataset.confirm || '';
      }

      // fallback: detect known action path (approval)
      if (!message) {
        var action = (form.getAttribute('action') || '').toLowerCase();
        if (action.indexOf('/admin/solicitudes/aprobar') !== -1) {
          message = 'Aprobar y registrar canje?';
        }
      }

      if (message) {
        if (!confirm(message)) {
          e.preventDefault();
          announce('Acción cancelada');
        } else {
          // prevent double submits
          var btn = form.querySelector('button[type="submit"], input[type="submit"]');
          if (btn) { btn.disabled = true; setTimeout(function(){ btn.disabled = false; }, 1500); }
        }
      }
    });

  })();
  </script>
</main>
