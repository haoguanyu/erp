<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpPurchaseController extends BaseController
{

    /**
     *采购单列表
     *DATE:2017-03-31 Time:11:00
     *Author: xiaowen <xiaowen@51zhaoyou.com>
     */
    public function purchaseOrderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpPurchase')->purchaseOrderList($param);
            $this->echoJson($data);
        }
        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);

        $new_depots = getDepotToRegion($data['depots']);
        $new_storehouse = getStoreHouseToRegion($StoreHouseData);

        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);

        $data['purchaseOrderStatus'] = purchaseOrderStatus();
        $data['purchasePayType'] = purchasePayType();
        $data['purchaseType'] = purchaseType();
        $data['purchasePayStatus'] = purchasePayStatus();
        $data['purchaseInvoiceStatus'] = purchaseInvoiceStatus();
        $data['purchaseContractStatus'] = purchaseContract();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['provinceList'] = provinceCityZone()['province'];
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['business_type'] = getBusinessType();
        //$data['companyList'] = getClientsData(1);
        //--------查询该菜单下的操作权限-----------------------------------------
        $access_node = $this->getUserAccessNode('ErpPurchase/purchaseOrderList');
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        $this->assign('access_node', json_encode($access_node));
        //-----------------------------------------------------------------------
        $this->assign('data', $data);
        $this->display();
    }

    /**
     *我的采购单
     *DATE:2017-03-29
     *Author: xiaowen
     *
     */
    public function myPurchaseOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['dealer_id'] = $this->getUserInfo('id');
            //print_r($param);
            $data = $this->getEvent('ErpPurchase')->purchaseOrderList($param);
            $this->echoJson($data);
        }
        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);

        $new_depots = getDepotToRegion($data['depots']);
        $new_storehouse = getStoreHouseToRegion($StoreHouseData);

        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);

        $data['purchaseOrderStatus'] = purchaseOrderStatus();
        $data['purchasePayType'] = purchasePayType();
        $data['purchaseType'] = purchaseType();
        $data['purchasePayStatus'] = purchasePayStatus();
        $data['purchaseInvoiceStatus'] = purchaseInvoiceStatus();
        $data['purchaseContractStatus'] = purchaseContract();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['provinceList'] = provinceCityZone()['province'];
        $data['business_type'] = getBusinessType();
        # 联动 - 市区数据
        # qianbin
        # 2017.07.25
        $data['city2'] = json_encode(cityLevelData()['city2']);
        # end
        //$data['companyList'] = getClientsData(1);
        //--------查询该菜单下的操作权限-----------------------------------------
        $access_node = $this->getUserAccessNode('ErpPurchase/myPurchaseOrderList');
        $this->assign('access_node', json_encode($access_node));
        //-----------------------------------------------------------------------
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 新增采购单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function addPurchaseOrder()
    {
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpPurchase')->addPurchaseOrder($param);

            $this->echoJson($data);
        }
        //获取采购单详细信息

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        $StoreHouseData = getStoreHouseData(['type'=>['neq','7']]);
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        $new_storehouse = [];
        $whole_country = [];
        $storehouse_type = erpStorehouseType();
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                if ($value['is_purchase'] == 1) {
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
        $data['pay_type_list'] = purchasePayType();
        $data['business_type'] = getBusinessType();
        // 清除内部交易单 选项
        unset($data['business_type'][6]);
        unset($data['pay_type_list'][4]);
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['myUsers'] = getUserByDealer($this->getUserInfo('dealer_name'));
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['my_region'] = $this->getModel('Dealer')->where(['id'=>$data['dealer_id']])->find()['region'];
        //$data['erp_company_id'] = session('erp_company_id');
        $this->assign('data', $data);
        $this->assign('my_region', $region['region_list'][$data['my_region']]);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 编辑采购单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function updatePurchaseOrder()
    {
        $id = intval(I('param.id', 0));
        $is_show = intval(I('param.is_show', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpPurchase')->updatePurchaseOrder($id, $param);

            $this->echoJson($data);
        }
        //获取采购单详细信息

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();


        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,s.data_source';

        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $id], $field);

        // 支持 内不交易单
        // ********** 更改前
        // $StoreHouseData = getStoreHouseData(['type'=>['neq','7']]);
        // ********** 更改后
        if ( $data['order']['business_type'] == 6 ) {
            $StoreHouseData = getStoreHouseData(['type'=>['eq','7']]);
        } else {
            $StoreHouseData = getStoreHouseData(['type'=>['neq','7']]);
        }
        
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }

        }

        $new_storehouse = [];
        $whole_country = [];
        $storehouse_type = erpStorehouseType();
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                // 支持内部交易单
                if ( $data['order']['business_type'] == 6 ) {
                    $new_storehouse[$value['region']][] = $value;
                }

                if($data['order']['type'] == 2){
                    if($value['type'] == 2){
                        $new_storehouse[$value['region']][] = $value;
                    }
                }else{
                    if ($value['is_purchase'] == 1) {
                        if ($value['whole_country'] == 1) {
                            array_push($whole_country,$value);
                        } elseif(in_array($value['type'], array_keys($storehouse_type))) {
                            $new_storehouse[$value['region']][] = $value;
                        }
                    }
                }
            }
            $new_storehouse[0] = $whole_country;
        }
        $data['order']['data_source'] = dataSourceName($data['order']['data_source']);
        //print_r($new_storehouse);
        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);

        $data['order']['buy_num_liter'] = getNum($data['order']['buy_num_liter']);
        $data['order']['price_liter'] = getNum($data['order']['price_liter']);

        $data['order']['prepay_ratio'] = $data['order']['prepay_ratio'] != 0 ? $data['order']['prepay_ratio'] : '';
        $data['order']['account_period'] = $data['order']['account_period'] != 0 ? $data['order']['account_period'] : '';
        $data['order']['add_order_time'] = date('Y-m-d', strtotime($data['order']['add_order_time']));

        $is_edit = 0;
        if ($data['order']['order_status'] <= 10 && $data['order']['is_special'] == 1 && $data['order']['pay_status'] == 1 && $data['order']['invoice_status'] == 1) {
            $is_edit = 1;
        } else if ($data['order']['order_status'] == 1) {
            $is_edit = 2;
        }
        if ($is_show) {
            $is_edit = 3;
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
        $data['business_type'] = getBusinessType();
        $data['purchaseType'] = purchaseType();
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        //当前登陆交易员与采购单所属交易员是否一致，判断是编辑模板还是详情模板
        $tpl = $data['order']['buyer_dealer_id'] == $this->getUserInfo('id') ? 'updatePurchaseOrder' : 'detailPurchaseOrder';
        $this->assign('is_show', $is_show);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display($tpl);

    }

    /**
     * 删除采购单
     * @author xiaowen
     * @time 2017-4-5
     */
    public function delPurchaseOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpPurchase')->delPurchaseOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 采购单审核时确认信息
     * 价格，吨数，产品
     * @author qianbin
     * @time 2017-07-24
     */
    public function purchaseOrderExamine(){
        $id   = intval(I('param.id', 0));
        $data = [];
        if(intval($id)>0) {
            # o.id,o.goods_num,o.price,d.depot_name,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level
            $field = 'o.id,o.goods_num,o.price,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
            s.supplier_name as company_name,o.region';
            $data  = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => intval($id)], $field);
            $data['price']             = getNum($data['price']);
            $data['goods_num']         = getNum($data['goods_num']);
            $data['region']            = provinceCityZone()['city'][intval($data['region'])];//getCompanyNameById(intval($data['region'])) ;
        }
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 审核采购单
     * @author xiaowen
     * @time 2017-4-5
     */
    public function auditPurchaseOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpPurchase')->auditPurchaseOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 确认采购单
     * @author xiaowen
     * @time 2017-4-5
     */
    public function confirmPurchaseOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpPurchase')->confirmPurchaseOrder($id);
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
            $data = $this->getEvent('ErpPurchase')->uploadContract($id, $param);

            $this->echoJson($data);
        }

        $data = [];
        $this->assign('data', $data);
        //$this->assign('region', $region);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 复制采购单
     * @author xiaowen
     * @time 2017-4-6
     */
    public function copyPurchaseOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $data = $this->getEvent('ErpPurchase')->copyPurchaseOrder($id);

            $this->echoJson($data);
        }
    }

    /**
     * 申请付款
     * @author senpei
     * @time 2017-04-06
     */
    public function applicationPayment()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpPurchase')->applicationPayment($param);

            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));
        //获取采购单详细信息
        $field = 'o.*,d.depot_name,es.storehouse_name,s.supplier_name s_company_name,su.user_name s_user_name,
        su.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $id], $field);

        $all_pay_money = $this->getModel('ErpPurchasePayment')->field('sum(pay_money) as total')->where(['purchase_id' => $data['order']['id'], 'status' => ['neq', 2]])->group('purchase_id')->find();

        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        //log_info('采购单' . print_r($data['order'], true));
        //log_info('采购退货金额' . getNum($data['order']['returned_goods_num']) * getNum($data['order']['price']));
        //log_info('采购总金额' . (getNum($data['order']['order_amount']) - getNum($data['order']['returned_goods_num']) * $data['order']['price']));
        //$data['order']['order_amount'] = getNum($data['order']['order_amount']) - getNum($data['order']['returned_goods_num']) * $data['order']['price'];
        $data['order']['no_payed_money'] = ($data['order']['order_amount'] - getNum($data['order']['returned_goods_num']) * getNum($data['order']['price'])) - getNum($all_pay_money['total']);
        $data['order']['depot_name'] = $data['order']['depot_id'] == 99999 ? '不限油库' : $data['order']['depot_name'];
        $data['pay_type_list'] = purchasePayType();

        $region['region_list'] = provinceCityZone()['city'];

        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 获取采购单状态
     * @author xiaowen
     * @time 2017-4-6
     */
    public function getPurchaseOrderStatus()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $status = $this->getEvent('ErpPurchase')->getPurchaseOrderStatus($id);
            $data['is_inner_order'] = 2;
            if ( !empty($status['retail_inner_order_number']) && $status['business_type'] == 6 ) {
                $data['is_inner_order'] = 1;
            }
            $data['status'] = $status['order_status'];
            $data['pay_type'] = $status['pay_type'];
            $data['pay_status'] = $status['pay_status'];
            $this->echoJson($data);
        }
    }

    /**
     * 返回申请付款及发票信息
     * @author senpai
     * @time 2017-4-14
     */
    public function getPurchaseOrderInfo()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $data = $this->getEvent('ErpPurchase')->getPurchaseOrderInfo($id);
            $this->echoJson($data);
        }
    }

    /**
     * 采购需求
     * @author senpai
     * @time 2017-05-08
     */
    public function purchaseRequireList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpPurchase')->purchaseRequireList($param);
            $this->echoJson($data);
        }
        $data['depots'] = getDepotData();
        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
        $new_depots = [];
        if ($data['depots']) {
            foreach ($data['depots'] as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }

        }
        $new_storehouse = [];
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                $new_storehouse[$value['region']][] = $value;
            }
        }
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);
        $data['regionList'] = provinceCityZone()['city'];
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 新增代采采购单
     * @author senpai
     * @time 2017-05-09
     */
    public function addActingPurchaseOrder()
    {
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpPurchase')->addActingPurchaseOrder($param);
            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));
        $field = 'o.*,d.depot_name,c.customer_name s_company_name,cu.user_name s_user_name,cu.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data = $this->getEvent('ErpSale')->findOneSaleOrder($id,$field);
        $data['price'] = getNum($data['price']);
        $data['prepay_ratio'] = getNum($data['prepay_ratio']);
        $data['buy_num'] = getNum($data['buy_num']-$data['acting_purchase_num']);
        $data['pay_type'] = saleToPurchasePayType($data['pay_type']);

        //获取采购单详细信息
        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        $new_storehouse = [];
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                if($value['type'] == 2)
                $new_storehouse[$value['region']][] = $value; //只显示代采仓库
            }
        }
        //------------------------当前的登陆的子公司帐号----------------------------------
        $erp_company_id = session('erp_company_id');
        $erp_company_name = session('erp_company_name');
        $data['ourCompany'][$erp_company_id] = $erp_company_name;
        //-------------------------end----------------------------------------------------
        $data['depots'] = json_encode($new_depots);
        $data['storehouse'] = json_encode($new_storehouse);
        $data['pay_type_list'] = purchasePayType();
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        $data['myUsers'] = getUserByDealer($this->getUserInfo('dealer_name'));
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        //$data['erp_company_id'] = session('erp_company_id');
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 采购计划
     * @author senpai
     * @time 2017-05-08
     */
    public function purchasePlanList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpPurchase')->purchasePlanList($param);
            $this->echoJson($data);
        }
        $StoreHouseData = getStoreHouseData(['type'=>['neq',7]]);
        $new_storehouse = [];
        if ($StoreHouseData) {
            foreach ($StoreHouseData as $key => $value) {
                $new_storehouse[$value['region']][] = $value;
            }
        }
        $data['storehouse'] = json_encode($new_storehouse);
        $data['regionList'] = provinceCityZone()['city'];
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        //$data['companyList'] = getClientsData(1);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 采购计划详情
     * @author senpai
     * @time 2017-05-08
     */
    public function purchasePlanDetail()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpPurchase')->purchasePlanDetail($param);
            $this->echoJson($data);
        }
    }

    /**
     * 导出采购单
     * @author senpei
     * @time 2017-06-01
     */
    public function exportPurchaseOrderData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        //导出所有销售单
        if ($search_type == 1) {
            unset($param['search_type']);
        } else if($search_type == 2) { //导出我的采购单
            unset($param['search_type']);
            $param['dealer_id'] = $this->getUserInfo('id');
        }
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name_arr = [
            1 => '采购单'  . currentTime('Ymd').'.csv',
            2 => '我的采购单'  . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[$search_type]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','采购单号','订单时间','采购员','采购类型','业务类型','供应商用户','供应商公司','城市','仓库','油库',
            '付款方式','是否特需','商品','单价(吨)','单价(升)','数量(吨)','数量（升）','入库数量(吨)','退货数量(吨)',
            '订单金额(元)','订单总额（元）','上传合同','已付金额(元)','未付金额(元)','开票金额(元)','订单状态','收款状态',
            '发票状态','来源销售单号','内部交易单号','创建人','创建时间','是否作废'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while (($data = $this->getEvent('ErpPurchase')->purchaseOrderList($param)['data']) && ($count = $this->getEvent('ErpPurchase')->purchaseOrderList($param)['recordsTotal'])) {
            $arr = [];
            foreach ($data as $k=>$v) {
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['order_number'] = $v['order_number'];
                $arr[$k]['add_order_time'] = $v['add_order_time'];
                $arr[$k]['buyer_dealer_name'] = $v['buyer_dealer_name'];
                $arr[$k]['type_font'] = $v['type_font'];
                $arr[$k]['business_type'] = $v['business_type'];
                $arr[$k]['s_user_name'] = $v['s_user_name'];
                $arr[$k]['s_company_name'] = $v['s_company_name'];
                $arr[$k]['region_name'] = $v['region_name'];
                $arr[$k]['storehouse_name'] = $v['storehouse_name'];
                $arr[$k]['depot_name'] = $v['depot_name'];
                $arr[$k]['pay_type'] = $v['pay_type'];
                $arr[$k]['is_special'] = $v['is_special'] == 1 ? '是' : '否';
                $arr[$k]['goods_code'] = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['price'] = $v['price'];
                $arr[$k]['price_liter'] = $v['price_liter'];
                $arr[$k]['goods_num'] = $v['goods_num'];
                $arr[$k]['buy_num_liter'] = $v['buy_num_liter'];
                $arr[$k]['storage_quantity'] = $v['storage_quantity'];
                $arr[$k]['returned_goods_num'] = empty($v['returned_goods_num']) ? '0' : $v['returned_goods_num'] ;
                $arr[$k]['order_amount'] = $v['order_amount'];
                $arr[$k]['total_order_amount'] = $v['total_order_amount'];
                $arr[$k]['is_upload_contract'] = strip_tags($v['is_upload_contract']);
                $arr[$k]['payed_money'] =  $v['payed_money'];
                $arr[$k]['no_payed_money'] =  empty($v['no_payed_money']) ? '0' : $v['no_payed_money'];
                $arr[$k]['invoice_money'] =  $v['invoice_money'];
                $arr[$k]['order_status'] = strip_tags($v['order_status']);
                $arr[$k]['pay_status'] = strip_tags($v['pay_status']);
                $arr[$k]['invoice_status'] = strip_tags($v['invoice_status']);
                $arr[$k]['from_sale_order_number'] = strip_tags($v['from_sale_order_number']);
                $arr[$k]['retail_inner_order_number'] = strip_tags($v['retail_inner_order_number']);
                $arr[$k]['creater_name'] = strip_tags($v['creater_name']);
                $arr[$k]['create_time'] = strip_tags($v['create_time']);
                $arr[$k]['is_void'] = strip_tags($v['is_void']);
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
     * 采购单回滚
     * @author senpei
     * @time 2017-06-20
     */
    public function rollBackPurchaseOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpPurchase')->rollBackPurchaseOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 采购单二次定价界面
     * @author xiaowen
     * @time 2017-7-14
     */
    public function updatePrice(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX){

            $param = I('param.');
            $data = $this->getEvent('ErpPurchase')->updatePrice($param);
            $this->echoJson($data);
        }
        $data['order'] = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id'=>intval($id)]);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['price'] = getNum($data['order']['price']);

        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 二次定价 操作列表
     * @author xiaowen
     * @time 2017-7-14
     */
    public function updatePriceLogList(){
        $id = intval(I('post.order_id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpPurchase')->getUpdatePriceLogList($id);
            $this->echoJson($data);
        }
    }

    /**
     * 检验采购单是否可退货
     * @author xiaowen
     * @time 2017-8-24
     * @return array
     */
    public function checkOrderCanReturn(){
        $id = intval(I('param.id', 0));
        $data = $this->getEvent('ErpPurchase')->checkOrderCanReturn($id);
        $this->echoJson($data);
    }

    /**
     * 验证采购单是否使用余额抵扣
     * @param $order_info
     * @author guanyu
     * @time 2017-11-21
     * @return bool
     */
    public function checkBalanceDeduction(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpPurchase')->checkBalanceDeduction($id);
            $this->echoJson($data);
        }
    }
    /**
     * 采购单（加油站业务）获取密度配置
     * @author qianbin
     * @time 2018-05-09
     * @return bool
     */
    public function getConfig(){
        if (IS_AJAX) {
            $data['status']          = 1 ;
            $data['message']         = '系统正常';
            $data['config_density']  = getConfig('Config_Density');
            if(empty($data['config_density'])){
                $data['status']  =  2;
                $data['message'] = '系统配置密度有误，请检查！';
            }
            $this->echoJson($data);
        }
    }


}
