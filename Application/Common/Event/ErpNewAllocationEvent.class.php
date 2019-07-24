<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpNewAllocationEvent extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP库存逻辑处理层
    // +----------------------------------
    // |Author:senpai Time:2017.5.3
    // +----------------------------------
    public function _initialize()
    {

    }

    /**
     * 取消调拨单
     * @author xiaowen
     * @time 2017-05-11
     * @param $id
     * @return array
     */
    public function cancelAllocationOrder($id){
        if(getCacheLock('ErpNewAllocation/cancelAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/cancelAllocationOrder', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$id]);
            if(!in_array($order_info['status'], [1, 3, 4])){
                $result = [
                    'status' => 3,
                    'message' => '订单已确认或已取消，无法取消',
                ];
            }else if(empty($order_info)){
                $result = [
                    'status' => 4,
                    'message' => '订单不存在，无法取消',
                ];
            } else {
                $data = [
                    'status' => 2,
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),

                ];
                $log_data = [
                    'allocation_id' => $order_info['id'],
                    'allocation_order_number' => $order_info['order_number'],
                    'log_type' => 3,
                    'log_info' => serialize($order_info),

                ];
                M()->startTrans();
                $status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$id], $data);
                log_info("要调拨更新状态：". $status);
                $log_status = $this->addAllocationLog($log_data);
                log_info("要调拨日志状态：". $log_status);

                //将调拨单审批流置为无效
                $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id, 'status'=>['neq', 2]])->order('id desc')->find();
                if ($workflow && $workflow['status'] == 1) {
                    $workflow['status'] = 2;
                    $work_status = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
                } else {
                    $work_status = true;
                }

                //===============回滚来源仓库调拨预留的库存======================================================
                //---------------确定该订单影响哪个库存，并查出该库存的信息-----

                $stock_where['goods_id'] = $order_info['goods_id'];
                $stock_where['region'] = $order_info['out_region'];
                if(in_array($order_info['allocation_type'], [2, 3])){
//                    $stock_where['object_id'] = $order_info['out_facilitator_id'];
                    $stock_where['object_id'] = $order_info['out_storehouse'];
//                    $stock_where['stock_type'] = 3;
                    $stock_where['stock_type'] = 4;
                }else if(in_array($order_info['allocation_type'], [1, 4])){
                    $stock_where['object_id'] = $order_info['out_storehouse'];
                    $stock_where['stock_type'] = getAllocationStockType($order_info['out_storehouse']);
                }
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $stock_where['goods_id'],
                    'object_id' => $stock_where['object_id'],
                    'stock_type' =>$stock_where['stock_type'],
                    'region' => $stock_where['region'],
                    'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'],
                ];

                $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
                log_info("重置后的库存：". $stock_info['allocation_reserve_num']);
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                log_info("重置后的可用库存：". $stock_info['allocation_reserve_num']);
                //----------------------------------------------------------------
                $orders = [
                    'object_number' => $order_info['order_number'],
                    'object_type' => 5,
                    'log_type' => 6,
                ];
                //----------------更新库存，并保存库存日志-------------------------

                log_info("要变量的库存预留数量：". $order_info['num']);
                //log_info('库存data:'.var_dump($data));
                $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['num'], $orders);
                log_info("要变量的库存状态：". $stock_status);
                //===================================================================================================
                $result['status'] = $status && $log_status && $stock_status && $work_status ? 1 : 0;
                $result['message'] = $result['status'] ? '操作成功' : '操作失败';
                if($result['status']){
                    M()->commit();
                }else{
                    M()->rollback();
                }
            }

        }
        cancelCacheLock('ErpNewAllocation/cancelAllocationOrder');
        return $result;
    }

    /**
     * 审核调拨单
     * @author xiaowen
     * @time 2017-05-11
     * @param $id
     * @return array
     */
    public function auditAllocationOrder($id)
    {
        if(getCacheLock('ErpNewAllocation/auditAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/auditAllocationOrder', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$id]);
            if($order_info['status'] != 1){
                $result = [
                    'status' => 3,
                    'message' => '订单不是未审核状态，无法审核',
                ];
            }else if(empty($order_info)){
                $result = [
                    'status' => 4,
                    'message' => '订单不存在，无法审核',
                ];
            }else{
                M()->startTrans();
                $data = [
                   
                    'audit_time' => currentTime(),

                ];
                

                # 调拨单取消审批流
                # qianbin
                # 2018-06-19
                 #$workflow_status = $this->createWorkflow($order_info);

                 if($order_info['business_type'] == 3){
                    $workflow_status = $this->createWorkflowNew($order_info);
                    $data['status'] = 3;
                } else {
                    $workflow_status = ['status' => 1 , 'message' => '操作成功'];
                    $data['status'] = 4;
                }
                # end
               

                $status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$id], $data);

                $log_data = [
                    'allocation_id' => $order_info['id'],
                    'allocation_order_number' => $order_info['order_number'],
                    //'log_type' => 4,
                    'log_type' => 5,
                    'log_info' => serialize($order_info),
                ];
                $log_status = $this->addAllocationLog($log_data);
                $result['status'] = $status && $workflow_status['status'] && $log_status ? 1 : 0;
                $result['message'] = $result['status'] ? '操作成功' : ($workflow_status['message'] ? $workflow_status['message'] : '操作失败');
                if($result['status']){
                    M()->commit();
                }else{
                    M()->rollback();
                }
            }

        }
        cancelCacheLock('ErpNewAllocation/auditAllocationOrder');
        return $result;
    }

    /**
     * 确认调拨单（确认逻辑拆分：改变状态 && 调拨预留转调拨待提）
     * @author guanyu
     * @time 2017-10-09
     * @param $id
     * @return array
     */
    public function confirmAllocationOrderStatus($id)
    {
        if(getCacheLock('ErpNewAllocation/confirmAllocationOrderStatus'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/confirmAllocationOrderStatus', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$id]);
            if(empty($order_info)){
                $result = [
                    'status' => 3,
                    'message' => '订单不存在，无法取消',
                ];
            }else if($order_info['status'] != 4){
                $result = [
                    'status' => 4,
                    'message' => '订单不是已复核状态，无法确认',
                ];
            }else{
                M()->startTrans();

                //修改订单状态
                $data = [
                    'status' => 10,
                    'confirm_time' => currentTime(),
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$id], $data);

                //保存操作日志
                $log_data = [
                    'allocation_id' => $order_info['id'],
                    'allocation_order_number' => $order_info['order_number'],
                    'log_type' => 6,
                    'log_info' => serialize($order_info),
                ];

                /**------------------库存影响：调拨预留转为调拨待提 start------------------*/
                $stock_where = [
                    'goods_id' => $order_info['goods_id'],
                    'object_id' => $order_info['out_storehouse'],
                    'stock_type' => getAllocationStockType($order_info['out_storehouse']),
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $order_info['goods_id'],
                    'object_id' => $order_info['out_storehouse'],
                    'stock_type' => getAllocationStockType($order_info['out_storehouse']),
                    'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
                    'region' => $order_info['out_region'],
                    'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'],
                    'allocation_wait_num' => $stock_info['allocation_wait_num'] + $order_info['num'],
                ];
                $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
                $stock_info['allocation_wait_num'] = $data['allocation_wait_num']; //重置最新的预留库存
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                //----------------------------------------------------------------
                $orders = [
                    'object_number' => $order_info['order_number'],
                    'object_type' => 5,
                    'log_type' => 3,
                ];
                //----------------更新库存，并保存库存日志-------------------------
                $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['num'], $orders);
                /**------------------库存影响：调拨预留转为调拨待提 end------------------*/

                $log_status = $this->addAllocationLog($log_data);
                $result['status'] = $status && $log_status && $stock_status ? 1 : 0;
                $result['message'] = $result['status'] ? '操作成功' : '操作失败';
                if($result['status']){
                    M()->commit();
                }else{
                    M()->rollback();
                }
            }

        }
        cancelCacheLock('ErpNewAllocation/confirmAllocationOrderStatus');
        return $result;
    }

    /**
     * 复制调拨单
     * @author xiaowen
     * @time 2017-05-11
     * @param $id
     * @return array
     */
    public function copyAllocationOrder($id)
    {
        if (getCacheLock('ErpNewAllocation/copyAllocationOrder')) return ['status' => 9999, 'message' => $this->running_msg];
        setCacheLock('ErpNewAllocation/copyAllocationOrder', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id' => $id]);
            if (empty($order_info)) {
                $result = [
                    'status' => 3,
                    'message' => '订单不存在，无法取消',
                ];
            } else {
                M()->startTrans();
                $allocation_data = $order_info;
                $allocation_data['order_number'] = erpCodeNumber(9)['order_number'];
                $allocation_data['create_time'] = currentTime();
                $allocation_data['creater'] = $this->getUserInfo('id');
                $allocation_data['dealer_id'] = $this->getUserInfo('id');
                $allocation_data['dealer_name'] = $this->getUserInfo('dealer_name');
                $allocation_data['status'] = 1;
                $allocation_data['remark'] = '';
                $allocation_data['order_time'] = currentTime();
                unset($allocation_data['id']);
                unset($allocation_data['actual_out_num']);
                unset($allocation_data['actual_in_num']);
                unset($allocation_data['actual_in_num_liter']);
                unset($allocation_data['actual_out_num_liter']);
                unset($allocation_data['audit_time']);
                unset($allocation_data['update_time']);
                unset($allocation_data['updater']);
                unset($allocation_data['check_time']);
                unset($allocation_data['confirm_time']);
                unset($allocation_data['pick_up_number']);
                $status = $this->getModel('ErpNewAllocationOrder')->addAllocationOrder($allocation_data);

                $log_data = [
                    'allocation_id' => $order_info['id'],
                    'allocation_order_number' => $order_info['order_number'],
                    'log_type' => 7,
                    'log_info' => serialize($order_info),
                ];
                $log_status = $this->addAllocationLog($log_data);
                //更新来源库存的配送预留
                //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                $stock_where = [
                    'goods_id' => $allocation_data['goods_id'],
                    'object_id' => $allocation_data['out_storehouse'],
                    'stock_type' => getAllocationStockType($order_info['out_storehouse']),
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $allocation_data['goods_id'],
                    'object_id' => $allocation_data['out_storehouse'],
                    'stock_type' => getAllocationStockType($order_info['out_storehouse']),
                    'facilitator_id' => $allocation_data['out_facilitator_id'],
                    'region' => $allocation_data['out_region'],
                    'allocation_reserve_num' => $stock_info['allocation_reserve_num'] + $allocation_data['num'],
                ];
                $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                //----------------------------------------------------------------
                $orders = [
                    'object_number' => $allocation_data['order_number'],
                    'object_type' => 5,
                    'log_type' => 1,
                ];
                //----------------更新库存，并保存库存日志-------------------------
                $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $allocation_data['num'], $orders);

                if($status && $log_status && $stock_status){
                    $result['status'] = 1;
                    $result['message'] = '操作成功';
                    M()->commit();
                }else{
                    $result['status'] = 101;
                    $result['message'] = '操作失败';
                    M()->rollback();
                }
            }
        }
        cancelCacheLock('ErpNewAllocation/copyAllocationOrder');
        return $result;

    }

    /**
     * 确认调拨单（弃用）
     * @author xiaowen
     * @time 2017-5-12
     * @param $id 调拨单ID
     * @param $param 调拨单确认时输入的数据
     * @return array $result
     */
    public function confirmAllocationOrder($param,$files)
    {
        if(getCacheLock('ErpNewAllocation/confirmAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];

        if(empty($param['id'])){
            $result = [
                'status'=>2,
                'message'=>'调拨单信息有误，请重新操作',
            ];
            return $result;
        }
        $order_info = $this->getOneAllocationOrder(['id'=>$param['id']]);

        //取调拨双方的油品密度
        $out_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['out_region'], 'our_company_id'=>session('erp_company_id')]);
        $in_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['in_region'], 'our_company_id'=>session('erp_company_id')]);

        if ($param['out_density']) {
            $goods_out_density = $param['out_density'];
        } elseif ($out_goods_info['density']) {
            $goods_out_density = $out_goods_info['density'];
        } else {
            $goods_out_density = $this->getEvent('ErpGoods')->findGoodsById($order_info['goods_id'])['density_value'];
        }

        if ($param['in_density']) {
            $goods_in_density = $param['in_density'];
        } elseif ($in_goods_info['density']) {
            $goods_in_density = $in_goods_info['density'];
        } else {
            $goods_in_density = $this->getEvent('ErpGoods')->findGoodsById($order_info['goods_id'])['density_value'];
        }

        //=================统一用入库方区域商品维护和密度，如果没有则使用商品主档的密度 进行升转吨==================
//        $param['actual_out_num'] = round(literToTon($param['actual_out_num_liter'], $goods_out_density), 4);
        $param['actual_out_num'] = ErpFormatFloat(literToTon($param['actual_out_num_liter'], $goods_out_density), 8);
//        log_info("转化后实际出库数量：". $param['actual_out_num']);
//        $param['actual_in_num'] = round(literToTon($param['actual_in_num_liter'], $goods_in_density), 4);
        $param['actual_in_num'] = ErpFormatFloat(literToTon($param['actual_in_num_liter'], $goods_in_density), 8);
//        log_info("转化后实际入库数量：". $param['actual_in_num']);
        //===================================================end====================================================

        if ($order_info['status'] == 10) {
            $result = [
                'status'=>2,
                'message'=>'调拨单已确认，无法操作',
            ];
            return $result;
        }
        if(!trim($param['actual_out_num']) || !trim($param['actual_in_num'])){
            $result = [
                'status'=>3,
                'message'=>'请输入实际出库数量和实际入库数量',
            ];
            return $result;
        }
//            服务商到服务商间调拨允许出库数量大于调拨数量，允许入库数量大于调拨数量
//            else if(bccomp(setNum(trim($param['actual_out_num'])), $order_info['num']) == 1 ){  //setNum(trim($param['actual_out_num'])) > $order_info['num']
//                $result = [
//                    'status'=>4,
//                    'message'=>'实际出库数量不能大于调拨数量',
//                ];
//            }else if(bccomp(setNum(trim($param['actual_in_num'])), floatval($order_info['num'])) == 1){
//                $result = [
//                    'status'=>5,
//                    'message'=>'实际入库数量不能大于调拨数量',
//                ];
//            }

        $upload_status_all = true;
        $error_photo = [];
        $stock_out_attachment = [];
        $stock_in_attachment = [];
        if (!empty($files)) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if($value['size'] > 2*1024*1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
//                    if ($value['type'] != "image/jpeg" && $value['type'] != 'image/gif' && $value['type'] != 'image/png') {
//                        $result = [
//                            'status' => 5,
//                            'message' => '文件格式上传有误，只能上传图片文件'
//                        ];
//                        return $result;
//                    }
                } else {
                    continue;
                }
            }

            //上传文件
            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                if ($key == 'stock_out_attachment') {
                    $user_path = $this->uploads_path['stock_out_attach']['src'];
                } elseif ($key == 'stock_in_attachment') {
                    $user_path = $this->uploads_path['stock_in_attach']['src'];
                }
                //判断该用户文件夹是否已经有这个文件夹
                if (!file_exists($user_path)) {
                    mkdir($user_path, 0777, true);
                }
                $current_date = date('Y-m-d');
                if (!is_dir($user_path . $current_date)) {
                    mkdir($user_path . $current_date, 0777, true);
                }
                $current_date = date('Y-m-d');
                //后缀
                $type = substr($value['name'],strripos($value['name'],'.')+1);
                $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
                $upload_status = move_uploaded_file($uploaded_file, $user_path . $file_name);
                $upload_status_all = $upload_status && $upload_status_all ? true : false;
                //已上传文件，如果操作失败要删除
                array_push($error_photo,$file_name);
                //拼装新增数据
                if ($key == 'stock_out_attachment') {
                    $stock_out_attachment['attachment'] = $file_name;
                } elseif ($key == 'stock_in_attachment') {
                    $stock_in_attachment['attachment'] = $file_name;
                }
            }
        }
        setCacheLock('ErpNewAllocation/confirmAllocationOrder', 1);

        M()->startTrans();

        //保存调拨单数据
        $order_data = [
            'actual_out_num' => setNum(ErpFormatFloat($param['actual_out_num'])),
            'actual_in_num' => setNum(ErpFormatFloat($param['actual_in_num'])),
            'actual_out_num_liter' => setNum($param['actual_out_num_liter']),
            'actual_in_num_liter' => setNum($param['actual_in_num_liter']),
            'pick_up_number' => $param['pick_up_number'],
            'confirm_time' =>currentTime(),
            'status' => 10,
            'outbound_status' => 1,
            'storage_status' => 1,
        ];
        $order_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$param['id']], $order_data);
        //保存调拨单操作日志
        $log_data = [
            'allocation_id' => $order_info['id'],
            'allocation_order_number' => $order_info['order_number'],
            'log_info' => serialize($order_info),
            'log_type' => 6,
        ];
        $log_status = $this->addAllocationLog($log_data);

        //===============生成出库单和入库单====================================================================
        //$stock_out_cost = getStockOutCost($stock_info);
        $stock_out_data = array_merge([
            'outbound_type' => 2,
            'outbound_code' => erpCodeNumber(7)['order_number'],
            'outbound_status' => 10,
            'source_number' => $order_info['order_number'],
            'source_object_id' => $order_info['id'],
            'goods_id' => $order_info['goods_id'],
            'our_company_id' => $order_info['our_company_id'],
            'outbound_num' => $order_data['actual_out_num'],
            'actual_outbound_num' => $order_data['actual_out_num'],
            'outbound_density' => $goods_out_density,
            'create_time' => currentTime(),
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'creater_id' => $this->getUserInfo('id'),
            'creater_name' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'storehouse_id' => $order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
            'region' => $order_info['out_region'],
            //'cost'   => empty($stock_out_cost) ? '0' : $stock_out_cost, //edit qianbin 2018-02-05
        ],$stock_out_attachment);

        //服务商出的调拨单影响库存对应入库单的可用数量
        log_info(print_r($stock_out_data, true));
        $stock_in_result = $this->influenceStockIn($stock_out_data);

        $stock_out_data['deduction_num'] = $stock_in_result['deduction_num'];

        //调用java接口获取出库单成本-----------------------------
        $stock_out_cost = getStockOutCost($stock_out_data);
        // 如果成本值为空，则return edit qianbin 2018-03-09

        if ($stock_out_cost['price']===null) {
            M()->rollback();
            cancelCacheLock('ErpNewAllocation/confirmAllocationOrder');
            return  [
                'status' => 99,
                'message' => '成本获取失败，请联系管理员！',
            ];
        }

        $stock_out_data['cost'] = $stock_out_cost['price'] > 0 ? $stock_out_cost['price'] : 0;
        $stock_out_data['cost_log_id'] = $stock_out_cost['logId'] > 0 ? $stock_out_cost['logId'] : 0;
        //------------------------------------------------------
        $stock_in_data = array_merge([
            'storage_type' => 2,
            'storage_code' => erpCodeNumber(8)['order_number'],
            'storage_status' => 10,
            'source_number' => $order_info['order_number'],
            'source_object_id' => $order_info['id'],
            'our_company_id' => $order_info['our_company_id'],
            'goods_id' => $order_info['goods_id'],
            'storage_num' => $order_data['actual_in_num'],
            'actual_storage_num' => $order_data['actual_in_num'],
            'outbound_density' => $goods_in_density,
            'creater_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'storehouse_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'region' => $order_info['in_region'],
            'price'  => $stock_out_cost['price'] > 0 ? $stock_out_cost['price'] : 0, //edit qianbin 2018-02-05,
        ],$stock_in_attachment);
        //如果是服务网点到网点，确认生成的入库单需要增加可用升数 edit xiaowen 2018-2-27-------------------------
        if($order_info['allocation_type'] == 2){
            $stock_in_data['actual_storage_num_litre']  = empty($order_data['actual_in_num_liter']) ? '0' : trim($order_data['actual_in_num_liter']);
            $stock_in_data['balance_num']  = empty($order_data['actual_in_num']) ? '0' : trim($order_data['actual_in_num']);
            $stock_in_data['balance_num_litre']  = empty($order_data['actual_in_num_liter']) ? '0' : trim($order_data['actual_in_num_liter']);
        }
        //------------------------------------------------------------------------------------------------------
        $stock_out_status = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);

        $stock_in_status = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
        //===============生成出库单和入库单 end================================================================

        //===============出库方库存逻辑操作==================================================================
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
            'region' => $order_info['out_region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //---------------判断出库方的物理库存是否满足实际出库数量----------------------
//                log_info('出库方物理库存：'.$stock_info['stock_num']);
//                log_info('实际出库数量：'.$order_data['actual_out_num']);
//                $info = intval($stock_info['stock_num']) < intval($order_data['actual_out_num']) ? '库存不足' : '库存充足';
//                log_info('对比结果：'.$info);
        if(intval($stock_info['stock_num']) < intval($order_data['actual_out_num'])){
            M()->rollback();
            cancelCacheLock('ErpNewAllocation/confirmAllocationOrder');
            return  $result = [
                'status' =>0,
                'message' =>'出库方物理库存不足,无法确认调拨单',
            ];
        }
        //---------------判断出库方的物理库存是否满足实际出库数量 end---------------------
        //==================出库方库存操作逻辑===============================================================
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
//            'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
//            'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? getAllocationStockType($order_info['out_storehouse']) : 4,
            'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
            'region' => $order_info['out_region'],
            'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'], //减少出库方配货预留
            'stock_num' => $stock_info['stock_num'] - $order_data['actual_out_num'], //减少出库方物理库存
        ];

        $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_out_data['outbound_code'],
            'object_type' => 3,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_data['actual_out_num'], $orders);

        //===============出库方库存逻辑操作 end================================================================


        //===============入库方库存逻辑操作==================================================================

        /**调拨入网点时判断：
         * 1、是否有未全部冲减的出库单
         * 2、同网点不同品相手否有负库存，若有，则优先冲减负库存后再入库
         **/
        if (in_array($order_info['allocation_type'],[1,2])) {
            $stock_out_where = [
                '_string' => 'deduction_num < actual_outbound_num',
                'storehouse_id' => $order_info['in_storehouse'],
                'stock_type' => 4,
                'region' => $order_info['in_region'],
                'goods_id' => $order_info['goods_id'],
                'our_company_id' => $order_info['our_company_id'],
                'outbound_status' => 10,
                'reversed_status' => 2,
                'is_reverse' => 2
            ];
            $stock_out_data_deduction = $this->getModel('ErpStockOut')->where($stock_out_where)->select();
            $stock_where = [
                'object_id' => $order_info['in_storehouse'],
                'stock_type' => 4,
                'region' => $order_info['in_region'],
                'goods_id' => $order_info['goods_id'],
                'our_company_id' => $order_info['our_company_id'],
            ];
            $stock_info_deduction = $this->getModel('ErpStock')->where($stock_where)->find();
            if (count($stock_out_data_deduction) > 0) {
                $influence_stock_num = 0;
                foreach ($stock_out_data_deduction as $key => $value) {
                    $need_deduction_num = $value['actual_outbound_num'] - $value['deduction_num'];
                    if ($order_data['actual_in_num'] == 0) {
                        break;
                    } elseif ($order_data['actual_in_num'] <= $need_deduction_num) {
                        $change_num = $order_data['actual_in_num'];
                        $stock_out_data_deduction[$key]['deduction_num'] += $order_data['actual_in_num'];
                        $order_data['actual_in_num'] = 0;
                    } elseif ($order_data['actual_in_num'] > $need_deduction_num) {
                        $change_num = $need_deduction_num;
                        $order_data['actual_in_num'] -= $need_deduction_num;
                        $stock_out_data_deduction[$key]['deduction_num'] = $value['actual_outbound_num'];
                    }

                    $influence_stock_num += $change_num;
                    $deduction_data = [
                        'source_stock_in_number' => $stock_in_data['storage_code'],
                        'type' => 2,
                        'outbound_code' => $value['outbound_code'],
                        'before_balance_num' => $order_data['actual_in_num'] + $change_num,
                        'after_balance_num' => $order_data['actual_in_num'],
                        'deduction_num' => $change_num,
                        'deduction_type' => 1,
                        'create_time' => currentTime(),
                    ];
                    $this->getModel('ErpStockOut')->where(['outbound_code'=>$stock_out_data_deduction[$key]['outbound_code']])->save($stock_out_data_deduction[$key]);
                    $this->getModel('ErpStockInDeduction')->add($deduction_data);
                }
                $orders = [
                    'object_number' => $stock_in_data['storage_code'],
                    'object_type' => 4,
                    'log_type' => 2,
                ];
                //修改被冲减的库存记录
                $stock_info_deduction['stock_num'] += $influence_stock_num;
                $stock_info_deduction['available_num'] += $influence_stock_num;
                $this->getEvent('ErpStock')->saveStockInfo($stock_info_deduction, $influence_stock_num, $orders);
            }
            $write_downs_where = [
                'object_id' => $order_info['in_storehouse'],
                'stock_type' => 4,
                'region' => $order_info['in_region'],
                'stock_num' => ['lt',0],
                'our_company_id' => $order_info['our_company_id'],
                '_string' => 'stock_num + deduction_num < 0'
            ];
            $write_downs_stock = $this->getModel('ErpStockSkidData')->where($write_downs_where)->select();

            if (count($write_downs_stock) > 0 && $order_data['actual_in_num'] > 0) {
                foreach ($write_downs_stock as $key => $value) {
                    $need_deduction_stock_num = $value['stock_num'] + $value['deduction_num'];
                    if ($order_data['actual_in_num'] == 0) {
                        break;
                    } elseif ($order_data['actual_in_num'] + $need_deduction_stock_num <= 0) {
                        $change_num = $order_data['actual_in_num'];
                        $write_downs_stock[$key]['stock_num'] += $order_data['actual_in_num'];
                        $write_downs_stock[$key]['available_num'] += $order_data['actual_in_num'];
                        $order_data['actual_in_num'] = 0;
                    } elseif ($order_data['actual_in_num'] + $need_deduction_stock_num > 0) {
                        $change_num = abs($need_deduction_stock_num);
                        $order_data['actual_in_num'] += $write_downs_stock[$key]['stock_num'];
                        $write_downs_stock[$key]['stock_num'] = 0;
                        $write_downs_stock[$key]['available_num'] = 0;
                    }

                    $orders = [
                        'object_number' => $stock_in_data['storage_code'],
                        'object_type' => 4,
                        'log_type' => 2,
                    ];

                    $write_downs_stock_data = $write_downs_stock[$key];
                    $write_downs_stock_data['id'] = $write_downs_stock_data['stock_id'];
                    unset($write_downs_stock_data['stock_id']);
                    unset($write_downs_stock_data['goods_code']);
                    unset($write_downs_stock_data['deduction_num']);
                    //修改被冲减的库存记录
                    $this->getEvent('ErpStock')->saveStockInfo($write_downs_stock_data, $change_num, $orders);
                    //修改网点期初冲减数量
                    $this->getModel('ErpStockSkidData')->where(['stock_id'=>$write_downs_stock[$key]['stock_id']])->setInc('deduction_num',$change_num);
                    //插入冲减记录
                    $deduction_data = [
                        'source_stock_in_number' => $stock_in_data['storage_code'],
                        'type' => 1,
                        'stock_id' => $write_downs_stock[$key]['stock_id'],
                        'before_balance_num' => $order_data['actual_in_num'] + $change_num,
                        'after_balance_num' => $order_data['actual_in_num'],
                        'deduction_num' => $change_num,
                        'deduction_type' => 1,
                        'create_time' => currentTime(),
                    ];
                    $this->getModel('ErpStockInDeduction')->add($deduction_data);
                }
            }
            //冲减负库存结束后，修改原入库单的可用数量
            $stock_in_data['balance_num'] = $order_data['actual_in_num'];
            $stock_in_data['balance_num_litre'] = $stock_in_data['balance_num'] / $stock_in_data['outbound_density'] * 1000;
            $stock_in_data['deduction_num'] = $stock_in_data['actual_storage_num'] - $order_data['actual_in_num'];
            $this->getModel('ErpStockIn')->where(['storage_code'=>$stock_in_data['storage_code']])->save($stock_in_data);
        }

        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'region' => $order_info['in_region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //获取入库方增加库存前的物理库存 edit xiaowen 2018-2-9----------
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //--------------------------------------------------------------
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'facilitator_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 2 ? $order_info['in_facilitator_id'] : '',
            'region' => $order_info['in_region'],
            'stock_num' => $stock_info['stock_num'] + $order_data['actual_in_num'],
        ];
        $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_in_data['storage_code'],
            'object_type' => 4,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_data['actual_in_num'], $orders);

        //===============入库方库存逻辑操作 end================================================================

        $result_front = ['status' => 1 , 'message' => ''];
        # 如果为入库方为服务商，则需要生成收油单，极光推送
        if(intval($order_info['allocation_type']) == 2){
            # 1. ERP，服务商->服务商，确认入库，调Front生成收油记录 qianbin 2018-03-16
            # 2. 发送极光推送
            $erp_goods_info = $this->getModel('ErpGoods')->field('goods_name,source_from,grade,level')->where(['id'=>$order_info['goods_id']])->find();
            $num_kg  =  getNum($order_data['actual_in_num']) * 1000;
            $out_num = getNum($stock_out_data['actual_outbound_num']) * 1000;
            $params  = [
                'allocation_order_number'  => trim($order_info['order_number']),
                'stock_out_number'         => $stock_out_data['outbound_code'],
                'facilitator_id'           => $order_info['in_facilitator_id'],
                'facilitator_skid_id'      => $order_info['in_storehouse'],
                'allocation_order_type'    => $order_info['allocation_type'],
                'stock_out_weight'         => $out_num,
                'confirm_store_weight'     => $num_kg,
                'confirm_store_density'    => $goods_in_density,
                'create_name'              => $this->getUserInfo('dealer_name'),
                'goods_name'               => $erp_goods_info['goods_name'],
                'goods_source_from'        => $erp_goods_info['source_from'],
                'goods_grade'              => $erp_goods_info['grade'],
                'goods_level'              => $erp_goods_info['level'],
                'stock_time'               => $stock_out_data['audit_time'],
                'goods_num'                => $num_kg,
                'confirm_store_liter'      => getNum($order_data['actual_in_num_liter']),
                'source'                   => 3,
                'delivery_method'          => $order_info['delivery_method']
            ];
            $result_front = addOilReceiptRecord($params);
        }

        if($order_status && $log_status && $out_stock_status && $in_stock_status && $stock_out_status && $stock_in_status && $result_front['status'] == 1 && $stock_in_result['status'] && $upload_status_all){
            //计算加权成本 edit xiaowen 2018-2-7-------------------------
            $stock_in_data['before_stock_num'] = $beforeNum;
            $stock_in_data['stock_id'] = $stockId ? $stockId : 0;
            $stock_in_data['change_num'] = $stock_in_data['actual_storage_num'];
            //java重新计算加权成本
            updateStockInCost($stock_in_data);
            //------------------------------------------------------------
            M()->commit();
            //网点到网点调拨，需要生成零售出库单--------------------------
            if($order_info['allocation_type'] == 2){
                retailOrderCreate($order_info['in_storehouse'], $order_info['our_company_id']);
            }
            //-------------------------------------------------------------

            $result = [
                'status' =>1,
                'message' =>'操作成功',
            ];
        }else{
            //操作失败后删除文件
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            M()->rollback();
            $result = [
                'status' =>0,
                'message' =>'操作失败',
            ];
            if($result_front['status'] != 1 ) $result['message'] = $result_front['message'];
        }




        cancelCacheLock('ErpNewAllocation/confirmAllocationOrder');
        return $result;
    }

    /**
     * 获取一条调拨信息不包含关联信息）
     * @param array $where
     * @return array
     */
    public function getOneAllocationOrder($where = [],$confirm_type=1){
        $data = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder($where);
        $is_matching = $this->isMatching($data,$confirm_type);
        $data['matching_status'] = $is_matching['matching_status'];
        $data['message'] = $is_matching['message'];
        $data = !empty($data) ? $data : [];
        return $data;
    }

    /**
     * 判断调拨单类型与员工角色是否匹配
     * @param array $where
     * @return array
     */
    public function isMatching($data = [],$confirm_type = 1){
        $where['rn.role_id'] = array('in', $this->roles);
        $where['n.app'] = 'erp';

        //$confirm_type == 1 ：确认出库
        //$confirm_type == 2 ：确认入库
        if ($confirm_type == 1) {
            if ($data['allocation_type'] == 1 || $data['allocation_type'] == 4) {
                $where['n.node_code'] = ErpRoleList('仓管员');
                $message = '该订单只有仓管员才能确认出库';
            } elseif ($data['allocation_type'] == 2 || $data['allocation_type'] == 3) {
                $where['n.node_code'] = ErpRoleList('调拨员');
                $message = '该订单只有调拨员才能确认出库';
            }
        } elseif ($confirm_type == 2) {
            if ($data['allocation_type'] == 3 || $data['allocation_type'] == 4) {
                $where['n.node_code'] = ErpRoleList('仓管员');
                $message = '该订单只有仓管员才能确认入库';
            } elseif ($data['allocation_type'] == 1 || $data['allocation_type'] == 2) {
                $where['n.node_code'] = ErpRoleList('调拨员');
                $message = '该订单只有调拨员才能确认入库';
            }
        }
        $node = M('role_node')->alias('rn')
            ->join('oil_node n on rn.node_id = n.id', 'left')
            ->distinct(true)
            ->where($where)
            ->find();
        if (!empty($node)) {
            $matching_status = true;
        } else {
            $matching_status = false;
        }
        return ['matching_status'=>$matching_status,'message'=>$message];
    }

    /**
     * 获取一条调拨信息包含关联信息）
     * @param $id
     * @param string $field
     * @return array
     */
    public function findAllocationOrderInfo($id, $field = ''){
        $field =  $field ? $field : 'ao.*, osh.storehouse_name as osh_storehouse_name, osh.type as osh_type, 
        ish.storehouse_name as ish_storehouse_name, ish.type as ish_type';
        $data = $this->getModel('ErpNewAllocationOrder')->findAllocationOrder(['ao.id'=>$id], $field);
        if($data){
            //调拨类型 1  城市仓->服务商 2 服务商->服务商3 服务商->城市仓4 城市仓->城市仓

            $data['out_type'] = in_array($data['allocation_type'], [1, 4]) ? erpStorehouseType($data['osh_type']) : '服务商';
            $data['in_type'] = in_array($data['allocation_type'], [3, 4]) ? erpStorehouseType($data['ish_type']) : '服务商';

            $data['out_object_name'] = $data['osh_storehouse_name'];
            $data['in_object_name'] = $data['ish_storehouse_name'];

            $data['out_stock_type'] = in_array($data['allocation_type'], [1, 4]) ? storehouseTypeToStockType($data['osh_type']) : 4;
            $data['in_stock_type'] = in_array($data['allocation_type'], [3, 4]) ? storehouseTypeToStockType($data['ish_type']) : 4;
        }else{
            $data = [];
        }
        return $data;
    }

    /**
     * 生成调拨单日志
     * @author xiaowen
     * @time 2017-5-12
     * @param $data
     * @return mixed
     */
    protected function addAllocationLog($data){
        $data['create_time'] = currentTime();
        $data['operator'] = $this->getUserInfo('dealer_name');
        $data['operator_id'] = $this->getUserInfo('id');
        $status = $this->getModel('ErpAllocationOrderLog')->addAllocationOrderLog($data);
        return $status;
    }

    protected function createStockOutInData($order_info){
        $status  = false;
        if($order_info){

            //$this->getModel('ErpStockOut')->

        }
        return $status;
    }
    /**
     * 生成调拨审批流程
     * @author xiaowen
     * @time 2017-5-12
     * @param array $order_info
     * @return bool
     */
    protected function createWorkflow($order_info = []){
        //$status = false; //审批流程创建状态
        $data['status'] = false; //审批流程创建状态
        $data['message'] = '';
        if($order_info){

            # 新的调拨单规则 qianbin  2017.08.14
            # 1）当调拨吨数≤30吨 取消审批流
            # 2）当调拨吨数＞30吨 调整为：调拨员创建调拨单-> 地区调拨负责人-> 地区调拨采购负责人审批 -> 地区分公司负责人审批
            # 3）服务商->服务商之间调拨，遵循原有审批流：市场开发中心交易员创建->地区调拨负责人
            if(intval($order_info['allocation_type']) == 2 ){
                $type = 1;
                log_info('--------->服务商到服务商， 地区调拨负责人审批 ');
            }else if(getNum($order_info['num']) > 33){
                $type = 1;
                log_info('--------->调拨数量大于33吨 ， 地区调拨负责人-> 地区调拨采购负责人审批 -> 地区分公司负责人审批');
            }else{
                $type = 2 ;
                # log_info('--------->调拨数量小于33吨 ， 地区调拨采购负责人审批!');
                /*
                    # $type = 2; 去掉仓管，取消审批流
                    $result = $this->getEvent('ErpWorkflow')->updateErpWorkFlowOrderStatus(3,intval($order_info['id']),4);
                    log_info('--------->调拨数量小于30吨 ， 取消审批!');
                    if($result){
                        $result = ['status' => 1 , 'message' => '操作成功！'];
                    }else{
                        $result = ['status' => 222 , 'message' => '操作失败！'];
                    }
                    return $result;
                */
            }
            $workflow_step = allocationWorkflowStepPosition($type,$order_info['allocation_type']);
            $workflow_data = [
                'workflow_type' => 3,
                'workflow_order_number' => $order_info['order_number'],
                'workflow_order_id' => $order_info['id'],
                'our_company_id' => $order_info['our_company_id'],
                'creater' => $order_info['dealer_name'],
                'creater_id' => $order_info['dealer_id'],
            ];
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            $order_info['region'] = $order_info['out_region'];
            //$step_status = $this->getEvent('ErpWorkflow')->createWorkflowStepData($workflow_id, $workflow_step, $order_info);
            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $order_info, 3);
            $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];

        }
        return $data;
    }

    /**
     * 生成调拨审批流程()最新
     * @author xiaowen
     * @time 2019-5-8
     * @param array $order_info
     * @return bool
     */
    protected function createWorkflowNew($order_info = []){
        //$status = false; //审批流程创建状态
        $data['status'] = false; //审批流程创建状态
        $data['message'] = '';
        if($order_info['business_type'] == 3){ //ids调拨走 ids审批流 ，其他类型不走审批 2019-5-8
            
            $workflow_step = IdsWorkflow(5);
            $workflow_data = [
                'workflow_type' => 3,
                'workflow_order_number' => $order_info['order_number'],
                'workflow_order_id' => $order_info['id'],
                'our_company_id' => $order_info['our_company_id'],
                'creater' => $order_info['dealer_name'],
                'creater_id' => $order_info['dealer_id'],
            ];
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            $order_info['region'] = $order_info['out_region'];
            //$step_status = $this->getEvent('ErpWorkflow')->createWorkflowStepData($workflow_id, $workflow_step, $order_info);
            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $order_info, 3);
            $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];

        }
        return $data;
    }
    /**
     * 返回流程Event实例
     * @author xiaowen
     * @time 2017-05-11
     * @return \Controller|false|Controller
     */
    protected function getWorkflowEvent(){
        return A('ErpWorkFlow', 'Event');
    }

    /**
     * 调拨单列表
     * @param $param
     * @author senpai
     * @time 2017-05-10
     */
    public function erpAllocationOrderList($param = [])
    {
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['ao.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['ao.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['ao.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }

        if (trim($param['out_region'])) {
            $where['ao.out_region'] = intval($param['out_region']);
        }

        if (trim($param['in_region'])) {
            $where['ao.in_region'] = intval($param['in_region']);
        }

        if (trim($param['out_storehouse'])) {
            $where['ao.out_storehouse'] = intval($param['out_storehouse']);
        }

        if (trim($param['in_storehouse'])) {
            $where['ao.in_storehouse'] = intval($param['in_storehouse']);
        }

        if (trim($param['out_facilitator_skid_id'])) {
            $where['ao.out_storehouse'] = intval($param['out_facilitator_skid_id']);
        }

        if (trim($param['in_facilitator_skid_id'])) {
            $where['ao.in_storehouse'] = intval($param['in_facilitator_skid_id']);
        }

        if (trim($param['goods_id'])) {
            $where['ao.goods_id'] = intval($param['goods_id']);
        }

        if (trim($param['dealer_name'])) {
            $where['ao.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }

        if ($param['status']) {
            $where['ao.status'] = intval($param['status']);
        }else{
            $where['ao.status'] = ['neq', 2]; //默认不显示已取消调拨单 edit xiaowen 2017-10-13
        }

        if (trim($param['order_number'])) {
            $where['ao.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        if (trim($param['outbound_status'])) {
            $where['ao.outbound_status'] = intval($param['outbound_status']);
        }
        if (trim($param['storage_status'])) {
            $where['ao.storage_status'] = intval($param['storage_status']);
        }
        if(trim($param['delivery_method'])){
            $where['ao.delivery_method'] = intval($param['delivery_method']);
        }
        if(trim($param['business_type'])){
            $where['ao.business_type'] = intval($param['business_type']);
        }
        if(trim($param['allocation_type'])){
            $where['ao.allocation_type'] = trim($param['allocation_type']);
        }
        //当前登陆选择的我方公司
        $where['ao.our_company_id'] = session('erp_company_id');

        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,osh.type as o_storehouse_type,ish.storehouse_name as i_storehouse_name,
        ish.type as i_storehouse_type,g.goods_code,g.goods_name,g.source_from,g.grade, g.level';

        if($param['export'] && isset($param['export'])){
            $data = $this->getModel('ErpNewAllocationOrder')->erpAllocationOrderAlListData($where, $field);
        }else{
            $data = $this->getModel('ErpNewAllocationOrder')->getAllocationOrderList($where, $field, $param['start'], $param['length']);
        }

        $supplier_ids = array_merge(array_unique(array_column($data['data'],'out_facilitator_id')),array_unique(array_column($data['data'],'in_facilitator_id')));
        $where = [
            'id' => $supplier_ids ? ['in',$supplier_ids] : 0,
        ];
        $supplier_data = $this->getEvent('ErpSupplier')->getSupplierDataField($where,'id,supplier_name');
        //print_r($data['data']);

        //log_info("SQL语句:" .$this->erpPurchaseOrder->getLastSql());
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            $is_show_front = $param['export'] && isset($param['export']) ? false : true;
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['i_facilitator_name'] = $value['in_facilitator_id'] ? $supplier_data[$value['in_facilitator_id']] : '——';
                $data['data'][$key]['i_storehouse_name'] = $value['allocation_type'] == 3 || $value['allocation_type'] == 4 ? $value['i_storehouse_name'] : '——';
                $data['data'][$key]['i_skid_name'] = $value['allocation_type'] == 1 || $value['allocation_type'] == 2 ? $value['i_storehouse_name'] : '——';
                $data['data'][$key]['o_facilitator_name'] = $value['out_facilitator_id'] ? $supplier_data[$value['out_facilitator_id']] : '——';
                $data['data'][$key]['o_storehouse_name'] = $value['allocation_type'] == 1 || $value['allocation_type'] == 4 ? $value['o_storehouse_name'] : '——';
                $data['data'][$key]['o_skid_name'] = $value['allocation_type'] == 2 || $value['allocation_type'] == 3 ? $value['o_storehouse_name'] : '——';
                $data['data'][$key]['out_region_font'] = $cityArr[$value['out_region']];
                $data['data'][$key]['in_region_font'] = $cityArr[$value['in_region']];
                $data['data'][$key]['num'] = ErpFormatFloat(getNum($value['num']));
                $data['data'][$key]['actual_out_num'] = ErpFormatFloat(getNum($value['actual_out_num']));
                $data['data'][$key]['actual_in_num'] = ErpFormatFloat(getNum($value['actual_in_num']));

                $data['data'][$key]['status_font_real'] = AllocationOrderStatus($value['status']);
                $data['data'][$key]['status_font'] = AllocationOrderStatus($value['status'], $is_show_front);
                //显示凭证状态
                $data['data'][$key]['outbound_voucher_status'] = AllocationVoucherStatus($value['outbound_voucher_status'], true);
                $data['data'][$key]['storage_voucher_status'] = AllocationVoucherStatus($value['storage_voucher_status'], true);
                //出 入 库状态
                $data['data'][$key]['outbound_status'] = AllocationOutboundStatus($value['outbound_status'], true);
                $data['data'][$key]['storage_status'] = AllocationStorageStatus($value['storage_status'], true);
                $data['data'][$key]['delivery_method'] = allocationOrderDeliveryMethod($value['delivery_method'],true);
                // 城市仓->服务商 服务商->城市仓 存在的业务类型：零售和加油站业务
                $data['data'][$key]['business_type']   = empty($value['business_type']) ? '--' : getAllocationOrderBusinessType($value['business_type']);
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 新增调拨单
     * @author senpai
     * @time 2017-05-12
     */
    public function addErpAllocationOrder($param)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        $out_facilitator_skid = $this->getModel('ErpStorehouse')->where(['id'=>trim($param['out_facilitator_skid_id'])])->find();
        $in_facilitator_skid = $this->getModel('ErpStorehouse')->where(['id'=>trim($param['in_facilitator_skid_id'])])->find();

        $out_storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['out_storehouse']])->find();
        $in_storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['in_storehouse']])->find();

        $where['_string'] = 'goods_id = '.$param['goods_id'].' AND status = 1 AND region IN ('.$param['out_region'].') AND our_company_id = '.session('erp_company_id');
        $region_goods = D('ErpRegionGoods')->where($where)->find();
        if (empty($region_goods)) {
            $result['status'] = 101;
            $result['message'] = "该商品出库方所在区域维护中未设置";
            return $result;
        }
        if (trim($param['our_company_id']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择我方公司";
            return $result;
        }
        if (trim($param['delivery_method']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择配送方式";
            return $result;
        }
        if (trim($param['delivery_method']) == 1 && $in_storehourse['type'] == 3) {
            $result['status'] = 101;
            $result['message'] = "调入零售仓的调拨单不允许配送，请检查";
            return $result;
        }
        if (trim($param['allocation_type']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择调拨类型";
            return $result;
        }
        if (trim($param['out_region']) == "") {
            $result['status'] = 101;
            $result['message'] = "去选择来源城市";
            return $result;
        }
        if (trim($param['out_storehouse']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 4)) {
            $result['status'] = 101;
            $result['message'] = "请选择来源仓库";
            return $result;
        }
        if (trim($param['out_facilitator_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
            $result['status'] = 101;
            $result['message'] = "请选择来源服务商";
            return $result;
        }
        if (trim($param['out_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
            $result['status'] = 101;
            $result['message'] = "请选择来源加油网点";
            return $result;
        }
        if ($out_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
            $result['status'] = 101;
            $result['message'] = "来源加油网点无效，请检查";
            return $result;
        }
        if (trim($param['in_region']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择目的城市";
            return $result;
        }
        if (trim($param['in_storehouse']) == "" && (trim($param['allocation_type']) == 3 || trim($param['allocation_type']) == 4)) {
            $result['status'] = 101;
            $result['message'] = "请选择目的仓库";
            return $result;
        }
        if (trim($param['in_facilitator_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
            $result['status'] = 101;
            $result['message'] = "请选择目的服务商";
            return $result;
        }
        if (trim($param['in_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
            $result['status'] = 101;
            $result['message'] = "请选择目的加油网点";
            return $result;
        }
        if ($in_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
            $result['status'] = 101;
            $result['message'] = "目的加油网点无效，请检查";
            return $result;
        }
        if ($param['business_type'] == '') {
            $result['status'] = 101;
            $result['message'] = "请选择调拨场景！";
            return $result;
        }
        if ($param['out_storehouse'] == $param['in_storehouse'] && $param['allocation_type'] == 4) {
            $result['status'] = 101;
            $result['message'] = "相同仓库之间不允许同地调拨！";
            return $result;
        }
        if (trim($param['goods_id']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择商品代号";
            return $result;
        }
        if (trim($param['num']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入数量";
            return $result;
        }
        if (!is_numeric(trim($param['num']))) {
            $result['status'] = 101;
            $result['message'] = "请输入数字";
            return $result;
        }
        if ($param['allocation_type'] == 1 && $out_storehourse['is_allocation'] != 1) {
            $result['status'] = 101;
            $result['message'] = "该仓库不能做向服务商调拨业务，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 1 || $param['allocation_type'] == 4) && $out_storehourse['region'] != $param['out_region'] && $out_storehourse['whole_country'] != 1) {
            $result['status'] = 101;
            $result['message'] = "来源方仓库不是全国仓库，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 3 || $param['allocation_type'] == 4) && $in_storehourse['region'] != $param['in_region'] && $in_storehourse['whole_country'] != 1) {
            $result['status'] = 101;
            $result['message'] = "目的方仓库不是全国仓库，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 1 || $param['allocation_type'] == 4) && $out_storehourse['status'] == 2) {
            $result['status'] = 101;
            $result['message'] = "来源方仓库已禁用，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 3 || $param['allocation_type'] == 4) && $in_storehourse['status'] == 2) {
            $result['status'] = 101;
            $result['message'] = "目的方仓库已禁用，请检查";
            return $result;
        }
        # else if(!trim($param['actual_allocation_time'])){
        #   $result['status'] = 101;
        #    $result['message'] = "请输入实际调拨时间！";
        # }

        if(getCacheLock('ErpNewAllocation/addErpAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/addErpAllocationOrder', 1);

        $out_region = $param['out_region'];

        $in_region = $param['in_region'];

        M()->startTrans();

        //添加调拨单信息
        $erp_allocation_data = [
            'order_number' => erpCodeNumber(9)['order_number'],
            'our_company_id' => $param['our_company_id'],
            'delivery_method' => $param['delivery_method'],
            'out_region' => $out_region,
            'out_storehouse' => $param['allocation_type'] == 1 || $param['allocation_type'] == 4 ? $param['out_storehouse'] : $param['out_facilitator_skid_id'],
            'out_facilitator_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_id'] : '',
            'in_region' => $in_region,
            'in_storehouse' => $param['allocation_type'] == 3 || $param['allocation_type'] == 4 ? $param['in_storehouse'] : $param['in_facilitator_skid_id'],
            'in_facilitator_id' => $param['allocation_type'] == 1 || $param['allocation_type'] == 2 ? $param['in_facilitator_id'] : '',
            'status' => 1,
            'remark' => $param['remark'],
            'goods_id' => $param['goods_id'],
            'order_time' => currentTime(),
            'allocation_type' => $param['allocation_type'],
            'pick_up_number' => '',
            'num' => setNum($param['num']),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'dealer_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'creater' => $this->getUserInfo('id'),
            'actual_allocation_time' => empty(trim($param['actual_allocation_time'])) ? date('Y-m-d'): trim($param['actual_allocation_time']),
            'business_type' => intval($param['business_type']),
        ];
        $status_allocation = $id = $this->getModel('ErpNewAllocationOrder')->addAllocationOrder($erp_allocation_data);

        $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$id], '*');

        //记录log
        $log_data = [
            'allocation_id' => $order_info['id'],
            'allocation_order_number' => $order_info['order_number'],
            'log_type' => 1,
            'log_info' => serialize($order_info),
        ];
        $log_status = $this->addAllocationLog($log_data);

        //更新来源库存的配送预留
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
            'object_id' => $order_info['out_storehouse'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
            'object_id' => $order_info['out_storehouse'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
            'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
            'region' => $order_info['out_region'],
            'allocation_reserve_num' => $stock_info['allocation_reserve_num'] + $order_info['num'],
        ];
        $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 5,
            'log_type' => 1,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['num'], $orders);

        if ($status_allocation && $log_status && $stock_status) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '申请成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '操作失败';
        }
        cancelCacheLock('ErpNewAllocation/addErpAllocationOrder');
        return $result;
    }

    /**
     * 编辑调拨单
     * @author senpai
     * @time 2017-05-12
     */
    public function updateErpAllocationOrder($param)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }

        $out_facilitator_skid = $this->getModel('ErpStorehouse')->where(['id'=>trim($param['out_facilitator_skid_id'])])->find();
        $in_facilitator_skid = $this->getModel('ErpStorehouse')->where(['id'=>trim($param['in_facilitator_skid_id'])])->find();

        $where['_string'] = 'goods_id = '.$param['goods_id'].' AND status = 1 AND region IN ('.$param['out_region'].') AND our_company_id = '.session('erp_company_id');
        $region_goods = D('ErpRegionGoods')->where($where)->select();
        $out_storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['out_storehouse']])->find();
        $in_storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['in_storehouse']])->find();
        if (empty($region_goods)) {
            $result['status'] = 101;
            $result['message'] = "该商品出库方所在区域维护中未设置";
            return $result;
        }
        if (trim($param['our_company_id']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择我方公司";
            return $result;
        }
        if (trim($param['delivery_method']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择配送方式";
            return $result;
        }
        if (trim($param['delivery_method']) == 1 && $in_storehourse['type'] == 3) {
            $result['status'] = 101;
            $result['message'] = "调入零售仓的调拨单不允许配送，请检查";
            return $result;
        }
        if (trim($param['allocation_type']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择调拨类型";
            return $result;
        }
        if (trim($param['out_region']) == "") {
            $result['status'] = 101;
            $result['message'] = "去选择来源城市";
            return $result;
        }
        if (trim($param['out_storehouse']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 4)) {
            $result['status'] = 101;
            $result['message'] = "请选择来源仓库";
            return $result;
        }
        if (trim($param['out_facilitator_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
            $result['status'] = 101;
            $result['message'] = "请选择来源服务商";
            return $result;
        }
        if (trim($param['out_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
            $result['status'] = 101;
            $result['message'] = "请选择来源加油网点";
            return $result;
        }
        if ($out_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
            $result['status'] = 101;
            $result['message'] = "来源加油网点无效，请检查";
            return $result;
        }
        if (trim($param['in_region']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择目的城市";
            return $result;
        }
        if (trim($param['in_storehouse']) == "" && (trim($param['allocation_type']) == 3 || trim($param['allocation_type']) == 4)) {
            $result['status'] = 101;
            $result['message'] = "请选择目的仓库";
            return $result;
        }
        if (trim($param['in_facilitator_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
            $result['status'] = 101;
            $result['message'] = "请选择目的服务商";
            return $result;
        }
        if (trim($param['in_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
            $result['status'] = 101;
            $result['message'] = "请选择目的加油网点";
            return $result;
        }
        if ($in_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
            $result['status'] = 101;
            $result['message'] = "目的加油网点无效，请检查";
            return $result;
        }
        if ($param['out_storehouse'] == $param['in_storehouse'] && $param['allocation_type'] == 4) {
            $result['status'] = 101;
            $result['message'] = "相同仓库之间不允许同地调拨！";
            return $result;
        }
        if ($param['business_type'] == '') {
            $result['status'] = 101;
            $result['message'] = "请选择调拨场景！";
            return $result;
        }
//            elseif ($param['out_region'] != $param['in_region'] && $param['allocation_type'] != 2 && $param['allocation_type'] != 4) {
//                $result['status'] = 101;
//                $result['message'] = "非城市仓之间或服务商之间不允许异地调拨！";
//            }
        if (trim($param['goods_id']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择商品代号";
            return $result;
        }
        if (trim($param['num']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入数量";
            return $result;
        }
        if (!is_numeric(trim($param['num']))) {
            $result['status'] = 101;
            $result['message'] = "请输入数字";
            return $result;
        }
        if ($param['allocation_type'] == 1 && $out_storehourse['is_allocation'] != 1) {
            $result['status'] = 101;
            $result['message'] = "该仓库不能做向服务商调拨业务，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 1 || $param['allocation_type'] == 4) && $out_storehourse['region'] != $param['out_region'] && $out_storehourse['whole_country'] != 1) {
            $result['status'] = 101;
            $result['message'] = "来源方仓库不是全国仓库，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 3 || $param['allocation_type'] == 4) && $in_storehourse['region'] != $param['in_region'] && $in_storehourse['whole_country'] != 1) {
            $result['status'] = 101;
            $result['message'] = "目的方仓库不是全国仓库，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 1 || $param['allocation_type'] == 4) && $out_storehourse['status'] == 2) {
            $result['status'] = 101;
            $result['message'] = "来源方仓库已禁用，请检查";
            return $result;
        }
        if (($param['allocation_type'] == 3 || $param['allocation_type'] == 4) && $in_storehourse['status'] == 2) {
            $result['status'] = 101;
            $result['message'] = "目的方仓库已禁用，请检查";
            return $result;
        }
        # else if(!trim($param['actual_allocation_time'])){
        #    $result['status'] = 101;
        #    $result['message'] = "请输入实际调拨时间！";
        # }
        $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$param['id']], '*');
        if($order_info['status'] != 1){
            $result['status'] = 102;
            $result['message'] = "该调拨已不是未审核状态，请刷新重新";
            cancelCacheLock('ErpNewAllocation/updateErpAllocationOrder');
            return $result;
        }

        if(getCacheLock('ErpNewAllocation/updateErpAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/updateErpAllocationOrder', 1);
        M()->startTrans();

        $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$param['id']], '*');

        /** 回滚修改前来源库存的配送预留 */
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
            'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
            'region' => $order_info['out_region'],
            'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'],
        ];
        $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 5,
            'log_type' => 7,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $back_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['num'], $orders);

        $out_region = $param['out_region'];

        $in_region = $param['in_region'];

        //更新调拨单信息
        $erp_allocation_data = [
            'our_company_id' => $param['our_company_id'],
            'delivery_method' => $param['delivery_method'],
            'out_region' => $out_region,
            'out_storehouse' => $param['allocation_type'] == 1 || $param['allocation_type'] == 4 ? $param['out_storehouse'] : $param['out_facilitator_skid_id'],
            'out_facilitator_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_id'] : '',
            'out_facilitator_skid_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_skid_id'] : '',
            'in_region' => $in_region,
            'in_storehouse' => $param['allocation_type'] == 3 || $param['allocation_type'] == 4 ? $param['in_storehouse'] : $param['in_facilitator_skid_id'],
            'in_facilitator_id' => $param['allocation_type'] == 1 || $param['allocation_type'] == 2 ? $param['in_facilitator_id'] : '',
            'status' => 1,
            'remark' => $param['remark'],
            'goods_id' => $param['goods_id'],
            'allocation_type' => $param['allocation_type'],
            'pick_up_number' => '',
            'num' => setNum($param['num']),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'dealer_id' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
            'updater' => $this->getUserInfo('id'),
            'actual_allocation_time' =>  empty(trim($param['actual_allocation_time'])) ? date('Y-m-d'): trim($param['actual_allocation_time']),
            'business_type' => intval($param['business_type']),

        ];
        $status_allocation = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$param['id']],$erp_allocation_data);

        $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>$param['id']], '*');

        //记录log
        $log_data = [
            'allocation_id' => $order_info['id'],
            'allocation_order_number' => $order_info['order_number'],
            'log_type' => 2,
            'log_info' => serialize($order_info),
        ];
        $log_status = $this->addAllocationLog($log_data);

        //更新来源库存的配送预留
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
            'stock_type' => getAllocationStockType($order_info['out_storehouse']),
            'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
            'region' => $order_info['out_region'],
            'allocation_reserve_num' => $stock_info['allocation_reserve_num'] + $order_info['num'],
        ];
        $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 5,
            'log_type' => 7,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['num'], $orders);

        if ($status_allocation && $log_status && $stock_status && $back_stock_status) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '申请成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '操作失败';
        }
        cancelCacheLock('ErpNewAllocation/updateErpAllocationOrder');
        return $result;
    }

    /**
     * 获取一条调拨单信息
     * @author senpai
     * @time 2017-05-12
     */
    public function getErpAlloctionData($id)
    {
        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,ish.storehouse_name as i_storehouse_name,
        g.id as g_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,si.outbound_density as storage_density,so.outbound_density';
        $data = $this->getModel('ErpNewAllocationOrder')->findAllocationOrder(['ao.id'=>$id],$field);
        $where = [
            'id' => $data['out_facilitator_id'] || $data['in_facilitator_id'] ? ['in',[$data['out_facilitator_id'],$data['in_facilitator_id']]] : 0,
        ];
        $supplier_data = $this->getEvent('ErpSupplier')->getSupplierDataField($where,'id,supplier_name');
        if ($data) {
            $cityArr = provinceCityZone()['city'];
            $data['i_facilitator_name'] = $data['in_facilitator_id'] ? $supplier_data[$data['in_facilitator_id']] : '';
            $data['i_skid_name'] = $data['i_storehouse_name'] ? $data['i_storehouse_name'] : '';
            $data['i_storehouse_name'] = $data['i_storehouse_name'] ? $data['i_storehouse_name'] : '';
            $data['o_facilitator_name'] = $data['out_facilitator_id'] ? $supplier_data[$data['out_facilitator_id']] : '';
            $data['o_skid_name'] = $data['o_storehouse_name'] ? $data['o_storehouse_name'] : '';
            $data['o_storehouse_name'] = $data['o_storehouse_name'] ? $data['o_storehouse_name'] : '';
            $data['out_region_font'] = $cityArr[$data['out_region']];
            $data['in_region_font'] = $cityArr[$data['in_region']];
            $data['num'] = getNum($data['num']);
            $data['actual_out_num'] = getNum($data['actual_out_num']);
            $data['actual_in_num'] = getNum($data['actual_in_num']);
            $data['actual_out_num_liter'] = getNum($data['actual_out_num_liter']);
            $data['actual_in_num_liter'] = getNum($data['actual_in_num_liter']);
            $data['status_font'] = AllocationOrderStatus($data['status'],true);
        }

        return $data;
    }

    /**
     * 采购计划显示调拨单
     * @param $param
     * @author senpai
     * @time 2017-05-22
     */
    public function allocationDetail($param = [])
    {
        $where = [
            'ao.out_storehouse' => $param['storehouse_id'],
            'ao.goods_id' => $param['goods_id'],
            'ao.status' => ['in',[1,3,4]],
        ];

        //当前登陆选择的我方公司
        $where['ao.our_company_id'] = session('erp_company_id');

        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,osh.type as o_storehouse_type,ish.storehouse_name as i_storehouse_name,
        ish.type as i_storehouse_type,g.goods_code,g.goods_name,g.source_from,g.grade, g.level';

        $data = $this->getModel('ErpNewAllocationOrder')->getAllocationOrderList($where, $field, $param['start'], $param['length']);

        //log_info("SQL语句:" .$this->erpPurchaseOrder->getLastSql());
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            $is_show_front = $param['export'] && isset($param['export']) ? false : true;
            foreach ($data['data'] as $key => $value) {
                $data['data'][$key]['i_facilitator_name'] = $value['i_facilitator_name'] ? $value['i_facilitator_name'] : '';
                $data['data'][$key]['i_skid_name'] = $value['i_skid_name'] ? $value['i_skid_name'] : '';
                $data['data'][$key]['i_storehouse_name'] = $value['i_storehouse_name'] ? $value['i_storehouse_name'] : '';
                $data['data'][$key]['o_facilitator_name'] = $value['o_facilitator_name'] ? $value['o_facilitator_name'] : '';
                $data['data'][$key]['o_skid_name'] = $value['o_skid_name'] ? $value['o_skid_name'] : '';
                $data['data'][$key]['o_storehouse_name'] = $value['o_storehouse_name'] ? $value['o_storehouse_name'] : '';
                $data['data'][$key]['out_region_font'] = $cityArr[$value['out_region']];
                $data['data'][$key]['in_region_font'] = $cityArr[$value['in_region']];
                $data['data'][$key]['num'] = getNum($value['num']);
                $data['data'][$key]['actual_out_num'] = getNum($value['actual_out_num']);
                $data['data'][$key]['actual_in_num'] = getNum($value['actual_in_num']);

                $data['data'][$key]['status_font'] = AllocationOrderStatus($value['status'], $is_show_front);
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }


    /**-------------------------------------------------------------------------------------
     *
     *
     *  新增代码写这里，在原基础修改的直接在上面代码修改
     *
     * --------------------------------------------------------------------------------------
     */
    /**
     * 调拨单确认出库
     * @author xiaowen
     * @time 2017-10-11
     * @param $id 调拨单ID
     * @param $param 调拨单确认时输入的数据
     * @return array $result
     */
    public function confirmOutStock($param,$files)
    {
        if(empty($param['id'])) {
            $result = [
                'status'  => 2,
                'message' => '调拨单信息有误，请重新操作',
            ];
            return $result;
        }
        $order_info = $this->getOneAllocationOrder(['id'=>$param['id']]);
        $out_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['out_region'], 'our_company_id'=>session('erp_company_id')]);
        //批次信息
        $batch_info = json_decode(htmlspecialchars_decode($param['batch']),true);

        if ($order_info['allocation_type'] == 3) {
            foreach ($batch_info['density'] as $key => $value) {
                if (is_nan($value) || $value < 0.7 || $value > 1) {
                    $result = [
                        'status'  => 2,
                        'message' => '密度未填写，请检查',
                    ];
                    return $result;
                }
            }
        }

        //确认出库密度
        if ($param['out_density'] && !is_nan($param['out_density'])) {
            $goods_out_density = $param['out_density'];
        } elseif ($out_goods_info['density']) {
            $goods_out_density = $out_goods_info['density'];
        } else {
            $goods_out_density = $this->getEvent('ErpGoods')->findGoodsById($order_info['goods_id'])['density_value'];
        }

        # 查询对应的配送单是否已确认
        if(intval($order_info['delivery_method']) == 1){
            $where_shipping = [
                'source_order_number' => $order_info['order_number'],
                'order_status'        => 10,
            ];
            $shipping_order = $this->getModel('ErpShippingOrder')->findShippingOrder($where_shipping);
        }

        //============================================进行升转吨===================================================
        if(($param['actual_out_num_liter'] && isset($param['actual_out_num_liter']))){

            //$param['actual_out_num'] = round(literToTon($param['actual_out_num_liter'], $goods_out_density), 4);
            log_info("转化后实际出库数量：". $param['actual_out_num']);

        }
        //==================================================end====================================================

        //验证数据
        if ($order_info['status'] != 10) {
            $result = [
                'status'=>3,
                'message'=>'调拨单不是已确认状态，无法操作',
            ];
            return $result;
        }
        if ($order_info['outbound_status'] != 2) {
            $result = [
                'status'=>5,
                'message'=>'调拨单已确认出库，无法再操作',
            ];
            return $result;
        }
        if (empty($param['actual_out_num']) || $param['actual_out_num'] == 0) {
            $result = [
                'status'=>3,
                'message'=>'请输入实际出库数量',
            ];
            return $result;
        }
        if(bccomp(setNum(trim($param['actual_out_num'])), $order_info['num']) == 1 ){  //setNum(trim($param['actual_out_num'])) > $order_info['num']
            $result = [
                'status'=>4,
                'message'=>'实际出库数量不能大于调拨数量',
            ];
            return $result;
        }
//        if( $order_info['allocation_type'] == 2 ) {  //setNum(trim($param['actual_out_num'])) > $order_info['num']
//            $result = [
//                'status' => 6,
//                'message' => '不允许服务商到服务商调拨单进行确认出库',
//            ];
//            return $result;
//        }
        if(intval($order_info['allocation_type']) == 1 && intval($order_info['delivery_method']) == 1 && $order_info['is_shipping'] == 2 && getCompareOilTime($order_info['create_time'])){  //setNum(trim($param['actual_out_num'])) > $order_info['num']
            $result = [
                'status'=>7,
                'message'=>'请先完成配送单并确认！',
            ];
            return $result;
        }
        if(intval($order_info['delivery_method']) == 1 && empty($shipping_order) && getCompareOilTime($order_info['create_time'])){  //setNum(trim($param['actual_out_num'])) > $order_info['num']
            $result = [
                'status'=>8,
                'message'=>'请先完成配送单并确认！',
            ];
            return $result;
        }


        $upload_status = true;
        $error_photo = [];
        $attachment = [];
        if (!empty($files)) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if($value['size'] > 2*1024*1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
                } else {
                    continue;
                }
            }

            //上传文件
            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_out_attach']['src'];
                //判断该用户文件夹是否已经有这个文件夹
                if (!file_exists($user_path)) {
                    mkdir($user_path, 0777, true);
                }
                $current_date = date('Y-m-d');
                if (!is_dir($user_path . $current_date)) {
                    mkdir($user_path . $current_date, 0777, true);
                }
                $current_date = date('Y-m-d');
                //后缀
                $type = substr($value['name'],strripos($value['name'],'.')+1);
                $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
                $upload_status = move_uploaded_file($uploaded_file, $user_path . $file_name);
                //已上传文件，如果操作失败要删除
                array_push($error_photo,$file_name);
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        }

        if(getCacheLock('ErpNewAllocation/confirmOutStock'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/confirmOutStock', 1);

        M()->startTrans();

        //=============================================业务单据处理start=================================================

        //保存调拨单数据
        $order_data = [
            'actual_out_num' => setNum($param['actual_out_num']),
            'actual_out_num_liter' => setNum($param['actual_out_num_liter']),
            'outbound_status' => 1, //调拨单出库状态更新为已出库
            'update_time' =>currentTime(),
            'status' => 10,
        ];
        $order_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$param['id']], $order_data);
        //保存调拨单操作日志
        $log_data = [
            'allocation_id' => $order_info['id'],
            'allocation_order_number' => $order_info['order_number'],
            'log_info' => serialize($order_info),
            'log_type' => 8,
        ];
        $log_status = $this->addAllocationLog($log_data);

        /* -------------------------------------- 业务单据处理end ---------------------------------------------------- */


        /* -------------------------------------- 出库方库存逻辑操作start -------------------------------------------- */

        /* -------------------------------------- 确定该订单影响哪个库存，并查出该库存的信息 -------------------------- */
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['out_storehouse'],
            'stock_type' =>getAllocationStockType($order_info['out_storehouse']),
            'region' => $order_info['out_region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //判断出库方的物理库存是否满足实际出库数量
        if(intval($stock_info['stock_num']) < intval($order_data['actual_out_num'])){
            M()->rollback();
            cancelCacheLock('ErpNewAllocation/confirmOutStock');
            return  $result = [
                'status' =>0,
                'message' =>'出库方物理库存不足,无法确认调拨单',
            ];
        }

        /* ------------------------------------------ 生成出库单 start ----------------------------------------------- */

        //处理批次数据，根据选择的批次来生成对应的出库单
        $batch_status_all = true;
        $index = 0;
        $batch_num_total = 0;
        $stock_out_count = count($batch_info['ton']) - 1;
        //剩余未扣减的待提数量
        $allocation_wait_num = $order_info['num'];
        foreach ($batch_info['ton'] as $key=>$value) {
            $batch_id = substr($key,6);
            $batch_num = round($value,4);
            $batch_num_total += $batch_num;
            $batch = $this->getModel('ErpBatch')->where(['id'=>$batch_id])->find();
            $density = isset($batch_info['density']['density_'.$batch_id]) ? $batch_info['density']['density_'.$batch_id] : $goods_out_density;
            //出库单和批次建立关联
            $stock_out_data[$index] = array_merge([
                'outbound_type' => 2,
                'outbound_code' => erpCodeNumber(7)['order_number'],
                'outbound_status' => 10,
                'source_number' => $order_info['order_number'],
                'source_object_id' => $order_info['id'],
                'goods_id' => $order_info['goods_id'],
                'our_company_id' => $order_info['our_company_id'],
                'outbound_num' => setNum($batch_num),
                'actual_outbound_num' => setNum($batch_num),
                'outbound_density' => $density,
                'create_time' => currentTime(),
                'dealer_id' => $this->getUserInfo('id'),
                'dealer_name' => $this->getUserInfo('dealer_name'),
                'creater_id' => $this->getUserInfo('id'),
                'creater_name' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime(),
                'outbound_remark' => trim($param['outbound_remark']),
                'storehouse_id' => $order_info['out_storehouse'],
                'stock_type' => getAllocationStockType($order_info['out_storehouse']),
                'region' => $order_info['out_region'],
                'batch_sys_bn' => $batch['sys_bn'],
                'batch_id' => $batch_id,
            ],$attachment);

            if (!isset($price) || !isset($log_id)) {
                //通过java接口获取出库单成本 edit xiaowen 2018-2-4
                $stock_out_cost = getStockOutCost($stock_out_data[$index]);

                // 如果成本值为空，则return edit qianbin 2018-03-09
                if ($stock_out_cost['price']===null) {
                    M()->rollback();
                    cancelCacheLock('ErpNewAllocation/confirmOutStock');
                    return  [
                        'status' => 99,
                        'message' => '成本获取失败，请联系管理员！',
                    ];
                }
                $price = $stock_out_cost['price'];
                $log_id = $stock_out_cost['price'];
            }

            $stock_out_data[$index]['cost'] = isset($price) ? $price : 0;
            $stock_out_data[$index]['cost_log_id'] = isset($log_id) ? $log_id : 0;

            //服务商出的调拨单影响库存对应入库单的可用数量
            if ($order_info['allocation_type'] == 3) {
                $stock_in_result = $this->influenceStockIn($stock_out_data[$index]);
                $stock_out_data[$index]['deduction_num'] = $stock_in_result['deduction_num'];
            } else {
                $stock_in_result['status'] = true;
            }

            $stock_out_status = $this->getModel('ErpStockOut')->add($stock_out_data[$index]);

            //更新批次数据
            $batch['balance_num'] -= setNum($batch_num);
            $batch_data = [
                'batch_id' => $batch_id,
                'change_balance_num' => setNum($batch_num) * -1,
                'change_reserve_num' => 0,
                'change_type' => 2,
                'change_number' => $stock_out_data[$index]['outbound_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);
            if($batch_result['status'] != 1){
                M()->rollback();
                cancelCacheLock('ErpNewAllocation/confirmOutStock');
                return  $result = [
                    'status' =>0,
                    'message' =>'出库方批次可用不足,无法确认调拨单',
                ];
            }
            $batch_status_all = $batch_result['status'] == 1 && $batch_status_all ? true : false;

            //批量操作出库单的时候，若到最后一笔还未扣减完调拨待提，则一次性全部操作
            if ($index == $stock_out_count) {
                $wait_num = $allocation_wait_num;
            } else {
                $wait_num = $stock_out_data[$index]['actual_outbound_num'];
            }

            //------------------组装库存表的字段值--------------------------
            $data = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['out_storehouse'],
                'stock_type' => getAllocationStockType($order_info['out_storehouse']),
                'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
                'region' => $order_info['out_region'],
                'allocation_wait_num' => $stock_info['allocation_wait_num'] - $wait_num, //减少出库方配货待提 edit 因为改造后，非服务商到服务商调拨单 先将调拨预留转化到调拨待提了，确认出库应改为减调拨待提
                'stock_num' => $stock_info['stock_num'] - $stock_out_data[$index]['actual_outbound_num'], //减少出库方物理库存
            ];

            $stock_info['allocation_wait_num'] = $data['allocation_wait_num']; //重置最新的预留库存
            $stock_info['stock_num'] = $data['stock_num']; //重置最新的预留库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $stock_out_data[$index]['outbound_code'],
                'object_type' => 3,
                'log_type' => 2,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_out_data[$index]['actual_outbound_num'], $orders);

            $allocation_wait_num -= $stock_out_data[$index]['actual_outbound_num'];
            $index++;
        }
        /* -------------------------------------------- 生成出库单 end ----------------------------------------------- */

        /* --------------------------------------- 出库方库存逻辑操作 end -------------------------------------------- */


        /* --------------------------------------- 入库方库存逻辑操作start ------------------------------------------- */

        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'region' => $order_info['in_region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'facilitator_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 2 ? $order_info['in_facilitator_id'] : '',
            'region' => $order_info['in_region'],
            // 'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'],
            'transportation_num' => $stock_info['transportation_num'] + $order_data['actual_out_num'], //出库方的实际出库数量加到入库方的在途
        ];

        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的预留库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 5,
            'log_type' => 3,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_data['actual_out_num'], $orders);

        //===============入库方库存逻辑操作 end================================================================

//        $result_front = ['status' => 1 , 'message' => ''];
//        # 更新收油单状态
//        if(intval($order_info['allocation_type']) == 1 && intval($order_info['delivery_method']) == 1 && getCompareOilTime($order_info['create_time'])){
//            # front 更新 收油单状态 qianbin 2018-03-22
//            # 1. 单据：调拨单
//            # 2. 类型：城市仓->服务商
//            #  front 更新 收油单状态 qianbin 2018-03-22
//            $params = [
//                'allocation_order_number'  => $order_info['order_number'],
//                'shipping_order_number'    => $shipping_order['order_number'],
//                'stock_out_time'           => $stock_out_data[0]['audit_time'],
//                'create_name'              => $this->getUserInfo('dealer_name'),
//                'stock_out_number'         => setNum($batch_num_total),
//                'stock_out_weight'         => setNum($batch_num_total),
//            ];
//            $result_front = updateOilReceiptStatus($params);
//
//            # end ---------------
//        }
        # 更新调拨单是否生成配送需求字段
        # 更新配送单出库单号和实际出库数量
        $shipping_order_status = true;
        if(intval($order_info['delivery_method']) == 1 && getCompareOilTime($order_info['create_time'])){
            $where =[
                'order_number' => $shipping_order['order_number']
            ];

            $data = [
                'source_number'         => $stock_out_data[0]['outbound_code'],
                'actual_shipping_num'   => setNum($batch_num_total),
                'updater'               => $this->getUserInfo('id'),
                'update_time'           => currentTime(),
            ];
            $shipping_order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder($where, $data);
        }

        if($order_status && $log_status && $out_stock_status && $stock_out_status && $in_stock_status && $stock_in_result['status'] && $shipping_order_status && $upload_status){
            M()->commit();
            $result = [
                'status' =>1,
                'message' =>'操作成功',
            ];
        }else{
            //操作失败后删除文件
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            M()->rollback();
            $result = [
                'status' =>0,
                'message' =>'操作失败',
            ];
        }


        cancelCacheLock('ErpNewAllocation/confirmOutStock');
        return $result;
    }

    /**
     * 服务商出的调拨单影响库存对应入库单的可用数量
     * @author guanyu
     * @time 2018-03-12
     * @param $id
     * @param $param
     * @return array $result
     */
    public function influenceStockIn($stock_out)
    {
        $where = [
            'goods_id'          => $stock_out['goods_id'],
            'our_company_id'    => $stock_out['our_company_id'],
            'storehouse_id'     => $stock_out['storehouse_id'],
            'stock_type'        => $stock_out['stock_type'],
            'region'            => $stock_out['region'],
            'storage_status'    => 10,
            'is_reverse'        => 2,
            'reversed_status'   => 2,
            'balance_num'       => ['neq',0],
        ];
        $stock_in = $this->getModel('ErpStockIn')->where($where)->order('audit_time')->select();

        //未冲减数量
        $outbound_num = $stock_out['actual_outbound_num'];
        $stock_in_numbers = [];
        $stock_in_status_all = true;
        //循环扣减入库单可用
        foreach ($stock_in as $key => $value) {
            if ($outbound_num == 0) {
                break;
            } elseif ($outbound_num <= $value['balance_num']) {
                $stock_in_numbers[$value['storage_code']] = $outbound_num;
                $new_balance_num = $value['balance_num'] - $outbound_num;
                $new_balance_num_litre = $value['balance_num_litre'] - tonToLiter($outbound_num,$value['outbound_density']);
                $outbound_num = 0;
            } elseif ($outbound_num > $value['balance_num']) {
                $outbound_num -= $value['balance_num'];
                $stock_in_numbers[$value['storage_code']] = $value['balance_num'];
                $new_balance_num = 0;
                $new_balance_num_litre = 0;
            }
            //修改入库单可用数量
            $stock_in_data = [
                'update_time'   => currentTime(),
                'balance_num'   => $new_balance_num,
                'balance_num_litre'   => $new_balance_num_litre,
                'deduction_num'   => $value['deduction_num'] + $stock_in_numbers[$value['storage_code']],
            ];
            $stock_in_status = $this->getModel('ErpStockIn')->saveStockIn(['id' => $value['id']],$stock_in_data);
            $deduction_data = [
                'source_stock_in_number' => $value['storage_code'],
                'type' => 2,
                'outbound_code' => $stock_out['outbound_code'],
                'before_balance_num' => $new_balance_num + $stock_in_numbers[$value['storage_code']],
                'after_balance_num' => $new_balance_num,
                'deduction_num' => $stock_in_numbers[$value['storage_code']],
                'deduction_type' => 1,
                'create_time' => currentTime(),
            ];
            log_info(print_r($deduction_data, true));
            $deduction_status = $this->getModel('ErpStockInDeduction')->add($deduction_data);
            $stock_in_status_all = $stock_in_status && $deduction_status && $stock_in_status_all ? true : false;
        }
        //冲减数量
        $deduction_num = $stock_out['actual_outbound_num'] - $outbound_num;
        return ['status' => $stock_in_status_all,'deduction_num' => $deduction_num];
    }

    /**
     * 调拨单确认入库
     * @author xiaowen
     * @time 2017-10-11
     * @param $id 调拨单ID
     * @param $param 调拨单确认时输入的数据
     * @return array $result
     */
    public function confirmInStock($param,$files)
    {
        if(empty($param['id'])) {
            $result = [
                'status'=>2,
                'message'=>'调拨单信息有误，请重新操作',
            ];
            return $result;
        }
        //获取订单信息
        $order_info = $this->getOneAllocationOrder(['id'=>$param['id']]);
        $in_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['in_region'], 'our_company_id'=>session('erp_company_id')]);

        //批次信息
        $batch_info = json_decode(htmlspecialchars_decode($param['batch']),true);
        if ($order_info['allocation_type'] == 1) {
            foreach ($batch_info['density'] as $key => $value) {
                if (is_nan($value) || $value < 0.7 || $value > 1) {
                    $result = [
                        'status'  => 2,
                        'message' => '密度未填写，请检查',
                    ];
                    return $result;
                }
            }
        }

        //获取密度
        if ($param['in_density']) {
            $goods_in_density = $param['in_density'];
        } elseif ($in_goods_info['density']) {
            $goods_in_density = $in_goods_info['density'];
        } else {
            $goods_in_density = $this->getEvent('ErpGoods')->findGoodsById($order_info['goods_id'])['density_value'];
        }

        # 添加变量，标记是否为城市仓->服务商（配送）
        # 1 是城市仓->服务商（配送）
        # 2 除1以外的类型
        $order_reveice = intval($order_info['allocation_type']) == 1 && intval($order_info['delivery_method'] == 1) ? 1 : 2 ;

        $check_reveice_order = ['status' => 1 , 'message' => '请求正常！'];
        $shipping_status     = [];
        $is_white            = [];
        if($order_reveice == 1){
            # 判断白名单 ， 如果未出现在白名单，则可以直接入库
            $is_white = $this->getModel('ErpFacilitatorSkidList')->where(['facilitator_skid_id' => $order_info['in_storehouse'], 'status' => 1 ])->find();

            # 确认入库之前，判断front收油是否完成 qianbin 2018-03-21
            if(!empty($is_white)){
                # 判断配送单是否完成
                $shipping_status = $this->getModel('ErpShippingOrder')->field('order_status,distribution_status')->where(['source_order_number'=>trim($order_info['order_number']),'order_status' => ['neq' , 2]])->find();
                $check_reveice_order = checkOilReceiptStatus(['allocation_order_number' => $order_info['order_number']]) ;
            }
        }
        # end

        if ($order_info['status'] != 10) {
            $result = [
                'status'=>3,
                'message'=>'调拨单不是已确认状态，无法操作',
            ];
            return $result;
        }
        if($order_info['storage_status'] != 2){
            $result = [
                'status'=>5,
                'message'=>'调拨单已确认入库，无法再操作',
            ];
            return $result;
        }
        if(!trim($param['actual_in_num'])){
            $result = [
                'status'=>3,
                'message'=>'请输入实际入库数量',
            ];
            return $result;
        }
        //实际业务中允许入库大于出库（密度原因导致）【只针对城市仓 => 服务网点】 edit xiaowen 2018-6-22
        if(bccomp(setNum(trim($param['actual_in_num'])), $order_info['actual_out_num']) == 1 && $order_info['allocation_type'] != 1){  //实际入库数量不能大于实际出库数量（也是确认出库转化到入库方的在途数量）
            $result = [
                'status'=>4,
                'message'=>'实际入库数量不能大于实际出库数量',
            ];
            return $result;
        }
        if($order_reveice == 1 && !empty($is_white) && empty($shipping_status) && getCompareOilTime($order_info['create_time'])){

            # 调拨单（配送）入库校验
            # 1.生成收油单并且完成配送
            # 2.front收油单已完成状态

            $result = [
                'status' => 8,
                'message' => '该笔调拨单未生成配送单，请先生成配送单！',
            ];
            return $result;
        }
        if($order_reveice == 1 && !empty($is_white) && $check_reveice_order['status'] != 1 && getCompareOilTime($order_info['create_time'])){
            $result = [
                'status' => 7,
                'message' => '服务商未完成在线收油，无法入库！',
            ];
            if($check_reveice_order['status'] == 2) $result['message'] = '服务商已停止在线收油，请红冲该笔调拨单！';
            return $result;
        }
        if($order_reveice == 1 && !empty($is_white) &&  intval($shipping_status['distribution_status']) != 3 && getCompareOilTime($order_info['create_time'])){
            $result = [
                'status' => 10,
                'message' => '该笔调拨单对应的配送单未完成配送，请先完成配送单！',
            ];
            return $result;
        }

        $upload_status = true;
        $error_photo = [];
        $attachment = [];
        if (!empty($files)) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if($value['size'] > 2*1024*1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
//                    if ($value['type'] != "image/jpeg" && $value['type'] != 'image/gif' && $value['type'] != 'image/png') {
//                        $result = [
//                            'status' => 5,
//                            'message' => '文件格式上传有误，只能上传图片文件'
//                        ];
//                        return $result;
//                    }
                } else {
                    continue;
                }
            }

            //上传文件
            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_in_attach']['src'];
                //判断该用户文件夹是否已经有这个文件夹
                if (!file_exists($user_path)) {
                    mkdir($user_path, 0777, true);
                }
                $current_date = date('Y-m-d');
                if (!is_dir($user_path . $current_date)) {
                    mkdir($user_path . $current_date, 0777, true);
                }
                $current_date = date('Y-m-d');
                //后缀
                $type = substr($value['name'],strripos($value['name'],'.')+1);
                $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
                $upload_status = move_uploaded_file($uploaded_file, $user_path . $file_name);
                //已上传文件，如果操作失败要删除
                array_push($error_photo,$file_name);
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        }
        // cancelCacheLock('ErpNewAllocation/confirmInStock');
        if(getCacheLock('ErpNewAllocation/confirmInStock'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpNewAllocation/confirmInStock', 1);

        M()->startTrans();

        $storage_status = 1;
        foreach ($batch_info['ton'] as $batch_key => $batch_value) {
            $batch_id = substr($batch_key, 6);
            $loss_num = $batch_info['loss']['loss_' . $batch_id];
            if ($loss_num > 0) {
                $storage_status = 3;
            }
        }
        //保存调拨单数据
        $order_data = [
            'actual_in_num' => setNum($param['actual_in_num']),
            'actual_in_num_liter' => setNum($param['actual_in_num_liter']),
            'pick_up_number' => $param['pick_up_number'],
            'storage_status' => $storage_status, //调拨单入库状态更新为已入库，若有损耗，则为部分入库
            'update_time' =>currentTime(),
            'storage_remark' => trim($param['storage_remark']),
        ];
        $order_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id'=>$param['id']], $order_data);
        //保存调拨单操作日志
        $log_data = [
            'allocation_id' => $order_info['id'],
            'allocation_order_number' => $order_info['order_number'],
            'log_info' => serialize($order_info),
            'log_type' => 9,
        ];
        $log_status = $this->addAllocationLog($log_data);

        //===============生成入库单====================================================================

        //================取出库单成本字段 ==========================//
        $cost = $this->getModel('ErpStockOut')->where(['source_number' => trim($order_info['order_number']) , 'outbound_type' => 2 ])->getField('cost');
        //================取出库单成本字段 end ======================//

        //根据选择的批次数据，生成入库单
        $batch_status_all = true;
        $stock_in_status_all = true;
        $in_stock_status_all = true;
        $index = 0;
        $batch_num_total = 0;

        foreach ($batch_info['ton'] as $batch_key => $batch_value) {
            $batch_id = substr($batch_key,6);
            $batch_num = $batch_value;
            $density = isset($batch_info['density']['density_'.$batch_id]) ? $batch_info['density']['density_'.$batch_id] : $goods_in_density;
            $batch_num_litre = isset($batch_info['litre']['litre_'.$batch_id]) ? $batch_info['litre']['litre_'.$batch_id] : tonToLiter($batch_num,$density);
            $loss_num = $batch_info['loss']['loss_'.$batch_id];
            $batch_num_total += $batch_num;
            $storage_code = erpCodeNumber(8)['order_number'];
            $batch = $this->getModel('ErpBatch')->where(['id'=>$batch_id])->find();

            $stock_in_data = array_merge([
                'storage_type' => 2,
                'storage_code' => $storage_code,
                'storage_status' => $loss_num == 0 ? 10 : 1,
                'source_number' => $order_info['order_number'],
                'source_object_id' => $order_info['id'],
                'our_company_id' => $order_info['our_company_id'],
                'goods_id' => $order_info['goods_id'],
                'storage_num' => setNum($batch_num),
                'actual_storage_num' => setNum($batch_num),
                'outbound_density' => $density,
                'creater_id' => $this->getUserInfo('id'),
                'create_time' => currentTime(),
                'dealer_id' => $this->getUserInfo('id'),
                'dealer_name' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime(),
                'storage_remark' => trim($param['storage_remark']),
                'storehouse_id' => $order_info['in_storehouse'],
                'stock_type' => getAllocationStockType($order_info['in_storehouse']),
                'region' => $order_info['in_region'],
                'price'  => empty($cost) ? '0' : trim($cost),
                'actual_storage_num_litre'  => setNum($batch_num_litre),
                'balance_num'  => setNum($batch_num),
                'balance_num_litre'  => setNum($batch_num_litre),
                'batch_sys_bn' => $batch['sys_bn'],
                'cargo_bn_id' => $batch['cargo_bn_id'],
            ],$attachment);
            //log_info(print_r($stock_in_data,true));
            $stock_in_status = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
            $stock_in_status_all = $stock_in_status && $stock_in_status_all ? true : false;

            $loss_data = [];
            //如果有损耗，则生成损耗单
            if ($loss_num != 0) {
                $theoretically_reasonable_loss_num = ($loss_num + $batch_num) * lossRatioMath($param['loss_ratio']);
                //YF更改 （合理损耗数量）
                $reasonable_loss_num = $batch_info['reasonable_loss']['reasonable_loss'.$batch_id];
                // (超损数量)
                $exceed_loss_num = $batch_info['supper_loss']['exceed_loss'.$batch_id];
                if ( (float)$loss_num != (float)(round($reasonable_loss_num + $exceed_loss_num,4)) ) {
                    M()->rollback();
                    cancelCacheLock('ErpNewAllocation/confirmInStock');
                    return ['status' => 13,'message' => '请填写正确的合理损耗及超损！'];
                }
                // $reasonable_loss_num = $loss_num < $theoretically_reasonable_loss_num ? $loss_num : $theoretically_reasonable_loss_num;
                $loss_data[] = [
                    'source_number' => $storage_code,
                    'type' => 2,
                    'loss_num' => setNum(round($loss_num,4)),
                    'loss_ratio' => $param['loss_ratio'],
                    'reasonable_loss_num' => setNum(round($reasonable_loss_num,4)),
                    'exceed_loss_num' => setNum(round($loss_num - $reasonable_loss_num,4)),
                    'price' => $cost,
                    'responsible_party' => 0,
                ];
                $loss_status = $this->getEvent('ErpLoss')->addLossOrder($loss_data);
                if ($loss_status['status'] == 1) {
                    continue;
                } else {
                    //操作失败后删除文件
                    foreach ($error_photo as $value) {
                        unlink($user_path.$value);
                    }
                    M()->rollback();
                    $result = [
                        'status' =>0,
                        'message' => $loss_status['message'],
                    ];

                    cancelCacheLock('ErpNewAllocation/confirmInStock');
                    return $result;
                }
            }

            //===============入库方库存逻辑操作==================================================================
            //调拨入网点时判断：同网点不同品相是否有负库存，若有，则优先冲减负库存后再入库
            if (in_array($order_info['allocation_type'],[1,2])) {
                $stock_out_where = [
                    '_string' => 'deduction_num < actual_outbound_num',
                    'storehouse_id' => $order_info['in_storehouse'],
                    'stock_type' => 4,
                    'region' => $order_info['in_region'],
                    'goods_id' => $order_info['goods_id'],
                    'our_company_id' => $order_info['our_company_id'],
                    'outbound_status' => 10,
                    'reversed_status' => 2,
                    'is_reverse' => 2,
                    'retail_inner_order_number' => '',
                ];
                $stock_out_data_deduction = $this->getModel('ErpStockOut')->where($stock_out_where)->select();
                if (count($stock_out_data_deduction) > 0) {
                    $influence_stock_num = 0;
                    foreach ($stock_out_data_deduction as $key => $value) {
                        $need_deduction_num = $value['actual_outbound_num'] - $value['deduction_num'];
                        if ($stock_in_data['actual_storage_num'] == 0) {
                            $change_num = 0;
                            break;
                        } elseif ($stock_in_data['actual_storage_num'] <= $need_deduction_num) {
                            $change_num = $stock_in_data['actual_storage_num'];
                            $stock_out_data_deduction[$key]['deduction_num'] += $stock_in_data['actual_storage_num'];
                            $stock_in_data['actual_storage_num'] = 0;
                        } elseif ($stock_in_data['actual_storage_num'] > $need_deduction_num) {
                            $change_num = $need_deduction_num;
                            $stock_in_data['actual_storage_num'] -= $need_deduction_num;
                            $stock_out_data_deduction[$key]['deduction_num'] = $value['actual_outbound_num'];
                        }

                        $influence_stock_num += $change_num;
                        $deduction_data = [
                            'source_stock_in_number' => $stock_in_data['storage_code'],
                            'type' => 2,
                            'outbound_code' => $value['outbound_code'],
                            'before_balance_num' => $stock_in_data['actual_storage_num'] + $change_num,
                            'after_balance_num' => $stock_in_data['actual_storage_num'],
                            'deduction_num' => $change_num,
                            'deduction_type' => 1,
                            'create_time' => currentTime(),
                        ];
                        $this->getModel('ErpStockOut')->where(['outbound_code'=>$stock_out_data_deduction[$key]['outbound_code']])->save($stock_out_data_deduction[$key]);
                        $this->getModel('ErpStockInDeduction')->add($deduction_data);
                    }
                }
                $write_downs_where = [
                    'object_id' => $order_info['in_storehouse'],
                    'stock_type' => 4,
                    'region' => $order_info['in_region'],
                    'stock_num' => ['lt',0],
                    'our_company_id' => $order_info['our_company_id'],
                    '_string' => 'stock_num + deduction_num < 0',
                ];
                $write_downs_stock = $this->getModel('ErpStockSkidData')->where($write_downs_where)->select();

                if (count($write_downs_stock) > 0 && $stock_in_data['actual_storage_num'] > 0) {
                    foreach ($write_downs_stock as $key => $value) {
                        $need_deduction_stock_num = $value['stock_num'] + $value['deduction_num'];
                        if ($stock_in_data['actual_storage_num'] == 0) {
                            break;
                        } elseif ($stock_in_data['actual_storage_num'] + $need_deduction_stock_num <= 0) {
                            $change_num = $stock_in_data['actual_storage_num'];
                            $write_downs_stock[$key]['stock_num'] += $stock_in_data['actual_storage_num'];
                            $write_downs_stock[$key]['available_num'] = $write_downs_stock[$key]['stock_num'];
                            $stock_in_data['actual_storage_num'] = 0;
                        } elseif ($stock_in_data['actual_storage_num'] + $need_deduction_stock_num > 0) {
                            $change_num = abs($need_deduction_stock_num);
                            $stock_in_data['actual_storage_num'] += $write_downs_stock[$key]['stock_num'];
                            $write_downs_stock[$key]['stock_num'] = 0;
                            $write_downs_stock[$key]['available_num'] = 0;
                        }

                        $orders = [
                            'object_number' => $stock_in_data['storage_code'],
                            'object_type' => 4,
                            'log_type' => 2,
                        ];

                        $write_downs_stock_data = $write_downs_stock[$key];
                        $write_downs_stock_data['id'] = $write_downs_stock_data['stock_id'];
                        unset($write_downs_stock_data['stock_id']);
                        unset($write_downs_stock_data['goods_code']);
                        unset($write_downs_stock_data['deduction_num']);

                        //修改被冲减的库存记录
                        $this->getEvent('ErpStock')->saveStockInfo($write_downs_stock_data, $change_num, $orders);
                        //修改网点期初冲减数量
                        $this->getModel('ErpStockSkidData')->where(['stock_id'=>$write_downs_stock[$key]['stock_id']])->setInc('deduction_num',$change_num);
                        //插入冲减记录
                        $deduction_data = [
                            'source_stock_in_number' => $stock_in_data['storage_code'],
                            'type' => 1,
                            'stock_id' => $write_downs_stock[$key]['stock_id'],
                            'before_balance_num' => $stock_in_data['actual_storage_num'] + $change_num,
                            'after_balance_num' => $stock_in_data['actual_storage_num'],
                            'deduction_num' => $change_num,
                            'deduction_type' => 1,
                            'create_time' => currentTime(),
                        ];
                        $this->getModel('ErpStockInDeduction')->add($deduction_data);
                    }
                }

                //冲减负库存结束后，修改原入库单的可用数量
                $stock_in_data['balance_num'] = $stock_in_data['actual_storage_num'];
                $stock_in_data['balance_num_litre'] = $stock_in_data['balance_num'] / $stock_in_data['outbound_density'] * 1000;
                $stock_in_data['deduction_num'] = setNum($batch_num) - $stock_in_data['balance_num'];
                $this->getModel('ErpStockIn')->where(['storage_code'=>$stock_in_data['storage_code']])->save($stock_in_data);
            }

            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['in_storehouse'],
                'stock_type' => getAllocationStockType($order_info['in_storehouse']),
                'region' => $order_info['in_region'],
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //入库方变动前的物理库存 edit xiaowen 2018-2-9
            $beforeNum = $stock_info['stock_num'];
            $stockId = $stock_info['id'];
            //------------------组装库存表的字段值--------------------------
            //对应批次的出库单信息
            $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number'=>$order_info['order_number'],'batch_sys_bn'=>$stock_in_data['batch_sys_bn'],'reversed_status'=>2,'is_reverse'=>2])->find();
            $data = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['in_storehouse'],
                'stock_type' => getAllocationStockType($order_info['in_storehouse']),
                'facilitator_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 2 ? $order_info['in_facilitator_id'] : '',
                'region' => $order_info['in_region'],
                'transportation_num' => $stock_info['transportation_num'] - $stock_out_info['actual_outbound_num'], //入库方在途 - 实际出库数量（也是入库方在途数量）
                'stock_num' => $stock_info['stock_num'] + $stock_in_data['actual_storage_num'], //入库方的实际出库数量加到入库方的物理
            ];

            $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的预留库存
            $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $stock_in_data['storage_code'],
                'object_type' => 4,
                'log_type' => 2,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stock_in_data['actual_storage_num'], $orders);
            $in_stock_status_all = $in_stock_status && $in_stock_status_all ? true : false;

            //查询调入方是否有该批次数据
            $where = [
                'storehouse_id' => $order_info['in_storehouse'],
                'goods_id' => $order_info['goods_id'],
                'our_company_id' => $order_info['our_company_id'],
                'stock_type' => getAllocationStockType($order_info['in_storehouse']),
                'sys_bn' => $batch['sys_bn'],
            ];
            $in_batch = $this->getModel('ErpBatch')->where($where)->find();
            if (!empty($in_batch)) {
                //更新批次数据
                $batch_data = [
                    'batch_id' => $in_batch['id'],
                    'change_total_num' => setNum($batch_num),
                    'change_balance_num' => $stock_in_data['actual_storage_num'],
                    'change_reserve_num' => 0,
                    'change_type' => 1,
                    'change_number' => $storage_code,
                ];
                $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);
                $batch_status_all = $batch_result['status'] == 1 && $batch_status_all ? true : false;
                $new_batch_id = $in_batch['id'];
            } else {
                //新增批次数据
                $batch_data = [
                    'sys_bn' => $batch['sys_bn'],
                    'cargo_bn_id' => $batch['cargo_bn_id'],
                    'goods_id' => $order_info['goods_id'],
                    'storehouse_id' => $order_info['in_storehouse'],
                    'our_company_id' => $order_info['our_company_id'],
                    'region' => $order_info['in_region'],
                    'stock_type' => getAllocationStockType($order_info['in_storehouse']),
                    'total_num' => setNum($batch_num),
                    'balance_num' => $stock_in_data['actual_storage_num'],
                    'reserve_num' => 0,
                    'status' => 1,
                    'data_source' => 1,
                    'data_source_number' => $storage_code,
                ];
                $batch_status = $this->getEvent('ErpBatch')->AddBatch($batch_data);
                $batch_status_all = $batch_status['status'] && $batch_status_all ? true : false;
                $new_batch_id = $batch_status['batch_id'];
            }
            $stock_in_data['batch_id'] = $new_batch_id;
            $this->getModel('ErpStockIn')->where(['storage_code'=>$stock_in_data['storage_code']])->save($stock_in_data);

            $index++;
        }

        //===============end 生成入库单================================================================

        //===============入库方库存逻辑操作 end================================================================

//        # =====================================零售收油单=====================
//        $result_front = ['status' => 1 , 'message' => ''];
//        # 确认入库 - 收油单接口
//        # 1. 城市仓->服务商（配送） ， 更新收油单信息，极光推送
//        # 2. 城市仓->服务商（自提）， 推送信息，生成收油记录，极光推送
//
//        if(intval($order_info['allocation_type']) == 1) {
//            $erp_goods_info = $this->getModel('ErpGoods')->field('goods_name,source_from,grade,level')->where(['id' => $order_info['goods_id']])->find();
//            $num_kg = getNum($order_data['actual_in_num']) * 1000;
//            if (intval($order_info['delivery_method'] == 1)) {
//                $params = [
//                    'allocation_order_number'   => trim($order_info['order_number']),
//                    'confirm_store_weight'      => $num_kg,
//                    'confirm_store_density'     => $goods_in_density,
//                    'create_name'               => $this->getUserInfo('dealer_name'),
//                    'goods_name'                => $erp_goods_info['goods_name'],
//                    'goods_source_from'         => $erp_goods_info['source_from'],
//                    'goods_grade'               => $erp_goods_info['grade'],
//                    'goods_level'               => $erp_goods_info['level'],
//                    'goods_num'                 => $num_kg,
//                    'confirm_store_liter'       => getNum($order_data['actual_in_num_liter']),
//                    'confirm_time'              => currentTime(),
//                ];
//                # 自动屏蔽历史数据
//                if (getCompareOilTime($order_info['create_time'])) {
//                    $result_front = updateOilReceipt($params);
//                }
//            } else {
//                $out_num = getNum($stock_out_data['sum_actual_outbound_num']) * 1000;
//                $params = [
//                    'allocation_order_number'   => trim($order_info['order_number']),
//                    'stock_out_number'          => $stock_out_data['outbound_code'],
//                    'facilitator_id'            => $order_info['in_facilitator_id'],
//                    'facilitator_skid_id'       => $order_info['in_storehouse'],
//                    'allocation_order_type'     => $order_info['allocation_type'],
//                    'confirm_store_weight'      => $num_kg,
//                    'stock_out_weight'          => $out_num,
//                    'confirm_store_density'     => $goods_in_density,
//                    'create_name'               => $this->getUserInfo('dealer_name'),
//                    'goods_name'                => $erp_goods_info['goods_name'],
//                    'goods_source_from'         => $erp_goods_info['source_from'],
//                    'goods_grade'               => $erp_goods_info['grade'],
//                    'goods_level'               => $erp_goods_info['level'],
//                    'stock_time'                => $stock_out_data['audit_time'],
//                    'goods_num'                 => $num_kg,
//                    'confirm_store_liter'       => getNum($order_data['actual_in_num_liter']),
//                    'source'                    => 3,
//                    'delivery_method'           => $order_info['delivery_method']
//                ];
//                $result_front = addOilReceiptRecord($params);
//            }
//        }
        # end ---------------

        if($order_status && $log_status && $stock_in_status_all && $in_stock_status_all && $upload_status && $batch_status_all){
            if ($loss_num == 0) {
                //重新计算加权成本------------------------------------------------------
                $stock_in_data['before_stock_num'] = $beforeNum;
                $stock_in_data['stock_id'] = $stockId ? $stockId : 0;
                $stock_in_data['change_num'] = $stock_in_data['actual_storage_num'];
                updateStockInCost($stock_in_data);
                //----------------------------------------------------------------------
                if(intval($order_info['allocation_type']) == 1) {
                    //如果是调入到网点，生成零售出库单--------------------------------------
                    retailOrderCreate($order_info['in_storehouse'], $order_info['our_company_id']);
                    //----------------------------------------------------------------------
                }
            }
            M()->commit();

            $result = [
                'status' =>1,
                'message' =>'操作成功',
            ];
        }else{
            //操作失败后删除文件
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            M()->rollback();
            $result = [
                'status' =>0,
                'message' =>'操作失败',
            ];
        }

        cancelCacheLock('ErpNewAllocation/confirmInStock');
        return $result;
    }

    /**
     * 上传退货凭证
     * @param  $id 调拨单ID
     * @param  array $attach 凭证附件
     * @param  int $type 类型 1 出库 2 入库
     * @return array
     * @author xiaowen
     * @time 2017-8-23
     */
    public function uploadVoucher($id, $attach = [], $type)
    {

        if ($id && $attach) {

            if (count($attach) > 1) {
                return $result = [
                    'status' => 2,
                    'message' => '对不起，同时只能上传一份凭证',

                ];
            }

            $OrderInfo = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id'=>intval($id)], true);
            if (in_array($OrderInfo['order_status'], [2])) {
                $result = [
                    'status' => 3,
                    'message' => '该退货单已取消，无法上传凭证',
                ];
            } else {
                if (getCacheLock('ErpNewAllocation/uploadVoucher')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpNewAllocation/uploadVoucher', 1);
                M()->startTrans();
                $data = [

                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),

                ];
                $voucher = $type == 1 ? 'outbound_voucher' : 'storage_voucher';
                $voucher_status = $type == 1 ? 'outbound_voucher_status' : 'storage_voucher_status';
                $data[$voucher] = $attach[0];
                $data[$voucher_status] = 1;
                $status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id' => intval($id)], $data);

                //记录log
                $log_data = [
                    'allocation_id' => $OrderInfo['id'],
                    'allocation_order_number' => $OrderInfo['order_number'],
                    'log_type' => $type == 1 ? 10 : 11,
                    'log_info' => serialize($OrderInfo),
                ];
                $log_status = $this->addAllocationLog($log_data);

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
                cancelCacheLock('ErpNewAllocation/uploadVoucher');
            }

        } else {
            $result = [
                'status' => 0,
                'message' => '参数有误',
            ];
        }
        return $result;
    }

    /*
    * ------------------------------------------
    *        ****** 已作废******
    * 调拨单回滚
    * Author：qianbin        Time：2017-11-08
    * ------------------------------------------
    */
    public function rollBackAllocationOrder($id = 0){
        return ['status' => 99, 'message' => '该功能已作废，无法操作。'];
        if (getCacheLock('ErpNewAllocation/rollBackAllocationOrder'))  return ['status' => 99, 'message' => $this->running_msg];
        if (intval($id) <= 0 )                          return ['status' => 2 , 'message' => '参数有误，无法获取调拨单ID！'];
        $order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder(['id' => intval($id)]);
        if (empty($order_info))                         return ['status' => 3 , 'message' => '调拨单据信息错误，请刷新后重试！'];
        if (intval($order_info['status']) != 10)        return ['status' => 4 , 'message' => '调拨单不是已确认状态，无法作废！'];
        if (intval($order_info['allocation_type']) == 1 && intval($order_info['in_facilitator_skid_id']) == 0)
            return ['status' => 5 , 'message' => '调拨单入库方未查询到服务网点，无法作废！'];
        if((intval($order_info['allocation_type']) == 2) && (intval($order_info['in_facilitator_skid_id']) == 0 || intval($order_info['out_facilitator_skid_id']) == 0))
            return ['status' => 6 , 'message' => '调拨单类型未查询到服务网点，无法作废！'];
        if(intval($order_info['allocation_type']) == 3  && intval($order_info['out_facilitator_skid_id']) == 0)
            return ['status' => 7 , 'message' => '调拨单出库方未查询到服务网点，无法作废！'];
        setCacheLock('ErpNewAllocation/rollBackAllocationOrder', 1);

        # 已确认状态--------------------------------------------
        # 1 城市仓->服务商
        # 2 服务商->服务商
        # 3 服务商->城市仓
        # 4 城市仓->城市仓
        # 判断出库方仓库    1: 城市仓  4: 服务网点
        # -----------------------------------------------------
        $stock_out_type = [
            1 => 1 ,
            2 => 4 ,
            3 => 4 ,
            4 => 1 ,
        ];
        # 判断入库方仓库
        $stock_in_type = [
            1 => 4 ,
            2 => 4 ,
            3 => 1 ,
            4 => 1 ,
        ];
        $allocation_status      = true ;
        $stock_out_order_status = true ;
        $stock_in_order_status  = true ;
        $stock_out_status       = true ;
        $stock_in_status        = true ;
        $log_status             = true ;
        # 出库方库存信息-------------------------------------------------------------------------------------------------
        $stock_out_where = [
            'goods_id'   => $order_info['goods_id'],
            'object_id'  => $order_info['out_storehouse'],
            'stock_type' => in_array($order_info['allocation_type'],[1,2]) ? 4 : getAllocationStockType($order_info['in_storehouse']),
        ];
        $log_data = [
            'allocation_id'     => $order_info['id'],
            'log_type'          => 12,
            'log_info'          => serialize($order_info),
            'allocation_order_number' => $order_info['order_number'],
        ];
        $stock_out_info = $this->getEvent('ErpStock')->getStockInfo($stock_out_where);
        M()->startTrans();
        if (intval($order_info['outbound_status']) == 2 && intval($order_info['storage_status'] == 2)) {
            # [未出库 | 未入库]---------------------------------
            # 1.  城市仓->服务商
            # 3.  服务商->城市仓
            # 4.  城市仓->城市仓
            # 逻辑：减出库方配货待提，配货预留不变，入库方不做调整
            # -------------------------------------------------
            $data = [
                'goods_id'            => $order_info['goods_id'],
                'object_id'           => $order_info['out_storehouse'],
                'stock_type'          => getAllocationStockType($order_info['in_storehouse']),
                'region'              => $order_info['out_region'],
                'allocation_wait_num' => $stock_out_info['allocation_wait_num'] - $order_info['num'],
            ];
            $stock_out_info['allocation_wait_num'] = $data['allocation_wait_num'];
            # 计算可用库存, 添加日志 , 更新库存
            $stock_out_order_status = $this->changeSaveStock($stock_out_info,$data,$order_info,$order_info['num']);

            # 作废单据 ---------------------------------
            $allocation_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id' => intval($id)] , ['status' => 2]);
            $log_status        = $this->addAllocationLog($log_data);
        } else if (intval($order_info['outbound_status']) == 1 && intval($order_info['storage_status']) == 2) {
            # [已出库 | 未入库]---------------------------------
            # 1.  城市仓->服务商
            # 3.  服务商->城市仓
            # 4.  城市仓->城市仓
            # 加出库方物理库存，减入库方在途库存，作废出库单，订单状态变为已取消
            # -------------------------------------------------
            # 入库方库存信息
            $stock_in_where = [
                'goods_id'   => $order_info['goods_id'],
                'object_id'  => $stock_in_type[$order_info['allocation_type']] == 1 ? $order_info['in_storehouse'] : $order_info['in_facilitator_skid_id'],
                'stock_type' => in_array($order_info['allocation_type'],[1,2]) ? 4 : getAllocationStockType($order_info['in_storehouse']),
            ];
            $stock_in_info = $this->getEvent('ErpStock')->getStockInfo($stock_in_where);

            # 出库方------
            $out_data = [
                'goods_id'      => $order_info['goods_id'],
                'object_id'     => $order_info['out_storehouse'],
                'stock_type'    => getAllocationStockType($order_info['in_storehouse']),
                'region'        => $order_info['out_region'],
                'stock_num'     => $stock_out_info['stock_num'] + $order_info['actual_out_num'],
            ];
            $stock_out_info['stock_num'] = $out_data['stock_num'];
            # 计算可用库存, 添加日志 , 更新库存
            $stock_out_order_status = $this->changeSaveStock($stock_out_info,$out_data,$order_info,$order_info['actual_out_num']);

            # 入库方-----
            $in_data = [
                'goods_id'               => $order_info['goods_id'],
                'object_id'              => $stock_in_type[$order_info['allocation_type']] == 1 ? $order_info['in_storehouse'] : $order_info['in_facilitator_skid_id'],
                'stock_type'             => in_array($order_info['allocation_type'],[1,2]) ? 4 : getAllocationStockType($order_info['in_storehouse']),
                'region'                 => $order_info['in_region'],
                'transportation_num'     => $stock_in_info['transportation_num'] - $order_info['actual_out_num'],
            ];
            $stock_in_info['transportation_num'] = $in_data['transportation_num'];
            # 计算可用库存, 添加日志 , 更新库存
            $stock_in_order_status = $this->changeSaveStock($stock_in_info,$in_data,$order_info,$order_info['actual_out_num']);

            # 作废单据
            $allocation_status  = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id' => intval($id)] , ['status' => 2,'outbound_status'=> 2]);
            $stock_out_status   = $this->getModel('ErpStockOut')->saveStockOut(['source_number' => $order_info['order_number']], ['outbound_status' => 2]);
            $log_status         = $this->addAllocationLog($log_data);
        }else if(intval($order_info['outbound_status']) == 1 && intval($order_info['storage_status']) == 1){

            # [已出库 | 已入库]---------------------------------
            # 1.  城市仓->服务商      减服务网点物理库存，加城市仓物理库存，作废出库单、入库单，订单状态变为已取消
            # 2.  服务商->服务商      加出库方（服务网点）仓物理库存，减入库方（服务网点）物理库存，作废出库单、入库单，订单状态变为已取消
            # 3.  服务商->城市仓      加服务网点物理库存，减城市仓物理库存，作废出库单、入库单，订单状态变为已取消
            # 4.  城市仓->城市仓      加出库方城市仓物理库存，减入库方城市仓物理库存，作废出库单、入库单，订单状态变为已取消

            # 入库方库存信息-------------------------------------------------------------------------------------------------
            $stock_in_where = [
                'goods_id'   => $order_info['goods_id'],
                'object_id'  => $stock_in_type[$order_info['allocation_type']] == 1 ? $order_info['in_storehouse'] : $order_info['in_facilitator_skid_id'],
                'stock_type' => in_array($order_info['allocation_type'],[1,2]) ? 4 : getAllocationStockType($order_info['in_storehouse']),
            ];
            $stock_in_info = $this->getEvent('ErpStock')->getStockInfo($stock_in_where);

            # 出库方------
            $out_data = [
                'goods_id'      => $order_info['goods_id'],
                'object_id'     => $order_info['out_storehouse'],
                'stock_type'    => getAllocationStockType($order_info['in_storehouse']),
                'region'        => $order_info['out_region'],
                'stock_num'     => $stock_out_info['stock_num'] + $order_info['actual_out_num'],
            ];
            $stock_out_info['stock_num'] = $out_data['stock_num'] ;
            # 计算可用库存, 添加日志 , 更新库存
            $stock_out_order_status = $this->changeSaveStock($stock_out_info,$out_data,$order_info,$order_info['actual_out_num']);

            # 入库方-----
            $in_data = [
                'goods_id'           => $order_info['goods_id'],
                'object_id'          => $stock_in_type[$order_info['allocation_type']] == 1 ? $order_info['in_storehouse'] : $order_info['in_facilitator_skid_id'],
                'stock_type'         => in_array($order_info['allocation_type'],[1,2]) ? 4 : getAllocationStockType($order_info['in_storehouse']),
                'region'             => $order_info['in_region'],
                'stock_num'          => $stock_in_info['stock_num'] - $order_info['actual_in_num'],
            ];
            $stock_in_info['stock_num'] = $in_data['stock_num'];
            # 计算可用库存, 添加日志 , 更新库存
            $stock_in_order_status = $this->changeSaveStock($stock_in_info,$in_data,$order_info,$order_info['actual_in_num']);
            $allocation_status     = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder(['id' => intval($id)] , ['status' => 2,'outbound_status'=> 2 ,'storage_status'=> 2]);
            $stock_out_status      = $this->getModel('ErpStockOut')->saveStockOut(['source_number' => $order_info['order_number']], ['outbound_status' => 2]);
            $stock_in_status       = $this->getModel('ErpStockIn')->saveStockIn(['source_number' => $order_info['order_number']] , ['storage_status' => 2]);
            $log_status            = $this->addAllocationLog($log_data);
        }else{
            cancelCacheLock('ErpNewAllocation/rollBackAllocationOrder');
            return ['status' => 11 , 'message' => '订单状态错误，请联系系统管理员！'];
        }
        //echo $allocation_status.'=========='.$stock_out_order_status.'=========='.$stock_in_order_status.'=========='.$stock_out_status.'=========='.$stock_in_status.'=========='.$log_status;exit;
        if ($allocation_status && $stock_out_order_status && $stock_in_order_status  && $stock_out_status && $stock_in_status && $log_status) {
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
        cancelCacheLock('ErpNewAllocation/rollBackAllocationOrder');
        return $result;
    }

    /*
     * ------------------------------------------
     * 调拨单作废影响库存
     * Author：qianbin        Time：2017-11-08
     * ------------------------------------------
     */
    private  function changeSaveStock($stock_info = [] , $data = [] , $order_info = [] ,$changeNum = 0){
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type'   => 5,
            'log_type'      => 9,
        ];
        $stock_status      = $this->getEvent('ErpStock')->saveStockInfo($data, $changeNum, $orders);
        return $stock_status;
    }

    /**
     * 调拨损耗报表
     * @param $param
     * @author guanyu
     * @time 2017-11-17
     */
    public function erpAllocationLossList($param = [])
    {
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['ao.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['ao.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['ao.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }

        if (trim($param['allocation_type'])) {
            $where['ao.allocation_type'] = intval($param['allocation_type']);
        }

        if (trim($param['goods_id'])) {
            $where['ao.goods_id'] = intval($param['goods_id']);
        }

        if ($param['storage_status']) {
            $where['ao.storage_status'] = intval($param['storage_status']);
        }
        $where['ao.status'] = 10;
        $where['ao.outbound_status'] = 1;
        //当前登陆选择的我方公司
        $where['ao.our_company_id'] = session('erp_company_id');

        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,osh.type as o_storehouse_type,ish.storehouse_name as i_storehouse_name,
        ish.type as i_storehouse_type,g.goods_code,g.goods_name,g.source_from,g.grade, g.level';

        if($param['export'] && isset($param['export'])){
            $data = $this->getModel('ErpNewAllocationOrder')->erpAllocationOrderAlListData($where, $field);
        }else{
            $data = $this->getModel('ErpNewAllocationOrder')->getAllocationOrderList($where, $field, $param['start'], $param['length']);
        }

        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['allocation_type'] = AllocationType($value['allocation_type']);
                $data['data'][$key]['out_stock'] = $value['o_facilitator_name'] ? $value['o_facilitator_name'].'-'.$value['o_skid_name'] : $value['o_storehouse_name'];
                $data['data'][$key]['in_stock'] = $value['i_facilitator_name'] ? $value['i_facilitator_name'].'-'.$value['i_skid_name'] : $value['i_storehouse_name'];
                $data['data'][$key]['out_region_font'] = $cityArr[$value['out_region']];
                $data['data'][$key]['in_region_font'] = $cityArr[$value['in_region']];
                $data['data'][$key]['num'] = getNum($value['num']);
                $data['data'][$key]['actual_out_num'] = getNum($value['actual_out_num']);
                $data['data'][$key]['actual_in_num'] = getNum($value['actual_in_num']);
                $data['data'][$key]['loss_num'] = getNum($value['actual_out_num'] - $value['actual_in_num']);
                $data['data'][$key]['actual_out_num_liter'] = getNum($value['actual_out_num_liter']);
                $data['data'][$key]['actual_in_num_liter'] = getNum($value['actual_in_num_liter']);
                $data['data'][$key]['loss_num_liter'] = getNum($value['actual_out_num_liter'] - $value['actual_in_num_liter']);
                /*
                    类型代码 1 城市仓->服务商 2 服务商->服务商3 服务商->城市仓4 城市仓->城市仓

                    1）城市仓->服务商， 城市仓出库升数为空的，城市仓出库升入默认显示 --， 升量差异显示--
                    2）服务商->城市仓， 城市仓入库升数为空的，入库升入默认显示 --， 升量差异显示--
                    3）-城市仓-城市仓  无出入库升入 ，字段显示用--代替，升量差异用--代替
                 */
                if(intval($value['allocation_type']) == 1) {
                    $data['data'][$key]['actual_out_num_liter'] = '--';
                    $data['data'][$key]['loss_num_liter']       = '--';
                }else if(intval($value['allocation_type']) == 3){
                    $data['data'][$key]['actual_in_num_liter']  = '--';
                    $data['data'][$key]['loss_num_liter']       = '--';
                }else if(intval($value['allocation_type']) == 4){
                    $data['data'][$key]['actual_out_num_liter'] = '--';
                    $data['data'][$key]['actual_in_num_liter']  = '--';
                    $data['data'][$key]['loss_num_liter']       = '--';
                }
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 返回仓库对应的库存类型
     * @author xiaowen
     * @param int $storehouse_id
     * @return int $stock_type
     */
    function getAllocationStockType($storehouse_id){
        $storehouse_type = $this->getModel("ErpStorehouse")->where(['id'=>$storehouse_id])->getField('type');

        return storehouseTypeToStockType($storehouse_type);
    }
}


