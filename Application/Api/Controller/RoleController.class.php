<?php
namespace Api\Controller;

use Think\Controller;
use Api\Controller\BaseController;

class RoleController extends BaseController
{

    public function getList()
    {
        $where = [];
        $where['app'] = $this->getAppName();
        $data['data'] = M('role')->where($where)->order('create_at desc')->select();
        $this->echoJson($data);
    }

    public function addRole()
    {
        $data = I('param.');
        if (!trim($data['role_name'])) {
            $this->echoError('角色名称不能为空');
        }
        $data['create_at'] = DateTime();
        $data['app'] = $this->getAppName();
        $status = D('Role')->add($data);
        if ($status) {
            $this->echoSuccess('角色添加成功');

        } else {
            $this->echoError('角色添加失败');
        }
    }

    public function editRole()
    {
        $data = I('param.');
        if (!intval($data['role_id'])) {
            $this->echoError('参数有误，ID无法获取');
        }
        if (!trim($data['role_name'])) {
            $this->echoError('角色名称不能为空');
        }
        $data['update_at'] = DateTime();
        $status = D('Role')->save($data);
        if ($status) {
            $this->echoSuccess('角色编辑成功');
        } else {
            $this->echoError('角色编辑失败');
        }
    }

    public function setRoleNode()
    {
        $role_id = I('param.role_id', 0, 'int');
        $node_id = I('param.node_id', 0, 'int');
        $where['role_id'] = $role_id;
        $where['node_id'] = $node_id;
        if (!($role_id && $node_id)) {
            $this->echoError('参数有误');
        }
        if (I('param.checked') == 'true') {
            $haveNode = M('role_node')->where($where)->find();
            if (empty($haveNode)) {
                $data = $where;
                $data['create_at'] = date('Y-m-d H:i:s');
                $result = M('role_node')->add($data);
            } else {
                $result = true;
            }
        } else {
            //echo 'mmbw';
            $result = M('role_node')->where($where)->delete();
        }
        if ($result) {
            $this->echoSuccess('权限分配成功');
        } else {
            $this->echoError('权限分配失败');
        }
    }
}