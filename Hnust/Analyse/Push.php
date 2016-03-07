<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;

Class Push
{
    //推送
    public function socket($data)
    {
        $msg  = json_encode(array('push' => $data), true);
        $baseUrl = Config::getConfig('local_base_url');
        try {
            $http = new Http(array(
                CURLOPT_URL        => $baseUrl . 'socket/' . $data['uid'],
                CURLOPT_POSTFIELDS => $msg,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($msg)
                ),
                CURLOPT_TIMEOUT    => 1,
            ));
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    //获取推送详细信息
    public function info($id)
    {
        $sql = "SELECT `id`, `uid`, `type`, `title`, `content`, `success`, `received`, `time`, `upTime`
                FROM `push` WHERE `id` = ? LIMIT 1";
        $data = Mysql::execute($sql, array($id));
        return empty($data)? false:$data[0];
    }

    //添加推送消息
    public function add($uid, $type, $title, $content, $success)
    {
        //判断是否存在相同的推送
        $sql = 'SELECT `id` FROM `push` WHERE `uid` = ? AND `type` = ? AND `title` = ?
                AND `content` = ? AND `success` = ? AND `received` = 0 LIMIT 1';
        $data = Mysql::execute($sql, array($uid, $type, $title, $content, $success));
        if (!empty($data)) {
            $id = $data[0]['id'];
            return $this->info($id);
        }

        //插入新推送
        $sql = 'INSERT INTO `push`(`uid`, `type`, `title`, `content`, `success`, `time`)
                VALUES(?, ?, ?, ?, ?, CURRENT_TIMESTAMP)';
        if (Mysql::execute($sql, array($uid, $type, $title, $content, $success))) {
            $id = Mysql::lastInsertId();
            return $this->info($id);
        }
        return false;
    }

    //重置推送
    public function reset($id)
    {
        $sql = "UPDATE `push` SET `received` = IF(`received` = 0, 1, 0) WHERE `id` = ?";
        Mysql::execute($sql, array($id));
        return $this->info($id);
    }

    //删除一条推送
    public function delete($id)
    {
        $sql = 'DELETE FROM `push` WHERE `id` = ?';
        return Mysql::execute($sql, array($id));
    }

    //更新推送消息
    public function achieve($uid, $id)
    {
        $sql = 'UPDATE `push` SET `received` = 1 WHERE `uid` = ? AND `id` = ?';
        return Mysql::execute($sql, array($uid, $id));
    }

    //获取一条推送消息
    public function fetch($uid)
    {
        $sql = "SELECT `id`, `uid`, `type`, `title`, `content`, `success`, `received`, `time`, `upTime`
                FROM `push` WHERE `uid` = ? AND `received` = 0 ORDER BY `id` ASC LIMIT 1";
        $data = Mysql::execute($sql, array($uid));
        return $data? $data[0]:array();
    }
}