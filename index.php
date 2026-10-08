<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$u = usuarioAtual();
$pdo = db();

$filtroUsuario = '';
$paramsUsuario = [];

if ($u['perfil'] === 'OPERADOR') {
    $filtroUsuario = ' WHERE usuario_id = ?';
    $paramsUsuario[] = (int)$u['id'];
}

/*
|--------------------------------------------------------------------------
| Total de atendimentos
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM atendimentos
    $filtroUsuario
");

$stmt->execute($paramsUsuario);

$totalAtendimentos = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Total de parcelas em atraso
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(parcelas_em_atraso), 0)
    FROM atendimentos
    $filtroUsuario
");

$stmt->execute($paramsUsuario);

$totalParcelasAtraso = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Valor total negociado
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor_negociado), 0)
    FROM atendimentos
    $filtroUsuario
");

$stmt->execute($paramsUsuario);

$totalNegociado = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Valor total recebido
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor_recebido), 0)
    FROM atendimentos
    $filtroUsuario
");

$stmt->execute($paramsUsuario);

$totalRecebido = $stmt->fetchColumn();

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Sistema de Negociações</title>

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

            <h1>Painel</h1>

            <p>
                Atendimentos centralizados em um único sistema.
            </p>

        </div>

        <a
            class="button"
            href="novo_atendimento.php"
        >
            + Novo atendimento
        </a>

    </div>


    <section class="cards">

    <div class="card">

        <span>
            Total de atendimentos
        </span>

        <b>
            <?= $totalAtendimentos ?>
        </b>

    </div>


    <div class="card">

        <span>
            Atendimentos hoje
        </span>

        <b>
            <?= $atendimentosHoje ?>
        </b>

    </div>


    <div class="card">

    <span>
        Valor recebido hoje
    </span>

    <b>
        <?= dinheiro($totalRecebidoHoje) ?>
    </b>

</div>


    <div class="card">

        <span>
            Valor total negociado
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

</section>



    <section class="panel">

        <h2>Ações rápidas</h2>


        <a
            class="button secondary"
            href="novo_atendimento.php"
        >
            Registrar atendimento
        </a>


        <a
            class="button secondary"
            href="atendimentos.php"
        >
            Ver atendimentos
        </a>


        <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

            <a
                class="button secondary"
                href="relatorio.php"
            >
                Relatório mensal
            </a>

        <?php endif; ?>


        <?php if ($u['perfil'] === 'ADMIN'): ?>

            <a
                href="usuarios.php"
                class="button"
            >
                Usuários
            </a>


            <a
                href="auditoria.php"
                class="button secondary"
            >
                Auditoria
            </a>

        <?php endif; ?>

    </section>

</main>

</body>

</html>