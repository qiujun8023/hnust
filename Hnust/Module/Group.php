<?php

namespace Hnust\Module;

use Hnust\Config;

class Group extends Auth
{
    protected $group;

    //初始化用户信息
    public function __construct($module, $method)
    {
        parent::__construct($module, $method);
        $this->group = new \Hnust\Analyse\Group();
    }

    //获取所属群组信息
    public function belong()
    {
        $list  = $this->group->belong($this->uid);
        $list  = empty($list)? array():$list;

        $this->data = array();
        foreach ($list as $item) {
            $this->data[] = array_merge($item, array(
                'member' => $this->group->getMember($item['gid'])
            ));
        }
    }

    //查看群组
    public function get()
    {
        $this->data = $this->group->get();
    }

    //新增群组
    public function add()
    {
        return $this->edit();
    }

    //编辑群组
    public function edit()
    {
        $gid   = \Hnust\input('gid');
        $name  = \Hnust\input('name');
        $share = \Hnust\input('share/b', false);
        $share = $share? '1':'0';
        if (empty($name)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '群组名称不能为空';
            return false;
        }
        if (empty($gid)) {
            $result = $this->group->add($name, $share, $this->uid);
        } else {
            $result = $this->group->edit($gid, $name, $share, $this->uid);
        }

        if ($result) {
            $this->code = Config::RETURN_NORMAL;
        } elseif (empty($gid)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '添加失败，可能群组已存在';
        } else {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '操作失败，请稍后再试';
        }
    }

    //删除群组
    public function delete()
    {
        $gid = \Hnust\input('gid');
        $this->group->delete($gid);
        $this->code = Config::RETURN_NORMAL;
    }

    //获取群组成员
    public function getMember()
    {
        $gid = \Hnust\input('gid');
        $this->info = $this->group->get($gid);
        $this->data = $this->group->getMember($gid);
    }

    //添加群组成员
    public function addMember()
    {
        $gid = \Hnust\input('gid');
        if (empty($gid)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '参数不能为空';
            return false;
        }
        $this->group->addMember($gid, $this->sid);
        $this->msg = "已添加学号{$this->sid}";
    }

    //删除群组成员
    public function deleteMember()
    {
        $gid = \Hnust\input('gid');
        if (empty($gid)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '参数不能为空';
            return false;
        }
        $this->group->deleteMember($gid, $this->sid);
        $this->msg = "已删除学号{$this->sid}";
    }
}
