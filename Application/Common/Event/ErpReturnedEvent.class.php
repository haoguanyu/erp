<?php
/**
 * 退货单业务处理层
 * @author xiaowen
 * @time 2017-04-17
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpReturnedEvent extends BaseController
{

    /**---------------------------------------------------------------------//
     *
     * 采购退货单模块
     *
     **----------------------------------------------------------------------*/
    /**************************************
        @ Content 红冲采退单
        @ Author YF
        @ Time 2018-12-25
    ***************************************/
    public function redRushReverse( $id = 0 )
    {
        if ( $id == 0 ) {
            return ['status' => 2,'message' => '退货单ID有误！'];
        }
        (array)$returned_order_where = [
            'id'    => ['eq',trim($id)],
        ];
        (array)$returned_order_arr = $this->getModel('ErpReturnedOrder')->where($returned_order_where)->find();
        if ( !isset($returned_order_arr['order_number']) ) {
            return ['status' => 4,'message' => '未查询到退货单！'];
        }

        if ( $returned_order_arr['order_status'] != 10 ) {
            return ['status' => 5,'message' => '采退单 不是已确认状态，不能红冲！'];
        }

        $purchase_payment_where = [
            'purchase_order_number' => ['eq',$returned_order_arr['order_number']],
            'status'                => ['neq',2]
        ];
        $purchase_payment_arr = $this->getModel('ErpPurchasePayment')->where($purchase_payment_where)->field('id')->select();
        if ( count($purchase_payment_arr) == 1 ) {
            // if ( $purchase_payment_arr[0]['status'] != 10 ) {
            //     $this->getModel('ErpPurchasePayment')->where($purchase_payment_where)->save();
            // }
            return ['status' => 3 ,'message' => '应付管理未红冲！'];
        }
        /***********************************************
            @ Content 判断未取消的出库单 是否红冲
            @ Author YF
        ************************************************/      
        (array)$stock_out_where = [
            'source_number' => ['eq',$returned_order_arr['order_number']],
            'outbound_status' => ['neq',2]
        ];
        $stock_out_arr = $this->getModel('ErpStockOut')->where($stock_out_where)->field('id,reversed_status,is_reverse,outbound_code')->select();
        if ( $stock_out_arr ) {
            foreach ($stock_out_arr as $key => $value) {
                if ( $value['reversed_status'] != 1 && $value['is_reverse'] != 1 ) {
                    return ['status' => 3 ,'message' => '出库单未红冲！'];
                }
            }  
        }
        /***************** END *********************************/
        M()->startTrans();
        /*************************************
            Author YF
            更新 采购单的 是否退货字段
        **************************************/
        //更新采购单数据
        $purchase_order_data = [
            'is_returned' => 2,
            'update_time' => currentTime(),
            'updater'     => $this->getUserInfo('id'),
        ];
        $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['order_number' => $returned_order_arr['source_order_number']], $purchase_order_data);
        if ( !$purchase_order_status ) {
            M()->rollback();
            return ['status' => 7, 'message' => '采购单更新失败！'];
        }
        /*****************************************
                        END
        ******************************************/
        //更新采退单数据
        $purchase_return_order_data = [
            'order_status' => 2,
        ];
        $purchase_return_order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => $returned_order_arr['id']], $purchase_return_order_data);
        if ( $purchase_return_order_status ) {
            M()->commit();
            return ['status' => 1 ,'message' => '作废成功！'];
        }
        M()->rollback();
        return ['status' => 5, 'message' => '作废失败！'];
    }

    /*****************************************
        @ Content 验证批次列表数据 更改预留数量
        @ Author  YF
        @ Time    2019-02-28
        @ Param  重点提醒 $batch_str [string] => [
                批次ID - 退货数量, 批次ID - 退货数量，
                1 - 3 , 2 - 3,
        ]
    ******************************************/
    public function handleBatchList( $batch_str = '' ){
        if ( $batch_str == '' ) {
            return ['status' => 8 ,'message' => '字符串不能为空！'];
        }
        if ( !is_string($batch_str) ) {
            return ['status' => 2 ,'message' => '不是字符串！'];
        }
        /* ------- 去掉最后一个逗号 ------------ */
        $param1 = substr($batch_str,0,strlen($batch_str)-1);
        /* ------- 分割成为数组 ------------ */
        $arr = explode(',',$param1);
        $batch_arr_ = [];
        foreach ($arr as $key => $value) {
            $batch_arr = explode('-', $value);
            $batch_arr_[$key]['batch_id'] = $batch_arr[0];
            $batch_arr_[$key]['batch_num'] = $batch_arr[1];
        }
        $batch_ids = array_column($batch_arr_,'batch_id');
        $batch_where['id'] = ['in',$batch_ids];
        $batch_data = $this->getModel('ErpBatch')->where($batch_where)->getField('id,balance_num,sys_bn');
        /* - 出库单数据 - */
        $stock_out = [];
        $update_result = true;
        foreach ($batch_arr_ as $key => $value) {
            if ( isset($batch_data[$value['batch_id']]) ) {
                if ( $batch_data[$value['batch_id']]['balance_num'] < setNum($value['batch_num']) ) {
                    return ['status' => 4, 'message' => '批次ID:'.$value['batch_id'].'退货数量不能大于可退数量！'];
                }
            } else {
                return ['status' => 3 ,'message' => '批次ID:'.$value['batch_id'].'非法请求！'];
            }

            if ( $value['batch_num'] != 0 ) {
                $stock_out_order_number = erpCodeNumber(7)['order_number'];
                /* ---------- 组装出库单数据 ------------------------------------ */
                $stock_out[$key]['batch_id']            = $value['batch_id'];
                $stock_out[$key]['batch_sys_bn']        = $batch_data[$value['batch_id']]['sys_bn'];
                $stock_out[$key]['outbound_num']        = setNum($value['batch_num']);
                $stock_out[$key]['actual_outbound_num'] = setNum($value['batch_num']);
                $stock_out[$key]['outbound_code']       = $stock_out_order_number;
                $stock_out[$key]['outbound_remark']     = '';
                /* -------------- END ----------------------------------------- */

                $batch_change_data = [
                    'batch_id'           => $value['batch_id'],
                    'change_reserve_num' => setNum($value['batch_num']),
                    'change_type'        => 2,
                    'change_number'      => $stock_out_order_number,
                ];
                $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
                if ( $batch_result['status'] != 1 ) {
                    //$update_result = false;
                    return $batch_result;
                }
            }
        }
        if ( $update_result && !empty($stock_out) ) {
            return ['status' => 1,'message' => '处理成功！','data' => $stock_out];
        }
        return ['status' => 5, 'message' => '处理失败！'];
    }


    /**
     * 采退单损耗处理
     * @author Yf
     * @param $param
     * @time 2019-6-05
     * @return array $purchase_order
     */
    public function lossWhereByPurchaseReturn( $purchase_order ){
        /*------------ 损耗处理 ---------------*/
        $stock_where = [
            'source_number'  => ['eq',$purchase_order],
            'storage_status' => ['neq',2],
        ];
        $stock_in_arr = $this->getModel('ErpStockIn')->where($stock_where)->field('storage_code')->select();
        $storage_code = array_column($stock_in_arr,'storage_code');
        $loss_where = [
            'source_number' => ['in',$storage_code],
            'order_status'  => ['neq',2],
        ];
        $loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->field('loss_num')->select();
        $loss_num = 0;
        if ( !empty($loss_arr) ) {
            $loss_num = getNum(array_sum(array_column($loss_arr, 'loss_num')));
        }
        return $loss_num;
    }

    /**
     * 新增采退单
     * @author xiaowen
     * @param $param
     * @time 2017-8-22
     * @return array $status
     */
    public function addPurchaseReturn($param){
        // cancelCacheLock('ErpReturned/addPurchaseReturn');
        if(getCacheLock('ErpReturned/addPurchaseReturn'))  return ['status' => 99, 'message' => $this->running_msg];
        if(!intval($param['source_order_id'])){
            $result = ['status' => 2, 'message' => '来源订单ID有误'];
        }else if(!trim($param['source_order_number'])){
            $result = ['status' => 3, 'message' => '来源订单号有误'];
        }else if(!trim($param['return_goods_time'])){
            $result = ['status' => 4, 'message' => '退货时间不能为空'];
        }else if(!trim($param['return_goods_num'])){
            $result = ['status' => 5, 'message' => '退货数量不能为空'];
        }else if(!trim($param['return_price'])){
            $result = ['status' => 6, 'message' => '退货单价不能为空'];
        }else if(!trim($param['refund_remark'])){
            $result = ['status' => 7, 'message' => '退款走向备注必填'];
        }else if(!trim($param['return_type'])){
            $result = ['status' => 8, 'message' => '请选择退货类型'];
        }else{
            log_info('参数：'. print_r($param, true));
            $purchase_order = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id'=>$param['source_order_id']]);
            $loss_num = $this->lossWhereByPurchaseReturn($purchase_order['order_number']);
            if(intval(setNum(trim($param['return_goods_num']))) > ($purchase_order['goods_num'] - setNum($loss_num)) ){
                return $result = ['status' => 9, 'message' => '退货数量不能大于待退数量'];
            }else if(setNum(trim($param['return_goods_num'])) < ($purchase_order['goods_num'] - $purchase_order['storage_quantity'] - setNum($loss_num))){
                return $result = ['status' => 10, 'message' => '退货数量不能小于在途数量'];
            }else if($purchase_order['order_status'] != 10 || $purchase_order['pay_status'] != 10 || $purchase_order['invoice_status'] != 1){
                return $result = ['status' => 11, 'message' => '订单必须同时满足已确认、已收款、未收票，才能进行退货操作'];
            }else if($purchase_order['type'] == 2){
                return $result = ['status' => 13, 'message' => '代采采购单请通过代采销售单进行退货操作'];
            }else if($purchase_order['is_returned'] == 1){
                return $result = ['status' => 14,
                    'message' => '一笔采购单只能创建一笔对应的采购退货单'
                    //'message' => '该采购单请已有退货单，无法再退货'
                ];
            }else if($purchase_order['storage_quantity'] == 0){
                return $result = ['status' => 15, 'message' => '该采购单未入库，请通过作废处理'];
            }
            //查询采购单是否有未审核的入库单
            $stock_in_count = $this->getModel('ErpStockIn')->where(['source_object_id'=>$param['source_order_id'], 'storage_type'=>1, 'storage_status'=>1])->count();
            if($stock_in_count){
                return $result = ['status' => 12, 'message' => '该订单存在未审核入库单，无法退货'];
            }

            //未审核的发票存在不能创建退货单
            $purchase_invoice = $this->getModel('ErpPurchaseInvoice')->where(['purchase_id'=>$param['source_order_id'], 'status'=>1])->count();
            if($purchase_invoice){
                return $result = ['status' => 13, 'message' => '该订单存在未审核发票，无法退货，请驳回后操作'];
            }
            setCacheLock('ErpReturned/addPurchaseReturn', 1);

            /* ----------- 开启事物 ------------ */
            M()->startTrans();
            /* ---------------- 只针对 在途 进行退货 ----------------- */
            $stock_out_one_arr = [];
            if ( sctonum($purchase_order['goods_num'] - setNum($loss_num) - $purchase_order['storage_quantity']) > 0 ) {
                /* ------------------ 组装出库单数据 ------------------------------------ */
                $stock_out_one_arr['batch_id']            = 0;
                $stock_out_one_arr['batch_sys_bn']        = '';
                // 在途数量
                $stock_out_one_arr['outbound_num']        = $purchase_order['goods_num'] - setNum($loss_num) - $purchase_order['storage_quantity'];
                // 实际出库数量
                $stock_out_one_arr['actual_outbound_num'] = 0;
                $stock_out_one_arr['outbound_code']       = erpCodeNumber(7)['order_number'];
                $stock_out_one_arr['outbound_remark']     = '在途退货';
            }

            /* ------------------ 如果有实际库存扣减(操作批次数据) ----------------*/
            if ( (int)setNum(trim($param['return_goods_num'])) != sctonum((int)($purchase_order['goods_num'] - setNum($loss_num) - $purchase_order['storage_quantity'])) ) {
                 /* ------------------- 处理批次数据 ----------------------- */
                $handle_batch_result = $this->handleBatchList($param['batch_str']);
                if ( $handle_batch_result['status'] != 1 ) {
                    cancelCacheLock('ErpReturned/addPurchaseReturn');
                    M()->rollback();
                    return $handle_batch_result;
                }
                $stock_out_data = $handle_batch_result['data'];
                /* ------------------------ END -------------------------- */
            }
            if ( !empty($stock_out_one_arr) ) {
                $stock_out_data[] = $stock_out_one_arr;
            }
            unset($param['batch_str']);
            /* ------------------- 整理添加采购退货单数据 ---------------------- */
            $param['order_number']       = erpCodeNumber(11)['order_number'];
            $param['return_price']       = setNum(trim($param['return_price']));
            $param['return_goods_num']   = setNum(trim($param['return_goods_num']));
            $param['remark']             = trim($param['remark']);
            $param['refund_remark']      = trim($param['refund_remark']);
            $param['order_status']       = 1;
            $param['region']             = $purchase_order['region'];
            $param['business_type']      = $purchase_order['business_type'];
            /* ------------------- 添加采购退货单 ---------------------- */
            $result = $this->savePurchaseReturn($param, 1);
            if ( $result['status'] != 1 ) {
                cancelCacheLock('ErpReturned/addPurchaseReturn');
                // 回滚事物
                M()->rollback();
                return $result;
            }
            /* -------------------- 整理出库单数据 ------------------------ */
            foreach ($stock_out_data as $key => $value) {
                $stock_out_data[$key]['outbound_type']      = '3';
                $stock_out_data[$key]['outbound_status']    = '1';
                $stock_out_data[$key]['source_number']      = $param['order_number'];
                $stock_out_data[$key]['source_object_id']   = $result['data']['returned_order_id'];
                $stock_out_data[$key]['our_company_id']     = session('erp_company_id');
                $stock_out_data[$key]['goods_id']           = $purchase_order['goods_id'];
                $stock_out_data[$key]['depot_id']           = $purchase_order['depot_id'];
                $stock_out_data[$key]['create_time']        = currentTime();
                $stock_out_data[$key]['creater_id']         = $this->getUserInfo('id');
                $stock_out_data[$key]['dealer_id']          = $this->getUserInfo('id');
                $stock_out_data[$key]['dealer_name']        = $this->getUserInfo('dealer_name');
                $stock_out_data[$key]['storehouse_id']      = $purchase_order['storehouse_id'];
                $stock_out_data[$key]['stock_type']         = $purchase_order['type'] == 1 ? getAllocationStockType($purchase_order['storehouse_id']) : 2;
                $stock_out_data[$key]['region']             = $purchase_order['region'];
            }
            $stock_out_status = $this->getModel('ErpStockOut')->addAll(array_values($stock_out_data));
            if ( !$stock_out_status ) {
                cancelCacheLock('ErpReturned/addPurchaseReturn');
                M()->rollback();
                return ['status' => 20, 'message' => '出库单创建失败！'];
            }
            M()->commit();
            cancelCacheLock('ErpReturned/addPurchaseReturn');
            return ['status' => 1 ,'message' => '操作成功！'];
        }
        return $result;
    }

    /**
     * 编辑采退单
     * @author xiaowen
     * @param $param
     * @time 2017-8-22
     * @return array $status
     */
    public function editPurchaseReturn($param){

        if(getCacheLock('ErpReturned/editPurchaseReturn'))  return ['status' => 99, 'message' => $this->running_msg];

        $returned_order_info = $this->getModel('ErpReturnedOrder')->field(true)->where(['id'=>intval($param['id'])])->find();
        if(!intval($param['id'])){
            $result = ['status' => 9, 'message' => '退货单ID有误'];
        }else if(empty($returned_order_info)){
            $result = ['status' => 10, 'message' => '退货单不存在'];
        }else if($returned_order_info['order_status'] != 1){
            $result = ['status' => 11, 'message' => '只有未审核退货单才能编辑'];
        }
//        else if(!intval($param['source_order_id'])){
//            $result = ['status' => 2, 'message' => '来源订单ID有误'];
//        }else if(!trim($param['source_order_number'])){
//            $result = ['status' => 3, 'message' => '来源订单号有误'];
//        }
        else if(!trim($param['return_goods_time'])){
            $result = ['status' => 4, 'message' => '退货时间不能为空'];
        }else if(!trim($param['return_goods_num'])){
            $result = ['status' => 5, 'message' => '退货数量不能为空'];
        }else if(!trim($param['return_price'])){
            $result = ['status' => 6, 'message' => '退货单价不能为空'];
        }else if(!($param['refund_remark'] = trim($param['refund_remark']))){
            $result = ['status' => 7, 'message' => '退款走向备注必填'];
        }else if(!trim($param['return_type'])){
            $result = ['status' => 8, 'message' => '请选择退货类型'];
        }else{
            $purchase_order = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id'=>$returned_order_info['source_order_id'], 'order_type'=>2]);
            if(setNum(trim($param['return_goods_num'])) > $purchase_order['goods_num']){
                return $result = ['status' => 9, 'message' => '退货数量不能大于待退数量'];
            }else if(setNum(trim($param['return_goods_num'])) < ($purchase_order['goods_num'] - $purchase_order['storage_quantity'])){
                return $result = ['status' => 10, 'message' => '退货数量不能小于在途数量'];
            }else if($purchase_order['order_status'] != 10 || $purchase_order['pay_status'] != 10 || $purchase_order['pay_status'] != 10){
                return $result = ['status' => 11, 'message' => '订单必须同时满足已确认、已收款、未收票，才能进行退货操作'];
            }
            setCacheLock('ErpReturned/editPurchaseReturn', 1);
            $param['return_price']       = setNum(trim($param['return_price']));
            $param['return_goods_num']   = setNum(trim($param['return_goods_num']));
            $param['remark']             = trim($param['remark']);
            $param['refund_remark']      = trim($param['refund_remark']);

            $param['return_order_id'] = $returned_order_info['id'];
            $param['return_order_number'] = $returned_order_info['order_number'];
            $result = $this->savePurchaseReturn($param, 2);
            cancelCacheLock('ErpReturned/editPurchaseReturn');
        }
        return $result;
    }

    /**
     * 新增/编辑采购退货单
     * @param $data
     * @param int $type
     * @return array $result
     */
    public function savePurchaseReturn($data, $type = 1){
        if($type == 1){
            log_info('数据：'. print_r($data, true));
            $result = $this->addPurchaseReturnOrder($data);
        }else{
            $result = $this->updatePurchaseReturnOrder($data);
        }
        return $result;
    }

    /**
     * 新增采购退货单方法
     * @param $data
     * @return array
     */
    public function addPurchaseReturnOrder($data)
    {
        // M()->startTrans();
        $data['order_time']         = currentTime('Y-m-d');
        $data['create_time']        = currentTime();
        $data['order_type']         = 2;
        $data['creater_id']         = $this->getUserInfo('id');
        $data['our_company_id']     = session('erp_company_id');
        $order_status = $returned_order_id = $this->getModel('ErpReturnedOrder')->addReturnedOrder($data);

        $log_data = [
            'return_order_id' => $returned_order_id,
            'return_order_number' => $data['order_number'],
            'return_order_type' => 2,
            'log_info' => serialize($data),
            'log_type' => 1,
        ];
        $log_status         = $this->addReturnedLog($log_data);

        $order_update_data  = [
            'is_returned'=>1, 'update_time'=>currentTime()
        ];
        $purchase_status    = $this->getModel('ErpPurchaseOrder')->where(['id'=>$data['source_order_id']])->save($order_update_data);

        if($order_status && $log_status && $purchase_status){
            // M()->commit();
            $result = [
                'status'    => 1,
                'message'   => '操作成功',
                'data'      => ['returned_order_id'=> $returned_order_id],
            ];
        }else{
            // M()->rollback();
            $result = [
                'status'=> 0,
                'message'=> '操作失败',
            ];
        }
        return $result;
    }

    /**
     * 新增采购退货单方法
     * @param $data
     * @return array
     */
    public function updatePurchaseReturnOrder($data){
        $data['update_time']        = currentTime();
        $data['updater_id']         = $this->getUserInfo('id');
        $data['our_company_id']     = session('erp_company_id');
        $return_order_id = $data['return_order_id'];
        $return_order_number = $data['return_order_number'];
        unset($data['return_order_id']);
        unset($data['return_order_number']);
        $order_status               = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id'=>$data['id']], $data);

        $log_data = [
            'return_order_id' => $return_order_id,
            'return_order_number' => $return_order_number,
            'return_order_type' => 2,
            'log_info' => serialize($data),
            'log_type' => 2,
        ];
        $log_status         = $this->addReturnedLog($log_data);

        M()->startTrans();
        if($order_status && $log_status){
            M()->commit();
            $result = [
                'status'=> 1,
                'message'=> '操作成功',
            ];
        }else{
            M()->rollback();
            $result = [
                'status'=> 0,
                'message'=> '操作失败',
            ];
        }
        return $result;
    }

    /**
     * 生成退货单操作日志
     * @author xiaowen
     * @time 2017-8-22
     * @param $log
     * @return bool $status
     */
    public function addReturnedLog($log){
        $log['create_time']= currentTime();
        $log['operator']= $this->getUserInfo('dealer_name');
        $log['operator_id']= $this->getUserInfo('id');
        $status = $this->getModel('ErpReturnedOrderLog')->add($log);
        return $status;
    }

    /**
     * 采购退货单列表
     * @author xiaowen 2017-8-23
     * @param $param
     */
    public function purchaseReturnOrderList($param = [])
    {
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {
                $where['ro.order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
                $where['ro.order_time'] = ['elt', trim($param['end_time'])];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {
                $where['ro.order_time'] = ['between', [trim($param['start_time']), trim($param['end_time'])]]; //
            }
        }

        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['o.region'] = ['in',$city_id];
        }
        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }
//        if (trim($param['region'])) {
//            $where['o.region'] = intval($param['region']);
//        }

        if (intval(trim($param['goods_id']))) {
            $where['o.goods_id'] = intval(trim($param['goods_id']));
        }

        if (trim($param['return_type'])) {
            $where['ro.return_type'] = intval(trim($param['return_type']));
        }

        if (trim($param['is_upload_voucher'])) {
            $where['ro.is_upload_voucher'] = intval(trim($param['is_upload_voucher']));
        }

        if (trim($param['depot_id'])) {

            $where['o.depot_id'] = intval($param['depot_id']);
        }
        if (trim($param['storehouse_id'])) {
            $where['o.storehouse_id'] = intval($param['storehouse_id']);
        }
        if (trim($param['dealer_name'])) {
            $where['o.buyer_dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        if (trim($param['sale_user'])) {
            //$user_ids = D("User")->where(['user_name' => ['like', '%' . trim($param['sale_user']) . '%'], 'is_available' => 0])->getField('id', true);
//            if($param['type'] == 1){
//            }else if($param['type'] == 2){
//            }
            $user_ids = $this->getEvent('ErpSupplier')->getSupplierUserDataField(['user_name' => ['like', '%' . trim($param['sale_user']) . '%']],'id,user_name');

            $user_ids = array_keys($user_ids);
            if ($user_ids) {
                $where['o.sale_user_id'] = ['in', $user_ids];
            } else {
                $data['recordsFiltered'] = $data['recordsTotal'] = 0;
                $data['data'] = [];
                $data['draw'] = $_REQUEST['draw'];
                return $data;
            }

        }
        if (trim($param['sale_company_id'])) {
            $where['o.sale_company_id'] = intval(trim($param['sale_company_id']));
        }

        if ($param['status']) {
            $where['ro.order_status'] = intval($param['status']);
        }

        if (trim($param['order_number'])) {
            $where['ro.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        if (trim($param['source_order_number'])) {
            $where['ro.source_order_number'] = ['like', '%' . trim($param['source_order_number']) . '%'];
        }
        if (trim($param['is_void']) == 1) {
            $where['o.is_void'] = trim($param['is_void']);
        }
        if(trim($param['business_type'])){
            $where['ro.business_type'] = intval($param['business_type']);
        }
        //我的采购退货单
        if (trim($param['dealer_id'])) {
            //$where['o.buyer_dealer_id'] = trim($param['dealer_id']);
            $where['ro.creater_id'] = trim($param['dealer_id']);
        }

        //当前登陆选择的我方公司
        $where['ro.our_company_id'] = session('erp_company_id');
        $where['ro.order_type'] = 2;
        $field = 'ro.*,o.region,o.buyer_dealer_name,o.sale_company_id,o.sale_user_id,o.depot_id,o.depot_id, d.depot_name,g.goods_code,
        g.goods_name,g.source_from,g.grade,g.level,s.storehouse_name';
        if(isset($param['export']) && $param['export']){

            //查询批发采购退货单
            $data = $this->getModel('ErpReturnedOrder')->getAllReturnedOrderList($where,$field);

        }else{
            //查询批发采购退货单
            $data = $this->getModel('ErpReturnedOrder')->getReturnedOrderList($where,$field, $_REQUEST['start'], $_REQUEST['length']);
        }

        if ($data['data']) {

            $cityArr = provinceCityZone()['city'];
            $creater_ids = array_unique(array_column($data['data'], 'creater_id'));
            $createrArr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', $creater_ids]])->getField('id, dealer_name,dealer_email');

            $user_ids = array_column($data['data'], 'sale_user_id');
            $company_ids = array_column($data['data'], 'sale_company_id');
            $userInfo = $this->getEvent('ErpCommon')->getUserData($user_ids, 2);
            $companyInfo = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 2);

            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }

                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                //$data['data'][$key]['order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['order_status'] = returnedOrderStatus($value['order_status'], true);
                $data['data'][$key]['return_amount_status'] = returnedAmountStatus($value['return_amount_status'], true);
                $data['data'][$key]['is_upload_voucher'] = $value['voucher'] ? '是' : '否';
                $data['data'][$key]['return_price'] = $value['return_price'] > 0 ? round(getNum($value['return_price']), 2) : '0.00';
                $data['data'][$key]['order_amount'] = round(getNum($value['return_price']) * getNum($value['return_goods_num']), 2);
                $data['data'][$key]['return_goods_num'] = round(getNum($value['return_goods_num']), 4);
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];
                $data['data'][$key]['s_company_name'] = $value['sale_company_id'] == 99999 ? '不限' : $data['data'][$key]['s_company_name'];
                $data['data'][$key]['creater_name'] = $createrArr[$value['creater_id']]['dealer_name'];
                $data['data'][$key]['storehouse_name'] =$data['data'][$key]['storehouse_name'];
                $data['data'][$key]['return_type'] = returnType($value['return_type']);
                $data['data'][$key]['business_type']        = $value['business_type'] > 0 ?  getBusinessType($value['business_type']) : '--';


                $data['data'][$key]['s_company_name'] = $companyInfo[$value['sale_company_id']]['company_name'];
                $data['data'][$key]['s_user_name'] = $userInfo[$value['sale_user_id']]['user_name'];
                $data['data'][$key]['s_user_phone'] = $userInfo[$value['sale_user_id']]['user_phone'];

                //-----------------------------------------------------------------------------------------------------------------------------------

            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }
    /**
     * 删除采购退货单
     * @author xiaowen
     * @param $id
     * @return array
     */
    public function delReturnedPurchase($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
            if (!in_array($order_info['order_status'],[1, 3, 4])) {
                $result = [
                    'status' => 2,
                    'message' => '只允许【未审核\已审核\已复核】单据进行删除',
                ];
                return $result;
            }
            /* -------------- 验证所对应的出库单 ---------------------- */
            $stock_out_where['source_number'] = ['eq',$order_info['order_number']];
            $stock_out_where['outbound_status'] = ['neq',2];
            $stock_out_arr = $this->getModel('ErpStockOut')->where($stock_out_where)->field('outbound_status')->find();
            if (!empty($stock_out_arr)){
                return ['status' => 13 , 'message' => '请先取消所对应的出库单！'];
            }
            /* ------------------- END-------------------------- */
            else {
                if (getCacheLock('ErpReturned/delReturnedPurchase')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpReturned/delReturnedPurchase', 1);
                M()->startTrans();
                $data = [
                    'order_status' => 2,
                    'update_time' => currentTime(),
                    'updater_id' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $data);

                $log = [
                    'return_order_id' => $order_info['id'],
                    'return_order_number' => $order_info['order_number'],
                    'return_order_type' => 2,
                    'log_info' => serialize($order_info),
                    'log_type' => 3,
                ];
                $log_status = $this->addReturnedLog($log);

                //删除审批流的流程
                $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id,'workflow_type'=> '5', 'status'=>['neq', 2]])->order('id desc')->find();

                if ($workflow) {
                    $workflow['status'] = 2;
                    $status_work = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
                } else {
                    $status_work = true;
                }

                $order_data = [
                    'update_time'=>currentTime(),
                    'is_returned'=>2,
                    'returned_goods_num' => 0,
                ];
                $order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id'=>$order_info['source_order_id']], $order_data);

                if ($status && $log_status && $status_work && $order_status) {
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

                cancelCacheLock('ErpReturned/delReturnedPurchase');
            }

            return $result;

        }
    }

    /**
     * 审核采购退货单
     * @param $id
     * @author xiaowen
     * @time 2017-9-4
     * @return array
     */
    public function auditReturnedPurchase($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrderJoinOrder(['ro.id' => $id, 'ro.order_type'=>2], 'ro.* ,o.region,o.sale_company_id');
            if ($order_info['order_status'] != 1) {
                $result = [
                    'status' => 2,
                    'message' => '该采购退货单不是未审核状态，无法审核',
                ];
            }  else {
                if (getCacheLock('ErpReturned/auditReturnedPurchase')) return ['status' => 99, 'message' => $this->running_msg];
                /*************************************
                    # Content 如果采购退货单 没有对应的 未审核出库单 
                    那么采购退货单 不允许审核
                    @ Author YF
                **************************************/
                $stock_out_arr_where['source_object_id'] = ['eq',$order_info['id']];
                $stock_out_arr_where['outbound_status']  = ['eq',1];// 1 代表未审核
                $stock_out_arr = $this->getModel('ErpStockOut')->where($stock_out_arr_where)->field('id')->find();
                if ( !isset($stock_out_arr['id']) ) {
                    return ['status' => 11 ,'message' => '采购退货单没有对应未审核出库单，无法审核！'];
                }
                /**************** END **********************/
                # -------------------------判断公司账套之间 ， 不走审批----------------------------
                # qianbin
                # 2017.11.06
                //$company_id = getErpCompanyList('company_id');
                //判断供应商公司是否为内部公司，判断是否走审核流 edit xiaowen 2019-3-28
                $order_info['company_name'] = $this->getModel('ErpSupplier')->where(['id'=>$order_info['sale_company_id']])->getField('supplier_name');
                //if(in_array(intval($order_info['sale_company_id']),$company_id)){
                if(checkInErpCompany($order_info['company_name'])){
                    $result = $this->getEvent('ErpWorkFlow')->updateErpWorkFlowOrderStatus(5,intval($order_info['id']),4);
                    log_info('--------->公司账套之间 ， 不走审批!');
                    if($result){
                        $result = ['status' => 1 , 'message' => '操作成功！'];
                    }else{
                        $result = ['status' => 222 , 'message' => '操作失败！'];
                    }
                    return $result;
                }
                setCacheLock('ErpReturned/auditReturnedPurchase', 1);

                M()->startTrans();
                $data = [
                    'order_status' => 3,
                    'update_time' => currentTime(),
                    'updater_id' => $this->getUserInfo('id'),
                ];

                //生成采购退货单审批流程
                //$workflow_result = $this->createWorkflow($order_info);
                $workflow_result = $this->createWorkflowNew($order_info);
                if($workflow_result['check_order'] == 1){
                    $data['order_status'] = 4;
                }

                $status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $data);

                $log = [
                    'return_order_id' => $order_info['id'],
                    'return_order_number' => $order_info['order_number'],
                    'return_order_type' => 2,
                    'log_info' => serialize($order_info),
                    'log_type' => 4,
                ];

                $log_status = $this->addReturnedLog($log);

                if ($workflow_result['status'] && $status && $log_status) {

                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                    ];
                } else {
                    M()->rollback();
                    $result = [
                        'status' => 0,
                        'message' => $workflow_result['message'] ? $workflow_result['message'] :  '操作失败',

                    ];
                }

                cancelCacheLock('ErpReturned/auditReturnedPurchase');
            }

        } else {
            $result = [
                'status' => 0,
                'message' => '参数有误，请选择采购退货单',
            ];
        }
        return $result;
    }

    /**
     * 确认采购退货单
     * @param id
     * @return array $result
     * @author xiaowen
     * @time 2017-8-30
     */
    public function confirmReturnedPurchase($id)
    {

        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误，无法获取采购退货单ID',
            ];
        } else {
            $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrder(['ro.id' => $id, 'ro.order_type'=>2],'ro.*, o.goods_id, o.depot_id, o.storage_quantity, o.goods_num, o.storehouse_id, o.type, o.region');
            //获取采购单已入库数量
            if ($order_info['order_status'] != 4) {
                $result = [
                    'status' => 2,
                    'message' => '该采购退货单不是已复核状态，请稍后操作',
                ];
            }
            else {
                if (getCacheLock('ErpReturned/confirmReturnedPurchase')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpReturned/confirmReturnedPurchase', 1);
                M()->startTrans();

                $data = [
                    'order_status' => 10,
                    'update_time' => currentTime(),
                    'updater_id' => $this->getUserInfo('id'),
                ];

                $status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $data);

                $log = [
                    'return_order_id' => $order_info['id'],
                    'return_order_number' => $order_info['order_number'],
                    'return_order_type' => 2,
                    'log_info' => serialize($order_info),
                    'log_type' => 6,
                ];
                $log_status = $this->addReturnedLog($log);

                //----------------------生成采退出库单-------------------------------------
                // $stock_out_data = [
                //     'outbound_code' => erpCodeNumber(7)['order_number'],
                //     'outbound_type' => '3',
                //     'outbound_status' => '1',
                //     'outbound_remark' => '',
                //     'source_number' => $order_info['order_number'],
                //     'source_object_id' => $order_info['id'],
                //     'our_company_id' => session('erp_company_id'),
                //     'goods_id' => $order_info['goods_id'],
                //     'depot_id' => $order_info['depot_id'],
                //     'outbound_num' => $order_info['return_goods_num'],
                //     //'actual_outbound_num' => $order_info['return_goods_num'],
                //     //实际出库数量 = 采购退货数量 - 在途数量
                //     'actual_outbound_num' => $order_info['return_goods_num'] - ($order_info['goods_num'] - $order_info['storage_quantity']),
                //     'create_time' => currentTime(),
                //     'creater_id' => $this->getUserInfo('id'),
                //     'dealer_id' => $this->getUserInfo('id'),
                //     'dealer_name' => $this->getUserInfo('dealer_name'),
                //     'storehouse_id' => $order_info['storehouse_id'],
                //     'stock_type' => $order_info['type'] == 1 ? getAllocationStockType($order_info['storehouse_id']) : 2,
                //     'region' => $order_info['region'],
                // ];
                // $stock_out_status = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);

                //----------------------end生成采退出库单----------------------------------
                // if ($status && $log_status && $stock_out_status) {

                if ($status && $log_status) {
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

            }

            cancelCacheLock('ErpReturned/confirmReturnedPurchase');
        }

        return $result;

    }

    /**
     * 上传退货凭证
     * @param $id
     * @param array $attach
     * @return array
     * @author xiaowen
     * @time 2017-8-23
     */
    public function uploadVoucher($id, $attach = [])
    {

        if ($id && $attach) {

            if (count($attach) > 1) {
                return $result = [
                    'status' => 4,
                    'message' => '对不起，同时只能上传一份凭证',

                ];
            }

            $OrderInfo = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
            if (in_array($OrderInfo['order_status'], [2])) {
                $result = [
                    'status' => 2,
                    'message' => '该退货单已取消，无法上传凭证',

                ];
            } else {
                if (getCacheLock('ErpReturned/uploadVoucher')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpReturned/uploadVoucher', 1);
                M()->startTrans();
                $data = [
                    'voucher' => $attach[0],
                    'update_time' => currentTime(),
                    'updater_id' => $this->getUserInfo('id'),
                    'is_upload_voucher' => 1,
                ];
                $status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $data);

                $log_data = [
                    'return_order_id' => $OrderInfo['id'],
                    'return_order_number' => $OrderInfo['order_number'],
                    'return_order_type' => $OrderInfo['order_type'],
                    'log_info' => serialize($OrderInfo),
                    'log_type' => 7,
                ];
                $log_status = $this->addReturnedLog($log_data);

                if ($status && $log_status) {
                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                    ];
                } else {
                    M()->rollback();
                    $result = [
                        'status' => 2,
                        'message' => '操作失败',
                    ];
                }
                cancelCacheLock('ErpReturned/uploadVoucher');
            }

        } else {
            $result = [
                'status' => 0,
                'message' => '参数有误',
            ];
        }
        return $result;
    }

    /**
     * 生成退货单付款申请
     * @param $param
     */
    public function applicationPayment($param){
        if (getCacheLock('ErpReturned/applicationPayment')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReturned/applicationPayment', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $all_pay_money = $this->getModel('ErpPurchasePayment')->field('sum(pay_money) as total')->where(['purchase_id' => intval($param['purchase_id']), 'status' => ['neq', 2], 'source_order_type'=>2])->group('purchase_id')->find();
            $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrder(['ro.id' => intval($param['purchase_id']), 'ro.order_type'=>2], 'ro.*, o.sale_company_id, o.buyer_dealer_id, o.order_number as from_purchase_order_number');
            $order_info['order_amount'] = setNum(round(getNum($order_info['return_goods_num']) * getNum($order_info['return_price']),2));
            log_info('退款总金额：' . $order_info['order_amount']);
            log_info('申请退款金额：' . setNum(trim($param['pay_money'])));
            $date = date('Y-m-d');
            if (trim($param['pay_money']) == "") {
                $result['status'] = 101;
                $result['message'] = "请输入申请金额";
            }elseif (trim($param['pay_money']) <= 0) {
                $result['status'] = 102;
                $result['message'] = "申请金额必须大于0";
            }
            elseif ($order_info['order_status'] != 10) {
                $result['status'] = 2;
                $result['message'] = "退货单状态不是已确认，无法申请付款";
            }elseif ($order_info['return_amount_status'] == 10) {
                $result['status'] = 3;
                $result['message'] = "退货单已退款，无法再申请付款";
            }
            elseif (intval(setNum(trim($param['pay_money']))) > $order_info['order_amount'] - $all_pay_money['total']) {
                $result['status'] = 101;
                $result['message'] = "已申请金额不能超出退款总金额";
            } elseif (trim($param['apply_pay_time']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择申请付款日期";
            } elseif (trim($param['apply_pay_time']) < $date) {
                $result['status'] = 101;
                $result['message'] = "申请付款日期必须为今天或今天之后";
            } 
            // else if(($this->getModel('ErpPurchaseOrder')->where(['id'=>$order_info['source_order_id']])->getField('returned_goods_num')) <= 0){
            //     $result['status'] = 103;
            //     $result['message'] = "请先将采退出库审核后，再申请退款";
            // }

            else {
                M()->startTrans();
                $erp_payment_data = [
                    'pay_money' => setNum($param['pay_money']),
                    'purchase_id' => intval($param['purchase_id']),
                    'purchase_order_number' => trim($param['purchase_order_number']),
                    'source_order_type' => 2, //付识为退款的付款申请
                    'create_time' => currentTime(),
                    'creator' => $this->getUserInfo('dealer_name'),
                    'creator_id' => $this->getUserInfo('id'),
                    'apply_pay_time' => $param['apply_pay_time'] . ' 23:59:59',
                    'status' => 1,
                    'remark' => $param['remark'],
                    'our_company_id' => $order_info['our_company_id'],
                    'sale_company_id' => $order_info['sale_company_id'],
                    'dealer_id' => $order_info['buyer_dealer_id'],
                    'from_purchase_order_number' => $order_info['from_purchase_order_number'],
                ];
                if (trim($param['id']) == "") {
                    //添加erp付款申请信息
                    $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);
                } else {
                    //编辑erp付款申请信息
                    $status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $param['id']], $erp_payment_data);
                }
                $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $param['purchase_id']]);
                $log_data = [
                    'return_order_id' => $param['purchase_id'],
                    'return_order_number' => $param['purchase_order_number'],
                    'return_order_type' => 2,
                    'log_type' => 8,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpReturnedOrderLog')->add($log_data);
                if ($status_payment && $status_log) {
                    M()->commit();
                    $result['status'] = 1;
                    $result['message'] = '申请成功';
                } else {
                    M()->rollback();
                    $result['status'] = 0;
                    $result['message'] = isset($err_message) ? $err_message : '操作失败';
                }
            }
        }
        cancelCacheLock('ErpReturned/applicationPayment');
        return $result;
    }

    /**
     * 返回采购退货单付款信息
     * @author xiaowen
     * @time 2017-9-7
     * @param $id
     * @return mixed
     */
    public function getReturnedPurchaseOrderInfo($id){
        $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
        $all_invoice_money = $this->getModel('ErpPurchaseInvoice')->field('sum(apply_invoice_money) as total')->where(['purchase_id' => $id, 'status' => ['neq', 2]])->group('purchase_id')->find();
        $all_pay_money = $this->getModel('ErpPurchasePayment')->field('sum(pay_money) as total')->where(['purchase_id' => $id, 'status' => ['neq', 2], 'source_order_type'=>2])->group('purchase_id')->find();
        $data['order_amount'] = round(getNum($order_info['return_goods_num'] * getNum($order_info['return_price'])), 2);
        $data['all_invoice_money'] = getNum($all_invoice_money['total']);
        $data['all_pay_money'] = getNum($all_pay_money['total']);
        return $data;
    }

    /**
     * 验证采购退货单是否可能申请退款
     * @param $id
     * @return array
     */
    public function returnedPurchaseCanPayment($id){
        $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
        if($order_info['order_status'] != 10){
            $result = [
                'status' => 2,
                'message' => '退货单不是已确认状态，无法申请退款',
            ];
        }else if($order_info['return_amount_status'] == 10){
            $result = [
                'status' => 3,
                'message' => '退货单已退款，无法再申请退款',
            ];
        }else{
            $stock_out_order = $this->getModel('ErpStockOut')->where(['source_number'=>trim($order_info['order_number']), 'outbound_type'=> 3])->find();
            if($stock_out_order['outbound_status'] != 10){
                $result = [
                    'status' => 4,
                    'message' => '采购退货单未完成出库审核，无法申请退款',
                ];
            }else{
                $result = [
                    'status' => 1,
                    'message' => '采购退货单可以申请退款',
                ];
            }
        }
        return $result;
    }

    /**
     * 插入退货单操作日志
     * @author xiaowen
     * @param $data
     * @return mixed
     */
    public function addReturnedOrderLog($data)
    {
        if ($data) {
            $data['create_time'] = currentTime();
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0;
            $data['operator_id'] = $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0;
            $status = $this->getModel('ErpReturnedOrderLog')->add($data);
        }
        return $status;
    }


    /**
     * 生成销售退货单审批步骤
     * @param array $order_info
     * @author xiaowen
     * @time 2017-05-09
     * @return bool $check_status
     */
    public function createWorkflow($order_info = []){
        $data['status']      = false; //审批流程创建状态
        $data['check_order'] = 0; //是否复核订单状态
        $data['message']     = '';
        if($order_info){
            switch (intval($order_info['order_type'])){
            # 销售退货单审批职位-----------------------------------------------------------------------------------------
                case 1:
                    $workflow_type = 4;
                    $workflow_step = returnedOrderWorkflowStep(1);
                break;
            # 采购退货单审批职位-----------------------------------------------------------------------------------------
                case 2:
                    $workflow_type = 5;
                    # 计算当天采购当前商品的退货量
                    $where_purchase_order = [
                        'our_buy_company_id'     => intval($order_info['our_buy_company_id']),     # 账套公司
                        'goods_id'               => intval($order_info['goods_id']) ,              # 商品id
                       // 'type'                   => intval($order_info['type']),                   # 采购类型：1 自采 ， 2 待采
                        //'storehouse_id'          => intval($order_info['storehouse_id']) ,         # 仓库id
                        'region'                 => intval($order_info['region']),               # 城市id
                        'order_status'           => ['neq',2],                                     # 未取消
                        'is_void'                => 2 ,                                            # 未作废
                        'create_time'            => ['between', [date('Y-m-d 00:00:00',time()), date('Y-m-d 23:59:59',time())]]
                    ];
                    $purchase_order_sum = D('ErpPurchaseOrder')->where($where_purchase_order)->sum('returned_goods_num');

                    # 数量 / 10000
                    $purchase_order_sum = empty($purchase_order_sum) ? 0 : ($purchase_order_sum < 0 ? '-'.abs(getNum($purchase_order_sum)) : getNum($purchase_order_sum));
                    $type               = $purchase_order_sum <= 100  ? 1 : 2 ;
                    $workflow_step      = returnedOrderWorkflowStep(2)[$type];
                    break;
                default:
                    $workflow_type   = 4;
                    $workflow_step   = [];
                    break;

            }
            # 交易员创建销退订单->分公司交易负责人审批->分公司负责人审批
            $workflow_data = [
                'workflow_type'         => $workflow_type,
                'workflow_order_number' => $order_info['order_number'],
                'workflow_order_id'     => $order_info['id'],
                'our_company_id'        => $order_info['our_company_id'],
                'creater'               => $this->getUserInfo('dealer_name'),
                'creater_id'            => $this->getUserInfo('id'),
            ];
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);
            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $order_info, $workflow_type);
            $data['status']  = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];
        }
        return $data;
    }

    /**---------------------------------------------------------------------//
     *
     * 销售退货单模块
     *
     **----------------------------------------------------------------------*/

    /**
     * 新增销售退货单
     * @param array $param
     * @author guanyu
     * @time 2017-08-21
     * @return array
     */
    public function addSaleReturn($param = [])
    {
        //参数验证
        if (empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (!strHtml($param['source_order_id'])) {
            $result = [
                'status' => 2,
                'message' => '来源订单id错误'
            ];
            return $result;
        }
        if (!strHtml($param['source_order_number'])) {
            $result = [
                'status' => 2,
                'message' => '来源订单号错误'
            ];
            return $result;
        }
        if (strHtml($param['return_goods_time']) == '') {
            $result = [
                'status' => 3,
                'message' => '请选择退货时间'
            ];
            return $result;
        }
        if (strHtml($param['return_goods_num']) == '') {
            $result = [
                'status' => 4,
                'message' => '请填写退货数量'
            ];
            return $result;
        }
        if (strHtml($param['return_type']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择退货类型'
            ];
            return $result;
        }
        if (strHtml($param['refund_remark']) == '') {
            $result = [
                'status' => 6,
                'message' => '请填写退款走向备注'
            ];
            return $result;
        }

        //单据验证
        $sale_order_info = $this->getModel('ErpSaleOrder')->where(['id'=>$param['source_order_id'],'order_number'=>$param['source_order_number'],'our_company_id'=>session('erp_company_id')])->find();

        $min_returned_num = $sale_order_info['buy_num'] - $sale_order_info['loss_num'] - $sale_order_info['returned_goods_num'] - $sale_order_info['outbound_quantity'];
        $max_returned_num = $sale_order_info['buy_num'] - $sale_order_info['loss_num'] - $sale_order_info['returned_goods_num'];

        if ($sale_order_info['outbound_quantity'] == 0) {
            $result = [
                'status' => 7,
                'message' => '没有出库记录的销售单不允许创建销退'
            ];
            return $result;
        }
        if (intval(round(setNum($param['return_goods_num']))) < intval($min_returned_num) || intval(round(setNum($param['return_goods_num']))) > intval($max_returned_num)) {
            $result = [
                'status' => 8,
                'message' => '退货数量不能超过可退货数量区间'
            ];
            return $result;
        }
        if ($sale_order_info['collection_status'] != 10 || $sale_order_info['invoice_status'] != 1) {
            $result = [
                'status' => 9,
                'message' => '销售退货单无法创建，销售单必须满足已收款，未开票，涉及开票或部分开票必须通知财务红冲发票，再进行创建'
            ];
            return $result;
        }
        if ($sale_order_info['is_returned'] == 1) {
            $result = [
                'status' => 10,
                'message' => '一笔销售单只能创建一笔对应的销售退货单'
            ];
            return $result;
        }

        //验证单据是否存在未处理的出库单
        $stock_out = $this->getModel('ErpStockOut')->where(['source_number'=>$param['source_order_number'],'source_object_id'=>$param['source_order_id'],'our_company_id'=>session('erp_company_id'),'outbound_status'=>1])->select();
        if ($stock_out) {
            $result = [
                'status' => 11,
                'message' => '该订单还有未处理的出库单，请取消相应的未审核出库单后再退货'
            ];
            return $result;
        }
        //销退订单数据处理
        $returnOrderOrder = erpCodeNumber(12)['order_number'] ;
        $data = [
            'order_number' => $returnOrderOrder,
            'order_type' => 1,
            'source_order_id' => $param['source_order_id'],
            'source_order_number' => $param['source_order_number'],
            'our_company_id' => session('erp_company_id'),
            'order_time' => $sale_order_info['add_order_time'],
            'return_goods_time' => $param['return_goods_time'],
            'return_goods_num' => setNum($param['return_goods_num']),
            'return_price' => $sale_order_info['price'],
            'return_type' => $param['return_type'],
            'order_status' => $param['order_status'] ? $param['order_status'] : 1,
            'remark' => $param['remark'],
            'refund_remark' => $param['refund_remark'],
            'create_time' => currentTime(),
            'creater_id' => $this->getUserInfo('id'),
            'region' => $sale_order_info['region'],
            'business_type' => $sale_order_info['business_type'],
        ];
        //销退新增log数据处理
        $log_data = [
            'return_order_number' => $data['order_number'],
            'return_order_type' => 1,
            'log_type' => 1,
            'log_info' => serialize($data),
        ];
        //修改来源单据退货数量数据处理
        $sale_order_data = [
            'is_returned' => 1,
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $stockInLists = [] ;//批次入库单数据
        $noStockInLists = [] ;//非批次入库单数据
        $batchIds = [] ;//批次编号
        $batchNums = 0 ;//批次退货数量
        $batch = [];//批次的信息
        if(isset($param['batch']) && !empty($param['batch']) && is_array($param['batch'])){//批次退货数据整理
            foreach ($param['batch'] as $batchKey=> $batchValue){
                if(is_array($batchValue) && !empty($batchValue)){
                   foreach ($batchValue as $k => $v){
                       $batch[$k] = $v ;
                       $batchIds[] = $k ;
                   }
                }
            }
            $batchWhere['id'] = ['in' , $batchIds] ;
            $batchField = "id , sys_bn , cargo_bn_id" ;
            $batchLists = $this->getModel("ErpBatch")->getFieldList($batchField , $batchWhere) ;
            foreach ($batch as $batchId => $batchNum){
                $batchNum = setNum($batchNum) ;
                $stockInLists[] = [
                    "storage_code" => erpCodeNumber(8)['order_number'] ,
                    "storage_type" => 3 ,
                    "storage_remark" => $param['refund_remark'],
                    "source_number" => $returnOrderOrder ,
                    "source_object_id" => "",
                    "our_company_id" => session('erp_company_id'),
                    "goods_id" => $sale_order_info['goods_id'] ,
                    "storage_num" => $batchNum ,
                    "actual_storage_num" => $batchNum ,
                    "balance_num" => getAllocationStockType($sale_order_info['storehouse_id']) == 4 ? $batchNum : 0, // 根据销退单仓库对应的库存类型，判断是否为网点，为网点 可用数量要赋值 2019-5-16
                    "balance_num_litre" => getAllocationStockType($sale_order_info['storehouse_id']) == 4 ? tonToLiter($batchNum, getConfig('Config_Density')) : 0, // 根据销退单仓库对应的库存类型，判断是否为网点，为网点 可用数量要赋值 2019-5-16
                    "outbound_density" => getAllocationStockType($sale_order_info['storehouse_id']) == 4 ? getConfig('Config_Density') : 0, //，密度暂时空
                    'create_time' => currentTime(),
                    'creater_id' => $this->getUserInfo('id'),
                    "dealer_id" => $sale_order_info['dealer_id'] ,
                    "dealer_name" => $sale_order_info['dealer_name'],
                    "storehouse_id" =>$sale_order_info['storehouse_id'] ,
                    "stock_type" => $sale_order_info['is_agent'] == 2 ? getAllocationStockType($sale_order_info['storehouse_id']) : 2 ,
                    "region" =>  $sale_order_info['region'] ,
                    "price" => $sale_order_info['price'] ,
                    "batch_id" => $batchId ,
                    'batch_sys_bn' => $batchLists[$batchId]['sys_bn'],
                    'cargo_bn_id' => $batchLists[$batchId]['cargo_bn_id'],
                ];
                $batchNums += $batchNum ;
            }
        }
        //未出库的批次数量

        $noStockOutNum = setNum($param['return_goods_num']) - $batchNums ;
        if($noStockOutNum > 0) {
            $noStockInLists = [
                "storage_code" => erpCodeNumber(8)['order_number'],
                "storage_type" => 3,
                "storage_remark" => $param['refund_remark'],
                "source_number" => $returnOrderOrder,
                "source_object_id" => "",
                "our_company_id" => session('erp_company_id'),
                "goods_id" => $sale_order_info['goods_id'],
                "storage_num" => $noStockOutNum,
                "actual_storage_num" => 0 ,
//          "outbound_density" => $sale_order_info['']//，密度暂时空
                'create_time' => currentTime(),
                'creater_id' => $this->getUserInfo('id'),
                "dealer_id" => $sale_order_info['dealer_id'],
                "dealer_name" => $sale_order_info['dealer_name'],
                "storehouse_id" => $sale_order_info['storehouse_id'],
                "stock_type" => $sale_order_info['is_agent'] == 2 ? getAllocationStockType($sale_order_info['storehouse_id']) : 2 ,
                "region" => $sale_order_info['region'],
                "price" => $sale_order_info['price'],
            ];
        }
        if(empty($stockInLists) && empty($noStockInLists)){
            return ["status" => 16 , "message" => "退货数量必须大于0"];
        }
        cancelCacheLock('ErpReturned/addSaleReturn');
        if (getCacheLock('ErpReturned/addSaleReturn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReturned/addSaleReturn', 1);
        M()->startTrans();
        $order_status = $erpReturnOrderId = $this->getModel('ErpReturnedOrder')->addReturnedOrder($data);
        if(!$order_status){
            M()->rollback() ;
            cancelCacheLock('ErpReturned/addSaleReturn');
            return ["status" => 12 , "message" => "生成退货单信息失败，请重新操作"];
        }
        $log_data['return_order_id'] = $erpReturnOrderId ;
        if(!$this->addReturnedOrderLog($log_data)){
            M()->rollback() ;
            cancelCacheLock('ErpReturned/addSaleReturn');
            return ["status" => 13 , "message" => "生成退货单记录数据失败，请重新操作"];
        }

        if(!empty($stockInLists)){
            foreach ($stockInLists as $stockInListKey =>$stockInListsVal ){
                $stockInLists[$stockInListKey]['source_object_id'] = $erpReturnOrderId ;
            }
            $stockInList = array_values($stockInLists) ;
            if(!$this->getModel("ErpStockIn")->addStockInAll($stockInList)){
                M()->rollback() ;
                cancelCacheLock('ErpReturned/addSaleReturn');
                return ["status" => 14 , "message" => "销售退货批次生成入库单失败，请重新操作"];
            }
        }

        if(!empty($noStockInLists)){
            $noStockInLists['source_object_id'] = $erpReturnOrderId ;
            if(!$this->getModel("ErpStockIn")->addStockIn($noStockInLists)){
                M()->rollback() ;
                cancelCacheLock('ErpReturned/addSaleReturn');
                return ["status" => 14 , "message" => "销售退货生成入库单失败，请重新操作"];
            }
        }
        if(!$this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($data['source_order_id'])], $sale_order_data)){
            M()->rollback() ;
            cancelCacheLock('ErpReturned/addSaleReturn');
            return ["status" => 15 , "message" => "销售单操作失败，请重新操作"];
        }
        M()->commit() ;
        cancelCacheLock('ErpReturned/addSaleReturn');
        return ['status' => 1, 'message' => '操作成功'];
    }

    /**
     * 销售退货单列表
     * @author guanyu 2017-08-25
     * @param $param
     */
    public function saleReturnOrderList($param = [])
    {
        $where = [];

        if (trim($param['order_number'])) {
            $where['ro.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }

        if (trim($param['source_order_number'])) {
            $where['ro.source_order_number'] = ['like', '%' . trim($param['source_order_number']) . '%'];
        }

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {
                $where['ro.return_goods_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
                $where['ro.return_goods_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time'])))];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {
                $where['ro.return_goods_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time'])))]];
            }
        }

        if (intval(trim($param['goods_id']))) {
            $where['o.goods_id'] = intval(trim($param['goods_id']));
        }

        if ($param['order_status']) {
            $where['ro.order_status'] = intval($param['order_status']);
        }

        if ($param['return_type']) {
            $where['ro.return_type'] = intval($param['return_type']);
        }

        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }

        if (trim($param['sale_user'])) {
            //$user_ids = D("User")->where(['user_name' => ['like', '%' . trim($param['sale_user']) . '%'], 'is_available' => 0])->getField('id', true);
            $user_ids = $this->getEvent('ErpCustomer')->getCustomerUserDataField(['user_name' => ['like', '%' . trim($param['sale_user']) . '%']],'id,user_name');
            $user_ids = array_keys($user_ids);
            if ($user_ids) {
                $where['o.user_id'] = ['in', $user_ids];
            } else {
                $data['recordsFiltered'] = $data['recordsTotal'] = 0;
                $data['data'] = [];
                $data['draw'] = $_REQUEST['draw'];
                return $data;
            }
        }

        if (trim($param['sale_company_id'])) {
            $where['o.company_id'] = intval(trim($param['sale_company_id']));
        }

        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['o.region'] = ['in',$city_id];
        }

        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }

        if (trim($param['depot_id'])) {
            $where['o.depot_id'] = intval($param['depot_id']);
        }

        if (trim($param['storehouse_id'])) {
            $where['o.storehouse_id'] = intval($param['storehouse_id']);
        }

        if(trim($param['business_type'])){
            $where['ro.business_type'] = intval($param['business_type']);
        }
        //我的销售退货单
        if (trim($param['dealer_id'])) {
            $where['ro.creater_id'] = trim($param['dealer_id']);
        }

        if (intval(trim($param['order_type']))) {
            $where['ro.order_type'] = intval(trim($param['order_type']));
        }

        if (intval(trim($param['return_amount_status']))) {
            $where['ro.return_amount_status'] = intval(trim($param['return_amount_status']));
        }

        //当前登陆选择的我方公司
        $where['ro.our_company_id'] = session('erp_company_id');

        //查询批发销售退货单
        $field = 'ro.*,o.add_order_time,o.region,o.user_id,o.company_id,o.depot_id,s.storehouse_name,d.depot_name,dl.dealer_name,
       g.goods_code,g.goods_name,g.source_from,g.grade,g.level,cdl.dealer_name as creater_name';

        if ($param['export']) {
            $data = $this->getModel('ErpReturnedOrder')->getAllReturnedOrderList($where,$field);
        } else {
            $data = $this->getModel('ErpReturnedOrder')->getReturnedOrderList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            $user_ids = array_column($data['data'], 'user_id');
            $company_ids = array_column($data['data'], 'company_id');
            $userInfo = $this->getEvent('ErpCommon')->getUserData($user_ids, $param['order_type']);
            $companyInfo = $this->getEvent('ErpCommon')->getCompanyData($company_ids, $param['order_type']);
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['order_status_font'] = ReturnedOrderStatus($value['order_status']);
                $data['data'][$key]['order_status'] = ReturnedOrderStatus($value['order_status'], true);
                $data['data'][$key]['return_amount_status_font'] = returnedAmountStatus($value['return_amount_status']);
                $data['data'][$key]['return_amount_status'] = returnedAmountStatus($value['return_amount_status'], true);
                $data['data'][$key]['return_type'] = returnType($value['return_type']);
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $value['depot_name'];
                $data['data'][$key]['return_goods_num'] = $value['return_goods_num'] > 0 ? getNum($value['return_goods_num']) : '0';
                $data['data'][$key]['return_price'] = $value['return_price'] > 0 ? getNum($value['return_price']) : '0';
                $data['data'][$key]['return_amount'] = round(getNum($value['return_goods_num'] * getNum($value['return_price'])),2);
                $data['data'][$key]['business_type'] = isset($value['business_type']) ?  getSaleOrderBusinessType($value['business_type']) : '';

                $data['data'][$key]['user_name'] = $userInfo[$value['user_id']]['user_name'];
                $data['data'][$key]['company_name'] = $companyInfo[$value['company_id']]['company_name'];
            }
        } else {
            $data['data'] = [];
        }
        //print_r($data['data']);
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 销售退货单列表
     * @author guanyu 2017-08-25
     * @param $param
     */
    public function findOneSaleReturnOrder($param, $field){
        $where = [
            'ro.id' => intval($param['id']),
            'ro.order_type' => intval($param['order_type'])
        ];
        $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrder($where, $field);
        return $order_info;
    }

    /**
     * 返回退货单
     * @author guanyu 2017-08-25
     * @param $param
     */
    public function findOneReturnOrder($param, $field){
        $where = [
            'ro.id' => intval($param['id']),
            'ro.order_type' => intval($param['order_type'])
        ];
        $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrder($where, $field);
        if($order_info['order_type'] == 1){
            $source_order_info = $this->getModel('ErpSaleOrder')->where(['order_number'=>$order_info['source_order_number']])->field('id,company_id, user_id')->find();
            //$order_info['company_name'] = $this->getEvent('ErpCustomer')->getCustomerDataField(['id'=>$source_order_info['company_id']], 'customer_name');
            //$user_info = $this->getEvent('ErpCustomer')->UserDetail(['id'=>$source_order_info['user_id']], 'user_name,user_phone');
            $company_info = $this->getEvent('ErpCommon')->getCompanyData([$source_order_info['company_id']], 1);
            $user_info = $this->getEvent('ErpCommon')->getUserData([$source_order_info['user_id']], 1);
            $order_info['user_name'] = $user_info[$source_order_info['user_id']]['user_name'];
            $order_info['user_phone'] = $user_info[$source_order_info['user_id']]['user_phone'];
            $order_info['company_name'] = $company_info[$source_order_info['company_id']]['company_name'];

        }else{
            $source_order_info = $this->getModel('ErpPurchaseOrder')->where(['order_number'=>$order_info['source_order_number']])->field('id,sale_company_id, sale_user_id')->find();
            $company_info = $this->getEvent('ErpCommon')->getCompanyData([$source_order_info['sale_company_id']], 2);
            $user_info = $this->getEvent('ErpCommon')->getUserData([$source_order_info['sale_user_id']], 2);
            $order_info['user_name'] = $user_info[$source_order_info['sale_user_id']]['user_name'];
            $order_info['user_phone'] = $user_info[$source_order_info['sale_user_id']]['user_phone'];
            $order_info['company_name'] = $company_info[$source_order_info['sale_company_id']]['company_name'];
        }

        return $order_info;
    }

    /**
     * 编辑销售退货单
     * @param array $param
     * @author guanyu
     * @time 2017-08-21
     * @return array
     */
    public function updateSaleReturn($param = [])
    {
        //验证参数及单据
        $sale_return_order_info = $this->getModel('ErpReturnedOrder')->where(['id'=>$param['id'],'our_company_id'=>session('erp_company_id')])->find();
        if ($sale_return_order_info['order_status'] != 1) {
            return [
                'status' => 2,
                'message' => '只有未审核订单可编辑'
            ];
            return $result;
        }
        if (empty($param)) {
            return [
                'status' => 3,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (!strHtml($param['source_order_id'])) {
            $result = [
                'status' => 4,
                'message' => '来源订单id错误'
            ];
            return $result;
        }
        if (!strHtml($param['source_order_number'])) {
            $result = [
                'status' => 5,
                'message' => '来源订单号错误'
            ];
            return $result;
        }
        if (strHtml($param['return_goods_time']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择退货时间'
            ];
            return $result;
        }
        if (strHtml($param['return_goods_num']) == '') {
            $result = [
                'status' => 7,
                'message' => '请填写退货数量'
            ];
            return $result;
        }
        if (strHtml($param['return_type']) == '') {
            $result = [
                'status' => 8,
                'message' => '请选择退货类型'
            ];
            return $result;
        }
        if (strHtml($param['refund_remark']) == '') {
            $result = [
                'status' => 9,
                'message' => '请填写退款走向备注'
            ];
            return $result;
        }

        $sale_order_info = $this->getModel('ErpSaleOrder')->where(['id'=>$param['source_order_id'],'order_number'=>$param['source_order_number'],'our_company_id'=>session('erp_company_id')])->find();

        $min_returned_num = $sale_order_info['buy_num'] - $sale_order_info['loss_num'] - $sale_order_info['returned_goods_num'] - $sale_order_info['outbound_quantity'];
        $max_returned_num = $sale_order_info['buy_num'] - $sale_order_info['loss_num'] - $sale_order_info['returned_goods_num'];

        if ($sale_order_info['outbound_quantity'] == 0) {
            $result = [
                'status' => 7,
                'message' => '没有出库记录的销售单不允许创建销退'
            ];
            return $result;
        }
        if (intval(setNum($param['return_goods_num'])) < intval($min_returned_num) || intval(setNum($param['return_goods_num'])) > intval($max_returned_num)) {
            $result = [
                'status' => 8,
                'message' => '退货数量不能超过可退货数量区间'
            ];
            return $result;
        }
        if ($sale_order_info['collection_status'] != 10 || $sale_order_info['invoice_status'] != 1) {
            $result = [
                'status' => 10,
                'message' => '销售退货单无法创建，销售单必须满足已收款，未开票，涉及开票或部分开票必须通知财务红冲发票，再进行创建'
            ];
            return $result;
        }
        if (getCacheLock('ErpReturned/addSaleReturn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpReturned/addSaleReturn', 1);

        M()->startTrans();

        //更新单据
        $data = [
            'return_goods_time' => $param['return_goods_time'],
            'return_goods_num' => setNum($param['return_goods_num']),
            'return_price' => $sale_order_info['price'],
            'return_type' => $param['return_type'],
            'remark' => $param['remark'],
            'refund_remark' => $param['refund_remark'],
            'update_time' => currentTime(),
            'updater_id' => $this->getUserInfo('id'),
            'region' => $sale_order_info['region'],
        ];
        $order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id'=>$param['id']],$data);

        $returnedOrderNew = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $param['id']]);

        //新增log
        $log_data = [
            'return_order_id' => $param['id'],
            'return_order_number' => $returnedOrderNew['order_number'],
            'return_order_type' => 1,
            'log_type' => 2,
            'log_info' => serialize($returnedOrderNew),
        ];
        $log_status = $this->addReturnedOrderLog($log_data);

        //修改来源单据退货数量
        $sale_order_data = [
            'is_returned' => 1,
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
        ];
        $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($sale_order_info['id'])], $sale_order_data);

        //======================================================================================================
        if ($order_status && $log_status && $sale_order_status) {
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

        cancelCacheLock('ErpReturned/addSaleReturn');
        return $result;
    }

    /**
     * 删除销售退货单
     * @author guanyu
     * @param $id
     * @return array
     */
    public function delSaleReturn($id)
    {
        if ($id) {
            //验证单据
            $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
            if ($order_info['order_status'] == 2) {
                $result = [
                    'status' => 2,
                    'message' => '订单已取消，无法重复操作',
                ];
                return $result;
            }
            if ($order_info['order_status'] == 10) {
                $result = [
                    'status' => 2,
                    'message' => '订单已确认，无法操作',
                ];
                return $result;
            }
            $erpStockInWhere =[
                "source_object_id" => intval($order_info['id']) ,
                "storage_status" => ['neq' , 2] ,
                "storage_type" => 3
            ];
            $stockInCount  = $this->getModel("ErpStockIn")->getCount($erpStockInWhere);
            if($stockInCount > 0){
                return ['status' => 8 , 'message' => "请先取消入库单"];
            }
            //修改退货单数据单据
            $returnOrderData = [
                'order_status' => 2,
                'update_time' => currentTime(),
                'updater_id' => $this->getUserInfo('id'),
            ];
            //新增退货单Log
            $returnLogData = [
                'return_order_id' => $id,
//                'return_order_number' => $returnedOrderNew['order_number'],
                'return_order_type' => 1,
                'log_type' => 3,
//                'log_info' => serialize($returnedOrderNew),
            ];
            //销售单的修改
            $saleOrderData = [
                'is_returned' => 2,
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('id'),
            ];
            //审批流的修改
            $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_number'=>$order_info['order_number'],'workflow_order_id'=>$id, 'status'=>['neq', 2]])->order('id desc')->find();
            $workflowData['status'] = 2;
            //入库单的数据修改
//            $stockInData = [
//                "storage_status" => 2 ,
//                "update_time" =>currentTime() ,
//            ] ;
            if (getCacheLock('ErpReturned/delSaleReturn'))
                return ['status' => 99, 'message' => $this->running_msg];
            setCacheLock('ErpReturned/delSaleReturn', 1);
            M()->startTrans();
            if(!$this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $returnOrderData)) {
                M()->rollback();
                cancelCacheLock('ErpReturned/delSaleReturn');
                return  ['status' => 3,'message' => '退货单修改失败，请重新操作'];
            }
            $returnedOrderNew = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
            $returnLogData['return_order_number'] = $returnedOrderNew['order_number'];
            $returnLogData['log_info'] = serialize($returnedOrderNew);
            if(!$this->addReturnedOrderLog($returnLogData)){
                M()->rollback();
                cancelCacheLock('ErpReturned/delSaleReturn');
                return  ['status' => 4,'message' => '退货单记录数据失败，请重新操作'];
            }
            if(!$this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($order_info['source_order_id'])], $saleOrderData)){
                M()->rollback();
                cancelCacheLock('ErpReturned/delSaleReturn');
                return  ['status' => 5,'message' => '销售单数据失败，请重新操作'];
            }
            if ($workflow && $workflow['status'] == 1) {
                if(!$this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflowData)){
                    M()->rollback();
                    cancelCacheLock('ErpReturned/delSaleReturn');
                    return  ['status' => 6,'message' => '退货单审核流程修改失败，请重新操作'];
                }
            }
//            $erpStockInWhere =[
//                "source_object_id" => intval($order_info['id']) ,
//                "storage_status" => 1 ,
//                "storage_type" => 3
//            ];
//            if(!$this->getModel("ErpStockIn")->saveStockIn($erpStockInWhere , $stockInData)){
//                M()->rollback();
//                cancelCacheLock('ErpReturned/delSaleReturn');
//                return  ['status' => 7,'message' => '退货入库单修改失败，请重新操作'];
//            }
            M()->commit();
            cancelCacheLock('ErpReturned/delSaleReturn');
            return  ['status' => 1,'message' => '操作成功'];
        }
    }

    /**
     * 审核销售退货单
     * @author guanyu
     * @param $id
     * @return array
     */
    public function auditSaleReturn($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrderJoinOrder(['ro.id' => $id, 'ro.order_type'=>1], 'ro.*, o.company_id');
            if ($order_info['order_status'] != 1) {
                $result = [
                    'status' => 2,
                    'message' => '只允许未审核单据进行审核',
                ];
                return $result;
            }
            if (getCacheLock('ErpReturned/auditSaleReturn')) return ['status' => 99, 'message' => $this->running_msg];
            # -------------------------判断公司账套之间 ， 不走审批----------------------------
            # qianbin
            # 2017.11.06
            //$company_id = getErpCompanyList('company_id');
            //判断客户公司是否为内部公司，判断是否走审核流 edit xiaowen 2019-3-28
            $order_info['company_name'] = $this->getModel('ErpCustomer')->where(['id'=>$order_info['company_id']])->getField('customer_name');
            //if(in_array(intval($order_info['company_id']),$company_id)){
            if(checkInErpCompany($order_info['company_name'])){
                $result = $this->getEvent('ErpWorkFlow')->updateErpWorkFlowOrderStatus(4,intval($order_info['id']),4);
                log_info('--------->公司账套之间 ， 不走审批!');
                if($result){
                    $result = ['status' => 1 , 'message' => '操作成功！'];
                }else{
                    $result = ['status' => 222 , 'message' => '操作失败！'];
                }
                return $result;
            }
            setCacheLock('ErpReturned/auditSaleReturn', 1);
            M()->startTrans();
            $data = [
                'order_status' => 3,
                'update_time' => currentTime(),
                'updater_id' => $this->getUserInfo('id'),
            ];
            $order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $data);

            $returnedOrderNew = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);

            $log_data = [
                'return_order_id' => $id,
                'return_order_number' => $returnedOrderNew['order_number'],
                'return_order_type' => 1,
                'log_type' => 4,
                'log_info' => serialize($returnedOrderNew),
            ];
            $log_status = $this->addReturnedOrderLog($log_data);

            $sale_order_info = $this->getModel('ErpSaleOrder')->where(['id'=>$returnedOrderNew['source_order_id'],'order_number'=>$returnedOrderNew['source_order_number']])->find();
            $returnedOrderNew['region'] = $sale_order_info['region'];
            //$workflow_status = $this->createWorkflow($returnedOrderNew);
            $workflow_status = $this->createWorkflowNew($returnedOrderNew);

            if ($order_status && $log_status && $workflow_status['status']) {
                M()->commit();
                $result = [
                    'status' => 1,
                    'message' => '操作成功',
                ];
            } else {
                M()->rollback();
                $result = [
                    'status' => 0,
                    'message' => $workflow_status['status'] ? '操作失败' : $workflow_status['message'],
                ];
            }
            cancelCacheLock('ErpReturned/auditSaleReturn');
            return $result;
        }
    }

    /**
     * 确认销售退货单
     * @author guanyu
     * @time 2017-08-30
     * @param $id
     * @return array
     */
    public function confirmSaleReturn($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);
            if ($order_info['order_status'] != 4) {
                $result = [
                    'status' => 2,
                    'message' => '只允许已复核的单据进行确认',
                ];
                return $result;
            }
            if (getCacheLock('ErpReturned/confirmSaleReturn')) return ['status' => 99, 'message' => $this->running_msg];
            setCacheLock('ErpReturned/confirmSaleReturn', 1);
            M()->startTrans();
            $data = [
                'order_status' => 10,
                'update_time' => currentTime(),
                'updater_id' => $this->getUserInfo('id'),
            ];
            $order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => intval($id)], $data);

            $returnedOrderNew = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $id]);

            $log_data = [
                'return_order_id' => $id,
                'return_order_number' => $returnedOrderNew['order_number'],
                'return_order_type' => 1,
                'log_type' => 6,
                'log_info' => serialize($returnedOrderNew),
            ];
            $log_status = $this->addReturnedOrderLog($log_data);

            //创建一条未审核的入库单
            $sale_order_info = $this->getModel('ErpSaleOrder')->where(['id'=>$returnedOrderNew['source_order_id'],'order_number'=>$returnedOrderNew['source_order_number']])->find();
            /* -------------------------------------------
                @ Content 注释生成入库单 的操作
                @ Author  Yf
                @ Time    2019-02-27
            --------------------------------------------- */
            // $stock_in_data = [
            //     'storage_code' => erpCodeNumber(8)['order_number'],
            //     'storage_type' => 3,
            //     'storage_status' => 1,
            //     'source_number' => $returnedOrderNew['order_number'],
            //     'source_object_id' => $returnedOrderNew['id'],
            //     'our_company_id' => session('erp_company_id'),
            //     'goods_id' => $sale_order_info['goods_id'],
            //     'storage_num' => $returnedOrderNew['return_goods_num'],
            //     'actual_storage_num' => $returnedOrderNew['return_goods_num'] - ($sale_order_info['buy_num'] - $sale_order_info['outbound_quantity']),
            //     'creater_id' => $this->getUserInfo('id'),
            //     'create_time' => currentTime(),
            //     'dealer_id' => $this->getUserInfo('id'),
            //     'dealer_name' => $this->getUserInfo('dealer_name'),
            //     'storehouse_id' => $sale_order_info['storehouse_id'],
            //     'stock_type' => $sale_order_info['is_agent'] == 1 ? 2 : getAllocationStockType($sale_order_info['storehouse_id']),
            //     'region' => $sale_order_info['region'],
            // ];
            // $status_stockin = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
            // if ($order_status && $log_status && $status_stockin) {
            /* -------------------------------------------
                                END
            --------------------------------------------- */
            if ( $order_status && $log_status  ) {
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
            cancelCacheLock('ErpReturned/confirmSaleReturn');
            return $result;
        }
    }

    /**
     * 返回ErpWorkflowEvent实例
     * @author xiaowen
     * @return \Controller|false|Controller
     */
    public function getWorkflowEvent(){
        return A('ErpWorkFlow', 'Event');
    }

    /**
     * 生成销售单审批步骤(2018.7.2改造)
     * @param array $order_info
     * @author xiaowen
     * @time 2018-07-02
     * @return bool $check_status
     */
    public function createWorkflowNew($order_info = []){
        $data['status'] = false; //审批流程创建状态
        $data['check_order'] = 0; //是否复核订单状态
        $data['message'] = '';
        if($order_info){

            if($order_info['order_type'] == 1){  //销售退货单审批
                //销售单类型为属地，走营销中心销售单审批
                if($order_info['business_type'] == 1){
                    //数量大于等于5，小于500 走二级审批
                    if($order_info['return_goods_num'] >= setNum(5) && $order_info['return_goods_num'] < setNum(500)){
                        $workflow_step = dependencyPurchaseSaleWorkflow(1);
                    }else{  //其他情况 走三级审批
                        $workflow_step = dependencyPurchaseSaleWorkflow(2);
                    }
                }else if($order_info['business_type'] == 2){   //销售退货单类型为大宗，走供应链销售退货单审批
                    $workflow_step = gylSaleWorkflow();
                }else if($order_info['business_type'] == 3){   //销售退货单类型为小十代，走小十代销售退货单审批
                    $workflow_step = LabECOPurchaseSaleWorkflow();
                }else if($order_info['business_type'] == 5){  //ids 销退审批流 2019-5-8
                    $workflow_step = IdsWorkflow(4);
                }
            }else{  //采购退货单审批
                //采购退货单类型为属地，走营销中心采购退货单审批
                if($order_info['business_type'] == 1){
                    //数量小于1000 走二级审批
                    if($order_info['return_goods_num'] < setNum(1000)){
                        $workflow_step = dependencyPurchaseSaleWorkflow(1);
                    }else{  //其他情况 走三级审批
                        $workflow_step = dependencyPurchaseSaleWorkflow(2);
                    }
                }else if($order_info['business_type'] == 5){
                    $workflow_step = LabECOPurchaseSaleWorkflow();
                }else if($order_info['business_type'] == 7){  //ids 采退审批流 2019-5-8
                    $workflow_step = IdsWorkflow(2);
                }else{   //采购退货类型为大宗地炼，走供应链销售单审批，加油站审批
                    $type = $order_info['business_type'] == 4 ? 2 : 1;
                    $workflow_step = gylPurchaseWorkflow($type);
                }
            }
            log_info(print_r($workflow_step, true));
            $workflow_order_type = $order_info['order_type'] == 1 ? 4 : 5;
            $workflow_data = [
                'workflow_type' => $workflow_order_type,
                'workflow_order_number' => $order_info['order_number'],
                'workflow_order_id' => $order_info['id'],
                'our_company_id' => $order_info['our_company_id'],
                'creater' => $this->getUserInfo('dealer_name'),
                'creater_id' => $this->getUserInfo('id'),
            ];
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $order_info, $workflow_order_type);

            $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];

        }
        return $data;
    }

    /**
     * 返回销售退货单信息及账户信息
     * @param $id
     * @return mixed
     * @auther qianbin
     */
    public function getReturnedSaleOrderInfo($id = 0)
    {
        if($id <= 0 ){
            return [];
        }
        //获取订单信息
        $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrderJoinOrder(['ro.id' => $id, 'ro.order_type'=>1], 'ro.*, o.company_id,o.order_amount,o.pay_type,o.collected_amount,o.user_id,o.user_bank_info');
        $data = $order_info;
        $data['order_amount'] = getNum($order_info['order_amount']);
        $data['dealer_name']  = $this->getModel('Dealer')->where(['id' => intval($order_info['creater_id'])])->getField('dealer_name');
        //$data['user_name']    = $this->getModel('User')->where(['id' => intval($order_info['user_id'])])->getField('user_name');
//        $data['company_name'] = $this->getModel('Clients')->where(['id' => intval($order_info['company_id'])])->getField('company_name');
        $data['company_name'] = $this->getModel('ErpCustomer')->where(['id' => intval($order_info['company_id'])])->getField('customer_name');
        $data['collected_amount'] = getNum($order_info['collected_amount']);
        // 待货金额  如果是已退款，则待退余额为0
        $data['no_collect_amount'] = intval($order_info['return_amount_status']) == 10  ?  0 : round(getNum($order_info['return_goods_num'] * getNum($order_info['return_price'])),2);
        $data['pay_type_name'] = empty($order_info['pay_type']) ? '--' : saleOrderPayType($order_info['pay_type']);
        //获取预存账户信息
        $account_where = [
            'account_type' => 1,
            'our_company_id' => $order_info['our_company_id'],
            'company_id' => $order_info['company_id'],
        ];
        $account_info = $this->getModel('ErpAccount')->findAccount($account_where);

        $data['account_balance'] = $account_info ? getNum($account_info['account_balance']) : 0;

        return $data;
    }
}
