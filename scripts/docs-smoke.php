<?php

$root = dirname(__DIR__);
$required = [
    'CHANGELOG.md', 'docs/index.md', 'docs/installation.md', 'docs/configuration.md',
    'docs/clients.md', 'docs/reverse-proxy.md', 'docs/tools-and-scopes.md',
    'docs/troubleshooting.md', 'docs/security.md', 'docs/release.md', 'docs/phase-7-release.md',
];
foreach ($required as $path) {
    if (!is_file($root.'/'.$path)) {
        throw new RuntimeException('Required release documentation is missing: '.$path);
    }
}

$version = trim((string) file_get_contents($root.'/version.txt'));
$manifest = json_decode((string) file_get_contents($root.'/module.json'), true, 512, JSON_THROW_ON_ERROR);
$config = (string) file_get_contents($root.'/Config/config.php');
$changelog = (string) file_get_contents($root.'/CHANGELOG.md');
if (!preg_match('/\A\d+\.\d+\.\d+\z/', $version)
    || $version !== ($manifest['version'] ?? null)
    || !str_contains($config, "'server_version' => '".$version."'")
    || !str_contains($changelog, '## '.$version.' ')
) {
    throw new RuntimeException('Release versions are inconsistent.');
}

$markdown = array_merge([$root.'/README.md', $root.'/CHANGELOG.md'], glob($root.'/docs/*.md') ?: []);
foreach ($markdown as $file) {
    $contents = (string) file_get_contents($file);
    if (preg_match('/fsmcp_[A-Za-z0-9_-]{20,}/', $contents)) {
        throw new RuntimeException('Documentation contains a token-like literal: '.$file);
    }
    preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $contents, $matches);
    foreach ($matches[1] as $link) {
        $link = trim($link, '<>');
        if ('' === $link || '#' === $link[0] || preg_match('/\A(?:https?|mailto):/i', $link)) {
            continue;
        }
        $path = rawurldecode(explode('#', $link, 2)[0]);
        if ('' !== $path && !file_exists(dirname($file).'/'.$path)) {
            throw new RuntimeException(sprintf('Broken relative link in %s: %s', substr($file, strlen($root) + 1), $link));
        }
    }
}

fwrite(STDOUT, 'Release documentation and version consistency passed for '.$version.".\n");
