<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpNewAllocationController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP调拨单逻辑层
    // +----------------------------------
    // |Author:senpai Time:2017.5.10
    // +----------------------------------

    /**
     * 取消调拨单
     * @author xiaowen
     * @time 2017-05-11
     */
    public function cancelAllocationOrder(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpNewAllocation')->cancelAllocationOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 审核调拨单
     * @author xiaowen
     * @time 2017-05-11
     */
    public function auditAllocationOrder(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpNewAllocation')->auditAllocationOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认调拨单（确认逻辑拆分：改变状态 && 调拨预留转调拨待提）
     * @author guanyu
     * @time 2017-10-09
     */
    public function confirmAllocationOrderStatus(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpNewAllocation')->confirmAllocationOrderStatus($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认调拨单
     * @author xiaowen
     * @time 2017-05-11
     */
    public function confirmAllocationOrder(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){

            $param = I('param.');
            $data = $this->getEvent('ErpNewAllocation')->confirmAllocationOrder($param,$_FILES);

            $this->echoJson($data);

        }

        $data['order'] = $this->getEvent('ErpNewAllocation')->findAllocationOrderInfo($id);
        $out_region_goods = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$data['order']['goods_id'],'region'=>$data['order']['out_region'],'our_company_id'=>session('erp_company_id'),'status'=>1])->find();
        $in_region_goods = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$data['order']['goods_id'],'region'=>$data['order']['in_region'],'our_company_id'=>session('erp_company_id'),'status'=>1])->find();
        if (!$out_region_goods['density']) {
            $data['out_density'] = $this->getModel('ErpGoods')->where(['goods_id'=>$data['order']['goods_id'],'status'=>10])->find()['density_value'];
        } else {
            $data['out_density'] = $out_region_goods['density'];
        }
        if (!$in_region_goods['density']) {
            $data['in_density'] = $this->getModel('ErpGoods')->where(['goods_id'=>$data['order']['goods_id'],'status'=>10])->find()['density_value'];
        } else {
            $data['in_density'] = $in_region_goods['density'];
        }
        $data['order']['num'] = getNum($data['order']['num']);
        //print_r($data);
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 复制调拨单
     * @author xiaowen
     * @time 2017-05-11
     */
    public function copyAllocationOrder(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpNewAllocation')->copyAllocationOrder($id);
            $this->echoJson($data);
        }
    }
//    /**
//     * 导出调拨单
//     * @author xiaowen
//     * @time 2017-05-12
//     */
//    public function exportAllocationOrder(){
//
//            $param = I('get.');
//            $param['export'] = 1;
//            $data = $this->getEvent('ErpNewAllocation')->erpAllocationOrderList($param);
//            $arr     = [];
//            foreach ($data['data'] as $k => $v) {
//                $arr[$k]['create_time']        = $v['create_time'];
//                $arr[$k]['order_number']        = $v['order_number'];
//                $arr[$k]['dealer_name']         = $v['dealer_name'];
//                $arr[$k]['out_region_font']            = $v['out_region_font'];
//                $arr[$k]['o_storehouse_name']         = $v['o_storehouse_name'];
//                $arr[$k]['o_facilitator_name']      = $v['o_facilitator_name'];
//                $arr[$k]['o_skid_name']      = $v['o_skid_name'];
//                $arr[$k]['in_region_font']      = $v['in_region_font'];
//                $arr[$k]['i_storehouse_name']      = $v['i_storehouse_name'];
//                $arr[$k]['i_facilitator_name']       = $v['i_facilitator_name'];
//                $arr[$k]['i_skid_name']       = $v['i_skid_name'];
//                $arr[$k]['goods_code']          = $v['goods_code'] . '/' .$v['source_from']. '/' .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
//                $arr[$k]['num']               = $v['num'];
//                $arr[$k]['actual_out_num']             = $v['actual_out_num'];
//                $arr[$k]['actual_in_num']      = $v['actual_in_num'];
//                $arr[$k]['status_font']        = $v['status_font'];
//                $arr[$k]['outbound_status']        = strip_tags($v['outbound_status']);
//                $arr[$k]['storage_status']        = strip_tags($v['storage_status']);
//                $arr[$k]['outbound_voucher_status'] = strip_tags($v['outbound_voucher_status']);
//                $arr[$k]['storage_voucher_status'] = strip_tags($v['storage_voucher_status']);
//                $arr[$k]['pick_up_number']    = $v['pick_up_number'];
//                $arr[$k]['remark']   = $v['remark'];
//
//            }
//            $header=['订单日期','调拨单号','业务员','从城市','从仓库','从服务商','从加油网点','至城市','至仓库','至服务商','至加油网点','商品','调拨数量','调出数量','调入数量','订单状态','出库状态','入库状态','出库凭证','入库凭证','提单号','备注'];
//            array_unshift($arr,$header);
//            create_xls($arr,$filename='调拨单列表'.currentTime().'.xls');
//    }

    /**
     * 导出调拨单
     * @author guanyu
     * @time 2017-12-04
     */
    public function exportAllocationOrder(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '调拨单' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header=[
            '订单日期','实际调拨日期','调拨单号','业务员','调拨场景','从城市','从仓库','从服务商','从加油网点','至城市','至仓库','至服务商',
            '至加油网点','商品','配送方式','调拨数量','调出数量','调入数量','订单状态','出库状态','入库状态','出库凭证','入库凭证',
            '提单号','备注'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpNewAllocation')->erpAllocationOrderList($param)['data']) && ($count = $this->getEvent('ErpNewAllocation')->erpAllocationOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['create_time']                 = empty(trim($v['create_time'])) ? '--'             :  "\t".$v['create_time'];
                $arr[$k]['actual_allocation_time']      = empty(trim($v['actual_allocation_time'])) ? '--'  :  "\t".$v['actual_allocation_time'];
                $arr[$k]['order_number']                = empty(trim($v['order_number'])) ? '--'            :  "\t".$v['order_number'];
                $arr[$k]['dealer_name']                 = empty(trim($v['dealer_name'])) ? '--'             :  $v['dealer_name'];
                $arr[$k]['business_type']               = $v['business_type'];
                $arr[$k]['out_region_font']             = empty(trim($v['out_region_font'])) ? '--'         :  $v['out_region_font'];
                $arr[$k]['o_storehouse_name']           = empty(trim($v['o_storehouse_name'])) ? '--'       :  $v['o_storehouse_name'];
                $arr[$k]['o_facilitator_name']          = empty(trim($v['o_facilitator_name'])) ? '--'      :  $v['o_facilitator_name'];
                $arr[$k]['o_skid_name']                 = empty(trim($v['o_skid_name'])) ? '--'             :  $v['o_skid_name'];
                $arr[$k]['in_region_font']              = empty(trim($v['in_region_font'])) ? '--'          :  $v['in_region_font'];
                $arr[$k]['i_storehouse_name']           = empty(trim($v['i_storehouse_name'])) ? '--'       :  $v['i_storehouse_name'];
                $arr[$k]['i_facilitator_name']          = empty(trim($v['i_facilitator_name'])) ? '--'      :  $v['i_facilitator_name'];
                $arr[$k]['i_skid_name']                 = empty(trim($v['i_skid_name'])) ? '--'             :  $v['i_skid_name'];
                $arr[$k]['goods_code']                  = $v['goods_code'] . '/' .$v['source_from']. '/' .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
                $arr[$k]['delivery_method']             = "\t".strip_tags($v['delivery_method']);
                $arr[$k]['num']                         = empty(trim(' '.$v['num'])) ? '0'                  :  $v['num'];
                $arr[$k]['actual_out_num']              = empty(trim(' '.$v['actual_out_num'])) ? '0'       :  $v['actual_out_num'];
                $arr[$k]['actual_in_num']               = empty(trim(' '.$v['actual_in_num'])) ? '0'        :  $v['actual_in_num'];
                $arr[$k]['status_font']                 = empty(trim(' '.$v['status_font_real'])) ? '--'    :  $v['status_font_real'];
                $arr[$k]['outbound_status']             = empty(trim(strip_tags($v['outbound_status']))) ? '--' :  strip_tags($v['outbound_status']);
                $arr[$k]['storage_status']              = empty(trim(strip_tags($v['storage_status']))) ? '--' :  strip_tags($v['storage_status']);
                $arr[$k]['outbound_voucher_status']     = empty(trim(strip_tags($v['outbound_voucher_status']))) ? '--' :  strip_tags($v['outbound_voucher_status']);
                $arr[$k]['storage_voucher_status']      = empty(trim(strip_tags($v['storage_voucher_status']))) ? '--' :  strip_tags($v['storage_voucher_status']);
                $arr[$k]['pick_up_number']              = empty(trim($v['pick_up_number'])) ? '0' :  "\t".$v['pick_up_number'];
                $arr[$k]['remark']                      = "\t".$v['remark'];
            }
            //分批生成CSV内容
            $csvObj->exportCsv($arr);
            //查询下一页数据，设置起始偏移量
            $page++;
            $param['start'] = ($page - 1 ) * $param['length'];
        }
        //关闭文件句柄
        $csvObj->closeFile();
    }

    public function getOneAllocationOrderInfo(){
        $id = intval(I('param.id', 0));
        $confirm_type = intval(I('param.confirm_type', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpNewAllocation')->getOneAllocationOrder(['id'=>$id],$confirm_type);
            $this->echoJson($data);
        }

    }
    /**
     * 调拨单列表
     * @author senpai
     * @time 2017-05-10
     */
    public function erpAllocationOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpNewAllocation')->erpAllocationOrderList($param);
            $this->echoJson($data);
        }
        $data['business_type'] = getAllocationOrderBusinessType();
        $data['regionList'] = provinceCityZone()['city'];
        $data['statusList'] = AllocationOrderStatus();
        $data['outboundStatus'] = AllocationOutboundStatus();
        $data['storageStatus'] = AllocationStorageStatus();
        $data['allocation_type'] = allocationOrderType();
        $data['delivery_method'] = allocationOrderDeliveryMethod();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $access_node = $this->getUserAccessNode('ErpNewAllocation/erpAllocationOrderList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 新增调拨单
     * @author senpai
     * @time 2017-05-10
     */
    public function addErpAllocationOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpNewAllocation')->addErpAllocationOrder($param);
            $this->echoJson($data);
        }
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['regionList'] = provinceCityZone()['city'];
        $data['deliveryMethod'] = allocationOrderDeliveryMethod();
        $data['business_type'] = getAllocationOrderBusinessType();
        $data['today'] = date('Y-m-d');
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 编辑调拨单
     * @author senpai
     * @time 2017-05-10
     */
    public function updateErpAllocationOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpNewAllocation')->updateErpAllocationOrder($param);
            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));
        $copy = intval(I('param.copy', 0));
        $erp_allocation_data = $this->getEvent('ErpNewAllocation')->getErpAlloctionData($id);
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['regionList'] = provinceCityZone()['city'];
        $data['deliveryMethod'] = allocationOrderDeliveryMethod();
        $data['business_type'] = getAllocationOrderBusinessType();
        $this->assign('allocation_data',$erp_allocation_data);
        $this->assign('data',$data);
        $this->assign('copy',$copy);
        $this->display();
    }

    /**
     * 调拨单详情
     * @author senpai
     * @time 2017-09-28
     */
    public function detailErpAllocationOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpNewAllocation')->updateErpAllocationOrder($param);
            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));
        $erp_allocation_data = $this->getEvent('ErpNewAllocation')->getErpAlloctionData($id);
        $erp_allocation_data['outbound_voucher'] = $this->uploads_path['allocation_attach']['url'] . $erp_allocation_data['outbound_voucher'];
        $erp_allocation_data['storage_voucher'] = $this->uploads_path['allocation_attach']['url'] . $erp_allocation_data['storage_voucher'];
        $erp_allocation_data['remark'] = "单据备注：".$erp_allocation_data['remark']."\r\n出库备注：".$erp_allocation_data['outbound_remark']."\r\n入库备注：".$erp_allocation_data['storage_remark'];
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['regionList'] = provinceCityZone()['city'];
        $data['deliveryMethod'] = allocationOrderDeliveryMethod();
        $data['business_type'] = getAllocationOrderBusinessType();
        $this->assign('allocation_data',$erp_allocation_data);
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 计划数量调拨详情
     * @author senpai
     * @time 2017-05-22
     */
    public function allocationDetail()
    {
        $goods_id = intval(I('get.goods_id', 0));
        $storehouse_id = intval(I('get.storehouse_id', 0));
        $data['goods_id'] = $goods_id;
        $data['storehouse_id'] = $storehouse_id;
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpNewAllocation')->allocationDetail($param);
            $this->echoJson($data);
        }
        $this->assign('data',$data);
        $this->display();
    }

    /**-------------------------------------------------------------------------------------
     *
     *
     *  新增代码写这里，在原基础修改的直接在上面代码修改
     *
     * --------------------------------------------------------------------------------------
     */

    /**
     * 确认出库
     * @author xiaowen
     * @time 2017-8-31
     */
    public function confirmOutStock(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpNewAllocation')->confirmOutStock($param,$_FILES);
            $this->echoJson($data);
        }
        $data['order'] = $this->getEvent('ErpNewAllocation')->findAllocationOrderInfo($id);
        $out_region_goods = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$data['order']['goods_id'],'region'=>$data['order']['out_region'],'our_company_id'=>session('erp_company_id'),'status'=>1])->find();
        $in_region_goods = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$data['order']['goods_id'],'region'=>$data['order']['in_region'],'our_company_id'=>session('erp_company_id'),'status'=>1])->find();
        if (!$out_region_goods['density']) {
            $data['out_density'] = $this->getModel('ErpGoods')->where(['goods_id'=>$data['order']['goods_id'],'status'=>10])->find()['density_value'];
        } else {
            $data['out_density'] = $out_region_goods['density'];
        }
        if (!$in_region_goods['density']) {
            $data['in_density'] = $this->getModel('ErpGoods')->where(['goods_id'=>$data['order']['goods_id'],'status'=>10])->find()['density_value'];
        } else {
            $data['in_density'] = $in_region_goods['density'];
        }
        $data['order']['num'] = getNum($data['order']['num']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 确认入库
     * @author xiaowen
     * @time 2017-8-31
     */
    public function confirmInStock(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpNewAllocation')->confirmInStock($param,$_FILES);
            $this->echoJson($data);
        }
        $data['order'] = $this->getEvent('ErpNewAllocation')->findAllocationOrderInfo($id);
        $out_region_goods = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$data['order']['goods_id'],'region'=>$data['order']['out_region'],'our_company_id'=>session('erp_company_id'),'status'=>1])->find();
        $in_region_goods = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$data['order']['goods_id'],'region'=>$data['order']['in_region'],'our_company_id'=>session('erp_company_id'),'status'=>1])->find();
        if (!$out_region_goods['density']) {
            $data['out_density'] = $this->getModel('ErpGoods')->where(['goods_id'=>$data['order']['goods_id'],'status'=>10])->find()['density_value'];
        } else {
            $data['out_density'] = $out_region_goods['density'];
        }
        if (!$in_region_goods['density']) {
            $data['in_density'] = $this->getModel('ErpGoods')->where(['goods_id'=>$data['order']['goods_id'],'status'=>10])->find()['density_value'];
        } else {
            $data['in_density'] = $in_region_goods['density'];
        }
        # 调拨单（城市仓->服务商 && 自提 ） 需要展示 收油反馈密度和升数
        $data['is_shipping'] = 2 ;
        if(intval($data['order']['allocation_type']) == 1 && intval($data['order']['delivery_method']) == 1){
            $data['is_shipping']    = 1 ;
            $data['shipping_order'] = $this->getModel('ErpShippingOrder')->field('actual_density,actual_num_liter')->where(['source_order_number' => $data['order']['order_number']])->find();
            $data['shipping_order']['actual_density']   = $data['shipping_order']['actual_density']   > 0 ? $data['shipping_order']['actual_density'] : 0 ;
            $data['shipping_order']['actual_num_liter'] = $data['shipping_order']['actual_num_liter'] > 0 ? getNum($data['shipping_order']['actual_num_liter']) : 0 ;
        }
        $data['order']['num'] = getNum($data['order']['num']);
        $data['order']['actual_out_num'] = getNum($data['order']['actual_out_num']);
        $data['loss_ratio'] = lossRatio();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 上传凭证
     * @author xiaowen
     * @time 2017-8-31
     */
    public function uploadVoucher()
    {
        $id = intval(I('param.id', 0));
        $type = intval(I('param.type', 0));
        if (IS_AJAX) {
            if(!in_array($type,[1,2])){
                $data = [
                    'status' => 2,
                    'message' => '凭证类型不对，请稍后操作',
                ];
            }else{
                $param = I('param.attach');
                $data = $this->getEvent('ErpNewAllocation')->uploadVoucher($id, $param, $type);
            }
            $this->echoJson($data);

        }

        $data = [];
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->assign('type', $type);
        $this->display();
    }

    /**
     * 调拨单回滚
     * @author qianbin
     * @time 2017-11-08
     */
    public function rollBackAllocationOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpNewAllocation')->rollBackAllocationOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 调拨单损耗报表
     * @author guanyu
     * @time 2017-11-17
     */
    public function erpAllocationLossList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpNewAllocation')->erpAllocationLossList($param);
            $this->echoJson($data);
        }
        $data['allocation_type'] = AllocationType();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $access_node = $this->getUserAccessNode('ErpNewAllocation/erpAllocationOrderList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 导出调拨损耗报表
     * @author xiaowen
     * @time 2017-05-12
     */
    public function exportAllocationLoss(){

        $param = I('get.');
        $param['export'] = 1;
        $data = $this->getEvent('ErpNewAllocation')->erpAllocationLossList($param);
        $arr     = [];
        foreach ($data['data'] as $k => $v) {
            $arr[$k]['id']                      = $v['id'];
            $arr[$k]['order_time']              = $v['order_time'];
            $arr[$k]['order_number']            = $v['order_number'];
            $arr[$k]['allocation_type']         = $v['allocation_type'];
            $arr[$k]['out_region_font']         = $v['out_region_font'];
            $arr[$k]['out_stock']               = $v['out_stock'];
            $arr[$k]['in_region_font']          = $v['in_region_font'];
            $arr[$k]['in_stock']                = $v['in_stock'];
            $arr[$k]['goods_code']              = $v['goods_code'] . '/' .$v['source_from']. '/' .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
            $arr[$k]['actual_out_num']          = "".$v['actual_out_num'];
            $arr[$k]['actual_in_num']           = "".$v['actual_in_num'];
            $arr[$k]['loss_num']                = "".$v['loss_num'];
            $arr[$k]['actual_out_num_liter']    = "".$v['actual_out_num_liter'];
            $arr[$k]['actual_in_num_liter']     = "".$v['actual_in_num_liter'];
            $arr[$k]['loss_num_liter']          = "".$v['loss_num_liter'];
        }
        $header=['序号','订单日期','调拨单号','调拨类型','从城市','从仓库','至城市','至仓库','商品','出库数量','入库数量','损耗（吨）','出库升量','入库升量','损耗（升）'];
        array_unshift($arr,$header);
        create_xls($arr,$filename='调拨单列表'.currentTime().'.xls');
    }

    /**
     * 上传凭证
     * @author xiaowen
     * @time 2017-8-31
     */
    public function updateRemark()
    {
        $id = intval(I('param.id', 0));
        $remark = trim(I('param.remark', ''));
        if (IS_AJAX) {

            //$data = $this->getEvent('ErpNewAllocation')->updateRemark($id, $remark);
            $status = $this->getModel('ErpAllocationOrder')->where(['id'=>$id])->save(['remark'=>$remark, 'update_time'=>currentTime()]);
            if($status){
                $data = [
                    'status'=>1,
                    'message'=>'操作成功',
                ];
            }else{
                $data = [
                    'status'=>0,
                    'message'=>'操作失败',
                ];
            }
            $this->echoJson($data);

        }

        $data = $this->getEvent('ErpNewAllocation')->findAllocationOrderInfo($id);;
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->display();
    }
}