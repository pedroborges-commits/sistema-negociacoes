
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

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<header class="topbar">
    <strong>Sistema de Negociações</strong>

    <span>
        <?= e($u['nome']) ?>
        ·
        <?= e($u['perfil']) ?>
        ·

        <form method="POST" action="logout.php" style="display:inline;">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <button type="submit">Sair</button>
        </form>
    </span>
</header>

<main class="container">

    <div class="head">
        <div>
            <h1>Auditoria</h1>

            <p>
                Histórico das ações realizadas no sistema.
            </p>
        </div>

        <a class="button secondary" href="index.php">
            ← Voltar
        </a>
    </div>

    <!-- Filtros -->

    <section class="panel">
        <h2>Filtros</h2>

        <form method="get" class="form">

            <label>
                Data inicial

                <input
                    type="date"
                    name="data_inicio"
                    value="<?= e($dataInicio) ?>"
                >
            </label>

            <label>
                Data final

                <input
                    type="date"
                    name="data_fim"
                    value="<?= e($dataFim) ?>"
                >
            </label>

            <label>
                Usuário

                <select name="usuario">
                    <option value="">Todos</option>

                    <?php foreach ($usuarios as $usuario): ?>
                        <option
                            value="<?= e($usuario['id']) ?>"
                            <?= $usuarioId === (int) $usuario['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($usuario['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Ação

                <select name="acao">
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
            </label>

            <label>
                ID do atendimento

                <input
                    type="number"
                    name="registro_id"
                    min="1"
                    value="<?= $registroId > 0 ? e($registroId) : '' ?>"
                >
            </label>

            <div>
                <button type="submit" class="button">
                    Filtrar
                </button>

                <a href="auditoria.php" class="button secondary">
                    Limpar
                </a>
            </div>

        </form>
    </section>

    <!-- Detalhes da auditoria -->

    <?php if ($detalhe): ?>
        <section class="panel">

            <h2>
                Detalhes da auditoria #<?= e($detalhe['id']) ?>
            </h2>

            <p>
                <strong>Usuário:</strong>
                <?= e($detalhe['usuario_nome'] ?? 'Usuário removido') ?>
            </p>

            <p>
                <strong>Ação:</strong>
                <?= e($detalhe['acao']) ?>
            </p>

            <p>
                <strong>Tabela:</strong>
                <?= e($detalhe['tabela_afetada']) ?>
            </p>

            <p>
                <strong>Registro:</strong>
                #<?= e($detalhe['registro_id']) ?>
            </p>

            <p>
                <strong>Data:</strong>
                <?= e($detalhe['criado_em']) ?>
            </p>

            <h3>Dados anteriores</h3>

            <?php if ($detalhe['dados_anteriores_array']): ?>
                <pre><?= e(
                    json_encode(
                        $detalhe['dados_anteriores_array'],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
                ) ?></pre>
            <?php else: ?>
                <p>Nenhum dado anterior.</p>
            <?php endif; ?>

            <h3>Dados novos</h3>

            <?php if ($detalhe['dados_novos_array']): ?>
                <pre><?= e(
                    json_encode(
                        $detalhe['dados_novos_array'],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
                ) ?></pre>
            <?php else: ?>
                <p>Nenhum dado novo.</p>
            <?php endif; ?>

        </section>
    <?php endif; ?>

    <!-- Histórico -->

    <section class="panel table">

        <h2>Histórico</h2>

        <table>
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
                            <?= e($auditoria['acao']) ?>
                        </td>

                        <td>
                            <?= e($auditoria['tabela_afetada']) ?>
                        </td>

                        <td>
                            #<?= e($auditoria['registro_id']) ?>
                        </td>

                        <td>
                            <a
                                class="button secondary"
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
                        <td colspan="6">
                            Nenhum registro de auditoria encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginação -->

        <div class="pagination">
            <span>
                Página <?= e($pagina) ?> de <?= e($totalPaginas) ?>
                · <?= e($totalRegistros) ?> registros
            </span>

            <div>
                <?php if ($pagina > 1): ?>
                    <a
                        class="button secondary"
                        href="?<?= http_build_query(
                            array_merge(
                                $filtrosPaginacao,
                                ['pagina' => $pagina - 1]
                            )
                        ) ?>"
                    >
                        Anterior
                    </a>
                <?php endif; ?>

                <?php if ($pagina < $totalPaginas): ?>
                    <a
                        class="button secondary"
                        href="?<?= http_build_query(
                            array_merge(
                                $filtrosPaginacao,
                                ['pagina' => $pagina + 1]
                            )
                        ) ?>"
                    >
                        Próxima
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </section>

</main>

</body>
</html>
