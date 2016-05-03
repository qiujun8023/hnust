<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Mysql;

class Api extends Base
{
    public $key   = '';
    public $sid   = '';
    public $uid   = '';
    public $name  = '游客';
    public $rank  = 0;
    public $token = '';
    protected $module;
    protected $method;
    protected $access;

    //初始化用户信息
    public function __construct($module, $method)
    {
        parent::__construct($module, $method);
        $this->access  = Config::getAccess($this->module, $this->method);

        //初始化数据
        $this->token  = \Hnust\input('server.HTTP_TOKEN');
        $this->sid    = \Hnust\input('sid');
        $this->passwd = \Hnust\input('passwd');
        $this->page   = \Hnust\input('page/d', 1);
        $this->page   = ($this->page < 1)? 1:$this->page;

        //权限验证
        $this->auth();
    }

    //判断用户访问权限
    public function auth()
    {
        //获取用户信息
        $sql = 'SELECT `u`.`uid`, `s`.`name`, `u`.`rank`
                FROM `user` `u`, `student` `s`
                WHERE `u`.`uid` = `s`.`sid` AND `u`.`apiToken` = ? LIMIT 1';
        $result = Mysql::execute($sql, array($this->token));

        //token不存在
        if (empty($result)) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                Config::getConfig('api_token_error_msg')
            );
        }

        //获取用户信息
        $this->uid  = $result[0]['uid'];
        $this->name = $result[0]['name'];
        $this->rank = (int)$result[0]['rank'];

        //404错误
        if (empty($this->method) || is_null($this->access)) {
            http_response_code(404);
            return $this->checkAuth(
                Config::STATE_NOT_FOUND,
                Config::RETURN_ALERT,
                Config::getConfig('not_found_msg')
            );
        }

        //权限不足
        if ($this->rank < $this->access) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                Config::getConfig('api_forbid_access_msg')
            );
        }

        //权限不足或学号为空查自己
        if (empty($this->sid) || ($this->sid === $this->uid)) {
            $this->sid = $this->uid;
        } elseif ($this->rank < Config::RANK_OTHER) {
            return $this->checkAuth(
                Config::STATE_FORBIDDEN,
                Config::RETURN_ERROR,
                Config::getConfig('api_forbid_others_msg')
            );
        }

        //更新API调用次数
        $sql = 'UPDATE `user` SET
                  `apiCount` = `apiCount` + 1,
                  `lastTime` = NOW()
                WHERE `uid` = ?';
        Mysql::execute($sql, array($this->uid));

        //返回记录
        return $this->checkAuth(
            Config::STATE_API,
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

    //当前周次
    public function week()
    {
        $this->data = \Hnust\Week();
    }

    //当前学期
    public function term()
    {
        $this->data = \Hnust\Config::getConfig('current_term');
    }

    //群组
    public function group()
    {
        $group = new \Hnust\Analyse\Group();
        $list  = $group->belong($this->uid);
        $list  = empty($list)? array():$list;

        $this->data = array();
        foreach ($list as $item) {
            $this->data[] = array_merge($item, array(
                'member' => $group->getMember($item['gid'])
            ));
        }
    }

    //成绩
    public function score()
    {
        $score = new \Hnust\Analyse\Score($this->sid);
        $this->data = $score->getScore();
        $this->info = array(
            'sid' => $this->sid
        );
        if ($score->error) {
            $this->msg  = $score->error;
        } elseif (empty($this->data)) {
            $this->msg  = '未查询到相关成绩记录';
            $this->code = Config::RETURN_ERROR;
        }
    }

    //课表
    public function schedule()
    {
        $type = \Hnust\input('type');
        $week = \Hnust\input('week');
        $term = \Hnust\input('term');
        $term = empty($term)? Config::getConfig('current_term'):$term;
        $schedule   = new \Hnust\Analyse\Schedule($this->sid);
        $this->data = $schedule->getSchdule($this->sid, $term, $type, $week);
        $this->info = array(
            'sid'     => $this->sid,
            'week'    => $week,
            'term'    => $term,
            'remarks' => $this->data['remarks']
        );
        $this->msg  = $schedule->error;
        unset($this->data['remarks']);
    }

    //考试
    public function exam()
    {
        $exam = new \Hnust\Analyse\Exam($this->sid, $this->passwd);
        $this->data = $exam->getExam();
        if ($exam->error) {
            $this->msg = $exam->error;
        } elseif (empty($this->data)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未查询到相关考试安排';
        }
        $this->info = array(
            'sid'  => $this->sid
        );
    }

    //学分绩点
    public function credit()
    {
        $credit = new \Hnust\Analyse\Credit($this->sid, $this->passwd);
        $this->data = $credit->getCredit();
        if ($credit->error) {
            $this->msg  = $credit->error;
        } elseif (empty($this->data)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未查询到相关学分绩点记录';
        }
        $this->info = array(
            'sid' => $this->sid
        );
    }

    //一卡通
    public function card()
    {
        $type       = \Hnust\input('type');
        $startDate  = \Hnust\input('startDate', null);
        $endDate    = \Hnust\input('endDate', null);
        $card       = new \Hnust\Analyse\Card($this->sid, $this->passwd);
        $this->info = $card->getInfo();
        if ('bill' === $type) {
            $this->data = $card->getBill($this->info['cardId'], $startDate, $endDate);
            $student = new \Hnust\Analyse\Student();
            if ($info = $student->info($this->sid)) {
                $this->data['assess'] = $info['assess'];
            } else {
                $this->data['assess'] = '适中';
            }
        } else {
            $this->data = $card->getRecord($this->info['cardId'], $startDate, $endDate);
        }
        $this->info['sid'] = $this->sid;
    }

    //排名
    public function rank()
    {
        $by    = \Hnust\input('by', 'term');
        $scope = \Hnust\input('scope', 'class');
        $term  = \Hnust\input('term');
        if (empty($term) || !in_array(strlen($term), array(9, 11))) {
            $term = Config::getConfig('current_term');
        } elseif (('term' === $by) && (11 !== strlen($term))) {
            $term = Config::getConfig('current_term');
        }
        $term = ('term' !== $by)? substr($term, 0, 9):$term;
        $rank = new \Hnust\Analyse\Rank($this->sid);
        $this->data = $rank->getRank($term, $scope, $by);
        $this->info = array(
            'sid'   => $this->sid,
            'by'    => $by,
            'scope' => $scope,
            'term'  => $term,
            'class' => $rank->class,
            'major' => $rank->major,
            'terms' => $rank->terms,
            'courses'  => $rank->courses,
            'rankName' => $rank->rankName,
        );
    }

    //学费查询
    public function tuition()
    {
        $tuition    = new \Hnust\Analyse\Tuition($this->sid, $this->passwd);
        $this->data = $tuition->getTuition();
        $this->msg  = $tuition->error;
        $this->info = array(
            'sid' => $this->sid
        );
    }

    //挂科率
    public function failRate()
    {
        $failRate   = new \Hnust\Analyse\FailRate();
        $this->data = $failRate->search($this->key);
        if (empty($this->data)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未找到相关课程';
        }
    }
}