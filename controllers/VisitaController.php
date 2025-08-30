<?php
namespace Controllers;

use MVC\Router;
use Model\ActiveRecord;
use Model\Usuario;
use Model\Tarjeta;

class VisitaController {

    // GET /registrar-visita  -> formulario (solo barberos)
    public static function form(Router $router) {
        require_barbero_simple('/');

        // Obtener alertas (si tu ActiveRecord tiene getAllAlertsAndClear)
        $alerts = [];
        if (method_exists('\Model\ActiveRecord', 'getAllAlertsAndClear')) {
            $alerts = \Model\ActiveRecord::getAllAlertsAndClear();
        } elseif (method_exists('\Model\ActiveRecord', 'getAlertas')) {
            $alerts = \Model\ActiveRecord::getAlertas();
        }

        $router->render('admin/registrar-visita', [
            'alerts' => $alerts
        ]);
    }

    // POST /registrar-visita  -> manejo clásico (form), redirige con flash
    public static function registrar(Router $router) {

        start_session_if_needed();

        require_barbero_simple('/');

        // leer payload de POST (form)
        $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        $codigo = trim($_POST['codigo'] ?? '');

        // si no viene clienteId, intentar resolver por codigo
        if ($clienteId <= 0 && $codigo !== '') {
            $tar = Tarjeta::findBy('codigo', $codigo);
            if ($tar) {
                $user = Usuario::where('id_tarjeta', $tar->id);
                if ($user && !empty($user->id)) $clienteId = (int)$user->id;
            }
        }

        if ($clienteId <= 0) {
            self::flash('error','Debes indicar ID del cliente', true);
            header('Location: /registrar-visita');
            exit;
        }

        // verificar usuario y tarjeta
        $usuario = Usuario::find($clienteId);
        if (!$usuario || empty($usuario->id_tarjeta)) {
            self::flash('error','Usuario o tarjeta no encontrada', true);
            header('Location: /registrar-visita');
            exit;
        }

        // UPDATE atómico: incrementar casilla_actual hasta casilla_max
        $db = ActiveRecord::getDB();
        $stmt = $db->prepare(
            "UPDATE usuario u
             JOIN tarjeta t ON u.id_tarjeta = t.id
             SET u.casilla_actual = LEAST(t.casilla_max, u.casilla_actual + 1)
             WHERE u.id = ?"
        );
        if (!$stmt) {
            self::flash('error','Error interno DB', true);
            header('Location: /registrar-visita');
            exit;
        }
        $stmt->bind_param('i', $clienteId);
        $exec = $stmt->execute();
        $stmt->close();

        if ($exec === false) {
            self::flash('error','No se pudo actualizar la visita', true);
            header('Location: /registrar-visita');
            exit;
        }

        // leer el nuevo progreso
        $stmt2 = $db->prepare("SELECT u.casilla_actual, t.casilla_max FROM usuario u JOIN tarjeta t ON u.id_tarjeta = t.id WHERE u.id = ? LIMIT 1");
        $stmt2->bind_param('i', $clienteId);
        $stmt2->execute();
        $stmt2->bind_result($nuevaCasillaActual, $casillaMax);
        $stmt2->fetch();
        $stmt2->close();

        $nueva = (int)$nuevaCasillaActual;
        $max = (int)$casillaMax;
        $alcanzo = $nueva >= $max;

        self::flash('success', "Visita registrada: $nueva / $max" . ($alcanzo ? ' — alcanzó recompensa' : ''), true);
        header('Location: /administrar-tarjeta');
        exit;
    }

    // POST /api/registrar-visita -> endpoint JSON para AJAX (fetch)
    public static function apiRegistrar() {
        start_session_if_needed();

        header('Content-Type: application/json; charset=utf-8');

        // Verificar rol barbero
        if (empty($_SESSION['barbero']) || (int)$_SESSION['barbero'] !== 1) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }

        // Leer JSON body
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $clienteId = isset($data['cliente_id']) ? (int)$data['cliente_id'] : 0;
        $codigo = trim($data['codigo'] ?? '');

        // resolver si viene codigo
        if ($clienteId <= 0 && $codigo !== '') {
            $tar = Tarjeta::findBy('codigo', $codigo);
            if ($tar) {
                $user = Usuario::where('id_tarjeta', $tar->id);
                if ($user && !empty($user->id)) $clienteId = (int)$user->id;
            }
        }

        if ($clienteId <= 0) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'cliente_id requerido']);
            exit;
        }

        // validar usuario y tarjeta
        $usuario = Usuario::find($clienteId);
        if (!$usuario || empty($usuario->id_tarjeta)) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'error'=>'Usuario o tarjeta no encontrada']);
            exit;
        }

        // (Opcional) CSRF: si quieres activarlo, compara X-CSRF-Token con $_SESSION['csrf_token']
        // $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        // if (!$csrf || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { ... }

        // UPDATE atomico
        $db = ActiveRecord::getDB();
        $stmt = $db->prepare(
            "UPDATE usuario u
             JOIN tarjeta t ON u.id_tarjeta = t.id
             SET u.casilla_actual = LEAST(t.casilla_max, u.casilla_actual + 1)
             WHERE u.id = ? LIMIT 1"
        );
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'Error interno DB']);
            exit;
        }
        $stmt->bind_param('i', $clienteId);
        $exec = $stmt->execute();
        $stmt->close();
        if ($exec === false) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'No se pudo actualizar progreso']);
            exit;
        }

        // Leer nuevo progreso
        $stmt2 = $db->prepare("SELECT u.casilla_actual, t.casilla_max FROM usuario u JOIN tarjeta t ON u.id_tarjeta = t.id WHERE u.id = ? LIMIT 1");
        $stmt2->bind_param('i', $clienteId);
        $stmt2->execute();
        $stmt2->bind_result($nuevaCasillaActual, $casillaMax);
        $stmt2->fetch();
        $stmt2->close();

        $nueva = (int)$nuevaCasillaActual;
        $max = (int)$casillaMax;
        $alcanzo = $nueva >= $max;

        echo json_encode([
            'ok' => true,
            'cliente_id' => $clienteId,
            'casilla_actual' => $nueva,
            'casilla_max' => $max,
            'alcanzo' => (bool)$alcanzo
        ]);
        exit;
    }

    // helper flash (intenta usar ActiveRecord si existe)
    protected static function flash($type, $message, $preserve = false) {
        if (class_exists('\Model\ActiveRecord')) {
            // intenta addAlert o setAlerta
            if (method_exists('\Model\ActiveRecord', 'addAlert')) {
                \Model\ActiveRecord::addAlert($type, $message, $preserve);
                return;
            }
            if (method_exists('\Model\ActiveRecord', 'setAlerta')) {
                \Model\ActiveRecord::setAlerta($type, $message);
                return;
            }
        }
        if (session_status() !== PHP_SESSION_ACTIVE) start_session_if_needed();
        $_SESSION['flash'][] = ['type'=>$type,'message'=>$message];
    }
}
