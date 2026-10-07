<?php
require_once __DIR__ . '/config/bootstrap.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();


/*
 * Busca as negociações
 *
 * OPERADOR:
 * vê apenas suas próprias negociações.
 *
 * SUPERVISOR e ADMIN:
 * veem todas as negociações.
 */

if ($u['perfil'] === 'OPERADOR') {

    $stmt = $pdo->prepare("
        SELECT
            n.id,
            n.data_contato,
            n.valor_original,
            n.desconto,
            n.valor_final,
            n.data_combinada,
            n.data_pagamento,
            n.valor_pago,
            n.status,
            n.descricao,
            c.nome AS cliente_nome,
            u.nome AS usuario_nome
        FROM negociacoes n
        INNER JOIN clientes c ON c.id = n.cliente_id
        INNER JOIN usuarios u ON u.id = n.usuario_id
        WHERE n.usuario_id = ?
        ORDER BY n.id DESC
    ");

    $stmt->execute([$u['id']]);

} else {

    $stmt = $pdo->query("
        SELECT
            n.id,
            n.data_contato,
            n.valor_original,
            n.desconto,
            n.valor_final,
            n.data_combinada,
            n.data_pagamento,
            n.valor_pago,
            n.status,
            n.descricao,
            c.nome AS cliente_nome,
            u.nome AS usuario_nome
        FROM negociacoes n
        INNER JOIN clientes c ON c.id = n.cliente_id
        INNER JOIN usuarios u ON u.id = n.usuario_id
        ORDER BY n.id DESC
    ");
}

$negociacoes = $stmt->fetchAll();
?>

<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Negociações</title>

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


<main class="container">

    <div class="head">

        <div>

            <h1>Negociações</h1>

            <p>
                Consulte as negociações cadastradas no sistema.
            </p>

        </div>

        <div>

            <a href="negociacao.php" class="button">
                + Nova negociação
            </a>

            <a href="index.php">
                ← Voltar
            </a>

        </div>

    </div>


    <div class="panel">

        <?php if (!$negociacoes): ?>

            <p>
                Nenhuma negociação cadastrada.
            </p>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>Cliente</th>

                            <th>Responsável</th>

                            <th>Valor final</th>

                            <th>Data combinada</th>

                            <th>Pagamento</th>

                            <th>Status</th>

                            <th>Ação</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($negociacoes as $n): ?>

                        <tr>

                            <td>
                                <?= e($n['cliente_nome']) ?>
                            </td>

                            <td>
                                <?= e($n['usuario_nome']) ?>
                            </td>

                            <td>
                                <?= dinheiro($n['valor_final']) ?>
                            </td>

                            <td>
                                <?= $n['data_combinada']
                                    ? e($n['data_combinada'])
                                    : '-' ?>
                            </td>

                            <td>

                                <?php if ($n['data_pagamento']): ?>

                                    <?= e($n['data_pagamento']) ?>

                                    <br>

                                    <small>
                                        <?= dinheiro($n['valor_pago']) ?>
                                    </small>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= e($n['status']) ?>

                            </td>

                          <td>

    <!-- Editar aparece para qualquer status -->
    <a
        class="button secondary"
        href="editar_negociacao.php?id=<?=$n['id']?>"
    >
        Editar
    </a>

    <?php if ($n['status'] !== 'PAGO'): ?>

        <a
            class="button"
            href="pagamento.php?id=<?=$n['id']?>"
        >
            Registrar pagamento
        </a>

    <?php else: ?>

        <a
            class="button secondary"
            href="pagamento.php?id=<?=$n['id']?>"
        >
            Ver pagamento
        </a>

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