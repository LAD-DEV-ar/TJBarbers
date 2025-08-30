<?php
namespace Controllers;

use Model\Usuario;
use MVC\Router;

class AuthController {

    // Mostrar / procesar login
    public static function login(Router $router) {
        // Asegurar sesión disponible para leer pending_vincular_code y otras cosas
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Si es POST -> procesar login
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Sanitizar inputs mínimos
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Validar inputs (usa tu modelo)
            $temp = new \Model\Usuario(['email' => $email, 'password' => $password]);
            $temp->validarLogin();
            $errs = \Model\Usuario::getAlertas();
            if (!empty($errs)) {
                // Mostrar vista con errores (no redirect)
                $flat = [];
                foreach ($errs as $type => $msgs) {
                    foreach ($msgs as $m) $flat[] = ['type' => $type, 'message' => $m];
                }
                $router->render('auth/login', ['alerts' => $flat, 'email' => $email]);
                return;
            }

            // Buscar usuario por email
            $usuario = \Model\Usuario::findBy('email', $email);

            // Verificar existencia y password
            if (!$usuario || !$usuario->comprobarPassword($password)) {
                \Model\Usuario::addAlert('error', 'Credenciales incorrectas', true);
                header('Location: /');
                exit;
            }

            // Verificar confirmación
            if (!$usuario->estaConfirmado()) {
                \Model\Usuario::addAlert('warning', 'Debes confirmar tu cuenta. Revisa tu email.', true);
                header('Location: /');
                exit;
            }

            // LOGIN OK: Crear sesión de manera segura
            // Regenerar id de sesión para evitar session fixation
            session_regenerate_id(true);

            // Guardar datos en sesión (cast y valores básicos)
            $_SESSION['id'] = (int) $usuario->id;
            $_SESSION['login'] = true;
            $_SESSION['nombre'] = $usuario->nombre;
            $_SESSION['barbero'] = (int) $usuario->barbero;
            $_SESSION['id_tarjeta'] = $_SESSION['id_tarjeta'] = !empty($usuario->id_tarjeta) ? (int)$usuario->id_tarjeta : null;

            // --- Procesar vinculación pendiente (si existe) ---
            // Importante: usar el objeto $usuario que ya tenemos (no re-leer por $_SESSION['id'])
            if (!empty($_SESSION['pending_vincular_code'])) {
                $pendingCode = $_SESSION['pending_vincular_code'];
                unset($_SESSION['pending_vincular_code']); // consumible una vez

                $tarjeta = \Model\Tarjeta::findBy('codigo', $pendingCode);
                if ($tarjeta) {
                    // evitar sobreescribir si ya tiene otra tarjeta (cambiar según negocio)
                    if (empty($usuario->id_tarjeta) || (int)$usuario->id_tarjeta !== (int)$tarjeta->id) {
                        $usuario->id_tarjeta = (int)$tarjeta->id;
                        $ok = $usuario->guardar();
                        if ($ok) {
                            \Model\Usuario::addAlert('success','Tarjeta vinculada automáticamente tras iniciar sesión', true);
                        } else {
                            \Model\Usuario::addAlert('error','No se pudo vincular la tarjeta tras iniciar sesión', true);
                        }
                    } else {
                        \Model\Usuario::addAlert('info','Tu cuenta ya está vinculada a esa tarjeta', true);
                    }
                } else {
                    \Model\Usuario::addAlert('error','El código para vincular expiró o es inválido', true);
                }
            }

            // Redirigir según rol (agrupar en condición clara)
            if ((int) $_SESSION['barbero'] === 1) {
                \Model\Usuario::addAlert('success', 'Bienvenido a la administración, ' . $usuario->nombre, true);
                header('Location: /administrar-tarjeta');
                exit;
            } else {
                \Model\Usuario::addAlert('success', 'Bienvenido ' . $usuario->nombre, true);
                header('Location: /tarjeta');
                exit;
            }
        }

        // GET -> mostrar login (traer alertas flash si las hay)
        $alerts = \Model\Usuario::getAllAlertsAndClear(); // trae y limpia flash + memoria
        $router->render('auth/login', [
            'alerts' => $alerts
        ]);
    }

    // Logout
    public static function logout(Router $router) {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        session_destroy();
        header('Location: /');
        exit;
    }

    // Registro
    public static function registrar(Router $router) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? ''
            ];

            $usuario = new Usuario($data);
            $usuario->validarNuevaCuenta();
            $password2 = $_POST['password2'] ?? '';
            if (($data['password'] ?? '') !== $password2) {
                // agregamos la alerta al modelo (no flash, porque vamos a renderizar la misma vista)
                Usuario::addAlert('error', 'Las contraseñas no coinciden');
            }
            $errs = Usuario::getAlertas();
            if (!empty($errs)) {
                // convertir a array plano y renderizar sin redirect
                $flat = [];
                foreach ($errs as $type => $msgs) {
                    foreach ($msgs as $m) $flat[] = ['type'=>$type,'message'=>$m];
                }
                $router->render('auth/registro', ['usuario' => $usuario, 'alerts' => $flat]);
                return;
            }

            // Verificar si ya existe
            if ($usuario->existeUsuario()) {
                Usuario::addAlert('error', 'El email ya está registrado', true);
                header('Location: /registro');
                exit;
            }

            // Preparar y guardar
            $usuario->hashPassword();
            // $usuario->crearToken();
            $usuario->confirmado = 1; // Que sea 0 cuando haya email
            $usuario->barbero = 0;
            $ok = $usuario->guardar();

            if ($ok) {
                // Aquí deberías enviar el email de confirmación con $usuario->token
                // enviarEmailConfirmacion($usuario->email, $usuario->token);
                Usuario::addAlert('success', 'Cuenta creada. Revisa tu email para confirmar.', true);
                header('Location: /');
                exit;
            } else {
                Usuario::addAlert('error', 'Hubo un error al crear la cuenta', true);
                header('Location: /registro');
                exit;
            }
        }

        // GET -> mostrar formulario
        $alerts = Usuario::getAllAlertsAndClear();
        $router->render('auth/registro', [
            'alerts'  => $alerts
        ]);
    }

    // Confirmar cuenta por token
    public static function confirmar(Router $router) {
        $token = $_GET['token'] ?? null;
        if (!$token) {
            Usuario::addAlert('error', 'Token inválido', true);
            header('Location: /');
            exit;
        }

        $usuario = Usuario::findBy('token', $token);
        if (!$usuario) {
            Usuario::addAlert('error', 'Token no válido o usuario no encontrado', true);
            header('Location: /');
            exit;
        }

        $usuario->confirmado = 1;
        $usuario->token = null;
        $usuario->guardar();

        Usuario::addAlert('success', 'Cuenta confirmada. Ya podés iniciar sesión', true);
        header('Location: /');
        exit;
    }

    // Olvidé mi contraseña (enviar email con token)
    public static function olvide(Router $router) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $usuario = Usuario::findBy('email', $email);

            if (!$usuario) {
                Usuario::addAlert('error', 'No existe una cuenta con ese email', true);
                header('Location: /olvide');
                exit;
            }

            // Generar token y guardar
            $usuario->crearToken();
            $usuario->guardar();

            // Enviar email con link de recuperación (pendiente implementar)
            // enviarEmailRecuperacion($usuario->email, $usuario->token);

            Usuario::addAlert('success', 'Te enviamos instrucciones al email para recuperar tu contraseña', true);
            header('Location: /');
            exit;
        }

        $alerts = Usuario::getAllAlertsAndClear();
        $router->render('auth/olvide', ['alerts' => $alerts]);
    }

    // Recuperar (nueva contraseña)
    public static function recuperar(Router $router) {
        $token = $_GET['token'] ?? null;
        if (!$token) {
            Usuario::addAlert('error', 'Token inválido', true);
            header('Location: /');
            exit;
        }

        $usuario = Usuario::findBy('token', $token);
        if (!$usuario) {
            Usuario::addAlert('error', 'Token no válido', true);
            header('Location: /');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';

            if ($password !== $password2) {
                // mostrar en la misma vista (no redirect)
                Usuario::addAlert('error', 'Las contraseñas no coinciden', false);
                $flat = Usuario::getAllAlertsAndClear(); // leer y limpiar memoria
                $router->render('auth/recuperar', ['usuario' => $usuario, 'alerts' => $flat]);
                return;
            }

            $usuario->password = $password;
            $usuario->validarPasswordReset();
            $errs = Usuario::getAlertas();
            if (!empty($errs)) {
                $flat = [];
                foreach ($errs as $type => $msgs) {
                    foreach ($msgs as $m) $flat[] = ['type'=>$type,'message'=>$m];
                }
                $router->render('auth/recuperar', ['usuario' => $usuario, 'alerts' => $flat]);
                return;
            }

            $usuario->hashPassword();
            $usuario->token = null;
            $usuario->guardar();

            Usuario::addAlert('success', 'Contraseña actualizada. Ya podés iniciar sesión', true);
            header('Location: /');
            exit;
        }

        // GET -> mostrar formulario para nueva contraseña
        $alerts = Usuario::getAllAlertsAndClear();
        $router->render('auth/recuperar', ['usuario' => $usuario, 'alerts' => $alerts]);
    }
}
