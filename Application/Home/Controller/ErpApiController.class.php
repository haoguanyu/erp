<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * ERP公共接口api
 * @author xiaowen
 * Class ErpApiController
 * @package Home\Controller
 */
class ErpApiController extends BaseController
{
    /**
     * 调拨单红冲
     * @author qianbin
     * @time 2018-01-21
     */
        public function reverseAllocationOrder(){
        $order_numer = $_REQUEST['allocation_order_number'];
        if(empty(trim($order_numer)))   $this->ajaxReturn(['status' => 22 , 'message' => 'ERP获取参数错误，请刷新后重试！']);
        $order_info  = $this->getModel('ErpAllocationOrder')->field('id,our_company_id')->where(['order_number' => trim($order_numer)])->find();
        if(intval($order_info['id']) <= 0)            $this->ajaxReturn(['status' => 23 , 'message' => '未查询到该调拨单信息，请刷新后重试！']);
        # 设置session
        session('erp_company_id',$order_info['our_company_id']);
        $data = $this->getEvent('ErpReverse')->reverseAllocationOrder($order_info['id'],2);
        $this->ajaxReturn(['status' => $data['status'] , 'message' => $data['message']]);
        //return ['status' => $data['status'] , 'message' => $data['message']];
        }

}