<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';
require_once __DIR__.'/auth.php';

function e(?string $v): string { return htmlspecialchars($v ?? '',ENT_QUOTES,'UTF-8'); }
function dinheiro(float|int|string $v): string { return 'R$ '.number_format((float)$v,2,',','.'); }
