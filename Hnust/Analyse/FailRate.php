<?php

namespace Hnust\Analyse;

use Hnust\Utils\Mysql;

Class FailRate
{
    //自动补全
    public function complet($key)
    {
        $sql = 'SELECT DISTINCT `course` FROM `failRate`
                WHERE `course` like ? AND `all` > 10 ORDER BY `course` LIMIT 20';
        $result = Mysql::execute($sql, array($key . '%'));

        $keys = array();
        foreach ($result as $item) {
            $keys[] = $item['course'];
        }
        return $keys;
    }

    //返回结果集
    public function search($key)
    {
        $sql = 'SELECT IF(`name` = ?, `course`, `name`) `name`, `course`, `all`, `fail`, `rate`
                FROM `failRate` WHERE (`course` = ? OR `name` = ?) AND `all` > 10';
        return Mysql::execute($sql, array($key, $key, $key));
    }
}