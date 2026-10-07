<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

function usuarioAtual(): ?array { return $_SESSION['usuario'] ?? null; }

function exigirLogin(): void {
    if (!usuarioAtual()) { header('Location: login.php'); exit; }
}

function exigirPerfis(array $perfis): void {
    exigirLogin();
    if (!in_array(usuarioAtual()['perfil'], $perfis, true)) {
        http_response_code(403); exit('Acesso negado.');
    }
}

function login(string $email, string $senha): bool {
    $stmt = db()->prepare(
        'SELECT u.id,u.nome,u.email,u.senha,p.nome AS perfil
         FROM usuarios u INNER JOIN perfis p ON p.id=u.perfil_id
         WHERE u.email=? AND u.ativo=1 LIMIT 1'
    );
    $stmt->execute([$email]);
    $u=$stmt->fetch();
    if (!$u || !password_verify($senha,$u['senha'])) return false;
    session_regenerate_id(true);
    unset($u['senha']);
    $_SESSION['usuario']=$u;
    return true;
}

function sair(): void {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $p=session_get_cookie_params();
        setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
    header('Location: login.php'); exit;
}

function podeAcessarNegociacao(array $negociacao): bool {
    $usuario = usuarioAtual();

    if (!$usuario) {
        return false;
    }

    // Supervisor e Admin podem acessar qualquer negociação
    if (in_array($usuario['perfil'], ['SUPERVISOR', 'ADMIN'], true)) {
        return true;
    }

    // Operador só pode acessar suas próprias negociações
    return (int)$negociacao['usuario_id'] === (int)$usuario['id'];
}
function registrarAuditoria(
    string $acao,
    string $tabela,
    int $registroId,
    ?array $dadosAnteriores = null,
    ?array $dadosNovos = null
): void {
    $usuario = usuarioAtual();

    $stmt = db()->prepare("
        INSERT INTO auditoria (
            usuario_id,
            acao,
            tabela_afetada,
            registro_id,
            dados_anteriores,
            dados_novos
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $usuario['id'] ?? null,
        $acao,
        $tabela,
        $registroId,
        $dadosAnteriores !== null
            ? json_encode($dadosAnteriores, JSON_UNESCAPED_UNICODE)
            : null,
        $dadosNovos !== null
            ? json_encode($dadosNovos, JSON_UNESCAPED_UNICODE)
            : null
    ]);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validarCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }
}
function validarCpf(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    // Rejeita CPFs com todos os dígitos iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Primeiro dígito verificador
    $soma = 0;

    for ($i = 0; $i < 9; $i++) {
        $soma += (int)$cpf[$i] * (10 - $i);
    }

    $resto = $soma % 11;
    $digito1 = $resto < 2 ? 0 : 11 - $resto;

    if ((int)$cpf[9] !== $digito1) {
        return false;
    }

    // Segundo dígito verificador
    $soma = 0;

    for ($i = 0; $i < 10; $i++) {
        $soma += (int)$cpf[$i] * (11 - $i);
    }

    $resto = $soma % 11;
    $digito2 = $resto < 2 ? 0 : 11 - $resto;

    if ((int)$cpf[10] !== $digito2) {
        return false;
    }

    return true;
}
function validarData(string $data): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $data);

    return $d !== false
        && $d->format('Y-m-d') === $data;
}

function buscarSenhaUsuario(int $usuarioId): string
{
    $stmt = db()->prepare(
        'SELECT senha FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1'
    );

    $stmt->execute([$usuarioId]);

    $senha = $stmt->fetchColumn();

    return is_string($senha) ? $senha : '';
}