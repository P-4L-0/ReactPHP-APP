<?php
require 'vendor/autoload.php';

use React\Http\Server;
use React\Http\Message\Response;
use React\Socket\Server as SocketServer;
use React\EventLoop\Factory;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Psr\Http\Message\ServerRequestInterface;


// creacion de el loop de eventos
$loop = Factory::create();

// creacion de rutas
$dispatcher = simpleDispatcher(function (RouteCollector $r) {
    // Definir las rutas
    $r->addRoute('GET', '/', function () {
        $html = file_get_contents(__DIR__ . '/public/index.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    });

    $r->addRoute('GET', '/contact', function () {
        $html = file_get_contents(__DIR__ . '/public/contact.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    });

    $r->addRoute('GET', '/data', function () {
        $html = file_get_contents(__DIR__ . '/public/data.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
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

    // Servir archivos estáticos desde /public
    $filePath = __DIR__ . '/public' . $uri;
    if ($httpMethod === 'GET' && file_exists($filePath) && is_file($filePath)) {
        // Detectar el tipo de contenido (Content-Type)
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'html' => 'text/html',
        ];
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
        return new Response(200, ['Content-Type' => $contentType], file_get_contents($filePath));
    }

    // manejo de rutas invalidas
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
    return new Response(500, ['Content-Type' => 'text/plain'], "Error interno del servidor: " . $e->getMessage());
}