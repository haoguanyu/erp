<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/21
 * Time: 18:18
 */

namespace Home\Event;


use Home\Controller\BaseController;

class ErpStockEvent extends BaseController
{
    /*
   * @params:
        id:申请出库单的编号
   * @return:
   * @desc:我的申请出库单的取消
   * @author:小黑
   * @time:2019-2-20
   */
    public function checkOrderCanOutStock($id){
        //判断申请单的状态
        $applyInfo = $this->getEvent("ErpStockOutApply", "Home")->getInfo($id);
        if(empty($applyInfo['status'])){
           return ['status' => false , "message" => "申请出库单信息异常，请核查"] ;
        }
        if($applyInfo['status'] == 10){
            return ['status' => false , "message" => "该笔申请单已完成出库单转换"] ;
        }
        //判断申请单是否生成出库单
        $stockOutWhere = [
            "is_reverse" => 2 ,
            "reversed_status" => 2 ,
            "source_apply_number" => $applyInfo['outbound_apply_code'],
            "outbound_status"=> ['neq' , 2]
        ];
        $stockOutCount = $this->getEvent("ErpStock")->stockOutCoutn($stockOutWhere);
        if($stockOutCount > 0){
            return ['status' => false , "message" => "申请出库单信息已生成出库单，不可继续申请出库单"] ;
        }
        //销售订单的状态
        $order_info = $this->getEvent('ErpSale')->findSaleOrder($applyInfo['source_object_id']);
        $status = $this->getEvent('ErpStock')->checkOrderCanOutStock($order_info);
        if($status){
            return ['status' => $status , "message" => "可以生成出库单"] ;
        }else{
            return ['status' => $status , "message" => "销售单数据信息异常，请核查"] ;
        }

    }
}