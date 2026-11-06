<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

$usuarioAtual = usuarioAtual();

$stmt = db()->query("
    SELECT
        u.id,
        u.nome,
        u.email,
        p.nome AS perfil,
        u.ativo,
        u.criado_em,
        u.atualizado_em
    FROM usuarios u
    INNER JOIN perfis p ON p.id = u.perfil_id
    ORDER BY u.nome ASC
");

$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
<link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>Gerenciamento de usuários</title>

    <link
        rel="stylesheet"
        href="/sistema-negociacoes-redesign/assets/style.css?v=2"
    >
</head>

<body class="dashboard-page">

<div class="dashboard-layout">

    <!-- MENU LATERAL -->
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

            <div class="sidebar-section-label">GESTÃO</div>

            <a href="relatorio.php" class="sidebar-link">
                <span class="nav-icon">▥</span>
                <span>Relatórios</span>
            </a>

            <a href="usuarios.php" class="sidebar-link active">
                <span class="nav-icon">♙</span>
                <span>Usuários</span>
            </a>

            <a href="auditoria.php" class="sidebar-link">
                <span class="nav-icon">◷</span>
                <span>Auditoria</span>
            </a>

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

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="dashboard-main">

        <div class="dashboard-content">

            <!-- CABEÇALHO -->
            <section class="dashboard-welcome users-page-welcome">

                <div>
                    <span class="welcome-label">GESTÃO</span>

                    <h1 class="welcome-text">
                        Gerenciamento de usuários
                    </h1>

                    <p>
                        Gerencie os acessos e as permissões dos usuários do sistema.
                    </p>
                </div>

                <a
                    href="novo_usuario.php"
                    class="users-new-button"
                >
                    <span>＋</span>
                    Novo usuário
                </a>

            </section>

            <!-- LISTAGEM DE USUÁRIOS -->
            <section class="panel users-list-panel">

                <div class="users-section-heading">

                    <div>
                        <h2>Usuários cadastrados</h2>

                        <p>
                            <?= count($usuarios) ?>
                            <?= count($usuarios) === 1 ? 'usuário cadastrado' : 'usuários cadastrados' ?>
                        </p>
                    </div>

                </div>

                <?php if (count($usuarios) === 0): ?>

                    <div class="users-empty">
                        <span class="users-empty-icon">♙</span>
                        <strong>Nenhum usuário cadastrado</strong>
                        <p>Não existem usuários para exibir.</p>
                    </div>

                <?php else: ?>

                    <div class="users-table-wrapper">

                        <table class="users-table">

                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($usuarios as $usuario): ?>

                                    <tr>

                                        <td>
                                            <div class="users-name-cell">
                                                <div class="users-avatar">
                                                    <?= e(mb_strtoupper(mb_substr($usuario['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                                                </div>

                                                <strong>
                                                    <?= e($usuario['nome']) ?>
                                                </strong>
                                            </div>
                                        </td>

                                        <td>
                                            <?= e($usuario['email']) ?>
                                        </td>

                                        <td>
                                            <span class="users-role">
                                                <?= e($usuario['perfil']) ?>
                                            </span>
                                        </td>

                                        <td>

                                            <?php if ((int)$usuario['ativo'] === 1): ?>

                                                <span class="users-status active">
                                                    <span></span>
                                                    Ativo
                                                </span>

                                            <?php else: ?>

                                                <span class="users-status inactive">
                                                    <span></span>
                                                    Inativo
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= e(date('d/m/Y', strtotime($usuario['criado_em']))) ?>
                                        </td>

                                        <td>

                                            <div class="users-actions">

                                                <a
                                                    href="editar_usuario.php?id=<?= (int)$usuario['id'] ?>"
                                                    class="users-action-button edit"
                                                >
                                                    Editar
                                                </a>

                                                <?php if ((int)$usuario['id'] !== (int)$usuarioAtual['id']): ?>

                                                    <a
                                                        href="redefinir_senha_usuario.php?id=<?= (int)$usuario['id'] ?>"
                                                        class="users-action-button password"
                                                    >
                                                        Redefinir senha
                                                    </a>

                                                    <form
                                                        method="POST"
                                                        action="alternar_status_usuario.php"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e(csrfToken()) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int)$usuario['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="users-action-button <?= (int)$usuario['ativo'] === 1 ? 'deactivate' : 'activate' ?>"
                                                        >
                                                            <?= (int)$usuario['ativo'] === 1
                                                                ? 'Desativar'
                                                                : 'Ativar'
                                                            ?>
                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</div>

</body>
</html>