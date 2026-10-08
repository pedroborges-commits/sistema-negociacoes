<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN', 'SUPERVISOR']);

$pdo = db();
$u = usuarioAtual();

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$mes = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$responsavelId = (int)($_GET['responsavel'] ?? 0);

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
| Monta consulta
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
    a.observacoes,
    u.nome AS responsavel

    FROM atendimentos a

    INNER JOIN usuarios u
        ON u.id = a.usuario_id

    WHERE DATE_FORMAT(a.data_atendimento, '%Y-%m') = ?
";

$params = [$mes];

/*
|--------------------------------------------------------------------------
| Filtro por responsável
|--------------------------------------------------------------------------
*/

if ($responsavelId > 0) {

    $sql .= "
        AND a.usuario_id = ?
    ";

    $params[] = $responsavelId;
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

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$rows = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Totais do relatório
|--------------------------------------------------------------------------
*/

$quantidade = count($rows);
$totalParcelasAtraso = 0;
$totalNegociado = 0;
$totalRecebido = 0;
$quantidadePagamentos = 0;

foreach ($rows as $row) {

    $totalParcelasAtraso += (int)$row['parcelas_em_atraso'];

    $totalNegociado += (float)$row['valor_negociado'];

    if ($row['valor_recebido'] !== null) {
        $totalRecebido += (float)$row['valor_recebido'];
        $quantidadePagamentos++;
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

    <title>Relatório mensal</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<header class="topbar">

    <strong>Sistema de Negociações</strong>

    <span>

        <?= e($u['nome']) ?>

        ·

        <?= e($u['perfil']) ?>

        ·

        <form
            method="POST"
            action="logout.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <button type="submit">
                Sair
            </button>

        </form>

    </span>

</header>


<main class="container">

    <div class="head">

        <div>

            <h1>Relatório mensal</h1>

            <p>
                Consolidado dos atendimentos realizados.
            </p>

        </div>

        <a href="index.php">
            ← Voltar
        </a>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    -->

    <form
        class="filter"
        method="get"
    >

        <label>

            Mês

            <input
                type="month"
                name="mes"
                value="<?= e($mes) ?>"
            >

        </label>


        <label>

            Operadora

            <select name="responsavel">

                <option value="">
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

        </label>


        <button
            type="submit"
            class="button"
        >
            Filtrar
        </button>

    </form>


    <!--
    |--------------------------------------------------------------------------
    | Resumo
    |--------------------------------------------------------------------------
    -->

    <section class="cards">

        <div class="card">
    <span>Atendimentos</span>
    <b><?= $quantidade ?></b>
</div>

<div class="card">
    <span>Parcelas em atraso</span>
    <b><?= $totalParcelasAtraso ?></b>
</div>

<div class="card">
    <span>Total negociado</span>
    <b><?= dinheiro($totalNegociado) ?></b>
</div>

<div class="card">
    <span>Total recebido</span>
    <b><?= dinheiro($totalRecebido) ?></b>
</div>

<div class="card">
    <span>Pagamentos registrados</span>
    <b><?= $quantidadePagamentos ?></b>
</div>

    </section>


    <!--
    |--------------------------------------------------------------------------
    | Tabela
    |--------------------------------------------------------------------------
    -->

    <section class="panel table">

        <table>

            <thead>

                <tr>

                   <th>Data</th>
<th>Inscrição</th>
<th>Parcelas em atraso</th>
<th>Valor negociado</th>
<th>Valor recebido</th>
<th>Data do pagamento</th>
<th>Responsável</th>
<th>Observações</th>

                </tr>

            </thead>


            <tbody>

                <?php foreach ($rows as $row): ?>

                    <tr>

                       <td><?= e(date('d/m/Y', strtotime($row['data_atendimento']))) ?></td>

<td><?= e($row['inscricao']) ?></td>

<td><?= (int)$row['parcelas_em_atraso'] ?></td>

<td><?= dinheiro($row['valor_negociado']) ?></td>

<td>
    <?php if ($row['valor_recebido'] !== null): ?>
        <?= dinheiro($row['valor_recebido']) ?>
    <?php else: ?>
        -
    <?php endif; ?>
</td>

<td>
    <?php if ($row['data_pagamento'] !== null): ?>
        <?= e(date('d/m/Y', strtotime($row['data_pagamento']))) ?>
    <?php else: ?>
        -
    <?php endif; ?>
</td>

<td><?= e($row['responsavel']) ?></td>

<td><?= e($row['observacoes'] ?? '') ?></td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$rows): ?>

                    <tr>

                        <td colspan="6">
                            Nenhum atendimento encontrado.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>

</body>

</html>