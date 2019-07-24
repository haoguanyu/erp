<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
/**
* 退货单控制器
* @author xiaowen
* @time 2017-8-16 
*/
class ErpReturnedController extends BaseController
{
    /**---------------------------------------------------------------------//
    *
    * 采购退货单模块
    *
    **----------------------------------------------------------------------*/

    /************************************
        @ Content 红冲退货单
    *************************************/
    public function redRushReverse()
    {
        $id = intval(I('param.id'));
        $result = $this->getEvent('ErpReturned')->redRushReverse($id);
        $this->echoJson($result);
    }

    
    /**
     * 生成采购退货单
     * @author xiaowen
     * @time 2017-8-16
     */
    public function addPurchaseReturn(){
        $id = intval(I('param.id'));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpReturned')->addPurchaseReturn($param);
            $this->echoJson($data);
        }

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        $StoreHouseData = getStoreHouseData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        $new_storehouse = [];
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                $storehouse_type = erpStorehouseType(0, 0);
                if(in_array($value['type'],$storehouse_type)){
                    $new_storehouse[$value['region']][] = $value;
                }
            }
        }
        $data = [];
        //------------------------当前的登陆的子公司帐号----------------------------------
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.type as storehouse_type';

        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $id], $field);

        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['prepay_ratio'] = $data['order']['prepay_ratio'] != 0 ? $data['order']['prepay_ratio'] : '';
        $data['order']['account_period'] = $data['order']['account_period'] != 0 ? $data['order']['account_period'] : '';
        $data['order']['add_order_time'] = date('Y-m-d', strtotime($data['order']['add_order_time']));
        $loss_num = $this->getEvent('ErpReturned')->lossWhereByPurchaseReturn($data['order']['order_number']);
        $data['order']['min_return_num'] = sctonum(($data['order']['goods_num']-getNum($data['order']['storage_quantity']))-$loss_num);
        //可退货数量 = 已入库数量+在途数量
        $data['order']['can_return_goods_num'] = sctonum(($data['order']['goods_num']) - $loss_num);
        //获取业务类型
        $data['order']['business_type_info'] = getBusinessType($data['order']['business_type']);
        $is_edit = 0;
        if ($data['order']['order_status'] <= 10 && $data['order']['is_special'] == 1 && $data['order']['pay_status'] == 1 && $data['order']['invoice_status'] == 1) {
            $is_edit = 1;
        } else if ($data['order']['order_status'] == 1) {
            $is_edit = 2;
        }
        $data['is_edit'] = $is_edit;
        if ($data['order']['is_upload_contract'] == 1) {
            $data['order']['contract_url'] = $this->uploads_path['purchase_attach']['url'] . $data['order']['contract_url'];
        }
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //------------------------end-----------------------------------------------------
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);
        $data['pay_type_list'] = purchasePayType();
        if ($data['order']['type'] == 1) {
            unset($data['pay_type_list'][4]);
        }
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['return_type'] = returnType();
        /**************************************
            @ Content 获取批次列表
            @ Author  YF
            @ Time    2019-02-27
        ***************************************/
        $data['order']['storehouse_type'] = storehouseTypeToStockType($data['order']['storehouse_type']);
        /**************************************
                        END
        ***************************************/
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 采购退货单列表
     * @author xiaowen
     * @time 2017-08-17
     */
    public function purchaseReturnOrderList()
    {
        //ajax 处理，请求业务处理方法
        if (IS_AJAX) {
            $param = $_REQUEST;
            //$param['order_type'] = 2;
            $data = $this->getEvent('ErpReturned')->purchaseReturnOrderList($param);
            $this->echoJson($data);
        }

        //--------------展示页面及所需数据-------------------------------------------------

        //获取油库和仓库数据
        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData();
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
        $data['returnType'] = returnType();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        //-------------------end---------------------------------------------------------------
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2']        = json_encode(cityLevelData()['city2']);
        # end
        //获取业务类型
        $data['business_type'] = getBusinessType();

        $access_node = $this->getUserAccessNode('ErpReturned/purchaseReturnOrderList');
        $this->assign('access_node', json_encode($access_node));
        //赋值所有数据到模板
        $this->assign('data', $data);
        $this->display();

    }
    /**
     * 采购退货单列表
     * @author xiaowen
     * @time 2017-08-17
     */
    public function myPurchaseReturnOrderList()
    {
        //ajax 处理，请求业务处理方法
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['dealer_id'] = $this->getUserInfo('id');
            $data = $this->getEvent('ErpReturned')->purchaseReturnOrderList($param);
            $this->echoJson($data);
        }

        //--------------展示页面及所需数据-------------------------------------------------

        //获取油库和仓库数据
        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData();
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
        $data['returnType'] = returnType();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        //-------------------end---------------------------------------------------------------
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2']        = json_encode(cityLevelData()['city2']);
        # end
        //获取业务类型
        $data['business_type'] = getBusinessType();
        //赋值所有数据到模板
        $this->assign('data', $data);
        $this->display();

    }
    /**
     * 生成采购退货单
     * @author xiaowen
     * @time 2017-8-16
     */
    public function editPurchaseReturn(){
        $id = intval(I('param.id'));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpReturned')->editPurchaseReturn($param);
            $this->echoJson($data);
        }

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        $StoreHouseData = getStoreHouseData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        $new_storehouse = [];
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                if(in_array($value['type'], erpStorehouseType(0,0))){
                    $new_storehouse[$value['region']][] = $value;
                }
            }
        }
        $data = [];
        $data['returned'] = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id'=>$id]);
        $data['returned']['return_goods_num'] = getNum($data['returned']['return_goods_num']);
        $data['returned']['return_price'] = getNum($data['returned']['return_price']);
        //------------------------当前的登陆的子公司帐号----------------------------------
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';

        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $data['returned']['source_order_id']], $field);

        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['prepay_ratio'] = $data['order']['prepay_ratio'] != 0 ? $data['order']['prepay_ratio'] : '';
        $data['order']['account_period'] = $data['order']['account_period'] != 0 ? $data['order']['account_period'] : '';
        $data['order']['add_order_time'] = date('Y-m-d', strtotime($data['order']['add_order_time']));
        //最小退在途数量 = 总数量 - 已入库数量
        $data['order']['min_return_num'] = $data['order']['goods_num'] - getNum($data['order']['storage_quantity']);

        //可退货数量 = 已入库数量+在途数量
        $data['order']['can_return_goods_num'] = ($data['order']['goods_num']);

        $data['is_edit'] = $data['returned']['order_status'] == 1 ? 1 : 0; //只有未审核状态才能编辑
        if ($data['order']['is_upload_contract'] == 1) {
            $data['order']['contract_url'] = $this->uploads_path['purchase_attach']['url'] . $data['order']['contract_url'];
        }
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //------------------------end-----------------------------------------------------
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);
        $data['pay_type_list'] = purchasePayType();
        if ($data['order']['type'] == 1) {
            unset($data['pay_type_list'][4]);
        }
        //获取业务类型
        $data['order']['business_type_info'] = getBusinessType($data['order']['business_type']);
        $data['purchaseType'] = purchaseType();
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['return_type'] = returnType();
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 删除采购退货单
     */
    public function delReturnedPurchase(){
        if(IS_AJAX){
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpReturned')->delReturnedPurchase($id);
            $this->echoJson($data);
        }
    }

    /**
     * 审核采购退货单
     */
    public function auditReturnedPurchase(){
        if(IS_AJAX){
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpReturned')->auditReturnedPurchase($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认采购退货单
     */
    public function confirmReturnedPurchase(){
        if(IS_AJAX){
            $id = intval(I('param.id', 0));
            $data = $this->getEvent('ErpReturned')->confirmReturnedPurchase($id);
            $this->echoJson($data);
        }
    }

    /**
     * 上传凭证
     * @author xiaowen
     * @time 2017-8-31
     */
    public function uploadVoucher()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $param = I('param.attach');
            $data = $this->getEvent('ErpReturned')->uploadVoucher($id, $param);
            $this->echoJson($data);

        }

        $data = [];
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 采购退款申请
     * @author xiaowen
     * @time 2017-09-06
     */
    public function purchaseReturnPayment(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = $_POST;
            $data = $this->getEvent('ErpReturned')->applicationPayment($param);

            $this->echoJson($data);
        }
        $data['returned'] = $return_order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id'=>$id, 'order_type'=>2]);
        //获取交易单详细信息
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $return_order_info['source_order_id']], $field);

        $all_pay_money = $this->getModel('ErpPurchasePayment')->field('sum(pay_money) as total')->where(['purchase_order_number' => $data['returned']['order_number'], 'status' => ['neq', 2]])->group('purchase_order_number')->find();

        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['no_payed_money'] = ($data['order']['order_amount'] - getNum($data['order']['returned_goods_num']) * getNum($data['order']['price'])) - getNum($all_pay_money['total']);
        $data['order']['depot_name'] = $data['order']['depot_id'] == 99999 ? '不限油库' : $data['order']['depot_name'];
        $data['pay_type_list'] = purchasePayType();

        $region['region_list'] = provinceCityZone()['city'];

        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 验证采购退货单是否可能申请退款
     */
    public function returnedPurchaseCanPayment(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->returnedPurchaseCanPayment($id);
            $this->echoJson($data);
        }
    }
    /**
     * 返回退货单申请付款信息
     * @author xiaowen
     * @time 2017-9-7
     */
    public function getReturnedPurchaseOrderInfo()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->getReturnedPurchaseOrderInfo($id);
            $this->echoJson($data);
        }
    }

    /**
     * 导出采购退货
     * @author xiaowen
     * @time 2017-9-12
     */
    public function exportReturnedPurchase(){
        $param = I('param.');
        $param['export'] = 1;
        $search_type = intval(I('param.search_type', 1)); //默认导出所有

        $arr = [];
        if($search_type == 1){
            unset($param['search_type']);
        }else if($search_type == 2){ //导出我的出库单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        $data = $this->getEvent('ErpReturned')->purchaseReturnOrderList($param);
        if($data){
            foreach($data['data'] as $k=>$v){
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['order_time'] = $v['order_time'];
                $arr[$k]['order_number'] = $v['order_number'];
                $arr[$k]['source_order_number'] = $v['source_order_number'];
                $arr[$k]['business_type'] = $v['business_type'];
                $arr[$k]['region_name'] = $v['region_name'];
                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
                $arr[$k]['depot_name'] = $v['depot_name'];
                $arr[$k]['buyer_dealer_name'] = $v['buyer_dealer_name'];
                $arr[$k]['s_user_name'] = $v['s_user_name'];
                $arr[$k]['s_user_phone'] = $v['s_user_phone'] . "\t\n";
                $arr[$k]['s_company_name'] = $v['s_company_name'];
                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['return_type'] = strip_tags($v['return_type']);
                $arr[$k]['return_price'] = $v['return_price'];
                $arr[$k]['return_goods_num'] = $v['return_goods_num'];
                $arr[$k]['order_amount'] = $v['order_amount'];
                $arr[$k]['is_upload_voucher'] = strip_tags($v['is_upload_voucher']);
                $arr[$k]['order_status'] = strip_tags($v['order_status']);
                $arr[$k]['return_amount_status'] = strip_tags($v['return_amount_status']);
                $arr[$k]['remark'] = $v['remark'];
                $arr[$k]['creater_name'] = $v['creater_name'];
                $arr[$k]['create_time'] = $v['create_time'];
            }
        }

        $header = [
            'ID','单据日期','采购退货单号','来源单号','业务类型','城市','仓库','油库','交易员','客户','手机' ,'公司','商品','退货类型','单价','退货数量','退货金额','是否上传凭证','订单状态','退款状态','备注','创建人','创建时间'
        ];
        array_unshift($arr,  $header);
        $file_name_arr = [
            1 => '采购退货单'.currentTime().'.xls',
            2 => '我的采购退货单'.currentTime().'.xls',
        ];
        create_xls($arr, $filename=$file_name_arr[$search_type]);
    }

    /**---------------------------------------------------------------------//
    *
    * 销售退货单模块
    *
    **----------------------------------------------------------------------*/

    /**
     * 生成销售退货单
     * @author guanyu
     * @time 2017-8-16
     */
    public function addSaleReturn()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->addSaleReturn($param);
            $this->echoJson($data);
        }
        $data['today'] = date('Y-m-d');
        //$field = 'o.*,d.depot_name,cs.company_name,us.user_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';
        $field = 'o.*,d.depot_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';
        $data['order'] = $this->getEvent('ErpSale')->findOneSaleOrder($param['id'],$field);
        $userInfo = $this->getEvent('ErpCommon')->getUserData([$data['order']['user_id']], 1);
        $companyInfo = $this->getEvent('ErpCommon')->getCompanyData([$data['order']['company_id']], 1);
        $data['order']['user_name'] = $userInfo[$data['order']['user_id']]['user_name'];
        $data['order']['company_name'] = $companyInfo[$data['order']['company_id']]['company_name'];
        $data['order']['depot_name'] = $data['order']['depot_id'] == 99999 ? '不限油库' : $data['order']['depot_name'];
        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['min_returned_num'] = getNum($data['order']['buy_num'] - $data['order']['loss_num'] - $data['order']['outbound_quantity']);
        $data['order']['min_returned_num'] = $data['order']['min_returned_num'] <= 0 ? 0 : $data['order']['min_returned_num'];
        $data['order']['max_returned_num'] = getNum($data['order']['buy_num'] - $data['order']['loss_num']);
        $data['order']['outbound_quantity'] = getNum($data['order']['outbound_quantity']);
        $data['order']['buy_num'] = getNum($data['order']['buy_num']);
        $data['region_list'] = provinceCityZone()['city'];
        $data['return_type'] = returnType();
        $data['order']['business_type_info'] = getSaleOrderBusinessType($data['order']['business_type']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑销售退货单
     * @author guanyu
     * @time 2017-8-16
     */
    public function updateSaleReturn()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->updateSaleReturn($param);
            $this->echoJson($data);
        }
//        $field = 'ro.*,o.dealer_name,o.user_id,o.company_id,o.region,o.storehouse_id,o.depot_id,o.goods_id,o.price,
//        o.outbound_quantity,o.buy_num,o.loss_num,o.returned_goods_num,d.depot_name,c.company_name,u.user_name,g.goods_code,
//        g.goods_name,g.source_from,g.grade,g.level,s.storehouse_name';

        $field = 'ro.*,o.dealer_name,o.user_id,o.company_id,o.region,o.storehouse_id,o.depot_id,o.goods_id,o.price,
        o.outbound_quantity,o.buy_num,o.loss_num,o.returned_goods_num,d.depot_name,g.goods_code,
        g.goods_name,g.source_from,g.grade,g.level,s.storehouse_name';

        $param['order_type'] = 1;
        $data['order'] = $this->getEvent('ErpReturned')->findOneSaleReturnOrder($param,$field);
        $userInfo = $this->getEvent('ErpCommon')->getUserData([$data['order']['user_id']],$data['order']['order_type']);
        $companyInfo = $this->getEvent('ErpCommon')->getCompanyData([$data['order']['company_id']],$data['order']['order_type']);
        $data['order']['user_name'] = $userInfo[$data['order']['user_id']]['user_name'];
        $data['order']['company_name'] = $companyInfo[$data['order']['company_id']]['company_name'];
        $data['order']['depot_name'] = $data['order']['depot_id'] == 99999 ? '不限油库' : $data['order']['depot_name'];
        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['min_returned_num'] = getNum($data['order']['buy_num'] - $data['order']['loss_num'] - $data['order']['outbound_quantity']);
        $data['order']['min_returned_num'] = $data['order']['min_returned_num'] <= 0 ? 0 : $data['order']['min_returned_num'];
        $data['order']['max_returned_num'] = getNum($data['order']['buy_num'] - $data['order']['loss_num']);
        $data['order']['outbound_quantity'] = getNum($data['order']['outbound_quantity']);
        $data['order']['return_goods_num'] = getNum($data['order']['return_goods_num']);
        $data['order']['business_type_info'] = getSaleOrderBusinessType($data['order']['business_type']);
        $data['order']['buy_num'] = getNum($data['order']['buy_num']);
        $data['region_list'] = provinceCityZone()['city'];
        $data['return_type'] = returnType();

        $useBatchLists = $this->getEvent("ErpStockIn")->getSourceStockInList($data['order']['order_number']);
        $useBatchList = [] ;
        $batchInfo = [];
        $stockOutBatch = [] ;
        if(!empty($useBatchLists)) {
            foreach ($useBatchLists as $v) {
                $useBatchList[$v['batch_id']] = [
                    "status" => $v['storage_status'],
                    "userNum" => getNum($v['storage_num'])
                ];
            }

            $whereBatch['batchId'] = array_unique(array_column($useBatchLists, "batch_id"));
            $whereBatch['userBatchId'] = array_unique(array_column($useBatchLists, "batch_id"));
            $batchInfo = $this->getEvent("ErpBatch")->erpBatchList($whereBatch);
            //获取当前的销售单对应批次出库数量‘
            $stockOutField = "batch_id , sum(actual_outbound_num)" ;
            $stockOutWhere = [
                "source_number" => $data['order']['source_order_number'] ,
                "is_reverse" => 2 ,
                "reversed_status" => 2,
                "outbound_status" => ['neq' , 2]
            ];
            $groupBy = "batch_id";
            $stockOutBatch = $this->getEvent("ErpStock")->stockOutGetField($stockOutField , $stockOutWhere , $groupBy) ;
            if(!empty($stockOutBatch)){
                foreach ($stockOutBatch as &$stockValue){
                    $stockValue = getNum($stockValue) ;
                }
            }
        }
        $this->assign('userBatchList' , $batchInfo['data']);
        $this->assign("useBatchNum" , $useBatchList);
        $this->assign("useStockOurNum" , $stockOutBatch);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 销售退货单列表
     * @author guanyu
     * @time 2017-8-17
     */
    public function saleReturnOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['order_type'] = 1;
            $data = $this->getEvent('ErpReturned')->saleReturnOrderList($param);
            $this->echoJson($data);
        }

        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData();
        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($data['depots']);
        $new_storehouse = getStoreHouseToRegion($StoreHouseData);
        //转换油库和仓库数据为json格式，供页面使用
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);

        //查询页面所需要的销退单相关状态入类型数据
        $data['saleOrderStatus'] = saleOrderStatus();
        $data['provinceList'] = provinceCityZone()['province'];
        $data['return_type'] = returnType();
        //获取业务类型
        $data['business_type'] = getSaleOrderBusinessType();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        # 添加按钮权限 edit qianbin 2018-03-19
        $access_node = $this->getUserAccessNode('ErpReturned/saleReturnOrderList');
        $this->assign('access_node', json_encode($access_node));
        # end
        $this->assign('data', $data);

        $this->display();
    }

    /**
     * 销售退货单列表
     * @author guanyu
     * @time 2017-8-17
     */
    public function mySaleReturnOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['order_type'] = 1;
            $param['dealer_id'] = $this->getUserInfo('id');
            $data = $this->getEvent('ErpReturned')->saleReturnOrderList($param);
            $this->echoJson($data);
        }

        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData();
        //按地区重新组装油库和仓库数量
        $new_depots = getDepotToRegion($data['depots']);
        $new_storehouse = getStoreHouseToRegion($StoreHouseData);
        //转换油库和仓库数据为json格式，供页面使用
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);

        //查询页面所需要的销退单相关状态入类型数据
        $data['saleOrderStatus'] = saleOrderStatus();
        $data['provinceList'] = provinceCityZone()['province'];
        $data['return_type'] = returnType();
        //获取业务类型
        $data['business_type'] = getSaleOrderBusinessType();
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        $this->assign('data', $data);

        $this->display();
    }

    /**
     * 删除销售退货单
     * @author guanyu
     * @time 2017-08-29
     */
    public function delSaleReturn()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->delSaleReturn($id);
            $this->echoJson($data);
        }
    }

    /**
     * 审核销售退货单
     * @author guanyu
     * @time 2017-08-29
     */
    public function auditSaleReturn()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->auditSaleReturn($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认销售退货单
     * @author guanyu
     * @time 2017-08-29
     */
    public function confirmSaleReturn()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->confirmSaleReturn($id);
            $this->echoJson($data);
        }
    }

    /**
     * 导出销售退货单
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportSaleReturnOrderData()
    {
        set_time_limit(30000);
        //@ini_set('memory_limit', '256M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        //导出所有销售单
        if($search_type == 1){
            unset($param['search_type']);
        }else if($search_type == 2){ //导出我的销售单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        $param['export'] = 1;
        $param['order_type'] = 1;
        $data = $this->getEvent('ErpReturned')->saleReturnOrderList($param);
        $arr = [];
        if($data){
            foreach($data['data'] as $k=>$v){
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['add_order_time'] = $v['add_order_time'];
                $arr[$k]['return_goods_time'] = $v['return_goods_time'];
                $arr[$k]['order_number'] = $v['order_number'];
                $arr[$k]['source_order_number'] = $v['source_order_number'];
                $arr[$k]['business_type'] = $v['business_type'];
                $arr[$k]['region_name'] = $v['region_name'];
                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
                $arr[$k]['depot_name'] = $v['depot_name'];
                $arr[$k]['dealer_name'] = $v['dealer_name'];
                $arr[$k]['user_name'] = $v['user_name'];
                $arr[$k]['company_name'] = $v['company_name'];
                $arr[$k]['return_type'] = $v['return_type'];
                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['return_goods_num'] = $v['return_goods_num'];
                $arr[$k]['return_price'] = $v['return_price'];
                $arr[$k]['return_amount'] = $v['return_amount'];
                $arr[$k]['order_status_font'] = $v['order_status_font'];
                $arr[$k]['return_amount_status_font'] =  $v['return_amount_status_font'];
                $arr[$k]['remark'] = $v['remark'];
                $arr[$k]['refund_remark'] = $v['refund_remark'];
                $arr[$k]['creater_name'] =  $v['creater_name'];
                $arr[$k]['create_time'] =  $v['create_time'];
            }
        }
        $header = [
            'ID','单据日期','实际退货日期','销售退货单号','来源单号','业务类型','城市','仓库','油库','交易员','客户','公司','退货类型',
            '商品','退货数量','单价','退货金额','订单状态','退款状态','备注','退款走向备注','创建人','创建时间'
        ];
        array_unshift($arr,  $header);
        $file_name_arr = [
            1 => '销售单'.currentTime().'.xls',
            2 => '我的销售单'.currentTime().'.xls',
        ];
        create_xls($arr, $filename=$file_name_arr[$search_type]);
    }

    /**
     * 返回销售退货单信息及账户信息
     * @param $id
     * @return mixed
     * @auther qianbin
     */
    public function getReturnedSaleOrderInfo()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReturned')->getReturnedSaleOrderInfo($id);
            $this->echoJson($data);
        }
    }

}
