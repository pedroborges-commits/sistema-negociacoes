<?php
require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();

$erro = '';
$ok = '';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$id) {
    die('Negociação não informada.');
}

/*
 * Busca a negociação
 */
$stmt = $pdo->prepare("
    SELECT
        n.*,
        c.nome AS cliente_nome
    FROM negociacoes n
    INNER JOIN clientes c ON c.id = n.cliente_id
    WHERE n.id = ?
");

$stmt->execute([$id]);

$negociacao = $stmt->fetch();

if (!$negociacao) {
    die('Negociação não encontrada.');
}
if (!podeAcessarNegociacao($negociacao)) {
    http_response_code(403);
    exit('Você não tem permissão para acessar esta negociação.');
}
/*
 * Registrar pagamento
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
validarCsrf();
    // Guarda o estado antes do pagamento
    $dadosAnteriores = $negociacao;

    $dataPagamento = $_POST['data_pagamento'] ?? '';
    $valorPago = (float)($_POST['valor_pago'] ?? 0);

    if (!$dataPagamento) {

        $erro = 'Informe a data do pagamento.';

    } elseif ($valorPago <= 0) {

        $erro = 'Informe um valor pago válido.';

    } elseif (
        abs($valorPago - (float)$negociacao['valor_final']) > 0.009
    ) {

        $erro = 'O valor pago deve ser igual ao valor final da negociação.';

    } else {

        $stmt = $pdo->prepare("
            UPDATE negociacoes
            SET
                data_pagamento = ?,
                valor_pago = ?,
                status = 'PAGO'
            WHERE id = ?
        ");

        $stmt->execute([
            $dataPagamento,
            $valorPago,
            $id
        ]);

        $ok = 'Pagamento registrado com sucesso.';

        /*
         * Busca os dados atualizados
         */
        $stmt = $pdo->prepare("
            SELECT
                n.*,
                c.nome AS cliente_nome
            FROM negociacoes n
            INNER JOIN clientes c ON c.id = n.cliente_id
            WHERE n.id = ?
        ");

        $stmt->execute([$id]);

        $negociacao = $stmt->fetch();

        /*
         * Registra o pagamento na auditoria
         */
        registrarAuditoria(
            'PAGAR',
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Registrar pagamento</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<header class="topbar">
    <strong>Sistema de Negociações</strong>

    <span>
        <?= e($u['nome']) ?> ·
        <a href="logout.php">Sair</a>
    </span>
</header>

<main class="container narrow">

    <div class="head">

        <div>
            <h1>Registrar pagamento</h1>

            <p>
                Cliente: <?= e($negociacao['cliente_nome']) ?>
            </p>
        </div>

        <a href="index.php">← Voltar</a>

    </div>


    <?php if ($erro): ?>

        <div class="alert error">
            <?= e($erro) ?>
        </div>

    <?php endif; ?>


    <?php if ($ok): ?>

        <div class="alert success">
            <?= e($ok) ?>
        </div>

    <?php endif; ?>


    <div class="panel">

        <h2>Dados da negociação</h2>

        <p>
            <strong>Valor original:</strong>
            <?= dinheiro($negociacao['valor_original']) ?>
        </p>

        <p>
            <strong>Desconto:</strong>
            <?= dinheiro($negociacao['desconto']) ?>
        </p>

        <p>
            <strong>Valor final:</strong>
            <?= dinheiro($negociacao['valor_final']) ?>
        </p>

        <p>
            <strong>Status atual:</strong>
            <?= e($negociacao['status']) ?>
        </p>

    </div>


    <?php if ($negociacao['status'] !== 'PAGO'): ?>

        <form class="panel form" method="post">

            <input
                type="hidden"
                name="id"
                value="<?= $negociacao['id'] ?>"

            >
            <input
    type="hidden"
    name="csrf_token"
    value="<?=e(csrfToken())?>"
>

            <label>
                Data do pagamento

                <input
                    type="date"
                    name="data_pagamento"
                    value="<?= date('Y-m-d') ?>"
                    required
                >
            </label>


            <label>
                Valor pago

                <input
                    type="number"
                    name="valor_pago"
                    step="0.01"
                    min="0.01"
                    value="<?= e($negociacao['valor_final']) ?>"
                    required
                >
            </label>


            <div class="wide">

                <button class="button">
                    Confirmar pagamento
                </button>

            </div>

        </form>

    <?php else: ?>

        <div class="panel">

            <h2>Pagamento realizado</h2>

            <p>
                <strong>Data:</strong>
                <?= e($negociacao['data_pagamento']) ?>
            </p>

            <p>
                <strong>Valor pago:</strong>
                <?= dinheiro($negociacao['valor_pago']) ?>
            </p>

        </div>

    <?php endif; ?>

</main>

</body>
</html>