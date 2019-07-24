<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
use Home\Lib\csvLib;

class ErpRechargeController extends BaseController
{

    /**
     * 预存申请单列表
     * @author guanyu
     * @time 2017-10-31
     */
    public function prestoreOrderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 1;
            $data = $this->getEvent('ErpRecharge')->RechargeOrderList($param);
            $this->echoJson($data);
        }

        //查询页面所需要的销售单相关状态入类型数据
        $data['RechargeOrderStatus'] = RechargeOrderStatus();
        $data['RechargeFinanceStatus'] = RechargeFinanceStatus();
        $data['regionList'] = provinceCityZone()['city'];
        //$access_node = $this->getUserAccessNode('ErpRecharge/RechargeOrderList');
        $access_node = $this->getUserAccessNode('ErpRecharge/prestoreOrderList');
        //print_r($per);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 新增预存申请单
     * @author guanyu
     * @time 2017-11-07
     */
    public function addPrestoreOrder()
    {
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpRecharge')->addPrestoreOrder($param);

            $this->echoJson($data);
        }
        $region['region_list'] = provinceCityZone()['city'];
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //-------------------------end----------------------------------------------------
        $data['prestore_type'] = PrestoreType();
        $data['today'] = date('Y-m-d');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 编辑预存申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function updatePrestoreOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpRecharge')->updatePrestoreOrder($id, $param);

            $this->echoJson($data);
        }

        //获取预存申请单详情
        //$field = 'o.*,u.user_name,c.company_name';
        $field = 'o.*';
        $data['order'] = $this->getEvent('ErpRecharge')->findOneRechargeOrder($id,$field);
        $data['order']['recharge_amount'] = getNum($data['order']['recharge_amount']);
        $region['region_list'] = provinceCityZone()['city'];
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //-------------------------end----------------------------------------------------
        $data['prestore_type'] = PrestoreType();
        $data['today'] = date('Y-m-d');
        $data['order']['creater_name'] = getDealer(['id'=>$data['order']['creater']])['dealer_name'];
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 获取一条预付单信息
     * @author guanyu
     * @time 2017-11-08
     */
    public function getOneRechargeOrderInfo(){
        $id = intval(I('param.id', 0));
        $data = $this->getEvent('ErpRecharge')->findRechargeOrder($id);
        # 查询用户余额
        $where = [
            'account_type'   => $data['order_type'],
            'our_company_id' => $data['our_company_id'],
            'company_id'     => $data['company_id'],
        ];
        $account_balance = $this->getModel('ErpAccount')->findAccount($where)['account_balance'];
        $data['account_balance'] = $account_balance <= 0 ? 0 : $account_balance;
        $data['account_balance'] = getNum($data['account_balance'] + $data['recharge_amount']) ;
        # 获取当前账套对应银行账号信息 qianbin 2018.08.08
        # order_type        充值订单类型  1、预存  2、预付
        # 对应银行信息：     收付类型: 1 收款、2 付款
        $data['bank']      = $this->getEvent('ErpBank')->getErpBankList($data['order_type']);
        $data['bank_num']  = count($data['bank']);
        $data['bank_json'] = json_encode($data['bank'],true);
        $this->echoJson($data);
    }

    /**
     * 审核预存申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function auditPrestoreOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->auditPrestoreOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 删除预存申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function delPrestoreOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->delPrestoreOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认预存申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function confirmPrestoreOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->confirmPrestoreOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 预付申请单列表
     * @author guanyu
     * @time 2017-10-31
     */
    public function prepayOrderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 2;
            $data = $this->getEvent('ErpRecharge')->RechargeOrderList($param);
            $this->echoJson($data);
        }

        //查询页面所需要的销售单相关状态入类型数据
        $data['RechargeOrderStatus'] = RechargeOrderStatus();
        $data['RechargeFinanceStatus'] = RechargeFinanceStatus();
        $data['regionList'] = provinceCityZone()['city'];
        //$access_node = $this->getUserAccessNode('ErpRecharge/RechargeOrderList');
        $access_node = $this->getUserAccessNode('ErpRecharge/prepayOrderList');
        //print_r($per);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 新增预付申请单
     * @author guanyu
     * @time 2017-11-07
     */
    public function addPrepayOrder()
    {
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpRecharge')->addPrepayOrder($param);

            $this->echoJson($data);
        }
        $region['region_list'] = provinceCityZone()['city'];
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //-------------------------end----------------------------------------------------
        $data['prepay_type'] = PrepayType();
        $data['today'] = date('Y-m-d');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 编辑预付申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function updatePrepayOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpRecharge')->updatePrepayOrder($id, $param);

            $this->echoJson($data);
        }

        //获取预付申请单详情
        //$field = 'o.*,u.user_name,c.company_name';
        $field = 'o.*';
        $data['order'] = $this->getEvent('ErpRecharge')->findOneRechargeOrder($id,$field);
        $data['order']['recharge_amount'] = getNum($data['order']['recharge_amount']);
        $region['region_list'] = provinceCityZone()['city'];
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //-------------------------end----------------------------------------------------
        $data['prepay_type'] = PrepayType();
        $data['today'] = date('Y-m-d');
        $data['order']['creater_name'] = getDealer(['id'=>$data['order']['creater']])['dealer_name'];
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 审核预付申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function auditPrepayOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->auditPrepayOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 删除预付申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function delPrepayOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->delPrepayOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认预付申请单
     * @author guanyu
     * @time 2017-11-08
     */
    public function confirmPrepayOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->confirmPrepayOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 导出预存申请单
     * @author guanyu
     * @time 2017-11-13
     */
    public function exportPrestoreOrderData(){
        $param = I('param.');
        $param['type'] = 1;
        $param['export'] = 1;
        $data = $this->getEvent('ErpRecharge')->RechargeOrderList($param);
        $arr  = [];
        foreach ($data['data'] as $k => $v) {
            $arr[$k]['id'] = $v['id'];
            $arr[$k]['add_order_time'] = $v['add_order_time'];
            $arr[$k]['order_number'] = $v['order_number'];
            $arr[$k]['region_name'] = $v['region_name'];
            $arr[$k]['user_name'] = $v['user_name'];
            $arr[$k]['user_phone'] = "\t".$v['user_phone'];
            $arr[$k]['company_name'] = $v['company_name'];
            $arr[$k]['recharge_amount'] = "".$v['recharge_amount'];
            $arr[$k]['recharge_type'] = $v['recharge_type'];
            $arr[$k]['order_status'] = $v['order_status_font'];
            $arr[$k]['finance_status'] = $v['finance_status_font'];
            $arr[$k]['creater_name'] = $v['creater_name'];
            $arr[$k]['create_time'] = $v['create_time'];
            $arr[$k]['remark'] = $v['remark'];
        }
        $header = ['序号','业务日期','预存申请单号','城市','客户','手机','公司','预存金额（元）','预存款类型','订单状态','收款状态','创建人','创建时间','备注'];
        array_unshift($arr,  $header);
        create_xls($arr,$filename='预存申请单'.currentTime().'.xls');
    }

    /**
     * 导出预付申请单
     * @author guanyu
     * @time 2017-11-13
     */
    public function exportPrepayOrderData(){
        $param = I('param.');
        $param['type'] = 2;
        $param['export'] = 1;
        $data = $this->getEvent('ErpRecharge')->RechargeOrderList($param);
        $arr  = [];
        foreach ($data['data'] as $k => $v) {
            $arr[$k]['id'] = $v['id'];
            $arr[$k]['add_order_time'] = $v['add_order_time'];
            $arr[$k]['apply_finance_time'] = $v['apply_finance_time'];
            $arr[$k]['pay_time'] = $v['pay_time'];
            $arr[$k]['order_number'] = $v['order_number'];
            $arr[$k]['region_name'] = $v['region_name'];
            $arr[$k]['user_name'] = $v['user_name'];
            $arr[$k]['user_phone'] = "\t".$v['user_phone'];
            $arr[$k]['company_name'] = $v['company_name'];
            $arr[$k]['recharge_amount'] = "".$v['recharge_amount'];
            $arr[$k]['recharge_type'] = $v['recharge_type'];
            $arr[$k]['order_status'] = $v['order_status_font'];
            $arr[$k]['finance_status'] = $v['finance_status_font'];
            $arr[$k]['creater_name'] = $v['creater_name'];
            $arr[$k]['create_time'] = $v['create_time'];
            $arr[$k]['remark'] = $v['remark'];
        }
        $header = ['序号','业务日期','申请付款日期','付款时间','预付申请单号','城市','客户','手机','公司','预付金额（元）','预付款类型','订单状态','付款状态','创建人','创建时间','备注'];
        array_unshift($arr,  $header);
        create_xls($arr,$filename='预付申请单'.currentTime().'.xls');
    }

    /**
     * 预存单回滚
     * @author qianbin
     * @time 2018-02-06
     */
    public function rollBackPrepayOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpRecharge')->rollBackPrepayOrder($id);
            $this->echoJson($data);
        }
    }
}
