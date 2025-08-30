<?php
// views/admin/registrar-visita.php
$alerts = $alerts ?? \Model\ActiveRecord::getAllAlertsAndClear() ?? [];
?>
<main class="admin-page container">

  <section class="card">
    <h2>Registrar visita con QR:</h2>
    <p>Puede registrar una visita de tu cliente escaneando su QR</p>
    <div class="actions">
      <button id="" class="btn btn--primary" type="submit">Escanear QR</button>
    </div>
    

    <form id="formRegistrar" method="POST" action="/registrar-visita" novalidate>
      <h2>Registrar visita con ID de usuario:</h2>
      <p>Podes pedir el id del Usuario e ingresarlo para registrar la visita</p>
      <?php if (function_exists('csrf_input_field')) echo csrf_input_field(); ?>
      <div class="field">
        <label for="cliente_id">ID del cliente</label>
        <input id="cliente_id" name="cliente_id" type="number" min="1" placeholder="Ej: 123">
      </div>

      <div class="actions">
        <button id="btnSubmit" class="btn btn--primary" type="submit">Registrar visita</button>
      </div>
    </form>

    <div id="resultado" style="margin-top:12px;"></div>
  </section>
  <div>
    <label>Cliente ID: <input id="cb_cliente" type="number"></label>
    <label>Recompensa ID: <input id="cb_recompensa" type="number"></label>
    <button id="btnCanjear">Canjear recompensa</button>
    <div id="cb_result"></div>
  </div>
</main>

<script>
(function(){
  const form = document.getElementById('formRegistrar');
  const btnAjax = document.getElementById('btnAjax');
  const resultado = document.getElementById('resultado');

  function getCsrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : null;
  }

  async function ajaxRegistrar(payload) {
    const csrf = getCsrf();
    const headers = { 'Content-Type': 'application/json' };
    if (csrf) headers['X-CSRF-Token'] = csrf;

    resultado.innerHTML = '<div class="small">Enviando...</div>';
    try {
      const res = await fetch('/api/registrar-visita', {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(payload)
      });

      // manejar códigos
      if (res.status === 401) { resultado.innerHTML = '<div class="small" style="color:#ef4444">No autorizado</div>'; return; }
      if (res.status === 403) { resultado.innerHTML = '<div class="small" style="color:#ef4444">No tienes permisos</div>'; return; }

      const data = await res.json();
      if (!res.ok) {
        resultado.innerHTML = '<div class="small" style="color:#ef4444">' + (data.error || 'Error') + '</div>';
        return;
      }

      // éxito
      resultado.innerHTML = '<div class="small" style="color:#16a34a">Visita registrada: ' + data.casilla_actual + ' / ' + data.casilla_max + (data.alcanzo ? ' — ¡Alcanzó!' : '') + '</div>';
    } catch (err) {
      console.error(err);
      resultado.innerHTML = '<div class="small" style="color:#ef4444">Error de red</div>';
    }
  }

  // botón AJAX
  btnAjax.addEventListener('click', function(){
    const cliente = document.getElementById('cliente_id').value.trim();
    const codigo = document.getElementById('codigo').value.trim();
    const payload = {};

    if (cliente) payload.cliente_id = Number(cliente);
    else if (codigo) payload.codigo = codigo;
    else {
      resultado.innerHTML = '<div class="small" style="color:#ef4444">Ingresa el ID del cliente</div>';
      return;
    }

    ajaxRegistrar(payload);
  });
  document.getElementById('btnCanjear').addEventListener('click', async () => {
  const cliente = Number(document.getElementById('cb_cliente').value);
  const recompensa = Number(document.getElementById('cb_recompensa').value);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const headers = { 'Content-Type': 'application/json' };
  if (csrf) headers['X-CSRF-Token'] = csrf;

  try {
    const res = await fetch('/api/tarjeta/canjear', {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify({ cliente_id: cliente, id_recompensa: recompensa })
    });
    const data = await res.json();
    const out = document.getElementById('cb_result');
    if (!res.ok) {
      out.innerHTML = '<span style="color:red;">' + (data.error || 'Error') + '</span>';
      return;
    }
    out.innerHTML = '<span style="color:green;">' + (data.mensaje || 'Canje OK') + '</span>';
  } catch (err) {
    console.error(err);
    document.getElementById('cb_result').innerHTML = '<span style="color:red;">Error de red</span>';
  }
});

  // formulario normal (lo dejamos enviar para fallback)
  form.addEventListener('submit', function(e){
    // deja el submit normal — si preferís evitar redirect, descomenta e usa fetch
    // e.preventDefault();
    // const cliente = document.getElementById('cliente_id').value.trim();
    // const codigo = document.getElementById('codigo').value.trim();
    // ...
  });
})();
</script>
