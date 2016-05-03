<?php

namespace Hnust\Analyse;

use Hnust\Utils\Mysql;

Class Group
{
    //获取共享群组
    public function belong($sid, $share = 1)
    {
        $sql = 'SELECT `gid`, `name` FROM `group_list`
                WHERE `share` = ? AND `gid` IN
                  (SELECT DISTINCT `gid` FROM `group_member` WHERE `sid` = ?)';
        return Mysql::execute($sql, array($share, $sid));
    }

    //获取所有群组
    public function get($gid = null)
    {
        $sql = 'SELECT `g`.`gid`, `g`.`name`, `g`.`share`,
                `s`.`name` `creator`, `g`.`time` FROM `group_list` `g`
                LEFT JOIN `student` `s` ON `s`.`sid` = `g`.`creator`';
        if (isset($gid)) {
            $sql .= ' WHERE `gid` = ?';
            $res  = Mysql::execute($sql, array($gid));
            return empty($res)? false:$res[0];
        }
        return Mysql::execute($sql);
    }

    //新增群组
    public function add($name, $share, $creator)
    {
        $sql = 'INSERT INTO `group_list`(`name`, `share`, `creator`)
                VALUES(?, ?, ?)';
        $arr = array($name, $share, $creator);
        if (Mysql::execute($sql, $arr)) {
            $gid = Mysql::lastInsertId();
            $sql = 'INSERT INTO `group_member`(`gid`, `sid`)
                    SELECT ?, `sid` FROM `student` WHERE `class` = ?';
            Mysql::execute($sql, array($gid, $name));
            return $gid;
        } else {
            return false;
        }
    }

    //编辑群组
    public function edit($gid, $name, $share, $creator)
    {
        $sql = 'UPDATE `group_list` SET `name` = ?, `share` =? , `creator` =?
                WHERE `gid` = ? LIMIT 1';
        $arr = array($name, $share, $creator, $gid);
        return Mysql::execute($sql, $arr);
    }

    //删除群组
    public function delete($gid)
    {
        $sql = 'DELETE FROM `group_list` WHERE `gid` = ? LIMIT 1;
                DELETE FROM `group_member` WHERE `gid` =?';
        Mysql::execute($sql, array($gid, $gid));
    }

    //获取群组成员
    public function getMember($gid)
    {
        $sql = 'SELECT `s`.`sid`, `s`.`name`, `s`.`class`,
                  `s`.`phone`, `s`.`birthday` FROM `group_member` `g`
                LEFT JOIN `student` `s` ON `s`.`sid` = `g`.`sid`
                WHERE `g`.`gid` = ? ORDER BY `s`.`sid`';
        return Mysql::execute($sql, array($gid));
    }

    //添加群组成员
    public function addMember($gid, $sid)
    {
        $sql = 'INSERT INTO `group_member`(`gid`, `sid`) VALUES(?, ?)';
        return Mysql::execute($sql, array($gid, $sid));
    }

    //删除群组成员
    public function deleteMember($gid, $sid)
    {
        $sql = 'DELETE FROM `group_member` WHERE `gid` = ? AND `sid` = ?';
        return Mysql::execute($sql, array($gid, $sid));
    }
}