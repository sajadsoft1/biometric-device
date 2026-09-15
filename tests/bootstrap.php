<?php

declare(strict_types=1);

$autoloaders = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/../../../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        require __DIR__.'/TestCase.php';

        return;
    }
}

throw new RuntimeException('Composer autoloader was not found.');
