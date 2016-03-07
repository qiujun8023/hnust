<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Mysql;
use Hnust\Utils\Wechat;

Class Auth extends Base
{
    public $key   = '';
    public $sid   = '';
    public $uid   = '';
    public $name  = '游客';
    public $token = '';
    public $rank  = 0;
    protected $module;
    protected $method;
    protected $access;
    protected $isAdmin;
    protected $NMStatus;

    //初始化用户信息
    public function __construct($module, $method)
    {
        parent::__construct($module, $method);
        $this->access  = Config::getAccess($this->module, $this->method);
        $this->isAdmin = ('Admin' === $this->module);
        $this->NMStatus = $this->isAdmin? Config::STATE_ADMIN:Config::STATE_NORMAL;

        //初始化数据
        $this->token  = \Hnust\input('cookie.token', \Hnust\input('token', ''));
        $this->key    = \Hnust\input('get.key', \Hnust\input('post.key', ''));
        $this->sid    = \Hnust\input('get.sid', \Hnust\input('post.sid', ''));
        $this->uid    = \Hnust\input('uid', '');
        $this->passwd = \Hnust\input('passwd', '');
        $this->page   = \Hnust\input('page/d', 1);
        $this->page   = ($this->page < 1)? 1:$this->page;

        //权限验证
        $this->auth();
    }

    //判断用户访问权限
    public function auth()
    {
        if (!empty($this->token)) {
            //获取用户信息
            $sql = 'SELECT `u`.`uid`, `s`.`name`, `u`.`error`, `u`.`rank`, `u`.`loginTime`
                    FROM `user` `u`, `student` `s`
                    WHERE `u`.`uid` = `s`.`sid` AND `u`.`token` = ? LIMIT 1';
            $result = Mysql::execute($sql, array($this->token));

            //token不存在
            if (empty($result)) {
                $this->logout();
                if ($this->access > Config::RANK_VISITOR) {
                    return $this->checkAuth(
                        Config::STATE_NEED_LOGIN,
                        Config::RETURN_NEED_LOGIN,
                        Config::getConfig('token_error_msg')
                    );
                } else {
                    return $this->checkAuth(
                        $this->NMStatus,
                        Config::RETURN_NORMAL,
                        'Success'
                    );
                }
            }

            //获取用户信息
            $this->uid  = $result[0]['uid'];
            $this->name = $result[0]['name'];
            $this->rank = (int)$result[0]['rank'];

            //密码错误次数过多
            if ($result[0]['error'] >= Config::getConfig('max_passwd_error')) {
                $this->logout();
                return $this->checkAuth(
                    Config::STATE_ERROR,
                    Config::RETURN_NEED_LOGIN,
                    Config::getConfig('excessive_error_msg')
                );
            }

            //记住登陆失效
            if ((time() - strtotime($result[0]['loginTime'])) > Config::getConfig('max_remember_time')) {
                $this->logout();
                return $this->checkAuth(
                    Config::STATE_NEED_LOGIN,
                    Config::RETURN_NEED_LOGIN,
                    Config::getConfig('invalid_cookies_msg')
                );
            }

            //账号冻结
            if ($this->rank === Config::RANK_FREEZE) {
                return $this->checkAuth(
                    Config::STATE_FORBIDDEN,
                    Config::RETURN_ALERT,
                    Config::getConfig('freeze_msg')
                );
            }
        }
        //404错误
        if (empty($this->method) || is_null($this->access)) {
            return $this->checkAuth(
                Config::STATE_NOT_FOUND,
                Config::RETURN_ALERT,
                Config::getConfig('not_found_msg')
            );
        }
        //权限不足
        if ($this->rank < $this->access) {
            //登陆后访问
            if (empty($this->token)) {
                return $this->checkAuth(
                    Config::STATE_NEED_LOGIN,
                    Config::RETURN_NEED_LOGIN,
                    Config::getConfig('login_access_msg')
                );
            //无权访问
            } else {
                return $this->checkAuth(
                    Config::STATE_FORBIDDEN,
                    Config::RETURN_ALERT,
                    Config::getConfig('forbid_access_msg')
                );
            }
        }

        //权限不足或学号为空查自己
        if (($this->rank < Config::RANK_OTHER) || empty($this->sid)) {
            $this->sid = $this->uid;
        } elseif (!$this->isAdmin || ('update' !== $this->method)) {
            $student = new \Hnust\Analyse\Student();
            $result = $student->search($this->sid);
            $result = $result['data'];
            //返回错误
            if (empty($result)) {
                return $this->checkAuth(
                    $this->NMStatus,
                    Config::RETURN_ERROR,
                    '未找到相关学号'
                );
            } elseif (1 !== count($result)) {
                return $this->checkAuth(
                    $this->NMStatus,
                    Config::RETURN_ERROR,
                    '学号不唯一，请修改关键词。'
                );
            } else {
                $this->sid = $result[0]['sid'];
            }
        }

        //返回记录
        return $this->checkAuth(
            $this->NMStatus,
            Config::RETURN_NORMAL,
            null
        );
    }

    //记录访问记录并退出
    public function checkAuth($state, $code, $msg)
    {
        Log::recode($this->uid, $this->name, $this->module, $this->method, $this->key, $state);
        if ($code !== Config::RETURN_NORMAL) {
            $this->code = $code;
            $this->msg  = $msg;
            die;
        }
    }

    //密码加密函数
    protected function passwdEncrypt($passwd)
    {
        return \Hnust\passwdEncrypt($this->uid, $passwd);
    }

    //登陆
    public function login()
    {
        $passwd = \Hnust\input('passwd', '');

        //获取用户信息
        $sql = 'SELECT `u`.`error`, `u`.`passwd`, `u`.`rank`, `s`.`name`
                  FROM `user` `u`, `student` `s`
                  WHERE `s`.`sid` = `u`.`uid` AND `s`.`sid` = ? LIMIT 1';
        $result = Mysql::execute($sql, array($this->uid));

        //未查找到用户
        if (empty($result[0])) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '该用户不存在，请检查用户名是否正确或长期未使用（长期未使用账号会被系统自动清理）';
            return false;
        }

        //密码错误次数过多
        if ($result[0]['error'] >= Config::getConfig('max_passwd_error')) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '您的错误次数过多，已被限制使用';
            return false;
        }

        //密码不正确
        if ($result[0]['passwd'] !== $this->passwdEncrypt($passwd)) {
            //错误次数加1
            $sql = 'UPDATE `user` SET `error` = (`error` + 1) WHERE `uid` = ? LIMIT 1';
            Mysql::execute($sql, array($this->uid));

            $this->code = Config::RETURN_ERROR;
            $this->msg  = '您输入的密码有误';
            return false;
        }

        //选取与数据库不重复的token
        while (true) {
            $token  = \Hnust\randStr(32);
            $sql    = 'SELECT COUNT(*) `count` FROM `user` WHERE `token` = ?';
            $result = Mysql::execute($sql, array($token));;
            if ('0' == $result[0]['count']) {
                break;
            }
        }
        //更新用户信息
        $sql = 'UPDATE `user` SET `token` = ?, `error` = 0, `loginTime` = CURRENT_TIMESTAMP WHERE `uid` = ?';
        Mysql::execute($sql, array($token, $this->uid));

        //设置cookies
        setcookie('token' , $token, time() + Config::getConfig('max_remember_time'), Config::WEB_PATH . '/');

        $this->msg  = 'Login Success';
        $this->code = Config::RETURN_RECORD_PAGE;
        $this->info = $this->user();
        return true;
    }

    //注销登陆
    public function logout()
    {
        setcookie('token', '', time() - 86400, Config::WEB_PATH . '/');
        $this->msg  = '注销登陆成功';
        $this->code = Config::RETURN_NEED_LOGIN;
        return true;
    }

    //获取用户信息
    public function user()
    {
        $sql = 'SELECT `u`.`uid`, `u`.`rank`, `u`.`token`, `u`.`apiToken`,
                       `s`.`name`, `s`.`class`, `s`.`major`, `s`.`college`, `s`.`mail`, `s`.`phone`
                FROM `user` `u`, `student` `s` WHERE `u`.`uid` = `s`.`sid` AND `u`.`uid` = ?';
        $result = Mysql::execute($sql, array($this->uid));
        $this->info = empty($result)? array():$result[0];

        //当前周次
        $this->info['week'] = \Hnust\week();

        //用户所在群组
        $group = new \Hnust\Analyse\Group();
        $this->info['group'] = $group->getList($this->uid);

        //获取微信头像
        $wechat = Wechat::getUser($this->uid);
        $this->info['avatar'] = empty($wechat['avatar'])? '':$wechat['avatar'];
        $this->info['weixin'] = empty($wechat['weixinid'])? '':$wechat['weixinid'];

        //数组合并
        $this->info = array_merge($this->info, array(
            'sid'     => $this->sid,
            'time'    => date('H:i:s', time()),
            'isAdmin' => ($this->rank === Config::RANK_ADMIN),
        ));
        return $this->info;
    }

    //更新用户信息
    public function updateUser()
    {
        $oldPasswd = \Hnust\input('oldPasswd', '');
        $newPasswd = \Hnust\input('newPasswd', '');
        $mail = \Hnust\input('mail', '');
        $phone = \Hnust\input('phone', '');

        //修改密码
        if (!empty($oldPasswd) && !empty($newPasswd)) {
            //验证旧密码
            $sql = 'SELECT * FROM `user` WHERE `uid` = ? AND `passwd` = ? LIMIT 1';
            $result = Mysql::execute($sql, array($this->uid, $this->passwdEncrypt($oldPasswd)));

            //原密码错误
            if (empty($result)) {
                //错误次数加1
                $sql = 'UPDATE `user` SET `error` = (`error` + 1) WHERE `uid` = ? LIMIT 1';
                Mysql::execute($sql, array($this->uid));

                $this->code = Config::RETURN_ALERT;
                $this->msg  = '原密码错误。';
                return false;
            }

            //检查弱密码
            $sql = 'SELECT COUNT(*) `count` FROM `weak` WHERE `md5` = ? LIMIT 1';
            $result = Mysql::execute($sql, array($newPasswd));
            if ('0' != $result[0]['count']) {
                $this->code = Config::RETURN_ALERT;
                $this->msg  = '您的密码过于简单。';
                return false;
            }

            $sql = 'UPDATE `user` SET `passwd` = ?, `error` = 0 WHERE `uid` = ?';
            Mysql::execute($sql, array($this->passwdEncrypt($newPasswd), $this->uid));
            $this->data = '修改成功，请牢记您的密码。';
        }

        //修改其他数据
        $sql = "UPDATE `user` `u`,`student` `s`
                SET `s`.`mail` = IF(? = '', `s`.`mail`, ?),
                    `s`.`phone` = IF(length(?) <> 11, `s`.`phone`, ?)
                WHERE `s`.`sid` = `u`.`uid` AND `u`.`uid` = ?";
        Mysql::execute($sql, array($mail, $mail, $phone, $phone, $this->uid));
        \Hnust\Utils\Wechat::updateUser($this->uid);

        $this->msg  = '系统提示';
        $this->data = empty($this->data)? '已保存您的修改。':$this->data;
        $this->code = Config::RETURN_CONFIRM;
        return true;
    }
}