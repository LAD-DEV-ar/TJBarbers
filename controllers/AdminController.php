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
        require_barbero_simple('/tarjeta');

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
            $sql = "SELECT s.id, s.id_recompensa, s.id_usuario, s.estado, s.descripcion, s.creado_en,
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
        require_barbero_con_tarjeta('/administrar-tarjeta');
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
            'tarjeta' => $tarjetaId,
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
        // helpers (ya definidos en tu proyecto)
        require_barbero_con_tarjeta('/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/solicitudes');
            exit;
        }

        start_session_if_needed();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // Log inicial
        error_log("aprobarSolicitud START - incoming id: " . var_export($id, true));
        error_log("aprobarSolicitud SESSION id: " . ($_SESSION['id'] ?? 'NULL') . " id_tarjeta: " . ($_SESSION['id_tarjeta'] ?? 'NULL'));

        if (!$id) {
            \Model\ActiveRecord::addAlert('error','Solicitud inválida', true);
            error_log("aprobarSolicitud - invalid id, aborting");
            header('Location: /admin/solicitudes');
            exit;
        }

        $db = \Model\ActiveRecord::getDB();

        try {
            // iniciar transacción
            if (!$db->begin_transaction()) {
                error_log("aprobarSolicitud - begin_transaction FAILED: (" . $db->errno . ") " . $db->error);
                throw new \Exception('No se pudo iniciar transacción');
            }
            error_log("aprobarSolicitud - transaction started");

            // 1) bloquear y obtener la solicitud con FOR UPDATE
            $sqlSelect = "SELECT * FROM canje_solicitudes WHERE id = ? LIMIT 1 FOR UPDATE";
            error_log("aprobarSolicitud - preparing: $sqlSelect");
            $stmt = $db->prepare($sqlSelect);
            if (!$stmt) {
                error_log("aprobarSolicitud - prepare failed (select solicitud): (" . $db->errno . ") " . $db->error);
                throw new \Exception('Prepare failed (select solicitud): ' . $db->error);
            }
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            if ($ok === false) {
                error_log("aprobarSolicitud - execute failed (select solicitud): (" . $stmt->errno . ") " . $stmt->error);
                throw new \Exception('Execute failed (select solicitud): ' . $stmt->error);
            }
            $res = $stmt->get_result();
            $sol = $res->fetch_assoc();
            $stmt->close();
            error_log("aprobarSolicitud - solicitud fetched: " . var_export($sol, true));

            if (!$sol) {
                $db->rollback();
                error_log("aprobarSolicitud - solicitud not found, rolled back");
                \Model\ActiveRecord::addAlert('error','Solicitud no encontrada', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // seguridad: que la solicitud pertenezca a la tarjeta del admin
            $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
            if ((int)$sol['id_tarjeta'] !== $tarjetaId) {
                $db->rollback();
                error_log("aprobarSolicitud - unauthorized: solicitud.id_tarjeta={$sol['id_tarjeta']} vs session id_tarjeta={$tarjetaId}");
                \Model\ActiveRecord::addAlert('error','No autorizado', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // estado pendiente?
            $estadoActual = $sol['estado'] ?? null;
            error_log("aprobarSolicitud - estado actual solicitud: " . var_export($estadoActual, true));
            if ($estadoActual !== 'pending') {
                // ya procesada
                $db->rollback();
                error_log("aprobarSolicitud - solicitud ya procesada (estado != pending), rolled back");
                \Model\ActiveRecord::addAlert('info','La solicitud ya fue procesada', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // 2) comprobar si ya existe un canje (bloqueando la posible fila)
            $sqlChk = "SELECT id FROM canje WHERE id_recompensa = ? AND id_usuario = ? LIMIT 1 FOR UPDATE";
            error_log("aprobarSolicitud - preparing: $sqlChk with id_recompensa={$sol['id_recompensa']} id_usuario={$sol['id_usuario']}");
            $chk = $db->prepare($sqlChk);
            if (!$chk) {
                error_log("aprobarSolicitud - prepare failed (check canje): (" . $db->errno . ") " . $db->error);
                throw new \Exception('Prepare failed (check canje): ' . $db->error);
            }
            $chk->bind_param('ii', $sol['id_recompensa'], $sol['id_usuario']);
            $ok = $chk->execute();
            if ($ok === false) {
                error_log("aprobarSolicitud - execute failed (check canje): (" . $chk->errno . ") " . $chk->error);
                throw new \Exception('Execute failed (check canje): ' . $chk->error);
            }
            $resChk = $chk->get_result();
            $already = $resChk->fetch_assoc();
            $chk->close();
            error_log("aprobarSolicitud - check canje result: " . var_export($already, true));

            if ($already) {
                // ya existe canje: marcar solicitud como rejected con nota
                $note = 'Recompensa ya canjeada anteriormente';
                $sqlUpdReject = "UPDATE canje_solicitudes SET estado = 'rejected', comentario = ? WHERE id = ?";
                error_log("aprobarSolicitud - preparing update reject: $sqlUpdReject with note={$note}");
                $upd = $db->prepare($sqlUpdReject);
                if ($upd) {
                    $upd->bind_param('si', $note, $id);
                    $upd->execute();
                    error_log("aprobarSolicitud - update reject executed, affected_rows: " . $upd->affected_rows . " err: {$upd->errno} {$upd->error}");
                    $upd->close();
                } else {
                    error_log("aprobarSolicitud - prepare failed (update reject): (" . $db->errno . ") " . $db->error);
                }
                $db->commit();
                error_log("aprobarSolicitud - committed after marking rejected (existing canje)");
                \Model\ActiveRecord::addAlert('error','La recompensa ya fue canjeada por este usuario', true);
                header('Location:/admin/solicitudes');
                exit;
            }

            // 3) insertar en canje
            $sqlInsert = "INSERT INTO canje (id_recompensa, id_usuario, id_tarjeta, descripcion, creado_en) VALUES (?, ?, ?, ?, NOW())";
            error_log("aprobarSolicitud - preparing insert: $sqlInsert with params ({$sol['id_recompensa']}, {$sol['id_usuario']}, {$sol['id_tarjeta']})");
            $ins = $db->prepare($sqlInsert);
            if (!$ins) {
                error_log("aprobarSolicitud - prepare failed (insert canje): (" . $db->errno . ") " . $db->error);
                throw new \Exception('Prepare failed (insert canje): ' . $db->error);
            }
            $ins->bind_param('iiis', $sol['id_recompensa'], $sol['id_usuario'], $sol['id_tarjeta'], $sol['descripcion']);
            $ok = $ins->execute();
            if ($ok === false) {
                $errno = $ins->errno;
                $err = $ins->error;
                error_log("aprobarSolicitud - insert execute FAILED: errno={$errno} error={$err}");
                $ins->close();

                // si hay constraint único, manejarlo
                if ($errno === 1062) {
                    $note = 'Recompensa ya canjeada (conflicto de concurrencia)';
                    error_log("aprobarSolicitud - duplicate key (1062) detected, marking request rejected");
                    $upd = $db->prepare("UPDATE canje_solicitudes SET estado = 'rejected', comentario = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param('si', $note, $id);
                        $upd->execute();
                        $upd->close();
                    }
                    $db->commit();
                    \Model\ActiveRecord::addAlert('error','La recompensa ya fue canjeada por este usuario', true);
                    header('Location:/admin/solicitudes');
                    exit;
                }

                throw new \Exception('Error insert canje: ' . $err . ' (errno ' . $errno . ')');
            }
            error_log("aprobarSolicitud - insert canje OK, insert_id: " . $db->insert_id);
            $ins->close();

            // 4) actualizar solicitud como approved
            $aprobador = (int)($_SESSION['id'] ?? 0);
            $sqlUpd = "UPDATE canje_solicitudes SET estado = 'approved', aprobado_por = ?, aprobado_en = NOW() WHERE id = ?";
            error_log("aprobarSolicitud - preparing update to approved: $sqlUpd with aprobador={$aprobador}");
            $upd2 = $db->prepare($sqlUpd);
            if (!$upd2) {
                error_log("aprobarSolicitud - prepare failed (update approved): (" . $db->errno . ") " . $db->error);
                throw new \Exception('Prepare failed (update approved): ' . $db->error);
            }
            $upd2->bind_param('ii', $aprobador, $id);
            if (!$upd2->execute()) {
                error_log("aprobarSolicitud - execute failed (update approved): (" . $upd2->errno . ") " . $upd2->error);
                throw new \Exception('Execute failed (update approved): ' . $upd2->error);
            }
            error_log("aprobarSolicitud - update approved executed, affected_rows: " . $upd2->affected_rows);
            $upd2->close();

            // commit final
            if (!$db->commit()) {
                error_log("aprobarSolicitud - commit FAILED: (" . $db->errno . ") " . $db->error);
                throw new \Exception('Commit failed: ' . $db->error);
            }
            error_log("aprobarSolicitud - transaction committed successfully");

            \Model\ActiveRecord::addAlert('success','Canje aprobado y registrado', true);
            header('Location:/admin/solicitudes');
            exit;

        } catch (\Throwable $e) {
            // rollback y log completo
            if ($db->in_transaction) {
                $db->rollback();
                error_log("aprobarSolicitud - rollback executed due to exception");
            }
            error_log("aprobarSolicitud - EXCEPTION: " . $e->getMessage());
            error_log("aprobarSolicitud - TRACE: " . $e->getTraceAsString());
            \Model\ActiveRecord::addAlert('error','Error interno al aprobar (ver logs)', true);
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
    public static function editarForm(\MVC\Router $router) {
    // Permitir solo barbero con tarjeta
    require_barbero_con_tarjeta('/administrar-tarjeta');
    start_session_if_needed();
    $tarjetaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    // Si no viene id, intentar usar la tarjeta del admin en session
    if (!$tarjetaId) $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);

    if (!$tarjetaId) {
        ActiveRecord::addAlert('error', 'Tarjeta no indicada o no tienes tarjeta asignada.', true);
        header('Location: /administrar-tarjeta');
        exit;
    }

    // Asegurar que la tarjeta pertenece al admin en sesión
    $userId = (int)($_SESSION['id'] ?? 0);
    $usuario = Usuario::find($userId);
    if (!$usuario || (int)$usuario->id_tarjeta !== $tarjetaId) {
        ActiveRecord::addAlert('error', 'No autorizado para editar esa tarjeta.', true);
        header('Location: /administrar-tarjeta');
        exit;
    }

    $tarjeta = Tarjeta::find($tarjetaId);
    if (!$tarjeta) {
        ActiveRecord::addAlert('error', 'Tarjeta no encontrada.', true);
        header('Location: /administrar-tarjeta');
        exit;
    }

    // traer alertas flash + limpiar (si tienes helper)
    $alerts = ActiveRecord::getAllAlertsAndClear();

    $router->render('admin/editar-tarjeta', [
        'tarjeta' => $tarjeta,
        'alerts'  => $alerts
    ]);
}

    public static function editarSubmit(\MVC\Router $router) {
        // Procesar POST del form de edición
        require_barbero_con_tarjeta('/administrar-tarjeta');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ActiveRecord::addAlert('error', 'Método inválido', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        start_session_if_needed();
        $userId = (int)($_SESSION['id'] ?? 0);
        $tarjetaId = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_SESSION['id_tarjeta'] ?? 0);

        if (!$tarjetaId) {
            ActiveRecord::addAlert('error', 'Tarjeta no indicada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // validar que el admin realmente sea dueño de la tarjeta
        $usuario = Usuario::find($userId);
        if (!$usuario || (int)$usuario->id_tarjeta !== $tarjetaId) {
            ActiveRecord::addAlert('error', 'No autorizado para editar esta tarjeta', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        $tarjeta = Tarjeta::find($tarjetaId);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error', 'Tarjeta no encontrada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Inputs
        $casilla_max = isset($_POST['casilla_max']) ? (int)$_POST['casilla_max'] : 0;
        $codigo = trim($_POST['codigo'] ?? '');

        // Asignar valores temporales al objeto (no guardamos aún)
        $tarjeta->casilla_max = $casilla_max;
        // si el admin envía un código (puede ser opcional), validar unicidad
        $codigoEnviado = false;
        if ($codigo !== '') {
            $codigoEnviado = true;
            $tarjeta->codigo = $codigo;
        }

        // Validar con el método del modelo (reutiliza validarNueva)
        $tarjeta->validarNueva();
        $errs = Tarjeta::getAlertas();
        if (!empty($errs)) {
            // convertir alertas a formato plano para la vista si lo necesitas
            $flat = [];
            foreach ($errs as $type => $msgs) foreach ($msgs as $m) $flat[] = ['type'=>$type,'message'=>$m];
            // volver a mostrar el form con errores
            $router->render('admin/editar-tarjeta', [
                'tarjeta' => $tarjeta,
                'alerts'  => $flat
            ]);
            return;
        }

        $db = ActiveRecord::getDB();

        // Si el admin intentó cambiar el código, verificar unicidad (y permitir si es la misma tarjeta)
        if ($codigoEnviado) {
            $ex = Tarjeta::findBy('codigo', $codigo);
            if ($ex && (int)$ex->id !== (int)$tarjeta->id) {
                ActiveRecord::addAlert('error','El código ya está en uso por otra tarjeta', true);
                header('Location: /administrar-tarjeta/editar?id=' . $tarjeta->id);
                exit;
            }
        }

        // Guardar cambios en transacción y, si se redujo casilla_max, ajustar usuarios
        $inTx = false;
        try {
            if (!$db->begin_transaction()) {
                throw new \Exception('No se pudo iniciar transacción: ' . $db->error);
            }
            $inTx = true;

            $ok = $tarjeta->guardar(); // ActiveRecord->actualizar()
            if (!$ok) {
                throw new \Exception('Error al guardar la tarjeta');
            }

            // Si se redujo casilla_max por debajo de casilla_actual de usuarios, capearlos
            // Obtenemos la nueva casilla_max (aseguramos entero)
            $nuevaMax = (int)$tarjeta->casilla_max;
            if ($nuevaMax > 0) {
                // Contamos usuarios con casilla_actual > nuevaMax
                $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM usuario WHERE id_tarjeta = ? AND casilla_actual > ?");
                $stmt->bind_param('ii', $tarjetaId, $nuevaMax);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $countAffected = (int)($row['cnt'] ?? 0);
                $stmt->close();

                if ($countAffected > 0) {
                    // Ajustar casilla_actual de esos usuarios para no superar la nuevaMax
                    $stmt2 = $db->prepare("UPDATE usuario SET casilla_actual = ? WHERE id_tarjeta = ? AND casilla_actual > ?");
                    $stmt2->bind_param('iii', $nuevaMax, $tarjetaId, $nuevaMax);
                    $stmt2->execute();
                    $stmt2->close();

                    ActiveRecord::addAlert('info', "Se ajustaron {$countAffected} usuario/s cuya casilla actual de ello/s superaba el nuevo máximo.", true);
                }
            }

            // Commit
            $db->commit();
            $inTx = false;

            ActiveRecord::addAlert('success','Tarjeta actualizada correctamente', true);
            header('Location: /administrar-tarjeta');
            exit;
        } catch (\Throwable $e) {
            if ($inTx) $db->rollback();
            error_log('editarSubmit error: ' . $e->getMessage());
            ActiveRecord::addAlert('error','Error interno al actualizar la tarjeta', true);
            header('Location: /administrar-tarjeta');
            exit;
        }
    }
    public static function listarClientes(Router $router){
        start_session_if_needed();
        require_barbero_con_tarjeta('/administrar-tarjeta');
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
        $tarjeta = Tarjeta::find($tarjetaId);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error','Tarjeta no encontrada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        $clientes = Usuario::SQL("SELECT * FROM usuario WHERE id_tarjeta = " . (int)$tarjetaId . "");
        $alerts = ActiveRecord::getAllAlertsAndClear();

        $router->render('admin/clientes/index', [
            'alerts' => $alerts,
            'clientes' => $clientes,
            'tarjeta' => $tarjeta
        ]);
    }
    public static function reiniciarTarjeta(Router $router){
        
        require_barbero_con_tarjeta('/administrar-tarjeta');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ActiveRecord::addAlert('error', 'Método inválido', true);
            header('Location: /admin/clientes');
            exit;
        }
        start_session_if_needed();
        // Los datos de session para ver que admin es
        $userId = $_SESSION["id"] ?? 0;
        $tarjetaId = (int)($_SESSION['id_tarjeta'] ?? 0);
        if (!$tarjetaId) {
            ActiveRecord::addAlert('error', 'Tarjeta no indicada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }

        // Informacion del post necesario para ver con que clientes se esta tratando
        $tarjetaIdCliente = (int)($_POST['id_tarjeta'] ?? 0);
        $id = $_POST["id"];

        $tarjeta = Tarjeta::find($tarjetaId);
        if (!$tarjeta) {
            ActiveRecord::addAlert('error', 'Tarjeta no encontrada', true);
            header('Location: /administrar-tarjeta');
            exit;
        }        
        $casilla_max = $tarjeta->casilla_max;

        $usuario = Usuario::find($userId);
        if (!$usuario || (int)$usuario->id_tarjeta !== $tarjetaIdCliente) {
            ActiveRecord::addAlert('error', 'No autorizado para editar esta tarjeta', true);
            header('Location: /admin/clientes');
            exit;
        }

        if ($id == $userId){
            ActiveRecord::addAlert('error', 'Tu no puedes reiniciar tu propia tarjeta', true);
            header('Location: /admin/clientes');
            exit;            
        }

        $casilla_actual = $_POST['casilla_actual'] ?? 0;
        $racha = $_POST['racha'] ?? 0;

        $nuevaRacha = $racha+1;

        if ($casilla_actual < $casilla_max){
            ActiveRecord::addAlert('error', 'No completo su tarjeta el usuario, no puede ser reiniciado', true);
            header('Location: /admin/clientes');
            exit;   
        }

        $db = ActiveRecord::getDB();

        $stmt = $db->prepare("UPDATE usuario SET casilla_actual = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        // $res = $stmt->get_result();
        // $row = $res->fetch_assoc();
        $stmt->close();
        

        $stmt2 = $db->prepare("UPDATE usuario SET racha = ? WHERE id = ?");
        $stmt2->bind_param('ii', $nuevaRacha, $id);
        $stmt2->execute();
        // $res = $stmt2->get_result();
        // $row = $res->fetch_assoc();
        $stmt2->close();
        
        $db->commit();
        ActiveRecord::addAlert('success', 'Tarjeta de usuario reiniciada correctamente', true);
        ActiveRecord::addAlert('success', 'Su nueva racha fue actualizada', true);
        header('Location: /admin/clientes');
        exit; 
    }
}
