<?php
namespace BaseApi\Controller;

use Think\Controller;
use Common\Controller\BaseController;

class ErpOrderController extends BaseController
{

    /**
     *交易单列表
     *DATE:2017-03-10 Time:11:00
     *Author: xiaowen <xiaowen@51zhaoyou.com>
     */
    public function orderList()
    {

        //if (IS_POST) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpOrder', 'Common')->orderList($param);
            $this->echoJson($data);
        //}

    }


}
