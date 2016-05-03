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
            self::$con = new \Redis();
            self::$con->connect(Config::CACHE_HOST, Config::CACHE_PORT);
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
    public function add($key, $value, $time = null)
    {
        self::connection();
        $key    = $this->prefix . $key;
        $value  = json_encode($value, JSON_UNESCAPED_UNICODE);
        $result = self::$con->setnx($key, $value);
        if ((true === $result) && (null !== $time)) {
            self::$con->expire($key, $time);
        }
        return $result;
    }

    // 设置
    public function set($key, $value, $time = null)
    {
        self::connection();
        $key   = $this->prefix . $key;
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (null === $time) {
            return self::$con->set($key, $value);
        } else {
            return self::$con->setex($key, $time, $value);
        }
    }

    // 获取
    public function get($key)
    {
        self::connection();
        $key = $this->prefix . $key;
        $result = self::$con->get($key);
        return $result? json_decode($result, true):$result;
    }

    // 删除
    public function delete($key)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->del($key);
    }

    // hash添加
    public function hadd($key, $field, $value)
    {
        $result = $this->hexists($key, $field);
        if ($result) {
            return false;
        }
        return $this->hset($key, $field, $value);
    }

    // hash设置
    public function hset($key, $field, $value)
    {
        self::connection();
        $key   = $this->prefix . $key;
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        return self::$con->hset($key, $field, $value);
    }

    // hash获取
    public function hget($key, $field)
    {
        self::connection();
        $key    = $this->prefix . $key;
        $result = self::$con->hget($key, $field);
        return $result? json_decode($result, true):$result;
    }

    // hash判断
    public function hexists($key, $field)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->hexists($key, $field);
    }

    // hash删除
    public function hdelete($key, $field)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->hdel($key, $field);
    }

    // 集合添加
    public function sadd($key, $member)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->sadd($key, $member);
    }

    // 集合列表
    public function smembers($key)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->smembers($key);
    }

    // 集合判断
    public function sismember($key, $member)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->sismember($key, $member);
    }

    // 集合删除
    public function sdelete($key, $member)
    {
        self::connection();
        $key = $this->prefix . $key;
        return self::$con->srem($key, $member);
    }
}