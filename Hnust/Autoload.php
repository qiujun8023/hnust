<?php

//自动注册
spl_autoload_register(function ($class) {
    $prefix = 'Hnust\\';

    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (0 !== strncmp($prefix, $class, $len)) {
        return;
    }

    $relative_class = substr($class, $len);

    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/Functions.php';