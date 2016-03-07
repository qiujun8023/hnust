<?php
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

spl_autoload_extensions('.php');
spl_autoload_register(function ($class) {
    require_once __DIR__.'/Qiniu/'.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 6)).'.php';
});

require_once 'Qiniu/functions.php';