<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Mysql;
use Hnust\Utils\Wechat;

class Weixin extends Base
{
    public $key;
    public $uid  = '';
    public $sid  = '';
    public $name = '游客';
    public $rank = 0;
    protected $secret;
    protected $passwd;
    protected $module;
    protected $method;

    //初始化用户信息
    public function __construct($module, $method)
    {
        parent::__construct($module, $method);

        //初始化数据
        $this->key    = \Hnust\input('key');
        $this->uid    = \Hnust\input('uid');
        $this->sid    = \Hnust\input('sid');
        $this->secret = \Hnust\input('secret');
        $this->passwd = \Hnust\input('passwd');

        //权限验证
        $this->auth();
    }

    //判断用户访问权限
    public function auth()
    {
        //判断微信Secret
        $wechatSecret = Config::getConfig('wechat_secret');
        if ($wechatSecret !== $this->secret) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                Config::getConfig('forbid_access_msg')
            );
        }

        //账号及带查询的学号为空
        if (empty($this->sid) || empty($this->uid)) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                '参数有误，请检查'
            );
        }

        //获取用户信息
        $sql = 'SELECT `s`.`sid`, `s`.`name`, `u`.`rank` FROM `student` `s`
                LEFT JOIN `user` `u` ON `u`.`uid` = `s`.`sid`
                WHERE `s`.`sid` = ? LIMIT 1';
        $result = Mysql::execute($sql, array($this->uid));

        //用户不存在
        if (empty($result)) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                '账号不存在，请检查'
            );
        }

        //获取用户信息
        $this->name = $result[0]['name'];
        $this->rank = $result[0]['rank']? (int)$result[0]['rank']:-1;

        //更新API调用次数
        $sql = 'UPDATE `user` SET
                  `wxCount` = `wxCount` + 1,
                  `lastTime` = NOW()
                WHERE `uid` = ?';
        Mysql::execute($sql, array($this->uid));

        //处理学号
        if ($this->uid !== $this->sid) {
            $student = new \Hnust\Analyse\Student();
            $result = $student->search($this->sid);
            $result = $result['data'];
            //返回错误
            if (empty($result)) {
                return $this->checkAuth(
                    $this->NMStatus,
                    Config::RETURN_ERROR,
                    '未找到相关学号'
                );
            } elseif (1 !== count($result)) {
                return $this->checkAuth(
                    $this->NMStatus,
                    Config::RETURN_ERROR,
                    '学号不唯一，请修改关键词'
                );
            } else {
                $this->sid = $result[0]['sid'];
            }
        }

        //返回记录
        return $this->checkAuth(
            Config::STATE_WECHAT,
            Config::RETURN_NORMAL
        );
    }

    //记录访问记录并退出
    public function checkAuth($state, $code, $msg = null)
    {
        Log::recode($this->uid, $this->name, $this->module, $this->method, $this->key, $state);
        if ($code !== Config::RETURN_NORMAL) {
            $this->code = $code;
            $this->msg  = $msg;
            exit;
        }
    }

    //关注与取消关注
    public function follow()
    {
        $info = Wechat::getUser($this->uid, false);
        if (!empty($info) && is_array($info)) {
            $wid    = empty($info['weixinid'])? '':$info['weixinid'];
            $status = empty($info['status'])? -1:$info['status'];
            $sql    = "INSERT INTO `weixin`(`uid`, `wid`, `status`) VALUES(?, ?, ?)
                       ON DUPLICATE KEY UPDATE `wid` = IF(? = '', `wid`, ?), `status` = ?";
            $sqlArr = array($this->uid, $wid, $status, $wid, $wid, $status);
            Mysql::execute($sql, $sqlArr);
        }
    }

    //成绩
    public function score()
    {
        $score = new \Hnust\Analyse\Score($this->sid);
        $data = $score->getScore();

        //查询失败
        if (empty($data)) {
            $this->msg  = '未查询到相关成绩记录';
            $this->code = Config::RETURN_ERROR;
            return false;
        }

        //选取最大学期
        foreach ($data as $term => $termScore) {
            if ($term > $maxTerm) {
                $maxTerm = $term;
            }
        }
        //标题栏
        $this->data = array(array(
            'title' => $maxTerm . ' 学期成绩'
        ));
        //获取最近一个学期成绩
        foreach ($data[$maxTerm] as $courseScore) {
            $title .= "{$courseScore['course']}\t\t{$courseScore['mark']}";
            $title .= $courseScore['resit']? "*\n":"\n";
        }
        $this->data[] = array(
            'title' => trim($title)
        );
        //获取错误信息
        if ($score->error) {
            $this->data[] = array(
                'title' => trim($score->error)
            );
        }
    }

    //课表
    public function schedule()
    {
        $term = Config::getConfig('current_term');
        $schedule = new \Hnust\Analyse\Schedule($this->sid);
        $data = $schedule->getSchdule($this->sid, $term, 1);

        //获取周次与星期
        $week = \Hnust\week();
        $day  = date('w');
        $isToday = true;
        if (date('H') >= 21) {
            $isToday = false;
            $day = ($day + 1) % 7;
        }
        $day  = $day? $day:7;

        //获取当天课表
        $data = $data[$week][$day];
        $session = array('', '一、二', '三、四', '五、六', '七、八', '九、十');
        $content = '';
        for ($i = 1; $i <= 5; $i++) {
            if (empty($data[$i])) {
                continue;
            }
            $content .= "第{$session[$i]}节：\n课程：{$data[$i]['course']}\n教室：{$data[$i]['classroom']}\n\n";
        }

        //回复
        if ($content) {
            $content = ($isToday? '今':'明') . "日课表如下：\n\n" . $content;
            if ($schedule->error) {
                $content .= $schedule->error;
            }
            $this->data = trim($content);
        } else {
            $this->data = ($isToday? '今':'明') . '日无课，但不要太放松哦';
        }
    }

    //考试
    public function exam()
    {
        $exam = new \Hnust\Analyse\Exam($this->sid, $this->passwd);
        $data = $exam->getExam();
        if (empty($data)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未查询到相关考试安排';
            return false;
        }

        //拼接考试安排字符串
        $content = '';
        $templet = "{course}\n开始：{begin}\n结束：{end}\n地点：{room}\n\n";
        foreach ($data as $item) {
            $content .= \Hnust\templet($templet, $item);
        }
        //回复
        if ($exam->error) {
            $content .= $exam->error;
        }
        $this->data = trim($content);
    }
}