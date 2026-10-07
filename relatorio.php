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

$statusFiltro = $_GET['status'] ?? '';

$statusPermitidos = [
    'PENDENTE',
    'VENCIDO',
    'PAGO',
    'CANCELADO'
];

if ($statusFiltro !== '' && !in_array($statusFiltro, $statusPermitidos, true)) {
    $statusFiltro = '';
}

/*
|--------------------------------------------------------------------------
| Responsáveis
|--------------------------------------------------------------------------
*/

$responsaveis = $pdo->query("
    SELECT id, nome
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
        n.id,
        n.data_contato,
        n.valor_original,
        n.desconto,
        n.valor_final,
        n.data_combinada,
        n.data_pagamento,
        n.valor_pago,
        n.status,
        c.nome AS cliente,
        u.nome AS responsavel
    FROM negociacoes n

    INNER JOIN clientes c
        ON c.id = n.cliente_id

    INNER JOIN usuarios u
        ON u.id = n.usuario_id

    WHERE DATE_FORMAT(n.data_contato, '%Y-%m') = ?
";

$params = [$mes];

/*
|--------------------------------------------------------------------------
| Filtro por responsável
|--------------------------------------------------------------------------
*/

if ($responsavelId > 0) {

    $sql .= "
        AND n.usuario_id = ?
    ";

    $params[] = $responsavelId;
}

/*
|--------------------------------------------------------------------------
| Filtro por status
|--------------------------------------------------------------------------
*/

if ($statusFiltro !== '') {

    $sql .= "
        AND n.status = ?
    ";

    $params[] = $statusFiltro;
}

$sql .= "
    ORDER BY n.data_contato DESC, n.id DESC
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

$valorBruto = 0;
$totalDescontos = 0;
$totalNegociado = 0;
$totalRecebido = 0;
$totalPendente = 0;

foreach ($rows as $row) {

    $valorBruto += (float)$row['valor_original'];

    $totalDescontos += (float)$row['desconto'];

    $totalNegociado += (float)$row['valor_final'];

    if ($row['status'] === 'PAGO') {

        $totalRecebido += (float)$row['valor_pago'];

    } elseif (
        $row['status'] === 'PENDENTE' ||
        $row['status'] === 'VENCIDO'
    ) {

        $totalPendente += (float)$row['valor_final'];
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

            <h1>Relatório mensal</h1>

            <p>
                Consolidado das negociações.
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

            Responsável

            <select name="responsavel">

                <option value="">
                    Todos
                </option>

                <?php foreach ($responsaveis as $responsavel): ?>

                    <option
                        value="<?= e($responsavel['id']) ?>"
                        <?= $responsavelId === (int)$responsavel['id']
                            ? 'selected'
                            : '' ?>
                    >

                        <?= e($responsavel['nome']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </label>


        <label>

            Status

            <select name="status">

                <option value="">
                    Todos
                </option>

                <?php foreach ($statusPermitidos as $status): ?>

                    <option
                        value="<?= e($status) ?>"
                        <?= $statusFiltro === $status
                            ? 'selected'
                            : '' ?>
                    >

                        <?= e($status) ?>

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

            <span>
                Quantidade
            </span>

            <b>
                <?= $quantidade ?>
            </b>

        </div>


        <div class="card">

            <span>
                Valor bruto negociado
            </span>

            <b>
                <?= dinheiro($valorBruto) ?>
            </b>

        </div>


        <div class="card">

            <span>
                Descontos aplicados
            </span>

            <b>
                <?= dinheiro($totalDescontos) ?>
            </b>

        </div>


        <div class="card">

            <span>
                Total negociado
            </span>

            <b>
                <?= dinheiro($totalNegociado) ?>
            </b>

        </div>


        <div class="card">

            <span>
                Total recebido
            </span>

            <b>
                <?= dinheiro($totalRecebido) ?>
            </b>

        </div>


        <div class="card">

            <span>
                Total pendente
            </span>

            <b>
                <?= dinheiro($totalPendente) ?>
            </b>

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

                    <th>
                        Data
                    </th>

                    <th>
                        Cliente
                    </th>

                    <th>
                        Responsável
                    </th>

                    <th>
                        Original
                    </th>

                    <th>
                        Desconto
                    </th>

                    <th>
                        Final
                    </th>

                    <th>
                        Data combinada
                    </th>

                    <th>
                        Data pagamento
                    </th>

                    <th>
                        Valor pago
                    </th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php foreach ($rows as $row): ?>

                    <tr>

                        <td>
                            <?= e($row['data_contato']) ?>
                        </td>

                        <td>
                            <?= e($row['cliente']) ?>
                        </td>

                        <td>
                            <?= e($row['responsavel']) ?>
                        </td>

                        <td>
                            <?= dinheiro($row['valor_original']) ?>
                        </td>

                        <td>
                            <?= dinheiro($row['desconto']) ?>
                        </td>

                        <td>
                            <?= dinheiro($row['valor_final']) ?>
                        </td>

                        <td>
                            <?= $row['data_combinada']
                                ? e($row['data_combinada'])
                                : '-' ?>
                        </td>

                        <td>
                            <?= $row['data_pagamento']
                                ? e($row['data_pagamento'])
                                : '-' ?>
                        </td>

                        <td>
                            <?= $row['valor_pago'] !== null
                                ? dinheiro($row['valor_pago'])
                                : '-' ?>
                        </td>

                        <td>
                            <?= e($row['status']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$rows): ?>

                    <tr>

                        <td colspan="10">
                            Nenhuma negociação encontrada.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>

</body>

</html>