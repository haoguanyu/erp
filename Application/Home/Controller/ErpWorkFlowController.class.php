<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpWorkflowController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP审批流逻辑层
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------

    // +----------------------------------
    // |Facilitator:审批流-待办流程列表
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function workFlowList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpWorkFlow')->workFlowList($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:审批流-待办流程详情
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------    
    public function workFlowDetail()
    {
        $id   = intval(I('param.id', 0));
        $type = intval(I('param.type', 0));
        $data = $this->getEvent('ErpWorkFlow')->workFlowDetail($id);
        $data['our_company_name'] = getOurCompany()[$data['our_company_id']];
        $this->assign("data", $data);
        $this->assign("id",$id);
        $this->assign("type",$type);
        $tpl = [
            3 => 'workflowAllocationOrder',
            4 => 'workFlowReturnDetail',
            5 => 'workFlowReturnDetail',
            6 => 'workFlowRechargeDetail',
            7 => 'workFlowRechargeDetail'
        ];
        $this->display($tpl[$type]);
    }

    // +----------------------------------
    // |Facilitator:审批流-待办流程审核操作
    // +----------------------------------
    // |Author:qianbin Time:2017.05.06
    // +----------------------------------    
    public function workFlowUpdateStatus()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data  = $this->getEvent('ErpWorkFlow')->workFlowUpdateStatus($param);
            $this->echoJson($data);
        }
    }

    
    // +----------------------------------
    // |Facilitator:审批流-我的流程列表
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function myWorkFlowList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpWorkFlow')->myWorkFlowList($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:审批流-我的流程详情
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------    
    public function myWorkFlowDetail()
    {
        $id   = intval(I('param.id', 0));
        $type = intval(I('param.type', 0));
        $data = $this->getEvent('ErpWorkFlow')->myWorkFlowDetail($id);
        $data['our_company_name'] = $this->getModel('Clients')->getClientsinfo(['id'=>$data['our_company_id']])['company_name'];
        $this->assign("data", $data);
        $this->assign("id",$id);
        $tpl = [
            3 => 'myWorkflowAllocationOrder',
            4 => 'myWorkFlowReturnDetail',
            5 => 'myWorkFlowReturnDetail',
            6 => 'myWorkFlowRechargeDetail',
            7 => 'myWorkFlowRechargeDetail'
        ];
        $this->display($tpl[$type]);
    }

    // +----------------------------------
    // |Facilitator:审批流-审批历史列表
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function historyWorkFlowList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpWorkFlow')->historyWorkFlowList($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    /*
     * ----------------------------------------
     * [销售单|采购单|调拨单]-展示审批进度
     * Author：qianbin       Time：2017-05-11
     * ----------------------------------------
     */
    public function orderWorkflow(){
        $data['id']   = intval(I('param.id', 0));
        $data['type'] = intval(I('param.type', 0));
        $result       = $this->getEvent('ErpWorkFlow')->orderWorkflow($data);
        $this->assign('data', $result);

        $tpl = [
            1 => 'ErpSale/saleOrderWorkflow',
            //2 => 'ErpPurchase/purchaseOrderWorkflow',
            //3 => 'ErpAllocation/allocationOrderWorkflow',
        ];
        $this->display($tpl[1]);
    }

    /*
     * -------------------------------------------
     * [销售单|采购单|调拨单]  测试审批流推送Emalil
     * Author：qianbin       Time：2017-06-10
     * -------------------------------------------
     */
    public function sendTestJguangMessage(){

        sendEmail('ERP审批工作流消息','您有一条新的流程需要审批，请及时跟进！','zhengqianbin@51zhaoyou.com');
        sendJpushMessage('142', '油沃客', 5, 'ERP审批工作流消息', '您有一条新的流程需要审批，请及时跟进！', '', '297', 1);
        echo 'success';exit;
    }

    /**
     * 工作流审批人列表
     * @author xiaowen
     */
    public function workflowPositionList(){
        $data['regionList'] = $allCity = provinceCityZone()['city'];
        if(IS_AJAX){
            $param = $_REQUEST;

            $where = [];
            if(trim($param['dealer_name'])){
                $where['dealer_name'] = $param['dealer_name'];
            }
            if(trim($param['region'])){
                $where['region'] = $param['region'];
            }
            //print_r($where);
            $where['status'] = 1;
            $data = D('ErpWorkflowPosition')->WorkflowPositionList($where, true, $_REQUEST['start'], $_REQUEST['length']);
            if(empty($data['data'])){
                $data['data'] = [];

            }else{
                $statusArr = [
                    1 => '启用',
                    2 => '禁用',
                ];
                foreach($data['data'] as $key=>$value){
                    $data['data'][$key]['region_name'] = $allCity[$value['region']] ? $allCity[$value['region']] : '--';
                    $data['data'][$key]['status_str'] = $statusArr[$value['status']];
                }
            }
            $data['recordsFiltered'] = $data['recordsTotal'];
            $data['draw'] = $_REQUEST['draw'];
            $this->echoJson($data);
        }
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 新增工作流审批人
     * @author xiaowen
     */
    public function addWorkflowPosition(){
        $data['regionList'] = $allCity = provinceCityZone()['city'];
//        $data['positionList'] = $allPosition= [
//            //'GeneralManager_0' => '采购中心总经理',
//            'PurchaseManager_' => '地区采购负责人',
//            //'TradingCenterManager_0' => '交易中心总经理',
//            'ExchangeManager_' => '地区交易负责人',
//            'AllocationManager_' => '地区调拨负责人',
//            'FoPurchaseManager_' => '地区调拨采购负责人',
//            //'FinanceManager_0' => '财务经理',
//            //'MiningSalesManager_0' => '采销中心总经理',
//            'AreaManager_'      => '地区分公司经理',
//            'StoreAreaManager_' => '地区仓管负责人',
//            'PreStoreManager_'  => '地区预存负责人',
//            'PrePayManager_'    => '地区预付负责人'
//        ];
        $data['positionList'] = $allPosition = workflowPositionConfig();
        if(IS_AJAX){
            $param = I('post.');
            $data = [];
            if(trim($param['dealer_name'])){
                $data['dealer_name'] = trim($param['dealer_name']);
            }
            if(trim($param['dealer_id'])){
                $data['dealer_id'] = trim($param['dealer_id']);
            }
            if(trim($param['region'])){
                $data['region'] = intval(trim($param['region']));
            }else{
                $data['region'] = 0;
            }
            if(trim($param['position_name'])){
                $data['position_name'] = trim($param['position_name']);
            }
            $data['position_name'] = $allPosition[trim($param['position_name'])]['name'];//$data['position_name'];
            //判断审批职位是否分地区，如果是分地区，则职位名称前缀要加上地区名称，职位code后缀要加上地区ID
            if($allPosition[trim($param['position_name'])]['area'] == 1){
                $data['position_name'] = $allCity[$data['region']] . $data['position_name'];
            }
            $data['position_code'] = trim($param['position_name']) . $data['region'];
            if($this->getModel('ErpWorkflowPosition')->where(['position_code' => trim($data['position_code'])])->find()){
                $this->echoJson(['status' => 2,'message' => '审批职位已存在，请检查城市和审批人！']);
            }


            $data['status'] = 1;
            $data['create_time'] = currentTime();
            $status = $this->getModel('ErpWorkflowPosition')->addPosition($data);
            $result['status'] = $status ? 1 : 0;
            $result['message'] = $result['status'] ? '操作成功' : '操作失败';

            $this->echoJson($result);
        }

        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 新增工作流审批人
     * @author xiaowen
     */
    public function updateWorkflowPosition(){

        $id = intval(I('param.id', 0));
        $data['info'] = $this->getModel('ErpWorkflowPosition')->findPosition(['id'=>$id]);
        $data['regionList'] = $allCity = provinceCityZone()['city'];
//        $data['positionList'] = $allPosition= [
//            //'GeneralManager_0' => '采购中心总经理',
//            'PurchaseManager_' => '地区采购负责人',
//            //'TradingCenterManager_0' => '交易中心总经理',
//            'ExchangeManager_' => '地区交易负责人',
//            'AllocationManager_' => '地区调拨负责人',
//            'FoPurchaseManager_' => '地区调拨采购负责人',
//            //'FinanceManager_0' => '财务经理',
//            //'MiningSalesManager_0' => '采销中心总经理',
//            'AreaManager_'      => '地区分公司经理',
//            'StoreAreaManager_' => '地区仓管负责人',
//            'PreStoreManager_'  => '地区预存负责人',
//            'PrePayManager_'    => '地区预付负责人'
//        ];
        $data['positionList'] = $allPosition = workflowPositionConfig();
        if(IS_AJAX){
            $param = I('post.');
            $data = [];
            if(trim($param['dealer_name'])){
                $data['dealer_name'] = trim($param['dealer_name']);
            }
            if(trim($param['dealer_id'])){
                $data['dealer_id'] = trim($param['dealer_id']);
            }
            if(trim($param['region'])){
                $data['region'] = trim($param['region']);
            }else{
                $data['region'] = 0;
            }
//            if($param['no_region'] == 0){
//                if(trim($param['position_name'])){
//                    $data['position_name'] = trim($param['position_name']);
//                }
//                if(strpos(array_search($data['position_name'], $allPosition), '0') === false){
//                    $data['position_code'] = array_search($data['position_name'], $allPosition) . $data['region'];
//                }
//                $data['position_name'] = $allCity[$data['region']] . $data['position_name'];
//            }
            $data['position_name'] = $allPosition[trim($param['position_name'])]['name'];
            //判断审批职位是否分地区，如果是分地区，则职位名称前缀要加上地区名称，职位code后缀要加上地区ID
            if($allPosition[trim($param['position_name'])]['area'] == 1){
                $data['position_name'] = $allCity[$data['region']] . $data['position_name'];
            }
            $data['position_code'] = trim($param['position_name']) . $data['region'];
            if($this->getModel('ErpWorkflowPosition')->where(['position_code' => trim($data['position_code']), 'id'=>['neq', $id]])->find()){
                $this->echoJson(['status' => 2,'message' => '审批职位已存在，请检查城市和审批人！']);
            }
            $data['update_time'] = currentTime();

            $status = $this->getModel('ErpWorkflowPosition')->savePosition(['id'=>$id], $data);
            $result['status'] = $status ? 1 : 0;
            $result['message'] = $result['status'] ? '操作成功' : '操作失败';

            $this->echoJson($result);
        }
        $tmp = explode('_', $data['info']['position_code']);
        $data['info']['position_code'] = $tmp[0] . '_';
        $this->assign('data', $data);
        $this->display();
    }

    public function delWorkflowPosition(){
        $id = intval(I('param.id', 0));
        $status = $this->getModel('ErpWorkflowPosition')->where(['id'=>$id])->delete();
        $result['status'] = $status ? true : false;
        $result['message'] = $result['status'] ? '删除成功' : '删除失败';
        $this->echoJson($result);
    }
}