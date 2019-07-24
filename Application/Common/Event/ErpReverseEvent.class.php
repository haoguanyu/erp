<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpReverseEvent extends BaseController
{

    public $reverse_stock_in_info;
    public $reverse_stock_out_info;
    public $reverse_allocation_type;
    /** --------------------------------------------------文哥------------------------------------------------------- */

    /**
     * 销售单红冲
     * @param $id
     * @author xiaowen
     * @time 2018-1-21
     * @return mixed
     */
    public function saleOrderReverse($id)
    {
        if(empty($id)) {
            return ['status' => 11,'message' => '参数错误！请检查'];
        }
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>intval($id)]);

        $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number'=>$order_info['order_number'],'outbound_status'=>1])->find();
        if(empty($order_info)) {
            $result = [
                'status' => 9,
                'message' => '销售单不存在',
            ];
            return $result;
        }
        if ( $order_info['business_type'] == 4 ) {
            return ['status' => 7,'message' => '该销售单属于内部交易单,不允许作废！'];
        }
        if ($stock_out_info) {
            $result = [
                'status' => 3,
                'message' => '该销售单有未处理的出库单，请检查',
            ];
            return $result;
        }
        if($order_info['invoice_status'] != 1){
            $result = [
                'status' => 3,
                'message' => '销售单必须未开票才可作废，请检查',
            ];
            return $result;
        }
        if($order_info['collection_status'] != 1){
            $result = [
                'status' => 2,
                'message' => '销售单必须未收款才可作废，请检查',
            ];
            return $result;
        }
        if($order_info['outbound_quantity'] != 0){
            $result = [
                'status' => 4,
                'message' => '销售单必须未出库才可作废，请检查',
            ];
            return $result;
        }
        if($order_info['order_status'] != 10){
            $result = [
                'status' => 5,
                'message' => '只有已确认的销售单才可作废，请检查',
            ];
            return $result;
        }

        //如果是代采销售单，需要判断是否存在未取消的代采采购单
        if($order_info['is_agent'] == 1){
            //查询出所有的对应的代采采购单
            $purchase_order_where = [
                'from_sale_order_number' => $order_info['order_number'],
                'from_sale_order_id' => $order_info['id'],
                'type' => 2,
                'order_status' => ['neq', 2],
                //'order_status' => ['neq', 2],
            ];
            $purchase_orders = $this->getModel('ErpPurchaseOrder')->where($purchase_order_where)->select();
            if($purchase_orders){
                return $result = [
                    'status' => 6,
                    'message' => '作废代采销售单必须先作废代采采购单',
                ];
            }
        }
        //end 代采采购单验证结束---------------------

        //判断财务中台开票情况，若已开票，则不允许红冲
        $invoice_status = $this->getEvent('FinanceMiddleGround')->fmgSelectInvoice($id,$order_info['order_number']);
        if ($invoice_status['code'] == 200) {
//            if ($invoice_status['data']['whole_status'] == 2) {
//                return ['status' => 8,'message' => '财务中台数据状态异常，请联系管理员'];
//            }
//            if ($invoice_status['data']['action_list']['action_status'] == 2) {
//                return ['status' => 9,'message' => '财务中台销售订单状态异常，请联系管理员'];
//            }
            if ($invoice_status['data']['action_list'][0]['invoice_status'] != 1) {
                return ['status' => 10,'message' => '财务中台销售订单已开发票，请检查'];
            }
        }

        if (getCacheLock('ErpReverse/saleOrderReverse')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/saleOrderReverse', 1);
        M()->startTrans();
        //库存变动处理----------------------------------------------------------------------------------------
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
            'our_company_id' => $order_info['our_company_id'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        $data = [
            'goods_id'   => $order_info['goods_id'],
            'object_id'  => $order_info['storehouse_id'],
            'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
            'region'     => $order_info['region'],
        ];
        //销售单已确认并且付款方式为货到付款、账期，应该扣减销售待提，其他只要扣减销售预留--------------------
        if($order_info['order_status'] == 10 && in_array($order_info['pay_type'], [2,4])){
            $change_stock_name = 'sale_wait_num';
        }else{
            $change_stock_name = 'sale_reserve_num';
        }
        $stock_info[$change_stock_name] = $data[$change_stock_name] = $stock_info[$change_stock_name] - $order_info['buy_num'];
        //------------------------------------------------------------------------------------------------------
        //重新计算可用库存数量----------------------------------------------------------------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 1,
            'log_type' => 9,
            'our_company_id' => $order_info['our_company_id'],
        ];
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['buy_num'], $orders);
        //end 库存变动------------------------------------------------------------------------------------------

        //处理销售单-------------------------------------------------------------------------------------------------
        //----修改销售单状态--------
        $sale_order_data = [
            'order_status' => 2,
            'is_void' => 1,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime()
        ];
        $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $sale_order_data);

        //-----保存操作到销售单日志-----
        $log_data = [
            'sale_order_id' => $order_info['id'],
            'sale_order_number' => $order_info['order_number'],
            'log_type' => 18,
            'log_info' => serialize($order_info),
        ];
        $log_status = $this->getEvent('ErpSale')->addSaleOrderLog($log_data);
        //end处理销售单----------------------------------------------------------------------------------------------

        //将审批流置为无效-------------------------------------------------------------------------------------------
        $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>intval($id), 'workflow_type'=>1, 'status'=>['neq', 2]])->order('id desc')->find();
        if ($workflow && $workflow['status'] == 1) {
            $workflow['status'] = 2;
            $work_status = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
        } else {
            $work_status = true;
        }
        //end 将审批流置为无效-----------------------------------------------------------------------------------------;

        //撤销财务中台的开票申请
        if ($order_info['pay_type'] == 2) {
            $invoice_cancel_status = $this->getEvent('FinanceMiddleGround')->fmgCancelInvoice($id,$order_info['order_number']);
        } else {
            $invoice_cancel_status['code'] = 200;
        }
        //财务中台没有数据返回1003 有且已开票返回1004
        if($stock_status && $sale_order_status && $log_status && $work_status &&
            ($invoice_cancel_status['code'] == 200 || $invoice_cancel_status['code'] == 1003)){
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        }else{
            $message = '操作失败';
            if ($invoice_cancel_status['code'] == 1004) {
                $message = '财务中台已开票，不允许撤销，请检查';
            }
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => $message,
            ];
        }
        cancelCacheLock('ErpReverse/saleOrderReverse');

        return $result;
    }

    /*********************************************
    @ Content 采退单 出库红冲
    @ Author YF
    @ Time 2018-12-25
     **********************************************/
    public function PurchaseRefundOrderRedRush($sale_stock_out_info = [])
    {
        if ( $sale_stock_out_info['outbound_status'] != 10 ) {
            return ['status' => 9,'message' => '红冲出库单必须是已审核状态！'];
        }
        $purchase_payment_where = [
            'purchase_order_number' => ['eq',$sale_stock_out_info['source_number']],
            'status'                => ['neq',2]
        ];
        $purchase_payment_arr = $this->getModel('ErpPurchasePayment')->where($purchase_payment_where)->field('id')->select();
        if ( count($purchase_payment_arr) == 1 ) {
            return ['status' => 3 ,'message' => '应付管理未红冲！'];
        }
        /**************  查询 出 退货单数据  *******************/
        (array)$returned_order_where = [
            'order_number'    => ['eq',$sale_stock_out_info['source_number']],
        ];
        (array)$returned_order_arr = $this->getModel('ErpReturnedOrder')->where($returned_order_where)->find();
        if ( !isset($returned_order_arr['order_number']) ) {
            return ['status' => 4,'message' => '未查询到退货单！'];
        }
        if ( $returned_order_arr['return_amount_status'] == 10 ) {
            return ['status' => 8, 'message' => '退货单 状态为 已退款，不能进行红冲！'];
        }

        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['order_number' => $returned_order_arr['source_order_number']]);
        if ( !isset($purchase_order_info['id']) ) {
            return ['status' => 5,'message' => '采购单未查询到！'];
        }
        /********************* END **************************/
        M()->startTrans();
        //红冲出库
        $stock_out_data = [
            'outbound_code'         => erpCodeNumber(7)['order_number'],
            'outbound_type'         => $sale_stock_out_info['outbound_type'],
            'outbound_status'       => 10,
            'outbound_remark'       => '',
            'source_number'         => $sale_stock_out_info['source_number'],
            'source_object_id'      => $sale_stock_out_info['source_object_id'],
            'our_company_id'        => $sale_stock_out_info['our_company_id'],
            'goods_id'              => $sale_stock_out_info['goods_id'],
            'depot_id'              => $sale_stock_out_info['depot_id'],
            'outbound_num'          => $sale_stock_out_info['outbound_num'] * -1,
            'actual_outbound_num'   => $sale_stock_out_info['actual_outbound_num'] * -1,
            'outbound_density'      => $sale_stock_out_info['outbound_density'],
            'create_time'           => currentTime(),
            'dealer_id'             => $this->getUserInfo('id'),
            'dealer_name'           => $this->getUserInfo('dealer_name'),
            'creater_id'            => $this->getUserInfo('id'),
            'creater_name'          => $this->getUserInfo('dealer_name'),
            'auditor_id'            => $this->getUserInfo('id'),
            'audit_time'            => currentTime(),
            'storehouse_id'         => $sale_stock_out_info['storehouse_id'],
            'stock_type'            => $sale_stock_out_info['stock_type'],
            'region'                => $sale_stock_out_info['region'],
            'is_reverse'            => 1,
            'reverse_source'        => $sale_stock_out_info['outbound_code'],
            'batch_id' => $sale_stock_out_info['batch_id'],
            'batch_sys_bn' => $sale_stock_out_info['batch_sys_bn'],
        ];
        /**************************************
        @ Content 处理批次信息
        @ Author  YF
        @ Time    2019-03-08(妇女节)
         ***************************************/
        if( $sale_stock_out_info['actual_outbound_num'] > 0 && $sale_stock_out_info['batch_id'] > 0 ){
            $batch_change_data = [
                'batch_id'           => $sale_stock_out_info['batch_id'],
                'change_balance_num' => $sale_stock_out_info['actual_outbound_num'], // 增加可用数量
                'change_type'        => 5,
                'change_number'      => $stock_out_data['outbound_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
            if($batch_result['status'] != 1){
                M()->rollback();
                return $batch_result;
            }
        }
        else{
            $batch_result['status'] = 1;
        }
        /**************************************
        END
         ***************************************/
        /****************** 修改原出库单 红冲状态 ****************/
        $update_stock_out_status = $this->getModel('ErpStockOut')->where(['id'=>['eq',$sale_stock_out_info['id']]])->save(['reversed_status' => 1]);
        if ( !$update_stock_out_status ) {
            M()->rollback();
            return ['status' => 12,'message' => '原出库单红冲状态更新失败！'];
        }
        /*********************** END **************************/
        //通过java接口获取出库单成本 edit xiaowen 2018-2-4
        $stock_out_cost = getStockOutCost($stock_out_data);

        $stock_out_data['cost'] = $sale_stock_out_info['cost'] ? $sale_stock_out_info['cost'] : 0;
        $stock_out_data['cost_log_id'] = $stock_out_cost['logId'] ? $stock_out_cost['logId'] : 0;

        $status_stockout = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);

        //更新采购单数据
        $purchase_order_data = [
            // 扣减退货数量
            'returned_goods_num' => $purchase_order_info['returned_goods_num'] - $sale_stock_out_info['outbound_num'],
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $purchase_order_info['id']], $purchase_order_data);

        //影响库存
        $stock_where = [
            'goods_id' => $sale_stock_out_info['goods_id'],
            'object_id' => $sale_stock_out_info['storehouse_id'],
            'stock_type' => $sale_stock_out_info['stock_type'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        if ( !isset($stock_info['stock_num']) ) {
            M()->rollback();
            return ['status' => 6,'message' => '库存获取失败!'];
        }
        //保存变动物理库存之前的物理数量 eidt xiaowen---------------
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $sale_stock_out_info['goods_id'],
            'object_id' => $sale_stock_out_info['storehouse_id'],
            'stock_type' => $sale_stock_out_info['stock_type'],
            'region' => $sale_stock_out_info['region'],
            'stock_num' => $stock_info['stock_num'] + $sale_stock_out_info['actual_outbound_num'],
            'transportation_num' => $stock_info['transportation_num'] + ($sale_stock_out_info['outbound_num'] - $sale_stock_out_info['actual_outbound_num']),
        ];
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的在途库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info );
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_out_data['outbound_code'],
            'object_type' => 3,
            'log_type' => 12,
        ];
        //----------------更新库存，并保存库存日志-------------------------

        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_out_data['actual_outbound_num'], $orders);
        if ( $stock_status && $status_stockout && $purchase_order_status && $batch_result['status'] == 1) {
            $stock_out_data['before_stock_num'] = $beforeNum;
            $stock_out_data['stock_id'] = $stockId ? $stockId : 0;
            $stock_out_data['change_num'] = abs($stock_out_data['actual_outbound_num']);
            updateStockInCost($stock_out_data);
            M()->commit();
            return ['status' => 1,'message' => '红冲成功！'];
        }
        M()->rollback();
        return ['status' => 8,'message' => '红冲失败！'];

    }

    /**
     * 调拨出库单红冲
     * @param $id
     * @author guanyu
     * @time 2019-03-05
     * @return mixed
     */
    public function reverseAllocationStockOut($param)
    {
        //出库单信息
        $stock_out_info = $this->getModel('ErpStockOut')->where(['id' => $param['id']])->find();

        //对应调拨单信息
        $allocation_order_info = $this->getModel('ErpAllocationOrder')->where(['id' => $stock_out_info['source_object_id'],'order_number' => $stock_out_info['source_number']])->find();

        //批次信息
        $batch_info = $this->getModel('ErpBatch')->where(['id'=>$stock_out_info['batch_id'],'sys_bn'=>$stock_out_info['batch_sys_bn']])->find();

        //对应入库单信息
        $stock_in_info = $this->getModel('ErpStockIn')->where(['source_number'=>$allocation_order_info['order_number'],'reversed_status'=>2,'is_reverse'=>2])->find();
        //验证单据信息
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if (!empty($stock_in_info)) {
            $result['status'] = 3;
            $result['message'] = '对应入库单未红冲，请检查';
            return $result;
        }

        if ($stock_out_info['reversed_status'] == 1) {
            $result['status'] = 3;
            $result['message'] = '该笔出库单已红冲，请检查';
            return $result;
        }

        if ($stock_out_info['outbound_status'] != 10) {
            $result['status'] = 4;
            $result['message'] = '未审核出库单无法红冲，请检查';
            return $result;
        }

        if ($stock_out_info['is_reverse'] == 1) {
            $result['status'] = 5;
            $result['message'] = '该笔出库单为红冲出库单，禁止操作';
            return $result;
        }

        if (empty($allocation_order_info)) {
            $result['status'] = 6;
            $result['message'] = '对应调拨单不存在，请检查';
            return $result;
        }

        if ($allocation_order_info['status'] != 10) {
            $result['status'] = 7;
            $result['message'] = '对应调拨单状态异常，请检查';
            return $result;
        }

        if($stock_out_info['is_shipping'] == 1){
            $result['status']  = 8;
            $result['message'] = "该笔出库单已生成配送单，请先取消配送单！";
            return $result;
        }

        if (getCacheLock('ErpReverse/reverseAllocationStockOut')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reverseAllocationStockOut', 1);
        M()->startTrans();

        //修改原出库单状态
        $old_stock_out_data = [
            'reversed_status' => 1,
        ];
        $old_stock_out_status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$param['id']],$old_stock_out_data);

        $new_stock_out_data = [
            'outbound_code' => erpCodeNumber(7)['order_number'],
            'outbound_type' => 2,
            'outbound_status' => 10,
            'outbound_remark' => '',
            'source_number' => $stock_out_info['source_number'],
            'source_object_id' =>  $stock_out_info['source_object_id'],
            'our_company_id' => $stock_out_info['our_company_id'],
            'goods_id' => $stock_out_info['goods_id'],
            'depot_id' => $stock_out_info['depot_id'],
            'outbound_num' => plusConvert($stock_out_info['outbound_num']),
            'actual_outbound_num' => plusConvert($stock_out_info['actual_outbound_num']),
            'create_time' => currentTime(),
            'creater_id' => $this->getUserInfo('id'),
            'creater_name' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'storehouse_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['stock_type'],
            'region' => $stock_out_info['region'],
            'is_reverse' => 1,
            'reverse_source' => $stock_out_info['outbound_code'],
            'batch_sys_bn' => $stock_out_info['batch_sys_bn'],
            'batch_id' => $stock_out_info['batch_id'],
            'cost' => $stock_out_info['cost'],
        ];

        $new_stock_out_status = $this->getModel('ErpStockOut')->addStockOut($new_stock_out_data);
        //----------end 出库单处理--------------------------------------------------------------------------

        $other_stock_out = $this->getModel('ErpStockOut')->where(['source_number'=>$stock_out_info['source_number'],'reversed_status'=>2,'is_reverse'=>2])->find();
        //修改调拨单状态
        if (empty($other_stock_out)) {
            $where = [
                'id'=>$allocation_order_info['id'],
            ];
            $allocation_data = [
                'outbound_status' => 2,
                'actual_out_num' => $allocation_order_info['actual_out_num'] - $stock_out_info['actual_outbound_num'],
                'actual_out_num_liter' => $allocation_order_info['actual_out_num_liter'] - tonToLiter($stock_out_info['actual_outbound_num'],$stock_out_info['outbound_density']),
            ];
        } else {
            $where = [
                'id'=>$allocation_order_info['id'],
            ];
            $allocation_data = [
                'outbound_status' => 3,
                'actual_out_num' => $allocation_order_info['actual_out_num'] - $stock_out_info['actual_outbound_num'],
                'actual_out_num_liter' => $allocation_order_info['actual_out_num_liter'] - tonToLiter($stock_out_info['actual_outbound_num'],$stock_out_info['outbound_density']),
            ];
        }
        $allocation_status = $this->getModel('ErpAllocationOrder')->where($where)->save($allocation_data);

        //-----保存操作到出库单日志-----
        $erp_stock_option_log = [
            'order_type'    => 1,                                         # 1 出库单  2 入库单
            'order_number'  => trim($stock_out_info['outbound_code']),     # 单据号
            'log_type'      => 2,                                         # 操作类型 1 审核  2 取消审核
            'operator_id'   => session('erp_adminInfo')['id'],
            'operator'      => session('erp_adminInfo')['dealer_name'],
            'create_time'   => date('Y-m-d H:i:s',time())
        ];
        $add_log = M('erpStockOptionLog')->add($erp_stock_option_log);

        //出库方库存变动处理----------------------------------------------------------------------------------------
        $stock_where = [
            'goods_id' => $stock_out_info['goods_id'],
            'object_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['stock_type'],
            'our_company_id' => $stock_out_info['our_company_id'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];

        $data = [
            'goods_id' => $stock_out_info['goods_id'],
            'object_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['is_agent'] == 1 ? 2 : $stock_out_info['stock_type'],
            'region' => $stock_out_info['region'],
        ];

        $stock_info['stock_num'] = $data['stock_num'] = $stock_info['stock_num'] + $stock_out_info['actual_outbound_num'];
        $stock_info['allocation_wait_num'] = $data['allocation_wait_num'] = $stock_info['allocation_wait_num'] + $stock_out_info['actual_outbound_num'];
        //------------------------------------------------------------------------------------------------------
        //重新计算可用库存数量----------------------------------------------------------------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $new_stock_out_data['outbound_code'],
            'object_type' => 3,
            'log_type' => 12,
            'our_company_id' => $stock_out_info['our_company_id'],
        ];
        $stock_out_status = $this->getEvent('ErpStock')->saveStockInfo($data, plusConvert($stock_out_info['actual_outbound_num']), $orders);
        //end 库存变动------------------------------------------------------------------------------------------

        //入库方库存变动处理(减在途)----------------------------------------------------------------------------------------
        $stock_where = [
            'goods_id' => $allocation_order_info['goods_id'],
            'object_id' => $allocation_order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($allocation_order_info['in_storehouse']),
            'our_company_id' => $allocation_order_info['our_company_id'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        $data = [
            'goods_id' => $stock_where['goods_id'],
            'object_id' => $stock_where['object_id'],
            'stock_type' => $stock_where['stock_type'],
            'region' => $allocation_order_info['in_region'],
        ];

        $stock_info['transportation_num'] = $data['transportation_num'] = $stock_info['transportation_num'] - $stock_out_info['actual_outbound_num'];
        //------------------------------------------------------------------------------------------------------
        //重新计算可用库存数量----------------------------------------------------------------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $stock_out_info['outbound_code'],
            'object_type' => 3,
            'log_type' => 12,
            'our_company_id' => $stock_out_info['our_company_id'],
        ];
        $stock_in_status = $this->getEvent('ErpStock')->saveStockInfo($data, plusConvert($stock_out_info['actual_outbound_num']), $orders);
        //end 库存变动------------------------------------------------------------------------------------------

        //更新批次数据
        $batch_info['balance_num'] += $stock_out_info['actual_outbound_num'];
        $batch_data = [
            'batch_id' => $batch_info['id'],
            'change_balance_num' => $stock_out_info['actual_outbound_num'],
            'change_reserve_num' => 0,
            'change_type' => 5,
            'change_number' => $stock_out_info['outbound_code'],
        ];
        $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);

        if ($allocation_order_info['allocation_type'] == 2 || $allocation_order_info['allocation_type'] == 3) {
            $stock_in_dedcution_status = $this->returnToStockIn($allocation_order_info,$stock_out_info);
        } else {
            $stock_in_dedcution_status = ['status' => true, 'message' => ''];
        }

        if ($stock_out_status && $stock_in_status && $old_stock_out_status && $new_stock_out_status && $add_log &&
            $allocation_status && $batch_result['status'] == 1 && $stock_in_dedcution_status['status']) {
            //重新计算加权成本 edit xiaowen 2018-2-7
            $new_stock_out_data['before_stock_num'] = $beforeNum;
            $new_stock_out_data['stock_id'] = $stockId ? $stockId : 0;
            $new_stock_out_data['change_num'] = abs($new_stock_out_data['actual_outbound_num']);
            updateStockInCost($new_stock_out_data);
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpReverse/reverseAllocationStockOut');

        return $result;
    }

    /**
     * 销售出库单红冲
     * @param $id
     * @author xiaowen
     * @time 2018-1-21
     * @return mixed
     */
    public function saleStockOutReverse($id)
    {
        if(intval($id)){
            $field = 'so.*,o.order_number,o.invoice_status,o.order_status,o.is_agent,o.outbound_quantity,o.total_sale_wait_num,o.business_type';
            $sale_stock_out_info = $this->getModel('ErpStockOut')->findStockOut(['so.id'=>intval($id)], $field);
            //判断是否存在损耗
            $whereLossOrder = [
                "source_number" => $sale_stock_out_info['outbound_code'] ,
                "type" => 3
            ];
            $lossOrderCount = $this->getEvent("ErpLoss")->getLossOrderEffectiveCount($whereLossOrder) ;

            if($lossOrderCount > 0){
                return  [
                    'status' => 10,
                    'message' => '出库存在损耗单，请在损耗单列表进行相关操作',
                ];
            }
            //判断损耗结束
            $sale_order_info = $this->getModel('ErpSaleOrder')->where(['order_number'=>$sale_stock_out_info['source_number']])->find();
            if($sale_stock_out_info){
                /************** YF Time 2018-12-11 ****************/
                if ( !empty($sale_stock_out_info['retail_inner_order_number']) || $sale_order_info['business_type'] == 4 ) {
                    return ['status' => 0,'message' => '此出库单 属于内部交易单，不允许红冲！'];
                }
                else if(in_array($sale_stock_out_info['outbound_type'], [4,5])){
                    return $result = [
                        'status' => 4,
                        'message' => '盘点出库不能红冲',
                    ];
                }else if($sale_stock_out_info['is_reverse'] == 1){
                    return $result = [
                        'status' => 5,
                        'message' => '红冲的出库单，不能再红冲',
                    ];
                }
                /******************* END *************************/

                /*********************************************
                @ Content 销售 出库红冲
                @ Author xiaowen
                # Time 2019-03-08
                 ************************************************/
                if ( $sale_stock_out_info['outbound_type'] == 1 ) {
                    $result = $this->reverseSaleStockOut($id, $sale_stock_out_info);
                }

                /************************************************
                 * end  销售 出库红冲
                 ***********************************************/

                /*********************************************
                @ Content 采退单 出库红冲
                @ Author YF
                # Time 2018-12-25
                 **********************************************/
                if ( $sale_stock_out_info['outbound_type'] == 3 ) {
                    $result = $this->PurchaseRefundOrderRedRush($sale_stock_out_info);
                    return $result;
                }
                /*********************************************
                @ Content 调拨单 出库红冲
                @ Author guanyu
                # Time 2019-03-04
                 **********************************************/
                if ( $sale_stock_out_info['outbound_type'] == 2 ) {
                    $result = $this->reverseAllocationStockOut($sale_stock_out_info);
                    return $result;
                }
                /*********************************************
                END
                 **********************************************/

            }else{
                $result = [
                    'status' => 9,
                    'message' => '出库单不存在',
                ];
            }
            return $result;
        }
    }

    /**
     * 销退收款红冲
     * @param $collection_id 红冲的退款ID
     * @author xiaowen
     * @time 2018-1-26
     * @return mixed
     */
    public function returnedCollectionReverse($collection_id)
    {
        if ($collection_id) {

            $collection_info = $this->getModel('ErpSaleCollection')->findSaleCollection(['id' => $collection_id]);
            if($collection_info['is_reverse'] == 1){
                return $result = [
                    'status' => 3,
                    'message' => '该退款为红冲记录，无法再红冲',
                ];
            }
            if($collection_info['reversed_status'] == 1){
                return $result = [
                    'status' => 4,
                    'message' => '该退款已红冲，无法再红冲',
                ];
            }
            $order_info = $this->getModel('ErpReturnedOrder')->where(['id' => $collection_info['sale_order_id'], 'order_type' => 1])->find();
            if(empty($order_info)){
                return $result = [
                    'status'   => 21,
                    'message'  => '未查询到符合条件的订单，请刷新后重试！'
                ];
            }

            //------------------end--------------------------------------------------------------------

            # 验证原销售单是否开票 qianbin 2018-04-17
            $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $order_info['source_order_id']]);
            if(intval($sale_order_info['order_status']) == 2 ){
                $result['status']  = 25 ;
                $result['message'] = '销售退货单对应的销售单已为取消状态，无法进行红冲！';
                return $result;
            }
            if(intval($sale_order_info['invoice_status']) != 1 ){
                $result['status']  = 26 ;
                $result['message'] = '销售退货单对应的销售单已开票，请先红冲销售发票！';
                return $result;
            }

            // edit xiaowen 2019-3-18 销退的退款红冲，要先验证销退的入库单是否都红冲-----------------
            $returned_stock_in = $this->getModel("ErpStockIn")->where(['source_number'=>$order_info['order_number'],'storage_type'=>3,'storage_status'=>10, 'is_reverse'=>2, 'reversed_status'=>2])->find();
            if(!empty($returned_stock_in)){
                return $result = [
                    'status'   => 22,
                    'message'  => '该销退单有对应的入库单未红冲，请红冲入库单后再操作'
                ];
            }

            if (getCacheLock('ErpReverse/returnedCollectionReverse')) return ['status' => 99, 'message' => $this->running_msg];
            setCacheLock('ErpReverse/returnedCollectionReverse', 1);
            M()->startTrans();
            //修改原收款信息
            $old_erp_collect_data = [
                'reversed_status' => 1
            ];
            $old_status_collection = $this->getModel('ErpSaleCollection')->saveSaleCollection(['id'=>$collection_id],$old_erp_collect_data);

            $erp_collect_data = [
                'collect_money' => plusConvert($collection_info['collect_money']),
                'sale_order_id' => $order_info['id'],
                'sale_order_number' => $order_info['order_number'],
                'creator' => $this->getUserInfo('dealer_name'),
                'creator_id' => $this->getUserInfo('id'),
                'create_time' => currentTime(),
                'collect_time' => currentTime(),
                /** 银行账套信息改版 - qianbin - 2018.08.07 */
                'bank_id'          => $collection_info['bank_id'],
                'bank_simple_name' => $collection_info['bank_simple_name'],
                'bank_info'        => $collection_info['bank_info'],
                /** 银行账套信息改版 - end - 2018.08.07 */
                'is_reverse' => 1,//标识这条退款为红冲
                'reverse_source' => $collection_info['id'], //基于哪条退款进行红冲，记录原退款ID
                'status' => 1,
                'remark' => '',
                'source_order_type' => $collection_info['source_order_type'],
                'from_sale_order_number' => $collection_info['from_sale_order_number'],
                'company_id' => $collection_info['company_id'],
                'our_company_id' => $collection_info['our_company_id'],
            ];
            //添加erp收款信息
            $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collect_data);

            //修改原订单收款状态
            $returned_order_data = [
                'return_amount_status' => 1,//红冲为未退款
                'return_payed_amount' => $order_info['return_payed_amount'] - $collection_info['collect_money'],
                'updater_id' => $this->getUserInfo('id'),
                'update_time' => currentTime(),
            ];
            $status_order = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => $order_info['id']], $returned_order_data);

            //添加log日志
            $log_data = [
                'return_order_id' => $order_info['id'],
                'return_order_number' => $order_info['order_number'],
                'return_order_type' => 1,
                'log_type' => 21,
                'log_info' => serialize($order_info),
                'create_time' => currentTime(),
                'operator' => $this->getUserInfo('dealer_name'),
                'operator_id' => $this->getUserInfo('id'),
            ];
            $status_log = $this->getModel('ErpReturnedOrderLog')->add($log_data);

            // 如果原销退单确认退款时，选择了转预存。则需要：
            // 1.预存单申请变为无效
            // 2.用户余额减少
            $account_status = true;
            $result = ['status' => 21 , 'message' => '余额扣减有误，请刷新后重试！'];
            if(intval($collection_info['is_prestore_money']) == 1){
                # 查询用户余额
                # 如果余额不足时，不允许红冲
                $where = [
                    'account_type'   => 1,
                    'our_company_id' => $erp_collect_data['our_company_id'],
                    'company_id'     => $erp_collect_data['company_id'],
                ];
                $account_balance = $this->getModel('ErpAccount')->findAccount($where)['account_balance'];
                $account_balance = $account_balance <= 0 ? 0 : $account_balance;
                $account_balance = getNum($account_balance + $erp_collect_data['collect_money']);
                if($account_balance < 0){
                    $account_status = false;
                    $result = [
                        'status' => 22,
                        'message' => '该用户余额不足，请刷新后查看！',
                    ];
                }else{
                    $recharge_order = [
                        'order_status'    => 2,
                        'finance_status'  => 1,
                        'updater'         => $this->getUserInfo('id'),
                        'update_time'     => currentTime(),
                    ];
                    $order_status   = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['from_order_number' =>$collection_info['sale_order_number']], $recharge_order);

                    //修改账户
                    $collection_info['order_number']   = $collection_info['sale_order_number'];
                    $collection_info['object_type']    = 2;
                    $account_status = $this->getEvent('ErpAccount')->changeAccount($collection_info, PRESTORE_TYPE, $erp_collect_data['collect_money']);

                    $account_status = $account_status && $order_status ? true : false;
                }
            }

            if ($old_status_collection && $status_collection && $status_order && $status_log && $account_status) {
                M()->commit();
                $result = [
                    'status' => 1,
                    'message' => '操作成功',
                ];
            } else {
                M()->rollback();
                $result =  $account_status == false ? $result : ['status' => 0, 'message' => '操作失败',];
            }
            cancelCacheLock('ErpReverse/returnedCollectionReverse');
        } else {
            $result = [
                'status' => 2,
                'message' => '收款ID无法获取',
            ];
        }
        return $result;
    }

    /**
     * 销售收款红冲
     * @param $param
     * @author xiaowen
     * @time 2018-1-26
     * @return mixed
     */
    public function saleCollectionReverse($id)
    {
        //获取应收信息
        $collection_info = $this->getModel('ErpSaleCollection')->findSaleCollection(['id' => $id]);

        //对应销售单信息
        $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $collection_info['sale_order_id'],'order_number' => $collection_info['sale_order_number']]);

        //验证单据信息
        $check_result = $this->checkReverseSaleCollection($collection_info,$sale_order_info);

        //判断财务中台开票情况，若已开票，则不允许红冲
        $invoice_status = $this->getEvent('FinanceMiddleGround')->fmgSelectInvoice($id,$sale_order_info['order_number']);
        if ($invoice_status['code'] == 200) {
//            if ($invoice_status['data']['whole_status'] == 2) {
//                return ['status' => 8,'message' => '财务中台数据状态异常，请联系管理员'];
//            }
//            if ($invoice_status['data']['action_list']['action_status'] == 2) {
//                return ['status' => 9,'message' => '财务中台销售订单状态异常，请联系管理员'];
//            }
            if ($invoice_status['data']['action_list'][0]['invoice_status'] != 1) {
                return ['status' => 10,'message' => '财务中台销售订单已开发票，请检查'];
            }
        }

        if ($check_result['status'] != 1) {
            return $check_result;
        }

        if (getCacheLock('ErpReverse/saleCollectionReverse')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/saleCollectionReverse', 1);

        M()->startTrans();

        //红冲前单据影响待提数量
        $before_sale_order_info = $this->calculateSaleWaitBySale($sale_order_info);

        $sale_order_info['collected_amount'] -= ($collection_info['collect_money'] + $collection_info['balance_deduction']);

        //红冲后单据影响待提数量
        $after_sale_order_info = $this->calculateSaleWaitBySale($sale_order_info);

        //影响对应库存待提数量
        $change_num = $sale_order_info['pay_type'] == 5 && $after_sale_order_info['effect_sale_wait'] == 0 ? $sale_order_info['total_sale_wait_num'] * -1
            : $after_sale_order_info['effect_sale_wait'] - $before_sale_order_info['effect_sale_wait'];
        if ($change_num != 0) {
            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $after_sale_order_info['goods_id'],
                'object_id' => $after_sale_order_info['storehouse_id'],
                'stock_type' => $after_sale_order_info['is_agent'] == 1 ? 2 : getAllocationStockType($after_sale_order_info['storehouse_id']),
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //------------------组装库存表的字段值--------------------------
            $data = [
                'goods_id' => $after_sale_order_info['goods_id'],
                'object_id' => $after_sale_order_info['storehouse_id'],
                'stock_type' => $after_sale_order_info['is_agent'] == 1 ? 2 : getAllocationStockType($after_sale_order_info['storehouse_id']),
                'region' => $after_sale_order_info['region'],
                'sale_reserve_num' => $stock_info['sale_reserve_num'] - $change_num,
                'sale_wait_num' => $stock_info['sale_wait_num'] + $change_num,
            ];
            $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的在途库存
            $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的在途库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $after_sale_order_info['order_number'],
                'object_type' => 1,
                'log_type' => 14,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $change_num, $orders);
        } else {
            $stock_status = true;
        }

        //修改原收款信息
        $old_erp_collect_data = [
            'reversed_status' => 1
        ];
        $old_status_collection = $this->getModel('ErpSaleCollection')->saveSaleCollection(['id'=>$id],$old_erp_collect_data);

        //录入一条负数的收款记录冲平
        $erp_collection_data = [
            'collect_money' => $collection_info['collect_money'] * -1,
            'balance_deduction' => $collection_info['balance_deduction'] * -1,
            'order_collected_money' => $after_sale_order_info['collected_amount'],
            'sale_order_id' => $collection_info['sale_order_id'],
            'sale_order_number' => $collection_info['sale_order_number'],
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'collect_time' => currentTime(),
            'status' => 10,
            'remark' => '',
            'is_reverse' => 1,
            'reverse_source' => $collection_info['id'],
            'source_order_type' => $collection_info['source_order_type'],
            'from_sale_order_number' => $collection_info['from_sale_order_number'],
            'company_id' => $collection_info['company_id'],
            'our_company_id' => $collection_info['our_company_id'],
            /** 银行账套信息改版 - qianbin - 2018.08.09 */
            'bank_id'          => intval($collection_info['bank_id']),
            'bank_simple_name' => trim($collection_info['bank_simple_name']),
            'is_prestore_money'=> intval($collection_info['is_prestore_money']),
            'bank_info'        => $collection_info['bank_info'],
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
        ];
        $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collection_data);
        //若有预存账户余额扣减，则回滚对应的账户
        if ($collection_info['balance_deduction'] != 0) {
            # 应收红冲，抵扣余额生成预存明细 qianibn 2018.08.09 ***********************
            $data = [
                'order_number'    => erpCodeNumber(13)['order_number'],
                'add_order_time'  => currentTime(),
                'our_company_id'  => session('erp_company_id'),
                'region'          => $sale_order_info['region'],
                'user_id'         => $sale_order_info['user_id'],
                'company_id'      => $sale_order_info['company_id'],
                'collection_info' => $sale_order_info['user_bank_info'],
                'order_type'      => 1 ,
                'recharge_type'   => 11 , // 预存款
                'recharge_amount' => $collection_info['balance_deduction'],
                'order_status'    => 10,
                'finance_status'  => 10,
                'dealer_name'     => $this->getUserInfo('dealer_name'),
                'creater'         => $this->getUserInfo('id'),
                'create_time'     => currentTime(),
                'remark'          => '来源销售单号: '.trim($collection_info['from_sale_order_number']).'&红冲销售单退还余额自动生成',
                'from_order_number'  => trim($collection_info['from_sale_order_number']),
                'apply_finance_time' => currentTime(),
            ];
            $order_status = $id = $this->getModel('ErpRechargeOrder')->addRechargeOrder($data);
            //新增log
            $log_data = [
                'recharge_id'           => $id,
                'recharge_order_number' => $data['order_number'],
                'order_type'            => 1,
                'log_info'              => serialize($data),
                'log_type'              => 1,
                'create_time'           =>  currentTime(),
                'operator'              =>  $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0,
                'operator_id'           =>  $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0,
            ];
            $status = $this->getModel('ErpRechargeOrderLog')->add($log_data);
            # ***********************end**********************
            $after_sale_order_info['object_type'] = 3;//单据类型：销售单
            $accout_status = $this->getEvent('ErpAccount')->changeAccount($after_sale_order_info, PRESTORE_TYPE, $collection_info['balance_deduction']);
            $accout_status = $order_status && $accout_status && $status ? true : false ;
        } else {
            $accout_status = true;
        }

        //更新订单信息
        $update_order_data = [
            'collected_amount' => $after_sale_order_info['collected_amount'],
            'collection_status' => $after_sale_order_info['collection_status'],
            'total_sale_wait_num' => $after_sale_order_info['pay_type'] == 5 ? $after_sale_order_info['total_sale_wait_num'] + $change_num : $after_sale_order_info['total_sale_wait_num'],
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $status_purchase_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $collection_info['sale_order_id']], $update_order_data);

        //更新日志信息
        $log_data = [
            'sale_order_id' => $after_sale_order_info['id'],
            'sale_order_number' => $after_sale_order_info['order_number'],
            'log_type' => 9,
            'log_info' => serialize($after_sale_order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);

        //撤销财务中台的开票申请
        if ($sale_order_info['pay_type'] != 2 && $sale_order_info['collection_status'] == 10) {
            $invoice_cancel_status = $this->getEvent('FinanceMiddleGround')->fmgCancelInvoice($sale_order_info['id'],$sale_order_info['order_number']);
        } else {
            $invoice_cancel_status['code'] = 200;
        }

        if ($stock_status && $old_status_collection && $status_collection && $accout_status && $status_purchase_order &&
            $status_log && $invoice_cancel_status['code'] == 200 || $invoice_cancel_status['code'] == 1003) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }
        cancelCacheLock('ErpReverse/saleCollectionReverse');
        return $result;
    }

    /**
     * 销售收款红冲数据验证
     * @author guanyu
     * @time 2018-01-26
     */
    public function checkReverseSaleCollection($collection_info,$sale_order_info)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if ($sale_order_info['retail_inner_order_number'] != '') {
            $result['status'] = 2;
            $result['message'] = '对应销售单由内部交易单生成，不允许红冲收款';
            return $result;
        }
        if ($collection_info['reversed_status'] == 1) {
            $result['status'] = 2;
            $result['message'] = '该笔收款申请已红冲，请检查';
            return $result;
        }

        if ($collection_info['is_reverse'] == 1) {
            $result['status'] = 3;
            $result['message'] = '该笔收款为红冲收款，禁止操作';
            return $result;
        }

        if (empty($sale_order_info)) {
            $result['status'] = 4;
            $result['message'] = '对应销售单不存在，请检查';
            return $result;
        }

        if ($sale_order_info['invoice_status'] != 1) {
            $result['status'] = 5;
            $result['message'] = '对应销售单已开票，请红冲发票后再进行操作';
            return $result;
        }

        if ($sale_order_info['outbound_quantity'] != 0) {
            $result['status'] = 6;
            $result['message'] = '对应销售单已出库，请红冲出库单后再进行操作';
            return $result;
        }

        return $result;
    }

    /**
     * 根据销售单信息计算待提数量
     * @author guanyu
     * @time 2018-01-26
     */
    public function calculateSaleWaitBySale($sale_order_info)
    {
        $prepay = setNum(round(getNum($sale_order_info['order_amount']) * getNum($sale_order_info['prepay_ratio']) / 100, 2));

        if ($sale_order_info['collected_amount'] <= 0) {

            $sale_order_info['collection_status'] = 1;
            $sale_order_info['effect_sale_wait'] = 0;

        } elseif ($sale_order_info['collected_amount'] < $prepay && $sale_order_info['pay_type'] == 5) {

            $sale_order_info['collection_status'] = 2;
            $sale_order_info['effect_sale_wait'] = 0;

        } elseif ($sale_order_info['collected_amount'] == $prepay && $sale_order_info['pay_type'] == 5) { //定金锁价刚好达到预付 不需要影响库存 edit xiaowen 2017-6-8

            $sale_order_info['collection_status'] = 3;
            $sale_order_info['effect_sale_wait'] = 0;

        } elseif ($sale_order_info['collected_amount'] > $prepay && $sale_order_info['collected_amount'] < $sale_order_info['order_amount'] && $sale_order_info['pay_type'] == 5) {

            $sale_order_info['collection_status'] = 4;
            $sale_order_info['effect_sale_wait'] = floor(setNum(($sale_order_info['collected_amount'] - $prepay) / $sale_order_info['price']));

        } elseif ($sale_order_info['collected_amount'] > 0 && $sale_order_info['collected_amount'] < $sale_order_info['order_amount']) {

            $sale_order_info['collection_status'] = 4;
            $sale_order_info['effect_sale_wait'] = 0;

        } elseif ($sale_order_info['collected_amount'] == $sale_order_info['order_amount']) {

            $sale_order_info['collection_status'] = 10;
            $sale_order_info['effect_sale_wait'] = $sale_order_info['buy_num'];

        }

        if (in_array($sale_order_info['pay_type'], [2,4])) {
            $sale_order_info['effect_sale_wait'] = 0;
        }

        return $sale_order_info;
    }

    /**
     * 调拨单红冲
     * @author qianbin
     * @time 2018-01-21
     */
    public function reverseAllocationOrder($id = 0)
    {
        //对应调拨单信息
        $allocation_order_info = $this->getModel('ErpAllocationOrder')->where(['id' => $id])->find();

        //验证单据信息
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if (empty($allocation_order_info)) {
            $result['status'] = 3;
            $result['message'] = '未找到调拨单信息，请检查';
            return $result;
        }

        if ($allocation_order_info['storage_status'] != 2) {
            $result['status'] = 3;
            $result['message'] = '调拨单对应的入库单已审核，请优先处理，请检查';
            return $result;
        }

        if ($allocation_order_info['outbound_status'] != 2) {
            $result['status'] = 4;
            $result['message'] = '调拨单对应的出库单已审核，请优先处理，请检查';
            return $result;
        }

        if ($allocation_order_info['status'] != 10) {
            $result['status'] = 5;
            $result['message'] = '调拨单状态有误，请检查';
            return $result;
        }

        if (getCacheLock('ErpReverse/reverseAllocationOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reverseAllocationOrder', 1);
        M()->startTrans();

        //修改调拨单状态
        $old_stock_out_status = $this->getModel('ErpAllocationOrder')->where(['id'=>$id])->save(['status'=>2]);

        //更新来源库存的配送预留
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $allocation_order_info['goods_id'],
            'object_id' => $allocation_order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($allocation_order_info['out_storehouse']),
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $allocation_order_info['goods_id'],
            'object_id' => $allocation_order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($allocation_order_info['out_storehouse']),
            'facilitator_id' => $allocation_order_info['allocation_type'] == 2 || $allocation_order_info['allocation_type'] == 3 ? $allocation_order_info['out_facilitator_id'] : '',
            'region' => $allocation_order_info['out_region'],
            'allocation_wait_num' => $stock_info['allocation_wait_num'] - $allocation_order_info['num'],
        ];
        $stock_info['allocation_wait_num'] = $data['allocation_wait_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $allocation_order_info['order_number'],
            'object_type' => 5,
            'log_type' => 9,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $allocation_order_info['num'], $orders);

        if ($old_stock_out_status || $stock_status) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpReverse/reverseAllocationOrder');

        return $result;

    }

    /** --------------------------------------------------乾斌------------------------------------------------------- */

    # 1.调拨单红冲
    # 2.销退单红冲

    /**
     * 调拨单红冲
     * @author qianbin
     * @time 2018-01-21
     */
    public function reverseAllocationOrderOld($id = 0 ,$type = 1)
    {
        # $type 做标识。
        # 1 主动红冲调拨单，回滚Front收油单
        # 2 Front回滚收油单，被动红冲调拨单
        $this->reverse_allocation_type = $type;
        if (getCacheLock('ErpReverse/reverseAllocationOrder'))  return ['status' => 99, 'message' => $this->running_msg];
        $stock_status = true ;
        # 验证参数-----------------
        $result = $this->checkReverseAllocationOrder($id);
        if($result['status'] != 1 ){
            return ['status' => $result['status'] , 'message' => $result['message']];
        }
        # 订单信息
        $order_info = $result['order_info'];

        setCacheLock('ErpReverse/reverseAllocationOrder', 1);

        # --------------------------------------------回滚库存----------------------------------------------------------
        # ------------------------------------已确认的订单进行红冲------------------------------------------------------

        # 出库单
        $stock['object_id_out']  = in_array(intval($order_info['allocation_type']), [1, 4]) ? $order_info['out_storehouse'] : $order_info['out_storehouse'];
        $stock['stock_type_out'] = in_array(intval($order_info['allocation_type']), [1, 4]) ? getAllocationStockType($order_info['out_storehouse']) : 4;
        $stock['region_out']    = $order_info['out_region'];

        # 入库单
        $stock['object_id_in']   = in_array(intval($order_info['allocation_type']), [3, 4]) ? $order_info['in_storehouse'] : $order_info['in_storehouse'];
        $stock['stock_type_in']  = in_array(intval($order_info['allocation_type']), [3, 4]) ? getAllocationStockType($order_info['in_storehouse']) : 4;
        $stock['region_in']      = $order_info['in_region'];

        # 查询出库方库存
        $stock_out_where = [
            'goods_id'      => $order_info['goods_id'],
            'object_id'     => $stock['object_id_out'],
            'stock_type'    => $stock['stock_type_out'],
            'region'        => $stock['region_out']
        ];
        $stock_out_info = $this->getEvent('ErpStock')->getStockInfo($stock_out_where);

        # 出库方-----------------------------------------------
        if (intval($order_info['outbound_status']) == 2 && intval($order_info['storage_status'] == 2)) {
            # [未出库 | 未入库]---------------------------------
            # 1.  城市仓->服务商
            # 3.  服务商->城市仓
            # 4.  城市仓->城市仓
            # 逻辑：减出库方配货待提，配货预留不变，入库方不做调整
            # -------------------------------------------------
            $param = [
                'type'            => 1 ,
                'stock'           => $stock ,
                'order_info'      => $order_info ,
                'stock_out_info'  => $stock_out_info,
                'stock_out_where' => $stock_out_where ,
            ];
            $stock_status = $this->changeAllocationOrderstock($param);

        } else if (intval($order_info['outbound_status']) == 1 && intval($order_info['storage_status'] == 2)) {
            # [已出库 | 未入库]---------------------------------
            # 1.  城市仓->服务商
            # 3.  服务商->城市仓
            # 4.  城市仓->城市仓
            # 加出库方物理库存，减入库方在途库存，红冲出库单
            # -------------------------------------------------
            $param = [
                'type'            => 2 ,
                'stock'           => $stock ,
                'order_info'      => $order_info ,
                'stock_out_info'  => $stock_out_info,
                'stock_out_where' => $stock_out_where ,
            ];
            $stock_status = $this->changeAllocationOrderstock($param);

        } else if (intval($order_info['outbound_status']) == 1 && intval($order_info['storage_status']) == 1) {
            # [已出库 | 已入库]---------------------------------
            # 1.  城市仓->服务商      减服务网点物理库存，加城市仓物理库存，红冲出库单、入库单，订单状态变为已取消
            # 2.  服务商->服务商      加出库方（服务网点）仓物理库存，减入库方（服务网点）物理库存，红冲出库单、入库单，订单状态变为已取消
            # 3.  服务商->城市仓      加服务网点物理库存，减城市仓物理库存，红冲出库单、入库单，订单状态变为已取消
            # 4.  城市仓->城市仓      加出库方城市仓物理库存，减入库方城市仓物理库存，红冲出库单、入库单，订单状态变为已取消
            $param = [
                'type'            => 3 ,
                'stock'           => $stock ,
                'order_info'      => $order_info ,
                'stock_out_info'  => $stock_out_info,
                'stock_out_where' => $stock_out_where ,
            ];
            $stock_status = $this->changeAllocationOrderstock($param);

            # ---------------------------------end-----------------------------------
        }

        if($stock_status['status'] != 1){
            cancelCacheLock('ErpReverse/reverseAllocationOrder');
            return [ 'status' => 0, 'message' => $stock_status['message']];
        }
        if($stock_status) {
            $result = ['status' => 1, 'message' => '操作成功'];
        } else {
            $result = [ 'status' => 0, 'message' => '操作失败'];
        }
        cancelCacheLock('ErpReverse/reverseAllocationOrder');
        return $result;
    }

    /**
     * 服务商出调拨单回滚出库单还回对应入库单可用
     * @author guanyu
     * @time 2018-03-13
     * @param
     * $order_info 业务单号
     * $stock_out_info 出库单号
     * $storage_code 入库单号，没有这个参数返还使用到的所有入库单可用
     * @param $param
     * @return array $result
     */
    public function returnToStockIn($order_info,$stock_out_info = [])
    {
        if (empty($stock_out_info)) {
            $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number' => $order_info['order_number'],'source_object_id' => $order_info['id']])->find();
        }
        $deduction_where = [
            'outbound_code' => $stock_out_info['outbound_code']
        ];
        $deduction_info = $this->getModel('ErpStockInDeduction')->where($deduction_where)->select();
        $stock_in_status_all = true;
        $stock_out_deduction_num = 0;
        foreach ($deduction_info as $key => $value) {
            $stock_in = $this->getModel('ErpStockIn')->where(['storage_code' => $value['source_stock_in_number']])->find();
            $new_balance_num = $stock_in['balance_num'] + $value['deduction_num'];
            $new_balance_num_litre = $stock_in['balance_num_litre'] + tonToLiter($value['deduction_num'],$stock_in['outbound_density']);
            $new_deduction_num = $stock_in['deduction_num'] - $value['deduction_num'];
            $stock_out_deduction_num += $value['deduction_num'];
            //修改入库单可用数量
            $stock_in_data = [
                'update_time'   => currentTime(),
                'balance_num'   => $new_balance_num,
                'balance_num_litre'   => $new_balance_num_litre,
                'deduction_num'   => $new_deduction_num,
            ];
            $stock_in_status = $this->getModel('ErpStockIn')->saveStockIn(['storage_code' => $stock_in['storage_code']],$stock_in_data);
            //插入一条逆向冲减记录
            $deduction_data = [
                'source_stock_in_number' => $stock_in['storage_code'],
                'type' => 2,
                'outbound_code' => $stock_out_info['outbound_code'],
                'before_balance_num' => $stock_in['balance_num'],
                'after_balance_num' => $new_balance_num,
                'deduction_num' => $value['deduction_num'] * -1,
                'deduction_type' => 2,
                'create_time' => currentTime(),
            ];
            $deduction_statsu = $this->getModel('ErpStockInDeduction')->add($deduction_data);
            $stock_in_status_all = $stock_in_status && $stock_in_status_all && $deduction_statsu ? true : false;
        }
        $this->getModel('ErpStockOut')->where(['outbound_code'=>$stock_out_info['outbound_code']])->setDec('deduction_num',$stock_out_deduction_num);
        return ['status' => $stock_in_status_all,'message' => '入库单可用归还失败，请联系技术部'];
    }

    /*
     * --------------------------------------------------------
     * 调拨单红冲 - 已确认调拨单
     * $type  : 1 未出库 未入库  2 已出库 未入库 3 已出库已入库
     * Author：qianbin        Time：2018-02-06
     * --------------------------------------------------------
     */
    public function changeAllocationOrderstock($param = []){
        $stock_out_order_status = true ;
        $deduction_status_all = true;
        $stock_in_order_status  = true ;
        $workflow_status        = true ;
        $update_order           = true ;
        $type            = 1;
        $order_info      = [];
        $stock           = [];
        $stock_out_info  = [];
        $stock_out_where = [];
        extract($param);
        switch (intval($type)){
            case 1:
                M()->startTrans();
                # 作废审批流
                $workflow_status = $this->changeErpWorkFlow($order_info);

                # [未出库 | 未入库]---------------------------------
                # 1.  城市仓->服务商
                # 3.  服务商->城市仓
                # 4.  城市仓->城市仓
                # 逻辑：减出库方配货待提，配货预留不变，入库方不做调整
                # -------------------------------------------------
                $data = [
                    'goods_id'   => $order_info['goods_id'],
                    'object_id'  => $stock['object_id_out'],
                    'stock_type' => $stock['stock_type_out'],
                    'region'     => $stock['region_out'],
                    'allocation_wait_num' => $stock_out_info['allocation_wait_num'] - $order_info['num'],
                ];
                $stock_out_info['allocation_wait_num'] = $data['allocation_wait_num'];
                $log_info_data = [
                    'event'     => '调拨单红冲-未出库未入库，减少出库方配货待提，入库方不变：',
                    'key'       => $order_info['order_number'],
                    'request'   => '确定库存：' . json_encode($stock_out_where) . '。更改预留库存为：' . $stock_out_info['allocation_reserve_num'],
                ];
                log_write($log_info_data);

                # 计算可用库存, 添加日志 , 更新库存
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_out_info);
                $orders = [
                    'object_number' => $order_info['order_number'],
                    'object_type'   => 5,
                    'log_type'      => 12,
                ];
                $stock_out_order_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['num'], $orders);

//                $stock_out_order_status = $this->changeSaveStock($stock_out_info, $data, $order_info, $order_info['num']);
                $stock_out_order_status = ($stock_out_order_status && $workflow_status)  ? true : false ;

                break;
            case 2:
                M()->startTrans();
                # 作废审批流
                $workflow_status = $this->changeErpWorkFlow($order_info);

                # [已出库 | 未入库]---------------------------------
                # 1.  城市仓->服务商
                # 3.  服务商->城市仓
                # 4.  城市仓->城市仓
                # 加出库方物理库存，减入库方在途库存，红冲出库单
                # -------------------------------------------------

                # 成本字段 qianbin 2018-02-27
                $order_info['beforeCostNum'] = $stock_out_info['stock_num'];
                $order_info['newCostNum']    = $order_info['actual_out_num'];
                $order_info['stockId']       = $stock_out_info['id'];
                # end

                # 生成红冲出库单
                $stock_out_result = $this->reverseStockOut($order_info);
                $stock_out_status = $stock_out_result['status'];
                $log_info_data = [
                    'event'     => '调拨单红冲-已出库未入库，红冲出库单：',
                    'key'       => $order_info['order_number'],
                    'request'   => '红冲出库单数据状态：' . json_encode($stock_out_status),
                ];
                log_write($log_info_data);

                # 出库方------
                # 加出库方物理库存---------------------------------------------------------
                $out_data = [
                    'goods_id'   => $order_info['goods_id'],
                    'object_id'  => $stock['object_id_out'],
                    'stock_type' => $stock['stock_type_out'],
                    'region'     => $stock['region_out'],
                    'stock_num'  => $stock_out_info['stock_num'] + $order_info['actual_out_num'],
                ];
                $stock_out_info['stock_num'] = $out_data['stock_num'];
                # 计算可用库存, 添加日志 , 更新库存
                $log_info_data = [
                    'event'     => '调拨单红冲-已出库未入库，加出库方物理库存：',
                    'key'       => $order_info['order_number'],
                    'request'   => '确定库存：' . json_encode($stock_out_where) . '。更改物理库存为：' . $stock_out_info['stock_num'],
                ];
                log_write($log_info_data);

                //------------------计算出新的可用库存----------------------------
                $out_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_out_info);
                $orders = [
                    'object_number' => $stock_out_result['outbound_code'],
                    'object_type'   => 3,
                    'log_type'      => 12,
                ];
                $stock_out_order_status = $this->getEvent('ErpStock')->saveStockInfo($out_data, $order_info['actual_out_num'] * -1, $orders);

//                $stock_out_order_status = $this->changeSaveStock($stock_out_info, $out_data, $order_info, $order_info['actual_out_num']);

                # 入库方库存信息
                $stock_in_where = [
                    'goods_id'      => $order_info['goods_id'],
                    'object_id'     => $stock['object_id_in'],
                    'stock_type'    => $stock['stock_type_in'],
                    'region'        => $stock['region_in'],
                ];
                $stock_in_info = $this->getEvent('ErpStock')->getStockInfo($stock_in_where);

                # 入库方----------
                # 减入库方在途库存---------------------------------------------------------
                $in_data = [
                    'goods_id'      => $order_info['goods_id'],
                    'object_id'     => $stock['object_id_in'],
                    'stock_type'    => $stock['stock_type_in'],
                    'region'        => $stock['region_in'],
                    'transportation_num' => $stock_in_info['transportation_num'] - $order_info['actual_out_num'],
                ];
                $stock_in_info['transportation_num'] = $in_data['transportation_num'];
                # 计算可用库存, 添加日志 , 更新库存
                $log_info_data = [
                    'event'     => '调拨单红冲-已出库未入库，减入库方在途库存：',
                    'key'       => $order_info['order_number'],
                    'request'   => '确定库存：' . json_encode($stock_out_where) . '。更改物理库存为：' . $order_info['actual_out_num'],
                ];
                log_write($log_info_data);

                //------------------计算出新的可用库存----------------------------
                $in_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_in_info);
                $orders = [
                    'object_number' => $order_info['order_number'],
                    'object_type'   => 5,
                    'log_type'      => 12,
                ];
                $stock_in_order_status = $this->getEvent('ErpStock')->saveStockInfo($in_data, $order_info['actual_out_num'] * -1, $orders);
//                $stock_in_order_status = $this->changeSaveStock($stock_in_info, $in_data, $order_info, $order_info['actual_out_num']);

                $stock_in_order_status = ($stock_out_order_status && $stock_in_order_status && $stock_out_status && $workflow_status)  ? true : false ;


                break;
            case 3:
                # [已出库 | 已入库]---------------------------------
                # 1.  城市仓->服务商      减服务网点物理库存，加城市仓物理库存，红冲出库单、入库单，订单状态变为已取消
                # 2.  服务商->服务商      加出库方（服务网点）仓物理库存，减入库方（服务网点）物理库存，红冲出库单、入库单，订单状态变为已取消
                # 3.  服务商->城市仓      加服务网点物理库存，减城市仓物理库存，红冲出库单、入库单，订单状态变为已取消
                # 4.  城市仓->城市仓      加出库方城市仓物理库存，减入库方城市仓物理库存，红冲出库单、入库单，订单状态变为已取消

                # 为方便添加事务，所以先回滚入库方

                # -----------------------------------------入库方库存信息------------------------------------------------
                # 1.如果为城市仓  -> 减少库存 ，生成红冲入库单 -> java接口计算成本（异步）
                # 2.如果为服务网点-> java 红冲零售出库单 -> 回滚库存 -> 是否成功 -> 回滚入库方库存，生成红冲入库单-> 接口重新计算成本

                if ($stock['stock_type_in'] != 4) {

                    M()->startTrans();

                    # 入库方库存信息
                    $stock_in_where = [
                        'goods_id'      => $order_info['goods_id'],
                        'object_id'     => $stock['object_id_in'],
                        'stock_type'    => $stock['stock_type_in'],
                        'region'        => $stock['region_in'],
                    ];
                    $stock_in_info = $this->getEvent('ErpStock')->getStockInfo($stock_in_where);
                    if(intval($stock_in_info['stock_num']) < intval($order_info['actual_in_num'])){
                        M()->rollback();
                        return  $result = [
                            'status' =>0,
                            'message' =>'入库方物理库存不足,无法红冲调拨单',
                        ];
                    }
                    # 成本字段 qianbin 2018-02-27
                    $order_info['beforeCostNum'] = $stock_in_info['stock_num'];
                    $order_info['newCostNum']    = $order_info['actual_in_num'];
                    $order_info['stockId']       = $stock_in_info['id'];
                    # end

                    # 生成红冲入库单
                    $stock_in_result = $this->reverseStockInOrder($order_info ,2);
                    $stock_in_status = $stock_in_result['status'];
                    $log_info_data = [
                        'event' => '调拨单红冲-已出库已入库，入库方为城市仓，红冲入库单：',
                        'key' => $order_info['order_number'],
                        'request' => '红冲出库单数据：' . json_encode($stock_in_status),
                    ];
                    log_write($log_info_data);

                    # 入库方-----
                    # 加出库方物理库存---------------------------------------------------------
                    $in_data = [
                        'goods_id'      => $order_info['goods_id'],
                        'object_id'     => $stock['object_id_in'],
                        'stock_type'    => $stock['stock_type_in'],
                        'region'        => $stock['region_in'],
                        'stock_num'     => $stock_in_info['stock_num'] - $order_info['actual_in_num'],
                    ];
                    $stock_in_info['stock_num'] = $in_data['stock_num'];
                    # 计算可用库存, 添加日志 , 更新库存
                    $log_info_data = [
                        'event'     => '调拨单红冲-已出库已入库，减少入库方物理库存：',
                        'key'       => $order_info['order_number'],
                        'request'   => '确定库存：' . json_encode($stock_in_where) . '。更改物理库存为：' . $stock_in_info['stock_num']
                    ];
                    log_write($log_info_data);

                    //------------------计算出新的可用库存----------------------------
                    $in_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_in_info);
                    $orders = [
                        'object_number' => $stock_in_result['storage_code'],
                        'object_type'   => 4,
                        'log_type'      => 12,
                    ];
                    $stock_in_order_status = $this->getEvent('ErpStock')->saveStockInfo($in_data, $order_info['actual_in_num'] * -1, $orders);
//                    $stock_in_order_status = $this->changeSaveStock($stock_in_info, $in_data, $order_info, $order_info['actual_in_num']);

                    # 重新计算成本
                    //updateStockInCost($stock_in_where);
                } else {

                    # java 红冲零售出库单 -> 回滚库存 -> 是否成功 -> 回滚入库方库存，生成红冲入库单-> 接口重新计算成本
                    $stock_in_order_info = $this->getModel('ErpStockIn')->field('id,storage_code,reversed_status')->where(['storage_type' => 2, 'source_number' => $order_info['order_number'], 'source_object_id' => $order_info['id']])->find();
                    $log_info_data = [
                        'event' => '调拨单红冲-已出库已入库，入库方为服务网点，java接口红冲零售出库单：',
                        'key' => $order_info['order_number'],
                        'request' => '调拨单入库单单号为：' . $stock_in_order_info['storage_code'].'该入库单红冲状态：'.$stock_in_order_info['reversed_status'],
                    ];
                    log_write($log_info_data);
                    if(intval($stock_in_order_info['reversed_status']) == 1 && $order_info['status'] == 2){
                        return  $result = [
                            'status' =>0,
                            'message' =>'该调拨单对应的入库单已被红冲，请检查后重试！',
                        ];
                    }
                    if(empty($stock_in_order_info)){
                        return  $result = [
                            'status' =>0,
                            'message' =>'未查询到调拨入库单，零售出库单无法红冲！',
                        ];
                    }
                    //只有入库未红冲且调拨单为已确认且已入库，才调java接口红冲 edit xiaowen 2018-6-12
                    if(intval($stock_in_order_info['reversed_status']) == 2 && $order_info['status'] == 10 && $order_info['storage_status'] == 1){

                        $status = reverseRetailOrder($stock_in_order_info['storage_code']);
                        if(!$status){
                            $log_info_data = [
                                'event' => '调拨单红冲-已出库已入库，入库方为服务网点，java接口红冲零售出库单：',
                                'key' => $order_info['order_number'],
                                'request' => '零售出库单回滚失败：',
                            ];
                            log_write($log_info_data);
                            return  $result = [
                                'status' =>0,
                                'message' =>'零售出库单回滚失败，请重试！',
                            ];
                        }
                    }

                    M()->startTrans();

                    # 入库方-----
                    # 入库方库存信息
                    $stock_in_where = [
                        'goods_id'      => $order_info['goods_id'],
                        'object_id'     => $stock['object_id_in'],
                        'stock_type'    => $stock['stock_type_in'],
                        'region'        => $stock['region_in'],
                    ];
                    $stock_in_info = $this->getEvent('ErpStock')->getStockInfo($stock_in_where);

                    $log_info_data = [
                        'event' => '调拨单红冲-已出库已入库，入库方为服务网点---------确定库存信息：',
                        'key' => $order_info['order_number'],
                        'request' => '确认库存的where：'.var_export($stock_in_where,true).'确认库存信息：'.var_export($stock_in_info,true),
                    ];
                    log_write($log_info_data);
                    # 成本字段 qianbin 2018-02-27
                    $order_info['beforeCostNum'] = $stock_in_info['stock_num'];
                    $order_info['stockId']       = $stock_in_info['id'];
                    # end
                    $log_info_data = [
                        'event' => '调拨单红冲-已出库已入库，入库方为服务网点：',
                        'key' => $order_info['order_number'],
                        'request' => '入库方物理库存和应回滚库存：'.intval($stock_in_info['stock_num']).'<===>'.intval($order_info['actual_in_num']),
                    ];
                    log_write($log_info_data);

                    # 生成红冲入库单
                    $stock_in_result = $this->reverseStockInOrder($order_info);
                    $stock_in_status = $stock_in_result['status'];
                    $log_info_data = [
                        'event'     => '调拨单红冲-已出库已入库，入库方为服务网点，红冲入库单：',
                        'key'       => $order_info['order_number'],
                        'request'   => '红冲出库单数据：' . json_encode($stock_in_status),
                    ];
                    log_write($log_info_data);

                    if(intval($stock_in_info['stock_num']) < intval($order_info['actual_in_num'])){
                        $log_info_data = [
                            'event' => '调拨单红冲-已出库已入库，入库方为服务网点：',
                            'key' => $order_info['order_number'],
                            'request' => '入库方物理库存不足,无法红冲调拨单：'.intval($stock_in_info['stock_num']).'<===>'.intval($order_info['actual_in_num']),
                        ];
                        log_write($log_info_data);
                        M()->rollback();
                        return  $result = [
                            'status' =>0,
                            'message' =>'入库方物理库存不足,无法红冲调拨单',
                        ];
                    }
                    $in_data = [
                        'goods_id'      => $order_info['goods_id'],
                        'object_id'     => $stock['object_id_in'],
                        'stock_type'    => $stock['stock_type_in'],
                        'region'        => $stock['region_in'],
                        'stock_num'     => $stock_in_info['stock_num'] - $order_info['actual_in_num'],
                    ];
                    $stock_in_info['stock_num'] = $in_data['stock_num'];
                    # 计算可用库存, 添加日志 , 更新库存
                    $log_info_data = [
                        'event'     => '调拨单红冲-已出库已入库，减少入库方物理库存：',
                        'key'       => $order_info['order_number'],
                        'request'   => '确定库存：' . json_encode($stock_in_where) . '。更改物理库存为：' . $stock_in_info['stock_num']
                    ];
                    log_write($log_info_data);

                    //------------------计算出新的可用库存----------------------------
                    $in_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_in_info);
                    $orders = [
                        'object_number' => $stock_in_result['storage_code'],
                        'object_type'   => 4,
                        'log_type'      => 12,
                    ];
                    $stock_in_order_status = $this->getEvent('ErpStock')->saveStockInfo($in_data, $order_info['actual_in_num'] * -1, $orders);
//                    $stock_in_order_status = $this->changeSaveStock($stock_in_info, $in_data, $order_info, $order_info['actual_in_num']);

                    # 重新计算成本
                    //updateStockInCost($stock_in_info);
                }

                # 成本字段 qianbin 2018-02-27
                $order_info['beforeCostNum'] = $stock_out_info['stock_num'];
                $order_info['newCostNum']    = $order_info['actual_out_num'];
                $order_info['stockId']       = $stock_out_info['id'];
                # end

                # 生成红冲出库单
                $stock_out_result = $this->reverseStockOut($order_info);
                $stock_out_status = $stock_out_result['status'];
                $log_info_data = [
                    'event'   => '调拨单红冲-已出库已入库，红冲出库单：',
                    'key'     => $order_info['order_number'],
                    'request' => '红冲出库单数据状态：' . json_encode($stock_out_status),
                ];
                log_write($log_info_data);

                # 出库方------
                # 加出库方物理库存---------------------------------------------------------
                $out_data = [
                    'goods_id'      => $order_info['goods_id'],
                    'object_id'     => $stock['object_id_out'],
                    'stock_type'    => $stock['stock_type_out'],
                    'region'        => $stock['region_out'],
                    'stock_num'     => $stock_out_info['stock_num'] + $order_info['actual_out_num'],
                ];
                $stock_out_info['stock_num'] = $out_data['stock_num'];
                # 计算可用库存, 添加日志 , 更新库存
                $log_info_data = [
                    'event'     => '调拨单红冲-已出库已入库，加出库方物理库存：',
                    'key'       => $order_info['order_number'],
                    'request'   => '确定库存：' . json_encode($stock_out_where) . '。更改物理库存为：' . $stock_out_info['stock_num'],
                ];
                log_write($log_info_data);

                //------------------计算出新的可用库存----------------------------
                $out_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_out_info);
                $orders = [
                    'object_number' => $stock_out_result['outbound_code'],
                    'object_type'   => 3,
                    'log_type'      => 12,
                ];
                $stock_out_order_status = $this->getEvent('ErpStock')->saveStockInfo($out_data, $order_info['actual_out_num'] * -1, $orders);
//                $stock_out_order_status = $this->changeSaveStock($stock_out_info, $out_data, $order_info, $order_info['actual_out_num']);

                # 作废审批流
                $workflow_status = $this->changeErpWorkFlow($order_info);

                $stock_out_order_status = ($stock_out_order_status && $stock_out_status && $workflow_status)  ? true : false ;
                $stock_in_order_status  = ($stock_in_order_status && $stock_in_status  )  ? true : false ;
                $log_info_data = [
                    'event'   => '调拨单红冲-结尾：',
                    'key'     => $order_info['order_number'],
                    'request' => '1：' . $stock_in_order_status.',2：'.$stock_in_status,
                    'response' => '2:' . $stock_out_order_status.',2：'.$stock_out_status .'3: '.$workflow_status,
                ];
                log_write($log_info_data);

                //判断调拨单对应入库单是否用于冲减网点期初负库存，若有，则返还原负库存
                $stock_in_data = $this->getModel('ErpStockIn')->where(['source_number'=>$order_info['order_number']])->find();
                $deduction_data = $this->getModel('ErpStockInDeduction')->where(['source_stock_in_number'=>$stock_in_data['storage_code'],'type'=>1])->select();
                if (!empty($deduction_data)) {
                    $deduction_num = 0;
                    $before_balance_num = $after_balance_num = $stock_in_data['balance_num'];
                    foreach ($deduction_data as $key=>$value) {
                        $deduction_num += $value['deduction_num'];
                        $stock_info = $this->getModel('ErpStock')->where(['id'=>$value['stock_id']])->find();

                        //修改网点期初冲减数量
                        $this->getModel('ErpStockSkidData')->where(['stock_id'=>$stock_info['id']])->setDec('deduction_num',$value['deduction_num']);
                        $after_balance_num = $after_balance_num + $value['deduction_num'];
                        //插入冲减记录
                        $deduction_data = [
                            'source_stock_in_number' => $stock_in_data['storage_code'],
                            'type' => 1,
                            'stock_id' => $stock_info['id'],
                            'before_balance_num' => $before_balance_num,
                            'after_balance_num' => $after_balance_num,
                            'deduction_num' => $value['deduction_num'] * -1,
                            'deduction_type' => 2,
                            'create_time' => currentTime(),
                        ];
                        $deduction_status = $this->getModel('ErpStockInDeduction')->add($deduction_data);
                        $deduction_status_all = $deduction_status && $deduction_status_all ? true : false;
                        $before_balance_num = $before_balance_num + $value['deduction_num'];
                    }
                    //冲减负库存结束后，修改原入库单的可用数量
                    $stock_in_data['balance_num'] = $after_balance_num;
                    $stock_in_data['balance_num_litre'] = $stock_in_data['balance_num'] / $stock_in_data['outbound_density'] * 1000;
                    $stock_in_data['deduction_num'] = $stock_in_data['deduction_num'] - $deduction_num;
                    $in_deduction_status = $this->getModel('ErpStockIn')->where(['storage_code'=>$stock_in_data['storage_code']])->save($stock_in_data);

                    $deduction_status_all = $in_deduction_status && $deduction_status_all ? true : false;
                } else {
                    $deduction_status_all = true;
                }
                break;
            default:
                break;
        }

        //服务商出调拨单回滚出库单还回对应入库单可用
        if ($order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3) {
            $stock_in_status = $this->returnToStockIn($order_info);
        } else {
            $stock_in_status = ['status' => true, 'message' => ''];
        }

        # 取消原订单
        $update_order = $this->updateAllocationOrder($order_info);
        if($stock_out_order_status && $stock_in_order_status && $update_order && $stock_in_status && $deduction_status_all){
            M()->commit();
            # 重新计算成本
            if (!empty($this->reverse_stock_in_info) && $this->reverse_stock_in_info['is_count'] == 1) {
                updateStockInCost($this->reverse_stock_in_info);
            }
            # 重新计算成本
            if (!empty($this->reverse_stock_out_info) && $this->reverse_stock_out_info['is_count'] == 1) {
                $this->reverse_stock_out_info['strage_code'] = $this->reverse_stock_out_info['outbound_code'];
                updateStockInCost($this->reverse_stock_out_info);
            }
            # 主动红冲调拨单 ， 如果为入库方为服务商，则需要Front取消收油单，极光推送
            if($this->reverse_allocation_type == 1 && in_array(intval($order_info['allocation_type']),[1,2])){
                $params = [
                    'allocation_order_number' => $order_info['order_number'],
                    'create_name'             =>  $this->getUserInfo('dealer_name'),
                    'handle_id'               => 9
                ];
                cancelOilReceipt($params);
            }
            $result = ['status' => 1, 'message' => '操作成功'];
        }else{
            M()->rollback();
            $result = ['status' =>0,'message' =>'操作失败，请稍后重试！'];
        }
        return $result;

    }

    /*
     * ------------------------------------------
     * 调拨单生成红冲出库单
     * Author：qianbin        Time：2018-02-06
     * ------------------------------------------
     */
    public function reverseStockOut($order_info = []){
        # 生成红冲出库单
        $stock_out_order_info = $this->getModel('ErpStockOut')->field('*')->where(['outbound_type' => 2, 'source_number' => $order_info['order_number'], 'source_object_id' => $order_info['id']])->find();
        $stock_out_data = [
            'outbound_type'     => 2,
            'outbound_code'     => erpCodeNumber(7)['order_number'],
            'outbound_status'   => 10,
            'source_number'     => $stock_out_order_info['source_number'],
            'source_object_id'  => $stock_out_order_info['source_object_id'],
            'goods_id'          => $stock_out_order_info['goods_id'],
            'our_company_id'    => $stock_out_order_info['our_company_id'],
            'outbound_num'      => $stock_out_order_info['outbound_num']* -1,
            'actual_outbound_num'=> $stock_out_order_info['actual_outbound_num']* -1,
            'outbound_density'  => $stock_out_order_info['outbound_density'],
            'create_time'       => currentTime(),
            'dealer_id'         => $stock_out_order_info['dealer_id'],
            'dealer_name'       => $stock_out_order_info['dealer_name'],
            'creater_id'        => empty($this->getUserInfo('id'))? '': $this->getUserInfo('id'),
            'creater_name'      => empty($this->getUserInfo('dealer_name'))?'':$this->getUserInfo('dealer_name'),
            'auditor_id'        => $this->getUserInfo('id'),
            'audit_time'        => currentTime(),
            'outbound_remark'   => trim($stock_out_order_info['outbound_remark']),
            'storehouse_id'     => $stock_out_order_info['storehouse_id'],
            'stock_type'        => $stock_out_order_info['stock_type'],
            'region'            => $stock_out_order_info['region'],
            'is_reverse'        => 1,
            'reverse_source'    => $stock_out_order_info['outbound_code'],
            'cost'              => $stock_out_order_info['cost'],
        ];
        $reversed_status                = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$stock_out_order_info['id']],['reversed_status'=>1]);
        $stock_out_status               = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);
        $stock_out_data['before_stock_num'] = $order_info['beforeCostNum'];
        $stock_out_data['stock_id']         = $order_info['stockId'] ? $order_info['stockId'] : 0;
        $stock_out_data['change_num']       = $stock_out_data['actual_outbound_num'];
        $stock_out_data['is_count']      = 1;
        $this->reverse_stock_out_info   = $stock_out_data;
        if($reversed_status && $stock_out_status){
            $result = ['status' => true,'outbound_code' => $stock_out_data['outbound_code']];
        }else{
            $result = ['status' => false,'outbound_code' => ''];
        }
        return $result;
    }
    /*
     * ------------------------------------------
     * 调拨单生成红冲入库单
     * Author：qianbin        Time：2018-02-06
     * ------------------------------------------
     */
    public function reverseStockInOrder($order_info = [] , $is = 1){
        # 生成红冲入库单
        $reversed_status = true;
        $stock_in_order_info = $this->getModel('ErpStockIn')->field('*')->where(['storage_type' => 2, 'source_number' => $order_info['order_number'], 'source_object_id' => $order_info['id']])->find();
        $stock_in_data = [
            'storage_type'      => 2,
            'storage_code'      => erpCodeNumber(8)['order_number'],
            'storage_status'    => 10,
            'source_number'     => $stock_in_order_info['source_number'],
            'source_object_id'  => $stock_in_order_info['source_object_id'],
            'goods_id'          => $stock_in_order_info['goods_id'],
            'our_company_id'    => $stock_in_order_info['our_company_id'],
            'storage_num'       =>  $stock_in_order_info['storage_num']* -1,
            'actual_storage_num' => $stock_in_order_info['actual_storage_num']* -1,
            'outbound_density'  => $stock_in_order_info['outbound_density'],
            'create_time'       => currentTime(),
            'dealer_id'         => $stock_in_order_info['dealer_id'],
            'dealer_name'       => $stock_in_order_info['dealer_name'],
            'creater_id'        => empty($this->getUserInfo('id')) ? '' : $this->getUserInfo('id'),
            'auditor_id'        => $this->getUserInfo('id'),
            'audit_time'        => currentTime(),
            'storage_remark'    => trim($stock_in_order_info['storage_remark']),
            'storehouse_id'     => $stock_in_order_info['storehouse_id'],
            'is_reverse'        => 1,
            'reverse_source'    => $stock_in_order_info['storage_code'],
            'price'             => $stock_in_order_info['price'],
            'actual_storage_num_litre' => $stock_in_order_info['actual_storage_num_litre'],
            'balance_num'       => $stock_in_order_info['balance_num'],
            'balance_num_litre' => $stock_in_order_info['balance_num_litre'],
            'stock_type'        => $stock_in_order_info['stock_type'],
            'region'            => $stock_in_order_info['region'],
        ];
        $stock_in_status = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
        $stock_in_data['before_stock_num']  = $order_info['beforeCostNum'];
        $stock_in_data['stock_id']          = $order_info['stockId'] ? $order_info['stockId'] : 0;
        $stock_in_data['change_num']        = $stock_in_data['actual_storage_num'];
        $stock_in_data['is_count']      = 1;
        $this->reverse_stock_in_info       = $stock_in_data;

        # 如果已入库类型为服务网点，则java那边进行标识
        if( $is == 2 ){
            $reversed_status = $this->getModel('ErpStockIn')->saveStockIn(['id' => $stock_in_order_info['id']],['reversed_status' => 1]);
        }
        if($reversed_status && $stock_in_status){
            $result = ['status' => true,'storage_code' => $stock_in_data['storage_code']];
        }else{
            $result = ['status' => false,'storage_code' => ''];
        }
        return $result;
    }



    /*
     * ------------------------------------------
     * 调拨单红冲影响库存
     * Author：qianbin        Time：2017-11-08
     * ------------------------------------------
     */
    private  function changeSaveStock($stock_info = [] , $data = [] , $order_info = [] ,$changeNum = 0){
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type'   => 5,
            'log_type'      => 12,
        ];
        $stock_status      = $this->getEvent('ErpStock')->saveStockInfo($data, $changeNum, $orders);
        return $stock_status;
    }

    /*
     * ------------------------------------------
     * 调拨单取消审批流
     * Author：qianbin        Time：2017-11-08
     * ------------------------------------------
     */
    private  function changeErpWorkFlow($order_info){
        $work_status = true;
        # 将调拨单审批流置为无效
        $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$order_info['id'], 'status'=>['neq', 2] ,'workflow_type' => 3])->order('id desc')->find();
        if ($workflow && $workflow['status'] != 2) {
            $workflow['status'] = 2;
            $work_status = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id'],'workflow_type' => 3])->save($workflow);
            $log_info_data = [
                'event'=> '调拨单红冲-作废审批流：',
                'key'=> $order_info['order_number'],
                'request'=> '作废审批流状态：'.$work_status,
            ];
            log_write($log_info_data);
        }
        return $work_status;
    }


    /**
     * 验证调拨单
     * @author qianbin
     * @time 2018-01-21
     */
    private function checkReverseAllocationOrder($id = 0)
    {
        $result = ['status' => 1 , 'message' => '数据正常'];
        if(intval($id) < 0 ){
            $result['status']  = 2 ;
            $result['message'] = '参数有误，请刷新后重试！';
            return $result;
        }
        # 验证订单信息 --------------------
        $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id' => intval($id)]);
        if(empty($order_info)){
            $result['status']  = 3 ;
            $result['message'] = '调拨单信息错误，请刷新后重试！';
            return $result;
        }
        //if(intval($order_info['status']) == 2 ){
        if(intval($order_info['status']) != 10 ){
            $result['status']  = 4 ;
            $result['message'] = '该笔调拨单非已确认状态，无法红冲！';
            return $result;
        }
        # 城市仓->服务商
        # 判断入库方服务网点是否存在
        if(intval($order_info['allocation_type']) == 1 && intval($order_info['in_storehouse']) == 0){
            $result['status']  = 5 ;
            $result['message'] = '该调拨单入库方信息错误，无法进行红冲！';
            return $result;
        }
        # 服务商->服务商
        # 判断出入库方服务网点是否正确
        if(intval($order_info['allocation_type']) == 2 && (intval($order_info['in_storehouse']) == 0 || intval($order_info['out_storehouse']) == 0)){
            $result['status']  = 6 ;
            $result['message'] = '该调拨单服务商信息错误，无法进行红冲！';
            return $result;
        }
        # 服务商->城市仓
        # 判断出库方服务网点是否存在
        if(intval($order_info['allocation_type']) == 3 && intval($order_info['out_storehouse']) == 0){
            $result['status']  = 7 ;
            $result['message'] = '该调拨单出库方未查询到服务网点，无法进行红冲！';
            return $result;
        }
        //判断入库单是否用于其他调拨单可用
        $stock_in_data = $this->getModel('ErpStockIn')->where(['source_number'=>$order_info['order_number']])->find();
        $deduction_where = [
            'source_stock_in_number' => $stock_in_data['storage_code'],
            'type' => 2,
        ];
        $stock_out_data = $this->getModel('ErpStockInDeduction')->where($deduction_where)->group('outbound_code')->having('sum(deduction_num)<>0')->select();
        if (!empty($stock_in_data) && !empty($stock_out_data)) {
            $outbound_codes = '';
            foreach ($stock_out_data as $key => $value) {
                $outbound_codes .= $value['outbound_code'].',';
            }
            $outbound_codes = trim($outbound_codes,',');
            $result['status']  = 8 ;
            $result['message'] = '该调拨单对应入库单被其他调拨出占用，请先处理对应出库单'.$outbound_codes;
            return $result;
        }
        //判断调拨单对应入库单是否被内部交易占用
        $is_inner_use = $this->getModel('ErpStockOut')
            ->alias('so')
            ->field('so.outbound_code')
            ->join('oil_erp_stock_in_deduction as sid on so.outbound_code = sid.outbound_code')
            ->join('oil_erp_stock_in as si on si.storage_code = sid.source_stock_in_number')
            ->where(['si.storage_code'=>$stock_in_data['storage_code'],'so.retail_inner_order_number'=>['neq','']])
            ->find();
        if (!empty($is_inner_use)) {
            $result['status']  = 9 ;
            $result['message'] = '该调拨单对应入库单被内部交易占用，无法红冲';
            return $result;
        }

        $result['order_info'] = $order_info;
        return $result;
    }


    /*
     * ------------------------------------------
     * 调拨单状态更改为已取消
     * Author：qianbin        Time：2017-05-21
     * ------------------------------------------
     */
    private function updateAllocationOrder($order_info = [])
    {
        $result = ['status' => 1 , 'message' => '操作成功'] ;
        if(intval($order_info['id']) <= 0 ){
            $result['status']  = 11 ;
            $result['message'] = '调拨单更新状态错误，红冲失败！';
            return $result;
        }
        # 更新订单
        $update_data = [
            'status'      => 2,
            'outbound_status' => 2,
            'storage_status'  => 2,
            'update_time' => currentTime(),
            'updater'     => $this->getUserInfo('id'),
        ];
        $allocation_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id' => intval($order_info['id'])] , $update_data);

        #  增加日志
        $log_data = [
            'allocation_id'     => $order_info['id'],
            'log_type'          => $this->reverse_allocation_type == 2 ? 21 : 20,  # 21 front 停止收油 -> 红冲调拨单
            'log_info'          => serialize($order_info),
            'allocation_order_number' => $order_info['order_number'],
            'create_time'       => currentTime(),
            'operator'          => empty($this->getUserInfo('dealer_name')) ? '':$this->getUserInfo('dealer_name'),
            'operator_id'       => empty($this->getUserInfo('id')) ? '' : $this->getUserInfo('id'),
        ];
        $log_status =  $this->getModel('ErpAllocationOrderLog')->addAllocationOrderLog($log_data);
        $log_info_data = [
            'event'=> '调拨单红冲-作废调拨单：',
            'key'=> $order_info['order_number'],
            'request'=> '作废调拨单状态：'.$allocation_status.'添加作废日志状态：'.$log_status,
        ];
        log_write($log_info_data);
        # 返回结果
        if($allocation_status && $log_status) {
            return true ;
        }else{
            return false;
        }
    }


    /**************************************
    @ Content 一笔销售退货单 对应多个 入库单
    @ Author  YF
    @ Time    2019-02-27
     ***************************************/
    public function oneReturnedOrderYesMoreStockInOrder( $stock_where = [] ){
        $stock_in_arr = $this->getModel('ErpStockIn')->where($stock_where)->select();
        if ( empty($stock_in_arr) ) {
            return ['status' => 20, 'message' => '未查询到对应的销退入库单，红冲失败！'];
        }
        foreach ($stock_in_arr as $key => $value) {
            /* -------------- 此入库单状态为 未审核状态！------------- */
            if ( $value['storage_status'] == 1 ) {
                return ['status' =>22, 'message' => $value['storage_code'].':次入库单为未审核状态，请先取消此入库单！'];
            }
            /* ---------------- 入库单未红冲 并且 入库单状态 不为 取消状态 ------------------ */
            // 是否红冲   1是 2否
            // 被红冲状态 1 已被戏冲 2未被红冲
            if ( $value['is_reverse'] == 2 && $value['reversed_status'] == 2 && $value['storage_status'] != 2 ) {
                return ['status' => 21,'message' => '入库单未红冲！'];
            }
        }
        return ['status' => 0, 'message' => '允许红冲！' ];

    }

    /**
     * 销退单红冲
     * @author qianbin
     * @time 2018-01-21
     */
    public function reverseReturnSaleOrder($id = 0 )
    {
        if (getCacheLock('ErpReverse/reverseReturnSaleOrder')) return ['status' => 99, 'message' => $this->running_msg];
        $work_status = true;
        $update_order = true;
        $stock_status = true;
        # 验证参数-----------------
        $result = $this->checkReverseReturnSaleOrder($id);
        if ($result['status'] != 1) {
            return ['status' => $result['status'], 'message' => $result['message']];
        }
        # 订单信息
        $order_info = $result['order_info'];
        $return_order_info = $result['return_order_info'];

        setCacheLock('ErpReverse/reverseReturnSaleOrder', 1);

        M()->startTrans();

        # 更新原订单信息
        $update_order = $this->updateReturnSaleOrder($id, $return_order_info, $order_info);

        if (!$update_order) {
            cancelCacheLock('ErpReverse/reverseReturnSaleOrder');
            M()->rollback();
            return ['status' => 12, 'message' => $update_order['message']];
        }

        # 将销退单审批流置为无效
        $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id' => $id, 'status' => ['neq', 2], 'workflow_type' => 4])->order('id desc')->find();
        if ($workflow && $workflow['status'] == 1) {
            $workflow['status'] = 2;
            $work_status = $this->getModel('ErpWorkflow')->where(['id' => $workflow['id']])->save($workflow);
            $log_info_data = [
                'event'     => '销售退货单红冲-作废审批流',
                'key'       => $return_order_info['order_number'],
                'request'   => '作废审批流状态：'.$work_status,
            ];
            log_write($log_info_data);
        }
        $stock_where = [
            'storage_type' => 3,
            'source_object_id' => intval($id),
        ];
        /* ---------------- 更新后 ---------------------*/
        $stock_in_result = $this->oneReturnedOrderYesMoreStockInOrder($stock_where);
        if ( $stock_in_result['status'] != 0 ) {
            cancelCacheLock('ErpReverse/reverseReturnSaleOrder');
            M()->rollback();
            return $stock_in_result;
        }
        # ------------------------------------判断是否存在入库------------------------------

        /* ---------------- 更新前 ---------------------*/
        // $stock_where = [
        //     'storage_type' => 3,
        //     'source_object_id' => intval($id),
        // ];
        // $stock_in_data = $this->getModel('ErpStockIn')->field(true)->where($stock_where)->find();
        // if (empty($stock_in_data)) {
        //     cancelCacheLock('ErpReverse/reverseReturnSaleOrder');
        //     M()->rollback();
        //     return ['status' => 20, 'message' => '未查询到对应的销退入库单，红冲失败！'];
        // }
        // if (intval($stock_in_data['storage_status']) == 2) {

        //     # 入库单已经为已取消，无需操作

        //     $log_info_data = [
        //         'event'     => '销售退货单红冲-作废入库单',
        //         'key'       => $return_order_info['order_number'],
        //         'request'   => '销售退货单的入库单已为已取消状态：入库单无需操作',
        //     ];
        //     log_write($log_info_data);

        // } else if (intval($stock_in_data['storage_status']) == 1){
        //     # 未审核
        //     # 变更为已取消
        //     $update_stock = [
        //         'storage_status'  => 2 ,
        //         'update_time'     => currentTime(),
        //         'dealer_id'       => $this->getUserInfo('id'),
        //         'dealer_name'     => $this->getUserInfo('dealer_name'),
        //     ];
        //     $stock_status = $this->getModel('ErpStockIn')->saveStockIn($stock_where,$update_stock);
        //     $log_info_data = [
        //         'event'     => '销售退货单红冲-作废入库单',
        //         'key'       => $return_order_info['order_number'],
        //         'request'   => '销售退货单的入库单由未审核更改为已取消状态：'.$stock_status,
        //     ];
        //     log_write($log_info_data);

        // }else if(intval($stock_in_data['storage_status']) == 10){
        //     # 已入库
        //     # 生成对应的入库单冲减
        //     $add_stock_data = [
        //         'storage_code'         => erpCodeNumber(12)['order_number'],
        //         'storage_type'         => 3,
        //         'storage_status'       => 10,
        //         'source_number'        => $stock_in_data['source_number'],
        //         'source_object_id'     => $stock_in_data['source_object_id'],
        //         'our_company_id'       => $stock_in_data['our_company_id'],
        //         'goods_id'             => $stock_in_data['goods_id'],
        //         'storage_num'          => plusConvert($stock_in_data['storage_num']),
        //         'actual_storage_num'   => plusConvert($stock_in_data['actual_storage_num']),
        //         'outbound_density'     => $stock_in_data['outbound_density'],
        //         'creater_id'           => $this->getUserInfo('id'),
        //         'create_time'          => currentTime(),
        //         'dealer_id'            => $this->getUserInfo('id'),
        //         'dealer_name'          => $this->getUserInfo('dealer_name'),
        //         'storehouse_id'        => $stock_in_data['storehouse_id'],
        //         'stock_type'           => $stock_in_data['stock_type'],
        //         'region'               => $stock_in_data['region'],
        //         'is_reverse'           => $stock_in_data['is_reverse'],
        //         'reverse_source'       => $stock_in_data['reverse_source'],
        //         'price'                => $stock_in_data['price'], //将获取的成本保存在红冲销退入库单价格上 edit xiaowen
        //     ];

        //     $add_stock_status = $this->getModel('ErpStockIn')->addStockIn($add_stock_data);

        //     $log_info_data = [
        //         'event'     => '销售退货单红冲-生成负数的入库单',
        //         'key'       => $return_order_info['order_number'],
        //         'request'   => '销售退货单的入库单生成一笔负数的入库单：'.json_encode($add_stock_data).'，生成入库单状态：'.$add_stock_status,
        //     ];
        //     log_write($log_info_data);

        //     //---------------确定该订单影响哪个库存，并查出该库存的信息-----

        //     $stock_where['goods_id']        = $add_stock_data['goods_id'];
        //     $stock_where['region']          = $add_stock_data['region'];
        //     $stock_where['object_id']       = $add_stock_data['storehouse_id'];
        //     $stock_where['stock_type']      = $add_stock_data['stock_type'];
        //     $stock_where['our_company_id']  = $add_stock_data['our_company_id'];

        //     $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //     //保存变动库存前的物理库存 edit xiaowen
        //     $beforeNum = $stock_info['stock_num'];
        //     $stockId = $stock_info['id'];
        //     $log_info_data = [
        //         'event'     => '销售退货单红冲-确定影响的库存',
        //         'key'       => $return_order_info['order_number'],
        //         'request'   => '销售退货单确定影响的库存是：'.json_encode($stock_where),
        //     ];
        //     log_write($log_info_data);
        //     //------------------组装库存表的字段值--------------------------
        //     //------------------红冲销退入库，需先验证库存是否满足 eidt xiaowen 2018-3-27----------------
        //     if($stock_info['stock_num'] < $stock_in_data['actual_storage_num']){
        //         M()->rollback();
        //         cancelCacheLock('ErpReverse/reverseReturnSaleOrder');
        //         return [ 'status' => 9, 'message' => '物理库存不足，无法红冲销退单'];
        //     }
        //     //--------------------------------------------------------------------------------------------
        //     $data = [
        //         'goods_id'   => $stock_where['goods_id'],
        //         'object_id'  => $stock_where['object_id'],
        //         'stock_type' => $stock_where['stock_type'],
        //         'region'     => $stock_where['region'],
        //         'stock_num'  => $stock_info['stock_num'] - $stock_in_data['actual_storage_num'],
        //         'sale_wait_num'  => $stock_info['sale_wait_num'] + ($stock_in_data['storage_num'] - $stock_in_data['actual_storage_num']),
        //     ];
        //     $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        //     $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的物理库存
        //     $log_info_data = [
        //         'event'     => '销售退货单红冲-确定影响的库存',
        //         'key'       => $return_order_info['order_number'],
        //         'request'   => '销售退货单新的物理库存是：'.$stock_info['stock_num'],
        //     ];
        //     log_write($log_info_data);

        //     //------------------计算出新的可用库存----------------------------
        //     $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //     //----------------------------------------------------------------
        //     $orders = [
        //         'object_number' => $add_stock_data['storage_code'],
        //         'object_type'   => 4,
        //         'log_type'      => 12,  // 红冲
        //     ];
        //     //----------------更新库存，并保存库存日志-------------------------
        //     $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $add_stock_data['actual_storage_num'], $orders);

        //     $log_info_data = [
        //         'event'     => '销售退货单红冲-确定影响的库存',
        //         'key'       => $return_order_info['order_number'],
        //         'request'   => '销售退货单导致的库存更改状态：'.$stock_status,
        //     ];
        //     log_write($log_info_data);
        //     //===================================================================================================
        //     if($add_stock_status && $stock_status){
        //         $stock_status = true;
        //     }else{
        //         $stock_status = false;
        //     }

        // }
        if($work_status && $update_order && $stock_status){
            M()->commit();
            $result = [ 'status' => 1, 'message' => '操作成功'];
        } else {
            M()->rollback();
            $result = [ 'status' => 0, 'message' => '操作失败'];
        }
        cancelCacheLock('ErpReverse/reverseReturnSaleOrder');
        return $result;

    }

    /**
     * 验证销退单
     * @author qianbin
     * @time 2018-01-21
     */
    private function checkReverseReturnSaleOrder($id = 0){

        $result = ['status' => 1 , 'message' => '数据正常'];
        if(intval($id) < 0 ){
            $result['status']  = 2 ;
            $result['message'] = '参数有误，请刷新后重试！';
            return $result;
        }
        # 验证订单信息 --------------------
        $return_order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => intval($id)]);
        if(empty($return_order_info)){
            $result['status']  = 3 ;
            $result['message'] = '销售退货单信息错误，请刷新后重试！';
            return $result;
        }
        if(intval($return_order_info['order_status']) != 10 ){
            $result['status']  = 4 ;
            $result['message'] = '销售退货单非已确认状态，无法进行红冲！';
            return $result;
        }
        if(intval($return_order_info['return_amount_status']) == 10 ){
            $result['status']  = 4 ;
            $result['message'] = '销售退货单已付款，请先进行红冲该付款信息！';
            return $result;
        }
        # 验证原销售单是否开票
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $return_order_info['source_order_id']]);
        if(intval($order_info['order_status']) == 2 ){
            $result['status']  = 5 ;
            $result['message'] = '销售退货单对应的销售单已为取消状态，无法进行红冲！';
            return $result;
        }
        if(intval($order_info['invoice_status']) != 1 ){
            $result['status']  = 6 ;
            $result['message'] = '销售退货单对应的销售单已开票，请先红冲销售发票！';
            return $result;
        }
        if(in_array($return_order_info['order_status'],[1,3,4])){
            $result['status']  = 7 ;
            $result['message'] = '销售退货单并非已确认状态，可通过取消按钮进行红冲！';
            return $result;
        }
        $result['order_info'] = $order_info;
        $result['return_order_info'] = $return_order_info;
        return $result;
    }


    /*
     * ------------------------------------------
     *  1. 销售退货单状态更改为已取消
     *  2. 销售单退货标识去掉
     * Author：qianbin        Time：2017-05-21
     * ------------------------------------------
     */
    private function updateReturnSaleOrder($id = 0 , $return_order_info = [] , $order_info =[])
    {
//        if(!$update_order){
//            return [];
//        }
        $result = ['status' => 1 , 'message' => '操作成功'] ;
        if(intval($id) <= 0 ){
            $result['status']  = 11 ;
            $result['message'] = '销售退货单更新状态错误，红冲失败！';
            return $result;
        }

        $return_order_status = true ;
        $all_order_log       = true ;
        $order_status        = true ;
        $add_order_log_status= true ;
        # 更新订单为已取消
        $update_data = [
            'order_status'  => 2,
            'update_time'   => currentTime(),
            'updater_id'    => $this->getUserInfo('id'),
        ];
        $return_order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)] , $update_data);

        #  增加日志
        $add_return_log = [
            'return_order_id'       => $id ,
            'return_order_number'   => $return_order_info['order_number'],
            'return_order_type'     => 1,
            'log_type'              => 20,
            'log_info'              => serialize($return_order_info),
            'create_time'           => currentTime(),
            'operator'              => $this->getUserInfo('id'),
            'operator_id'           => $this->getUserInfo('dealer_name')
        ];
        $all_order_log = $this->getModel('ErpReturnedOrderLog')->add($add_return_log);

        # 去掉原订单标识
        $update_order_data = [
            'is_returned'           => 2 ,
            // 'returned_goods_num'    => 0 ,
            'updater'               => $this->getUserInfo('id'),
            'update_time'           => currentTime(),
        ];
        $order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($order_info['id'])],$update_order_data);
        $add_order_log = [
            'sale_order_id'     => $order_info['id'],
            'sale_order_number' => $order_info['order_number'],
            'log_info'          => serialize($order_info),
            'log_type'          => 21,
            'create_time'       => currentTime(),
            'operator'          => $this->getUserInfo('id'),
            'operator_id'       => $this->getUserInfo('dealer_name')
        ];
        $add_order_log_status = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($add_order_log);

        $log_info_data = [
            'event'     => '销售退货单红冲-作废销售退货单，取消销售单退货标识',
            'key'       => $return_order_info['order_number'],
            'request'   => '作废销退单状态：'.$return_order_status.'添加销退单作废日志状态：'.$all_order_log.'，更新销售单退货标识状态：'.$order_status.'，添加销售单日志状态：'.$add_order_log_status,
        ];
        log_write($log_info_data);

        # 返回结果
        if($return_order_status && $all_order_log && $order_status && $add_order_log_status ) {
            return true ;
        }else{
            return false;
        }

    }



    /** --------------------------------------------------冠宇------------------------------------------------------- */


    /**
     * 采购单回滚
     * @author guanyu
     * @time 2018-01-18
     */
    public function reversePurchaseOrder($id)
    {
        $check_result = $this->checkReversePurchaseOrder($id);
        if ($check_result['status'] != 1) {
            return $check_result;
        }

        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);

        if (getCacheLock('ErpReverse/reversePurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reversePurchaseOrder', 1);

        M()->startTrans();

        //修改采购单状态
        $purchase_order_data = [
            'order_status' => 2,
            'is_void' => 1,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime()
        ];
        $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $purchase_order_info['id']], $purchase_order_data);

        //如果有未审核的付款申请，则将对应申请驳回
        $where = [
            'purchase_id' => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'status' => 1
        ];
        $purchase_payment_info = $this->getModel('ErpPurchasePayment')->where($where)->find();
        if ($purchase_payment_info) {
            $purchase_payment_data = [
                'status' => 2,
                'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime()
            ];
            $payment_status = $this->getModel('ErpPurchasePayment')->savePurchasePayment($where, $purchase_payment_data);
        } else {
            $payment_status = true;
        }

        //查看是否有对应的入库单
        $stock_in_info = $this->getModel('ErpStockIn')->where(['source_number'=>$purchase_order_info['order_number'],'source_object_id'=>$purchase_order_info['id'],'storage_status'=>1])->find();
        if ($stock_in_info) {
            //将对应的入库单取消
            $stock_in_data = [
                'storage_status' => 2,
                'update_time' => currentTime(),
            ];
            $stock_in_where = [
                'source_number'=>$purchase_order_info['order_number'],
                'source_object_id'=>$purchase_order_info['id'],
                'storage_type'=>1,
                'storage_status'=>1,
            ];
            $stock_in_status = $this->getModel('ErpStockIn')->saveStockIn($stock_in_where, $stock_in_data);
        } else {
            $stock_in_status = true;
        }

        //如果有未审核的发票申请，则将对应发票驳回
        $where = [
            'purchase_id' => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'status' => 1
        ];
        $purchase_invoice_info = $this->getModel('ErpPurchaseInvoice')->where($where)->find();
        if ($purchase_invoice_info) {
            $purchase_invoice_data = [
                'status' => 2,
                'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime()
            ];
            $invoice_status = $this->getModel('ErpPurchaseInvoice')->savePurchaseInvoice($where, $purchase_invoice_data);
        } else {
            $invoice_status = true;
        }

        //将采购单审批流置为无效
        $where = [
            'workflow_order_id' => $purchase_order_info['id'],
            'workflow_type' => 2,
            'status' => ['neq', 2]
        ];
        $purchase_workflow = $this->getModel('ErpWorkflow')->where($where)->order('id desc')->find();
        if ($purchase_workflow && $purchase_workflow['status'] == 1) {
            $purchase_workflow['status'] = 2;
            $purchase_work_status = $this->getModel('ErpWorkflow')->where(['id'=>$purchase_workflow['id']])->save($purchase_workflow);
        } else {
            $purchase_work_status = true;
        }

        //保存操作到采购单日志
        $purchase_log_data = [
            'purchase_id' => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'log_type' => 20,
            'log_info' => serialize($purchase_order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $purchase_log_status = $this->getModel('ErpPurchaseLog')->add($purchase_log_data);

        //账期付款方式回滚库存
        if ($purchase_order_info['pay_type'] == 3) {
            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $purchase_order_info['goods_id'],
                'object_id' => $purchase_order_info['storehouse_id'],
                'stock_type' => $purchase_order_info['type'] == 2 ? 2 : getAllocationStockType($purchase_order_info['storehouse_id']),
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //------------------组装库存表的字段值--------------------------
            $data = [
                'goods_id' => $purchase_order_info['goods_id'],
                'object_id' => $purchase_order_info['storehouse_id'],
                'stock_type' => $purchase_order_info['type'] == 2 ? 2 : getAllocationStockType($purchase_order_info['storehouse_id']),
                'region' => $purchase_order_info['region'],
                'transportation_num' => $stock_info['transportation_num'] - $purchase_order_info['goods_num'],
            ];
            $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的在途库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $purchase_order_info['order_number'],
                'object_type' => 2,
                'log_type' => 12,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $purchase_order_info['goods_num'], $orders);
        } else {
            $stock_status = true;
        }

        //如果是代采采购单，则回滚采购需求
        if ($purchase_order_info['type'] == 2) {
            $where = [
                'id' => $purchase_order_info['from_sale_order_id'],
                'order_number' => $purchase_order_info['from_sale_order_number']
            ];
            $data = [
                'acting_purchase_num' => 0
            ];
            $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder($where,$data);
        } else {
            $sale_order_status = true;
        }

        if ($purchase_order_status && $payment_status  && $stock_in_status && $invoice_status && $purchase_work_status
            && $purchase_log_status && $stock_status && $sale_order_status) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpReverse/reversePurchaseOrder');
        return $result;
    }

    /**
     * 采购单回滚数据验证
     * @author guanyu
     * @time 2018-01-18
     */
    public function checkReversePurchaseOrder($id)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];

        $id = intval($id);
        if (!$id) {
            $result['status'] = 2;
            $result['message'] = '参数有误，无法获取采购单ID';
            return $result;
        }

        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
        if (empty($purchase_order_info)) {
            $result['status'] = 3;
            $result['message'] = '该采购单不存在，请稍后操作';
            return $result;
        }
        /*************** YF Time 2018-12-11 ****************/
        if ( $purchase_order_info['business_type'] == 6 ) {
            return ['status' => 3,'message' => '此采购单属于内部交易单 ，不允许作废！'];
        }
        /******************** END *************************/

        if ($purchase_order_info['order_status'] != 10) {
            $result['status'] = 4;
            $result['message'] = '未确认状态下请自行手动取消';
            return $result;
        }

        if ($purchase_order_info['is_void'] == 1) {
            $result['status'] = 5;
            $result['message'] = '该采购单已作废，不能重复作废';
            return $result;
        }

        if ($purchase_order_info['pay_status'] != 1) {
            $result['status'] = 6;
            $result['message'] = '该采购单已有付款记录，不能作废';
            return $result;
        }

        $purchase_payment_info = $this->getModel('ErpPurchasePayment')->where(['purchase_id'=>$purchase_order_info['id'],'purchase_order_number'=>$purchase_order_info['order_number'],'status'=>3])->find();
        if (!empty($purchase_payment_info)) {
            $result['status'] = 7;
            $result['message'] = '该采购单还有已同意的付款申请，请驳回后再操作';
            return $result;
        }

        if ($purchase_order_info['storage_quantity'] != 0) {
            $result['status'] = 8;
            $result['message'] = '该采购单已入库，不能作废';
            return $result;
        }

        if ($purchase_order_info['invoice_status'] != 1) {
            $result['status'] = 9;
            $result['message'] = '该采购单已开发票，不能作废';
            return $result;
        }
        /* ------------- 判断是否有损耗 ----------------- */
        $stock_in_where = [
            'source_number'     => ['eq',$purchase_order_info['order_number']],
            'source_object_id'  => ['eq',$purchase_order_info['id']],
            'storage_type'      => ['eq',1],
            'storage_status'    => ['neq',2],
        ];
        $stock_arr = $this->getModel('ErpStockIn')->where($stock_in_where)->getField('id,storage_code');
        if ( !empty($stock_arr) ) {
            $loss_where = [
                'source_number' => ['in',$stock_arr],
                'order_status'  => ['neq',2],
            ];
            $loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->field('id')->find();
            if ( isset($loss_arr['id']) ) {
                $result['status'] = 10;
                $result['message'] = '请先取消入库单对应的损耗单！';
                return $result;
            }
        }

        return $result;
    }

    /**
     * 付款红冲
     * @author guanyu
     * @time 2018-01-19
     */
    public function reversePayment($id)
    {
        //获取应付信息
        $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $id]);

        if ($payment_info['source_order_type'] == 1) {
            //采购单付款红冲
            $result = $this->reversePurchasePayment($payment_info);
        } elseif ($payment_info['source_order_type'] == 2) {
            //采退单付款红冲
            $result = $this->reversePurchaseReturnPayments($payment_info);
        }

        return $result;
    }

    /**
     * 采购单付款红冲
     * @author guanyu
     * @time 2018-01-19
     */
    public function reversePurchasePayment($payment_info)
    {
        //对应采购单信息
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $payment_info['purchase_id'],'order_number' => $payment_info['purchase_order_number']]);

        //验证单据信息
        $check_result = $this->checkReversePurchasePayment($payment_info,$purchase_order_info);

        if ($check_result['status'] != 1) {
            return $check_result;
        }

        if (getCacheLock('ErpReverse/reversePurchasePayment')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reversePurchasePayment', 1);

        M()->startTrans();

        //红冲前单据影响在途数量
        $before_purchase_order_info = $this->calculateTransportationByPurchase($purchase_order_info);

        $purchase_order_info['payed_money'] -= $payment_info['pay_money'];

        //红冲后单据影响在途数量
        $after_purchase_order_info = $this->calculateTransportationByPurchase($purchase_order_info);

        //影响对应库存在途数量
        $change_num = $after_purchase_order_info['effect_transportation'] - $before_purchase_order_info['effect_transportation'];
        if ($change_num != 0) {
            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $after_purchase_order_info['goods_id'],
                'object_id' => $after_purchase_order_info['storehouse_id'],
                'stock_type' => $after_purchase_order_info['type'] == 2 ? 2 : getAllocationStockType($after_purchase_order_info['storehouse_id']),
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //------------------组装库存表的字段值--------------------------
            $data = [
                'goods_id' => $after_purchase_order_info['goods_id'],
                'object_id' => $after_purchase_order_info['storehouse_id'],
                'stock_type' => $after_purchase_order_info['type'] == 2 ? 2 : getAllocationStockType($after_purchase_order_info['storehouse_id']),
                'region' => $after_purchase_order_info['region'],
                'transportation_num' => $stock_info['transportation_num'] + $change_num,
            ];
            $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的在途库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $after_purchase_order_info['order_number'],
                'object_type' => 2,
                'log_type' => 13,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $change_num, $orders);
        } else {
            $stock_status = true;
        }

        //修改原付款记录的红冲状态
        $old_erp_payment_data = [
            'reversed_status' => 1
        ];
        $old_status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $payment_info['id']],$old_erp_payment_data);

        //录入一条负数的付款记录冲平
        $erp_payment_data = [
            'pay_money'         => $payment_info['pay_money'] * -1,
            'balance_deduction' => $payment_info['balance_deduction'] * -1,
            'purchase_id' => $payment_info['purchase_id'],
            'purchase_order_number' => $payment_info['purchase_order_number'],
            'create_time' => currentTime(),
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'apply_pay_time' => date('Y-m-d', time()) . ' 23:59:59',
            'pay_time' => currentTime(),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'bank_id'          => $payment_info['bank_id'],
            'bank_simple_name' => $payment_info['bank_simple_name'],
            'bank_info'        => $payment_info['bank_info'],
            /** 银行账套信息改版 - end - 2018.08.07 */
            'our_company_id' => session('erp_company_id'),
            'auditor' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'status' => 10,
            'sale_company_id' => $payment_info['sale_company_id'],
            'dealer_id' => $payment_info['dealer_id'],
            'from_purchase_order_number' => $payment_info['from_purchase_order_number'],
            'is_reverse' => 1,
            'reverse_source' => $payment_info['id'],
        ];
        $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);

        //若有预付账户余额扣减，则回滚对应的账户
        if ($payment_info['balance_deduction'] != 0) {
            $accout_status = $this->addPrepayOrderRecord($after_purchase_order_info,$payment_info);
        } else {
            $accout_status = true;
        }

        //查看是否有对应的入库单
        $stock_in_info = $this->getModel('ErpStockIn')->where(['source_number'=>$after_purchase_order_info['order_number'],'source_object_id'=>$after_purchase_order_info['id'],'storage_status'=>1])->find();
        if ($stock_in_info) {
            //将对应的入库单取消
            $stock_in_data = [
                'storage_status' => 2,
                'update_time' => currentTime(),
            ];
            $stock_in_where = [
                'source_number'=>$after_purchase_order_info['order_number'],
                'source_object_id'=>$after_purchase_order_info['id'],
                'storage_type'=>1,
                'storage_status'=>1,
            ];
            $stock_in_status = $this->getModel('ErpStockIn')->saveStockIn($stock_in_where, $stock_in_data);
        } else {
            $stock_in_status = true;
        }

        //更新订单信息
        $update_order_data = [
            'payed_money' => $after_purchase_order_info['payed_money'],
            'pay_status' => $after_purchase_order_info['pay_status'],
            'no_payed_money' => $after_purchase_order_info['order_amount'] - $after_purchase_order_info['payed_money'],
            'total_purchase_wait_num' => $after_purchase_order_info['pay_type'] == 5 ? $after_purchase_order_info['total_purchase_wait_num'] + $change_num : $after_purchase_order_info['total_purchase_wait_num'],
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $status_purchase_order = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $payment_info['purchase_id']], $update_order_data);

        //更新日志信息
        $log_data = [
            'purchase_id' => $after_purchase_order_info['id'],
            'purchase_order_number' => $after_purchase_order_info['order_number'],
            'log_type' => 21,
            'log_info' => serialize($after_purchase_order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

        if ($stock_status && $old_status_payment && $status_payment && $accout_status && $stock_in_status && $status_purchase_order && $status_log) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }
        cancelCacheLock('ErpReverse/reversePurchasePayment');
        return $result;

    }

    /**
     * 采购付款红冲数据验证
     * @author guanyu
     * @time 2018-01-19
     */
    public function checkReversePurchasePayment($payment_info,$purchase_order_info)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if ($purchase_order_info['retail_inner_order_number'] != '') {
            $result['status'] = 2;
            $result['message'] = '对应采购单由内部交易单生成，不允许红冲付款';
            return $result;
        }
        if ($payment_info['reversed_status'] == 1) {
            $result['status'] = 2;
            $result['message'] = '该笔付款申请已红冲，请检查';
            return $result;
        }

        if ($payment_info['status'] != 10) {
            $result['status'] = 3;
            $result['message'] = '该笔付款申请未确认，请检查';
            return $result;
        }

        if ($payment_info['is_reverse'] == 1) {
            $result['status'] = 4;
            $result['message'] = '该笔付款为红冲付款，禁止操作';
            return $result;
        }

        if (empty($purchase_order_info)) {
            $result['status'] = 5;
            $result['message'] = '对应采购单不存在，请检查';
            return $result;
        }

        if ($purchase_order_info['invoice_status'] != 1) {
            $result['status'] = 6;
            $result['message'] = '对应采购单已开票，请红冲发票后再进行操作';
            return $result;
        }

        if ($purchase_order_info['storage_quantity'] != 0) {
            $result['status'] = 7;
            $result['message'] = '对应采购单已入库，请红冲入库单后再进行操作';
            return $result;
        }

        return $result;
    }

    /**
     * 根据采购单信息计算在途数量
     * @author guanyu
     * @time 2018-01-21
     */
    public function calculateTransportationByPurchase($purchase_order_info)
    {
        $prepay = setNum(round(getNum($purchase_order_info['order_amount']) * getNum($purchase_order_info['prepay_ratio']) / 100, 2));

        if ($purchase_order_info['payed_money'] <= 0) {

            $purchase_order_info['pay_status'] = 1;
            $purchase_order_info['effect_transportation'] = 0;

        } elseif ($purchase_order_info['payed_money'] < $prepay && in_array($purchase_order_info['pay_type'],[2,5])) {

            $purchase_order_info['pay_status'] = 2;
            $purchase_order_info['effect_transportation'] = 0;

        } elseif ($purchase_order_info['payed_money'] == $prepay && in_array($purchase_order_info['pay_type'],[2])) {

            $purchase_order_info['pay_status'] = 3;
            $purchase_order_info['effect_transportation'] = $purchase_order_info['goods_num'];

        }elseif ($purchase_order_info['payed_money'] == $prepay && in_array($purchase_order_info['pay_type'],[5])) { //定金锁价刚好达到预付 不需要影响库存 edit xiaowen 2017-6-8

            $purchase_order_info['pay_status'] = 3;
            $purchase_order_info['effect_transportation'] = 0;

        } elseif ($purchase_order_info['payed_money'] > $prepay && $purchase_order_info['payed_money'] < $purchase_order_info['order_amount'] && $purchase_order_info['pay_type'] == 2) {

            $purchase_order_info['pay_status'] = 4;
            $purchase_order_info['effect_transportation'] = $purchase_order_info['goods_num'];

        } elseif ($purchase_order_info['payed_money'] > $prepay && $purchase_order_info['payed_money'] < $purchase_order_info['order_amount'] && $purchase_order_info['pay_type'] == 5) {

            $purchase_order_info['pay_status'] = 4;
            $purchase_order_info['effect_transportation'] = floor(setNum(($purchase_order_info['payed_money'] - $prepay) / $purchase_order_info['price']));

        } elseif ($purchase_order_info['payed_money'] > 0 && $purchase_order_info['payed_money'] < $purchase_order_info['order_amount']) {

            $purchase_order_info['pay_status'] = 4;
            $purchase_order_info['effect_transportation'] = 0;

        } elseif ($purchase_order_info['payed_money'] == $purchase_order_info['order_amount']) {

            $purchase_order_info['pay_status'] = 10;
            $purchase_order_info['effect_transportation'] = $purchase_order_info['goods_num'];

        }

        if ($purchase_order_info['pay_type'] == 3) {

            $purchase_order_info['effect_transportation'] = 0;

        }

        return $purchase_order_info;
    }

    /********************************************
    @ Content 采退单单付款红冲
    @ Author YF
    @ Time 2018-12-25
     *********************************************/
    public function reversePurchaseReturnPayments( $payment_info )
    {
        // 对应采购单信息
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['order_number' => $payment_info['from_purchase_order_number']]);
        //对应采退单信息
        $purchase_return_order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['source_order_id' => $purchase_order_info['id'],'source_order_number' => $purchase_order_info['order_number'],'order_status' => 10]);
        if ( empty($purchase_return_order_info) ) {
            return ['status' => 4,'message' => '未查询到所对应的应退单！'];
        }
        // 验证数据
        $check_result = $this->checkReversePurchaseReturnPayment($payment_info,$purchase_order_info);
        if ( $check_result['status'] != 1 ) {
            return $check_result;
        }
        if (getCacheLock('ErpReverse/reversePurchaseReturnPayment')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reversePurchaseReturnPayment', 1);

        // 开启事物
        M()->startTrans();

        //修改原付款记录的红冲状态
        $old_erp_payment_data = [
            'reversed_status' => 1
        ];
        $old_status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $payment_info['id']],$old_erp_payment_data);

        //录入一条负数的付款记录冲平
        $erp_payment_data = [
            'pay_money'             => $payment_info['pay_money'] * -1,
            'purchase_id'           => $payment_info['purchase_id'],
            'purchase_order_number' => $payment_info['purchase_order_number'],
            'source_order_type'     => $payment_info['source_order_type'],
            'create_time'           => currentTime(),
            'creator'               => $this->getUserInfo('dealer_name'),
            'creator_id'            => $this->getUserInfo('id'),
            'apply_pay_time'        => date('Y-m-d', time()) . ' 23:59:59',
            'pay_time'              => currentTime(),
            'our_company_id'        => session('erp_company_id'),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'bank_id'               => $payment_info['bank_id'],
            'bank_simple_name'      => $payment_info['bank_simple_name'],
            'bank_info'             => $payment_info['bank_info'],
            /** 银行账套信息改版 - end - 2018.08.07 */
            'auditor'               => $this->getUserInfo('dealer_name'),
            'auditor_id'            => $this->getUserInfo('id'),
            'audit_time'            => currentTime(),
            'status'                => 10,
            'sale_company_id'       => $payment_info['sale_company_id'],
            'dealer_id'             => $payment_info['dealer_id'],
            'from_purchase_order_number' => $payment_info['from_purchase_order_number'],
            'is_reverse'            => 1,
            'reverse_source'        => $payment_info['id'],
        ];
        $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);

        // 更改采购退货单的 状态
        $purchase_return_order_data = [
            // 'order_status'          => 10,
            'return_amount_status'  => 1,
            'return_payed_amount'   => 0,
            'update_time'           => currentTime(),
            'updater_id'            => $this->getUserInfo('id'),
        ];
        $purchase_return_order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id'=>['eq',$purchase_return_order_info['id']]], $purchase_return_order_data);
        if ( !$purchase_return_order_status ) {
            M()->rollback();
            return ['status' => 20, 'message' => '采退单付款状态 修改失败！'];
        }

        // 如果原采退单确认付款时，选择了转预付。则需要：
        // 1.预付单申请变为无效
        // 2.用户余额减少
        $account_status = true ;
        if(intval($payment_info['is_prepay_money']) == 1){
            # 查询用户余额
            # 如果余额不足时，不允许红冲
            $err_message = '余额扣减有误，请刷新后重试！';
            $where = [
                'account_type'   => 2,
                'our_company_id' => $erp_payment_data['our_company_id'],
                'company_id'     => $erp_payment_data['sale_company_id'],
            ];
            $account_balance = $this->getModel('ErpAccount')->findAccount($where)['account_balance'];
            $account_balance = $account_balance <= 0 ? 0 : $account_balance;
            $account_balance = getNum($account_balance + $erp_payment_data['pay_money']) ;
            if($account_balance < 0){
                $account_status = false;
                $err_message = '该用户余额不足，请刷新后查看！';
            }else{
                $recharge_order = [
                    'order_status'    => 2,
                    'finance_status'  => 1,
                    'updater'         => $this->getUserInfo('id'),
                    'update_time'     => currentTime(),
                ];
                $order_status   = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['from_order_number' =>$purchase_return_order_info['order_number']], $recharge_order);
                //修改账户
                $payment_info['order_number']   = $payment_info['purchase_order_number'];
                $payment_info['company_id']     = $payment_info['sale_company_id'];
                $payment_info['object_type']    = 2;
                $account_status = $this->getEvent('ErpAccount')->changeAccount($payment_info, PREPAY_TYPE, $erp_payment_data['pay_money']);
                $account_status = $account_status && $order_status ? true : false;
            }
        }

        if ( $status_payment && $old_status_payment && $purchase_return_order_status && $account_status) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }

        cancelCacheLock('ErpReverse/reversePurchaseReturnPayment');
        return $result;
    }

    /**
     * 采退单付款红冲
     * @author guanyu   温馨提示 ！！！废弃 ！！！ <---------
     * @time 2018-01-21
     */
    public function reversePurchaseReturnPayment($payment_info)
    {
        //对应采购单信息
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['order_number' => $payment_info['from_purchase_order_number']]);

        //对应采退单信息
        $purchase_return_order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['source_order_id' => $purchase_order_info['id'],'source_order_number' => $purchase_order_info['order_number'],'order_status' => 10]);

        //对应出库单信息
        $stock_out_info = $this->getModel('ErpStockOut')->getOneStockOut(['source_number' => $purchase_return_order_info['order_number'],'source_object_id' => $purchase_return_order_info['id']]);

        //验证数据
        $check_result = $this->checkReversePurchaseReturnPayment($payment_info,$purchase_order_info);

        if ($check_result['status'] != 1) {
            return $check_result;
        }

        if (getCacheLock('ErpReverse/reversePurchaseReturnPayment')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reversePurchaseReturnPayment', 1);

        M()->startTrans();

        //修改原付款记录的红冲状态
        $old_erp_payment_data = [
            'reversed_status' => 1
        ];
        $old_status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $payment_info['id']],$old_erp_payment_data);

        //录入一条负数的付款记录冲平
        $erp_payment_data = [
            'pay_money' => $payment_info['pay_money'] * -1,
            'purchase_id' => $payment_info['purchase_id'],
            'purchase_order_number' => $payment_info['purchase_order_number'],
            'source_order_type' => $payment_info['source_order_type'],
            'create_time' => currentTime(),
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'apply_pay_time' => date('Y-m-d', time()) . ' 23:59:59',
            'pay_time' => currentTime(),
            'our_company_id' => session('erp_company_id'),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'bank_id'          => $payment_info['bank_id'],
            'bank_simple_name' => $payment_info['bank_simple_name'],
            'bank_info'        => $payment_info['bank_info'],
            /** 银行账套信息改版 - end - 2018.08.07 */
            'auditor' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'status' => 10,
            'sale_company_id' => $payment_info['sale_company_id'],
            'dealer_id' => $payment_info['dealer_id'],
            'from_purchase_order_number' => $payment_info['from_purchase_order_number'],
            'is_reverse' => 1,
            'reverse_source' => $payment_info['id'],
        ];
        $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);

        //红冲出库
        $stock_out_data = [
            'outbound_code' => erpCodeNumber(7)['order_number'],
            'outbound_type' => $stock_out_info['outbound_type'],
            'outbound_status' => 10,
            'outbound_remark' => '',
            'source_number' => $stock_out_info['source_number'],
            'source_object_id' => $stock_out_info['source_object_id'],
            'our_company_id' => $stock_out_info['our_company_id'],
            'goods_id' => $stock_out_info['goods_id'],
            'depot_id' => $stock_out_info['depot_id'],
            'outbound_num' => $stock_out_info['outbound_num'] * -1,
            'actual_outbound_num' => $stock_out_info['actual_outbound_num'] * -1,
            'outbound_density' => $stock_out_info['outbound_density'],
            'create_time' => currentTime(),
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'creater_id' => $this->getUserInfo('id'),
            'creater_name' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'storehouse_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['stock_type'],
            'region' => $stock_out_info['region'],
            'is_reverse' => 1,
            'reverse_source' => $stock_out_info['outbound_code'],
        ];

        //通过java接口获取出库单成本 edit xiaowen 2018-2-4
        $stock_out_cost = getStockOutCost($stock_out_data);

        $stock_out_data['cost'] = $stock_out_info['cost'] ? $stock_out_info['cost'] : 0;
        $stock_out_data['cost_log_id'] = $stock_out_cost['logId'] ? $stock_out_cost['logId'] : 0;

        $status_stockout = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);

        //更新采退单数据
        $purchase_return_order_data = [
            'order_status' => 2,
            'return_amount_status' => 1,
            'return_payed_amount' => 0,
            'update_time' => currentTime(),
            'updater_id' => $this->getUserInfo('id'),
        ];
        $purchase_return_order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => $purchase_return_order_info['id']], $purchase_return_order_data);

        //更新采购单数据
        $purchase_order_data = [
            'returned_goods_num' => 0,
            'is_returned' => 2,
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $purchase_order_info['id']], $purchase_order_data);

        //影响库存
        $stock_where = [
            'goods_id' => $stock_out_info['goods_id'],
            'object_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['stock_type'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //保存变动物理库存之前的物理数量 eidt xiaowen---------------
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $stock_out_info['goods_id'],
            'object_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['stock_type'],
            'region' => $stock_out_info['region'],
            'stock_num' => $stock_info['stock_num'] + $stock_out_info['actual_outbound_num'],
            'transportation_num' => $stock_info['transportation_num'] + ($stock_out_info['outbound_num'] - $stock_out_info['actual_outbound_num']),
        ];
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的在途库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info );
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_out_data['outbound_code'],
            'object_type' => 3,
            'log_type' => 12,
        ];
        //----------------更新库存，并保存库存日志-------------------------

        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_out_data['actual_outbound_num'], $orders);

        // 如果原采退单确认付款时，选择了转预付。则需要：
        // 1.预付单申请变为无效
        // 2.用户余额减少
        $account_status = true ;
        if(intval($payment_info['is_prepay_money']) == 1){
            # 查询用户余额
            # 如果余额不足时，不允许红冲
            $err_message = '余额扣减有误，请刷新后重试！';
            $where = [
                'account_type'   => 2,
                'our_company_id' => $erp_payment_data['our_company_id'],
                'company_id'     => $erp_payment_data['sale_company_id'],
            ];
            $account_balance = $this->getModel('ErpAccount')->findAccount($where)['account_balance'];
            $account_balance = $account_balance <= 0 ? 0 : $account_balance;
            $account_balance = getNum($account_balance + $erp_payment_data['pay_money']) ;
            if($account_balance < 0){
                $account_status = false;
                $err_message = '该用户余额不足，请刷新后查看！';
            }else{
                $recharge_order = [
                    'order_status'    => 2,
                    'finance_status'  => 1,
                    'updater'         => $this->getUserInfo('id'),
                    'update_time'     => currentTime(),
                ];
                $order_status   = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['from_order_number' =>$purchase_return_order_info['order_number']], $recharge_order);
                //修改账户
                $payment_info['order_number']   = $payment_info['purchase_order_number'];
                $payment_info['company_id']     = $payment_info['sale_company_id'];
                $payment_info['object_type']    = 2;
                $account_status = $this->getEvent('ErpAccount')->changeAccount($payment_info, PREPAY_TYPE, $erp_payment_data['pay_money']);
                $account_status = $account_status && $order_status ? true : false;
            }
        }

        if ($old_status_payment && $status_payment && $status_stockout && $purchase_return_order_status && $purchase_order_status && $stock_status && $account_status) {
            //重新计算加权成本
            $stock_out_data['before_stock_num'] = $beforeNum;
            $stock_out_data['stock_id'] = $stockId ? $stockId : 0;
            $stock_out_data['change_num'] = $stock_out_data['actual_outbound_num'];
            updateStockInCost($stock_out_data);
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }

        cancelCacheLock('ErpReverse/reversePurchaseReturnPayment');
        return $result;
    }

    /**
     * 采退单付款红冲数据验证
     * @author guanyu
     * @time 2018-01-21
     */
    public function checkReversePurchaseReturnPayment($payment_info,$purchase_order_info)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if ($payment_info['reversed_status'] == 1) {
            $result['status'] = 2;
            $result['message'] = '该笔付款申请已红冲，请检查';
            return $result;
        }

        if ($payment_info['status'] != 10) {
            $result['status'] = 3;
            $result['message'] = '该笔付款申请未确认，请检查';
            return $result;
        }

        if ($payment_info['is_reverse'] == 1) {
            $result['status'] = 4;
            $result['message'] = '该笔付款为红冲付款，禁止操作';
            return $result;
        }

        if (empty($purchase_order_info)) {
            $result['status'] = 5;
            $result['message'] = '对应采购单不存在，请检查';
            return $result;
        }

        if ($purchase_order_info['invoice_status'] != 1) {
            $result['status'] = 6;
            $result['message'] = '对应采购单已开票，请红冲发票后再进行操作';
            return $result;
        }

        return $result;
    }

    /**
     * 入库单红冲
     * @author guanyu
     * @time 2018-01-21
     */
    public function reverseStockIn($id)
    {
        //入库单信息
        $stock_in_info = $this->getModel('ErpStockIn')->where(['id' => $id])->find();
        if(in_array($stock_in_info['storage_type'], [4,5])){
            return [
                'status' => 2,
                'message' => '盘点入库单不能红冲',
            ];
        }
        if($stock_in_info['is_reverse'] == 1){
            return [
                'status' => 3,
                'message' => '红冲的入库单不能再红冲',
            ];
        }

        if ($stock_in_info['storage_type'] == 1) {
            return $this->reversePurchaseStockIn($id);
        }
        if ($stock_in_info['storage_type'] == 2) {
            return $this->reverseAllocationStockIn($id);
        }
        if ($stock_in_info['storage_type'] == 3) {
            return $this->reverseReturnedStockIn($id);
        }
    }

    /******************************************
    @ Content 销退入库单 红冲
    @ Author YF
    @ TIME 2019-03-04
     ******************************************/
    public function reverseReturnedStockIn( $id = 0 )
    {
        /* - 入库单信息 - */
        $stock_in_info = $this->getModel('ErpStockIn')->where(['id' => $id])->find();
        /* - 批次信息 - */
        /* ---------------- 更新前 ---------------------*/
        if ( empty($stock_in_info) ) {
            return ['status' => 20, 'message' => '未查询到对应的销退入库单，红冲失败！'];
        }
        if ( intval($stock_in_info['storage_status']) == 2 ) {
            return ['status' => 21, 'message' => '入库单为取消状态，无需操作！'];
        }
        if ( intval($stock_in_info['storage_status']) == 1 ){
            return ['status' => 22 ,'message' => '入库单为未审核状态，请直接取消！'];
        }
        if ( intval($stock_in_info['reversed_status']) == 1 ){
            return ['status' => 22 ,'message' => '入库单已被红冲，请勿重复操作！'];
        }
        if ( intval($stock_in_info['is_reverse']) == 1 ){
            return ['status' => 22 ,'message' => '该入库单为红冲入库单，无法红冲！'];
        }

        /* --------------- 查询销售退货单数据 ----------------- */
        $returned_order = $this->getModel('ErpReturnedOrder')->where(['id'=>$stock_in_info['source_object_id']])->find();
        $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['order_number' => $returned_order['source_order_number']]);

        if ( !isset($returned_order['source_order_number']) ) {
            return ['status' => 25 ,'message' => '销售退货单数据未查询到!'];
        }
        if ( !isset($sale_order_info['id']) ) {
            return ['status' => 26, 'message' => '未查询到销售单！'];
        }

        if(intval($sale_order_info['invoice_status']) != 1 ){
            $result['status']  = 26 ;
            $result['message'] = '销售退货单对应的销售单已开票，请先红冲销售发票！';
            return $result;
        }
        if (getCacheLock('ErpReverse/reverseReturnedStockIn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reverseReturnedStockIn', 1);
        M()->startTrans();
        $message = '红冲失败！';
        # 已入库
        # 生成对应的入库单冲减
        $add_stock_data = [
            'storage_code'         => erpCodeNumber(8)['order_number'],
            'storage_type'         => 3,
            'storage_status'       => 10,
            'source_number'        => $stock_in_info['source_number'],
            'source_object_id'     => $stock_in_info['source_object_id'],
            'our_company_id'       => $stock_in_info['our_company_id'],
            'goods_id'             => $stock_in_info['goods_id'],
            'storage_num'          => plusConvert($stock_in_info['storage_num']),
            'actual_storage_num'   => plusConvert($stock_in_info['actual_storage_num']),
            'outbound_density'     => $stock_in_info['outbound_density'],
            'creater_id'           => $this->getUserInfo('id'),
            'create_time'          => currentTime(),
            'dealer_id'            => $this->getUserInfo('id'),
            'dealer_name'          => $this->getUserInfo('dealer_name'),
            'storehouse_id'        => $stock_in_info['storehouse_id'],
            'stock_type'           => $stock_in_info['stock_type'],
            'region'               => $stock_in_info['region'],
            'is_reverse'           => 1,
            'reverse_source'       => $stock_in_info['storage_code'],
            'price'                => $stock_in_info['price'], //将获取的成本保存在红冲销退入库单价格上 edit xiaowen
            'batch_sys_bn'         => !empty($stock_in_info['batch_sys_bn']) ? $stock_in_info['batch_sys_bn'] : '',
            'batch_id'             => $stock_in_info['batch_id'],
            'cargo_bn_id'          => $stock_in_info['cargo_bn_id'],
        ];
        $add_stock_status = $this->getModel('ErpStockIn')->addStockIn($add_stock_data);
        if (!$add_stock_status) {
            $message = '新增红冲入库单失败！';
        }
        /* - 生成日志 - */
        $log_info_data = [
            'event'     => '销售退货单红冲-生成负数的入库单',
            'key'       => $stock_in_info['source_number'],
            'request'   => '销售退货单的入库单生成一笔负数的入库单：'.json_encode($add_stock_data).'，生成入库单状态：'.$add_stock_status,
        ];
        log_write($log_info_data);

        $update_order_data = [
            'returned_goods_num'    => $sale_order_info['returned_goods_num'] - $stock_in_info['storage_num'],
            'updater'               => $this->getUserInfo('id'),
            'update_time'           => currentTime(),
        ];
        $update_sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['order_number' => $returned_order['source_order_number']],$update_order_data);
        if ( !$update_sale_order_status ) {
            $message = '销售单更新退货数量失败！';
        }
        /* - 查询库存条件 - */
        $stock_where = [
            'goods_id'       => $add_stock_data['goods_id'],
            'region'         => $add_stock_data['region'],
            'object_id'      => $add_stock_data['storehouse_id'],
            'stock_type'     => $add_stock_data['stock_type'],
            'our_company_id' => $add_stock_data['our_company_id'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        if ( empty($stock_info) ) {
            $message = '库存信息不存在！';
        }
        //保存变动库存前的物理库存 edit xiaowen
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        $log_info_data = [
            'event'     => '销售退货单红冲-确定影响的库存',
            'key'       => $stock_in_info['source_number'],
            'request'   => '销售退货单确定影响的库存是：'.json_encode($stock_where),
        ];
        log_write($log_info_data);
        //------------------红冲销退入库，需先验证库存是否满足 eidt xiaowen 2018-3-27----------------
        if( $stock_info['stock_num'] < $stock_in_info['actual_storage_num'] ){
            $message = '物理库存不足，无法红冲销退单';
        }
        $data = [
            'goods_id'       => $stock_where['goods_id'],
            'object_id'      => $stock_where['object_id'],
            'stock_type'     => $stock_where['stock_type'],
            'region'         => $stock_where['region'],
            'stock_num'      => $stock_info['stock_num'] - $stock_in_info['actual_storage_num'],
            'sale_wait_num'  => $stock_info['sale_wait_num'] + ($stock_in_info['storage_num'] - $stock_in_info['actual_storage_num']),
        ];
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的物理库存
        $log_info_data = [
            'event'     => '销售退货单红冲-确定影响的库存',
            'key'       => $stock_in_info['source_number'],
            'request'   => '销售退货单新的物理库存是：'.$stock_info['stock_num'],
        ];
        log_write($log_info_data);

        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);

        $orders = [
            'object_number' => $add_stock_data['storage_code'],
            'object_type'   => 4,
            'log_type'      => 12,  // 红冲
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $add_stock_data['actual_storage_num'], $orders);

        $log_info_data = [
            'event'     => '销售退货单红冲-确定影响的库存',
            'key'       => $stock_in_info['source_number'],
            'request'   => '销售退货单导致的库存更改状态：'.$stock_status,
        ];
        log_write($log_info_data);
        /* --------------------------- 修改原入库记录的红冲状态 ------------------------------------- */
        $old_stock_in_data = [
            'reversed_status' => 1
        ];
        $old_status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id' => $id],$old_stock_in_data);

        /* --------------------------- 修改批次信息 ------------------------------------ */
        if( $stock_in_info['actual_storage_num'] > 0 && $stock_in_info['batch_id'] > 0 ){
            $batch_change_data = [
                'batch_id'           => $stock_in_info['batch_id'],
                'change_balance_num' => plusConvert($stock_in_info['actual_storage_num']), //减少批次可用
                'change_type'        => 4,
                'change_number'      => $add_stock_data['storage_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
            if($batch_result['status'] != 1){
                $message = '批次数量扣减失败！';
            }
        }else{
            $batch_result['status'] = 1;
        }

        if( $add_stock_status && $stock_status && $old_status_stockin && $batch_result['status'] == 1 && $update_sale_order_status){
            //重新计算加权成本 edit xiaowen 2018-2-7
            $add_stock_data['before_stock_num'] = $beforeNum;
            $add_stock_data['stock_id'] = $stockId ? $stockId : 0;
            $add_stock_data['change_num'] = $add_stock_data['actual_storage_num'];
            updateStockInCost($add_stock_data);
            M()->commit();
            cancelCacheLock('ErpReverse/reverseReturnedStockIn');
            return ['status' => 1, 'message' => '红冲成功！'];
        }
        M()->rollback();
        cancelCacheLock('ErpReverse/reverseReturnedStockIn');
        return ['status' => 24, 'message' => $message];
    }

    /**
     * 采购入库单红冲
     * @author guanyu
     * @time 2018-01-21
     */
    public function reversePurchaseStockIn($id)
    {
        //入库单信息
        $stock_in_info = $this->getModel('ErpStockIn')->where(['id' => $id])->find();

        $loss_order = $this->getModel('ErpLossOrder')->where(['source_number'=>$stock_in_info['storage_code']])->find(); //'order_status'=>10
        $loss_num = 0;
        if (!empty($loss_order)) {
            $loss_num += $loss_order['loss_num'];
        }
        if ( intval($stock_in_info['storage_status']) == 2 ) {
            return ['status' => 21, 'message' => '入库单为取消状态，无需操作！'];
        } else if ( intval($stock_in_info['storage_status']) == 1 ){
            return ['status' => 22 ,'message' => '入库单为未审核状态，请直接取消！'];
        }else if( intval($stock_in_info['storage_status']) != 10 ){
            return ['status' => 23 ,'message' => '入库单不是已审核状态，不能进行红冲！'];
        }
        //edit xiaowen 2019-5-28 入库红冲增加验证：损耗必须先红冲
        if(!empty($loss_order) && $loss_order['order_status'] != 2){
            return ['status' => 24 ,'message' => '请先完成对应损耗单的红冲！'];
        }

        //对应采购单信息
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $stock_in_info['source_object_id'],'order_number' => $stock_in_info['source_number']]);

        //批次信息
        $batch_info = $this->getModel('ErpBatch')->where(['id'=>$stock_in_info['batch_id'],'sys_bn'=>$stock_in_info['batch_sys_bn']])->find();

        //验证单据信息
        $check_result = $this->checkReversePurchaseStockIn($stock_in_info,$purchase_order_info,$batch_info);

        if ($check_result['status'] != 1) {
            return $check_result;
        }

        if (getCacheLock('ErpReverse/reverseStockIn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reverseStockIn', 1);

        M()->startTrans();

        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $stock_in_info['goods_id'],
            'object_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        //---------------判断出库方的物理库存是否满足实际出库数量----------------------
        if(intval($stock_info['stock_num']) < intval($stock_in_info['actual_storage_num'])){
            M()->rollback();
            cancelCacheLock('ErpReverse/reverseStockIn');
            return  $result = [
                'status' =>0,
                'message' =>'物理库存不足，无法红冲',
            ];
        }
        //采购 入库单红冲 批次处理========================================
        if($stock_in_info['actual_storage_num'] > 0 && $stock_in_info['batch_id']){
            $batch_change_data = [
                'batch_id' => $stock_in_info['batch_id'],
                'change_balance_num' => plusConvert($stock_in_info['actual_storage_num']), //扣减批次可用数量
                'change_type' => 3,
                'change_number' => $stock_in_info['storage_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
            if($batch_result['status'] != 1){
                M()->rollback();
                cancelCacheLock('ErpReverse/reverseStockIn');
                return $batch_result;
            }
        }
        // ==========end 采购 入库单红冲 批次处理========================================

        //新增红冲入库单
        $stock_in_data = [
            'storage_code' => erpCodeNumber(8)['order_number'],
            'storage_type' => $stock_in_info['storage_type'],
            'storage_status' => 10,
            'source_number' => $stock_in_info['source_number'],
            'source_object_id' => $stock_in_info['source_object_id'],
            'our_company_id' => $stock_in_info['our_company_id'],
            'goods_id' => $stock_in_info['goods_id'],
            'storage_num' => $stock_in_info['storage_num'] * -1,
            'actual_storage_num' => $stock_in_info['actual_storage_num'] * -1,
            'outbound_density' => $stock_in_info['outbound_density'],
            'creater_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'storehouse_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
            'region' => $stock_in_info['region'],
            'is_reverse' => 1,
            'reverse_source' => $stock_in_info['storage_code'],
            'price' => $purchase_order_info['price'],
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'batch_id' => $stock_in_info['batch_id'],
            'batch_sys_bn' => $stock_in_info['batch_sys_bn'],
            'cargo_bn_id' => $stock_in_info['cargo_bn_id'],
            'source_apply_number' => $stock_in_info['source_apply_number'],
        ];

        $status_stockin = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
        # 审核通过，增加操作日志
        $status_stockin_log = $this->getEvent('ErpStock')->addStockOptionLog($stock_in_info['storage_code'], 2, 3);
        //获取入库单改变物理库存之前的物理库存 edit xiaowen
        $beforeCostNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $stock_in_info['goods_id'],
            'object_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
            'region' => $stock_in_info['region'],
            'stock_num' => $stock_info['stock_num'] + $stock_in_data['actual_storage_num'],
            'transportation_num' => $stock_info['transportation_num'] - $stock_in_data['actual_storage_num'] + $loss_num,
        ];
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的在途库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_in_data['storage_code'],
            'object_type' => 4,
            'log_type' => 12,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_in_data['actual_storage_num'], $orders);

        //修改单据入库数量
        $update_order_data = [
            'storage_quantity' => $purchase_order_info['storage_quantity'] + $stock_in_data['actual_storage_num'],
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $status_purchase_order = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $purchase_order_info['id']], $update_order_data);

        //修改原入库记录的红冲状态
        $old_stock_in_data = [
            'reversed_status' => 1
        ];
        $old_status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id' => $id],$old_stock_in_data);

        //更新批次数据
        $batch_data = [
            'batch_id' => $batch_info['id'],
            'change_balance_num' => $stock_in_info['actual_storage_num'] * -1,
            'change_reserve_num' => 0,
            'change_type' => 4,
            'change_number' => $stock_in_info['storage_code'],
        ];
        $batch_status = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);

        if ($stock_status && $status_purchase_order && $old_status_stockin && $status_stockin && $status_stockin_log && $batch_status) {
            $stock_in_data['before_stock_num'] = $beforeCostNum;
            $stock_in_data['stock_id'] = $stockId ? $stockId : 0;
            $stock_in_data['change_num'] = $stock_in_data['actual_storage_num'];

            //是否有损耗？若有损耗，则进行三笔加权（或者两笔，取决于是否有超损）
            if (!empty($loss_order) && $loss_order['reasonable_loss_num'] > 0) {
                //合理损耗影响成本
                //与正向的处理顺序相反，先处理合理出红冲
                $reasonable_stock_out_info = $stock_in_data;
                $reasonable_stock_out_info['before_stock_num'] = $beforeCostNum;
                $reasonable_stock_out_info['change_num'] = $loss_order['reasonable_loss_num'];
                $reasonable_stock_out_info['price'] = 0;
                updateStockInCost($reasonable_stock_out_info);

                //处理合理入红冲
                $reasonable_stock_in_info = $stock_in_data;
                $reasonable_stock_in_info['before_stock_num'] = $beforeCostNum + $reasonable_stock_out_info['actual_storage_num'];
                $reasonable_stock_in_info['change_num'] = $loss_order['reasonable_loss_num'] * -1;
                updateStockInCost($reasonable_stock_in_info);
            }

            //计算加权成本 edit xiaowen 2018-2-7
            updateStockInCost($stock_in_data);

            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }

        cancelCacheLock('ErpReverse/reverseStockIn');
        return $result;
    }

    /**
     * 采购入库单红冲数据验证
     * @author guanyu
     * @time 2018-01-21
     */
    public function checkReversePurchaseStockIn($stock_in_info,$purchase_order_info,$batch_info)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];
        /************** YF Time 2018-12-11 ****************/
        if ( !empty($stock_in_info['retail_inner_order_number']) || $purchase_order_info['business_type'] == 6 ) {
            return ['status' => 0,'message' => '此入库单 属于内部交易单，不允许红冲！'];
        }
        /******************* END *************************/
        if ($stock_in_info['storage_type'] != 1) {
            $result['status'] = 2;
            $result['message'] = '只有采购单对应的入库单才可红冲，请检查';
            return $result;
        }

        if ($batch_info['balance_num'] - $batch_info['reserve_num'] < $stock_in_info['actual_storage_num']) {
            $result['status'] = 2;
            $result['message'] = '该批次可用数量不足，请检查';
            return $result;
        }

        if ($stock_in_info['reversed_status'] == 1) {
            $result['status'] = 3;
            $result['message'] = '该笔入库单已红冲，请检查';
            return $result;
        }

        if ($stock_in_info['storage_status'] != 10) {
            $result['status'] = 4;
            $result['message'] = '未审核入库单无法红冲，请检查';
            return $result;
        }

        if ($stock_in_info['is_reverse'] == 1) {
            $result['status'] = 5;
            $result['message'] = '该笔入库单为红冲入库单，禁止操作';
            return $result;
        }

        if (empty($purchase_order_info)) {
            $result['status'] = 6;
            $result['message'] = '对应采购单不存在，请检查';
            return $result;
        }

//        if ($purchase_order_info['invoice_status'] != 1) {
//            $result['status'] = 6;
//            $result['message'] = '对应采购单已开票，请红冲发票后再进行操作';
//            return $result;
//        }

        if ($purchase_order_info['is_returned'] == 1) {
            $result['status'] = 7;
            $result['message'] = '对应采购单已退货，请红冲退货后再进行操作';
            return $result;
        }

        if($stock_in_info['is_shipping'] == 1){
            $result['status']  = 8;
            $result['message'] = "该笔入库单已生成配送单，请先取消配送单！";
            return $result;
        }

        return $result;
    }

    /**
     * 调拨入库单红冲
     * @author guanyu
     * @time 2018-01-21
     */
    public function reverseAllocationStockIn($id)
    {
        //入库单信息
        $stock_in_info = $this->getModel('ErpStockIn')->where(['id' => $id])->find();

        //对应调拨单信息
        $allocation_order_info = $this->getModel('ErpAllocationOrder')->where(['id' => $stock_in_info['source_object_id'],'order_number' => $stock_in_info['source_number']])->find();

        //对应批次的出库单信息
        $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number'=>$allocation_order_info['order_number'],'batch_sys_bn'=>$stock_in_info['batch_sys_bn'],'reversed_status'=>2,'is_reverse'=>2])->find();
        if (empty($stock_out_info)) {
            $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number'=>$allocation_order_info['order_number'],'reversed_status'=>2,'is_reverse'=>2])->find();
        }

        //验证单据信息
        $check_result = $this->checkReverseAllocationStockIn($stock_in_info,$allocation_order_info);

        if ($check_result['status'] != 1) {
            return $check_result;
        }
        //edit xiaowen 2019-5-28 入库红冲增加验证：损耗必须先红冲
        $loss_order = $this->getModel('ErpLossOrder')->where(['source_number'=>$stock_in_info['storage_code']])->find();

        if(!empty($loss_order) && $loss_order['order_status'] != 2){
            return ['status' => 24 ,'message' => '请先完成对应损耗单的红冲！'];
        }
        //记录日志，调用java入库单回滚接口，回滚零售订单归还库存
        if ($stock_in_info['stock_type'] == 4) {
            # java 红冲零售出库单 -> 回滚库存 -> 是否成功 -> 回滚入库方库存，生成红冲入库单-> 接口重新计算成本
            $log_info_data = [
                'event' => '调拨单红冲-已出库已入库，入库方为服务网点，java接口红冲零售出库单：',
                'key' => $allocation_order_info['order_number'],
                'request' => '调拨单入库单单号为：' . $stock_in_info['storage_code'] . '该入库单红冲状态：' . $stock_in_info['reversed_status'],
            ];
            log_write($log_info_data);
            $status = reverseRetailOrder($stock_in_info['storage_code']);
            if (!$status) {
                $log_info_data = [
                    'event' => '调拨单红冲-已出库已入库，入库方为服务网点，java接口红冲零售出库单：',
                    'key' => $allocation_order_info['order_number'],
                    'request' => '零售出库单回滚失败：',
                ];
                log_write($log_info_data);
                return $result = [
                    'status' => 0,
                    'message' => '零售出库单回滚失败，请重试！',
                ];
            }
        }

        //判断调拨单对应入库单是否被内部交易占用
        $inner_stock_out = $this->getModel('ErpStockOut')
            ->alias('so')
            ->field('so.outbound_code')
            ->join('oil_erp_stock_in_deduction as sid on so.outbound_code = sid.outbound_code')
            ->join('oil_erp_stock_in as si on si.storage_code = sid.source_stock_in_number')
            ->where(['si.storage_code'=>$stock_in_info['storage_code'],'so.retail_inner_order_number'=>['neq',''],'so.is_reverse'=>2,'so.reversed_status'=>2])
            ->select();

        $inner_reverse_all_status = true;
        if (!empty($inner_stock_out)) {
            foreach ($inner_stock_out as $key=>$value) {
                /**查询出库单信息*/
                $inner_stock_out_info = $this->getModel('ErpStockOut')->where(['outbound_code' => $value['outbound_code']])->find();

                /**查询入库单信息*/
                $inner_stock_in_info = $this->getModel('ErpStockIn')->where(['retail_inner_order_number' => $inner_stock_out_info['retail_inner_order_number'], 'batch_id' => $inner_stock_out_info['batch_id']])->find();
                if (empty($inner_stock_in_info)) {
                    $inner_stock_in_info = $this->getModel('ErpStockIn')->where(['retail_inner_order_number' => $inner_stock_out_info['retail_inner_order_number'], 'actual_storage_num' => $inner_stock_out_info['actual_outbound_num']])->find();
                }

                /**查询内部交易单信息*/
                $inner_order_info = $this->getModel('ErpRetailInnerOrder')->where(['order_number' => $inner_stock_out_info['retail_inner_order_number']])->find();

                /**红冲入库单对应的零售出库单*/
                $log_info_data = [
                    'event' => '内部交易单红冲-已出库已入库，入库方为服务网点，java接口红冲零售出库单：',
                    'key' => $inner_order_info['inventory_order_number'],
                    'request' => '调拨单入库单单号为：' . $inner_stock_in_info['storage_code'] . '该入库单红冲状态：' . $inner_stock_in_info['reversed_status'],
                ];
                log_write($log_info_data);
                $status = reverseRetailOrder($inner_stock_in_info['storage_code']);
                if (!$status) {
                    $old_stock_in_data = [
                        'reversed_status' => 2
                    ];
                    $this->getModel('ErpStockIn')->saveStockIn(['id' => $id],$old_stock_in_data);
                    $log_info_data = [
                        'event' => '内部交易单红冲-已出库已入库，入库方为服务网点，java接口红冲零售出库单：',
                        'key' => $inner_order_info['inventory_order_number'],
                        'request' => '零售出库单回滚失败：',
                    ];
                    log_write($log_info_data);
                    return $result = [
                        'status' => 0,
                        'message' => '零售出库单回滚失败，请重试！',
                    ];
                }
            }

            if (getCacheLock('ErpReverse/reverseAllocationStockIn')) return ['status' => 99, 'message' => $this->running_msg];
            setCacheLock('ErpReverse/reverseAllocationStockIn', 1);

            M()->startTrans();

            foreach ($inner_stock_out as $key=>$value) {
                $inner_reverse_result = $this->reverseInnerOrder($value['outbound_code'],$stock_in_info['storage_code']);
                $inner_reverse_all_status = $inner_reverse_all_status && $inner_reverse_result['status'] ? true : false;
                if (!$inner_reverse_all_status) {
                    break;
                }
            }
        } else {
            if (getCacheLock('ErpReverse/reverseAllocationStockIn')) return ['status' => 99, 'message' => $this->running_msg];
            setCacheLock('ErpReverse/reverseAllocationStockIn', 1);

            M()->startTrans();
        }
        if (!$inner_reverse_all_status) {
            M()->rollback();
            cancelCacheLock('ErpReverse/reverseAllocationStockIn');
            return  $result = [
                'status' =>0,
                'message' =>'入库单对应内部交易单红冲失败，失败信息：'.$inner_reverse_result['message'],
            ];
        }
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $stock_in_info['goods_id'],
            'object_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        //---------------判断出库方的物理库存是否满足实际出库数量----------------------
        if(intval($stock_info['stock_num']) < intval($stock_in_info['actual_storage_num'])){
            M()->rollback();
            cancelCacheLock('ErpReverse/reverseAllocationStockIn');
            $old_stock_in_data = [
                'reversed_status' => 2
            ];
            $this->getModel('ErpStockIn')->saveStockIn(['id' => $id],$old_stock_in_data);
            return  $result = [
                'status' =>0,
                'message' =>'入库方物理库存不足,无法红冲入库单',
            ];
        }

        //批次信息
        $batch_info = $this->getModel('ErpBatch')->where(['id'=>$stock_in_info['batch_id'],'sys_bn'=>$stock_in_info['batch_sys_bn']])->find();

        if ($batch_info['balance_num'] - $batch_info['reserve_num'] < $stock_in_info['actual_storage_num']) {
            $result['status'] = 3;
            $result['message'] = '该批次可用数量不足，请检查';
            return $result;
        }

        $stock_in_info = $this->getModel('ErpStockIn')->where(['id' => $id])->find();
        //新增红冲入库单
        $stock_in_data = [
            'storage_type'              => 2,
            'storage_code'              => erpCodeNumber(8)['order_number'],
            'storage_status'            => 10,
            'source_number'             => $stock_in_info['source_number'],
            'source_object_id'          => $stock_in_info['source_object_id'],
            'goods_id'                  => $stock_in_info['goods_id'],
            'our_company_id'            => $stock_in_info['our_company_id'],
            'storage_num'               => $stock_in_info['storage_num'] * -1,
            'actual_storage_num'        => $stock_in_info['actual_storage_num'] * -1,
            'outbound_density'          => $stock_in_info['outbound_density'],
            'create_time'               => currentTime(),
            'dealer_id'                 => $this->getUserInfo('id'),
            'dealer_name'               => $this->getUserInfo('dealer_name'),
            'creater_id'                => empty($this->getUserInfo('id')) ? '' : $this->getUserInfo('id'),
            'auditor_id'                => $this->getUserInfo('id'),
            'audit_time'                => currentTime(),
            'storehouse_id'             => $stock_in_info['storehouse_id'],
            'is_reverse'                => 1,
            'reverse_source'            => $stock_in_info['storage_code'],
            'price'                     => $stock_in_info['price'],
            'actual_storage_num_litre'  => $stock_in_info['actual_storage_num_litre'],
            'balance_num'               => $stock_in_info['balance_num'],
            'balance_num_litre'         => $stock_in_info['balance_num_litre'],
            'stock_type'                => $stock_in_info['stock_type'],
            'region'                    => $stock_in_info['region'],
            'batch_sys_bn'              => $stock_in_info['batch_sys_bn'],
            'batch_id'                  => $stock_in_info['batch_id'],
            'cargo_bn_id'               => $stock_in_info['cargo_bn_id'],
        ];

        $status_stockin = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);

        # 审核通过，增加操作日志
        $status_stockin_log = $this->getEvent('ErpStock')->addStockOptionLog($stock_in_info['storage_code'], 2, 3);

        $other_stock_in = $this->getModel('ErpStockIn')->where(['storage_code'=>['neq',$stock_in_info['storage_code']],'source_number'=>$stock_in_info['source_number'],'reversed_status'=>2,'is_reverse'=>2])->find();
        //修改调拨单状态
        if (empty($other_stock_in)) {
            $where = [
                'id'=>$allocation_order_info['id'],
            ];
            $allocation_data = [
                'storage_status' => 2,
                'actual_in_num' => $allocation_order_info['actual_in_num'] - $stock_in_info['storage_num'],
                'actual_in_num_liter' => $allocation_order_info['actual_in_num_liter'] - $stock_in_info['actual_storage_num_litre'],
            ];
        } else {
            $where = [
                'id'=>$allocation_order_info['id'],
            ];
            $allocation_data = [
                'storage_status' => 3,
                'actual_in_num' => $allocation_order_info['actual_in_num'] - $stock_in_info['storage_num'],
                'actual_in_num_liter' => $allocation_order_info['actual_in_num_liter'] - $stock_in_info['actual_storage_num_litre'],
            ];
        }
        $allocation_status = $this->getModel('ErpAllocationOrder')->where($where)->save($allocation_data);

        //修改原入库记录的红冲状态
        $old_stock_in_data = [
            'reversed_status' => 1
        ];
        $old_status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id' => $id],$old_stock_in_data);

        //获取入库单改变物理库存之前的物理库存 edit xiaowen
        $beforeCostNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $stock_in_info['goods_id'],
            'object_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
            'region' => $stock_in_info['region'],
            'stock_num' => $stock_info['stock_num'] + $stock_in_data['actual_storage_num'],
            'transportation_num' => $stock_info['transportation_num'] + $stock_out_info['actual_outbound_num'],
        ];
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的物理库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_in_data['storage_code'],
            'object_type' => 4,
            'log_type' => 12,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_in_data['actual_storage_num'], $orders);

        //更新批次数据
        $batch_info['balance_num'] -= $stock_in_info['actual_storage_num'];
        $batch_data = [
            'batch_id' => $batch_info['id'],
            'change_balance_num' => plusConvert($stock_in_info['actual_storage_num']),
            'change_reserve_num' => 0,
            'change_type' => 4,
            'change_number' => $stock_in_info['storage_code'],
        ];
        $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);
        if($batch_result['status'] != 1){
            M()->rollback();
            cancelCacheLock('ErpReverse/reverseAllocationStockIn');
            return $batch_result;
        }

        $log_info_data = [
            'event'     => '调拨单红冲-已出库已入库，减少入库方物理库存：',
            'key'       => $allocation_order_info['order_number'],
            'request'   => '确定库存：' . json_encode($stock_where) . '。更改物理库存为：' . $stock_info['stock_num']
        ];
        log_write($log_info_data);

        //判断调拨单对应入库单是否用于冲减网点期初负库存，若有，则返还原负库存
        $deduction_data = $this->getModel('ErpStockInDeduction')->where(['source_stock_in_number'=>$stock_in_info['storage_code'],'type'=>1])->select();
        $deduction_status_all = true;
        if (!empty($deduction_data)) {
            $deduction_num = 0;
            $before_balance_num = $after_balance_num = $stock_in_info['balance_num'];
            foreach ($deduction_data as $key=>$value) {
                $deduction_num += $value['deduction_num'];
                $stock_info = $this->getModel('ErpStock')->where(['id'=>$value['stock_id']])->find();

                //修改网点期初冲减数量
                $this->getModel('ErpStockSkidData')->where(['stock_id'=>$stock_info['id']])->setDec('deduction_num',$value['deduction_num']);

                $orders = [
                    'object_number' => $stock_in_info['storage_code'],
                    'object_type' => 4,
                    'log_type' => 12,
                ];

                $stock_info['stock_num'] -= $value['deduction_num'];
                $stock_info['available_num'] -= $value['deduction_num'];
                //修改被冲减的库存记录
                $this->getEvent('ErpStock')->saveStockInfo($stock_info, $value['deduction_num']*-1, $orders);
                $after_balance_num = $after_balance_num + $value['deduction_num'];
                //插入冲减记录
                $deduction_data = [
                    'source_stock_in_number' => $stock_in_info['storage_code'],
                    'type' => 1,
                    'stock_id' => $stock_info['id'],
                    'before_balance_num' => $before_balance_num,
                    'after_balance_num' => $after_balance_num,
                    'deduction_num' => $value['deduction_num'] * -1,
                    'deduction_type' => 2,
                    'create_time' => currentTime(),
                ];
                $deduction_status = $this->getModel('ErpStockInDeduction')->add($deduction_data);
                $deduction_status_all = $deduction_status && $deduction_status_all ? true : false;
                $before_balance_num = $before_balance_num + $value['deduction_num'];
            }
            //冲减负库存结束后，修改原入库单的可用数量
            $stock_in_info['balance_num'] = $after_balance_num;
            $stock_in_info['balance_num_litre'] = $stock_in_info['balance_num'] / $stock_in_info['outbound_density'] * 1000;
            $stock_in_info['deduction_num'] = $stock_in_info['deduction_num'] - $deduction_num;
            $in_deduction_status = $this->getModel('ErpStockIn')->where(['storage_code'=>$stock_in_info['storage_code']])->save($stock_in_info);

            $deduction_status_all = $in_deduction_status && $deduction_status_all ? true : false;
        } else {
            $deduction_status_all = true;
        }

        if ($stock_status && $old_status_stockin !== false && $status_stockin && $status_stockin_log && $allocation_status
            && $batch_result['status'] == 1 && $deduction_status_all) {
            $stock_in_data['before_stock_num'] = $beforeCostNum;
            $stock_in_data['change_num'] = $stock_in_data['actual_storage_num'];
            $stock_in_data['stock_id'] = $stockId ? $stockId : 0;

            //是否有损耗？若有损耗，则进行三笔加权（或者两笔，取决于是否有超损）
            if (!empty($loss_order) && $loss_order['reasonable_loss_num'] > 0) {
                //合理损耗影响成本
                //与正向的处理顺序相反，先处理合理出红冲
                $reasonable_stock_out_info = $stock_in_data;
                $reasonable_stock_out_info['before_stock_num'] = $beforeCostNum;
                $reasonable_stock_out_info['change_num'] = $loss_order['reasonable_loss_num'];
                $reasonable_stock_out_info['price'] = 0;
                updateStockInCost($reasonable_stock_out_info);

                //处理合理入红冲
                $reasonable_stock_in_info = $stock_in_data;
                $reasonable_stock_in_info['before_stock_num'] = $beforeCostNum + $reasonable_stock_out_info['actual_storage_num'];
                $reasonable_stock_in_info['change_num'] = $loss_order['reasonable_loss_num'] * -1;
                updateStockInCost($reasonable_stock_in_info);
            }

            //计算加权成本 edit xiaowen 2018-2-7
            updateStockInCost($stock_in_data);
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }

        cancelCacheLock('ErpReverse/reverseAllocationStockIn');
        return $result;
    }

    /**
     * 采购入库单红冲数据验证
     * @author guanyu
     * @time 2018-01-21
     */
    public function checkReverseAllocationStockIn($stock_in_info,$allocation_order_info)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if ($stock_in_info['reversed_status'] == 1) {
            $result['status'] = 3;
            $result['message'] = '该笔入库单已红冲，请检查';
            return $result;
        }

        if ($stock_in_info['storage_status'] != 10) {
            $result['status'] = 4;
            $result['message'] = '未审核入库单无法红冲，请检查';
            return $result;
        }

        if ($stock_in_info['is_reverse'] == 1) {
            $result['status'] = 5;
            $result['message'] = '该笔入库单为红冲入库单，禁止操作';
            return $result;
        }

        if (empty($allocation_order_info)) {
            $result['status'] = 6;
            $result['message'] = '对应调拨单不存在，请检查';
            return $result;
        }

        if ($allocation_order_info['status'] != 10) {
            $result['status'] = 7;
            $result['message'] = '对应调拨单状态异常，请检查';
            return $result;
        }

        if($stock_in_info['is_shipping'] == 1){
            $result['status']  = 8;
            $result['message'] = "该笔入库单已生成配送单，请先取消配送单！";
            return $result;
        }

        return $result;
    }

    /**
     * 采购发票红冲
     * @author guanyu
     * @time 2018-01-21
     */
    public function reversePurchaseInvoice($id)
    {
        //采购发票信息
        $purchase_invoice_info = $this->getModel('ErpPurchaseInvoice')->where(['id' => $id])->find();

        //对应采购单信息
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $purchase_invoice_info['purchase_id'],'order_number' => $purchase_invoice_info['purchase_order_number']]);

        //验证单据信息
        $check_result = $this->checkReversePurchaseInvoice($purchase_order_info,$purchase_invoice_info);

        if ($check_result['status'] != 1) {
            return $check_result;
        }

        if (getCacheLock('ErpReverse/reversePurchaseInvoice')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReverse/reversePurchaseInvoice', 1);

        M()->startTrans();

        //最新发票金额
        $invoice_money = $purchase_order_info['invoice_money'] - $purchase_invoice_info['apply_invoice_money'];
        if ($invoice_money <= 0) {
            $invoice_status = 1;
        } elseif ($invoice_money > 0 && $invoice_money < $purchase_order_info['order_amount']) {
            $invoice_status = 2;
        } elseif ($invoice_money == $purchase_order_info['order_amount']) {
            $invoice_status = 10;
        }

        //更新订单信息
        $update_order_data = [
            'invoice_money' => $invoice_money,
            'invoice_status' => $invoice_status,
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $status_purchase_order = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $purchase_invoice_info['purchase_id'],'order_number' => $purchase_invoice_info['purchase_order_number']], $update_order_data);

        //新增日志
        $log_data = [
            'purchase_id' => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'log_type' => 10,
            'log_info' => serialize($purchase_order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

        //修改原入库记录的红冲状态
        $old_erp_invoice_data = [
            'reversed_status' => 1
        ];
        $old_status_invoice = $this->getModel('ErpPurchaseInvoice')->savePurchaseInvoice(['id' => $id],$old_erp_invoice_data);

        //新增红冲发票
        $erp_invoice_data = [
            'purchase_id' => $purchase_invoice_info['purchase_id'],
            'purchase_order_number' => $purchase_invoice_info['purchase_order_number'],
            'invoice_sn' => '',
            'notax_invoice_money' => $purchase_invoice_info['notax_invoice_money'] * -1,
            'tax_money' => $purchase_invoice_info['tax_money'] * -1,
            'apply_invoice_money' => $purchase_invoice_info['apply_invoice_money'] * -1,
            'invoice_type' => $purchase_invoice_info['invoice_type'],
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'status' => 10,
            'remark' => $purchase_invoice_info['remark'],
            'is_reverse' => 1,
            'reverse_source' => $purchase_invoice_info['id'],
            'auditor'    => $this->getUserInfo('dealer_name'),
            'audit_time' => currentTime(),
        ];
        $status_invoice = $this->getModel('ErpPurchaseInvoice')->addPurchaseInvoice($erp_invoice_data);

        if ($status_purchase_order && $status_log && $old_status_invoice && $status_invoice) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }

        cancelCacheLock('ErpReverse/reversePurchaseInvoice');
        return $result;
    }

    /**
     * 采购发票红冲数据验证
     * @author guanyu
     * @time 2018-01-21
     */
    public function checkReversePurchaseInvoice($purchase_order_info,$purchase_invoice_info)
    {
        $result = [
            'status' => 1,
            'message' => '',
        ];

        if ($purchase_order_info['invoice_money'] - $purchase_invoice_info['apply_invoice_money'] < 0) {
            $result['status'] = 2;
            $result['message'] = '请优先操作负数发票';
            return $result;
        }

        if ($purchase_invoice_info['reversed_status'] == 1) {
            $result['status'] = 3;
            $result['message'] = '该笔发票已红冲，请检查';
            return $result;
        }

        if ($purchase_invoice_info['status'] != 10) {
            $result['status'] = 4;
            $result['message'] = '该笔发票未确认，请检查';
            return $result;
        }

        if ($purchase_invoice_info['is_reverse'] == 1) {
            $result['status'] = 5;
            $result['message'] = '该笔发票为红冲发票，禁止操作';
            return $result;
        }

        return $result;
    }

    /*
     * ------------------------------------------
     * 红冲采购单确认付款，生成预付记录，充值金额
     * Author：qianbin        Time：2017-05-21
     * ------------------------------------------
     */
    private function addPrepayOrderRecord($order_info = [],$payment_info = [])
    {
        if(count($payment_info) <= 0) return false;
        //新增单据
        $data = [
            'order_number'    => erpCodeNumber(14)['order_number'],
            'add_order_time'  => currentTime(),
            'our_company_id'  => session('erp_company_id'),
            'region'          => $order_info['region'],
            'user_id'         => $order_info['sale_user_id'],
            'company_id'      => $order_info['sale_company_id'],
            'collection_info' => $order_info['sale_collection_info'],
            'order_type'      => 2 ,
            'recharge_type'   => 21 , // 预付款
            'recharge_amount' => $payment_info['balance_deduction'],
            'order_status'    => 10,
            'finance_status'  => 10,
            'dealer_name'     => $this->getUserInfo('dealer_name'),
            'creater'         => $this->getUserInfo('id'),
            'create_time'     => currentTime(),
            'remark'          => '来源采购单号: '.trim($payment_info['purchase_order_number']).'&红冲采购单退还余额自动生成',
            'from_order_number'  => trim($payment_info['purchase_order_number']),
            'apply_finance_time' => currentTime(),
        ];
        $order_status = $id = $this->getModel('ErpRechargeOrder')->addRechargeOrder($data);

        //新增log
        $log_data = [
            'recharge_id'           => $id,
            'recharge_order_number' => $data['order_number'],
            'order_type'            => 2,
            'log_info'              => serialize($data),
            'log_type'              => 1,
            'create_time'           =>  currentTime(),
            'operator'              =>  $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0,
            'operator_id'           =>  $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0,
        ];
        $status = $this->getModel('ErpRechargeOrderLog')->add($log_data);

        //修改账户
        $order_info['company_id']     = $order_info['sale_company_id'];
        $order_info['our_company_id'] = $order_info['our_buy_company_id'];
        $order_info['object_type'] = 4;//单据类型：预存申请单
        $accout_status = $this->getEvent('ErpAccount')->changeAccount($order_info, PREPAY_TYPE, $payment_info['balance_deduction']);
        $status = ($order_status && $status && $accout_status) ? true : false;
        return $status ;
    }

    /**
     * 红冲销售出库单
     * @param $id 出库单id
     * @param $sale_stock_out_info 出库单数据
     * @return array $result
     */
    function reverseSaleStockOut($id, $sale_stock_out_info)
    {
        if (strtotime($sale_stock_out_info['create_time']) < strtotime('2018-03-11 22:00:00')) {
            $result = [
                'status' => 2,
                'message' => '2018-03-12前创建的出库单没有成本，不允许红冲',
            ];
        }
        else if($sale_stock_out_info['outbound_type'] != 1){
            $result = [
                'status' => 7,
                'message' => '只有销售出库单才能红冲',
            ];
        }
        else if($sale_stock_out_info['outbound_status'] != 10){
            $result = [
                'status' => 3,
                'message' => '出库单必须为已审核状态',
            ];
        } else if($sale_stock_out_info['reversed_status'] == 1){
            $result = [
                'status' => 6,
                'message' => '该出库单已红冲，无法再次红冲',
            ];
        }
//                else if($sale_stock_out_info['invoice_status'] != 1){
//                    $result = [
//                        'status' => 6,
//                        'message' => '请先红冲销售单发票',
//                    ];
//                }
        else if($sale_stock_out_info['outbound_quantity'] <= 0){
            $result = [
                'status' => 4,
                'message' => '销售单已出库数量必须大于0',
            ];
        } else if($sale_stock_out_info['order_status'] == 2){
            $result = [
                'status' => 5,
                'message' => '销售单已取消无法再红冲',
            ];
        } else if($sale_stock_out_info['is_reverse'] == 1){
            $result = [
                'status' => 8,
                'message' => '该出库单为红冲出库单，无法红冲',
            ];
        } else if(($this->getModel('ErpSaleOrder')->where(['order_number' => trim($sale_stock_out_info['source_number']),'order_type' => 1 ])->getField('is_returned')) == 1){
            $result = [
                'status' => 9,
                'message' => '该笔交易已产生了销退单，请先红冲销退单！',
            ];
        }else{

            if (getCacheLock('ErpReverse/saleStockOutReverse')) return ['status' => 99, 'message' => $this->running_msg];
            setCacheLock('ErpReverse/saleStockOutReverse', 1);
            M()->startTrans();
            //出库单处理----------------------------------------------------------------------------------------

            //修改原出库单状态
            $old_stock_out_data = [
                'reversed_status' => 1,
            ];
            $old_stock_out_status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$id],$old_stock_out_data);

            $new_stock_out_data = [
                'outbound_code' => erpCodeNumber(7)['order_number'],
                'outbound_type' => 1,
                'outbound_status' => 10,
                'outbound_remark' => '',
                'source_number' => $sale_stock_out_info['order_number'],
                'source_object_id' =>  $sale_stock_out_info['source_object_id'],
                'our_company_id' => $sale_stock_out_info['our_company_id'],
                'goods_id' => $sale_stock_out_info['goods_id'],
                'depot_id' => $sale_stock_out_info['depot_id'],
                'outbound_num' => plusConvert($sale_stock_out_info['outbound_num']),
                'actual_outbound_num' => plusConvert($sale_stock_out_info['actual_outbound_num']),
                'create_time' => currentTime(),
                'creater_id' => $this->getUserInfo('id'),
                'creater_name' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime(),
                'dealer_id' => $this->getUserInfo('id'),
                'dealer_name' => $this->getUserInfo('dealer_name'),
                'storehouse_id' => $sale_stock_out_info['storehouse_id'],
                'stock_type' => $sale_stock_out_info['stock_type'],
                'region' => $sale_stock_out_info['region'],
                'is_reverse' => 1,
                'reverse_source' => $sale_stock_out_info['outbound_code'],
                'batch_id' => $sale_stock_out_info['batch_id'],
                'batch_sys_bn' => $sale_stock_out_info['batch_sys_bn'],
                'cost' => $sale_stock_out_info['cost'],

            ];

            $new_stock_out_status = $this->getModel('ErpStockOut')->addStockOut($new_stock_out_data);
            //----------end 出库单处理--------------------------------------------------------------------------

            //处理销售单----------------------------------------------------------------------------------------
            //----修改销售单状态--------
            $sale_order_data = [
                'outbound_quantity' => $sale_stock_out_info['outbound_quantity'] - $sale_stock_out_info['actual_outbound_num'],
                'updater' => $this->getUserInfo('id'),
                'update_time' => currentTime()
            ];
            $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $sale_stock_out_info['source_object_id'], 'order_number'=>$sale_stock_out_info['source_number']], $sale_order_data);

            //-----保存操作到出库单日志-----
            $erp_stock_option_log = [
                'order_type'    => 1,                                         # 1 出库单  2 入库单
                'order_number'  => trim($sale_stock_out_info['outbound_code']),     # 单据号
                'log_type'      => 2,                                         # 操作类型 1 审核  2 取消审核
                'operator_id'   => session('erp_adminInfo')['id'],
                'operator'      => session('erp_adminInfo')['dealer_name'],
                'create_time'   => date('Y-m-d H:i:s',time())
            ];
            $add_log = M('erpStockOptionLog')->add($erp_stock_option_log);
            //end处理销售单----------------------------------------------------------------------------------------------
            //库存变动处理----------------------------------------------------------------------------------------
            $stock_where = [
                'goods_id' => $sale_stock_out_info['goods_id'],
                'object_id' => $sale_stock_out_info['storehouse_id'],
                'stock_type' => $sale_stock_out_info['is_agent'] == 1 ? 2 : $sale_stock_out_info['stock_type'],
                'our_company_id' => $sale_stock_out_info['our_company_id'],
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

            $beforeNum = $stock_info['stock_num'];
            $stockId = $stock_info['id'];

            $data = [
                'goods_id' => $sale_stock_out_info['goods_id'],
                'object_id' => $sale_stock_out_info['storehouse_id'],
                'stock_type' => $sale_stock_out_info['is_agent'] == 1 ? 2 : $sale_stock_out_info['stock_type'],
                'region' => $sale_stock_out_info['region'],
            ];

            $stock_info['stock_num'] = $data['stock_num'] = $stock_info['stock_num'] + $sale_stock_out_info['actual_outbound_num'];
            $stock_info['sale_wait_num'] = $data['sale_wait_num'] = $stock_info['sale_wait_num'] + $sale_stock_out_info['actual_outbound_num'];
            //------------------------------------------------------------------------------------------------------
            //重新计算可用库存数量----------------------------------------------------------------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            $orders = [
                'object_number' => $new_stock_out_data['outbound_code'],
                'object_type' => 3,
                'log_type' => 12,
                'our_company_id' => $sale_stock_out_info['our_company_id'],
            ];
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, plusConvert($sale_stock_out_info['actual_outbound_num']), $orders);
            //end 库存变动------------------------------------------------------------------------------------------

            /**************************************
            @ Content 处理批次信息
            @ Author  xiaowen
            @ Time    2019-03-08
             ***************************************/
            if( $sale_stock_out_info['actual_outbound_num'] > 0 && $sale_stock_out_info['batch_id'] > 0 ){
                $batch_change_data = [
                    'batch_id'           => $new_stock_out_data['batch_id'],
                    'change_balance_num' => plusConvert($new_stock_out_data['actual_outbound_num']), //减少批次预留
                    'change_type'        => 5,
                    'change_number'      => $new_stock_out_data['outbound_code'],
                ];
                $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
                if($batch_result['status'] != 1){
                    M()->rollback();
                    cancelCacheLock('ErpReverse/saleStockOutReverse');
                    return $batch_result;
                }
            }
            //end 批次处理------------------------------------------------------------------------------------------

            //网点出库红冲，归还入库单可用 edit xiaowen 2019-5-16
            if($sale_stock_out_info['stock_type'] == 4){
                $stock_in_dedcution_status = $this->returnToStockIn('', $sale_stock_out_info);
            }else{
                $stock_in_dedcution_status = ['status'=>true, 'message'=> ''];
            }

            cancelCacheLock('ErpReverse/saleStockOutReverse');
            if($stock_status && $old_stock_out_status && $new_stock_out_status && $sale_order_status && $add_log && $batch_result['status'] == 1 && $stock_in_dedcution_status['status']){
                //重新计算加权成本 edit xiaowen 2018-2-7
                $new_stock_out_data['before_stock_num'] = $beforeNum;
                $new_stock_out_data['stock_id'] = $stockId ? $stockId : 0;
                $new_stock_out_data['change_num'] = abs($new_stock_out_data['actual_outbound_num']);
                updateStockInCost($new_stock_out_data);
                M()->commit();
                $result = [
                    'status' => 1,
                    'message' => '操作成功',
                ];
            }else{
                M()->rollback();
                $result = [
                    'status' => 0,
                    'message' => '操作失败',
                ];
            }
        }

        return $result;

    }

    /**
     * 根据出库单红冲内部交易单（部分红冲）
     * @param $outbound_code 出库单号
     * @return array $result
     */
    function reverseInnerOrder($outbound_code = '',$from_storage_code)
    {
        if (empty($outbound_code)) {
            $result = [
                'status' => 2,
                'message' => '参数错误，请检查',
            ];
            return $result;
        }

        $message = '操作成功';
        /**查询出库单信息*/
        $stock_out_info = $this->getModel('ErpStockOut')->where(['outbound_code'=>$outbound_code])->find();

        /**查询销售单信息*/
        $sale_order_info = $this->getModel('ErpSaleOrder')->where(['order_number'=>$stock_out_info['source_number']])->find();

        /**查询入库单信息*/
        $stock_in_info = $this->getModel('ErpStockIn')->where(['retail_inner_order_number'=>$stock_out_info['retail_inner_order_number'],'batch_id'=>$stock_out_info['batch_id']])->find();
        if (empty($stock_in_info)) {
            $stock_in_info = $this->getModel('ErpStockIn')->where(['retail_inner_order_number'=>$stock_out_info['retail_inner_order_number'],'actual_storage_num'=>$stock_out_info['actual_outbound_num']])->find();
        }

        /**查询采购单信息*/
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->where(['order_number'=>$stock_in_info['source_number']])->find();

        /**红冲入库单及入库方库存*/
        //新增红冲入库单
        $stock_in_info = $this->getModel('ErpStockIn')->where(['storage_code'=>$stock_in_info['storage_code']])->find();
        $stock_in_data = [
            'storage_type'              => 1,
            'storage_code'              => erpCodeNumber(8,'',$stock_in_info['our_company_id'])['order_number'],
            'storage_status'            => 10,
            'source_number'             => $stock_in_info['source_number'],
            'source_object_id'          => $stock_in_info['source_object_id'],
            'goods_id'                  => $stock_in_info['goods_id'],
            'our_company_id'            => $stock_in_info['our_company_id'],
            'storage_num'               => $stock_in_info['storage_num'] * -1,
            'actual_storage_num'        => $stock_in_info['actual_storage_num'] * -1,
            'outbound_density'          => $stock_in_info['outbound_density'],
            'create_time'               => currentTime(),
            'dealer_id'                 => $this->getUserInfo('id'),
            'dealer_name'               => $this->getUserInfo('dealer_name'),
            'creater_id'                => empty($this->getUserInfo('id')) ? '' : $this->getUserInfo('id'),
            'auditor_id'                => $this->getUserInfo('id'),
            'audit_time'                => currentTime(),
            'storehouse_id'             => $stock_in_info['storehouse_id'],
            'is_reverse'                => 1,
            'reverse_source'            => $stock_in_info['storage_code'],
            'price'                     => $stock_in_info['price'],
            'actual_storage_num_litre'  => $stock_in_info['actual_storage_num_litre'],
            'balance_num'               => $stock_in_info['balance_num'],
            'balance_num_litre'         => $stock_in_info['balance_num_litre'],
            'stock_type'                => $stock_in_info['stock_type'],
            'region'                    => $stock_in_info['region'],
            'batch_sys_bn'              => $stock_in_info['batch_sys_bn'],
            'batch_id'                  => $stock_in_info['batch_id'],
            'cargo_bn_id'               => $stock_in_info['cargo_bn_id'],
            'storage_remark'            => '因调拨红冲产生出入库单红冲，调拨对应入库单号：'.$from_storage_code,
            'retail_inner_order_number' => $stock_in_info['retail_inner_order_number'],
        ];

        $status_stockin = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
        if ($status_stockin === false) {
            $message = '新增红冲入库单操作失败失败';
        }

        //审核通过，增加操作日志
        $status_stockin_log = $this->getEvent('ErpStock')->addStockOptionLog($stock_in_info['storage_code'], 2, 3);
        if ($status_stockin_log === false) {
            $message = '新增入库单日志操作失败';
        }

        //修改原入库记录的红冲状态
        $old_stock_in_data = [
            'reversed_status' => 1,
            'update_time' => currentTime(),
        ];
        $old_status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id' => $stock_in_info['id']],$old_stock_in_data);
        if ($old_status_stockin === false) {
            $message = '修改原入库单红冲状态操作失败';
        }

        //确定该订单影响哪个库存，并查出该库存的信息
        $stock_where = [
            'goods_id' => $stock_in_info['goods_id'],
            'object_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
            'our_company_id' => $stock_in_info['our_company_id'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        //获取入库单改变物理库存之前的物理库存
        $beforeCostNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];

        //组装库存表的字段值
        $data = [
            'goods_id' => $stock_in_info['goods_id'],
            'object_id' => $stock_in_info['storehouse_id'],
            'stock_type' => $stock_in_info['stock_type'],
            'region' => $stock_in_info['region'],
            'our_company_id' => $stock_in_info['our_company_id'],
            'stock_num' => $stock_info['stock_num'] + $stock_in_data['actual_storage_num'],
        ];
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_in_data['storage_code'],
            'object_type' => 4,
            'log_type' => 12,
            'our_company_id' => $stock_in_info['our_company_id'],
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_in_data['actual_storage_num'], $orders,false);
        if ($in_stock_status === false) {
            $message = '入库方库存变动操作失败';
        }

        //更新批次数据
        $batch_data = [
            'batch_id' => $stock_in_info['batch_id'],
            'change_balance_num' => plusConvert($stock_in_info['actual_storage_num']),
            'change_reserve_num' => 0,
            'change_type' => 4,
            'change_number' => $stock_in_info['storage_code'],
        ];
        $in_batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);
        if ($in_batch_result['status'] != 1) {
            $message = '入库方批次变动操作失败';
        }

        /**处理入库方成本*/
        $stock_in_data['before_stock_num'] = $beforeCostNum;
        $stock_in_data['stock_id'] = $stockId ? $stockId : 0;
        $stock_in_data['change_num'] = $stock_in_data['actual_storage_num'];
        updateStockInCost($stock_in_data);

        /**根据入库单和采购单数量对比来处理采购单的状态*/
        $where = [
            'id'            => $purchase_order_info['id'],
            'order_number'  => $purchase_order_info['order_number'],
        ];
        $purchase_data = [
            'storage_quantity' => $purchase_order_info['storage_quantity'] - $stock_in_info['actual_storage_num'],
            'update_time'       => currentTime(),
        ];

        $purchase_status = $this->getModel('ErpPurchaseOrder')->where($where)->save($purchase_data);
        if ($purchase_status === false) {
            $message = '采购单变动操作失败';
        }

        /**红冲出库单及出库方库存*/
        $new_stock_out_data = [
            'outbound_code'         => erpCodeNumber(7,'',$stock_out_info['our_company_id'])['order_number'],
            'outbound_type'         => 1,
            'outbound_status'       => 10,
            'outbound_remark'       => '',
            'source_number'         => $stock_out_info['source_number'],
            'source_object_id'      => $stock_out_info['source_object_id'],
            'our_company_id'        => $stock_out_info['our_company_id'],
            'goods_id'              => $stock_out_info['goods_id'],
            'depot_id'              => $stock_out_info['depot_id'],
            'outbound_num'          => plusConvert($stock_out_info['outbound_num']),
            'actual_outbound_num'   => plusConvert($stock_out_info['actual_outbound_num']),
            'create_time'           => currentTime(),
            'creater_id'            => $this->getUserInfo('id'),
            'creater_name'          => $this->getUserInfo('dealer_name'),
            'auditor_id'            => $this->getUserInfo('id'),
            'audit_time'            => currentTime(),
            'dealer_id'             => $this->getUserInfo('id'),
            'dealer_name'           => $this->getUserInfo('dealer_name'),
            'storehouse_id'         => $stock_out_info['storehouse_id'],
            'stock_type'            => $stock_out_info['stock_type'],
            'region'                => $stock_out_info['region'],
            'is_reverse'            => 1,
            'reverse_source'        => $stock_out_info['outbound_code'],
            'batch_sys_bn'          => $stock_out_info['batch_sys_bn'],
            'batch_id'              => $stock_out_info['batch_id'],
            'cost'                  => $stock_out_info['cost'],
            'outbound_remark'       => '因调拨红冲产生出入库单红冲，调拨对应入库单号：'.$from_storage_code,
            'retail_inner_order_number' => $stock_in_info['retail_inner_order_number'],
        ];

        $new_stock_out_status = $this->getModel('ErpStockOut')->addStockOut($new_stock_out_data);
        if ($new_stock_out_status === false) {
            $message = '新增红冲出库单操作失败';
        }
        //----------end 出库单处理--------------------------------------------------------------------------

        //修改原出库单状态
        $old_stock_out_data = [
            'reversed_status' => 1,
        ];
        $old_stock_out_status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$stock_out_info['id'],'outbound_code'=>$stock_out_info['outbound_code']],$old_stock_out_data);
        if ($old_stock_out_status === false) {
            $message = '修改原出库单操作失败';
        }

        //-----保存操作到出库单日志-----
        $erp_stock_option_log = [
            'order_type'    => 1,                                         # 1 出库单  2 入库单
            'order_number'  => trim($stock_out_info['outbound_code']),     # 单据号
            'log_type'      => 2,                                         # 操作类型 1 审核  2 取消审核
            'operator_id'   => session('erp_adminInfo')['id'],
            'operator'      => session('erp_adminInfo')['dealer_name'],
            'create_time'   => date('Y-m-d H:i:s',time())
        ];
        $add_log = M('erpStockOptionLog')->add($erp_stock_option_log);
        if ($add_log === false) {
            $message = '新增出库单日志操作失败';
        }

        //出库方库存变动处理----------------------------------------------------------------------------------------
        $stock_where = [
            'goods_id' => $stock_out_info['goods_id'],
            'object_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['stock_type'],
            'our_company_id' => $stock_out_info['our_company_id'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];

        $data = [
            'goods_id' => $stock_out_info['goods_id'],
            'object_id' => $stock_out_info['storehouse_id'],
            'stock_type' => $stock_out_info['is_agent'] == 1 ? 2 : $stock_out_info['stock_type'],
            'region' => $stock_out_info['region'],
            'our_company_id' => $stock_out_info['our_company_id'],
        ];

        $stock_info['stock_num'] = $data['stock_num'] = $stock_info['stock_num'] + $stock_out_info['actual_outbound_num'];
        //------------------------------------------------------------------------------------------------------
        //重新计算可用库存数量----------------------------------------------------------------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $new_stock_out_data['outbound_code'],
            'object_type' => 3,
            'log_type' => 12,
            'our_company_id' => $stock_out_info['our_company_id'],
        ];
        $out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $new_stock_out_data['actual_outbound_num'], $orders,false);
        if ($out_stock_status === false) {
            $message = '出库方库存变动操作失败';
        }
        //end 库存变动------------------------------------------------------------------------------------------

        //更新批次数据
        $batch_data = [
            'batch_id' => $stock_out_info['batch_id'],
            'change_balance_num' => $stock_out_info['actual_outbound_num'],
            'change_reserve_num' => 0,
            'change_type' => 5,
            'change_number' => $stock_out_info['outbound_code'],
        ];
        $out_batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);
        if ($out_batch_result['status'] != 1) {
            $message = '出库方批次变动操作失败';
        }

        /**处理出库方成本*/
        $new_stock_out_data['before_stock_num'] = $beforeNum;
        $new_stock_out_data['stock_id'] = $stockId ? $stockId : 0;
        $new_stock_out_data['change_num'] = abs($new_stock_out_data['actual_outbound_num']);
        updateStockInCost($new_stock_out_data);

        /**归还出库单使用的入库单可用*/
        $stock_in_dedcution_status = $this->returnToStockIn('',$stock_out_info);

        /**根据出库单和销售单数量对比来处理销售单的状态*/
        $where = [
            'id'            => $sale_order_info['id'],
            'order_number'  => $sale_order_info['order_number'],
        ];
        $sale_data = [
            'outbound_quantity' => $sale_order_info['outbound_quantity'] - $stock_out_info['actual_outbound_num'],
            'update_time'       => currentTime(),
        ];
        $sale_status = $this->getModel('ErpSaleOrder')->where($where)->save($sale_data);
        if ($sale_status === false) {
            $message = '销售单修改操作失败';
        }
        if ($status_stockin && $status_stockin_log && $old_status_stockin !== false && $in_stock_status &&
            $in_batch_result['status'] == 1 && $purchase_status && $new_stock_out_status && $old_stock_out_status && $add_log &&
            $out_stock_status && $out_batch_result['status'] == 1 && $stock_in_dedcution_status['status'] && $sale_status) {
            return ['status'=>1,'message'=>$message];
        } else {
            return ['status'=>0,'message'=>$message];
        }
    }
}

