<?php

namespace Hnust\Utils;

use Hnust\Config;

class Mysql
{
    public static $count = 0;
    protected static $dbh = null;
    protected static $lastCheckTime = 0;

    //连接Mysql
    protected static function connection()
    {
        try {
            $pdoInfo = 'mysql:host=' . Config::MYSQL_HOST . ';port=' . Config::MYSQL_PORT . ';dbname=' . Config::MYSQL_DB . ';charset=utf8';
            self::$dbh = new \PDO($pdoInfo, Config::MYSQL_USER, Config::MYSQL_PWD);
        } catch (\PDOException $e) {
            throw new \Exception('数据库连接失败：' . $e->getMessage());
        }
        return self::$dbh;
    }

    //返回Mysql的连接
    public static function getCon()
    {
        return self::ensureConnection();
    }

    //返回最后插入的值
    public static function lastInsertId()
    {
        try {
            return self::$dbh->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    //执行Mysql语句
    public static function execute($sql, $array = array())
    {
        self::$count++;
        self::ensureConnection();
        $st = self::$dbh->prepare($sql);
        try {
            if (false === $st->execute($array)) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        if (0 === stripos($sql, 'SELECT')) {
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } else if (0 === stripos($sql, 'UPDATE')) {
            return $st->rowCount();
        } else if (0 === stripos($sql, 'DELETE')) {
            return $st->rowCount();
        } else {
            return true;
        }
    }

    //执行多条Mysql语句
    public static function executeMultiple($sql, $array)
    {
        self::$count++;
        self::ensureConnection();
        $st = self::$dbh->prepare($sql);
        try {
            foreach ($array as $item) {
                if (false === $st->execute($item)) {
                    return false;
                }
            }
        } catch (PDOException $e) {
            return false;
        }
        return true;
    }

    //分页
    public function paging($sql, $array = array(), $page = 1, $per = 30)
    {
        //获取总条数
        $tmpSql = preg_replace('/SELECT .*? FROM/is', 'SELECT COUNT(*) `nums` FROM', $sql);
        if (!($result = self::execute($tmpSql, $array))) {
            return false;
        }
        $nums = $result[0]['nums'];

        //计算总页数
        $pages = ceil($nums/$per);

        //确定当前页
        if (!is_numeric($page) || ($page < 1)) {
            $page = 1;
        } elseif (($pages > 0) && ($page > $pages)) {
            $page = $pages;
        }

        //确定起始位置
        $start = ($page - 1) * $per;

        //返回
        $data = array();
        if ($nums > 0) {
            $sql .= " LIMIT {$start}, {$per}";
            $data = self::execute($sql, $array);
        }

        return array(
            'data' => $data,
            'info' => array(
                'per'   => $per,
                'nums'  => $nums,
                'page'  => $page,
                'pages' => $pages,
                'start' => $start
            )
        );
    }

    //检查数据库连接
    protected static function ensureConnection($type = 0)
    {
        if (is_null(self::$dbh)) {
            self::$lastCheckTime = time();
            return self::connection();
        }

        try {
            if (1 === $type) {
                self::$dbh->getAttribute(PDO::ATTR_SERVER_INFO);

            } else if ((time() - self::$lastCheckTime) > Config::RECHECK_FREQUENCY) {
                self::$lastCheckTime = time();
                self::$dbh->query('SELECT 1');
            }

        } catch (PDOException $e) {
            if (((int)$e->errorInfo[1] == 2006) && ($e->errorInfo[2] == 'MySQL server has gone away')) {
                return self::connection();
            }
        }

        return self::$dbh;
    }
}