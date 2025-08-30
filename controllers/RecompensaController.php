<?php
namespace Controllers;

use MVC\Router;
use Model\ActiveRecord;
use Model\Recompensa;
use Model\Tarjeta;
use Model\Usuario;

class RecompensaController {

    // LISTAR recompensas para la tarjeta del admin
    public static function index(Router $router) {
        // Debe requerir que el barbero tenga tarjeta
        require_barbero_con_tarjeta('/');

        start_session_if_needed();
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);

        // seguridad extra: si viene tarjeta_id por GET, solo usarla para validar (no confiar)
        if (isset($_GET['tarjeta_id'])) {
            $given = (int)$_GET['tarjeta_id'];
            if ($given !== $tarjetaId) {
                ActiveRecord::addAlert('error','No autorizado para ver esa tarjeta', true);
                header('Location: /administrar-tarjeta');
                exit;
            }
        }

        $tarjeta = Tarjeta::find($tarjetaId);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error','Tarjeta no encontrada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Usar SQL seguro: todos los valores son ints, pero mejor usar el helper de ActiveRecord o prepared stmt.
        // Aquí aprovecho ActiveRecord::SQL pero forzando cast int (ya lo hacés). Alternativa: crear método en ActiveRecord para whereMultiple.
        $recompensas = Recompensa::SQL("SELECT * FROM recompensas WHERE id_tarjeta = " . (int)$tarjetaId . " ORDER BY casilla ASC");
        $alerts = ActiveRecord::getAllAlertsAndClear();

        $router->render('admin/recompensas/index', [
            'tarjeta' => $tarjeta,
            'recompensas' => $recompensas,
            'alerts' => $alerts
        ]);
    }

    // FORM crear (usa tarjeta del admin)
    public static function crearForm(Router $router) {
        require_barbero_con_tarjeta('/');

        start_session_if_needed();
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
        $tarjeta = Tarjeta::find($tarjetaId);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error','Tarjeta no encontrada', true);
            header('Location:/administrar-tarjeta');
            exit;
        }

        // Si hay old inputs guardados en sesión (por fallo), úsalos
        $old = $_SESSION['old_recompensa'] ?? null;
        if ($old) unset($_SESSION['old_recompensa']);

        $recompensa = new Recompensa([
            'id_tarjeta' => $tarjetaId,
            'casilla' => $old['casilla'] ?? null,
            'descripcion' => $old['descripcion'] ?? ''
        ]);

        $alerts = ActiveRecord::getAllAlertsAndClear();

        $router->render('admin/recompensas/crear', [
            'tarjeta' => $tarjeta,
            'recompensa' => $recompensa,
            'alerts' => $alerts
        ]);
    }

    // PROCESAR creación: toma tarjeta desde sesión (AHORA: validaciones reforzadas)
    public static function crearRecompensa(Router $router) {
        // requiere que sea barbero con tarjeta -> obliga id_tarjeta en session
        require_barbero_con_tarjeta('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ActiveRecord::addAlert('error', 'Método inválido', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Validar CSRF si tienes helper (opcional pero recomendado)
        if (function_exists('verify_csrf') && !verify_csrf()) {
            ActiveRecord::addAlert('error', 'Token CSRF inválido', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        start_session_if_needed();
        // !!! Forzar id_tarjeta desde sesión: no confiar en POST
        $id_tarjeta = (int)($_SESSION['id_tarjeta'] ?? 0);

        // leer inputs (casilla y descripcion)
        $casilla = isset($_POST['casilla']) ? (int)$_POST['casilla'] : null;
        $descripcion = trim($_POST['descripcion'] ?? '');

        // Guardar old inputs por si hay error para repoblar el form
        $_SESSION['old_recompensa'] = [
            'casilla' => $casilla,
            'descripcion' => $descripcion
        ];

        if (empty($id_tarjeta) || $id_tarjeta <= 0) {
            ActiveRecord::addAlert('error','Tarjeta inválida o no asociada a tu cuenta', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        if (empty($casilla) || !is_int($casilla) || $casilla <= 0) {
            ActiveRecord::addAlert('error','La casilla debe ser un número entero mayor que 0.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        $tarjeta = Tarjeta::find($id_tarjeta);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error','Tarjeta no encontrada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        $casilla_max = (int)($tarjeta->casilla_max ?? 0);
        if ($casilla_max <= 0) {
            ActiveRecord::addAlert('error','La tarjeta no tiene un número válido de casillas.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        if ($casilla > $casilla_max) {
            ActiveRecord::addAlert('error', "La casilla indicada ({$casilla}) excede el máximo ({$casilla_max}) de esta tarjeta.", true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Verificar duplicado para esta tarjeta+casilla (prepared statement para seguridad)
        $db = ActiveRecord::getDB();
        $exists = false;
        if ($db) {
            // mysqli
            $stmt = $db->prepare("SELECT id FROM recompensas WHERE id_tarjeta = ? AND casilla = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $id_tarjeta, $casilla);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->fetch_assoc()) $exists = true;
                $stmt->close();
            }
        }

        if ($exists) {
            ActiveRecord::addAlert('error','Ya existe una recompensa en esa casilla para esta tarjeta.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Crear y validar vía modelo (defensa en profundidad)
        $recompensa = new Recompensa([
            'id_tarjeta' => $id_tarjeta,
            'casilla' => $casilla,
            'descripcion' => $descripcion
        ]);

        // Este método debe comprobar casilla <= casilla_max internamente también
        if (method_exists($recompensa, 'validarNueva')) {
            $recompensa->validarNueva(true);
            $errs = Recompensa::getAlertas();
            if (!empty($errs)) {
                foreach ($errs as $type => $msgs) {
                    foreach ($msgs as $m) ActiveRecord::addAlert($type, $m);
                }
                header('Location: /administrar-tarjeta');
                exit;
            }
        }

        // Guardar y manejar errores (incluye posibilidad de unique constraint en BD)
        try {
            $ok = $recompensa->guardar();
        } catch (\Throwable $e) {
            error_log('Error al guardar recompensa: ' . $e->getMessage());
            ActiveRecord::addAlert('error','Error al crear la recompensa (excepción).', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        if ($ok) {
            // limpiar old
            unset($_SESSION['old_recompensa']);
            ActiveRecord::addAlert('success', 'Recompensa creada correctamente', true);
        } else {
            ActiveRecord::addAlert('error', 'Error al crear la recompensa', true);
        }

        header('Location: /administrar-tarjeta');
        exit;
    }

    // FORM editar: sólo si la recompensa pertenece a la tarjeta del admin
    public static function editarForm(Router $router) {
        require_barbero_con_tarjeta('/');

        start_session_if_needed();
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            ActiveRecord::addAlert('error','Recompensa no indicada', true);
            header('Location: /admin/recompensas');
            exit;
        }

        $recompensa = Recompensa::find($id);
        if (!$recompensa || (int)$recompensa->id_tarjeta !== $tarjetaId) {
            ActiveRecord::addAlert('error','No autorizado o recompensa no encontrada', true);
            header('Location: /admin/recompensas');
            exit;
        }

        $tarjeta = Tarjeta::find($tarjetaId);
        $alerts = ActiveRecord::getAllAlertsAndClear();

        $router->render('admin/recompensas/editar', [
            'tarjeta' => $tarjeta,
            'recompensa' => $recompensa,
            'alerts' => $alerts
        ]);
    }

    // PROCESAR edición: sólo si pertenece a la tarjeta del admin
    public static function editarSubmit(Router $router) {
        require_barbero_con_tarjeta('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /administrar-tarjeta');
            exit;
        }

        // CSRF
        if (function_exists('verify_csrf') && !verify_csrf()) {
            ActiveRecord::addAlert('error', 'Token CSRF inválido', true);
            header('Location: /admin/recompensas');
            exit;
        }

        start_session_if_needed();
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $casilla = isset($_POST['casilla']) ? (int)$_POST['casilla'] : 0;
        $descripcion = trim($_POST['descripcion'] ?? '');

        $recompensa = Recompensa::find($id);
        if (!$recompensa || (int)$recompensa->id_tarjeta !== $tarjetaId) {
            ActiveRecord::addAlert('error','No autorizado o recompensa no encontrada', true);
            header('Location: /admin/recompensas');
            exit;
        }

        if ($casilla <= 0) {
            ActiveRecord::addAlert('error','Casilla inválida', true);
            header('Location: /admin/recompensas');
            exit;
        }

        $tarjeta = Tarjeta::find($tarjetaId);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error','Tarjeta no encontrada', true);
            header('Location: /admin/recompensas');
            exit;
        }

        $casilla_max = (int)($tarjeta->casilla_max ?? 0);
        if ($casilla > $casilla_max) {
            ActiveRecord::addAlert('error', "La casilla indicada ({$casilla}) excede el máximo ({$casilla_max}).", true);
            // re-render form con errores y datos actuales
            $flat = [['type'=>'error','message'=>"La casilla indicada ({$casilla}) excede el máximo ({$casilla_max})."]];
            $router->render('admin/recompensas/editar', [
                'tarjeta' => $tarjeta,
                'recompensa' => $recompensa,
                'alerts' => $flat
            ]);
            return;
        }

        // Evitar duplicado: comprobar si otra recompensa ya ocupa esa casilla (excluyendo la actual)
        $db = ActiveRecord::getDB();
        $exists = false;
        if ($db) {
            $stmt = $db->prepare("SELECT id FROM recompensas WHERE id_tarjeta = ? AND casilla = ? AND id != ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('iii', $tarjetaId, $casilla, $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->fetch_assoc()) $exists = true;
                $stmt->close();
            }
        }

        if ($exists) {
            ActiveRecord::addAlert('error','Ya existe otra recompensa en esa casilla.', true);
            header('Location: /admin/recompensas');
            exit;
        }

        // aplicar cambios y validar en el modelo (si tienes un validarEdicion, mejor; aquí uso validarNueva para consistencia)
        $recompensa->casilla = $casilla;
        $recompensa->descripcion = $descripcion;
        if (method_exists($recompensa, 'validarNueva')) {
            $recompensa->validarNueva(false); // false = no check existencia si lo deseas
            $errs = Recompensa::getAlertas();
            if (!empty($errs)) {
                $flat = [];
                foreach ($errs as $type => $msgs) foreach ($msgs as $m) $flat[] = ['type'=>$type,'message'=>$m];
                $router->render('admin/recompensas/editar', [
                    'tarjeta' => $tarjeta,
                    'recompensa' => $recompensa,
                    'alerts' => $flat
                ]);
                return;
            }
        }

        try {
            $ok = $recompensa->guardar();
            if ($ok) ActiveRecord::addAlert('success','Recompensa actualizada', true);
            else ActiveRecord::addAlert('error','No se pudo actualizar', true);
        } catch (\Throwable $e) {
            ActiveRecord::addAlert('error','Error al actualizar: ' . $e->getMessage(), true);
        }

        header('Location: /admin/recompensas');
        exit;
    }

    // ELIMINAR (POST): sólo si pertenece a la tarjeta del admin
    public static function eliminar() {
        require_barbero_con_tarjeta('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /administrar-tarjeta');
            exit;
        }

        // CSRF
        if (function_exists('verify_csrf') && !verify_csrf()) {
            ActiveRecord::addAlert('error', 'Token CSRF inválido', true);
            header('Location: /admin/recompensas');
            exit;
        }

        start_session_if_needed();
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            ActiveRecord::addAlert('error','Recompensa no indicada', true);
            header('Location: /admin/recompensas');
            exit;
        }

        $recompensa = Recompensa::find($id);
        if (!$recompensa || (int)$recompensa->id_tarjeta !== $tarjetaId) {
            ActiveRecord::addAlert('error','No autorizado o recompensa no encontrada', true);
            header('Location: /admin/recompensas');
            exit;
        }

        try {
            $ok = $recompensa->eliminar();
            if ($ok) ActiveRecord::addAlert('success','Recompensa eliminada', true);
            else ActiveRecord::addAlert('error','No se pudo eliminar', true);
        } catch (\Throwable $e) {
            ActiveRecord::addAlert('error','Error al eliminar: ' . $e->getMessage(), true);
        }

        header('Location: /admin/recompensas');
        exit;
    }
}
