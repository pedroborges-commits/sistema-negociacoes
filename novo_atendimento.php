
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

    <title>Novo atendimento</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<header class="topbar">

    <strong>Sistema de Negociações</strong>

    <span>

        <?= e($u['nome']) ?> ·

        <form method="POST" action="logout.php" style="display:inline;">

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

<main class="container narrow">

    <div class="head">

        <div>

            <h1>Novo atendimento</h1>

            <p>
                Registre o atendimento realizado com o associado.
            </p>

        </div>

        <a href="index.php">
            ← Voltar
        </a>

    </div>

    <?php if ($erro): ?>

        <div class="alert error">
            <?= e($erro) ?>
        </div>

    <?php elseif ($ok): ?>

        <div class="alert success">
            <?= e($ok) ?>
        </div>

    <?php endif; ?>

    <form class="panel form" method="post">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrfToken()) ?>"
        >

        <label>

            Data do atendimento

            <input
                type="date"
                name="data_atendimento"
                value="<?= e($data) ?>"
                required
            >

        </label>

        <label>

            Inscrição

            <input
                type="text"
                name="inscricao"
                value="<?= e($inscricao) ?>"
                maxlength="50"
                required
            >

        </label>

        <label>

            Parcelas em atraso

            <input
                type="number"
                name="parcelas_em_atraso"
                value="<?= e((string)$parcelas) ?>"
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
                value="<?= e((string)$valorNegociado) ?>"
                min="0.01"
                step="0.01"
                required
            >

        </label>

        <label class="wide">

            Observações

            <textarea
                name="observacoes"
                rows="4"
            ><?= e($observacoes) ?></textarea>

        </label>

        <div class="wide">

            <button class="button" type="submit">
                Registrar atendimento
            </button>

        </div>

    </form>

</main>

</body>

</html>
