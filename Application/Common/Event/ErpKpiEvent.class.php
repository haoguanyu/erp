<?php
/**
 * KPI管理处理层
 * @author xiaowen
 * @time 2017-11-28
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpKpiEvent extends BaseController
{
    public $date;
    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
    }


    /**
     * 新增岗位基础资料
     * @param array $param $type = 1  添加 | 2修改
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function addKpiBase($param = [] , $type = 1)
    {
        if(count($param) <= 0 ) {
            return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['dealer_name']))) {
            return ['status' => 3 , 'message' => '姓名参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['region']))) {
            return ['status' => 4 , 'message' => '城市参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['is_regular'])) || !in_array(intval($param['is_regular']),[1,2])){
            return ['status' => 5 , 'message' => '转正参数错误，请刷新后重试！'];
        }
        if(!isset($param['post_base_num']) ||  trim($param['post_base_num']) < 0) {
            return ['status' => 6 , 'message' => '岗位基础量参数错误，请刷新后重试！'];
        }
        if(!isset($param['new_mall_num']) || trim($param['new_mall_num']) < 0) {
            return ['status' => 7 , 'message' => '新客户商城成交基础量参数错误，请刷新后重试！'];
        }
        if(!isset($param['old_mall_num']) || trim($param['old_mall_num']) < 0) {
            return ['status' => 8 , 'message' => '老客户商城成交基础量参数错误，请刷新后重试！'];
        }
        # 分割姓名 和 地区 格式 ：id + 名称
        $dealer_data  = explode('+',trim($param['dealer_name']));
        $region_data  = explode('+',trim($param['region']));
        # 验证名称 + 地区是否存在
        $check_data = $this->getModel('ErpKpiBase')->findKpiBase(['region' => $region_data[0],'dealer_id' => $dealer_data[0], 'status' => 1],'id,status');
        if(count($check_data) > 0 && $type == 1){
            return ['status' => 9 , 'message' => '该地区已存在此员工信息，请核查后添加！'];
        }else if($check_data['status'] == 2){
            return ['status' => 11 , 'message' => '该笔数据已被取消，请重新添加！'];
        }

        if(getCacheLock('ErpKpi/addKpiBase'))  return ['status' => 99, 'message' => $this->running_msg];
        $data     = [
            'dealer_id'      => $dealer_data[0],
            'dealer_name'    => $dealer_data[1],
            'region'         => $region_data[0],
            'region_name'    => $region_data[1],
            'is_regular'     => trim($param['is_regular']),
            'post_base_num'  => setNum(trim($param['post_base_num'])),
            'new_mall_num'   => setNum(trim($param['new_mall_num'])),
            'old_mall_num'   => setNum(trim($param['old_mall_num'])),
            'status'         => 1,
        ];
        if($type == 1) {
            $data['create_time']  = $this->date;
            $data['creater']      = $this->getUserInfo('dealer_name');
        }
        if($type == 2) $data['update_time']  = $this->date;
        if($type == 1 ) {
            $result      = $this->getModel('ErpKpiBase')->addKpiBase($data);
            $data['id']  = $result;
        }
        if($type == 2 ) {
            $result = $this->getModel('ErpKpiBase')->saveKpiBase(['id' => intval($param['id'])],$data);
            $data['id']  = intval($param['id']);
        }
        if(!$result){
            cancelCacheLock('ErpKpi/addKpiBase');
            return ['status' => 9 , 'message' => '操作失败，请刷新后重试！'];
        }
        $add_log    = [
            'object_id'     => $data['id'],
            'obect_type'    => 1 ,
            'log_type'      => $type ,
            'log_info'      => serialize($data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => $this->date
        ];
        $result_log = $this->getModel('ErpKpiLog')->add($add_log);
        cancelCacheLock('ErpKpi/addKpiBase');
        if($result && $result_log){
            return ['status' => 1 , 'message' => '操作成功！'];
        }else{
            return ['status' => 10 , 'message' => '操作失败！'];
        }
    }

    /**
     * 编辑岗位基础资料
     * @param array $param
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function updateKpiBase($param = [])
    {
        if(intval($param['id']) < 0) return ['status' => 11 , 'message' => '数据异常，请刷新后重试！'];
        $result = $this->addKpiBase($param , 2);
        return $result;
    }

    /**
     * 更改岗位基础资料状态
     * @param array $param
     * @author qianbin
     * @time 2017-11-29
     * @return array
     */
    public function cancelErpkpi($param = [])
    {
        if(!isset($param['id']) || intval($param['id']) < 0)
                                 return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        $data = $this->getModel('ErpKpiBase')->findKpiBase(['id' => intval($param['id'])]);
        if(empty($data))         return ['status' => 3 , 'message' => '未查询到该笔数据，请稍后重试！'];
        if($data['status'] != 1) return ['status' => 4 , 'message' => '该笔数据存在状态错误，操作失败！'];
        if(getCacheLock('ErpKpi/cancelErpkpi'))  return ['status' => 99, 'message' => $this->running_msg];
        $result     = $this->getModel('ErpKpiBase')->saveKpiBase(['id' => intval($param['id'])],['status' => 2]);
        $result_log = 1 ;
        if($result){
            $add_log    = [
                'object_id'     => $data['id'],
                'obect_type'    => 1 ,
                'log_type'      => 3 ,
                'log_info'      => serialize($data),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id'),
                'create_time'   => $this->date
            ];
            $result_log = $this->getModel('ErpKpiLog')->add($add_log);
        }
        cancelCacheLock('ErpKpi/cancelErpkpi');
        if($result && $result_log) {
            return ['status' => 1 , 'message' => '操作成功！'];
        }else{
            return ['status' => 5 , 'message' => '操作失败！'];
        }
    }


     /**
     * 岗位基础资料列表
     * @param array $param
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function kpiBaseList($param = [])
    {
        $where      = [];
        $is_regular = kpiRegularStatus();
        if (intval($param['region']) != 0) {
            $where['region'] = intval($param['region']);
        }
        if (trim($param['user_name'])){
            $where['dealer_name'] = ['like' , '%'.trim($param['user_name']).'%'];
        }
        $where['status'] = 1;
        if (intval($param['status'])){
            $where['status'] = intval($param['status']);
        }
        # 获取数据-----
        $field  = 'id,dealer_name,region_name,is_regular,post_base_num,new_mall_num,old_mall_num';
        $result = $this->getModel('ErpKpiBase')->getKpiBaseList( $where, $field, $param['start'], $param['length']);
        # 整理数据-----
        if(count($result['data']) > 0) {
            foreach ($result['data'] as $k => $v) {
                $result['data'][$k]['is_regular']    = $is_regular[$v['is_regular']];
                $result['data'][$k]['post_base_num'] = getNum($v['post_base_num']);
                $result['data'][$k]['new_mall_num']  = getNum($v['new_mall_num']);
                $result['data'][$k]['old_mall_num']  = getNum($v['old_mall_num']);
            }
        }
        $result['recordsFiltered'] = $result['recordsTotal'];
        $result['draw']            = $_REQUEST['draw'];
        return $result;
    }

    /**
     * 新增计提方案
     * @param array $param
     * @param $type = 1  添加 | 2修改
     * @author qianbin
     * @time 2017-11-29
     * @return array
     */
    public function addKpiPlan($param = [] , $type = 1)
    {
        if(count($param) <= 0 ) {
            return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['plan_name']))) {
            return ['status' => 3 , 'message' => '请正确填写计提方案名称！'];
        }
        if(empty(trim($param['new_terminal_commission'])) || trim($param['new_terminal_commission']) < 0) {
            return ['status' => 4 , 'message' => '请正确填写新终端计提！'];
        }
        if(empty(trim($param['new_trade_commission'])) || trim($param['new_trade_commission']) < 0) {
            return ['status' => 5 , 'message' => '请正确填写新贸易计提！'];
        }
        if(empty(trim($param['new_terminal_commission'])) || trim($param['new_terminal_commission']) < 0) {
            return ['status' => 6 , 'message' => '请正确填写新终端计提！'];
        }
        if(empty(trim($param['old_terminal_commission'])) || trim($param['old_terminal_commission']) < 0) {
            return ['status' => 7 , 'message' => '请正确填写老终端计提！'];
        }
        if(empty(trim($param['old_trade_commission'])) || trim($param['old_trade_commission']) < 0) {
            return ['status' => 8 , 'message' => '请正确填写老贸易计提！'];
        }
        if(empty(trim($param['activate_customer_commission'])) || trim($param['activate_customer_commission']) < 0) {
            return ['status' => 9 , 'message' => '请正确填写激活计提！'];
        }
        if(getCacheLock('ErpKpi/addKpiPlan'))  return ['status' => 99, 'message' => $this->running_msg];
        $data     = [
            'plan_name'                     => trim($param['plan_name']),
            'new_terminal_commission'       => setNum(trim($param['new_terminal_commission'])),
            'new_trade_commission'          => setNum(trim($param['new_trade_commission'])),
            'old_terminal_commission'       => setNum(trim($param['old_terminal_commission'])),
            'old_trade_commission'          => setNum(trim($param['old_trade_commission'])),
            'activate_customer_commission'  => setNum(trim($param['activate_customer_commission'])),
            'status'                        => 1,
        ];
        if($type == 1) {
            $data['create_time']  = $this->date;
            $data['creater']      = $this->getUserInfo('dealer_name');
        }
        if($type == 2) $data['update_time']  = $this->date;
        if($type == 1 ) {
            $result      = $this->getModel('ErpKpiPlan')->addKpiPlan($data);
            $data['id']  = $result;
        }
        if($type == 2 ) {
            $result = $this->getModel('ErpKpiPlan')->saveKpiPlan(['id' => intval($param['id'])],$data);
            $data['id']  = intval($param['id']);
        }
        if(!$result){
            cancelCacheLock('ErpKpi/addKpiPlan');
            return ['status' => 9 , 'message' => '操作失败，请刷新后重试！'];
        }
        $add_log    = [
            'object_id'     => $data['id'],
            'obect_type'    => 2 ,
            'log_type'      => $type ,
            'log_info'      => serialize($data),
            'operator'      => $this->getUserInfo('dealer_name'),
            'operator_id'   => $this->getUserInfo('id'),
            'create_time'   => $this->date
        ];
        $result_log = $this->getModel('ErpKpiLog')->add($add_log);
        cancelCacheLock('ErpKpi/addKpiPlan');
        if($result && $result_log){
            return ['status' => 1 , 'message' => '操作成功！'];
        }else{
            return ['status' => 10 , 'message' => '操作失败！'];
        }
    }

    /**
     * 编辑计提方案
     * @param array $param
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function updateKpiPlan($param = [])
    {
        if(intval($param['id']) < 0) return ['status' => 11 , 'message' => '数据异常，请刷新后重试！'];
        $result = $this->addKpiPlan($param , 2);
        return $result;
    }


     /**
     * 计提方案列表
     * @param array $param
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function kpiPlanList($param = [])
    {

        $where['status'] = 1;
        if (trim($param['plan_name'])) {
            $where['plan_name']   = [ 'like' , '%'.trim($param['plan_name']).'%'];
        }
        if (trim($param['user_name'])){
            $where['creater'] = ['like' , '%'.trim($param['user_name']).'%'];
        }
        # 获取数据-----
        $result = $this->getModel('ErpKpiPlan')->getKpiPlanList( $where, true, $param['start'], $param['length']);
        # 整理数据-----
        if(count($result['data']) > 0) {
            foreach ($result['data'] as $k => $v) {
                $result['data'][$k]['new_terminal_commission']      = getNum($v['new_terminal_commission']);
                $result['data'][$k]['new_trade_commission']         = getNum($v['new_trade_commission']);
                $result['data'][$k]['old_terminal_commission']      = getNum($v['old_terminal_commission']);
                $result['data'][$k]['old_trade_commission']         = getNum($v['old_trade_commission']);
                $result['data'][$k]['activate_customer_commission'] = getNum($v['activate_customer_commission']);
            }
        }
        $result['recordsFiltered'] = $result['recordsTotal'];
        $result['draw']            = $_REQUEST['draw'];
        return $result;
    }

    /************************************************************
    *
    *   KPI报表相关方法
    *
    *************************************************************
    */

    /**
     * KPI数据报表
     * @author xiaowen 2017-11-28
     * @param $param
     * @author xiaowen
     * @return array $data
     */
    public function kpiDataList($param = [])
    {
        set_time_limit(2000);
        ini_set('memory_limit','528M');
        //$kpiBase = $this->getModel('ErpKpiBase')->where(['status'=>1])->select();
        $where = ['status'=>1];
        if ($param['export'] == 1) {
            $kpiBaseData = $this->getModel('ErpKpiBase')->getAllKpiBaseList($where, true);
        } else {
            $kpiBaseData = $this->getModel('ErpKpiBase')->getKpiBaseList($where, true, $param['start'], $param['length']);
        }
        $kpiBase = $kpiBaseData['data'];

        //获取已设置岗位基础的员工
        $dealer_ids = array_column($kpiBase, 'dealer_id');
        $dealer_names = array_column($kpiBase, 'dealer_name');
        //获取所有员工的所有用户
        //$user_ids = $this->getModel('User')->where(['dealer_name'=>['in', $dealer_names], 'is_available'=>0, 'dealer_name'=>['neq', '']])->getField('id, dealer_name,user_phone');

        $param['month'] = $param['month'] ? $param['month'] : date('Y-m');
        //查询并缓存ERP内部账套
        $this->setCacheErpCompanyIds();
        //查询并缓存截止到本月的全部销售单
        $this->getErpOrder($param['month']);

        //历史成交客户池
        $history_user = $this->getHistoryOrderUser($param['month'], -3);

        $in_two_month_user = $this->getNumMonthAgoUser($param['month'], -2);
        
        $month_order = $this->getOneMonthErpOrder($param['month']);

        $current_month_user = $month_order['month_user'];
        //$current_month_order = $month_order['month_order'];
        $current_month_order = $month_order['orders'];

        log_info('3月以前成交客户：' . implode(',', $history_user) );
        log_info('3月内成交客户：' . implode(',', $in_two_month_user) );
        log_info('本月成交客户：' . implode(',', $current_month_user));

        //-------遍历本月订单，根据新客户、3月内老客户、3月以上老客户区分不同维度的成交量------------
        if($current_month_order){
            $order_data = [];

            //获取本月客户等级信息
            $user_level_arr = $this->getModel('User')->where(['id'=>['in', $current_month_user], 'is_available'=>0, 'dealer_name'=>['neq', '']])->getField('id,user_purchase_level');

            foreach($current_month_order as $key=>$order){
                //log_info('该订单用户：'.$order['user_id']);
                if(in_array($order['user_id'], $history_user)){
                    //log_info('历史客户：'.$order['user_id']);
                    $order_data[$order['dealer_id']]['beforeThreeOrder'] += $order['buy_num'];
                    $order_data[$order['dealer_id']]['beforeThreeOrder_' . checkUserLevel($user_level_arr[$order['user_id']])] += $order['buy_num'];
                    $order_data[$order['dealer_id']]['oldMallCustomer_'. checkUserLevel($user_level_arr[$order['user_id']])][] = $order['user_id'];
                    $user_one[] = $order['user_id'];
                }else if(!in_array($order['user_id'], $history_user) && in_array($order['user_id'], $in_two_month_user)  && in_array($order['user_id'], $current_month_user)){
                    $order_data[$order['dealer_id']]['inThreeOrder'] += $order['buy_num'];
                    $order_data[$order['dealer_id']]['inThreeOrder_' . checkUserLevel($user_level_arr[$order['user_id']])] += $order['buy_num'];
                    $order_data[$order['dealer_id']]['oldMallCustomer_'. checkUserLevel($user_level_arr[$order['user_id']])][] = $order['user_id'];
                    //log_info('3月内客户：'.$order['user_id']);
                    $user_two[] = $order['user_id'];
                }else if(!in_array($order['user_id'], $history_user) && !in_array($order['user_id'], $in_two_month_user) && in_array($order['user_id'], $current_month_user)){
                    $order_data[$order['dealer_id']]['newOrder'] += $order['buy_num'];
                    $order_data[$order['dealer_id']]['newOrder_' . checkUserLevel($user_level_arr[$order['user_id']])] += $order['buy_num'];
                    $order_data[$order['dealer_id']]['newMallCustomer_'. checkUserLevel($user_level_arr[$order['user_id']])][] = $order['user_id'];
                    //log_info('本月客户：'.$order['user_id']);
                    $user_three[] = $order['user_id'];
                }else{
                    //log_info('错误区'.$order['user_id']);
                }
            }
            log_info('本月成交3月上老用户：'.implode(',', array_unique($user_one)));
            log_info('本月成交3月内老用户：'.implode(',', array_unique($user_two)));
            log_info('本月成交新用户：'.implode(',', array_unique($user_three)));

            $activate_user_data = $this->getActivateUser($param['month'], $current_month_order);

            $regionArr = provinceCityZone()['city'];
            $provinceArr = provinceCityZone()['province'];
            $kpiBaseProvince = $this->getModel('Area')->where(['id'=>['in',array_column($kpiBase, 'region')]])->getField('id,parent_id');

            $is_regular = kpiRegularStatus();
            $kpi_plan_data = $this->getModel('ErpKpiPlan')->findKpiPlan(['id' => intval($param['plan_id'])]);

            //获取本月退货单数据
            $return_dealer_order = $this->getErpReturnOrder($param['month']);
            //处理本月退货数量并按客户进行分类
            $return_order_data = [];
            if($return_dealer_order){

                foreach($return_dealer_order as $key=>$order){
                    if(in_array($order['user_id'], $history_user)){
                        $return_order_data[$order['dealer_id']]['beforeThreeOrder_' . checkUserLevel($user_level_arr[$order['user_id']])] += $order['return_goods_num'];

                    }else if(!in_array($order['user_id'], $history_user) && in_array($order['user_id'], $in_two_month_user)){
                        $return_order_data[$order['dealer_id']]['inThreeOrder_' . checkUserLevel($user_level_arr[$order['user_id']])] += $order['return_goods_num'];

                    }else if(!in_array($order['user_id'], $history_user) && !in_array($order['user_id'], $in_two_month_user)){
                        $return_order_data[$order['dealer_id']]['newOrder_' . checkUserLevel($user_level_arr[$order['user_id']])] += $order['return_goods_num'];

                    }
                }
            }
            //开始计算已设置岗位基础资料交易员绩效-----------------------------------------
            foreach($kpiBase as $key=>$value){
                //岗位基础资料信息
                $tmp['id'] = $value['id'];
                $tmp['dealer_id'] = $value['dealer_id'];
                $tmp['dealer_name'] = $value['dealer_name'];
                $tmp['region'] = $value['region'];
                $tmp['region_name'] = $regionArr[$value['region']];
                $tmp['is_regular'] = $is_regular[$value['is_regular']];
                //$tmp['province_name'] = $provinceArr[$kpiBaseProvince[$value['region']]] . '-' . $tmp['region_name'];
                $tmp['province_name'] = $provinceArr[$kpiBaseProvince[$value['region']]];
                $tmp['post_base_num'] = getNum($value['post_base_num']);
                $tmp['new_mall_num'] = getNum($value['new_mall_num']);
                $tmp['old_mall_num'] = getNum($value['old_mall_num']);

                //激活客户数
                $tmp['active_users'] = count(array_unique($activate_user_data[$value['dealer_id']]));

                //本月退货数量
                $tmp['beforeThreeOrder_return_Terminal'] = getRealNum($return_order_data[$value['dealer_id']]['beforeThreeOrder_Terminal']);
                $tmp['beforeThreeOrder_return_Trade'] = getRealNum($return_order_data[$value['dealer_id']]['beforeThreeOrder_Trade']);

                $tmp['inThreeOrder_return_Terminal'] = getRealNum($return_order_data[$value['dealer_id']]['inThreeOrder_Terminal']);
                $tmp['inThreeOrder_return_Trade'] = getRealNum($return_order_data[$value['dealer_id']]['inThreeOrder_Trade']);

                $tmp['newOrder_return_Terminal'] = getRealNum($return_order_data[$value['dealer_id']]['newOrder_Terminal']);
                $tmp['newOrder_return_Trade'] = getRealNum($return_order_data[$value['dealer_id']]['newOrder_Trade']);

                //3月以上历史客户成交量
                $tmp['beforeThreeOrder'] = getRealNum($order_data[$value['dealer_id']]['beforeThreeOrder']) - $tmp['beforeThreeOrder_return_Terminal'] - $tmp['beforeThreeOrder_return_Trade'];
                $tmp['beforeThreeOrder_Terminal'] = getRealNum($order_data[$value['dealer_id']]['beforeThreeOrder_Terminal']) - $tmp['beforeThreeOrder_return_Terminal'];
                $tmp['beforeThreeOrder_Trade'] = getRealNum($order_data[$value['dealer_id']]['beforeThreeOrder_Trade']) - $tmp['beforeThreeOrder_return_Trade'];

                //3月内老客户成交量
                $tmp['inThreeOrder'] = getRealNum($order_data[$value['dealer_id']]['inThreeOrder']) - $tmp['inThreeOrder_return_Terminal'] - $tmp['inThreeOrder_return_Trade'];
                $tmp['inThreeOrder_Terminal'] = getRealNum($order_data[$value['dealer_id']]['inThreeOrder_Terminal']) - $tmp['inThreeOrder_return_Terminal'];
                $tmp['inThreeOrder_Trade'] = getRealNum($order_data[$value['dealer_id']]['inThreeOrder_Trade']) - $tmp['inThreeOrder_return_Trade'];

                //本月新客户成交量
                $tmp['newOrder'] = getRealNum($order_data[$value['dealer_id']]['newOrder']) - $tmp['newOrder_return_Terminal'] - $tmp['newOrder_return_Trade'];
                $tmp['newOrder_Terminal'] = getRealNum($order_data[$value['dealer_id']]['newOrder_Terminal']) - $tmp['newOrder_return_Terminal'];
                $tmp['newOrder_Trade'] = getRealNum($order_data[$value['dealer_id']]['newOrder_Trade']) - $tmp['newOrder_return_Trade'];

                //老客户成交总量
                //$tmp['oldOrderNum'] = getRealNum($order_data[$value['dealer_id']]['beforeThreeOrder'] + $order_data[$value['dealer_id']]['inThreeOrder']);
                $tmp['oldOrderNum'] = $tmp['beforeThreeOrder'] + $tmp['inThreeOrder'];
                //本月激活客户绩效
                $tmp['active_bonus'] = $this->activateCustomerBonus($tmp['active_users'], $kpi_plan_data['activate_customer_commission']);
                $tmp['active_bonus'] = round($tmp['active_bonus'], 2);
                //绩效奖金  = 本月销售量绩效 + 本月激活客户绩效
                $tmp['total_bonus'] = $this->getDealerBonus($value['is_regular'], $tmp, $kpi_plan_data, $value) + $tmp['active_bonus'];
                $tmp['total_bonus'] = round($tmp['total_bonus'], 2);
                    //新老客户数
                $tmp['newMallCustomer_Terminal'] = count(array_unique($order_data[$value['dealer_id']]['newMallCustomer_Terminal']));
                $tmp['newMallCustomer_Trade'] = count(array_unique($order_data[$value['dealer_id']]['newMallCustomer_Trade']));
                $tmp['oldMallCustomer_Terminal'] = count(array_unique($order_data[$value['dealer_id']]['oldMallCustomer_Terminal']));
                $tmp['oldMallCustomer_Trade'] = count(array_unique($order_data[$value['dealer_id']]['oldMallCustomer_Trade']));

                //新老客户总数
                $tmp['newMallCustomer_total'] = $tmp['newMallCustomer_Terminal'] + $tmp['newMallCustomer_Trade'];
                $tmp['oldMallCustomer_total'] = $tmp['oldMallCustomer_Terminal'] + $tmp['oldMallCustomer_Trade'];

                $data['data'][] = $tmp;
                //根据计提方案的激活计提 计算激活资金

            }
        }
        //-----------------------------------遍历结束--------------------------------------------------

        //print_r($data);
        $data['recordsFiltered'] = $data['recordsTotal'] = $kpiBaseData['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /************************************************************
     *
     * KPI报表基础方法
     *
     ************************************************************
     */

    /**
     * 获取激活客户
     * @param $month 当前月份 格式 2017-11
     * @param array $current_month_order  当月成交用户
     * @return array
     */
    public function getActivateUser($month, $current_month_order = []){
        //历史成交客户池4个月前
        $history_user = $this->getHistoryOrderUser($month, -4);

        $in_three_month_user = $this->getNumMonthAgoUser($month, -3);
        $activate_user = [];
        $activate_user_dealer = [];
        foreach($current_month_order as $key=>$order){
            if(in_array($order['user_id'], $history_user) && !in_array($order['user_id'], $in_three_month_user) && !in_array($order['user_id'],$activate_user)){
                $activate_user[] = $order['user_id'];
                if(!in_array($order['user_id'], $activate_user_dealer[$order['dealer_id']])){
                    $activate_user_dealer[$order['dealer_id']][] = $order['user_id'];
                }
            }
        }
        return $activate_user_dealer;
    }
    /**
     * 验证是否有当月成交但未配置岗位资料的交易员
     * @param array $param
     * @return array
     */
    public function getNoBaseInfoUser($param){
        set_time_limit(0);
        ini_set('memory_limit','1024M');
        //查询并缓存ERP内部账套
        $this->setCacheErpCompanyIds();
        ////查询并缓存ERP订单
        $this->getErpOrder($param['month'] ? $param['month'] : date('Y-m'),1);

        //当前月有成交订单的所有交易员
        $param['month'] = $param['month'] ? $param['month'] : date('Y-m');
        $month_order = $this->getOneMonthErpOrder($param['month']);
        $current_month_order = $month_order['orders'];
        $current_month_dealer_names = array_unique(array_column($current_month_order, 'dealer_name'));

        //查询所有设置过基础量的交易员
        $where = ['status'=>1];
        $kpiBaseData = $this->getModel('ErpKpiBase')->getAllKpiBaseList($where, true);
        $kpiBase = $kpiBaseData['data'];

        //获取已设置岗位基础的员工
        $dealer_names = array_column($kpiBase, 'dealer_name');
        $no_base_user = array_diff($current_month_dealer_names, $dealer_names);
        //### 如果当月订单中存在交易员未配置岗位基础信息，则返回提示信息----------------------------
        if($no_base_user){
            $data = [
                'status' => 4,
                'message' => '【' . implode(',', $no_base_user) . '】 以上人员未配置人员',

            ];
            return $data;
        }else{
            return ['status'=>1, 'message'=>''];
        }
    }
    /**
     * 历史已成交的用户
     * @param string $month
     * @param int $num
     * @author xiaowen
     * @time 2017-11-28
     * @return array
     */
    public function getHistoryOrderUser($month = '', $num){
        $user_pluto = $this->getHistoryPlutoOrder();
        $user_erp = $this->getHistoryErpOrderUser($month,$num);

        return array_unique(array_merge($user_erp, $user_pluto));
    }


    /**
     * 返回Pluto中已成交的历史客户
     * @return array|mixed
     */
    public function getHistoryPlutoOrder(){
        $pluto_where = [
            'o.is_available'=>0,
            'o.is_pass'=>2,
            'o.sale_coid'=> ['in', S('getErpCompanyIds')],
            'o.buy_coid'=>['not in', S('getErpCompanyIds')],
        ];

        if(S('pluto_order_user')){
            $pluto_order_user = S('pluto_order_user');
        }else{
            $pluto_order_data = $this->getModel('Order')->alias('o')->field('o.*')->where($pluto_where)->join('oil_user u on o.buy_id = u.id ', 'left')->group('o.buy_id')->select();
            $pluto_order_user = array_unique(array_column($pluto_order_data, 'buy_id'));
            S('pluto_order_user', $pluto_order_user);
        }

        return $pluto_order_user;

    }

    /**
     * 获取ERP截止到当月所有已确认的销售单
     * @param string $month 当月月份 格式2017-11 默认本月
     * @param int $is_search 是否已查询并缓存 0 否 1是
     * @return mixed
     */
    public function getErpOrder($month = '', $is_search = 0){
        if($is_search){
            $month = trim($month) ? trim($month) : date('Y-m');
            $where = [
                'o.order_status' => 10,
                'o.order_type' => 1,
                "DATE_FORMAT(o.add_order_time, '%Y-%m' )" => ['ELT', $month], //edit xiaowen 2017-12-6 查询历史成交用户，不用包含当月 将elt 改为 lt
                'o.company_id' => ['not in', S('getErpCompanyIds')],
            ];
            //$field = "o.*,DATE_FORMAT(o.add_order_time, '%Y-%m') as add_order_month";
            $field = "o.add_order_time,o.user_id,o.buy_num,o.dealer_id,o.dealer_name,o.order_status,o.goods_id,o.price,o.order_amount,DATE_FORMAT(o.add_order_time, '%Y-%m') as add_order_month";
            //$data = $this->getModel('ErpSaleOrder')->alias('o')->field($field)->where($where)->group("o.user_id,DATE_FORMAT(o.add_order_time, '%Y-%m' )")->select();
            $data = $this->getModel('ErpSaleOrder')->alias('o')->field($field)->where($where)->select();

            //将全部订单放入缓存中，以供后续销售统计及新老客户区分使用
            S('all_erp_order', $data);
        }

        return $data;
    }

    /**
     * 历史ERP订单成交用户（前3月以上）
     * @param $month
     * @param int $num 默认前3月
     * @return array $result
     */
    public function getHistoryErpOrderUser($month = '', $num = -3){

        $history_users = [];
        if(S('all_erp_order')){
            $month = trim($month) ? trim($month) : date('Y-m');
            //获取往前推3个月的所有历史成交客户
            foreach(S('all_erp_order') as $key=>$value){
                //if(intval(date('Ym', strtotime($value['add_order_time']))) <= intval(getMonthAgoByNum(date('Y-m', strtotime($value['add_order_time'])), 3, 1)) && !in_array($value['user_id'], $history_users)){
                if(intval(date('Ym', strtotime($value['add_order_time']))) <= intval(getMonthAgoByNum($month, $num , 1)) && !in_array($value['user_id'], $history_users)){
                    $history_users[] = $value['user_id'];
                }
            }
            $result = array_unique($history_users);
        }

        return $result;
    }
    /**
     * ERP订单成交用户（前N月以内）
     * @param string $month 当前月 格式2017-11
     * @param int $num 前N月数 默认前2个月
     * @return array $result
     */
    public function getNumMonthAgoUser($month = '', $num = -2){

        $months_ago_users = [];
        if(S('all_erp_order')){
            $month = trim($month) ? trim($month) : date('Y-m');
            //获取截止当月且往前推2个月的所有成交客户
            foreach(S('all_erp_order') as $key=>$value){
                if(intval(date('Ym', strtotime($value['add_order_month']))) >= intval(getMonthAgoByNum($month, $num, 1)) && intval(date('Ym', strtotime($value['add_order_time']))) < intval(getMonthAgoByNum($month, 0, 1)) && !in_array($value['user_id'], $months_ago_users)){
                    $months_ago_users[] = $value['user_id'];
                    $orders[intval(date('Ym', strtotime($value['add_order_time'])))][] = $value['user_id'];
                }
            }
            //log_info(print_r($orders, true));
            $result = array_unique($months_ago_users);
        }

        return $result;
    }
    /**
     * 返回当月的销售单
     * @param string $month 默认当前月，参数格式 '2017-11'
     * @return array
     */
    public function getOneMonthErpOrder($month = ''){

        $data = [];
        $result = [];
        if(S('all_erp_order')){
            $month = trim($month) ? trim($month) : date('Y-m');
            //获取当月的成交订单
            foreach(S('all_erp_order') as $key=>$value){
                //if(intval(date('Ym', strtotime($value['add_order_time']))) <= intval(getMonthAgoByNum(date('Y-m', strtotime($value['add_order_time'])), 3, 1)) && !in_array($value['user_id'], $history_users)){
                if(date('Y-m', strtotime($value['add_order_time'])) == trim($month)){
                    $data[] = $value;
                }
            }
        }else{
            $where = [
                'o.order_status' => 10,
                'o.order_type' => 1,
                "DATE_FORMAT(o.add_order_time, '%Y-%m')" => trim($month) ? trim($month) : date('Y-m'),
                'o.company_id' => ['not in', S('getErpCompanyIds')],
            ];
            $field = 'o.*';
            $data = $this->getModel('ErpSaleOrder')->alias('o')->field($field)->where($where)->select();
        }
        if($data){
            $result['month_user'] = array_unique(array_column($data, 'user_id'));
            $result['month_order'] = [];
            foreach($data as $key=>$value){
                $result['month_order'][$value['user_id']][] = $value;
            }
            $result['orders'] = $data;
        }

        return $result;
    }

    /**
     * @param $where
     * @param bool|true $field
     * @return mixed
     */
    public function getErpSaleOrderByWhere($where, $field = true){
        $data = $this->getModel('ErpOrder')->field($field)->where($where)->select();
        return $data;
    }

    /**
     * 返回交易员绩效
     * @param $is_regular 1 转正 2未转正
     * @param array $volume 成交量数组
     * @param array $commission  计提资金数组
     * @param array $base  岗位基础量
     * @return int
     */
    public function getDealerBonus($is_regular, $volume, $commission, $base){

        //转正员工绩效
        if($is_regular == 1){
            $bonus_one = $this->newCustomerBonus($volume, $commission, $base);
            $bonus_two = $this->oldCustomerBonus($volume, $commission, $base);
            $result = $bonus_one + $bonus_two;
        }else{
            //未转正员工绩效
            $bonus_one = $this->noRegularBonus($volume, $commission);
            $result = $bonus_one;
        }

        return $result;
    }

    /**
     * 新客户绩效奖金
     * @param array $volume 成交量数组
     * @param array $commission  计提资金数组
     * @param array $base  岗位基础量
     * @author xiaowen
     * @time 2017-12-1
     * @return int $result
     */
    public function newCustomerBonus($volume = [], $commission, $base){
        $result = 0;
        //新客户和3月内老客户的成交量和
        $total_volume = $volume['newOrder_Terminal'] + $volume['newOrder_Trade'] + $volume['inThreeOrder_Terminal'] + $volume['inThreeOrder_Trade'];
        $overrun = $total_volume - getNum($base['new_mall_num']);
        if($overrun > 0){ // if (B+C+D+E-M) <=0
           if(($overrun - ($volume['newOrder_Terminal'] + $volume['inThreeOrder_Terminal'])) <= 0){ //if（B+C+D+E-M）-(B+D) <=0
               //((B+C+D+E-M)*H）
                $result = $overrun * getNum($commission['new_terminal_commission']);
           }else{
               //((B+C+D+E-B-D)-M)*I)+((B+D)*H)
               $result = ($overrun - ($volume['newOrder_Terminal'] + $volume['inThreeOrder_Terminal'])) * getNum($commission['new_trade_commission']) + (($volume['newOrder_Terminal'] + $volume['inThreeOrder_Terminal'])  * getNum($commission['new_terminal_commission']));
           }
        }
        return $result;
    }

    /**
     * 老客户绩效
     * @param array $volume 成交量数组
     * @param array $commission  计提资金数组
     * @param array $base  岗位基础量
     * @author xiaowen
     * @time 2017-12-1
     * @return int $result
     */
    public function oldCustomerBonus($volume = [], $commission, $base){
        $result = 0;
        //新客户和3月内老客户的成交量和
        $total_volume = $volume['beforeThreeOrder_Terminal'] + $volume['beforeThreeOrder_Trade'];
        $overrun = $total_volume - getNum($base['old_mall_num']);
        if($overrun > 0){
            if(($overrun - $volume['beforeThreeOrder_Terminal']) <= 0){ //IF((AB+AC)-N)<=AB
                //((AB+AC)-N)*AD
                $result = $overrun * getNum($commission['old_terminal_commission']);
            }else{
                //(((AB+AC)-N)-AB)*AE)+(AB*AD)
                $result = (($overrun - $volume['beforeThreeOrder_Terminal']) * getNum($commission['old_trade_commission'])) + ($volume['beforeThreeOrder_Terminal'] * getNum($commission['old_terminal_commission']));
            }
        }
        return $result;
    }

    /**
     * 计算未转正员工绩效
     * @param array $volume 成交量数组
     * @param array $commission  计提资金数组
     * @author xiaowen
     * @time 2017-12-1
     * @return int $result
     */
    public function noRegularBonus($volume = [], $commission){
        //未转正员工绩效公式：=（（新客户商城终端成交量 * 新客户终端计提）+（新客户商城贸易成交量* 贸易计提））+（（老客户商城终端成交量*老客户终端计提）+（老客户商城贸易成交量*老客户贸易计提）
//        $result =(($volume['beforeThreeOrder_Terminal'] + $volume['inThreeOrder_Terminal']) * getNum($commission['old_terminal_commission'])) +
//
//                 (($volume['beforeThreeOrder_Trade'] + $volume['inThreeOrder_Trade']) * getNum($commission['old_trade_commission'])) +
        //edit xiaowen 2018-5-3 未转正员工的三月内老客户与新客户一样，按新客户计提系统计算提成
        $result =(($volume['beforeThreeOrder_Terminal']) * getNum($commission['old_terminal_commission'])) +

            (($volume['beforeThreeOrder_Trade']) * getNum($commission['old_trade_commission'])) +
            (($volume['newOrder_Terminal']  + $volume['inThreeOrder_Terminal']) * getNum($commission['new_terminal_commission'])) +

            (($volume['newOrder_Trade'] + $volume['inThreeOrder_Trade']) * getNum($commission['new_trade_commission']));
        return $result;
    }

    /**
     * 激活客户绩效
     * @param $customer_num
     * @param $commission
     * @return int $result
     */
    public function activateCustomerBonus($customer_num, $commission){
        $result = 0;
        if($customer_num > 0){
            $result = $customer_num * getNum($commission);
        }
        return $result;
    }

    /**
     * ERP订单退货量（当月）
     * @param string $month 当前月 格式2017-11
     * @author qianbin
     * @return array $result
     */
    public function getErpReturnOrder($month = '')
    {
        $result = [];
        $month = trim($month) ? trim($month) : date('Y-m');
        $where = [
            'r.order_status' => 10,     # 已确认
            'r.order_type'   => 1 ,     # 销售单
            'o.order_type'   => 1,      # 批发
            'o.company_id'   => ['not in', S('getErpCompanyIds')],
            "DATE_FORMAT(r.order_time, '%Y-%m' )" => ['eq', $month],
        ];

        $field        = "r.id,r.source_order_id,r.return_goods_num,o.dealer_id,o.user_id,DATE_FORMAT(r.order_time, '%Y-%m') as add_order_month";
        $return_data  = $this->getModel('ErpReturnedOrder')->field($field)->alias('r')->join('oil_erp_sale_order o on o.id = r.source_order_id ' , "left")->where($where)->select();
        if(count($return_data) <= 0) return $result;

        return $return_data;
    }

    /**
     * 返回所有的KPI计提方案
     * @author xiaowen
     * @time 2017
     * @return mixed
     */
    public function getAllKpiPlan(){
        $data = $this->getModel('ErpKpiPlan')->where(['status'=>1])->getField('id, plan_name,new_terminal_commission,new_trade_commission,old_terminal_commission,old_trade_commission,activate_customer_commission');
        if(count($data) <= 0) return $data;
        foreach ($data as $k => $v) {
            $data[$k]['new_terminal_commission']        = getNum($data[$k]['new_terminal_commission']);
            $data[$k]['new_trade_commission']           = getNum($data[$k]['new_trade_commission']);
            $data[$k]['activate_customer_commission']   = getNum($data[$k]['activate_customer_commission']);
            $data[$k]['old_terminal_commission']        = getNum($data[$k]['old_terminal_commission']);
            $data[$k]['old_trade_commission']           = getNum($data[$k]['old_trade_commission']);
        }
        return $data;
    }

    /**
     * 获取ERP账套公司并缓存
     * @time 2017-12-14
     * @author xiaowen
     * @return mixed
     */
    public function setCacheErpCompanyIds(){
        //return S('getErpCompanyIds', [3372, 70, 22, 18]);
        //return S('getErpCompanyIds') ? S('getErpCompanyIds') : S('getErpCompanyIds', getErpCompanyList('company_id'));
        return S('getErpCompanyIds',getErpCompanyList('company_id'));
    }
}
