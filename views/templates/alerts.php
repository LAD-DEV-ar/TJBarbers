<?php
/**
 * views/templates/alerts.php
 * Template centralizado para mostrar alertas.
 *
 * - Si la vista ya pasó $alerts lo usará.
 * - Si no, intentará leer alertas desde ActiveRecord::getAllAlertsAndClear()
 *   (lee flash en $_SESSION + alertas en memoria y las limpia).
 *
 * Guardar aquí para requerirlo desde el layout o desde vistas específicas.
 */

if (!isset($alerts) || !is_array($alerts)) {
    // Intentar leer desde ActiveRecord (trae y limpia flash + memoria)
    if (class_exists('\Model\ActiveRecord')) {
        $alerts = \Model\ActiveRecord::getAllAlertsAndClear();
    } else {
        $alerts = [];
    }
}

// Normalizar formato a lista plana: [ ['type'=>'success','message'=>'...'], ... ]
$flat = [];

foreach ($alerts as $a) {
    if (is_array($a) && isset($a['type']) && isset($a['message'])) {
        $flat[] = $a;
        continue;
    }

    // Soporte para formatos legacy: ['error' => ['m1','m2']]
    if (is_array($a)) {
        foreach ($a as $type => $msgs) {
            if (is_array($msgs)) {
                foreach ($msgs as $m) $flat[] = ['type' => $type, 'message' => $m];
            }
        }
        continue;
    }

    // Soporte para strings simples -> tipo info
    if (is_string($a)) {
        $flat[] = ['type' => 'info', 'message' => $a];
    }
}

if (empty($flat)) return; // no hay alertas, no renderiza nada

// Mapeo tipo -> clase e icono (ajustá clases si querés)
function _alert_meta($type) {
    $map = [
        'success' => ['class'=>'alert-success','icon'=>'✓'],
        'error'   => ['class'=>'alert-error','icon'=>'✕'],
        'warning' => ['class'=>'alert-warning','icon'=>'!'],
        'info'    => ['class'=>'alert-info','icon'=>'i'],
    ];
    return $map[$type] ?? $map['info'];
}
?>

<div class="alerts-wrapper" aria-live="polite" role="status">
  <?php foreach ($flat as $alert):
      $type = htmlspecialchars($alert['type'] ?? 'info');
      $msg  = htmlspecialchars($alert['message'] ?? '');
      $meta = _alert_meta($type);
      $class = $meta['class'];
      $icon  = $meta['icon'];
  ?>
    <div class="alert <?= $class ?>" data-alert-type="<?= $type ?>">
      <div class="alert-left" aria-hidden="true"><?= $icon ?></div>
      <div class="alert-body">
        <div class="alert-message"><?= $msg ?></div>
      </div>
      <button class="alert-close" aria-label="Cerrar">&times;</button>
    </div>
  <?php endforeach; ?>
</div>