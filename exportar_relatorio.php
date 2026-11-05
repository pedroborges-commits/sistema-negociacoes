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
| Validação de datas
|--------------------------------------------------------------------------
*/

$hoje = date('Y-m-d');
$primeiroDiaMes = date('Y-m-01');

$validarData = static function ($data): bool {
    if (!is_string($data)) {
        return false;
    }

    $dataObj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    return $dataObj !== false
        && $dataObj->format('Y-m-d') === $data;
};

$dataInicio = $_GET['data_inicio'] ?? $primeiroDiaMes;
$dataFim = $_GET['data_fim'] ?? $hoje;

if (!$validarData($dataInicio)) {
    $dataInicio = $primeiroDiaMes;
}

if (!$validarData($dataFim)) {
    $dataFim = $hoje;
}

if ($dataInicio > $dataFim) {
    $dataInicio = $primeiroDiaMes;
    $dataFim = $hoje;
}

/*
|--------------------------------------------------------------------------
| Demais filtros
|--------------------------------------------------------------------------
*/

$indicadoresPermitidos = ['todos', 'atendimentos', 'recebido'];

$indicador = $_GET['indicador'] ?? 'todos';

if (!in_array($indicador, $indicadoresPermitidos, true)) {
    $indicador = 'todos';
}

$responsavelId = max(0, (int)($_GET['responsavel'] ?? 0));

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

    $nomeResponsavel = $stmtResponsavel->fetchColumn()
        ?: 'Responsável não encontrado';
}

/*
|--------------------------------------------------------------------------
| Nome do indicador
|--------------------------------------------------------------------------
*/

$nomeIndicador = [
    'todos' => 'Todos os indicadores',
    'atendimentos' => 'Atendimentos',
    'recebido' => 'Valores recebidos',
][$indicador];

/*
|--------------------------------------------------------------------------
| Filtro por responsável
|--------------------------------------------------------------------------
*/

$filtroResponsavel = '';
$paramsResponsavel = [];

if ($responsavelId > 0) {
    $filtroResponsavel = ' AND a.usuario_id = ? ';
    $paramsResponsavel[] = $responsavelId;
}

/*
|--------------------------------------------------------------------------
| Indicadores de atendimentos
|--------------------------------------------------------------------------
*/

$sqlAtendimentos = "
    SELECT
        COUNT(*) AS quantidade,
        COALESCE(SUM(a.parcelas_em_atraso), 0) AS total_parcelas,
        COALESCE(SUM(a.valor_negociado), 0) AS total_negociado
    FROM atendimentos a
    WHERE
        a.data_atendimento >= ?
        AND a.data_atendimento < DATE_ADD(?, INTERVAL 1 DAY)
        $filtroResponsavel
";

$stmtAtendimentos = $pdo->prepare($sqlAtendimentos);

$stmtAtendimentos->execute(array_merge(
    [$dataInicio, $dataFim],
    $paramsResponsavel
));

$totaisAtendimentos = $stmtAtendimentos->fetch();

$quantidade = (int)$totaisAtendimentos['quantidade'];
$totalParcelasAtraso = (int)$totaisAtendimentos['total_parcelas'];
$totalNegociado = (float)$totaisAtendimentos['total_negociado'];

/*
|--------------------------------------------------------------------------
| Indicadores de recebimentos
|--------------------------------------------------------------------------
*/

$sqlRecebimentos = "
    SELECT
        COUNT(a.valor_recebido) AS quantidade_pagamentos,
        COALESCE(SUM(a.valor_recebido), 0) AS total_recebido
    FROM atendimentos a
    WHERE
        a.data_pagamento IS NOT NULL
        AND a.data_pagamento >= ?
        AND a.data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
        AND a.valor_recebido IS NOT NULL
        $filtroResponsavel
";

$stmtRecebimentos = $pdo->prepare($sqlRecebimentos);

$stmtRecebimentos->execute(array_merge(
    [$dataInicio, $dataFim],
    $paramsResponsavel
));

$totaisRecebimentos = $stmtRecebimentos->fetch();

$quantidadePagamentos = (int)$totaisRecebimentos['quantidade_pagamentos'];
$totalRecebido = (float)$totaisRecebimentos['total_recebido'];

/*
|--------------------------------------------------------------------------
| Consulta dos registros detalhados
|--------------------------------------------------------------------------
*/

$condicaoAtendimento = "
    a.data_atendimento >= ?
    AND a.data_atendimento < DATE_ADD(?, INTERVAL 1 DAY)
";

$condicaoRecebimento = "
    a.data_pagamento IS NOT NULL
    AND a.data_pagamento >= ?
    AND a.data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
";

$params = [];

if ($indicador === 'atendimentos') {

    $condicaoDetalhes = $condicaoAtendimento;
    $params = [$dataInicio, $dataFim];

} elseif ($indicador === 'recebido') {

    $condicaoDetalhes = $condicaoRecebimento;
    $params = [$dataInicio, $dataFim];

} else {

    $condicaoDetalhes = "
        (
            ($condicaoAtendimento)
            OR
            ($condicaoRecebimento)
        )
    ";

    $params = [
        $dataInicio,
        $dataFim,
        $dataInicio,
        $dataFim
    ];
}

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

    WHERE
        $condicaoDetalhes
        $filtroResponsavel

    ORDER BY
        a.data_atendimento DESC,
        a.id DESC
";

$params = array_merge($params, $paramsResponsavel);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Montagem da planilha
|--------------------------------------------------------------------------
*/

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Relatório');

$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'RELATÓRIO DE NEGOCIAÇÕES');

$sheet->mergeCells('A2:H2');
$sheet->setCellValue(
    'A2',
    'Período: '
    . date('d/m/Y', strtotime($dataInicio))
    . ' até '
    . date('d/m/Y', strtotime($dataFim))
);

$sheet->mergeCells('A3:H3');
$sheet->setCellValue('A3', 'Responsável: ' . $nomeResponsavel);

$sheet->mergeCells('A4:H4');
$sheet->setCellValue('A4', 'Indicador: ' . $nomeIndicador);


/*
|--------------------------------------------------------------------------
| Resumo
|--------------------------------------------------------------------------
*/

$sheet->setCellValue('A5', 'RESUMO');

$linhaResumo = 6;
$celulasMoedaResumo = [];

if ($indicador !== 'recebido') {

    $sheet->setCellValue('A' . $linhaResumo, 'Atendimentos');
    $sheet->setCellValue('B' . $linhaResumo, $quantidade);
    $linhaResumo++;

    $sheet->setCellValue('A' . $linhaResumo, 'Parcelas em atraso');
    $sheet->setCellValue('B' . $linhaResumo, $totalParcelasAtraso);
    $linhaResumo++;

    $sheet->setCellValue('A' . $linhaResumo, 'Total negociado');
    $sheet->setCellValue('B' . $linhaResumo, $totalNegociado);
    $celulasMoedaResumo[] = 'B' . $linhaResumo;
    $linhaResumo++;
}

if ($indicador !== 'atendimentos') {

    $sheet->setCellValue('A' . $linhaResumo, 'Total recebido');
    $sheet->setCellValue('B' . $linhaResumo, $totalRecebido);
    $celulasMoedaResumo[] = 'B' . $linhaResumo;
    $linhaResumo++;

    $sheet->setCellValue('A' . $linhaResumo, 'Pagamentos registrados');
    $sheet->setCellValue('B' . $linhaResumo, $quantidadePagamentos);
    $linhaResumo++;
}

/*
|--------------------------------------------------------------------------
| Cabeçalho da tabela
|--------------------------------------------------------------------------
*/

$cabecalho = 12;

if ($indicador === 'atendimentos') {

    $colunas = [
        'Data do atendimento',
        'Inscrição',
        'Parcelas em atraso',
        'Valor negociado',
        'Responsável',
        'Observações'
    ];

} elseif ($indicador === 'recebido') {

    $colunas = [
        'Data do pagamento',
        'Inscrição',
        'Valor recebido',
        'Responsável',
        'Observações'
    ];

} else {

    $colunas = [
        'Data do atendimento',
        'Inscrição',
        'Parcelas em atraso',
        'Valor negociado',
        'Valor recebido',
        'Data do pagamento',
        'Responsável',
        'Observações'
    ];
}

$ultimaColuna = chr(64 + count($colunas));

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

    $colunaAtual = 1;

    if ($indicador !== 'recebido') {

        $sheet->setCellValueExplicit(
            chr(64 + $colunaAtual++) . $linha,
            date('d/m/Y', strtotime($row['data_atendimento'])),
            DataType::TYPE_STRING
        );

        $sheet->setCellValueExplicit(
            chr(64 + $colunaAtual++) . $linha,
            (string)$row['inscricao'],
            DataType::TYPE_STRING
        );

        $sheet->setCellValue(
            chr(64 + $colunaAtual++) . $linha,
            (int)$row['parcelas_em_atraso']
        );

        $sheet->setCellValue(
            chr(64 + $colunaAtual++) . $linha,
            (float)$row['valor_negociado']
        );
    }

    if ($indicador !== 'atendimentos') {

        if ($indicador === 'recebido') {

            $sheet->setCellValueExplicit(
                chr(64 + $colunaAtual++) . $linha,
                (string)$row['data_pagamento'] !== ''
                    ? date('d/m/Y', strtotime($row['data_pagamento']))
                    : '',
                DataType::TYPE_STRING
            );

            $sheet->setCellValueExplicit(
                chr(64 + $colunaAtual++) . $linha,
                (string)$row['inscricao'],
                DataType::TYPE_STRING
            );

            $sheet->setCellValue(
                chr(64 + $colunaAtual++) . $linha,
                (float)$row['valor_recebido']
            );

        } else {

            $sheet->setCellValue(
                chr(64 + $colunaAtual++) . $linha,
                $row['valor_recebido'] !== null
                    ? (float)$row['valor_recebido']
                    : ''
            );

            $sheet->setCellValueExplicit(
                chr(64 + $colunaAtual++) . $linha,
                $row['data_pagamento'] !== null
                    ? date('d/m/Y', strtotime($row['data_pagamento']))
                    : '',
                DataType::TYPE_STRING
            );
        }
    }

    $sheet->setCellValueExplicit(
        chr(64 + $colunaAtual++) . $linha,
        (string)$row['responsavel'],
        DataType::TYPE_STRING
    );

    $sheet->setCellValueExplicit(
        chr(64 + $colunaAtual++) . $linha,
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
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]);

$sheet->getStyle('A5:B5')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '213DB0'],
    ],
]);

$sheet->getStyle('A6:A10')->getFont()->setBold(true);

foreach ($celulasMoedaResumo as $celula) {
    $sheet->getStyle($celula)
        ->getNumberFormat()
        ->setFormatCode('"R$" #,##0.00');
}

$sheet->getStyle('A12:' . $ultimaColuna . '12')->applyFromArray([
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

    if ($indicador === 'atendimentos') {
        $colunasMoeda = ['D'];
    } elseif ($indicador === 'recebido') {
        $colunasMoeda = ['C'];
    } else {
        $colunasMoeda = ['D', 'E'];
    }

    foreach ($colunasMoeda as $coluna) {
        $sheet->getStyle(
            $coluna . ($cabecalho + 1) . ':' . $coluna . $ultimaLinha
        )->getNumberFormat()->setFormatCode('"R$" #,##0.00');
    }

    $sheet->getStyle('A12:' . $ultimaColuna . $ultimaLinha)
        ->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->setAutoFilter('A12:' . $ultimaColuna . $ultimaLinha);
}

/*
|--------------------------------------------------------------------------
| Largura das colunas
|--------------------------------------------------------------------------
*/

if ($indicador === 'atendimentos') {

    $larguras = [
        'A' => 22,
        'B' => 20,
        'C' => 20,
        'D' => 19,
        'E' => 25,
        'F' => 45,
    ];

} elseif ($indicador === 'recebido') {

    $larguras = [
        'A' => 22,
        'B' => 20,
        'C' => 19,
        'D' => 25,
        'E' => 45,
    ];

} else {

    $larguras = [
        'A' => 22,
        'B' => 20,
        'C' => 20,
        'D' => 19,
        'E' => 19,
        'F' => 20,
        'G' => 25,
        'H' => 45,
    ];
}

foreach ($larguras as $coluna => $largura) {
    $sheet->getColumnDimension($coluna)->setWidth($largura);
}

$sheet->getStyle('A1:' . $ultimaColuna . $ultimaLinha)
    ->getAlignment()
    ->setVertical(Alignment::VERTICAL_CENTER);


/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
*/

$nomeArquivo = 'relatorio-negociacoes-'
    . $dataInicio
    . '-a-'
    . $dataFim
    . '.xlsx';

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
