<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpConfigEvent extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP系统配置逻辑处理层
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
        $this->arr = [];
    }

    // +----------------------------------
    // |Facilitator:ERP系统配置列表
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function erpConfigList($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['name']))) {
            $where['name'] = ['like', '%' . trim($param['name']) . '%'];
        }
        $field     = true;
        $erpConfig = $this->getModel('ErpConfig')->erpConfigList($where, $field, $param['start'], $param['length'], 'id desc');
        //空数据
        if (count($erpConfig['data']) > 0) {
            foreach ($erpConfig['data'] as $k => $v) {
                $erpConfig['data'][$k]['config_status'] = configStatus($v['status'],1);
                $erpConfig['data'][$k]['type_str'] = configTypeArr($v['type']);

                //$erpConfig['data'][$k]['purchase_business_type'] = getBusinessType($v['purchase_business_type']);
                if($v['purchase_business_type']){
                    $erpConfig['data'][$k]['type_str'] .= '-' . getBusinessType($v['purchase_business_type']);
                }
            }
        } else {
            $erpConfig['data'] = [];
        }
        $erpConfig['recordsFiltered'] = $erpConfig['recordsTotal'];
        $erpConfig['draw'] = $_REQUEST['draw'];
        return $erpConfig;
    }

    // +----------------------------------
    // |Facilitator:添加ERP系统配置
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function addErpConfig($param = [])
    {
        if(getCacheLock('ErpConfig/addErpConfig')) return ['status' => 99, 'message' => $this->running_msg];
        if(count($param)<=0){
            return ['status'  => 2 , 'message' => '参数有误，请刷新后重试！'];
        }
        if(empty(trim($param['name']))){
            return ['status' => 3 ,'message' => '请填写配置名称！'];
        }
        if(empty(trim($param['key']))){
            return ['status' => 4 ,'message' => '请填写配置key！'];
        }
        if(empty(trim($param['value']))){
            return ['status' => 5 ,'message' => '请填写对应的值！'];
        }
        if(empty(trim($param['status']))){
            return ['status' => 6 ,'message' => '请选择状态！'];
        }
        if($this->getModel('ErpConfig')->where(['key' => trim($param['key'])])->find()){
            return ['status' => 7 ,'message' => '该key已存在，请重新填写！'];
        }
        setCacheLock('ErpConfig/addErpConfig', 1);

        try{
            M()->startTrans();
            # 添加
            $add_data   = [
                'name'          => trim($param['name']),
                'key'           => trim($param['key']),
                'value'         => trim($param['value']),
                'status'        => trim($param['status']),
                'type'        => trim($param['type']),
                'purchase_business_type'        => trim($param['purchase_business_type']),
                'info'          => trim($param['info']),
                'create_time'   => currentTime(),
            ];
            $add_result = $this->getModel('ErpConfig')->add($add_data);

            # 添加log
            $add_data['id'] = $add_result;
            $add_data_log   = [
                'config_id'     => $add_result,
                'log_type'      => 1 ,
                'log_info'      => json_encode($add_data),
                'create_time'   => currentTime(),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id')
            ];
            $add_data_log_status = $this->getModel('ErpConfigLog')->add($add_data_log);

            if($add_result && $add_data_log_status){
                M()->commit();
                $result = ['status' => 1 ,'message' => '添加成功！'];
            }else{
                M()->rollback();
                $result = ['status' => 7 ,'message' => '添加失败！'];
            }

        }catch(\Exception $e){
            M()->rollback();
            cancelCacheLock('ErpConfig/addErpConfig');
            $result =  ['status' =>$e->getCode(),'message' => $e->getMessage()];
        }

        cancelCacheLock('ErpConfig/addErpConfig');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:更新ERP系统配置
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function updateErpConfig($param = [])
    {
        if(getCacheLock('ErpConfig/updateErpConfig')) return ['status' => 99, 'message' => $this->running_msg];
        if(count($param)<=0){
            return ['status'  => 2 , 'message' => '参数有误，请刷新后重试！'];
        }
        if(intval($param['id']) <= 0){
            return ['status' => 8 ,'message' => '参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['name']))){
            return ['status' => 3 ,'message' => '请填写配置名称！'];
        }
        if(empty(trim($param['key']))){
            return ['status' => 4 ,'message' => '请填写配置key！'];
        }
        if(empty(trim($param['value']))){
            return ['status' => 5 ,'message' => '请填写对应的值！'];
        }
        if(empty(trim($param['status']))){
            return ['status' => 6 ,'message' => '请选择状态！'];
        }
        if(!$this->getModel('ErpConfig')->where(['id' => intval(trim($param['id']))])->find()){
            return ['status' => 7 ,'message' => '未查询到该笔配置信息，请刷新后重试！'];
        }
        if($this->getModel('ErpConfig')->where(['key' => trim($param['key']),'id' => ['neq' ,intval($param['id'])]])->find()){
            return ['status' => 7 ,'message' => '该key已存在，请重新填写！'];
        }
        setCacheLock('ErpConfig/updateErpConfig', 1);

        try{
            M()->startTrans();
            # 修改
            $update_data   = [
                'name'          => trim($param['name']),
                'key'           => trim($param['key']),
                'value'         => trim($param['value']),
                'status'        => trim($param['status']),
                'info'          => trim($param['info']),
                'update_time'   => currentTime(),
            ];
            $update_result = $this->getModel('ErpConfig')->saveErpConfig(['id' => intval($param['id'])],$update_data);

            # 添加log
            $update_data['id'] = $param['id'];
            $add_data_log   = [
                'config_id'     => $update_result,
                'log_type'      => 2 ,
                'log_info'      => json_encode($update_data),
                'create_time'   => currentTime(),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id')
            ];
            $add_data_log_status = $this->getModel('ErpConfigLog')->add($add_data_log);

            if($update_result && $add_data_log_status){
                M()->commit();
                $result = ['status' => 1 ,'message' => '修改成功！'];
            }else{
                M()->rollback();
                $result = ['status' => 7 ,'message' => '修改失败！'];
            }

        }catch(\Exception $e){
            M()->rollback();
            cancelCacheLock('ErpConfig/updateErpConfig');
            $result =  ['status' =>$e->getCode(),'message' => $e->getMessage()];
        }

        cancelCacheLock('ErpConfig/updateErpConfig');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:更新ERP系统配置状态
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function updateErpConfigStatus($param = [])
    {
        if(getCacheLock('ErpConfig/updateErpConfig')) return ['status' => 99, 'message' => $this->running_msg];
        if(count($param)<=0){
            return ['status'  => 2 , 'message' => '参数有误，请刷新后重试！'];
        }
        if(intval($param['id']) <= 0){
            return ['status' => 8 ,'message' => '参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['status']))){
            return ['status' => 3 ,'message' => '请选择开启或关闭！'];
        }
        $data = $this->getModel('ErpConfig')->where(['id' => intval(trim($param['id']))])->find();
        if(empty($data)){
            return ['status' => 7 ,'message' => '未查询到该笔配置信息，请刷新后重试！'];
        }
        if($data['status'] == intval($param['status'])){
            $status_message = $data['status'] == 1 ? '开启' : '关闭';
            return ['status' => 8 ,'message' => '该笔配置数据已为'.$status_message.'状态，请勿重复操作！'];
        }
        setCacheLock('ErpConfig/updateErpConfigStatus', 1);

        try{
            M()->startTrans();
            # 修改
            $update_data   = [
                'status'        => trim($param['status']),
                'update_time'   => currentTime(),
            ];
            $update_result = $this->getModel('ErpConfig')->saveErpConfig(['id' => intval($param['id'])],$update_data);

            # 添加log
            $data['status']      = $param['status'];
            $data['update_time'] = $update_data['update_time'];
            $add_data_log   = [
                'config_id'     => $update_result,
                'log_type'      => 2 ,
                'log_info'      => json_encode($data),
                'create_time'   => currentTime(),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id')
            ];
            $add_data_log_status = $this->getModel('ErpConfigLog')->add($add_data_log);

            if($update_result && $add_data_log_status){
                M()->commit();
                $result = ['status' => 1 ,'message' => '操作成功！'];
            }else{
                M()->rollback();
                $result = ['status' => 7 ,'message' => '操作失败！'];
            }

        }catch(\Exception $e){
            M()->rollback();
            cancelCacheLock('ErpConfig/updateErpConfigStatus');
            $result =  ['status' =>$e->getCode(),'message' => $e->getMessage()];
        }

        cancelCacheLock('ErpConfig/updateErpConfigStatus');
        return $result;
    }

}

?>
