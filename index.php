<?php

require_once __DIR__.'/config/bootstrap.php';

exigirLogin();

$u = usuarioAtual();
$pdo = db();

// Atualiza automaticamente as negociações que passaram da data combinada
$pdo->exec("
    UPDATE negociacoes
    SET status = 'VENCIDO'
    WHERE status = 'PENDENTE'
      AND data_combinada IS NOT NULL
      AND data_combinada < CURDATE()
");
$total=(int)$pdo->query("
    SELECT COUNT(*)
    FROM negociacoes
")->fetchColumn();

$pend=(int)$pdo->query("
    SELECT COUNT(*)
    FROM negociacoes
    WHERE status='PENDENTE'
")->fetchColumn();

$pagos=(int)$pdo->query("
    SELECT COUNT(*)
    FROM negociacoes
    WHERE status='PAGO'
")->fetchColumn();
$vencidos=(int)$pdo->query("
    SELECT COUNT(*)
    FROM negociacoes
    WHERE status='VENCIDO'
")->fetchColumn();

$totalBruto=$pdo->query("
    SELECT COALESCE(SUM(valor_original),0)
    FROM negociacoes
")->fetchColumn();

$totalDescontos=$pdo->query("
    SELECT COALESCE(SUM(desconto),0)
    FROM negociacoes
")->fetchColumn();

$totalNegociado=$pdo->query("
    SELECT COALESCE(SUM(valor_final),0)
    FROM negociacoes
")->fetchColumn();

$totalRecebido=$pdo->query("
    SELECT COALESCE(SUM(valor_pago),0)
    FROM negociacoes
    WHERE status='PAGO'
")->fetchColumn();

$totalPendente=$pdo->query("
    SELECT COALESCE(SUM(valor_final),0)
    FROM negociacoes
    WHERE status IN ('PENDENTE', 'VENCIDO')
")->fetchColumn();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistema de Negociações</title><link rel="stylesheet" href="assets/style.css"></head>
<body><header class="topbar"><strong>Sistema de Negociações</strong><span><?=e($u['nome'])?> · <?=e($u['perfil'])?> · <a href="logout.php">Sair</a></span></header>
<main class="container"><div class="head"><div><h1>Painel</h1><p>Negociações centralizadas em um único sistema.</p></div><a class="button" href="negociacao.php">+ Nova negociação</a></div>
<section class="cards">

  <section class="cards">

    <div class="card">
        <span>Total de negociações</span>
        <b><?=$total?></b>
    </div>

    <div class="card">
        <span>Pendentes</span>
        <b><?=$pend?></b>
    </div>

    <div class="card">
        <span>Vencidos</span>
        <b><?=$vencidos?></b>
    </div>

    <div class="card">
        <span>Pagas</span>
        <b><?=$pagos?></b>
    </div>

    <div class="card">
        <span>Valor bruto negociado</span>
        <b><?=dinheiro($totalBruto)?></b>
    </div>

    <div class="card">
        <span>Descontos aplicados</span>
        <b><?=dinheiro($totalDescontos)?></b>
    </div>

    <div class="card">
        <span>Total negociado</span>
        <b><?=dinheiro($totalNegociado)?></b>
    </div>

    <div class="card">
        <span>Total recebido</span>
        <b><?=dinheiro($totalRecebido)?></b>
    </div>

    <div class="card">
        <span>Total pendente</span>
        <b><?=dinheiro($totalPendente)?></b>
    </div>

</section>

</section><section class="panel"><h2>Ações rápidas</h2><a class="button secondary" href="clientes.php">Clientes</a> <a class="button secondary" href="negociacao.php">Registrar negociação</a> <?php if(in_array($u['perfil'],['ADMIN','SUPERVISOR'],true)): ?><a class="button secondary" href="relatorio.php">Relatório mensal</a><?php endif;?>  <a href="negociacoes.php" class="button">
        Ver negociações
    </a>
 <?php if ($u['perfil'] === 'ADMIN'): ?>

        <a href="auditoria.php" class="button secondary">
            Auditoria
        </a>

    <?php endif; ?></section></main></body></html>
