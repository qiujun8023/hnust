<?php

namespace Hnust\Utils;

use Hnust\Config;

class Output
{
    //格式化
    public static function format($msg = null, $code = null, $info = null, $data = null, $output = false)
    {
        //code及msg属性
        if (isset($msg) && isset($data) && is_string($data)) {
            $code = isset($code)? $code:Config::RETURN_CONFIRM;
        } else if (isset($msg)) {
            $code = isset($code)? $code:Config::RETURN_ALERT;
        } else {
            $msg  = '';
            $code = isset($code)? $code:Config::RETURN_NORMAL;
        }

        //info属性
        $get = \Hnust\array_map_recursive('htmlspecialchars', $_GET);
        if (isset($info)) {
            $info = array_merge($get, $info);
        } else {
            $info = $get;
        }
        //data属性
        $data = isset($data)? $data:array();

        //构造数组
        $anser = array(
            'code' => $code,
            'msg'  => $msg,
            'info' => $info,
            'data' => $data
        );

        //输出或返回
        return $output? self::output($anser):$anser;
    }

    //输出
    public static function output($anser)
    {
        exit(json_encode($anser, JSON_UNESCAPED_UNICODE));
    }
}