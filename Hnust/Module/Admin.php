<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;

require_once __DIR__ . '/../../library/Qiniu.php';

class Admin extends Auth
{
    //数据统计
    public function statistic()
    {
        $this->data = array();

        //概略
        $sql = "SELECT `time`, COUNT(*) `total`, SUM(IF(`state` = 4, 1, 0)) `loss`,
                  SUM(IF(`state` IN (2, 3) OR (`uid` != '' AND `name` = '游客'), 1, 0)) `error`
                FROM `logs` WHERE DATE_SUB(CURDATE(), INTERVAL 1 MONTH) <= DATE(`time`)
                GROUP BY DATE(`time`)";
        $this->data['sum'] = Mysql::execute($sql);

        //IP频率
        $sql = "SELECT `ip`, `location`, COUNT(*) AS `count` FROM `logs`
                WHERE `ip` <> ''
                AND DATE_SUB(CURDATE(), INTERVAL 15 DAY) <= DATE(`time`)
                GROUP BY `ip`";
        $this->data['ip'] = Mysql::execute($sql);
        if (is_array($this->data['ip']) && (count($this->data['ip']) > 60)) {
            usort($this->data['ip'], function($a, $b){
                return ($a['count'] > $b['count'])? -1:1;
            });
            $this->data['ip'] = array_slice($this->data['ip'], 0, 60);
        }

        //关键词统计
        $sql = "SELECT `key`, COUNT(*) `count` FROM `logs`
                WHERE `key` <> ''
                AND DATE_SUB(CURDATE(), INTERVAL 15 DAY) <= DATE(`time`)
                GROUP BY `key`";
        $this->data['key'] = Mysql::execute($sql);
        if (is_array($this->data['key']) && (count($this->data['key']) > 60)) {
            usort($this->data['key'], function($a, $b){
                return ($a['count'] > $b['count'])? -1:1;
            });
            $this->data['key'] = array_slice($this->data['key'], 0, 60);
        }

        //模块使用统计
        $sql = "SELECT `module`, `method`, COUNT(*) `count` FROM `logs`
                WHERE `module` <> '' AND `method` <> '' AND `state` = 0
                AND DATE_SUB(CURDATE(), INTERVAL 15 DAY) <= DATE(`time`)
                GROUP BY `module`, `method`";
        $this->data['module'] = Mysql::execute($sql);
    }

    //日志记录
    public function logs()
    {
        $type = \Hnust\input('type', '');
        $key  = \Hnust\input('key', '');

        //其他日志则查询数据库
        $sql = "SELECT `uid`, `name`, `ip`, `location`, `ua`, `url`, `state`, `time` FROM `logs` WHERE 1 ";
        if ('error' === $type) {
            $sql .= "AND `state` IN (2, 3) OR (`uid` != '' AND `name` = '游客') ";
        } elseif ('visitor' === $type) {
            $sql .= "AND `uid` = '' ";
        } elseif ('loss' === $type) {
            $sql .= "AND `state` = 4 ";
        } elseif ('wechat' === $type) {
            $sql .= "AND `state` = 6 ";
        } elseif ('api' === $type) {
            $sql .= "AND `state` = 7 ";
        } elseif ('admin' === $type) {
            $sql .= "AND `state` = 9 ";
        } else {
            $type = 'all';
        }
        $sql .= "AND CONCAT_WS(',', `uid`, `name`, `ip`, `location`, `ua`, `url`) LIKE ?
                 ORDER BY `time` DESC";

        $result = Mysql::paging($sql, array("%{$key}%"), $this->page);
        $this->data = $result['data'];
        $this->info = array_merge($result['info'], array(
            'type' => $type,
            'key'  => $key
        ));
    }

    //系统配置
    public function setting()
    {
        $type = \Hnust\input('type', '');
        //更新参数
        if ('update' === $type) {
            $method = \Hnust\input('method', '');
            $value  = \Hnust\input('value', '');
            $sql    = 'UPDATE `ini` SET `value` = ? WHERE `method` = ?';
            Mysql::execute($sql, array($value, $method));
            //更新缓存
            $cache = new Cache();
            $cache->delete('config');
            $this->code = Config::RETURN_NORMAL;
        //获取参数列表
        } else {
            $sql = 'SELECT * FROM `ini`';
            $this->data = Mysql::execute($sql);
        }
    }

    //用户管理
    public function user()
    {
        $type = \Hnust\input('type', '');
        $uid  = \Hnust\input('uid', '');

        //添加用户
        if ('add' === $type) {
            $rank = \Hnust\input('rank', '');
            $sql = "SELECT * FROM
                    (SELECT COUNT(*) `user` FROM `user` WHERE `uid` = ?) `a` ,
                    (SELECT COUNT(*) `student` FROM `student` WHERE `sid` = ?) `b`";
            $result = Mysql::execute($sql, array($uid, $uid));
            if (empty($result)) {
            	return $this->msg = '添加失败。';
            } elseif ($result[0]['user'] > 0) {
            	return $this->msg = '添加失败，用户已存在。';
            } elseif ($result[0]['student'] <= 0) {
            	return $this->msg = '添加失败，未找到对应学号。';
            }
            $randPasswd = \Hnust\randStr(6);
            $passwd = \Hnust\passwdEncrypt($uid, md5($randPasswd));

            //选取与数据库不重复的token
            while (true) {
                $token  = \Hnust\randStr(32);
                $sql    = 'SELECT COUNT(*) `count` FROM `user` WHERE `token` = ? ';
                $result = Mysql::execute($sql, array($token));
                if ('0' == $result[0]['count']) {
                    break;
                }
            }

            //添加新用户
            $sql = "INSERT INTO `user`(`uid`, `passwd`, `token`, `apiToken`, `loginTime`, `regTime`, `rank`)
                      VALUES (?, ?, ?, ?, NOW(), NOW(), ?)";
            if (Mysql::execute($sql, array($uid, $passwd, $token, $token, $rank))) {
                $push = new \Hnust\Analyse\Push();
                $push->add($uid, 1, '系统安全提示：', '您的密码过弱，请立即修改密码！', '#/user');
	            $this->msg  = '成功添加网站用户。';
	            $this->data = "新用户 {$uid} 的密码为 {$randPasswd}";
                \Hnust\Utils\Wechat::createUser($uid);
            } else {
            	$this->msg  = '添加失败，数据库有误。';
            }

        //解锁用户
        } elseif ('unlock' === $type) {
            $sql = 'UPDATE `user` SET `error` = 0 WHERE `uid` = ? LIMIT 1';
            Mysql::execute($sql, array($uid));
            $this->code = Config::RETURN_NORMAL;

        //修改用户权限
        } elseif ('change' === $type) {
            $rank = \Hnust\input('rank', '');
            if ($this->uid == $uid) {
                return $this->msg  = '不能修改自己的权限!';
            }
            $sql = 'UPDATE `user` SET `rank` = ? WHERE `uid` = ? LIMIT 1';
            Mysql::execute($sql, array($rank, $uid));
            $this->code = Config::RETURN_NORMAL;

        //重置用户密码
        } elseif ('reset' === $type) {
            $randPasswd = \Hnust\randStr(6);
            $passwd = \Hnust\passwdEncrypt($uid, md5($randPasswd));
            $sql    = 'UPDATE `user` SET `passwd` = ?, `error` = 0 WHERE `uid` = ? LIMIT 1';
            Mysql::execute($sql, array($passwd, $uid));
            $this->msg  = '重置密码成功';
            $this->data = "已成功重置用户密码为{$randPasswd}";

        //删除用户
        } elseif ('delete' === $type) {
            if ($uid == $this->uid) {
                return $this->msg = '不能删除自己!';
            }
            $sql = 'DELETE FROM `user` WHERE `uid` = ? LIMIT 1';
            Mysql::execute($sql, array($uid));
            \Hnust\Utils\Wechat::deleteUser($uid);
            $this->msg = '删除成功。';

        //获取最近使用用户
        } else {
            $this->data = array();

            //获取最近使用用户
            $sql = "SELECT `uid`, `name`, `time`, count(*) count
                    FROM (
                        SELECT `uid`, `name`, `time` FROM `logs`
                        WHERE `uid` != '' AND `name` != '游客'
                        ORDER BY `time` DESC
                    ) `api`
                    GROUP BY `uid` ORDER BY `time` DESC";
            $this->data['last'] = Mysql::execute($sql);

            //获取全部用户
            $sql  = 'SELECT `u`.`uid`, `s`.`name`, `u`.`error`,`u`.`rank`, `u`.`loginTime`,
                     `u`.`regTime`, IF(`a`.`apiCount` IS NULL, 0, `a`.`apiCount`) `apiCount`
                     FROM `student` `s`, `user` `u`
                     LEFT JOIN (
                        SELECT `uid`, COUNT(*) `apiCount` FROM `logs` WHERE `state` = 7 GROUP BY `uid`
                     ) `a` ON `u`.`uid` = `a`.`uid`
                     WHERE `s`.`sid` = `u`.`uid`
                     ORDER BY `u`.`rank` DESC, `a`.`apiCount` DESC, `u`.`loginTime` DESC';
            $result = Mysql::execute($sql);
            //计算用户状态
            $maxError = Config::getConfig('max_passwd_error');
            for ($i = 0; $i < count($result); $i++) {
                if ('0' == $result[$i]['error']) {
                    $result[$i]['state'] = '正常';
                } elseif ($maxError == $result[$i]['error']) {
                    $result[$i]['state'] = '冻结';
                } else {
                    $result[$i]['state'] = "{$result[$i]['error']}次错误";
                }
            }
            $this->data['user'] = $result;
        }
    }

    //APP信息
    public function app()
    {
        $type = \Hnust\input('type', '');
        //发布App
        if ('put' === $type) {
            $version = \Hnust\input('version', '');
            $develop = \Hnust\input('develop', '');
            $intro   = \Hnust\input('intro', '');
            $url     = \Hnust\input('url', '');
            $size    = \Hnust\input('size', '');
            $sql     = "INSERT INTO `app` (`version`, `develop`, `intro`, `url`, `size`) VALUES (?, ?, ?, ?, ?);";
            $result  = Mysql::execute($sql, array($version, $develop, $intro, $url, $size));
            if ($result) {
                $this->code = Config::RETURN_CONFIRM;
                $this->msg  = '系统消息';
                $this->data = "{$version}版本发布成功。";
            } else {
                $this->code = Config::RETURN_ERROR;
                $this->msg  = "{$version}版本发布失败。";
            }

        //获取APP统计记录
        } else {
            $this->data = $this->info = array();
            $sql = "SELECT `name`, `uid`, `ua`, `time`
                    FROM (SELECT * FROM `logs` ORDER BY `time` DESC) `a`
                    WHERE `ua` LIKE 'hnust%' AND `uid` != '' AND `name` != '游客'
                    GROUP BY `uid` ORDER BY `time` DESC";
            $result = Mysql::execute($sql);
            foreach ($result as $item) {
                $clientInfo = explode('   ',$item['ua']);
                $this->data[] = array(
                    'name'      => $item['name'],
                    'uid'       => $item['uid'],
                    'time'      => $item['time'],
                    'version'   => $clientInfo[1],
                    'model'     => $clientInfo[2],
                    'system'    => $clientInfo[3],
                    'network'   => $clientInfo[4] . ' / ' . $clientInfo[5],
                    'resolution'=> $clientInfo[6],
                );
            }
            //获取七牛Token
            $bucket = 'ypan';
            $accessKey = 'HDFk_P0orhoc9hmQS32-4uKxhQVZI67Cr3mgF-kv';
            $secretKey = 'ERNsjIybLKO45K0sNoYXWYhJu_A0O5f34QXvwWUc';
            $auth = new \Qiniu\Auth($accessKey, $secretKey);
            $this->info['qiniu'] = $auth->uploadToken($bucket);
        }
    }

    //消息推送
    public function push()
    {
        $type  = \Hnust\input('type', '');
        $key   = \Hnust\input('key', '');
        $id    = \Hnust\input('id', '');
        $uid   = \Hnust\input('uid', '');
        $mode  = \Hnust\input('mode', '');
        $title = \Hnust\input('title', '');
        $content = \Hnust\input('content', '');
        $success = \Hnust\input('success', '');

        //初始化推送对象
        $push = new \Hnust\Analyse\Push();

        //添加推送
        if ('add' === $type) {
            $data = $push->add($uid, $mode, $title, $content, $success);
            $push->socket($data);
            $this->msg  = '系统提示';
            $this->data = '添加推送成功。';

        //重置推送
        } elseif ('reset' === $type) {
            $data = $push->reset($id);
            if ('0' == $data['received']) {
                $push->socket($data);
            }
            $this->code = Config::RETURN_NORMAL;

        //删除推送
        } elseif ('delete' === $type) {
            $push->delete($id);
            $this->code = Config::RETURN_NORMAL;

        //获取推送列表
        } else {
            $sql = "SELECT `p`.`id`, `p`.`uid`, `s`.`name`, `p`.`type`, `p`.`title`, `p`.`content`,
                    `p`.`success`, `p`.`received`, `p`.`time`, `p`.`upTime` FROM `push` `p`
                    LEFT JOIN `student` `s` ON `s`.`sid` = `p`.`uid`
                    WHERE CONCAT_WS(',', `p`.`uid`, `s`.`name`, `p`.`title`, `p`.`content`, `p`.`success`) LIKE ?
                    ORDER BY `p`.`id` DESC";
            $result = Mysql::paging($sql, array("%{$key}%"), $this->page);
            $this->data = $result['data'];
            $this->info = array_merge($result['info'], array(
                'type' => $type,
                'key'  => $key
            ));
        }
    }

    //数据更新
    public function update()
    {
        $type   = \Hnust\input('type', '');
        $sid    = \Hnust\input('sid', '');
        $cookie = \Hnust\input('cookie', '');
        $cache  = new Cache('update');

        //更新缓存数据
        $cacheData = $cache->get($type);
        $cacheData = empty($cacheData)? array():$cacheData;
        if (!empty($sid)) {
            $cacheData['sid'] = $sid;
        }
        if (!empty($cookie)) {
            $cacheData['cookie'] = $cookie;
        }
        $cache->set($type, $cacheData);

        $url = Config::getConfig('local_base_url') . 'update/' . $type;
        try {
            new Http(array(
                CURLOPT_URL      => $url,
                CURLOPT_TIMEOUT  => 3,
            ));
        } catch (\Exception $e) {
            //pass
        }

        $this->msg = '已加入更新队列，请通过实时日志查看更新进度。';
    }
}