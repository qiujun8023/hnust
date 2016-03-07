<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Cache;

Class Credit extends \Hnust\Crawler\Credit
{
    public $error;

    public function login()
    {
        //获取教务网密码
        $inputPasswd = $this->passwd;
        $passwdClass = new Passwd($this->sid);
        if (empty($this->passwd)) {
            $this->passwd = $passwdClass->jwc();
        }

        $result = parent::login();
        //保存密码
        if ($result && !empty($inputPasswd)) {
            $passwdClass->jwc($inputPasswd);
        }
        return $result;
    }

    //重载获取学分绩点方法
    public function getCredit()
    {
        //读取缓存
        $cache = new Cache('credit');
        $cacheData = $cache->get($this->sid);
        //判断缓存时间
        if (time() - $cacheData['time'] <= Config::getConfig('credit_cache_time')) {
            return $cacheData['data'];
        }
        try {
            //获取最新学分绩点
            $credit = parent::getCredit();
            $cache->set($this->sid, array(
                'time' => time(),
                'data' => $credit
            ));
        } catch (\Exception $e) {
            if (empty($cacheData) || ($e->getCode() !== Config::RETURN_ERROR)) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
            //读取旧记录
            $time = date('Y-m-d H:i:s', $cacheData['time']);
            $this->error = $e->getMessage() . "\n当前数据更新时间为：" . $time;

            $credit = $cacheData['data'];
        }
        return $credit;
    }
}