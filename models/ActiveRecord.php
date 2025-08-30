<?php
/**
 * ActiveRecord.php — Reacondicionado (mysqli + prepared statements)
 *
 * CAMBIOS/MEJORAS CLAVE
 * ------------------------------------------------------------------
 * 1) Unificación a mysqli (se eliminó uso de PDO).
 * 2) INSERT (crear) y UPDATE (actualizar) con prepared statements y
 *    binding dinámico para mayor seguridad (evitar SQL injection).
 * 3) Nuevos métodos utilitarios:
 *      - findBy(string $columna, $valor): 1 registro o null.
 *      - whereAll(string $columna, $valor): array de registros.
 *      - whereLikeMultiple(array $campo=>valor): búsqueda con LIKE.
 * 4) Correcciones de coherencia:
 *      - find($id) escapa el id y retorna 1 o null.
 *      - get($limite) retorna un array (antes hacía array_shift).
 * 5) Mantiene consultarSQL() para SELECT simples ya compuestos.
 *
 * NOTAS IMPORTANTES
 * ------------------------------------------------------------------
 * - Asegurate de setear la conexión con ActiveRecord::setDB($mysqli).
 * - Si usas logs de depuración (error_log), apagalos en producción.
 * - Para selects dinámicos preferí findBy/whereAll en lugar de SQL crudo.
 * - where() del código original devolvía 1 registro; se deja una
 *   versión DEPRECADA que llama a findBy para no romper compatibilidad.
 */

namespace Model;

class ActiveRecord {

    /** @var \mysqli */
    protected static $db;
    protected static $tabla = '';
    protected static $columnasDB = [];

    // Alertas y mensajes (heredadas en modelos concretos)
    protected static $alertas = [];

    public function __construct(array $args = []) {
        // Inicializa propiedades dinámicamente según columnas del modelo
        foreach (static::$columnasDB as $columna) {
            if ($columna === 'id') continue;
            $this->$columna = $args[$columna] ?? null;
        }
    }

    // ====================== Configuración & Alerts ======================

    /** Inyecta la conexión mysqli */
    public static function setDB($database) {
        self::$db = $database;
    }

    public static function addAlert(string $type, string $message, bool $flash = false): void {
        if ($flash) {
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            if (!isset($_SESSION['alerts']) || !is_array($_SESSION['alerts'])) {
                $_SESSION['alerts'] = [];
            }
            $_SESSION['alerts'][] = ['type' => $type, 'message' => $message];
            return;
        }

        // alerta para la petición actual (se guarda en la clase del modelo)
        static::$alertas[$type][] = $message;
    }

    // Retorna solo las alertas en memoria del modelo (no incluye flash)
    public static function getAlertas(): array {
        return static::$alertas ?? [];
    }

    // Limpia las alertas en memoria del modelo
    public static function clearAlertas(): void {
        static::$alertas = [];
    }

    // Retorna alertas flash guardadas en $_SESSION (sin borrarlas)
    public static function getFlashAlerts(): array {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION['alerts'] ?? [];
    }

    // Limpia alertas flash en $_SESSION
    public static function clearFlashAlerts(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION['alerts']);
    }

    // Retorna todas las alertas (flash + del modelo actual) y las limpia
    public static function getAllAlertsAndClear(): array {
        // 1) flash
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $flash = $_SESSION['alerts'] ?? [];
        unset($_SESSION['alerts']);

        // 2) alertas del modelo (static)
        $memory = static::$alertas ?? [];
        static::$alertas = []; // limpiar

        // Normalizar el formato a lista plana: [ ['type'=>..., 'message'=>...], ... ]
        $out = [];

        foreach ($flash as $a) {
            if (isset($a['type']) && isset($a['message'])) {
                $out[] = ['type' => $a['type'], 'message' => $a['message']];
            }
        }

        foreach ($memory as $type => $messages) {
            foreach ($messages as $msg) {
                $out[] = ['type' => $type, 'message' => $msg];
            }
        }

        return $out;
    }

    /** Punto de extensión para validaciones en modelos hijos */
    public function validar() {
        static::$alertas = [];
        return static::$alertas;
    }

    // ====================== Helpers internos ============================

    /** Ejecuta un SELECT ya armado y devuelve array de objetos del modelo */
    public static function consultarSQL($query) {
        $resultado = self::$db->query($query);
        if(!$resultado) {
            error_log("ActiveRecord->consultarSQL ERROR: " . self::$db->error);
            return [];
        }

        $array = [];
        while($registro = $resultado->fetch_assoc()) {
            $array[] = static::crearObjeto($registro);
        }
        $resultado->free();
        return $array;
    }

    /** Convierte un array asociativo (fila) en objeto del modelo */
    protected static function crearObjeto($registro) {
        $objeto = new static;
        foreach($registro as $key => $value ) {
            if(property_exists($objeto, $key)) {
                $objeto->$key = $value;
            }
        }
        return $objeto;
    }

    /** Devuelve atributos del objeto (sin id) como array asociativo */
    public function atributos() {
        $atributos = [];
        foreach(static::$columnasDB as $columna) {
            if($columna === 'id') continue;
            $atributos[$columna] = $this->$columna;
        }
        return $atributos;
    }

    /** Sincroniza propiedades desde un array (útil en formularios) */
    public function sincronizar($args = []) {
        foreach($args as $key => $value) {
            if(property_exists($this, $key) && !is_null($value)) {
                $this->$key = $value;
            }
        }
    }

    // ====================== CRUD público ================================

    /** Crea o actualiza según exista id */
    public function guardar() {
        if(!is_null($this->id)) {
            return $this->actualizar();
        } else {
            return $this->crear();
        }
    }

    /** SELECT * FROM tabla */
    public static function all() {
        $query = "SELECT * FROM " . static::$tabla;
        return self::consultarSQL($query);
    }

    /** Busca por id (seguro) */
    public static function find($id) {
        $idEsc = intval($id);
        $query = "SELECT * FROM " . static::$tabla . " WHERE id = {$idEsc} LIMIT 1";
        $resultado = self::consultarSQL($query);
        return array_shift($resultado) ?: null;
    }

    /** Devuelve N registros */
    public static function get($limite) {
        $limiteEsc = (int)$limite;
        $query = "SELECT * FROM " . static::$tabla . " LIMIT {$limiteEsc}";
        return self::consultarSQL($query);
    }

    /** Busca 1 por columna=valor */
    public static function findBy(string $columna, $valor) {
        $valorEsc = self::$db->escape_string($valor);
        $query = "SELECT * FROM " . static::$tabla . " WHERE {$columna} = '{$valorEsc}' LIMIT 1";
        $resultado = self::consultarSQL($query);
        return array_shift($resultado) ?: null;
    }

    /** Busca TODOS por columna=valor */
    public static function whereAll(string $columna, $valor) {
        $valorEsc = self::$db->escape_string($valor);
        $query = "SELECT * FROM " . static::$tabla . " WHERE {$columna} = '{$valorEsc}'";
        return self::consultarSQL($query);
    }

    /** DEPRECADO: donde columna=valor y retorna 1 (compatibilidad) */
    public static function where($columna, $valor) {
        return static::findBy($columna, $valor);
    }

    /** Consulta plana */
    public static function SQL($query) {
        return self::consultarSQL($query);
    }

    /** Búsqueda con LIKE en múltiples campos */
    public static function whereLikeMultiple(array $camposYValores) {
        $filtros = [];
        foreach ($camposYValores as $campo => $valor) {
            $valorEsc = self::$db->escape_string($valor);
            $filtros[] = "{$campo} LIKE '%{$valorEsc}%'";
        }
        $query = "SELECT * FROM " . static::$tabla . " WHERE " . implode(" OR ", $filtros);
        return self::consultarSQL($query);
    }

    // ====================== INSERT/UPDATE (prepared) ====================

    /** INSERT dinámico con prepared statements */
    protected function crear() {
        $db = self::$db;
        $atributos = $this->atributos();

        // Filtrar NULLs (no se insertan columnas con null)
        $filtrados = array_filter($atributos, function($valor) {
            return !is_null($valor);
        });

        if(empty($filtrados)) {
            error_log("ActiveRecord->crear: no hay atributos para insertar.");
            return false;
        }

        $columnas = array_keys($filtrados);
        $placeholders = array_fill(0, count($filtrados), '?');

        $sql = "INSERT INTO " . static::$tabla . " (" . implode(", ", $columnas) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $db->prepare($sql);
        if(!$stmt) {
            error_log("ActiveRecord->crear prepare ERROR: " . $db->error);
            return false;
        }

        // Tipos y valores
        $types = '';
        $values = [];
        foreach ($filtrados as $val) {
            if (is_int($val)) $types .= 'i';
            else $types .= 's';
            $values[] = $val;
        }

        // Bind dinámico (por referencia)
        $refs = [];
        foreach ($values as $i => $v) { $refs[$i] = &$values[$i]; }
        array_unshift($refs, $types);
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            error_log("ActiveRecord->crear bind_param ERROR: " . $stmt->error);
            return false;
        }

        $ok = $stmt->execute();
        if (!$ok) {
            error_log("ActiveRecord->crear execute ERROR: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $this->id = $db->insert_id;
        $stmt->close();
        return true;
    }

    /** UPDATE dinámico con prepared statements */
    protected function actualizar() {
        $db = self::$db;

        // Si existe, refrescamos timestamp de actualización
        if(property_exists($this, 'actualizado_en')) {
            $this->actualizado_en = date('Y-m-d H:i:s');
        }

        $atributos = $this->atributos();
        $sets = [];
        $values = [];
        foreach ($atributos as $col => $val) {
            if ($col === 'creado_en') continue; // no tocar creado_en
            $sets[] = "{$col} = ?";
            $values[] = $val; // puede ser null
        }

        if(empty($sets)) {
            error_log("ActiveRecord->actualizar: no hay valores para actualizar.");
            return false;
        }

        $sql = "UPDATE " . static::$tabla . " SET " . implode(", ", $sets) . " WHERE id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        if(!$stmt) {
            error_log("ActiveRecord->actualizar prepare ERROR: " . $db->error);
            return false;
        }

        // tipos (s por defecto, i para enteros)
        $types = '';
        foreach ($values as $val) {
            if (is_int($val)) $types .= 'i'; else $types .= 's';
        }
        // id al final
        $types .= 'i';
        $values[] = (int)$this->id;

        $refs = [];
        foreach ($values as $k => $v) { $refs[$k] = &$values[$k]; }
        array_unshift($refs, $types);

        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            error_log("ActiveRecord->actualizar bind_param ERROR: " . $stmt->error);
            return false;
        }

        $ok = $stmt->execute();
        if (!$ok) {
            error_log("ActiveRecord->actualizar execute ERROR: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    // ====================== DELETE =====================================

    public function eliminar() {
        $idEsc = (int)$this->id;
        $sql = "DELETE FROM " . static::$tabla . " WHERE id = {$idEsc} LIMIT 1";
        $ok = self::$db->query($sql);
        if(!$ok) {
            error_log("ActiveRecord->eliminar ERROR: " . self::$db->error);
        }
        return $ok;
    }

    // ====================== Utilidades extra ============================

    /** Devuelve el handler mysqli (por si necesitás operaciones avanzadas) */
    public static function getDB() {
        return self::$db;
    }

} // end class
