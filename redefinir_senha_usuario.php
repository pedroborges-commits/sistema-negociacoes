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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha | Sistema de Negociações</title>
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

            <?php if (in_array($usuarioAtual['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link">
                    <span class="nav-icon">▥</span>
                    <span>Relatórios</span>
                </a>

            <?php endif; ?>

            <?php if ($usuarioAtual['perfil'] === 'ADMIN'): ?>

                <a href="usuarios.php" class="sidebar-link active">
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
                    <?= e(mb_strtoupper(mb_substr($usuarioAtual['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                </div>

                <div class="user-info">
                    <strong><?= e($usuarioAtual['nome']) ?></strong>
                    <span><?= e($usuarioAtual['perfil']) ?></span>
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

    <main class="dashboard-main reset-password-main">

        <div class="reset-password-header">

            <div>
                <h1>Redefinir senha</h1>
                <p>Cadastre uma nova senha para o usuário selecionado.</p>
            </div>

            <a href="usuarios.php" class="reset-password-back">
                ← Voltar para usuários
            </a>

        </div>

        <?php if ($erro !== ''): ?>

            <div class="reset-password-alert reset-password-alert-error">
                <strong>Não foi possível redefinir a senha</strong>
                <p><?= e($erro) ?></p>
            </div>

        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>

            <div class="reset-password-alert reset-password-alert-success">
                <strong><?= e($sucesso) ?></strong>
            </div>

        <?php endif; ?>

        <section class="reset-password-card">

            <div class="reset-password-card-heading">

                <div class="reset-password-icon">♙</div>

                <div>
                    <h2>Dados do usuário</h2>
                    <p>Confira os dados da conta que terá a senha alterada.</p>
                </div>

            </div>

            <div class="reset-password-user-info">

                <div class="reset-password-info-item">
                    <span>Nome completo</span>
                    <strong><?= e($usuario['nome']) ?></strong>
                </div>

                <div class="reset-password-info-item">
                    <span>E-mail</span>
                    <strong><?= e($usuario['email']) ?></strong>
                </div>

            </div>

            <div class="reset-password-divider"></div>

            <div class="reset-password-card-heading">

                <div class="reset-password-icon">🔒</div>

                <div>
                    <h2>Nova senha</h2>
                    <p>Informe e confirme a nova senha do usuário.</p>
                </div>

            </div>

            <form method="POST" class="reset-password-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <div class="reset-password-fields">

                    <div class="reset-password-field">
                        <label for="nova_senha">Nova senha</label>

                        <input
                            type="password"
                            id="nova_senha"
                            name="nova_senha"
                            placeholder="Digite a nova senha"
                            required
                        >

                        <small>
                            Mínimo de 8 caracteres, contendo pelo menos uma letra e um número.
                        </small>
                    </div>

                    <div class="reset-password-field">
                        <label for="confirmacao">Confirmar nova senha</label>

                        <input
                            type="password"
                            id="confirmacao"
                            name="confirmacao"
                            placeholder="Digite novamente a nova senha"
                            required
                        >
                    </div>

                </div>

                <div class="reset-password-notice">
                    <span>ⓘ</span>
                    <p>
                        A nova senha será aplicada imediatamente após a confirmação.
                    </p>
                </div>

                <div class="reset-password-actions">

                    <a href="usuarios.php" class="button secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="button">
                        Redefinir senha
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

</body>
</html>
