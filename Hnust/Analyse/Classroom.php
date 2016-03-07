<?php

namespace Hnust\Analyse;

Class Classroom extends \Hnust\Crawler\Classroom
{
    public function login()
    {
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

    public function getClassroom($term, $build, $week, $day, $beginSession, $endSession)
    {
        return parent::getClassroom($term, $build, $week, $day, $beginSession, $endSession);
    }
}