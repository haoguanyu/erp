<?php
/**
 * 批次管理业务处理层
 * @author xiaowen
 * @time 2019-02-19
 */
namespace Home\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpBatchEvent extends BaseController
{

    /**
     * 入库单生成或修改货权信息
     * @param $param
     * @return array
     */
    public function handleBatchCargoBn($param){

        if(empty(trim($param['cargo_bn']))){
            $result = [
                'status'=>3,
                'message'=>'货权编号不能为空',
            ];
            return $result;
        }

        $cargo_bn_info = $this->getModel('ErpCargoBn')->where(['cargo_bn' => trim($param['cargo_bn']), 'cargo_bn_type'=>trim($param['cargo_bn_type'])])->find();
        if($cargo_bn_info['id'] == 1){
            $result = [
                'status'=>4,
                'message'=>'系统初始化货权不能更新',
            ];
            return $result;
        }
        if($cargo_bn_info){
            $cargo_bn_id = $cargo_bn_info['id'];
            $status = true;
            // $data = [
            //     'cargo_bn_type' => trim($param['cargo_bn_type']),
            //     'cargo_bn' => trim($param['cargo_bn']),
            //     'update_time' => DateTime(),
            // ];
            // $status = $this->getEvent('ErpBatch')->commonUpdateBatchCargo($cargo_bn_id, $data);
        }else{
            $data = [
                'cargo_bn_type' => trim($param['cargo_bn_type']),
                'cargo_bn' => trim($param['cargo_bn']),
                'create_time' => DateTime(),
                'creater' => $this->getUserInfo('dealer_name'),
            ];
            $status = $cargo_bn_id = $this->getEvent('ErpBatch')->commonAddBatchCargo($data);
        }

        if($status === false){
            $result = [
                'status'=>5,
                'message' => '货权生成失败',
            ];
        }else{
             $result = [
                'status'=>1,
                'message' => '货权生成成功',
                'cargo_bn_id' => $cargo_bn_id,
            ];
        }
        return $result;
    }
    /**
     * 生成批次数据（入口：入库单审核）
     * @param $param
     * @return array
     */
    public function addBatch($param){
        $data = [
            'sys_bn' => erpCodeNumber(20, '', $param['our_company_id'])['order_number'],
            'goods_id' => $param['goods_id'],
            //'stock_id' => $param['stock_id'],
            'storehouse_id' => $param['storehouse_id'],
            'our_company_id' => $param['our_company_id'],
            'region' => $param['region'],
            'stock_type' => $param['stock_type'],
            'total_num' => $param['actual_storage_num'],
            'balance_num' => $param['actual_storage_num'],
            'status' => 1,
            'data_source' => 1,
            'data_source_number' => $param['storage_code'],
            'create_time' => DateTime(),
        ];
        $data['cargo_bn_id'] = $this->getModel('ErpCargoBn')->where(['id'=>trim($param['cargo_bn_id'])])->getField('id');
        //判断仓库是否为全国仓
        $whereStockhouse['id'] = $param['storehouse_id'] ;
        $field = "whole_country";
        $stockHouseIsWhole = $this->getModel("ErpStorehouse")->getListField($field , $whereStockhouse);
        if($stockHouseIsWhole== 1){
            $where_stock = [
                'goods_id'=>$param['goods_id'],
                'object_id'=>$param['storehouse_id'],
                'stock_type'=>$param['stock_type'],
                'region'=>1,
                'our_company_id'=>$param['our_company_id'],
            ];
        }else{
            $where_stock = [
                'goods_id'=>$param['goods_id'],
                'object_id'=>$param['storehouse_id'],
                'stock_type'=>$param['stock_type'],
                'region'=>$param['region'],
                'our_company_id'=>$param['our_company_id'],
            ];
        }
        $data['stock_id'] = $this->getModel('ErpStock')->where($where_stock)->getField('id');
        $batch_status = $batch_id = $this->getEvent('ErpBatch')->commonAddBatch($data);
        if(!$batch_status){
            return ['status'=>2, 'message'=>'批次生成失败'];
        }
        $data['id'] = $batch_id;
        $data_log = [
            'batch_id' => $batch_id,
            'batch_sys_bn' => $data['sys_bn'],
            'change_num' => $data['total_num'],
            'balance_num' => $data['balance_num'],
            'change_type' => 1,
            'change_number' => $param['storage_code'],
            'create_time' => DateTime(),
        ];
        $batch_log_status = $this->getEvent('ErpBatch')->commonAddBatchLog($data_log);

        if(!$batch_log_status){
            return ['status'=>3, 'message'=>'批次日志生成失败'];
        }
        if ($batch_status && $batch_log_status){
            return ['status'=>1, 'message'=>'批次数据生成成功','data'=>$data];
        }
    }


}
