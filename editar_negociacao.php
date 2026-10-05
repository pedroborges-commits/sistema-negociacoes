<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();

$erro = '';
$ok = '';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    die('Negociação inválida.');
}

/*
|--------------------------------------------------------------------------
| Busca a negociação
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM negociacoes
    WHERE id = ?
");

$stmt->execute([$id]);

$negociacao = $stmt->fetch();

if (!$negociacao) {
    die('Negociação não encontrada.');
}
if (!podeAcessarNegociacao($negociacao)) {
    http_response_code(403);
    exit('Você não tem permissão para editar esta negociação.');
}
/*
|--------------------------------------------------------------------------
| Verifica se a negociação já foi paga
|--------------------------------------------------------------------------
*/

$negociacaoPaga = $negociacao['status'] === 'PAGO';

/*
|--------------------------------------------------------------------------
| Busca os clientes
|--------------------------------------------------------------------------
*/

$clientes = $pdo->query("
    SELECT id, nome, documento
    FROM clientes
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Salva as alterações
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

validarCsrf();
    // Guarda o estado da negociação antes da alteração
    $dadosAnteriores = $negociacao;

    $observacoes = trim($_POST['observacoes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | NEGOCIAÇÃO PAGA
    | Somente a observação pode ser alterada
    |--------------------------------------------------------------------------
    */

    if ($negociacaoPaga) {

        $stmt = $pdo->prepare("
            UPDATE negociacoes
            SET observacoes = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $observacoes ?: null,
            $id
        ]);

        $ok = 'Observação atualizada com sucesso.';

    }

    /*
    |--------------------------------------------------------------------------
    | NEGOCIAÇÃO PENDENTE OU VENCIDA
    | Permite edição completa
    |--------------------------------------------------------------------------
    */

    else {

        $clienteId = (int)($_POST['cliente_id'] ?? 0);

        $dataContato = $_POST['data_contato'] ?? '';

        $valorOriginal = (float)($_POST['valor_original'] ?? 0);

        $desconto = (float)($_POST['desconto'] ?? 0);

        $dataCombinada = !empty($_POST['data_combinada'])
            ? $_POST['data_combinada']
            : null;

        $descricao = trim($_POST['descricao'] ?? '');

        $valorFinal = $valorOriginal - $desconto;

        /*
        |--------------------------------------------------------------------------
        | Validações
        |--------------------------------------------------------------------------
        */

        if (!$clienteId) {

            $erro = 'Selecione um cliente.';

        } elseif (!$dataContato) {

            $erro = 'Informe a data do contato.';

        } elseif ($valorOriginal < 0) {

            $erro = 'O valor original não pode ser negativo.';

        } elseif ($desconto < 0) {

            $erro = 'O desconto não pode ser negativo.';

        } elseif ($desconto > $valorOriginal) {

            $erro = 'O desconto não pode ser maior que o valor original.';

        } elseif ($valorFinal < 0) {

            $erro = 'O valor final não pode ser negativo.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Atualiza a negociação
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE negociacoes
                SET
                    cliente_id = ?,
                    data_contato = ?,
                    valor_original = ?,
                    desconto = ?,
                    valor_final = ?,
                    data_combinada = ?,
                    descricao = ?,
                    observacoes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $clienteId,
                $dataContato,
                $valorOriginal,
                $desconto,
                $valorFinal,
                $dataCombinada,
                $descricao ?: null,
                $observacoes ?: null,
                $id
            ]);

            $ok = 'Negociação atualizada com sucesso.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Busca novamente os dados atualizados
    |--------------------------------------------------------------------------
    */

   if ($ok) {

    // Busca os dados depois da alteração
    $stmt = $pdo->prepare("
        SELECT *
        FROM negociacoes
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $negociacao = $stmt->fetch();

    $negociacaoPaga = $negociacao['status'] === 'PAGO';

    // Registra a alteração na auditoria
    registrarAuditoria(
        'EDITAR',
        'negociacoes',
        $id,
        $dadosAnteriores,
        $negociacao
    );
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

    <title>Editar negociação</title>

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
        <a href="logout.php">Sair</a>
    </span>

</header>

<main class="container">

    <div class="head">

        <div>

            <h1>Editar negociação</h1>

            <p>
                <?php if ($negociacaoPaga): ?>

                    Esta negociação já foi paga.
                    Apenas a observação pode ser alterada.

                <?php else: ?>

                    Altere os dados da negociação conforme necessário.

                <?php endif; ?>
            </p>

        </div>

        <a
            class="button secondary"
            href="negociacoes.php"
        >
            Voltar
        </a>

    </div>


    <?php if ($erro): ?>

        <div class="panel">

            <p>
                <strong>Erro:</strong>
                <?=e($erro)?>
            </p>

        </div>

    <?php endif; ?>


    <?php if ($ok): ?>

        <div class="panel">

            <p>
                <strong><?=e($ok)?></strong>
            </p>

        </div>

    <?php endif; ?>


    <section class="panel">

        <?php if ($negociacaoPaga): ?>

            <div class="panel">

                <p>
                    <strong>Status:</strong>
                    PAGO
                </p>

                <p>
                    <strong>Valor final:</strong>
                    <?=dinheiro($negociacao['valor_final'])?>
                </p>

                <p>
                    <strong>Valor pago:</strong>
                    <?=dinheiro($negociacao['valor_pago'])?>
                </p>

                <p>
                    <strong>Data do pagamento:</strong>
                    <?=e($negociacao['data_pagamento'])?>
                </p>

            </div>

        <?php endif; ?>


        <form method="post">

            <input
                type="hidden"
                name="id"
                value="<?=e($id)?>"
            >
<input
        type="hidden"
        name="csrf_token"
        value="<?=e(csrfToken())?>"
    >

            <label>
                Cliente

                <select
                    name="cliente_id"
                    <?= $negociacaoPaga ? 'disabled' : 'required' ?>
                >

                    <option value="">
                        Selecione o cliente
                    </option>

                    <?php foreach ($clientes as $cliente): ?>

                        <option
                            value="<?=e($cliente['id'])?>"
                            <?=((int)$cliente['id'] === (int)$negociacao['cliente_id']) ? 'selected' : ''?>
                        >

                            <?=e($cliente['nome'])?>

                            <?php if (!empty($cliente['documento'])): ?>

                                - <?=e($cliente['documento'])?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label>
                Data do contato

                <input
                    type="date"
                    name="data_contato"
                    value="<?=e($negociacao['data_contato'])?>"
                    <?= $negociacaoPaga ? 'disabled' : 'required' ?>
                >

            </label>


            <label>
                Valor original

                <input
                    type="number"
                    name="valor_original"
                    step="0.01"
                    min="0"
                    value="<?=e($negociacao['valor_original'])?>"
                    <?= $negociacaoPaga ? 'disabled' : 'required' ?>
                >

            </label>


            <label>
                Desconto

                <input
                    type="number"
                    name="desconto"
                    step="0.01"
                    min="0"
                    value="<?=e($negociacao['desconto'])?>"
                    <?= $negociacaoPaga ? 'disabled' : '' ?>
                >

            </label>


            <label>
                Data combinada

                <input
                    type="date"
                    name="data_combinada"
                    value="<?=e($negociacao['data_combinada'] ?? '')?>"
                    <?= $negociacaoPaga ? 'disabled' : '' ?>
                >

            </label>


            <label>
                Descrição

                <textarea
                    name="descricao"
                    rows="4"
                    <?= $negociacaoPaga ? 'disabled' : '' ?>
                ><?=e($negociacao['descricao'] ?? '')?></textarea>

            </label>


            <label>
                Observações

                <textarea
                    name="observacoes"
                    rows="4"
                ><?=e($negociacao['observacoes'] ?? '')?></textarea>

            </label>


            <div>

                <button
                    type="submit"
                    class="button"
                >

                    <?php if ($negociacaoPaga): ?>

                        Salvar observação

                    <?php else: ?>

                        Salvar alterações

                    <?php endif; ?>

                </button>


                <a
                    href="negociacoes.php"
                    class="button secondary"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </section>

</main>

</body>

</html>