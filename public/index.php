<?php 

require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\TarjetaController;
use Controllers\AuthController;
use Controllers\AdminController;
use Controllers\VisitaController;
use Controllers\RecompensaController;

$router = new Router();

// ====== Autenticacion ======
$router->get('/', [AuthController::class, 'login']);
$router->post('/', [AuthController::class, 'login']);
$router->get('/registro', [AuthController::class, 'registrar']);
$router->post('/registro', [AuthController::class, 'registrar']);
$router->get('/confirmar', [AuthController::class, 'confirmar']);
$router->get('/olvide', [AuthController::class, 'olvide']);
$router->post('/olvide', [AuthController::class, 'olvide']);
$router->get('/recuperar', [AuthController::class, 'recuperar']);
$router->post('/recuperar', [AuthController::class, 'recuperar']);
$router->get('/logout', [AuthController::class, 'logout']);


// ====== Interfaz Tarjeta CLIENTE ======
$router->get('/tarjeta', [Controllers\TarjetaController::class, 'index']);
$router->get('/api/tarjeta', [Controllers\TarjetaController::class, 'apiGetTarjeta']);              // retorna JSON con casilla_max y casilla_actual
$router->post('/api/tarjeta/avanzar', [Controllers\TarjetaController::class, 'apiAvanzar']);       // incremento (solo barbero)
// Solicitar canje (usuario) - soporta fetch JSON y form POST
$router->post('/tarjeta/solicitar-canje', [TarjetaController::class, 'solicitarCanje']);

$router->get('/tarjeta/vincular', [Controllers\TarjetaController::class, 'vincularForm']);
$router->post('/tarjeta/vincular', [Controllers\TarjetaController::class, 'vincularSubmit']);


// ====== Administracion Tarjeta Barberos ======
$router->get('/administrar-tarjeta', [Controllers\AdminController::class, 'administrarTarjeta']);
$router->post('/administrar-tarjeta', [Controllers\AdminController::class, 'crearTarjeta']);

// Rutas para registrar visita
$router->get('/registrar-visita', [Controllers\VisitaController::class, 'form']);
$router->post('/admin/registrar-visita', [Controllers\AdminController::class, 'registrarVisita']);        // form clásico (redirect)
$router->post('/api/registrar-visita', [Controllers\VisitaController::class, 'apiRegistrar']);  // endpoint JSON (AJAX)

// endpoints para recompensas / canje
$router->get('/api/tarjeta/recompensas', [Controllers\TarjetaController::class, 'apiRecompensas']);
$router->post('/api/tarjeta/canjear', [Controllers\TarjetaController::class, 'apiCanjear']);
$router->post('/api/tarjeta/solicitar-canje', [Controllers\TarjetaController::class, 'apiSolicitarCanje']);


// CRUD recompensas (solo admin/barbero)
$router->get('/admin/recompensas', [Controllers\RecompensaController::class, 'index']);         // ?tarjeta_id=#
$router->get('/admin/recompensas/crear', [Controllers\RecompensaController::class, 'crearForm']); 
$router->post('/admin/recompensas/crear', [Controllers\RecompensaController::class, 'crearRecompensa']);
$router->get('/admin/recompensas/editar', [Controllers\RecompensaController::class, 'editarForm']); // ?id=#
$router->post('/admin/recompensas/editar', [Controllers\RecompensaController::class, 'editarSubmit']);
$router->post('/admin/recompensas/eliminar', [Controllers\RecompensaController::class, 'eliminar']); // POST {id, tarjeta_id}

// Solicitudes (admin)
$router->get('/admin/solicitudes', [AdminController::class, 'listarSolicitudes']);
$router->post('/admin/solicitudes/aprobar', [AdminController::class, 'aprobarSolicitud']);
$router->post('/admin/solicitudes/rechazar', [AdminController::class, 'rechazarSolicitud']);









// Comprueba y valida las rutas, que existan y les asigna las funciones del Controlador
$router->comprobarRutas();