<?php
namespace Model;

class Canje extends ActiveRecord {
    protected static $tabla = 'canje';
    // columnasDB inclúyelas si quieres (no obligatorias para este ejemplo)
    protected static $columnasDB = [
        'id','id_recompensa','id_usuario','id_tarjeta','status','aprobado_por','aprobado_en','creado_en','actualizado_en'
    ];

    public $id;
    public $id_recompensa;
    public $id_usuario;
    public $id_tarjeta;
    public $status;
    public $aprobado_por;
    public $aprobado_en;
    public $creado_en;
    public $actualizado_en;

    public function __construct($args = []) {
        $this->id = $args['id'] ?? null;
        $this->id_recompensa = $args['id_recompensa'] ?? ($args['id_recompensa'] ?? null);
        $this->id_usuario = $args['id_usuario'] ?? null;
        $this->id_tarjeta = $args['id_tarjeta'] ?? null;
        $this->status = $args['status'] ?? null;
        $this->aprobado_por = $args['aprobado_por'] ?? null;
        $this->aprobado_en = $args['aprobado_en'] ?? null;
        $this->creado_en = $args['creado_en'] ?? null;
        $this->actualizado_en = $args['actualizado_en'] ?? null;
    }

    /**
     * Comprueba si una recompensa ya fue aprobada (o existe registro canje) para un usuario.
     * Esta función intenta ser tolerante a diferencias en nombres de columnas entre esquemas.
     *
     * @param int $idRecompensa
     * @param int $idUsuario
     * @return bool
     */
    public static function isRewardAlreadyApprovedForUser(int $idRecompensa, int $idUsuario): bool {
        $db = self::getDB();

        // 1) detectar nombre de columna para la referencia de recompensa
        // comprobamos variantes comunes: 'id_recompensa' y 'id_recompensa'
        $colRecompensa = null;
        $res = $db->query("SHOW COLUMNS FROM `canje` LIKE 'id_recompensa'");
        if ($res && $res->num_rows > 0) $colRecompensa = 'id_recompensa';
        else {
            $res2 = $db->query("SHOW COLUMNS FROM `canje` LIKE 'id_recompensa'");
            if ($res2 && $res2->num_rows > 0) $colRecompensa = 'id_recompensa';
        }

        if (!$colRecompensa) {
            // Si no existe ninguna de las dos, buscar otras variantes genéricas
            $variants = ['id_recompensa_id','recompensa_id','id_recompensa_id'];
            foreach ($variants as $v) {
                $r = $db->query("SHOW COLUMNS FROM `canje` LIKE '".$db->escape_string($v)."'");
                if ($r && $r->num_rows > 0) { $colRecompensa = $v; break; }
            }
        }

        if (!$colRecompensa) {
            // No encontramos columna esperada -> no podemos comprobar: asumir falso (no aprobado).
            error_log("Canje::isRewardAlreadyApprovedForUser - columna de recompensa no encontrada en 'canje' table");
            return false;
        }

        // 2) detectar si existe columna 'status' para filtrar por estado 'approved'
        $hasStatus = false;
        $r3 = $db->query("SHOW COLUMNS FROM `canje` LIKE 'status'");
        if ($r3 && $r3->num_rows > 0) $hasStatus = true;

        // 3) preparar y ejecutar la consulta de forma segura
        $idR = (int)$idRecompensa;
        $idU = (int)$idUsuario;

        if ($hasStatus) {
            $sql = "SELECT id FROM canje WHERE `{$colRecompensa}` = {$idR} AND `id_usuario` = {$idU} AND `status` = 'approved' LIMIT 1";
        } else {
            // si no hay status, cualquier registro se considera "canjeado/aprobado" según tu esquema antiguo
            $sql = "SELECT id FROM canje WHERE `{$colRecompensa}` = {$idR} AND `id_usuario` = {$idU} LIMIT 1";
        }

        $resultado = self::SQL($sql); // reutiliza ActiveRecord::SQL que retorna array
        return !empty($resultado);
    }

    public function validar(): array {
        static::clearAlertas();
        if (!$this->id_recompensa) static::addAlert('error','id_recompensa requerido');
        if (!$this->id_usuario) static::addAlert('error','id_usuario requerido');
        return static::getAlertas();
    }
}
