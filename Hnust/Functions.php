<?php

namespace Hnust;

//获取当前周数
function week()
{
    $termBegin = Config::getConfig('term_begin');
    $timeDif = time() - strtotime($termBegin);
    $weeks = $timeDif / 604800 + 1;
    if ($timeDif < 0) {
        return 1;
    } else if ($weeks > 20) {
        return 20;
    } else {
        return (int)$weeks;
    }
}

//随机字符串函数
function randStr($length)
{
    $chars   = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $randStr = '';
    for ($i = 0; $i < $length; $i++) {
        $randStr .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $randStr;
}

//密码加密函数，传入MD5后的密码
function passwdEncrypt($uid, $passwd)
{
    return md5($passwd . $uid);
}

//10进制转26进制
function num2alpha($num)
{
    $alpha = 'ZABCDEFGHIJKLMNOPQRSTUVWXY';
    $result = '';
    while($num) {
        $temp  = $num % 26;
        $num = ($temp == 0) ? (intval($num / 26) - 1) : intval($num / 26);
        $result  = $alpha[$temp].$result;
    }
    return $result;
}

//大小转换
function sizeFormat($bytes)
{
    $s = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $e = floor(log($bytes, 1024));
    if (is_infinite($e)) {
        return '0.00Bytes';
    }
    return number_format($bytes / pow(1024, $e), 2) . $s[$e];
}

//成绩比较
function scoreCompare($a, $b)
{
    $grade = array(
        array('优', '良', '中', '及格', '不及格'),
        array(95.02, 84.02, 74.02, 60.02, 0)
    );
    $a = (int) str_replace($grade[0], $grade[1], $a);
    $b = (int) str_replace($grade[0], $grade[1], $b);
    return $a < $b;
}

//模板处理函数
function templet($message, $context = array())
{
    // 构建一个花括号包含的键名的替换数组
    $replace = array();
    foreach ($context as $key => $val) {
        $replace['{' . $key . '}'] = $val;
    }

    // 替换记录信息中的占位符，最后返回修改后的记录信息
    return strtr($message, $replace);
}

function array_map_recursive($func, $arr)
{
    $out = [];
    foreach ($arr as $k => $x) {
        $out[$k] = is_array($x)? array_map_recursive($func, $x):$func($x);
    }
    return $out;
}

//判断是否HTTPS
function ishttps()
{
    if ($_SERVER['HTTPS'] === 'on') {
        return true;
    }
    return false;
}

//获取输入参数
function input($name, $default = '')
{
    static $_PUT = null;

    //返回值类型
    if (strpos($name, '/')) {
        list($name, $type) = explode('/', $name, 2);
    } else {
        $type = 's';
    }

    //数据来源
    if (strpos($name, '.')) {
        list($method, $name) = explode('.', $name, 2);
    } else {
        $method = 'param';
    }

    switch(strtolower($method)) {
        case 'get'     :
            $input =& $_GET;
            break;
        case 'post'    :
            $input =& $_POST;
            break;
        case 'put'     :
            if(is_null($_PUT)){
                parse_str(file_get_contents('php://input'), $_PUT);
            }
            $input = $_PUT;
            break;
        case 'param'   :
            switch($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input = $_POST;
                    break;
                case 'PUT':
                    if(is_null($_PUT)){
                        parse_str(file_get_contents('php://input'), $_PUT);
                    }
                    $input = $_PUT;
                    break;
                default:
                    $input = $_GET;
            }
            break;
        case 'request' :
            $input =& $_REQUEST;
            break;
        case 'session' :
            $input =& $_SESSION;
            break;
        case 'cookie'  :
            $input =& $_COOKIE;
            break;
        case 'server'  :
            $input =& $_SERVER;
            break;
        case 'globals' :
            $input =& $GLOBALS;
            break;
        default:
            return null;
    }

    if (empty($name)) {
        return $input;
    } elseif (isset($input[$name])) {
        $data = $input[$name];
        if (!empty($type)) {
            switch(strtolower($type)){
                case 'a':
                    $data = (array)$data;
                    break;
                case 'd':
                    $data = (int)$data;
                    break;
                case 'f':
                    $data = (float)$data;
                    break;
                case 'b':
                    $data = (boolean)$data;
                    break;
                case 's':
                default:
                    $data = (string)$data;
            }
        }
    } else {
        $data = isset($default)? $default:null;
    }
    return $data;
}