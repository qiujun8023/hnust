<?php

namespace Hnust\Utils;

use Hnust\Config;

class Wechat
{
    //缓存
    protected static $cache   = null;
    protected static $secret  = null;
    protected static $baseUrl = null;

    //初始缓存
    protected static function init()
    {
        //实例化数据缓存
        if (is_null(self::$cache)) {
            self::$cache = new Cache('wechat');
        }
        //获取微信Secret
        if (is_null(self::$secret)) {
            self::$secret = Config::getConfig('wechat_secret');
        }
        //获取基地址
        if (is_null(self::$baseUrl)) {
            self::$baseUrl = Config::getConfig('local_base_url');
        }
    }

    protected static function getHttp($method, $post)
    {
        //HTTP请求
        $post['secret'] = self::$secret;
        $post = json_encode($post);
        try {
            $http = new Http(array(
                CURLOPT_URL        => self::$baseUrl . 'wechat/' . $method,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($post)
                ),
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            return array(
                'errcode' => '-1',
                'errmsg'  => $e->getMessage()
            );
        }

        //检查数据
        $result = @json_decode($http->content, true);
        if (empty($result) || !is_array($result)) {
            $result = array(
                'errcode' => '-1',
                'errmsg'  => 'Incorrect Data'
            );
        }
        return $result;
    }

    //获取用户信息
    public static function getUser($uid, $useCache = true)
    {
        self::init();
        $info = self::$cache->get($uid);
        if (!$useCache || empty($info) || !is_array($info)) {
            $info = self::getHttp('getUser', array('uid' => $uid));
            if ('0' != $info['errcode']) {
                return false;
            }
            self::$cache->set($uid, $info, 86400);
        }
        return $info;
    }

    //添加用户
    public static function createUser($user)
    {
        return self::updateUser($user);
    }

    //更新用户
    public static function updateUser($user)
    {
        self::init();
        if (!is_array($user)) {
            $sql = "SELECT `sid` `userid`, `name`,
                            IF(`sex` = '男', 1, 0) `gender`,
                            `phone` `mobile`, `mail` `email`
                    FROM `student` WHERE `sid` = ? LIMIT 1";
            $result = Mysql::execute($sql, array($user));
            if (empty($result)) {
                return false;
            }
            $user = $result[0];
        }
        $result = self::getHttp('updateUser', array('user' => $user));
        return ('0' == $result['errcode']);
    }

    //删除用户
    public static function deleteUser($uid)
    {
        self::init();
        $result = self::getHttp('deleteUser', array('uid' => $uid));
        return ('0' == $result['errcode']);
    }

    //发送消息
    public static function sendMsg($uid, $type, $data)
    {
        //判断用户是否关注微信
        $sql = 'SELECT `status` FROM `weixin` WHERE `uid` = ? LIMIT 1';
        $result = Mysql::execute($sql, array($uid));
        if (empty($result) || (1 != $result[0]['status'])) {
            return false;
        }

        //调用接口发送消息
        self::init();
        $result = self::getHttp('sendMsg', array(
            'to'      => array(
                'touser' => $uid
            ),
            'message' => array(
                'msgtype' => $type,
                $type     => $data,
            )
        ));
        return ('0' == $result['errcode']);
    }

    //发送文本消息
    public static function sendTextMsg($uid, $content)
    {
        $type = 'text';
        $data = array(
            'content' => $content
        );
        return self::sendMsg($uid, $type, $data);
    }
}