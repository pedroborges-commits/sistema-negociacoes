<?php
require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

$pdo = db();
$u = usuarioAtual();

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

if ($dataInicio !== '' && !validarData($dataInicio)) {
    $dataInicio = '';
}

if ($dataFim !== '' && !validarData($dataFim)) {
    $dataFim = '';
}

$usuarioId = filter_input(INPUT_GET, 'usuario', FILTER_VALIDATE_INT);
$usuarioId = $usuarioId && $usuarioId > 0 ? $usuarioId : 0;

$acaoFiltro = $_GET['acao'] ?? '';
$registroId = filter_input(INPUT_GET, 'registro_id', FILTER_VALIDATE_INT);
$registroId = $registroId && $registroId > 0 ? $registroId : 0;

$acoesPermitidas = [
    'CRIAR',
    'EDITAR',
    'EDITAR_OBSERVACAO',
    'PAGAR',
    'ALTERAR_SENHA'
];

if (!in_array($acaoFiltro, $acoesPermitidas, true)) {
    $acaoFiltro = '';
}

/*
|--------------------------------------------------------------------------
| Paginação
|--------------------------------------------------------------------------
*/

$registrosPorPagina = 50;

$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$pagina = $pagina && $pagina > 0 ? $pagina : 1;

/*
|--------------------------------------------------------------------------
| Busca usuários para o filtro
|--------------------------------------------------------------------------
*/

$usuarios = $pdo->query("
    SELECT id, nome
    FROM usuarios
    ORDER BY nome
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Monta os filtros SQL
|--------------------------------------------------------------------------
*/

$condicoes = ['1=1'];
$params = [];

if ($dataInicio !== '') {
    $condicoes[] = 'a.criado_em >= ?';
    $params[] = $dataInicio . ' 00:00:00';
}

if ($dataFim !== '') {
    $condicoes[] = 'a.criado_em < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $dataFim;
}

if ($usuarioId > 0) {
    $condicoes[] = 'a.usuario_id = ?';
    $params[] = $usuarioId;
}

if ($acaoFiltro !== '') {
    $condicoes[] = 'a.acao = ?';
    $params[] = $acaoFiltro;
}

if ($registroId > 0) {
    $condicoes[] = 'a.registro_id = ?';
    $params[] = $registroId;
}

$whereSql = implode(' AND ', $condicoes);

/*
|--------------------------------------------------------------------------
| Conta os registros que correspondem aos filtros
|--------------------------------------------------------------------------
*/

$sqlContagem = "
    SELECT COUNT(*)
    FROM auditoria a
    WHERE $whereSql
";

$stmtContagem = $pdo->prepare($sqlContagem);
$stmtContagem->execute($params);

$totalRegistros = (int) $stmtContagem->fetchColumn();

$totalPaginas = max(
    1,
    (int) ceil($totalRegistros / $registrosPorPagina)
);

/*
|--------------------------------------------------------------------------
| Garante que a página solicitada exista
|--------------------------------------------------------------------------
*/

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$offset = ($pagina - 1) * $registrosPorPagina;

/*
|--------------------------------------------------------------------------
| Busca os registros da página atual
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.usuario_id,
        a.acao,
        a.tabela_afetada,
        a.registro_id,
        a.dados_anteriores,
        a.dados_novos,
        a.criado_em,
        u.nome AS usuario_nome
    FROM auditoria a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE $whereSql
    ORDER BY a.id DESC
    LIMIT $registrosPorPagina OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$auditorias = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Função para interpretar os dados da auditoria
|--------------------------------------------------------------------------
*/

function formatarDadosAuditoria(?string $json): array
{
    if (!$json) {
        return [];
    }

    $dados = json_decode($json, true);

    return is_array($dados) ? $dados : [];
}

/*
|--------------------------------------------------------------------------
| Busca os detalhes de um registro, se solicitado
|--------------------------------------------------------------------------
*/

$detalheId = filter_input(INPUT_GET, 'detalhe', FILTER_VALIDATE_INT);
$detalheId = $detalheId && $detalheId > 0 ? $detalheId : 0;

$detalhe = null;

if ($detalheId > 0) {
    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.nome AS usuario_nome
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.id = ?
        LIMIT 1
    ");

    $stmt->execute([$detalheId]);

    $detalhe = $stmt->fetch();

    if ($detalhe) {
        $detalhe['dados_anteriores_array'] =
            formatarDadosAuditoria($detalhe['dados_anteriores']);

        $detalhe['dados_novos_array'] =
            formatarDadosAuditoria($detalhe['dados_novos']);
    }
}

/*
|--------------------------------------------------------------------------
| Mantém os filtros nos links de paginação e detalhes
|--------------------------------------------------------------------------
*/

$filtrosPaginacao = [
    'data_inicio' => $dataInicio,
    'data_fim' => $dataFim,
    'usuario' => $usuarioId ?: '',
    'acao' => $acaoFiltro,
    'registro_id' => $registroId ?: ''
];

?>

<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Auditoria</title>

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

            <a href="usuarios.php" class="sidebar-link">
                <span class="nav-icon">♙</span>
                <span>Usuários</span>
            </a>

            <a href="auditoria.php" class="sidebar-link active">
                <span class="nav-icon">◷</span>
                <span>Auditoria</span>
            </a>

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
            <section class="dashboard-welcome audit-page-welcome">

                <div>
                    <span class="welcome-label">GESTÃO</span>

                    <h1 class="welcome-text">Auditoria</h1>

                    <p>
                        Histórico das ações realizadas no sistema.
                    </p>
                </div>

                <a href="index.php" class="audit-back-button">
                    ← Voltar ao painel
                </a>

            </section>

            <!-- FILTROS -->
            <section class="panel audit-filter-panel">

                <div class="audit-section-heading">
                    <div>
                        <h2>Filtros de pesquisa</h2>
                        <p>
                            Utilize os filtros para localizar registros específicos.
                        </p>
                    </div>
                </div>

                <form method="get" class="audit-filter-form">

                    <div class="audit-filter-field">
                        <label for="data_inicio">Data inicial</label>

                        <input
                            type="date"
                            id="data_inicio"
                            name="data_inicio"
                            value="<?= e($dataInicio) ?>"
                        >
                    </div>

                    <div class="audit-filter-field">
                        <label for="data_fim">Data final</label>

                        <input
                            type="date"
                            id="data_fim"
                            name="data_fim"
                            value="<?= e($dataFim) ?>"
                        >
                    </div>

                    <div class="audit-filter-field">
                        <label for="usuario">Usuário</label>

                        <select id="usuario" name="usuario">

                            <option value="">Todos</option>

                            <?php foreach ($usuarios as $usuario): ?>

                                <option
                                    value="<?= e((string)$usuario['id']) ?>"
                                    <?= $usuarioId === (int)$usuario['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($usuario['nome']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="audit-filter-field">
                        <label for="acao">Ação</label>

                        <select id="acao" name="acao">

                            <option value="">Todas</option>

                            <?php foreach ($acoesPermitidas as $acao): ?>

                                <option
                                    value="<?= e($acao) ?>"
                                    <?= $acaoFiltro === $acao ? 'selected' : '' ?>
                                >
                                    <?= e($acao) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="audit-filter-field">
                        <label for="registro_id">ID do atendimento</label>

                        <input
                            type="number"
                            id="registro_id"
                            name="registro_id"
                            min="1"
                            value="<?= $registroId > 0 ? e((string)$registroId) : '' ?>"
                            placeholder="Ex.: 125"
                        >
                    </div>

                    <div class="audit-filter-actions">

                        <button type="submit" class="audit-filter-button">
                            Filtrar
                        </button>

                        <a href="auditoria.php" class="audit-clear-button">
                            Limpar filtros
                        </a>

                    </div>

                </form>

            </section>

            <!-- DETALHES DA AUDITORIA -->
            <?php if ($detalhe): ?>

                <section class="panel audit-detail-panel">

                    <div class="audit-section-heading audit-detail-heading">

                        <div>
                            <span class="audit-detail-label">REGISTRO DE AUDITORIA</span>

                            <h2>
                                Detalhes #<?= e((string)$detalhe['id']) ?>
                            </h2>

                            <p>
                                Informações completas da ação selecionada.
                            </p>
                        </div>

                        <a
                            href="?<?= http_build_query(
                                array_merge(
                                    $filtrosPaginacao,
                                    ['pagina' => $pagina]
                                )
                            ) ?>"
                            class="audit-close-detail"
                        >
                            Fechar detalhes
                        </a>

                    </div>

                    <div class="audit-detail-info-grid">

                        <div class="audit-detail-info">
                            <span>Usuário</span>
                            <strong>
                                <?= e($detalhe['usuario_nome'] ?? 'Usuário removido') ?>
                            </strong>
                        </div>

                        <div class="audit-detail-info">
                            <span>Ação</span>
                            <strong><?= e($detalhe['acao']) ?></strong>
                        </div>

                        <div class="audit-detail-info">
                            <span>Tabela afetada</span>
                            <strong><?= e($detalhe['tabela_afetada']) ?></strong>
                        </div>

                        <div class="audit-detail-info">
                            <span>Registro</span>
                            <strong>#<?= e((string)$detalhe['registro_id']) ?></strong>
                        </div>

                        <div class="audit-detail-info">
                            <span>Data e hora</span>
                            <strong><?= e($detalhe['criado_em']) ?></strong>
                        </div>

                    </div>

                    <div class="audit-json-grid">

                        <div class="audit-json-card">

                            <h3>Dados anteriores</h3>

                            <?php if ($detalhe['dados_anteriores_array']): ?>

                                <pre><?= e(
                                    json_encode(
                                        $detalhe['dados_anteriores_array'],
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                    )
                                ) ?></pre>

                            <?php else: ?>

                                <p class="audit-no-data">
                                    Nenhum dado anterior.
                                </p>

                            <?php endif; ?>

                        </div>

                        <div class="audit-json-card">

                            <h3>Dados novos</h3>

                            <?php if ($detalhe['dados_novos_array']): ?>

                                <pre><?= e(
                                    json_encode(
                                        $detalhe['dados_novos_array'],
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                    )
                                ) ?></pre>

                            <?php else: ?>

                                <p class="audit-no-data">
                                    Nenhum dado novo.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </section>

            <?php endif; ?>

            <!-- HISTÓRICO -->
            <section class="panel audit-history-panel">

                <div class="audit-section-heading audit-history-heading">

                    <div>
                        <h2>Histórico de ações</h2>

                        <p>
                            <?= $totalRegistros ?>
                            <?= $totalRegistros === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                        </p>
                    </div>

                </div>

                <div class="audit-table-wrapper">

                    <table class="audit-table">

                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>Ação</th>
                                <th>Tabela</th>
                                <th>Registro</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($auditorias as $auditoria): ?>

                                <tr>

                                    <td>
                                        <?= e($auditoria['criado_em']) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $auditoria['usuario_nome']
                                            ?? 'Usuário removido'
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="audit-action-tag">
                                            <?= e($auditoria['acao']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e($auditoria['tabela_afetada']) ?>
                                    </td>

                                    <td>
                                        #<?= e((string)$auditoria['registro_id']) ?>
                                    </td>

                                    <td>
                                        <a
                                            class="audit-detail-button"
                                            href="?<?= http_build_query(
                                                array_merge(
                                                    $filtrosPaginacao,
                                                    [
                                                        'pagina' => $pagina,
                                                        'detalhe' => $auditoria['id']
                                                    ]
                                                )
                                            ) ?>"
                                        >
                                            Ver detalhes
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$auditorias): ?>

                                <tr>
                                    <td colspan="6" class="audit-empty">
                                        Nenhum registro de auditoria encontrado.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <!-- PAGINAÇÃO -->
                <div class="audit-pagination">

                    <div class="audit-pagination-info">
                        Página <?= e((string)$pagina) ?>
                        de <?= e((string)$totalPaginas) ?>

                        <span>·</span>

                        <?= e((string)$totalRegistros) ?> registros
                    </div>

                    <div class="audit-pagination-actions">

                        <?php if ($pagina > 1): ?>

                            <a
                                class="audit-pagination-button"
                                href="?<?= http_build_query(
                                    array_merge(
                                        $filtrosPaginacao,
                                        ['pagina' => $pagina - 1]
                                    )
                                ) ?>"
                            >
                                ← Anterior
                            </a>

                        <?php endif; ?>

                        <?php if ($pagina < $totalPaginas): ?>

                            <a
                                class="audit-pagination-button"
                                href="?<?= http_build_query(
                                    array_merge(
                                        $filtrosPaginacao,
                                        ['pagina' => $pagina + 1]
                                    )
                                ) ?>"
                            >
                                Próxima →
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>

</body>
</html>