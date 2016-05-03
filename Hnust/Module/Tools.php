<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Ip;
use Hnust\Utils\Mysql;
use Hnust\Utils\Notice;
use Hnust\Analyse\Group;
use Hnust\Analyse\Download;

class Tools extends Auth
{
    //文件下载
    public function download()
    {
        $file = \Hnust\input('file');
        $download = new Download($this->uid);
        if (!$download->get($file)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未找到相关文件';
        }
    }

    //短信通知
    public function sms()
    {
        $gid      = \Hnust\input('group/d');
        $template = \Hnust\input('template');

        //获取群组成员
        $group    = new Group();
        $member   = $group->phone($gid);

        //发送短信
        $success = 0;
        $error   = array();
        foreach ($member as $item) {
            if (Notice::sms($item['phone'], $template, $item)) {
                $success++;
            } else {
                $error[] = $item['name'];
            }
        }
        $this->msg  = '通知发送结果';
        $this->data = "成功发送{$success}条短信";
        if (count($error)) {
            $this->data .= '，其中失败有：' . implode($error);
        }
    }

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

    //查询老师课表
    public function teacher()
    {
        $teacher = \Hnust\input('teacher');
        if (empty($teacher)) {
            $this->msg  = '参数有误';
            $this->code = Config::RETURN_ERROR;
        } else {
            $schedule = new \Hnust\Analyse\Schedule('1301010101');
            $this->data = $schedule->getTeacherSchedule(trim($teacher));
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
        $gid = \Hnust\input('group/d');
        $list  = \Hnust\input('list');

        //判断周次
        if (($week < 1) || ($week > 20)) {
            $this->msg  = '周次有误，请重新选择';
            $this->code = Config::RETURN_ERROR;
            return;
        }

        $student = array();
        //群组中获取
        if ($gid) {
            $group  = new Group();
            $result = $group->getMember($gid);
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