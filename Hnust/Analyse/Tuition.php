<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Cache;

Class Tuition extends \Hnust\Crawler\Tuition
{
    public $error;

    public function login()
    {
        //获取教务网密码
        $inputPasswd = $this->passwd;
        $passwdClass = new Passwd($this->sid);
        if (empty($this->passwd)) {
            $this->passwd = $passwdClass->cwc();
        }

        $result = parent::login();
        //保存密码
        if ($result && !empty($inputPasswd)) {
            $passwdClass->cwc($inputPasswd);
        }
        return $result;
    }

    //重载获取学分方法，用于读取缓存
    public function getTuition()
    {
        $cache = new Cache('tuition');
        //$cacheData = $cache->get($this->sid);
        if (time() - $cacheData['time'] <= Config::getConfig('tuition_cache_time')) {
            return $cacheData['data'];
        }
        try {
            $tuition = parent::getTuition();
            $cache->set($this->sid, array(
                'time' => time(),
                'data' => $tuition
            ));
        } catch (\Exception $e) {
            if (empty($cacheData) || ($e->getCode() !== Config::RETURN_ERROR)) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
            $time = date('Y-m-d H:i:s', $cacheData['time']);
            $this->error = $e->getMessage() . "\n当前数据更新时间为：" . $time;

            $tuition = $cacheData['data'];
        }
        return $tuition;
    }
}