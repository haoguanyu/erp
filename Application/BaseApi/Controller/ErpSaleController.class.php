<?php
namespace BaseApi\Controller;
use Think\Log ;
use Think\Controller;
use Common\Controller\BaseController;
class ErpSaleController extends BaseController
{

    /**
     *交易单列表
     *DATE:2017-03-10 Time:11:00
     *Author: xiaowen <xiaowen@51zhaoyou.com>
     */
    public function orderList()
    {

            $param = $_REQUEST;
            $param['our_company_id'] = 3372;
            $param['type'] = 1;
            $param['start'] = 0;
            $param['length'] = 10;
            $data = $this->getEvent('ErpSale', 'Common')->saleOrderList($param);
            $this->echoJson($data);

    }

    /**
     * 对外接口-生成销售单
     * @author xiaowen
     * @time 2019-1-14
     */
    public function addSaleOrder(){
        //$data = [];
        $data = [
            'company_id' => 10,
        ];
        $this->getEvent('ErpSale', MODULE_NAME)->addSaleOrder($data);
    }
    /*
     * @params :
     *      $saleOrderData：添加销售订单得数据
     * @return :json
     * @desc:生成销售订单公共方法
     * @auth:小黑
     * @time:2019-3-23
     */
    public function addSale(){
        $data = $_POST ;
        $returnData = $this->getEvent("ErpSale" , "BaseApi")->CommonAddSaleOrder($data) ;
        Log::write($returnData) ;
        $this->echoJson($returnData) ;
    }

}
