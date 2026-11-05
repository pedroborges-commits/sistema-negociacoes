
<?php

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/comprovantes.php';

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
    $valorTexto = trim((string)($_POST['valor_recebido'] ?? ''));
    $valorRecebido = (float)$valorTexto;

    $arquivoSalvo = null;
    $nomeArquivo = null;

    /*
    |--------------------------------------------------------------------------
    | Valida os campos
    |--------------------------------------------------------------------------
    */

    if (!validarData($dataPagamento)) {
    $erro = 'Informe uma data de pagamento válida.';
} elseif ($dataPagamento > date('Y-m-d')) {
    $erro = 'A data do pagamento não pode ser posterior à data atual.';
} elseif ($valorRecebido <= 0) {
    $erro = 'Informe um valor recebido válido.';

    } elseif (
        !isset($_FILES['comprovante']) ||
        !is_array($_FILES['comprovante'])
    ) {

        $erro = 'O comprovante de pagamento é obrigatório.';

    } else {

        $arquivo = $_FILES['comprovante'];

        /*
        |--------------------------------------------------------------------------
        | Verifica erros do upload
        |--------------------------------------------------------------------------
        */

        if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {

            $erro = 'Anexe o comprovante de pagamento.';

        } elseif ($arquivo['error'] !== UPLOAD_ERR_OK) {

            $erro = 'Ocorreu um erro no envio do comprovante. Verifique o arquivo e tente novamente.';

        } elseif (
            !is_uploaded_file($arquivo['tmp_name'])
        ) {

            $erro = 'O arquivo enviado não é válido.';

        } elseif (
            $arquivo['size'] <= 0 ||
            $arquivo['size'] > TAMANHO_MAXIMO_COMPROVANTE
        ) {

            $erro = 'O comprovante deve ter no máximo 5 MB.';

        } elseif (
            !is_dir(DIRETORIO_COMPROVANTES) ||
            !is_writable(DIRETORIO_COMPROVANTES)
        ) {

            $erro = 'A pasta de comprovantes não existe ou não possui permissão de gravação.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Verifica o tipo real do arquivo
            |--------------------------------------------------------------------------
            */

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $tipoArquivo = $finfo->file($arquivo['tmp_name']);

            $extensao = TIPOS_COMPROVANTES[$tipoArquivo] ?? null;

            if ($extensao === null) {

                $erro = 'Formato inválido. Envie um arquivo PDF, JPG ou PNG.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Gera um nome aleatório para o arquivo
                |--------------------------------------------------------------------------
                */

                try {

                    $nomeArquivo = bin2hex(random_bytes(16))
                        . '.'
                        . $extensao;

                    $destino = DIRETORIO_COMPROVANTES
                        . DIRECTORY_SEPARATOR
                        . $nomeArquivo;

                    /*
                    |--------------------------------------------------------------------------
                    | Inicia a transação
                    |--------------------------------------------------------------------------
                    */

                    $pdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | Salva o comprovante
                    |--------------------------------------------------------------------------
                    */

                    if (!move_uploaded_file(
                        $arquivo['tmp_name'],
                        $destino
                    )) {
                        throw new RuntimeException(
                            'Não foi possível salvar o comprovante.'
                        );
                    }

                    $arquivoSalvo = $destino;

                    /*
                    |--------------------------------------------------------------------------
                    | Atualiza o pagamento
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE atendimentos
                        SET
                            valor_recebido = ?,
                            data_pagamento = ?,
                            comprovante = ?
                        WHERE id = ?
                          AND valor_recebido IS NULL
                          AND data_pagamento IS NULL
                          AND comprovante IS NULL
                    ");

                    $stmt->execute([
                        $valorRecebido,
                        $dataPagamento,
                        $nomeArquivo,
                        $id
                    ]);

                    if ($stmt->rowCount() !== 1) {

                        $pdo->rollBack();

                        if ($arquivoSalvo !== null && is_file($arquivoSalvo)) {
                            unlink($arquivoSalvo);
                        }

                        $arquivoSalvo = null;

                        $erro = 'Não foi possível registrar o pagamento. O atendimento pode já possuir um pagamento.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Dados para auditoria
                        |--------------------------------------------------------------------------
                        */

                        $dadosNovos = [
                            'valor_recebido' => $valorRecebido,
                            'data_pagamento' => $dataPagamento,
                            'comprovante' => $nomeArquivo
                        ];

                        /*
                        |--------------------------------------------------------------------------
                        | Registra auditoria na mesma transação
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
                        | Confirma o pagamento
                        |--------------------------------------------------------------------------
                        */

                        $pdo->commit();

                        $arquivoSalvo = null;

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

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Remove arquivo caso a operação falhe
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $arquivoSalvo !== null &&
                        is_file($arquivoSalvo)
                    ) {
                        unlink($arquivoSalvo);
                    }

                    error_log(
                        'Erro ao registrar pagamento: ' . $e->getMessage()
                    );

                    $erro = 'Não foi possível registrar o pagamento. Nenhuma alteração foi salva. Tente novamente.';
                }
            }
        }
    }
}

?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar pagamento | Sistema de Negociações</title>
    <link rel="stylesheet" href="/sistema-negociacoes-redesign/assets/style.css?v=2">
</head>

<body>

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
            </a>

            <?php if (in_array($u['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link">
                    <span class="nav-icon">▥</span>
                    <span>Relatórios</span>
                </a>
                <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
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

    <main class="dashboard-main payment-main">

        <div class="payment-header">

            <div>
                <h1>Registrar pagamento</h1>
                <p>Registre o pagamento realizado para este atendimento.</p>
            </div>

            <a href="atendimentos.php" class="payment-back">
                ← Voltar para atendimentos
            </a>

        </div>

        <?php if ($erro): ?>

            <div class="payment-alert payment-alert-error">
                <strong>Não foi possível concluir a operação</strong>
                <p><?= e($erro) ?></p>
            </div>

        <?php endif; ?>

        <?php if ($ok): ?>

            <div class="payment-alert payment-alert-success">
                <strong><?= e($ok) ?></strong>
            </div>

        <?php endif; ?>

        <section class="payment-card">

            <div class="payment-card-heading">
                <div class="payment-card-icon">▤</div>

                <div>
                    <h2>Dados do atendimento</h2>
                    <p>Confira as informações antes de registrar o pagamento.</p>
                </div>
            </div>

            <div class="payment-info-grid">

                <div class="payment-info-item">
                    <span>Data do atendimento</span>
                    <strong><?= e($atendimento['data_atendimento']) ?></strong>
                </div>

                <div class="payment-info-item">
                    <span>Inscrição</span>
                    <strong><?= e($atendimento['inscricao']) ?></strong>
                </div>

                <div class="payment-info-item">
                    <span>Parcelas em atraso</span>
                    <strong><?= e((string)$atendimento['parcelas_em_atraso']) ?></strong>
                </div>

                <div class="payment-info-item">
                    <span>Valor negociado</span>
                    <strong><?= dinheiro($atendimento['valor_negociado']) ?></strong>
                </div>

                <div class="payment-info-item">
                    <span>Operadora responsável</span>
                    <strong><?= e($atendimento['usuario_nome']) ?></strong>
                </div>

            </div>

        </section>

        <?php if (
            $atendimento['valor_recebido'] === null &&
            $atendimento['data_pagamento'] === null
        ): ?>

            <section class="payment-card">

                <div class="payment-card-heading">
                    <div class="payment-card-icon">R$</div>

                    <div>
                        <h2>Dados do pagamento</h2>
                        <p>Informe os dados e anexe o comprovante obrigatório.</p>
                    </div>
                </div>

                <form
                    method="post"
                    enctype="multipart/form-data"
                    class="payment-form"
                >

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

                    <div class="payment-form-grid">

                        <div class="payment-field">
                            <label for="data_pagamento">Data do pagamento</label>

                            <input
                                type="date"
                                type="date"
                                name="data_pagamento"
                                value="<?= e($_POST['data_pagamento'] ?? date('Y-m-d')) ?>"
                                max="<?= date('Y-m-d') ?>"
                                required
                            >
                        </div>

                        <div class="payment-field">
                            <label for="valor_recebido">Valor recebido</label>

                            <input
                                type="number"
                                id="valor_recebido"
                                name="valor_recebido"
                                value="<?= e($_POST['valor_recebido'] ?? '') ?>"
                                min="0.01"
                                step="0.01"
                                placeholder="0,00"
                                required
                            >
                        </div>

                        <div class="payment-field payment-field-full">
                            <label for="comprovante">Comprovante de pagamento</label>

                            <input
                                type="file"
                                id="comprovante"
                                name="comprovante"
                                accept=".pdf,.jpg,.jpeg,.png"
                                required
                            >

                            <small>
                                Formatos permitidos: PDF, JPG e PNG.
                                Tamanho máximo: 5 MB.
                            </small>
                        </div>

                    </div>

                    <div class="payment-notice">
                        <span>ⓘ</span>
                        <p>
                            O comprovante é obrigatório para concluir o registro do pagamento.
                        </p>
                    </div>

                    <div class="payment-actions">

                        <a href="atendimentos.php" class="button secondary">
                            Cancelar
                        </a>

                        <button type="submit" class="button">
                            Registrar pagamento
                        </button>

                    </div>

                </form>

            </section>

        <?php else: ?>

            <section class="payment-card">

                <div class="payment-card-heading">
                    <div class="payment-card-icon">✓</div>

                    <div>
                        <h2>Pagamento registrado</h2>
                        <p>Este atendimento já possui um pagamento registrado.</p>
                    </div>
                </div>

                <div class="payment-info-grid">

                    <div class="payment-info-item">
                        <span>Data do pagamento</span>
                        <strong><?= e($atendimento['data_pagamento']) ?></strong>
                    </div>

                    <div class="payment-info-item">
                        <span>Valor recebido</span>
                        <strong><?= dinheiro($atendimento['valor_recebido']) ?></strong>
                    </div>

                    <div class="payment-info-item">
                        <span>Comprovante</span>
                        <strong>Anexado ao atendimento</strong>
                    </div>

                </div>

            </section>

        <?php endif; ?>

    </main>

</div>

</body>
</html>
