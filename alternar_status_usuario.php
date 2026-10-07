<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

validarCsrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('Usuário inválido.');
}

$usuarioAtual = usuarioAtual();

/*
 * O ADMIN não pode desativar a própria conta.
 */
if ((int)$usuarioAtual['id'] === (int)$id) {
    http_response_code(400);
    exit('Você não pode desativar sua própria conta.');
}

$stmt = db()->prepare("
    SELECT
        id,
        nome,
        email,
        ativo
    FROM usuarios
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}

/*
 * Inverte o status atual:
 *
 * 1 -> 0
 * 0 -> 1
 */
$novoStatus = (int)$usuario['ativo'] === 1 ? 0 : 1;

$stmt = db()->prepare("
    UPDATE usuarios
    SET ativo = ?
    WHERE id = ?
");

$stmt->execute([
    $novoStatus,
    $id
]);

/*
 * Registra a alteração na auditoria.
 */
registrarAuditoria(
    'EDITAR',
    'usuarios',
    (int)$usuario['id'],
    [
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'ativo' => (int)$usuario['ativo']
    ],
    [
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'ativo' => $novoStatus
    ]
);

header('Location: usuarios.php');
exit;