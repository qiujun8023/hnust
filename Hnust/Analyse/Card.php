<?php

namespace Hnust\Analyse;

Class Card extends \Hnust\Crawler\Card
{
    protected $passwdClass;
    protected $inputPasswd;

    //重载构造函数，用于密码的预处理
    public function __construct($sid, $passwd = '')
    {
        $this->passwdClass = new Passwd($sid);
        $this->inputPasswd = $passwd;

        $passwd = empty($passwd)? $this->passwdClass->ykt():$passwd;
        parent::__construct($sid, $passwd);
    }

    public function login()
    {
        $result = parent::login();
        //保存密码
        if ($result && !empty($this->inputPasswd)) {
            $this->passwdClass->ykt($this->inputPasswd);
        }
        return $result;
    }

    //挂失与解挂
    public function doLoss($loss = true)
    {
        $result = parent::doLoss($loss);
        if (!$loss && '操作成功' === $result) {
            $result = '解挂成功，如卡状态仍未更改，请稍等1分钟再刷新页面。';
        }
        return $result;
    }
}