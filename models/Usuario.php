<?php
namespace Model;

class Usuario extends ActiveRecord {
    protected static $tabla = 'usuario';
    protected static $columnasDB = [
        'id','nombre','telefono','email','password','token',
        'confirmado','barbero','id_tarjeta','casilla_actual','racha','creado_en','actualizado_en'
    ];

    public $id;
    public $nombre;
    public $telefono;
    public $email;
    public $password;
    public $token;
    public $confirmado;
    public $barbero;
    public $id_tarjeta;
    public $casilla_actual;
    public $racha;
    public $creado_en;
    public $actualizado_en;

    public function __construct($args = []) {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->telefono = $args['telefono'] ?? '';
        $this->email = $args['email'] ?? '';
        $this->password = $args['password'] ?? '';
        $this->token = $args['token'] ?? null;
        $this->confirmado = $args['confirmado'] ?? 0;
        $this->barbero = $args['barbero'] ?? 0;
        $this->id_tarjeta = $args['id_tarjeta'] ?? null;
        $this->casilla_actual = $args['casilla_actual'] ?? 0;
        $this->racha = $args['racha'] ?? 0;
        $this->creado_en = $args['creado_en'] ?? null;
        $this->actualizado_en = $args['actualizado_en'] ?? null;
    }

    // -------------------
    // Validaciones
    // -------------------
    public function validarNuevaCuenta(): array {
        // limpiar alertas previas del modelo
        self::clearAlertas();

        if (!$this->nombre) self::addAlert('error', 'El nombre es obligatorio');
        if (!$this->telefono) self::addAlert('error', 'El teléfono es obligatorio');
        if (!$this->email) self::addAlert('error', 'El email es obligatorio');
        if ($this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) self::addAlert('error', 'Email inválido');
        if (!$this->password || strlen($this->password) < 6) self::addAlert('error', 'La contraseña debe tener al menos 6 caracteres');

        // devolver alertas en la forma asociativa (no las limpia)
        return self::getAlertas();
    }

    public function validarLogin(): array {
        self::clearAlertas();
        if (!$this->email) self::addAlert('error', 'El email es obligatorio');
        if (!$this->password) self::addAlert('error', 'La contraseña es obligatoria');
        return self::getAlertas();
    }

    public function validarEmail(): array {
        self::clearAlertas();
        if (!$this->email) self::addAlert('error', 'El email es obligatorio');
        if ($this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) self::addAlert('error', 'Email inválido');
        return self::getAlertas();
    }

    public function validarPasswordReset(): array {
        self::clearAlertas();
        if (!$this->password || strlen($this->password) < 6) self::addAlert('error', 'La contraseña debe tener al menos 6 caracteres');
        return self::getAlertas();
    }

    // -------------------
    // Utilidades
    // -------------------
    public function hashPassword(): void {
        // Sólo hash si no está ya hasheada (asumiendo que si tiene $2y$ es hash de bcrypt)
        if ($this->password && strpos($this->password, '$2y$') !== 0 && strpos($this->password, '$2a$') !== 0) {
            $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        }
    }

    public function crearToken(): void {
        $this->token = bin2hex(random_bytes(16)); // 32 chars hex
    }

    public function comprobarPassword(string $passwordIngresado): bool {
        // Si en BD se guardó hash, password_verify; si por algún motivo está plain (no recomendable) fallback a comparación simple
        if (empty($this->password)) return false;
        if (password_needs_rehash($this->password, PASSWORD_BCRYPT)) {
            // aún así verificamos antes de re-hashear
        }
        return password_verify($passwordIngresado, $this->password);
    }

    public function existeUsuario(): bool {
        $usuario = self::findBy('email', $this->email);
        return !empty($usuario);
    }

    public function estaConfirmado(): bool {
        return (int)$this->confirmado === 1;
    }

    public function marcarConfirmado(): bool {
        $this->confirmado = 1;
        $this->token = null;
        return $this->guardar();
    }

    // Opcional: reiniciar token y guardar (para recuperación)
    public function generarTokenRecuperacion(): bool {
        $this->crearToken();
        return $this->guardar();
    }
}
