<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();


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

if ($u['perfil'] === 'OPERADOR') {

    $stmt = $pdo->prepare("
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
        WHERE a.usuario_id = ?
        ORDER BY
            a.data_atendimento DESC,
            a.id DESC
    ");

    $stmt->execute([$u['id']]);

} else {

    $stmt = $pdo->query("
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
        ORDER BY
            a.data_atendimento DESC,
            a.id DESC
    ");
}

$atendimentos = $stmt->fetchAll();

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

        <?php if (!$atendimentos): ?>

            <p>
                Nenhum atendimento cadastrado.
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