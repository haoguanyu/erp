<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpWorkFlowEvent extends BaseController
{

    /*
     * ----------------------------------------
     * ERP审批流逻辑处理层
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */

    /*
     * ----------------------------------------
     * ERP审批流-待办流程
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    public function workFlowList($param = [])
    {
        $where['status']      = '1';
        $where['assignor_id'] = session('erp_adminInfo')['id'];
        return $this->workFlowDataList($where,$param);
    }

    /*
     * ----------------------------------------
     * ERP审批流-待办流程详情页
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    public function workFlowDetail($id = '')
    {
        if ($id <= 0) return [];
        $data = $this->getModel('ErpWorkflow')->findErpWorkflow(['id' => $id]);
        if (empty($data)) return [];
        $where['id']           = $data['workflow_order_id'];
        # $where['order_status'] = 3;
        $result = $this->workFlowDataDetail(intval($id),$data,$where);
        return $result;
    }

    /*
     * ----------------------------------------
     * 待办流程详情页-审核操作
     * Author：qianbin       Time：2017-05-06
     * ----------------------------------------
     */
    public function workFlowUpdateStatus($param = [])
    {
        if (getCacheLock('ErpWorkFlow/workFlowUpdateStatus')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpWorkFlow/workFlowUpdateStatus', 1);
        if (intval($param['id']) <= 0) {
            return ['status' => 2,'message' => '对不起，网络异常，请稍后刷新重试！'];
        }
        switch (intval($param['status'])) {
            case 1:
                # 审核通过
                $result = $this->workFlowUpdateStatusYes($param);
                cancelCacheLock('ErpWorkFlow/workFlowUpdateStatus');
                return $result;
                break;
            case 2:
                # 驳回
                $result = $this->workFlowUpdateStatusNo($param);
                cancelCacheLock('ErpWorkFlow/workFlowUpdateStatus');
                return $result;
                break;
            default:
                return ['status' => 3, 'message' => '对不起，网络异常，请稍后刷新重试！'];
                break;
        }
    }

    /*
     * ----------------------------------------
     * 待办审核通过
     * Author:qianbin Time:2017.05.06
     * ----------------------------------------
     */
    protected function workFlowUpdateStatusYes($param = [])
    {
        # 基础参数验证
        if (!isset($param['workflow_type']) || intval($param['workflow_type']) <= 0) {
            return ['status' => 4, 'message' => '对不起，网络异常，请稍后刷新重试！'];
        }
        # 业务逻辑验证
        $data = $this->getModel('ErpWorkflow')->findErpWorkflow(['id' => intval($param['id'])]);
        if (!$data) {
            return ['status' => 4, 'message' => '对不起，网络异常，请稍后刷新重试！'];
        }
        #------------< 判断订单状态是否为已审核 qianbin 2017.08.29>-----------------------------------
        $order_info = $this->checkOrderStatus($data);
        if(intval($order_info['status']) != 1){
            return ['status' => $order_info['status'], 'message' => $order_info['message']];
        }
        //edit xiaowen 验证当前审批人是否为当前操作人 2017-6-14
        if ($data['assignor_id'] != $this->getUserInfo('id')) {
            return ['status' => 5, 'message' => '对不起，您不是当前审批人，无权操作！'];
        }
        switch (intval($data['status'])) {
            case 1:break;
            case 2:
                # 待办事项无效 | 审批步骤不会在第一步
                return ['status' => 6, 'message' => '对不起，您选择的待办事项已被驳回，无法再次审核通过，请刷新后查询该笔事项信息！'];
                break;
            case 3:
                # 待办事项已完结
                if (intval($data['current_step_num']) == intval($data['total_step_num'])) {
                    return ['status' => 7, 'message' => '对不起，您选择的待办事项已被审核，无法再次审核，请刷新后查询该笔事项信息！'];
                } else {
                    return ['status' => 8, 'message' => '对不起，该笔待办事项异常，请联系管理员！'];
                }
                break;
            default:
                # 数据异常
                return ['status' => 9, 'message' => '对不起，您选择的待办事项异常，请联系管理员！'];
                break;
        }
        # 业务处理 | 审核通过 | 多次审核通过 | 如果最后一次审核则待办事项更新完结
        M()->startTrans();
        $reult_order = 1;
        switch (intval($data['current_step_num'])) {
            case intval($data['total_step_num']):
                $update_workflow = [
                    'status' => 3
                ];
                $send_jiguang_user    = $data['creater_id'];
                $send_jiguang_title   = '审核流程 - 通过提醒';
                $send_jiguang_content = '您的'.workflowOrderType($data['workflow_type']).'号：'.$data['workflow_order_number'].'，已审批通过，请及时进行处理！';
                $reult_order = $this->updateErpWorkFlowOrderStatus(intval($data['workflow_type']), intval($data['workflow_order_id']), 4);
                break;
            default:
                $workflow_step   = $this->getModel('ErpWorkflowStep')->findWorkFlowStep(['workflow_id' => intval($data['id']),'step_num' => $data['current_step_num']+1],'assignor_id,assignor');
                $update_workflow = [
                    'current_step_num' => $data['current_step_num'] + 1,
                    'assignor_id'      => intval($workflow_step['assignor_id']),
                    'assignor'         => !empty(trim($workflow_step['assignor'])) ? trim($workflow_step['assignor']) : ''
                ];
                $send_jiguang_title     = '审核流程 - 待审批提醒';
                $send_jiguang_user      = intval($update_workflow['assignor_id']);
                $send_jiguang_content   = workflowOrderType($data['workflow_type']).'号：'.$data['workflow_order_number'].'，需要审批，请及时跟进！';
                break;
        }
        $send_jiguang_email     = M('Dealer')->where(['id' => intval($send_jiguang_user)])->getField('dealer_email');
        $add_workflow_log = [
            'workflow_id'       => intval($data['id']),
            'step_num'          => intval($data['current_step_num']),
            'operating_state'   => 1,
            'remark'            => trim(htmlspecialchars($param['remark'])),
            'create_time'       => date('Y-m-d H:i:s',time()),
            'order_source'      => '1',
            'perator'           => trim($this->getUserInfo('dealer_name')),
            'perator_id'        => intval($this->getUserInfo('id'))
        ];
        $result_workflow = $this->getModel('ErpWorkflow')->saveErpWorkflow(['id' => intval($data['id'])], $update_workflow);
        $r_workflow_log  = $this->getModel('ErpWorkflowLog')->addErpWorkFlowLog($add_workflow_log);
        if ($reult_order && $result_workflow && $r_workflow_log) {
            M()->commit();
            # 生成推送
            sendJpushMessage($send_jiguang_user, '油沃客', 5,$send_jiguang_title, $send_jiguang_content, '', intval($data['id']), 1);
            if (!in_array($send_jiguang_email, emailBlackList())) {
                sendEmailByJava($send_jiguang_title,$send_jiguang_content,$send_jiguang_email);
            }
            return ['status' => 1, 'message' => '恭喜您，待办事项审核成功！'];
        } else {
            M()->rollback();
            return ['status' => 11, 'message' => '对不起，网络异常，请刷新后重试！'];
        }
    }

    /*
     * ----------------------------------------
     * 代办审核驳回
     * Author:qianbin Time:2017.05.06
     * ----------------------------------------
     */
    protected function workFlowUpdateStatusNo($param = [])
    {
        # 基础参数验证
        if (!isset($param['remark']) || empty(trim($param['remark']))) {
            return ['status' => 4, 'message' => '对不起，驳回备注必填！'];
        }
        if (!isset($param['workflow_type']) || intval($param['workflow_type']) <= 0) {
            return ['status' => 5, 'message' => '对不起，网络异常，请稍后刷新重试！'];
        }
        # 业务逻辑验证
        $data = $this->getModel('ErpWorkflow')->findErpWorkflow(['id' => intval($param['id'])]);
        if (!$data) {
            return ['status' => 6, 'message' => '对不起，网络异常，请稍后刷新重试！'];
        }
        #------------< 判断订单状态是否为已审核 qianbin 2017.08.29>-----------------------------------
        $order_info = $this->checkOrderStatus($data);
        if(intval($order_info['status']) != 1){
            return ['status' => $order_info['status'], 'message' => $order_info['message']];
        }
        //edit xiaowen 验证当前审批人是否为当前操作人 2017-6-14
        if ($data['assignor_id'] != $this->getUserInfo('id')) {
            return ['status' => 7, 'message' => '对不起，您不是当前审批人，无权操作！'];
        }
        switch (intval($data['status'])) {
            case 1:break;
            case 2:
                # 待办事项无效 | 审批步骤不会在第一步
                if(intval($data['current_step_num']) == 1){
                    return ['status' => 6, 'message' => '对不起，您选择的待办事项已被驳回，无法再次驳回，请刷新后查询该笔事项信息！'];
                }else{
                    return ['status' => 7, 'message' => '对不起，该笔待办事项异常，请联系管理员！'];
                }
                break;
            case 3:
                # 待办事项已完结
                return ['status' => 8, 'message' => '对不起，您选择的待办事项已完结，无法再次驳回，请刷新后查询该笔事项信息！'];
                break;
            default:
                # 数据异常
                return ['status' => 9, 'message' => '对不起，您选择的待办事项异常，请联系管理员！'];
                break;
        }
        # 业务处理 | 驳回 | 多次驳回 | 如果第一次直接驳回则待办事项更新无效
        M()->startTrans();
        $reult_order = 1;
        switch (intval($data['current_step_num'])) {
            case 1:
                $update_workflow = [
                    'status' => 2
                ];
                $send_jiguang_user    = $data['creater_id'];
                $send_jiguang_title   = '审核流程 - 驳回提醒';
                $send_jiguang_content = '您的'.workflowOrderType($data['workflow_type']).'号：'.$data['workflow_order_number'].'，已被驳回，请留意！';
                $reult_order         = $this->updateErpWorkFlowOrderStatus(intval($data['workflow_type']), intval($data['workflow_order_id']), 1);
                break;
            default:
                $workflow_step   = $this->getModel('ErpWorkflowStep')->findWorkFlowStep(['workflow_id' => intval($data['id']),'step_num' => $data['current_step_num']-1],'assignor_id,assignor');
                $update_workflow = [
                    'current_step_num' => $data['current_step_num'] - 1,
                    'assignor_id'      => intval($workflow_step['assignor_id']),
                    'assignor'         => !empty(trim($workflow_step['assignor'])) ? trim($workflow_step['assignor']) : ''
                ];
                $send_jiguang_user    = $update_workflow['assignor_id'];
                $send_jiguang_title   = '审核流程 - 待审批提醒';
                $send_jiguang_content = workflowOrderType($data['workflow_type']).'号：'.$data['workflow_order_number'].'，需要审批，请及时跟进！';
                break;
        }
        $send_jiguang_email     = M('Dealer')->where(['id' => intval($send_jiguang_user)])->getField('dealer_email');
        $add_workflow_log = [
            'workflow_id'       => intval($data['id']),
            'step_num'          => intval($data['current_step_num']),
            'operating_state'   => 2,
            'remark'            => trim(htmlspecialchars($param['remark'])),
            'create_time'       => date('Y-m-d H:i:s',time()),
            'order_source'      => '1',
            'perator'           => trim($this->getUserInfo('dealer_name')),
            'perator_id'        => intval($this->getUserInfo('id'))
        ];
        $result_workflow = $this->getModel('ErpWorkflow')->saveErpWorkflow(['id' => intval($data['id'])], $update_workflow);
        $r_workflow_log  = $this->getModel('ErpWorkflowLog')->addErpWorkFlowLog($add_workflow_log);
        if ($reult_order && $result_workflow && $r_workflow_log) {
            M()->commit();
            # 生成推送
            sendJpushMessage($send_jiguang_user, '油沃客', 5, $send_jiguang_title,$send_jiguang_content, '', intval($data['id']), 1);
            if (!in_array($send_jiguang_email, emailBlackList())) {
                sendEmailByJava($send_jiguang_title,$send_jiguang_content,$send_jiguang_email);
            }
            return ['status' => 1, 'message' => '恭喜您，待办事项驳回成功！'];
        } else {
            M()->rollback();
            return ['status' => 11, 'message' => '对不起，网络异常，请刷新后重试！'];
        }
    }

    /*
     * ----------------------------------------
     * ERP审批流-我的流程
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    public function myWorkFlowList($param = [])
    {
        # $where['status']       = ['in',[1,2]];
        $where['creater_id']   = session('erp_adminInfo')['id'];
        return $this->workFlowDataList($where,$param);
    }

    /*
     * ----------------------------------------
     * ERP审批流-我的流程详情页
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    public function myWorkFlowDetail($id = 0)
    {
        if ($id <= 0)    return [];
        $data = $this->getModel('ErpWorkflow')->findErpWorkflow(['id' => $id]);
        if(empty($data)) return [];
        $where['id'] = $data['workflow_order_id'];
        $result = $this->workFlowDataDetail(intval($id),$data,$where);
        return $result;
    }

    /*
     * ----------------------------------------
     * ERP审批流-审批历史
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    public function historyWorkFlowList($param = [])
    {
        # 郭大伟可以看到所有历史审批 包括正在审批的单据
        # qianbin
        # 2017.08.10
        $where_log = [];
        $where     = [];
        if(intval(session('erp_adminInfo')['id']) != 75){
            $where_log['perator_id'] = intval(session('erp_adminInfo')['id']);
            $workflow_log_user = $this->getModel('ErpWorkflowLog')->erpWorkflowLogList($where_log,'id,workflow_id');
            if (empty($workflow_log_user)) return ['recordsTotal' => 0,'data' => [],'recordsFiltered' => 0,'draw' => 0];
            $workflow_id     = array_unique(array_column($workflow_log_user,'workflow_id'));
            $where['id']     = ['in',$workflow_id];
        }
        # $where['status'] = ['in',[1,2]];
        return $this->workFlowDataList($where,$param);
    }

    /*
     * ----------------------------------------
     * ERP审批流-获取审批列表
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    private function workFlowDataList($where = [],$param=[])
    {
        if(isset($param['order_number']) && !empty($param['order_number'])){
            $where['workflow_order_number']   = ['like','%'.trim($param['order_number']).'%'];
        }
        if ($param['workflow_type'] != '0' && !empty($param['workflow_type'])) {
            $where['workflow_type']  = intval($param['workflow_type']);
        }
        if ($param['workflow_status'] != '0' && !empty($param['workflow_status'])) {
            $where['status']  = intval($param['workflow_status']);
        }
        $work_flow_data = $this->getModel('ErpWorkflow')->erpWorkflowList($where,'*', $param['start'], $param['length']);
        if (count($work_flow_data['data']) > 0) {
            $our_company_id = array_unique(array_column($work_flow_data['data'],'our_company_id'));
            $our_company = $this->getModel('ErpCompany')->where(['company_id'=>['in',$our_company_id]])->getField('company_id,company_name',true);
            foreach ($work_flow_data['data'] as $k => $v) {
                $work_flow_data['data'][$k]['our_company_name']= empty($our_company[$v['our_company_id']])?'暂无名称':$our_company[$v['our_company_id']];
                $work_flow_data['data'][$k]['workflow_type']   = workflowOrderType($v['workflow_type']);
                $work_flow_data['data'][$k]['status'] == 1?'':$work_flow_data['data'][$k]['assignor'] = '-';
                $work_flow_data['data'][$k]['workflow_status'] = workflowStatusType($v['status']);
                $work_flow_data['data'][$k]['work_type']       = intval($v['workflow_type']);
            }
        } else {
            $work_flow_data['data'] = [];
        }
        $work_flow_data['recordsFiltered'] = $work_flow_data['recordsTotal'];
        $work_flow_data['draw'] = $_REQUEST['draw'];
        return $work_flow_data;
    }

    /*
     * ----------------------------------------
     * ERP审批流-获取审批详情
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    private function workFlowDataDetail($id=0,$data = [],$where = [])
    {
        if ($id <= 0) return [];
        switch ($data['workflow_type']) {
            case '1':
                # 销售单
                $data['order_info'] = $this->getModel('ErpSaleOrder')->findSaleOrder($where);
                $data = $this->orderInfoDetail($data,1);
                # 客户公司
                //$data['order_info']['company_name'] = D('Clients')->where(['id' => $data['order_info']['company_id']])->getField('company_name');
                $data['order_info']['company_name'] = $this->getEvent('ErpCustomer')->getCustomerDataField(['id' => $data['order_info']['company_id']], 'customer_name');
                $data['order_info']['company_name'] = $data['order_info']['company_name'] ? $data['order_info']['company_name'] : '';
                $data['order_info']['company_name_n'] = '客户公司';
                $data['order_info']['remark']         = $data['order_info']['order_remark'];
                $data['workflow_type_info']  = '销售单';
                $data['workflow_order_type'] = '销售';
                break;
            case '2':
                # 采购单
                $data['order_info'] = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder($where);
                $data = $this->orderInfoDetail($data,1);
                # 供应商
                //$data['order_info']['company_name'] = D('Clients')->where(['id' => $data['order_info']['sale_company_id']])->getField('company_name');
                $data['order_info']['company_name'] = $this->getEvent('ErpSupplier')->getSupplierDataField(['id' => $data['order_info']['sale_company_id']], 'supplier_name');

                $data['order_info']['company_name'] = $data['order_info']['company_name'] ? $data['order_info']['company_name'] : '';
                //print_r($data);

                //$data['order_info']['company_name'] = $this->getEvent('ErpSupplier')->getSupplierDataField(['id' => $data['order_info']['sale_company_id']], 'supplier_name');

                $data['order_info']['company_name_n'] = '供应商';
                $data['workflow_type_info']  = '采购单';
                $data['workflow_order_type'] = '采购';
                //print_r($data);
                break;
            case '3':
                # 调拨单
                $data['order_info'] = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder($where);
                $data = $this->orderInfoDetail($data,2);
                $data['workflow_type_info']  = '调拨单';
                $data['workflow_order_type'] = '调拨';
                break;
            case '4':
                # 销退单
                //$data['return_order_info'] = $this->getModel('ErpReturnedOrder')->findReturnedOrder(['ro.id' => $data['workflow_order_id'],'ro.order_type' => 1],'*');
                $data['return_order_info'] = $this->getEvent('ErpReturned')->findOneReturnOrder(['id'=>$data['workflow_order_id'], 'order_type'=>1], '*');
                //print_r($data);
                $data['order_info'] = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($data['return_order_info']['source_order_id'])]);

                $data = $this->orderInfoDetail($data,3);
                $data['workflow_type_info']  = '销退单';
                $data['workflow_order_type'] = '客户';
                $data['workflow_stock_type'] = '出库';
                //print_r($data);
                break;
            case '5':
                # 采退单
                //$data['return_order_info'] = $this->getModel('ErpReturnedOrder')->findReturnedOrder(['ro.id' => $data['workflow_order_id'],'ro.order_type' => 2],'*');
                $data['return_order_info'] = $this->getEvent('ErpReturned')->findOneReturnOrder(['id'=>$data['workflow_order_id'], 'order_type'=>2], '*');
                $data['order_info']  = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => intval($data['return_order_info']['source_order_id'])]);
                $data = $this->orderInfoDetail($data,3);
                $data['return_order_info']['company_name'] = $this->getEvent('ErpSupplier')->getSupplierDataField(['id' => $data['order_info']['sale_company_id']], 'supplier_name');
                $data['return_order_info']['company_name'] = $data['return_order_info']['company_name'] ? $data['return_order_info']['company_name'] : '';
                $data['return_order_info']['user_name'] = $this->getEvent('ErpSupplier')->getSupplierUserDataField(['id' => $data['order_info']['sale_user_id']], 'user_name');
                $data['return_order_info']['user_name'] = $data['return_order_info']['user_name'] ? $data['return_order_info']['user_name'] : '';
                $data['workflow_type_info']  = '采退单';
                $data['workflow_order_type'] = '供应商';
                $data['workflow_stock_type'] = '入库';
                #-----------------------------------------------------------------------------------------------------------#
                break;
            case '6':
                # 预付单
                //$data['order_info'] = $this->getModel('ErpRechargeOrder')->findOneRechargeOrder(['o.id' => $data['workflow_order_id']],'o.*,u.user_name,c.company_name,u.user_phone');
                $data['order_info'] = $this->getEvent('ErpRecharge')->findOneRechargeOrder($data['workflow_order_id'],'o.*');
                $data = $this->orderInfoDetail($data,4);
                $data['workflow_type_info']  = '预存单';
                break;
            case '7':
                # 预存单
                //$data['order_info'] = $this->getModel('ErpRechargeOrder')->findOneRechargeOrder(['o.id' => $data['workflow_order_id']],'o.*,u.user_name,c.company_name,u.user_phone');
                $data['order_info'] = $this->getEvent('ErpRecharge')->findOneRechargeOrder($data['workflow_order_id'],'o.*');
                $data = $this->orderInfoDetail($data,4);
                $data['workflow_type_info']  = '预付单';
                break;
        }
        if (empty($data['order_info'])) return [];
        # 获取对应商品
        $goods_field        = 'goods_code,goods_name,source_from,grade,level';
        $data['goods_info'] = $this->getModel('ErpGoods')->findErpGoods(['id' => $data['order_info']['goods_id']],$goods_field);
        # 获取审批人
        $data['work_flow_step_data'] = $this->getModel('ErpWorkflowStep')->workflowStepList(['workflow_id' =>intval($id) ],'assignor,assignor_id,step_num','id asc');
        foreach ($data['work_flow_step_data'] as $k => $v) {
            if(empty(trim($v['assignor']))) $data['work_flow_step_data'][$k]['assignor'] = $v['assignor'] = '无';
            if($data['current_step_num'] == $v['step_num'] &&  intval($data['status']) == 1 ){
                $data['work_flow_step_data'][$k]['assignor']  = '<span style="color:#F00">'.$v['assignor'].'</span>';
            }
        }
        # 审批流历史
        $where_work_flow_log['workflow_id'] = intval($id);
        $field = 'id,operating_state,remark,create_time,perator';
        $data['work_flow_log'] = $this->getModel('ErpWorkflowLog')->erpWorkflowLogList($where_work_flow_log,$field);
        if (empty($data['work_flow_log'])) return $data;
        foreach ($data['work_flow_log'] as $k => $v) {
            $data['work_flow_log'][$k]['operating_state'] = workflowStatus($v['operating_state']);
        }
        return $data;
    }

    /*
     * ----------------------------------------
     * 审批流详情页匹配信息
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    private function orderInfoDetail($data = [],$type = 1)
    {
        # 获取城市
        $city_name = provinceCityZone()['city'];
        switch (intval($type)) {
            case '1':
                # [销售单|采购单] 补全信息
                $data['order_info']['city']         = empty($city_name[$data['order_info']['region']]) ? '':$city_name[$data['order_info']['region']];
                # 数值处理[升数|金额]
                $data['order_info']['price']        = getNum($data['order_info']['price']);
                $data['order_info']['order_amount'] = getNum($data['order_info']['order_amount']);
                $data['order_info']['is_market'] = 1;
                if(isset($data['order_info']['delivery_money'])) {
                    # 销售单
                    $data['order_info']['buy_num']    = getNum($data['order_info']['buy_num']);
                    $data['order_info']['delivery_money']    = getNum($data['order_info']['delivery_money']);
                    # 付款方式不同，显示不同
                    # 账期（*天）
                    # 定金锁价 （*%，*天）
                    switch (intval($data['order_info']['pay_type'])){
                        case '2':
                            $data['order_info']['pay_type'] = saleOrderPayType($data['order_info']['pay_type']).'（'.intval($data['order_info']['account_period']).'天）';
                            break;
                        case '5':
                            $data['order_info']['pay_type'] = saleOrderPayType($data['order_info']['pay_type']).'（'.getNum($data['order_info']['prepay_ratio']).'%，'.intval($data['order_info']['account_period']).'天）';
                            break;
                        default:
                            $data['order_info']['pay_type'] = saleOrderPayType(intval($data['order_info']['pay_type']));
                            break;
                    }
                    # 显示是否提供市场信息
                    # 如果单价小于采购定价，则显示差额
                    if($data['order_info']['price'] < getNum($data['order_info']['goods_price'])){
                        $data['order_info']['is_market'] = 2;
                        $data['order_info']['price'] .='（低于批发售价'.bcsub(getNum($data['order_info']['goods_price']),$data['order_info']['price'],2).'元 ）';
                        # 是否含有市场信息
                        $data['order_info']['provide_market_info'] = intval($data['order_info']['provide_market_info']) == 1 ? '是':'否';
                    }
                }else{
                    # 采购单
                    $data['order_info']['buy_num']  = getNum($data['order_info']['goods_num']);
                    # 预付显示付款比例  如：付款方式：预付（15%）
                    # 账期显示账期天数，显示方式 如：  付款方式：账期（15天）
                    # 定金锁价显示付款比例和账期天数 ，如：  付款方式：定金锁价（15%，15天）
                    switch (intval($data['order_info']['pay_type'])){
                        case '2':
                            $data['order_info']['pay_type'] = purchasePayType($data['order_info']['pay_type']).'（'.getNum(intval($data['order_info']['prepay_ratio'])).'%）';
                            break;
                        case '3':
                            $data['order_info']['pay_type'] = purchasePayType($data['order_info']['pay_type']).'（'.intval($data['order_info']['account_period']).'天）';
                            break;
                        case '5':
                            $data['order_info']['pay_type'] = purchasePayType($data['order_info']['pay_type']).'（'.getNum($data['order_info']['prepay_ratio']).'%，'.intval($data['order_info']['account_period']).'天）';
                            break;
                        default:
                            $data['order_info']['pay_type'] = purchasePayType(intval($data['order_info']['pay_type']));
                            break;
                    }
                }
                $data['order_info']['is_special'] = intval($data['order_info']['is_special']) ==1 ?'是':'否' ;
                # 获取仓库
                $data['order_info']['storehouse']   = $this->getModel('ErpStorehouse')->where(['id' => $data['order_info']['storehouse_id']])->getField('storehouse_name');
                # 获取油库
                switch (intval($data['order_info']['depot_id'])) {
                    case '99999':
                        $oil_name = '不限油库';
                        break;
                    default:
                        $oil_name = $this->getModel('Depot')->where(['id' => intval($data['order_info']['depot_id'])])->getField('depot_name');
                        break;
                }
                $data['order_info']['depot_name']   = $oil_name;
                $data['order_info']['create_time']   = date('Y-m-d',strtotime($data['order_info']['add_order_time']));
                # ------采购单审批显示该城市该商品上一次采购价格--------------- 2017.09.19
                $where_purchase_order = [
                    'our_buy_company_id'     => intval($data['order_info']['our_buy_company_id']),     # 账套公司
                    'goods_id'               => intval($data['order_info']['goods_id']) ,              # 商品id
                    'region'                 => intval($data['order_info']['region']),                 # 城市id
                    'order_status'           => ['neq',2],                                             # 未取消
                    'id'                     => ['lt', $data['order_info']['id']]
                ];
                $last_price = $this->getModel('ErpPurchaseOrder')->where($where_purchase_order)->order('id desc')->getField('price');
                $data['order_info']['last_price'] = intval($last_price) <= 0 ? '0' : getNum($last_price);
                # -----当前该地区该商品的售价（区域维护的价格）---------------
                $where_region_goods = [
                    'goods_id'          => $data['order_info']['goods_id'],
                    'region'            => $data['order_info']['region'],
                    'our_company_id'    => $data['order_info']['our_buy_company_id'],
                    'status'            => '1',
                ];
                $region_price = $this->getModel('ErpRegionGoods')->where($where_region_goods)->order('id desc')->getField('price');
                $data['order_info']['region_price'] = intval($region_price) <= 0 ? '0' : getNum($region_price);
                # -----可用库存的显示的字段---------------
                $where_stock = [
                    'goods_id'              => intval($data['order_info']['goods_id']),
                    'object_id'             => intval($data['order_info']['storehouse_id']),
                    'stock_type'            => intval($data['order_info']['type']),
                    'our_company_id'        => intval($data['order_info']['our_buy_company_id']),
                    'region'                => intval($data['order_info']['region']),
                ];
                $available_num = $this->getEvent('ErpStock')->getStockInfo($where_stock)['available_num'];
                $data['order_info']['available_num'] =  getNum($available_num);
                # ---------------------------------------------end---------------------------------------
                break;
            case '2':
                # [调拨单]
                # allocation_type 调拨类型 1  城市仓->服务商 2 服务商->服务商3 服务商->城市仓4 城市仓->城市仓
                # $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['order_number' => $data['order_info']['out_region']]);
                # -----------------------------------------查询可用库存----------------------------
                # 计算当前可用库存
                if(!in_array(intval($data['order_info']['allocation_type']),[1,4])){
                    # 加油网点
                    //$object_id  = intval($data['order_info']['out_facilitator_skid_id']);
                    //930改造 取调拨出库网点调整为 out_storehouse 字段 xiaowen 2018-10-24
                    $object_id  = intval($data['order_info']['out_storehouse']);
                    $stock_type = 4;
                }else{
                    # 城市仓
                    $object_id  = intval($data['order_info']['out_storehouse']);
                    $stock_type  = getAllocationStockType($object_id);
                }
                $where_stock = [
                    'goods_id'              => $data['order_info']['goods_id'],
                    'object_id'             => $object_id,
                    'stock_type'            => $stock_type,
                    'our_company_id'        => intval($data['order_info']['our_company_id']),
                    'region'                => intval($data['order_info']['out_region']),
                ];
                $available_num = $this->getEvent('ErpStock')->getStockInfo($where_stock)['available_num'];
                $data['order_info']['available_num']  = empty($available_num) ? 0 : ($available_num < 0 ? '-'.abs(getNum($available_num)) : getNum($available_num));
                #-----------------------------------------end-----------------------------------------
                $data['order_info']['out_region']  = empty($city_name[$data['order_info']['out_region']]) ? '':$city_name[$data['order_info']['out_region']];
                $data['order_info']['in_region']   = empty($city_name[$data['order_info']['in_region']]) ? '':$city_name[$data['order_info']['in_region']];
                switch (intval($data['order_info']['allocation_type'])) {
                    case '1':
                        $data['order_info']['out_storehouse']     = $this->getAllocationTypeName(intval($data['order_info']['out_storehouse']),1);
                        $data['order_info']['in_facilitator_id']  = $this->getAllocationTypeName(intval($data['order_info']['in_facilitator_id']),2);
                        //$data['order_info']['in_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['in_facilitator_skid_id']));
                        $data['order_info']['in_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['in_storehouse']));
                        break;
                    case '2':
                        $data['order_info']['out_facilitator_id'] = $this->getAllocationTypeName(intval($data['order_info']['out_facilitator_id']),2);
                        //$data['order_info']['out_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['out_facilitator_skid_id']));
                        $data['order_info']['out_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['out_storehouse']));
                        $data['order_info']['in_facilitator_id']  = $this->getAllocationTypeName(intval($data['order_info']['in_facilitator_id']),2);
                        //$data['order_info']['in_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['in_facilitator_skid_id']));
                        $data['order_info']['in_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['in_storehouse']));
                        break;
                    case '3':
                        $data['order_info']['out_facilitator_id'] = $this->getAllocationTypeName(intval($data['order_info']['out_facilitator_id']),2);
                        //$data['order_info']['out_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['out_facilitator_skid_id']));
                        $data['order_info']['out_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['out_storehouse']));
                        $data['order_info']['in_storehouse']      = $this->getAllocationTypeName(intval($data['order_info']['in_storehouse']),1);
                        break;
                    case '4':
                        $data['order_info']['out_storehouse']     = $this->getAllocationTypeName(intval($data['order_info']['out_storehouse']),1);
                        $data['order_info']['in_storehouse']      = $this->getAllocationTypeName(intval($data['order_info']['in_storehouse']),1);
                        break;
                    default:
                        break;
                }
//                switch (intval($data['order_info']['allocation_type'])) {
//                    case '1':
//                        $data['order_info']['out_storehouse']     = $this->getAllocationTypeName(intval($data['order_info']['out_storehouse']),1);
//                        $data['order_info']['in_facilitator_id']  = $this->getAllocationTypeName(intval($data['order_info']['in_facilitator_id']),2);
//                        $data['order_info']['in_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['in_facilitator_skid_id']));
//                        break;
//                    case '2':
//                        $data['order_info']['out_facilitator_id'] = $this->getAllocationTypeName(intval($data['order_info']['out_facilitator_id']),2);
//                        $data['order_info']['out_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['out_facilitator_skid_id']));
//                        $data['order_info']['in_facilitator_id']  = $this->getAllocationTypeName(intval($data['order_info']['in_facilitator_id']),2);
//                        $data['order_info']['in_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['in_facilitator_skid_id']));
//                        break;
//                    case '3':
//                        $data['order_info']['out_facilitator_id'] = $this->getAllocationTypeName(intval($data['order_info']['out_facilitator_id']),2);
//                        $data['order_info']['out_facilitator_skid_id']  = $this->getAllocationSkidName(intval($data['order_info']['out_facilitator_skid_id']));
//                        $data['order_info']['in_storehouse']      = $this->getAllocationTypeName(intval($data['order_info']['in_storehouse']),1);
//                        break;
//                    case '4':
//                        $data['order_info']['out_storehouse']     = $this->getAllocationTypeName(intval($data['order_info']['out_storehouse']),1);
//                        $data['order_info']['in_storehouse']      = $this->getAllocationTypeName(intval($data['order_info']['in_storehouse']),1);
//                        break;
//                    default:
//                        break;
//                }
                $data['order_info']['num']                  = getNum($data['order_info']['num']);
                $data['order_info']['allocation_type_name'] = allocationOrderType(intval($data['order_info']['allocation_type']));
                $data['order_info']['create_time']   = date('Y-m-d',strtotime($data['order_info']['order_time']));
                break;
            case '3':
                # [销退单|采退单] ---------------------------------------------------------------------------------------
                if(intval($data['workflow_type']) == 4){
                    $dealer_name = $data['order_info']['dealer_name'];
                    $company_id  = $data['order_info']['company_id'];
                    $user_id     = $data['order_info']['user_id'];
                    # 入库数量： oil_erp_sale_order   outbound_quantity
                    $num         = $data['order_info']['outbound_quantity'];
                    # 待退数量： oil_erp_sale_order   buy_num - outbound_quantity   ~  buy_num
                    $min_num     = getNum($data['order_info']['buy_num'] - $data['order_info']['outbound_quantity'] - $data['order_info']['loss_num']);
                    $max_num     = getNum($data['order_info']['buy_num'] - $data['order_info']['loss_num']);
                    $wait_num    = ($min_num <= 0 ? 0 : $min_num) .'~'. $max_num;
                }else{
                    $dealer_name = $data['order_info']['buyer_dealer_name'];
                    $company_id  = $data['order_info']['sale_company_id'];
                    $user_id     = $data['order_info']['sale_user_id'];
                    # 出库数量
                    $num         = $data['order_info']['storage_quantity'];
                    # 待退数量
                    $min_num     = getNum($data['order_info']['goods_num'] - $data['order_info']['storage_quantity']);
                    $wait_num    = ($min_num <= 0 ? 0 : $min_num) .'~'. getNum($data['order_info']['goods_num']);
                }
                $data['return_order_info']['dealer_name']    =  $dealer_name;
//                $data['return_order_info']['company_name']   = D('Clients')->where(['id' => intval($company_id)])->getField('company_name');
//                $data['return_order_info']['user_name']      = D('User')->where(['id' => intval($user_id)])->getField('user_name');
                $data['return_order_info']['city']           = empty($city_name[$data['order_info']['region']]) ? '':$city_name[$data['order_info']['region']];
                # 获取仓库
                $data['return_order_info']['storehouse']   = $this->getModel('ErpStorehouse')->where(['id' => $data['order_info']['storehouse_id']])->getField('storehouse_name');
                # 获取油库
                if(intval($data['order_info']['depot_id']) == 99999){
                    $oil_name = '不限油库';
                }else{
                    $oil_name = $this->getModel('Depot')->where(['id' => intval($data['order_info']['depot_id'])])->getField('depot_name');
                }
                $data['return_order_info']['depot_name']       = $oil_name;
                $data['order_info']['price']                   = getNum($data['order_info']['price']);
                $data['return_order_info']['return_price']     = getNum($data['return_order_info']['return_price']);
                $data['return_order_info']['return_goods_num'] = getNum($data['return_order_info']['return_goods_num']);
                $data['order_info']['outbound_quantity']       = intval($num) < 0  ? '0' : getNum($num) ;
                $data['order_info']['wait_num']                = $wait_num;
                $data['order_info']['return_num']              = $data['return_order_info']['return_goods_num'];
                $data['return_order_info']['return_type']      = returnType(intval($data['return_order_info']['return_type']));
                break;
            case '4':
                # 预存 预付
                $data['order_info']['region']        = $city_name[intval($data['order_info']['region'])];
                $data['order_info']['creater']       = D('Dealer')->where(['id' => intval($data['order_info']['creater'])])->getField('dealer_name');
                $data['order_info']['user_name']     =  empty(trim($data['order_info']['user_name']))    ? '-' : $data['order_info']['user_name'];
                $data['order_info']['company_name']  =  empty(trim($data['order_info']['company_name'])) ? '-' : $data['order_info']['company_name'];
                $data['order_info']['user_phone']    =  empty(trim($data['order_info']['user_phone']))   ? '-' : $data['order_info']['user_phone'];
                $data['order_info']['recharge_amount'] =  getNum($data['order_info']['recharge_amount']);
                $data['order_info']['recharge_type'] = $data['order_info']['order_type'] == 1 ? PrestoreType($data['order_info']['recharge_type']) : PrepayType($data['order_info']['recharge_type']);
                $data['order_info']['apply_finance_time'] = date('Y-m-d', strtotime($data['order_info']['apply_finance_time']));
                break;
            default:
                break;
        }
        return $data;
    }

    /*
     * ----------------------------------------
     * 获取 城市仓 | 服务商
     * Author：qianbin       Time：2017-05-12
     * ----------------------------------------
     */
    private function getAllocationTypeName($id=1,$type = 1)
    {
        switch (intval($type)) {
            case '1':
                # 城市仓
                $data = $this->getModel('ErpStorehouse')->where(['id'=>intval($id)])->getField('storehouse_name');
                break;
            case '2':
                # 服务商
                //$data = $this->getModel('Facilitator')->where(['facilitator_id'=>intval($id)])->getField('name');
                $data = $this->getModel('ErpSupplier')->where(['id'=>intval($id)])->getField('supplier_name');
                break;
            default:
                $data = [];
                break;
        }
        return $data;
    }

    /*
     * ----------------------------------------
     * 获取 城市仓 | 服务商
     * Author：guanyu       Time：2017-07-17
     * ----------------------------------------
     */
    private function getAllocationSkidName($id)
    {
        if (intval($id)) {
            //$data = $this->getModel('FacilitatorSkid')->where(['facilitator_skid_id'=>intval($id)])->getField('name');
            $data = $this->getModel('ErpStorehouse')->where(['id'=>intval($id)])->getField('storehouse_name');
        } else {
            $data = '—';
        }
        return $data;
    }

    /*
     * ----------------------------------------
     * 审批流完结，更新对应订单status
     * Author：qianbin       Time：2017-05-04
     * ----------------------------------------
     */
    public function updateErpWorkFlowOrderStatus($type = 1,$order_id='',$status)
    {
        if(intval($type) == 7) $type = 6;
        switch (intval($type)) {
            case '1':
                # 销售单
                # if ($status == 4)
                # if (!$this->updateSaleOrder(trim($order_id))) return false;
                $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($order_id)]);
                if (empty($order_info)){
                    $result = 0;
                    break;
                }
                $update_orderInfo['order_status'] = intval($status);
                if(intval($status) == 1) {
                    $update_orderInfo['update_time'] = date('Y-m-d H:i:s',time());
                    $update_orderInfo['updater']     = intval($this->getUserInfo('id'));
                }else{
                    $update_orderInfo['check_time'] = date('Y-m-d H:i:s',time());
                }
                $result     = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id'=> $order_id],$update_orderInfo);
                $add_saleOrder_log = [
                    'sale_order_id'         => intval($order_id),
                    'sale_order_number'     => trim($order_info['order_number']),
                    'log_info'              => serialize($order_info),
                    'log_type'              => 5,
                    'create_time'           => date('Y-m-d H:i:s',time()),
                    'operator'              => trim($this->getUserInfo('dealer_name')),
                    'operator_id'           => intval($this->getUserInfo('id'))
                ];
                $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($add_saleOrder_log);
                break;
            case '2':
                # 采购单
                $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => intval($order_id)]);
                if (empty($order_info)){
                    $result = 0;
                    break;
                }
                $update_orderInfo['order_status'] = intval($status);
                if(intval($status) == 1) {
                    $update_orderInfo['update_time'] = date('Y-m-d H:i:s',time());
                    $update_orderInfo['updater']     = intval($this->getUserInfo('id'));
                }else{
                    $update_orderInfo['check_time'] = date('Y-m-d H:i:s',time());
                }
                $result     = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $order_id],$update_orderInfo);
                $add_Purchase_log = [
                    'purchase_id'               => intval($order_id),
                    'purchase_order_number'     => trim($order_info['order_number']),
                    'log_info'                  => serialize($order_info),
                    'log_type'                  => 5,
                    'create_time'               => date('Y-m-d H:i:s',time()),
                    'operator'                  => trim($this->getUserInfo('dealer_name')),
                    'operator_id'               => intval($this->getUserInfo('id'))
                ];
                $this->getModel('ErpPurchaseLog')->addPurchaseLog($add_Purchase_log);
                break;
            case '3':
                # 调拨单
                $order_info = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id' => intval($order_id)]);
                if (empty($order_info)){
                    $result = 0;
                    break;
                }
                $update_orderInfo['status'] = intval($status);
                if(intval($status) == 1) {
                    $update_orderInfo['update_time'] = date('Y-m-d H:i:s',time());
                    $update_orderInfo['updater']     = intval($this->getUserInfo('id'));
                    $log_type = 19;
                }else{
                    $update_orderInfo['check_time'] = date('Y-m-d H:i:s',time());
                    $log_type = 5;
                }
                $result     = $this->getModel('ErpAllocationOrder')->saveAllocationOrder(['id'=> intval($order_id)],$update_orderInfo);
                $add_Allocation_log = [
                    'allocation_id'               => intval($order_id),
                    'allocation_order_number'     => trim($order_info['order_number']),
                    'log_info'                    => serialize($order_info),
                    'log_type'                    => $log_type,
                    'create_time'                 => date('Y-m-d H:i:s',time()),
                    'operator'                    => trim($this->getUserInfo('dealer_name')),
                    'operator_id'                 => intval($this->getUserInfo('id'))
                ];
                $this->getModel('ErpAllocationOrderLog')->addAllocationOrderLog($add_Allocation_log);
                break;
            case '4':
                # 销退单
                $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => intval($order_id)]);
                if (empty($order_info)){
                    $result = 0;
                    break;
                }
                $update_orderInfo['order_status'] = intval($status);
                $update_orderInfo['update_time'] = date('Y-m-d H:i:s',time());
                $update_orderInfo['updater_id']  = intval($this->getUserInfo('id'));
                $log_type   = intval($status) == 1 ? 19 : 1;
                $result     = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id'=> intval($order_id)],$update_orderInfo);
                $add_ReturnedOrder_log = [
                    'return_order_id'               => intval($order_id),
                    'return_order_number'           => trim($order_info['order_number']),
                    'return_order_type'             => intval($order_info['order_type']),
                    'log_info'                      => serialize($order_info),
                    'log_type'                      => $log_type,
                    'create_time'                   => date('Y-m-d H:i:s',time()),
                    'operator'                      => trim($this->getUserInfo('dealer_name')),
                    'operator_id'                   => intval($this->getUserInfo('id'))
                ];
                $this->getModel('ErpReturnedOrderLog')->add($add_ReturnedOrder_log);
                break;
            case '5':
                # 采退单
                $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => intval($order_id)]);
                if (empty($order_info)){
                    $result = 0;
                    break;
                }
                $update_orderInfo['order_status'] = intval($status);
                $update_orderInfo['update_time'] = date('Y-m-d H:i:s',time());
                $update_orderInfo['updater_id']  = intval($this->getUserInfo('id'));
                $log_type   = intval($status) == 1 ? 19 : 1;
                $result     = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id'=> intval($order_id)],$update_orderInfo);
                $add_ReturnedOrder_log = [
                    'return_order_id'               => intval($order_id),
                    'return_order_number'           => trim($order_info['order_number']),
                    'return_order_type'             => intval($order_info['order_type']),
                    'log_info'                      => serialize($order_info),
                    'log_type'                      => $log_type,
                    'create_time'                   => date('Y-m-d H:i:s',time()),
                    'operator'                      => trim($this->getUserInfo('dealer_name')),
                    'operator_id'                   => intval($this->getUserInfo('id'))
                ];
                $this->getModel('ErpReturnedOrderLog')->add($add_ReturnedOrder_log);
                break;
            case '6':
                # 预存单 | 预付单
                $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => intval($order_id)]);
                if (empty($order_info)){
                    $result = 0;
                    break;
                }
                $update_orderInfo['order_status'] = intval($status);
                # $update_orderInfo['update_time'] = date('Y-m-d H:i:s',time());
                # $update_orderInfo['updater_id']  = intval($this->getUserInfo('id'));
                $log_type   = intval($status) == 1 ? 19 : 1;
                $result     = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id'=> intval($order_id)],$update_orderInfo);
                $add_rechargeOrder_log = [
                    'recharge_id'                   => intval($order_id),
                    'recharge_order_number'         => trim($order_info['order_number']),
                    'order_type'                    => intval($order_info['order_type']),
                    'log_info'                      => serialize($order_info),
                    'log_type'                      => $log_type,
                    'create_time'                   => date('Y-m-d H:i:s',time()),
                    'operator'                      => trim($this->getUserInfo('dealer_name')),
                    'operator_id'                   => intval($this->getUserInfo('id'))
                ];
                $this->getModel('ErpRechargeOrderLog')->add($add_rechargeOrder_log);
                break;
        }
        if ($result == 1) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * ----------------------------------------
     * [销售单|采购单|调拨单]  获取审批流详情
     * Author：qianbin       Time：2017-05-11
     * ----------------------------------------
     */
    public function orderWorkflow($param = [])
    {
        $order_source = [
            1 => '后台',
            2 => '油沃客'
        ];
        # 审批流
        if (intval($param['id']) <=0 || !in_array(intval($param['type']),array_keys(workflowOrderType())) )  return [];
        $data_workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id' => intval($param['id']),'workflow_type' => intval($param['type'])])->order('create_time desc')->getField('id,workflow_name,workflow_order_number,workflow_type,current_step_num,assignor,create_time,status,creater',true);
        if (empty($data_workflow)) return [];
        $workflow_id = array_column($data_workflow,'id');
        # 匹配审批流审批人
        $data_workflow_step = $this->getModel('ErpWorkflowStep')->workflowStepList(['workflow_id' =>['IN', $workflow_id]],'workflow_id,assignor,assignor_id,step_num','id asc');
        # 匹配历史
        $field = 'id,workflow_id,operating_state,remark,create_time,order_source,perator';
        $data_workflow_log = $this->getModel('ErpWorkflowLog')->erpWorkflowLogList(['workflow_id' => ['IN', $workflow_id]], $field);
        //拼装审核步骤
        $data_workflow_step_new = [];
        foreach ($data_workflow_step as $ko => $vo) {
            $data_workflow_step_new[$vo['workflow_id']][] = $vo;
        }
        //拼装审核日志
        $data_workflow_log_new = [];
        foreach ($data_workflow_log as $ko => $vo) {
            $vo['operating_state'] = workflowStatus($vo['operating_state']);
            $vo['order_source']    = $order_source[$vo['order_source']];
            $data_workflow_log_new[$vo['workflow_id']][] = $vo;
        }
        //拼装最后页面需要显示的数组
        foreach ($data_workflow as $key => $value) {
            $data_workflow[$key]['workflow_status'] = workflowLogStatus($value['status']);
            $data_workflow[$key]['workflow_type']   = workflowOrderType($value['workflow_type']) ;
            $data_workflow[$key]['workflow_step']   = '';
            $data_workflow[$key]['workflow_log']    = [];
            if(($value['status'] == 1)){
                $step_num_arr = array_column($data_workflow_step_new[$key],'step_num');
                $step_key = array_search($value['current_step_num'], $step_num_arr);
                $data_workflow_step_new[$key][$step_key]['assignor'] = '<span style="color:#F00">'.$data_workflow_step_new[$key][$step_key]['assignor'].'</span>';
            }
            $assignor = array_column($data_workflow_step_new[$key], 'assignor');
            $data_workflow[$key]['workflow_step'] = implode('->', $assignor);
            $data_workflow[$key]['workflow_log'] = $data_workflow_log_new[$key];
        }
        return $data_workflow;
    }

    /**
     * 生成审批流程
     * @param $data
     * @return mixed
     */
    public function addWorkFlow($data){
        $workflow_name = [
            1 =>'销售单审批',
            2 =>'采购单审批',
            3 =>'调拨单审批',
            4 =>'销退单审批',
            5 =>'采退单审批',
            6 =>'预存单审批',
            7 =>'预付单审批'
        ];
        if(in_array($data['workflow_type'], array_keys($workflow_name))){
            $data['workflow_name'] = $workflow_name[$data['workflow_type']];
        }

//        $data['creater'] = $this->getUserInfo('dealer_name');
//        $data['creater_id'] = $this->getUserInfo('id');
        $data['create_time'] = currentTime();
        return D('ErpWorkflow')->add($data);
    }

    /**
     * 生成审批流程，审核步骤数据
     * @param $workflow_id
     * @param $workflow_step
     * @param $order_info
     * @param $type
     * @return bool
     */
    public function createWorkflowStepData($workflow_id, $workflow_step, $order_info, $type = 1){
        //$status = false;
        $result = [
            'status' => 0,
            'message' => '',
        ];
        if($workflow_step){
            $step_data = [];
            $workflowPositionModel = D('ErpWorkflowPosition');
            $getAllArea = provinceCityZone()['city'];
            $workflowOrderType = workflowOrderType();
            $workflowPositionConfig = workflowPositionConfig();
            foreach($workflow_step as $key=>$value){
                //log_info('审核职位：'.workflowPositionCode($value));
                $region = $order_info['region'] ? $order_info['region'] : '';
//                if(strpos(workflowPositionCode($value), '0') !== false){
//                    $region = '';
//                    //log_info("不拼地区");
//                }
                $position_code = $workflowPositionConfig[$value]['area'] == 1 ? $value . $region : $value . '0';
                //$where_position = ['position_code'=>workflowPositionCode($value) . $region];
                $where_position = ['position_code'=>$position_code];
                $check_position_info = $workflowPositionModel->where($where_position)->find();
               
                if(empty($check_position_info)){
                        //验证ids审批人是否存在 awen 2019-5-9
                    if(strpos($workflowPositionConfig[$value]['name'], 'IDS') !== false){
                        sendEmailByJava('该审核人不存在',"审核职位:" . $workflowPositionConfig[$value]['name'] . '， 不存在。请及时处理' ,'xiaowen@51zhaoyou.com');
                        return $result = ['status'=>998, 'message'=> $workflowOrderType[$type].'审批流程对应审核人【'.$workflowPositionConfig[$value]['name'].'】不完善, 请完善后再操作'];
            
                    }else{
                        
                        sendEmailByJava('该审核人不存在',"审核职位:" . $workflowPositionConfig[$value]['name'] . '，在地区：'.$getAllArea[$region] .' 不存在。请及时处理' ,'xiaowen@51zhaoyou.com');
                        return $result = ['status'=>999, 'message'=> $getAllArea[$region] . $workflowOrderType[$type].'审批流程对应审核人不完善,请完善后再操作'];
                    }
                }
                $step_data[] = [
                    'workflow_id' => $workflow_id,
                    'step_num' => $key+1,
                    'assignor_id' => $check_position_info['dealer_id'],
                    'assignor' => $check_position_info['dealer_name'],
                    'create_time' => currentTime(),
                    //'creater' => $this->getUserInfo('id'),
                ];

            }
            $step_status = $this->addWorkflowStep($step_data);
            $update_workflow['total_step_num'] = count($workflow_step);
            $update_workflow['current_step_num'] = 1;
            $update_workflow['assignor_id'] = $step_data[0]['assignor_id'];
            $update_workflow['assignor'] = $step_data[0]['assignor'];
            $update_status = D('ErpWorkflow')->where(['id'=>$workflow_id])->save($update_workflow);
            if($step_status && $update_status){
                $result = [
                    'status' => 1,
                    'message' =>'',
                ];
                # 生成推送
                if($step_data[0]['assignor_id']){ //如果第一步审核的角色存在对应审核人，则给他发送推送消息
                    $send_jiguang_email     = M('Dealer')->where(['id' => intval($step_data[0]['assignor_id'])])->getField('dealer_email');
                    sendJpushMessage($step_data[0]['assignor_id'], '油沃客', 5, '审核流程 - 待审批提醒', $workflowOrderType[$type].'号：'.$order_info['order_number'].'，需要审批，请及时跟进！', '', intval($workflow_id), 1);

                    if (!in_array($send_jiguang_email, emailBlackList())) {
                        sendEmailByJava('审核流程 - 待审批提醒',$workflowOrderType[$type].'号：'.$order_info['order_number'].'，需要审批，请及时跟进！',$send_jiguang_email);
                    }
                }else{
                    sendEmailByJava('审核人不存在', "审核职位:".$workflow_step[0] .'在地区：'.provinceCityZone()['city'][$order_info['region']] .' 不存在。请及时处理' , 'xiaowen@51zhaoyou.com');
                    log_info("审核职位:".$workflow_step[0] .'在地区：'.provinceCityZone()['city'][$order_info['region']] .' 不存在。请及时处理');
                }
            }
        }
        return $result;
    }

    /**
     * 添加审核步骤
     * @author xiaowen
     * @param $data
     * @return bool|string
     */
    public function addWorkflowStep($data){
        $status = false;
        if($data){
            $status = D('ErpWorkflowStep')->addAll($data);
        }
        return $status;

    }

    /*
     * ------------------------------------------------------
     * [销售单|采购单|调拨单|退货单|预存|预付]  检查单据本身的状态
     * Author：qianbin       Time：2017-08-29
     * ------------------------------------------------------
     */
    private function checkOrderStatus($param = []){
        $field  = 'id,order_status';
        if(intval($param['workflow_type']) == 7 ) $param['workflow_type'] = 6;
        switch (intval($param['workflow_type'])){
            case 1:
                $data = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($param['workflow_order_id'])],$field);
                break;
            case 2:
                $data = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => intval($param['workflow_order_id'])],$field);
                break;
            case 3:
                $data = $this->getModel('ErpAllocationOrder')->getOneAllocationOrder(['id' => intval($param['workflow_order_id'])],'id,status as order_status');
                break;
            case 4:
                $data = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => intval($param['workflow_order_id'])],$field);
                break;
            case 5:
                $data = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => intval($param['workflow_order_id'])],$field);
                break;
            case 6:
                $data = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => intval($param['workflow_order_id'])],$field);
                break;
            default:
                return ['status' => 25 , 'message' => '该笔审批单据类型错误，请刷新后重试！'];
                break;
        }
        if(empty($data))                        return ['status' => 21 , 'message' => '该笔订单异常，请确认订单是否存在！'];
        if(intval($data['order_status']) == 1)  return ['status' => 22 , 'message' => '该笔订单状态为未审核，请刷新后重试！'];
        if(intval($data['order_status']) == 2)  return ['status' => 23 , 'message' => '该笔订单状态为已取消，请刷新后重试！'];
        if(intval($data['order_status']) == 4)  return ['status' => 24 , 'message' => '该笔订单状态为已复核，请刷新后重试！'];
        return ['status' => 1 ,'message' => '数据正常！'];
    }
}

