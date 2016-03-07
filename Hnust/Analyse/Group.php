<?php

namespace Hnust\Analyse;

use Hnust\Utils\Mysql;

Class Group
{
    //获取用户所在群组列表
    public function getList($sid = null)
    {
        if (is_null($sid)) {
            $sql = 'SELECT `gid`, `name` FROM `group_list`';
            return Mysql::execute($sql);
        } else {
            $sql = 'SELECT `gid`, `name` FROM `group_list` WHERE `gid` IN
                      (SELECT `gid` FROM `group_member` WHERE `sid` = ?)';
            return Mysql::execute($sql, array($sid));
        }
    }

    //获取群组成员
    public function getMember($gid)
    {
        $sql = 'SELECT `s`.`sid`, `s`.`name`, `s`.`phone` FROM `group_member` `g`
                LEFT JOIN `student` `s` ON `s`.`sid` = `g`.`sid`
                WHERE `g`.`gid` = ?';
        return Mysql::execute($sql, array($gid));
    }

    //添加群组
    public function add($name)
    {
        $sql = 'INSERT INTO `group_list`(`name`) VALUES(?)';
        Mysql::execute($sql, array($name));
        return Mysql::lastInsertId();
    }

    //修改群组信息
    public function edit($gid, $name)
    {
        $sql = 'UPDATE `group_list` SET `name` = ? WHERE `gid` = ? LIMIT 1';
        return Mysql::execute($sql, array($name, $gid));
    }

    //删除群组
    public function delete($gid)
    {
        $sql = 'DELETE FROM `group_list` WHERE `gid` = ? LIMIT 1';
        return Mysql::execute($sql, array($gid));
    }

    //添加成员
    public function addMember($gid, $sid)
    {
        $sql = 'INSERT INTO `group_member`(`gid`, `sid`) VALUES(?, ?)';
        return Mysql::execute($sql, array($gid, $sid));
    }

    //删除成员
    public function deleteMember($gid, $sid)
    {
        $sql = 'DELETE FROM `group_member` WHERE `gid` = ? AND `sid` = ?';
        return Mysql::execute($sql, array($gid, $sid));
    }
}