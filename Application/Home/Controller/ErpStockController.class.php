<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpStockController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP库存逻辑层
    // +----------------------------------
    // |Author:senpai Time:2017.5.3
    // +----------------------------------

    /**
     * 入库单列表
     * @author senpai
     * @time 2017-05-04
     */
    public function erpStockInList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['def'] = $_REQUEST['def'] ? $_REQUEST['def'] : 1;
            $data = $this->getEvent('ErpStock')->erpStockInList($param);
            $this->echoJson($data);
        }
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # $data['regionList'] = provinceCityZone()['city'];
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['order_type']   = stockInType();
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        $access_node = $this->getUserAccessNode('ErpStock/erpStockInList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 我的入库单列表
     * @author senpai
     * @time 2017-05-04
     */
    public function myErpStockInList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['def'] = $_REQUEST['def'] ? $_REQUEST['def'] : 1;
            $param['dealer_id'] = $this->getUserInfo('id');
            $data = $this->getEvent('ErpStock')->erpStockInList($param);
            $this->echoJson($data);
        }
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # $data['regionList'] = provinceCityZone()['city'];
        # 联动 - 市区数据  qianbin 2017.07.25
        $data['order_type']   = stockInType();
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 销售单生成出库单
     * @author xiaowen
     * @time 2017-5-5
     */
    public function addStockOut(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = I('param.');
//            $data = $this->getEvent('ErpStock')->addStockOut($param,$_FILES);
            $data = $this->getEvent('ErpStock')->addStockOut($param);
            $this->echoJson($data);
        }
        $applyInfo = $this->getEvent("ErpStockOutApply", "Home")->getInfo($id);
        list($region , $data) = $this->getEvent("ErpStockOutApply", "Home")->getApplyExtendData($applyInfo['source_object_id']);
//        print_r($data) ;
        $applyInfo['outbound_apply_num'] = getNum($applyInfo['outbound_apply_num']);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->assign("applyInfo" , $applyInfo);
        $this->display();

    }

    /**
     * 新增入库单
     * @author senpai
     * @time 2017-05-04
     */
    public function showAddErpStockIn()
    {
        if (IS_AJAX) {
            $data = $this->getEvent('ErpStock')->actAddErpStockIn($_POST,$_FILES);

            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));

        $order_info = $this->getEvent('ErpStock')->showAddErpStockIn($id);
        $order_info['order']['stock_not_in'] = $order_info['order']['pay_type'] == 5 ? round(getNum($order_info['order']['total_purchase_wait_num']) - $order_info['order']['storage_quantity'], 4) : $order_info['order']['stock_not_in'];
        $this->assign('data', $order_info);
        $this->display();
    }

    /**
     * 编辑入库单
     * @author senpai
     * @time 2017-05-04
     */
    public function showUpdateErpStockIn()
    {
        if (IS_AJAX) {
            $data = $this->getEvent('ErpStock')->actUpdateErpStockIn($_POST,$_FILES);

            $this->echoJson($data);
        }
        /* ----------- YF 更改 (根据不同入库进行区分查询条件)------------ */
        $param = $_REQUEST;
        if ( isset($param['stock_number']) ) {
            $stock_where = [
                'storage_code' => trim($param['stock_number'])
            ];
            $stock_arr = $this->getModel('ErpStockIn')->where($stock_where)->field('id')->find();
            $id = $stock_arr['id'];
            $is_update = 1;
        } else {
            $id = intval(I('param.id', 0));
            $is_update = 2;
        }
        /* ----------------------- END ------------------------------ */
        $order_info = $this->getEvent('ErpStock')->showUpdateErpStockIn($id);
        //获取该采购单所有的入库单数量
        $all_stockin_num = $this->getModel('ErpStockIn')->field('sum(actual_storage_num) as total')->where(['source_object_id' => $order_info['id'], 'storage_status' => ['eq', 10]])->group('source_object_id')->find();
        $order_info['order']['stock_not_in'] = $order_info['order']['pay_type'] == 5 ? getNum($order_info['order']['total_purchase_wait_num'] - $all_stockin_num['total']) : $order_info['order']['stock_not_in'];
        //edit xiaowen 2019-2-19 获取货权类型
        $cargo_type_arr = getConfigByType(2);
        /***************************************
            @ Content 存在损耗单 不允许编辑入库数量
                        START
        ***************************************/
        $loss_where = [
            'source_number' => ['eq',$order_info['order']['storage_code']],
            'order_status'  => ['neq',2],
        ];
        $loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->field('id')->find();
        // 是否存在损耗 | 1 ：存在 2：不存在
        $order_info['order']['is_loss'] = 2;
        if ( isset($loss_arr['id']) ) {
            $order_info['order']['is_loss'] = 1;
        }
        /***************************************
                        END
        ***************************************/
        //print_r($order_info);
        // 判断详情是否可以更改
        $this->assign('is_update', $is_update);
        $this->assign('cargo_type_arr', $cargo_type_arr);
        $this->assign('data', $order_info);
        $this->display();
    }

    /**
     * 取消入库单
     * @author senpai
     * @time 2017-05-05
     */
    public function actDeleteErpStockIn()
    {
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->actDeleteErpStockIn($id);

            $this->echoJson($data);
        }
    }

    /**
     * 审核入库单
     * @author senpai
     * @time 2017-05-05
     */
    public function actAuditErpStockIn()
    {
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->actAuditErpStockIn($id);

            $this->echoJson($data);
        }
    }

    /**
     * 获取入库单信息
     * @author senpai
     * @time 2017-05-05
     */
    public function getStockInInfo()
    {
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->getStockInInfo($id);

            $this->echoJson($data);
        }
    }
    /**
     * 出库单列表
     * @author xiaowen
     * @time 2017-05-04
     */
    public function erpStockOutList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['def'] = $_REQUEST['def'] ? $_REQUEST['def'] : 1;
            $param['type'] = 1;
            $data = $this->getEvent('ErpStock')->erpStockOutList($param);
            $this->echoJson($data);
        }
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # $data['regionList'] = provinceCityZone()['city'];
        # 联动 - 市区数据  qianbin 2017.07.25
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        # -------------------添加交易员筛选<qianbin> 2017/07/17--------------------
        $data['dealerList'] = $this->getModel('Dealer')->getDealerList('id,dealer_name',['is_available' => 0]);
        $data['stock_out_type'] = stockOutType();
        $access_node = $this->getUserAccessNode('ErpStock/erpStockOutList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign('data', $data);
        $this->display();
    }
    /**
     * 出库单(零售)列表
     * @author xiaowen
     * @time 2017-05-04
     */
    public function erpRetailStockOutList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['def'] = $_REQUEST['def'] ? $_REQUEST['def'] : 1;
            $param['type'] = 2;
            $data = $this->getEvent('ErpStock')->erpStockOutList($param);
            $this->echoJson($data);
        }

        //获取所有网点数据
        $getStoreHouseData = getStoreHouseData(['type'=>7]);

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                $new_storehouse[$key] = ['id'=>$key,'name'=>$value['storehouse_name']];
            }
        }
        $data['facilitatorSkidData'] = $new_storehouse;

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }
    /**
     * 我的出库单列表
     * @author xiaowen
     * @time 2017-05-04
     */
    public function myErpStockOutList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['def'] = $_REQUEST['def'] ? $_REQUEST['def'] : 1;
            $param['dealer_id'] = $this->getUserInfo('id');
            $param['type'] = 1;
            $data = $this->getEvent('ErpStock')->erpStockOutList($param);
            $this->echoJson($data);
        }
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # $data['regionList'] = provinceCityZone()['city'];
        # 联动 - 市区数据  qianbin 2017.07.25
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2'] = json_encode(cityLevelData()['city2']);
        $data['stock_out_type'] = stockOutType();
        $this->assign('data', $data);
        $this->display();
    }
    /**
     * 编辑出库单
     * @author xiaowen
     * @time 2017-05-04
     */
    public function updateErpStockOut()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('param.');
//            $data = $this->getEvent('ErpStock')->updateStockOut($param,$_FILES);
            $data = $this->getEvent('ErpStock')->updateStockOut($param);
            $this->echoJson($data);
        }
        /* ----------- YF 更改 (根据不同入口进行区分查询条件)------------ */
        $param = $_REQUEST;
        if ( isset($param['stock_number']) ) {
            $stock_where = [
                'outbound_code' => trim($param['stock_number'])
            ];
            $stock_arr = $this->getModel('ErpStockOut')->where($stock_where)->field('id')->find();
            $id = $stock_arr['id'];
            $is_update = 1;
        } else {
            $id = intval(I('param.id', 0));
            $is_update = 2;
        }
        /* ----------------------- END ------------------------------ */
        //查找该条出库信息
        $stock_out_info = $this->getEvent('ErpStock')->getStockOutInfo($id);

        //根据出库的出库类型 查询需要显示的数据
        if($stock_out_info['outbound_type'] == 1){
            $stock_out_info['actual_outbound_num'] = round(getNum($stock_out_info['actual_outbound_num']),4);
            $stock_out_info['outbound_num'] = round(getNum($stock_out_info['outbound_num']),4);
            //获取订单相关信息
            $order_id = $stock_out_info['source_object_id'];
            list($region , $data) = $this->getEvent("ErpStockOutApply", "Home")->getApplyExtendData($order_id);
            //判断是否来源于老吕找油的出库单
            $erpThreeWhere = ['erp_number' => $stock_out_info['outbound_code'] , "type" => 3 , "status" => 1];
            $erpThreeCount = $this->getModel("ErpThree")->countErpThree($erpThreeWhere);
            $isErpThree =  1 ;
            if($erpThreeCount > 0){
                $isErpThree = 0 ;
            }
            $this->assign("isErpThree" , $isErpThree);
            $this->assign('region', $region);
            $this->assign('stock_out', $stock_out_info);
            $tpl = 'updateErpStockOut';
        } else if ($stock_out_info['outbound_type'] == 2) {
            $data = $this->getEvent('ErpStock')->showUpdateErpStockOut($id,$stock_out_info['outbound_type']);
            $data['order']['actual_outbound_num'] = getNum($stock_out_info['actual_outbound_num']);
            $data['order']['outbound_num'] = getNum($stock_out_info['outbound_num']);
            $data['depots'] = json_encode([]);
            $tpl = 'detailErpStockOut';
        } else if (in_array($stock_out_info['outbound_type'],[3,4,5])) {
            $data = $this->getEvent('ErpStock')->showUpdateErpStockOut($id,$stock_out_info['outbound_type']);
            $data['order']['actual_outbound_num'] = getNum($stock_out_info['actual_outbound_num']);
            $data['order']['outbound_num'] = getNum($stock_out_info['outbound_num']);
            $data['depots'] = json_encode([]);
//            if($stock_out_info['outbound_type'] == 3){
//                $returnWhere= [
//                    "order_number" => $stock_out_info['source_number'],
//                ];
//                $returnField = "id , source_order_number" ;
//                $returnOrder = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder($returnWhere , $returnField) ;
//                $this->assign("returnOrder" , $returnOrder);
//            }
            $tpl = 'detailErpStockOut';
        }
        if($stock_out_info['outbound_type'] != 1){
            $whereBatch['sys_bn'] = $stock_out_info['batch_sys_bn'] ;
            $whereBatch['batchId'] = $stock_out_info['batch_id'] ;
            $whereBatch['show_all'] = 1 ;
            $batchInfo = $this->getEvent("ErpBatch")->erpBatchList($whereBatch);
            $this->assign('userBatch' , $batchInfo['data'][0]);
        }
        $applyInfo = $this->getEvent("ErpStockOutApply", "Home")->getInfoCode($stock_out_info['source_apply_number']);
        $applyInfo['outbound_apply_num'] = getNum($applyInfo['outbound_apply_num']);
        $this->assign('is_update',$is_update);
        $this->assign('applyInfo', $applyInfo);
        $this->assign('data', $data);
        $this->display($tpl);
    }

    /**
     * 获取出库单信息
     * @author xiaowen
     * @time 2017-05-05
     */
    public function getStockOutInfo()
    {
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->getStockOutInfo($id);

            $this->echoJson($data);
        }
    }

    /**
     * 取消出库单
     * @author xiaowen
     * @time 2017-05-05
     */
    public function cancelStockOut(){
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->cancelStockOut($id);

            $this->echoJson($data);
        }
    }
    /**
     * 审核出库单
     * @author xiaowen
     * @time 2017-05-05
     */
    public function auditErpStockOut(){
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->auditErpStockOut($id);

            $this->echoJson($data);
        }
    }

    /**
     * 库存查询列表
     * @author xiaowen
     * @time 2017-05-05
     */
    public function erpStockList(){
        if(IS_AJAX){
            $param = $_REQUEST;
            $data = $this->getEvent('ErpStock')->getStockList($param);
//            echo M()->getLastSql() ;
            $this->echoJson($data);
        }

        $data['stockType'] = stockType();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['provinceList'] = provinceCityZone()['province'];

        //搜索条件加入全国，目前仅仅在库存查询中有需求
        $data['provinceList'][1] = '全国';
        $city2 = cityLevelData()['city2'];
        $city2[1][] = ['id' => 1,'parent_id' => 1,'area_name' => '全国'];
        $data['city2'] = json_encode($city2);

        //将全国属性仓库，整合到region 为 1 键值对中
        $getStoreHouseData = getStoreHouseData();

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                if ($value['whole_country'] == 1) {
                    $new_storehouse[1][] = $value;
                } else {
                    $new_storehouse[$value['region']][] = $value;
                }
            }
        }
        $data['regionStorehouse'] = json_encode($new_storehouse);

        $new_stocktype = storehouseTypeToStockType();
        $data['stockTypeToStorehouseType'] = json_encode($new_stocktype);

        //$data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();

    }

    public function erpGoodsEvent(){
        return A('ErpGoods', 'Event');
    }

    /**
     * 验证销售单是否满足生成出库单条件
     * @author senpai
     * @time 2017-05-22
     */
    public function checkOrderCanOutStock()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent("ErpStock", "Home")->checkOrderCanOutStock($id);
            $this->echoJson($data);
        }
    }

    /**
     * 计划数量出库单详情
     * @author senpai
     * @time 2017-05-22
     */
    public function stockOutDetail()
    {
        $goods_id = intval(I('get.goods_id', 0));
        $storehouse_id = intval(I('get.storehouse_id', 0));
        $data['goods_id'] = $goods_id;
        $data['storehouse_id'] = $storehouse_id;
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpStock')->stockOutDetail($param);
            $this->echoJson($data);
        }
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 出库单取消审核
     * @author xiaowen
     * @time 2017-5-24
     */
    public function cancelAuditErpStockOut(){
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpStock')->cancelAuditErpStockOut($id);

            $this->echoJson($data);
        }
    }

    /**
     * 附件预览
     * @author guanyu
     * @time 2018-07-25
     */
    public function attachmentDetail()
    {
        $param = I('param.', 0);

        $attachment = $this->getEvent('ErpStock')->attachmentDetail($param);
        $this->assign('attachment', $attachment);
        $this->display();
    }

    /**
     * 财务核对（出/入库单）
     * @author guanyu
     * @time 2018-7-26
     */
    public function financeConfirm(){
        if (IS_AJAX) {
            $param = I('param.', 0);
            $data = $this->getEvent('ErpStock')->financeConfirm($param);

            $this->echoJson($data);
        }
    }

    /**********************************
        @ Content 财务驳回
        @ Author SYF
        @ Time 2018-11-30
        @ Param [
            'id'   => 入库单id 
            'type' => 1：出库单 2：入库单
            ]
        @ Return [
            'status'  => 状态码
            'message' => 提示语
        ]
    ***********************************/
    public function financialRejection()
    {
        if (IS_AJAX) {
            $param = I('param.', 0);
            $data = $this->getEvent('ErpStock')->financialRejection($param);

            $this->echoJson($data);
        }
    }

    /**
     * 导出库存数据
     * @author xiaowen
     * @time 2017-5-24
     */
    public function exportStockData(){
        $param = I('param.');
        $param['export'] = 1;
        $data = $this->getEvent('ErpStock')->getStockList($param);
        $arr  = [];
        foreach ($data['data'] as $k => $v) {
            $arr[$k]['id'] = $v['id'];
            $arr[$k]['region_name'] = $v['region_name'];
            $arr[$k]['stock_type'] = $v['stock_type'];
            $arr[$k]['facilitator_name'] = $v['facilitator_name'];
            $arr[$k]['object_name'] = $v['object_name'];
            $arr[$k]['goods_code'] = $v['goods_code']  . '/' .$v['source_from']. '/'  .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
            $arr[$k]['stock_num'] = "".$v['stock_num'];
            $arr[$k]['transportation_num'] = "".$v['transportation_num'];
            $arr[$k]['sale_reserve_num'] = "".$v['sale_reserve_num'];
            $arr[$k]['allocation_reserve_num'] = "".$v['allocation_reserve_num'];
            $arr[$k]['sale_wait_num'] = "".$v['sale_wait_num'];
            $arr[$k]['allocation_wait_num'] = "".$v['allocation_wait_num'];
            $arr[$k]['available_num'] = "".$v['available_num'];
            $arr[$k]['current_available_sale_num'] = "".$v['current_available_sale_num'];
        }
        $header = ['序号','地区','仓库类型','服务商','仓库','产品代码','物理库存','在途库存','销售预留','配货预留','销售待提','配货待提','可用库存','可售库存'];
        array_unshift($arr,  $header);
        create_xls($arr,$filename='库存记录'.currentTime().'.xls');
    }

    /**
     * 导入库存盘点数据
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importStockData(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
        die('已无用，不要来调戏我^___^');
        $filePath1 = './stock_data.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './stock_data.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
            //print_r($data);

            $this->getEvent('ErpStock')->importStockData($data);
        }

    }

    /**
     * 导入库存盘点数据
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importSkidData(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './stock_skid_data.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './stock_skid_data.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
            //print_r($data);

            $this->getEvent('ErpStock')->importSkidData($data);
        }

    }

    /**
     * 导出入库单
     * @author senpei
     * @time 2018-06-15
     */
    public function exportStockInData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        //导出所有入库单
        if($search_type == 1){
            unset($param['search_type']);
        }else if($search_type == 2){ //导出我的入库单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        //$param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name_arr = [
            1 => '入库单' . currentTime('Ymd').'.csv',
            2 => '我的入库单' . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[$search_type]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        if($search_type==1){
            $header = [
                'ID','订单日期','入库单号','来源申请单号','来源单号','单据类型','是否团内','城市','仓库','服务商','加油网点','交易员','客户', '公司名称',
                '商品代码','入库数量','实际入库数量','订单状态','财务审核状态','审核时间','审核人','备注','升数','密度','成本','是否上传附件','系统批次号','货权号','是否损耗'
            ];
        }else{
            $header = [
                'ID','订单日期','入库单号','来源单号','单据类型','城市','仓库','服务商','加油网点','交易员','客户', '公司名称',
                '商品代码','入库数量','实际入库数量','订单状态','财务审核状态','审核时间','审核人','备注','升数','密度','成本','是否上传附件','系统批次号','货权号','是否损耗'
            ];
        }

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpStock')->erpStockInList($param)['data']) && ($count = $this->getEvent('ErpStock')->erpStockInList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['create_time'] = $v['create_time'];
                $arr[$k]['storage_code'] = $v['storage_code'];
                $arr[$k]['source_apply_number'] = $v['source_apply_number'];
                $arr[$k]['source_number'] = $v['source_number'];
                $arr[$k]['storage_type_font'] = $v['storage_type_font'];
                if($search_type== 1){
                    $arr[$k]['inner'] = $v['is_inner'];//交易单类型
                }
                $arr[$k]['region_font'] = $v['region_font'];
                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
                $arr[$k]['facilitator_name'] = $v['facilitator_name'] ? $v['facilitator_name'] : '——';
                $arr[$k]['facilitator_point'] = $v['facilitator_skid_name'];
                $arr[$k]['sale_dealer_name'] = $v['sale_dealer_name'];
                $arr[$k]['user_name'] = $v['user_name'];
                $arr[$k]['company_name'] = $v['company_name'];
                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['storage_num'] = $v['storage_num'] != 0 ? $v['storage_num'] : '0';
                $arr[$k]['actual_storage_num'] = $v['actual_storage_num'] != 0 ? $v['actual_storage_num'] : '0';
                $arr[$k]['storage_status'] = strip_tags($v['storage_status']);
                $arr[$k]['finance_status'] = strip_tags($v['finance_status']);
                $arr[$k]['audit_time'] = $v['audit_time'];
                $arr[$k]['auditor'] = $v['auditor'];
                $arr[$k]['storage_remark'] = strip_tags($v['storage_remark']);
                $arr[$k]['actual_in_num_liter'] = $v['actual_in_num_liter'] != 0 ? $v['actual_in_num_liter'] : '0';
                $arr[$k]['outbound_density'] = $v['outbound_density'] != 0 ? $v['outbound_density'] : '0';
                $arr[$k]['price'] = $v['price'] != 0 ? $v['price'] : '0';
                $arr[$k]['attachment'] = $v['attachment'];
                $arr[$k]['batch_sys_bn'] = $v['batch_sys_bn'] ;
                $arr[$k]['cargo_bn'] = chr(9).$v['cargo_bn'];
                // 是否损耗
                $arr[$k]['is_loss'] = $v['is_loss'] ;
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

//    /**
//     * 导出入库单
//     * @author senpei
//     * @time 2017-06-01
//     */
//    public function exportStockInData(){
//        $param = I('param.');
//        $search_type = intval(I('param.search_type', 1)); //默认导出所有
//        //导出所有入库单
//        if($search_type == 1){
//            unset($param['search_type']);
//        }else if($search_type == 2){ //导出我的入库单
//            unset($param['search_type']);
//            $param['dealer_id'] = $this->getUserInfo('id');
//        }
//        $param['export'] = 1;
//        $data = $this->getEvent('ErpStock')->erpStockInList($param);
//        $arr = [];
//        if($data){
//            foreach($data['data'] as $k=>$v){
//                $arr[$k]['id'] = $v['id'];
//                $arr[$k]['create_time'] = $v['create_time'];
//                $arr[$k]['storage_code'] = $v['storage_code'];
//                $arr[$k]['source_number'] = $v['source_number'];
//                $arr[$k]['storage_type_font'] = $v['storage_type_font'];
//                $arr[$k]['region_font'] = $v['region_font'];
//                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
//                $arr[$k]['facilitator_name'] = $v['a_facilitator_name'] ? $v['a_facilitator_name'] : '——';
//                $arr[$k]['facilitator_point'] = $v['a_facilitator_skid_name'];
//                $arr[$k]['sale_dealer_name'] = $v['sale_dealer_name'];
//                $arr[$k]['user_name'] = $v['user_name'];
//                $arr[$k]['company_name'] = $v['company_name'];
//                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
//                $arr[$k]['storage_num'] = $v['storage_num'] != 0 ? $v['storage_num'] : '0';
//                $arr[$k]['actual_storage_num'] = $v['actual_storage_num'] != 0 ? $v['actual_storage_num'] : '0';
//                $arr[$k]['storage_status'] = $v['storage_status_font'];
//                $arr[$k]['audit_time'] = $v['audit_time'];
//                $arr[$k]['auditor'] = $v['auditor'];
//                $arr[$k]['storage_remark'] = strip_tags($v['storage_remark']);
//                $arr[$k]['actual_in_num_liter'] = $v['actual_in_num_liter'] != 0 ? $v['actual_in_num_liter'] : '0';
//                $arr[$k]['outbound_density'] = $v['outbound_density'] != 0 ? $v['outbound_density'] : '0';
//                $arr[$k]['price'] = $v['price'] != 0 ? $v['price'] : '0';
//            }
//        }
//
//        $header = [
//            'ID','订单日期','入库单号','来源单号','单据类型','城市','仓库','服务商','加油网点','交易员','客户', '公司名称',
//            '商品代码','入库数量','实际入库数量','订单状态','审核时间','审核人','备注','升数','密度','成本'
//        ];
//        array_unshift($arr,  $header);
//        $file_name_arr = [
//            1 => '入库单'.currentTime().'.xls',
//            2 => '我的入库单'.currentTime().'.xls',
//        ];
//        create_xls($arr, $filename=$file_name_arr[$search_type]);
//    }

    /**
     * 导出出库单
     * @author senpei
     * @time 2018-06-15
     */
    public function exportStockOutData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        //导出所有出库单
        if($search_type == 1){
            unset($param['search_type']);
        }else if($search_type == 2){ //导出我的出库单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        //$param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name_arr = [
            1 => '出库单' . currentTime('Ymd').'.csv',
            2 => '我的出库单' . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[$search_type]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        if($search_type == 1){
            $header = [
                'ID','订单日期','出库单号','来源申请单','来源单号','单据类型',"是否团内",'城市','仓库','服务商','加油网点','交易员','客户','公司名称',
                '商品代码','出库数量','实际出库数量','订单状态','审核时间','审核人','备注','升数','密度','单价','成本',"系统批次号","货权号",'是否上传附件'
            ];
        }else{
            $header = [
                'ID','订单日期','出库单号','来源申请单','来源单号','单据类型','城市','仓库','服务商','加油网点','交易员','客户','公司名称',
                '商品代码','出库数量','实际出库数量','订单状态','审核时间','审核人','备注','升数','密度','单价','成本',"系统批次号","货权号",'是否上传附件'
            ];
        }

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpStock')->erpStockOutList($param)['data']) && ($count = $this->getEvent('ErpStock')->erpStockOutList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['create_time'] = $v['create_time'];
                $arr[$k]['outbound_code'] = $v['outbound_code'];
                $arr[$k]['source_apply_number'] = $v['source_apply_number'] ;
                $arr[$k]['source_number'] = $v['source_number'];
                $arr[$k]['outbound_type_font'] = $v['outbound_type_font'];
                if($search_type== 1){
                    $arr[$k]['inner'] = $v['inner'];
                }
                $arr[$k]['region_font'] = $v['region_font'];
                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
                $arr[$k]['facilitator_name'] = $v['facilitator_name'];
                $arr[$k]['facilitator_skid_name'] = $v['facilitator_skid_name'];
                $arr[$k]['dealer_name'] = $v['sale_dealer_name'];
                $arr[$k]['user_name'] = $v['user_name'];
                $arr[$k]['company_name'] = $v['company_name'];
                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['outbound_num'] = $v['outbound_num'] != 0 ? $v['outbound_num'] : '0';
                $arr[$k]['actual_outbound_num'] = $v['actual_outbound_num'] != 0 ? $v['actual_outbound_num'] : '0';
                $arr[$k]['outbound_status'] = strip_tags($v['outbound_status']);
                $arr[$k]['audit_time'] = $v['audit_time'];
                $arr[$k]['auditor'] = $v['auditor'];
                $arr[$k]['outbound_remark'] = strip_tags($v['outbound_remark']);
                $arr[$k]['actual_out_num_liter'] = $v['actual_out_num_liter'] != 0 ? $v['actual_out_num_liter'] : '0';
                $arr[$k]['outbound_density'] = $v['outbound_density'] != 0 ? $v['outbound_density'] : '0';
                $arr[$k]['price'] = $v['price'] > 0 ? $v['price'] : '0.00';
                $arr[$k]['cost']  = $v['cost'] != 0 ? $v['cost'] : '0';
                //批次信息
                $arr[$k]['batch_sys_bn'] = $v['batch_sys_bn'] ;
                $arr[$k]['batch_cargo_bn']  =  chr(9).$v['batch_cargo_bn'];

                $arr[$k]['attachment'] = $v['attachment'];

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

//    /**
//     * 导出出库单
//     * @author senpei
//     * @time 2017-06-01
//     */
//    public function exportStockOutData(){
//        $param = I('param.');
//        $search_type = intval(I('param.search_type', 1)); //默认导出所有
//        //导出所有出库单
//        if($search_type == 1){
//            unset($param['search_type']);
//        }else if($search_type == 2){ //导出我的出库单
//            unset($param['search_type']);
//            $param['dealer_id'] = $this->getUserInfo('id');
//        }
//        $param['export'] = 1;
//        $data = $this->getEvent('ErpStock')->erpStockOutList($param);
//        $arr = [];
//        if($data){
//            foreach($data['data'] as $k=>$v){
//                $arr[$k]['id'] = $v['id'];
//                $arr[$k]['create_time'] = $v['create_time'];
//                $arr[$k]['outbound_code'] = $v['outbound_code'];
//                $arr[$k]['source_number'] = $v['source_number'];
//                $arr[$k]['outbound_type_font'] = $v['outbound_type_font'];
//                $arr[$k]['region_font'] = $v['region_font'];
//                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
//                $arr[$k]['facilitator_name'] = $v['facilitator_name'];
//                $arr[$k]['facilitator_skid_name'] = $v['facilitator_skid_name'];
//                $arr[$k]['dealer_name'] = $v['dealer_name'];
//                $arr[$k]['user_name'] = $v['user_name'];
//                $arr[$k]['company_name'] = $v['company_name'];
//                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
//                $arr[$k]['outbound_num'] = $v['outbound_num'] != 0 ? $v['outbound_num'] : '0';
//                $arr[$k]['actual_outbound_num'] = $v['actual_outbound_num'] != 0 ? $v['actual_outbound_num'] : '0';
//                $arr[$k]['outbound_status'] = $v['outbound_status_font'];
//                $arr[$k]['audit_time'] = $v['audit_time'];
//                $arr[$k]['auditor'] = $v['auditor'];
//                $arr[$k]['outbound_remark'] = strip_tags($v['outbound_remark']);
//                $arr[$k]['actual_out_num_liter'] = $v['actual_out_num_liter'] != 0 ? $v['actual_out_num_liter'] : '0';
//                $arr[$k]['outbound_density'] = $v['outbound_density'] != 0 ? $v['outbound_density'] : '0';
//                $arr[$k]['price'] = $v['price'] > 0 ? $v['price'] : '0.00';
//                $arr[$k]['cost']  = $v['cost'] != 0 ? $v['cost'] : '0';
//            }
//        }
//
//        $header = [
//            'ID','订单日期','出库单号','来源单号','单据类型','城市','仓库','服务商','加油网点','交易员','客户','公司名称',
//            '商品代码','出库数量','实际出库数量','订单状态','审核时间','审核人','备注','升数','密度','单价','成本'
//        ];
//        array_unshift($arr,  $header);
//        $file_name_arr = [
//            1 => '出库单'.currentTime().'.xls',
//            2 => '我的出库单'.currentTime().'.xls',
//        ];
//        create_xls($arr, $filename=$file_name_arr[$search_type]);
//    }

    /**
     * 导入生成销售单
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importSaleOrderData(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
        die('已无用，不要来调戏我^___^');
        exit;
        $filePath1 = './sale_order_data.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './sale_order_data.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
//            print_r($data);
//            exit;
            $this->getEvent('ErpStock')->importSaleOrderData($data);
        }

    }

    /**
     * 客户待提详情
     * @author senpai
     * @time 2017-06-29
     */
    public function showUserDetail(){
        $id = I('param.id');

        if(IS_AJAX){
            $param = $_REQUEST;
            $data = $this->getEvent('ErpStock')->showUserDetail($param);
            $this->echoJson($data);
        }

        $this->assign('id', $id);
        $this->display();

    }

    /**
     * 出入库报表
     * @author qianbin
     * @time 2017-10-25
     */
    public function reportFormsList(){
        if(IS_AJAX){
            $param = $_REQUEST;
            $data  = $this->getEvent('ErpStock')->reportFormsList($param,$param['start'],$param['length']);
            $this->echoJson($data);
        }
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2'] = json_encode(cityLevelData()['city2']);
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出出入库报表
     * @author qianbin
     * @time 2017-11-01
     */
    public function exportReportFormsData(){
        if(IS_AJAX){
            $param = $_REQUEST;
            $type  = [
                1 => ['status'=>1,'data'=>[],'message'=>'正在导出CSV， 数据较多， 请耐心等待...'],
                2 => ['status'=>2,'data'=>[],'message'=>'对不起，未查询到数据，无法导出！']
            ];
            $num  = $this->getEvent('ErpStock')->reportFormsList($param,$param['start'],$param['length'],2);
            $num = intval($num) > 0 ? 1:2;
            $this->echoJson($type[$num]);
        }
        set_time_limit(30000);
        @ini_set('memory_limit', '256M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 5000;
        $file_name_arr = '出入库报表'. currentTime().'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成菜单行
        $header = [
            'ID','单据日期','出/入库单号','出入库类型','来源单号','单据类型','商品代码','城市','仓库','仓库类型','吨量','升数','密度','审核时间','审核人','备注'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpStock')->reportFormsList($param,$param['start'],$param['length'])['data']) && ($count = $this->getEvent('ErpStock')->reportFormsList($param,$param['start'],$param['length'],2))){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['create_time']         = $v['create_time'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['stock_order_type']    = $v['stock_order_type'];
                $arr[$k]['source_number']       = $v['source_number'];
                $arr[$k]['type']                = $v['type'];
                $arr[$k]['goods_id']            = $v['goods_id'];
                $arr[$k]['region']              = $v['region'];
                $arr[$k]['storehouse_id']       = $v['storehouse_id'];
                $arr[$k]['stock_type']          = $v['stock_type'];
                $arr[$k]['goods_num']           = $v['goods_num'];
                $arr[$k]['num_liter']           = $v['num_liter'];
                $arr[$k]['goods_density']       = $v['goods_density'];
                $arr[$k]['audit_time']          = $v['audit_time'] == '0000-00-00' ? '-' : "\t".date('Y-m-d' , strtotime($v['audit_time']));
                $arr[$k]['auditor_id']          = $v['auditor_id'];
                $arr[$k]['remark']              = $v['remark'];
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

    /**
     * 入库单财务编辑价格
     * @time 2018-02-09
     * @author xiaowen
     */
    public function updateErpStockInPrice(){
        $id = intval(I('post.id', 0));
        if($id){
            $price = trim(I('post.price', 0));
            $data = $this->getEvent("ErpStock")->updateErpStockInPrice($id, $price);
            $this->echoJson($data);
        }

    }

    /**
     * 导入成本期初数据
     * @author guanyu
     * @time 2018-02-27
     */
    public function importCostInfo(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './cost_info.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './cost_info.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $sheet_one = $currentSheet->toArray();
            unset($sheet_one[0]);

            $currentSheet = $PHPExcel->getSheet(1);
            $sheet_two = $currentSheet->toArray();
            unset($sheet_two[0]);

            $currentSheet = $PHPExcel->getSheet(2);
            $sheet_three = $currentSheet->toArray();
            unset($sheet_three[0]);

            $currentSheet = $PHPExcel->getSheet(3);
            $sheet_four = $currentSheet->toArray();
            unset($sheet_four[0]);

            $currentSheet = $PHPExcel->getSheet(4);
            $sheet_five = $currentSheet->toArray();
            unset($sheet_five[0]);

            $data = [
                1 => $sheet_one,
                2 => $sheet_two,
                3 => $sheet_three,
                4 => $sheet_four,
                5 => $sheet_five,
            ];

            $this->getEvent('ErpStock')->importCostInfo($data);
        }

    }

    /**
     * 导出出库单(零售)
     * @author qianbin
     * @time 2018-04-09
     */
    public function exportStockOutRetailData(){
        if(IS_AJAX){
            $param = $_REQUEST;
            $type  = [
                1 => ['status'=>1,'data'=>[],'message'=>'正在导出CSV， 数据较多， 请耐心等待...'],
                2 => ['status'=>2,'data'=>[],'message'=>'对不起，未查询到数据，无法导出！']
            ];
            $num  = $this->getEvent('ErpStock')->erpStockOutList($param);
            $num = intval($num) > 0 ? 1:2;
            $this->echoJson($type[$num]);
        }
        set_time_limit(30000);
        @ini_set('memory_limit', '256M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 5000;
        $file_name_arr = '零售出库单'. currentTime().'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成菜单行
        $header = [
            '序号','订单日期','出库单号','来源单号','单据类型','城市','服务商','服务网点','商品代码','商品名称','商品来源','商品标号','商品级别','商品标注',
            '出库数量','实际出库升数','单价','成本(元/吨)','实际出库密度','成本(元/升)','是否红冲','红冲来源单号','订单状态','系统批次号','货权号','备注'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpStock')->erpStockOutList($param)['data']) && ($count = $this->getEvent('ErpStock')->erpStockOutList($param))){
            $arr = [];
            foreach($data as $k=>$v){

                $arr[$k]["id"]                      = $v["id"];
                $arr[$k]["create_time"]             = $v["create_time"];
                $arr[$k]["outbound_code"]           = $v["outbound_code"];
                $arr[$k]["source_number"]           = $v["source_number"];
                $arr[$k]["outbound_type_font"]      = $v["outbound_type_font"];
                $arr[$k]["region_font"]             = $v["region_font"];
                $arr[$k]["facilitator_name"]        = $v["facilitator_name"];
                $arr[$k]["facilitator_skid_name"]   = $v["facilitator_skid_name"];
                $arr[$k]["goods_code"]              = $v["goods_code"];
                $arr[$k]["goods_name"]              = $v["goods_name"];
                $arr[$k]["source_from"]             = $v["source_from"];
                $arr[$k]["grade"]                   = $v["grade"];
                $arr[$k]["level"]                   = $v["level"];
                $arr[$k]["label"]                   = $v["label"];
                $arr[$k]["actual_retail_num"]       = $v["actual_retail_num"];
                $arr[$k]["actual_outbound_litre"]   = $v["actual_outbound_litre"];
                $arr[$k]["price"]                   = $v["price"];
                $arr[$k]["cost"]                    = $v["cost"];
                $arr[$k]["outbound_density"]        = $v["outbound_density"];
                $arr[$k]["cost_litre"]              = $v["cost_litre"];
                $arr[$k]["is_reverse"]              = $v["is_reverse"];
                $arr[$k]["reverse_source"]          = $v["reverse_source"];
                $arr[$k]["outbound_status"]         = strip_tags($v["outbound_status"]);
                //批次信息
                $arr[$k]["batch_sys_bn"]          = $v["batch_sys_bn"];
                $arr[$k]["batch_cargo_bn"]         = $v["batch_cargo_bn"];
                $arr[$k]["outbound_remark"]         = $v["outbound_remark"];
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
    /*
     * params:$_file
     * return:json
     * @desc:上传图片信息
     * @auth:小黑
     * @time:2013-3-13
     */
    public function fileUpload(){
        $params = $_REQUEST ;
        if (IS_AJAX) {
            $imageUrl = $this->getEvent('ErpImage')->uploadImage($_FILES);
            if(is_array($imageUrl) && $imageUrl['status'] == 11){
                $this->echoJson($imageUrl);
            }
            $data = $this->getEvent("ErpStockOut", "Home")->updateAttachment($imageUrl , $params['source_object_id']) ;
            $this->echoJson($data);
        }
        $this->assign("stockOutId" , $params['id']);
        $this->display();
    }
}