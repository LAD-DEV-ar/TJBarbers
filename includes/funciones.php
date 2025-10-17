<?php

function debuguear($variable) : string {
    echo "<pre>";
    var_dump($variable);
    echo "</pre>";
    exit;
}

// Escapa / Sanitizar el HTML
function s($html) : string {
    $s = htmlspecialchars($html);
    return $s;
}


function start_session_if_needed(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Requiere que el usuario esté logueado.
 * Si no lo está, redirige a $redirect (por defecto '/').
 */
function require_login_simple(string $redirect = '/'): void {
    start_session_if_needed();
    if (empty($_SESSION['id']) || empty($_SESSION['login'])) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Requiere que el usuario tenga el flag "barbero" (1).
 */
function require_barbero_simple(string $redirect = '/'): void {
    start_session_if_needed();
    if (empty($_SESSION['barbero']) || (int)$_SESSION['barbero'] !== 1) {
        header('Location: ' . $redirect);
        exit;
    }
}

function isBarbero(string $redirect = '/'): void {
    start_session_if_needed();
    if (!empty($_SESSION['barbero']) || (int)$_SESSION['barbero'] == 1) {
        \Model\Usuario::addAlert('warning','Si eres administrador no puedes utilizar tu propia tarjeta, solo la puedes administrar', true);
        header('Location: ' . $redirect);
        exit;
    }
}

function require_barbero_con_tarjeta(string $redirect = '/') : void {
    start_session_if_needed();

    if (empty($_SESSION['id']) || empty($_SESSION['login']) ) {
        header('Location:' . $redirect);
        exit;
    }
    if (empty($_SESSION['barbero']) || (int)$_SESSION['barbero'] !== 1) {
        header('Location:' . $redirect);
        exit;
    }

    // Obtener id_tarjeta desde la sesión si existe (mejor si cargás desde DB)
    if (empty($_SESSION['id_tarjeta'])) {
        // intentar cargar desde DB y colocarlo en sesión (fallback)
        if (class_exists('\Model\Usuario')) {
            $u = \Model\Usuario::find((int)$_SESSION['id']);
            if ($u && !empty($u->id_tarjeta)) {
                $_SESSION['id_tarjeta'] = (int)$u->id_tarjeta;
            }
        }
    }

    if (empty($_SESSION['id_tarjeta'])) {
        // el admin no tiene tarjeta asignada -> no puede administrar
        // en vez de permitir crear otra tarjeta aquí forzamos a que el admin primero
        // tenga una tarjeta asignada por el superadmin o por el proceso del local.
        \Model\ActiveRecord::addAlert('error','No tienes ninguna tarjeta, crea o vinculate a una para empezar a administrarla', true);
        header('Location:' . $redirect);
        exit;
    }
}