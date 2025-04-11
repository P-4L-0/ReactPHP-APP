<?php
// Ruta donde se guardará la base de datos SQLite
$dbFile = __DIR__ . '/comments.db';

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la tabla de comentarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            subject TEXT NOT NULL,
            message TEXT NOT NULL
        );
    ");

    echo "Base de datos creada y tabla 'comments' lista.\n";
} catch (PDOException $e) {
    echo "Error al crear la base de datos: " . $e->getMessage() . "\n";
}
