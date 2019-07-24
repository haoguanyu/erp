<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
use Home\Lib\csvLib;
class ErpSaleController extends BaseController
{

    /**
     * 销售单列表(批发)
     * @author xiaowen
     * @time 2017-4-17
     */
    public function saleOrderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 1;
            $data = $this->getEvent('ErpSale')->saleOrderList($param);
            $this->echoJson($data);
        }

        $data['depots'] = getDepotData();
//        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
        //开放二级网点销售单
        $StoreHouseData = getStoreHouseData(['is_sale' => 1]);
        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($data['depots']);
        $new_storehouse = getStoreHouseToRegion($StoreHouseData);
        //转换油库和仓库数据为json格式，供页面使用
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);

        //查询页面所需要的销售单相关状态入类型数据
        $data['saleOrderStatus'] = saleOrderStatus();
        $data['saleOrderPayType'] = saleOrderPayType();
        $data['saleOrderType'] = saleOrderType();
        $data['saleCollectionStatus'] = saleCollectionStatus();
        $data['purchaseInvoiceStatus'] = saleInvoiceStatus();
        $data['purchaseContractStatus'] = purchaseContract();
        $data['delivery_method'] = saleOrderDeliveryMethod();
        $data['provinceList'] = provinceCityZone()['province'];
        # $data['regionList'] = provinceCityZone()['city'];
        $access_node = $this->getUserAccessNode('ErpSale/saleOrderList');
        //print_r($per);
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        $data['is_agent'] = saleAgentType();
        //获取业务类型
        $data['business_type'] = getSaleOrderBusinessType();
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 我的销售单
     * @author xiaowen
     * @time 2017-04-17
     */
    public function mySaleOrderList()
    {
        //ajax 处理，请求业务处理方法
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 1;
            $param['dealer_id'] = $this->getUserInfo('id');

            $data = $this->getEvent('ErpSale')->saleOrderList($param);
            $this->echoJson($data);
        }

        //--------------展示页面及所需数据-------------------------------------------------

        //获取油库和仓库数据
        $data['depots'] = getDepotData();
//        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
        //开启二级网点销售单
        $StoreHouseData = getStoreHouseData(['is_sale' => 1]);
        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($data['depots']);
        $new_storehouse = getStoreHouseToRegion($StoreHouseData);
        //转换油库和仓库数据为json格式，供页面使用
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);

        //查询页面所需要的销售单相关状态入类型数据
        $data['saleOrderStatus'] = saleOrderStatus();
        $data['saleOrderPayType'] = saleOrderPayType();
        $data['saleOrderType'] = saleOrderType();
        $data['saleCollectionStatus'] = saleCollectionStatus();
        $data['purchaseInvoiceStatus'] = saleInvoiceStatus();
        $data['purchaseContractStatus'] = purchaseContract();
        $data['delivery_method'] = saleOrderDeliveryMethod();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        //-------------------end---------------------------------------------------------------
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2']        = json_encode(cityLevelData()['city2']);
        # end
        $data['is_agent'] = saleAgentType();
        //获取业务类型
        $data['business_type'] = getSaleOrderBusinessType();
        $access_node = $this->getUserAccessNode('ErpSale/mySaleOrderList');
        $this->assign('access_node', json_encode($access_node));
        //赋值所有数据到模板
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 销售单列表(零售)
     * @author xiaowen
     * @time 2017-4-17
     */
    public function saleRetailOrderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 2;
            $data = $this->getEvent('ErpSale')->saleOrderList($param);
            $this->echoJson($data);
        }

        $data['depots'] = getDepotData();
        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($data['depots']);
        //转换油库和仓库数据为json格式，供页面使用
        $data['depots'] = json_encode($new_depots);
        //查询页面所需要的销售单相关状态入类型数据

        //获取所有网点数据
        $getStoreHouseData = getStoreHouseData(['type'=>7]);

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                $new_storehouse[$key] = ['id'=>$key,'name'=>$value['storehouse_name']];
            }
        }
        $data['facilitatorSkidData'] = $new_storehouse;

        $data['saleOrderStatus'] = saleOrderStatus();
        $data['saleOrderPayType'] = saleOrderPayType();
        $data['saleOrderType'] = saleOrderType();
        $data['saleCollectionStatus'] = saleCollectionStatus();
        $data['purchaseInvoiceStatus'] = saleInvoiceStatus();
        $data['purchaseContractStatus'] = purchaseContract();
        $data['regionList'] = provinceCityZone()['city'];
        $access_node = $this->getUserAccessNode('ErpSale/saleOrderList');

        //$data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 新增销售单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function addSaleOrder()
    {
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpSale',MODULE_NAME)->addsaleOrder($param);

            $this->echoJson($data);
        }
        //获取销售单详细信息

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
//        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);

        //开启二级仓信息
        $StoreHouseData = getStoreHouseData(['is_sale' => 1]);
        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($DepotData);
        $new_storehouse = [];
        $whole_country = [];
        $storehouse_type = erpStorehouseType();
        unset($storehouse_type[2]);
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                if ($value['is_sale'] == 1) {
                    if ($value['whole_country'] == 1) {
                        array_push($whole_country,$value);
                    } elseif(in_array($value['type'], array_keys($storehouse_type))) {
                        $new_storehouse[$value['region']][] = $value;
                    }
                }
            }
            $new_storehouse[0] = $whole_country;
        }
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //-------------------------end----------------------------------------------------
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);
        $data['pay_type_list'] = saleOrderPayType();
        $data['saleOrderDeliveryMethod'] = saleOrderDeliveryMethod();
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['my_region'] = $this->getModel('Dealer')->where(['id'=>$data['dealer_id']])->find()['region'];
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['myUsers'] = getUserByDealer($this->getUserInfo('dealer_name'));
        //获取业务类型
        $data['business_type'] = getSaleOrderBusinessType();
        // 清除内部交易单
        unset($data['business_type'][4]);
        //$data['erp_company_id'] = session('erp_company_id');
        $this->assign('data', $data);
        $this->assign('my_region', $region['region_list'][$data['my_region']]);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 编辑销售单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function updateSaleOrder()
    {
        $id = intval(I('param.id', 0));
        $is_show = intval(I('param.is_show', 0));
        $copy = intval(I('param.copy', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpSale')->updatesaleOrder($id, $param);

            $this->echoJson($data);
        }

        //获取销售单详细信息

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();

        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($DepotData);

        $field = 'o.*,d.depot_name,c.customer_name s_company_name,cu.user_name s_user_name,cu.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';

        $data['order'] = $this->getEvent('ErpSale')->findOneSaleOrder($id, $field);
        // yf 更改 支持 内部交易单 显示 二级网点 
        if ( $data['order']['business_type'] == 4 ) {
            $StoreHouseData = getStoreHouseData(['type'=>['eq',7]]);
        } else {
//            $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
            //开放二级网点
            $StoreHouseData = getStoreHouseData(['is_sale' => 1]);
        }

        //是否显示代采仓由是否代采控制
//        if ($data['order']['is_agent'] == 1) {
//            $new_storehouse = getStoreHouseToRegion($StoreHouseData);
//        } elseif ($data['order']['is_agent'] == 2) {
//            $new_storehouse = [];
//            if ($StoreHouseData) {
//                foreach ($StoreHouseData as $key => $value) {
//                    if($value['type'] == 1)
//                        $new_storehouse[$value['region']][] = $value;
//                }
//            }
//        }
        $new_storehouse = [];
        $whole_country = [];
        $storehouse_type = erpStorehouseType();
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                if ($value['is_sale'] == 1 || $value['type'] == 7) {
                    if ($value['whole_country'] == 1) {
                        array_push($whole_country,$value);
                    } elseif(in_array($value['type'], array_keys($storehouse_type))) {
                        $new_storehouse[$value['region']][] = $value;
                    }
                }
            }
            $new_storehouse[0] = $whole_country;
        }
        $data['order']['price'] = round(getNum($data['order']['price']),2);
        $data['order']['buy_num'] = round(getNum($data['order']['buy_num']),4);
        $data['order']['prepay_ratio'] = $data['order']['prepay_ratio'] ? getNum($data['order']['prepay_ratio']) : $data['order']['pay_type'] == 5 ? getNum($data['order']['prepay_ratio']) : '';
        $data['order']['account_period'] = $data['order']['account_period'] ? $data['order']['account_period'] : '';
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['delivery_money'] = getNum($data['order']['delivery_money']);
        $data['order']['outbound_quantity'] = round(getNum($data['order']['outbound_quantity']),4);
        $data['order']['add_order_time'] = date('Y-m-d', strtotime($data['order']['add_order_time']));
        if ($copy == 1) {
            $data['order']['outbound_quantity'] = 0;
            $data['order']['dealer_id'] = $this->getUserInfo('id');
            $data['order']['dealer_name'] = $this->getUserInfo('dealer_name');

            $data['order']['add_order_time'] = date('Y-m-d');
        }
        $is_edit = $data['order']['order_status'] == 1 ? 1 : 0;

        $data['is_edit'] = $is_edit;
        if ($data['order']['is_upload_contract'] == 1) {
            $data['order']['contract_url'] = $this->uploads_path['sale_attach']['url'] . $data['order']['contract_url'];
        }
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //------------------------end-----------------------------------------------------
        
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);
        $data['orderType'] = saleOrderType();
        $data['pay_type_list'] = saleOrderPayType();
        $data['saleOrderDeliveryMethod'] = saleOrderDeliveryMethod();
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        //获取业务类型
        $data['business_type'] = getSaleOrderBusinessType();
        //当前登陆交易员与销售单所属交易员一致，则查询该交易下的用户
        if ($data['order']['dealer_id'] == $this->getUserInfo('id')) {
            $data['myUsers'] = getUserByDealer($this->getUserInfo('dealer_name'));
        }

        //当前登陆交易员与销售单所属交易员是否一致，判断是编辑模板还是详情模板
        $tpl = $data['order']['dealer_id'] == $this->getUserInfo('id') ? 'updateSaleOrder' : $copy ? 'updateSaleOrder' : 'detailSaleOrder';
        $this->assign('is_show', $is_show);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->assign('copy', $copy);
        $this->display($tpl);

    }

    /**
     * 删除销售单
     * @author xiaowen
     * @time 2017-4-5
     */
    public function delSaleOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {

            $data = $this->getEvent('ErpSale')->delSaleOrder($id);
            $this->echoJson($data);

        }
    }

    /**
     * 审核销售单
     * @author xiaowen
     * @time 2017-4-5
     */
    public function auditSaleOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {

            $data = $this->getEvent('ErpSale')->auditSaleOrder($id);
            $this->echoJson($data);

        }
    }

    /**
     * 确认销售单
     * @author xiaowen
     * @time 2017-4-5
     */
    public function confirmSaleOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {

            $data = $this->getEvent('ErpSale')->confirmSaleOrder($id);
            $this->echoJson($data);

        }
    }

    /**
     * 上传合同
     * @author xiaowen
     * @time 2017-3-31
     */
    public function uploadContract()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $param = I('param.attach');
            $data = $this->getEvent('ErpSale')->uploadContract($id, $param);
            $this->echoJson($data);

        }

        $data = [];
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 复制销售单
     * @author xiaowen
     * @time 2017-4-6
     */
    public function copySaleOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $data = $this->getEvent('ErpSale')->copySaleOrder($id);
            $this->echoJson($data);

        }
    }

    /******************************
        @ Content 确认是否是内部 销售单
        @ Author Yf
        @ Time 2018-12-11
        @ Param [
            'id' => 12312, // 销售单ID
        ]
        @ Return [
            status => message [
                3 => 此销售单属于 内部交易单，不允许！
                1 => 成功！
            ]
        ]
    *******************************/
    public function isInternalTransactionOrder()
    {
        $id = intval(I('param.id', 0));
        $result = $this->getEvent('ErpSale')->isInternalTransactionOrder($id);
        $this->echoJson($result);
    }

    /**
     * 获取销售单状态
     * @author xiaowen
     * @time 2017-4-6
     */
    public function getSaleOrderStatus()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $status = $this->getEvent('ErpSale')->getSaleOrderStatus($id);
            $data['status'] = $status;
            $this->echoJson($data);

        }
    }

    /**
     * 返回单条销售单信息
     * @author senpai
     * @time 2017-4-14
     */
    public function getSaleOrderInfo()
    {
        $id = intval(I('param.id', 0));
        $invoice_id = intval(I('param.invoice_id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpSale')->getSaleOrderInfo($id,$invoice_id);
            $this->echoJson($data);
        }
    }


    /**
     * 变更交易员
     * @author xiaowen
     * @time 2017-4-19
     */
    public function changeDealer(){
        $data['id'] = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpSale')->changeDealer($param);
            $this->echoJson($data);

        }
        $data['order'] = $this->getEvent('ErpSale')->findSaleOrder($data['id']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 交易员变更记录列表
     * @author xiaowen
     * @time 2017-4-20
     */
    public function changeDealerList(){
        if(IS_AJAX){

            $param = $_REQUEST;
            $data = $this->getEvent('ErpSale')->changeDealerList($param);
            $this->echoJson($data);

        }
    }

    public function orderDelay(){
        $data['id'] = intval(I('param.id', 0));
        if(IS_AJAX){

            $param = I('param.');
            $data = $this->getEvent('ErpSale')->orderDelay($param);
            $this->echoJson($data);

        }
        $data['order'] = $this->getEvent('ErpSale')->findSaleOrder($data['id']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $this->assign('data', $data);
        $this->display();
    }
    /**
     * 订单延长记录列表
     * @author xiaowen
     * @time 2017-4-20
     */
    public function orderDelayList(){
        if(IS_AJAX){

            $param = $_REQUEST;
            $data = $this->getEvent('ErpSale')->orderDelayList($param);
            $this->echoJson($data);

        }
    }

    /**
     * 获取一条销售单信息
     * @author xiaowen
     * @time 2017-4-20
     */
    public function getOneSaleOrderInfo(){
        $id = intval(I('param.id', 0));
        $data = $this->getEvent('ErpSale')->findSaleOrder($id);
        /************ YF Time 2018-12-11 *************/
        if ( $data['business_type'] == 4 ) {
            $data['status'] = 4;
        } else {
            $data['status'] = 0;
        }        
        /***************** END ***********************/
        $data['subsidy_money'] = getNum($data['subsidy_money']);
        $data['loss_num'] = getNum($data['loss_num']);
        $this->echoJson($data);
    }

    /**
     * 审核提供市场信息
     * @author xiaowen
     * @time 2017-4-20
     */
    public function auditSaleOrderShow(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpSale')->auditSaleOrderData($id, $param);
            $this->echoJson($data);
        }
        $data = [];
        if(intval($id)>0) {
            $data = $this->getEvent('ErpSale')->findOneSaleOrder($id, 'o.id,o.delivery_method,o.buy_num,o.price,g.goods_code,g.goods_name,g.source_from,g.grade,g.level');
            $data['price']           = getNum($data['price']);
            $data['buy_num']         = getNum($data['buy_num']);
            $data['delivery_method'] = saleOrderDeliveryMethod(intval($data['delivery_method']));
        }
        $this->assign('id', $id);
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 销售单补贴金额
     * @author xiaowen
     * @time 2017-5-24
     * @return array
     */
    public function subsidyMoney(){
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpSale')->subsidyMoney($param);
            $this->echoJson($data);
        }

    }

    /**
     * 录入损耗
     * @author senpai
     * @time 2017-5-25
     * @return array
     */
    public function entryLoss(){
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpSale')->entryLoss($param);
            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0)) ;
        $field = 'o.id as orderId ,o.order_number , o.add_order_time  , o.dealer_name , o.region , o.storehouse_id ,
        o.price , o.outbound_quantity , o.region, o.business_type ,
        c.customer_name s_company_name,g.goods_code';
        $orderInfo = $this->getEvent('ErpSale')->findOneSaleOrder($id, $field);
        //地区
        $regionList = provinceCityZone()['city'];
        $orderInfo['regionName'] = $regionList[$orderInfo['region']] ;
        //仓库信息
        if ( $orderInfo['business_type'] == 4 ) {
            $StoreHouseData = getStoreHouseData(['type'=>['eq',7]]);
        } else {
            $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
        }
        $orderInfo['storehouseName'] = $StoreHouseData[$orderInfo['storehouse_id']]['storehouse_name'] ;
        //损耗比例
        $lossRatios = lossRatio();
        //数据整理
        $orderInfo['outbound_quantity'] = round(getNum($orderInfo['outbound_quantity']),4);
        $orderInfo['price'] = round(getNum($orderInfo['price']),2);
        //出库信息
        $whereStockOut = [
            "outbound_type" => 1 ,
            "outbound_status" =>10 ,
            "source_number" => $orderInfo['order_number'] ,
            "is_reverse" => 2 ,
            "reversed_status" => 2 ,
        ];
        $stockOutField = "id , outbound_code , batch_sys_bn , batch_id , actual_outbound_num";
        $stockOutList = $this->getEvent('ErpStockOut')->getStockOutLists($stockOutField , $whereStockOut) ;
        $batchIds = array_unique(array_column($stockOutList , "batch_id"));
        $field = "b.id  , c.cargo_bn" ;
        $whereBatch = ["b.id" => ['in' , $batchIds]] ;
        $batchCargo = $this->getEvent("ErpBatch")->getBatchCargo($field , $whereBatch);
        foreach ($stockOutList as &$value){
            $value['cargoBn'] = isset($batchCargo[$value['batch_id']]) ? $batchCargo[$value['batch_id']] : "" ;
            $value['actual_outbound_num'] = round(getNum($value['actual_outbound_num']),4);
        }
        $this->assign("orderInfo" , $orderInfo);
        $this->assign("lossRatios" , $lossRatios) ;
        $this->assign("stockOutList" , $stockOutList);
        $this->display();
    }

//    /**
//     * 导出销售单
//     * @author xiaowen
//     * @time 2017-5-25
//     */
//    public function exportSaleOrderData(){
//        set_time_limit(30000);
//        @ini_set('memory_limit', '256M');
//        $param = I('param.');
//        $search_type = intval(I('param.search_type', 1)); //默认导出所有
//        //导出所有销售单
//        if($search_type == 1){
//            unset($param['search_type']);
//        }else if($search_type == 2){ //导出我的销售单
//            unset($param['search_type']);
//            $param['dealer_id'] = $this->getUserInfo('id');
//        }
//        $param['export'] = 1;
//        $data = $this->getEvent('ErpSale')->saleOrderList($param);
//        $arr = [];
//        if($data){
//            foreach($data['data'] as $k=>$v){
//                $arr[$k]['id']                  = $v['id'];
//                $arr[$k]['order_number']        = $v['order_number'];
//                $arr[$k]['add_order_time']      = $v['add_order_time'];
//                $arr[$k]['residue_time']        = $v['residue_time'];
//                $arr[$k]['dealer_name']         = $v['dealer_name'];
//                $arr[$k]['s_user_name']         = $v['s_user_name'];
//                $arr[$k]['s_user_phone']        = ' '.$v['s_user_phone'];
//                $arr[$k]['s_company_name']      = $v['s_company_name'];
//                $arr[$k]['region_name']         = $v['region_name'];
//                $arr[$k]['storehouse_name']     = $v['storehouse_name'];
//                $arr[$k]['depot_name']          = $v['depot_name'];
//                $arr[$k]['is_special']          = $v['is_special'] == 1 ? '是' : '否';
//                $arr[$k]['goods_code']          = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
//                $arr[$k]['price']               = $v['price'];
//                $arr[$k]['buy_num']             = $v['buy_num'];
//                $arr[$k]['loss_num']            = $v['loss_num'];
//                $arr[$k]['outbound_quantity']   = $v['outbound_quantity'];
//                $arr[$k]['returned_goods_num']  = $v['returned_goods_num'];
//                $arr[$k]['delivery_money']      = $v['delivery_money'];
//                $arr[$k]['order_amount']        = $v['order_amount'];
//                $arr[$k]['total_amount']        = $v['total_amount'];
//                $arr[$k]['loss_amount']         = $v['loss_amount'];
//                $arr[$k]['entered_loss_amount'] = $v['entered_loss_amount'];
//                $arr[$k]['pay_type']            = $v['pay_type'];
//                $arr[$k]['is_upload_contract']  = strip_tags($v['is_upload_contract']);
//                $arr[$k]['no_collect_amount']   = $v['no_collect_amount'];
//                $arr[$k]['no_invoice_amount']   = $v['no_invoice_amount'];
//                $arr[$k]['order_status']        = strip_tags($v['order_status']);
//                $arr[$k]['collection_status']   = strip_tags($v['collection_status']);
//                $arr[$k]['invoice_status']      = strip_tags($v['invoice_status']);
//                $arr[$k]['invoice_sn']          = $v['invoice_sn'];
//                $arr[$k]['order_source']        = strip_tags($v['order_source']);
//                $arr[$k]['creater_name']        = strip_tags($v['creater_name']);
//                $arr[$k]['create_time']         = strip_tags($v['create_time']);
//                $arr[$k]['is_void']             = strip_tags($v['is_void']);
//            }
//        }
//
//        $header = [
//            'ID','销售单号','订单时间','截单时间','交易员','客户','手机','公司','城市','仓库','油库','是否特需',
//            '商品','单价(元)','数量(吨)','损耗数量(吨)','出库数量(吨)','退货数量(吨)','运费(元)','订单金额(元)',
//            '订单总额(元)','损耗金额(元)','损耗已退(元)','付款方式','上传合同','未收金额(元)',
//            '待开票金额(元)','订单状态','收款状态','发票状态','发票号','来源','创建人','创建时间','是否作废'
//        ];
//        array_unshift($arr,  $header);
//        $file_name_arr = [
//            1 => '销售单'.currentTime().'.xls',
//            2 => '我的销售单'.currentTime().'.xls',
//        ];
//        create_xls($arr, $filename=$file_name_arr[$search_type]);
//    }
    /**
     * 导出销售单
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportSaleOrderData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        //导出所有销售单
        if($search_type == 1){
            unset($param['search_type']);
        }else if($search_type == 2){ //导出我的销售单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        //$param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $name = $param['type'] == 1 ? '' : '(零售)';
        $file_name_arr = [
            1 => '销售单' . $name . currentTime('Ymd').'.csv',
            2 => '我的销售单' . $name . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[$search_type]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','销售单号','订单时间','截单时间','代采','业务类型','交易员','客户','手机','公司','城市','仓库','油库',
            '商品','单价(元)','数量(吨)','损耗数量(吨)','出库数量(吨)','退货数量(吨)','运费(元)','订单金额(元)',
            '订单总额(元)','损耗金额(元)','损耗已退(元)','付款方式','配送方式','上传合同','未收金额(元)',
            '待开票金额(元)','订单状态','收款状态','发票状态','发票号','是否特需','来源','内部交易单号','创建人','创建时间','是否作废'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpSale')->saleOrderList($param)['data']) && ($count = $this->getEvent('ErpSale')->saleOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['add_order_time']      = "\t".$v['add_order_time'];
                $arr[$k]['residue_time']        = $v['residue_time'];
                $arr[$k]['is_agent']            = strip_tags($v['is_agent']);
                $arr[$k]['business_type']       = strip_tags($v['business_type']);
                $arr[$k]['dealer_name']         = $v['dealer_name'];
                $arr[$k]['s_user_name']         = $v['s_user_name'];
                $arr[$k]['s_user_phone']        = "\t".$v['s_user_phone'];
                $arr[$k]['s_company_name']      = $v['s_company_name'];
                $arr[$k]['region_name']         = $v['region_name'];
                $arr[$k]['storehouse_name']     = $v['storehouse_name'];
                $arr[$k]['depot_name']          = $v['depot_name'];
                $arr[$k]['goods_code']          = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['price']               = $v['price'];
                $arr[$k]['buy_num']             = $v['buy_num'];
                $arr[$k]['loss_num']            = $v['loss_num'];
                $arr[$k]['outbound_quantity']   = $v['outbound_quantity'];
                $arr[$k]['returned_goods_num']  = $v['returned_goods_num'];
                $arr[$k]['delivery_money']      = $v['delivery_money'];
                $arr[$k]['order_amount']        = $v['order_amount'];
                $arr[$k]['total_amount']        = $v['total_amount'];
                $arr[$k]['loss_amount']         = $v['loss_amount'];
                $arr[$k]['entered_loss_amount'] = $v['entered_loss_amount'];
                $arr[$k]['pay_type']            = $v['pay_type'];
                $arr[$k]['delivery_method']     = strip_tags($v['delivery_method']);
                $arr[$k]['is_upload_contract']  = strip_tags($v['is_upload_contract']);
                $arr[$k]['no_collect_amount']   = $v['no_collect_amount'];
                $arr[$k]['no_invoice_amount']   = $v['no_invoice_amount'];
                $arr[$k]['order_status']        = strip_tags($v['order_status']);
                $arr[$k]['collection_status']   = strip_tags($v['collection_status']);
                $arr[$k]['invoice_status']      = strip_tags($v['invoice_status']);
                $arr[$k]['invoice_sn']          = "\t".$v['invoice_sn'];
                $arr[$k]['is_special']          = $v['is_special'] == 1 ? '是' : '否';
                $arr[$k]['order_source']        = strip_tags($v['order_source']);
                $arr[$k]['retail_inner_order_number'] = strip_tags($v['retail_inner_order_number']);
                $arr[$k]['creater_name']        = strip_tags($v['creater_name']);
                $arr[$k]['create_time']         = "\t".strip_tags($v['create_time']);
                $arr[$k]['is_void']             = strip_tags($v['is_void']);
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
     * 导出销售单（临时，配合金融随时拉取字段）
     * @author guanyu
     * @time 2018-01-15
     */
    public function exportSaleOrderDataTemporary(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        //导出所有销售单
        if($search_type == 1){
            unset($param['search_type']);
        }else if($search_type == 2){ //导出我的销售单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        //$param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $name = $param['type'] == 1 ? '' : '(零售)';
        $file_name_arr = [
            1 => '销售单' . $name . currentTime('Ymd').'.csv',
            2 => '我的销售单' . $name . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[$search_type]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','销售单号','订单时间','截单时间','交易员','客户','手机','公司','城市','仓库','油库','配送方式','是否特需',
            '商品','单价(元)','数量(吨)','损耗数量(吨)','出库数量(吨)','退货数量(吨)','运费(元)','订单金额(元)',
            '订单总额(元)','损耗金额(元)','损耗已退(元)','付款方式','上传合同','未收金额(元)',
            '待开票金额(元)','订单状态','收款状态','发票状态','发票号','来源','创建人','创建时间','是否作废'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpSale')->saleOrderList($param)['data']) && ($count = $this->getEvent('ErpSale')->saleOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['add_order_time']      = $v['add_order_time'];
                $arr[$k]['residue_time']        = $v['residue_time'];
                $arr[$k]['dealer_name']         = $v['dealer_name'];
                $arr[$k]['s_user_name']         = $v['s_user_name'];
                $arr[$k]['s_user_phone']        = "\t".$v['s_user_phone'];
                $arr[$k]['s_company_name']      = $v['s_company_name'];
                $arr[$k]['region_name']         = $v['region_name'];
                $arr[$k]['storehouse_name']     = $v['storehouse_name'];
                $arr[$k]['depot_name']          = $v['depot_name'];
                $arr[$k]['delivery_method']     = $v['delivery_method'] == 1 ? '配送' : '自提';
                $arr[$k]['is_special']          = $v['is_special'] == 1 ? '是' : '否';
                $arr[$k]['goods_code']          = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['price']               = $v['price'];
                $arr[$k]['buy_num']             = $v['buy_num'];
                $arr[$k]['loss_num']            = $v['loss_num'];
                $arr[$k]['outbound_quantity']   = $v['outbound_quantity'];
                $arr[$k]['returned_goods_num']  = $v['returned_goods_num'];
                $arr[$k]['delivery_money']      = $v['delivery_money'];
                $arr[$k]['order_amount']        = $v['order_amount'];
                $arr[$k]['total_amount']        = $v['total_amount'];
                $arr[$k]['loss_amount']         = $v['loss_amount'];
                $arr[$k]['entered_loss_amount'] = $v['entered_loss_amount'];
                $arr[$k]['pay_type']            = $v['pay_type'];
                $arr[$k]['is_upload_contract']  = strip_tags($v['is_upload_contract']);
                $arr[$k]['no_collect_amount']   = $v['no_collect_amount'];
                $arr[$k]['no_invoice_amount']   = $v['no_invoice_amount'];
                $arr[$k]['order_status']        = strip_tags($v['order_status']);
                $arr[$k]['collection_status']   = strip_tags($v['collection_status']);
                $arr[$k]['invoice_status']      = strip_tags($v['invoice_status']);
                $arr[$k]['invoice_sn']          = $v['invoice_sn'];
                $arr[$k]['order_source']        = strip_tags($v['order_source']);
                $arr[$k]['creater_name']        = strip_tags($v['creater_name']);
                $arr[$k]['create_time']         = strip_tags($v['create_time']);
                $arr[$k]['is_void']             = strip_tags($v['is_void']);
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
     * 销售单回滚
     * @author senpei
     * @time 2017-06-20
     */
    public function rollBackSaleOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpSale')->rollBackSaleOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销售单审核时确认信息
     * 价格，吨数，产品，配送方式
     * @author qianbin
     * @time 2017-07-24
     */
    public function saleOrderExamine(){
        $id   = intval(I('param.id', 0));
        $data = [];
        if(intval($id)>0) {
            $data = $this->getEvent('ErpSale')->findOneSaleOrder($id, 'o.id,o.delivery_method,o.buy_num,o.price,g.goods_code,g.goods_name,g.source_from,g.grade,g.level');
            $data['price']           = getNum($data['price']);
            $data['buy_num']         = getNum($data['buy_num']);
            $data['delivery_method'] = saleOrderDeliveryMethod(intval($data['delivery_method']));
        }
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 销售单二次定价界面
     * @author xiaowen
     * @time 2017-7-14
     */
    public function updatePrice(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX){
            $param = I('param.');
            log_info('id:'. var_export($id,true));
            log_info('参数'. var_export($param, true));
            $data = $this->getEvent('ErpSale')->updatePrice($param);
            $this->echoJson($data);
        }else{
            $data['order'] = $this->getEvent('ErpSale')->findSaleOrder($id);
            $data['order']['order_amount'] = getNum($data['order']['order_amount']);
            $data['order']['price'] = getNum($data['order']['price']);
            //$data['order']['order_amount'] = getNum($data['order']['order_amount']);
            $this->assign('data', $data);
            $this->display();
        }

    }

    /**
     * 二次定价 操作列表
     * @author xiaowen
     * @time 2017-7-14
     */
    public function updatePriceLogList(){
        $id = intval(I('post.order_id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpSale')->getUpdatePriceLogList($id);
            $this->echoJson($data);
        }
    }

    /**
     * 验证销售是否满足退款条件
     * @param $order_info
     * @author guanyu
     * @time 2017-08-17
     * @return bool
     */
    public function checkOrderCanReturn(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpSale')->checkOrderCanReturn($id);
            $this->echoJson($data);
        }
    }

    /**
     * 验证销售单是否使用余额抵扣
     * @param $order_info
     * @author guanyu
     * @time 2017-11-21
     * @return bool
     */
    public function checkBalanceDeduction(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpSale')->checkBalanceDeduction($id);
            $this->echoJson($data);
        }
    }
    /**
     * 导出销售单(零售)
     * @author qianbin
     * @time 2017-5-25
     */
    public function exportSaleRetailOrderData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        //$param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name_arr = [
            1 => '销售单(零售)' . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[1]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','销售单号','订单时间','截单时间','交易员','客户','手机','公司','城市','服务商','加油网点','油库',
            '商品','单价(元)','数量(升)','数量(吨)','出库数量(吨)','出库数量(升)','运费(元)','订单金额(元)',
            '订单总额(元)','付款方式','未收金额(元)','待开票金额(元)','订单状态','收款状态','发票状态','是否完全出库',
            '是否红冲','来源','来源订单号','创建人','创建时间'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpSale')->saleOrderList($param)['data']) && ($count = $this->getEvent('ErpSale')->saleOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['add_order_time']      = "\t".$v['add_order_time'];
                $arr[$k]['residue_time']        = $v['residue_time'];
                $arr[$k]['dealer_name']         = $v['dealer_name'];
                $arr[$k]['s_user_name']         = $v['s_user_name'];
                $arr[$k]['s_user_phone']        = "\t".$v['s_user_phone'];
                $arr[$k]['s_company_name']      = $v['s_company_name'];
                $arr[$k]['region_name']         = $v['region_name'];
                $arr[$k]['storehouse_name']     = $v['storehouse_name'];
                $arr[$k]['facilitator_skid_name']= $v['facilitator_skid_name'];
                $arr[$k]['depot_name']          = $v['depot_name'];
                $arr[$k]['goods_code']          = $v['goods_name_retail'];
                $arr[$k]['price']               = $v['price'];
                $arr[$k]['buy_num_retail']      = $v['buy_num_retail'];
                $arr[$k]['buy_num']             = $v['buy_num'];
                $arr[$k]['outbound_quantity']   = $v['outbound_quantity'];
                $arr[$k]['outbound_num_litre']  = $v['outbound_num_litre'];
                $arr[$k]['delivery_money']      = $v['delivery_money'];
                $arr[$k]['order_amount']        = $v['order_amount'];
                $arr[$k]['total_amount']        = $v['total_amount'];
                $arr[$k]['retail_pay_type']     = $v['retail_pay_type'];
                $arr[$k]['no_collect_amount']   = $v['no_collect_amount'];
                $arr[$k]['no_invoice_amount']   = $v['no_invoice_amount'];
                $arr[$k]['order_status']        = strip_tags($v['order_status']);
                $arr[$k]['collection_status']   = strip_tags($v['collection_status']);
                $arr[$k]['invoice_status']      = strip_tags($v['invoice_status']);
                $arr[$k]['is_all_outbound']     = $v['is_all_outbound'];
                $arr[$k]['is_reverse']          = "\t".$v['is_reverse'];
                $arr[$k]['order_source']        = strip_tags($v['order_source']);
                $arr[$k]['from_order_number']   = "\t".$v['from_order_number'];
                $arr[$k]['creater_name']        = "\t".$v['creater_name'];
                $arr[$k]['create_time']         = "\t".$v['create_time'];
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
