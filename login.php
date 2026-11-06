
<?php
require_once __DIR__ . '/config/bootstrap.php';

if (usuarioAtual()) {
    $destino = !empty(usuarioAtual()['precisa_trocar_senha'])
        ? 'trocar_senha.php'
        : 'index.php';

    header('Location: ' . $destino);
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login(trim($_POST['email'] ?? ''), $_POST['senha'] ?? '')) {
        $destino = !empty(usuarioAtual()['precisa_trocar_senha'])
            ? 'trocar_senha.php'
            : 'index.php';

        header('Location: ' . $destino);
        exit;
    }

    $erro = 'E-mail ou senha inválidos.';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>Entrar | Sistema de Negociações</title>

    <link rel="stylesheet" href="assets/style.css?v=3">
</head>

<body class="login-page">

    <main class="login-container">

        <div class="login-brand">
            <div class="login-brand-symbol">N</div>
            <span>Sistema de Negociações</span>
        </div>

        <section class="login-card">

            <div class="login-heading">
                <h1>Bem-vindo de volta!</h1>
                <p>Entre com seus dados para acessar o sistema.</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert error">
                    <?= e($erro) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">

                <div class="login-field">
                    <label for="email">E-mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Digite seu e-mail"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="login-field">
                    <label for="senha">Senha</label>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua senha"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="button login-submit">
                    Entrar no sistema
                </button>

            </form>

        </section>

        <div class="login-footer">
            Sistema de Negociações
        </div>

        <div class="login-test">
            <strong>Acessos para teste</strong>
            <p>admin@local.test / password</p>
            <p>supervisor@local.test / password</p>
            <p>operador@local.test / password</p>
        </div>

    </main>

</body>
</html>
