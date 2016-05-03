<?php

namespace Hnust\Utils;

use Hnust\Config;

class Log
{
    public static function urlFormat($url)
    {
        return str_ireplace(array('http', 'https', '<', '>'), '', urldecode($url));
    }

    //推送实时日志
    public static function realtime($log)
    {
        $log = json_encode(array('log' => $log));
        try {
            $http = new Http(array(
                CURLOPT_URL        => Config::getConfig('local_base_url') . 'socket/log',
                CURLOPT_POSTFIELDS => $log,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($log)
                ),
                CURLOPT_TIMEOUT    => 1,
            ));
        } catch (\Exception $e) {
            //pass
        }
    }

    //记录到数据库
    public static function recode($uid, $name, $module, $method, $key, $state)
    {
        $ip       = Ip::value();
        $location = Ip::location($ip);
        $ua       = isset($_SERVER['HTTP_USER_AGENT'])? $_SERVER['HTTP_USER_AGENT']:'';
        $url      = self::urlFormat($_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);

        //写入数据库
        $sql = 'INSERT INTO `logs`(`uid`, `name`, `ip`, `location`, `module`, `method`, `key`, `ua`, `url`, `state`)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $sqlArr = array($uid, $name, $ip, $location, $module, $method, $key, $ua, $url, $state);
        Mysql::execute($sql, $sqlArr);

        //推送实时日志
        self::realtime(array(
            'uid'      => $uid,
            'name'     => $name,
            'ip'       => $ip,
            'location' => $location,
            'module'   => $module,
            'method'   => $method,
            'key'      => $key,
            'ua'       => $ua,
            'url'      => $url,
            'state'    => $state,
            'time'     => date('H:i:s', time())
        ));
    }

    //记录到日志文件
    public static function file($filename, $log)
    {
        $log  = str_replace(array("\n", "\r\n"), ' ', $log);
        $path = Config::BASE_PATH . Config::LOGS_PATH . "/{$filename}.log";
        return error_log(date('Y-m-d H:i:s', time()) . "  {$log}\r\n", '3', $path);
    }
}