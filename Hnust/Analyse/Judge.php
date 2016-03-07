<?php

namespace Hnust\Analyse;

use Hnust\Utils\Mysql;

Class Judge extends \Hnust\Crawler\Judge
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

    public function getList()
    {
        return parent::getList();
    }

    public function submit($param, $radio)
    {
        return parent::submit($param, $radio);
    }
}