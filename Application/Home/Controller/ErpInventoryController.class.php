<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
use Home\Lib\csvLib;
class ErpInventoryController extends BaseController
{
    // 测试添加log
    public function test_sock(){
        $data = array(
            'event'=>'ddd',
            'key'=>'ddd',
            'request'=> $_POST
        );
        log_write($data);

    }

    /**
     * 添加盘点计划
     * @author qianibn
     * @time 2018-01-03
     */
    public function addInventoryPlan()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpInventory')->addInventoryPlan($param);
            $this->echoJson($data);
        }

        $data['inventory_type'] = getInventoryPlanType();
        $data['regionList']     = provinceCityZone()['province'];
        $data['regionList'][1]  = '全国';
        $this->assign('data', $data);
        $this->display();
    }

     /**
     * 编辑盘点计划
     * @author qianibn
     * @time 2018-01-03
     */
    public function updateInventoryPlan()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            
            $data = $this->getEvent('ErpInventory')->updateInventoryPlan($param);
            $this->echoJson($data);
        }

        $id         = intval(I('param.id', 0));
        $data['data']           = $this->getModel('ErpInventoryPlan')->findErpInventoryPlan(['id' => intval($id)],'id,inventory_name,inventory_type,inventory_storehouse_ids');
        $data['data']['inventory_storehouse_ids'] = json_decode($data['data']['inventory_storehouse_ids'],true);
        $data['regionList']     = provinceCityZone()['province'];
        $data['regionList'][1]  = '全国';
        $data['inventory_type'] = getInventoryPlanType();
        $this->assign('data', $data);
        $this->display();
    }


    /**
     * 确认盘点计划
     * @author qianibn
     * @time 2018-01-03
     */
    public function confirmInventoryPlan()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->confirmInventoryPlan($id);
            $this->echoJson($data);
        }

    }

    /**
     * 取消盘点计划
     * @author qianibn
     * @time 2018-01-03
     */
    public function cancelInventoryPlan()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->cancelInventoryPlan($id);
            $this->echoJson($data);
        }

    }

    /**
     * 关闭盘点计划
     * @author qianibn
     * @time 2018-01-03
     */
    public function cancelInventoryPlanUse()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->cancelInventoryPlanUse($id);
            $this->echoJson($data);
        }

    }


    /**
     * 盘点计划列表
     * @author qianibn
     * @time 2018-01-03
     */
    public function inventoryPlanList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            
            $data = $this->getEvent('ErpInventory')->inventoryPlanList($param);
            $this->echoJson($data);
        }
        $data['inventory_type'] = getInventoryPlanType();
        $this->assign('data', $data);
        $this->display();
    }


    /**
     * 添加盘点单
     * @author qianibn
     * @time 2018-01-03
     */
    public function addInventoryOrder()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            
            $data = $this->getEvent('ErpInventory')->addInventoryOrder($param);
            $this->echoJson($data);
        }
        $data['plan_list'] = $this->getModel('ErpInventoryPlan')->where(['status'=>10 ,'is_use' => 1, 'our_company_id'=>session('erp_company_id')])->order('id desc')->getField('id,inventory_name,inventory_type');
        foreach($data['plan_list'] as $key=>$value){
            $data['plan_list'][$key]['inventory_type_name'] = getInventoryPlanType($value['inventory_type']);
        }
        $data['type_list'] = inventoryOrderType();

        $this->assign('data', $data);
        $this->display();
    }

    /***
     * -------------------------------------------
     * 分界线
     *
     * -------------------------------------------
     */
     /**
     * 编辑盘点单
     * @author qianibn
     * @time 2018-01-03
     */
    public function updateInventoryOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpInventory')->updateInventoryOrder($param);
            $this->echoJson($data);
        }
        $data['plan_list'] = $this->getModel('ErpInventoryPlan')->where(['status'=>10 ,'is_use' => 1, 'our_company_id'=>session('erp_company_id')])->order('id desc')->getField('id,inventory_name,inventory_type');
        foreach($data['plan_list'] as $key=>$value){
            $data['plan_list'][$key]['inventory_type_name'] = getInventoryPlanType($value['inventory_type']);
        }
        $data['type_list'] = inventoryOrderType();
        $data['order'] = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>$id]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 盘点单列表
     * @author qianibn
     * @time 2018-01-03
     */
    public function inventoryOrderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpInventory')->inventoryOrderList($param);
            $this->echoJson($data);
        }
        //$data['plan_list'] = $this->getModel('ErpInventoryPlan')->where('status=10')->getField('id,inventory_name');
        $data['inventory_plan'] = $this->getModel('ErpInventoryPlan')->where(['status'=>10, 'our_company_id'=>session('erp_company_id')])->order('id desc')->getField('id, inventory_name,inventory_type');
        foreach($data['inventory_plan'] as $key=>&$value){
            $value['inventory_type_name'] = getInventoryPlanType($value['inventory_type']);
        }
        //print_r($data['inventory_plan']);
        $data['order_status_list'] = inventoryOrderStatus();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 取消盘点单
     * @author qianibn
     * @time 2018-01-03
     */
    public function cancelInventoryOrder()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->cancelInventoryOrder($id);
            $this->echoJson($data);
        }

    }

    /**
     * 确认盘点单
     * @author qianibn
     * @time 2018-01-03
     */
    public function confirmInventoryOrder()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->confirmInventoryOrder($id);
            $this->echoJson($data);
        }

    }

    /**
     * 生成盘点单明细数据
     * @author xiaowen
     * @time 2018-01-03
     */
    public function createInventoryOrderData()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            //$data = $this->getEvent('ErpInventory')->createInventoryOrderData($id);
            $data = $this->getEvent('ErpInventory')->createInventoryOrderDataNew($id);
            $this->echoJson($data);
        }

    }

    /**
     * 验证是否生成数据
     * @author xiaowen
     * @time 2018-1-5
     */
    public function checkCreateOrderData(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->checkCreateOrderData($id);
            $this->echoJson($data);
        }
    }

    /**
     * 盘点单详情
     * @author qianibn
     * @time 2018-01-03
     */
    public function inventoryOrderDetail()
    {
        $id = $_REQUEST['id'] ? intval($_REQUEST['id']) : intval(I('param.id', 0));

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpInventory')->inventoryOrderDetail($id,$param);
            $this->echoJson($data);
        }
        $data[] = [];
        $data['order'] = $this->getModel('ErpInventoryOrder')->findErpInventoryOrder(['id'=>$id]);
        $data['order']['inventory_order_type'] = inventoryOrderType($data['order']['inventory_order_type']);
        $data['order']['is_confirm_data'] = isStatus($data['order']['is_confirm_data']);
        $data['plan'] = $this->getModel('ErpInventoryPlan')->field('id,inventory_name,inventory_type')->find($data['order']['inventory_plan_id']);
        $data['plan']['inventory_type_name'] = getInventoryPlanType($data['plan']['inventory_type']);
        //print_r($data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 盘点单数据确认
     * @time 2018-1-9
     * @author xiaowen
     */
    public function confirmOrderDetailData(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->confirmOrderDetailData($id);
            $this->echoJson($data);
        }
    }

    /**
     * 生成库存盘盈盘亏出入单
     * @author xiaowen
     * @time 2018-1-10
     */
    public function createOrderStockData(){

         $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpInventory')->createOrderStockData($id);
            $this->echoJson($data);
        }
    }
    /**
     * 获取省份下的仓库
     * @author qianibn
     * @time 2018-01-03
     */
    public function getStoreHouseByProvince()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpInventory')->getStoreHouseByProvince($param);
            $this->echoJson($data);
        }
    }

    /**
     * 盘点明细更新 实盘数量
     * @author xiaowen
     * @time 2018-01-15
     */
    public function inventoryDetailUpdate(){
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpInventory')->inventoryDetailUpdate($param);
            $this->echoJson($data);
        }
    }
    /*
   ************************************************************
   *
   *   宇
   *
   *************************************************************
   */

    /**
     * 导出盘点单明细数据
     * @author guanyu
     * @time 2018-01-05
     */
    public function exportInventoryOrderData ()
    {
        $param = I('param.');
        $id = I('param.id');
        $param['export'] = 1;
        $data = $this->getEvent('ErpInventory')->inventoryOrderDetail($id,$param);
        $arr = [];
        if($data){
            foreach($data['data'] as $k=>$v){
                $arr[$k]['id']                      = $v['id'];
                $arr[$k]['storehouse_name']         = $v['storehouse_name'];
                $arr[$k]['inventory_order_number']  = $v['inventory_order_number'];
                $arr[$k]['goods_code']              = $v['goods_code'];
                $arr[$k]['goods_name']              = $v['goods_name'];
                $arr[$k]['source_from']             = $v['source_from'];
                $arr[$k]['grade']                   = $v['grade'];
                $arr[$k]['level']                   = $v['level'];
                //$arr[$k]['transportation_num']      = $v['transportation_num'] != 0 ? $v['transportation_num'] : '0';
                $arr[$k]['stock_num']               = $v['stock_num'] != 0 ? $v['stock_num'] : '0';
                $arr[$k]['inventory_stock_num']     = $v['inventory_stock_num'] != 0 ? $v['inventory_stock_num'] : '0';
                $arr[$k]['stock_diff_num']          = $v['stock_diff_num'] != 0 ? $v['stock_diff_num'] : '0';
                $arr[$k]['batch_sys_bn']          = $v['batch_sys_bn'] != '' ? $v['batch_sys_bn'] : '';
                $arr[$k]['batch_cargo_bn']          = $v['batch_cargo_bn'] != '' ? $v['batch_cargo_bn'] : '';
            }
        }

        $header = [
            'ID','仓库','盘点单号','商品代码','商品名称','商品来源','商品标号','商品级别','批次库存',
            '实际物理库存','实盘差异','系统批次','外部货权号'
        ];
        array_unshift($arr,  $header);
        $file_name = '盘点单详情'.currentTime().'.xls';
        create_xls($arr, $filename=$file_name);
    }

    /**
     * 导入盘点详细数据
     * @author guanyu
     * @time 2018-01-08
     */
    public function uploadInventoryOrderData()
    {
        @set_time_limit(5 * 60);
        require_once(APP_PATH . 'Home/Lib/silver.php');
        $silver = new \silver();
        $id = $_REQUEST['id'];
        $file = $_FILES['file_excel'];
        if (mb_substr($file['name'], -3, 3) != 'xls' && mb_substr($file['name'], -4, 4) != 'xlsx') {
            $this->ajaxReturn(['status' => 2, 'info' => [], 'is_null' => true, 'message' => '请选择excel文件']);
        }
        # @上传
        $time       = date("Y-m-d-H-i-s");
        $file_name  = $time . mt_rand(1, 1000);
        $path       = './Public/Uploads/Erp/Inventory/';
        $uploadFile = $silver->uploadFile($path, $file, $file_name);
        if($uploadFile != '上传成功'){
            $this->ajaxReturn(['status' => 3, 'info' => [], 'is_null' => true, 'message' => $uploadFile]);
        }
        # @返回数据
        $result = $this->getEvent('ErpInventory')->uploadInventoryOrderData($path.$file_name,$id);
        unlink($path.$file_name);
        $this->ajaxReturn($result);
    }

    public function exportOutInventoryOrderData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;

        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $file_name = '盘点单' . DateTime(). '.csv';
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','盘点单号','盘点方案名称','业务时间','盘点计划日期','盘点仓库类型','盘点类型','单据状态','生成盘点数据','盘点数据确认','生成调整单','是否锁货','财务审核状态',
            '备注',

        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpInventory')->inventoryOrderList($param)['data']) && (count($data) > 0)){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['inventory_order_number']        = $v['inventory_order_number'];
                $arr[$k]['inventory_name']      = "\t".$v['inventory_name'];
                $arr[$k]['create_time']        = $v['create_time'];
                $arr[$k]['add_order_date']            = strip_tags($v['add_order_date']);
                $arr[$k]['inventory_plan_type']       = strip_tags($v['inventory_plan_type']);
                $arr[$k]['inventory_order_type']         = $v['inventory_order_type'];
                $arr[$k]['order_status']         = strip_tags($v['order_status']);
                $arr[$k]['is_create_data']        = strip_tags($v['is_create_data']);
                $arr[$k]['s_company_name']      = strip_tags($v['is_confirm_data']);
                $arr[$k]['is_create_order']         = strip_tags($v['is_create_order']);
                $arr[$k]['is_locked']     = strip_tags($v['is_locked']);
                $arr[$k]['check_status']          = strip_tags($v['check_status']);
                $arr[$k]['remark']          = trim(strip_tags(str_replace(array("/r","/n","/r/n"),"",$v['remark'])));
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

}
