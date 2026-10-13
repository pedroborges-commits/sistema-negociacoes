<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();


/*
|--------------------------------------------------------------------------
| Busca os atendimentos
|--------------------------------------------------------------------------
|
| OPERADOR:
| somente os próprios atendimentos.
|
| SUPERVISOR e ADMIN:
| todos os atendimentos.
|
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

    <title>Atendimentos</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<header class="topbar">

    <strong>
        Sistema de Negociações
    </strong>

    <span>

        <?= e($u['nome']) ?> ·

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

            <h1>
                Atendimentos
            </h1>

            <p>
                Consulte os atendimentos registrados no sistema.
            </p>

        </div>


        <div>

            <a
                href="novo_atendimento.php"
                class="button"
            >
                + Novo atendimento
            </a>

            <a href="index.php">
                ← Voltar
            </a>

        </div>

    </div>


    <div class="panel">

    <form method="GET">

        <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:end;">

            <div>
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

            <div>
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

            <div>
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

                <div>
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

            <div>
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

            <div>

                <button
                    type="submit"
                    class="button"
                >
                    Filtrar
                </button>

                <a
                    href="atendimentos.php"
                    class="button secondary"
                >
                    Limpar
                </a>

            </div>

        </div>

    </form>
<hr>

<?php if ($erroFiltro !== ''): ?>

    <p>
        <?= e($erroFiltro) ?>
    </p>

<?php elseif (empty($atendimentos)): ?>

    <p>
        Nenhum atendimento encontrado.
    </p>

<?php else: ?>
   

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Data
                            </th>

                            <th>
                                Inscrição
                            </th>

                            <th>
                                Parcelas em atraso
                            </th>

                            <th>
                                Valor negociado
                            </th>

                            <th>
                                Valor recebido
                            </th>

                            <th>
                                Data pagamento
                            </th>

                            <th>
                                Operadora
                            </th>

                            <th>
                                Ação
                            </th>

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

                                    Não pago

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
                                <?= e($a['usuario_nome']) ?>
                            </td>


                            <td>

                                <a
                                    class="button secondary"
                                    href="editar_atendimento.php?id=<?= $a['id'] ?>"
                                >
                                    <?= $a['valor_recebido'] !== null
                                        ? 'Editar observação'
                                        : 'Editar'
                                    ?>
                                </a>


                                <?php if ($a['valor_recebido'] === null): ?>

                                    <a
                                        class="button"
                                        href="pagamento_atendimento.php?id=<?= $a['id'] ?>"
                                    >
                                        Registrar pagamento
                                    </a>

                                <?php else: ?>

                                    <span>
                                        Pagamento registrado
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</main>

</body>

</html>