<?php
/**
 * 销售单业务处理层
 * @author xiaowen
 * @time 2017-04-17
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpRechargeEvent extends BaseController
{

    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
    }

/////////////////////////////////////////////////////预存管理///////////////////////////////////////////////////////////

    /**
     * 预存/预付申请单列表
     * @author guanyu
     * @time 2017-10-31
     */
    public function RechargeOrderList($param = [])
    {
        $where = [];

        //订单号
        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        //时间区间
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.add_order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time'])))];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time'])))]];
            }
        }
        //城市
        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }
        //客户
        if (trim($param['Recharge_user'])) {
            //$user_ids = D("User")->where(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%'], 'is_available' => 0])->getField('id', true);
            if($param['type'] == 1){
                $user_ids = $this->getEvent('ErpCustomer')->getCustomerUserDataField(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%']],'id,user_name');
            }else if($param['type'] == 2){
                $user_ids = $this->getEvent('ErpSupplier')->getSupplierUserDataField(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%']],'id,user_name');
            }
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
        //公司
        if (trim($param['Recharge_company_id'])) {
            $where['o.company_id'] = intval(trim($param['Recharge_company_id']));
        }
        //订单状态
        if (trim($param['order_status'])) {
            $where['o.order_status'] = intval(trim($param['order_status']));
        }
        //财务处理状态
        if (trim($param['finance_status'])) {
            $where['o.finance_status'] = intval(trim($param['finance_status']));
        }
        //交易员
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        //订单类型：1、预付  2、预存
        if (trim($param['type'])) {
            $where['o.order_type'] = trim($param['type']);
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        $field = 'o.*,u.user_name,u.user_phone,c.company_name';
        //$field = 'o.*';
        if ($param['export']) {
            $data = $this->getModel('ErpRechargeOrder')->getAllRechargeOrderList($where, $field);
        } else {
            $data = $this->getModel('ErpRechargeOrder')->getRechargeOrderList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];

            $creater_arr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', array_unique(array_column($data['data'],'creater'))]])->getField('id,dealer_name');
            //===================匹配用户和公司 edit:xiaowen time:2018-10-15========================================
            $userIdArr = array_column($data['data'], 'user_id');
            $companyIdArr = array_column($data['data'], 'company_id');

            $userInfo = $this->getEvent('ErpCommon')->getUserData($userIdArr, $param['type']);
            $companyInfo = $this->getEvent('ErpCommon')->getCompanyData($companyIdArr, $param['type']);
            //print_r($companyInfo);
            //====================用户与公司匹配结束==================================================================
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['create_time'] = date('Y-m-d', strtotime($value['create_time']));
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['recharge_amount'] = $value['recharge_amount'] != 0 ? getNum($value['recharge_amount']) : '0';
                $data['data'][$key]['recharge_type'] = $param['type'] == 1 ? PrestoreType($value['recharge_type']) : PrepayType($value['recharge_type']);
                $data['data'][$key]['order_status_font'] = RechargeOrderStatus($value['order_status']);
                $data['data'][$key]['order_status'] = RechargeOrderStatus($value['order_status'],true);
                $data['data'][$key]['finance_status_font'] = RechargeFinanceStatus($value['finance_status']);
                $data['data'][$key]['finance_status'] = RechargeFinanceStatus($value['finance_status'],true);
                $data['data'][$key]['creater_name'] = $creater_arr[$value['creater']];

                $data['data'][$key]['user_name'] = $userInfo[$value['user_id']]['user_name'];
                $data['data'][$key]['user_phone'] = $userInfo[$value['user_id']]['user_phone'];
                $data['data'][$key]['company_name'] = $companyInfo[$value['company_id']]['company_name'];
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 新增预存单
     * @param array $param
     * @author guanyu
     * @time 2017-11-05
     * @return array
     */
    public function addPrestoreOrder($param = [])
    {
        //参数验证
        if (empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择业务日期'
            ];
            return $result;
        }
        if (strHtml($param['region']) == '') {
            $result = [
                'status' => 4,
                'message' => '请选择城市'
            ];
            return $result;
        }
        if (strHtml($param['user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择客户'
            ];
            return $result;
        }
        if (strHtml($param['company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择公司'
            ];
            return $result;
        }
        if (strHtml($param['recharge_type']) == '') {
            $result = [
                'status' => 7,
                'message' => '请选择预存类型'
            ];
            return $result;
        }
        if (strHtml($param['recharge_amount']) == '') {
            $result = [
                'status' => 8,
                'message' => '请填写预存金额'
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/addPrestoreOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/addPrestoreOrder', 1);

        M()->startTrans();

        //新增单据
        $data = [
            'order_number' => erpCodeNumber(13)['order_number'],
            'add_order_time' => $param['add_order_time'],
            'our_company_id' => session('erp_company_id'),
            'region' => $param['region'],
            'user_id' => $param['user_id'],
            'company_id' => $param['company_id'],
            'order_type' => 1,
            'recharge_type' => $param['recharge_type'],
            'recharge_amount' => setNum($param['recharge_amount']),
            'order_status' => 1,
            'finance_status' => 1,
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'creater' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'remark' => $param['remark'],
        ];
        $order_status = $id = $this->getModel('ErpRechargeOrder')->addRechargeOrder($data);

        //新增log
        $log_data = [
            'recharge_id' => $id,
            'recharge_order_number' => $data['order_number'],
            'order_type' => 1,
            'log_info' => serialize($data),
            'log_type' => 1,
        ];
        $log_status = $this->addRechargeOrderLog($log_data);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpRecharge/addPrestoreOrder');
        return $result;
    }

    /**
     * 编辑预存单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function updatePrestoreOrder($id, $param = [])
    {
        //参数验证
        if (!$id || empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择业务日期'
            ];
            return $result;
        }
        if (strHtml($param['region']) == '') {
            $result = [
                'status' => 4,
                'message' => '请选择城市'
            ];
            return $result;
        }
        if (strHtml($param['user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择客户'
            ];
            return $result;
        }
        if (strHtml($param['company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择公司'
            ];
            return $result;
        }
        if (strHtml($param['recharge_type']) == '') {
            $result = [
                'status' => 7,
                'message' => '请选择预存类型'
            ];
            return $result;
        }
        if (strHtml($param['recharge_amount']) == '') {
            $result = [
                'status' => 8,
                'message' => '请填写预存金额'
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/updatePrestoreOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/updatePrestoreOrder', 1);

        M()->startTrans();

        //编辑单据
        $data = [
            'add_order_time' => $param['add_order_time'],
            'our_company_id' => session('erp_company_id'),
            'region' => $param['region'],
            'user_id' => $param['user_id'],
            'company_id' => $param['company_id'],
            'recharge_type' => $param['recharge_type'],
            'recharge_amount' => setNum($param['recharge_amount']),
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
            'remark' => $param['remark'],
        ];
        $order_status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' =>$id], $data);

        $RechargeOrderInfo_new = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        //新增log
        $log_data = [
            'recharge_id' => $id,
            'recharge_order_number' => $RechargeOrderInfo_new['order_number'],
            'order_type' => 1,
            'log_info' => serialize($RechargeOrderInfo_new),
            'log_type' => 2,
        ];
        $log_status = $this->addRechargeOrderLog($log_data);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpRecharge/updatePrestoreOrder');
        return $result;
    }

    /**
     * 审核预存申请单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function auditPrestoreOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if ($order_info['order_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '该预存申请单不是未审核状态，无法审核',
            ];
            return $result;
        }
        //930改造此验证已无效
        //------------------------验证公司交易类型是否允许做预存业务，edit xiaowen 2018-5-23---------------------//
//        if(!$this->getEvent('Clients')->checkCompanyTransactionType($order_info['company_id'], 1)){
//            $result = [
//                'status' => 4,
//                'message' => '对不起，该公司交易类型不允许做预存业务，请修改该公司交易类型后再操作！',
//            ];
//            cancelCacheLock('ErpPurchase/auditPurchaseOrder');
//            return $result;
//        }
        //----------------------end 验证公司交易类型是否允许做预存业务，edit xiaowen 2018-5-23---------------------//
        if (getCacheLock('ErpRecharge/auditPrestoreOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/auditPrestoreOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 3,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];

        //生成预存申请单审批流程
        $workflow_result = $this->createWorkflow($order_info);
        if($workflow_result['check_order'] == 1){
            $data['order_status'] = 4;
        }
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 1,
            'log_info' => serialize($order_info),
            'log_type' => 4,
        ];
        $log_status = $this->addRechargeOrderLog($log);

        log_info('生成审批' . $workflow_result['status'] ? '成功' : '失败');

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

        cancelCacheLock('ErpRecharge/auditPrestoreOrder');
        return $result;
    }

    /**
     * 取消预存申请单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function delPrestoreOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if ($order_info['order_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '该预存申请单不是未审核状态，无法取消',
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/delPrestoreOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/delPrestoreOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 2,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 1,
            'log_info' => serialize($order_info),
            'log_type' => 3,
        ];
        $log_status = $this->addRechargeOrderLog($log);


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

        cancelCacheLock('ErpRecharge/delPrestoreOrder');
        return $result;
    }

    /**
     * 确认预存申请单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function confirmPrestoreOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if ($order_info['order_status'] != 4) {
            $result = [
                'status' => 2,
                'message' => '该预存申请单不是已复核状态，无法确认',
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/confirmPrestoreOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/confirmPrestoreOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 10,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 1,
            'log_info' => serialize($order_info),
            'log_type' => 6,
        ];
        $log_status = $this->addRechargeOrderLog($log);

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

        cancelCacheLock('ErpRecharge/confirmPrestoreOrder');
        return $result;
    }

/////////////////////////////////////////////////////预付管理///////////////////////////////////////////////////////////

    /**
     * 新增预付单
     * @param array $param
     * @author guanyu
     * @time 2017-11-05
     * @return array
     */
    public function addPrepayOrder($param = [])
    {
        //参数验证
        if (empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择业务日期'
            ];
            return $result;
        }
        if (strHtml($param['region']) == '') {
            $result = [
                'status' => 4,
                'message' => '请选择城市'
            ];
            return $result;
        }
        if (strHtml($param['user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择客户'
            ];
            return $result;
        }
        if (strHtml($param['company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择公司'
            ];
            return $result;
        }
        if (strHtml($param['collection_info']) == '') {
            $result = [
                'status' => 7,
                'message' => '请选择银行账号'
            ];
            return $result;
        }
        if (strHtml($param['recharge_type']) == '') {
            $result = [
                'status' => 8,
                'message' => '请选择预付类型'
            ];
            return $result;
        }
        if (strHtml($param['recharge_amount']) == '') {
            $result = [
                'status' => 9,
                'message' => '请填写预付金额'
            ];
            return $result;
        }
        if (strHtml($param['apply_finance_time']) == '') {
            $result = [
                'status' => 10,
                'message' => '请选择申请付款日期'
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/addPrepayOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/addPrepayOrder', 1);

        M()->startTrans();

        //新增单据
        $data = [
            'order_number' => erpCodeNumber(14)['order_number'],
            'add_order_time' => $param['add_order_time'],
            'our_company_id' => session('erp_company_id'),
            'region' => $param['region'],
            'user_id' => $param['user_id'],
            'company_id' => $param['company_id'],
            'collection_info' => $param['collection_info'],
            'order_type' => 2,
            'recharge_type' => $param['recharge_type'],
            'recharge_amount' => setNum($param['recharge_amount']),
            'order_status' => 1,
            'finance_status' => 1,
            'apply_finance_time' => $param['apply_finance_time'],
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'creater' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'remark' => $param['remark'],
        ];
        $order_status = $id = $this->getModel('ErpRechargeOrder')->addRechargeOrder($data);

        //新增log
        $log_data = [
            'recharge_id' => $id,
            'recharge_order_number' => $data['order_number'],
            'order_type' => 2,
            'log_info' => serialize($data),
            'log_type' => 1,
        ];
        $log_status = $this->addRechargeOrderLog($log_data);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpRecharge/addPrepayOrder');
        return $result;
    }

    /**
     * 编辑预付单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function updatePrepayOrder($id, $param = [])
    {
        //参数验证
        if (!$id || empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择业务日期'
            ];
            return $result;
        }
        if (strHtml($param['region']) == '') {
            $result = [
                'status' => 4,
                'message' => '请选择城市'
            ];
            return $result;
        }
        if (strHtml($param['user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择客户'
            ];
            return $result;
        }
        if (strHtml($param['company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择公司'
            ];
            return $result;
        }
        if (strHtml($param['collection_info']) == '') {
            $result = [
                'status' => 7,
                'message' => '请选择银行账号'
            ];
            return $result;
        }
        if (strHtml($param['recharge_type']) == '') {
            $result = [
                'status' => 8,
                'message' => '请选择预付类型'
            ];
            return $result;
        }
        if (strHtml($param['recharge_amount']) == '') {
            $result = [
                'status' => 9,
                'message' => '请填写预付金额'
            ];
            return $result;
        }
        if (strHtml($param['apply_finance_time']) == '') {
            $result = [
                'status' => 10,
                'message' => '请选择申请付款日期'
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/updatePrepayOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/updatePrepayOrder', 1);

        M()->startTrans();

        //编辑单据
        $data = [
            'add_order_time' => $param['add_order_time'],
            'our_company_id' => session('erp_company_id'),
            'region' => $param['region'],
            'user_id' => $param['user_id'],
            'company_id' => $param['company_id'],
            'collection_info' => $param['collection_info'],
            'recharge_type' => $param['recharge_type'],
            'recharge_amount' => setNum($param['recharge_amount']),
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
            'remark' => $param['remark'],
        ];
        $order_status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' =>$id], $data);

        $RechargeOrderInfo_new = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        //新增log
        $log_data = [
            'recharge_id' => $id,
            'recharge_order_number' => $RechargeOrderInfo_new['order_number'],
            'order_type' => 2,
            'log_info' => serialize($RechargeOrderInfo_new),
            'log_type' => 2,
        ];
        $log_status = $this->addRechargeOrderLog($log_data);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpRecharge/updatePrepayOrder');
        return $result;
    }

    /**
     * 审核预付申请单
     * @param int $id
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function auditPrepayOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if ($order_info['order_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '该预付申请单不是未审核状态，无法审核',
            ];
            return $result;
        }
        //930改造此验证已无效 edit xiaowen 2018-10-16
        //------------------------验证公司交易类型是否允许做预付业务，edit xiaowen 2018-5-23---------------------//
//        if(!$this->getEvent('Clients')->checkCompanyTransactionType($order_info['company_id'], 2)){
//            $result = [
//                'status' => 4,
//                'message' => '对不起，该公司交易类型不允许做预付业务，请修改该公司交易类型后再操作！',
//            ];
//            cancelCacheLock('ErpPurchase/auditPurchaseOrder');
//            return $result;
//        }
        //----------------------end 验证公司交易类型是否允许做预付业务，edit xiaowen 2018-5-23---------------------//
        if (getCacheLock('ErpRecharge/auditPrepayOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/auditPrepayOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 3,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];

        //生成预付申请单审批流程
        $workflow_result = $this->createWorkflow($order_info);
        if($workflow_result['check_order'] == 1){
            $data['order_status'] = 4;
        }
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 2,
            'log_info' => serialize($order_info),
            'log_type' => 4,
        ];
        $log_status = $this->addRechargeOrderLog($log);

        log_info('生成审批' . $workflow_result['status'] ? '成功' : '失败');

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

        cancelCacheLock('ErpRecharge/auditPrepayOrder');
        return $result;
    }

    /**
     * 取消预付申请单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function delPrepayOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if ($order_info['order_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '该预付申请单不是未审核状态，无法取消',
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/delPrepayOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/delPrepayOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 2,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 2,
            'log_info' => serialize($order_info),
            'log_type' => 3,
        ];
        $log_status = $this->addRechargeOrderLog($log);


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

        cancelCacheLock('ErpRecharge/delPrepayOrder');
        return $result;
    }

    /**
     * 确认预付申请单
     * @param array $param
     * @author guanyu
     * @time 2017-11-08
     * @return array
     */
    public function confirmPrepayOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if ($order_info['order_status'] != 4) {
            $result = [
                'status' => 2,
                'message' => '该预付申请单不是已复核状态，无法确认',
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/confirmPrepayOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/confirmPrepayOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 10,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 2,
            'log_info' => serialize($order_info),
            'log_type' => 6,
        ];
        $log_status = $this->addRechargeOrderLog($log);

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

        cancelCacheLock('ErpRecharge/confirmPrepayOrder');
        return $result;
    }

    /**
     * 获取一条订单信息（不含关联信息）
     * @param $id
     * @return mixed
     * @author xiaowen
     * @time 2017-4-21
     */
    public function findRechargeOrder($id){
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id'=>intval($id)]);
        return $order_info;
    }

    /**
     * 获取一条完整的订单信息（包含关联信息）
     * @param $id
     * @param $field
     * @return mixed
     * @author guanyu
     * @time 2017-11-07
     */
    public function findOneRechargeOrder($id, $field){
        //$field = 'o.*,d.depot_name,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';

        $order_info = $this->getModel('ErpRechargeOrder')->findOneRechargeOrder(['o.id'=>intval($id)], $field);
        if($order_info['order_type'] == 1){ //预存单取客户表数据
            $user_info = $this->getEvent('ErpCustomer')->UserDetail($order_info['user_id']);
            $order_info['user_name'] = $user_info['user_name'];
            $order_info['user_phone'] = $user_info['user_phone'];
            # syf 更改 Time 2018-12-06
            // $order_info['company_name'] = $this->getEvent('ErpCustomer')->getCustomerDataField(['id' => $order_info['company_id']], 'customer_name');
            $customer_arr = $this->getEvent('ErpCustomer')->getOneCustomerData(['id' => $order_info['company_id']], 'customer_name,data_source');
            $order_info['company_name'] = $customer_arr['customer_name'];
            $order_info['data_source'] = dataSourceName($customer_arr['data_source']);
        }else if($order_info['order_type'] == 2){ //预付单取供应商表数据
            $user_info = $this->getEvent('ErpSupplier')->UserDetail($order_info['user_id']);
            $order_info['user_name'] = $user_info['user_name'];
            $order_info['user_phone'] = $user_info['user_phone'];
            # syf 更改 Time 2018-12-06
            // $order_info['company_name'] = $this->getEvent('ErpSupplier')->getSupplierDataField(['id' => $order_info['company_id']], 'supplier_name');
            $supplier_arr = $this->getEvent('ErpSupplier')->getOneSupplierData(['id' => $order_info['company_id']],'supplier_name,data_source');
            $order_info['company_name'] = $supplier_arr['supplier_name'];
            $order_info['data_source'] = dataSourceName($supplier_arr['data_source']);
        }

        return $order_info;
    }

    /**
     * 生成预存/预付申请单审批步骤
     * @param array $Recharge_order_info
     * @author xiaowen
     * @time 2017-05-09
     * @return bool $check_status
     */
    public function createWorkflow($Recharge_order_info = []){
        $data['status'] = false; //审批流程创建状态
        $data['check_order'] = 0; //是否复核订单状态
        $data['message'] = '';
        if(empty($Recharge_order_info['creater_name'])) $Recharge_order_info['creater_name'] = $this->getModel('Dealer')->where(['id' => intval($Recharge_order_info['creater'])])->getField('dealer_name');
        if($Recharge_order_info){
            $type = intval($Recharge_order_info['order_type']) == 1 ? '6':'7';
            $workflow_data = [
                'workflow_type' => $type, // 6 是预存 ， 7 预付
                'workflow_order_number' => $Recharge_order_info['order_number'],
                'workflow_order_id' => $Recharge_order_info['id'],
                'our_company_id' => $Recharge_order_info['our_company_id'],
                'creater' => $Recharge_order_info['creater_name'],
                'creater_id' => $Recharge_order_info['creater'],
            ];
            $work_status      = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);
            # 1 预存 2 预付
            //$workflow_step    = rechargeWorkflowStepPosition(intval($Recharge_order_info['order_type']));
            if ($Recharge_order_info['order_type'] == 1) {
                if ($Recharge_order_info['recharge_type'] == 13) {
                    $workflow_step = LabECOPurchaseSaleWorkflow(1);
                }else if($Recharge_order_info['recharge_type'] == 14){
                    $workflow_step = IdsWorkflow(6);

                } else {
                    $workflow_step = marketingPreStoreWorkflow();
                }
            } else {
                if ($Recharge_order_info['recharge_type'] == 23) {
                    $workflow_step = LabECOPurchaseSaleWorkflow(1);
                }else if($Recharge_order_info['recharge_type'] == 24){
                    $workflow_step = IdsWorkflow(7);

                } else {
                    $workflow_step = gylPrepayWorkflow($Recharge_order_info['recharge_type']);
                }
            }
            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $Recharge_order_info, $type);
            $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];
        }
        return $data;
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
     * 插入预存/预付申请单日志
     * @author xiaowen
     * @param $data
     * @return mixed
     */
    public function addRechargeOrderLog($data)
    {
        if ($data) {
            $data['create_time'] = currentTime();
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0;
            $data['operator_id'] = $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0;
            $status = $this->getModel('ErpRechargeOrderLog')->add($data);
        }
        return $status;
    }

    /**
     * 预付单、预付单作废
     * @author qianbin
     * @param $data
     * @return mixed
     */
    public function rollBackPrepayOrder($id)
    {
        $update_account  = ['status' => 1 , 'message' => ''];
        $update_order    = ['status' => 1 , 'message' => ''];
        $update_workflow = true;
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);
        if (empty($order_info) || $order_info['order_status'] == 2) {
            $result = [
                'status'  => 2,
                'message' => '该笔单据信息状态错误，无法作废！',
            ];
            return $result;
        }
        if($order_info['order_status'] == 1) {
            $result = [
                'status' => 2,
                'message' => '该笔单据状态为未审核，请使用取消操作！',
            ];
            return $result;
        }
        if(!empty(trim($order_info['from_order_number']))){
            $result = [
                'status' => 2,
                'message' => '该笔单据来源红冲操作，无法作废！',
            ];
            return $result;
        }

        if (getCacheLock('ErpRecharge/rollBackPrepayOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpRecharge/rollBackPrepayOrder', 1);

        # 添加日志
        $log_info_data = [
            'event' => '作废预付单-预付单：'.$order_info['order_type'],
            'key'   => $order_info['order_number'],
            'request' => '单据状态和财务操作状态：'.$order_info['status'].'==='.$order_info['finance_status'].'。单据信息：' . json_encode($order_info),
        ];
        log_write($log_info_data);
        # end
        M()->startTrans();
        switch (intval($order_info['order_status'])) {
            case 1:
                # 作废预付单
                //$update_order    = $this->updateErpRechargeOrder($order_info);
                break;
            case 3:
                # 作废预付单
                # 作废审批流
                $update_workflow = $this->updateWorkFlow($id,$order_info['order_type']);
                $update_order    = $this->updateErpRechargeOrder($order_info);
                break;
            case 4:
                # 作废预付单
                # 作废审批流
                $update_workflow = $this->updateWorkFlow($id,$order_info['order_type']);
                $update_order    = $this->updateErpRechargeOrder($order_info);
                break;
            case  10:
                $update_workflow = $this->updateWorkFlow($id,$order_info['order_type']);
                $update_order    = $this->updateErpRechargeOrder($order_info);
                $update_account  = $this->updateErpAccount($order_info);
                break;
            default:
                $update_order    = ['status' => 3 , 'message' => '单据状态错误，请刷新后重试！'];
                break;
        }
        if($update_order['status'] != 1){
            M()->rollback();
            cancelCacheLock('ErpRecharge/rollBackPrepayOrder');
            return ['status' => $update_order['status'] , 'message' => $update_order['message']];
        }
        if($update_account['status'] != 1){
            M()->rollback();
            cancelCacheLock('ErpRecharge/rollBackPrepayOrder');
            return ['status' => $update_account['status'] , 'message' => $update_account['message']];
        }
        if ($update_workflow && $update_account['status'] && $update_order['status']) {
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
        cancelCacheLock('ErpRecharge/rollBackPrepayOrder');
        return $result;


    }

    /**
     * 取消审批流
     * @author qianbin
     * @param $data
     * @return mixed
     */
    public function updateWorkFlow($id,$type){
        if(intval($id) < 0 ) return false;
        if($type == 1 ) {
            $type = 6;
        }else{
            $type = 7;
        }
        $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id,'workflow_type' => $type, 'status'=>['neq', 2]])->order('id desc')->find();
        if ($workflow) {
            $workflow['status'] = 2;
            $work_status = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
        } else {
            $work_status = true;
        }
        return $work_status;
    }

    /**
     * 更改账户余额
     * @author qianbin
     * @param $data
     * @return mixed
     */
    public function updateErpAccount($order_info){

        # 财务操作状态如果为未处理，则不需要回滚金额
        if($order_info['finance_status'] == 1){
            return ['status' => 1 ,'message' => ''];
        }
        $where = [
            'company_id'        => $order_info['company_id'],
            'our_company_id'    => $order_info['our_company_id'],
            'account_type'      => $order_info['order_type'],
        ];
        # 查询余额是否足够
        $account_balance = $this->getModel('ErpAccount')->field('id,account_balance')->where($where)->find();
        if(empty($account_balance) || ($account_balance['account_balance'] < $order_info['recharge_amount'])){
            return ['status' => 21 , 'message' => '该账户余额不足以作废该笔预付单，请检查余额！'];
        }
        # 更新余额
        $update_data = [
            'account_balance'   => $account_balance['account_balance'] - $order_info['recharge_amount'],
            'update_time'       => currentTime(),
        ];
        $update_status   = $this->getModel('ErpAccount')->where($where)->save($update_data);

        # 作废充值记录
        $where = [
            'account_id'    => $account_balance['id'] ,
            'object_number' => $order_info['order_number']
        ];
        $account_balance = $this->getModel('ErpAccountLog')->where($where)->save(['status' => 2]);

        # 添加日志
        $log_info_data = [
            'event' => '作废预付单-预付单：'.$order_info['order_type'],
            'key'   => $order_info['order_number'],
            'request' => '单据余额状态'.json_encode($account_balance).'更新的余额：'.json_encode($update_data).'更新余额状态和作废充值记录：'.$update_status.'=='.$account_balance,
        ];
        log_write($log_info_data);
        # end
        if($update_status && $account_balance ){
            $result = ['status' => 1 ,'message' => '修改成功！'];
        }else{
            $result = ['status' => 22 , 'message' => '资金修改失败！'];
        }
        return $result;
    }

    /*
     * ------------------------------------------
     * 作废预存预付单
     * Author：qianbin        Time：2018-02-06
     * ------------------------------------------
     */
    public function updateErpRechargeOrder($order_info)
    {
        if(count($order_info) < 0 ) return ['status' => 31 , 'message' => '参数错误，请重试！'];
        $data   = [
            'order_status'   => 2,
            'updater'        => $this->getUserInfo('id'),
            'update_time'    => currentTime(),
            'finance_status' => 1 ,
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($order_info['id'])], $data);
        //新增log
        $log    = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => $order_info['order_type'],
            'log_info'   => serialize($order_info),
            'log_type'   => 10,
        ];
        $log_status = $this->addRechargeOrderLog($log);

        # 添加日志
        $log_info_data = [
            'event' => '作废预付单-预付单：'.$order_info['order_type'],
            'key'   => $order_info['order_number'],
            'request' => '作废单据状态：' . $status.'添加预存预付-单据日志状态：'.$log_status,
        ];
        log_write($log_info_data);
        # end
        if($status && $log_status ){
            $result = ['status' => 1 ,'message' => '单据作废成功！'];
        }else{
            $result = ['status' => 32 , 'message' => '单据作废失败！'];
        }
        return $result;
    }
}
