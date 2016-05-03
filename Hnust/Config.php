<?php
namespace Hnust;

use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;

class Config
{
    //缓存
    protected static $cache = null;
    //访问控制数组
    protected static $access;
    //配置文件数组
    protected static $config;

    //调试模式
    const DEBUG = false;

    //Mysql相关
    const MYSQL_HOST = '数据库用户名';
    const MYSQL_PORT = '数据库端口';
    const MYSQL_DB   = '数据库库名';
    const MYSQL_USER = '数据库用户';
    const MYSQL_PWD  = '数据库密码';
    //数据库检测重连时间
    const RECHECK_FREQUENCY = 300;

    //Cache相关
    const CACHE_HOST = 'localhost';
    const CACHE_PORT = '6379';

    //权限控制缓存时间
    const ACCESS_CACHE_TIME = 86400;
    const CONFIG_CACHE_TIME = 86400;

    //缓存文件路径
    const TMP_PATH  = '/tmp';
    //程序根目录地址
    const BASE_PATH = '/home/qiujun/hnust';
    //程序相对WEB目录
    const WEB_PATH  = '';
    //资料文件路径
    const INFO_PATH = '/runtime/info';
    //临时文件路径
    const TEMP_PATH = '/runtime/temp';
    //日志记录目录
    const LOGS_PATH = '/runtime/logs';
    //字体路径
    const FONT_PATH = '/static/src/font/文泉驿等宽微米黑.ttf';

    //返回值相关
    const RETURN_NEED_LOGIN    = -2; //需要登陆
    const RETURN_ERROR         = -1; //返回错误
    const RETURN_NORMAL        =  0; //正常
    const RETURN_ALERT         =  1; //弹出提示信息
    const RETURN_CONFIRM       =  2; //确认窗口
    const RETURN_RECORD_PAGE   =  3; //返回记录页面
    const RETURN_NEED_PASSWORD =  4; //输入密码

    //记录值相关
    const STATE_NORMAL     = 0; //正常
    const STATE_NEED_LOGIN = 1; //需要登陆
    const STATE_FORBIDDEN  = 2; //禁止访问
    const STATE_ERROR      = 3; //账号异常
    const STATE_NOT_FOUND  = 4; //页面未找到
    const STATE_WECHAT     = 6; //微信调用
    const STATE_API        = 7; //API调用
    const STATE_UPDATE     = 8; //数据更新
    const STATE_ADMIN      = 9; //后台页面

    //权限控制相关
    const RANK_FREEZE    = -1; //冻结
    const RANK_VISITOR   =  0; //游客
    const RANK_PERSON    =  1; //个人
    const RANK_GROUP     =  2; //班级
    const RANK_STATISTIC =  3; //统计
    const RANK_OTHER     =  4; //他人
    const RANK_DATA      =  8; //资料
    const RANK_ADMIN     =  9; //后台管理

    public static function cacheInit()
    {
        //获取缓存对象
        if (null == self::$cache) {
            self::$cache = new Cache();
        }
        return self::$cache;
    }

    //最大化运行
    public static function fullLoad()
    {
        //不超时
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
        //忽略用户浏览器的关闭
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        //最大运行时间
        if (function_exists('ini_set')) {
            ini_set('max_execution_time', '0');
        }
    }

    //获取访问权限
    public static function getAccess($module = '', $method = '')
    {
        self::cacheInit();
        //读取缓存
        if (!self::DEBUG && empty(self::$access)) {
            self::$access = self::$cache->get('access');
        }
        //数据库中获取所有配置文件
        if (empty(self::$access) || !is_array(self::$access)) {
            self::$access = array();

            $sql = 'SELECT `module`, `method`, `rank` FROM `access`';
            $result = Mysql::execute($sql);
            if (false === $result) {
                throw new \Exception('服务器数据库异常', Config::RETURN_ERROR);
            }
            foreach ($result as $item) {
                self::$access[$item['module']][$item['method']] = (int)$item['rank'];
            }

            //缓存配置文件
            self::$cache->set('access', self::$access, Config::ACCESS_CACHE_TIME);
        }

        if (empty($module)) {
            return self::$access;
        } else if (empty($method)) {
            return isset(self::$access[$module])? self::$access[$module]:array();
        } else {
            return isset(self::$access[$module][$method])? self::$access[$module][$method]:null;
        }
    }

    //获取配置文件
    public static function getConfig($method = '')
    {
        self::cacheInit();
        //读取缓存
        if (!self::DEBUG && empty(self::$config)) {
            self::$config = self::$cache->get('config');
        }

        //数据库中获取所有配置文件
        if (empty(self::$config) || !is_array(self::$config)) {
            self::$config = array();

            $sql = 'SELECT `method`, `value` FROM `ini`';
            $result = Mysql::execute($sql);
            if (false === $result) {
                throw new \Exception('服务器数据库异常', Config::RETURN_ERROR);
            }
            foreach ($result as $item) {
                self::$config[$item['method']] = $item['value'];
            }

            //缓存配置文件
            self::$cache->set('config', self::$config, Config::CONFIG_CACHE_TIME);
        }

        //返回想要的内容
        if (empty($method)) {
            return self::$config;
        } elseif (isset(self::$config[$method])) {
            return self::$config[$method];
        } else {
            return false;
        }
    }
}