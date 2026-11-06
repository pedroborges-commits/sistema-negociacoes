<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();

$erro = '';
$ok = '';

$data = $_POST['data_atendimento'] ?? date('Y-m-d');
$inscricao = trim($_POST['inscricao'] ?? '');
$parcelas = $_POST['parcelas_em_atraso'] ?? '';
$valorNegociado = $_POST['valor_negociado'] ?? '';
$observacoes = trim($_POST['observacoes'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    /*
    |--------------------------------------------------------------------------
    | Valida os dados recebidos
    |--------------------------------------------------------------------------
    */

    $parcelasInt = filter_var(
        $parcelas,
        FILTER_VALIDATE_INT
    );

    $valor = is_numeric($valorNegociado)
        ? (float)$valorNegociado
        : 0;

    if (!validarData($data)) {

        $erro = 'Informe uma data de atendimento válida.';

    } elseif ($inscricao === '') {

        $erro = 'Informe a inscrição.';

    } elseif (
        $parcelasInt === false ||
        $parcelasInt < 1
    ) {

        $erro = 'A quantidade de parcelas em atraso deve ser um número inteiro de pelo menos 1.';

    } elseif (
        !is_numeric($valorNegociado) ||
        !is_finite($valor) ||
        $valor <= 0
    ) {

        $erro = 'O valor negociado deve ser maior que zero.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Prepara os dados do atendimento
        |--------------------------------------------------------------------------
        */

        $dadosNovos = [
            'usuario_id' => (int)$u['id'],
            'data_atendimento' => $data,
            'inscricao' => $inscricao,
            'parcelas_em_atraso' => $parcelasInt,
            'valor_negociado' => $valor,
            'observacoes' => $observacoes !== ''
                ? $observacoes
                : null
        ];

        try {

            /*
            |--------------------------------------------------------------------------
            | Inicia a transação
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Cadastra o atendimento
            |--------------------------------------------------------------------------
            */

            $s = $pdo->prepare("
                INSERT INTO atendimentos (
                    usuario_id,
                    data_atendimento,
                    inscricao,
                    parcelas_em_atraso,
                    valor_negociado,
                    observacoes
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $s->execute([
                $dadosNovos['usuario_id'],
                $dadosNovos['data_atendimento'],
                $dadosNovos['inscricao'],
                $dadosNovos['parcelas_em_atraso'],
                $dadosNovos['valor_negociado'],
                $dadosNovos['observacoes']
            ]);

            $atendimentoId = (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Registra a auditoria na mesma transação
            |--------------------------------------------------------------------------
            */

            registrarAuditoria(
                'CRIAR',
                'atendimentos',
                $atendimentoId,
                null,
                $dadosNovos
            );

            /*
            |--------------------------------------------------------------------------
            | Confirma o cadastro e a auditoria juntos
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            $ok = 'Atendimento registrado com sucesso.';

            /*
            |--------------------------------------------------------------------------
            | Limpa os campos após o cadastro
            |--------------------------------------------------------------------------
            */

            $data = date('Y-m-d');
            $inscricao = '';
            $parcelas = '';
            $valorNegociado = '';
            $observacoes = '';

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Desfaz o cadastro se alguma operação falhar
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erro ao cadastrar atendimento: ' . $e->getMessage()
            );

            $erro = 'Não foi possível registrar o atendimento. Nenhuma alteração foi confirmada. Tente novamente.';
        }
    }
}

?>

<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/x-icon" href="/sistema-negociacoes/favicon.ico?v=2">
    <title>Novo atendimento</title>

    <link rel="stylesheet" href="/sistema-negociacoes-redesign/assets/style.css?v=2">
</head>

<body class="dashboard-page">

<div class="dashboard-layout">

    <!-- MENU LATERAL -->
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

            <a href="novo_atendimento.php" class="sidebar-link active">
                <span class="nav-icon">＋</span>
                <span>Novo atendimento</span>
            </a>

            <a href="atendimentos.php" class="sidebar-link">
                <span class="nav-icon">▤</span>
                <span>Atendimentos</span>
            </a>
            <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
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

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="dashboard-main">

        <div class="dashboard-content">

            <!-- CABEÇALHO -->
            <section class="dashboard-welcome attendance-page-welcome">

                <div>
                    <span class="welcome-label">ATENDIMENTOS</span>

                    <h1 class="welcome-text">Novo atendimento</h1>

                    <p>
                        Registre o atendimento realizado com o associado.
                    </p>
                </div>

                <a href="atendimentos.php" class="new-attendance-back">
                    ← Voltar para atendimentos
                </a>

            </section>

            <!-- MENSAGENS -->
            <?php if ($erro): ?>

                <div class="alert error">
                    <?= e($erro) ?>
                </div>

            <?php elseif ($ok): ?>

                <div class="alert success">
                    <?= e($ok) ?>
                </div>

            <?php endif; ?>

            <!-- FORMULÁRIO -->
            <section class="panel new-attendance-panel">

                <div class="new-attendance-heading">

                    <div>
                        <h2>Dados do atendimento</h2>

                        <p>
                            Preencha os campos abaixo para registrar o atendimento.
                        </p>
                    </div>

                    <span class="new-attendance-required">
                        * Campos obrigatórios
                    </span>

                </div>

                <form class="new-attendance-form" method="post">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrfToken()) ?>"
                    >

                    <div class="new-attendance-field">

                        <label for="data_atendimento">
                            Data do atendimento <span>*</span>
                        </label>

                        <input
                            type="date"
                            id="data_atendimento"
                            name="data_atendimento"
                            value="<?= e($data) ?>"
                            required
                        >

                    </div>

                    <div class="new-attendance-field">

                        <label for="inscricao">
                            Inscrição <span>*</span>
                        </label>

                        <input
                            type="text"
                            id="inscricao"
                            name="inscricao"
                            value="<?= e($inscricao) ?>"
                            maxlength="50"
                            placeholder="Informe o número da inscrição"
                            required
                        >

                    </div>

                    <div class="new-attendance-field">

                        <label for="parcelas_em_atraso">
                            Parcelas em atraso <span>*</span>
                        </label>

                        <input
                            type="number"
                            id="parcelas_em_atraso"
                            name="parcelas_em_atraso"
                            value="<?= e((string)$parcelas) ?>"
                            min="1"
                            step="1"
                            placeholder="Ex.: 3"
                            required
                        >

                    </div>

                    <div class="new-attendance-field">

                        <label for="valor_negociado">
                            Valor negociado <span>*</span>
                        </label>

                        <input
                            type="number"
                            id="valor_negociado"
                            name="valor_negociado"
                            value="<?= e((string)$valorNegociado) ?>"
                            min="0.01"
                            step="0.01"
                            placeholder="Ex.: 150.00"
                            required
                        >

                    </div>

                    <div class="new-attendance-field full">

                        <label for="observacoes">
                            Observações
                        </label>

                        <textarea
                            id="observacoes"
                            name="observacoes"
                            rows="5"
                            placeholder="Adicione informações relevantes sobre o atendimento..."
                        ><?= e($observacoes) ?></textarea>

                    </div>

                    <div class="new-attendance-actions">

                        <a href="atendimentos.php" class="new-attendance-cancel">
                            Cancelar
                        </a>

                        <button type="submit" class="new-attendance-submit">
                            Registrar atendimento
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

</div>

</body>
</html>