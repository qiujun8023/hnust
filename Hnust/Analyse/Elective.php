<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;

Class Elective extends \Hnust\Crawler\Elective
{
    protected $term;

    public function __construct($sid, $passwd = '')
    {
        $this->term = Config::getConfig('current_term');
        parent::__construct($sid, $passwd);
    }

    public function login()
    {
        if ($this->logined) {
            return true;
        }
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

    //自动补全
    public function complet($key)
    {
        $key = "%{$key}%";
        $sql = "SELECT `key` FROM (
                  SELECT DISTINCT `course` `key`, `school`, `term`
                   FROM `elective_list` WHERE `course` like ?
                  UNION SELECT DISTINCT `place` `key`, `school`, `term`
                   FROM `elective_list` WHERE `place` like ?
                  UNION SELECT DISTINCT `teacher` `key`, `school`, `term`
                   FROM `elective_list` WHERE `teacher` like ?
                  UNION SELECT DISTINCT `time` `key`, `school`, `term`
                   FROM `elective_list` WHERE `time` like ?
                ) AS `a` WHERE `school` = ? AND `term` = ? ORDER BY `key` ASC LIMIT 6";
        $sqlArr  = array($key, $key, $key, $key, $this->school, $this->term);
        if (!($result = Mysql::execute($sql, $sqlArr))) {
            return array();
        }

        $keys = array();
        foreach ($result as $item) {
            if (!empty($item['key'])) {
                $keys[] = $item['key'];
            }
        }
        return $keys;
    }

    //获取搜索列表
    public function search($key, $page = 1)
    {
        $key = "%{$key}%";
        $sql = "SELECT `a`.`id`, `a`.`course`, `a`.`credit`, `a`.`total`,
                  `a`.`remain`, `a`.`teacher`, `a`.`time`, `a`.`place`, `a`.`url`,
                  IF(`b`.`college` IS NULL, '1' , '0.5') opacity,
                  IF(`c`.`rate` IS NULL, -1 , `c`.`rate`) rate
                FROM `elective_list` `a`
                LEFT JOIN `student` `b` ON `a`.`college` = `b`.`college` AND `b`.`sid` = ?
                LEFT JOIN `failRate` `c` ON `a`.`course` = `c`.`course` AND `a`.`school` = `c`.`name`
                WHERE (`a`.`course` LIKE ? OR `a`.`teacher` LIKE ? OR `a`.`time` LIKE ? OR `a`.`place` LIKE ?)
                  AND `a`.`school` = ? AND `a`.`term` = ? ORDER BY `c`.`rate` ASC";
        $sqlArr = array($this->sid, $key, $key, $key, $key, $this->school, $this->term);
        return Mysql::paging($sql, $sqlArr, $page, 20);
    }

    //获取教务网课程列表
    public function getList($term = null)
    {
        if (empty($term)) {
            $term = $this->term;
        }

        //随机登陆
        $passwdClass = new Passwd($this->sid);
        $students = $passwdClass->randJwc();
        foreach ($students as $student) {
            $this->sid = $student['sid'];
            $this->passwd = $student['jwc'];
            try {
                parent::login();
            } catch (\Exception $e) {
                if (Config::RETURN_NEED_PASSWORD === $e->getCode()) {
                    continue;
                }
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }

        //获取列表
        $list = parent::getList($term);

        //更新数据库
        $sql = 'INSERT INTO `elective_list`(
                    `school`, `course`, `college`, `credit`, `total`, `remain`,
                    `teacher`, `week`, `time`, `place`, `url`, `term`
                ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY
                UPDATE `credit` = ?, `total` = ?, `remain` = ?,
                `week` = ?, `time` = ?, `place` = ?';
        $sqlArr = array();
        foreach ($list as $item) {
            $sqlArr[] = array(
                $item['school'], $item['course'], $item['college'], $item['credit'],
                $item['total'], $item['remain'], $item['teacher'], $item['week'],
                $item['time'], $item['place'], $item['url'], $item['term'],
                $item['credit'], $item['total'], $item['remain'],
                $item['week'], $item['time'], $item['place']
            );
        }
        Mysql::executeMultiple($sql, $sqlArr);

        return $list;
    }

    //获取已选列表
    public function getSelected()
    {
        if (empty($term)) {
            $term = $this->term;
        }
        return parent::getSelected($term);
    }

    //加入队列
    public function addQueue($title, $url)
    {
        //判断是否开启选课功能
        if ('是' !== Config::getConfig('is_elective')) {
            throw new \Exception('加入队列失败，原因：管理员已关闭选课功能', Config::RETURN_ALERT);
        }

        $sql = "INSERT INTO `elective_queue` (`sid`, `title`, `url`, `time`)
                   VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        $sqlArr = array($this->sid, $title, $url);
        if (!Mysql::execute($sql, $sqlArr)) {
            throw new \Exception('加入队列失败，数据库异常。', Config::RETURN_ALERT);
        }

        $id = Mysql::lastInsertId();
        $baseUrl = Config::getConfig('local_base_url');
        try {
            $http = new Http(array(
                CURLOPT_URL     => $baseUrl . 'remind/electiveQueue?id=' . $id,
                CURLOPT_TIMEOUT => 1,
            ));
        } catch (\Exception $e) {
            //pass
        }
        return array(
            'title'  => $title,
            'result' => '',
            'time'   => date('Y-m-d H:i:s', time()),
        );
    }

    //获取队列记录
    public function getQueue()
    {
        $sql = 'SELECT `title`, `result`, `upTime` FROM `elective_queue` WHERE `sid` = ?';
        $sqlArr = array($this->sid);
        return Mysql::execute($sql, $sqlArr);
    }

    //选/退操作
    public function doAction($url)
    {
        return parent::doAction($url);
    }
}