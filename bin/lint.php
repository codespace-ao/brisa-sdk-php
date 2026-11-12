<?php

// php -l em todos os ficheiros PHP do projeto (sem dependências): sai com 1 se algum tiver erros de sintaxe.
declare(strict_types=1);

$root = dirname(__DIR__);
$failed = 0;
$count = 0;
foreach (['src', 'tests', 'bin', 'examples'] as $dir) {
    if (!is_dir("$root/$dir")) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count++;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $failed++;
            fwrite(STDERR, implode(PHP_EOL, $out) . PHP_EOL);
        }
        $out = [];
    }
}
echo "$count ficheiros verificados, $failed com erros" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
