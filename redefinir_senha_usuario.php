<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

$usuarioAtual = usuarioAtual();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('Usuário inválido.');
}

$stmt = db()->prepare("
    SELECT
        u.id,
        u.nome,
        u.email
    FROM usuarios u
    WHERE u.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmacao = $_POST['confirmacao'] ?? '';

    if (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $novaSenha)) {
        $erro = 'A nova senha deve conter pelo menos uma letra.';
    } elseif (!preg_match('/\d/', $novaSenha)) {
        $erro = 'A nova senha deve conter pelo menos um número.';
    } elseif ($novaSenha !== $confirmacao) {
        $erro = 'A confirmação da senha não confere.';
    } else {

        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $stmt = db()->prepare("
            UPDATE usuarios
            SET senha = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $senhaHash,
            $id
        ]);

        registrarAuditoria(
            'EDITAR',
            'usuarios',
            (int)$usuario['id'],
            [
                'alteracao' => 'senha'
            ],
            [
                'alteracao' => 'senha'
            ]
        );

        $sucesso = 'Senha redefinida com sucesso.';

        $novaSenha = '';
        $confirmacao = '';
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Redefinir senha</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<header class="topbar">

    <div>
        <strong>Sistema de Negociações</strong>
    </div>

    <div>

        <span>
            <?= e($usuarioAtual['nome']) ?>
            (<?= e($usuarioAtual['perfil']) ?>)
        </span>

        <a href="index.php">Início</a>

        <form
            method="post"
            action="logout.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <button type="submit">
                Sair
            </button>

        </form>

    </div>

</header>

<main class="container">

    <h1>Redefinir senha</h1>

    <section class="panel">

        <h2>
            <?= e($usuario['nome']) ?>
        </h2>

        <p>
            E-mail:
            <strong><?= e($usuario['email']) ?></strong>
        </p>

        <?php if ($erro !== ''): ?>

            <p>
                <?= e($erro) ?>
            </p>

        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>

            <p>
                <?= e($sucesso) ?>
            </p>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <div>
                <label for="nova_senha">
                    Nova senha
                </label>

                <input
                    type="password"
                    id="nova_senha"
                    name="nova_senha"
                    required
                >
            </div>

            <div>
                <label for="confirmacao">
                    Confirmar nova senha
                </label>

                <input
                    type="password"
                    id="confirmacao"
                    name="confirmacao"
                    required
                >
            </div>

            <button type="submit">
                Redefinir senha
            </button>

        </form>

        <p>
            <a href="usuarios.php">
                Voltar para usuários
            </a>
        </p>

    </section>

</main>

</body>

</html>