<?php
declare(strict_types=1);

/**
 * CI static check without a Magento install: every PHP/PHTML file must parse,
 * and every XML file must be well-formed.
 *
 * Usage: php Test/static-check.php
 */
$root = dirname(__DIR__);
$failures = 0;
$checked = 0;

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) || str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $ext = strtolower($file->getExtension());

    if ($ext === 'php' || $ext === 'phtml') {
        $checked++;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $failures++;
            fwrite(STDERR, implode(PHP_EOL, $out) . PHP_EOL);
        }
        $out = [];
    } elseif ($ext === 'xml' || str_ends_with($path, '.xml.dist')) {
        $checked++;
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($path)) {
            $failures++;
            foreach (libxml_get_errors() as $error) {
                fwrite(STDERR, sprintf('%s:%d %s', $path, $error->line, trim($error->message)) . PHP_EOL);
            }
        }
        libxml_clear_errors();
    }
}

printf("Checked %d files, %d problem(s).\n", $checked, $failures);
exit($failures > 0 ? 1 : 0);
