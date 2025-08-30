<?php
namespace Model;

class Tarjeta extends ActiveRecord {
    protected static $tabla = 'tarjeta';
    protected static $columnasDB = [
        'id','codigo','casilla_max','creado_en','actualizado_en'
    ];

    public $id;
    public $codigo;
    public $casilla_max;
    public $creado_en;
    public $actualizado_en;

    public function __construct($args = []) {
        $this->id = $args['id'] ?? null;
        $this->codigo = $args['codigo'] ?? '';
        $this->casilla_max = isset($args['casilla_max']) ? (int)$args['casilla_max'] : 10;
        $this->creado_en = $args['creado_en'] ?? null;
        $this->actualizado_en = $args['actualizado_en'] ?? null;
    }

    // Validación para nueva tarjeta
    public function validarNueva(): array {
        static::clearAlertas();

        if (!$this->casilla_max || !is_numeric($this->casilla_max) || (int)$this->casilla_max <= 0) {
            static::addAlert('error', 'La cantidad de casillas debe ser un número mayor a 0.');
        }

        // (opcional) verificar longitud/código si viene seteado
        if ($this->codigo && strlen($this->codigo) > 77) {
            static::addAlert('error', 'El código es demasiado largo.');
        }

        return static::getAlertas();
    }

    // Genera un código único para la tarjeta
    public function generarCodigoUnico(int $bytes = 16): string {
        // Generar un string seguro (hex). Ajustá longitud si querés.
        do {
            $codigo = bin2hex(random_bytes($bytes)); // e.g. 32 hex chars si $bytes=16
            $existe = static::findBy('codigo', $codigo);
        } while ($existe);

        $this->codigo = $codigo;
        return $codigo;
    }
}
