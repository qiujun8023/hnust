<?php

include_once __DIR__ . '/../Hnust/Autoload.php';

use Hnust\Utils\Output;

//全局使用UTF8
header('Content-Type:text/html;charset=UTF-8');
header("X-Powered-By: Tick.Net");

//命名空间
$namespace = '\\Hnust\\Module\\';
$defaultModule = $namespace . 'User';
$forbiddenModule = array('Base', 'Auth');

//获取调用的类与方法
$pathInfo = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$moduleName = ucfirst($pathInfo[0]);
$methodName = lcfirst(empty($pathInfo[1])? $moduleName:$pathInfo[1]);
array_splice($pathInfo, 0, 2);

//判断是否调用基类
if (in_array($moduleName, $forbiddenModule)) {
    Output::output(Output::format('Access Denied.'));

//判断类是否存在
} elseif (class_exists($namespace . $moduleName)) {
    $class = $namespace . $moduleName;

//不存在调用默认模块
} else {
    $class = $defaultModule;
}

$module = new $class($moduleName, $methodName);
$module->run();