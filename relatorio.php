
<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN', 'SUPERVISOR']);

$pdo = db();
$u = usuarioAtual();

/*
|--------------------------------------------------------------------------
| Validação de datas
|--------------------------------------------------------------------------
*/

$hoje = date('Y-m-d');
$primeiroDiaMes = date('Y-m-01');

$validarData = static function ($data): bool {
    if (!is_string($data)) {
        return false;
    }

    $dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    return $dataObj !== false
        && $dataObj->format('Y-m-d') === $data;
};

$dataInicio = $_GET['data_inicio'] ?? $primeiroDiaMes;
$dataFim = $_GET['data_fim'] ?? $hoje;

if (!$validarData($dataInicio)) {
    $dataInicio = $primeiroDiaMes;
}

if (!$validarData($dataFim)) {
    $dataFim = $hoje;
}

$erroFiltro = '';

if ($dataInicio > $dataFim) {
    $dataInicio = $primeiroDiaMes;
    $dataFim = $hoje;
    $erroFiltro = 'A data inicial não pode ser posterior à data final. O período foi redefinido.';
}

/*
|--------------------------------------------------------------------------
| Demais filtros
|--------------------------------------------------------------------------
*/

$indicadoresPermitidos = ['todos', 'atendimentos', 'recebido'];

$indicador = $_GET['indicador'] ?? 'todos';

if (!in_array($indicador, $indicadoresPermitidos, true)) {
    $indicador = 'todos';
}

$responsavelId = max(0, (int)($_GET['responsavel'] ?? 0));

/*
|--------------------------------------------------------------------------
| Responsáveis
|--------------------------------------------------------------------------
*/

$responsaveis = $pdo->query("
    SELECT
        id,
        nome
    FROM usuarios
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Filtro por responsável
|--------------------------------------------------------------------------
*/

$filtroResponsavel = '';
$paramsResponsavel = [];

if ($responsavelId > 0) {
    $filtroResponsavel = ' AND a.usuario_id = ? ';
    $paramsResponsavel[] = $responsavelId;
}

/*
|--------------------------------------------------------------------------
| Indicadores de atendimentos
|
| O período é definido pela data_atendimento.
|--------------------------------------------------------------------------
*/

$sqlAtendimentos = "
    SELECT
        COUNT(*) AS quantidade,
        COALESCE(SUM(a.parcelas_em_atraso), 0) AS total_parcelas,
        COALESCE(SUM(a.valor_negociado), 0) AS total_negociado
    FROM atendimentos a
    WHERE
        a.data_atendimento >= ?
        AND a.data_atendimento < DATE_ADD(?, INTERVAL 1 DAY)
        $filtroResponsavel
";

$stmtAtendimentos = $pdo->prepare($sqlAtendimentos);

$stmtAtendimentos->execute(array_merge(
    [$dataInicio, $dataFim],
    $paramsResponsavel
));

$totaisAtendimentos = $stmtAtendimentos->fetch();

$quantidade = (int)$totaisAtendimentos['quantidade'];
$totalParcelasAtraso = (int)$totaisAtendimentos['total_parcelas'];
$totalNegociado = (float)$totaisAtendimentos['total_negociado'];

/*
|--------------------------------------------------------------------------
| Indicadores de recebimentos
|
| O período é definido pela data_pagamento.
|--------------------------------------------------------------------------
*/

$sqlRecebimentos = "
    SELECT
        COUNT(a.valor_recebido) AS quantidade_pagamentos,
        COALESCE(SUM(a.valor_recebido), 0) AS total_recebido
    FROM atendimentos a
    WHERE
        a.data_pagamento IS NOT NULL
        AND a.data_pagamento >= ?
        AND a.data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
        AND a.valor_recebido IS NOT NULL
        $filtroResponsavel
";

$stmtRecebimentos = $pdo->prepare($sqlRecebimentos);

$stmtRecebimentos->execute(array_merge(
    [$dataInicio, $dataFim],
    $paramsResponsavel
));

$totaisRecebimentos = $stmtRecebimentos->fetch();

$quantidadePagamentos = (int)$totaisRecebimentos['quantidade_pagamentos'];
$totalRecebido = (float)$totaisRecebimentos['total_recebido'];

/*
|--------------------------------------------------------------------------
| Consulta dos registros detalhados
|
| Todos:
| Atendimento no período OU pagamento no período.
|
| Atendimentos:
| Somente atendimentos realizados no período.
|
| Recebido:
| Somente registros com pagamento no período.
|--------------------------------------------------------------------------
*/

$condicaoAtendimento = "
    a.data_atendimento >= ?
    AND a.data_atendimento < DATE_ADD(?, INTERVAL 1 DAY)
";

$condicaoRecebimento = "
    a.data_pagamento IS NOT NULL
    AND a.data_pagamento >= ?
    AND a.data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
";

$params = [];

if ($indicador === 'atendimentos') {
    $condicaoDetalhes = $condicaoAtendimento;
    $params = [$dataInicio, $dataFim];

} elseif ($indicador === 'recebido') {
    $condicaoDetalhes = $condicaoRecebimento;
    $params = [$dataInicio, $dataFim];

} else {
    $condicaoDetalhes = "
        (
            ($condicaoAtendimento)
            OR
            ($condicaoRecebimento)
        )
    ";

    $params = [
        $dataInicio,
        $dataFim,
        $dataInicio,
        $dataFim
    ];
}

$sql = "
    SELECT
        a.id,
        a.data_atendimento,
        a.inscricao,
        a.parcelas_em_atraso,
        a.valor_negociado,
        a.valor_recebido,
        a.data_pagamento,
        a.observacoes,
        u.nome AS responsavel

    FROM atendimentos a

    INNER JOIN usuarios u
        ON u.id = a.usuario_id

    WHERE
        $condicaoDetalhes
        $filtroResponsavel

    ORDER BY
        a.data_atendimento DESC,
        a.id DESC
";

$params = array_merge($params, $paramsResponsavel);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll();

$quantidadeRegistros = count($rows);

/*
|--------------------------------------------------------------------------
| Informações para exibição
|--------------------------------------------------------------------------
*/

$tituloIndicador = [
    'todos' => 'Todos os indicadores',
    'atendimentos' => 'Atendimentos',
    'recebido' => 'Valores recebidos',
][$indicador];

?>

<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
<link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>Relatório por período</title>

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

            <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link active">
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

            <!-- CABEÇALHO -->
            <section class="dashboard-welcome report-page-welcome">

                <div>
                    <span class="welcome-label">GESTÃO</span>

                    <h1 class="welcome-text">Relatório por período</h1>

                    <p>
                        Acompanhe os atendimentos e os pagamentos realizados.
                    </p>
                </div>

                <a
                    href="index.php"
                    class="report-back-button"
                >
                    ← Voltar ao painel
                </a>

            </section>

            <!-- FILTROS -->
            <section class="panel report-filter-panel">

                <div class="report-section-heading">
                    <div>
                        <h2>Filtros do relatório</h2>
                        <p>Defina o período, o indicador e o responsável.</p>
                    </div>
                </div>

                <?php if ($erroFiltro !== ''): ?>
                    <p class="report-filter-error">
                        <?= e($erroFiltro) ?>
                    </p>
                <?php endif; ?>

                <form class="report-filter-form" method="get">

                    <div class="report-filter-field">

                        <label for="data_inicio">Data inicial</label>

                        <input
                            type="date"
                            id="data_inicio"
                            name="data_inicio"
                            value="<?= e($dataInicio) ?>"
                            required
                        >

                    </div>

                    <div class="report-filter-field">

                        <label for="data_fim">Data final</label>

                        <input
                            type="date"
                            id="data_fim"
                            name="data_fim"
                            value="<?= e($dataFim) ?>"
                            required
                        >

                    </div>

                    <div class="report-filter-field">

                        <label for="indicador">Indicador</label>

                        <select id="indicador" name="indicador">

                            <option value="todos" <?= $indicador === 'todos' ? 'selected' : '' ?>>
                                Todos os indicadores
                            </option>

                            <option value="atendimentos" <?= $indicador === 'atendimentos' ? 'selected' : '' ?>>
                                Atendimentos
                            </option>

                            <option value="recebido" <?= $indicador === 'recebido' ? 'selected' : '' ?>>
                                Valores recebidos
                            </option>

                        </select>

                    </div>

                    <div class="report-filter-field">

                        <label for="responsavel">Operadora</label>

                        <select id="responsavel" name="responsavel">

                            <option value="0">
                                Todas
                            </option>

                            <?php foreach ($responsaveis as $responsavel): ?>

                                <option
                                    value="<?= e((string)$responsavel['id']) ?>"
                                    <?= $responsavelId === (int)$responsavel['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($responsavel['nome']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="report-filter-actions">

                        <button type="submit" class="report-filter-button">
                            Filtrar relatório
                        </button>

                    </div>

                </form>

                <div class="report-export-area">

                    <a
                        href="exportar_relatorio.php?<?= e(http_build_query([
                            'data_inicio' => $dataInicio,
                            'data_fim' => $dataFim,
                            'indicador' => $indicador,
                            'responsavel' => $responsavelId
                        ])) ?>"
                        class="report-export-button"
                    >
                        <span>↓</span>
                        Exportar para Excel
                    </a>

                </div>

            </section>

            <!-- RESUMO -->
            <section class="report-summary-section">

                <div class="report-section-heading">
                    <div>
                        <h2>Resumo do período</h2>
                        <p>
                            <?= e(date('d/m/Y', strtotime($dataInicio))) ?>
                            até
                            <?= e(date('d/m/Y', strtotime($dataFim))) ?>
                            · <?= e($tituloIndicador) ?>
                        </p>
                    </div>
                </div>

                <div class="report-summary-grid">

                    <?php if (in_array($indicador, ['todos', 'atendimentos'], true)): ?>

                        <div class="report-summary-card">
                            <span class="report-summary-label">Atendimentos</span>
                            <strong><?= $quantidade ?></strong>
                            <span class="report-summary-description">
                                Registros realizados no período
                            </span>
                        </div>

                        <div class="report-summary-card">
                            <span class="report-summary-label">Parcelas em atraso</span>
                            <strong><?= $totalParcelasAtraso ?></strong>
                            <span class="report-summary-description">
                                Total de parcelas negociadas
                            </span>
                        </div>

                        <div class="report-summary-card">
                            <span class="report-summary-label">Total negociado</span>
                            <strong><?= dinheiro($totalNegociado) ?></strong>
                            <span class="report-summary-description">
                                Valor dos atendimentos do período
                            </span>
                        </div>

                    <?php endif; ?>

                    <?php if (in_array($indicador, ['todos', 'recebido'], true)): ?>

                        <div class="report-summary-card">
                            <span class="report-summary-label">Total recebido</span>
                            <strong><?= dinheiro($totalRecebido) ?></strong>
                            <span class="report-summary-description">
                                Valores pagos dentro do período
                            </span>
                        </div>

                        <div class="report-summary-card">
                            <span class="report-summary-label">Pagamentos registrados</span>
                            <strong><?= $quantidadePagamentos ?></strong>
                            <span class="report-summary-description">
                                Pagamentos realizados no período
                            </span>
                        </div>

                    <?php endif; ?>

                </div>

                    </section>   
<!-- TABELA -->
<section class="panel report-results-panel">
<div class="report-section-heading report-table-heading">
        <div>
            <h2>Registros do período</h2>
            <p>
                <?= $quantidadeRegistros ?>
                <?= $quantidadeRegistros === 1 ? 'registro encontrado' : 'registros encontrados' ?>.
            </p>
        </div>
    </div>

    <div class="report-table-wrapper">

        <table class="report-table">

            <thead>
                <tr>

                    <?php if ($indicador !== 'recebido'): ?>
                        <th>Data do atendimento</th>
                        <th>Inscrição</th>
                        <th>Parcelas em atraso</th>
                        <th>Valor negociado</th>
                    <?php endif; ?>

                    <?php if ($indicador !== 'atendimentos'): ?>
                        <th>Data do pagamento</th>
                        <th>Valor recebido</th>
                    <?php endif; ?>

                    <?php if ($indicador === 'recebido'): ?>
                        <th>Inscrição</th>
                    <?php endif; ?>

                    <th>Responsável</th>
                    <th>Observações</th>

                </tr>
            </thead>

            <tbody>

                <?php foreach ($rows as $row): ?>

                    <tr>

                        <?php if ($indicador !== 'recebido'): ?>

                            <td>
                                <?= e(date('d/m/Y', strtotime($row['data_atendimento']))) ?>
                            </td>

                            <td>
                                <?= e($row['inscricao']) ?>
                            </td>

                            <td>
                                <?= (int)$row['parcelas_em_atraso'] ?>
                            </td>

                            <td>
                                <?= dinheiro($row['valor_negociado']) ?>
                            </td>

                        <?php endif; ?>

                        <?php if ($indicador !== 'atendimentos'): ?>

                            <?php if ($indicador === 'recebido'): ?>
                                <td>
                                    <?= e($row['inscricao']) ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?php if ($row['data_pagamento'] !== null): ?>
                                    <?= e(date('d/m/Y', strtotime($row['data_pagamento']))) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($row['valor_recebido'] !== null): ?>
                                    <?= dinheiro($row['valor_recebido']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                        <?php endif; ?>

                        <td>
                            <?= e($row['responsavel']) ?>
                        </td>

                        <td>
                            <?= e($row['observacoes'] ?? '') ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$rows): ?>

                    <tr>
                        <td colspan="<?= $indicador === 'todos' ? 8 : 6 ?>" class="report-empty">
                            Nenhum registro encontrado para os filtros selecionados.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

        </div>

    </main>

</div>

</body>
</html>