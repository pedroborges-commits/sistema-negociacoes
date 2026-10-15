<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$usuario = usuarioAtual();
$erro = '';
$mensagem = '';

if (empty($usuario['precisa_trocar_senha'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if ($senhaAtual === '' || $novaSenha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $novaSenha)) {
        $erro = 'A nova senha deve conter pelo menos uma letra.';
    } elseif (!preg_match('/\d/', $novaSenha)) {
        $erro = 'A nova senha deve conter pelo menos um número.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'A confirmação da nova senha não corresponde.';
    } elseif ($senhaAtual === $novaSenha) {
        $erro = 'Escolha uma senha diferente da senha provisória.';
    } else {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT senha, precisa_trocar_senha
                 FROM usuarios
                 WHERE id = ? AND ativo = 1
                 FOR UPDATE'
            );

            $stmt->execute([(int)$usuario['id']]);
            $dadosUsuario = $stmt->fetch();

            if (!$dadosUsuario) {
                throw new RuntimeException(
                    'A conta não está disponível.'
                );
            }

            if (
                !password_verify(
                    $senhaAtual,
                    $dadosUsuario['senha']
                )
            ) {
                $pdo->rollBack();
                $erro = 'A senha atual está incorreta.';
            } elseif (
                (int)$dadosUsuario['precisa_trocar_senha'] !== 1
            ) {
                $pdo->rollBack();
                $_SESSION['usuario']['precisa_trocar_senha'] = 0;

                header('Location: index.php');
                exit;
            } else {
                $senhaHash = password_hash(
                    $novaSenha,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    'UPDATE usuarios
                     SET senha = ?, precisa_trocar_senha = 0
                     WHERE id = ? AND ativo = 1'
                );

                $stmt->execute([
                    $senhaHash,
                    (int)$usuario['id']
                ]);

                registrarAuditoria(
                    'ALTERAR_SENHA',
                    'usuarios',
                    (int)$usuario['id'],
                    null,
                    [
                        'senha_alterada' => true,
                        'precisa_trocar_senha' => 0
                    ]
                );

                $pdo->commit();

                $_SESSION['usuario']['precisa_trocar_senha'] = 0;

                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao trocar senha: ' . $e->getMessage()
            );

            $erro = 'Não foi possível alterar a senha. Tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trocar senha</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="container">
    <section class="panel">
        <h1>Troca obrigatória de senha</h1>

        <p>
            Olá, <?= e($usuario['nome']) ?>.
            Defina uma nova senha para continuar.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="error"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <div class="form-group">
                <label for="senha_atual">Senha provisória atual</label>
                <input
                    type="password"
                    id="senha_atual"
                    name="senha_atual"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="nova_senha">Nova senha</label>
                <input
                    type="password"
                    id="nova_senha"
                    name="nova_senha"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
                <small>
                    Pelo menos 8 caracteres, uma letra e um número.
                </small>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirme a nova senha</label>
                <input
                    type="password"
                    id="confirmar_senha"
                    name="confirmar_senha"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="button">
                Salvar nova senha
            </button>
        </form>

        <form method="post" action="logout.php" style="margin-top: 12px;">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >
            <button type="submit" class="button secondary">
                Sair
            </button>
        </form>
    </section>
</main>
</body>
</html>
