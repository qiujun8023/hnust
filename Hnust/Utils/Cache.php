<?php

namespace Hnust\Utils;

use Hnust\Config;

class Cache
{
    // 前缀
    protected $prefix = '';
    // 连接
    public static $con = null;

    // 静态连接，减少连接数
    protected static function connection()
    {
        if (null === self::$con) {
            self::$con = new \Memcached();
            self::$con->addServer(Config::CACHE_HOST, Config::CACHE_PORT);
        }
        return self::$con;
    }

    // 添加前缀
    public function __construct($prefix = '')
    {
        $this->prefix = $prefix;
        if (!empty($this->prefix)) {
            $this->prefix .= '_';
        }
    }

    // 添加
    public function add($key, $value, $time = 0)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->add($key, $value, $time);
    }

    // 设置
    public function set($key, $value, $time = 0)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->set($key, $value, $time);
    }

    // 获取
    public function get($key)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->get($key);
    }

    // 删除
    public function delete($key)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->delete($key);
    }
}