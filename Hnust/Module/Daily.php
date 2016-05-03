<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;
use Hnust\Utils\Wechat;

class Daily extends Base
{
    //删除过期文件
    protected function cleanDir($path, $saveTime)
    {
        //清理日志文件
        $count  = 0;
        $handle = opendir($path);
        while ($handle && (false !== ($file = readdir($handle)))) {
            if ('.' === $file[0]) {
                continue;
            }
            if (false === ($time = filemtime($path . $file))) {
                continue;
            }
            if (!is_file($path . $file)) {
                continue;
            }
            if (time() - $time > $saveTime) {
                $count++;
                unlink($path . $file);
            }
        }
        closedir($handle);
        return $count;
    }

    //删除用户
    public function user()
    {
        $saveTime = Config::getConfig('save_account_time') + (Config::getConfig('max_remember_time') * 2);
        $saveDay  = $saveTime / 86400;
        $sql = "SELECT `u`.`uid`, `s`.`name` FROM `user` `u`
                LEFT JOIN `student` `s` ON `s`.`sid` = `u`.`uid`
                WHERE `u`.`rank` < ?
                AND DATE_SUB(CURDATE(), INTERVAL {$saveDay} DAY) >= DATE(`u`.`lastTime`)";
        ;
        if (!($users = Mysql::execute($sql, array(Config::RANK_ADMIN)))) {
            return;
        }

        //删除微信及Token
        $names = array();
        $cache = new Cache('auth');
        foreach ($users as $user) {
            $names[] = $user['name'];
            Wechat::deleteUser($user['uid']);
            $tokens = $cache->smembers($user['uid']);
            foreach ($tokens as $token) {
                $cache->hdelete('token', $token);
            }
            $cache->delete($user['uid']);
        }

        //从数据库中删除
        $sql = "DELETE FROM `user` WHERE `rank` < ?
                AND DATE_SUB(CURDATE(), INTERVAL {$saveDay} DAY) >= DATE(`lastTime`)";
        Mysql::execute($sql, array(Config::RANK_ADMIN));
        return '删除用户' . implode('、', $names);
    }

    //删除失效Token
    public function token()
    {
        $num   = 0;
        $sql   = 'SELECT `uid` FROM `user`';
        $users = Mysql::execute($sql);
        $cache = new Cache('auth');
        $remember = Config::getConfig('max_remember_time');
        foreach ($users as $user) {
            $tokens = $cache->smembers($user['uid']);
            if (empty($tokens)) {
                continue;
            }
            foreach ($tokens as $token) {
                $loginInfo = $cache->hget('token', $token);
                if ((time() - $loginInfo['time']) > $remember) {
                    $num++;
                    $cache->hdelete('token', $token);
                    $cache->sdelete($user['uid'], $token);
                }
            }
        }
        return "删除Token{$num}条";
    }

    //删除失效提醒
    public function push()
    {
        $sql = 'DELETE FROM `push`
                WHERE `uid` NOT IN (SELECT `uid` FROM `user`)
                OR `time` < DATE_SUB(NOW(), INTERVAL 1 MONTH)';
        $num = Mysql::execute($sql);
        return "删除提醒{$num}条";
    }

    //删除过期日志或更新日志
    public function log()
    {
        //清理日志文件
        $logSuffix = '.log';
        $logLength = 5000;
        $path   = Config::BASE_PATH . Config::LOGS_PATH . '/';
        $handle = opendir($path);
        while ($handle && (false !== ($file = readdir($handle)))) {
            $suffix = strrchr(strtolower($file), $logSuffix);
            if ($suffix !== $logSuffix) {
                continue;
            }
            $content = file_get_contents($path . $file);
            $content = explode("\n", $content);
            $count   = count($content);
            if ($count > $logLength) {
                $content = array_slice($content, $count - $logLength);
                $content = implode("\n", $content);
                file_put_contents($path . $file, $content);
            }
        }
        closedir($handle);

        //清除日志
        $sql = "DELETE FROM `logs`
                WHERE `uid` IN ('1305010117', '1305010119', '1305020233')
                OR (`ip` != ? AND `location` LIKE '%阿里%')
                OR`time` < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $num = Mysql::execute($sql, array(Config::getConfig('local_out_ip')));
        $sql = "DELETE FROM `logs` WHERE `uid` != ''
                AND `uid` NOT IN (SELECT `uid` FROM `user`)";
        $num += Mysql::execute($sql);
        $log = "清除日志{$num}条";

        //更新日志
        $sql = "UPDATE `student` `s`, `logs` `l`
                SET `l`.`key` = `s`.`name`
                WHERE `l`.`key` = `s`.`sid`";
        $num = Mysql::execute($sql);
        $sql = "UPDATE `logs` SET `key` = ''
                WHERE `key` in (`uid`, `name`, '湖南科技大学', '计算机科学与工程学院')";
        $num += Mysql::execute($sql);
        return $log . "，更新日志{$num}条";
    }

    //删除临时文件
    public function temp()
    {
        $saveTime = 604800;
        $path     = Config::BASE_PATH . Config::TEMP_PATH . '/';
        $count    = $this->cleanDir($path, $saveTime);
        return "删除临时文件{$count}个";
    }

    //日常任务
    public function daily()
    {
        $result  = [];
        $methods = array('age', 'token', 'push', 'log', 'temp');
        foreach ($methods as $method) {
            $result[] = $this->$method();
        }

        //写入日志文件
        Log::file('daily', implode('，', array_filter($result)));
    }
}