<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Ip;
use Hnust\Utils\Mysql;

class Tools extends Auth
{
    //教室情况
    public function room()
    {
        $session   = \Hnust\input('session');
        $classroom = \Hnust\input('classroom');
        if (empty($session) || empty($classroom)) {
            $this->msg  = '参数有误';
            $this->code = Config::RETURN_ERROR;
        } elseif (!in_array($session, array(1, 2, 3, 4, 5))) {
            $this->msg  = '上课时间有误，注意：一天只有1-5节课';
            $this->code = Config::RETURN_ERROR;
        } else {
            $week  = \Hnust\week();
            $day   = date('w')? date('w'):7;
            $schedule = new \Hnust\Analyse\Schedule('1301010101');
            $this->data = $schedule->getCourse($week, $day, $session, $classroom);
        }
    }

    //选修课列表
    public function elective()
    {
        $course  = \Hnust\input('course');
        $day     = \Hnust\input('day');
        $session = \Hnust\input('session');
        if (empty($course) || empty($day) || empty($session)) {
            $this->msg  = '参数有误';
            $this->code = Config::RETURN_ERROR;
        } elseif (!in_array($day, array(1, 2, 3, 4, 5, 6 ,7))) {
            $this->msg  = '星期参数有误';
            $this->code = Config::RETURN_ERROR;
        } elseif (!in_array($session, array(1, 2, 3, 4, 5))) {
            $this->msg  = '节次参数有误';
            $this->code = Config::RETURN_ERROR;
        } else {
            $schedule = new \Hnust\Analyse\Schedule('1301010101');
            $this->data = $schedule->getElectiveList(trim($course), $day, $session);
            $this->info = array(
                'day'     => $day,
                'session' => $session,
                'course'  => $course
            );
        }
    }

    //上课统计
    public function time()
    {
        $week  = \Hnust\input('week/d');
        $group = \Hnust\input('group/d');
        $list  = \Hnust\input('list', '');

        //判断周次
        if (($week < 1) || ($week > 20)) {
            $this->msg  = '周次有误，请重新选择';
            $this->code = Config::RETURN_ERROR;
            return;
        }

        $student = array();
        //群组中获取
        if ($group) {
            $Group  = new \Hnust\Analyse\Group();
            $result = $Group->getMember($group);
            if ($result) {
                foreach ($result as $item) {
                    $student[] = $item['sid'];
                }
            }
        }
        //输入框中获取
        $list = trim($list);
        if ($list) {
            $list = explode("\n", $list);
            if ($list) {
                $student = array_merge($student, $list);
            }
        }
        $student = array_unique($student);

        //判断学号
        if (empty($student)) {
            $this->msg  = '学号不能为空，请选择群组或者输入学号';
            $this->code = Config::RETURN_ERROR;
            return;
        }

        //正常
        $schedule = new \Hnust\Analyse\Schedule('1301010101');
        $result   = $schedule->getFreeTime($student, $week);
        $this->data = $result['data'];
        $this->info = array(
            'week'  => $week,
            'list'  => $list,
            'error' => $result['error']
        );
    }
}