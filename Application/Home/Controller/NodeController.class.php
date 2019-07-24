<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class NodeController extends BaseController
{

    public function nodeList()
    {

        $this->display();
    }

    public function add()
    {
        $menuData = D('Node')->where(['type' => 1, 'is_show' => 1, 'app' => $this->app_name])->select();
        $menuArray = GetTree($menuData);
        //print_r($menuArray);
        $data = [];
        $id = I('param.id', 0, 'int');
        if ($id) {
            $data = D('Node')->find($id);
        }
        unset($menuData);
        $this->assign('data', $data);
        $this->assign('menu', $menuArray);
        $this->display();
    }
}