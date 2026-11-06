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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>Minha conta | Sistema de Negociações</title>
    <link rel="stylesheet" href="/sistema-negociacoes-redesign/assets/style.css?v=2">
</head>

<body>

<div class="dashboard-layout">

    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="brand-symbol">N</div>
            <div>
                <strong>Negociações</strong>
                <span>Sistema de gestão</span>
            </div>
        </div>

        <div class="sidebar-section-label">MENU PRINCIPAL</div>

        <nav class="sidebar-nav">

            <a href="index.php" class="sidebar-link">
                <span class="nav-icon">⌂</span>
                <span>Painel</span>
            </a>

            <a href="novo_atendimento.php" class="sidebar-link">
                <span class="nav-icon">＋</span>
                <span>Novo atendimento</span>
            </a>

            <a href="atendimentos.php" class="sidebar-link">
                <span class="nav-icon">▤</span>
                <span>Atendimentos</span>
            </a>
            <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
</a>

            <?php if (in_array($usuario['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link">
                    <span class="nav-icon">▥</span>
                    <span>Relatórios</span>
                </a>

            <?php endif; ?>

            <?php if ($usuario['perfil'] === 'ADMIN'): ?>

                <a href="usuarios.php" class="sidebar-link">
                    <span class="nav-icon">♙</span>
                    <span>Usuários</span>
                </a>

                <a href="auditoria.php" class="sidebar-link">
                    <span class="nav-icon">◷</span>
                    <span>Auditoria</span>
                </a>

            <?php endif; ?>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-user">
                <div class="user-avatar">
                    <?= e(mb_strtoupper(mb_substr($usuario['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                </div>

                <div class="user-info">
                    <strong><?= e($usuario['nome']) ?></strong>
                    <span><?= e($usuario['perfil']) ?></span>
                </div>
            </div>

            <form method="POST" action="logout.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <button type="submit" class="logout-button">
                    <span>↪</span>
                    Sair do sistema
                </button>
            </form>

        </div>

    </aside>

    <main class="dashboard-main account-main">

        <div class="account-header">
            <div>
                <h1>Minha conta</h1>
                <p>Consulte seus dados e gerencie sua senha de acesso.</p>
            </div>
        </div>

        <?php if ($mensagem !== ''): ?>

            <div class="account-alert account-alert-success">
                <strong><?= e($mensagem) ?></strong>
            </div>

        <?php elseif ($erro !== ''): ?>

            <div class="account-alert account-alert-error">
                <strong>Não foi possível alterar a senha</strong>
                <p><?= e($erro) ?></p>
            </div>

        <?php endif; ?>

        <section class="account-card">

            <div class="account-card-heading">

                <div class="account-icon">♙</div>

                <div>
                    <h2>Dados da conta</h2>
                    <p>Informações do seu usuário no sistema.</p>
                </div>

            </div>

            <div class="account-info-grid">

                <div class="account-info-item">
                    <span>Nome completo</span>
                    <strong><?= e($usuario['nome']) ?></strong>
                </div>

                <div class="account-info-item">
                    <span>E-mail</span>
                    <strong><?= e($usuario['email']) ?></strong>
                </div>

                <div class="account-info-item">
                    <span>Perfil de acesso</span>
                    <strong><?= e($usuario['perfil']) ?></strong>
                </div>

            </div>

        </section>

        <section class="account-card">

            <div class="account-card-heading">

                <div class="account-icon">🔒</div>

                <div>
                    <h2>Alterar senha</h2>
                    <p>Atualize sua senha de acesso ao sistema.</p>
                </div>

            </div>

            <form method="POST" class="account-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <div class="account-fields">

                    <div class="account-field">
                        <label for="senha_atual">Senha atual</label>

                        <input
                            type="password"
                            id="senha_atual"
                            name="senha_atual"
                            placeholder="Digite sua senha atual"
                            required
                        >
                    </div>

                    <div class="account-field">
                        <label for="nova_senha">Nova senha</label>

                        <input
                            type="password"
                            id="nova_senha"
                            name="nova_senha"
                            placeholder="Digite sua nova senha"
                            required
                        >

                        <small>
                            Mínimo de 8 caracteres, contendo pelo menos uma letra e um número.
                        </small>
                    </div>

                    <div class="account-field">
                        <label for="confirmar_senha">Confirmar nova senha</label>

                        <input
                            type="password"
                            id="confirmar_senha"
                            name="confirmar_senha"
                            placeholder="Digite novamente sua nova senha"
                            required
                        >
                    </div>

                </div>

                <div class="account-notice">
                    <span>ⓘ</span>
                    <p>
                        A nova senha deve ser diferente da senha atual.
                    </p>
                </div>

                <div class="account-actions">
                    <button type="submit" class="button">
                        Alterar senha
                    </button>
                </div>

            </form>

        </section>

    </main>

</div>

</body>
</html>
