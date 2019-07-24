<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
use Home\Lib\csvLib;
class ErpKpiController extends BaseController
{

    /**
     * 添加岗位基础资料
     * @author qianbin
     * @time 2017-11-28
     */
    public function addKpiBase()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;

            $data = $this->getEvent('ErpKpi')->addKpiBase($param);
            $this->echoJson($data);
        }
        $data['dealer']       = getDealerList('id,dealer_name');
        $data['is_regular']   = kpiRegularStatus();
        $data['regionList']   = provinceCityZone()['city'];
        $this->assign('data', $data);
        $this->display();
    }

     /**
     * 编辑岗位基础资料
     * @author qianbin
     * @time 2017-11-28
     */
    public function updateKpiBase()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data  = $this->getEvent('ErpKpi')->updateKpiBase($param);
            $this->echoJson($data);
        }
        $id         = intval(I('param.id', 0));
        $data['data']         = $this->getModel('ErpKpiBase')->findKpiBase(['id' => intval($id)]);
        $data['data']['post_base_num'] = getNum($data['data']['post_base_num']);
        $data['data']['new_mall_num']  = getNum($data['data']['new_mall_num']);
        $data['data']['old_mall_num']  = getNum($data['data']['old_mall_num']);
        $data['dealer']       = getDealerList('id,dealer_name');
        $data['regionList']   = provinceCityZone()['city'];
        $data['is_regular']   = kpiRegularStatus();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 更改岗位基础资料状态
     * @author zhegnqianbin
     * @time 2017-11-28
     */
    public function cancelErpkpi()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data  = $this->getEvent('ErpKpi')->cancelErpkpi($param);
            $this->echoJson($data);
        }
    }

    /**
     * 岗位基础资料列表
     * @author qianbin
     * @time 2017-11-28
     */
    public function kpiBaseList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;

            $data = $this->getEvent('ErpKpi')->kpiBaseList($param);
            $this->echoJson($data);
        }
        $data['regionList'] = provinceCityZone()['city'];
        $this->assign('data', $data);
        $this->display();
    }


    /**
     * 添加计提方案
     * @author qianbin
     * @time 2017-11-28
     */
    public function addKpiPlan()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpKpi')->addKpiPlan($param);
            $this->echoJson($data);
        }


        $this->assign('data', $data);
        $this->display();
    }

     /**
     * 编辑计提方案
     * @author qianbin
     * @time 2017-11-28
     */
    public function updateKpiPlan()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data  = $this->getEvent('ErpKpi')->updateKpiPlan($param);
            $this->echoJson($data);
        }
        $id             = intval(I('param.id', 0));
        $data['data']   = $this->getModel('ErpKpiPlan')->findKpiPlan(['id' => intval($id)]);
        $data['data']['new_terminal_commission']        = getNum($data['data']['new_terminal_commission']);
        $data['data']['new_trade_commission']           = getNum($data['data']['new_trade_commission']);
        $data['data']['old_terminal_commission']        = getNum($data['data']['old_terminal_commission']);
        $data['data']['old_trade_commission']           = getNum($data['data']['old_trade_commission']);
        $data['data']['activate_customer_commission']   = getNum($data['data']['activate_customer_commission']);
        $this->assign('data', $data);
        $this->display();
    }
    /**
     * 计提方案列表
     * @author qianbin
     * @time 2017-11-28
     */
    public function kpiPlanList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpKpi')->kpiPlanList($param);
            $this->echoJson($data);
        }

        $this->assign('data', $data);
        $this->display();
    }

    /**
     * KPI报表(批发)
     * @author xiaowen
     * @time 2017-11-28
     */
    public function kpiDataList()
    {
        $param = $_REQUEST;
        $param['month'] = $param['month'] ?  $param['month'] : date('Y-m');//'2017-11'
        $param['plan_id'] = intval($param['plan_id']);
        if (IS_AJAX) {
            if(!$param['plan_id']){
                $this->echoJson(['data'=>[],'recordsFiltered'=>0, 'recordsTotal'=>0]);
            }
            $data = $this->getEvent('ErpKpi')->kpiDataList($param);
            $this->echoJson($data);
        }
        //$data = $this->getEvent('ErpKpi')->kpiDataList($param);
        $data['plan_list']      = $this->getEvent('ErpKpi')->getAllKpiPlan();
        $data['plan_list_json'] = json_encode($data['plan_list']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 验证本月成交交易员是否设置了基础量
     * @author guanyu
     * @time 2017-12-04
     */
    public function getNoBaseInfoUser()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data  = $this->getEvent('ErpKpi')->getNoBaseInfoUser($param);
            $this->echoJson($data);
        }
    }

    /**
     * 导出采购单
     * @author senpei
     * @time 2017-06-01
     */
    public function exportKpiData(){
        $param = I('param.');
        $param['export'] = 1;
        $data = $this->getEvent('ErpKpi')->kpiDataList($param);
        $arr = [];
        if($data){
            foreach($data['data'] as $k=>$v){
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['dealer_name'] = $v['dealer_name'];
                $arr[$k]['province_name'] = $v['province_name'];
                $arr[$k]['region_name'] = $v['region_name'];
                $arr[$k]['is_regular'] = $v['is_regular'];
                $arr[$k]['total_bonus'] = $v['total_bonus'] ? $v['total_bonus'] : '0';
                $arr[$k]['post_base_num'] = $v['post_base_num'] ? $v['post_base_num'] : '0';
                $arr[$k]['newMallCustomer_total'] = $v['newMallCustomer_total'] ? $v['newMallCustomer_total'] : '0';
                $arr[$k]['newMallCustomer_Terminal'] = $v['newMallCustomer_Terminal'] ? $v['newMallCustomer_Terminal'] : '0';
                $arr[$k]['newMallCustomer_Trade'] = $v['newMallCustomer_Trade'] ? $v['newMallCustomer_Trade'] : '0';
                $arr[$k]['new_mall_num'] = $v['new_mall_num'] ? $v['new_mall_num'] : '0';
                $arr[$k]['old_mall_num'] = $v['old_mall_num'] ? $v['old_mall_num'] : '0';
                $arr[$k]['newOrder'] = $v['newOrder'] ? $v['newOrder'] : '0';
                $arr[$k]['newOrder_Terminal'] = $v['newOrder_Terminal'] ? $v['newOrder_Terminal'] : '0';
                $arr[$k]['newOrder_Trade'] = $v['newOrder_Trade'] ? $v['newOrder_Trade'] : '0';
                $arr[$k]['oldOrderNum'] = $v['oldOrderNum'] ? $v['oldOrderNum'] : '0';
                $arr[$k]['inThreeOrder_Terminal'] = $v['inThreeOrder_Terminal'] ? $v['inThreeOrder_Terminal'] : '0';
                $arr[$k]['inThreeOrder_Trade'] = $v['inThreeOrder_Trade'] ? $v['inThreeOrder_Trade'] : '0';
                $arr[$k]['beforeThreeOrder_Terminal'] = $v['beforeThreeOrder_Terminal'] ? $v['beforeThreeOrder_Terminal'] : '0';
                $arr[$k]['beforeThreeOrder_Trade'] = $v['beforeThreeOrder_Trade'] ? $v['beforeThreeOrder_Trade'] : '0';
                $arr[$k]['active_users'] = $v['active_users'] ? $v['active_users'] : '0';
                $arr[$k]['active_bonus'] = $v['active_bonus'] ? $v['active_bonus'] : '0';
            }
        }

        $header = [
            'ID','姓名','省份','城市','是否转正','绩效奖金','岗位基础量','新客户数','新终端客户数','新贸易客户数','新客户商城成交基础量',
            '老客户商城成交基础量','新客户商城成交总量','新终端客户商城成交量','新贸易客户商城成交量','老客户商城成交总量',
            '3个月内老终端客户本月商城成交量','3个月内老贸易客户本月商城成交量','3个月以上老终端客户本月商城成交量',
            '3个月以上老贸易客户本月商城成交量','激活客户','激活客户数奖励',
        ];
        array_unshift($arr,  $header);
        $file_name = '采销中心KPI'.currentTime().'.xls';
        create_xls($arr, $filename=$file_name);
    }
}
