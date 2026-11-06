
<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

/*
|--------------------------------------------------------------------------
| Busca os atendimentos
|--------------------------------------------------------------------------
*/

$pdo = db();
$u = usuarioAtual();

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$inscricao = trim($_GET['inscricao'] ?? '');
$dataInicial = $_GET['data_inicial'] ?? '';
$dataFinal = $_GET['data_final'] ?? '';
$operadoraId = (int)($_GET['operadora'] ?? 0);
$pagamento = $_GET['pagamento'] ?? '';
$erroFiltro = '';

if (
    $dataInicial !== '' &&
    $dataFinal !== '' &&
    validarData($dataInicial) &&
    validarData($dataFinal) &&
    $dataInicial > $dataFinal
) {
    $erroFiltro = 'A data inicial não pode ser posterior à data final.';
}

/*
|--------------------------------------------------------------------------
| Operadoras disponíveis para o filtro
|--------------------------------------------------------------------------
*/

$operadoras = $pdo->query("
    SELECT
        id,
        nome
    FROM usuarios
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Consulta dos atendimentos
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.data_atendimento,
        a.inscricao,
        a.parcelas_em_atraso,
        a.valor_negociado,
        a.valor_recebido,
        a.data_pagamento,
        a.comprovante,
        a.observacoes,
        u.nome AS usuario_nome

    FROM atendimentos a

    INNER JOIN usuarios u
        ON u.id = a.usuario_id

    WHERE 1 = 1
";

$params = [];

/*
|--------------------------------------------------------------------------
| Permissão do operador
|--------------------------------------------------------------------------
*/

if ($u['perfil'] === 'OPERADOR') {

    $sql .= "
        AND a.usuario_id = ?
    ";

    $params[] = (int)$u['id'];
}

/*
|--------------------------------------------------------------------------
| Filtro por inscrição
|--------------------------------------------------------------------------
*/

if ($inscricao !== '') {

    $sql .= "
        AND a.inscricao = ?
    ";

    $params[] = $inscricao;
}

/*
|--------------------------------------------------------------------------
| Filtro por data inicial
|--------------------------------------------------------------------------
*/

if ($dataInicial !== '') {

    if (validarData($dataInicial)) {

        $sql .= "
            AND a.data_atendimento >= ?
        ";

        $params[] = $dataInicial;
    }
}

/*
|--------------------------------------------------------------------------
| Filtro por data final
|--------------------------------------------------------------------------
*/

if ($dataFinal !== '') {

    if (validarData($dataFinal)) {

        $sql .= "
            AND a.data_atendimento <= ?
        ";

        $params[] = $dataFinal;
    }
}

/*
|--------------------------------------------------------------------------
| Filtro por operadora
|--------------------------------------------------------------------------
*/

if (
    $u['perfil'] !== 'OPERADOR' &&
    $operadoraId > 0
) {

    $sql .= "
        AND a.usuario_id = ?
    ";

    $params[] = $operadoraId;
}

/*
|--------------------------------------------------------------------------
| Filtro por pagamento
|--------------------------------------------------------------------------
*/

if ($pagamento === 'pago') {

    $sql .= "
        AND a.valor_recebido IS NOT NULL
    ";

} elseif ($pagamento === 'nao_pago') {

    $sql .= "
        AND a.valor_recebido IS NULL
    ";
}

/*
|--------------------------------------------------------------------------
| Ordenação
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        a.data_atendimento DESC,
        a.id DESC
";

if ($erroFiltro === '') {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $atendimentos = $stmt->fetchAll();

} else {

    $atendimentos = [];
}

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
    <title>Atendimentos</title>

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

        <div class="sidebar-section-label">
            MENU PRINCIPAL
        </div>

        <nav class="sidebar-nav">

            <a href="index.php" class="sidebar-link">
                <span class="nav-icon">⌂</span>
                <span>Painel</span>
            </a>

            <a href="novo_atendimento.php" class="sidebar-link">
                <span class="nav-icon">＋</span>
                <span>Novo atendimento</span>
            </a>

            <a href="atendimentos.php" class="sidebar-link active">
                <span class="nav-icon">▤</span>
                <span>Atendimentos</span>
            </a>
            <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
</a>

            <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">
                    GESTÃO
                </div>

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

            <!-- CABEÇALHO DA PÁGINA -->
            <div class="dashboard-welcome">

                <div class="welcome-text">

                    <span class="welcome-label">
                        GESTÃO DE ATENDIMENTOS
                    </span>

                    <h1>Atendimentos</h1>

                    <p>
                        Consulte e gerencie os atendimentos registrados no sistema.
                    </p>

                </div>

                <a
                    href="novo_atendimento.php"
                    class="dashboard-primary-button"
                >
                    <span>＋</span>
                    Novo atendimento
                </a>

            </div>

            <!-- FILTROS -->
            <section class="dashboard-section">

                <div class="section-heading">

                    <div>
                        <h2>Filtros</h2>

                        <p>
                            Utilize os campos abaixo para localizar atendimentos.
                        </p>
                    </div>

                </div>

                <div class="panel attendance-filter-panel">

                    <form method="GET" class="attendance-filter-form">

                        <div class="attendance-filter-field">

                            <label for="inscricao">
                                Inscrição
                            </label>

                            <input
                                type="text"
                                id="inscricao"
                                name="inscricao"
                                value="<?= e($inscricao) ?>"
                                placeholder="Digite a inscrição"
                            >

                        </div>

                        <div class="attendance-filter-field">

                            <label for="data_inicial">
                                Data inicial
                            </label>

                            <input
                                type="date"
                                id="data_inicial"
                                name="data_inicial"
                                value="<?= e($dataInicial) ?>"
                            >

                        </div>

                        <div class="attendance-filter-field">

                            <label for="data_final">
                                Data final
                            </label>

                            <input
                                type="date"
                                id="data_final"
                                name="data_final"
                                value="<?= e($dataFinal) ?>"
                            >

                        </div>

                        <?php if ($u['perfil'] !== 'OPERADOR'): ?>

                            <div class="attendance-filter-field">

                                <label for="operadora">
                                    Operadora
                                </label>

                                <select
                                    id="operadora"
                                    name="operadora"
                                >

                                    <option value="0">
                                        Todas
                                    </option>

                                    <?php foreach ($operadoras as $operadora): ?>

                                        <option
                                            value="<?= $operadora['id'] ?>"
                                            <?= $operadoraId === (int)$operadora['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= e($operadora['nome']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        <?php endif; ?>

                        <div class="attendance-filter-field">

                            <label for="pagamento">
                                Pagamento
                            </label>

                            <select
                                id="pagamento"
                                name="pagamento"
                            >

                                <option
                                    value=""
                                    <?= $pagamento === ''
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Todos
                                </option>

                                <option
                                    value="pago"
                                    <?= $pagamento === 'pago'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Pago
                                </option>

                                <option
                                    value="nao_pago"
                                    <?= $pagamento === 'nao_pago'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Não pago
                                </option>

                            </select>

                        </div>

                        <div class="attendance-filter-actions">

                            <button
                                type="submit"
                                class="dashboard-primary-button"
                            >
                                Filtrar
                            </button>

                            <a
                                href="atendimentos.php"
                                class="attendance-clear-button"
                            >
                                Limpar
                            </a>

                        </div>

                    </form>

                </div>

            </section>

            <!-- RESULTADOS -->
            <section class="dashboard-section">

                <div class="section-heading">

                    <div>
                        <h2>Resultados</h2>

                        <p>
                            Atendimentos encontrados conforme os filtros selecionados.
                        </p>
                    </div>

                </div>

                <div class="panel attendance-results-panel">

                    <?php if ($erroFiltro !== ''): ?>

                        <div class="alert error">
                            <?= e($erroFiltro) ?>
                        </div>

                    <?php elseif (empty($atendimentos)): ?>

                        <div class="attendance-empty">
                            Nenhum atendimento encontrado.
                        </div>

                    <?php else: ?>

                        <div class="attendance-table-wrapper">

                            <table class="attendance-table">

                                <thead>

                                    <tr>

                                        <th>Data</th>
                                        <th>Inscrição</th>
                                        <th>Parcelas em atraso</th>
                                        <th>Valor negociado</th>
                                        <th>Valor recebido</th>
                                        <th>Data pagamento</th>
                                        <th>Comprovante</th>
                                        <th>Operadora</th>
                                        <th>Ação</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($atendimentos as $a): ?>

                                        <tr>

                                            <td>
                                                <?= e(
                                                    date(
                                                        'd/m/Y',
                                                        strtotime($a['data_atendimento'])
                                                    )
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= e($a['inscricao']) ?>
                                            </td>

                                            <td>
                                                <?= e(
                                                    (string)$a['parcelas_em_atraso']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= dinheiro($a['valor_negociado']) ?>
                                            </td>

                                            <td>

                                                <?php if ($a['valor_recebido'] !== null): ?>

                                                    <strong>
                                                        <?= dinheiro($a['valor_recebido']) ?>
                                                    </strong>

                                                <?php else: ?>

                                                    <span class="attendance-unpaid">
                                                        Não pago
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if ($a['data_pagamento'] !== null): ?>

                                                    <?= e(
                                                        date(
                                                            'd/m/Y',
                                                            strtotime($a['data_pagamento'])
                                                        )
                                                    ) ?>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php if (!empty($a['comprovante'])): ?>

                                                    <a
                                                        class="attendance-table-button secondary"
                                                        href="comprovante_atendimento.php?id=<?= (int)$a['id'] ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        Consultar comprovante
                                                    </a>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                            <td>
                                                <?= e($a['usuario_nome']) ?>
                                            </td>

                                            <td>

                                                <div class="attendance-actions">

                                                    <a
                                                        class="attendance-table-button secondary"
                                                        href="editar_atendimento.php?id=<?= $a['id'] ?>"
                                                    >
                                                        <?= $a['valor_recebido'] !== null
                                                            ? 'Editar observação'
                                                            : 'Editar'
                                                        ?>
                                                    </a>

                                                    <?php if ($a['valor_recebido'] === null): ?>

                                                        <a
                                                            class="attendance-table-button primary"
                                                            href="pagamento_atendimento.php?id=<?= $a['id'] ?>"
                                                        >
                                                            Registrar pagamento
                                                        </a>

                                                    <?php else: ?>

                                                        <span class="attendance-payment-registered">
                                                            Pagamento registrado
                                                        </span>

                                                    <?php endif; ?>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </main>

</div>

</body>

</html>
