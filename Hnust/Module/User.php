<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Mysql;
use Hnust\Utils\Wechat;

class User extends Auth
{
    //更新用户信息
    public function update()
    {
        $oldPasswd = \Hnust\input('oldPasswd');
        $newPasswd = \Hnust\input('newPasswd');
        $mail      = \Hnust\input('mail');
        $phone     = \Hnust\input('phone');

        //修改密码
        if (!empty($oldPasswd) && !empty($newPasswd)) {
            //验证旧密码
            $sql = 'SELECT * FROM `user` WHERE `uid` = ? AND `passwd` = ? LIMIT 1';
            $result = Mysql::execute($sql, array($this->uid, \Hnust\passwdEncrypt($this->uid, $oldPasswd)));

            //原密码错误
            if (empty($result)) {
                //错误次数加1
                $sql = 'UPDATE `user` SET `error` = (`error` + 1) WHERE `uid` = ? LIMIT 1';
                Mysql::execute($sql, array($this->uid));

                $this->code = Config::RETURN_ALERT;
                $this->msg  = '原密码错误';
                return false;
            }

            //检查弱密码
            $sql = 'SELECT COUNT(*) `count` FROM `weak` WHERE `md5` = ? LIMIT 1';
            $result = Mysql::execute($sql, array($newPasswd));
            if ('0' != $result[0]['count']) {
                $this->code = Config::RETURN_ALERT;
                $this->msg  = '您的密码过于简单';
                return false;
            }

            //修改密码
            $sql = 'UPDATE `user` SET `passwd` = ?, `error` = 0 WHERE `uid` = ?';
            Mysql::execute($sql, array(\Hnust\passwdEncrypt($this->uid, $newPasswd), $this->uid));

            //删除其他登陆设备
            $tokens = $this->authCache->smembers($this->uid);
            foreach ($tokens as $token) {
                if ($token === $this->token) {
                    continue;
                }
                $this->authCache->hdelete('token', $token);
                $this->authCache->sdelete($this->uid, $token);
            }
            $this->data = '修改成功，请牢记您的密码';
        }

        //修改其他数据
        $sql = "UPDATE `user` `u`,`student` `s`
                SET `s`.`mail` = IF(? = '', `s`.`mail`, ?),
                    `s`.`phone` = IF(length(?) <> 11, `s`.`phone`, ?)
                WHERE `s`.`sid` = `u`.`uid` AND `u`.`uid` = ?";
        Mysql::execute($sql, array($mail, $mail, $phone, $phone, $this->uid));
        Wechat::updateUser($this->uid);

        $this->msg  = '系统提示';
        $this->data = empty($this->data)? '已保存您的修改':$this->data;
        $this->code = Config::RETURN_CONFIRM;
        return true;
    }
}