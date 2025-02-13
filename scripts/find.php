<?php

declare(strict_types=1);

const MainDir = __DIR__ . '/..';
const PhpstanDir = __DIR__ . '/../../phpstan';
const MinimumTag = '1.0.0';

// 1. Retrieve all main tags.
$output = shell_exec('cd ' . MainDir . ' && git tag') ?? '';
$mainTags = array_map(fn (string $tag) => trim($tag), explode("\n", trim($output)));
sort($mainTags);

// 2. Retrieve all phpstan tags.
$output = shell_exec('cd ' . PhpstanDir . ' && git tag') ?? '';
$phpstanTags = array_map(fn (string $tag) => trim($tag), explode("\n", trim($output)));
$phpstanTags = array_filter($phpstanTags, fn (string $tag) => version_compare($tag, MinimumTag) >= 0);
sort($phpstanTags);

// 3. Get the first uncreated phpstan tag.
$uncreated = 'none';
foreach ($phpstanTags as $tag) {
    if (!in_array($tag, $mainTags)) {
        $uncreated = $tag;
        break;
    }
}

// 4. Return output.
shell_exec('echo "tag=' . $uncreated . '" >> $GITHUB_OUTPUT');
exit(0);
