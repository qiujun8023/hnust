<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;

Class Score extends \Hnust\Crawler\Score
{
    public $error;

    public function __construct($sid, $name = '', $idcard = '')
    {
        parent::__construct($sid, $name, $idcard);
    }

    public function login()
    {
        if (empty($this->name) || empty($this->idcard)) {
            $sql    = 'SELECT `name`, `idcard` FROM `student` WHERE `sid` = ?';
            $result = Mysql::execute($sql, array($this->sid));
            $this->name   = $result[0]['name'];
            $this->idcard = $result[0]['idcard'];
        }

        return parent::login();
    }


    public function getScore()
    {
        $cache = new Cache('score');
        $cacheData = $cache->get($this->sid);
        if (time() - $cacheData['time'] <= \Hnust\Config::getConfig('score_cache_time')) {
            return $cacheData['data'];
        }
        try {
            $score = parent::getScore();

            //从成绩中获取个人信息
            $sid     = $score['info']['sid'];
            $class   = $score['info']['class'];
            $major   = $score['info']['major'];
            $college = $score['info']['college'];
            $grade   = $class[0] . $class[1] . "级";
            $time    = $score['info']['time'];
            $score   = $score['data'];

            //更新资料表
            $sql = 'UPDATE `student` SET `sid` = ?, `class` = ?, `major` = ?, `college` = ?, `grade` = ? WHERE `sid` = ?';
            Mysql::execute($sql, array($sid, $class, $major, $college, $grade, $this->sid));
            //更新成绩表
            $sql = "INSERT INTO `score`(`sid`, `score`, `time`) VALUES(?, ?, ?)
                    ON DUPLICATE KEY UPDATE `score` = ?, `time` = ?";
            $jsonScore = json_encode($score, JSON_UNESCAPED_UNICODE);
            Mysql::execute($sql, array($sid, $jsonScore, $time, $jsonScore, $time));

            $cache->set($this->sid, array(
                'time' => time(),
                'data' => $score
            ));

        } catch (\Exception $e) {
            if ($e->getCode() !== Config::RETURN_ERROR) {
                throw new \Exception($e->getMessage(), $e->getCode());
            //从数据库中获取成绩缓存
            } elseif (empty($cacheData)) {
                $sql = 'SELECT `score`, `time` FROM `score` WHERE `sid` = ?';
                $result = Mysql::execute($sql, array($this->sid));
                if (!empty($result)) {
                    $cacheData = array(
                        'time' => $result[0]['time'],
                        'data' => json_decode($result[0]['score'], true)
                    );
                }
            }
            //无缓存抛出异常
            if (empty($cacheData)) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
            $time = date('Y-m-d H:i:s', $cacheData['time']);
            $this->error = $e->getMessage() . "\n当前数据更新时间为：" . $time;

            $score = $cacheData['data'];
        }
        return $score;
    }
}