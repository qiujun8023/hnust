<?php

namespace Hnust\Utils;

use Hnust\Config;

class Ip
{
    //缓存
    protected static $cache = null;

    //正则字符串中的IP
    public static function getIp($str)
    {
        $pattern = "((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d))))";
        if (preg_match($pattern, $str, $ip)) {
            return $ip[0];
        } else {
            return '';
        }
    }

    //获取当前IP
    public static function value()
    {
        //获取IP
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '';
        }
        //正则IP
        return self::getIp($ip);
    }

    //获取IP所在地
    public static function location($ip = '')
    {
        $ip = empty($ip)? self::value():$ip;

        //判断本机IP调用
        if ($ip === Config::getConfig('local_in_ip')) {
            return '服务器内网IP';
        } elseif ($ip === Config::getConfig('local_out_ip')) {
            return '服务器外网IP';
        }

        //读取本地缓存
        if (null == self::$cache) {
            self::$cache = new Cache('ip');
        }
        $location = self::$cache->get($ip);

        //读取ipip数据
        if (empty($location)) {
            try {
                $http = new Http(array(
                    CURLOPT_URL     => "http://freeapi.ipip.net/{$ip}",
                    CURLOPT_TIMEOUT => 3,
                ));
                $result = @json_decode($http->content, true);
                if (!is_array($result)) {
                    throw new \Exception($http->content);
                }
                array_shift($result);
                $result = array_filter($result);
                $location = trim(implode(' ', $result));
            } catch (\Exception $e) {
                //pass
            }
        }

        //读取IP138的数据
        if (empty($location)) {
            try {
                $http = new Http(array(
                    CURLOPT_URL     => "http://m.ip138.com/ip.asp?ip={$ip}",
                    CURLOPT_TIMEOUT => 3,
                ));
                $pattern = '/<p class="result">(.*?)：(.*?)<\/p>/';
                preg_match($pattern, $http->content, $result);
                $location = empty($result)? '':$result[2];
            } catch (\Exception $e) {
                //pass
            }
        }

        //设置缓存
        self::$cache->set($ip, $location, empty($location)? 86400:604800);
        return $location;
    }
}