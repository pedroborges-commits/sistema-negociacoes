<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

$usuarioAtual = usuarioAtual();

$usuarioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$usuarioId || $usuarioId <= 0) {
    http_response_code(400);
    exit('Usuário inválido.');
}

/*
|--------------------------------------------------------------------------
| Busca o usuário
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        u.id,
        u.nome,
        u.email,
        u.perfil_id,
        p.nome AS perfil,
        u.ativo
    FROM usuarios u
    INNER JOIN perfis p ON p.id = u.perfil_id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->execute([$usuarioId]);

$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}

/*
|--------------------------------------------------------------------------
| Busca os perfis disponíveis
|--------------------------------------------------------------------------
*/

$stmtPerfis = db()->query("
    SELECT id, nome
    FROM perfis
    ORDER BY nome ASC
");

$perfis = $stmtPerfis->fetchAll();

$erro = '';
$mensagem = '';

$nome = $usuario['nome'];
$email = $usuario['email'];
$perfilId = (string)$usuario['perfil_id'];

/*
|--------------------------------------------------------------------------
| Processa a alteração
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validarCsrf();

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfilId = $_POST['perfil_id'] ?? '';

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
    | Impede que o ADMIN altere o próprio perfil
    |--------------------------------------------------------------------------
    |
    | Isso evita que o administrador transforme a própria conta
    | em OPERADOR ou SUPERVISOR e perca acesso administrativo.
    |
    */

    if (
        $erro === '' &&
        (int)$usuario['id'] === (int)$usuarioAtual['id'] &&
        $perfilIdInt !== (int)$usuario['perfil_id']
    ) {

        $erro = 'Você não pode alterar o próprio perfil.';
    }

    /*
    |--------------------------------------------------------------------------
    | Verifica e-mail duplicado
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        $stmtEmail = db()->prepare("
            SELECT id
            FROM usuarios
            WHERE email = ?
              AND id <> ?
            LIMIT 1
        ");

        $stmtEmail->execute([
            $email,
            $usuarioId
        ]);

        if ($stmtEmail->fetch()) {

            $erro = 'Já existe outro usuário cadastrado com este e-mail.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Atualiza o usuário
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        $dadosAnteriores = [
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'perfil_id' => (int)$usuario['perfil_id'],
            'perfil' => $usuario['perfil'],
            'ativo' => (int)$usuario['ativo']
        ];

        try {

            $stmt = db()->prepare("
                UPDATE usuarios
                SET
                    nome = ?,
                    email = ?,
                    perfil_id = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $nome,
                $email,
                $perfilIdInt,
                $usuarioId
            ]);

            /*
            |--------------------------------------------------------------------------
            | Dados novos
            |--------------------------------------------------------------------------
            */

            $dadosNovos = [
                'nome' => $nome,
                'email' => $email,
                'perfil_id' => $perfilIdInt,
                'perfil' => $perfil['nome'],
                'ativo' => (int)$usuario['ativo']
            ];

            /*
            |--------------------------------------------------------------------------
            | Auditoria
            |--------------------------------------------------------------------------
            */

            registrarAuditoria(
                'EDITAR',
                'usuarios',
                $usuarioId,
                $dadosAnteriores,
                $dadosNovos
            );

            /*
            |--------------------------------------------------------------------------
            | Atualiza os dados exibidos na página
            |--------------------------------------------------------------------------
            */

            $usuario['nome'] = $nome;
            $usuario['email'] = $email;
            $usuario['perfil_id'] = $perfilIdInt;
            $usuario['perfil'] = $perfil['nome'];

            $mensagem = 'Usuário atualizado com sucesso.';

        } catch (PDOException $e) {

            if ((int)$e->errorInfo[1] === 1062) {

                $erro = 'Já existe outro usuário cadastrado com este e-mail.';

            } else {

                $erro = 'Não foi possível atualizar o usuário.';
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

    <title>Editar usuário</title>

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

    <h1>Editar usuário</h1>

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


            <p>
                <strong>Status:</strong>

                <?php if ((int)$usuario['ativo'] === 1): ?>
                    Ativo
                <?php else: ?>
                    Inativo
                <?php endif; ?>
            </p>


            <p>
                <strong>Senha:</strong>
                A senha não é alterada nesta tela.
            </p>


            <button
                type="submit"
                class="button"
            >
                Salvar alterações
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