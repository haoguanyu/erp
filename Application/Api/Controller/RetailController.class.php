<?php
namespace Api\Controller;

use Think\Controller;
use Api\Controller\BaseController;

class RetailController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function relist()
    {
        $data['data'] = D('Retail')->getRetailList(['status' => ['eq', 1]]);
        if (IS_AJAX) {
            $this->echoJson($data);
        }
        $this->display();
    }

    public function update()
    {
        $id = intval(I('param.id', '', 'int'));
        //if($id)return false;
        $data = D('Retail')->getRetailList(['status' => ['eq', 1], 'id' => $id]);
        //var_dump($data);die;
        $this->assign("data", $data);
        $this->display();
    }

    public function actupdate()
    {
        $this->echoJson(I('param.'));
        var_dump("12334234234");
    }

    public function welcome()
    {
        $this->display();
    }


}