<?php

require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();

$erro = '';
$ok = '';

$clientes = $pdo->query("
    SELECT id, nome, documento
    FROM clientes
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    $cid = (int)($_POST['cliente_id'] ?? 0);
    $data = $_POST['data_contato'] ?? date('Y-m-d');

    $orig = (float)($_POST['valor_original'] ?? 0);
    $desc = (float)($_POST['desconto'] ?? 0);

    $final = $orig - $desc;
$combinada = trim($_POST['data_combinada'] ?? '');

if ($combinada === '') {
    $combinada = null;
}

    $status = 'PENDENTE';

    $descricao = trim($_POST['descricao'] ?? '');
    $obs = trim($_POST['observacoes'] ?? '');

 if (!$cid) {

    $erro = 'Selecione um cliente.';

} elseif ($orig <= 0) {

    $erro = 'O valor original deve ser maior que zero.';

} elseif ($desc < 0) {

    $erro = 'O desconto não pode ser negativo.';

} elseif ($desc > $orig) {

    $erro = 'O desconto não pode ser maior que o valor original.';

} elseif ($final < 0) {

    $erro = 'O valor final não pode ser negativo.';

} elseif (!validarData($data)) {
    $erro = 'Informe uma data de contato válida.';
} elseif ($combinada !== null && !validarData($combinada)) {
    $erro = 'Informe uma data combinada válida.';
} elseif (
    $combinada !== null &&
    $combinada < $data
) {
    $erro = 'A data combinada não pode ser anterior à data de contato.';
} else {

    $clienteStmt = $pdo->prepare("
        SELECT id
        FROM clientes
        WHERE id = ?
          AND ativo = 1
        LIMIT 1
    ");

    $clienteStmt->execute([$cid]);

    if (!$clienteStmt->fetch()) {

        $erro = 'O cliente selecionado não existe ou está inativo.';

    } else {

        $s = $pdo->prepare("
            INSERT INTO negociacoes (
                cliente_id,
                usuario_id,
                data_contato,
                valor_original,
                desconto,
                valor_final,
                data_combinada,
                status,
                descricao,
                observacoes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $s->execute([
            $cid,
            $u['id'],
            $data,
            $orig,
            $desc,
            $final,
            $combinada,
            $status,
            $descricao ?: null,
            $obs ?: null
        ]);

        // Recupera o ID da negociação recém-criada
        $negociacaoId = (int)$pdo->lastInsertId();

        // Dados registrados na auditoria
        $dadosNovos = [
            'cliente_id' => $cid,
            'usuario_id' => (int)$u['id'],
            'data_contato' => $data,
            'valor_original' => $orig,
            'desconto' => $desc,
            'valor_final' => $final,
            'data_combinada' => $combinada,
            'status' => $status,
            'descricao' => $descricao ?: null,
            'observacoes' => $obs ?: null
        ];

        registrarAuditoria(
            'CRIAR',
            'negociacoes',
            $negociacaoId,
            null,
            $dadosNovos
        );

        $ok = 'Negociação registrada com sucesso.';
    }
}
    }

?>

<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Nova negociação</title>
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
            <h1>Nova negociação</h1>
            <p>Registre contato, desconto e acordo.</p>
        </div>

        <a href="index.php">← Voltar</a>

    </div>

   <?php if ($erro): ?>

    <div class="alert error">
        <?=e($erro)?>
    </div>

<?php elseif ($ok): ?>

    <div class="alert success">
        <?=e($ok)?>
    </div>

<?php endif; ?>

    <form class="panel form" method="post">
        <input
    type="hidden"
    name="csrf_token"
    value="<?=e(csrfToken())?>"
>
        <label>
            Cliente

            <select name="cliente_id" required>

                <option value="">Selecione</option>

                <?php foreach ($clientes as $c): ?>

                    <option value="<?=$c['id']?>">
                        <?=e($c['nome'])?>
                        <?= $c['documento'] ? ' - ' . e($c['documento']) : '' ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </label>

        <label>
            Data do contato

            <input
                type="date"
                name="data_contato"
                value="<?=date('Y-m-d')?>"
                required
            >
        </label>

        <label>
            Valor original

            <input
                type="number"
                step="0.01"
                min="0"
                name="valor_original"
                required
            >
        </label>

        <label>
            Desconto

            <input
                type="number"
                step="0.01"
                min="0"
                name="desconto"
                value="0"
            >
        </label>

        <label>
            Data combinada

            <input
                type="date"
                name="data_combinada"
            >
        </label>

        <label class="wide">
            Descrição

            <textarea
                name="descricao"
                rows="3"
            ></textarea>
        </label>

        <label class="wide">
            Observações

            <textarea
                name="observacoes"
                rows="3"
            ></textarea>
        </label>

        <div class="wide">
            <button class="button">
                Salvar negociação
            </button>
        </div>

    </form>

</main>

</body>
</html>