<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;

class Update extends Base
{
    protected $sid;
    protected $cookie;
    protected $cache = null;

    //推送实时日志
    protected function log($uid, $name, $data)
    {
        Log::realtime(array(
            'uid'   => $uid,
            'name'  => $name,
            'data'  => $data,
            'state' => Config::STATE_UPDATE,
            'time'  => date('H:i:s', time())
        ));
    }

    //通过缓存获取用户信息
    protected function getCache($key)
    {
        if (is_null($this->cache)) {
            $this->cache = new Cache('update');
        }

        //获取缓存数据
        $cacheData    = $this->cache->get($key);
        $this->sid    = $cacheData['start'];
        $this->cookie = $cacheData['cookie'];
        return array(
            'sid'    => $this->sid,
            'cookie' => $this->cookie
        );
    }

    //缓存用户信息
    protected function setCache($key, $sid = null, $cookie = null)
    {
        if (is_null($this->cache)) {
            $this->cache = new Cache('update');
        }

        //获取缓存数据
        $cacheData = $this->cache->get($key);

        //更新缓存学号
        if (!empty($sid)) {
            $cacheData['start'] = $sid;
        }

        //更新缓存cookie
        if (!empty($cookie)) {
            $cacheData['cookie'] = $cookie;
        }
        return $this->cache->set($key, $cacheData, 259200);
    }

    //递归
    protected function recursion($method)
    {
        $baseUrl = Config::getConfig('local_base_url');
        try {
            new Http(array(
                CURLOPT_URL     => $baseUrl . 'update/' . $method,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            //不处理
        }
    }

    //单个成绩
    public function score()
    {
        $sid    = \Hnust\input('sid');
        $name   = \Hnust\input('name');
        $idcard = \Hnust\input('idcard');
        $score  = new \Hnust\Analyse\Score($sid, $name, $idcard);
        echo ($score->getScore() && !$score->error)? 'success':'error';
    }

    //全部成绩
    public function allScore()
    {
        //获取起始学号
        $this->getCache(__FUNCTION__);
        if (empty($this->sid)) {
            return $this->log('', '成绩更新', '学号为空，不更新成绩');
        }

        //全负荷运行
        Config::fullLoad();

        $baseUrl = Config::getConfig('local_base_url');
        for ($i = 0; $i < 20; $i++) {
            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`idcard` FROM `student` `a`
                    LEFT JOIN `score` `b` ON `a`.`sid` = `b`.`sid`
                    WHERE (`b`.`sid` IS NULL OR (`b`.`time` + INTERVAL 3 DAY) < NOW())
                    AND `a`.`sid` > ? LIMIT 20";
            $students = Mysql::execute($sql, array($this->sid));
            if (empty($students)) {
                return $this->log('', '成绩更新', '成绩更新完成');
            }

            $url = $baseUrl . 'update/score';
            Http::multi($url, $students);

            $this->sid = end($students)['sid'];
            $this->log($this->sid, '成绩更新', '成绩更新至' . $this->sid);
        }

        //递归
        $this->setCache(__FUNCTION__, $this->sid);
        $this->recursion(__FUNCTION__);
    }

    //课表
    public function schedule()
    {
        //获取起始学号
        $this->getCache(__FUNCTION__);
        if (empty($this->sid)) {
            return $this->log('', '课表更新', '学号为空，不更新课表');
        }

        //全负荷运行
        Config::fullLoad();

        $sql = "SELECT `a`.`sid`, `a`.`school` FROM `student` `a`
                LEFT JOIN `schedule` `b` ON `a`.`sid` = `b`.`sid`
                WHERE (`b`.`sid` IS NULL OR (`b`.`time` + INTERVAL 1 WEEK) < NOW())
                AND `a`.`sid` > ? LIMIT 1";
        $result = Mysql::execute($sql, array($this->sid));
        if (empty($result)) {
            return $this->log('', '课表更新', '课表更新完成');
        }

        $this->sid = $result[0]['sid'];
        $term      = Config::getConfig('current_term');
        $school    = $result[0]['school'];
        $schedule  = new \Hnust\Analyse\Schedule($this->sid, '');

        $sql = 'SELECT `a`.`sid` FROM `student` `a`
                LEFT JOIN `schedule` `b` ON `a`.`sid` = `b`.`sid`
                WHERE `a`.`sid` >= ? AND `a`.`school` = ?
                AND (`b`.`sid` IS NULL OR (`b`.`time` + INTERVAL 1 WEEK) < NOW())
                LIMIT 100';
        $students = Mysql::execute($sql, array($this->sid, $school));
        foreach ($students as $student) {
            try {
                $schedule->getSchdule($student['sid'], $term);
                $this->log($student['sid'], '课表更新', '课表更新至' . $student['sid']);
            } catch (\Exception $e) {
                //pass
            }
        }

        //递归
        $this->setCache(__FUNCTION__, $this->sid);
        $this->recursion(__FUNCTION__);
    }

    //挂科率统计
    public function failRate()
    {
        //全负荷运行
        Config::fullLoad();

        //清空FailRate
        $sql = 'TRUNCATE TABLE `failRate`';
        Mysql::execute($sql);
        $this->log('', '挂科率统计', '已清空所有挂科率信息');
        //获取所有学院
        $sql = 'SELECT DISTINCT `college` FROM `student`';
        $colleges = Mysql::execute($sql);
        $sid = (substr(date('Y', time()), 2, 2) - 3) . '01010100';
        foreach ($colleges as $college) {
            $college = $college['college'];

            //获取全学院成绩信息
            $sql = 'SELECT `b`.`score` FROM `student` `a`, `score` `b`
                    WHERE `a`.`sid` = `b`.`sid` AND `a`.`college` = ? AND `a`.`sid` > ?';
            $students = Mysql::execute($sql, array($college, $sid));

            //统计挂科情况
            $data = array();
            foreach ($students as $student) {
                $score = json_decode($student['score'], true);
                foreach ($score as $termScore) {
                    foreach ($termScore as $courseScore) {
                        //课程成绩异常情况
                        if ((strlen($courseScore['course']) == 0) || (strlen($courseScore['mark']) == 0)) {
                            continue;
                        }
                        //获取当前课程位置
                        for ($i = 0; $i < count($data); $i++) {
                            if ($data[$i]['course'] == $courseScore['course']) {
                                break;
                            }
                        }
                        //统计数据中不存在该课程
                        if ($i == count($data)) {
                            $data[$i] = array(
                                'course' => $courseScore['course'],
                                'all'    => 0,
                                'fail'   => 0
                            );
                        }
                        $data[$i]['all'] ++ ;
                        if ((is_numeric($courseScore['mark']) && ($courseScore['mark'] < 60)) || ($courseScore['mark'] == '不及格')) {
                            $data[$i]['fail'] ++;
                        }
                    }
                }
            }
            $sqlArr = array();
            foreach ($data as $item) {
                $item['rate'] = round($item['fail'] / $item['all'] * 100, 3);
                $sqlArr[] = array($college, $item['course'], $item['all'], $item['fail'], $item['rate']);
            }
            //计算挂科率并写入数据库
            $sql = 'INSERT INTO `failRate`(`name`, `course`, `all`, `fail`, `rate`) VALUES(?, ?, ?, ?, ?)';
            if (Mysql::executeMultiple($sql, $sqlArr)) {
                $this->log('', '挂科率统计', $college . '挂科率更新成功');
            } else {
                $this->log('', '挂科率统计', $college . '挂科率更新失败');
            }
        }

        $sql = "INSERT INTO `failRate`
                SELECT '科大本部',`course`, SUM(`all`) `all`, SUM(`fail`) `fail`, (SUM(`fail`) / SUM(`all`) * 100) `rate`, CURRENT_TIMESTAMP
                FROM `failRate` WHERE `name` LIKE '%学院' GROUP BY `course`;
                INSERT INTO `failRate`
                SELECT '潇湘学院',`course`, SUM(`all`) `all`, SUM(`fail`) `fail`, (SUM(`fail`) / SUM(`all`) * 100) `rate`, CURRENT_TIMESTAMP
                FROM `failRate` WHERE `name` LIKE '%系' GROUP BY `course`;";
        Mysql::execute($sql);
        $this->log('', '挂科率统计', '已更新全校挂科率信息');
    }

    //更新弱密码数据库
    public function passwd()
    {
        //全负荷执行
        Config::fullLoad();

        //添加学生学号到密码数据库
        $sql = 'INSERT INTO `weak`(`passwd`)
                  (SELECT `sid` FROM `student` WHERE `sid` NOT IN
                    (SELECT `passwd` FROM `weak`))';
        Mysql::execute($sql);

        //添加教工号到数据库
        $sql = 'INSERT INTO `weak`(`passwd`)
                  (SELECT `tid` FROM `teacher` WHERE `tid` NOT IN
                    (SELECT `passwd` FROM `weak`))';
        Mysql::execute($sql);

        //更新数据库
        while (true) {
            $sql = "SELECT `passwd` FROM `weak` WHERE `sha1` = '' OR `md5` = '' LIMIT 10000";
            $passwds = Mysql::execute($sql);
            if (empty($passwds)) {
                break;
            }
            $sql = 'UPDATE `weak` SET `sha1` = ?, `md5` = ? WHERE `passwd` = ? LIMIT 1';
            for ($i = 0; $i < count($passwds); $i++) {
                $passwd = $passwds[$i]['passwd'];
                $passwds[$i] = array(sha1($passwd), md5($passwd), $passwd);
            }
            if (Mysql::executeMultiple($sql, $passwds)) {
                Log::file('passwd', '成功更新' . count($passwds) . '条密码');
            } else {
                Log::file('passwd', '密码更新失败');
                break;
            }
        }
    }
}