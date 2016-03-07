<?php

namespace Hnust\Analyse;

use Hnust\Utils\Log;
use Hnust\Utils\Mysql;

class Passwd
{
    protected $id;

    //Passwd构造函数
    public function __construct($id)
    {
        $this->id = $id;
    }

    //获取或者设置密码
    public function passwd($part, $passwd = null)
    {
        //取密码
        if (is_null($passwd)) {
            $sql = "SELECT `passwd` FROM `passwd` WHERE `id` = ? AND `part` = ? LIMIT 1";
            $result = Mysql::execute($sql, array($this->id, $part));
            return empty($result)? '':$result[0]['passwd'];
        //删除密码
        } elseif (empty($passwd)) {
            $sql = 'DELETE FROM `passwd` WHERE `id` = ? AND `part` = ? LIMIT 1';
            Mysql::execute($sql, array($this->id, $part));
        //更新密码
        } else {
            $sql = "INSERT INTO `passwd`(`id`, `part`, `passwd`) VALUES(?, ?, ?)
                    ON DUPLICATE KEY UPDATE `passwd` = ?";
            return Mysql::execute($sql, array($this->id, $part, $passwd, $passwd));
        }
    }

    //获取所有密码
    public function all()
    {
        $sql = 'SELECT `part`, `passwd` FROM `passwd` WHERE `id` = ?';
        $result = Mysql::execute($sql, array($this->id));
        $passwd = array();
        foreach ($result as $item) {
            $passwd[$item['part']] = $item['passwd'];
        }
        return $passwd;
    }

    //信息门户
    public function portal($passwd = null)
    {
        return $this->passwd('portal', $passwd);
    }

    //教务处
    public function jwc($passwd = null)
    {
        return $this->passwd('jwc', $passwd);
    }

    //随机教务网密码
    public function randJwc()
    {
        $sql = "SELECT COUNT(*) `count` FROM `student` `s`, `passwd` `p`
                WHERE `s`.`sid` = `p`.`id` AND `p`.`part` = 'jwc'
                AND `s`.`school` IN (SELECT `school` FROM `student` WHERE `sid` = ?)";
        $result = Mysql::execute($sql, array($this->id));
        $limit = 3;
        $start = mt_rand(0, $result[0]['count'] - $limit);
        $sql = "SELECT `s`.`sid`, `p`.`passwd` `jwc` FROM `student` `s`, `passwd` `p`
                WHERE `s`.`sid` = `p`.`id` AND `p`.`part` =  'jwc'
                AND `s`.`school` IN (SELECT `school` FROM `student` WHERE `sid` = ?)
                LIMIT {$start}, {$limit}";
        return Mysql::execute($sql, array($this->id));
    }

    //财务处
    public function cwc($passwd = null)
    {
        return $this->passwd('cwc', $passwd);
    }

    //一卡通
    public function ykt($passwd = null)
    {
        return $this->passwd('ykt', $passwd);
    }

    //图书馆
    public function opac($passwd = null)
    {
        return $this->passwd('opac', $passwd);
    }
}