<?php

namespace MVC;

class Router
{
    public array $getRoutes = [];
    public array $postRoutes = [];

    public function get($url, $fn)
    {
        $this->getRoutes[$url] = $fn;
    }

    public function post($url, $fn)
    {
        $this->postRoutes[$url] = $fn;
    }

    public function comprobarRutas(){
    // Iniciar sesión para sesiones protegidas


    // Obtener la ruta sin query string
    $currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Si tu app está en un subdirectorio, remueve el prefijo:
    // $currentUrl = substr($currentUrl, strlen('/subdirectorio'));

    $method = $_SERVER['REQUEST_METHOD'];

    $fn = $method === 'GET'
        ? $this->getRoutes[$currentUrl] ?? null
        : $this->postRoutes[$currentUrl] ?? null;

    if ($fn) {
        call_user_func($fn, $this);
    } else {
        http_response_code(404);
        include_once __DIR__ . '/views/404.php';
    }
}

    public function render($view, $datos = [])
    {

        // Leer lo que le pasamos  a la vista
        foreach ($datos as $key => $value) {
            $$key = $value;  // Doble signo de dolar significa: variable variable, básicamente nuestra variable sigue siendo la original, pero al asignarla a otra no la reescribe, mantiene su valor, de esta forma el nombre de la variable se asigna dinamicamente
        }

        ob_start(); // Almacenamiento en memoria durante un momento...

        // entonces incluimos la vista en el layout
        include_once __DIR__ . "/views/$view.php";
        $contenido = ob_get_clean(); // Limpia el Buffer
        include_once __DIR__ . '/views/layout.php';
    }
}
