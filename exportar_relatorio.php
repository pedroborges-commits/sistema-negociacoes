<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

exigirPerfis(['ADMIN', 'SUPERVISOR']);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$mes = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$responsavelId = (int)($_GET['responsavel'] ?? 0);

/*
|--------------------------------------------------------------------------
| Nome do responsável selecionado
|--------------------------------------------------------------------------
*/

$nomeResponsavel = 'Todos';

if ($responsavelId > 0) {
    $stmtResponsavel = $pdo->prepare("
        SELECT nome
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmtResponsavel->execute([$responsavelId]);

    $nomeResponsavel = $stmtResponsavel->fetchColumn() ?: 'Responsável não encontrado';
}

/*
|--------------------------------------------------------------------------
| Consulta
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.data_atendimento,
        a.inscricao,
        a.parcelas_em_atraso,
        a.valor_negociado,
        a.valor_recebido,
        a.data_pagamento,
        a.observacoes,
        u.nome AS responsavel

    FROM atendimentos a

    INNER JOIN usuarios u
        ON u.id = a.usuario_id

    WHERE DATE_FORMAT(a.data_atendimento, '%Y-%m') = ?
";

$params = [$mes];

if ($responsavelId > 0) {
    $sql .= " AND a.usuario_id = ?";
    $params[] = $responsavelId;
}

$sql .= "
    ORDER BY
        a.data_atendimento DESC,
        a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Totais
|--------------------------------------------------------------------------
*/

$quantidade = count($rows);
$totalParcelasAtraso = 0;
$totalNegociado = 0;
$totalRecebido = 0;
$quantidadePagamentos = 0;

foreach ($rows as $row) {
    $totalParcelasAtraso += (int)$row['parcelas_em_atraso'];

    $totalNegociado += (float)$row['valor_negociado'];

    if ($row['valor_recebido'] !== null) {
        $totalRecebido += (float)$row['valor_recebido'];
        $quantidadePagamentos++;
    }
}

/*
|--------------------------------------------------------------------------
| Montagem da planilha
|--------------------------------------------------------------------------
*/

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Relatório mensal');

$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'RELATÓRIO MENSAL DE NEGOCIAÇÕES');

$sheet->mergeCells('A2:H2');
$sheet->setCellValue('A2', 'Mês de referência: ' . date('m/Y', strtotime($mes . '-01')));

$sheet->mergeCells('A3:H3');
$sheet->setCellValue('A3', 'Responsável: ' . $nomeResponsavel);

/*
|--------------------------------------------------------------------------
| Resumo
|--------------------------------------------------------------------------
*/

$sheet->setCellValue('A5', 'RESUMO');
$sheet->setCellValue('A6', 'Atendimentos');
$sheet->setCellValue('B6', $quantidade);

$sheet->setCellValue('A7', 'Parcelas em atraso');
$sheet->setCellValue('B7', $totalParcelasAtraso);

$sheet->setCellValue('A8', 'Total negociado');
$sheet->setCellValue('B8', $totalNegociado);

$sheet->setCellValue('A9', 'Total recebido');
$sheet->setCellValue('B9', $totalRecebido);

$sheet->setCellValue('A10', 'Pagamentos registrados');
$sheet->setCellValue('B10', $quantidadePagamentos);

/*
|--------------------------------------------------------------------------
| Cabeçalho da tabela
|--------------------------------------------------------------------------
*/

$cabecalho = 12;

$colunas = [
    'Data',
    'Inscrição',
    'Parcelas em atraso',
    'Valor negociado',
    'Valor recebido',
    'Data do pagamento',
    'Responsável',
    'Observações'
];

foreach ($colunas as $indice => $titulo) {
    $coluna = chr(65 + $indice);
    $sheet->setCellValue($coluna . $cabecalho, $titulo);
}

/*
|--------------------------------------------------------------------------
| Dados
|--------------------------------------------------------------------------
*/

$linha = $cabecalho + 1;

foreach ($rows as $row) {

    $dataAtendimento = date(
        'd/m/Y',
        strtotime($row['data_atendimento'])
    );

    $dataPagamento = $row['data_pagamento'] !== null
        ? date('d/m/Y', strtotime($row['data_pagamento']))
        : '';

    // Textos são gravados explicitamente como texto.
    // Isso evita que conteúdos iniciados por "=" sejam interpretados como fórmulas.

    $sheet->setCellValueExplicit(
        'A' . $linha,
        $dataAtendimento,
        DataType::TYPE_STRING
    );

    $sheet->setCellValueExplicit(
        'B' . $linha,
        (string)$row['inscricao'],
        DataType::TYPE_STRING
    );

    $sheet->setCellValue('C' . $linha, (int)$row['parcelas_em_atraso']);

    $sheet->setCellValue('D' . $linha, (float)$row['valor_negociado']);

    $sheet->setCellValue(
        'E' . $linha,
        $row['valor_recebido'] !== null
            ? (float)$row['valor_recebido']
            : null
    );

    $sheet->setCellValueExplicit(
        'F' . $linha,
        $dataPagamento,
        DataType::TYPE_STRING
    );

    $sheet->setCellValueExplicit(
        'G' . $linha,
        (string)$row['responsavel'],
        DataType::TYPE_STRING
    );

    $sheet->setCellValueExplicit(
        'H' . $linha,
        (string)($row['observacoes'] ?? ''),
        DataType::TYPE_STRING
    );

    $linha++;
}

/*
|--------------------------------------------------------------------------
| Formatação
|--------------------------------------------------------------------------
*/

$sheet->getStyle('A1:H1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '213DB0'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
]);

$sheet->getStyle('A5:B5')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '213DB0'],
    ],
]);

$sheet->getStyle('A6:A10')->getFont()->setBold(true);

$sheet->getStyle('A12:H12')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '213DB0'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
]);

$ultimaLinha = max($cabecalho, $linha - 1);

if ($ultimaLinha > $cabecalho) {
    $sheet->getStyle('D' . ($cabecalho + 1) . ':E' . $ultimaLinha)
        ->getNumberFormat()
        ->setFormatCode('"R$" #,##0.00');

    $sheet->getStyle('A12:H' . $ultimaLinha)
        ->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->setAutoFilter('A12:H' . $ultimaLinha);
}

$larguras = [
    'A' => 15,
    'B' => 20,
    'C' => 20,
    'D' => 19,
    'E' => 19,
    'F' => 20,
    'G' => 25,
    'H' => 45,
];

foreach ($larguras as $coluna => $largura) {
    $sheet->getColumnDimension($coluna)->setWidth($largura);
}

$sheet->getStyle('A1:H' . $ultimaLinha)
    ->getAlignment()
    ->setVertical(Alignment::VERTICAL_CENTER);

$sheet->freezePane('A13');

/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
*/

$nomeArquivo = 'relatorio-negociacoes-' . $mes . '.xlsx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Cache-Control: max-age=0');
header('X-Content-Type-Options: nosniff');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$spreadsheet->disconnectWorksheets();

exit;
