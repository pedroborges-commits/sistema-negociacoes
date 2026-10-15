
<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();

$erro = '';
$ok = '';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    die('Atendimento inválido.');
}

/*
|--------------------------------------------------------------------------
| Busca o atendimento
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.*,
        u.nome AS usuario_nome
    FROM atendimentos a
    INNER JOIN usuarios u
        ON u.id = a.usuario_id
    WHERE a.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$atendimento = $stmt->fetch();

if (!$atendimento) {
    die('Atendimento não encontrado.');
}

/*
|--------------------------------------------------------------------------
| Verifica permissão
|--------------------------------------------------------------------------
*/

if (
    $u['perfil'] === 'OPERADOR' &&
    (int)$atendimento['usuario_id'] !== (int)$u['id']
) {
    http_response_code(403);
    exit('Você não tem permissão para registrar pagamento deste atendimento.');
}

/*
|--------------------------------------------------------------------------
| Verifica se já existe pagamento
|--------------------------------------------------------------------------
*/

if (
    $atendimento['valor_recebido'] !== null ||
    $atendimento['data_pagamento'] !== null
) {
    $erro = 'Este atendimento já possui um pagamento registrado.';
}

/*
|--------------------------------------------------------------------------
| Processamento do pagamento
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro === '') {

    validarCsrf();

    $dataPagamento = $_POST['data_pagamento'] ?? '';
    $valorRecebido = (float)($_POST['valor_recebido'] ?? 0);

    if (!validarData($dataPagamento)) {

        $erro = 'Informe uma data de pagamento válida.';

    } elseif (!is_numeric($_POST['valor_recebido'] ?? null) || $valorRecebido <= 0) {

        $erro = 'O valor recebido deve ser maior que zero.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Inicia a transação
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Salva o pagamento
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE atendimentos
                SET
                    valor_recebido = ?,
                    data_pagamento = ?
                WHERE id = ?
                  AND valor_recebido IS NULL
                  AND data_pagamento IS NULL
            ");

            $stmt->execute([
                $valorRecebido,
                $dataPagamento,
                $id
            ]);

            /*
            |--------------------------------------------------------------------------
            | Verifica se o pagamento foi atualizado
            |--------------------------------------------------------------------------
            */

            if ($stmt->rowCount() !== 1) {

                $pdo->rollBack();

                $erro = 'Não foi possível registrar o pagamento. O atendimento pode já possuir um pagamento.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Dados novos para auditoria
                |--------------------------------------------------------------------------
                */

                $dadosNovos = [
                    'valor_recebido' => $valorRecebido,
                    'data_pagamento' => $dataPagamento
                ];

                /*
                |--------------------------------------------------------------------------
                | Registra a auditoria na mesma transação
                |--------------------------------------------------------------------------
                */

                registrarAuditoria(
                    'PAGAR',
                    'atendimentos',
                    (int)$id,
                    null,
                    $dadosNovos
                );

                /*
                |--------------------------------------------------------------------------
                | Confirma o pagamento e a auditoria juntos
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | Atualiza os dados exibidos
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        a.*,
                        u.nome AS usuario_nome
                    FROM atendimentos a
                    INNER JOIN usuarios u
                        ON u.id = a.usuario_id
                    WHERE a.id = ?
                    LIMIT 1
                ");

                $stmt->execute([$id]);

                $atendimento = $stmt->fetch();

                $ok = 'Pagamento registrado com sucesso.';
            }

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Desfaz a transação se alguma operação falhar
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao registrar pagamento: ' . $e->getMessage()
            );

            $erro = 'Não foi possível registrar o pagamento. Nenhuma alteração foi salva. Tente novamente.';
        }
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

    <title>Registrar pagamento</title>

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

            <h1>Registrar pagamento</h1>

            <p>
                Registre o pagamento realizado para este atendimento.
            </p>

        </div>

        <a
            class="button secondary"
            href="atendimentos.php"
        >
            Voltar
        </a>

    </div>

    <?php if ($erro): ?>

        <div class="panel">

            <p>
                <strong>Erro:</strong>
                <?= e($erro) ?>
            </p>

        </div>

    <?php endif; ?>

    <?php if ($ok): ?>

        <div class="panel">

            <p>
                <strong><?= e($ok) ?></strong>
            </p>

        </div>

    <?php endif; ?>

    <section class="panel">

        <h2>Dados do atendimento</h2>

        <p>
            <strong>Data do atendimento:</strong>
            <?= e($atendimento['data_atendimento']) ?>
        </p>

        <p>
            <strong>Inscrição:</strong>
            <?= e($atendimento['inscricao']) ?>
        </p>

        <p>
            <strong>Parcelas em atraso:</strong>
            <?= e((string)$atendimento['parcelas_em_atraso']) ?>
        </p>

        <p>
            <strong>Valor negociado:</strong>
            <?= dinheiro($atendimento['valor_negociado']) ?>
        </p>

        <p>
            <strong>Operadora:</strong>
            <?= e($atendimento['usuario_nome']) ?>
        </p>

    </section>

    <?php if (
        $atendimento['valor_recebido'] === null &&
        $atendimento['data_pagamento'] === null
    ): ?>

        <section class="panel">

            <h2>Dados do pagamento</h2>

            <form method="post">

                <input
                    type="hidden"
                    name="id"
                    value="<?= e((string)$id) ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <label>

                    Data do pagamento

                    <input
                        type="date"
                        name="data_pagamento"
                        value="<?= e($_POST['data_pagamento'] ?? date('Y-m-d')) ?>"
                        required
                    >

                </label>

                <label>

                    Valor recebido

                    <input
                        type="number"
                        name="valor_recebido"
                        value="<?= e($_POST['valor_recebido'] ?? '') ?>"
                        min="0.01"
                        step="0.01"
                        required
                    >

                </label>

                <div>

                    <button
                        type="submit"
                        class="button"
                    >
                        Registrar pagamento
                    </button>

                    <a
                        href="atendimentos.php"
                        class="button secondary"
                    >
                        Cancelar
                    </a>

                </div>

            </form>

        </section>

    <?php else: ?>

        <section class="panel">

            <h2>Pagamento registrado</h2>

            <p>

                <strong>Data do pagamento:</strong>

                <?= e($atendimento['data_pagamento']) ?>

            </p>

            <p>

                <strong>Valor recebido:</strong>

                <?= dinheiro($atendimento['valor_recebido']) ?>

            </p>

        </section>

    <?php endif; ?>

</main>

</body>

</html>
