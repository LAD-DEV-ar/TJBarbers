<?php
namespace Controllers;

use MVC\Router;
use Model\Tarjeta;
use Model\Usuario;
use Model\Recompensa;
use Model\ActiveRecord;

class AdminController {

    /**
     * Mostrar el panel de administración (mi tarjeta).
     * - Carga únicamente la tarjeta vinculada al admin en sesión.
     * - Carga recompensas y solicitudes relacionadas a esa tarjeta.
     */
    public static function administrarTarjeta(Router $router) {
        // helpers existentes (no incluir require_once aquí)
        start_session_if_needed();
        require_barbero_simple('/');

        $userId = (int)($_SESSION['id'] ?? 0);
        $miTarjeta = null;
        $recompensas = [];
        $solicitudes = [];

        // Cargar usuario y su tarjeta (si tiene)
        if ($userId) {
            $usuario = Usuario::find($userId);
            if ($usuario && !empty($usuario->id_tarjeta)) {
                $miTarjeta = Tarjeta::find((int)$usuario->id_tarjeta);
            }
        }

        if ($miTarjeta && !empty($miTarjeta->id)) {
            // Recompensas: simple SELECT (devuelve array de arrays/objetos según ActiveRecord::SQL)
            $recompensas = Recompensa::SQL("SELECT * FROM recompensas WHERE id_tarjeta = " . (int)$miTarjeta->id . " ORDER BY casilla ASC");

            // Solicitudes pendientes / históricas relacionadas con esta tarjeta
            $db = ActiveRecord::getDB();
            $sql = "SELECT s.id, s.id_recompensa, s.id_usuario, s.estado, s.comentario, s.creado_en,
                           r.descripcion, r.casilla,
                           u.nombre AS usuario_nombre, u.email, u.telefono
                    FROM canje_solicitudes s
                    JOIN recompensas r ON r.id = s.id_recompensa
                    JOIN usuario u ON u.id = s.id_usuario
                    WHERE s.id_tarjeta = ?
                    ORDER BY s.creado_en DESC";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $miTarjeta->id);
                $stmt->execute();
                $res = $stmt->get_result();
                $solicitudes = [];
                while ($row = $res->fetch_assoc()) {
                    $solicitudes[] = $row;
                }
                $stmt->close();
            } else {
                // fallback: log y dejar solicitudes vacías
                error_log('AdminController->administrarTarjeta prepare failed: ' . $db->error);
            }
        }

        // Obtener alertas (flash + memoria) y limpiarlas
        $alerts = ActiveRecord::getAllAlertsAndClear();

        // Render: pasar todo por PHP (no AJAX)
        $router->render('admin/index', [
            'miTarjeta'   => $miTarjeta,
            'recompensas' => $recompensas,
            'solicitudes' => $solicitudes,
            'alerts'      => $alerts
        ]);
    }

    /**
     * Crear una tarjeta y vincularla al usuario en sesión de forma transaccional.
     */
    public static function crearTarjeta(Router $router) {
        require_barbero_simple('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ActiveRecord::addAlert('error', 'Método inválido', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $userId = (int)($_SESSION['id'] ?? 0);
        if (!$userId) {
            ActiveRecord::addAlert('error', 'Usuario no autenticado', true);
            header('Location: /');
            exit;
        }

        $casilla_max = isset($_POST['casilla_max']) ? (int)$_POST['casilla_max'] : 0;
        $codigo_opcional = trim($_POST['codigo_opcional'] ?? '');

        $tarjeta = new Tarjeta([
            'casilla_max' => $casilla_max
        ]);

        if ($codigo_opcional !== '') {
            $tarjeta->codigo = $codigo_opcional;
        }

        // Validación por el modelo
        $tarjeta->validarNueva();
        $errs = Tarjeta::getAlertas();
        if (!empty($errs)) {
            // Pasar alertas a flash
            foreach ($errs as $type => $messages) {
                foreach ($messages as $m) ActiveRecord::addAlert($type, $m);
            }
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Si no vino código, generarlo; si vino, validar unicidad
        if (empty($tarjeta->codigo)) {
            $tarjeta->generarCodigoUnico();
        } else {
            $exist = Tarjeta::findBy('codigo', $tarjeta->codigo);
            if ($exist) {
                ActiveRecord::addAlert('error', 'El código ya existe. Elige otro.', true);
                header('Location: /administrar-tarjeta');
                exit;
            }
        }

        // Guardar tarjeta
        $ok = $tarjeta->guardar();
        if (!$ok) {
            ActiveRecord::addAlert('error', 'Error al crear la tarjeta', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Vincular tarjeta al usuario creador
        $usuario = Usuario::find($userId);
        if (!$usuario) {
            // rollback
            $tarjeta->eliminar();
            ActiveRecord::addAlert('error', 'Usuario no encontrado. Operación anulada.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        $usuario->id_tarjeta = (int)$tarjeta->id;
        $okUser = $usuario->guardar();

        if ($okUser) {
            // actualizar session si lo deseas
            $_SESSION['id_tarjeta'] = $usuario->id_tarjeta ?? null;
            ActiveRecord::addAlert('success', 'Tarjeta creada y vinculada correctamente.', true);
            header('Location: /administrar-tarjeta');
            exit;
        } else {
            // rollback
            $tarjeta->eliminar();
            ActiveRecord::addAlert('error', 'Tarjeta creada, pero no se pudo vincular al usuario. Operación revertida.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }
    }

    /**
     * GET /admin/solicitudes
     * Lista solicitudes pendientes para la tarjeta del admin.
     */
    public static function listarSolicitudes(Router $router) {
        require_barbero_con_tarjeta('/');

        start_session_if_needed();
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);

        $db = ActiveRecord::getDB();
        $sql = "SELECT s.id, s.id_recompensa, s.id_usuario, s.estado, s.creado_en, r.descripcion, r.casilla, u.nombre AS usuario_nombre
                FROM canje_solicitudes s
                JOIN recompensas r ON r.id = s.id_recompensa
                JOIN usuario u ON u.id = s.id_usuario
                WHERE s.id_tarjeta = ? AND s.estado = 'pending'
                ORDER BY s.creado_en ASC";
        $stmt = $db->prepare($sql);
        $solicitudes = [];
        if ($stmt) {
            $stmt->bind_param('i', $tarjetaId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $solicitudes[] = $row;
            $stmt->close();
        } else {
            error_log('AdminController->listarSolicitudes prepare failed: ' . $db->error);
        }

        $alerts = ActiveRecord::getAllAlertsAndClear();
        $router->render('admin/solicitudes/index', [
            'solicitudes' => $solicitudes,
            'alerts' => $alerts
        ]);
    }

    /**
     * POST /admin/solicitudes/aprobar
     * Aprueba una solicitud: inserta registro en "canje" y marca la solicitud como approved.
     * Operación atómica (transaction).
     */
    // controllers/AdminController.php (fragmento)
    public static function aprobarSolicitud() {
        require_barbero_con_tarjeta('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/solicitudes');
            exit;
        }

        start_session_if_needed();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            \Model\ActiveRecord::addAlert('error','Solicitud inválida', true);
            header('Location: /admin/solicitudes');
            exit;
        }

        $db = \Model\ActiveRecord::getDB();

        try {
            // Iniciar transacción
            $db->begin_transaction();

            // 1) Obtener la solicitud y bloquearla (FOR UPDATE)
            $stmt = $db->prepare("SELECT * FROM canje_solicitudes WHERE id = ? LIMIT 1 FOR UPDATE");
            if (!$stmt) throw new \Exception('Error interno: ' . $db->error);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $sol = $res->fetch_assoc();
            $stmt->close();

            if (!$sol) {
                $db->rollback();
                \Model\ActiveRecord::addAlert('error','Solicitud no encontrada', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // verificar que la solicitud pertenece a la tarjeta del admin en sesión
            $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
            if ((int)$sol['id_tarjeta'] !== $tarjetaId) {
                $db->rollback();
                \Model\ActiveRecord::addAlert('error','No autorizado', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // verificar estado pendiente
            $estadoActual = $sol['estado'] ?? $sol['estado'];
            if ($estadoActual !== 'pending') {
                // ya procesada
                $db->rollback();
                \Model\ActiveRecord::addAlert('info','La solicitud ya fue procesada anteriormente', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // 2) Comprobar nuevamente si ya existe un canje (evitar race)
            $chk = $db->prepare("SELECT id FROM canje WHERE id_recompensa = ? AND id_usuario = ? LIMIT 1");
            if (!$chk) throw new \Exception('Error interno: ' . $db->error);
            $chk->bind_param('ii', $sol['id_recompensa'], $sol['id_usuario']);
            $chk->execute();
            $resChk = $chk->get_result();
            $already = $resChk->fetch_assoc();
            $chk->close();
            if ($already) {
                // ya canjeado por otro proceso -> marcar solicitud como rejected o keep pending with note
                // Aquí optamos por marcar 'rejected' y notificar
                $updReject = $db->prepare("UPDATE canje_solicitudes SET estado = 'rejected', comentario = ? WHERE id = ?");
                $note = 'Recompensa ya canjeada anteriormente';
                $updReject->bind_param('si', $note, $id);
                $updReject->execute();
                $updReject->close();
                $db->commit();
                \Model\ActiveRecord::addAlert('error','La recompensa ya fue canjeada por este usuario', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // 3) Insertar en canje (registro aprobado)
            $ins = $db->prepare("INSERT INTO canje (id_recompensa, id_usuario, id_tarjeta, creado_en) VALUES (?, ?, ?, NOW())");
            if (!$ins) throw new \Exception('Error interno: ' . $db->error);
            $ins->bind_param('iii', $sol['id_recompensa'], $sol['id_usuario'], $sol['id_tarjeta']);
            if (!$ins->execute()) {
                // posible duplicado u otro error
                $err = $ins->error;
                $ins->close();
                throw new \Exception('Error insert canje: ' . $err);
            }
            $ins->close();

            // 4) actualizar la solicitud -> estado = 'approved', aprobado_por, aprobado_en
            $aprobador = (int)($_SESSION['id'] ?? 0);
            $upd = $db->prepare("UPDATE canje_solicitudes SET estado = 'approved', aprobado_por = ?, aprobado_en = NOW() WHERE id = ?");
            if (!$upd) throw new \Exception('Error interno: ' . $db->error);
            $upd->bind_param('ii', $aprobador, $id);
            if (!$upd->execute()) throw new \Exception('Error actualizar solicitud: ' . $upd->error);
            $upd->close();

            // Commit
            $db->commit();
            \Model\ActiveRecord::addAlert('success','Canje aprobado y registrado', true);
            header('Location:/admin/solicitudes');
            exit;

        } catch (\Throwable $e) {
            // Rollback y log
            if ($db->in_transaction) $db->rollback();
            error_log('Error aprobar solicitud: ' . $e->getMessage());
            \Model\ActiveRecord::addAlert('error','Error interno al aprobar', true);
            header('Location:/admin/solicitudes');
            exit;
        }
    }


    /**
     * Registrar visita (admin registra visita del cliente -> incrementa casilla_actual).
     * POST /administrar-tarjeta/registrar-visita
     */
    public static function registrarVisita(Router $router) {
        start_session_if_needed();
        require_barbero_simple('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ActiveRecord::addAlert('error', 'Método inválido', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        $adminId = (int)($_SESSION['id'] ?? 0);
        if (!$adminId) {
            ActiveRecord::addAlert('error', 'No autorizado', true);
            header('Location: /');
            exit;
        }

        // Obtener tarjeta del admin
        $admin = Usuario::find($adminId);
        $tarjetaId = (int)($admin->id_tarjeta ?? 0);
        if (!$tarjetaId) {
            ActiveRecord::addAlert('error', 'No tienes una tarjeta asignada para registrar visitas.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // inputs
        $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
        $telefono = !empty($_POST['cliente_telefono']) ? trim($_POST['cliente_telefono']) : null;

        if (!$clienteId && !$telefono) {
            ActiveRecord::addAlert('error', 'Debe indicar ID de usuario o teléfono para registrar la visita.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // buscar usuario objetivo
        $usuario = null;
        if ($clienteId) {
            $usuario = Usuario::find($clienteId);
        }
        if (!$usuario && $telefono) {
            $usuario = Usuario::where('telefono', $telefono);
        }

        if (!$usuario) {
            ActiveRecord::addAlert('error', 'Usuario no encontrado', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // verificar vínculo con misma tarjeta del admin
        if ((int)$usuario->id_tarjeta !== $tarjetaId) {
            ActiveRecord::addAlert('error', 'El usuario no está vinculado a tu tarjeta.', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // cargar tarjeta para casilla_max
        $tarjeta = Tarjeta::find($tarjetaId);
        $casilla_max = (int)($tarjeta->casilla_max ?? 0);

        // incrementar casilla_actual (no exceder casilla_max)
        $prev = (int)($usuario->casilla_actual ?? 0);
        $nuevo = $prev + 1;
        if ($casilla_max > 0 && $nuevo > $casilla_max) {
            $nuevo = $casilla_max; // política: mantener en máximo
        }

        $usuario->casilla_actual = $nuevo;
        // incrementar racha simple (puedes mejorar con lógica de fechas)
        $usuario->racha = (int)($usuario->racha ?? 0) + 1;

        $ok = $usuario->guardar();
        if ($ok) {
            ActiveRecord::addAlert('success', "Visita registrada para {$usuario->nombre}. Casilla actual: {$usuario->casilla_actual}.", true);
        } else {
            ActiveRecord::addAlert('error', 'Error al actualizar usuario. No se registró la visita.', true);
        }

        // si el admin está actualizando su propia cuenta en sesión, sincronizar session
        if (isset($_SESSION['id']) && (int)$_SESSION['id'] === (int)$usuario->id) {
            $_SESSION['id_tarjeta'] = $usuario->id_tarjeta;
        }

        header('Location: /administrar-tarjeta');
        exit;
    }
}
