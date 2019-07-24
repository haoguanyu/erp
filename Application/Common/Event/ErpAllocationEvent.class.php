<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpAllocationEvent extends BaseController
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
        if(getCacheLock('ErpAllocation/cancelAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpAllocation/cancelAllocationOrder', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id'=>$id]);
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
                $status = $this->getModel('ErpAllocationOrder')->saveAllocationOrder(['id'=>$id], $data);
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
                    $stock_where['object_id'] = $order_info['out_facilitator_skid_id'];
//                    $stock_where['stock_type'] = 3;
                    $stock_where['stock_type'] = 4;
                }else if(in_array($order_info['allocation_type'], [1, 4])){
                    $stock_where['object_id'] = $order_info['out_storehouse'];
                    $stock_where['stock_type'] = 1;
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
        cancelCacheLock('ErpAllocation/cancelAllocationOrder');
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
        if(getCacheLock('ErpAllocation/auditAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpAllocation/auditAllocationOrder', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id'=>$id]);
            if(($order_info['out_facilitator_id'] != '0' && $order_info['out_facilitator_skid_id'] == '0') || ($order_info['in_facilitator_id'] != '0' && $order_info['in_facilitator_skid_id'] == '0')){
                $result = [
                    'status' => 3,
                    'message' => '请绑定加油网点',
                ];
            }else if($order_info['status'] != 1){
                $result = [
                    'status' => 3,
                    'message' => '订单不是未审核状态，无法取消',
                ];
            }else if(empty($order_info)){
                $result = [
                    'status' => 4,
                    'message' => '订单不存在，无法取消',
                ];
            }else{
                M()->startTrans();
                $data = [
                    'status' => 3,
                    'audit_time' => currentTime(),

                ];
                $status = $this->getModel('ErpAllocationOrder')->saveAllocationOrder(['id'=>$id], $data);

                $workflow_status = $this->createWorkflow($order_info);

                $log_data = [
                    'allocation_id' => $order_info['id'],
                    'allocation_order_number' => $order_info['order_number'],
                    'log_type' => 4,
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
        cancelCacheLock('ErpAllocation/auditAllocationOrder');
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
        if (getCacheLock('ErpAllocation/copyAllocationOrder')) return ['status' => 9999, 'message' => $this->running_msg];
        setCacheLock('ErpAllocation/copyAllocationOrder', 1);
        if(!$id){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请重新操作',
            ];
        }else {
            $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id' => $id]);
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
                $status = $this->getModel('ErpAllocationOrder')->addAllocationOrder($allocation_data);

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
//                    'object_id' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? $allocation_data['out_storehouse'] : $allocation_data['out_facilitator_id'],
                    'object_id' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? $allocation_data['out_storehouse'] : $allocation_data['out_facilitator_skid_id'],
//                    'stock_type' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? 1 : 4,
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $allocation_data['goods_id'],
//                    'object_id' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? $allocation_data['out_storehouse'] : $allocation_data['out_facilitator_id'],
                    'object_id' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? $allocation_data['out_storehouse'] : $allocation_data['out_facilitator_skid_id'],
//                    'stock_type' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $allocation_data['allocation_type'] == 1 || $allocation_data['allocation_type'] == 4 ? 1 : 4,
                    'facilitator_id' => $allocation_data['allocation_type'] == 2 || $allocation_data['allocation_type'] == 3 ? $allocation_data['out_facilitator_id'] : '',
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
        cancelCacheLock('ErpAllocation/copyAllocationOrder');
        return $result;

    }

    /**
     * 确认调拨单
     * @author xiaowen
     * @time 2017-5-12
     * @param $id 调拨单ID
     * @param $param 调拨单确认时输入的数据
     * @return array $result
     */
    public function confirmAllocationOrder($id, $param){
        if(getCacheLock('ErpAllocation/confirmAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpAllocation/confirmAllocationOrder', 1);

        if($id){
            $order_info = $this->getOneAllocationOrder(['id'=>$id]);

            $out_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['out_region'], 'our_company_id'=>session('erp_company_id')]);
            $in_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['in_region'], 'our_company_id'=>session('erp_company_id')]);
            if ($param['out_density']) {
                $goods_out_density = $param['out_density'];
                //$goods_out_density = setNum($param['out_density']);
            } elseif ($out_goods_info['density']) {
                $goods_out_density = $out_goods_info['density'];
            } else {
                $goods_out_density = $this->getEvent('ErpGoods')->findGoodsById($order_info['goods_id'])['density_value'];
            }

            if ($param['in_density']) {
                //$goods_in_density = setNum($param['in_density']);
                $goods_in_density = $param['in_density'];
            } elseif ($in_goods_info['density']) {
                $goods_in_density = $in_goods_info['density'];
            } else {
                $goods_in_density = $this->getEvent('ErpGoods')->findGoodsById($order_info['goods_id'])['density_value'];
            }

            //==调拨单至少有一方是以升数为实际出入库数量 统一用入库方区域商品维护和密度，如果没有则使用商品主档的密度 进行升转吨==
            if(($param['actual_out_num_liter'] && isset($param['actual_out_num_liter'])) || ($param['actual_in_num_liter'] && isset($param['actual_in_num_liter']))){

                if($param['actual_out_num_liter'] && isset($param['actual_out_num_liter'])){
                    $param['actual_out_num'] = round(literToTon($param['actual_out_num_liter'], $goods_out_density), 4);
                    log_info("转化后实际出库数量：". $param['actual_out_num']);
                }
                if($param['actual_in_num_liter'] && isset($param['actual_in_num_liter'])){
                    log_info(''.$goods_in_density);
                    $param['actual_in_num'] = round(literToTon($param['actual_in_num_liter'], $goods_in_density), 4);
                    log_info("转化后实际入库数量：". $param['actual_in_num']);
                }
            }
            //==================================================end===============================================================
//            if($param['actual_out_num_liter'] && isset($param['actual_out_num_liter'])){
//                $out_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['out_region']]);
//
//                $param['actual_out_num'] = literToTon($param['actual_out_num_liter'], $out_goods_info['density']);
//                log_info("转化后实际出库数量：". $param['actual_out_num']);
//            }
//            if($param['actual_in_num_liter'] && isset($param['actual_in_num_liter'])){
//                $in_goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$order_info['goods_id'], 'region'=>$order_info['in_region']]);
//
//                $param['actual_in_num'] = literToTon($param['actual_in_num_liter'], $in_goods_info['density']);
//
//                log_info("转化后实际入库数量：". $param['actual_in_num']);
//            }
//            if(isset($param['actual_out_num_liter']) && $param['actual_out_num_liter']){
//                $param['actual_out_num_liter'];
//            }
            if ($order_info['status'] == 10) {
                $result = [
                    'status'=>3,
                    'message'=>'调拨单已确认，无法操作',
                ];
            }else if(!trim($param['actual_out_num']) || !trim($param['actual_in_num'])){
                $result = [
                    'status'=>3,
                    'message'=>'请输入实际出库数量和实际入库数量',
                ];
            }else if(bccomp(setNum(trim($param['actual_out_num'])), $order_info['num']) == 1 ){  //setNum(trim($param['actual_out_num'])) > $order_info['num']
                $result = [
                    'status'=>4,
                    'message'=>'实际出库数量不能大于调拨数量',
                ];
            }else if(setNum(trim($param['actual_in_num'])) > $order_info['num']){
                $result = [
                    'status'=>5,
                    'message'=>'实际入库数量不能大于调拨数量',
                ];
            }else{

                M()->startTrans();

                //保存调拨单数据
                $order_data = [
                    'actual_out_num' => setNum($param['actual_out_num']),
                    'actual_in_num' => setNum($param['actual_in_num']),
                    'actual_out_num_liter' => setNum($param['actual_out_num_liter']),
                    'actual_in_num_liter' => setNum($param['actual_in_num_liter']),
                    'pick_up_number' => $param['pick_up_number'],
                    'confirm_time' =>currentTime(),
                    'status' => 10,
                ];
                $order_status = $this->getModel('ErpAllocationOrder')->saveAllocationOrder(['id'=>$id], $order_data);
                //保存调拨单操作日志
                $log_data = [
                    'allocation_id' => $order_info['id'],
                    'allocation_order_number' => $order_info['order_number'],
                    'log_info' => serialize($order_info),
                    'log_type' => 6,
                ];
                $log_status = $this->addAllocationLog($log_data);
                //===============出库方库存逻辑操作==================================================================

                //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                $stock_where = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 4,
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //---------------判断出库方的物理库存是否满足实际出库数量----------------------
//                log_info('出库方物理库存：'.$stock_info['stock_num']);
//                log_info('实际出库数量：'.$order_data['actual_out_num']);
//                $info = intval($stock_info['stock_num']) < intval($order_data['actual_out_num']) ? '库存不足' : '库存充足';
//                log_info('对比结果：'.$info);
                if(intval($stock_info['stock_num']) < intval($order_data['actual_out_num'])){
                    M()->rollback();
                    cancelCacheLock('ErpAllocation/confirmAllocationOrder');
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
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 4,
                    'facilitator_id' => $order_info['allocation_type'] == 2 || $order_info['allocation_type'] == 3 ? $order_info['out_facilitator_id'] : '',
                    'region' => $order_info['out_region'],
                    'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'], //减少出库方配货预留
                    'stock_num' => $stock_info['stock_num'] - $order_data['actual_out_num'], //减少出库方物理库存
                    'region' => $order_info['out_region'],
                ];

                $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
                $stock_info['stock_num'] = $data['stock_num']; //重置最新的预留库存
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                //----------------------------------------------------------------
                $orders = [
                    'object_number' => $order_info['order_number'],
                    'object_type' => 5,
                    'log_type' => 3,
                ];
                //----------------更新库存，并保存库存日志-------------------------
                $out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_data['actual_out_num'], $orders);

                //===============出库方库存逻辑操作 end================================================================


                //===============入库方库存逻辑操作==================================================================

                //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                $stock_where = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? $order_info['in_storehouse'] : $order_info['in_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? $order_info['in_storehouse'] : $order_info['in_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? 1 : 4,
                    'region' => $order_info['in_region'],
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? $order_info['in_storehouse'] : $order_info['in_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? $order_info['in_storehouse'] : $order_info['in_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 3 || $order_info['allocation_type'] == 4 ? 1 : 4,
                    'facilitator_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 2 ? $order_info['in_facilitator_id'] : '',
                    'region' => $order_info['in_region'],
                   // 'allocation_reserve_num' => $stock_info['allocation_reserve_num'] - $order_info['num'],
                    'stock_num' => $stock_info['stock_num'] + $order_data['actual_in_num'],
                ];
                $stock_info['allocation_reserve_num'] = $data['allocation_reserve_num']; //重置最新的预留库存
                $stock_info['stock_num'] = $data['stock_num']; //重置最新的预留库存
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                //----------------------------------------------------------------
                $orders = [
                    'object_number' => $order_info['order_number'],
                    'object_type' => 5,
                    'log_type' => 3,
                ];
                //----------------更新库存，并保存库存日志-------------------------
                $in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_data['actual_in_num'], $orders);

                //===============入库方库存逻辑操作 end================================================================

                //===============生成出库单和入库单====================================================================

                $stock_out_data = [
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
                    'storehouse_id' => in_array($order_info['allocation_type'],[2,3]) ? $order_info['out_facilitator_skid_id'] : $order_info['out_storehouse'],
                    'stock_type' => in_array($order_info['allocation_type'],[2,3]) ? 4 : 1,
                    'region' => $order_info['out_region'],
                ];

                $stock_in_data = [
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
                    'storehouse_id' => in_array($order_info['allocation_type'],[1,2]) ? $order_info['in_facilitator_skid_id'] : $order_info['in_storehouse'],
                    'stock_type' => in_array($order_info['allocation_type'],[1,2]) ? 4 : 1,
                    'region' => $order_info['in_region'],
                ];

                $stock_out_status = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);

                $stock_in_status = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
                //===============生成出库单和入库单 end================================================================

                if($order_status && $log_status && $out_stock_status && $in_stock_status && $stock_out_status && $stock_in_status){
                    M()->commit();
                    $result = [
                        'status' =>1,
                        'message' =>'操作成功',
                    ];
                }else{
                    M()->rollback();
                    $result = [
                        'status' =>0,
                        'message' =>'操作失败',
                    ];
                }

            }

        }else{
            $result = [
                'status'=>2,
                'status'=>'调拨单信息有误，请重新操作',
            ];
        }
        cancelCacheLock('ErpAllocation/confirmAllocationOrder');
        return $result;
    }

    /**
     * 获取一条调拨信息不包含关联信息）
     * @param array $where
     * @return array
     */
    public function getOneAllocationOrder($where = []){
        $data = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder($where);
        $data = !empty($data) ? $data : [];
        return $data;
    }
    /**
     * 获取一条调拨信息包含关联信息）
     * @param $id
     * @param string $field
     * @return array
     */
    public function findAllocationOrderInfo($id, $field = ''){
        $field =  $field ? $field : 'ao.*, osh.storehouse_name as osh_storehouse_name, ish.storehouse_name as ish_storehouse_name, oft.name as oft_name, ift.name as ift_name';
        $data = $this->getModel('ErpAllocationOrder')->findAllocationOrder(['ao.id'=>$id], $field);
        if($data){
            //调拨类型 1  城市仓->服务商 2 服务商->服务商3 服务商->城市仓4 城市仓->城市仓

            $data['out_type'] = in_array($data['allocation_type'], [1, 4]) ? '城市仓' : '服务商';
            $data['in_type'] = in_array($data['allocation_type'], [3, 4]) ? '城市仓' : '服务商';

            $data['out_object_name'] = in_array($data['allocation_type'], [1, 4]) ? $data['osh_storehouse_name'] : $data['oft_name'];
            $data['in_object_name'] = in_array($data['allocation_type'], [3, 4]) ? $data['ish_storehouse_name'] : $data['ift_name'];

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

            $workflow_data = [
                'workflow_type' => 3,
                'workflow_order_number' => $order_info['order_number'],
                'workflow_order_id' => $order_info['id'],
                'our_company_id' => $order_info['our_company_id'],
                'creater' => $order_info['dealer_name'],
                'creater_id' => $order_info['dealer_id'],
            ];
            //$work_status = $workflow_id = $this->getEvent('ErpWorkflow')->addWorkFlow($workflow_data);
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            # 新的调拨单规则 qianbin  2017.08.14
            # 1）当调拨吨数≤30吨 调整为： 调拨员创建调拨单-> 地区仓管审批
            # 2）当调拨吨数＞30吨 调整为：调拨员创建调拨单-> 地区调拨负责人-> 地区调拨采购负责人审批 -> 地区分公司负责人审批 -> 地区仓管审批
            # 3）服务商->服务商之间调拨，遵循原有审批流：市场开发中心交易员创建->地区调拨负责人
            if(getNum($order_info['num']) > 30){
                $type = 1;
            }else{
                $type = 2;
            }
            $workflow_step = allocationWorkflowStepPosition($type,$order_info['allocation_type']);

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

        if (trim($param['out_facilitator_id'])) {
            $where['ao.out_facilitator_id'] = intval($param['out_facilitator_id']);
        }

        if (trim($param['out_facilitator_skid_id'])) {
            $where['ao.out_facilitator_skid_id'] = intval($param['out_facilitator_skid_id']);
        }

        if (trim($param['in_facilitator_id'])) {
            $where['ao.in_facilitator_id'] = intval($param['in_facilitator_id']);
        }

        if (trim($param['in_facilitator_skid_id'])) {
            $where['ao.in_facilitator_skid_id'] = intval($param['in_facilitator_skid_id']);
        }

        if (trim($param['goods_id'])) {
            $where['ao.goods_id'] = intval($param['goods_id']);
        }

        if (trim($param['dealer_name'])) {
            $where['ao.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }

        if ($param['status']) {
            $where['ao.status'] = intval($param['status']);
        }

        if (trim($param['order_number'])) {
            $where['ao.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        //当前登陆选择的我方公司
        $where['ao.our_company_id'] = session('erp_company_id');

        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,ish.storehouse_name as i_storehouse_name,g.goods_code,
        g.goods_name,g.source_from,g.grade, g.level';
        if($param['export'] && isset($param['export'])){
            $data = $this->getModel('ErpAllocationOrder')->erpAllocationOrderAlListData($where, $field);
        }else{
            $data = $this->getModel('ErpAllocationOrder')->getAllocationOrderList($where, $field, $param['start'], $param['length']);
        }
        $out_supplier = $this->getEvent('ErpSupplier')->getSupplierDataField(['id' => ['in', array_unique(array_column($data['data'],'out_facilitator_id'))]],'id,supplier_name');
        $in_supplier = $this->getEvent('ErpSupplier')->getSupplierDataField(['id' => ['in', array_unique(array_column($data['data'],'in_facilitator_id'))]],'id,supplier_name');
        //log_info("SQL语句:" .$this->erpPurchaseOrder->getLastSql());
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            $is_show_front = $param['export'] && isset($param['export']) ? false : true;
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
//                $data['data'][$key]['i_facilitator_name'] = $value['i_facilitator_name'] ? $value['i_facilitator_name'] : '';
                $data['data'][$key]['i_facilitator_name'] = $in_supplier['in_facilitator_id'];
//                $data['data'][$key]['i_skid_name'] = $value['i_skid_name'] ? $value['i_skid_name'] : '';
                $data['data'][$key]['i_storehouse_name'] = $value['i_storehouse_name'] ? $value['i_storehouse_name'] : '';
//                $data['data'][$key]['o_facilitator_name'] = $value['o_facilitator_name'] ? $value['o_facilitator_name'] : '';
                $data['data'][$key]['o_facilitator_name'] = $out_supplier['out_facilitator_id'];
//                $data['data'][$key]['o_skid_name'] = $value['o_skid_name'] ? $value['o_skid_name'] : '';
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

    /**
     * 新增调拨单
     * @author senpai
     * @time 2017-05-12
     */
    public function addErpAllocationOrder($param)
    {
        if(getCacheLock('ErpAllocation/addErpAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpAllocation/addErpAllocationOrder', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $out_facilitator_skid = M('facilitator_skid')->where(['facilitator_skid_id'=>trim($param['out_facilitator_skid_id'])])->find();
            $in_facilitator_skid = M('facilitator_skid')->where(['facilitator_skid_id'=>trim($param['in_facilitator_skid_id'])])->find();
            //$where['_string'] = 'goods_id = '.$param['goods_id'].' AND status = 1 AND region IN ('.$param['out_region'].','.$param['in_region'].') AND our_company_id = '.session('erp_company_id');
            $where['_string'] = 'goods_id = '.$param['goods_id'].' AND status = 1 AND region IN ('.$param['out_region'].') AND our_company_id = '.session('erp_company_id');
            $region_goods = D('ErpRegionGoods')->where($where)->find();
            if (empty($region_goods)) {
                $result['status'] = 101;
                $result['message'] = "该商品出库方所在区域维护中未设置";
            } elseif (trim($param['our_company_id']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择我方公司";
            } elseif (trim($param['allocation_type']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择调拨类型";
            } elseif (trim($param['out_region']) == "") {
                $result['status'] = 101;
                $result['message'] = "去选择来源城市";
            } elseif (trim($param['out_storehouse']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 4)) {
                $result['status'] = 101;
                $result['message'] = "请选择来源仓库";
            } elseif (trim($param['out_facilitator_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
                $result['status'] = 101;
                $result['message'] = "请选择来源服务商";
            } elseif (trim($param['out_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
                $result['status'] = 101;
                $result['message'] = "请选择来源加油网点";
            } elseif ($out_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
                $result['status'] = 101;
                $result['message'] = "来源加油网点无效，请检查";
            } elseif (trim($param['in_region']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择目的城市";
            } elseif (trim($param['in_storehouse']) == "" && (trim($param['allocation_type']) == 3 || trim($param['allocation_type']) == 4)) {
                $result['status'] = 101;
                $result['message'] = "请选择目的仓库";
            } elseif (trim($param['in_facilitator_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
                $result['status'] = 101;
                $result['message'] = "请选择目的服务商";
            }
            elseif (trim($param['in_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
                $result['status'] = 101;
                $result['message'] = "请选择目的加油网点";
            }
            elseif ($in_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
                $result['status'] = 101;
                $result['message'] = "目的加油网点无效，请检查";
            } elseif ($param['out_region'] == $param['in_region'] && $param['allocation_type'] == 4) {
                $result['status'] = 101;
                $result['message'] = "城市仓之间不允许同地调拨！";
            } elseif ($param['out_region'] != $param['in_region'] && $param['allocation_type'] != 2 && $param['allocation_type'] != 4) {
                $result['status'] = 101;
                $result['message'] = "非城市仓之间或服务商之间不允许异地调拨！";
            } elseif (trim($param['goods_id']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择商品代号";
            } elseif (trim($param['num']) == "") {
                $result['status'] = 101;
                $result['message'] = "请输入数量";
            } elseif (!is_numeric(trim($param['num']))) {
                $result['status'] = 101;
                $result['message'] = "请输入数字";
            } else {

                M()->startTrans();

                //添加调拨单信息
                $erp_allocation_data = [
                    'order_number' => erpCodeNumber(9)['order_number'],
                    'our_company_id' => $param['our_company_id'],
                    'out_region' => $param['out_region'],
                    'out_storehouse' => $param['allocation_type'] == 1 || $param['allocation_type'] == 4 ? $param['out_storehouse'] : '',
                    'out_facilitator_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_id'] : '',
                    'out_facilitator_skid_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_skid_id'] : '',
                    'in_region' => $param['in_region'],
                    'in_storehouse' => $param['allocation_type'] == 3 || $param['allocation_type'] == 4 ? $param['in_storehouse'] : '',
                    'in_facilitator_id' => $param['allocation_type'] == 1 || $param['allocation_type'] == 2 ? $param['in_facilitator_id'] : '',
                    'in_facilitator_skid_id' => $param['allocation_type'] == 1 || $param['allocation_type'] == 2 ? $param['in_facilitator_skid_id'] : '',
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
                ];
                $status_allocation = $id = $this->getModel('ErpAllocationOrder')->addAllocationOrder($erp_allocation_data);

                $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id'=>$id], '*');

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
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 4,
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 4,
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
            }
        }
        cancelCacheLock('ErpAllocation/addErpAllocationOrder');
        return $result;
    }

    /**
     * 编辑调拨单
     * @author senpai
     * @time 2017-05-12
     */
    public function updateErpAllocationOrder($param)
    {
        if(getCacheLock('ErpAllocation/updateErpAllocationOrder'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpAllocation/updateErpAllocationOrder', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $out_facilitator_skid = M('facilitator_skid')->where(['facilitator_skid_id'=>trim($param['out_facilitator_skid_id'])])->find();
            $in_facilitator_skid = M('facilitator_skid')->where(['facilitator_skid_id'=>trim($param['in_facilitator_skid_id'])])->find();

            $where['_string'] = 'goods_id = '.$param['goods_id'].' AND status = 1 AND region IN ('.$param['out_region'].') AND our_company_id = '.session('erp_company_id');
            $region_goods = D('ErpRegionGoods')->where($where)->select();
            if (empty($region_goods)) {
                $result['status'] = 101;
                $result['message'] = "该商品出库方所在区域维护中未设置";
            } elseif (trim($param['our_company_id']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择我方公司";
            } elseif (trim($param['allocation_type']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择调拨类型";
            } elseif (trim($param['out_region']) == "") {
                $result['status'] = 101;
                $result['message'] = "去选择来源城市";
            } elseif (trim($param['out_storehouse']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 4)) {
                $result['status'] = 101;
                $result['message'] = "请选择来源仓库";
            } elseif (trim($param['out_facilitator_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
                $result['status'] = 101;
                $result['message'] = "请选择来源服务商";
            }
            elseif (trim($param['out_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
                $result['status'] = 101;
                $result['message'] = "请选择来源加油网点";
            }
            elseif ($out_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 2 || trim($param['allocation_type']) == 3)) {
                $result['status'] = 101;
                $result['message'] = "来源加油网点无效，请检查";
            } elseif (trim($param['in_region']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择目的城市";
            } elseif (trim($param['in_storehouse']) == "" && (trim($param['allocation_type']) == 3 || trim($param['allocation_type']) == 4)) {
                $result['status'] = 101;
                $result['message'] = "请选择目的仓库";
            } elseif (trim($param['in_facilitator_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
                $result['status'] = 101;
                $result['message'] = "请选择目的服务商";
            } elseif (trim($param['in_facilitator_skid_id']) == "" && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
                $result['status'] = 101;
                $result['message'] = "请选择目的加油网点";
            } elseif ($in_facilitator_skid['status'] != 1 && (trim($param['allocation_type']) == 1 || trim($param['allocation_type']) == 2)) {
                $result['status'] = 101;
                $result['message'] = "目的加油网点无效，请检查";
            } elseif ($param['out_region'] == $param['in_region'] && $param['allocation_type'] == 4) {
                $result['status'] = 101;
                $result['message'] = "城市仓之间不允许同地调拨！";
            } elseif ($param['out_region'] != $param['in_region'] && $param['allocation_type'] != 2 && $param['allocation_type'] != 4) {
                $result['status'] = 101;
                $result['message'] = "非城市仓之间或服务商之间不允许异地调拨！";
            } elseif (trim($param['goods_id']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择商品代号";
            } elseif (trim($param['num']) == "") {
                $result['status'] = 101;
                $result['message'] = "请输入数量";
            } elseif (!is_numeric(trim($param['num']))) {
                $result['status'] = 101;
                $result['message'] = "请输入数字";
            } else {
                $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id'=>$param['id']], '*');
                if($order_info['status'] != 1){
                    $result['status'] = 102;
                    $result['message'] = "该调拨已不是未审核状态，请刷新重新";
                    cancelCacheLock('ErpAllocation/updateErpAllocationOrder');
                    return $result;
                }
                M()->startTrans();

                $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id'=>$param['id']], '*');

                //临时使用--------start
                $object_id = $order_info['out_facilitator_skid_id'] == '0' ? $order_info['out_facilitator_id'] : $order_info['out_facilitator_skid_id'];
                $stock_type = $order_info['out_facilitator_skid_id'] == '0' ? 3 : 4;
                //临时使用--------end


                /** 回滚修改前来源库存的配送预留 */
                //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                $stock_where = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $object_id,
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : $stock_type,
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $object_id,
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : $stock_type,
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

                //更新调拨单信息
                $erp_allocation_data = [
                    'our_company_id' => $param['our_company_id'],
                    'out_region' => $param['out_region'],
                    'out_storehouse' => $param['allocation_type'] == 1 || $param['allocation_type'] == 4 ? $param['out_storehouse'] : '',
                    'out_facilitator_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_id'] : '',
                    'out_facilitator_skid_id' => $param['allocation_type'] == 2 || $param['allocation_type'] == 3 ? $param['out_facilitator_skid_id'] : '',
                    'in_region' => $param['in_region'],
                    'in_storehouse' => $param['allocation_type'] == 3 || $param['allocation_type'] == 4 ? $param['in_storehouse'] : '',
                    'in_facilitator_id' => $param['allocation_type'] == 1 || $param['allocation_type'] == 2 ? $param['in_facilitator_id'] : '',
                    'in_facilitator_skid_id' => $param['allocation_type'] == 1 || $param['allocation_type'] == 2 ? $param['in_facilitator_skid_id'] : '',
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
                ];
                $status_allocation = $this->getModel('ErpAllocationOrder')->saveAllocationOrder(['id'=>$param['id']],$erp_allocation_data);

                $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id'=>$param['id']], '*');

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
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 4,
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $order_info['goods_id'],
//                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_id'],
                    'object_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? $order_info['out_storehouse'] : $order_info['out_facilitator_skid_id'],
//                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 3,
                    'stock_type' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 4 ? 1 : 4,
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
            }
        }
        cancelCacheLock('ErpAllocation/updateErpAllocationOrder');
        return $result;
    }

    /**
     * 获取一条调拨单信息
     * @author senpai
     * @time 2017-05-12
     */
    public function getErpAlloctionData($id)
    {
        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,ish.storehouse_name as i_storehouse_name,oft.name as o_facilitator_name,ift.name as i_facilitator_name,ofs.name as o_skid_name,ifs.name as i_skid_name,g.id as g_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data = $this->getModel('ErpAllocationOrder')->findAllocationOrder(['ao.id'=>$id],$field);
        if ($data) {
            $cityArr = provinceCityZone()['city'];
            $data['i_facilitator_name'] = $data['i_facilitator_name'] ? $data['i_facilitator_name'] : '';
            $data['i_skid_name'] = $data['i_skid_name'] ? $data['i_skid_name'] : '';
            $data['i_storehouse_name'] = $data['i_storehouse_name'] ? $data['i_storehouse_name'] : '';
            $data['o_facilitator_name'] = $data['o_facilitator_name'] ? $data['o_facilitator_name'] : '';
            $data['o_skid_name'] = $data['o_skid_name'] ? $data['o_skid_name'] : '';
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

        $field = 'ao.*,osh.storehouse_name as o_storehouse_name,ish.storehouse_name as i_storehouse_name,oft.name as o_facilitator_name,ift.name as i_facilitator_name,ofs.name as o_skid_name,ifs.name as i_skid_name,g.goods_code';

        $data = $this->getModel('ErpAllocationOrder')->getAllocationOrderList($where, $field, $param['start'], $param['length']);

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
}


