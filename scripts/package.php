<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function esbuild(string $root, array $args): string
{
    $exe = getenv('SIMPLE_SLOT_ESBUILD') ?: 'esbuild';
    $errors = tmpfile();
    $proc = proc_open([$exe, ...$args], [1=>['pipe', 'w'], 2=>$errors], $pipes, $root);
    if (!is_resource($proc)) {
        throw new RuntimeException('Set SIMPLE_SLOT_ESBUILD to the esbuild 0.28.2 executable.');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($proc);
    rewind($errors);
    $message = trim(stream_get_contents($errors));
    fclose($errors);
    if ($code !== 0 || $output === false) {
        throw new RuntimeException("esbuild failed. Check SIMPLE_SLOT_ESBUILD. $message");
    }
    return $output;
}

$root = dirname(__DIR__);
$name = $argv[1] ?? 'simple-slot-release-3';
$out = $argv[2] ?? "$root/dist";
if (!preg_match('/^[a-z0-9-]+$/D', $name)) {
    throw new InvalidArgumentException('Use a lowercase archive name containing letters, digits or hyphens.');
}
if (!is_dir($out) && !mkdir($out, 0777, true) && !is_dir($out)) {
    throw new RuntimeException('Could not create the distribution directory.');
}
if (trim(esbuild($root, ['--version'])) !== '0.28.2') {
    throw new RuntimeException('This release uses esbuild 0.28.2.');
}
$options = ['--minify', '--charset=utf8', '--target=es2022', '--legal-comments=inline'];
$contents = [
    'public/ajax.js'=>esbuild($root, ['ajax.js', '--bundle', '--platform=browser', '--format=esm', ...$options]),
    'public/style.css'=>esbuild($root, ['style.css', ...$options]),
];

// List specific source files so local configuration, fixtures and debugging tools stay private.
$files = ['README.md'=>'README.md', 'config/example.php'=>'setup/config.example.php'];
foreach ([
    '.htaccess', 'index.php', 'game.php',
    'src/Challenge.php', 'src/Collection.php', 'src/Daily.php', 'src/Engine.php',
    'src/GameState.php', 'src/Repository.php', 'src/Rules.php',
    'assets/fruit.svg', 'assets/icons.svg', 'assets/mark.svg',
    'assets/fonts/barlow-condensed-800.ttf', 'assets/fonts/dm-sans-bold.ttf', 'assets/fonts/dm-sans-regular.ttf',
    'assets/fonts/OFL-Barlow.txt', 'assets/fonts/OFL-DM-Sans.txt',
] as $file) {
    $files[$file] = "public/$file";
}
foreach (['001_guest_games.sql', '002_completed_runs.sql', '003_daily.sql'] as $file) {
    $files["migrations/$file"] = "setup/migrations/$file";
}
foreach (['deployment', 'rules', 'assets', 'simulation'] as $doc) {
    $files["docs/$doc.md"] = "docs/$doc.md";
}
foreach ($files as $source => $destination) {
    $bytes = file_get_contents("$root/$source");
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException("Missing or empty release file: $source");
    }
    // Git checkouts may have either newline style. Keep binary fonts byte-exact.
    $contents[$destination] = str_ends_with($source, '.ttf') ? $bytes : str_replace("\r\n", "\n", $bytes);
}
ksort($contents, SORT_STRING);
$path = "$out/$name.zip";
$temp = tempnam($out, 'slot-build-');
try {
    $archive = new ZipArchive();
    if ($archive->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the archive.');
    }
    foreach ($contents as $entry => $bytes) {
        if (!$archive->addFromString($entry, $bytes)
            || !$archive->setCompressionName($entry, ZipArchive::CM_DEFLATE, 9)
            || !$archive->setMtimeName($entry, 946684800)
            || !$archive->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            throw new RuntimeException("Could not package $entry");
        }
    }
    if (!$archive->close() || $archive->open($temp, ZipArchive::CHECKCONS | ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('Archive integrity check failed.');
    }
    if ($archive->numFiles !== count($contents)) {
        throw new RuntimeException('Archive entry count differs from the allowlist.');
    }
    foreach (array_keys($contents) as $i => $entry) {
        if ($archive->getNameIndex($i) !== $entry || $archive->getFromIndex($i) !== $contents[$entry]) {
            throw new RuntimeException("Archive verification failed for $entry");
        }
    }
    $archive->close();
    if (!rename($temp, $path)) {
        throw new RuntimeException('Could not install the verified archive.');
    }
} finally {
    if (is_file($temp)) {
        unlink($temp);
    }
}
echo "$path · " . count($contents) . " verified files · SHA-256 " . hash_file('sha256', $path) . PHP_EOL;
