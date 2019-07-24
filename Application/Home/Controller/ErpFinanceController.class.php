<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpFinanceController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP财务逻辑层
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------

    // +----------------------------------
    // |Facilitator:应付列表页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function erpPurchasePaymentList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpPurchasePaymentList($param);
            $this->echoJson($data);
        }
        $access_node = $this->getUserAccessNode('ErpFinance/erpPurchasePaymentList');
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:应付列表页面及数据（内容跟上面方法一样，只是为了避开权限）
    // +----------------------------------
    // |Author:senpai Time:2017.06.12
    // +----------------------------------
    public function erpPurchasePaymentDetail()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpPurchasePaymentList($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:发票列表页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function erpPurchaseInvoiceList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpPurchaseInvoiceList($param);
            $this->echoJson($data);
        }
        $data['regionList'] = provinceCityZone()['city'];
        $this->assign('data',$data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:发票列表页面及数据（申请时展示）
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function erpPurchaseShowInvoiceList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpPurchaseInvoiceList($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:付款确认操作
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function paymentConfirmation()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->paymentConfirmation($param);
            $this->echoJson($data);
        }
        $this->assign('id', $id);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:付款同意操作
    // +----------------------------------
    // |Author:senpai Time:2017.05.08
    // +----------------------------------
    public function paymentAgree()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->paymentAgree($param);
            $this->echoJson($data);
        }
        $this->assign('id', $id);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:付款驳回操作
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function paymentReject()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->paymentReject($param);
            $this->echoJson($data);
        }
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 发票录入
     * @author senpei
     * @time 2017-04-06
     */
    public function addPurchaseInvoice()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpFinance')->addPurchaseInvoice($param);

            $this->echoJson($data);
        }
        $id = intval(I('param.id', 0));
        //获取采购单详细信息
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $id], $field);

        $all_invoice_money = $this->getModel('ErpPurchaseInvoice')->field('sum(apply_invoice_money) as total')->where(['purchase_id' => $data['order']['id'], 'status' => ['neq', 2]])->group('purchase_id')->find();

        $data['order']['price'] = getNum($data['order']['price']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['no_invoice_money'] = $data['order']['order_amount'] - getNum($all_invoice_money['total']);
        $data['order']['depot_name'] = $data['order']['depot_id'] == 99999 ? '不限油库' : $data['data']['depot_name'];
        $data['pay_type_list'] = purchasePayType();
        $data['invoice_type'] = invoiceType();
        $data['invoiceTaxRate'] = json_encode(invoiceTaxRate());
        $region['region_list'] = provinceCityZone()['city'];

        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:发票确认操作
    // +----------------------------------
    // |Author:senpai Time:2017.4.5
    // +----------------------------------
    public function purchaseInvoiceConfirmation()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->purchaseInvoiceConfirmation($param);
            $this->echoJson($data);
        }
        $this->assign('id', $id);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:发票驳回操作
    // +----------------------------------
    // |Author:senpai Time:2017.4.5
    // +----------------------------------
    public function purchaseInvoiceReject()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->purchaseInvoiceReject($param);
            $this->echoJson($data);
        }
        $this->assign('id', $id);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:应收列表页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.04.17
    // +----------------------------------
    public function erpSaleCollectionList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            //$data = $this->getEvent('ErpFinance')->erpSaleCollectionList($param);
            $data = $this->getEvent('ErpFinance')->newErpSaleCollectionList($param);
            $this->echoJson($data);
        }
        $access_node = $this->getUserAccessNode('ErpFinance/erpSaleCollectionList');
        $this->assign('data_node',$access_node);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:应收详情页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.04.17
    // +----------------------------------
    public function erpSaleCollectionDetail()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpSaleCollectionDetail($param);
            $this->echoJson($data);
        }
        $access_node = $this->getUserAccessNode('ErpFinance/erpSaleCollectionList');
        # $order_type 0 销售 1 销退
        # 对应银行信息：     收付类型: 1 收款、2 付款
        $data['order_type'] =  intval(I('order_type', '', 'htmlspecialchars')) == 0 ? 1 : 2 ;
        $data['bank']       = $this->getEvent('ErpBank')->getErpBankList($data['order_type']);
        $data['bank_json']  = json_encode($data['bank'],true);
        # 渲染模板文案
        $data['apply_button'] = $data['order_type'] == 1 ? '收':'退';
        $this->assign('data_node',$access_node);
        $this->assign('access_node', json_encode($access_node));
        $this->assign('sale_order_id',$id);
        $this->assign('data',$data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:损耗录入详情页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.05.25
    // +----------------------------------
    public function erpSaleLossDetail()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpSaleLossDetail($param);
            $this->echoJson($data);
        }
        $this->assign('sale_order_id',$id);
        $this->display();
    }

    // +----------------------------------------------------------
    // |Facilitator:确认收款（可批量） 整单收款功能去掉，此方法已取消
    // +----------------------------------------------------------
    // |Author:senpai Time:2017.04.19
    // +----------------------------------------------------------
    public function confirmSaleCollection()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->confirmSaleCollection($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:添加收款记录
    // +----------------------------------
    // |Author:senpai Time:2017.04.19
    // +----------------------------------
    public function addSaleCollection()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->addSaleCollection($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:添加损耗退款记录
    // +----------------------------------
    // |Author:senpai Time:2017.05.25
    // +----------------------------------
    public function addSaleLoss()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->addSaleLoss($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:发票列表页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function erpSaleInvoiceList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpSaleInvoiceList($param);
            $this->echoJson($data);
        }
        $data['invoice_list'] = saleInvoiceStatus();
        $data['collection_list'] = saleCollectionStatus();
        $data['pay_type_list'] = saleOrderPayType();

        $this->assign('data', $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:发票详情页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.04.17
    // +----------------------------------
    public function erpSaleInvoiceDetail()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpSaleInvoiceDetail($param);
            $this->echoJson($data);
        }
        $data['invoice_type'] = array_slice(invoiceType(),0,6,true);
        $this->assign('data', $data);
        $this->assign('sale_order_id',$id);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:确认开票（可批量）
    // +----------------------------------
    // |Author:senpai Time:2017.04.19
    // +----------------------------------
    public function confirmSaleInvoice()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->confirmSaleInvoice($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:添加收款记录
    // +----------------------------------
    // |Author:senpai Time:2017.04.19
    // +----------------------------------
    public function addSaleInvoice()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->addSaleInvoice($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:发票信息（录入发票号和发票类型）
    // +----------------------------------
    // |Author:senpai Time:2017.04.19
    // +----------------------------------
    public function erpSaleInvoiceInfo()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpSaleInvoiceInfo($param);
            $this->echoJson($data);
        }
        $data = $this->getModel('ErpSaleInvoice')->findSaleInvoice(['sale_order_id' => $id]);
        # 根据对应发票类型，列出16%、17%所有发票类型，提前对应的类型
        $invoice_type = invoiceType();
        //$data['invoice_all_type'] = in_array($data['invoice_type'],[1,2,3]) ? array_slice($invoice_type,0,6,true) : array_slice($invoice_type ,3,3,true);
        $data['invoice_all_type'] = array_slice($invoice_type,0,6,true);
        $this->assign('data',$data);
        $this->assign('sale_order_id',$id);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:判断是否整单开票
    // +----------------------------------
    // |Author:senpai Time:2017.04.24
    // +----------------------------------
    public function getSaleOrderStatus()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->getSaleOrderStatus($param);
            $this->echoJson($data);
        }
    }

    /**
     * Excel导出应收列表
     * @author senpai
     * @time 2017-04-21
     */
    public function exportCollection()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        if ($param['order_number'] == 'null') {
            $param['order_number'] = '';
        }
        if ($param['start_time'] == 'null') {
            $param['start_time'] = '';
        }
        if ($param['end_time'] == 'null') {
            $param['end_time'] = '';
        }
        if ($param['sale_company_id'] == 'null') {
            $param['sale_company_id'] = '';
        }
        if($param['put_start_time'] == 'null'){
            $param['put_start_time']= '';
        }
        if($param['put_end_time'] == 'null'){
            $param['put_end_time']= '';
        }
        if ($param['dealer_id'] == 'null') {
            $param['dealer_id'] = '';
        }
        if ($param['collection_status'] == 'null') {
            $param['collection_status'] = '';
            $param['collection'] = 1;
        }
        if ($param['is_loss'] == 'null') {
            $param['is_loss'] = '';
        }
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '应收列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header=[
            '销售单号','订单日期','付款方式','交易员','城市','客户','开户银行','银行账号','商品代码','单价(元)','数量(吨)',
            '运费(元)','订单金额(元)','已收金额(元)','未收金额(元)','','收款状态','损耗状态','损耗金额（元）','已退损耗（元）',
            '操作员(财务)','是否作废'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        $data = $this->getEvent('ErpFinance')->newErpSaleCollectionList($param)['data'];
        while(($data = $this->getEvent('ErpFinance')->newErpSaleCollectionList($param)['data']) && ($count = $this->getEvent('ErpFinance')->newErpSaleCollectionList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['order_number']            = $v['order_number'];
                $arr[$k]['add_order_time']          = $v['add_order_time'];
                $arr[$k]['pay_type']                = $v['pay_type'];
                $arr[$k]['dealer_name']             = $v['dealer_name'];
                $arr[$k]['region_name']             = $v['region_name'];
                $arr[$k]['s_company_name']          = $v['s_company_name'];
                $arr[$k]['user_bank_name']          = $v['user_bank_info'];
                $arr[$k]['user_bank_num']           = $v['user_bank_info'];
                $arr[$k]['goods_code']              = $v['goods_info'];
                $arr[$k]['price']                   = $v['price'];
                $arr[$k]['buy_num']                 = $v['buy_num'];
                $arr[$k]['delivery_money']          = $v['delivery_money'];
                $arr[$k]['order_amount']            = $v['order_amount'];
                $arr[$k]['collected_amount']        = $v['collected_amount'];
                $arr[$k]['no_collect_amount']       = $v['order_amount'] - $v['collected_amount'] > 0 ? $v['order_amount'] - $v['collected_amount'] : '0';
                $arr[$k]['order_status']            = strip_tags($v['order_status']);
                $arr[$k]['collection_status_font']  = $v['collection_status_font'];
                $arr[$k]['is_loss']                 = $v['is_loss_font'];
                $arr[$k]['loss_amount']             = $v['loss_amount'];
                $arr[$k]['entered_loss_amount']     = $v['entered_loss_amount'];
                $arr[$k]['c_creator']               = $v['c_creator'];
                # $arr[$k]['collect_time']            = $v['collect_time'];
                $arr[$k]['is_void']                 = strip_tags($v['is_void']);
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
//     * Excel导出应收列表
//     * @author senpai
//     * @time 2017-04-21
//     */
//    public function exportCollection(){
//        $param = I('get.');
//        if ($param['order_number'] == 'null') {
//            $param['order_number'] = '';
//        }
//        if ($param['start_time'] == 'null') {
//            $param['start_time'] = '';
//        }
//        if ($param['end_time'] == 'null') {
//            $param['end_time'] = '';
//        }
//        if ($param['sale_company_id'] == 'null') {
//            $param['sale_company_id'] = '';
//        }
//        if($param['put_start_time'] == 'null'){
//            $param['put_start_time']= '';
//        }
//        if($param['put_end_time'] == 'null'){
//            $param['put_end_time']= '';
//        }
//        if ($param['dealer_id'] == 'null') {
//            $param['dealer_id'] = '';
//        }
//        if ($param['collection_status'] == 'null') {
//            $param['collection_status'] = '';
//            $param['collection'] = 1;
//        }
//        if ($param['is_loss'] == 'null') {
//            $param['is_loss'] = '';
//        }
//        $param['export'] = 1;
//        //$data = $this->getEvent('ErpFinance')->erpSaleCollectionList($param);
//        $data = $this->getEvent('ErpFinance')->newErpSaleCollectionList($param);
//        $arr     = [];
//        foreach ($data['data'] as $k => $v) {
//            $arr[$k]['order_number']        = $v['order_number'];
//            $arr[$k]['add_order_time']      = $v['add_order_time'];
//            $arr[$k]['pay_type']            = $v['pay_type'];
//            $arr[$k]['dealer_name']         = $v['dealer_name'];
//            $arr[$k]['s_company_name']      = $v['s_company_name'];
//            $arr[$k]['user_bank_name']      = $v['user_bank_info'];
//            $arr[$k]['user_bank_num']       = $v['user_bank_info'];
//            //$arr[$k]['goods_code']          = $v['goods_code'] . '/' .$v['source_from']. '/' .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
//            $arr[$k]['goods_code']          = $v['goods_info'];
//            $arr[$k]['price']               = $v['price'];
//            $arr[$k]['buy_num']             = $v['buy_num'];
//            $arr[$k]['delivery_money']      = $v['delivery_money'];
//            $arr[$k]['order_amount']        = $v['order_amount'];
//            $arr[$k]['collected_amount']    = $v['collected_amount'];
//            //$arr[$k]['no_collect_amount']   = $v['order_amount'] - $v['collected_amount'] > 0 ? $v['order_amount'] - $v['collected_amount'] : '0';
//            $arr[$k]['no_collect_amount']   = $v['no_collect_amount'] > 0 ? $v['no_collect_amount'] : '0';
//            //$arr[$k]['collection_status_font']   = $v['collection_status_font'];
//            $arr[$k]['collection_status']   = strip_tags($v['collection_status']);
//            $arr[$k]['is_loss']             = $v['is_loss_font'];
//            $arr[$k]['loss_amount']         = $v['loss_amount'];
//            $arr[$k]['entered_loss_amount'] = $v['entered_loss_amount'];
//            $arr[$k]['c_creator']           = $v['c_creator'];
//            $arr[$k]['collect_time']        = $v['collect_time'];
//            $arr[$k]['is_void']             = strip_tags($v['is_void']);
//        }
//        $header=['销售单号','订单日期','付款方式','交易员','客户','开户银行','银行账号','商品代码','单价(元)','数量(吨)','运费(元)','订单总额(元)','已收金额(元)','未收金额(元)','收款状态','损耗状态','损耗金额（元）','已退损耗（元）','操作员(财务)','收款时间','是否作废'];
//        array_unshift($arr,$header);
//        create_xls($arr,$filename='应收列表'.currentTime().'.xls');
//    }

    /**
     * Excel导出销售发票列表
     * @author senpai
     * @time 2017-04-21
     */
    public function exportInvoice()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        if ($param['order_number'] == 'null') {
            $param['order_number'] = '';
        }
        if ($param['start_time'] == 'null') {
            $param['start_time'] = '';
        }
        if ($param['end_time'] == 'null') {
            $param['end_time'] = '';
        }
        if ($param['sale_company_id'] == 'null') {
            $param['sale_company_id'] = '';
        }
        if ($param['dealer_id'] == 'null') {
            $param['dealer_id'] = '';
        }
        if ($param['collection_status'] == 'null') {
            $param['collection_status'] = '';
        }
        if ($param['invoice_status'] == 'null') {
            $param['invoice_status'] = '';
            $param['invoice'] = 1;
        }
        if ($param['pay_type'] == 'null') {
            $param['pay_type'] = '';
        }
        if ($param['is_update_price'] == 'null') {
            $param['is_update_price'] = '';
        }

        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '销售发票' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header=[
            '销售单号','订单日期','交易员','客户','国税号','开户银行','银行账号','注册信息','商品代码','单价(元)',
            '数量(吨)','退货数量(吨)','实际销售数量','运费(元)','订单总额(元)','已开发票金额(元)','未开发票金额(元)',
            '收款状态','开票状态','收款方式','开票特殊要求','发票号码'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpFinance')->erpSaleInvoiceList($param)['data']) && ($count = $this->getEvent('ErpFinance')->erpSaleInvoiceList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['order_number']            = $v['order_number'];
                $arr[$k]['add_order_time']          = $v['add_order_time'];
                $arr[$k]['dealer_name']             = $v['dealer_name'];
                $arr[$k]['s_company_name']          = $v['s_company_name'];
                $arr[$k]['tax_num']                 = "\t".$v['tax_num'];
                $arr[$k]['user_bank_name']          = $v['bank_name'];
                $arr[$k]['user_bank_num']           = "\t".$v['bank_num'];
                $arr[$k]['company_info']            = $v['company_info'];
                $arr[$k]['goods_code']              = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['price']                   = $v['price'];
                $arr[$k]['buy_num']                 = $v['buy_num'];
                $arr[$k]['returned_goods_num']      = $v['returned_goods_num'];
                $arr[$k]['actual_goods_num']        = $v['actual_goods_num'] <= 0 ? '0' : $v['actual_goods_num'];
                $arr[$k]['delivery_money']          = $v['delivery_money'];
                $arr[$k]['total_amount']            = $v['total_amount'];
                $arr[$k]['invoiced_amount']         = $v['invoiced_amount'];
                $arr[$k]['no_invoice_amount']       = $v['total_amount'] - $v['invoiced_amount'];
                $arr[$k]['collection_status_font']  = $v['collection_status_font'];
                $arr[$k]['invoice_status_font']     = $v['invoice_status_font'];
                $arr[$k]['pay_type']                = $v['pay_type'];
                $arr[$k]['invoice_remark']          = $v['invoice_remark'];
                $arr[$k]['invoice_sn']              = "\t".$v['invoice_sn'];
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
     * 导出应付列表
     * @author senpei
     * @time 2017-06-01
     */
    public function exportPaymentData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '应付列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','业务单号','来源单号','城市','仓库','银行简称','供应商（公司）','对方开户银行','对方银行账号','付款方式','商品','单价(元)','申请付款金额','余额抵扣',
            '实际付款','付款金额','订单总额','申请付款人（采购员）','申请付款状态','申请时间','申请付款日期','实际付款时间','申请备注','财务备注'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpFinance')->erpPurchasePaymentList($param)['data']) && ($count = $this->getEvent('ErpFinance')->erpPurchasePaymentList($param)['recordsTotal'])){
            $arr              = [] ;
            $pay_money        = 0 ;
            $actual_pay_money = 0 ;
            foreach($data as $k=>$v){
                $arr[$k]['id']                      = $v['id'];
                $arr[$k]['purchase_order_number']   = $v['purchase_order_number'];
                $arr[$k]['from_sale_order_number']  = $v['from_sale_order_number'];
                $arr[$k]['region_font']             = $v['region_font'];
                $arr[$k]['storehouse_name']         = $v['storehouse_name'];
                $arr[$k]['bank_simple_name']        = $v['bank_simple_name'];
                $arr[$k]['sale_company_name']       = $v['sale_company_name'];
                $arr[$k]['sale_collection_name']    = substr($v['sale_collection_info'],0,strpos($v['sale_collection_info'],'--'));
                $arr[$k]['sale_collection_num']     = "\t".substr($v['sale_collection_info'],strpos($v['sale_collection_info'],'--')+2);
                $arr[$k]['pay_type']                = $v['pay_type'];
                # -------------------导出增加商品和单价<qianbin> 2017/07/17--------------------
                $arr[$k]['goods']                   = $v['goods']['goods_code'].'/'.$v['goods']['source_from'].'/'.$v['goods']['goods_name'].'/'.$v['goods']['grade'].'/'.$v['goods']['level'];
                $arr[$k]['price']                   = $v['price'];
                # 应付管理中导出字段（申请付款金额，实际付款）按以下要求显示
                # 1）采购单的正向申请付款 显示 正数
                # 2）采购单的红冲付款 显示 负数
                # 3）采退单的正向申请付款（实际为需要退款）显示负数
                # 4）采退单的付款红冲 显示 正数

                # 注：红冲申请付款字段，在数据库存的是负数
                if($v['source_order_type'] == 1){

                    $pay_money        = $v['is_reverse'] == 1 ? '-'.abs($v['pay_money']) : $v['pay_money'];
                    $actual_pay_money = $v['is_reverse'] == 1 ? '-'.abs($v['actual_pay_money']) : $v['actual_pay_money'];
                }else if($v['source_order_type'] == 2){

                    $pay_money        = $v['is_reverse'] == 1 ? abs($v['pay_money']) : '-'.$v['pay_money'];
                    $actual_pay_money = $v['is_reverse'] == 1 ? abs($v['actual_pay_money']) : '-'.$v['actual_pay_money'];
                }
                $arr[$k]['pay_money']               = $pay_money ;
                $arr[$k]['balance_deduction']       = $v['balance_deduction'];
                $arr[$k]['actual_pay_money']        = $actual_pay_money;
                $arr[$k]['payed_money']             = $v['payed_money'];
                $arr[$k]['order_amount']            = $v['order_amount'];
                $arr[$k]['buyer_dealer_name']       = $v['buyer_dealer_name'];
                $arr[$k]['status_font']             = $v['status'];
                $arr[$k]['create_time']             = $v['create_time'];
                $arr[$k]['apply_pay_time']          = $v['apply_pay_time'];
                $arr[$k]['pay_time']                = $v['pay_time'];
                $arr[$k]['remark']                  = strip_tags($v['remark']);
                $arr[$k]['audit_remark']            = strip_tags($v['audit_remark']);
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
     * 导出采购发票列表
     * @author senpei
     * @time 2017-06-01
     */
    public function exportInvoiceData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '采购发票列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','采购单号','城市','供应商（公司）','发票号码','订单金额','订单总额','已录发票金额','申请录入金额','不含税金额','税额',
            '发票类型','商品代码','数量','操作员（采购员）','发票审核人','发票录入时间','发票审核时间','申请备注','财务备注'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpFinance')->erpPurchaseInvoiceList($param)['data']) && ($count = $this->getEvent('ErpFinance')->erpPurchaseInvoiceList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['purchase_order_number']   = $v['purchase_order_number'];
                $arr[$k]['region_font']             = $v['region_font'];
                $arr[$k]['sale_company_name']       = empty($v['sale_company_name']) ? '-' : $v['sale_company_name'];
                $arr[$k]['invoice_sn']              = empty($v['invoice_sn']) ? '-' : "\t".$v['invoice_sn'] ;
                $arr[$k]['order_amount']            = $v['order_amount'];
                $arr[$k]['total_order_amount']      = $v['total_order_amount'];
                $arr[$k]['invoice_money']           = empty($v['invoice_money']) ? '0' : $v['invoice_money'];
                $arr[$k]['apply_invoice_money']     = empty($v['apply_invoice_money']) ? '0' : $v['apply_invoice_money'];
                $arr[$k]['notax_invoice_money']     = $v['notax_invoice_money'];
                $arr[$k]['tax_money']               = $v['tax_money'];
                $arr[$k]['invoice_type_font']       = $v['invoice_type_font'];
                $arr[$k]['invoice_goods']           = "\t" .$v['goods_code'] .'/'.$v['source_from'] .'/'. $v['goods_name'] .'/'. $v['grade'] .'/'. $v['level'];
                $arr[$k]['invoice_goods_num']       = getNum($v['goods_num']);
                $arr[$k]['buyer_dealer_name']       = $v['buyer_dealer_name'];
                $arr[$k]['auditor']                 = $v['auditor'];
                $arr[$k]['create_time']             = $v['create_time'];
                $arr[$k]['audit_time']              = $v['audit_time'] =='0000-00-00 00:00:00' ? '-' : $v['audit_time'];
                $arr[$k]['remark']                  = strip_tags($v['remark']);
                $arr[$k]['audit_remark']            = strip_tags($v['audit_remark']);
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
     * 预存收款列表
     * @author guanyu
     * @time 2017-10-31
     */
    public function prestoreCollectionOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 1;
            $data = $this->getEvent('ErpFinance')->prestoreCollectionOrderList($param);
            $this->echoJson($data);
        }

        //查询页面所需要的销售单相关状态入类型数据
        $data['RechargeOrderStatus'] = RechargeOrderStatus();
        $data['RechargeFinanceStatus'] = RechargeFinanceStatus();
        $data['PrestoreType'] = PrestoreType();
        $data['regionList'] = provinceCityZone()['city'];
        $access_node = $this->getUserAccessNode('ErpRecharge/RechargeOrderList');
        //print_r($per);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 确认预存收款
     * @author guanyu
     * @time 2017-11-10
     */
    public function confirmPrestoreCollection()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpFinance')->confirmPrestoreCollection($param);
            $this->echoJson($data);
        }
    }

    /**
     * 驳回预存收款
     * @author guanyu
     * @time 2017-11-10
     */
    public function rejectPrestoreCollection()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpFinance')->rejectPrestoreCollection($id);
            $this->echoJson($data);
        }
    }

    /**
     * 预付应付列表
     * @author guanyu
     * @time 2017-11-12
     */
    public function prepayPaymentOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 2;
            $data = $this->getEvent('ErpFinance')->prepayPaymentOrderList($param);
            $this->echoJson($data);
        }

        //查询页面所需要的销售单相关状态入类型数据
        $data['RechargeOrderStatus'] = RechargeOrderStatus();
        $data['RechargeFinanceStatus'] = RechargeFinanceStatus();
        $data['PrepayType'] = PrepayType();
        $data['regionList'] = provinceCityZone()['city'];
        $access_node = $this->getUserAccessNode('ErpRecharge/RechargeOrderList');
        //print_r($per);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 确认预付付款
     * @author guanyu
     * @time 2017-11-10
     */
    public function confirmPrepayPayment()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpFinance')->confirmPrepayPayment($param);
            $this->echoJson($data);
        }
    }

    /**
     * 驳回预付付款
     * @author guanyu
     * @time 2017-11-10
     */
    public function rejectPrepayPayment()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpFinance')->rejectPrepayPayment($id);
            $this->echoJson($data);
        }
    }

    /**
     * 验证账户
     * @author guanyu
     * @time 2017-11-12
     */
    public function checkAccount()
    {
        $id = intval(I('post.id', 0));
        $account_type = intval(I('post.account_type', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpFinance')->checkAccount($id,$account_type);
            $this->echoJson($data);
        }
    }

    /**
     * 预付余额抵扣
     * @author guanyu
     * @time 2017-11-12
     */
    public function prepayPaymentConfirmation()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        /* 经查询，此段代码暂时没有用到，注释 qianbin 2018.08.08
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->prepayPaymentConfirmation($param);
            $this->echoJson($data);
        }
        */
        $data         = $this->getEvent('ErpFinance')->getPrepayPaymentInfo($id);
        # source_order_type 1 采购 2 退货
        # 对应银行信息：     收付类型: 1 收款、2 付款
        $data['bank']      = $this->getEvent('ErpBank')->getErpBankList($data['order_type'] == 1 ? 2 : 1 );
        $data['bank_json'] = json_encode($data['bank'],true);
        $this->assign('id', $id);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出预存应收列表
     * @author guanyu
     * @time 2017-11-13
     */
    public function exportPrestoreCollectionData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        $param['type'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '预存应收列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','业务日期','预存申请单号','城市','银行简称','客户','手机','公司','预存金额（元）','预存款类型','订单状态',
            '收款状态','创建人','创建时间','备注'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpFinance')->prestoreCollectionOrderList($param)['data']) && ($count = $this->getEvent('ErpFinance')->prestoreCollectionOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']              = $v['id'];
                $arr[$k]['add_order_time']  = $v['add_order_time'];
                $arr[$k]['order_number']    = $v['order_number'];
                $arr[$k]['region_name']     = $v['region_name'];
                $arr[$k]['bank_simple_name']= $v['bank_simple_name'];
                $arr[$k]['user_name']       = $v['user_name'];
                $arr[$k]['user_phone']      = "\t".$v['user_phone'];
                $arr[$k]['company_name']    = $v['company_name'];
                $arr[$k]['recharge_amount'] = $v['recharge_amount'];
                $arr[$k]['recharge_type']   = $v['recharge_type'];
                $arr[$k]['order_status']    = $v['order_status_font'];
                $arr[$k]['finance_status']  = $v['finance_status_font'];
                $arr[$k]['creater_name']    = $v['creater_name'];
                $arr[$k]['create_time']     = $v['create_time'];
                $arr[$k]['remark']          = $v['remark'];
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
     * 导出预付应付列表
     * @author guanyu
     * @time 2017-11-13
     */
    public function exportPrepayPaymentData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        $param['type'] = 2;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '预付应付列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','业务日期','申请付款日期','付款日期','预付申请单号','城市','银行简称','客户','手机','公司','账户信息','预付金额（元）',
            '预付款类型','订单状态','付款状态','创建人','创建时间','备注'
        ];

        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpFinance')->prepayPaymentOrderList($param)['data']) && ($count = $this->getEvent('ErpFinance')->prepayPaymentOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['add_order_time']      = $v['add_order_time'];
                $arr[$k]['apply_finance_time']  = $v['apply_finance_time'];
                $arr[$k]['pay_time']            = $v['pay_time'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['region_name']         = $v['region_name'];
                $arr[$k]['bank_simple_name']    = $v['bank_simple_name'];
                $arr[$k]['user_name']           = $v['user_name'];
                $arr[$k]['user_phone']          = "\t".$v['user_phone'];
                $arr[$k]['company_name']        = $v['company_name'];
                $arr[$k]['collection_info']     = $v['collection_info'];
                $arr[$k]['recharge_amount']     = $v['recharge_amount'];
                $arr[$k]['recharge_type']       = $v['recharge_type'];
                $arr[$k]['order_status']        = $v['order_status_font'];
                $arr[$k]['finance_status']      = $v['finance_status_font'];
                $arr[$k]['creater_name']        = $v['creater_name'];
                $arr[$k]['create_time']         = $v['create_time'];
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
     * 导入采购发票
     * @author guanyu
     * @time 2018-01-02
     */
    public function uploadInvoice()
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
        $path       = './Public/Uploads/Erp/Purchase/';
        $uploadFile = $silver->uploadFile($path, $file, $file_name);
        if($uploadFile != '上传成功'){
            $this->ajaxReturn(['status' => 3, 'info' => [], 'is_null' => true, 'message' => $uploadFile]);
        }
        # @返回数据
        $result = $this->getEvent('ErpFinance')->uploadInvoice($path.$file_name,$id);
        unlink($path.$file_name);
        $this->ajaxReturn($result);
    }

    /**
     * 验证该笔销售单是否有销退单并且收款未确认
     * @author qianbin
     * @time 2017-12-27
     */
    public function checkReturnOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->checkReturnOrder($param);
            $this->echoJson($data);
        }
    }

    /**
     * 验证该笔采购单是否有采退单并且已收款已出库
     * @author guanyu
     * @time 2018-04-10
     */
    public function checkPurchaseReturnOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->checkPurchaseReturnOrder($param);
            $this->echoJson($data);
        }
    }

    /**
     * 应收列表 明细查询
     * @author qianbin
     * @time 2018-07-13
     */
    public function erpSaleReceivables()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->erpSaleReceivables($param);
            $this->echoJson($data);
        }
        $this->display();
    }



    /**
     * 应收列表 明细查询 导出
     * @author qianbin
     * @time 2018-07-13
     */
    public function exportSaleReceivables()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '应收明细'. currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成菜单行
        $header = [
            '序号','来源单号','订单日期','付款方式','交易员','银行简称','客户（公司）','开户银行','银行账号','订单金额',
            '收款金额','余额抵扣','实收金额','待收/退金额','收款状态','操作员（财务）','收款时间','备注',
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while (($data = $this->getEvent('ErpFinance')->erpSaleReceivables($param)['data']) && ($count = $this->getEvent('ErpFinance')->erpSaleReceivables($param)['recordsTotal'])) {
            $arr = [];
            foreach ($data as $k=>$v) {
                $user_bank_data = explode('--',$v['user_bank_info']);
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['sale_order_number']   = $v['sale_order_number'];
                $arr[$k]['add_order_time']      = $v['add_order_time'];
                $arr[$k]['pay_type']            = $v['pay_type'];
                $arr[$k]['dealer_name']         = $v['dealer_name'];
                $arr[$k]['bank_simple_name']    = $v['bank_simple_name'];
                $arr[$k]['company_name']        = $v['company_name'];
                $arr[$k]['user_bank_info']      = "\t".$user_bank_data[0];
                $arr[$k]['user_bank_num']       = "\t".$user_bank_data[1];

                # 应收管理中导出字段（订单金额 - 收款金额-实收金额-待收\退金额）按以下要求显示
                # 1）销售单的正向收款 显示正数
                # 2）销售单应收的红冲 显示负数
                # 3）销售退货单的正向收款 显示负数
                # 4）销售退货单的红冲收款 显示正数
                $order_amount = $total_collect_money = $collect_money = $order_nocollect_money = '';
                if($v['source_order_type'] == 1){

                    if($v['is_reverse'] == 1){
                        $order_amount           = '-'.abs($v['order_amount']);
                        $total_collect_money    = '-'.abs($v['total_collect_money']) ;
                        $collect_money          = '-'.abs($v['collect_money']) ;
                        $order_nocollect_money  = '-'.abs($v['order_nocollect_money']) ;
                    }else{
                        $order_amount           = abs($v['order_amount']);
                        $total_collect_money    = abs($v['total_collect_money']) ;
                        $collect_money          = abs($v['collect_money']) ;
                        $order_nocollect_money  = abs($v['order_nocollect_money']) ;
                    }

                }else if($v['source_order_type'] == 2){

                    if($v['is_reverse'] == 1){
                        $order_amount           = abs($v['order_amount']);
                        $total_collect_money    = abs($v['total_collect_money']) ;
                        $collect_money          = abs($v['collect_money']) ;
                        $order_nocollect_money  = abs($v['order_nocollect_money']) ;
                    }else{
                        $order_amount           = '-'.abs($v['order_amount']);
                        $total_collect_money    = '-'.abs($v['total_collect_money']) ;
                        $collect_money          = '-'.abs($v['collect_money']) ;
                        $order_nocollect_money  = '-'.abs($v['order_nocollect_money']) ;
                    }
                }

                $arr[$k]['order_amount']        = $order_amount;
                $arr[$k]['total_collect_money'] = $total_collect_money;
                $arr[$k]['balance_deduction']   = $v['balance_deduction'];
                $arr[$k]['collect_money']       = $collect_money;
                $arr[$k]['order_nocollect_money'] = $order_nocollect_money;
                $arr[$k]['collection_status']   = strip_tags($v['collection_status']);
                $arr[$k]['creator']             = $v['creator'];
                $arr[$k]['collect_time']        = $v['collect_time'];
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

   /*
    * ------------------------------------------
    * 预付、应付应付管理 - 选择银行信息
    * Author：qianbin        Time：2018-08-09
    * ------------------------------------------
    */
    public function prepayBank()
    {
        $id   = intval(I('id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->confirmPrepayPayment($param);
            $this->echoJson($data);
        }
        # 对应银行信息：     收付类型: 1 收款、2 付款
        #  type 用于模板当中，提交url  1为预存 2 预付
        $type = intval(I('type', '', 'htmlspecialchars'));
        $data['bank']      = $this->getEvent('ErpBank')->getErpBankList($type);
        $data['bank_json'] = json_encode($data['bank'],true);
        $data['bank_type'] = $type == 1 ? '收款':'付款';
        $this->assign('id', $id);
        $this->assign('type', $type);
        $this->assign('data', $data);
        $this->display();
    }

   /*
    * ------------------------------------------
    * 销退单收款
    * Author：qianbin        Time：2018-08-09
    * ------------------------------------------
    */
    public function confirmSaleReturnCollection()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->confirmSaleReturnCollection($param);
            $this->echoJson($data);
        }
    }

   /*
    * ------------------------------------------
    * 上传销售发票 发送给爱共享
    * Author：qianbin        Time：2018-08-09
    * ------------------------------------------
    */
    public function uploadSaleOrderTickets()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->uploadSaleOrderTickets($param);
            $this->echoJson($data);
        }
    }

    /*
     * ------------------------------------------
     * 取消发票 发送给爱共享
     * Author：qianbin        Time：2018-08-09
     * ------------------------------------------
     */
    public function cancleSaleOrderTickets()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->cancleSaleOrderTickets($param);
            $this->echoJson($data);
        }
    }
    /*
     * @params:
     *      $id:损耗金额编号
     * @return : json
     * @auth:小黑
     * @time:2019-5-9
     * @desc:损耗单得红冲
     */
    public function returnedSaleLoss(){
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpFinance')->returnedSaleLoss($param);
            $this->echoJson($data);
        }
    }
}