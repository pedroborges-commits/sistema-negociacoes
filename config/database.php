<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = '127.0.0.1';
    $port = '3306';
    $name = 'negociacoes';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        die('<h2>Erro ao conectar ao MySQL</h2><p>Confirme se o MySQL está iniciado no XAMPP e se o banco <strong>negociacoes</strong> existe.</p><pre>'.htmlspecialchars($e->getMessage()).'</pre>');
    }
    return $pdo;
}
