<?php
require 'vendor/autoload.php';

use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\SocketServer;


$server = new HttpServer(function (ServerRequestInterface $request) {
    return new Response(
        200,
        ['Content-type' => 'text/plain'],
        "WAZAAAAAAAAAAAAAAAAA"
    );
});

try {
    $socket = new SocketServer("127.0.0.1:8080");
    $server->listen($socket);
    echo "Servidor escuchando en 127.0.0.1:8080\n";
}catch(Exception $e){
    echo "Error: " . $e->getMessage();
}