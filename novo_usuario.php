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

    $perfilIdInt = 0;
    $perfil = null;

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
    | Verificação de e-mail duplicado
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
    | Criação do usuário
    |--------------------------------------------------------------------------
    */

    if ($erro === '') {

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo = db();

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (
                    nome,
                    email,
                    senha,
                    perfil_id,
                    ativo,
                    precisa_trocar_senha
                )
                VALUES (?, ?, ?, ?, 1, 1)
            ");

            $stmt->execute([
                $nome,
                $email,
                $senhaHash,
                $perfilIdInt
            ]);

            $novoUsuarioId = (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Registro na auditoria
            |--------------------------------------------------------------------------
            |
            | A senha nunca é registrada na auditoria.
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
                    'ativo' => 1,
                    'precisa_trocar_senha' => 1
                ]
            );

            $pdo->commit();

            $mensagem = 'Usuário criado com sucesso. Ele deverá trocar a senha no primeiro acesso.';

            $nome = '';
            $email = '';
            $perfilId = '';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Erro ao criar usuário: ' . $e->getMessage());

            if (
                $e instanceof PDOException &&
                isset($e->errorInfo[1]) &&
                (int)$e->errorInfo[1] === 1062
            ) {

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo usuário | Sistema de Negociações</title>
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

            <a href="atendimentos.php" class="sidebar-link">
                <span class="nav-icon">▤</span>
                <span>Atendimentos</span>
            </a>
            <a href="minha_conta.php" class="sidebar-link">
    <span class="nav-icon">⚙</span>
    <span>Minha conta</span>
</a>

            <?php if (in_array($usuarioAtual['perfil'], ['ADMIN', 'SUPERVISOR'], true)): ?>

                <div class="sidebar-section-label">GESTÃO</div>

                <a href="relatorio.php" class="sidebar-link">
                    <span class="nav-icon">▥</span>
                    <span>Relatórios</span>
                </a>

            <?php endif; ?>

            <?php if ($usuarioAtual['perfil'] === 'ADMIN'): ?>

                <a href="usuarios.php" class="sidebar-link active">
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

        <div class="new-user-header">
            <div>
                <h1>Novo usuário</h1>
                <p>Cadastre um novo usuário e defina seu perfil de acesso.</p>
            </div>

            <a href="usuarios.php" class="new-user-back">
                ← Voltar para usuários
            </a>
        </div>

        <?php if ($mensagem !== ''): ?>

            <div class="success">
                <?= e($mensagem) ?>
            </div>

        <?php elseif ($erro !== ''): ?>

            <div class="error">
                <?= e($erro) ?>
            </div>

        <?php endif; ?>

        <section class="new-user-panel">

            <div class="new-user-panel-heading">
                <div class="new-user-icon">♙</div>

                <div>
                    <h2>Dados do usuário</h2>
                    <p>Preencha as informações para cadastrar o novo usuário.</p>
                </div>
            </div>

            <form method="post" class="new-user-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <div class="new-user-fields">

                    <div class="new-user-field">
                        <label for="nome">Nome completo</label>

                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            maxlength="120"
                            value="<?= e($nome) ?>"
                            placeholder="Digite o nome completo"
                            required
                        >
                    </div>

                    <div class="new-user-field">
                        <label for="email">E-mail</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?= e($email) ?>"
                            placeholder="Digite o e-mail"
                            required
                        >
                    </div>

                    <div class="new-user-field">
                        <label for="perfil_id">Perfil de acesso</label>

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

                </div>

                <div class="new-user-password-section">

                    <div class="new-user-section-title">
                        <h3>Senha inicial</h3>
                        <p>Defina uma senha provisória para o primeiro acesso.</p>
                    </div>

                    <div class="new-user-fields">

                        <div class="new-user-field">
                            <label for="senha">Senha inicial</label>

                            <input
                                type="password"
                                id="senha"
                                name="senha"
                                placeholder="Digite a senha inicial"
                                required
                            >

                            <small>
                                Mínimo de 8 caracteres, contendo pelo menos uma letra e um número.
                            </small>
                        </div>

                        <div class="new-user-field">
                            <label for="confirmar_senha">Confirmar senha</label>

                            <input
                                type="password"
                                id="confirmar_senha"
                                name="confirmar_senha"
                                placeholder="Digite novamente a senha"
                                required
                            >
                        </div>

                    </div>

                    <div class="new-user-notice">
                        <span>ⓘ</span>
                        <p>
                            No primeiro acesso, o usuário deverá cadastrar uma nova senha.
                        </p>
                    </div>

                </div>

                <div class="new-user-actions">

                    <a href="usuarios.php" class="button secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="button">
                        Criar usuário
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

</body>
</html>
