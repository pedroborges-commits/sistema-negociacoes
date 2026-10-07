<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

$usuarioAtual = usuarioAtual();

$erro = '';
$mensagem = '';

$nome = '';
$email = '';
$perfilId = '';

/*
|--------------------------------------------------------------------------
| Carrega os perfis disponíveis
|--------------------------------------------------------------------------
*/

$stmtPerfis = db()->query("
    SELECT id, nome
    FROM perfis
    ORDER BY nome ASC
");

$perfis = $stmtPerfis->fetchAll();

/*
|--------------------------------------------------------------------------
| Processa o formulário
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfilId = $_POST['perfil_id'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validação do nome
    |--------------------------------------------------------------------------
    */

    if ($nome === '') {

        $erro = 'Informe o nome do usuário.';

    } elseif (mb_strlen($nome) > 120) {

        $erro = 'O nome deve ter no máximo 120 caracteres.';

    /*
    |--------------------------------------------------------------------------
    | Validação do e-mail
    |--------------------------------------------------------------------------
    */

    } elseif (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $erro = 'Informe um e-mail válido.';

    } elseif (mb_strlen($email) > 150) {

        $erro = 'O e-mail deve ter no máximo 150 caracteres.';

    /*
    |--------------------------------------------------------------------------
    | Validação do perfil
    |--------------------------------------------------------------------------
    */

    } elseif (!ctype_digit((string)$perfilId)) {

        $erro = 'Selecione um perfil válido.';

    } else {

        $perfilIdInt = (int)$perfilId;

        $stmtPerfil = db()->prepare("
            SELECT id, nome
            FROM perfis
            WHERE id = ?
            LIMIT 1
        ");

        $stmtPerfil->execute([$perfilIdInt]);

        $perfil = $stmtPerfil->fetch();

        if (!$perfil) {

            $erro = 'O perfil selecionado não existe.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validação do e-mail duplicado
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        $stmtEmail = db()->prepare("
            SELECT id
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ");

        $stmtEmail->execute([$email]);

        if ($stmtEmail->fetch()) {

            $erro = 'Já existe um usuário cadastrado com este e-mail.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validação da senha
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        if ($senha === '') {

            $erro = 'Informe uma senha.';

        } elseif (strlen($senha) < 8) {

            $erro = 'A senha deve ter pelo menos 8 caracteres.';

        } elseif (!preg_match('/[A-Za-z]/', $senha)) {

            $erro = 'A senha deve conter pelo menos uma letra.';

        } elseif (!preg_match('/\d/', $senha)) {

            $erro = 'A senha deve conter pelo menos um número.';

        } elseif ($senha !== $confirmarSenha) {

            $erro = 'A confirmação da senha não corresponde.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Criação do usuário
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        $senhaHash = password_hash(
            $senha,
            PASSWORD_DEFAULT
        );

        try {

            $stmt = db()->prepare("
                INSERT INTO usuarios (
                    nome,
                    email,
                    senha,
                    perfil_id,
                    ativo
                )
                VALUES (?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $nome,
                $email,
                $senhaHash,
                $perfilIdInt
            ]);

            $novoUsuarioId = (int)db()->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Auditoria
            |--------------------------------------------------------------------------
            |
            | A senha NÃO é registrada.
            |
            */

            registrarAuditoria(
                'CRIAR',
                'usuarios',
                $novoUsuarioId,
                null,
                [
                    'nome' => $nome,
                    'email' => $email,
                    'perfil_id' => $perfilIdInt,
                    'perfil' => $perfil['nome'],
                    'ativo' => 1
                ]
            );

            $mensagem = 'Usuário criado com sucesso.';

            /*
            |--------------------------------------------------------------------------
            | Limpa o formulário
            |--------------------------------------------------------------------------
            */

            $nome = '';
            $email = '';
            $perfilId = '';

        } catch (PDOException $e) {

            /*
            | Se o banco detectar alguma violação de UNIQUE,
            | mostramos uma mensagem amigável.
            */

            if ((int)$e->errorInfo[1] === 1062) {

                $erro = 'Já existe um usuário cadastrado com este e-mail.';

            } else {

                $erro = 'Não foi possível criar o usuário.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Novo usuário</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<header class="topbar">

    <div>
        <strong>Sistema de Negociações</strong>
    </div>

    <div>

        <span>
            <?= e($usuarioAtual['nome']) ?>
            (<?= e($usuarioAtual['perfil']) ?>)
        </span>

        <a href="index.php">Início</a>

        <a href="usuarios.php">Usuários</a>

        <form
            method="post"
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

    </div>

</header>

<main class="container">

    <h1>Novo usuário</h1>

    <?php if ($mensagem !== ''): ?>

        <div class="success">
            <?= e($mensagem) ?>
        </div>

    <?php elseif ($erro !== ''): ?>

        <div class="error">
            <?= e($erro) ?>
        </div>

    <?php endif; ?>


    <section class="panel">

        <h2>Dados do usuário</h2>

        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >


            <div class="form-group">

                <label for="nome">
                    Nome
                </label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="120"
                    value="<?= e($nome) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="email">
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= e($email) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="perfil_id">
                    Perfil
                </label>

                <select
                    id="perfil_id"
                    name="perfil_id"
                    required
                >

                    <option value="">
                        Selecione um perfil
                    </option>

                    <?php foreach ($perfis as $perfilOpcao): ?>

                        <option
                            value="<?= (int)$perfilOpcao['id'] ?>"
                            <?= (string)$perfilId === (string)$perfilOpcao['id'] ? 'selected' : '' ?>
                        >
                            <?= e($perfilOpcao['nome']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="senha">
                    Senha inicial
                </label>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    required
                >

                <small>
                    A senha deve ter pelo menos 8 caracteres,
                    uma letra e um número.
                </small>

            </div>


            <div class="form-group">

                <label for="confirmar_senha">
                    Confirmar senha
                </label>

                <input
                    type="password"
                    id="confirmar_senha"
                    name="confirmar_senha"
                    required
                >

            </div>


            <button
                type="submit"
                class="button"
            >
                Criar usuário
            </button>

            <a
                href="usuarios.php"
                class="button secondary"
            >
                Cancelar
            </a>

        </form>

    </section>

</main>

</body>

</html>