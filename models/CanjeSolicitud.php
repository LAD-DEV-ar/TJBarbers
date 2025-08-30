<?php
namespace Model;

class CanjeSolicitud extends ActiveRecord {
    protected static $tabla = 'canje_solicitudes';
    protected static $columnasDB = ['id','id_recompensa','id_usuario','id_tarjeta','estado','comentario','creado_en'];
    public static $alertas = [];

    public $id;
    public $id_recompensa;
    public $id_usuario;
    public $id_tarjeta;
    public $estado;
    public $comentario;
    public $creado_en;

    public function __construct($args = []) {
        $this->id = $args['id'] ?? null;
        $this->id_recompensa = $args['id_recompensa'] ?? null;
        $this->id_usuario = $args['id_usuario'] ?? null;
        $this->id_tarjeta = $args['id_tarjeta'] ?? null;
        $this->estado = $args['estado'] ?? 'pending';
        $this->comentario = $args['comentario'] ?? null;
        $this->creado_en = $args['creado_en'] ?? null;
    }

    public function validar(): array {
        static::clearAlertas();
        if (!$this->id_recompensa) static::addAlert('error','id_recompensa requerido');
        if (!$this->id_usuario) static::addAlert('error','id_usuario requerido');
        if (!$this->id_tarjeta) static::addAlert('error','id_tarjeta requerido');
        return static::getAlertas();
    }
}
