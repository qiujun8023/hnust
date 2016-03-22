<?php

namespace Hnust\Utils;

use Hnust\Config;

class Output
{
    //格式化
    public static function format($msg = '', $code = '', $info = array(), $data = array(), $output = false)
    {
        $code = (string) $code;
        if (!empty($msg) && !empty($data) && is_string($data)) {
            $code = strlen($code)? $code:Config::RETURN_CONFIRM;
        } else if (!empty($msg)) {
            $code = strlen($code)? $code:Config::RETURN_ALERT;
        } else {
            $code = strlen($code)? $code:Config::RETURN_NORMAL;
        }

        $get   = \Hnust\array_map_recursive('htmlspecialchars', $_GET);
        $anser = array(
            'code' => $code,
            'msg'  => isset($msg)?  $msg :'',
            'info' => isset($info) && !empty($info)? $info:$get,
            'data' => isset($data) && !empty($data)? $data:array()
        );
        return $output? self::output($anser):$anser;
    }

    //输出
    public static function output($anser)
    {
        exit(json_encode($anser, JSON_UNESCAPED_UNICODE));
    }
}