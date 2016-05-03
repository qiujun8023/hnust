<?php

namespace Hnust\Module;

use Hnust\Utils\Mysql;
use Hnust\Utils\Output;

Class Base
{
    protected $module;
    protected $method;
    protected $beginTime;

    //初始化
    public function __construct($module, $method)
    {
        $this->module    = $module;
        $this->method    = $method;
        $this->beginTime = microtime(true);
    }

    //运行
    public function run()
    {
        try {
            //尝试用映射及参数绑定执行
            $method = new \ReflectionMethod($this, $this->method);
            $method->invoke($this);
        } catch (\Exception $e) {
            $this->code = $e->getCode();
            $this->msg  = $e->getMessage();
            if (0 === $this->code) {
                $this->code = \Hnust\Config::RETURN_ERROR;
            }
        }
    }

    //析构函数，输出
    public function __destruct()
    {
        if (isset($this->msg) || isset($this->code) || isset($this->info) || isset($this->data)) {
            $this->anser = Output::format($this->msg, $this->code, $this->info, $this->data);
        }
        if (isset($this->anser) && is_array($this->anser)) {
            //统计代码执行时间及Mysql执行次数
            if (isset($this->anser['info']) && is_array($this->anser['info'])) {
                $this->anser['info']['runTime'] = microtime(true) - $this->beginTime;
                $this->anser['info']['mysql']   = Mysql::$count;
            }
            Output::output($this->anser);
        }
    }
}