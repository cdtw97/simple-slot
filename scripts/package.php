<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Only package game runtime and installation material. Credentials live outside the repository.
$root = dirname(__DIR__);
$name = $argv[1] ?? 'simple-slot';
if (!preg_match('/^[a-z0-9-]+$/D', $name)) {
    exit("Use a lowercase archive name containing letters, digits or hyphens.\n");
}
$out = $root . '/dist';
if (!is_dir($out) && !mkdir($out)) {
    throw new RuntimeException('Could not create the distribution directory.');
}
$archive = new ZipArchive();
$path = "$out/$name.zip";
if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Could not create the archive.');
}
$files = [];
foreach (['.htaccess', 'index.php', 'game.php', 'ajax.js', 'style.css'] as $file) {
    $files[$file] = "public/$file";
}
foreach (['src' => ['php'], 'ui' => ['js'], 'assets' => ['svg','webp','png','ttf','woff2','txt'],
          'migrations' => ['sql']] as $dir => $extensions) {
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($entries as $entry) {
        if ($entry->isFile() && !$entry->isLink() && in_array($entry->getExtension(), $extensions, true)) {
            $file = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            $files[$file] = ($dir === 'migrations' ? 'setup/' : 'public/') . $file;
        }
    }
}
$files['README.md'] = 'README.md';
$files['config/example.php'] = 'setup/config.example.php';
foreach (['deployment', 'rules', 'assets', 'simulation'] as $doc) {
    if (is_file("$root/docs/$doc.md")) {
        $files["docs/$doc.md"] = "docs/$doc.md";
    }
}
ksort($files);
foreach ($files as $source => $destination) {
    if (!$archive->addFile("$root/$source", $destination)) {
        throw new RuntimeException("Could not package $source");
    }
}
if (!$archive->close()) {
    throw new RuntimeException('Could not finish the archive.');
}
echo "$path · " . count($files) . " files · SHA-256 " . hash_file('sha256', $path) . PHP_EOL;
