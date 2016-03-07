<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;

class Update extends Base
{
    //推送实时日志
    protected function log($uid, $name, $data)
    {
        Log::realtime(array(
            'uid'      => $uid,
            'name'     => $name,
            'data'     => $data,
            'state'    => Config::STATE_UPDATE,
            'time'     => date('H:i:s', time())
        ));
    }

    //日常任务
    public function daily()
    {
        //更新年龄
        $sql = "UPDATE `student` SET age = CONCAT(YEAR(NOW()) - SUBSTRING(`idCard`, 7, 4) - 1 + (SUBSTRING(`idCard`, 11, 4) < DATE_FORMAT(NOW(), '%m%d')), '岁') WHERE LENGTH(`idCard`) = 18";
        $num = Mysql::execute($sql);
        $log = "更新年龄{$num}人";

        //密码初始化
        $sql = "INSERT INTO passwd(`id`, `part`, `passwd`)
                  SELECT `sid`, 'ykt', RIGHT(`idcard`, 6)
                  FROM `student` WHERE `sid` NOT IN (SELECT `id` FROM `passwd` WHERE `part` = 'ykt')";
        Mysql::execute($sql);

        //删除空密码
        $sql = "DELETE FROM `passwd` WHERE `passwd` = ''";
        $num = Mysql::execute($sql);
        $log .= "，删除密码{$num}条";

        //查询待删除用户
        $saveTime = Config::getConfig('save_account_time') + Config::getConfig('max_remember_time');
        $saveDay  = $saveTime / 60 / 60 / 24;
        $sql = "SELECT `u`.`uid`, `s`.`name` FROM `user` `u`
                LEFT JOIN `student` `s` ON `s`.`sid` = `u`.`uid`
                WHERE DATE_SUB(CURDATE(), INTERVAL {$saveDay} DAY) >= DATE(`u`.`loginTime`)";
        $users = Mysql::execute($sql);
        $names = array();
        foreach ($users as $user) {
            $names[] = $user['name'];
            \Hnust\Utils\Wechat::deleteUser($user['uid']);
        }
        if (!empty($names)) {
            $log .= '，删除用户' . implode('、', $names);
        }
        //删除用户
        $sql = "DELETE FROM `user`
                WHERE DATE_SUB(CURDATE(), INTERVAL {$saveDay} DAY) >= DATE(`loginTime`)";
        Mysql::execute($sql);

        //删除失效提醒
        $sql = 'DELETE FROM `push`
                WHERE `uid` NOT IN (SELECT `uid` FROM `user`)
                OR `time` < DATE_SUB(NOW(), INTERVAL 1 MONTH)';
        $num = Mysql::execute($sql);
        $log .= "，删除提醒{$num}条";

        //清除日志
        $sql = "DELETE FROM `logs`
                WHERE `uid` IN ('1305010117', '1305010119', '1305020233')
                OR (`ip` != ? AND `location` LIKE '%阿里%')
                OR`time` < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $num = Mysql::execute($sql, array(Config::getConfig('local_out_ip')));
        $sql = "DELETE FROM `logs` WHERE `uid` != ''
                AND `uid` NOT IN (SELECT `uid` FROM `user`)";
        $num += Mysql::execute($sql);
        $log .= "，清除日志{$num}条";

        //更新日志
        $sql = "UPDATE `student` `s`, `logs` `l`
                SET `l`.`key` = `s`.`name`
                WHERE `l`.`key` = `s`.`sid`";
        $num = Mysql::execute($sql);
        $sql = "UPDATE `logs` SET `key` = ''
                WHERE `key` in (`uid`, `name`, '湖南科技大学')";
        $num += Mysql::execute($sql);
        $log .= "，更新日志{$num}条";

        //写入日志文件
        Log::file('daily', $log);
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
        $name = '成绩更新';

        //获取开始学号
        $cache = new Cache('update');
        $cacheData = $cache->get('allScore');
        $sid = $cacheData['sid'];
        if (empty($sid)) {
            return $this->log('', $name, '学号为空，不更新成绩。');
        }

        //全负荷运行
        Config::fullLoad();

        $baseUrl = Config::getConfig('local_base_url');
        for ($i = 0; $i < 20; $i++) {
            $this->log($sid, $name, '成绩更新至' . $sid);

            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`idcard` FROM `student` `a`
                    LEFT JOIN `score` `b` ON `a`.`sid` = `b`.`sid`
                    WHERE (`b`.`sid` IS NULL OR (`b`.`time` + INTERVAL 3 DAY) < NOW())
                    AND `a`.`sid` > ? LIMIT 20";
            $students = Mysql::execute($sql, array($sid));
            if (empty($students)) {
                return $this->log('', $name, '成绩更新完成。');
            }

            $url = $baseUrl . 'update/score';
            Http::multi($url, $students);

            $sid = end($students)['sid'];
        }

        //php递归
        $cacheData['sid'] = $sid;
        $cache->set('allScore', $cacheData);
        try {
            new Http(array(
                CURLOPT_URL      => $baseUrl . 'update/allScore',
                CURLOPT_TIMEOUT  => 3,
            ));
        } catch (\Exception $e) {
            //不处理
        }
        $this->log($sid, $name, '进入下一次循环');
    }

    //课表
    public function schedule()
    {
        $name = '课表更新';

        //获取开始学号
        $cache = new Cache('update');
        $cacheData = $cache->get('schedule');
        $sid = $cacheData['sid'];
        if (empty($sid)) {
            return $this->log('', $name, '学号为空，不更新课表。');
        }

        //全负荷运行
        Config::fullLoad();

        $sql = "SELECT `a`.`sid`, `a`.`school` FROM `student` `a`
                LEFT JOIN `schedule` `b` ON `a`.`sid` = `b`.`sid`
                WHERE (`b`.`sid` IS NULL OR (`b`.`time` + INTERVAL 1 WEEK) < NOW())
                AND `a`.`sid` > ? LIMIT 1";
        $result = Mysql::execute($sql, array($sid));
        if (empty($result)) {
            return $this->log('', $name, '课表更新完成。');
        }

        $sid = $result[0]['sid'];
        $school = $result[0]['school'];
        $schedule = new \Hnust\Analyse\Schedule($sid, '');

        $sql = 'SELECT `a`.`sid` FROM `student` `a`
                LEFT JOIN `schedule` `b` ON `a`.`sid` = `b`.`sid`
                WHERE `a`.`sid` >= ? AND `a`.`school` = ?
                AND (`b`.`sid` IS NULL OR (`b`.`time` + INTERVAL 1 WEEK) < NOW())
                LIMIT 100';
        $students = Mysql::execute($sql, array($sid, $school));
        foreach ($students as $student) {
            $this->log($student['sid'], $name, '课表更新至' . $student['sid']);
            try {
                $schedule->getSchdule($student['sid']);
            } catch (\Exception $e) {
                //pass
            }
        }

        //php递归
        $cacheData['sid'] = $sid;
        $cache->set('schedule', $cacheData);
        $baseUrl = Config::getConfig('local_base_url');
        try {
            new Http(array(
                CURLOPT_URL      => $baseUrl . 'update/schedule',
                CURLOPT_TIMEOUT  => 3,
            ));
        } catch (\Exception $e) {
            //不处理
        }
        $this->log('', $name, '进入下一次循环');
    }

    //挂科率统计
    public function failRate()
    {
        $name = '挂科率统计';

        //全负荷运行
        Config::fullLoad();

        //清空FailRate
        $sql = 'TRUNCATE TABLE `failRate`';
        Mysql::execute($sql);
        $this->log('', $name, '已清空所有挂科率信息。');
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
                $this->log('', $name, $college . '挂科率更新成功。');
            } else {
                $this->log('', $name, $college . '挂科率更新失败。');
            }
        }

        $sql = "INSERT INTO `failRate`
                SELECT '科大本部',`course`, SUM(`all`) `all`, SUM(`fail`) `fail`, (SUM(`fail`) / SUM(`all`) * 100) `rate`, CURRENT_TIMESTAMP 
                FROM `failRate` WHERE `name` LIKE '%学院' GROUP BY `course`;
                INSERT INTO `failRate`
                SELECT '潇湘学院',`course`, SUM(`all`) `all`, SUM(`fail`) `fail`, (SUM(`fail`) / SUM(`all`) * 100) `rate`, CURRENT_TIMESTAMP 
                FROM `failRate` WHERE `name` LIKE '%系' GROUP BY `course`;";
        Mysql::execute($sql);
        $this->log('', $name, '已更新全校挂科率信息。');
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
                Log::file('passwd', '密码更新失败。');
                break;
            }
        }
    }
}