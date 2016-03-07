<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Cache;

Class Exam extends \Hnust\Crawler\Exam
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

    //去掉过期考试
    public function filter($exam)
    {
        for ($i = 0; $i < count($exam); $i++) {
            if ((strtotime($exam[$i]['end']) + 1296000) < time()) {
                array_splice($exam, $i--, 1);
            }
        }
        return $exam;
    }

    //重载获取考试方法，判断是否读取缓存
    public function getExam()
    {
        $cache = new Cache('exam');
        $cacheData = $cache->get($this->sid);
        if (time() - $cacheData['time'] <= Config::getConfig('exam_cache_time')) {
            return $this->filter($cacheData['data']);
        }
        try {
            $exam = parent::getExam();
            $cache->set($this->sid, array(
                'time' => time(),
                'data' => $exam
            ));
        } catch (\Exception $e) {
            if (empty($cacheData) || ($e->getCode() !== Config::RETURN_ERROR)) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
            $time = date('Y-m-d H:i:s', $cacheData['time']);
            $this->error = $e->getMessage() . "\n当前数据更新时间为：" . $time;

            $exam = $cacheData['data'];
        }

        return $this->filter($exam);
    }
}