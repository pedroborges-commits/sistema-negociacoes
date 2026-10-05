<?php
require_once __DIR__.'/config/bootstrap.php';
if(usuarioAtual()){header('Location:index.php');exit;}
$erro='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(login(trim($_POST['email']??''),$_POST['senha']??'')){header('Location:index.php');exit;}
 $erro='E-mail ou senha inválidos.';
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-page"><div class="login-box"><h1>Sistema de Negociações</h1><p>Entre para acessar o painel.</p><?php if($erro):?><div class="alert error"><?=e($erro)?></div><?php endif;?>
<form method="post"><label>E-mail<input type="email" name="email" required></label><label>Senha<input type="password" name="senha" required></label><button class="button full">Entrar</button></form>
<div class="test"><b>Teste:</b><br>admin@local.test / password<br>supervisor@local.test / password<br>operador@local.test / password</div></div></body></html>
