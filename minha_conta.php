<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$usuario = usuarioAtual();

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (
        $senhaAtual === '' ||
        $novaSenha === '' ||
        $confirmarSenha === ''
    ) {
        $erro = 'Preencha todos os campos de senha.';

    } elseif (
        !password_verify(
            $senhaAtual,
            buscarSenhaUsuario((int)$usuario['id'])
        )
    ) {
        $erro = 'A senha atual está incorreta.';

    } elseif (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';

    } elseif (!preg_match('/[A-Za-z]/', $novaSenha)) {
        $erro = 'A nova senha deve conter pelo menos uma letra.';

    } elseif (!preg_match('/\d/', $novaSenha)) {
        $erro = 'A nova senha deve conter pelo menos um número.';

    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'A confirmação da nova senha não corresponde.';

    } elseif (
        password_verify(
            $novaSenha,
            buscarSenhaUsuario((int)$usuario['id'])
        )
    ) {
        $erro = 'A nova senha deve ser diferente da senha atual.';

    } else {


        $novaSenhaHash = password_hash(
            $novaSenha,
            PASSWORD_DEFAULT
        );

        $stmt = db()->prepare(
            'UPDATE usuarios SET senha = ? WHERE id = ?'
        );

        $stmt->execute([
            $novaSenhaHash,
            $usuario['id']
        ]);

        registrarAuditoria(
            'EDITAR',
            'usuarios',
            (int)$usuario['id'],
            ['alteracao' => 'senha'],
            ['alteracao' => 'senha']
        );

        $mensagem = 'Senha alterada com sucesso.';
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

    <title>Minha conta</title>

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
            <?= e($usuario['nome']) ?>
            (<?= e($usuario['perfil']) ?>)
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

    <h1>Minha conta</h1>

    <?php if ($mensagem !== ''): ?>

        <div class="success">
            <?= e($mensagem) ?>
        </div>

    <?php elseif ($erro !== ''): ?>

        <div class="error">
            <?= e($erro) ?>
        </div>

    <?php endif; ?>


    <section class="panel">

        <h2>Dados da conta</h2>

        <p>
            <strong>Nome:</strong>
            <?= e($usuario['nome']) ?>
        </p>

        <p>
            <strong>E-mail:</strong>
            <?= e($usuario['email']) ?>
        </p>

        <p>
            <strong>Perfil:</strong>
            <?= e($usuario['perfil']) ?>
        </p>

    </section>


    <section class="panel">

        <h2>Alterar senha</h2>

        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <div class="form-group">

                <label for="senha_atual">
                    Senha atual
                </label>

                <input
                    type="password"
                    id="senha_atual"
                    name="senha_atual"
                    required
                >

            </div>


            <div class="form-group">

                <label for="nova_senha">
                    Nova senha
                </label>

                <input
                    type="password"
                    id="nova_senha"
                    name="nova_senha"
                    required
                >

                <small>
                    A senha deve ter pelo menos 8 caracteres,
                    uma letra e um número.
                </small>

            </div>


            <div class="form-group">

                <label for="confirmar_senha">
                    Confirmar nova senha
                </label>

                <input
                    type="password"
                    id="confirmar_senha"
                    name="confirmar_senha"
                    required
                >

            </div>


            <button
                type="submit"
                class="button"
            >
                Alterar senha
            </button>

        </form>

    </section>

</main>

</body>

</html>