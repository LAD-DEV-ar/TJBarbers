<?php 
namespace Controllers;

use MVC\Router;
use Model\ActiveRecord;
use Model\Recompensa;
use Model\Canje;
use Model\Usuario;
use Model\Tarjeta;

class TarjetaController{
    public static function index(Router $router) {
        require_login_simple('/'); // redirige si no está logueado

        $userId = $_SESSION['id'] ?? null;

        $miTarjeta = null;
        if ($userId) {
            $usuario = Usuario::find((int)$userId);
            if ($usuario && $usuario->id_tarjeta) {
                $miTarjeta = Tarjeta::find((int)$usuario->id_tarjeta);
            }
        }

        $alerts = ActiveRecord::getAllAlertsAndClear();
        $router->render('tarjeta/index', [
            'miTarjeta' => $miTarjeta,
            'alerts'    => $alerts
        ]);
    }
    public static function vincularForm(Router $router) {
        // Asegurar helpers de sesión
        start_session_if_needed();

        $code = isset($_GET['code']) ? trim($_GET['code']) : null;

        // Si no viene código, mostrar el formulario normal
        if (!$code) {
            $alerts = ActiveRecord::getAllAlertsAndClear();
            $router->render('tarjeta/vincular', ['alerts' => $alerts, 'prefill_code' => '']);
            return;
        }

        // Si el usuario está logueado, intentar vincular inmediatamente
        $userId = $_SESSION['id'] ?? null;
        if ($userId) {
            $tarjeta = Tarjeta::findBy('codigo', $code);
            if (!$tarjeta) {
                ActiveRecord::addAlert('error', 'Código de tarjeta inválido', true);
                header('Location: /tarjeta/vincular');
                exit;
            }

            $usuario = Usuario::find((int)$userId);
            if (!$usuario) {
                ActiveRecord::addAlert('error', 'Usuario no encontrado', true);
                header('Location: /');
                exit;
            }

            // Política: no sobreescribir si ya tiene otra tarjeta
            if (!empty($usuario->id_tarjeta) && (int)$usuario->id_tarjeta !== (int)$tarjeta->id) {
                ActiveRecord::addAlert('warning', 'Tu cuenta ya está vinculada a otra tarjeta. Si deseas cambiarla contacta al local.', true);
                header('Location: /tarjeta');
                exit;
            }

            // Vincular
            $usuario->id_tarjeta = (int)$tarjeta->id;
            $ok = $usuario->guardar();
            if ($ok) {
                ActiveRecord::addAlert('success', 'Tarjeta vinculada correctamente', true);
                header('Location: /tarjeta');
                exit;
            } else {
                ActiveRecord::addAlert('error', 'Error al vincular la tarjeta. Intenta nuevamente.', true);
                header('Location: /tarjeta/vincular');
                exit;
            }
        }

        // Si NO está logueado: guardar el code en sesión y redirigir al login
        // (será procesado automáticamente después del login)
        $_SESSION['pending_vincular_code'] = $code;
        ActiveRecord::addAlert('info', 'Inicia sesión para vincular automáticamente tu tarjeta.', true);
        header('Location: /'); // suponiendo que / es la ruta de login
        exit;
    }

    // Opcional: POST handler de vinculación manual (form)
    public static function vincularSubmit(Router $router) {
        start_session_if_needed();

        if (empty($_SESSION['id'])) {
            ActiveRecord::addAlert('error', 'Debes iniciar sesión para vincular una tarjeta', true);
            header('Location: /');
            exit;
        }

        $codigo = trim($_POST['codigo'] ?? '');
        if (!$codigo) {
            ActiveRecord::addAlert('error', 'Ingresa un código válido', true);
            header('Location: /tarjeta/vincular');
            exit;
        }

        $tarjeta = Tarjeta::findBy('codigo', $codigo);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error', 'Código inválido', true);
            header('Location: /tarjeta/vincular');
            exit;
        }

        $usuario = Usuario::find((int)$_SESSION['id']);
        if (!$usuario) {
            ActiveRecord::addAlert('error', 'Usuario no encontrado', true);
            header('Location: /');
            exit;
        }

        if (!empty($usuario->id_tarjeta) && (int)$usuario->id_tarjeta !== (int)$tarjeta->id) {
            ActiveRecord::addAlert('warning', 'Tu cuenta ya está vinculada a otra tarjeta.', true);
            header('Location: /tarjeta');
            exit;
        }

        $usuario->id_tarjeta = (int)$tarjeta->id;
        if ($usuario->guardar()) {
            ActiveRecord::addAlert('success', 'Tarjeta vinculada correctamente', true);
            header('Location: /tarjeta');
            exit;
        } else {
            ActiveRecord::addAlert('error', 'No se pudo vincular la tarjeta', true);
            header('Location: /tarjeta/vincular');
            exit;
        }
    }

        // API: devuelve JSON con estado de la tarjeta del usuario logueado
    public static function apiGetTarjeta() {
        // No usamos require_login_simple (porque redirige). Hacemos check manual y devolvemos JSON.
        start_session_if_needed();

        header('Content-Type: application/json; charset=utf-8');

        $userId = $_SESSION['id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }

        $usuario = Usuario::find((int)$userId);
        if (!$usuario || empty($usuario->id_tarjeta)) {
            echo json_encode(['ok' => true, 'tieneTarjeta' => false]);
            exit;
        }

        $tarjeta = Tarjeta::find((int)$usuario->id_tarjeta);
        if (!$tarjeta) {
            echo json_encode(['ok' => true, 'tieneTarjeta' => false]);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'tieneTarjeta' => true,
            'tarjeta' => [
                'id' => (int)$tarjeta->id,
                'codigo' => $tarjeta->codigo,
                'casilla_max' => (int)$tarjeta->casilla_max,
                'casilla_actual' => (int)$usuario->casilla_actual
            ]
        ]);
        exit;
    }
    // API: barbero incrementa la casilla del cliente (POST JSON). Usamos require_barbero_simple check manual y UPDATE atómico.
    public static function apiAvanzar() {
        // incluir helper de sesiones y funciones
        start_session_if_needed();

        header('Content-Type: application/json; charset=utf-8');

        // 1) Verificar sesión y rol barbero (sin redirecciones porque es API)
        $isBarbero = !empty($_SESSION['barbero']) && (int)$_SESSION['barbero'] === 1;
        if (!$isBarbero) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'No autorizado - se requiere rol barbero']);
            exit;
        }

        // 2) Verificar CSRF (opcional pero recomendado)
        // Se espera token en cabecera X-CSRF-Token y que en login se guardó en $_SESSION['csrf_token']
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (empty($csrfHeader) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
            exit;
        }

        // 3) Leer body JSON
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $clienteId = isset($input['cliente_id']) ? (int)$input['cliente_id'] : 0;
        if ($clienteId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'cliente_id requerido']);
            exit;
        }

        // 4) Comprobar usuario y tarjeta
        require_once __DIR__ . '/../../models/Usuario.php';
        require_once __DIR__ . '/../../models/Tarjeta.php';
        $usuario = \Model\Usuario::find($clienteId);
        if (!$usuario || empty($usuario->id_tarjeta)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Usuario o tarjeta no encontrada']);
            exit;
        }

        // 5) UPDATE atómico: incrementar casilla_actual hasta casilla_max en un solo statement
        //    Esto evita race conditions: la DB aplica la operación en bloque.
        $db = \Model\ActiveRecord::getDB(); // mysqli

        $stmt = $db->prepare(
            "UPDATE usuario u
            JOIN tarjeta t ON u.id_tarjeta = t.id
            SET u.casilla_actual = LEAST(t.casilla_max, u.casilla_actual + 1)
            WHERE u.id = ? LIMIT 1"
        );
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error preparing DB: '.$db->error]);
            exit;
        }

        $stmt->bind_param('i', $clienteId);
        $exec = $stmt->execute();
        if ($exec === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error executing DB: '.$stmt->error]);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // 6) Leer el nuevo valor actualizado
        $stmt2 = $db->prepare("SELECT u.casilla_actual, t.casilla_max FROM usuario u JOIN tarjeta t ON u.id_tarjeta = t.id WHERE u.id = ? LIMIT 1");
        if (!$stmt2) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error preparing DB (select): '.$db->error]);
            exit;
        }
        $stmt2->bind_param('i', $clienteId);
        $stmt2->execute();
        $stmt2->bind_result($nuevaCasillaActual, $casillaMax);
        $fetched = $stmt2->fetch();
        $stmt2->close();

        if ($fetched === null || $fetched === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo leer el progreso actualizado']);
            exit;
        }

        $alcanzo = ((int)$nuevaCasillaActual >= (int)$casillaMax);

        // 7) Responder con JSON con nuevo estado
        echo json_encode([
            'ok' => true,
            'casilla_actual' => (int)$nuevaCasillaActual,
            'casilla_max' => (int)$casillaMax,
            'alcanzo' => (bool)$alcanzo
        ]);
        exit;
    }
       // controllers/TarjetaController.php (añade este método)
    public static function apiRecompensas(\MVC\Router $router) {
        // Responde JSON con las recompensas y estados para el usuario en sesión
        header('Content-Type: application/json; charset=utf-8');

        start_session_if_needed();
        // requiere login
        if (empty($_SESSION['id']) || empty($_SESSION['login'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'No autenticado']);
            exit;
        }

        $userId = (int)($_SESSION['id'] ?? 0);

        // Cargar usuario
        $usuario = \Model\Usuario::find($userId);
        if (!$usuario) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }

        $tarjetaId = (int)($usuario->id_tarjeta ?? 0);
        if (!$tarjetaId) {
            // usuario no tiene tarjeta
            echo json_encode(['ok' => true, 'tieneTarjeta' => false, 'recompensas' => []]);
            exit;
        }

        $db = \Model\ActiveRecord::getDB();

        // 1) Obtener recompensas de la tarjeta
        $stmt = $db->prepare("SELECT id, casilla, descripcion FROM recompensas WHERE id_tarjeta = ? ORDER BY casilla ASC");
        $recompensas = [];
        if ($stmt) {
            $stmt->bind_param('i', $tarjetaId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                // normalizar tipos
                $row['id'] = (int)$row['id'];
                $row['casilla'] = (int)$row['casilla'];
                $row['descripcion'] = (string)$row['descripcion'];
                $recompensas[] = $row;
            }
            $stmt->close();
        }

        // 2) Obtener canjes aprobados del usuario para esta tarjeta (para marcar canjeado)
        $canjeadosSet = [];
        $stmt2 = $db->prepare("SELECT id_recompensa FROM canje WHERE id_usuario = ? AND id_tarjeta = ?");
        if ($stmt2) {
            $stmt2->bind_param('ii', $userId, $tarjetaId);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            while ($row = $r2->fetch_assoc()) {
                // columna id_recompensa supone que es singular (ajusta si tu esquema difiere)
                $canjeadosSet[(int)$row['id_recompensa']] = true;
            }
            $stmt2->close();
        }

        // 3) Obtener solicitudes del usuario para esta tarjeta (estado: pending/approved/rejected)
        $solicitudesMap = []; // id_recompensa => estado (we store last known)
        $stmt3 = $db->prepare("SELECT id_recompensa, estado FROM canje_solicitudes WHERE id_usuario = ? AND id_tarjeta = ?");
        if ($stmt3) {
            $stmt3->bind_param('ii', $userId, $tarjetaId);
            $stmt3->execute();
            $r3 = $stmt3->get_result();
            while ($row = $r3->fetch_assoc()) {
                $solicitudesMap[(int)$row['id_recompensa']] = $row['estado'];
            }
            $stmt3->close();
        }

        // 4) Obtener casilla_actual y casilla_max para cálculo de eligibility
        $casilla_actual = (int)($usuario->casilla_actual ?? 0);
        // casilla_max la podemos obtener de la tabla tarjeta
        $stmt4 = $db->prepare("SELECT casilla_max, codigo FROM tarjeta WHERE id = ? LIMIT 1");
        $casilla_max = 0;
        $codigo = '';
        if ($stmt4) {
            $stmt4->bind_param('i', $tarjetaId);
            $stmt4->execute();
            $r4 = $stmt4->get_result();
            if ($row = $r4->fetch_assoc()) {
                $casilla_max = (int)($row['casilla_max'] ?? 0);
                $codigo = (string)($row['codigo'] ?? '');
            }
            $stmt4->close();
        }

        // 5) Construir la respuesta con los flags por recompensa
        $outRecompensas = [];
        foreach ($recompensas as $r) {
            $idR = (int)$r['id'];
            $cas = (int)$r['casilla'];
            $eligible = $casilla_actual >= $cas;
            $canjeado = !empty($canjeadosSet[$idR]);
            $sol_pending = (isset($solicitudesMap[$idR]) && $solicitudesMap[$idR] === 'pending');

            // Si está canjeado, forzamos solicitud pending a false (prioridad)
            if ($canjeado) $sol_pending = false;

            $outRecompensas[] = [
                'id' => $idR,
                'casilla' => $cas,
                'descripcion' => $r['descripcion'],
                'eligible' => $eligible,
                'canjeado' => $canjeado,
                'solicitud_pending' => $sol_pending
            ];
        }

        // 6) Respuesta JSON
        echo json_encode([
            'ok' => true,
            'tieneTarjeta' => true,
            'tarjeta' => [
                'id' => $tarjetaId,
                'codigo' => $codigo,
                'casilla_max' => $casilla_max,
                'casilla_actual' => $casilla_actual
            ],
            'recompensas' => $outRecompensas
        ]);
        exit;
    }


    // POST /api/tarjeta/canjear
    public static function apiCanjear() {
        
        start_session_if_needed();
        header('Content-Type: application/json; charset=utf-8');

        // policy: sólo barbero puede ejecutar canje (puedes cambiar)
        if (empty($_SESSION['barbero']) || (int)$_SESSION['barbero'] !== 1) {
            http_response_code(403); echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $idRecompensa = isset($data['id_recompensa']) ? (int)$data['id_recompensa'] : 0;
        $clienteId = isset($data['cliente_id']) ? (int)$data['cliente_id'] : 0;
        if ($idRecompensa <= 0 || $clienteId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'id_recompensa y cliente_id requeridos']); exit; }

        $db = ActiveRecord::getDB();

        // cargar objetos
        $recompensa = Recompensa::find($idRecompensa);
        $usuario = Usuario::find($clienteId);
        if (!$recompensa || !$usuario) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Recompensa o usuario no encontrado']); exit; }

        if ((int)$recompensa->id_tarjeta !== (int)$usuario->id_tarjeta) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'La recompensa no pertenece a la tarjeta del usuario']); exit;
        }

        if ((int)$usuario->casilla_actual < (int)$recompensa->casilla) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Usuario no tiene suficientes casillas para canjear']); exit;
        }

        // transacción: insertar registro en canje (UNIQUE id_usuario,id_recompensa evitará duplicados)
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("INSERT INTO canje (id_recompensa, id_usuario, id_tarjeta) VALUES (?, ?, ?)");
            if (!$stmt) throw new \Exception('Prepare error: ' . $db->error);
            $stmt->bind_param('iii', $recompensa->id, $usuario->id, $recompensa->id_tarjeta);
            $ok = $stmt->execute();
            if ($ok === false) {
                // duplicate entry?
                if ($db->errno === 1062) {
                    $stmt->close();
                    $db->rollback();
                    http_response_code(400);
                    echo json_encode(['ok'=>false,'error'=>'Recompensa ya canjeada por este usuario']);
                    exit;
                }
                throw new \Exception('Execute error: ' . $stmt->error);
            }
            $stmt->close();

            $db->commit();

            echo json_encode([
                'ok' => true,
                'mensaje' => 'Canje registrado correctamente',
                'casilla_actual' => (int)$usuario->casilla_actual,
                'recompensa' => ['id' => (int)$recompensa->id, 'casilla' => (int)$recompensa->casilla, 'descripcion' => $recompensa->descripcion]
            ]);
            exit;
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('Error canje: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'Error interno al procesar canje']);
            exit;
        }
    }
    public static function apiSolicitarCanje() {
        start_session_if_needed();
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_SESSION['id'] ?? null;
        if (!$userId) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit; }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $idRecompensa = isset($data['id_recompensa']) ? (int)$data['id_recompensa'] : 0;
        if ($idRecompensa <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'id_recompensa requerido']); exit; }

        $usuario = \Model\Usuario::find($userId);
        if (!$usuario || empty($usuario->id_tarjeta)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Usuario no tiene tarjeta vinculada']); exit; }

        $recompensa = \Model\Recompensa::find($idRecompensa);
        if (!$recompensa) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Recompensa no encontrada']); exit; }
        if ((int)$recompensa->id_tarjeta !== (int)$usuario->id_tarjeta) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Recompensa no pertenece a tu tarjeta']); exit; }

        // verificar elegibilidad (casilla actual suficiente)
        if ((int)$usuario->casilla_actual < (int)$recompensa->casilla) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Aún no alcanzaste la casilla requerida']); exit;
        }

        $db = \Model\ActiveRecord::getDB();

        // prevenir solicitudes duplicadas pendientes
        $stmt = $db->prepare("SELECT id, estado FROM canje_solicitudes WHERE id_recompensa = ? AND id_usuario = ? AND estado = 'pending' LIMIT 1");
        $stmt->bind_param('ii', $recompensa->id, $usuario->id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $stmt->close();
            echo json_encode(['ok'=>false,'error'=>'Ya existe una solicitud pendiente para esta recompensa.']);
            exit;
        }
        $stmt->close();

        // crear solicitud
        $stmt2 = $db->prepare("INSERT INTO canje_solicitudes (id_recompensa,id_usuario,id_tarjeta,estado) VALUES (?, ?, ?, 'pending')");
        $stmt2->bind_param('iii', $recompensa->id, $usuario->id, $recompensa->id_tarjeta);
        $ok = $stmt2->execute();
        if (!$ok) {
            error_log('Error insertar solicitud: ' . $stmt2->error);
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'No se pudo crear la solicitud']);
            exit;
        }
        $stmt2->close();

        echo json_encode(['ok'=>true,'mensaje'=>'Solicitud creada. El barbero revisará y aprobará pronto.']);
        exit;
    }
    // POST /tarjeta/solicitar-canje
    // Soporta JSON (fetch) y form submit (non-js)
    public static function solicitarCanje(Router $router) {
        // Debe estar logueado
        require_login_simple('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /tarjeta');
            exit;
        }

        // leer input (JSON o form)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?? [];
            $id_recompensa = isset($data['id_recompensa']) ? (int)$data['id_recompensa'] : 0;
        } else {
            $id_recompensa = isset($_POST['id_recompensa']) ? (int)$_POST['id_recompensa'] : 0;
        }

        if (!$id_recompensa) {
            $msg = 'Recompensa no indicada';
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            \Model\ActiveRecord::addAlert('error', $msg, true);
            header('Location: /tarjeta');
            exit;
        }

        // usuario en session
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $userId = (int)($_SESSION['id'] ?? 0);
        if (!$userId) {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'error' => 'No autenticado']);
                exit;
            }
            header('Location: /');
            exit;
        }

        // cargar recompensa y usuario
        $recompensa = \Model\Recompensa::find($id_recompensa);
        if (!$recompensa) {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Recompensa no encontrada']);
                exit;
            }
            \Model\ActiveRecord::addAlert('error', 'Recompensa no encontrada', true);
            header('Location: /tarjeta');
            exit;
        }

        $usuario = \Model\Usuario::find($userId);
        if (!$usuario) {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
                exit;
            }
            \Model\ActiveRecord::addAlert('error', 'Usuario no encontrado', true);
            header('Location: /tarjeta');
            exit;
        }

        // validar que la recompensa pertenece a la tarjeta del usuario
        $miTarjetaId = (int)($usuario->id_tarjeta ?? 0);
        if ($miTarjetaId <= 0 || (int)$recompensa->id_tarjeta !== $miTarjetaId) {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'No autorizado para solicitar esa recompensa']);
                exit;
            }
            \Model\ActiveRecord::addAlert('error', 'No autorizado para solicitar esa recompensa', true);
            header('Location: /tarjeta');
            exit;
        }

        // validar elegibilidad: casilla_actual >= casilla requerida
        $casillaUsuario = (int)($usuario->casilla_actual ?? 0);
        $casillaRecompensa = (int)($recompensa->casilla ?? 0);
        if ($casillaUsuario < $casillaRecompensa) {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Aún no alcanzaste la casilla para esta recompensa']);
                exit;
            }
            \Model\ActiveRecord::addAlert('error', 'Aún no alcanzaste la casilla para esta recompensa', true);
            header('Location: /tarjeta');
            exit;
        }

        $db = \Model\ActiveRecord::getDB();

        // 1) comprobar si ya existe canje aprobado (registro en canje)
        $checkCanjeStmt = $db->prepare("SELECT id FROM canje WHERE id_recompensa = ? AND id_usuario = ? LIMIT 1");
        if ($checkCanjeStmt) {
            $checkCanjeStmt->bind_param('ii', $id_recompensa, $userId);
            $checkCanjeStmt->execute();
            $resCanje = $checkCanjeStmt->get_result();
            if ($resCanje && $resCanje->fetch_assoc()) {
                // ya canjeado
                if (stripos($contentType, 'application/json') !== false) {
                    echo json_encode(['ok' => false, 'error' => 'Ya canjeaste esta recompensa anteriormente']);
                    exit;
                }
                \Model\ActiveRecord::addAlert('error','Ya canjeaste esta recompensa anteriormente', true);
                header('Location:/tarjeta');
                exit;
            }
            $checkCanjeStmt->close();
        }

        // 2) comprobar si ya existe una solicitud pendiente para la misma recompensa+usuario
        $checkPendStmt = $db->prepare("SELECT id FROM canje_solicitudes WHERE id_recompensa = ? AND id_usuario = ? AND estado = 'pending' LIMIT 1");
        if ($checkPendStmt) {
            $checkPendStmt->bind_param('ii', $id_recompensa, $userId);
            $checkPendStmt->execute();
            $resPend = $checkPendStmt->get_result();
            if ($resPend && $resPend->fetch_assoc()) {
                if (stripos($contentType, 'application/json') !== false) {
                    echo json_encode(['ok' => true, 'status' => 'pending', 'message' => 'Ya existe una solicitud pendiente para esta recompensa']);
                    exit;
                }
                \Model\ActiveRecord::addAlert('info','Ya existe una solicitud pendiente para esta recompensa', true);
                header('Location:/tarjeta');
                exit;
            }
            $checkPendStmt->close();
        }

        // 3) Insertar la solicitud (pending)
        $insertStmt = $db->prepare("INSERT INTO canje_solicitudes (id_recompensa, id_usuario, id_tarjeta, estado, creado_en) VALUES (?, ?, ?, 'pending', NOW())");
        if (!$insertStmt) {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Error interno al crear solicitud']);
                exit;
            }
            \Model\ActiveRecord::addAlert('error','Error interno al crear solicitud', true);
            header('Location:/tarjeta');
            exit;
        }
        $insertStmt->bind_param('iii', $id_recompensa, $userId, $miTarjetaId);
        $ok = $insertStmt->execute();
        $insertStmt->close();

        if ($ok) {
            if (stripos($contentType, 'application/json') !== false) {
                echo json_encode(['ok' => true, 'status' => 'pending', 'message' => 'Solicitud creada, el barbero la revisará.']);
                exit;
            }
            \Model\ActiveRecord::addAlert('success','Solicitud enviada. El barbero la revisará.', true);
            header('Location:/tarjeta');
            exit;
        } else {
            if (stripos($contentType, 'application/json') !== false) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'No se pudo crear la solicitud']);
                exit;
            }
            \Model\ActiveRecord::addAlert('error','No se pudo crear la solicitud', true);
            header('Location:/tarjeta');
            exit;
        }
    }
}