<?php
/**
 * 盘点模块处理层
 * @author qianbin
 * @time 2018-01-03
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpInventoryEvent extends BaseController
{

    public $inventoryStockInData, $inventoryStockInBeforeNum, $inventoryStockIds;
    public function _initialize()
    {
        //$this->inventoryStockOutData = [];
        $this->inventoryStockInData = [];
        $this->inventoryStockInBeforeNum = [];
        $this->inventoryStockIds = [];
    }
    /**
     * 新增盘点计划
     * @param array $param
     * @author qianbin
     * @time 2018-01-03
     * @return array
     */
    public function addInventoryPlan($param = [])
    {
        $result = [];
        if(empty(trim($param['inventory_name']))) return ['status' => 2 , 'message' => '请填写盘点计划名称！'];
        if(empty(trim($param['inventory_type'])) || intval($param['inventory_type']) <= 0) return ['status' => 3 , 'message' => '请选择盘点仓库类型！'];
        if(empty($param['inventory_storehouse_ids']) || count($param['inventory_storehouse_ids']) <= 0) return ['status' => 4 , 'message' => '请选择仓库！'];
        # 验证名称是否唯一
        $data_name = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['inventory_name' => trim($param['inventory_name'])],'id,status');
        if(count($data_name)> 0 ){
            return ['status' => 5 , 'message' => '盘点方案名称已存在，请重新填写！'];
        }
        if (getCacheLock('ErpInventory/addInventoryPlan')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/addInventoryPlan', 1);
        # ----开始添加
        M()->startTrans();
        $add_data = [
            'inventory_name' => trim($param['inventory_name']),
            'is_lock'        => 1,
            'inventory_type' => intval($param['inventory_type']),
            'inventory_storehouse_ids' => json_encode($param['inventory_storehouse_ids'],JSON_UNESCAPED_UNICODE),
            'our_company_id' => session('erp_company_id'),
            'status'         => 1,
            'is_use'         => 1 ,
            'creater_id'     => $this->getUserInfo('id'),
            'creater_name'   => $this->getUserInfo('dealer_name'),
            'create_time'    => currentTime()
        ];
        $result_add = $this->getModel('ErpInventoryPlan')->addErpInventoryPlan($add_data);

        $add_data['id'] = $result_add;
        $add_log = [
            'source_type'   => 1,
            'source_id'     => $result_add,
            'log_type'      => 1,
            'log_info'      => serialize($add_data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => currentTime()
        ];

        $result_log = $this->getModel('ErpInventoryLog')->add($add_log);

        if ($result_add && $result_log) {
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
        cancelCacheLock('ErpInventory/addInventoryPlan');

        return $result;
    }

    /**
     * 编辑盘点计划
     * @param array $param
     * @author qianbin
     * @time 2018-01-03
     * @return array
     */
    public function updateInventoryPlan($param = [])
    {
        $result = [];
        if(empty(trim($param['id'])) || intval($param['id']) <= 0 ) return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        if(empty(trim($param['inventory_name'])))                   return ['status' => 3 , 'message' => '请填写盘点计划名称！'];
        if(empty(trim($param['inventory_type'])) || intval($param['inventory_type']) <= 0) return ['status' => 4 , 'message' => '请选择盘点仓库类型！'];
        if(empty($param['inventory_storehouse_ids']) || count($param['inventory_storehouse_ids']) <= 0) return ['status' => 5 , 'message' => '请选择仓库！'];
        $plan_data = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id' => intval($param['id'])],'id,status');
        if(empty($plan_data))                   return ['status' => 6 , 'message' => '参数错误，请刷新后重试！'];
        if(intval($plan_data['status']) != 1 )  return ['status' => 7 , 'message' => '该笔盘点计划已非未审核状态，无法操作！'];
        # 验证名称是否唯一
        $data_name = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['inventory_name' => trim($param['inventory_name']),'id' =>['neq',intval($param['id'])]],'id,status');
        if(count($data_name) > 0 ){
            return ['status' => 8 , 'message' => '盘点方案名称已存在，请重新填写！'];
        }

        # 开始修改
        if (getCacheLock('ErpInventory/updateInventoryPlan')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/updateInventoryPlan', 1);
        M()->startTrans();
        $update_data = [
            'inventory_name' => trim($param['inventory_name']),
            'inventory_type' => intval($param['inventory_type']),
            'inventory_storehouse_ids' => json_encode($param['inventory_storehouse_ids'],JSON_UNESCAPED_UNICODE),
            'update_time'    => currentTime()
        ];
        $result_update = $this->getModel('ErpInventoryPlan')->saveErpInventoryPlan(['id' => intval($param['id'])],$update_data);
        $plan_data     = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id' => intval($param['id'])],'*');
        $add_log = [
            'source_type'   => 1,
            'source_id'     => intval($param['id']),
            'log_type'      => 2,
            'log_info'      => serialize($plan_data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => currentTime()
        ];
        $result_log = $this->getModel('ErpInventoryLog')->add($add_log);

        if ($result_update && $result_log) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '修改成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '修改失败',
            ];
        }
        cancelCacheLock('ErpInventory/updateInventoryPlan');
        return $result;
    }

    /**
     * 确认盘点计划
     * @author qianbin
     * @time 2018-01-03
     * @param $param
     * @return array
     */
    public function confirmInventoryPlan($id = 0){
        if($id <= 0 ) return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        $plan_data = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id' => intval($id)],'id,status');
        if(empty($plan_data)) return ['status' => 3 , 'message' => '参数错误，请刷新后重试！'];
        if($plan_data['status'] != 1 ) return['status' => 4 , 'message' => '该笔盘点方案并非未审核状态，操作失败！'];
        # 开始修改 --------
        if (getCacheLock('ErpInventory/confirmInventoryPlan')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/confirmInventoryPlan', 1);
        M()->startTrans();
        $result_update = $this->getModel('ErpInventoryPlan')->saveErpInventoryPlan(['id' => intval($id)],['status' => 10]);
        $plan_data['status'] = 10 ;
        $add_log = [
            'source_type'   => 1,
            'source_id'     => intval($id),
            'log_type'      => 3,
            'log_info'      => serialize($plan_data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => currentTime()
        ];
        $result_log = $this->getModel('ErpInventoryLog')->add($add_log);

        if ($result_update && $result_log) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功！',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败！',
            ];
        }
        cancelCacheLock('ErpInventory/confirmInventoryPlan');
        return $result;
    }

    /**
     * 取消盘点计划
     * @author qianbin
     * @time 2018-01-03
     * @param $param
     * @return array
     */
    public function cancelInventoryPlan($id = 0){
        if($id <= 0 ) return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        $plan_data = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id' => intval($id)],'id,status');
        if(empty($plan_data)) return ['status' => 3 , 'message' => '参数错误，请刷新后重试！'];
        if($plan_data['status'] == 2 ) return['status' => 4 , 'message' => '该笔盘点方案已为取消状态，请勿重复操作！'];
        if($plan_data['status'] == 10 ) return['status' => 5 , 'message' => '只有未审核的盘点方案才可以取消！'];
        # 开始修改 --------
        if (getCacheLock('ErpInventory/cancelInventoryPlan')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/cancelInventoryPlan', 1);
        M()->startTrans();
        $result_update = $this->getModel('ErpInventoryPlan')->saveErpInventoryPlan(['id' => intval($id)],['status' => 2]);
        $plan_data['status'] = 2 ;
        $add_log = [
            'source_type'   => 1,
            'source_id'     => intval($id),
            'log_type'      => 4,
            'log_info'      => serialize($plan_data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => currentTime()
        ];
        $result_log = $this->getModel('ErpInventoryLog')->add($add_log);

        if ($result_update && $result_log) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '取消成功！',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '取消失败！',
            ];
        }
        cancelCacheLock('ErpInventory/cancelInventoryPlan');
        return $result;
    }

    /**
     * 关闭盘点计划
     * @author qianbin
     * @time 2018-01-03
     * @param $param
     * @return array
     */
    public function cancelInventoryPlanUse($id = 0){
        if($id <= 0 ) return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        $plan_data = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id' => intval($id)],'id,status');
        if(empty($plan_data)) return ['status' => 3 , 'message' => '参数错误，请刷新后重试！'];
        if($plan_data['status'] != 10 ) return['status' => 5 , 'message' => '只有已确认的盘点方案才可以关闭！'];
        # 开始修改 --------
        if (getCacheLock('ErpInventory/cancelInventoryPlanUse')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/cancelInventoryPlanUse', 1);
        M()->startTrans();
        $result_update = $this->getModel('ErpInventoryPlan')->saveErpInventoryPlan(['id' => intval($id)],['is_use' => 2]);
        $plan_data['status'] = 2 ;
        $add_log = [
            'source_type'   => 1,
            'source_id'     => intval($id),
            'log_type'      => 11,
            'log_info'      => serialize($plan_data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => currentTime()
        ];
        $result_log = $this->getModel('ErpInventoryLog')->add($add_log);

        if ($result_update && $result_log) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '关闭成功！',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '关闭失败！',
            ];
        }
        cancelCacheLock('ErpInventory/cancelInventoryPlanUse');
        return $result;
    }



     /**
     * 盘点计划列表
     * @param array $param
     * @author qianbin
     * @time 2018-01-03
     * @return array
     */
    public function inventoryPlanList($param = [])
    {
        $result   = [];
        $where  = [];
        if (trim($param['inventory_name'])) {
            $where['inventory_name'] = ['like' , '%'.trim($param['inventory_name']).'%'];
        }
        if(trim($param['inventory_type']) && intval($param['inventory_type']) > 0){
            $where['inventory_type'] = trim($param['inventory_type']);
        }
        if (trim($param['creater_name'])){
            $where['creater_name'] = ['like' , '%'.trim($param['creater_name']).'%'];
        }
        $where['our_company_id'] = session('erp_company_id');
        $field  = '*';
        $result = $this->getModel('ErpInventoryPlan')->getErpInventoryPlanList($where, $field, $param['start'], $param['length']);
        if(count($result['data'])>0) {
            foreach ($result['data'] as $k => $v ){
                $result['data'][$k]['inventory_type']   = getInventoryPlanType($v['inventory_type']);
                $result['data'][$k]['status']           = getInventoryStatus($v['status'] , 1);
                $result['data'][$k]['is_use']           = isUse($v['is_use'],1);
            }
        }
        $result['recordsFiltered'] = $result['recordsTotal'];
        $result['draw'] = $_REQUEST['draw'];
        return $result;
    }

   /*
    ************************************************************
    *
    *   斌
    *
    *************************************************************
    */
    /**
     * 获取省份下的仓库 、 加油网点
     * @author qianbin
     * @time 2018-01-03
     * @param $param
     * @return array
     */
    public function getStoreHouseByProvince($param = []){
        $stock       = [];
        $result      = [];
        $check_id    = [];
        $where_store = [];
        $where_skid  = [];
        # 如果选择了省份，获取下面的城市
        if(intval($param['id']) > 0 ){
            //全国属性特殊处理
            if (intval($param['id']) == 1) {
                $where_store['whole_country'] = 1;
            } else {
                $city = getCityListByProvinceId()[intval($param['id'])];
                if(count($city) <= 0 ) return ['status' => 3 , 'message' => '参数错误，请刷新后重试！'];
                $where_store['region']  = $where_skid['region'] = ['in' , $city] ;
            }
        }
        //edit xiaowen 新增零售仓盘点（并优化原有程序） 2018-12-14
        $stock = $this->getStockByInventoryType($param['type'], $where_store);

        if(count($stock) <= 0 ) return ['status' => 4 , 'message' => '未查询到符合条件的仓库，请重试！'];

        # 查询对应仓库是否发生业务
        $stock_id  = array_unique(array_column($stock , 'id'));
        $where_stock['object_id'] = ['in' , $stock_id];
        //============== edit xiaowen 增加当前ERP账套、和库存状态的查询条件 2018-12-17=======================
        $where_stock['our_company_id'] = session('erp_company_id');
        $where_stock['status'] = 1;
        //====================================================================================================
        $object_id = $this->getModel('ErpStock')->field('object_id')->where($where_stock)->group('object_id')->select();
        if(count($object_id) <= 0 ) return ['status' => 5 , 'message' => '未查询到发生业务的仓库，请重试！'];
        # 组装数据
        foreach ($object_id as $k => $v ){
            $result[]   = $stock[$v['object_id']];
            $check_id[] = $v['object_id'];
        }

        # 打印日志，排查哪些未发生业务
        log_info('----->仓库id');
        log_info(join(',',($stock_id)));
        log_info('----->发生业务的仓库id');
        log_info(join(',',($check_id)));
        log_info('----->未发生业务的仓库id');
        log_info(join(',',(array_diff($stock_id,$check_id))));
        #---------------------------

        return ['status' => 1 , 'data' => $result];
    }


   /*
    ************************************************************
    *
    *   文
    *
    *************************************************************
    */

    /**
     * 新增盘点单
     * @param array $param
     * @author qianbin edit xiaowen
     * @time 2018-01-03
     * @return array
     */
    public function addInventoryOrder($param = [])
    {
        if (getCacheLock('ErpInventory/addInventoryOrder')) return ['status' => 99, 'message' => $this->running_msg];
        //$result = [];
        if(!intval($param['inventory_plan_id'])){
            $result = ['status'=>2, 'message'=>'请选择盘点计划'];
        }else if(!trim($param['add_order_date'])){
            $result = ['status'=>3, 'message'=>'请选择盘点计划日期'];
        }else if(!trim($param['inventory_order_type'])){
            $result = ['status'=>4, 'message'=>'请选择盘点类型'];
        }else{
            setCacheLock('ErpInventory/addInventoryOrder',1);

            M()->startTrans();
            $order_data = [
                'inventory_order_number' =>erpCodeNumber(15)['order_number'],
                'inventory_plan_id' => $param['inventory_plan_id'],
                'add_order_date' => $param['add_order_date'],
                'inventory_order_type' => $param['inventory_order_type'],
                'remark' => $param['remark'],
                'our_company_id' => session('erp_company_id'),
                'create_time' => currentTime(),
                'creater_id' => $this->getUserInfo('id'),
                'creater_name' => $this->getUserInfo('dealer_name'),
            ];
            $order_status = $this->getModel('ErpInventoryOrder')->addErpInventoryOrder($order_data);

            $log_data = [
                'source_type' => 2,
                'source_id' => $order_status,
                'log_type' => 1,
                'log_info' => serialize($order_data),
            ];
            $log_status = $this->addInventoryLog($log_data);
            if($order_status && $log_status){
                M()->commit();
                $result = ['status'=>1, 'message'=>'操作成功'];
            }else{
                M()->rollback();
                $result = ['status'=>0, 'message'=>'操作失败'];
            }
            cancelCacheLock('ErpInventory/addInventoryOrder');
        }
        return $result;
    }

    /**
     * 编辑盘点单
     * @param array $param
     * @author qianbin edit xiaowen
     * @time 2018-01-03
     * @return array
     */
    public function updateInventoryOrder($param = [])
    {

        if (getCacheLock('ErpInventory/updateInventoryOrder')) return ['status' => 99, 'message' => $this->running_msg];
        //$result = [];

        if(!intval($param['id'])){
            $result = ['status'=>2, 'message'=>'请选择盘点单,再编辑'];
        }
        else if(($this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval($param['id'])])['order_status']) != 1){
            $result = ['status'=>6, 'message'=>'只有未审核的盘点单才可以编辑'];
        }
        else if(!intval($param['inventory_plan_id'])){
            $result = ['status'=>3, 'message'=>'请选择盘点计划'];
        }else if(!trim($param['add_order_date'])){
            $result = ['status'=>4, 'message'=>'请选择盘点计划日期'];
        }else if(!trim($param['inventory_order_type'])){
            $result = ['status'=>5, 'message'=>'请选择盘点类型'];
        }else{
            setCacheLock('ErpInventory/updateInventoryOrder',1);

            M()->startTrans();
            $order_data = [
                'inventory_plan_id' => $param['inventory_plan_id'],
                'add_order_date' => $param['add_order_date'],
                'inventory_order_type' => $param['inventory_order_type'],
                'remark' => $param['remark'],
                'our_company_id' => session('erp_company_id'),
                'update_time' => currentTime(),
            ];
            $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id'=>intval($param['id'])], $order_data);
            $log_data = [
                'source_type' => 2,
                'source_id' => intval($param['id']),
                'log_type' => 2,
                'log_info' => serialize($order_data),

            ];
            $log_status = $this->addInventoryLog($log_data);
            if($order_status && $log_status){
                M()->commit();
                $result = ['status'=>1, 'message'=>'操作成功'];
            }else{
                M()->rollback();
                $result = ['status'=>0, 'message'=>'操作失败'];
            }
            cancelCacheLock('ErpInventory/updateInventoryOrder');
        }
        return $result;
    }


    /**
     * 盘点单列表
     * @param array $param
     * @author qianbin edit xiaowen
     * @time 2018-01-03
     * @return array
     */
    public function inventoryOrderList($param = [])
    {
        $where  = [];
        if (trim($param['start_time']) && empty(trim($param['end_time']))) {
            $where['o.add_order_date'] = ['egt',trim($param['start_time'])];
        }
        if (trim($param['end_time'])  && empty(trim($param['start_time']))) {
            $where['o.add_order_date'] = ['elt',trim($param['end_time'])];
        }
        if (trim($param['start_time']) && trim($param['end_time'])) {
            $where['o.add_order_date'] = ['between',[trim($param['start_time']),trim($param['end_time'])]];
        }
        if (trim($param['order_number'])){
            $where['o.inventory_order_number'] = ['like' , trim($param['order_number']).'%'];
        }
        if (intval($param['inventory_plan_id'])){
            $where['o.inventory_plan_id'] = intval($param['inventory_plan_id']);
        }

        if (trim($param['creater_name'])){
            $where['o.creater_name'] = ['like' , '%'.trim($param['creater_name']).'%'];
        }
        if (intval(trim($param['order_status']))){
            $where['o.order_status'] = intval(trim($param['order_status']));
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');
        $field = 'o.*,p.inventory_name,p.inventory_type';
        $data = $this->getModel('ErpInventoryOrder')->getErpInventoryOrderList($where, $field, $param['start'], $param['length']);
        if($data['data']){
            foreach($data['data'] as $key=>$value){
                $data['data'][$key]['inventory_order_type'] = inventoryOrderType($value['inventory_order_type']);
                $data['data'][$key]['inventory_plan_type'] = getInventoryPlanType($value['inventory_type']);
                $data['data'][$key]['order_status'] = inventoryOrderStatus($value['order_status'], true);
                $data['data'][$key]['is_locked'] = isStatus($value['is_locked']);
                $data['data'][$key]['is_create_data'] = isStatus($value['is_create_data']);
                $data['data'][$key]['is_create_order'] = isStatus($value['is_create_order']);
                $data['data'][$key]['is_confirm_data'] = isStatus($value['is_confirm_data']);
                $data['data'][$key]['check_status'] = isStatus($value['check_status']);
            }
        }else{
            $data['recordsTotal'] = 0;
            $data['data'] = [];
        }
        //print_r($data);
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 取消盘点单
     * @author xiaowen
     * @param $id
     * @return array
     */
    public function cancelInventoryOrder($id){
        if (getCacheLock('ErpInventory/cancelInventoryOrder')) return ['status' => 99, 'message' => $this->running_msg];
        $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval($id)]);
        if(!$id){
            $result = [
                'status' => 0,
                'message' => '请选择盘点单',
            ];
        } else if($order_info['order_status'] != 1){
            $result = [
                'status' => 2,
                'message' => '只有未审核盘点单才能取消',
            ];
        }else{
            setCacheLock('ErpInventory/cancelInventoryOrder', 1);
            M()->startTrans();
            $order_data = [
                'order_status' => 2,
                'update_time' => currentTime(),
            ];
            $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id'=>intval($id)], $order_data);

            $log_data = [
                'source_type' => 2,
                'source_id' => $order_info['id'],
                'log_type' => 4,
                'log_info' => serialize($order_data),
            ];
            $log_status = $this->addInventoryLog($log_data);
            if($order_status && $log_status){
                M()->commit();
                $result = [
                    'status' => 1,
                    'message' => '操作成功',
                ];
            }else{
                M()->rollback();
                $result = [
                    'status' => 4,
                    'message' => '操作失败',
                ];
            }

            cancelCacheLock('ErpInventory/cancelInventoryOrder');
        }

        return $result;
    }

    /**
     * 确认盘点单
     * @author xiaowen
     * @param $id
     * @return array
     */
    public function confirmInventoryOrder($id)
    {
        if (getCacheLock('ErpInventory/confirmInventoryOrder')) return ['status' => 99, 'message' => $this->running_msg];
        $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id' => intval($id)]);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '请选择盘点单',
            ];
        } else if ($order_info['order_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '只有未审核盘点单才能确认',
            ];
        } else {
            setCacheLock('ErpInventory/confirmInventoryOrder', 1);
            M()->startTrans();
            $order_data = [
                'order_status' => 10,
                'update_time' => currentTime(),
            ];
            $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id' => intval($id)], $order_data);

            $log_data = [
                'source_type' => 2,
                'source_id' => $order_info['id'],
                'log_type' => 3,
                'log_info' => serialize($order_data),
            ];
            $log_status = $this->addInventoryLog($log_data);
            if ($order_status && $log_status) {
                M()->commit();
                $result = [
                    'status' => 1,
                    'message' => '操作成功',
                ];
            } else {
                M()->rollback();
                $result = [
                    'status' => 4,
                    'message' => '操作失败',
                ];
            }


            cancelCacheLock('ErpInventory/confirmInventoryOrder');
        }
        return $result;
    }


    /**
     * 生成操作日志
     * @param $param
     * @return mixed
     */
    protected function addInventoryLog($param)
    {
        $log_data = [
            'source_type' => $param['source_type'],
            'source_id' => $param['source_id'],
            'log_type' => $param['log_type'],
            'log_info' => $param['log_info'],
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
        ];
        $log_status = $this->getModel('ErpInventoryLog')->add($log_data);
        return $log_status;
    }

    /**
     * 生成盘点数据
     * @author xiaowen
     * @param $id
     * @return array $result
     */
    public function createInventoryOrderData($id){

        if(!$id){
            $result = [
                'status' => 0,
                'message' => '请选择盘点单',
            ];
        }else{
            $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval($id)]);
            if($order_info['order_status'] != 10){
                $result = [
                    'status' => 2,
                    'message' => '只有已确认的盘点单才能生成盘点数据',
                ];
            }else if($order_info['is_create_data'] == 1){
                $result = [
                    'status' => 3,
                    'message' => '已生成盘点数据,无须再次生成',
                ];
            }else{
                if (getCacheLock('ErpInventory/createInventoryOrderData')) return ['status' => 99, 'message' => $this->running_msg];

                $plan_info = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id'=>$order_info['inventory_plan_id']]);
                $plan_info['inventory_storehouse_ids'];
                $log_info_data = array(
                    'event'=> '生成盘点明细数据',
                    'key'=> $order_info['inventory_order_number'],
                    'request'=> json_decode($plan_info['inventory_storehouse_ids'])
                );

                log_write($log_info_data);
                //log_info(print_r(json_decode($plan_info['inventory_storehouse_ids']), true));
                $plan_info['inventory_storehouse_ids'] = array_keys(json_decode($plan_info['inventory_storehouse_ids'], true));
                log_info(print_r($plan_info, true));
                if($plan_info['inventory_storehouse_ids'] && in_array($plan_info['inventory_type'], array_keys(getInventoryPlanType()))){

                    $stock_where = [
                        'object_id'=>['in', $plan_info['inventory_storehouse_ids']],
                        'stock_type'=>$plan_info['inventory_type'] == 6 ? ['in','6,7,8'] : $plan_info['inventory_type'],
                        'our_company_id'=>$order_info['our_company_id'],
                    ];
                    $stock_data = $this->getModel('ErpStock')->where($stock_where)->select();
                    if($stock_data){
                        M()->startTrans();
                        setCacheLock('ErpInventory/createInventoryOrderData', 1);
                        //生成盘点数据
                        $inventory_order_detail = [];
                        foreach($stock_data as $key=>$stock){
                            $inventory_order_detail[$key]['inventory_order_id'] = $order_info['id'];
                            $inventory_order_detail[$key]['inventory_order_number'] = $order_info['inventory_order_number'];
                            $inventory_order_detail[$key]['stock_id'] = $stock['id'];
                            $inventory_order_detail[$key]['our_company_id'] = $order_info['our_company_id'];
                            $inventory_order_detail[$key]['goods_id'] = $stock['goods_id'];
                            $inventory_order_detail[$key]['region'] = $stock['region'];
                            $inventory_order_detail[$key]['stock_num'] = $stock['stock_num'];
                            $inventory_order_detail[$key]['transportation_num'] = $stock['transportation_num'];
                            $inventory_order_detail[$key]['create_time'] = currentTime();
                            $inventory_order_detail[$key]['creater'] = $this->getUserInfo('dealer_name');
                            $inventory_order_detail[$key]['creater_id'] = $this->getUserInfo('id');

                        }
                        $detail_status = $this->getModel('ErpInventoryOrderDetail')->addAll($inventory_order_detail);

                        //更新盘点单，状态更新为已生成盘点数据
                        $order_info['is_create_data'] = 1;
                        $order_info['update_time'] = currentTime();
                        $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id'=>$order_info['id']], $order_info);
                        //生成操作日志
                        $log_data = [
                            'source_type' => 2,
                            'source_id' => $order_info['id'],
                            'log_type' => 5,
                            'log_info' => serialize($order_info),
                        ];
                        $log_status = $this->addInventoryLog($log_data);

                        if($detail_status && $order_status && $log_status){
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
                    }else{
                        $result = [
                            'status' => 3,
                            'message' => '该盘点计划无有效的库存数据，无法生成盘点数据',
                        ];
                    }

                }else{
                    $result = [
                        'status' => 4,
                        'message' => '盘点类型或盘点仓库数据有误，请确认盘点计划是否设置正确',
                    ];
                }
                cancelCacheLock('ErpInventory/createInventoryOrderData');
            }
        }

        return $result;
    }

    /**
     * 验证是否生成数据
     * @param $id
     * @author xiaowen
     * @return array $result
     */
    public function checkCreateOrderData($id){
        $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval($id)]);
        if($order_info['is_create_data'] == 1){
            $result = [
                'status' => 1,
                'message' => '已生成数据',
            ];
        }else{
            $result = [
                'status' => 0,
                'message' => '未生成数据，无法查看详情',
            ];
        }
        return $result;
    }
    /**
     * 盘点单详情
     * @author qianbin edit xiaowen
     * @time 2018-01-03
     * @param int $id
     * @param array $param
     * @return array $data
     */
    public function inventoryOrderDetail($id = 0, $param = [])
    {
        $where = [];
        if($id){

            $field = 'd.*, g.goods_code,g.goods_name,g.source_from,g.grade,g.level,s.object_id,s.stock_type';
            $where['d.inventory_order_id'] = intval($id);
            //$order_info = $this->getModel('ErpInventoryOrder')->alias('o')->field('o.id,o.inventory_order_type,p.inventory_type')->where(['id'=>intval($id)])->join('oil_erp_inventory_plan p on o.inventory_plan_id = p.id')->find();
            if ($param['export'] == 1) {
                $data = $this->getModel('ErpInventoryOrderDetail')->getAllErpInventoryOrderDetailList($where, $field);
            } else {
                $data = $this->getModel('ErpInventoryOrderDetail')->getErpInventoryOrderDetailList($where, $field,$_REQUEST['start'], $_REQUEST['length']);
            }
            //print_r($data);
            if($data['data']){

                //获取所有详情的库存仓库ID
                $object_ids = array_unique(array_column($data['data'], 'object_id'));
                //如果类型为城市仓、代采仓、零售仓、实体仓，则查询ErpStorehouse表
                $stock_object_data = $this->getModel('ErpStorehouse')->where(['id'=>['in',$object_ids]])->getField('id,storehouse_name');
                //获取盘点明细批次id
                $batch_ids = array_unique(array_column($data['data'], 'batch_id'));
                $batch_data = [];
                if($batch_ids) {
                    $batch_data = $this->getModel('ErpBatch')->where(['id' => ['in', $batch_ids]])->getField('id,sys_bn,cargo_bn_id');

                }
                //获取批次货权id
                $cargo_bn_ids = array_unique(array_column($batch_data, 'cargo_bn_id'));
                $cargo_data =[];
                if($cargo_bn_ids){
                    $cargo_data = $this->getModel('ErpCargoBn')->where(['id' => ['in', $cargo_bn_ids]])->getField('id,cargo_bn');
                }
                foreach($data['data'] as $key=>$value){
                    //$data['data'][$key]['storehouse_name'] = $stock_object_data[$value['object_id']];
                    $data['data'][$key]['storehouse_name'] = $stock_object_data[$value['storehouse_id']];
                    $data['data'][$key]['inventory_order_number'] = trim($value['inventory_order_number']);
                    $data['data'][$key]['order_status'] = inventoryOrderStatus($value['order_status'], true);
                    $data['data'][$key]['stock_num'] = ErpFormatFloat(getNum($value['stock_num']));
                    $data['data'][$key]['transportation_num'] = ErpFormatFloat(getNum($value['transportation_num']));
                    $data['data'][$key]['goods_code'] = trim($value['goods_code']);
                    $data['data'][$key]['goods_name'] = trim($value['goods_name']);
                    $data['data'][$key]['source_from'] = trim($value['source_from']);
                    $data['data'][$key]['grade'] = trim($value['grade']) ? trim($value['grade']) : '--';
                    $data['data'][$key]['level'] = trim($value['level']) ? trim($value['level']) : '--';
                    $data['data'][$key]['inventory_stock_num'] = ErpFormatFloat(getNum($value['inventory_stock_num']));
                    $data['data'][$key]['stock_diff_num'] = ErpFormatFloat(getNum($value['stock_diff_num']));
                    //返回批次和货权数据 2019-3-1 xiaowen
                    $data['data'][$key]['batch_sys_bn'] = $value['batch_id'] ? $batch_data[$value['batch_id']]['sys_bn'] : '--';
                    $data['data'][$key]['batch_cargo_bn'] = $value['batch_id'] ? $cargo_data[$batch_data[$value['batch_id']]['cargo_bn_id']] : '--';
                }
            }else{
                $data['recordsTotal'] = 0;
                $data['data'] = [];
            }

            $data['recordsFiltered'] = $data['recordsTotal'];
            $data['draw'] = $_REQUEST['draw'];

        }else {
            $data['recordsTotal'] = 0;
            $data['data'] = [];
            $data['recordsFiltered'] = $data['recordsTotal'];
            $data['draw'] = $_REQUEST['draw'];
        }
        return $data;
    }

    /**
     * 盘点明细更新 实盘数量
     * @param $param
     * @author xiaowen
     * @return array
     */
    public function inventoryDetailUpdate($param){
        if(intval($param['id']) && is_numeric(trim($param['inventory_stock_num']))){
            $data = $this->getModel('ErpInventoryOrderDetail')->findErpInventoryOrderDetail(['id'=>intval($param['id'])]);
            if($data){
                //实际盘点数量如果未变化 则无须再更新数据库
//                if($data['inventory_stock_num'] == setNum(trim($param['inventory_stock_num']))){
//                    return $result = [
//                        'status' => 1,
//                        'message' => '更新成功',
//                    ];
//                }
                $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>$data['inventory_order_id']]);
                //log_info(print_r($order_info,true));
                if($order_info['order_status'] != 10){
                    $result = [
                        'status' => 3,
                        'message' => '盘点单必须已确认',
                    ];
                }else if($order_info['is_create_data'] != 1){
                    $result = [
                        'status' => 4,
                        'message' => '盘点单必须已生成数据',
                    ];
                }else if($order_info['is_confirm_data'] == 1){
                    $result = [
                        'status' => 5,
                        'message' => '盘点单数据已确认,无法再修改',
                    ];
                }else{
                    M()->startTrans();
                    $detail_data = [
                        'update_time' => currentTime(),
                        'inventory_stock_num' => setNum(trim($param['inventory_stock_num'])),
                        'stock_diff_num' => setNum(trim($param['inventory_stock_num'])) - $data['stock_num'],
                    ];
                    $status = $this->getModel('ErpInventoryOrderDetail')->saveErpInventoryOrderDetail(['id'=>intval($param['id'])], $detail_data);
                    //生成操作日志
                    $log_data = [
                        'source_type' => 3,
                        'source_id' => $data['id'],
                        'log_type' => 2,
                        'log_info' => serialize($data),
                    ];
                    $log_status = $this->addInventoryLog($log_data);
                    if($status && $log_status){
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
            }else{
                $result = [
                    'status' => 6,
                    'message' => '该明细不存在',
                ];
            }
        }else{
            $result = [
                'status' => 2,
                'message' => '参数有误',
            ];
        }
        return $result;
    }
    /**
     * 确认盘点数据
     * @param $id
     * @return array
     * @author xiaowen
     * @time 2018-1-9
     */
    public function confirmOrderDetailData($id){

        $id = intval($id);
        if($id){
            $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval($id)]);
            if($order_info){
                if($order_info['is_confirm_data'] == 1){
                    $result = [
                        'status' => 2,
                        'message' => '盘点单数据已确认，无法操作',
                    ];
                    return $result;
                }
                if (getCacheLock('ErpInventory/confirmOrderDetailData')) return ['status' => 99, 'message' => $this->running_msg];
                M()->startTrans();
                setCacheLock('ErpInventory/confirmOrderDetailData', 1);
                $order_data = [
                    'is_confirm_data' => 1,
                    'update_time' => currentTime(),
                ];
                $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id'=>$id], $order_data);
                $log_data = [
                    'source_type' => 2,
                    'source_id' => $order_info['id'],
                    'log_type' => 8,
                    'log_info' => serialize($order_info),
                ];
                $log_status = $this->addInventoryLog($log_data);

                if($log_status && $order_status) {
                    M()->commit();
                    $result = [
                        'status'=> 1,
                        'message' => '操作成功'
                    ];

                }else{
                    M()->rollback();
                    $result = [
                        'status'=> 0,
                        'message' => '操作失败'
                    ];
                }
            }else{
                $result = [
                    'status'=> 2,
                    'message' => '盘点单不存'
                ];
            }
            cancelCacheLock('ErpInventory/confirmOrderDetailData');
        }else{
            $result = [
                'status'=> 3,
                'message' => '盘点单ID参数有误'
            ];
        }
        return $result;
    }

    /**
     * 生成库存盘赢盘亏单
     * @param $id
     * @author xiaowen
     * @time 2017-1-9
     * @return array $result
     */
    public function createOrderStockData($id){
        $id = intval($id);
        if($id){
            $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>$id]);
            if($order_info['is_confirm_data'] != 1){
                $result = [
                    'status' => 2,
                    'message' => '请先确认盘点单数据，再操作',
                ];
                return $result;
            }else if($order_info['is_create_order'] == 1 || $order_info['check_status'] == 1){
                $result = [
                    'status' => 3,
                    'message' => '盘点单财务已审核，无法再次操作',
                ];
                return $result;
            }
            $field = 'd.*, g.goods_code,g.goods_name,g.source_from,g.grade,g.level,s.object_id,s.stock_type';
            $order_detail_data = $this->getModel('ErpInventoryOrderDetail')->getAllErpInventoryOrderDetailList(['inventory_order_id'=>$id, 'stock_diff_num'=>['neq', 0]], $field);
            //log_info(print_r($order_detail_data,true));
            //根据盘点详情数据生成 盘赢盘亏出入库单
            if($order_info && $order_detail_data['data']){
                if (getCacheLock('ErpInventory/createOrderStockData')) return ['status' => 99, 'message' => $this->running_msg];
                M()->startTrans();
                setCacheLock('ErpInventory/createOrderStockData', 1);
                //期初调整
                if($order_info['inventory_order_type'] == 3){

                    $stock_data_change_status = $this->initStockDataHandle($order_detail_data['data'], $order_info);

                }else{
                    //实物盘点，库存调整
                    $stock_change_result = $this->stockDataHandle($order_detail_data['data'], $order_info);
                    $stock_data_change_status = $stock_change_result['status'];
                    $stock_data_change_message = $stock_change_result['message'];
                }
                $order_data = [
                    'update_time' => currentTime(),
                    'is_create_order' => 1,
                    'check_status' => 1,
                ];
                $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id'=>$id], $order_data);

                $log_info_data = array(
                    'event'=> '生成盘点库存调整单',
                    'key'=> $order_info['inventory_order_number'],
                    'request'=> '盘点单状态',
                    'response'=> '盘点单状态 ' . $order_status,
                );
                log_write($log_info_data);
                $log_data = [
                    'source_type' => 2,
                    'source_id' => $order_info['id'],
                    'log_type' => 10,
                    'log_info' => serialize($order_info),
                ];
                $log_status = $this->addInventoryLog($log_data);

                $log_info_data = array(
                    'event'=> '生成盘点库存调整单',
                    'key'=> $order_info['inventory_order_number'],
                    'request'=> '盘点单日志状态',
                    'response'=> '盘点单日志状态 ' . $log_status,
                );

                log_write($log_info_data);
                //if($stock_change_status && $stock_in_status && $stock_out_status && $order_status && $log_status){
                if($stock_data_change_status == 1 && $order_status && $log_status){
                    //---------------处理入库单加权成本计算 edit xiaowen 2018-2-8---------------------------------------
//                    log_info("盘赢入库单：" . print_r($this->inventoryStockInData, true));
//                    log_info("盘赢入库前数量：" . print_r($this->inventoryStockInBeforeNum, true));
//                    $this->updateInventoryStockInCost($this->inventoryStockInData, $this->inventoryStockInBeforeNum, $this->inventoryStockIds);
                    //--------------------------------------------------------------------------------------------------
                    M()->commit();
                    $result = [
                        'status'=> 1,
                        'message' => '操作成功'
                    ];
                }else{
                    M()->rollback();
                    $result = [
                        'status'=> 2,
                        'message' => trim($stock_data_change_message) ? trim($stock_data_change_message) : '操作失败',
                    ];
                }
                cancelCacheLock('ErpInventory/createOrderStockData');
            }else{
                $result = [
                    'status'=> 3,
                    'message' => '盘点明细数据没有存在库存差异，无法生成库存调整单'
                ];
            }

        }else{
            $result = [
                'status'=> 4,
                'message' => '盘点单ID有误'
            ];
        }
        return $result;
    }

    /**
     * 生成期初盘点单库存调整单
     * @param array $order_detail_data 盘点单明细数据
     * @param array $order_info 盘点单明细数据
     * @return bool
     */
    protected function initStockDataHandle($order_detail_data = [], $order_info){
        //库存变动状态
        $stock_change_status = true;
        $stock_in_status_all = true;
        foreach($order_detail_data as $key=>$data){
            $tmp = [
                'init_stock_code' => erpCodeNumber(16)['order_number'],
                'init_stock_type' => $data['stock_diff_num'] > 0 ? 1 : 2,
                'storage_status' => 10,
                'source_number' => $data['inventory_order_number'],
                'source_object_id' => $data['id'],
                'our_company_id' => $data['our_company_id'],
                'goods_id' => $data['goods_id'],
                'storage_num' => $data['stock_diff_num'],
                'actual_storage_num' => $data['stock_diff_num'],
                'create_time' => currentTime(),
                'dealer_id' => $this->getUserInfo('id'),
                'dealer_name' => $this->getUserInfo('dealer_name'),
                'creater_id' => $this->getUserInfo('id'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime(),
                'storehouse_id' => $data['object_id'],
                'stock_type' => $data['stock_type'],
                'region' => $data['region'],
            ];
            $stock_in_status = $this->getModel('ErpStockInit')->add($tmp);
            $stock_in_status_all = $stock_in_status_all && $stock_in_status;

            $change_stock_number = $tmp['init_stock_code'];
            $stock_where = [
                'goods_id' => $data['goods_id'],
                'object_id' => $data['object_id'],
                'stock_type' => $data['stock_type'],
                'region' => $data['region'],
                'our_company_id' => $order_info['our_company_id'],
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

            $stock_data = [
                'goods_id' => $data['goods_id'],
                'object_id' => $data['object_id'], //待处理 oil_erp_inventory_order_detail 表中需要存object_id
                'stock_type' => $data['stock_type'],
                'region' => $data['region'],
                'stock_num' => $stock_info['stock_num'] + $data['stock_diff_num'],
                'init_stock_num' => $stock_info['init_stock_num'] + $data['stock_diff_num'],
            ];
            $stock_info['stock_num'] = $stock_data['stock_num'];
            //------------------计算出新的可用库存----------------------------
            $stock_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            $orders = [
                'object_number' => $change_stock_number,
                'object_type' => 4,
                'log_type' => 10,
                'our_company_id' => $order_info['our_company_id'],
            ];
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($stock_data, $data['stock_diff_num'], $orders);
            $stock_change_status = $stock_change_status && $stock_status;
        }
        $stock_out_status = true; //期初没有出库单，直接默认出库成功
        return $stock_in_status_all && $stock_out_status && $stock_change_status;
    }

    /**
     * 盘点数据物理库存处理
     * @param array $order_detail_data
     * @param $order_info
     * @return bool
     */
    protected function stockDataHandle($order_detail_data = [], $order_info){
        //实物盘点、库存调整
        $stock_order_type = [
            1 => 4,
            2 => 5,
        ];
//        //盘点类型对应的入库单状态 edit xiaowen 2018-2-8
//        $storage_status = [
//            4 => 10,
//            5 => 1,
//        ];
        //库存变动状态
        $stock_change_status = true;
        //入库单创建状态
        $stock_in_status_all = true;
        //出库单创建状态
        $stock_out_status_all = true;
        //获取所有详情的库存仓库ID
        $object_ids = array_unique(array_column($order_detail_data, 'storehouse_id'));
        //如果类型为城市仓、代采仓、零售仓、实体仓，则查询ErpStorehouse表
        $stock_object_data = $this->getModel('ErpStorehouse')->where(['id'=>['in',$object_ids]])->getField('id,storehouse_name');
        //获取系统当前配置的密度 edit xiaowen 2018-8-10
        $default_density = getConfig('Config_Density');

        $batch_ids = array_unique(array_column($order_detail_data, 'batch_id'));

        if($batch_ids){
            $batch_info_arr = $this->getModel('ErpBatch')->where(['id'=>['in', $batch_ids]])->getField('id,sys_bn,cargo_bn_id');

            $cargo_bn_ids = array_unique(array_column($batch_info_arr, 'cargo_bn_id'));

            $batch_info_arr = $this->getModel('ErpBatch')->where(['id'=>['in', $batch_ids]])->getField('id,sys_bn,cargo_bn_id');

        }
        foreach($order_detail_data as $key=>$data){
            $change_stock = true;
            if($data['stock_diff_num'] < 0){
                $stock_out_data = [
                    'outbound_code' => erpCodeNumber(7)['order_number'],
                    'outbound_type' => $stock_order_type[$order_info['inventory_order_type']],
                    'outbound_status' => 10,
                    'source_number' => $data['inventory_order_number'],
                    'source_object_id' => $data['id'],
                    'our_company_id' => $data['our_company_id'],
                    'goods_id' => $data['goods_id'],
                    'depot_id' => 99999,
                    'outbound_num' => abs($data['stock_diff_num']),
                    'actual_outbound_num' => abs($data['stock_diff_num']),
                    'create_time' => currentTime(),
                    'dealer_id' => $this->getUserInfo('id'),
                    'dealer_name' => $this->getUserInfo('dealer_name'),
                    'creater_id' => $this->getUserInfo('id'),
                    'creater_name' => $this->getUserInfo('dealer_name'),
                    'auditor_id' => $this->getUserInfo('id'),
                    'audit_time' => currentTime(),
                    'storehouse_id' => $data['storehouse_id'],
                    'stock_type' => $data['stock_type'],
                    'region' => $data['region'],
                    'batch_id' => $data['batch_id'],
                    'batch_sys_bn' => $batch_info_arr[$data['batch_id']]['sys_bn'],
                ];
                //获取出库单成本 edit xiaowen 2018-2-8---------------
                $cost_info = getStockOutCost($stock_out_data);
                $stock_out_data['cost'] = $cost_info['price'] ? $cost_info['price'] : 0;
                $stock_out_data['cost_log_id'] = $cost_info['logId'] ? $cost_info['logId'] : 0;
                // 如果成本值为空，则return edit qianbin 2018-03-09
//                if($stock_out_data['cost']===null){
//                    return  [
//                        'status' => 99,
//                        'message' => '成本获取失败，请联系管理员！',
//                    ];
//                }
                //---------------------------------------------------
                $change_stock_number = $stock_out_data['outbound_code'];
                $change_stock_type = 3;
                $stock_out_status = $this->getModel('ErpStockOut')->add($stock_out_data);
                $influence_status = $this->getEvent('ErpNewAllocation')->influenceStockIn($stock_out_data);
                $stock_out_status_all = $stock_out_status_all && $stock_out_status && $influence_status;
            } else {
                $stock_in_data = [
                    'storage_code' => erpCodeNumber(8)['order_number'],
                    'storage_type' => $stock_order_type[$order_info['inventory_order_type']],
                    // edit xiaowen 2018-2-8 根据盘点类型，确认生成的入库单状态：4 实物盘赢 == storage_status = 10 已确认， 5 库存调整 == storage_status = 1 未审核
                   // 'storage_status' => $storage_status[$stock_order_type[$order_info['inventory_order_type']]],//10,
                    'storage_status' => 1,
                    'source_number' => $data['inventory_order_number'],
                    'source_object_id' => $data['id'],
                    'our_company_id' => $data['our_company_id'],
                    'goods_id' => $data['goods_id'],
                    'storage_num' => $data['stock_diff_num'],
                    'actual_storage_num' => $data['stock_diff_num'],
                    'create_time' => currentTime(),
                    'dealer_id' => $this->getUserInfo('id'),
                    'dealer_name' => $this->getUserInfo('dealer_name'),
                    'creater_id' => $this->getUserInfo('id'),
                    'auditor_id' => $this->getUserInfo('id'),
                    'audit_time' => currentTime(),
                    'storehouse_id' => $data['storehouse_id'],
                    'stock_type' => $data['stock_type'],
                    'region' => $data['region'],
                    //入库单增加批次信息 edit xiaowen 2019-3-6
                    'batch_id' => $data['batch_id'],
                    'batch_sys_bn' =>  $batch_info_arr[$data['batch_id']]['sys_bn'],
                    'cargo_bn_id' => $batch_info_arr[$data['batch_id']]['cargo_bn_id'],
                ];
                /**=========================
                 *
                 * 批次最新修改，盘盈入库与库存调整一样，必须财务先输入价格再审核入库， 不再以0成本入库
                 * edit xiaowen 2019-3-7
                 */
                //edit xiaowen 2018-28 判断如生成入库单类型为4 实物盘赢，入库单自动确认并以0价格入库，计算加权成本------
//                if($stock_in_data['storage_type'] == 4){
//                    $stock_in_data['outbound_density'] = $default_density;
//                    array_push($this->inventoryStockInData, $stock_in_data);
//                    //$this->inventoryStockInData[] = $stock_in_data;
//                }
                //------------------------------------------------------------------------------------------------------
                $change_stock_number = $stock_in_data['storage_code'];
                $change_stock_type = 4;
                //库存调整生成的入库单为未审核状态，不要变动物理库存 xiaowen edit xiaowen2018-2-9
                //$change_stock = $stock_in_data['storage_type'] == 5 ? false : true;
                $change_stock = false;
                $stock_in_status = $this->getModel('ErpStockIn')->add($stock_in_data);
                $stock_in_status_all = $stock_in_status_all && $stock_in_status;
            }
            //----------------------------处理影响的库存差额-------------------------
            //log_info('明细数据ID：' . $data['id'] . ' 明细数据详情 :' . print_r($data,true));
            $stock_where = [
                'goods_id' => $data['goods_id'],
                'object_id' => $data['storehouse_id'],
                'stock_type' => $data['stock_type'],
                'region' => $data['region'],
                'our_company_id' => $order_info['our_company_id'],
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //如果是盘点差异大于0且盘点类型为实物盘赢，需要计算加权成本，将库存变动前的物理库存放入数组中，后续跟入库单数据一起加权计算
            //edit xiaowen 2018-2-8-------------------------------------------------------------------------------------
//            if($data['stock_diff_num'] > 0 && $stock_in_data[0]['storage_type'] == 4){
//                array_push($this->inventoryStockInBeforeNum, $stock_info['stock_num']);
//                array_push($this->inventoryStockIds, $stock_info['id']);
//            }
            //----------------------------------------------------------------------------------------------------------
            if($change_stock){
                //如果是盘亏生成出库单，需要判断当前物理库存是否满足出库
                if($data['stock_diff_num'] < 0 && ($stock_info['stock_num'] - abs($data['stock_diff_num']) < 0)){
                    return [
                        'status' => 2,
                        'message' => $stock_object_data[$data['storehouse_id']] . ' ' . $data['goods_code'] . '/'. $data['goods_name'] . '/'. $data['source_from'] . '/'. $data['grade']. '/'. $data['level'] . ', 物理库存不足，无法生成盘亏单',
                    ];
                }
                //判断盘点明细是否存在批次id edit xiaowen 2019-3-6
                if(empty($data['batch_id'])){
                    return [
                        'status' => 3,
                        'message' =>'盘点记录ID：'. $data['id'] . ", 没有批次信息，无法操作",
                    ];
                }
                $stock_data = [
                    'goods_id' => $data['goods_id'],
                    'object_id' => $data['storehouse_id'], //待处理 oil_erp_inventory_order_detail 表中需要存object_id
                    'stock_type' => $data['stock_type'],
                    'region' => $data['region'],
                    'stock_num' => $stock_info['stock_num'] + $data['stock_diff_num'],
                ];
                $stock_info['stock_num'] = $stock_data['stock_num'];
                //------------------计算出新的可用库存----------------------------
                $stock_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                $orders = [
                    'object_number' => $change_stock_number,
                    'object_type' => $change_stock_type,
                    'log_type' => 11,
                    'our_company_id' => $order_info['our_company_id'],
                ];
                $stock_status = $this->getEvent('ErpStock')->saveStockInfo($stock_data, abs($data['stock_diff_num']), $orders);

                //==============edit xiaowen 更新批次数量 2019-3-6=========================

                $batch_change_data = [
                    'batch_id' => $data['batch_id'],
                    'change_balance_num' => $data['stock_diff_num'], //更新批次可用数量
                    'change_type' => $data['stock_diff_num'] < 0 ? 2 : 1,
                    'change_number' => $change_stock_number,
                ];
                $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
                if($batch_result['status'] != 1){
                    return $batch_result;
                } else {
                    $batch_status = true;
                }

                //==================end 批次数据处理========================================
            }else{
                $batch_status = $stock_status = true;
            }

            $stock_change_status = $stock_change_status && $stock_status && $batch_status;
            $log_info_data = array(
                'event'=> '生成盘点库存调整单',
                'key'=> $order_info['inventory_order_number'],
                'request'=> '明细ID ' . $data['id'] . '库存ID ' . $data['stock_id'],
                'response'=> '本次状态 ' . $stock_status . '总体状态 ' . $stock_change_status,
            );
            log_write($log_info_data);
            //log_info( '明细ID ' . $data['id'] . '库存ID ' . $data['stock_id'].'本次状态 ' . ($stock_status ? '成功' : '失败') . '总体状态 ' . ($stock_change_status ? '成功' : '失败'));
            //-----------------------------库存处理结束--------------------------------------------------------------
        }
//        $log_info_data = array(
//            'event'=> '生成盘点库存调整单',
//            'key'=> $order_info['inventory_order_number'],
//            'request'=> '库存状态',
//            'response'=> '库存状态处理状态 ' . $stock_change_status,
//        );
//        log_write($log_info_data);
//
//        $log_info_data = array(
//            'event'=> '生成盘点库存调整单',
//            'key'=> $order_info['inventory_order_number'],
//            'request'=> '出库状态',
//            'response'=> '出库存状态 ' . $stock_out_status,
//        );
//
//        log_write($log_info_data);

        $log_info_data = array(
            'event'=> '生成盘点库存调整单',
            'key'=> $order_info['inventory_order_number'],
            'request'=> '入库状态',
            'response'=> '库存状态处理状态 ' . $stock_change_status . '; 出库存状态 ' . $stock_out_status  .'; 入库存状态 ' . $stock_in_status,
        );

        log_write($log_info_data);
        if($stock_in_status_all && $stock_out_status_all && $stock_change_status){
            $result = [
                'status'=>1,
                'message'=>'',
            ];
        }else{
            $result = [
                'status'=>0,
                'message'=>'',
            ];
        }
        return $result;
    }

    /*
     ************************************************************
     *
     *   宇
     *
     *************************************************************
     */

    /**
     * 导入盘点详细数据
     * @author guanyu
     * @time 2018-01-08
     */
    public function uploadInventoryOrderData($file_path,$id)
    {
        @set_time_limit(5 * 60);
        if (empty(trim($file_path))) {
            $result = [
                'status' => 4,
                'message' => '系统异常请联系管理员！'
            ];
            return $result;
        }
        # @excel数据
        $PHPReader = new \PHPExcel_Reader_Excel2007();
        if (!$PHPReader->canRead($file_path)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($file_path)) {
                $result = [
                    'status' => 5,
                    'message' => '文件不存在！'
                ];
                return $result;
            }
        }
        $PHPExcel = $PHPReader->load($file_path);
        # @读取excel文件中的第一个工作表
        $currentSheet = $PHPExcel->getSheet(0);
        $data = $currentSheet->toArray();
        //print_r($data);
        unset($data[0]);

        //验证数据
        if (empty($data)) {
            $result = [
                'status' => 6,
                'message' => '数据不能为空！'
            ];
            return $result;
        }

        $field = 'd.*, g.goods_code,g.goods_name,g.source_from,g.grade,g.level,s.object_id,s.stock_type';
        $where['d.inventory_order_id'] = intval($id);
        $inventory_detail_data = $this->getModel('ErpInventoryOrderDetail')->getAllErpInventoryOrderDetailList($where,$field);

        $object_ids = array_unique(array_column($inventory_detail_data['data'], 'object_id'));
        $stock_object_data = $this->getModel('ErpStorehouse')->where(['id'=>['in',$object_ids]])->getField('id,storehouse_name');

        $inventory_detail_data_new = [];
        foreach ($inventory_detail_data['data'] as $value) {
            $inventory_detail_data_new[$value['id']] = $value;
            $inventory_detail_data_new[$value['id']]['storehouse_name'] = $stock_object_data[$value['object_id']];
        }

        //验证数据
        $check_data = $this->checkUploadData($inventory_detail_data_new,$data);

        if ($check_data['status'] != 1) {
            $result = [
                'status' => 7,
                'message' => $check_data['message']
            ];
            return $result;
        }

        if (getCacheLock('ErpInventory/uploadInventoryOrderData')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/uploadInventoryOrderData', 1);

        M()->startTrans();
        $status_inventory_all = true;
        $status_log_all = true;

        foreach ($data as $value) {

            if (!$value[0]) {
                continue;
            }

            $update_data = [
                'inventory_stock_num' => setNum($value[9]),
                'stock_diff_num' => setNum($value[9] - $value[8]),
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('dealer_name'),
                'updater_id' => $this->getUserInfo('id'),
            ];

            $status_invoice = $this->getModel('ErpInventoryOrderDetail')->saveErpInventoryOrderDetail(['id'=>$value[0]],$update_data);

            //生成操作日志
            $inventory_detail_data_new[$value[0]]['inventory_stock_num'] = setNum($value[9]);
            $inventory_detail_data_new[$value[0]]['stock_diff_num'] = setNum($value[9] - $value[8]);
            $log_data = [
                'source_type' => 3,
                'source_id' => $value[0],
                'log_type' => 7,
                'log_info' => serialize($inventory_detail_data_new[$value[0]]),
            ];
            $status_log = $this->addInventoryLog($log_data);

            $status_invoice_all = $status_invoice && $status_inventory_all ? true : false;
            $status_log_all = $status_log && $status_log_all ? true : false;
        }
        if ($status_invoice_all && $status_log_all) {
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
        cancelCacheLock('ErpInventory/uploadInventoryOrderData');
        return $result;
    }
    /**
     * 导入盘点详细数据
     * @author xiaowen
     * @time 2018-01-08
     */
    public function uploadInventoryOrderDataNew($file_path,$id)
    {
        @set_time_limit(5 * 60);
        if (empty(trim($file_path))) {
            $result = [
                'status' => 4,
                'message' => '系统异常请联系管理员！'
            ];
            return $result;
        }
        # @excel数据
        $PHPReader = new \PHPExcel_Reader_Excel2007();
        if (!$PHPReader->canRead($file_path)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($file_path)) {
                $result = [
                    'status' => 5,
                    'message' => '文件不存在！'
                ];
                return $result;
            }
        }
        $PHPExcel = $PHPReader->load($file_path);
        # @读取excel文件中的第一个工作表
        $currentSheet = $PHPExcel->getSheet(0);
        $data = $currentSheet->toArray();
        //print_r($data);
        unset($data[0]);

        //验证数据
        if (empty($data)) {
            $result = [
                'status' => 6,
                'message' => '数据不能为空！'
            ];
            return $result;
        }else{
            //遍历xls数据，并根据盘点明细id，分别存放系统已有批次和盘盈新增批次
            foreach ($data as $key=>$value){
                if(intval($value[0]) > 0){
                    $data_in_sys = $value;
                }else{
                    $data_not_in_sys = $value;
                }
            }
        }

        $field = 'd.*, g.goods_code,g.goods_name,g.source_from,g.grade,g.level,s.object_id,s.stock_type';
        $where['d.inventory_order_id'] = intval($id);
        $inventory_detail_data = $this->getModel('ErpInventoryOrderDetail')->getAllErpInventoryOrderDetailList($where,$field);

        $object_ids = array_unique(array_column($inventory_detail_data['data'], 'object_id'));
        $stock_object_data = $this->getModel('ErpStorehouse')->where(['id'=>['in',$object_ids]])->getField('id,storehouse_name');

        $inventory_detail_data_new = [];
        foreach ($inventory_detail_data['data'] as $value) {
            $inventory_detail_data_new[$value['id']] = $value;
            $inventory_detail_data_new[$value['id']]['storehouse_name'] = $stock_object_data[$value['object_id']];
        }

        //验证数据
        //$check_data = $this->checkUploadData($inventory_detail_data_new,$data);
        //验证系统已有盘点数据
        $check_data = $this->checkUploadData($inventory_detail_data_new,$data_in_sys);

        if ($check_data['status'] != 1) {
            $result = [
                'status' => 7,
                'message' => $check_data['message']
            ];
            return $result;
        }

        if (getCacheLock('ErpInventory/uploadInventoryOrderData')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpInventory/uploadInventoryOrderData', 1);

        M()->startTrans();
        $status_inventory_all = true;
        $status_log_all = true;

        //foreach ($data as $value) {
        foreach ($data_in_sys as $value) {

            if (!$value[0]) {
                continue;
            }

            $update_data = [
                'inventory_stock_num' => setNum($value[9]),
                'stock_diff_num' => setNum($value[9] - $value[8]),
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('dealer_name'),
                'updater_id' => $this->getUserInfo('id'),
            ];

            $status_invoice = $this->getModel('ErpInventoryOrderDetail')->saveErpInventoryOrderDetail(['id'=>$value[0]],$update_data);

            //生成操作日志
            $inventory_detail_data_new[$value[0]]['inventory_stock_num'] = setNum($value[9]);
            $inventory_detail_data_new[$value[0]]['stock_diff_num'] = setNum($value[9] - $value[8]);
            $log_data = [
                'source_type' => 3,
                'source_id' => $value[0],
                'log_type' => 7,
                'log_info' => serialize($inventory_detail_data_new[$value[0]]),
            ];
            $status_log = $this->addInventoryLog($log_data);

            $status_invoice_all = $status_invoice && $status_inventory_all ? true : false;
            $status_log_all = $status_log && $status_log_all ? true : false;
        }
        if ($status_invoice_all && $status_log_all) {
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
        cancelCacheLock('ErpInventory/uploadInventoryOrderData');
        return $result;
    }

    /**
     * 上传新增的盘盈数据(该批次系统没有，通过xls导入新增的盘点批次)
     * @author xiaowen
     * @time 2019-3-1
     * @param $upload_data
     * @param $inventoryOrderInfo
     */
//    public function uploadCreateNewData($upload_data, $inventoryOrderInfo){
//        if ($upload_data){
//            foreach ($upload_data as $key=>$value){
//
//            }
//        }
//    }
    /**
     * 导入盘点详细数据验证
     * @author guanyu
     * @time 2018-01-08
     */
    public function checkUploadData($inventory_detail_data,$data)
    {

        $check_data = [
            'status' => 1,
            'message' => '',
        ];

        $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval(current($inventory_detail_data)['inventory_order_id'])]);

        if ($order_info['is_confirm_data'] == 1) {
            $check_data['status'] = 2;
            $check_data['message'] = '盘点单已确认数据，无法再次导入！';
            return $check_data;
        }

        foreach ($data as $value) {
            if (!array_key_exists(intval($value[0]),$inventory_detail_data) && $value[0]) {
                $check_data['status'] = 3;
                $check_data['message'] = '盘点数据ID验证失败！';
                return $check_data;
            }
            if ($inventory_detail_data[$value[0]]['storehouse_name'] != $value[1] && $value[1]) {
                $check_data['status'] = 4;
                $check_data['message'] = '仓库名称验证失败！';
                return $check_data;
            }
            if ($inventory_detail_data[$value[0]]['inventory_order_number'] != $value[2] && $value[2]) {
                $check_data['status'] = 5;
                $check_data['message'] = '盘点单号验证失败！';
                return $check_data;
            }
            if ($inventory_detail_data[$value[0]]['goods_code'] != $value[3] && $value[3]) {
                $check_data['status'] = 6;
                $check_data['message'] = '商品代码验证失败！';
                return $check_data;
            }
//            if (intval(round(setNum($inventory_detail_data[$value[0]]['transportation_num']))) != intval(round(setNum(setNum($value[8])))) && $value[8]) {
//                $check_data['status'] = 7;
//                $check_data['message'] = '账面在途库存验证失败！';
//                return $check_data;
//            }
            if (intval(round(setNum($inventory_detail_data[$value[0]]['stock_num']))) != intval(round(setNum(setNum($value[8])))) && $value[8]) {
                $check_data['status'] = 8;
                $check_data['message'] = '账面物理库存验证失败！';
                return $check_data;
            }
            unset($inventory_detail_data[$value[0]]);
        }

        if (!empty($inventory_detail_data)) {
            $check_data['status'] = 9;
            $check_data['message'] = '上传数据与盘点数据不匹配，请检查！';
            return $check_data;
        }

        return $check_data;
    }

    /**
     * 处理盘点入库单加权成本
     * @author xiaowen
     * @time 2018-2-8
     * @param array $stock_in_data
     * @param array $beforeNum
     */
    public function updateInventoryStockInCost($stock_in_data, $beforeNum, $stockIds){
        if($stock_in_data && $beforeNum){
            //循环匹配入库单和变动前库存数量
            foreach($stock_in_data as $key=>&$value){
                $value['before_stock_num'] = $beforeNum[$key];
                $value['stock_id'] = $stockIds[$key] ? $stockIds[$key] : 0;
                $value['change_num'] = $value['actual_storage_num'];
                //调用成本计算接口参与加权
                updateStockInCost($value);
            }
        }
    }

    /**
     * 根据盘点计划类型查询已存在的库存记录
     * @param int $type  盘点计划类型
     * @param array $where_store  其他查询条件
     * @author xiaowen
     * @time 2018-12-14
     * @return mixed
     */
    public function getStockByInventoryType($type, $where_store = []){
        $stock_status = 1;
        $where_map_data = [
            1 => [
                'type' => stockTypeToStorehouseType($type),
                'status' => $stock_status,
                'stock_type' => $type,
            ],
            4 => [
                'type' => stockTypeToStorehouseType($type),
                'status' => $stock_status,
                'stock_type' => 4,
            ],

            5 => [
                'type' => stockTypeToStorehouseType($type),
                'status' => $stock_status,
                'stock_type' => $type,
            ],

            6 => [
                'type' => ['in','4,5,6'],
                'status' => $stock_status,
                'stock_type' => ['in','6,7,8'],
            ],
        ];

        $where_skid = array_merge($where_map_data[$type], $where_store);
        $stock = $this->getModel('ErpStorehouse')->where($where_skid)->getField('id,storehouse_name as name,status',true);
        return $stock;

        # 查询对应仓库
//        if (intval($param['type']) == 1) {
//
//            # 批发零售仓
//            # 获取对应仓库
//            $where_store['type']        = stockTypeToStorehouseType($param['type']);
//            $where_store['status']      = 1;
//            $where_stock['stock_type']  = $param['type'];
//            $stock = $this->getModel('ErpStorehouse')->where($where_store)->getField('id,storehouse_name as name,status',true);
//
//        } else if (intval($param['type']) == 4) {
//
//            # 加油网点
//            # 获取加油网点
//            $where_store['type']         = stockTypeToStorehouseType($param['type']);
//            $where_store['status']       = 1;
//            $where_store['stock_type']  = 4 ;
//            $stock = $this->getModel('ErpStorehouse')->where($where_store)->getField('id,storehouse_name as name,status',true);
//        } else if(intval($param['type']) == 5) {
//
//            # 零售仓库存盘点 xiaowen 2018-12-14
//            $where_store['type']         = 3;
//            $where_store['status']       = 1;
//            $where_stock['stock_type']   = 5;
//            $stock = $this->getModel('ErpStorehouse')->where($where_store)->getField('id,storehouse_name as name,status',true);
//
//        }else if(intval($param['type']) == 6) {
//
//            # 批发零售仓
//            # 获取对应仓库
//            $where_store['type']         = ['in','4,5,6'];
//            $where_store['status']       = 1;
//            $where_stock['stock_type']   = ['in','6,7,8'];
//            $stock = $this->getModel('ErpStorehouse')->where($where_store)->getField('id,storehouse_name as name,status',true);
//
//        }
    }

    /**
     * 生成盘点数据(基于批次生成)
     * @author xiaowen
     * @time 2019-2-28
     * @param $id
     * @return array $result
     */
    public function createInventoryOrderDataNew($id){

        if(!$id){
            $result = [
                'status' => 0,
                'message' => '请选择盘点单',
            ];
        }else{
            $order_info = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>intval($id)]);
            if($order_info['order_status'] != 10){
                $result = [
                    'status' => 2,
                    'message' => '只有已确认的盘点单才能生成盘点数据',
                ];
            }else if($order_info['is_create_data'] == 1){
                $result = [
                    'status' => 3,
                    'message' => '已生成盘点数据,无须再次生成',
                ];
            }else{
                if (getCacheLock('ErpInventory/createInventoryOrderData')) return ['status' => 99, 'message' => $this->running_msg];

                $plan_info = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id'=>$order_info['inventory_plan_id']]);
                $plan_info['inventory_storehouse_ids'];
                $log_info_data = array(
                    'event'=> '生成盘点明细数据',
                    'key'=> $order_info['inventory_order_number'],
                    'request'=> json_decode($plan_info['inventory_storehouse_ids'])
                );

                log_write($log_info_data);
                //log_info(print_r(json_decode($plan_info['inventory_storehouse_ids']), true));
                $plan_info['inventory_storehouse_ids'] = array_keys(json_decode($plan_info['inventory_storehouse_ids'], true));
                log_info(print_r($plan_info, true));
                if($plan_info['inventory_storehouse_ids'] && in_array($plan_info['inventory_type'], array_keys(getInventoryPlanType()))){

                    $stock_where = [
                        //'object_id'=>['in', $plan_info['inventory_storehouse_ids']],
                        'storehouse_id'=>['in', $plan_info['inventory_storehouse_ids']],
                        'stock_type'=>$plan_info['inventory_type'] == 6 ? ['in','6,7,8'] : $plan_info['inventory_type'],
                        'our_company_id'=>$order_info['our_company_id'],
                    ];
                    //$stock_data = $this->getModel('ErpStock')->where($stock_where)->select();
                    //edit 切换成从批次表生成盘点数据 2019-2-28 xiaowen
                    $stock_data = $this->getModel('ErpBatch')->where($stock_where)->select();
                    if($stock_data){
                        M()->startTrans();
                        setCacheLock('ErpInventory/createInventoryOrderData', 1);
                        //生成盘点数据
                        $inventory_order_detail = [];
                        foreach($stock_data as $key=>$stock){
                            $inventory_order_detail[$key]['inventory_order_id'] = $order_info['id'];
                            $inventory_order_detail[$key]['inventory_order_number'] = $order_info['inventory_order_number'];
                            $inventory_order_detail[$key]['stock_id'] = $stock['stock_id'];
                            $inventory_order_detail[$key]['batch_id'] = $stock['id'];
                            //盘点明细表新增两个字段 xiaowen 2019-3-4 =========================
                            $inventory_order_detail[$key]['storehouse_id'] = $stock['storehouse_id'];
                            $inventory_order_detail[$key]['stock_type'] = $stock['stock_type'];
                            //==================================================================
                            $inventory_order_detail[$key]['our_company_id'] = $order_info['our_company_id'];
                            $inventory_order_detail[$key]['goods_id'] = $stock['goods_id'];
                            $inventory_order_detail[$key]['region'] = $stock['region'];
                            //$inventory_order_detail[$key]['stock_num'] = $stock['stock_num'];
                            $inventory_order_detail[$key]['stock_num'] = $stock['balance_num'];
                            //$inventory_order_detail[$key]['transportation_num'] = $stock['transportation_num'];
                            $inventory_order_detail[$key]['create_time'] = currentTime();
                            $inventory_order_detail[$key]['creater'] = $this->getUserInfo('dealer_name');
                            $inventory_order_detail[$key]['creater_id'] = $this->getUserInfo('id');

                        }
                        $detail_status = $this->getModel('ErpInventoryOrderDetail')->addAll($inventory_order_detail);

                        //更新盘点单，状态更新为已生成盘点数据
                        $order_info['is_create_data'] = 1;
                        $order_info['update_time'] = currentTime();
                        $order_status = $this->getModel('ErpInventoryOrder')->saveErpInventoryOrder(['id'=>$order_info['id']], $order_info);
                        //生成操作日志
                        $log_data = [
                            'source_type' => 2,
                            'source_id' => $order_info['id'],
                            'log_type' => 5,
                            'log_info' => serialize($order_info),
                        ];
                        $log_status = $this->addInventoryLog($log_data);

                        if($detail_status && $order_status && $log_status){
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
                    }else{
                        $result = [
                            'status' => 3,
                            'message' => '该盘点计划无有效的库存数据，无法生成盘点数据',
                        ];
                    }

                }else{
                    $result = [
                        'status' => 4,
                        'message' => '盘点类型或盘点仓库数据有误，请确认盘点计划是否设置正确',
                    ];
                }
                cancelCacheLock('ErpInventory/createInventoryOrderData');
            }
        }

        return $result;
    }
}
