
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
    INNER JOIN usuarios u ON u.id = a.usuario_id
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
    exit('Você não tem permissão para editar este atendimento.');
}

/*
|--------------------------------------------------------------------------
| Verifica se o atendimento já foi pago
|--------------------------------------------------------------------------
*/

$estaPago =
    $atendimento['valor_recebido'] !== null ||
    $atendimento['data_pagamento'] !== null;

/*
|--------------------------------------------------------------------------
| Processamento das alterações
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    try {

        /*
        |--------------------------------------------------------------------------
        | Inicia a transação e bloqueia o atendimento durante a edição
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                a.*,
                u.nome AS usuario_nome
            FROM atendimentos a
            INNER JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$id]);
        $atendimento = $stmt->fetch();

        if (!$atendimento) {
            throw new RuntimeException(
                'O atendimento não foi encontrado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Confirma novamente a permissão
        |--------------------------------------------------------------------------
        */

        if (
            $u['perfil'] === 'OPERADOR' &&
            (int)$atendimento['usuario_id'] !== (int)$u['id']
        ) {
            throw new RuntimeException(
                'Você não tem permissão para editar este atendimento.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Confere novamente se existe pagamento
        |--------------------------------------------------------------------------
        */

        $estaPago =
            $atendimento['valor_recebido'] !== null ||
            $atendimento['data_pagamento'] !== null;

        /*
        |--------------------------------------------------------------------------
        | Atendimento pago: permite alterar somente a observação
        |--------------------------------------------------------------------------
        */

        if ($estaPago) {

            $dadosAnteriores = [
                'observacoes' => $atendimento['observacoes']
            ];

            $observacoes = trim(
                $_POST['observacoes'] ?? ''
            );

            $observacoesNovas = $observacoes !== ''
                ? $observacoes
                : null;

            $stmt = $pdo->prepare("
                UPDATE atendimentos
                SET observacoes = ?
                WHERE id = ?
                  AND (
                      valor_recebido IS NOT NULL
                      OR data_pagamento IS NOT NULL
                  )
            ");

            $stmt->execute([
                $observacoesNovas,
                $id
            ]);

            $dadosNovos = [
                'observacoes' => $observacoesNovas
            ];

            /*
            |--------------------------------------------------------------------------
            | Registra a auditoria na mesma transação
            |--------------------------------------------------------------------------
            */

            registrarAuditoria(
                'EDITAR_OBSERVACAO',
                'atendimentos',
                $id,
                $dadosAnteriores,
                $dadosNovos
            );

            /*
            |--------------------------------------------------------------------------
            | Confirma a alteração e a auditoria juntas
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            $atendimento['observacoes'] = $observacoesNovas;

            $ok = 'Observação atualizada com sucesso.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Atendimento pendente: coleta os dados do formulário
            |--------------------------------------------------------------------------
            */

            $dadosAnteriores = [
                'usuario_id' => (int)$atendimento['usuario_id'],
                'data_atendimento' => $atendimento['data_atendimento'],
                'inscricao' => $atendimento['inscricao'],
                'parcelas_em_atraso' => (int)$atendimento['parcelas_em_atraso'],
                'valor_negociado' => $atendimento['valor_negociado'],
                'observacoes' => $atendimento['observacoes']
            ];

            $data = $_POST['data_atendimento'] ?? '';

            $inscricao = trim(
                $_POST['inscricao'] ?? ''
            );

            $parcelasTexto = $_POST['parcelas_em_atraso'] ?? '';
            $parcelas = filter_var(
                $parcelasTexto,
                FILTER_VALIDATE_INT
            );

            $valorTexto = $_POST['valor_negociado'] ?? '';
            $valorNegociado = is_numeric($valorTexto)
                ? (float)$valorTexto
                : 0;

            $observacoes = trim(
                $_POST['observacoes'] ?? ''
            );

            /*
            |--------------------------------------------------------------------------
            | Validações
            |--------------------------------------------------------------------------
            */

            if (!validarData($data)) {

                $erro = 'Informe uma data de atendimento válida.';

            } elseif ($inscricao === '') {

                $erro = 'Informe a inscrição.';

            } elseif (
                $parcelas === false ||
                $parcelas < 1
            ) {

                $erro = 'A quantidade de parcelas em atraso deve ser um número inteiro de pelo menos 1.';

            } elseif (
                !is_numeric($valorTexto) ||
                $valorNegociado <= 0 ||
                !is_finite($valorNegociado)
            ) {

                $erro = 'O valor negociado deve ser maior que zero.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Atualiza o atendimento ainda não pago
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE atendimentos
                    SET
                        data_atendimento = ?,
                        inscricao = ?,
                        parcelas_em_atraso = ?,
                        valor_negociado = ?,
                        observacoes = ?
                    WHERE id = ?
                      AND valor_recebido IS NULL
                      AND data_pagamento IS NULL
                ");

                $stmt->execute([
                    $data,
                    $inscricao,
                    $parcelas,
                    $valorNegociado,
                    $observacoes !== '' ? $observacoes : null,
                    $id
                ]);

                /*
                |--------------------------------------------------------------------------
                | Busca os dados atualizados
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        a.*,
                        u.nome AS usuario_nome
                    FROM atendimentos a
                    INNER JOIN usuarios u ON u.id = a.usuario_id
                    WHERE a.id = ?
                    LIMIT 1
                ");

                $stmt->execute([$id]);
                $atendimentoAtualizado = $stmt->fetch();

                if (
                    !$atendimentoAtualizado ||
                    $atendimentoAtualizado['valor_recebido'] !== null ||
                    $atendimentoAtualizado['data_pagamento'] !== null
                ) {
                    throw new RuntimeException(
                        'Não foi possível atualizar o atendimento. Verifique se ele já possui um pagamento registrado.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Prepara os dados novos para a auditoria
                |--------------------------------------------------------------------------
                */

                $dadosNovos = [
                    'usuario_id' => (int)$atendimentoAtualizado['usuario_id'],
                    'data_atendimento' => $atendimentoAtualizado['data_atendimento'],
                    'inscricao' => $atendimentoAtualizado['inscricao'],
                    'parcelas_em_atraso' => (int)$atendimentoAtualizado['parcelas_em_atraso'],
                    'valor_negociado' => $atendimentoAtualizado['valor_negociado'],
                    'observacoes' => $atendimentoAtualizado['observacoes']
                ];

                /*
                |--------------------------------------------------------------------------
                | Registra a auditoria na mesma transação
                |--------------------------------------------------------------------------
                */

                registrarAuditoria(
                    'EDITAR',
                    'atendimentos',
                    $id,
                    $dadosAnteriores,
                    $dadosNovos
                );

                /*
                |--------------------------------------------------------------------------
                | Confirma a alteração e a auditoria juntas
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                $atendimento = $atendimentoAtualizado;

                $ok = 'Atendimento atualizado com sucesso.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Se houve erro de validação, desfaz a transação
        |--------------------------------------------------------------------------
        */

        if ($erro !== '' && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Erro ao editar atendimento: ' . $e->getMessage()
        );

        if (
            $e instanceof RuntimeException &&
            (
                $e->getMessage() === 'Você não tem permissão para editar este atendimento.' ||
                $e->getMessage() === 'O atendimento não foi encontrado.'
            )
        ) {
            http_response_code(
                $e->getMessage() === 'O atendimento não foi encontrado.'
                    ? 404
                    : 403
            );

            exit(e($e->getMessage()));
        }

        $erro = 'Não foi possível salvar as alterações. Nenhuma alteração foi confirmada. Tente novamente.';
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

    <title>
        <?= $estaPago ? 'Editar observação' : 'Editar atendimento' ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<header class="topbar">

    <strong>Sistema de Negociações</strong>

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
                <?= $estaPago ? 'Editar observação' : 'Editar atendimento' ?>
            </h1>

            <p>

                <?php if ($estaPago): ?>

                    Este atendimento já foi pago.
                    Apenas a observação pode ser alterada.

                <?php else: ?>

                    Altere os dados do atendimento conforme necessário.

                <?php endif; ?>

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

    <?php elseif ($ok): ?>

        <div class="panel">

            <p>
                <strong><?= e($ok) ?></strong>
            </p>

        </div>

    <?php endif; ?>

    <section class="panel">

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

            <?php if (!$estaPago): ?>

                <label>

                    Data do atendimento

                    <input
                        type="date"
                        name="data_atendimento"
                        value="<?= e($atendimento['data_atendimento']) ?>"
                        required
                    >

                </label>

                <label>

                    Inscrição

                    <input
                        type="text"
                        name="inscricao"
                        value="<?= e($atendimento['inscricao']) ?>"
                        maxlength="50"
                        required
                    >

                </label>

                <label>

                    Parcelas em atraso

                    <input
                        type="number"
                        name="parcelas_em_atraso"
                        value="<?= e((string)$atendimento['parcelas_em_atraso']) ?>"
                        min="1"
                        step="1"
                        required
                    >

                </label>

                <label>

                    Valor negociado

                    <input
                        type="number"
                        name="valor_negociado"
                        value="<?= e((string)$atendimento['valor_negociado']) ?>"
                        min="0.01"
                        step="0.01"
                        required
                    >

                </label>

            <?php else: ?>

                <div class="alert">

                    Este atendimento já foi pago.
                    Os dados do atendimento não podem mais ser alterados.

                </div>

            <?php endif; ?>

            <label>

                Observações

                <textarea
                    name="observacoes"
                    rows="4"
                ><?= e($atendimento['observacoes'] ?? '') ?></textarea>

            </label>

            <div>

                <button
                    type="submit"
                    class="button"
                >
                    <?= $estaPago
                        ? 'Salvar observação'
                        : 'Salvar alterações'
                    ?>
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

</main>

</body>

</html>
