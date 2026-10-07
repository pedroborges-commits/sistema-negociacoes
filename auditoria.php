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
$usuarioId = (int)($_GET['usuario'] ?? 0);
$acaoFiltro = $_GET['acao'] ?? '';
$registroId = (int)($_GET['registro_id'] ?? 0);

$acoesPermitidas = [
    'CRIAR',
    'EDITAR',
    'PAGAR'
];

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
| Monta a consulta
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
    WHERE 1=1
";

$params = [];

/*
|--------------------------------------------------------------------------
| Filtro por data inicial
|--------------------------------------------------------------------------
*/

if ($dataInicio !== '') {
    $sql .= " AND DATE(a.criado_em) >= ?";
    $params[] = $dataInicio;
}

/*
|--------------------------------------------------------------------------
| Filtro por data final
|--------------------------------------------------------------------------
*/

if ($dataFim !== '') {
    $sql .= " AND DATE(a.criado_em) <= ?";
    $params[] = $dataFim;
}

/*
|--------------------------------------------------------------------------
| Filtro por usuário
|--------------------------------------------------------------------------
*/

if ($usuarioId > 0) {
    $sql .= " AND a.usuario_id = ?";
    $params[] = $usuarioId;
}

/*
|--------------------------------------------------------------------------
| Filtro por ação
|--------------------------------------------------------------------------
*/

if (in_array($acaoFiltro, $acoesPermitidas, true)) {
    $sql .= " AND a.acao = ?";
    $params[] = $acaoFiltro;
}

/*
|--------------------------------------------------------------------------
| Filtro por registro
|--------------------------------------------------------------------------
*/

if ($registroId > 0) {
    $sql .= " AND a.registro_id = ?";
    $params[] = $registroId;
}

$sql .= " ORDER BY a.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$auditorias = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Função para exibir JSON da auditoria
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
| Verifica se foi solicitado detalhe
|--------------------------------------------------------------------------
*/

$detalheId = (int)($_GET['detalhe'] ?? 0);

$detalhe = null;

if ($detalheId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.nome AS usuario_nome
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.id = ?
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
        href="assets/style.css"
    >

</head>

<body>

<header class="topbar">

    <strong>Sistema de Negociações</strong>

    <span>

        <?=e($u['nome'])?>
        ·
        <?=e($u['perfil'])?>
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

        <a
            class="button secondary"
            href="index.php"
        >
            ← Voltar
        </a>

    </div>


    <!-- Filtros -->

    <section class="panel">

        <h2>Filtros</h2>

        <form
            method="get"
            class="form"
        >

            <label>

                Data inicial

                <input
                    type="date"
                    name="data_inicio"
                    value="<?=e($dataInicio)?>"
                >

            </label>


            <label>

                Data final

                <input
                    type="date"
                    name="data_fim"
                    value="<?=e($dataFim)?>"
                >

            </label>


            <label>

                Usuário

                <select name="usuario">

                    <option value="">
                        Todos
                    </option>

                    <?php foreach ($usuarios as $usuario): ?>

                        <option
                            value="<?=$usuario['id']?>"
                            <?= $usuarioId === (int)$usuario['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?=e($usuario['nome'])?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label>

                Ação

                <select name="acao">

                    <option value="">
                        Todas
                    </option>

                    <?php foreach ($acoesPermitidas as $acao): ?>

                        <option
                            value="<?=e($acao)?>"
                            <?= $acaoFiltro === $acao
                                ? 'selected'
                                : '' ?>
                        >

                            <?=e($acao)?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label>

                ID da negociação

                <input
                    type="number"
                    name="registro_id"
                    min="1"
                    value="<?= $registroId > 0 ? e($registroId) : '' ?>"
                >

            </label>


            <div>

                <button
                    type="submit"
                    class="button"
                >
                    Filtrar
                </button>

                <a
                    href="auditoria.php"
                    class="button secondary"
                >
                    Limpar
                </a>

            </div>

        </form>

    </section>


    <?php if ($detalhe): ?>

        <!-- Detalhes -->

        <section class="panel">

            <h2>
                Detalhes da auditoria #<?=e($detalhe['id'])?>
            </h2>

            <p>
                <strong>Usuário:</strong>
                <?=e($detalhe['usuario_nome'] ?? 'Usuário removido')?>
            </p>

            <p>
                <strong>Ação:</strong>
                <?=e($detalhe['acao'])?>
            </p>

            <p>
                <strong>Tabela:</strong>
                <?=e($detalhe['tabela_afetada'])?>
            </p>

            <p>
                <strong>Registro:</strong>
                #<?=e($detalhe['registro_id'])?>
            </p>

            <p>
                <strong>Data:</strong>
                <?=e($detalhe['criado_em'])?>
            </p>


            <h3>
                Dados anteriores
            </h3>

            <?php if ($detalhe['dados_anteriores_array']): ?>

                <pre><?=e(
                    json_encode(
                        $detalhe['dados_anteriores_array'],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
                )?></pre>

            <?php else: ?>

                <p>
                    Nenhum dado anterior.
                </p>

            <?php endif; ?>


            <h3>
                Dados novos
            </h3>

            <?php if ($detalhe['dados_novos_array']): ?>

                <pre><?=e(
                    json_encode(
                        $detalhe['dados_novos_array'],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
                )?></pre>

            <?php else: ?>

                <p>
                    Nenhum dado novo.
                </p>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <!-- Lista -->

    <section class="panel table">

        <h2>
            Histórico
        </h2>

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
                            <?=e($auditoria['criado_em'])?>
                        </td>

                        <td>
                            <?=e(
                                $auditoria['usuario_nome']
                                ?? 'Usuário removido'
                            )?>
                        </td>

                        <td>
                            <?=e($auditoria['acao'])?>
                        </td>

                        <td>
                            <?=e($auditoria['tabela_afetada'])?>
                        </td>

                        <td>
                            #<?=e($auditoria['registro_id'])?>
                        </td>

                        <td>

                            <a
                                class="button secondary"
                                href="?<?=http_build_query([
                                    'detalhe' => $auditoria['id'],
                                    'data_inicio' => $dataInicio,
                                    'data_fim' => $dataFim,
                                    'usuario' => $usuarioId ?: '',
                                    'acao' => $acaoFiltro,
                                    'registro_id' => $registroId ?: ''
                                ])?>"
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

    </section>

</main>

</body>

</html>