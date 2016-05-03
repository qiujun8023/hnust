<?php

namespace Hnust\Module;

use Hnust\Config;
use Hnust\Utils\Log;
use Hnust\Utils\Cache;
use Hnust\Utils\Mysql;

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
    protected $NMStatus;
    protected $authCache;

    //初始化用户信息
    public function __construct($module, $method)
    {
        parent::__construct($module, $method);
        $this->access  = Config::getAccess($this->module, $this->method);
        $this->NMStatus = ('Admin' === $this->module)? Config::STATE_ADMIN:Config::STATE_NORMAL;

        //初始化数据
        $this->token  = \Hnust\input('cookie.token', \Hnust\input('token'));
        $this->key    = \Hnust\input('get.key', \Hnust\input('post.key'));
        $this->sid    = \Hnust\input('get.sid', \Hnust\input('post.sid'));
        $this->uid    = \Hnust\input('uid');
        $this->passwd = \Hnust\input('passwd');
        $this->page   = \Hnust\input('page/d', 1);
        $this->page   = ($this->page < 1)? 1:$this->page;

        //初始化缓存
        $this->authCache  = new Cache('auth');

        //权限验证
        $this->auth();
    }

    //判断用户访问权限
    public function auth()
    {
        if (!empty($this->token)) {
            //Token转学号
            $loginInfo = $this->authCache->hget('token', $this->token);
            //获取用户信息
            if (!empty($loginInfo)) {
                $sql = 'SELECT `s`.`name`, `u`.`error`, `u`.`rank`
                        FROM `user` `u`, `student` `s`
                        WHERE `u`.`uid` = `s`.`sid` AND `u`.`uid` = ? LIMIT 1';
                $result = Mysql::execute($sql, array($loginInfo['uid']));
            }

            //学号或Token不存在
            if (empty($loginInfo) || empty($result)) {
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
                        Config::RETURN_NORMAL
                    );
                }
            }
            $loginInfo = array_merge($loginInfo, $result[0]);

            //获取用户信息
            $this->uid  = $loginInfo['uid'];
            $this->name = $loginInfo['name'];
            $this->rank = (int)$loginInfo['rank'];

            //密码错误次数过多
            if ($loginInfo['error'] >= Config::getConfig('max_passwd_error')) {
                $this->logout();
                return $this->checkAuth(
                    Config::STATE_ERROR,
                    Config::RETURN_NEED_LOGIN,
                    Config::getConfig('excessive_error_msg')
                );
            }

            //记住登陆失效
            if ((time() - $loginInfo['time']) > Config::getConfig('max_remember_time')) {
                $this->logout();
                return $this->checkAuth(
                    Config::STATE_NEED_LOGIN,
                    Config::RETURN_NEED_LOGIN,
                    Config::getConfig('invalid_token_msg')
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

            $sql = 'UPDATE `user` SET
                      `webCount` = `webCount` + 1,
                      `lastTime` = NOW()
                    WHERE `uid` = ?';
            Mysql::execute($sql, array($this->uid));
        }
        //404错误
        if (empty($this->method) || is_null($this->access)) {
            http_response_code(404);
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

        //权限不足查自己
        if ($this->rank < Config::RANK_OTHER) {
            $this->sid = $this->uid;
        //学号为空或者为自己学号
        } elseif (in_array($this->sid, array('', $this->uid))) {
            $this->sid = $this->uid;
        //查询对应的学号
        } else {
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
                    '学号不唯一，请修改关键词'
                );
            } else {
                $this->sid = $result[0]['sid'];
            }
        }

        //返回记录
        return $this->checkAuth(
            $this->NMStatus,
            Config::RETURN_NORMAL
        );
    }

    //记录访问记录并退出
    public function checkAuth($state, $code, $msg = null)
    {
        Log::recode($this->uid, $this->name, $this->module, $this->method, $this->key, $state);
        if ($code !== Config::RETURN_NORMAL) {
            $this->code = $code;
            $this->msg  = $msg;
            exit;
        }
    }

    //登陆
    public function login()
    {
        $passwd = \Hnust\input('passwd');

        //获取用户信息
        $sql = 'SELECT `error`, `passwd`, `rank` FROM `user` WHERE `uid` = ?';
        $result = Mysql::execute($sql, array($this->uid));

        //未查找到用户
        if (empty($result)) {
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
        if ($result[0]['passwd'] !== \Hnust\passwdEncrypt($this->uid, $passwd)) {
            //错误次数加1
            $sql = 'UPDATE `user` SET `error` = (`error` + 1) WHERE `uid` = ? LIMIT 1';
            Mysql::execute($sql, array($this->uid));

            $this->code = Config::RETURN_ERROR;
            $this->msg  = '您输入的密码有误';
            return false;
        }

        //选取不重复的token
        $loginInfo = array(
            'uid'  => $this->uid,
            'time' => time(),
            'ua'   => $_SERVER['HTTP_USER_AGENT']
        );
        do {
            $this->token = \Hnust\randStr(32);
        } while (!$this->authCache->hadd('token', $this->token, $loginInfo));
        $this->authCache->sadd($this->uid, $this->token);

        //更新用户信息
        $sql = 'UPDATE `user` SET `error` = 0, `lastTime` = CURRENT_TIMESTAMP WHERE `uid` = ?';
        Mysql::execute($sql, array($this->uid));

        //设置cookies
        $cookieTime = time() + Config::getConfig('max_remember_time');
        setcookie('token' , $this->token, $cookieTime, Config::WEB_PATH . '/');

        $this->msg  = '登陆成功';
        $this->code = Config::RETURN_RECORD_PAGE;
        $this->info = $this->user();
        return true;
    }

    //注销登陆
    public function logout()
    {
        $this->authCache->hdelete('token', $this->token);
        $this->authCache->sdelete($this->uid, $this->token);
        setcookie('token', '', time() - 86400, Config::WEB_PATH . '/');
        $this->msg  = '注销登陆成功';
        $this->code = Config::RETURN_NEED_LOGIN;
        return true;
    }

    //获取用户信息
    public function user()
    {
        $sql = 'SELECT `u`.`uid`, `u`.`rank`, `u`.`apiToken`,
                       `s`.`name`, `s`.`class`, `s`.`major`, `s`.`college`,
                       `s`.`mail`, `s`.`phone`, `s`.`qq`
                FROM `user` `u`, `student` `s`
                WHERE `u`.`uid` = `s`.`sid` AND `u`.`uid` = ?';
        $result = Mysql::execute($sql, array($this->uid));
        $this->info = empty($result)? array():$result[0];
        $this->info['token'] = $this->token;

        //当前学期/周次
        $this->info['week']   = \Hnust\week();
        $this->info['term']   = Config::getConfig('current_term');

        //头像地址
        $this->info['avatar'] = Config::getConfig('local_base_url');
        if (empty($this->info['qq'])) {
            $this->info['avatar'] .= "avatar/qy/{$this->info['uid']}";
        } else {
            $this->info['avatar'] .= "avatar/qq/{$this->info['qq']}";
        }

        //用户所在群组
        $group = new \Hnust\Analyse\Group();
        $this->info['group'] = $group->belong($this->uid);

        //数组合并
        $this->info = array_merge($this->info, array(
            'sid'     => $this->sid,
            'isAdmin' => ($this->rank === Config::RANK_ADMIN),
        ));
        return $this->info;
    }
}