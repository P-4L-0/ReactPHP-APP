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

    //ruta para obtener 
    $r->addRoute('GET', '/comments', function () {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->query("SELECT * FROM comments");
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return new Response(200, ['Content-Type' => 'application/json'], json_encode($comments));
        } catch (Exception $e) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Error al obtener comentarios: ' . $e->getMessage()]));
        }
    });

    //ruta para agregar 
    $r->addRoute('POST', '/comments', function (ServerRequestInterface $request) {
        $input = $request->getParsedBody();

        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');

        // Validaciones básicas
        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Todos los campos obligatorios deben estar completos.']));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Correo electrónico inválido.']));
        }

        if (strlen($message) > 1000) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'El mensaje es demasiado largo.']));
        }

        // Sanitización antes de insertar
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("INSERT INTO comments (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $subject, $message]);

            return new Response(201, ['Content-Type' => 'application/json'], json_encode(['message' => 'Comentario guardado exitosamente.']));
        } catch (Exception $e) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]));
        }
    });



    //ruta para eliminar 
    $r->addRoute('DELETE', '/comments/{id}', function (ServerRequestInterface $request, $args) use ($loop) {
        $commentId = $args['id'];

        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);

            if ($stmt->rowCount() > 0) {
                return new Response(200, ['Content-Type' => 'application/json'], json_encode(['message' => 'Comentario eliminado exitosamente']));
            } else {
                return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Comentario no encontrado']));
            }
        } catch (Exception $e) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Error al eliminar comentario: ' . $e->getMessage()]));
        }
    });

    //ruta para actualizae
    $r->addRoute('PUT', '/comments/{id}', function (ServerRequestInterface $request, $args) use ($loop) {
        $commentId = $args['id'];
        $body = (string) $request->getBody();
        $input = json_decode($body, true);

        if (!isset($input['subject'], $input['message'])) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Datos incompletos para la actualización.'
            ]));
        }

        // Validar y sanitizar entradas
        $subject = trim($input['subject']);
        $message = trim($input['message']);

        if ($subject === '' || $message === '') {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Asunto y mensaje no pueden estar vacíos.'
            ]));
        }

        if (strlen($message) > 1000) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Mensaje demasiado largo. Máximo 1000 caracteres.'
            ]));
        }

        // Sanitizar entradas para evitar XSS
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE comments SET subject = ?, message = ? WHERE id = ?");
            $stmt->execute([$subject, $message, $commentId]);

            if ($stmt->rowCount() > 0) {
                return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'message' => 'Comentario actualizado exitosamente.'
                ]));
            } else {
                return new Response(404, ['Content-Type' => 'application/json'], json_encode([
                    'error' => 'Comentario no encontrado o sin cambios.'
                ]));
            }
        } catch (Exception $e) {
            return new Response(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Error al actualizar comentario: ' . $e->getMessage()
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
            $notFoundPage = file_get_contents(__DIR__ . '/public/404.html');
            return new Response(404, ['Content-Type' => 'text/html'], $notFoundPage);
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

function getDBConnection(): PDO
{
    return new PDO('sqlite:' . __DIR__ . '/data/comments.db');
}

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