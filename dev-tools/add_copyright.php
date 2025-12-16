<?php

$targetDir = __DIR__ . '/../app';
$licenseText = <<<'EOT'

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
EOT;

// Ensure target directory exists
if (!is_dir($targetDir)) {
    die("Directory not found: $targetDir\n");
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$count = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);

        // Skip if already has copyright
        if (str_contains($content, 'Copyright (c) 2025 Andrés Felipe Martínez González')) {
            continue;
        }

        // Only run on files starting with <?php
        if (!str_starts_with($content, '<?php')) {
            continue;
        }

        // Smart insertion: Insert after <?php and potential strict_types
        // But for safety and standard PSR, we usually place it immediately after <?php
        // We will insert it after the opening tag.

        $newContent = preg_replace('/^<\?php(\s*)/', "<?php\n" . $licenseText . "\n", $content, 1);

        if ($newContent !== $content && $newContent !== null) {
            file_put_contents($path, $newContent);
            echo "Updated: " . $file->getFilename() . "\n";
            $count++;
        }
    }
}

echo "Done. Updated $count files.\n";
