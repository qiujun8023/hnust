<?php

namespace Hnust\Analyse;

Class Book extends \Hnust\Crawler\Book
{
    protected $passwdClass;
    protected $inputPasswd;
    protected $defaultPasswd = '888888';

    //重载登陆方法，用于保存密码
    public function login()
    {
        $this->passwdClass = new Passwd($this->sid);
        $this->inputPasswd = $this->passwd;

        //获取密码
        if (empty($this->inputPasswd)) {
            if (!($this->passwd = $this->passwdClass->opac())) {
                $this->passwd = $this->defaultPasswd;
            }
        }
        $result = parent::login();
        //保存密码
        if ($result && !in_array($this->inputPasswd, array('', $this->defaultPasswd))) {
            $this->passwdClass->opac($this->inputPasswd);
        }
        return $result;
    }

    public function getLoanList()
    {
        return parent::getLoanList();
    }

    public function doRenew($barcode, $department, $library)
    {
        return parent::doRenew($barcode, $department, $library);
    }
}