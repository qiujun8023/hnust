<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;
use Hnust\Utils\Notice;

require_once __DIR__ . '/../../library/Lunar.php';

class Remind extends Base
{
    protected $logFileName = 'remind';

    protected function record($content)
    {
        return Log::file($this->logFileName, $content);
    }

    protected function br2nl($string)
    {
        return preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $string);
    }

    protected function remind($student, $title, $html, $url, $mode = '1110')
    {
        $content = trim(strip_tags($this->br2nl($html)));
        $this->record("即将提醒【{$student['name']}】：" . strip_tags($html));
        $types = array();

        //socket提醒
        if ('1' === $mode[0]) {
            $types[] = '推送';
            Notice::socket($student['sid'], $title, $content, $url);
        }

        //企业号提醒
        $articles = array(
            'articles' => array(array(
                'title'       => $title,
                'description' => $content
            ))
        );
        if (('1' === $mode[1]) && Notice::wechat($student['sid'], 'news', $articles)) {
            $types[] = '微信';
        }

        //邮件提醒
        if (('1' === $mode[2]) && Notice::mail($student['mail'], $title, $html)) {
            $types[] = '邮件';
        }

        //短信提醒
        $smsParam = $student['sms'];
        $template = $smsParam['template'];
        if (('1' === $mode[3]) && (count($types) < 3) && Notice::sms($student['phone'], $template, $smsParam)) {
            $types[] = '短信';
        }

        //记录提醒方式
        $this->record("已通过如右所示方式提醒【{$student['name']}】：" . implode('、', $types));
    }

    protected function hasExam($sid, $name, $score, $term, $case = 1)
    {
        //尝试获取考试安排
        try {
            $examClass = new \Hnust\Analyse\Exam($sid);
            $exam = $examClass->getExam('com');
        } catch (\Exception $e) {
            if (($e->getCode() === Config::RETURN_NEED_PASSWORD) || ($case > 3)) {
                $this->record("获取【{$name}】的最新考试安排失败：" . $e->getMessage());
                return false;
            }
            return $this->hasExam($sid, $name, $score, $term, $case + 1);
        }
        //成绩获取错误
        if ($examClass->error) {
            $this->record("获取【{$name}】的最新考试安排失败：" . $examClass->error);
        }

        //判断最近是否存在考试
        for ($i = 0; $i < count($exam); $i++) {
            //判断考试是否已经开始
            if (strtotime($exam[$i]['end']) >= time()) {
                continue;
            }
            //是否公布该科成绩
            $score[$term] = isset($score[$term])? $score[$term]:array();
            for ($j = 0; $j < count($score[$term]); $j++) {
                if ($exam[$i]['course'] === $score[$term][$j]['course']) {
                    break;
                }
            }
            if ($j < count($score[$term])) {
                continue;
            }
            return true;
        }
        return false;
    }

    public function scoreInit()
    {
        //设置日志文件
        $this->logFileName = 'score';

        //判断是否考试时间
        if ('是' !== Config::getConfig('is_exam')) {
            return false;
        }

        //全负荷运行
        Config::fullLoad();

        //获取所有用户
        $sql = 'SELECT `a`.`sid`, `a`.`name`, `a`.`idcard`,
                `a`.`mail`, `a`.`phone`, `b`.`score` FROM `student` `a`
                LEFT JOIN `score` `b` ON `b`.`sid` = `a`.`sid`
                WHERE `a`.`sid` IN (SELECT `uid` FROM `user`)';
        $students = Mysql::execute($sql);

        //计算需开启成绩提醒的用户
        $cache  = new Cache('remind_score');
        $term   = Config::getConfig('current_term');
        $result = array();
        foreach ($students as $student) {
            //从缓存/数据库中读取成绩
            $isCached = true;
            if (!($score = $cache->get($student['sid']))) {
                $isCached = false;
                $score = json_decode($student['score'], true);
            }
            //判断是否需要开启成绩提醒
            if ($this->hasExam($student['sid'], $student['name'], $score, $term)) {
                //未缓存成绩则缓存成绩
                if (!$isCached) {
                    $this->record("缓存【{$student['name']}】的成绩");
                    $cache->set($student['sid'], $score, 259200);
                }
                $result[] = $student;
            }
        }

        //设置开启成绩提醒的用户
        $cache->set('list', $result);
        $this->record('=== 已开启' . count($result) . '人的成绩提醒 ===');
    }

    //成绩提醒
    public function score()
    {
        //设置日志文件
        $this->logFileName = 'score';

        //判断是否考试时间
        if ('是' !== Config::getConfig('is_exam')) {
            return false;
        //判断当前是否工作时间
        } elseif ((date('H') < 8) || (date('H') > 21)) {
            $this->record("=== 非正常工作时间，退出 ===");
            return false;
        }

        //全负荷运行
        Config::fullLoad();

        //成绩提醒缓存数据
        $cache = new Cache('remind_score');

        //获取开启成绩提醒的学生列表
        if (!($students = $cache->get('list'))) {
            $this->record('=== 学生列表为空，退出 ===');
            return false;
        }

        //判断新成绩
        $failures = array();
        foreach ($students as $student) {
            //获取新成绩
            try {
                $scoreClass = new \Hnust\Analyse\Score($student['sid'], $student['name'], $student['idcard']);
                $newScore   = $scoreClass->getScore();
                if ($scoreClass->error) {
                    throw new \Exception('获取最新成绩失败', Config::RETURN_ERROR);
                }
            } catch (\Exception $e) {
                $failures[] = $student['name'];
                continue;
            }

            //获取旧成绩并缓存新成绩
            $oldScore = $cache->get($student['sid']);
            $cache->set($student['sid'], $newScore, 259200);
            if (empty($oldScore)) {
                $this->record("获取【{$student['name']}】的旧成绩失败");
                $failures[] = $student['name'];
                continue;
            }

            $remind = array();
            //这里如果两次考试课程名称、分数等都一致，会提醒失败
            foreach ($newScore as $term => $newTermScore) {
                $oldTermScore = isset($oldScore[$term])? $oldScore[$term]:array();
                $remind = array_merge($remind, array_filter($newTermScore, function($courseScore) use($oldTermScore) {
                    for ($i = 0; $i < count($oldTermScore); $i++) {
                        if ($courseScore['course'] != $oldTermScore[$i]['course']) {
                            continue;
                        } else if ($courseScore['mark'] != $oldTermScore[$i]['mark']) {
                            continue;
                        } else if  ($courseScore['resit'] != $oldTermScore[$i]['resit']) {
                            continue;
                        } else {
                            return false;
                        }
                    }
                    return true;
                }));
            }

            //无新成绩
            if (empty($remind)) {
                continue;
            }

            //构造消息
            $title   = '新成绩提醒 -- Tick团队';
            $content = '';
            foreach ($remind as $item) {
                if (empty($item['mark']) || ('不及格' == $item['mark']) || (is_numeric($item['mark']) && ($item['mark'] < 60))) {
                    $content .= "{$item['course']}  <span style='color:red'>{$item['mark']}</span><br/>";
                } else {
                    $content .= "{$item['course']}  {$item['mark']}<br/>";
                }
            }
            $student['sms'] = array(
                'name'     => $student['name'],
                'content'  => trim(strip_tags($this->br2nl($content))),
                'template' => 'SMS_6635017'
            );
            $this->remind($student, $title, $content, '#/score', '1111');
        }

        $total   = count($students);
        $success = $total - count($failures);
        $this->record("=== 成绩提醒执行完成 {$success}/{$total} ===");
    }

    //获取选修课列表
    public function electiveList()
    {
        //设置日志文件
        $this->logFileName = 'elective';

        if ('是' !== Config::getConfig('is_elective')) {
            return false;
        } elseif (date('H') < 6) {
            $this->record('=== 休息时间，退出列表获取 ===');
            return false;
        }

        //全负荷运行
        Config::fullLoad();

        $result = array(0, 0);
        $sids   = array('1305010101', '1355010101');
        for ($i = 0; $i < 2; $i++) {
            try {
                $elective   = new \Hnust\Analyse\Elective($sids[$i]);
                $list       = $elective->getList();
                $result[$i] = count($list);
            } catch (\Exception $e) {
                //pass
            }
        }
        $this->record("本部与潇湘分别更新列表{$result[0]}、$result[1]门");
    }

    //执行选课队列
    public function electiveQueue()
    {
        //设置日志文件
        $this->logFileName = 'elective';

        if ('是' !== Config::getConfig('is_elective')) {
            return false;
        }

        //全负荷运行
        Config::fullLoad();

        $id = \Hnust\input('id/d', null);

        //获取未完成队列列表
        if (is_null($id)) {
            $sql = "SELECT `id` FROM `elective_queue`
                    WHERE `result` = '' OR `result` IS NULL
                    AND `upTime` < DATE_SUB(NOW(), INTERVAL 3 MINUTE) LIMIT 50";
            if ($result = Mysql::execute($sql)) {
                $baseUrl = Config::getConfig('local_base_url');
                foreach ($result as $item) {
                    try {
                        $http = new Http(array(
                            CURLOPT_URL     => $baseUrl . 'remind/electiveQueue?id=' . $item['id'],
                            CURLOPT_TIMEOUT => 1,
                        ));
                    } catch (\Exception $e) {
                        //pass
                    }
                }
                $this->record("=== 执行未处理的选课队列" . count($result) . '条');
            }
            return true;
        }

        //执行单个队列
        $sql = 'SELECT `s`.`sid`, `s`.`name`, `s`.`mail`, `e`.`title`, `e`.`url`
                FROM `elective_queue` `e`
                LEFT JOIN `student` `s` ON `e`.`sid` = `s`.`sid`
                WHERE `id` = ? LIMIT 1';
        if ($queue = Mysql::execute($sql, array($id))) {
            $queue = $queue[0];
            $elective = new \Hnust\Analyse\Elective($queue['sid']);
            for ($i = 0; $i < 3; $i++) {
                try {
                    $queue['result'] = $elective->doAction($queue['url']);
                    break;
                } catch (\Exception $e) {
                    //pass
                }
            }

            //执行成功
            if (!empty($queue['result'])) {
                //更新队列
                $sql ='UPDATE `elective_queue` SET `result` = ? WHERE `id` = ? LIMIT 1';
                $sqlArr = array($queue['result'], $id);
                Mysql::execute($sql, $sqlArr);
                //推送
                $this->remind($queue, $queue['title'], $queue['result'], '#/elective', '1100');
            }
        }
    }

    //图书借阅提醒
    public function book()
    {
        //设置日志文件
        $this->logFileName = 'book';

        //图书借阅缓存
        $cache = new Cache('remind_book');
        if ($cache->get('lock')) {
            return false;
        } else {
            $cache->set('lock', true, 86000);
        }

        //全负荷运行
        Config::fullLoad();

        //剩余天数提醒
        $remains = array(7, 3, 1, 0);

        //获取所有用户
        $sql = 'SELECT `sid`, `name`, `phone`, `mail` FROM `student`
                WHERE `sid` IN (SELECT `uid` FROM `user`)';
        $students = Mysql::execute($sql);
        foreach ($students as $student) {
            //判断是否需要获取借阅列表
            if ($cache->get($student['sid'])) {
                continue;
            }

            try {
                $bookClass = new \Hnust\Analyse\Book($student['sid']);
                $loanList  = $bookClass->getLoanList();
            } catch (\Exception $e) {
                $this->record("获取【{$student['name']}】的借阅列表失败：" . $e->getMessage());
                continue;
            }

            $minDiff = 20;
            foreach ($loanList as $item) {
                $minDiff = min($minDiff, $item['remain']);

                if (empty($item['remain']) || !in_array($item['remain'], $remains)) {
                    continue;
                }

                try {
                    $result = $bookClass->doRenew($item['barcode'], $item['department'], $item['library']);
                } catch (\Exception $e) {
                    $result = $e->getMessage();
                }
                $title   = '图书借阅过期提醒 -- Tick团队';
                $content = "亲爱的 {$student['name']} 同学，您借阅的《{$item['title']}》将于{$item['remain']}天内到期，我们已尝试为您进行续借操作，操作结果为：【{$result}】";
                $student['sms'] = array(
                    'name'     => $student['name'],
                    'book'     => $item['title'],
                    'day'      => (string) $item['remain'],
                    'result'   => $result,
                    'template' => 'SMS_6730035'
                );
                $this->remind($student, $title, $content, '#/book', '1111');
            }

            //计算多少天秒内不需要获取借阅列表
            $cacheTime = ($minDiff - $remains[0] - 1) * 86400;
            if ($cacheTime > 0) {
                $cache->set($student['sid'], true, $cacheTime);
            }
        }
        $this->record("=== 图书提醒执行完成 ===");
    }

    //生日提醒
    public function birthday()
    {
        $this->logFileName = 'birthday';

        //生日提醒锁
        $cache = new Cache('remind_birthday');
        if ($cache->get('lock')) {
            return false;
        } else {
            $cache->set('lock', true, 86000);
        }

        $zh2num = [
            '零' => 0,
            '一' => 1,
            '二' => 2,
            '三' => 3,
            '四' => 4,
            '五' => 5,
            '六' => 6,
            '七' => 7,
            '八' => 8,
            '九' => 9,
        ];
        $lunar = new \Lunar();
        $date  = $lunar->convertSolarToLunar(date('Y'), date('m'), date('d'));
        $sql   = "SELECT `sid`, `name`, `phone`, `mail`, `birthday` FROM `student`
                  WHERE (`birthday` LIKE ? OR `birthday` LIKE ?)
                  AND `sid` IN (SELECT `uid` FROM `user`)";
        $arr   = array('____' . date('md'), '____' . $date[1] . $date[2]);
        $users = Mysql::execute($sql, $arr);

        $group = new \Hnust\Analyse\Group();
        foreach ($users as $user) {
            if (is_numeric($user['birthday'])) {
                $year = (int) substr($user['birthday'], 0, 4);
            } else {
                $year = 0;
                for ($i = 0; $i < 4; $i++) {
                    $year *= 10;
                    $year += $zh2num[mb_substr($user['birthday'], $i, 1, 'utf-8')];
                }
            }
            //计算所属群组
            $groups = ['Tick网络工作室'];
            $belong = $group->belong($user['sid']);
            foreach ($belong as $item) {
                if (!in_array($item['name'], $groups)) {
                    $groups[] = $item['name'];
                }
            }
            $user['sms'] = array(
                'name'     => $user['name'],
                'age'      => (string) (date('Y') - $year),
                'group'    => implode('、', $groups),
                'template' => 'SMS_7745798'
            );
            $this->remind($user, '生日快乐', '生日快乐', '', '0001');
        }
        $this->record("=== 生日提醒执行完成 ===");
    }

    //账号
    public function account()
    {
        //设置日志文件
        $this->logFileName = 'account';

        //账号提醒缓存
        $cache = new Cache('remind_account');
        if ($cache->get('lock')) {
            return false;
        } else {
            $cache->set('lock', true, 86000);
        }

        //检查
        $saveDay = Config::getConfig('save_account_time') / 86400;
        $sql = "SELECT `s`.`sid`, `s`.`name`, `s`.`phone`, `s`.`mail`
                FROM `user` `u`
                LEFT JOIN `student` `s` ON `s`.`sid` = `u`.`uid`
                WHERE `u`.`rank` < ?
                AND DATE_SUB(CURDATE(), INTERVAL {$saveDay} DAY) = DATE(`u`.`lastTime`)";
        $users = Mysql::execute($sql, array(Config::RANK_ADMIN));

        //提示
        $baseUrl = Config::getConfig('local_base_url');
        foreach ($users as $user) {
            $title   = '账号待删除提醒 -- Tick团队';
            $content = "亲爱的 {$user['name']} 同学您好，由于您的账号长期处于未使用状态，出于安全方面的考虑，我们将对您的账号进行回收处理，届时【成绩提醒】、【图书自动续借】、【图书超期提醒】等相关功能将一并停止。<span style='color:red'>【如需保留账号，请及时登陆小水表网站或小水表APP并保持账号活跃】</span>，谢谢您的支持！<br/>小水表网址：{$baseUrl}";
            $user['sms'] = array(
                'name'     => $user['name'],
                'template' => 'SMS_6770031'

            );
            $this->remind($user, $title, $content, '', '0111');
        }
        $this->record("=== 销号提醒执行完成 ===");
    }

    //网络监控
    public function network()
    {
        //设置日志文件
        $this->logFileName = 'network';

        //获取最新数据
        $net     = file_get_contents('/proc/net/dev');
        $pattern = "/eth1:\s*(\d+)\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/";
        preg_match($pattern, $net, $matches);
        $newRes  = array(
            'in'   => array(
                'bytes'   => $matches[1],
                'packets' => $matches[2],
            ),
            'out'  => array(
                'bytes'   => $matches[3],
                'packets' => $matches[4],
            ),
            'time' => time()
        );

        //处理缓存数据
        $cache  = new Cache('remind_network');
        $oldRes = $cache->get('res');
        $cache->set('res', $newRes);
        if (empty($oldRes)) {
            return;
        }

        //判断是否提醒
        $minutes = number_format(($newRes['time'] - $oldRes['time']) / 60, 2);
        $bytes   = $newRes['out']['bytes'] - $oldRes['out']['bytes'];
        $currentSize = \Hnust\sizeFormat($bytes);
        $totalSize   = \Hnust\sizeFormat($newRes['out']['bytes']);
        $remindValue = Config::getConfig('network_remind_value');
        $remindUser  = Config::getConfig('network_remind_user');
        if ($bytes > $remindValue) {
            $sql     = 'SELECT `sid`, `name`, `mail` FROM `student` WHERE `sid` = ?';
            $result  = Mysql::execute($sql, array($remindUser));
            $student = $result[0];
            $title   = '服务器流量异常提醒 -- Tick团队';
            $content = "尊敬的管理员您好，系统检测服务器出网流量在 {$minutes} 分钟内共消耗了 {$currentSize}，累计使用 {$totalSize}，请及时处理！" ;
            $this->remind($student, $title, $content, '', '0100');
        }
        $this->record("{$minutes} 分钟内共消耗出网流量 {$currentSize}，累计使用 {$totalSize}");
    }
}