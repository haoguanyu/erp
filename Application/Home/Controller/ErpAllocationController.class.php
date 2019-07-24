<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpAllocationController extends BaseController
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
            $data = $this->getEvent('ErpAllocation')->cancelAllocationOrder($id);
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
            $data = $this->getEvent('ErpAllocation')->auditAllocationOrder($id);
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
            $data = $this->getEvent('ErpAllocation')->confirmAllocationOrder($id, $param);

            $this->echoJson($data);

        }

        $data['order'] = $this->getEvent('ErpAllocation')->findAllocationOrderInfo($id);
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
            $data = $this->getEvent('ErpAllocation')->copyAllocationOrder($id);
            $this->echoJson($data);
        }
    }
    /**
     * 导出调拨单
     * @author xiaowen
     * @time 2017-05-12
     */
    public function exportAllocationOrder(){

            $param = I('get.');
            $param['export'] = 1;
            $data = $this->getEvent('ErpAllocation')->erpAllocationOrderList($param);
            $arr     = [];
            foreach ($data['data'] as $k => $v) {
                $arr[$k]['create_time']        = $v['create_time'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['dealer_name']         = $v['dealer_name'];
                $arr[$k]['out_region_font']            = $v['out_region_font'];
                $arr[$k]['o_storehouse_name']         = $v['o_storehouse_name'];
                $arr[$k]['o_facilitator_name']      = $v['o_facilitator_name'];
                $arr[$k]['o_skid_name']      = $v['o_skid_name'];
                $arr[$k]['in_region_font']      = $v['in_region_font'];
                $arr[$k]['i_storehouse_name']      = $v['i_storehouse_name'];
                $arr[$k]['i_facilitator_name']       = $v['i_facilitator_name'];
                $arr[$k]['i_skid_name']       = $v['i_skid_name'];
                $arr[$k]['goods_code']          = $v['goods_code'] . '/' .$v['source_from']. '/' .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
                $arr[$k]['num']               = $v['num'];
                $arr[$k]['actual_out_num']             = $v['actual_out_num'];
                $arr[$k]['actual_in_num']      = $v['actual_in_num'];
                $arr[$k]['status_font']        = $v['status_font'];
                $arr[$k]['pick_up_number']    = $v['pick_up_number'];
                $arr[$k]['remark']   = $v['remark'];

            }
            $header=['订单日期','调拨单号','业务员','从城市','从仓库','从服务商','从加油网点','至城市','至仓库','至服务商','至加油网点','商品','调拨数量','调出数量','调入数量','订单状态','提单号','备注'];
            array_unshift($arr,$header);
            create_xls($arr,$filename='调拨单列表'.currentTime().'.xls');
    }

    public function getOneAllocationOrderInfo(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpAllocation')->getOneAllocationOrder(['id'=>$id]);

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
            $data = $this->getEvent('ErpAllocation')->erpAllocationOrderList($param);
            $this->echoJson($data);
        }
        $data['regionList'] = provinceCityZone()['city'];
        $data['statusList'] = AllocationOrderStatus();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
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
            $data = $this->getEvent('ErpAllocation')->addErpAllocationOrder($param);
            $this->echoJson($data);
        }
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['regionList'] = provinceCityZone()['city'];
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
            $data = $this->getEvent('ErpAllocation')->updateErpAllocationOrder($param);
            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));
        $erp_allocation_data = $this->getEvent('ErpAllocation')->getErpAlloctionData($id);
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['regionList'] = provinceCityZone()['city'];
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
            $data = $this->getEvent('ErpAllocation')->allocationDetail($param);
            $this->echoJson($data);
        }
        $this->assign('data',$data);
        $this->display();
    }

}