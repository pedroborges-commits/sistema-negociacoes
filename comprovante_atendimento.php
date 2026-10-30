
<?php

ob_start();

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/comprovantes.php';

exigirLogin();

$pdo = db();
$u = usuarioAtual();

/*
|--------------------------------------------------------------------------
| Validação do ID
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Atendimento inválido.');
}

/*
|--------------------------------------------------------------------------
| Busca do atendimento
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        usuario_id,
        comprovante
    FROM atendimentos
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$atendimento = $stmt->fetch();

if (!$atendimento || empty($atendimento['comprovante'])) {
    http_response_code(404);
    exit('Comprovante não encontrado.');
}

/*
|--------------------------------------------------------------------------
| Verificação de permissão
|--------------------------------------------------------------------------
|
| OPERADOR:
| Somente comprovantes dos próprios atendimentos.
|
| ADMIN e SUPERVISOR:
| Podem consultar todos os comprovantes.
|
*/

if (
    $u['perfil'] === 'OPERADOR' &&
    (int)$atendimento['usuario_id'] !== (int)$u['id']
) {
    http_response_code(403);
    exit('Você não tem permissão para acessar este comprovante.');
}

/*
|--------------------------------------------------------------------------
| Localização do arquivo
|--------------------------------------------------------------------------
*/

$nomeArquivo = basename($atendimento['comprovante']);

$caminhoArquivo = DIRETORIO_COMPROVANTES
    . DIRECTORY_SEPARATOR
    . $nomeArquivo;

if (!is_file($caminhoArquivo) || !is_readable($caminhoArquivo)) {
    http_response_code(404);
    exit('Arquivo do comprovante não encontrado.');
}

/*
|--------------------------------------------------------------------------
| Validação do tipo real do arquivo
|--------------------------------------------------------------------------
*/

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime = $finfo->file($caminhoArquivo);

if (!isset(TIPOS_COMPROVANTES[$mime])) {
    http_response_code(403);
    exit('Tipo de arquivo não permitido.');
}

$extensao = TIPOS_COMPROVANTES[$mime];

/*
|--------------------------------------------------------------------------
| Envio do arquivo ao navegador
|--------------------------------------------------------------------------
*/

// Remove qualquer saída anterior para evitar corromper o arquivo.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminhoArquivo));

header(
    'Content-Disposition: inline; filename="comprovante.'
    . $extensao
    . '"'
);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

readfile($caminhoArquivo);

exit;
