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
        $this->key    = \Hnust\input('key', '');
        $this->uid    = \Hnust\input('uid', '');
        $this->sid    = \Hnust\input('sid', '');
        $this->secret = \Hnust\input('secret', '');
        $this->passwd = \Hnust\input('passwd', '');

        //权限验证
        $this->auth();
    }

    //判断用户访问权限
    public function auth()
    {
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

        //判断微信Secret
        $wechatSecret = Config::getConfig('wechat_secret');
        if ($wechatSecret !== $this->secret) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                Config::getConfig('forbid_access_msg')
            );
        }

        //处理Ta的学号
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
                    '学号不唯一，请修改关键词。'
                );
            } else {
                $this->sid = $result[0]['sid'];
            }
        }

        //返回记录
        return $this->checkAuth(
            Config::STATE_WECHAT,
            Config::RETURN_NORMAL,
            'Success'
        );
    }

    //记录访问记录并退出
    public function checkAuth($state, $code, $msg)
    {
        Log::recode($this->uid, $this->name, $this->module, $this->method, $this->key, $state);
        if ($code !== Config::RETURN_NORMAL) {
            $this->code = $code;
            $this->msg  = $msg;
            die;
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
        $this->code = Config::RETURN_NORMAL;
        $this->msg  = '欢迎关注小水表...';
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

    //人脸识别
    public function face()
    {
        //无权访问
        if ($this->rank < Config::RANK_OTHER) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '权限不足，无法进行识别';
            return;
        }

        $res  = \Hnust\input('res/a');
        $sids = $confidence = array();
        foreach ($res as $item) {
            $sid = $item['person_id'];
            $sids[] = $sid;
            $confidences[$sid] = round($item['confidence'], 4);
        }
        //查询数据库取结果
        $student = new \Hnust\Analyse\Student();
        $data    = $student->wechat($sids);
        if (!$data) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未找到相关结果';
            return;
        }
        usort($data, function($a, $b) use($confidences) {
            return $confidences[$a['sid']] > $confidences[$b['sid']]? -1:1;
        });
        //数据处理为微信数据
        $this->data = array(array(
            'title' => "上述图片识别结果如下："
        ));
        $templet = "姓名：{name}\n学号：{sid}\n班级：{class}\n相似：{confidence}%";
        for ($i = 0; (($i <= 7) && ($i < count($data))); $i++) {
            $data[$i]['confidence'] = $confidences[$data[$i]['sid']];
            $this->data[] = array(
                'title'  => \Hnust\templet($templet, $data[$i]),
                'picurl' => $student->avatar($data[$i]['sid'])
            );
        }
        $this->data[] = array(
            'title' => "以上只显示相似度最高的{$i}条记录"
        );
    }

    //资料查询
    public function student()
    {
        //无权访问
        if ($this->rank < Config::RANK_ADMIN) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '水表一下，你就知道';
            return;
        }

        if (empty($this->key)) {
            return $this->data = '请回复相关关键词，多个关键词可用空格分隔：';
        }

        $this->data = array();
        $student = new \Hnust\Analyse\Student();
        $result  = $student->search($this->key, 1);
        $data    = $result['data'];
        //无记录
        if (empty($data)) {
            $this->msg  = '未找到相关记录，请修改关键词';
            $this->code = Config::RETURN_ERROR;
        //单人
        } elseif (1 === count($data)) {
            $data = $student->info($data[0]['sid']);
            $templet = "姓名：{name}\n学号：{sid}\n身高：{height}\n体重：{weight}\n"
                     . "班级：{class}\n学院：{college}\n宿舍：{dorm}\n电话：{phone}\n"
                     . "邮箱：{mail}\n住址：{city}\n爱好：{hobby}\n其他：{mark}\n";
            $description = \Hnust\templet($templet, $data);
            $description = preg_replace("/\n(.*)?：\s*\n/", "\n", $description);
            $this->data = array(array(
                'title'       => '水表一下，你就知道',
                'description' => trim($description)
            ));
        //多人
        } else {
            $this->data = array(array(
                'title' => "与 {$this->key} 有关的记录如下："
            ));
            $templet = "姓名：{name}\n学号：{sid}\n班级：{class}";
            for ($i = 0; (($i <= 7) && ($i < count($data))); $i++) {
                $this->data[] = array(
                    'title'  => \Hnust\templet($templet, $data[$i]),
                    'picurl' => $student->avatar($data[$i]['sid'])
                );
            }
            if ($i !== count($data)) {
                $this->data[] = array(
                    'title' => "以上只显示{$i}条记录"
                );
            }
        }
    }
}