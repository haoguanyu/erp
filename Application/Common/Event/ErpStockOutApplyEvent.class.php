<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/18
 * Time: 17:28
 */

namespace Common\Event;


use Common\Controller\BaseController;

class ErpStockOutApplyEvent extends BaseController
{
    /*
     * @params:
     *      orderInfo
     * @return :num
     * @desc:获取出库申请单的数量
     * @author：小黑
     * @time:2019-2-18
     */
    public function getApplyNum($orderInfo){
        $userNum = 0 ;
        //获取待提订单数量
        if($orderInfo['pay_type'] == 5){
            $waitNum = $orderInfo['total_sale_wait_num'] - $orderInfo['outbound_quantity'] ;
        }else{
            $waitNum = $orderInfo['buy_num'] - $orderInfo['outbound_quantity'];
        }
        //获取未出库的申请单数量
        $applyNum = self::applyNum($orderInfo['id'] , $orderInfo['order_number']) ;
        if($waitNum > $applyNum){
            $userNum = $waitNum - $applyNum ;
        }
        return $userNum ;
    }
    /*
   * @params:
   *      id:销售单的编号
   * @return :num
   * @desc:获取出库申请单的数量
   * @author：小黑
   * @time:2019-2-18
   */
    public function applyNum($id , $orderNumber){
        $field = 'outbound_apply_num';
        $where =[
            "status" => 1 ,
            "source_number" => $orderNumber ,
            "source_object_id" => $id
        ];
        $applyNum = $this->getModel("ErpStockOutApply")->sumNum($field, $where);
        return $applyNum ;
    }
    /*
     *  @params:
     *      data:
     *          source_object_id:来源编号
     *          remark：备注
     *          apply_num：申请数量
     *          source:来源
     *  @return :
     *  @desc:添加申请销售单
     *  @author:小黑
     *  @time:2019-2-19
     */
    public function addApply($data){
        $returnData  = [
            'status' => 1,
            'message' => '操作成功',
        ] ;
        //获取订单信息
        $orderWhere = [
            "id"=> $data['source_object_id'] ,
            "order_status"=> 10 ,
            "is_void" => 2 ,
            "is_returned" => 2
        ];
        $orderInfo = $this->getModel("ErpSaleOrder")->findSaleOrder($orderWhere) ;
        if(empty($orderInfo)){
            $returnData = [
                'status' => 2,
                'message' => '请重新确认销售订单状态',
            ] ;
            return $returnData ;
        }
        //数量的判断
        $useNum = getNum(self::getApplyNum($orderInfo)) ;
        if(empty($useNum) || ($useNum <$data['apply_num'])){
            $returnData = [
                'status' => 3,
                'message' => '销售数量不足申请出库单数量',
            ] ;
            return $returnData ;
        }
//        $whereStock = ["object_id" => $orderInfo['storehouse_id']];
//        $stock_type = $this->getModel("ErpStock")->getStockfile("stock_type" , $whereStock);
        $stock_type = getAllocationStockType($orderInfo['storehouse_id']);
        $orderNumber = erpCodeNumber(21,'' , $orderInfo['our_company_id'])['order_number'] ;
        $addData = [
            "outbound_apply_code" => $orderNumber ,
            "outbound_apply_type" => $data['outbound_apply_type'] ,
            "remark" => $data['remark'] ,
            "source_number" => $orderInfo['order_number'] ,
            "source_object_id" => $orderInfo['id'] ,
            "our_company_id" => $orderInfo['our_company_id'],
            "goods_id" => $orderInfo['goods_id'] ,
            "depot_id" => $orderInfo['depot_id'] ,
            "outbound_apply_num" => setNum($data['apply_num']) ,
            "create_time" => date("Y-m-d H:i:s") ,
            "creater_id" => $data['creater_id'] ,
            "creater_name" => $data['creater_name'] ,
            "storehouse_id" => $orderInfo['storehouse_id'] ,
            "stock_type" => $stock_type ,
            "region" => $orderInfo['region'] ,
            "data_source" => $data['data_source'],
            "is_shipping" => 2
        ];
        if(getCacheLock('ErpStockOutApply/addStockOutApply'))
            return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStockOutApply/addStockOutApply', 1);
        if(!$insertId = $this->getModel("ErpStockOutApply")->addApply($addData)){
            cancelCacheLock('ErpStockOutApply/addStockOutApply');
            return ['status' => 4, 'message' => "添加出库申请单失败"];
        }
        cancelCacheLock('ErpStockOutApply/addStockOutApply');
        //真理返回数据
        $returnData['apply_order_number'] =$insertId ;
        return $returnData ;
    }
    /*
   * @params:
   * @return:
   * @desc:我的申请出库单的取消
   * @author:小黑
   * @time:2019-2-20
   */
    public function delApply($id){
        $applyWhere = [
            "id" => $id ,
            "status" => 1
        ] ;
        //获取申请出库单信息
        $applyInfo = $this->getModel("ErpStockOutApply")->info("*" , $applyWhere);
        if(empty($applyInfo)){
            return  ['status' => 3,'message' => '请查询此申请出库单是否正确！'] ;
        }
        if($applyInfo['status'] == 10){
            return  ['status' => 3,'message' => '该笔申请单已完成出库单转换！'] ;
        }
        //判断是否生成出库单
        $stockOutWhere = [
            "is_reverse" => 2 ,
            "reversed_status" => 2 ,
            "source_apply_number" => $applyInfo['outbound_apply_code']
        ];
        $stockOutCount = $this->getEvent("ErpStock")->stockOutCoutn($stockOutWhere);
        if($stockOutCount > 0){

            return ['status' => 4,'message' => '此申请出库单已经生成出库单信息，不可以取消！'] ;
        }
        //进行数据操作
        $where['id'] = $applyInfo['id'] ;
        $update = [
            "status" => 2 ,
            "update_time" => date("Y-m-d H:i:s")
        ];
        if(getCacheLock('ErpStockOutApply/updateStockOutApply'))
            return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStockOutApply/updateStockOutApply', 1);
        if(!$updateStatus = $this->getModel("ErpStockOutApply")->updateApply($update , $where )){
            cancelCacheLock('ErpStockOutApply/updateStockOutApply');
            return ['status' => 4, 'message' => "取消出库申请单失败"];
        }
        cancelCacheLock('ErpStockOutApply/updateStockOutApply');
        return ['status' => 1,'message' => '取消成功'];
    }
    /*
     * @params:
          where:查询条件
          data:修改得数据
     * @return:
     * @desc:我的申请出库单的详情
     * @author:小黑
     * @time:2019-2-20
     */
    public function saveApply($where , $data){
        $applyInfo = $this->getModel("ErpStockOutApply")->info("*" , $where);
        if(empty($applyInfo)){
            return  ['status' => 2,'message' => '请查询此申请出库单是否正确！'] ;
        }
        if(isset($data['outbound_apply_num'])){
            $data['outbound_apply_num'] = setNum($data['outbound_apply_num']) ;
        }
        //判断数据是否修改过
        $isupdate = 0 ;
        foreach ($data as $key => $value){
            if($value != $applyInfo[$key]){
                $isupdate ++ ;
            }
        }
        if(empty($isupdate)){
            return  ['status' => 3,'message' => '申请出库单数据未修改'] ;
        }
        $data['update_time'] = date("Y-m-d H:i:s") ;

        cancelCacheLock('ErpStockOutApply/updateStockOutApply');
        if(getCacheLock('ErpStockOutApply/updateStockOutApply'))
            return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStockOutApply/updateStockOutApply', 1);
        if(!$updateStatus = $this->getModel("ErpStockOutApply")->updateApply($data , $where )){
            cancelCacheLock('ErpStockOutApply/updateStockOutApply');
            return ['status' => 4, 'message' => "修改出库申请单失败"];
        }
        cancelCacheLock('ErpStockOutApply/updateStockOutApply');
        return ['status' => 1,'message' => '修改成功'];
    }


    /**
     *
     * 返回销售单可出库申请数量
     * @author xiaowen
     * @time 2019-3-10
     * @param $order_info 销售单数据
     * @return array $result
     */
    function getStockOutApplyNum($order_info){

        $stock_out_where = [
            'source_number'=>$order_info['order_number'], //来源于该销售单
            'outbound_type'=>1, //出库类型为：销售
            'outbound_status'=>1, //出库状态：未审核
        ];
        //查询销售单未审核出库数量
        $not_stock_out_num = $this->getModel('ErpStockOut')->where($stock_out_where)->sum('actual_outbound_num');
        $stock_out_apply_where = [
            'source_number'=>$order_info['order_number'], //来源于该销售单
            'status'=>1, //出库类型为：销售
        ];
        //查询销售单已创建的出库申请单数量
        $stock_out_apply_num = $this->getModel('ErpStockOutApply')->where($stock_out_apply_where)->sum('outbound_apply_num');

        $not_stock_out_num = $not_stock_out_num ? $not_stock_out_num : 0;
        $stock_out_apply_num = $stock_out_apply_num ? $stock_out_apply_num : 0;
        //订单总数 除定金锁价外 都是等于购买数量
        $order_info['total_num'] = $order_info['pay_type'] != 5 ? $order_info['buy_num'] : $order_info['total_sale_wait_num'];
        //计算可申请出库数量 公式：销售单待出库数量（订单数量-已出库数量）-已申请出库数量-未审核出库数量
        $can_stock_out_num = ($order_info['total_num'] - $order_info['outbound_quantity']) - $not_stock_out_num - $stock_out_apply_num;
        log_info('可出库申请计算，订单'.$order_info['order_number'] .': '. $order_info['total_num'] . '-' . $order_info['outbound_quantity'] . '-' . $not_stock_out_num . '-'.$stock_out_apply_num);
        return $can_stock_out_num ? $can_stock_out_num : 0;

    }
}