<?php
namespace Model;

class Recompensa extends ActiveRecord {
    protected static $tabla = 'recompensas';
    protected static $columnasDB = ['id','id_tarjeta','casilla','descripcion','creado_en','actualizado_en'];

    public $id;
    public $id_tarjeta;
    public $casilla;
    public $descripcion;
    public $creado_en;
    public $actualizado_en;

    public function __construct($args = []) {
        $this->id = $args['id'] ?? null;
        $this->id_tarjeta = isset($args['id_tarjeta']) ? (int)$args['id_tarjeta'] : null;
        $this->casilla = isset($args['casilla']) ? (int)$args['casilla'] : null;
        $this->descripcion = $args['descripcion'] ?? '';
        $this->creado_en = $args['creado_en'] ?? null;
        $this->actualizado_en = $args['actualizado_en'] ?? null;
    }

    /**
     * Validar nueva recompensa.
     * @param bool $checkExistencia opcional: verificar que no exista otra recompensa en la misma casilla
     * @return array alertas (vacío si ok)
     */
    public function validarNueva(bool $checkExistencia = true): array {
        static::clearAlertas();

        if (empty($this->id_tarjeta)) {
            static::addAlert('error', 'Falta la tarjeta asociada.');
            return static::getAlertas();
        }

        if (empty($this->casilla) || !is_numeric($this->casilla) || (int)$this->casilla <= 0) {
            static::addAlert('error', 'La casilla debe ser un número entero mayor que 0.');
            return static::getAlertas();
        }

        // Traer la tarjeta para validar casilla_max
        $tarjeta = Tarjeta::find((int)$this->id_tarjeta);
        if (!$tarjeta) {
            static::addAlert('error', 'Tarjeta inexistente.');
            return static::getAlertas();
        }

        $casillaMax = (int)($tarjeta->casilla_max ?? 0);
        if ($casillaMax <= 0) {
            static::addAlert('error', 'La tarjeta no tiene una cantidad válida de casillas configurada.');
            return static::getAlertas();
        }

        if ($this->casilla > $casillaMax) {
            static::addAlert('error', "La casilla indicada ({$this->casilla}) excede el máximo de la tarjeta ({$casillaMax}).");
        }

        // Opcional: evitar duplicados en la misma casilla para la tarjeta
        if ($checkExistencia) {
            $ex = self::SQL("SELECT id FROM " . self::$tabla . " WHERE id_tarjeta = " . (int)$this->id_tarjeta . " AND casilla = " . (int)$this->casilla . " LIMIT 1");
            if (!empty($ex)) {
                static::addAlert('error', 'Ya existe una recompensa en esa casilla para esta tarjeta.');
            }
        }

        return static::getAlertas();
    }
}
