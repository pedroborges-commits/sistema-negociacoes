
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


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>
        <?= $estaPago ? 'Editar observação' : 'Editar atendimento' ?>
    </title>

    <link rel="stylesheet" href="assets/style.css?v=3">
</head>

<body class="dashboard-page">

<div class="dashboard-layout">

    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="brand-symbol">N</div>

            <div>
                <strong>Negociações</strong>
                <span>Sistema de gestão</span>
            </div>
        </div>

        <div class="sidebar-section-label">MENU PRINCIPAL</div>

        <nav class="sidebar-nav">

            <a href="index.php" class="sidebar-link">
                <span class="nav-icon">⌂</span>
                <span>Painel</span>
            </a>

            <a href="novo_atendimento.php" class="sidebar-link">
                <span class="nav-icon">＋</span>
                <span>Novo atendimento</span>
            </a>

            <a href="atendimentos.php" class="sidebar-link active">
                <span class="nav-icon">▤</span>
                <span>Atendimentos</span>
                <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
</a>
            </a>

            <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link">
                    <span class="nav-icon">▥</span>
                    <span>Relatórios</span>
                </a>

            <?php endif; ?>

            <?php if ($u['perfil'] === 'ADMIN'): ?>

                <a href="usuarios.php" class="sidebar-link">
                    <span class="nav-icon">♙</span>
                    <span>Usuários</span>
                </a>

                <a href="auditoria.php" class="sidebar-link">
                    <span class="nav-icon">◷</span>
                    <span>Auditoria</span>
                </a>

            <?php endif; ?>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-user">

                <div class="user-avatar">
                    <?= e(mb_strtoupper(mb_substr($u['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                </div>

                <div class="user-info">
                    <strong><?= e($u['nome']) ?></strong>
                    <span><?= e($u['perfil']) ?></span>
                </div>

            </div>

            <form method="POST" action="logout.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <button type="submit" class="logout-button">
                    <span>↪</span>
                    Sair do sistema
                </button>

            </form>

        </div>

    </aside>

    <main class="dashboard-main">

        <div class="dashboard-content">

            <header class="edit-page-header">

                <div>
                    <h1>
                        <?= $estaPago ? 'Editar observação' : 'Editar atendimento' ?>
                    </h1>

                    <p>
                        <?php if ($estaPago): ?>

                            Este atendimento já foi pago.
                            Apenas a observação pode ser alterada.

                        <?php else: ?>

                            Atualize as informações do atendimento.

                        <?php endif; ?>
                    </p>
                </div>

                <a href="atendimentos.php" class="button secondary">
                    Voltar
                </a>

            </header>

            <?php if ($erro): ?>

                <div class="alert error">
                    <?= e($erro) ?>
                </div>

            <?php elseif ($ok): ?>

                <div class="alert success">
                    <?= e($ok) ?>
                </div>

            <?php endif; ?>

            <section class="edit-panel">

                <div class="edit-panel-heading">

                    <div>
                        <h2>Dados do atendimento</h2>
                        <p>
                            Confira as informações antes de salvar.
                        </p>
                    </div>

                    <?php if ($estaPago): ?>

                        <span class="edit-status paid">
                            Pago
                        </span>

                    <?php else: ?>

                        <span class="edit-status pending">
                            Pendente
                        </span>

                    <?php endif; ?>

                </div>

                <?php if ($estaPago): ?>

                    <div class="edit-paid-notice">
                        <strong>Atendimento já pago</strong>
                        <p>
                            Os dados principais não podem mais ser alterados.
                            Você pode atualizar somente as observações.
                        </p>
                    </div>

                <?php endif; ?>

                <form method="POST" class="edit-form">

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

                        <div class="edit-form-grid">

                            <div class="edit-field">
                                <label for="data_atendimento">
                                    Data do atendimento
                                </label>

                                <input
                                    type="date"
                                    id="data_atendimento"
                                    name="data_atendimento"
                                    value="<?= e($atendimento['data_atendimento']) ?>"
                                    required
                                >
                            </div>

                            <div class="edit-field">
                                <label for="inscricao">
                                    Inscrição
                                </label>

                                <input
                                    type="text"
                                    id="inscricao"
                                    name="inscricao"
                                    value="<?= e($atendimento['inscricao']) ?>"
                                    maxlength="50"
                                    required
                                >
                            </div>

                            <div class="edit-field">
                                <label for="parcelas_em_atraso">
                                    Parcelas em atraso
                                </label>

                                <input
                                    type="number"
                                    id="parcelas_em_atraso"
                                    name="parcelas_em_atraso"
                                    value="<?= e((string)$atendimento['parcelas_em_atraso']) ?>"
                                    min="1"
                                    step="1"
                                    required
                                >
                            </div>

                            <div class="edit-field">
                                <label for="valor_negociado">
                                    Valor negociado
                                </label>

                                <input
                                    type="number"
                                    id="valor_negociado"
                                    name="valor_negociado"
                                    value="<?= e((string)$atendimento['valor_negociado']) ?>"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >
                            </div>

                        </div>

                    <?php endif; ?>

                    <div class="edit-field edit-observations">
                        <label for="observacoes">
                            Observações
                        </label>

                        <textarea
                            id="observacoes"
                            name="observacoes"
                            rows="5"
                            placeholder="Adicione informações relevantes sobre o atendimento..."
                        ><?= e($atendimento['observacoes'] ?? '') ?></textarea>
                    </div>

                    <div class="edit-form-actions">

                        <button type="submit" class="button">
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

        </div>

    </main>

</div>

</body>
</html>
