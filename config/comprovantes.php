<?php

declare(strict_types=1);

const DIRETORIO_COMPROVANTES = 'C:\\xampp82\\comprovantes-negociacoes';

const TAMANHO_MAXIMO_COMPROVANTE = 5 * 1024 * 1024;

const TIPOS_COMPROVANTES = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];