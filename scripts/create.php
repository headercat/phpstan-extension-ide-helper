<?php

declare(strict_types=1);

const MainDir = __DIR__ . '/..';
const MainSrcDir = __DIR__ . '/../src';
const PhpstanDir = __DIR__ . '/../../phpstan';
const PhpstanSrcDir = __DIR__ . '/../../phpstan/src';

function recursivelyScanDir(string $targetDir): array
{
    $output = [];
    foreach (scandir($targetDir) as $file) {
        if (str_starts_with($file, '.')) {
            continue;
        }
        $filePath = $targetDir . '/' . $file;
        if (is_dir($filePath)) {
            foreach (recursivelyScanDir($filePath) as $childFile) {
                $output[] = $file . '/' . $childFile;
            }
            continue;
        }
        $output[] = $file;
    }
    return $output;
}

// 1. Create /repo/src directory.
shell_exec('rm -rf ' . MainSrcDir);
mkdir(MainSrcDir, 0777, true);

// 2. Scan PHP files in /phpstan/src.
$phpstanFiles = recursivelyScanDir(PhpstanSrcDir);
$phpstanFiles = array_filter($phpstanFiles, fn (string $file) => str_ends_with($file, '.php'));

// 3. Loop with phpstan PHP files.
foreach ($phpstanFiles as $file)
{
    // 3-1. Create directory in /repo/src.
    if (!file_exists($dir = MainSrcDir . '/' . dirname($file))) {
        mkdir($dir, 0777, true);
    }

    // 3-2. Get original file content and modify to the proper format.
    $content = file_get_contents(PhpstanSrcDir . '/' . $file);
    $content = preg_replace('/<\?\s+/', '<?php ', $content, 1);
    $content = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', '', $content, 1);
    $content = preg_replace('/(namespace\s+[^;]+;)/', "$1\nreturn;", $content, 1);

    // 3-3. Write modified file to the proper path.
    file_put_contents(MainSrcDir . '/' . $file, $content);
}

// 4. Retrieve phpstan/* dependencies from phpstan.
$raw = file_get_contents(PhpstanDir . '/composer.json');
$composer = json_decode($raw, true);
$requires = [];
foreach ($composer['require'] as $name => $version) {
    if (!str_starts_with($name, 'phpstan/')) {
        continue;
    }
    $requires[$name] = $version;
}

// 5. Write composer.json file.
$composer = [
    'name' => 'headercat/phpstan-extension-ide-helper',
    'description' => 'PHPStan extension IDE helper, provides dummy PHPStan namespace classes and functions.',
    'license' => 'MIT',
    'autoload-dev' => [
        'psr-4' => [
            'PHPStan\\' => 'src/'
        ]
    ],
    'require' => [
        'phpstan/phpstan' => '^' . $argv[1],
        ...$requires,
    ],
];
file_put_contents(
    MainDir . '/composer.json',
    json_encode($composer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);

// 6. Commit changes, create a new tag, and push it.
shell_exec('
    git add .
    git config --global user.email "jiyong.kim@headercat.com"
    git config --global user.name "Ji Yong, Kim"
    git commit -m "deploy: ' . $argv[1] . '"
    git tag ' . $argv[1] . '
    git push -u origin ' . $argv[1] . '
');
