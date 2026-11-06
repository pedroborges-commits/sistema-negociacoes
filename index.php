
<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$u = usuarioAtual();
$pdo = db();

/*
|--------------------------------------------------------------------------
| Período do mês atual
|--------------------------------------------------------------------------
*/

$inicioMes = date('Y-m-01');
$inicioProximoMes = date('Y-m-01', strtotime('first day of next month'));

/*
|--------------------------------------------------------------------------
| Filtro de usuário e período
|--------------------------------------------------------------------------
*/

$filtroMes = "
    WHERE data_atendimento >= ?
      AND data_atendimento < ?
";

$paramsMes = [$inicioMes, $inicioProximoMes];

if ($u['perfil'] === 'OPERADOR') {
    $filtroMes .= " AND usuario_id = ?";
    $paramsMes[] = (int)$u['id'];
}

/*
|--------------------------------------------------------------------------
| Total de atendimentos no mês
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM atendimentos
    $filtroMes
");

$stmt->execute($paramsMes);

$totalAtendimentos = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Total de parcelas em atraso no mês
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(parcelas_em_atraso), 0)
    FROM atendimentos
    $filtroMes
");

$stmt->execute($paramsMes);

$totalParcelasAtraso = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Valor total negociado no mês
|
| O período é definido pela data_atendimento.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor_negociado), 0)
    FROM atendimentos
    $filtroMes
");

$stmt->execute($paramsMes);

$totalNegociado = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Valor total recebido no mês
|
| O período é definido pela data_pagamento.
|--------------------------------------------------------------------------
*/

$filtroPagamentoMes = "
    WHERE data_pagamento >= ?
      AND data_pagamento < ?
";

$paramsPagamentoMes = [$inicioMes, $inicioProximoMes];

if ($u['perfil'] === 'OPERADOR') {
    $filtroPagamentoMes .= " AND usuario_id = ?";
    $paramsPagamentoMes[] = (int)$u['id'];
}

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor_recebido), 0)
    FROM atendimentos
    $filtroPagamentoMes
");

$stmt->execute($paramsPagamentoMes);

$totalRecebido = $stmt->fetchColumn();
/*
|--------------------------------------------------------------------------
| Valor negociado hoje
|--------------------------------------------------------------------------
*/

if ($u['perfil'] === 'OPERADOR') {

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor_negociado), 0)
        FROM atendimentos
        WHERE usuario_id = ?
          AND data_atendimento = CURDATE()
    ");

    $stmt->execute([(int)$u['id']]);

} else {

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(valor_negociado), 0)
        FROM atendimentos
        WHERE data_atendimento = CURDATE()
    ");
}

$totalNegociadoHoje = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Valor recebido hoje
|--------------------------------------------------------------------------
*/

if ($u['perfil'] === 'OPERADOR') {

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor_recebido), 0)
        FROM atendimentos
        WHERE usuario_id = ?
          AND data_pagamento = CURDATE()
    ");

    $stmt->execute([(int)$u['id']]);

} else {

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(valor_recebido), 0)
        FROM atendimentos
        WHERE data_pagamento = CURDATE()
    ");
}

$totalRecebidoHoje = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Atendimentos realizados hoje
|--------------------------------------------------------------------------
*/

if ($u['perfil'] === 'OPERADOR') {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM atendimentos
        WHERE usuario_id = ?
          AND data_atendimento = CURDATE()
    ");

    $stmt->execute([(int)$u['id']]);

} else {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM atendimentos
        WHERE data_atendimento = CURDATE()
    ");
}

$atendimentosHoje = (int)$stmt->fetchColumn();

?>

<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>Painel | Sistema de Negociações</title>

    <link rel="stylesheet" href="/sistema-negociacoes-redesign/assets/style.css?v=2">
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

            <a href="index.php" class="sidebar-link active">
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

            <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link">
                    <span class="nav-icon">▥</span>
                    <span>Relatórios</span>
                </a>

            <?php endif; ?>

            <?php if ($u['perfil'] === 'ADMIN'): ?>

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

        <!-- USUÁRIO E LOGOUT -->
        <div class="sidebar-bottom">

            <div class="sidebar-user">

                <div class="user-avatar">
                    <?= e(mb_strtoupper(mb_substr($u['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                </div>

                <div class="user-info">
                    <strong><?= e($u['nome']) ?></strong>
                    <span><?= e($u['perfil']) ?></span>
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

            <!-- BOAS-VINDAS -->
            <section class="dashboard-welcome">

                <div class="welcome-text">

                    <span class="welcome-label">VISÃO GERAL</span>

                    <h1>Olá, <?= e($u['nome']) ?>!</h1>

                    <p>
                        Acompanhe os indicadores e movimentações do sistema.
                    </p>

                    <span class="welcome-current-date">
                        <?= date('d/m/Y') ?>
                    </span>

                </div>

                <a href="novo_atendimento.php" class="button dashboard-primary-button">
                    <span>＋</span>
                    Novo atendimento
                </a>

            </section>

            <!-- INDICADORES GERAIS DO MÊS -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <div>
                        <h2>Visão geral</h2>
                       <p>Atendimentos registrados e pagamentos realizados neste mês.</p>
                    </div>
                </div>

                <div class="dashboard-cards">

                    <article class="dashboard-card">

                        <div class="card-top">
                            <span class="card-icon blue">▤</span>
                            <span class="card-caption">ATENDIMENTOS</span>
                        </div>

                        <strong class="card-value">
                            <?= $totalAtendimentos ?>
                        </strong>

                        <span class="card-description">
                            Atendimentos no mês
                        </span>

                    </article>

                    <article class="dashboard-card">

                        <div class="card-top">
                            <span class="card-icon purple">R$</span>
                            <span class="card-caption">VALOR NEGOCIADO</span>
                        </div>

                        <strong class="card-value">
                            <?= dinheiro($totalNegociado) ?>
                        </strong>

                        <span class="card-description">
                            Valor negociado no mês
                        </span>

                    </article>

                    <article class="dashboard-card">

                        <div class="card-top">
                            <span class="card-icon green">✓</span>
                            <span class="card-caption">VALOR RECEBIDO</span>
                        </div>

                        <strong class="card-value">
                            <?= dinheiro($totalRecebido) ?>
                        </strong>

                        <span class="card-description">
                          Pagamentos realizados neste mês
                        </span>

                    </article>

                </div>

            </section>

            <!-- MOVIMENTAÇÕES DE HOJE -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <div>
                        <h2>Movimentações de hoje</h2>
                        <p>Atendimentos registrados e pagamentos realizados hoje.</p>
                    </div>
                </div>

                <div class="dashboard-cards">

                    <article class="dashboard-card">

                        <div class="card-top">
                            <span class="card-icon blue">＋</span>
                            <span class="card-caption">ATENDIMENTOS HOJE</span>
                        </div>

                        <strong class="card-value">
                            <?= $atendimentosHoje ?>
                        </strong>

                        <span class="card-description">
                            Registros realizados hoje
                        </span>

                    </article>

                    <article class="dashboard-card">

                        <div class="card-top">
                            <span class="card-icon purple">R$</span>
                            <span class="card-caption">NEGOCIADO HOJE</span>
                        </div>

                        <strong class="card-value">
                            <?= dinheiro($totalNegociadoHoje) ?>
                        </strong>

                        <span class="card-description">
                            Valor negociado hoje
                        </span>

                    </article>

                    <article class="dashboard-card">

                        <div class="card-top">
                            <span class="card-icon green">✓</span>
                            <span class="card-caption">RECEBIDO HOJE</span>
                        </div>

                        <strong class="card-value">
                            <?= dinheiro($totalRecebidoHoje) ?>
                        </strong>

                        <span class="card-description">
                           Pagamentos realizados hoje
                        </span>

                    </article>

                </div>

            </section>

            <!-- ACESSO RÁPIDO -->
            <section class="quick-actions">

                <div class="section-heading">
                    <div>
                        <h2>Acesso rápido</h2>
                        <p>Acesse diretamente as principais funções.</p>
                    </div>
                </div>

                <div class="quick-actions-grid">

                    <a href="novo_atendimento.php" class="quick-action">

                        <span class="quick-action-icon blue">＋</span>

                        <div>
                            <strong>Registrar atendimento</strong>
                            <span>Nova negociação</span>
                        </div>

                        <span class="quick-arrow">↗</span>

                    </a>

                    <a href="atendimentos.php" class="quick-action">

                        <span class="quick-action-icon purple">▤</span>

                        <div>
                            <strong>Consultar atendimentos</strong>
                            <span>Visualizar registros</span>
                        </div>

                        <span class="quick-arrow">↗</span>

                    </a>

                    <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                        <a href="relatorio.php" class="quick-action">

                            <span class="quick-action-icon green">▥</span>

                            <div>
                                <strong>Relatório mensal</strong>
                                <span>Resultados do período</span>
                            </div>

                            <span class="quick-arrow">↗</span>

                        </a>

                    <?php endif; ?>

                    <?php if ($u['perfil'] === 'ADMIN'): ?>

                        <a href="usuarios.php" class="quick-action">

                            <span class="quick-action-icon orange">♙</span>

                            <div>
                                <strong>Gerenciar usuários</strong>
                                <span>Administrar acessos</span>
                            </div>

                            <span class="quick-arrow">↗</span>

                        </a>

                        <a href="auditoria.php" class="quick-action">

                            <span class="quick-action-icon gray">◷</span>

                            <div>
                                <strong>Auditoria</strong>
                                <span>Ações realizadas no sistema</span>
                            </div>

                            <span class="quick-arrow">↗</span>

                        </a>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </main>

</div>

</body>

</html>
