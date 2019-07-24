<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class RoleController extends BaseController
{

    public function roleList()
    {

        $menuId = D('Node')->where(['node_code' => 'role/list', 'app' => $this->app_name])->getField('id');
        $nodeCodeArr = D('Node')->where(['pid' => $menuId, 'is_show' => 1, 'app' => $this->app_name])->getField('node_code', true);
        //print_r($nodeCodeArr);
        //$permissionBtn = $this->getUserPermissionArr($nodeCodeArr);
//        echo '<hr/>';
//        print_r($permissionBtn);
        //$this->assign('permissionBtn', $permissionBtn);
        $this->display();
    }

    public function add()
    {
        $id = I('param.id');
        $data = [];
        if ($id) {
            $data = D('Role')->find($id);
        }
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 角色分配权限页面
     */
    public function permission()
    {
        $id = I('param.id');
        $data = [];
        if ($id) {
            $data = D('Role')->find($id);
        }
        //print_r($this->roles);
        $permission = D('Role')->getRolePermissionAll($id);
        $permission = array_keys($permission);
        $nodeData = D('Node')->field('id,pid as parentId, node_name as name')->where(['is_show' => 1, 'app' => $this->app_name])->select();
        foreach ($nodeData as $key => $value) {
            $value['open'] = true;
            $value['checked'] = in_array($value['id'], $permission) ? true : false;
            $nodeData[$key] = $value;
        }
        //$nodeData = GetTree($nodeData);
        //print_r($nodeData);
        $this->assign('role_id', $id);
        $this->assign('data', $data);
        $this->assign('nodeData', json_encode($nodeData));

        $this->display();
    }
}