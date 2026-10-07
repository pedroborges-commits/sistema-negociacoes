<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

exigirPerfis(['ADMIN']);

$usuarioAtual = usuarioAtual();

$stmt = db()->query("
    SELECT
        u.id,
        u.nome,
        u.email,
        p.nome AS perfil,
        u.ativo,
        u.criado_em,
        u.atualizado_em
    FROM usuarios u
    INNER JOIN perfis p ON p.id = u.perfil_id
    ORDER BY u.nome ASC
");

$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gerenciamento de usuários</title>

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

    <h1>Gerenciamento de usuários</h1>
    <a
    href="novo_usuario.php"
    class="button"
>
    + Novo usuário
</a>

    <section class="panel">

        <h2>Usuários cadastrados</h2>

        <?php if (count($usuarios) === 0): ?>

            <p>Nenhum usuário cadastrado.</p>

        <?php else: ?>

            <div style="overflow-x:auto;">

               <table>

    <thead>

        <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Perfil</th>
            <th>Status</th>
            <th>Criado em</th>
            <th>Ações</th>
        </tr>

    </thead>

    <tbody>

        <?php foreach ($usuarios as $usuario): ?>

            <tr>

                <td>
                    <?= e($usuario['nome']) ?>
                </td>

                <td>
                    <?= e($usuario['email']) ?>
                </td>

                <td>
                    <?= e($usuario['perfil']) ?>
                </td>

                <td>
                    <?php if ((int)$usuario['ativo'] === 1): ?>

                        Ativo

                    <?php else: ?>

                        Inativo

                    <?php endif; ?>
                </td>

                <td>
                    <?= e($usuario['criado_em']) ?>
                </td>

                <td>

    <a
        href="editar_usuario.php?id=<?= (int)$usuario['id'] ?>"
        class="button secondary"
    >
        Editar
    </a>

    <?php if ((int)$usuario['id'] !== (int)$usuarioAtual['id']): ?>

        <a
            href="redefinir_senha_usuario.php?id=<?= (int)$usuario['id'] ?>"
            class="button secondary"
        >
            Redefinir senha
        </a>

        <form
            method="POST"
            action="alternar_status_usuario.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$usuario['id'] ?>"
            >

            <button type="submit">
                <?= (int)$usuario['ativo'] === 1
                    ? 'Desativar'
                    : 'Ativar'
                ?>
            </button>

        </form>

    <?php endif; ?>

</td>

            </tr>

        <?php endforeach; ?>

    </tbody>

</table>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>

</html>