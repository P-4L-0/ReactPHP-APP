<?php
require 'vendor/autoload.php';

use React\Http\Server;
use React\Http\Message\Response;
use React\Socket\Server as SocketServer;
use React\EventLoop\Factory;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

// creacion de el loop de eventos
$loop = Factory::create();


// creacion de rutas
$dispatcher = simpleDispatcher(function (RouteCollector $r) {
    // Definir las rutas
    $r->addRoute('GET', '/', function () {
        return new Response(200, ['Content-Type' => 'text/plain'], "¡Hola Mundo!");
    });

    $r->addRoute('GET', '/about', function () {
        return new Response(200, ['Content-Type' => 'text/plain'], "Sobre nosotros");
    });
});

// creacion del servidor http
$server = new Server(function ($request) use ($dispatcher) {
    /*-MANEJO DE RUTAS*/

    //obtener metodo
    $httpMethod = $request->getMethod();

    //obtener url 
    $uri = $request->getUri()->getPath();

    //mathch con una ruta
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            return new Response(404, ['Content-Type' => 'text/plain'], "Ruta no encontrada");
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            return new Response(405, ['Content-Type' => 'text/plain'], "Método no permitido");
        case FastRoute\Dispatcher::FOUND:
            // Llamar al callback de la ruta
            return $routeInfo[1]();
    }

});

// try catch para el manejo de errores
try {
    $socket = new SocketServer('127.0.0.1:8080', $loop);
    $server->listen($socket);
    echo "Servidor escuchando en 127.0.0.1:8080\n";
    $loop->run();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}