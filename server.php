<?php
require 'vendor/autoload.php';

use React\Http\Server;
use React\Http\Message\Response;
use React\Socket\Server as SocketServer;
use React\EventLoop\Factory;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Psr\Http\Message\ServerRequestInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use React\Promise\PromiseInterface;

// creacion de el loop de eventos
$loop = Factory::create();

// creacion de rutas
$dispatcher = simpleDispatcher(function (RouteCollector $r) use ($loop) {
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

    //ruta para obtener clientes
    $r->addRoute('GET', '/dataClients', function () use ($loop) {
        try {
            $dataFile = __DIR__ . '/data/clients.json';

            // Usar promesa para leer el archivo
            $promise = React\Promise\resolve(file_get_contents($dataFile));

            return $promise->then(function ($data) {
                $dataDecoded = json_decode($data, true);
                return new Response(200, ['Content-Type' => 'application/json'], json_encode($dataDecoded));
            }, function () {
                return new Response(404, ['Content-Type' => 'text/plain'], "Datos no encontrados");
            });
        } catch (Exception $e) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Error del servidor: ' . $e->getMessage()
            ]));
        }
    });

    //ruta para agregar clientes
    $r->addRoute('POST', '/dataClients', function (ServerRequestInterface $request) use ($loop) {
        $dataFile = __DIR__ . '/data/clients.json';
        $inputClients = $request->getParsedBody();


        if (isset($inputClients['name'], $inputClients['email'])) {
            return React\Promise\resolve(file_get_contents($dataFile))
                ->then(function ($data) use ($inputClients, $dataFile) {
                    $existingData = json_decode($data, true) ?? ['clients' => []];
                    $existingData['clients'][] = [
                        'name' => $inputClients['name'],
                        'email' => $inputClients['email']
                    ];

                    // Escribir en el archivo de manera asíncrona
                    file_put_contents($dataFile, json_encode($existingData, JSON_PRETTY_PRINT));

                    return new Response(201, ['Content-Type' => 'application/json'], json_encode(['message' => 'Tarea agregada exitosamente']));
                })
                ->otherwise(function () {
                    return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Error al leer archivo']));
                });
        } else {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Datos incompletos']));
        }
    });

    //ruta para eliminar clientes
    $r->addRoute('DELETE', '/dataClients/{email}', function (ServerRequestInterface $request, $args) use ($loop) {
        try {
            $dataFile = __DIR__ . '/data/clients.json';
            $email = $args['email'];

            return React\Promise\resolve(file_get_contents($dataFile))
                ->then(function ($data) use ($email, $dataFile) {
                    $existingData = json_decode($data, true);
                    $clientIndex = null;

                    foreach ($existingData['clients'] as $index => $client) {
                        if ($client['email'] == $email) {
                            $clientIndex = $index;
                            break;
                        }
                    }

                    if ($clientIndex === null) {
                        return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Cliente no encontrado']));
                    }

                    // Eliminar el cliente
                    array_splice($existingData['clients'], $clientIndex, 1);

                    // Escribir los nuevos datos en el archivo
                    file_put_contents($dataFile, json_encode($existingData, JSON_PRETTY_PRINT));

                    return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Cliente eliminado exitosamente']));
                })
                ->otherwise(function () {
                    return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Error al leer archivo']));
                });
        } catch (Exception $e) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Error del servidor: ' . $e->getMessage()
            ]));
        }
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
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            // Verificamos cuántos parámetros acepta la función
            $refFunc = new ReflectionFunction($handler);
            $numParams = $refFunc->getNumberOfParameters();

            if ($numParams === 2) {
                return $handler($request, $vars);
            } elseif ($numParams === 1) {
                return $handler($request);
            } else {
                return $handler();
            }
    }

});

// try catch para el manejo de errores
try {
    $socket = new SocketServer('127.0.0.1:8080', $loop);
    $server->listen($socket);
    echo "Servidor escuchando en 127.0.0.1:8080\n";
    $loop->run();
} catch (Exception $e) {
    echo "Error interno del servidor: " . $e->getMessage();
    exit(1);
}