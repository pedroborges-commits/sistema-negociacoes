<?php
require_once __DIR__.'/config/bootstrap.php'; exigirLogin(); $pdo=db(); $erro=''; $ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $nome=trim($_POST['nome']??'');$doc=trim($_POST['documento']??'')?:null;$tel=trim($_POST['telefone']??'')?:null;$email=trim($_POST['email']??'')?:null;
 if(!$nome)$erro='Informe o nome.'; else try{$s=$pdo->prepare("INSERT INTO clientes(nome,documento,telefone,email) VALUES(?,?,?,?)");$s->execute([$nome,$doc,$tel,$email]);$ok='Cliente cadastrado.';}catch(PDOException $e){$erro='Não foi possível cadastrar. O documento pode já existir.';}
}
$clientes=$pdo->query("SELECT nome,documento,telefone,email FROM clientes WHERE ativo=1 ORDER BY nome")->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clientes</title><link rel="stylesheet" href="assets/style.css"></head>
<body><header class="topbar"><strong>Sistema de Negociações</strong><span><?=e(usuarioAtual()['nome'])?> · <a href="logout.php">Sair</a></span></header><main class="container"><div class="head"><div><h1>Clientes</h1><p>Cadastre os clientes.</p></div><a href="index.php">← Voltar</a></div>
<?php if($erro):?><div class="alert error"><?=e($erro)?></div><?php endif;?><?php if($ok):?><div class="alert success"><?=e($ok)?></div><?php endif;?>
<form class="panel form" method="post"><label>Nome<input name="nome" required></label><label>Documento<input name="documento"></label><label>Telefone<input name="telefone"></label><label>E-mail<input type="email" name="email"></label><div class="wide"><button class="button">Cadastrar cliente</button></div></form>
<section class="panel"><h2>Clientes cadastrados</h2><div class="table"><table><tr><th>Nome</th><th>Documento</th><th>Telefone</th><th>E-mail</th></tr><?php foreach($clientes as $c):?><tr><td><?=e($c['nome'])?></td><td><?=e($c['documento']?:'-')?></td><td><?=e($c['telefone']?:'-')?></td><td><?=e($c['email']?:'-')?></td></tr><?php endforeach;?></table></div></section></main></body></html>
