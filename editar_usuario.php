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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar usuário | Sistema de Negociações</title>

    <link rel="stylesheet" href="assets/style.css?v=3">
</head>

<body class="dashboard-page">

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

            <a href="atendimentos.php" class="sidebar-link">
                <span class="nav-icon">▤</span>
                <span>Atendimentos</span>
            </a>
            <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
</a>

            <div class="sidebar-section-label">GESTÃO</div>

            <a href="relatorio.php" class="sidebar-link">
                <span class="nav-icon">▥</span>
                <span>Relatórios</span>
            </a>

            <a href="usuarios.php" class="sidebar-link active">
                <span class="nav-icon">♙</span>
                <span>Usuários</span>
            </a>

            <a href="auditoria.php" class="sidebar-link">
                <span class="nav-icon">◷</span>
                <span>Auditoria</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-user">

                <div class="user-avatar">
                    <?= e(mb_strtoupper(mb_substr($usuarioAtual['nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                </div>

                <div class="user-info">
                    <strong><?= e($usuarioAtual['nome']) ?></strong>
                    <span><?= e($usuarioAtual['perfil']) ?></span>
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

    <main class="dashboard-main">

        <div class="dashboard-content">

            <header class="user-edit-header">

                <div>
                    <h1>Editar usuário</h1>
                    <p>
                        Atualize as informações e as permissões do usuário.
                    </p>
                </div>

                <a href="usuarios.php" class="button secondary">
                    Voltar
                </a>

            </header>

            <?php if ($mensagem !== ''): ?>

                <div class="alert success">
                    <?= e($mensagem) ?>
                </div>

            <?php elseif ($erro !== ''): ?>

                <div class="alert error">
                    <?= e($erro) ?>
                </div>

            <?php endif; ?>

            <section class="user-edit-panel">

                <div class="user-edit-panel-heading">

                    <div>
                        <h2>Dados do usuário</h2>
                        <p>
                            Confira os dados antes de salvar as alterações.
                        </p>
                    </div>

                    <?php if ((int)$usuario['ativo'] === 1): ?>

                        <span class="user-edit-status active">
                            Ativo
                        </span>

                    <?php else: ?>

                        <span class="user-edit-status inactive">
                            Inativo
                        </span>

                    <?php endif; ?>

                </div>

                <form method="POST" class="user-edit-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrfToken()) ?>"
                    >

                    <div class="user-edit-field">
                        <label for="nome">Nome completo</label>

                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            maxlength="120"
                            value="<?= e($nome) ?>"
                            placeholder="Digite o nome do usuário"
                            required
                        >
                    </div>

                    <div class="user-edit-field">
                        <label for="email">E-mail</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?= e($email) ?>"
                            placeholder="Digite o e-mail do usuário"
                            required
                        >
                    </div>

                    <div class="user-edit-field">
                        <label for="perfil_id">Perfil de acesso</label>

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

                        <small>
                            O perfil determina quais funcionalidades o usuário poderá acessar.
                        </small>
                    </div>

                    <div class="user-edit-information">

                        <div class="user-edit-information-item">
                            <span class="user-edit-information-label">
                                Status da conta
                            </span>

                            <strong>
                                <?= (int)$usuario['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                            </strong>
                        </div>

                        <div class="user-edit-information-item">
                            <span class="user-edit-information-label">
                                Senha
                            </span>

                            <strong>
                                Não alterada nesta tela
                            </strong>

                            <small>
                                Para redefinir a senha, utilize a opção correspondente na gestão de usuários.
                            </small>
                        </div>

                    </div>

                    <div class="user-edit-actions">

                        <button type="submit" class="button">
                            Salvar alterações
                        </button>

                        <a
                            href="usuarios.php"
                            class="button secondary"
                        >
                            Cancelar
                        </a>

                    </div>

                </form>

            </section>

        </div>

    </main>

</div>

</body>
</html>