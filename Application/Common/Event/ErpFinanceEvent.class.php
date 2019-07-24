<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpFinanceEvent extends BaseController
{

    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
    }

    // +----------------------------------
    // |Facilitator:ERP财务逻辑处理层
    // +----------------------------------
    // |Author:senpai Time:2017.3.31
    // +----------------------------------


    // +----------------------------------
    // |Facilitator:ERP应付列表
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function erpPurchasePaymentList($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['purchase_order_number']))) {
            $where['p.purchase_order_number'] = ['like', '%' . trim($param['purchase_order_number']) . '%'];
        }
        if (!empty(trim($param['id']))) {
            $where['p.id'] = $param['id'];
        }
        if (!empty(trim($param['status']))) {
            $where['p.status'] = $param['status'];
            if(intval($param['status']) == 99 ) unset($where['p.status']);
        }
        //edit xiaowen 申请付款时间改为申请创建时间搜索 2017-6-30
        if (isset($param['apply_pay_start_time']) || isset($param['apply_pay_end_time'])) {
            if (trim($param['apply_pay_start_time']) && !trim($param['apply_pay_end_time'])) {

                $where['p.create_time'] = ['egt', trim($param['apply_pay_start_time'])];
            } else if (!trim($param['apply_pay_start_time']) && trim($param['apply_pay_end_time'])) {

                $where['p.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['apply_pay_end_time']))+3600*24)];
            } else if (trim($param['apply_pay_start_time']) && trim($param['apply_pay_end_time'])) {

                $where['p.create_time'] = ['between', [trim($param['apply_pay_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['apply_pay_end_time']))+3600*24)]];
            }
        }

        if (isset($param['pay_start_time']) || isset($param['pay_end_time'])) {
            if (trim($param['pay_start_time']) && !trim($param['pay_end_time'])) {

                $where['p.pay_time'] = ['egt', trim($param['pay_start_time'])];
            } else if (!trim($param['pay_start_time']) && trim($param['pay_end_time'])) {

                $where['p.pay_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['pay_end_time']))+3600*24)];
            } else if (trim($param['pay_start_time']) && trim($param['pay_end_time'])) {

                $where['p.pay_time'] = ['between', [trim($param['pay_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['pay_end_time']))+3600*24)]];
            }
        }

        if (!empty(trim($param['sale_company_id']))) {
            //$where['o.sale_company_id'] = $param['sale_company_id'];
            $where['p.sale_company_id'] = $param['sale_company_id'];
        }
        if (!empty(trim($param['buyer_dealer_id']))) {
            //$where['o.buyer_dealer_id'] = $param['buyer_dealer_id'];
            $where['p.dealer_id'] = $param['buyer_dealer_id'];
        }
        if (empty(trim($param['show_all'])) && empty(trim($param['status']))) {
            $where['_string'] = 'p.status IN (1,3)';
        }
        //当前登陆选择的我方公司
        $where['p.our_company_id'] = session('erp_company_id');
        //@ edit xiaowen 2017-9-6 只查类型是采购的付款记录-----
        if(intval($param['source_order_type'])){
            $where['p.source_order_type'] = intval($param['source_order_type']);
        }
        //搜索来源采购单号
        if(trim($param['from_purchase_order_number'])){
            $where['p.from_purchase_order_number'] = ['like', '%'.trim($param['from_purchase_order_number']).'%'];
        }

        //@ ---------------------------------------------------
        //$field = 'p.*,o.order_number,o.region,o.sale_company_id,o.depot_id,o.storehouse_id,o.pay_type,o.prepay_ratio,o.sale_collection_info,o.buyer_dealer_id,o.buyer_dealer_name,o.order_amount,o.payed_money,o.no_payed_money,o.price,o.goods_id,o.goods_num,o.from_sale_order_number,ro.source_order_number';
        $field = 'p.*,o.order_number,o.region,o.depot_id,o.storehouse_id,o.pay_type,o.prepay_ratio,o.sale_collection_info,
        o.buyer_dealer_id,o.buyer_dealer_name,o.order_amount,o.payed_money,o.no_payed_money,o.price,o.goods_id,o.goods_num,
        o.from_sale_order_number, ro.source_order_number,ro.return_payed_amount,es1.storehouse_name as storehouse_name1,
        es2.storehouse_name as storehouse_name2';
        if ($param['export']) {
            $erpPurchasePayment = $this->getModel('ErpPurchasePayment')->getAllPurchasePaymentList($where, $field);
        } else {
            $erpPurchasePayment = $this->getModel('ErpPurchasePayment')->getPurchasePaymentList($where, $field, $param['start'], $param['length']);
        }
        //log_info('付款记录来源单号信息'.print_r($erpPurchasePayment['data'], true));

        if (count($erpPurchasePayment['data']) > 0) {

            $purchase_ids = [];
            $returned_ids = [];
            //log_info('付款记录来源单号信息'.print_r(array_column($erpPurchasePayment['data'], 'purchase_order_number'), true));
            //取出这批付款记录中的采购ID和退货ID
            foreach($erpPurchasePayment['data'] as $value){
                if($value['source_order_type'] == 1){
                    array_push($purchase_ids, $value['purchase_id']);

                }else{

                    array_push($returned_ids, $value['purchase_id']);
                }

            }
            log_info('付款记录采购单信息'.print_r($purchase_ids, true));
            if($returned_ids){
                //$return_purchase_ids = $this->getModel('ErpReturnedOrder')->where(['id'=>['in', $returned_ids]])->getField('source_order_id', true);
                $return_orders_info = $this->getModel('ErpReturnedOrder')->where(['id'=>['in', $returned_ids]])->getField('id, source_order_id, source_order_number');
                $return_purchase_ids = array_column($return_orders_info, 'source_order_id');
                $purchase_ids = array_unique(array_merge($purchase_ids, $return_purchase_ids));
            }
            $purchase_orders_data = [];
            $good_data = [];
            if($purchase_ids){
                log_info('付款记录采购单信息'.print_r($purchase_ids, true));
                $field_str = 'o.order_number,o.region,o.depot_id,o.storehouse_id,o.pay_type,o.prepay_ratio,o.sale_collection_info,o.buyer_dealer_id,o.buyer_dealer_name,o.order_amount,o.payed_money,o.no_payed_money,o.price,o.goods_id,o.goods_num,o.from_sale_order_number';
                $purchase_orders_info = $this->getModel('ErpPurchaseOrder')->alias('o')->field($field_str)->where(['id'=>['in', $purchase_ids]])->select();
                foreach($purchase_orders_info as $purchase){
                    $purchase_orders_data[$purchase['order_number']] = $purchase;
                }

                # 补全商品
                # qianbin
                # 2017.7.17
                //$erp_goods_id = array_unique(array_column($erpPurchasePayment['data'],'goods_id'));
                $erp_goods_id = array_unique(array_column($purchase_orders_data,'goods_id'));
                if(count($erp_goods_id) > 0){
                    $good_data    = $this->getModel('ErpGoods')->where(['id' => ['in',$erp_goods_id]])->getField('id,goods_code,goods_name,source_from,grade,level');
                }
            }
            log_info('付款记录采购单信息'.print_r($purchase_orders_data, true));
            //公司
            $company_ids = array_unique(array_column($erpPurchasePayment['data'], 'sale_company_id'));
            $company_arr = [];
            if($company_ids){
                //$company_arr = $this->getModel('Clients')->where(['id' => ['in', $company_ids]])->getField('id,company_name');
                $company_arr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 2);

            }
            $cityArr = provinceCityZone()['city'];
            foreach ($erpPurchasePayment['data'] as $k => $v) {
                if ($k == 0) {
                    $erpPurchasePayment['data'][$k]['sumTotal'] = $erpPurchasePayment['sumTotal'];
                }
                if($v['source_order_type'] == 1 ){
                    $order_number = 'purchase_order_number';
                    $erpPurchasePayment['data'][$k]['order_number'] = $v['purchase_order_number'];
                }else{
                    $order_number = 'source_order_number';
                    $erpPurchasePayment['data'][$k]['order_number'] = '<span class="list_red">'.$v['purchase_order_number'].'</span>';
                }
                $erpPurchasePayment['data'][$k]['goods'] = intval($purchase_orders_data[$v[$order_number]]['goods_id']) > 0 ? $good_data[intval($purchase_orders_data[$v[$order_number]]['goods_id'])] : '——';
                $erpPurchasePayment['data'][$k]['price'] = $purchase_orders_data[$v[$order_number]]['price'] > 0 ? round(getNum($purchase_orders_data[$v[$order_number]]['price']),2) : '0.00';
                $erpPurchasePayment['data'][$k]['pay_type'] = purchasePayType($purchase_orders_data[$v[$order_number]]['pay_type']);
                $erpPurchasePayment['data'][$k]['status_font'] = paymentStatus($v['status'],true);
                $erpPurchasePayment['data'][$k]['status'] = paymentStatus($v['status']);
                $erpPurchasePayment['data'][$k]['payed_money'] = getNum($v['pay_money']);
//                $erpPurchasePayment['data'][$k]['payed_money'] = $v['source_order_type'] == 1 ? getNum($purchase_orders_data[$v[$order_number]]['payed_money']) : getNum($v['return_payed_amount']);
                $erpPurchasePayment['data'][$k]['order_amount'] = getNum($purchase_orders_data[$v[$order_number]]['order_amount']);
                $erpPurchasePayment['data'][$k]['pay_money'] = getNum($v['pay_money']);
                $erpPurchasePayment['data'][$k]['actual_pay_money'] = getNum($v['pay_money'] - $v['balance_deduction']);
                $erpPurchasePayment['data'][$k]['balance_deduction'] = getNum($v['balance_deduction']);
                $erpPurchasePayment['data'][$k]['region_font'] = $cityArr[$purchase_orders_data[$v[$order_number]]['region']];
                //$erpPurchasePayment['data'][$k]['sale_company_name'] = $v['sale_company_id'] == '99999' ? '不限' : $company_arr[$v['sale_company_id']];
                $erpPurchasePayment['data'][$k]['sale_company_name'] = $v['sale_company_id'] == '99999' ? '不限' : $company_arr[$v['sale_company_id']]['company_name'];
                $erpPurchasePayment['data'][$k]['from_sale_order_number'] = $v['source_order_type'] == 1 ? $purchase_orders_data[$v[$order_number]]['from_sale_order_number'] :  $return_orders_info[$v['purchase_id']]['source_order_number'];
                $erpPurchasePayment['data'][$k]['from_sale_order_number'] = $erpPurchasePayment['data'][$k]['from_sale_order_number'] ? $erpPurchasePayment['data'][$k]['from_sale_order_number'] : '--';
                //($v['from_sale_order_number'] ? $v['from_sale_order_number'] : '——');
                $erpPurchasePayment['data'][$k]['sale_collection_info'] = $purchase_orders_data[$v[$order_number]]['sale_collection_info'] ? $purchase_orders_data[$v[$order_number]]['sale_collection_info'] : '——';
                $erpPurchasePayment['data'][$k]['buyer_dealer_name'] = $purchase_orders_data[$v[$order_number]]['buyer_dealer_name'] ? $purchase_orders_data[$v[$order_number]]['buyer_dealer_name'] : '——';
//                $erpPurchasePayment['data'][$k]['price'] = $v['price'] > 0 ? getNum($v['price']) : '0.00';
//                $erpPurchasePayment['data'][$k]['pay_type'] = purchasePayType($v['pay_type']);
//                $erpPurchasePayment['data'][$k]['status_font'] = paymentStatus($v['status'],true);
//                $erpPurchasePayment['data'][$k]['status'] = paymentStatus($v['status']);
//                $erpPurchasePayment['data'][$k]['payed_money'] = getNum($v['payed_money']);
//                $erpPurchasePayment['data'][$k]['order_amount'] = getNum($v['order_amount']);
//                $erpPurchasePayment['data'][$k]['pay_money'] = getNum($v['pay_money']);
//                $erpPurchasePayment['data'][$k]['region_font'] = provinceCityZone()['city'][$v['region']];
//                $erpPurchasePayment['data'][$k]['sale_company_name'] = $v['sale_company_id'] == '99999' ? '不限' : getAllCompanyName()[$v['sale_company_id']];
//                $erpPurchasePayment['data'][$k]['from_sale_order_number'] = $v['from_sale_order_number'] ? $v['from_sale_order_number'] : '——';
                # 列表添加银行简称 qianbin  2018.8.7
                $erpPurchasePayment['data'][$k]['bank_simple_name'] = empty($v['bank_simple_name']) ? '--' : $v['bank_simple_name'];
                $erpPurchasePayment['data'][$k]['storehouse_name'] = empty($v['storehouse_name1']) ? $v['storehouse_name2'] : $v['storehouse_name1'];
            }
        } else {
            $erpPurchasePayment['data'] = [];
        }
        $erpPurchasePayment['recordsFiltered'] = $erpPurchasePayment['recordsTotal'];
        $erpPurchasePayment['draw'] = $_REQUEST['draw'];
        return $erpPurchasePayment;
    }

    // +----------------------------------
    // |Facilitator:采购发票列表
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function erpPurchaseInvoiceList($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['purchase_order_number']))) {
            $where['i.purchase_order_number'] = ['like', '%' . trim($param['purchase_order_number']) . '%'];
        }
        if (!empty(trim($param['id']))) {
            $where['i.id'] = $param['id'];
        }
        if (!empty(trim($param['sale_company_id']))) {
            $where['o.sale_company_id'] = $param['sale_company_id'];
        }
        if (!empty(trim($param['buyer_dealer_id']))) {
            $where['o.buyer_dealer_id'] = $param['buyer_dealer_id'];
        }
        if (empty(trim($param['show_all']))) {
            $where['i.status'] = ['eq', 1];
        }
        if (trim($param['status'])) {
            $where['i.status'] = $param['status'];
        }
        if(intval(trim($param['region'])) > 0){
            $where['o.region']  = intval($param['region']);
        }
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['i.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['i.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['i.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        //当前登陆选择的我方公司
        $where['o.our_buy_company_id'] = session('erp_company_id');

        $field = 'i.*,o.order_number,o.region,o.sale_company_id,o.depot_id,o.storehouse_id,o.pay_type,o.prepay_ratio,
        o.sale_collection_info,o.buyer_dealer_id,o.buyer_dealer_name,o.order_amount,o.payed_money,o.no_payed_money,
        o.price,o.goods_num,o.invoice_money,o.returned_goods_num,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
        o.goods_id,r.return_payed_amount'; //,o.loss_num

        if ($param['export']) {
            $erpPurchaseInvoice = $this->getModel('ErpPurchaseInvoice')->getAllPurchaseInvoiceList($where, $field.',g.goods_code,g.goods_name,g.source_from,g.grade,g.level');
        } else {
            $erpPurchaseInvoice = $this->getModel('ErpPurchaseInvoice')->getPurchaseInvoiceList($where, $field, $param['start'], $param['length']);
        }
        //空数据
        if (count($erpPurchaseInvoice['data']) > 0) {
            //公司
            $company_ids = array_unique(array_column($erpPurchaseInvoice['data'], 'sale_company_id'));
            $company_arr = [];
            if($company_ids){
                //$company_arr = $this->getModel('Clients')->where(['id' => ['in', $company_ids]])->getField('id,company_name');
                $company_arr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 2);
            }
            $cityArr = provinceCityZone()['city'];
            foreach ($erpPurchaseInvoice['data'] as $k => $v) {
                if ($k == 0) {
                    $erpPurchaseInvoice['data'][$k]['sumTotal'] = $erpPurchaseInvoice['sumTotal'];
                }
                $erpPurchaseInvoice['data'][$k]['order_amount'] = getNum($v['order_amount']);
                $erpPurchaseInvoice['data'][$k]['total_order_amount'] = round($erpPurchaseInvoice['data'][$k]['order_amount'] - getNum($v['return_payed_amount']), 2);
                $erpPurchaseInvoice['data'][$k]['invoice_money'] = getNum($v['invoice_money']);
                $erpPurchaseInvoice['data'][$k]['notax_invoice_money'] = getNum($v['notax_invoice_money']);
                $erpPurchaseInvoice['data'][$k]['tax_money'] = getNum($v['tax_money']);
                $erpPurchaseInvoice['data'][$k]['status_font'] = invoiceStatus($v['status'],true);
                $erpPurchaseInvoice['data'][$k]['apply_invoice_money'] = getNum($v['apply_invoice_money']);
                $erpPurchaseInvoice['data'][$k]['region_font'] = $cityArr[$v['region']];
                //$erpPurchaseInvoice['data'][$k]['sale_company_name'] = $v['sale_company_id'] == 99999 ? '不限' : $company_arr[$v['sale_company_id']];
                $erpPurchaseInvoice['data'][$k]['sale_company_name'] = $v['sale_company_id'] == 99999 ? '不限' : $company_arr[$v['sale_company_id']]['company_name'];
                $erpPurchaseInvoice['data'][$k]['invoice_type_font'] = invoiceType()[$v['invoice_type']];
                $erpPurchaseInvoice['data'][$k]['auditor'] = empty($v['auditor']) ? '-' :$v['auditor'];
                $erpPurchaseInvoice['data'][$k]['invoice_phase'] = empty($v['invoice_phase']) ? '-' :$v['invoice_phase'];
            }
        } else {
            $erpPurchaseInvoice['data'] = [];
        }
        $erpPurchaseInvoice['recordsFiltered'] = $erpPurchaseInvoice['recordsTotal'];
        $erpPurchaseInvoice['draw'] = $_REQUEST['draw'];
        return $erpPurchaseInvoice;
    }

    // +----------------------------------
    // |Facilitator:同意付款
    // +----------------------------------
    // |Author:senpai Time:2017.5.8
    // +----------------------------------
    public function paymentAgree($param = [])
    {
        if (getCacheLock('ErpFinance/paymentAgree')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/paymentAgree', 1);
        $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $param['id']]);

        if ($payment_info['status'] != 1) {
            $result['status'] = 0;
            $result['message'] = '只有已申请状态可以进行同意操作';
        } else {
            M()->startTrans();

            //更新应付信息
            $update_payment_data = [
                'status' => 3,
                //'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'auditor' => $this->getUserInfo('dealer_name'),
                'audit_time' => currentTime(),
            ];
            $status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $param['id']], $update_payment_data);

            //获取应付信息
            //$payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $param['id']]);
            if($payment_info['source_order_type'] == 1){

                //获取订单信息
                $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $payment_info['purchase_id']]);

                //更新日志信息
                $log_data = [
                    'purchase_id' => $order_info['id'],
                    'purchase_order_number' => $order_info['order_number'],
                    'log_type' => 14,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

            }else if($payment_info['source_order_type'] == 2){
                //获取订单信息
                $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $payment_info['purchase_id']]);

                //更新日志信息
                $log_data = [
                    'return_order_id' => $order_info['id'],
                    'return_order_number' => $order_info['order_number'],
                    'return_order_type' => 2,
                    'log_type' => 9,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpReturnedOrderLog')->add($log_data);
            }


            if ($status_payment && $status_log) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '提交成功';
            } else {
                M()->rollback();
                $result['status'] = 0;
                $result['message'] = isset($err_message) ? $err_message : '提交失败';
            }
        }
        cancelCacheLock('ErpFinance/paymentAgree');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:应付确认
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function paymentConfirmation($param = [])
    {
        if (getCacheLock('ErpFinance/paymentConfirmation')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/paymentConfirmation', 1);

        //获取应付信息
        $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $param['id']]);

        // 验证银行账套信息是否有效
        $bank_info = $this->getEvent('ErpBank')->getErpBankInfoById(intval($param['bank_id']));
        if ($payment_info['source_order_type'] == 1) {
            //获取订单信息
            $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $payment_info['purchase_id']]);
        } else {
            $field = 'o.*';
            $order_info = $this->getModel('ErpReturnedOrder')->alias('ro')
                ->field($field)
                ->where(['ro.id' => $payment_info['purchase_id'],'ro.order_number' => $payment_info['purchase_order_number']])
                ->join('oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number', 'left')
                ->find();
        }
        if ($order_info && $order_info['order_status'] == 2) {
            $result['status'] = 0;
            $result['message'] = '对应采购单已取消，不能确认付款';
        } else if ($payment_info['status'] != 3) {
            $result['status'] = 0;
            $result['message'] = '同意状态下才可以确认付款';
        } else if (intval($param['bank_id']) > 0 && (empty($bank_info) || intval($bank_info['status']) != 1)) {
            $result['status']  = 21;
            $result['message'] = '该笔银行账号信息有误，请刷新后重试！';
        }else {
            M()->startTrans();

            $stock_status         = true;
            $change_accunt_status = true ;

            //更新应付信息
            $update_payment_data = [
                'status' => 10,
                'balance_deduction' => $param['prepay_status'] ? setNum($param['balance_deduction']) : 0,
                //'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'auditor' => $this->getUserInfo('dealer_name'),
                'audit_time' => currentTime(),
                'pay_time' => currentTime(),
                /** 银行账套信息改版 - qianbin - 2018.08.07 */
                'bank_id'          => intval($param['bank_id']),
                'bank_simple_name' => intval($param['bank_id']) <= 0 ? "" : trim($bank_info['bank_simple_name']),
                'is_prepay_money'  => intval($param['is_prepay_money']),
                'bank_info'        => intval($param['bank_id']) <= 0 ? " " : json_encode($bank_info,JSON_UNESCAPED_UNICODE),
                /** 银行账套信息改版 - qianbin - 2018.08.07 */
            ];
            # 如果为退款转预付，则不需要更新银行账套
            if($payment_info['source_order_type'] == 2 && intval($param['is_prepay_money']) == 1){
                unset($update_payment_data['bank_id'],$update_payment_data['bank_simple_name'],$update_payment_data['bank_info']);
            }
            $status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $param['id']], $update_payment_data);

            //预付账户抵扣
            if ($param['prepay_status']) {
                $order_info['company_id'] = $order_info['sale_company_id'];
                $order_info['our_company_id'] = $order_info['our_buy_company_id'];
                $order_info['object_type'] = 4;//单据类型：预存申请单
                $accout_status = $this->getEvent('ErpAccount')->changeAccount($order_info, PREPAY_TYPE, setNum($param['balance_deduction']) * -1);
            } else {
                $accout_status = true;
            }
            //根据采购单是否退货，走不同的处理流程 edit xiaowen 2017-9-4
            //@没有退货走正常采购付款流程-------------------------------
            if($payment_info['source_order_type'] == 1){
                //获取订单信息
                $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $payment_info['purchase_id']]);

                $is_effect_stock = false;
                /** 判断订单付款状态及是否影响库存-start */
                //预付订单的应预付金额
                //edit xiaowen 四舍五入保存两位计算预付金额
                $prepay = setNum(round(getNum($order_info['order_amount']) * getNum($order_info['prepay_ratio']) / 100, 2));
                log_info('预付金额：' . $prepay);

                //最新已付金额
                $payed_money = $payment_info['pay_money'] + $order_info['payed_money'];
                log_info('已付金额：' . $payed_money);
                $change_num = 0;
                //付款状态及库存修改判断
                if ($payed_money <= 0) {
                    $pay_status = 1;
                } elseif ($payed_money < $prepay && in_array($order_info['pay_type'],[2,5])) {
                    $pay_status = 2;
                } elseif ($payed_money == $prepay && in_array($order_info['pay_type'],[2])) {
                    $pay_status = 3;
                    //若预付类型采购单已预付则影响库存
                    $is_effect_stock = true;
                }elseif ($payed_money == $prepay && in_array($order_info['pay_type'],[5])) { //定金锁价刚好达到预付 不需要影响库存 edit xiaowen 2017-6-8
                    $pay_status = 3;
                    //若定金锁价类型采购单已预付不影响库存
                    $is_effect_stock = false;
                } elseif ($payed_money > $prepay && $payed_money < $order_info['order_amount'] && $order_info['pay_type'] == 2) {
                    $pay_status = 4;
                    //若预付类型采购单第一次付款操作超过预付则影响库存
                    if ($order_info['pay_status'] < 3) {
                        $is_effect_stock = true;
                    }
                } elseif ($payed_money > $prepay && $payed_money < $order_info['order_amount'] && $order_info['pay_type'] == 5) {
                    $pay_status = 4;
                    /**
                     * 定金锁价修改库存
                     * 这里做一个预防
                     * 预防他这笔支付满足定金同时还有剩余
                     * 影响库存的只是多出来的这部分
                     */
                    if ($order_info['pay_status'] < 3) {
                        // edit xiaowen 2017-6-10 为保证舍去取两位小数 先 * 100 取含两小位整数 再除 100
                        $change_num = floor(setNum(($payment_info['pay_money'] + $order_info['payed_money'] - $prepay) / $order_info['price']));
                        //                    $change_num = setNum(($payment_info['pay_money'] + getNum($order_info['payed_money']) - $prepay) / getNum($order_info['price']));
                    } else {
                        // edit xiaowen 2017-6-10 为保证舍去取两位小数 先 * 100 取含两小位整数 再除 100
                        $change_num = floor(setNum($payment_info['pay_money'] / $order_info['price']));
                    }
                    $is_effect_stock = true;
                    //------------定金锁价在这个时段会影响库存，根据付款比例转入采购在途------------------------------------
                    //$stock_status = $this->purchaseEarnestPayChangeStock($order_info,$change_num);
                    //$stock_message = $stock_status ? '操作成功' : '操作失败';
                    //log_info('销售单:'. $order_info['order_number'] . ', 部分收款时，库存更新操作' . $stock_message);
                } elseif ($payed_money > 0 && $payed_money < $order_info['order_amount']) {
                    $pay_status = 4;
                } elseif ($payed_money == $order_info['order_amount']) {
                    $pay_status = 10;
                    if ($order_info['pay_type'] == 1 || $order_info['pay_type'] == 4) {
                        $is_effect_stock = true;
                    }
                    //若预付类型采购单第一次付款操作超过预付则影响库存
                    if ($order_info['pay_status'] < 3 && $order_info['pay_type'] == 2) {
                        $is_effect_stock = true;
                    }
                    //-------------全部付款成功后，改变库存-------------------------------------------------------------
                    if ($order_info['pay_type'] == 5) {
                        // edit xiaowen 2017-6-10 为保证舍去取两位小数 先 * 100 取含两小位整数 再除 100
                        //$change_num = floor(($order_info['goods_num'] - $order_info['total_purchase_wait_num']));
                        $change_num = $order_info['goods_num'] - $order_info['total_purchase_wait_num'];
                        //定金锁价方式在全部付款后有区别
                        //$stock_status = $this->purchaseEarnestPayChangeStock($order_info,$change_num);
                        //$stock_message = $stock_status ? '操作成功' : '操作失败';
                        //log_info('销售单:'. $order_info['order_number'] . ', 完成收款时，库存更新操作' . $stock_message);
                        $is_effect_stock = true;
                    }
                    //--------------------------------------------------------------------------------------------------
                }
                /** 判断订单付款状态及是否影响库存-end */

                //更新订单信息
                $update_order_data = [
                    'payed_money' => $payed_money,
                    'pay_status' => $pay_status,
                    'no_payed_money' => $order_info['order_amount'] - $payed_money,
                    'total_purchase_wait_num' => $order_info['pay_type'] == 5 ? $order_info['total_purchase_wait_num'] + $change_num : $order_info['total_purchase_wait_num'],
                ];
                $status_order = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $payment_info['purchase_id']], $update_order_data);
                //更新日志信息
                $log_data = [
                    'purchase_id' => $order_info['id'],
                    'purchase_order_number' => $order_info['order_number'],
                    'log_type' => 11,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

                //不是二次定价的订单，确认付款时才需要影响库存 edit xiaowen
                $is_effect_stock = $order_info['is_update_price'] == 2 ? $is_effect_stock : false;

                /** --------付款方式为现结和预付在付款时影响在途库存-start------- **/
                if ($is_effect_stock) {

                    //------------------------定金锁价影响库存--------------------------------
                    if ($order_info['pay_type'] == 5 && $change_num > 0) {
                        // edit xiaowen 2017-6-10 为保证舍去取两位小数 先 * 100 取含两小位整数 再除 100

                        //定金锁价方式在全部付款后有区别
                        $stock_status = $this->purchaseEarnestPayChangeStock($order_info,$change_num);
                        $stock_message = $stock_status ? '操作成功' : '操作失败';
                        log_info('销售单:'. $order_info['order_number'] . ', 完成收款时，库存更新操作' . $stock_message);

                    }
                    //------------------------end---------------------------------------------
                    //------------------------其他方式影响库存--------------------------------
                    else if($order_info['pay_type'] != 5){
                        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                        $stock_where = [
                            'goods_id' => $order_info['goods_id'],
                            'object_id' => $order_info['storehouse_id'],
                            'stock_type' => $order_info['type'] == 2 ? 2 : getAllocationStockType($order_info['storehouse_id']),
                        ];
                        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                        //------------------组装库存表的字段值--------------------------
                        $data = [
                            'goods_id' => $order_info['goods_id'],
                            'object_id' => $order_info['storehouse_id'],
                            'stock_type' => $order_info['type'] == 2 ? 2 : getAllocationStockType($order_info['storehouse_id']),
                            'region' => $order_info['region'],
                            'transportation_num' => $stock_info['transportation_num'] + $order_info['goods_num'],
                        ];
                        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的在途库存
                        //------------------计算出新的可用库存----------------------------
                        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                        //----------------------------------------------------------------
                        $orders = [
                            'object_number' => $order_info['order_number'],
                            'object_type' => 2,
                            'log_type' => 4,
                        ];
                        //----------------更新库存，并保存库存日志-------------------------
                        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['goods_num'], $orders);
                    }
                    //------------------------end其他方式影响库存-------------------------------
                }
                /** --------付款方式为现结和预付在付款时影响在途库存-start------- **/
            }
            // 有退货走，退货采购付款确认流程
            else{
                //计算最新的已付金额
                //$payed_money = $payment_info['pay_money'] + $order_info['payed_money'];
                //计算订单总金额 （包含退货）
                //$total_order_amount = $order_info['order_amount'] - (getNum($order_info['price']) * $order_info['returned_goods_num']);

                //如果最新已付金额 == 订单总金额 则更新订单为已付款
                //$pay_status =  $payed_money == $total_order_amount ? 10 : $order_info['pay_status'];

                // 如果采退付款，选择转预付，
                // 则需要生成对应预付单 增加预付余额 qianbin 2018-08.08
                if(intval($param['is_prepay_money']) == 1 ){
                    $change_accunt_status = $this->addPrepayOrderRecord($payment_info,$order_info);
                }
                //更新订单信息
                $update_order_data = [
                    'return_amount_status' => 10,
                    'return_payed_amount' => $payment_info['pay_money'],
                    'update_time' => currentTime(),
                ];

                $status_order = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => $payment_info['purchase_id']], $update_order_data);
                //获取退货单信息
                $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $payment_info['purchase_id']]);
                //更新日志信息
                $log_data = [
                    'return_order_id' => $order_info['id'],
                    'return_order_number' => $order_info['order_number'],
                    'return_order_type' => 2,
                    'log_type' => 10,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpReturnedOrderLog')->add($log_data);
                $stock_status = true;
            }
            if ($status_order && $status_payment && $status_log && $stock_status && $accout_status && $change_accunt_status) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '提交成功';
            } else {
                M()->rollback();
                $result['status'] = 0;
                $result['message'] = isset($err_message) ? $err_message : '提交失败';
            }
        }
        cancelCacheLock('ErpFinance/paymentConfirmation');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:应付驳回
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function paymentReject($param = [])
    {
        if (getCacheLock('ErpFinance/paymentReject')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/paymentReject', 1);
        $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $param['id']]);

        if ($payment_info['status'] == 2) {
            $result['status'] = 0;
            $result['message'] = '该申请已被驳回';
        } else if ($payment_info['status'] == 10) {
            $result['status'] = 0;
            $result['message'] = '已付款状态无法驳回';
        } else {
            M()->startTrans();

            $update_payment_data = [
                'status' => $payment_info['status'] == 3 ? 1 : 2,
                'audit_remark' => $param['audit_remark'],
                //'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'auditor' => $this->getUserInfo('dealer_name'),
                'audit_time' => currentTime()
            ];

            //更新应付信息
            $status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $param['id']], $update_payment_data);

            //获取应付信息
            $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $param['id']]);
            if($payment_info['source_order_type'] == 1){
                //获取订单信息
                $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $payment_info['purchase_id']]);

                $log_data = [
                    'purchase_id' => $order_info['id'],
                    'purchase_order_number' => $order_info['order_number'],
                    'log_type' => 12,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

            }else{
                //采退单付款驳回
                //获取订单信息
                $order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => $payment_info['purchase_id']]);

                $log_data = [
                    'return_order_id' => $order_info['id'],
                    'return_order_number' => $order_info['order_number'],
                    'return_order_type' => 2,
                    'log_type' => 11,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpReturnedOrderLog')->add($log_data);
            }
            if ($status_payment && $status_log) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '提交成功';
            } else {
                M()->rollback();
                $result['status'] = 0;
                $result['message'] = isset($err_message) ? $err_message : '提交失败';
            }

        }
        cancelCacheLock('ErpFinance/paymentReject');
        return $result;
    }

    /**
     * 录入发票
     * @author senpai
     */
    public function addPurchaseInvoice($param)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        $all_invoice_money = $this->getModel('ErpPurchaseInvoice')->field('sum(apply_invoice_money) as total')->where(['purchase_id' => $param['purchase_id'], 'status' => ['neq', 2]])->group('purchase_id')->find();

        $order_info = $this->getModel('ErpPurchaseOrder')->alias('o')
            ->where(['o.id' => $param['purchase_id']])
            ->field('o.*,r.return_payed_amount')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
            ->find();

        $order_info['order_amount'] = $order_info['order_amount'] - round($order_info['return_payed_amount'],-2);

        //采购单有未退款的采退单无法录入发票
        $returned_order = $this->getModel('ErpReturnedOrder')->where(['source_order_number'=>$order_info['order_number'],'order_status'=>10,'return_amount_status'=>1])->count();
        if ($returned_order) {
            $result['status'] = 101;
            $result['message'] = "请先处理采退单的退款，无法录入发票";
            return $result;
        }

        if (trim($param['invoice_sn']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入发票号码";
            return $result;
        }
        if (trim($param['invoice_type']) == "") {
            $result['status'] = 101;
            $result['message'] = "请选择发票类型";
            return $result;
        }
        if (trim($param['notax_invoice_money']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入去税发票金额";
            return $result;
        }
        if (trim($param['notax_invoice_money']) == 0) {
            $result['status'] = 101;
            $result['message'] = "去税发票金额不能等于0";
            return $result;
        }
        if (trim($param['tax_money']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入税额";
            return $result;
        }
        if ($param['notax_invoice_money'] + $param['tax_money'] > 1 && trim($param['tax_money'] == 0)) {
            $result['status'] = 101;
            $result['message'] = "税额不能等于0";
            return $result;
        }
        if (is_nan($param['notax_invoice_money']) || is_nan($param['tax_money'])) {
            $result['status'] = 101;
            $result['message'] = "请输入正确的数字（保留两位小数）";
            return $result;
        }
        if (trim($param['notax_invoice_money'] + $param['tax_money']) > getNum($order_info['order_amount'] - $all_invoice_money['total'])) {
            $result['status'] = 101;
            $result['message'] = "已录入发票金额不能超出订单总金额";
            return $result;
        }

        if (in_array(intval($param['invoice_type']),[1,2,3])) {
            $coefficient = 0.17;
        } else if (in_array(intval($param['invoice_type']),[4,5,6])) {
            $coefficient = 0.16;
        }
        $apply_invoice_money = $param['notax_invoice_money'] + $param['tax_money'];
        //edit xiaowen 2019-3-25 与天杰确认发票不验证税额，只需验证开票总额
//        if (intval(setNum($param['tax_money'])) != intval(setNum(round($apply_invoice_money / (1 + $coefficient) * $coefficient,2)))) {
//            $result['status'] = 101;
//            $result['message'] = "税率计算错误，请检查";
//            return $result;
//        }

        if (getCacheLock('ErpFinance/addPurchaseInvoice')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/addPurchaseInvoice', 1);
        M()->startTrans();
        $erp_invoice_data = [
            'purchase_id' => $param['purchase_id'],
            'purchase_order_number' => $param['purchase_order_number'],
            'invoice_sn' => $param['invoice_sn'],
            'notax_invoice_money' => setNum($param['notax_invoice_money']),
            'tax_money' => setNum($param['tax_money']),
            'apply_invoice_money' => setNum($apply_invoice_money),
            'invoice_type' => $param['invoice_type'],
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'create_time' => $this->date,
            'status' => 1,
            'remark' => $param['remark']
        ];

        if (trim($param['id']) == "") {
            //添加erp发票录入信息
            $status_invoice = $this->getModel('ErpPurchaseInvoice')->addPurchaseInvoice($erp_invoice_data);
        } else {
            //编辑erp发票录入信息
            $status_invoice = $this->getModel('ErpPurchaseInvoice')->savePurchaseInvoice(['id' => $param['id']], $erp_invoice_data);
        }
        $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $param['purchase_id']]);
        $log_data = [
            'purchase_id' => $param['purchase_id'],
            'purchase_order_number' => $param['purchase_order_number'],
            'log_type' => 8,
            'log_info' => serialize($order_info),
            'create_time' => $this->date,
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);
        if ($status_invoice && $status_log) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '申请成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '操作失败';
        }

        cancelCacheLock('ErpFinance/addPurchaseInvoice');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:采购发票确认
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function purchaseInvoiceConfirmation($param = [])
    {
        //获取发票信息
        $invoice_info = $this->getModel('ErpPurchaseInvoice')->findPurchaseInvoice(['id' => $param['id']]);

        //获取订单信息
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->alias('o')
            ->where(['o.order_number' => $invoice_info['purchase_order_number']])
            ->field('o.*,r.return_payed_amount')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
            ->find();

        $purchase_total_amount = $purchase_order_info['order_amount'] - round($purchase_order_info['return_payed_amount'],-2);


        if ($invoice_info['status'] != 1) {
            $result['status'] = 2;
            $result['message'] = '只有已申请状态的发票才能确认';
            return $result;
        }

        if ($invoice_info['apply_invoice_money'] + $purchase_order_info['invoice_money'] > $purchase_total_amount) {
            $result['status'] = 3;
            $result['message'] = "已录入发票金额不能超出订单总金额";
            return $result;
        }

        //采购单有未退款的采退单无法录入发票
        $returned_order = $this->getModel('ErpReturnedOrder')->where(['source_order_number'=>$invoice_info['purchase_order_number'],'order_status'=>10,'return_amount_status'=>1])->count();
        if ($returned_order) {
            $result['status'] = 101;
            $result['message'] = "请先处理采退单的退款，无法录入发票";
            return $result;
        }

        if (getCacheLock('ErpFinance/purchaseInvoiceConfirmation')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/purchaseInvoiceConfirmation', 1);

        M()->startTrans();
        $update_invoice_data = [
            'status' => 10,
            //'auditor' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'auditor' => $this->getUserInfo('dealer_name'),
            'audit_time' => currentTime()
        ];

        //更新发票信息
        $status_invoice = $this->getModel('ErpPurchaseInvoice')->savePurchaseInvoice(['id' => $param['id']], $update_invoice_data);

        /**
         * 判断订单状态-start
         */
        //最新发票金额
        $invoice_money = $invoice_info['apply_invoice_money'] + $purchase_order_info['invoice_money'];
        if ($invoice_money <= 0) {
            $invoice_status = 1;
        } elseif ($invoice_money > 0 && $invoice_money < $purchase_total_amount) {
            $invoice_status = 2;
        } elseif ($invoice_money == $purchase_total_amount) {
            $invoice_status = 10;
        }
        /**
         * 判断订单状态-end
         */

        $update_order_data = [
            'invoice_money' => $invoice_money,
            'invoice_status' => $invoice_status,
        ];

        //更新订单信息
        $status_order = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $invoice_info['purchase_id']], $update_order_data);

        $log_data = [
            'purchase_id' => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'log_type' => 10,
            'log_info' => serialize($purchase_order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

        if ($status_log && $status_order && $status_invoice) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }
        cancelCacheLock('ErpFinance/purchaseInvoiceConfirmation');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:采购发票驳回
    // +----------------------------------
    // |Author:senpai Time:2017.4.1
    // +----------------------------------
    public function purchaseInvoiceReject($param = [])
    {
        //获取发票信息
        $invoice_info = $this->getModel('ErpPurchaseInvoice')->findPurchaseInvoice(['id' => $param['id']]);

        if ($invoice_info['status'] != 1) {
            $result['status'] = 2;
            $result['message'] = '只有已申请状态的发票才能驳回';
            return $result;
        }

        if (getCacheLock('ErpFinance/purchaseInvoiceReject')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/purchaseInvoiceReject', 1);
        M()->startTrans();
        $update_invoice_data = [
            'status' => 2,
            'audit_remark' => $param['audit_remark'],
            //'auditor' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'auditor' => $this->getUserInfo('dealer_name'),
            'audit_time' => currentTime()
        ];

        //更新应付信息
        $status_invoice = $this->getModel('ErpPurchaseInvoice')->savePurchaseInvoice(['id' => $param['id']], $update_invoice_data);

        //获取订单信息
        $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $invoice_info['purchase_id']]);

        $log_data = [
            'purchase_id' => $order_info['id'],
            'purchase_order_number' => $order_info['order_number'],
            'log_type' => 13,
            'log_info' => serialize($order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

        if ($status_invoice && $status_log) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '提交成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '提交失败';
        }
        cancelCacheLock('ErpFinance/purchaseInvoiceReject');
        return $result;
    }

    /**
     * 应收列表
     * @author senpai 2017-04-21
     * @param $param
     */
    public function erpSaleCollectionList($param = [])
    {
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.add_order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        if (trim($param['sale_company_id'])) {
            $where['o.company_id'] = intval(trim($param['sale_company_id']));
        }
        if ($param['status']) {
            $where['o.order_status'] = intval($param['status']);
        }

//        //是否损耗
//        if ($param['is_loss']) {
//            $is_loss = intval(trim($param['is_loss']));
//        } else {
//            $is_loss = 1;
//        }

        if ($param['collection_status']) {
            //是否损耗
            if ($param['is_loss']) {
                $is_loss = intval(trim($param['is_loss']));
                $collection_status = intval(trim($param['collection_status']));
                $where['_string'] = "(o.collection_status = ".$collection_status." AND o.is_loss = ".$is_loss.")";
            } else {
                $is_loss = 1;
                $collection_status = intval(trim($param['collection_status']));
                $where['_string'] = "(o.collection_status = ".$collection_status." OR o.is_loss = ".$is_loss.")";
            }
            # 增加收款状态 “全部状态” qianbin 2018-07-10
            if(intval($param['collection_status']) == 20){
                unset($where['o.is_loss']);
                unset($where['o.order_status']);
                unset($where['_string']);
            }
        } elseif ($param['collection']) {
            //是否损耗
            if ($param['is_loss']) {
                $is_loss = intval(trim($param['is_loss']));
                $where['o.is_loss'] = $is_loss;
            } else {
                $is_loss = 1;
                $where['_string'] = "(o.collection_status != 10 OR o.is_loss = ".$is_loss.")";
            }
        }

        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }

        if (trim($param['is_void']) == 1) {
            $where['o.is_void'] = trim($param['is_void']);
            unset($where['o.is_loss']);
            unset($where['o.order_status']);
            unset($where['_string']);
        }

        # 搜索 - 实际收款时间 - qianbin - 2017.10.11
        if (!empty(trim($param['put_start_time'])) || !empty(trim($param['put_end_time'])))  {
            if (trim($param['put_start_time']) && !trim($param['put_end_time'])) {
                $where['sc.collect_time'] = ['egt', trim($param['put_start_time'])];
            } else if (!trim($param['put_start_time']) && trim($param['put_end_time'])) {
                $where['sc.collect_time'] = ['elt',date('Y-m-d H:i:s', strtotime(trim($param['put_end_time']))+3600*24)];
            } else if (trim($param['put_start_time']) && trim($param['put_end_time'])) {
                $where['sc.collect_time'] = ['between', [trim($param['put_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['put_end_time']))+3600*24)]];
            }
            unset($where['_string']);
            $where['collection_status'] = ['gt',1];
        }
        //我的销售单
        if (trim($param['dealer_id'])) {
            $where['o.dealer_id'] = trim($param['dealer_id']);
        }
        if (trim($param['is_update_price'])) {
            $where['o.is_update_price'] = intval(trim($param['is_update_price']));
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        //过滤零售订单
        $where['o.order_type'] = 1;

        //$field = 'o.*,sc.creator as c_creator,MAX(collect_time) as collect_time,d.depot_name,cs.tax_num,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';
        $field = 'o.*,sc.creator as c_creator,MAX(collect_time) as collect_time,d.depot_name,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';

        if ($param['export']) {
            $data = $this->getModel('ErpSaleOrder')->getAllSaleOrderList($where, $field);
        } else {
            $data = $this->getModel('ErpSaleOrder')->getSaleOrderList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];
            //edit xiaowen 2018-10-19 930 改造
            $company_ids = array_column($data['data'], 'company_id');
            $user_ids = array_column($data['data'], 'user_id');
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1);
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 1);
            }
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['No'] = $i;
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['collection_status_font'] = saleCollectionStatus($value['collection_status']);
                $data['data'][$key]['collection_status'] = saleCollectionStatus($value['collection_status'], true);
                $data['data'][$key]['invoice_status'] = purchaseInvoiceStatus($value['invoice_status'], true);
                $data['data'][$key]['pay_type'] = purchasePayType($value['pay_type']);
                $data['data'][$key]['order_status'] = SaleOrderStatus($value['order_status'], true);
                $data['data'][$key]['is_void'] = purchaseContract($value['is_void'], true);
                $data['data'][$key]['is_upload_contract'] = purchaseContract($value['is_upload_contract'], true);
                //$data['data'][$key]['from_sale_order_number'] = $value['from_sale_order_number'] ? $value['from_sale_order_number'] : '--';
                $data['data'][$key]['price'] = $value['price'] > 0 ? getNum($value['price']) : '0.00';
                $data['data'][$key]['order_amount'] = $value['order_amount'] > 0 ? getNum($value['order_amount']) : '0';
                $data['data'][$key]['total_amount'] = $value['order_amount'] > 0 ? round(getNum($value['order_amount'] - getNum($value['returned_goods_num'] * $value['price'])),2) : '0';
                $data['data'][$key]['no_collect_amount'] = round(getNum($value['order_amount'] - getNum($value['returned_goods_num'] * $value['price']) - $value['collected_amount']),2);
                $data['data'][$key]['collected_amount'] = $value['collected_amount'] > 0 ? getNum($value['collected_amount']) : '0';
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? getNum($value['buy_num']) : '0';
                $data['data'][$key]['returned_goods_num'] = $value['returned_goods_num'] > 0 ? getNum($value['returned_goods_num']) : '0';
                $data['data'][$key]['delivery_money'] = $value['delivery_money'] > 0 ? getNum($value['delivery_money']) : '0';
                $data['data'][$key]['is_loss'] = lossStatus($value['is_loss'],true);
                $data['data'][$key]['is_loss_font'] = lossStatus($value['is_loss']);
                $data['data'][$key]['loss_amount'] = $value['loss_num'] > 0 ? round(getNum(getNum($value['loss_num'] * $value['price'])), 2) : '0';
                $data['data'][$key]['entered_loss_amount'] = $value['entered_loss_amount'] > 0 ? getNum($value['entered_loss_amount']) : '0';
                $data['data'][$key]['outbound_quantity'] = $value['outbound_quantity'] > 0 ? getNum($value['outbound_quantity']) : '0';
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];

                //$data['data'][$key]['s_company_name'] = $value['company_id'] == 99999 ? '不限' : $data['data'][$key]['s_company_name'];
                //=============930改造=================================
                $data['data'][$key]['s_company_name'] = $value['company_id'] == 99999 ? '不限' : $companyArr[$value['company_id']]['company_name'];
                $data['data'][$key]['s_user_name'] = $userArr[$value['user_id']]['user_name'];
                $data['data'][$key]['s_user_phone'] = $userArr[$value['user_id']]['user_phone'];
                //============end 930改造===============================
                $data['data'][$key]['order_source'] = saleOrderSourceFrom($value['order_source'], true);
                $data['data'][$key]['pay_type'] = saleOrderPayType($value['pay_type'], true);
                $data['data'][$key]['residue_time'] = getSaleOrderResidueTime($value['end_order_time'], currentTime()); //截单剩余时间 精确到分
                //是否二次定价 edit xiaowen 2017-7-24
                $data['data'][$key]['is_update_price'] = orderUpdatePriceStatus($value['is_update_price'], true);

                $i++;
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    // +----------------------------------
    // |Facilitator:ERP应收明细列表
    // +----------------------------------
    // |Author:senpai Time:2017.4.17
    // +----------------------------------
    public function erpSaleCollectionDetail($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['sale_order_id']))) {
            $where['c.sale_order_id'] = $param['sale_order_id'];
        }
        if (!empty(trim($param['sale_order_number']))) {
            $where['c.sale_order_number'] = $param['sale_order_number'];
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');
        //$where['c.status'] = 1;

        if($param['order_type'] == 0){
            //$field = 'c.*,us.user_name,cs.company_name,o.order_number,o.add_order_time,o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,o.order_amount,o.collected_amount,o.collection_status';
            $field = 'c.*,o.user_id,o.order_number,o.add_order_time,o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,o.order_amount,o.collected_amount,o.collection_status';

            $erpSaleCollection = $this->getModel('ErpSaleCollection')->getSaleCollectionList($where, $field, $param['start'], $param['length']);

        }else if($param['order_type'] == 1){
            //$field = 'c.*,us.user_name,cs.company_name,o.order_number,o.add_order_time,o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,o.order_amount,o.collected_amount,o.collection_status';
            $field = 'c.*,us.user_name,cs.company_name,o.order_number,o.add_order_time,o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,o.order_amount,o.collected_amount,ro.return_amount_status,ro.return_goods_num,ro.return_price';
            $field = 'c.*,o.user_id,o.order_number,o.add_order_time,o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,o.order_amount,o.collected_amount,ro.return_amount_status,ro.return_goods_num,ro.return_price';
            $erpSaleCollection = $this->getModel('ErpSaleCollection')->getSaleReturnedCollectionList($where, $field, $param['start'], $param['length']);

        }
        //空数据
        if (count($erpSaleCollection['data']) > 0) {
            $company_ids = array_unique(array_column($erpSaleCollection['data'], 'company_id'));
            $user_ids = array_unique(array_column($erpSaleCollection['data'], 'user_id'));
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1);
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 1);
            }
            foreach ($erpSaleCollection['data'] as $k => $v) {
                $erpSaleCollection['data'][$k]['collect_money'] = getNum($v['collect_money']);
                $erpSaleCollection['data'][$k]['total_collect_money'] = getNum($v['collect_money'] + $v['balance_deduction']);
                $erpSaleCollection['data'][$k]['order_amount'] = getNum($v['order_amount']);
                $erpSaleCollection['data'][$k]['collected_amount'] = getNum($v['collected_amount']);
                $erpSaleCollection['data'][$k]['balance_deduction'] = getNum($v['balance_deduction']);
                $erpSaleCollection['data'][$k]['delivery_money'] = getNum($v['delivery_money']);
                $erpSaleCollection['data'][$k]['no_collect_amount'] = getNum($v['order_amount'] - $v['collected_amount']);
                $erpSaleCollection['data'][$k]['order_collected_money'] = getNum($v['order_collected_money']);
                //$erpSaleCollection['data'][$k]['order_nocollect_money'] = getNum($v['order_amount'] - $v['order_collected_money']);
                $erpSaleCollection['data'][$k]['order_nocollect_money'] = $param['order_type'] == 0 ? getNum($v['order_amount'] - $v['order_collected_money']) : ($v['return_amount_status'] == 10 ? 0 : getNum(getNum($v['return_goods_num'] * $v['return_price'])));//order_nocollect_money
                //$erpSaleCollection['data'][$k]['collection_status'] = saleCollectionStatus($v['collection_status'],true);
                $erpSaleCollection['data'][$k]['collection_status'] = $param['order_type'] == 0 ? saleCollectionStatus($v['collection_status'],true) : returnedAmountStatus($v['return_amount_status'],true);
                $erpSaleCollection['data'][$k]['pay_type'] = saleOrderPayType($v['pay_type']);
                # 列表添加银行简称 qianbin  2018.8.7
                $erpSaleCollection['data'][$k]['bank_simple_name'] = empty($v['bank_simple_name']) ? '--' : $v['bank_simple_name'];
                //=========930改造 xiaowen ====================
                $erpSaleCollection['data'][$k]['company_name'] = $companyArr[$v['company_id']]['company_name'];
                $erpSaleCollection['data'][$k]['user_name'] = $userArr[$v['user_id']]['user_name'];
                //=========end 930改造=========================
            }
        } else {
            $erpSaleCollection['data'] = [];
        }
        $erpSaleCollection['recordsFiltered'] = $erpSaleCollection['recordsTotal'];
        $erpSaleCollection['draw'] = $_REQUEST['draw'];
        return $erpSaleCollection;
    }

    // +----------------------------------
    // |Facilitator:ERP损耗录入明细列表
    // +----------------------------------
    // |Author:senpai Time:2017.05.25
    // +----------------------------------
    public function erpSaleLossDetail($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['sale_order_id']))) {
            $where['l.sale_order_id'] = $param['sale_order_id'];
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        $field = 'l.*,us.user_name,cs.company_name,o.order_number,o.add_order_time,o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.loss_num,o.price,o.buy_num,o.delivery_money,o.order_amount,o.collected_amount,o.collection_status';
        $erpSaleCollection = $this->getModel('ErpSaleLoss')->getSaleLossList($where, $field, $param['start'], $param['length']);
        //空数据
        if (count($erpSaleCollection['data']) > 0) {
            foreach ($erpSaleCollection['data'] as $k => $v) {
                $erpSaleCollection['data'][$k]['order_amount'] = getNum($v['order_amount']);
                $erpSaleCollection['data'][$k]['loss_num'] = getNum(round($v['loss_num'] * getNum($v['price'])));
                $erpSaleCollection['data'][$k]['order_lossed_money'] = getNum($v['order_lossed_money']);
                $erpSaleCollection['data'][$k]['loss_amount'] = getNum($v['loss_amount']);
                $erpSaleCollection['data'][$k]['no_entered_loss_amount'] = getNum(round($v['loss_num'] * getNum($v['price'])) - $v['order_lossed_money']);
                $erpSaleCollection['data'][$k]['pay_type'] = saleOrderPayType($v['pay_type']);
            }
        } else {
            $erpSaleCollection['data'] = [];
        }
        $erpSaleCollection['recordsFiltered'] = $erpSaleCollection['recordsTotal'];
        $erpSaleCollection['draw'] = $_REQUEST['draw'];
        return $erpSaleCollection;
    }

    /**
     * 录入应收明细
     * @author senpai
     */
    public function addSaleCollection($param)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        //$all_collected_money = $this->getModel('ErpSaleCollection')->field('sum(collect_money) as total')->where(['sale_order_id' => $param['sale_order_id']])->group('sale_order_id')->find();
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['sale_order_id']]);
        //$all_collected_money['total'] = intval($all_collected_money['total']);
        log_info('此次收款：' . setNum(trim($param['collect_money']) + trim($param['balance_deduction'])));
        //log_info('已收金额：' . ($order_info['order_amount'] - intval($order_info['collected_amount'])));
        log_info('待收金额：' . bcsub($order_info['order_amount'], $order_info['collected_amount']));
        //log_info('判断大小：' . intval(setNum(trim($param['collect_money']))) > intval($order_info['order_amount'] - intval($all_collected_money['total'])) ? "大" : '小');
        log_info('判断大小：' . setNum(trim($param['collect_money']) + trim($param['balance_deduction'])) > bcsub($order_info['order_amount'], $order_info['collected_amount']) ? "大" : '小');

        if ($order_info['collection_status'] == 10 && $param['collect_money'] < 0) {
            $result['status'] = 101;
            $result['message'] = "已收款订单不允许退款，如需操作请联系技术部。";
            return $result;
        }
        if (trim($param['collect_money']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入收款金额";
            return $result;
        }
        if (trim($param['collect_money']) == 0 && trim($param['balance_deduction']) == 0) {
            $result['status'] = 101;
            $result['message'] = "收款金额不能为0";
            return $result;
        }
        if ($order_info['is_void'] == 1) {
            $result['status'] = 101;
            $result['message'] = "作废单据不允许收款";
            return $result;
        }

        if (intval(setNum(trim($param['collect_money']) + trim($param['balance_deduction']))) > intval(bcsub($order_info['order_amount'], $order_info['collected_amount']))) {
            //else if (bccomp(setNum(trim($param['collect_money'])), (intval($order_info['order_amount']) - intval($order_info['collected_amount'])), 0) == 1) {
            $result['status'] = 101;
            $result['message'] = "已收款金额不能超出订单总金额";
            return $result;
        }
        //edit xiaowen 2017-7-27 如果是二次定价订单，收款申请金额必须等于待收金额
        if($order_info['is_update_price'] == 1 && $order_info['is_returned'] == 2 && setNum(trim($param['collect_money']) + trim($param['balance_deduction'])) != bcsub($order_info['order_amount'], $order_info['collected_amount'])){
            $result['status'] = 102;
            $result['message'] = "二次定价订单，收款金额必须等于待收金额";
            return $result;
        }
        // 验证银行账套信息是否有效
        $bank_info = $this->getEvent('ErpBank')->getErpBankInfoById(intval($param['bank_id']));
        if (intval($param['bank_id']) > 0 && (empty($bank_info) || intval($bank_info['status']) != 1)) {
            $result['status']  = 21;
            $result['message'] = '该笔银行账号信息有误，请刷新后重试！';
            return $result;
        }

        if (getCacheLock('ErpFinance/addSaleCollection')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/addSaleCollection', 1);

        M()->startTrans();

        //退货产生的应收单独处理，不改原有逻辑
        if ($order_info['is_returned'] == 1) {
            $status = $this->addReturnSaleCollection($param,$order_info);
        } else {
            $erp_collect_data = [
                'collect_money' => setNum($param['collect_money']),
                'balance_deduction' => $param['balance_deduction'] ? setNum($param['balance_deduction']) : 0,
                'order_collected_money' => $order_info['collected_amount'] + setNum($param['collect_money'] + $param['balance_deduction']),
                'sale_order_id' => $param['sale_order_id'],
                'sale_order_number' => $param['sale_order_number'],
                'creator' => $this->getUserInfo('dealer_name'),
                'creator_id' => $this->getUserInfo('id'),
                'create_time' => currentTime(),
                'collect_time' => currentTime(),
                'status' => 1,
                'remark' => $param['remark'],
                'source_order_type' => 1,
                'from_sale_order_number' => $order_info['order_number'],
                'company_id' => $order_info['company_id'],
                'our_company_id' => $order_info['our_company_id'],
                /** 银行账套信息改版 - qianbin - 2018.08.09 */
                'bank_id'          => intval($param['bank_id']),
                'bank_simple_name' => intval($param['bank_id']) <= 0 ? "" : trim($bank_info['bank_simple_name']),
                'is_prestore_money'=> intval($param['is_prestore_money']),
                'bank_info'        => intval($param['bank_id']) <= 0 ? " " : json_encode($bank_info,JSON_UNESCAPED_UNICODE),
                /** 银行账套信息改版 - qianbin - 2018.08.07 */
            ];

            //添加erp收款信息
            $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collect_data);

            //预存账户抵扣
            if ($param['balance_deduction']) {
                $order_info['company_id'] = $order_info['company_id'];
                $order_info['our_company_id'] = $order_info['our_company_id'];
                $order_info['object_type'] = 3;//单据类型：销售单
                $accout_status = $this->getEvent('ErpAccount')->changeAccount($order_info, PRESTORE_TYPE, setNum($param['balance_deduction']) * -1);
            } else {
                $accout_status = true;
            }

            //定金锁价订单的应付定金
            $earnest = round(getNum($order_info['order_amount']) * getNum($order_info['prepay_ratio']) / 100, 2);
            //$earnest = setNum($earnest);
            //最新已收金额
            //$collected_money = trim($param['collect_money']) + getNum($all_collected_money['total']);
            $collected_money = setNum(trim($param['collect_money'])) + setNum(trim($param['balance_deduction'])) + $order_info['collected_amount'];
            log_info("已收金额:" . $collected_money);
            log_info("订单金额：" . $order_info['order_amount']);
            //log_info("收款状态：" . $collected_money == $order_info['order_amount'] ? '已收' : '部分');
            //是否影响库存
            $is_effect = false;
            $stock_status = true;
            $sale_order_log_type = 9;
            if ($collected_money <= 0) {
                $collection_status = 1;
                //录入负数的逻辑
                if ($param['collect_money'] < 0 && $order_info['pay_type'] == 5) {
                    $change_num = $order_info['total_sale_wait_num'] * -1;
                    if ($change_num < 0) {
                        $is_effect = true;
//                            $stock_status = $this->saleEarnestPayChangeStock($order_info,$change_num);
                    }
                }
            } elseif ($collected_money < setNum($earnest) && $order_info['pay_type'] == 5) {
                $collection_status = 2;
                //录入负数的逻辑
                if ($param['collect_money'] < 0) {
                    $change_num = $order_info['total_sale_wait_num'] * -1;
                    if ($change_num < 0) {
                        $is_effect = true;
//                            $stock_status = $this->saleEarnestPayChangeStock($order_info,$change_num);
                    }
                }
            } elseif ($collected_money == setNum($earnest) && $order_info['pay_type'] == 5) {
                $collection_status = 3;
                //录入负数的逻辑
                if ($param['collect_money'] < 0) {
                    $change_num = $order_info['total_sale_wait_num'] * -1;
                    if ($change_num < 0) {
                        $is_effect = true;
//                            $stock_status = $this->saleEarnestPayChangeStock($order_info,$change_num);
                    }
                }
            } elseif ($collected_money > setNum($earnest) && $collected_money < $order_info['order_amount'] && $order_info['pay_type'] == 5) {
                $collection_status = 4;
                $sale_order_log_type = 9;
                //转待提数量
                /**
                 * 这里做一个预防
                 * 预防他这笔支付满足定金同时还有剩余
                 * 影响库存的只是多出来的这部分
                 */
                if ($order_info['collection_status'] < 3) {
                    //$change_num = floor(setNum(($param['collect_money'] + getNum($order_info['collected_amount']) - $earnest) / getNum($order_info['price'])));
                    // edit xiaowen 2017-6-10 为保证舍去取两位小数 先 * 100 取含两小位整数 再除 100
                    $change_num = floor(setNum(($param['collect_money'] + getNum($order_info['collected_amount']) + trim($param['balance_deduction']) - $earnest) / getNum($order_info['price'])));
                    log_info("部分预付转待提:" . $change_num.'; 付款金额：' . setNum(($param['collect_money'] + getNum($order_info['collected_amount'])+ trim($param['balance_deduction']) - $earnest) / getNum($order_info['price'])));
                } else {

                    // edit xiaowen 2017-6-10 为保证舍去取两位小数 先 * 100 取含两小位整数 再除 100
                    if ($param['collect_money'] < 0) {
                        $change_num = ceil(setNum($param['collect_money'] / getNum($order_info['price'])));
                    } else {
                        $change_num = floor(setNum(($param['collect_money'] +  trim($param['balance_deduction']))  / getNum($order_info['price'])));
                    }
                    log_info("已预付转待提:" . $change_num .'; 付款金额：' . setNum($param['collect_money'] / getNum($order_info['price'])));
                }
                //------------定金锁价在这个时段会影响库存，根据付款比例将销售预留转为销售待提----------------------
                $is_effect = true;
//                    $stock_status = $this->saleEarnestPayChangeStock($order_info,$change_num);
//                    $stock_message = $stock_status ? '操作成功' : '操作失败';
//                    log_info('销售单:'. $order_info['order_number'] . ', 部分收款时，库存更新操作' . $stock_message);
                //--------------------------------------------------------------------------------------------------
            } elseif ($collected_money > 0 && intval(round($collected_money)) < intval(round($order_info['order_amount']))) {
                $collection_status = 4;
                $sale_order_log_type = 9;
            } elseif(intval(round($collected_money)) == intval(round($order_info['order_amount']))) {
                $collection_status = 10;
                $sale_order_log_type = 8;
                //全部付款后 转待提数量
                $change_num = $order_info['buy_num'] - $order_info['total_sale_wait_num'];

                /**
                 * 这里做一个预防
                 * 预防他这笔支付满足定金同时还有剩余
                 * 影响库存的只是多出来的这部分
                 */
//                    if ($order_info['collection_status'] < 3) {
//                        //----------edit xiaowen 2017-6-10 转待提数量可以为两位小数---------------------------------
//                        //$change_num = floor(setNum(($param['collect_money'] + getNum($order_info['collected_amount']) - $earnest) / getNum($order_info['price'])) + ($order_info['buy_num'] * $order_info['prepay_ratio'] / 10000));
//                        $change_num = floor( (setNum(($param['collect_money'] + getNum($order_info['collected_amount']) - $earnest) / getNum($order_info['price'])) + ($order_info['buy_num'] * $order_info['prepay_ratio'] / 10000) ));
//
//                    } else {
//                        $change_num = $order_info['buy_num'] - $order_info['total_sale_wait_num'];
//                    }

                //-------------全部付款成功后，改变库存-------------------------------------------------------------
                $is_effect = true;
//                    if ($order_info['pay_type'] == 5) {
//                        //定金锁价方式在全部付款后有区别
//                        $stock_status = $this->saleEarnestPayChangeStock($order_info,$change_num);
//                        $stock_message = $stock_status ? '操作成功' : '操作失败';
//                        log_info('销售单:'. $order_info['order_number'] . ', 完成收款时，库存更新操作' . $stock_message);
//                    } else {
//                        $stock_status = $this->orderFullPayChangeStock($order_info);
//                        $stock_message = $stock_status ? '操作成功' : '操作失败';
//                        log_info('销售单:'. $order_info['order_number'] . ', 完成收款时，库存更新操作' . $stock_message);
//                    }
                //--------------------------------------------------------------------------------------------------
            }
            //edit xiaowen 2017-7-17 如果订单是二次定价的，无须影响库存

            if($order_info['is_update_price'] == 1){
                $is_effect = false;
            }

            if ($is_effect && $order_info['pay_type'] == 5) {
                $stock_status = $this->saleEarnestPayChangeStock($order_info,$change_num);
            } elseif ($is_effect && (in_array($order_info['pay_type'],[1,3]))) {
                $stock_status = $this->orderFullPayChangeStock($order_info);
            }

            $erp_order_data = [
                'collection_status' => $collection_status,
                'collected_amount' => $erp_collect_data['order_collected_money'],
                'updater' => $this->getUserInfo('id'),
                'update_time' => currentTime(),
                'total_sale_wait_num' => $is_effect ? $order_info['total_sale_wait_num'] + $change_num : $order_info['total_sale_wait_num']
            ];

            //添加erp收款信息
            $status_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $param['sale_order_id']],$erp_order_data);

            $log_data = [
                'sale_order_id' => $param['sale_order_id'],
                'sale_order_number' => $param['sale_order_number'],
                'log_type' => $sale_order_log_type,
                'log_info' => serialize($order_info),
                'create_time' => currentTime(),
                'operator' => $this->getUserInfo('dealer_name'),
                'operator_id' => $this->getUserInfo('id'),
            ];
            $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);
            if ($status_collection && $status_order && $status_log && $stock_status && $accout_status) {
                $status = true;
            } else {
                $status = false;
            }
        }
        /*********************************************
        @ Content 处理内部交易单流程
        @ Author  Yf
        @ Time    2019-04-02
         **********************************************/
        if ($order_info['business_type'] == 4 && !empty($order_info['retail_inner_order_number']) ) {
            /* ------- 操作采购单的收款逻辑 -------- */
            $result_status = $this->purchaseOrderPaymentByInternalOrder($order_info['retail_inner_order_number'],trim($param['collect_money']));
            if ( $result_status['status'] != 1 ) {
                M()->rollback();
                cancelCacheLock('ErpFinance/addSaleCollection');
                return $result;
            }
        }
        /*********************************************
        END
         **********************************************/
        if ($status) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '申请成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '操作失败';
        }
        cancelCacheLock('ErpFinance/addSaleCollection');
        return $result;
    }

    /**************************************
    @ Content 采购单根据内部交易单进行付款
    @ Author  YF
    @ Time    2019-04-02
    @ Param [
    retail_inner_order_number 内部交易单号
    ]
     ***************************************/
    public function purchaseOrderPaymentByInternalOrder($retail_inner_order_number = 0,$price = 0)
    {
        if ( $retail_inner_order_number == 0 && $price == 0 ) {
            return ['status' => 11 ,'message' => '缺少参数！'];
        }
        /* -------------- 查询采购单信息 ------------------- */
        $purchase_order_where['retail_inner_order_number'] = ['eq',$retail_inner_order_number];
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->where($purchase_order_where)->find();
        if ( !isset($purchase_order_info['retail_inner_order_number']) ) {
            return ['status' => 12 ,'message' => '未查询到所对应的采购单！'];
        }
        /* ----------- 获取银行账号信息 ----------------- */
        $bank_info = $purchase_order_info['sale_collection_info'];//银行账号信息
        $bank_arr = explode('--',$bank_info);
        $bank_where['bank_name'] = ['eq',$bank_arr[0]];
        $bank_where['bank_num']  = ['eq',$bank_arr[1]];
        $bank_where['default_bank'] = ['eq',1];
        $bank_where['pay_type']  = ['eq',2];
        $bank_arrs = $this->getModel('ErpBank')->where($bank_where)->find();
        if ( !isset($bank_arrs['id']) ) {
            return ['status' => 13 ,'message' => '内部交易采购单 缺少银行信息！'];
        }

        /* --------------- 添加采购付款信息 ----------------- */
        $erp_payment_data = [
            'pay_money'             => setNum($price), // 申请金额
            'purchase_id'           => $purchase_order_info['id'],       // 采购单ID
            'purchase_order_number' => $purchase_order_info['order_number'], // 采购单号
            'create_time'           => DateTime(),                      // 创建时间
            'creator'               => $this->getUserInfo('dealer_name'),  //交易员
            'creator_id'            => $this->getUserInfo('id'),           // 交易员ID
            'apply_pay_time'        => DateTime(), //申请付款时间
            // 'status'                => 1, // 付款状态： 1 已申请、2已驳回、3 已同意、  10已付款
            'remark'                => '内部交易单', // 备注
            'our_company_id'        => $purchase_order_info['our_buy_company_id'], // 账套ID
            'sale_company_id'       => $purchase_order_info['sale_company_id'],     // 服务商ID
            'dealer_id'             => $purchase_order_info['buyer_dealer_id'],    // 来源单号交易员
            'from_purchase_order_number' => $purchase_order_info['order_number'],

            'status'                => 10,
            'balance_deduction'     =>  0, // 账户余额抵扣
            'auditor'               => $this->getUserInfo('dealer_name'), // 审核人姓名
            'auditor_id'            => $this->getUserInfo('id'),  // 审核人ID
            'auditor'               => $this->getUserInfo('dealer_name'),
            'audit_time'            => currentTime(), // 审核时间
            'pay_time'              => currentTime(),  //支付时间
            'bank_id'               => intval($bank_arrs['id']),
            'bank_simple_name'      => $bank_arrs['bank_simple_name'],
            'is_prepay_money'       => 2,
            'bank_info'             => json_encode($bank_arrs,JSON_UNESCAPED_UNICODE),

        ];
        // 添加erp付款申请信息
        $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);
        if ( !$status_payment ) {
            return ['status' => 14, 'message' => '添加erp付款申请信息失败！'];
        }
        // 现已付金额
        $payed_money = $purchase_order_info['payed_money'] + setNum($price);

        if ( $payed_money != $purchase_order_info['order_amount'] ) {
            $pay_status = 4;
        } else {
            $pay_status = 10;
        }
        //更新订单信息
        $update_order_data = [
            'payed_money'             => $payed_money, // 已付金额
            'pay_status'              => $pay_status,  // 付款状态
            'no_payed_money'          => $purchase_order_info['order_amount'] - $payed_money, // 未付金额
            'total_purchase_wait_num' => 0,
        ];
        $status_order = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => $purchase_order_info['id']], $update_order_data);
        if ( !$status_order ) {
            return ['status'=> 15 ,'message' =>'修改采购单信息失败！'];
        }
        $log_data = [
            'purchase_id'           => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'log_type'              => 9,
            'log_info'              => serialize($purchase_order_info),
            'create_time'           => DateTime(),
            'operator'              => $this->getUserInfo('dealer_name'),
            'operator_id'           => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);
        if ( !$status_log ) {
            return ['status' => 16, 'message' => '添加采购单日志失败！'];
        }

        return ['status' => 1, 'message' => '处理成功'];
    }

    /**
     * 退货单录入应收明细逻辑（单独处理）
     * @author senpai
     */
    public function addReturnSaleCollection($param,$order_info)
    {
        //最新已收金额
        $collected_money = setNum(trim($param['collect_money'])) + $order_info['collected_amount'];
        $erp_collect_data = [
            'collect_money' => setNum($param['collect_money']),
            'order_collected_money' => $collected_money,
            'sale_order_id' => $param['sale_order_id'],
            'sale_order_number' => $param['sale_order_number'],
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'collect_time' => currentTime(),
            'status' => 1,
            'remark' => $param['remark'],
            /* 销退未查询到单笔红冲
            'source_order_type' => 1,
            'from_sale_order_number' => $order_info['order_number'],
            'company_id' => $order_info['company_id'],
            'our_company_id' => $order_info['our_company_id'],
            */
        ];

        //添加erp收款信息
        $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collect_data);

        log_info("已收金额:" . $collected_money);
        log_info("订单金额：" . $order_info['order_amount']);

        //订单总额（包括退货金额）
        $total_amount = $order_info['order_amount'] - round(getNum($order_info['returned_goods_num'] * $order_info['price']),-2);

        //判断最新已收金额是否等于订单总额
        if ($collected_money != $total_amount) {
            $collection_status   = 4;
            $sale_order_log_type = 9;
        } elseif ($collected_money == $total_amount) {
            $collection_status   = 10;
            $sale_order_log_type = 8;
        }

        //修改原订单收款状态
        $erp_order_data = [
            'collection_status' => $collection_status,
            'collected_amount' => $collected_money,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $status_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $param['sale_order_id']],$erp_order_data);

        //添加log日志
        $log_data = [
            'sale_order_id' => $param['sale_order_id'],
            'sale_order_number' => $param['sale_order_number'],
            'log_type' => $sale_order_log_type,
            'log_info' => serialize($order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);

//        if ($collection_status == 10) {
//            //修改退货单的退货状态
//            $returned_order_data = [
//                'return_amount_status' => 10,
//                'update_time' => $this->date,
//                'updater_id' => $this->getUserInfo('id'),
//            ];
//            $returned_order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['source_order_id' => intval($order_info['id']),'source_order_number'=>$order_info['order_number']], $returned_order_data);
//        } else {
//            $returned_order_status = true;
//        }
        //修改退货单的退货状态
        $returned_order_data = [
            'return_amount_status' => 10,
            'update_time' => $this->date,
            'updater_id' => $this->getUserInfo('id'),

        ];
        $returned_order_status = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['source_order_id' => intval($order_info['id']),'source_order_number'=>$order_info['order_number']], $returned_order_data);

        if ($status_collection && $status_order && $status_log && $returned_order_status) {
            $status = true;
        } else {
            $status = false;
        }
        return $status;
    }

    /**
     * 录入损耗明细
     * @author senpai
     */
    public function addSaleLoss($param)
    {
        if (getCacheLock('ErpFinance/addSaleLoss')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/addSaleLoss', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['sale_order_id']]);
            if (trim($param['loss_amount']) == "") {
                $result['status'] = 101;
                $result['message'] = "请输入收款金额";
            } elseif (trim($param['loss_amount']) > (getNum(round($order_info['loss_num'] * getNum($order_info['price']))) - getNum('entered_loss_amount'))) {
                $result['status'] = 101;
                $result['message'] = "已退款金额不能超出损耗总金额";
            } else {
                M()->startTrans();
                $erp_loss_data = [
                    'sale_order_id' => $param['sale_order_id'],
                    'sale_order_number' => $param['sale_order_number'],
                    'order_lossed_money' => $order_info['entered_loss_amount'] + setNum($param['loss_amount']),
                    'loss_amount' => setNum($param['loss_amount']),
                    'creator' => $this->getUserInfo('dealer_name'),
                    'creator_id' => $this->getUserInfo('id'),
                    'create_time' => currentTime(),
                    'return_time' => currentTime(),
                    'status' => 1,
                    'remark' => $param['remark']
                ];

                //添加erp损耗信息
                $status_loss = $this->getModel('ErpSaleLoss')->addSaleLoss($erp_loss_data);

                //判断损耗是否处理完
                if ($param['loss_amount'] == round(getNum($order_info['loss_num']) * getNum($order_info['price']) - getNum($order_info['entered_loss_amount']), 2)) {
                    $is_loss = 3;
                } else {
                    $is_loss = 1;
                }

                $erp_order_data = [
                    'is_loss' => $is_loss,
                    'entered_loss_amount' => $order_info['entered_loss_amount'] + setNum($param['loss_amount']),
                    'updater' => $this->getUserInfo('id'),
                    'update_time' => currentTime(),
                ];

                //添加erp收款信息
                $status_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $param['sale_order_id']],$erp_order_data);

                $log_data = [
                    'sale_order_id' => $param['sale_order_id'],
                    'sale_order_number' => $param['sale_order_number'],
                    'log_type' => 17,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);
                if ($status_loss && $status_order && $status_log) {
                    M()->commit();
                    $result['status'] = 1;
                    $result['message'] = '申请成功';
                } else {
                    M()->rollback();
                    $result['status'] = 0;
                    $result['message'] = isset($err_message) ? $err_message : '操作失败';
                }
            }
        }
        cancelCacheLock('ErpFinance/addSaleLoss');
        return $result;
    }

    /**
     * 【已取消使用】 2018.08.08 财务应收应付改造 qianbin
     * 整单收款
     * @author senpai
     */
    public function confirmSaleCollection($param)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }

        //销退的整单收款加入流程限制——销退单对应的入库单未审核不允许退款
        if ($param['order_type'] == 1) {
            $returned_order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => ['in' , $param['id']]]);
            $order_info = $this->getModel('ErpSaleOrder')->where(['id' => $returned_order_info['source_order_id'],'order_number' => $returned_order_info['source_order_number']])->find();
            if ($order_info['is_returned'] == 1 && $order_info['returned_goods_num'] == 0) {
                return ['status' => 2 , 'message' => '退货单对应入库单未审核，无法退款！'];
            }
        }
        if (getCacheLock('ErpFinance/confirmSaleCollection')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/confirmSaleCollection', 1);

        M()->startTrans();
        $status = 2;
        $wrong_status = 1;
        $wrong_str = '';
        foreach ($param['id'] as $id) {
            //edit xiaowen 如果是销退单确认收款，走单独处理
            if($param['order_type'] == 1){
                $field = 'ro.*,o.company_id';
                $returned_order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrderJoinOrder(['ro.id'=>intval($id),'ro.order_type' => 1],$field);
                # 本方法已取消使用 qianbin 2018.08.08
                $status = $this->confirmReturnedCollection($returned_order_info);
                if($status){
                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                    ];
                }else{
                    M()->rollback();
                    $result = [
                        'status' => 0,
                        'message' => '操作失败',
                    ];
                }
                cancelCacheLock('ErpFinance/confirmSaleCollection');
                return $result;
            }
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if ($order_info['collection_status'] == 1) {
                $erp_order_data = [
                    'collection_status' => 10,
                    'collected_amount' => $order_info['order_amount'],
                    'updater' => $this->getUserInfo('id'),
                    'update_time' => currentTime()
                ];
                if ($order_info['pay_type'] == 5) {
                    $erp_order_data['total_sale_wait_num'] = $order_info['buy_num'];
                }
                $status_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $id],$erp_order_data);

                $erp_collect_data = [
                    'collect_money' => $order_info['order_amount'] - $order_info['collected_amount'],
                    'order_collected_money' => $order_info['order_amount'],
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'creator' => $this->getUserInfo('dealer_name'),
                    'creator_id' => $this->getUserInfo('id'),
                    'create_time' => currentTime(),
                    'collect_time' => currentTime(),
                    'status'       => 1,
                    'source_order_type' => 1,
                    'from_sale_order_number' => $order_info['order_number'],
                    'company_id' => $order_info['company_id'],
                    'our_company_id' => $order_info['our_company_id'],
                ];
                //添加erp收款信息
                $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collect_data);

                $log_data = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'log_type' => 8,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);
                //-------------付款成功后，改变库存-------------------------------------------------------------
                //edit xiaowen 2017-7-17 如果不是二次定价的订单，需要影响库存，否则 无须影响库存
                if($order_info['is_update_price'] == 2){
                    $stock_status = $this->orderFullPayChangeStock($order_info);
                }else{
                    $stock_status = true;
                }

                $stock_message = $stock_status ? '操作成功' : '操作失败';
                log_info('销售单:'. $order_info['order_number'] . ', 完成收款时，库存更新操作' . $stock_message);

                if ($status_order && $status_collection && $status_log && $stock_status) {
                    $status = 1;
                } else {
                    $status = 2;
                    break;
                }
            } else {
                $wrong_status = 2;
                $wrong_str .= $order_info['order_number'].',';
            }
        }

        $wrong_str = trim($wrong_str,',');

        if ($status == 1 && $wrong_status == 1) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '收款完成';
        } elseif ($wrong_status == 2) {
            M()->rollback();
            $result['status'] = 0;
            $result['wrong_message'] = $wrong_str.'订单收款状态有误，只有未收款状态订单才可整单收款';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '操作失败';
        }
        cancelCacheLock('ErpFinance/confirmSaleCollection');
        return $result;
    }

    /**
     * 销售发票列表
     * @author senpai 2017-04-21
     * @param $param
     */
    public function erpSaleInvoiceList($param = [])
    {
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.add_order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        if (trim($param['sale_company_id'])) {
            $where['o.company_id'] = intval(trim($param['sale_company_id']));
        }
        if ($param['status']) {
            $where['o.order_status'] = intval($param['status']);
        }

        if ($param['invoice_status']) {
            $where['o.invoice_status'] = $param['invoice_status'];
        } elseif ($param['invoice']) {
            $where['o.invoice_status'] = ['neq', 10];
        }

        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }

        //我的销售单
        if (trim($param['dealer_id'])) {
            $where['o.dealer_id'] = trim($param['dealer_id']);
        }
        if (trim($param['is_update_price'])) {
            $where['o.is_update_price'] = intval(trim($param['is_update_price']));
        }
        if ($param['collection_status']) {
            $where['o.collection_status'] = intval($param['collection_status']);
        }
        if ($param['pay_type']) {
            $where['o.pay_type'] = intval($param['pay_type']);
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        //过滤零售订单
        $where['o.order_type'] = 1;

        //订单总额为0的订单不在销售发票中显示
        $where['o.order_amount - o.price * o.returned_goods_num / 10000'] = ['neq',0];

        //$field = 'o.*,si.creator as i_creator,MAX(si.create_time) as invoice_time,si.invoice_sn,d.depot_name,cs.bank_name,cs.bank_num,cs.tax_num,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';
        $field = 'o.*,si.creator as i_creator,MAX(si.create_time) as invoice_time,si.invoice_sn,d.depot_name,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';

        if ($param['export']) {
            $data = $this->getModel('ErpSaleOrder')->getAllSaleOrderList($where, $field);
        } else {
            $data = $this->getModel('ErpSaleOrder')->getSaleOrderList($where, $field, $param['start'], $param['length']);
        }

        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];

            //============930 改造================
            $company_ids = array_unique(array_column($data['data'], 'company_id'));
            $user_ids = array_unique(array_column($data['data'], 'user_id'));
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1, 'id,registered_bank as bank_name,registered_bank_number as bank_num  ,tax_number as tax_num,customer_name as company_name');
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 1);
            }

            //======end 930改造====================
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                //拼接发票号
                if ($value['invoice_time']) {
                    $data['data'][$key]['invoice_sn'] = '';
                    $invoice_sn = $this->getModel('ErpSaleInvoice')->getAllSaleInvoiceList(['sale_order_id'=>$value['id']]);
                    foreach ($invoice_sn['data'] as $k => $v) {
                        $data['data'][$key]['invoice_sn'] .= $v['invoice_sn'].',';
                    }
                    $data['data'][$key]['invoice_sn'] = trim($data['data'][$key]['invoice_sn'],',');
                }
                $data['data'][$key]['No'] = $i;
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['collection_status_font'] = saleCollectionStatus($value['collection_status']);
                $data['data'][$key]['collection_status'] = saleCollectionStatus($value['collection_status'], true);
                $data['data'][$key]['invoice_status_font'] = saleInvoiceStatus($value['invoice_status']);
                $data['data'][$key]['invoice_status'] = saleInvoiceStatus($value['invoice_status'], true);
                //$data['data'][$key]['pay_type'] = purchasePayType($value['pay_type']); //去除 下面已正确取了销售单付款方式
                $data['data'][$key]['order_status'] = SaleOrderStatus($value['order_status'], true);
                $data['data'][$key]['is_upload_contract'] = purchaseContract($value['is_upload_contract'], true);
                //$data['data'][$key]['from_sale_order_number'] = $value['from_sale_order_number'] ? $value['from_sale_order_number'] : '--';
                $data['data'][$key]['price'] = $value['price'] > 0 ? round(getNum($value['price']),2) : '0.00';
                $data['data'][$key]['order_amount'] = $value['order_amount'] > 0 ? getNum($value['order_amount']) : '0';
                $data['data'][$key]['total_amount'] = getNum($value['order_amount']) - round(getNum(getNum($value['loss_num'] * $value['price']) + getNum($value['price'] * $value['returned_goods_num'])), 2);
                $data['data'][$key]['invoice_money'] = $value['invoice_money'] > 0 ? getNum($value['invoice_money']) : '0';
                $data['data'][$key]['invoiced_amount'] = $value['invoiced_amount'] > 0 ? getNum($value['invoiced_amount']) : '0';
                $data['data'][$key]['no_invoice_amount'] = $data['data'][$key]['total_amount'] - getNum($value['invoiced_amount']);
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? round(getNum($value['buy_num'] - $value['loss_num']),4) : '0';
                $data['data'][$key]['returned_goods_num'] = $value['returned_goods_num'] > 0 ? getNum($value['returned_goods_num']) : '0';
                # 新增实际销售数量 qianbin 2018-04-16
                $data['data'][$key]['actual_goods_num'] = round($data['data'][$key]['buy_num'] - $data['data'][$key]['returned_goods_num'],4);
                $data['data'][$key]['delivery_money'] = $value['delivery_money'] > 0 ? getNum($value['delivery_money']) : '0';
                $data['data'][$key]['outbound_quantity'] = $value['outbound_quantity'] > 0 ? getNum($value['outbound_quantity']) : '0';
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];

                //$data['data'][$key]['s_company_name'] = $value['company_id'] == 99999 ? '不限' : $data['data'][$key]['s_company_name'];
                //=========930改造===============================
                $data['data'][$key]['s_company_name'] = $companyArr[$value['company_id']]['company_name'];
                $data['data'][$key]['bank_name'] = $companyArr[$value['company_id']]['bank_name'];
                $data['data'][$key]['bank_num'] = $companyArr[$value['company_id']]['bank_num'];
                $data['data'][$key]['tax_num'] = $companyArr[$value['company_id']]['tax_num'];
                $data['data'][$key]['user_name'] = $userArr[$value['user_id']]['user_name'];
                $data['data'][$key]['user_phone'] = $userArr[$value['user_id']]['user_phone'];
                //====end 930 改造================================
                $data['data'][$key]['order_source'] = saleOrderSourceFrom($value['order_source'], true);
                $data['data'][$key]['pay_type'] = saleOrderPayType($value['pay_type'], true);
                $data['data'][$key]['residue_time'] = getSaleOrderResidueTime($value['end_order_time'], currentTime()); //截单剩余时间 精确到分
                //是否二次定价 edit xiaowen 2017-7-24
                $data['data'][$key]['is_update_price'] = orderUpdatePriceStatus($value['is_update_price'], true);

                $i++;
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    // +----------------------------------
    // |Facilitator:ERP发票明细列表
    // +----------------------------------
    // |Author:senpai Time:2017.4.17
    // +----------------------------------
    public function erpSaleInvoiceDetail($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['sale_order_id']))) {
            $where['i.sale_order_id'] = $param['sale_order_id'];
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        //$field = 'i.*,us.user_name,cs.bank_name,cs.bank_num,cs.tax_num,cs.company_name,o.order_number,o.add_order_time,
        //o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,
        //o.order_amount,o.collected_amount,o.collection_status,o.company_info,o.invoiced_amount,o.loss_num,o.returned_goods_num';

        $field = 'i.*,o.order_number,o.add_order_time,
        o.pay_type,o.dealer_id,o.dealer_name,o.company_id,o.user_bank_info,o.goods_id,o.price,o.buy_num,o.delivery_money,
        o.order_amount,o.collected_amount,o.collection_status,o.company_info,o.invoiced_amount,o.loss_num,o.returned_goods_num';
        $erpSaleInvoice = $this->getModel('ErpSaleInvoice')->getSaleInvoiceList($where, $field, $param['start'], $param['length']);
        //空数据
        if (count($erpSaleInvoice['data']) > 0) {
            $company_ids = array_unique(array_column($erpSaleInvoice['data'], 'company_id'));
            //log_info(print_r($company_ids, true));
            $user_ids = array_unique(array_column($erpSaleInvoice['data'], 'user_id'));
            //log_info(print_r($user_ids, true));
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1, 'id,registered_bank as bank_name,registered_bank_number as bank_num  ,tax_number as tax_num,customer_name as company_name');
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 1);
            }
            foreach ($erpSaleInvoice['data'] as $k => $v) {
                $erpSaleInvoice['data'][$k]['invoice_money'] = getNum($v['invoice_money']);
                $erpSaleInvoice['data'][$k]['order_amount'] = getNum($v['order_amount']) - round(getNum(getNum($v['loss_num'] * $v['price'])),2);
                $erpSaleInvoice['data'][$k]['delivery_money'] = getNum($v['delivery_money']);
                $erpSaleInvoice['data'][$k]['no_invoice_amount'] =  round(getNum($v['order_amount'] - getNum($v['price'] * $v['returned_goods_num']) - $v['invoiced_amount']), 2);
                //$erpSaleInvoice['data'][$k]['order_no_invoice_money'] = round(getNum($v['order_amount'] - $v['order_invoiced_money']), 2);
                $erpSaleInvoice['data'][$k]['order_no_invoice_money'] = round($erpSaleInvoice['data'][$k]['order_amount'] - getNum($v['order_invoiced_money'] + getNum($v['price'] * $v['returned_goods_num'])), 2);

                $erpSaleInvoice['data'][$k]['invoice_type'] = invoiceType($v['invoice_type']);
                $erpSaleInvoice['data'][$k]['pay_type'] = purchasePayType($v['pay_type']);
                //930改造
                $erpSaleInvoice['data'][$k]['company_name'] = $companyArr[$v['company_id']]['company_name'];

                $erpSaleInvoice['data'][$k]['bank_name'] = $companyArr[$v['company_id']]['bank_name'];
                $erpSaleInvoice['data'][$k]['bank_num'] = $companyArr[$v['company_id']]['bank_num'];
                $erpSaleInvoice['data'][$k]['tax_num'] = $companyArr[$v['company_id']]['tax_num'];
                $erpSaleInvoice['data'][$k]['user_name'] = $userArr[$v['user_id']]['user_name'];
                $erpSaleInvoice['data'][$k]['user_phone'] = $userArr[$v['user_id']]['user_phone'];
            }
        } else {
            $erpSaleInvoice['data'] = [];
        }
        log_info(print_r($erpSaleInvoice['data'], true));
        $erpSaleInvoice['recordsFiltered'] = $erpSaleInvoice['recordsTotal'];
        $erpSaleInvoice['draw'] = $_REQUEST['draw'];
        return $erpSaleInvoice;
    }

    /**
     * 录入发票明细
     * @author senpai
     */
    public function addSaleInvoice($param)
    {
        if (getCacheLock('ErpFinance/addSaleInvoice')) return ['status' => 99, 'message' => $this->running_msg];

        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        //$all_invoiced_money = $this->getModel('ErpSaleInvoice')->field('sum(invoice_money) as total')->where(['sale_order_id' => $param['sale_order_id']])->group('sale_order_id')->find();
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['sale_order_id']]);
        log_info('本次开票金额：' . (trim($param['notax_invoice_money']) + trim($param['tax_money'])));
        log_info('待开票金额：' . (round(getNum($order_info['order_amount']) - round(getNum($order_info['loss_num']) * getNum($order_info['price']), 2) - round(getNum($order_info['returned_goods_num']) * getNum($order_info['price']), 2) - getNum($order_info['invoiced_amount']),2)));

        if (trim($param['invoice_sn']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入发票号码";
            return $result;
        }
        if (trim($param['invoice_type']) == "") {
            $result['status'] = 102;
            $result['message'] = "请选择发票类型";
            return $result;
        }
        if (trim($param['notax_invoice_money']) == "") {
            $result['status'] = 103;
            $result['message'] = "请输入未税金额";
            return $result;
        }
        if (trim($param['tax_money']) == "") {
            $result['status'] = 104;
            $result['message'] = "请输入税额";
            return $result;
        }
        //不允许从erp后台直接录入正向发票  2019-06-27 财务中台接口项目
        if ($param['notax_invoice_money'] + $param['tax_money'] > 0 && !in_array($order_info['our_company_id'],[70,22,18])) {
            $result['status'] = 105;
            $result['message'] = "和瑞，誉州，昊瑞以外的账套公司请到税务系统进行开票";
            return $result;
        }
        if (intval(setNum($param['notax_invoice_money'] + $param['tax_money'])) > intval($order_info['order_amount'] - ($order_info['loss_num'] + $order_info['returned_goods_num']) * getNum($order_info['price']) - $order_info['invoiced_amount']) && !$param['id']) {
            $result['status'] = 106;
            $result['message'] = "已开发票金额不能超出订单总金额";
            return $result;
        }
        setCacheLock('ErpFinance/addSaleInvoice', 1);
        M()->startTrans();

        if ($param['id']) {
            //修改销售发票信息
            $erp_invoice_data = [
                'invoice_sn' => $param['invoice_sn'],
                'invoice_type' => $param['invoice_type'],
                'remark' => $param['remark']
            ];
            $status_invoice = $this->getModel('ErpSaleInvoice')->saveSaleInvoice(['id' => $param['id']],$erp_invoice_data);

            if ($status_invoice) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '申请成功';
            } else {
                M()->rollback();
                $result['status'] = 0;
                $result['message'] = isset($err_message) ? $err_message : '操作失败';
            }
        } else {
            //添加销售发票信息
            $erp_invoice_data = [
                'sale_order_id' => $param['sale_order_id'],
                'sale_order_number' => $param['sale_order_number'],
                'invoice_sn' => $param['invoice_sn'],
                'notax_invoice_money' => setNum($param['notax_invoice_money']),
                'tax_money' => setNum($param['tax_money']),
                'invoice_money' => setNum($param['notax_invoice_money'] + $param['tax_money']),
                'order_invoiced_money' => $order_info['invoiced_amount'] + setNum($param['notax_invoice_money'] + $param['tax_money']),
                'invoice_type' => $param['invoice_type'],
                'creator' => $this->getUserInfo('dealer_name'),
                'creator_id' => $this->getUserInfo('id'),
                'create_time' => currentTime(),
                'status' => 1,
                'remark' => $param['remark']
            ];
            $status_invoice = $this->getModel('ErpSaleInvoice')->addSaleInvoice($erp_invoice_data);
            $invoiced_money = intval(round($erp_invoice_data['invoice_money'])) + intval($order_info['invoiced_amount']);
            log_info('已开票金额：' . $invoiced_money);
            //$order_amount = intval($order_info['order_amount'] - round(getNum($order_info['loss_num']) * $order_info['price']));
            $order_amount = intval($order_info['order_amount'] - setNum(round(getNum($order_info['loss_num']) * getNum($order_info['price']) + getNum($order_info['returned_goods_num']) * getNum($order_info['price']),2)));
            log_info('订单总金额：' . $order_amount);

            if (intval($invoiced_money == 0)) {
                $invoice_status = 1;
                $log = 10;
            }
//                    elseif(bccomp($invoiced_money,$order_amount)){
//                        $invoice_status = 2;
//                        $log = 10;
//                    }
            elseif (intval($invoiced_money) < intval($order_amount)) {
                $invoice_status = 2;
                $log = 10;
            }
            elseif (intval($invoiced_money) == intval($order_amount)) {
                $invoice_status = 10;
                $log = 11;
            }

            $erp_order_data = [
                'invoice_status' => $invoice_status,
                'invoiced_amount' => $erp_invoice_data['order_invoiced_money'],
                'updater' => $this->getUserInfo('id'),
                'update_time' => currentTime()
            ];

            //添加销售发票信息
            $status_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $param['sale_order_id']],$erp_order_data);

            $log_data = [
                'sale_order_id' => $param['sale_order_id'],
                'sale_order_number' => $param['sale_order_number'],
                'log_type' => $log,
                'log_info' => serialize($order_info),
                'create_time' => currentTime(),
                'operator' => $this->getUserInfo('dealer_name'),
                'operator_id' => $this->getUserInfo('id'),
            ];
            $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);
            if ($status_invoice && $status_order && $status_log) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '申请成功';
            } else {
                M()->rollback();
                $result['status'] = 0;
                $result['message'] = isset($err_message) ? $err_message : '操作失败';
            }
        }


        cancelCacheLock('ErpFinance/addSaleInvoice');
        return $result;
    }

    /**
     * 整单开票
     * @author senpai
     */
    public function confirmSaleInvoice($param)
    {
        if (getCacheLock('ErpFinance/confirmSaleInvoice')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/confirmSaleInvoice', 1);
        if (count($param) <= 0) {
            $result['status'] = 2;
            $result['message'] = '参数有误！';
            return $result;
        }

        $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['id'][0]]);
        if (!in_array($sale_order_info['our_company_id'],[70,22,18])) {
            $result['status'] = 3;
            $result['message'] = '和瑞，誉州，昊瑞以外的账套公司请到税务系统进行开票';
            return $result;
        }

        M()->startTrans();
        $status = 1;
        $wrong_status = 1;
        $wrong_str = '';

        # 按税率整单开票
        # 类型为1则为17% 、2为16%
        # 发票类型默认 1 、4
        # qianbin 2018-06-07
        //$invoice_tax  = $param['invoice_type'] == 1 ? 0.17 : 0.16 ;
        $invoice_type = 7; //edit xiaowen 2019-3-26 销售整单开票默认  7 => '13%增票' 详见str.php 中 invoiceType() 方法,
        $invoice_tax  = invoiceTaxRate($invoice_type);
        # end ----------------

        foreach ($param['id'] as $id) {
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if ($order_info['invoice_status'] == 1) {

                //订单总额
                $order_amount = $order_info['order_amount'] - round(getNum($order_info['loss_num']) * $order_info['price'] + getNum($order_info['returned_goods_num']) * $order_info['price'],-2);

                $erp_order_data = [
                    'invoice_status' => 10,
                    'invoiced_amount' => $order_amount,
                    'updater' => $this->getUserInfo('id'),
                    'update_time' => currentTime()
                ];
                $status_order = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $id],$erp_order_data);

                $erp_invoice_data = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    // 'tax_money' => $order_amount * 0.17,
                    'tax_money' => $order_amount * $invoice_tax,
                    'invoice_money' => $order_amount,
                    'order_invoiced_money' => $order_amount,
                    'creator' => $this->getUserInfo('dealer_name'),
                    'creator_id' => $this->getUserInfo('id'),
                    'create_time' => currentTime(),
                    'status' => 1,
                    'invoice_type' => $invoice_type
                ];
                //未税发票金额 = 发票总金额 - 税额
                $erp_invoice_data['notax_invoice_money'] = $erp_invoice_data['invoice_money'] - $erp_invoice_data['tax_money'];
                //添加销售发票记录
                $status_invoice = $this->getModel('ErpSaleInvoice')->addSaleInvoice($erp_invoice_data);

                $log_data = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'log_type' => 11,
                    'log_info' => serialize($order_info),
                    'create_time' => currentTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpSaleOrderLog')->addSaleOrderLog($log_data);

                //-----------------------------------------------------------------------------------------------
                if ($status_order && $status_invoice && $status_log) {
                    $status = 1;
                } else {
                    $status = 2;
                    break;
                }
            } else {
                $wrong_status = 2;
                $wrong_str .= $order_info['order_number'].',';
            }
        }

        $wrong_str = trim($wrong_str,',');

        if ($status == 1 && $wrong_status == 1) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '开票成功';
        } elseif ($wrong_status == 2) {
            M()->rollback();
            $result['status'] = 0;
            $result['wrong_message'] = $wrong_str.'订单开票状态有误，只有未开票状态订单才可整单开票';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = isset($err_message) ? $err_message : '操作失败';
        }

        cancelCacheLock('ErpFinance/confirmSaleInvoice');
        return $result;
    }

    /**
     * 发票信息（录入发票号和发票类型）
     * @author senpai
     */
    public function erpSaleInvoiceInfo($param)
    {
        if (getCacheLock('ErpFinance/erpSaleInvoiceInfo')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/erpSaleInvoiceInfo', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            if (trim($param['invoice_sn']) == "") {
                $result['status'] = 101;
                $result['message'] = "请输入发票号码";
            } elseif (trim($param['invoice_type']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择发票类型";
            } else {
                $invoice_info = $this->getModel('ErpSaleInvoice')->where(['sale_order_id' => $param['sale_order_id']])->find();
                //根据发票类型 返回对应的税率
                $tax_rate = invoiceTaxRate($param['invoice_type']);
                $erp_invoice_data = [
                    'invoice_sn' => $param['invoice_sn'],
                    'invoice_type' => $param['invoice_type'],
                    'remark' => $param['remark'],
                    'tax_money' => $invoice_info['invoice_money'] * $tax_rate, //根据发票类型对应的税率，计算税额

                ];
                //未税发票金额 = 发票总金额 - 税额
                $erp_invoice_data['notax_invoice_money'] = $invoice_info['invoice_money'] - $erp_invoice_data['tax_money'];
                //添加erp收款信息
                $status_invoice = $this->getModel('ErpSaleInvoice')->saveSaleInvoice(['sale_order_id' => $param['sale_order_id']],$erp_invoice_data);
                if ($status_invoice) {
                    $result['status'] = 1;
                    $result['message'] = '操作成功';
                } else {
                    $result['status'] = 0;
                    $result['message'] = '操作失败';
                }
            }
        }
        cancelCacheLock('ErpFinance/erpSaleInvoiceInfo');
        return $result;
    }

    // +----------------------------------
    // |Facilitator:判断是否整单开票
    // +----------------------------------
    // |Author:senpai Time:2017.4.24
    // +----------------------------------
    public function getSaleOrderStatus($param = [])
    {
        $erpSaleInvoice = $this->getModel('ErpSaleInvoice')->getSaleInvoiceCount(['sale_order_id' => $param['id']]);
        $erpSaleOrder = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['id']]);

        if  ($erpSaleInvoice == 1 && $erpSaleOrder['invoice_status'] == 10) {
            $result['status'] = 1;
        } else {
            $result['status'] = 0;
        }
        return $result;
    }

    /**
     * 订单全部付款后改变库存
     * @param $order_info
     * @author xiaowen
     * @time 2017-05-08
     * @return bool
     */
    public function orderFullPayChangeStock($order_info){
        //==========================订单收款，影响库存信息===============================================
        //--------------除帐期和货到付款订单外，其他订单都减少预留，增加销售待提-----------------------------------
        if($order_info['pay_type'] != 2 && $order_info['pay_type'] != 4){

            $stock_log_info = D('ErpStockLog')->where(['object_number'=>$order_info['order_number']])->order('id desc')->find();


            $stock_where = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['storehouse_id'],
                'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

            $data = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['storehouse_id'],
                'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
                'region' => $order_info['region'],
                'sale_wait_num' => $stock_info['sale_wait_num'] + $order_info['buy_num'],
            ];
            if(!empty($stock_log_info) && $stock_log_info['change_num']){
                $data['sale_reserve_num'] = $stock_info['sale_reserve_num'] - $order_info['buy_num'];
                $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留
            }
            $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的销售待提
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            $orders = [
                'object_number' => $order_info['order_number'],
                'object_type' => 1,
                'log_type' => 5,
            ];
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['buy_num'], $orders);

        } else {
            $stock_status = true;
        }
        //===============================================================================================
        return $stock_status;
    }

    /**
     * 销售单定金锁价改变库存
     * @param $order_info
     * @author senpai
     * @time 2017-05-18
     * @return bool
     */
    public function saleEarnestPayChangeStock($order_info,$change_num){
        //==========================订单收款，影响库存信息===============================================
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        log_info('定金锁价转变数量：'. $change_num);

        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
            'region' => $order_info['region'],
            'sale_reserve_num' => $stock_info['sale_reserve_num'] - $change_num,
            'sale_wait_num' => $stock_info['sale_wait_num'] + $change_num,
        ];
        log_info('计算后的预留数量：'. $data['sale_reserve_num']);
        log_info('计算后的待提数量：'. $data['sale_wait_num']);
        $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留
        $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的销售待提
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 1,
            'log_type' => 5,
        ];
        $change = $order_info['pay_type'] == 5 ? $change_num : $order_info['buy_num'];
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $change, $orders);
        //===============================================================================================
        return $stock_status;
    }

    /**
     * 采购单定金锁价改变库存
     * @param $order_info
     * @author senpai
     * @time 2017-05-19
     * @return bool
     */
    public function purchaseEarnestPayChangeStock($order_info,$change_num){
        $stock_status = false;
        //==========================订单付款确认，影响库存信息===============================================

        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['type'] == 2 ? 2 : getAllocationStockType($order_info['storehouse_id']),
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['type'] == 2 ? 2 : getAllocationStockType($order_info['storehouse_id']),
            'region' => $order_info['region'],
            'transportation_num' => $stock_info['transportation_num'] + $change_num,
        ];
        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的销售待提
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        $orders = [
            'object_number' => $order_info['order_number'],
            'object_type' => 2,
            'log_type' => 4,
        ];
        $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $change_num, $orders);
        //===============================================================================================
        return $stock_status;
    }

    /**
     * 预存应收列表
     * @author guanyu
     * @time 2017-11-10
     */
    public function prestoreCollectionOrderList($param = [])
    {
        $where = [];

        //订单号
        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        //时间区间
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.add_order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        //城市
        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }
        //客户
        if (trim($param['Recharge_user'])) {
            //$user_ids = D("User")->where(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%'], 'is_available' => 0])->getField('id', true);
            $user_ids = $this->getEvent('ErpCustomer')->getCustomerUserDataField(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%']],'id,user_name');
            $user_ids = array_keys($user_ids);
            if ($user_ids) {
                $where['o.user_id'] = ['in', $user_ids];
            } else {
                $data['recordsFiltered'] = $data['recordsTotal'] = 0;
                $data['data'] = [];
                $data['draw'] = $_REQUEST['draw'];
                return $data;
            }
        }
        //公司
        if (trim($param['Recharge_company_id'])) {
            $where['o.company_id'] = intval(trim($param['Recharge_company_id']));
        }
        //订单状态
        if (trim($param['finance_status'])) {
            $where['o.finance_status'] = intval(trim($param['finance_status']));
        } else {
            $where['o.finance_status'] = 1;

        }
        //财务处理状态
        if (trim($param['recharge_type'])) {
            $where['o.recharge_type'] = intval(trim($param['recharge_type']));
        }
        //交易员
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        //订单类型：1、预付  2、预存
        if (trim($param['type'])) {
            $where['o.order_type'] = trim($param['type']);
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');
        $where['o.order_status'] = 10;

        //$field = 'o.*,u.user_name,u.user_phone,c.company_name';
        $field = 'o.*';
        if ($param['export']) {
            $data = $this->getModel('ErpRechargeOrder')->getAllRechargeOrderList($where, $field);
        } else {
            $data = $this->getModel('ErpRechargeOrder')->getRechargeOrderList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];

            $creater_arr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', array_unique(array_column($data['data'],'creater'))]])->getField('id,dealer_name');

            $company_ids = array_unique(array_column($data['data'], 'company_id'));
            $user_ids = array_unique(array_column($data['data'], 'user_id'));
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1);
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 1);
            }
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['create_time'] = date('Y-m-d', strtotime($value['create_time']));
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['recharge_amount'] = $value['recharge_amount'] != 0 ? getNum($value['recharge_amount']) : '0';
                $data['data'][$key]['recharge_type'] = PrestoreType($value['recharge_type']);
                $data['data'][$key]['order_status_font'] = RechargeOrderStatus($value['order_status']);
                $data['data'][$key]['order_status'] = RechargeOrderStatus($value['order_status'],true);
                $data['data'][$key]['finance_status_font'] = RechargeFinanceStatus($value['finance_status']);
                $data['data'][$key]['finance_status'] = RechargeFinanceStatus($value['finance_status'],true);
                $data['data'][$key]['creater_name'] = $creater_arr[$value['creater']];
                # 列表添加银行简称 qianbin  2018.8.7
                $data['data'][$key]['bank_simple_name'] = empty($value['bank_simple_name']) ? '--' : $value['bank_simple_name'];

//                //930改造
                $data['data'][$key]['company_name'] = $companyArr[$value['company_id']]['company_name'];
                $data['data'][$key]['user_name'] = $userArr[$value['user_id']]['user_name'];
                $data['data'][$key]['user_phone'] = $userArr[$value['user_id']]['user_phone'];
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 确认预存收款
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function confirmPrestoreCollection($param= [])
    {
        //参数验证
        if(count($param) <= 0 ){
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (intval($param['id']) <= 0 || intval($param['bank_id'])<= 0 || empty(trim($param['bank_simple_name']))) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $param['id']]);

        if ($order_info['order_status'] != 10) {
            $result = [
                'status' => 2,
                'message' => '该预存申请单不是已确认状态，无法收款',
            ];
            return $result;
        }

        if ($order_info['finance_status'] != 1) {
            $result = [
                'status' => 3,
                'message' => '该预存申请单不是未收款状态，无法收款',
            ];
            return $result;
        }

        // 验证银行账套信息是否有效
        $bank_info = $this->getEvent('ErpBank')->getErpBankInfoById(intval($param['bank_id']));
        if (intval($param['bank_id']) > 0 && (empty($bank_info) || intval($bank_info['status']) != 1)) {
            $result = [
                'status'  => 21 ,
                'message' => '该笔银行账号信息有误，请刷新后重试！',
            ];
            return $result;
        }

        if (getCacheLock('ErpFinance/confirmPrestoreCollection')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/confirmPrestoreCollection', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'finance_status' => 10,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'bank_id'          => intval($param['bank_id']),
            'bank_simple_name' => trim($bank_info['bank_simple_name']),
            'bank_info'        => intval($param['bank_id']) <= 0 ? " " : json_encode($bank_info,JSON_UNESCAPED_UNICODE),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($param['id'])], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 1,
            'log_info' => serialize($order_info),
            'log_type' => 7,
        ];
        $log_status = $this->getEvent('ErpRecharge')->addRechargeOrderLog($log);

        //修改账户
        $order_info['object_type'] = 1;//单据类型：预存申请单
        $account_status = $this->getEvent('ErpAccount')->changeAccount($order_info, PRESTORE_TYPE, $order_info['recharge_amount']);

        if ($status && $log_status && $account_status) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpFinance/confirmPrestoreCollection');
        return $result;
    }

    /**
     * 驳回预存收款
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function rejectPrestoreCollection($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);

        if ($order_info['order_status'] != 10) {
            $result = [
                'status' => 2,
                'message' => '该预存申请单不是已确认状态，无法驳回',
            ];
            return $result;
        }

        if ($order_info['finance_status'] != 1) {
            $result = [
                'status' => 3,
                'message' => '该预存申请单不是未收款状态，无法驳回',
            ];
            return $result;
        }

        if (getCacheLock('ErpFinance/rejectPrestoreCollection')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/rejectPrestoreCollection', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 1,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];

        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 1,
            'log_info' => serialize($order_info),
            'log_type' => 8,
        ];
        $log_status = $this->getEvent('ErpRecharge')->addRechargeOrderLog($log);


        if ($status && $log_status) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpFinance/rejectPrestoreCollection');
        return $result;
    }

    /**
     * 预付应付列表
     * @author guanyu
     * @time 2017-11-12
     */
    public function prepayPaymentOrderList($param = [])
    {
        $where = [];

        //订单号
        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        //时间区间
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.add_order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        //申请付款时间区间
        if (isset($param['finance_start_time']) || isset($param['finance_end_time'])) {
            if (trim($param['finance_start_time']) && !trim($param['finance_end_time'])) {

                $where['o.apply_finance_time'] = ['egt', trim($param['finance_start_time'])];
            } else if (!trim($param['finance_start_time']) && trim($param['finance_end_time'])) {

                $where['o.apply_finance_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['finance_end_time']))+3600*24)];
            } else if (trim($param['finance_start_time']) && trim($param['finance_end_time'])) {

                $where['o.apply_finance_time'] = ['between', [trim($param['finance_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['finance_end_time']))+3600*24)]];
            }
        }
        // 根据付款时间查询
        if (isset($param['pay_start_time']) || isset($param['pay_end_time'])) {
            if (trim($param['pay_start_time']) && !trim($param['pay_end_time'])) {

                $where['o.pay_time'] = ['egt', trim($param['pay_start_time'])];
            } else if (!trim($param['pay_start_time']) && trim($param['pay_end_time'])) {

                $where['o.pay_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['pay_end_time']))+3600*24)];
            } else if (trim($param['pay_start_time']) && trim($param['pay_end_time'])) {

                $where['o.pay_time'] = ['between', [trim($param['pay_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['pay_end_time']))+3600*24)]];
            }
        }
        //城市
        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }
        //客户
        if (trim($param['Recharge_user'])) {
            //$user_ids = D("User")->where(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%'], 'is_available' => 0])->getField('id', true);
            $user_ids = $this->getEvent('ErpSupplier')->getSupplierUserDataField(['user_name' => ['like', '%' . trim($param['Recharge_user']) . '%']],'id,user_name');

            $user_ids = array_keys($user_ids);
            if ($user_ids) {
                $where['o.user_id'] = ['in', $user_ids];
            } else {
                $data['recordsFiltered'] = $data['recordsTotal'] = 0;
                $data['data'] = [];
                $data['draw'] = $_REQUEST['draw'];
                return $data;
            }
        }
        //公司
        if (trim($param['Recharge_company_id'])) {
            $where['o.company_id'] = intval(trim($param['Recharge_company_id']));
        }
        //订单状态
        if (trim($param['finance_status'])) {
            $where['o.finance_status'] = intval(trim($param['finance_status']));
        } else {
            $where['o.finance_status'] = 1;

        }
        //财务处理状态
        if (trim($param['recharge_type'])) {
            $where['o.recharge_type'] = intval(trim($param['recharge_type']));
        }
        //交易员
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        //订单类型：1、预付  2、预存
        if (trim($param['type'])) {
            $where['o.order_type'] = trim($param['type']);
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');
        $where['o.order_status'] = 10;

        //$field = 'o.*,u.user_name,u.user_phone,c.company_name';
        $field = 'o.*';
        if ($param['export']) {
            $data = $this->getModel('ErpRechargeOrder')->getAllRechargeOrderList($where, $field);
        } else {
            $data = $this->getModel('ErpRechargeOrder')->getRechargeOrderList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];

            $creater_arr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', array_unique(array_column($data['data'],'creater'))]])->getField('id,dealer_name');

            $company_ids = array_unique(array_column($data['data'], 'company_id'));
            $user_ids = array_unique(array_column($data['data'], 'user_id'));
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 2);
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 2);
            }
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['create_time'] = date('Y-m-d', strtotime($value['create_time']));
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['recharge_amount'] = $value['recharge_amount'] != 0 ? getNum($value['recharge_amount']) : '0';
                $data['data'][$key]['recharge_type'] = PrepayType($value['recharge_type']);
                $data['data'][$key]['order_status_font'] = RechargeOrderStatus($value['order_status']);
                $data['data'][$key]['order_status'] = RechargeOrderStatus($value['order_status'],true);
                $data['data'][$key]['finance_status_font'] = RechargeFinanceStatus($value['finance_status']);
                $data['data'][$key]['finance_status'] = RechargeFinanceStatus($value['finance_status'],true);
                $data['data'][$key]['creater_name'] = $creater_arr[$value['creater']];
                # 列表添加银行简称 qianbin  2018.8.7
                $data['data'][$key]['bank_simple_name'] = empty($value['bank_simple_name']) ? '--' : $value['bank_simple_name'];

                //930改造
                $data['data'][$key]['company_name'] = $companyArr[$value['company_id']]['company_name'];
                $data['data'][$key]['user_name'] = $userArr[$value['user_id']]['user_name'];
                $data['data'][$key]['user_phone'] = $userArr[$value['user_id']]['user_phone'];
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 确认预付付款
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function confirmPrepayPayment($param = [])
    {
        //参数验证
        if(count($param) <= 0 ){
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (intval($param['id']) <= 0 || intval($param['bank_id'])<= 0 || empty(trim($param['bank_simple_name']))) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => intval($param['id'])]);

        if ($order_info['order_status'] != 10) {
            $result = [
                'status' => 2,
                'message' => '该预付申请单不是已确认状态，无法收款',
            ];
            return $result;
        }

        if ($order_info['finance_status'] != 1) {
            $result = [
                'status' => 3,
                'message' => '该预付申请单不是未收款状态，无法收款',
            ];
            return $result;
        }
        // 验证银行账套信息是否有效
        $bank_info = $this->getEvent('ErpBank')->getErpBankInfoById(intval($param['bank_id']));
        if (intval($param['bank_id']) > 0 && (empty($bank_info) || intval($bank_info['status']) != 1)) {
            $result = [
                'status'  => 21 ,
                'message' => '该笔银行账号信息有误，请刷新后重试！',
            ];
            return $result;
        }

        if (getCacheLock('ErpFinance/confirmPrepayPayment')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpFinance/confirmPrepayPayment', 1);

        M()->startTrans();
        //审核单据
        $data = [
            'finance_status' => 10,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
            /*** 新增付款时间 -2018-11-29  ***/
            'pay_time' => currentTime(),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'bank_id'          => intval($param['bank_id']),
            'bank_simple_name' => trim($bank_info['bank_simple_name']),
            'bank_info'        => intval($param['bank_id']) <= 0 ? " " : json_encode($bank_info,JSON_UNESCAPED_UNICODE),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
        ];
        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($param['id'])], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 2,
            'log_info' => serialize($order_info),
            'log_type' => 7,
        ];
        $log_status = $this->getEvent('ErpRecharge')->addRechargeOrderLog($log);

        //修改账户
        $order_info['object_type'] = 2;//单据类型：预付申请单
        $account_status = $this->getEvent('ErpAccount')->changeAccount($order_info, PREPAY_TYPE, $order_info['recharge_amount']);

        if ($status && $log_status && $account_status) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpFinance/confirmPrepayPayment');
        return $result;
    }

    /**
     * 驳回预付付款
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function rejectPrepayPayment($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpRechargeOrder')->findRechargeOrder(['id' => $id]);

        if ($order_info['order_status'] != 10) {
            $result = [
                'status' => 2,
                'message' => '该预付申请单不是已确认状态，无法驳回',
            ];
            return $result;
        }

        if ($order_info['finance_status'] != 1) {
            $result = [
                'status' => 3,
                'message' => '该预付申请单不是未收款状态，无法驳回',
            ];
            return $result;
        }

        if (getCacheLock('ErpFinance/rejectPrepayPayment')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/rejectPrepayPayment', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 1,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];

        $status = $this->getModel('ErpRechargeOrder')->saveRechargeOrder(['id' => intval($id)], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'order_type' => 2,
            'log_info' => serialize($order_info),
            'log_type' => 8,
        ];
        $log_status = $this->getEvent('ErpRecharge')->addRechargeOrderLog($log);


        if ($status && $log_status) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpFinance/rejectPrepayPayment');
        return $result;
    }

    /**
     * 验证付款账户
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function checkAccount($id,$account_type)
    {
        //获取应付信息
        $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $id]);

        //获取订单信息
        if ($payment_info['source_order_type'] == 1) {
            $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $payment_info['purchase_id']]);
        } elseif ($payment_info['source_order_type'] == 2) {
            $field = 'o.*';
            $order_info = $this->getModel('ErpReturnedOrder')->alias('ro')
                ->field($field)
                ->where(['ro.id' => $payment_info['purchase_id'],'ro.order_number' => $payment_info['purchase_order_number']])
                ->join('oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number', 'left')
                ->find();
        }

        $account_where = [
            'account_type' => $account_type,
            'our_company_id' => $order_info['our_buy_company_id'],
            'company_id' => $order_info['sale_company_id'],
        ];
        $account_info = $this->getModel('ErpAccount')->findAccount($account_where);

        if ($account_info) {
            $account_info['account_balance'] = getNum($account_info['account_balance']);
            $result = [
                'payment_status' => $payment_info['status'],
                'source_order_type' => $payment_info['source_order_type'],
                'account_status' => 1,
                'account_info' => $account_info,
            ];
        } else {
            $result = [
                'payment_status' => $payment_info['status'],
                'source_order_type' => $payment_info['source_order_type'],
                'account_status' => 2,
                'account_info' => [],
            ];
        }
        return $result;
    }

    /**
     * 获取预付余额抵扣信息
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function getPrepayPaymentInfo($id)
    {
        //获取应付信息
        $payment_info = $this->getModel('ErpPurchasePayment')->findPurchasePayment(['id' => $id]);

        //获取订单信息
        if ($payment_info['source_order_type'] == 1) {
            //$field = 'o.*,cs.company_name';
            $field = 'o.*';
            $order_info = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $payment_info['purchase_id'],'o.order_number' => $payment_info['purchase_order_number']],$field);
        } elseif ($payment_info['source_order_type'] == 2) {
            //$field = 'o.*,cs.company_name';
            $field = 'o.*';
            $order_info = $this->getModel('ErpReturnedOrder')->alias('ro')
                ->field($field)
                ->where(['ro.id' => $payment_info['purchase_id'],'ro.order_number' => $payment_info['purchase_order_number']])
                ->join('oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number', 'left')
                //->join('oil_clients cs on o.sale_company_id = cs.id', 'left')
                ->find();
        }
        // edit xiaowen 930 改造 2018-10-19
        $companyArr = $this->getEvent('ErpCommon')->getCompanyData([$payment_info['sale_company_id']], 2);
        $account_where = [
            'account_type' => 2,
            'our_company_id' => $order_info['our_buy_company_id'],
            'company_id' => $order_info['sale_company_id'],
        ];
        $account_info = $this->getModel('ErpAccount')->findAccount($account_where);

        $result = [
            'id'           => intval($id),
            'order_number' => $order_info['order_number'],
            'creator' => $payment_info['creator'],
            //'company_name' => $order_info['company_name'],
            'company_name' => $companyArr[$payment_info['sale_company_id']]['company_name'],
            'account_balance' => getNum($account_info['account_balance']),
            'pay_money' => getNum($payment_info['pay_money']),
            'order_amount' => getNum($order_info['order_amount']),
            'remark'       => $payment_info['remark'],
            'order_type'   => $payment_info['source_order_type'],
        ];
        return $result;
    }

    /**
     * 导入采购发票
     * @author guanyu
     * @time 2018-01-02
     */
    public function uploadInvoice($file_path,$id)
    {
        @set_time_limit(5 * 60);
        if (empty(trim($file_path))) {
            $result = [
                'status' => 4,
                'message' => '系统异常请联系管理员！'
            ];
            return $result;
        }
        # @excel数据
        $PHPReader = new \PHPExcel_Reader_Excel2007();
        if (!$PHPReader->canRead($file_path)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($file_path)) {
                $result = [
                    'status' => 5,
                    'message' => '文件不存在！'
                ];
                return $result;
            }
        }
        $PHPExcel = $PHPReader->load($file_path);
        # @读取excel文件中的第一个工作表
        $currentSheet = $PHPExcel->getSheet(0);
        $data = $currentSheet->toArray();
        //print_r($data);
        unset($data[0]);

        //验证数据
        if (empty($data)) {
            $result = [
                'status' => 6,
                'message' => '数据不能为空！'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpPurchaseOrder')->alias('o')
            ->where(['o.id' => $id])
            ->field('o.*,r.return_payed_amount')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
            ->find();
        $all_invoice_money = $this->getModel('ErpPurchaseInvoice')->field('sum(apply_invoice_money) as total')->where(['purchase_id' => $id, 'status' => ['neq', 2]])->group('purchase_id')->find();
        $invoice_money = 0;
        $invoice_type = invoiceType();

        //采购单有未退款的采退单无法录入发票
        $returned_order = $this->getModel('ErpReturnedOrder')->where(['source_order_number'=>$order_info['order_number'],'order_status'=>10,'return_amount_status'=>1])->count();
        if ($returned_order) {
            $result['status'] = 101;
            $result['message'] = "请先处理采退单的退款，无法录入发票";
            return $result;
        }

        foreach ($data as $key => $value) {
            if ($value[0] == '') {
                unset($data[$key]);
                continue;
            }
            if (trim($value[0]) != $order_info['order_number']) {
                $result = [
                    'status' => 7,
                    'message' => '导入的采购单号有误！'
                ];
                return $result;
            }
            if (!is_string($value[1]) && $value[1] < 10000000 || $value[1] >= 100000000) {
                $result = [
                    'status' => 8,
                    'message' => '发票号码位数有误！'
                ];
                return $result;
            }
            if (is_string($value[1]) && is_numeric($value[1]) && strlen($value[1]) != 8) {
                $result = [
                    'status' => 8,
                    'message' => '发票号码位数有误！'
                ];
                return $result;
            }
            if (!in_array($value[2],$invoice_type)) {
                $result = [
                    'status' => 9,
                    'message' => '请验证发票类型！'
                ];
                return $result;
            }
            if ($value[3] == 0) {
                $result = [
                    'status' => 10,
                    'message' => '申请发票金额不能为0！'
                ];
                return $result;
            }
            if(getFloatLength($value[3]) > 2 || getFloatLength($value[4]) > 2 || getFloatLength($value[5]) > 2){
                $result = [
                    'status' => 13,
                    'message' => '金额项不能超过2位小数，请核对后导入!'
                ];
                return $result;
            }
            if (intval(round(setNum($value[3]))) != intval(round(setNum($value[4] + $value[5])))) {
                $result = [
                    'status' => 11,
                    'message' => '请验证发票去税金额与税额合计是否等于申请发票金额！'
                ];
                return $result;
            }
            if (in_array(intval(array_keys($invoice_type,$value[2])[0]),[1,2,3])) {
                $coefficient = 0.17;
            } else if (in_array(intval(array_keys($invoice_type,$value[2])[0]),[4,5,6])) {
                $coefficient = 0.16;
            }
            //edit xiaowen 2019-3-25 与天杰确认发票不验证税额，只需验证开票总额
//            if (bccomp(setNum($value[5]),setNum(round($value[3] / (1 + $coefficient) * $coefficient,2))) != 0) {
//                $result['status'] = 101;
//                $result['message'] = "税率计算错误，请检查";
//                return $result;
//            }
            $invoice_money += $value[3];
        }

        if (bccomp(setNum($invoice_money),$order_info['order_amount'] - $order_info['return_payed_amount'] - $all_invoice_money['total']) == 1) {
            $result = [
                'status' => 12,
                'message' => '申请发票金额不能大于待收发票金额'
            ];
            return $result;
        }

        if (getCacheLock('ErpFinance/uploadInvoice')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/uploadInvoice', 1);

        M()->startTrans();
        $status_invoice_all = true;
        $status_log_all = true;
        foreach ($data as $value) {
            $erp_invoice_data = [
                'purchase_id' => $order_info['id'],
                'purchase_order_number' => $order_info['order_number'],
                'invoice_sn' => trim($value[1]),
                'notax_invoice_money' => setNum($value[4]),
                'tax_money' => setNum($value[5]),
                'apply_invoice_money' => setNum($value[4] + $value[5]),
                'invoice_type' => array_keys($invoice_type,$value[2])[0],
                'creator' => $this->getUserInfo('dealer_name'),
                'creator_id' => $this->getUserInfo('id'),
                'create_time' => $this->date,
                'status' => 1,
                'remark' => $value[6] ? $value[6] : ''
            ];

            $status_invoice = $this->getModel('ErpPurchaseInvoice')->addPurchaseInvoice($erp_invoice_data);

            $log_data = [
                'purchase_id' => $order_info['id'],
                'purchase_order_number' => $order_info['order_number'],
                'log_type' => 8,
                'log_info' => serialize($order_info),
                'create_time' => $this->date,
                'operator' => $this->getUserInfo('dealer_name'),
                'operator_id' => $this->getUserInfo('id'),
            ];
            $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);

            $status_invoice_all = $status_invoice && $status_invoice_all ? true : false;
            $status_log_all = $status_log && $status_log_all ? true : false;
        }
        if ($status_invoice_all && $status_log_all) {
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpFinance/uploadInvoice');
        return $result;
    }

    /**
     * 验证该笔销售单是否有销退单并且收款未确认
     * @author qianbin
     * @time 2017-12-27
     */
    public function checkReturnOrder($param)
    {
        if(count($param['id']) <= 0 ) return ['status' => 2 , 'message' => '参数错误，请刷新后重试！'];
        // type = 1 是 销售发票列表   2为详情页判断
        // 列表传的是order_number
        // 详情页传的是order_id
        if(intval($param['type']) == 1){
            $where = ['source_order_number' => ['in' , $param['order_number']] , 'order_status' => ['neq' , 2] , 'order_type' => 1 ,  'return_amount_status' => 1];
            $order_info = $this->getModel('ErpSaleOrder')->where(['order_number' => ['in' , $param['order_number']]])->find();
        }else{
            $where = ['source_order_id' => ['eq' , $param['id']] , 'order_status' => ['neq' , 2], 'order_type' => 1 , 'return_amount_status' => 1];
            $order_info = $this->getModel('ErpSaleOrder')->where(['id' => $param['id']])->find();
        }

        if ($order_info['is_returned'] == 1 && $order_info['returned_goods_num'] == 0) {
            return ['status' => 2 , 'message' => '您操作的该笔订单，存在退货单并且入库单未审核，无法开票！'];
        }

        // 查询是否存在销退单
        $return_order_data = $this->getModel('ErpReturnedOrder')->field('source_order_number,return_amount_status')->where($where)->select();
        if(count($return_order_data) > 0) {
            $order_data = array_column($return_order_data,'source_order_number');
            //if(count($order_data) == 0) return ['status' => 1 , 'message' => '校验通过，可以开票'];
            $message = implode("，<br /> ",$order_data);
            $return_info = intval($param['type']) == 1 ? '以下订单:<br />'.$message.' <br />存在退货单并且财务未确认，无法开票！' : '您操作的该笔订单，存在退货单并且财务未确认，无法开票！';
            return ['status' => 3 , 'message' => $return_info];
        }
        return ['status' => 1 , 'message' => '校验通过，可以开票！'];
    }

    /**
     * 验证该笔采购单是否有采退单并且已收款已出库
     * @author guanyu
     * @time 2018-04-10
     */
    public function checkPurchaseReturnOrder($param)
    {
        //参数验证
        if (!$param['id']) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpPurchaseOrder')->where(['id' => $param['id']])->find();
        if ($order_info['is_returned'] == 1 && $order_info['returned_goods_num'] == 0) {
            return ['status' => 2 , 'message' => '您操作的该笔订单，存在退货单并且出库单未审核，无法开票！'];
        }
        return ['status' => 1 , 'message' => '校验通过，可以开票！'];
    }

    /**
     * 新版应收列表
     * @author xiaowen 2018-01-22
     * @param $param
     */
    public function newErpSaleCollectionList($param = [])
    {
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.add_order_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        if (trim($param['sale_company_id'])) {
            $where['o.company_id'] = intval(trim($param['sale_company_id']));
        }
        if ($param['status']) {
            $where['o.order_status'] = intval($param['status']);
        }

//        //是否损耗
//        if ($param['is_loss']) {
//            $is_loss = intval(trim($param['is_loss']));
//        } else {
//            $is_loss = 1;
//        }

        if ($param['collection_status']) {
            //是否损耗
            if ($param['is_loss']) {
                $is_loss = intval(trim($param['is_loss']));
                $collection_status = intval(trim($param['collection_status']));
                $where['_string'] = "(o.collection_status = ".$collection_status." AND o.is_loss = ".$is_loss.")";
            } else {
                $is_loss = 1;
                $collection_status = intval(trim($param['collection_status']));
                $where['_string'] = "(o.collection_status = ".$collection_status." OR o.is_loss = ".$is_loss.")";
            }
            # 增加收款状态 “全部状态” qianbin 2018-07-10
            if(intval($param['collection_status']) == 20){
                unset($where['o.is_loss']);
                unset($where['o.order_status']);
                unset($where['_string']);
            }
        } elseif ($param['collection']) {
            //是否损耗
            if ($param['is_loss']) {
                $is_loss = intval(trim($param['is_loss']));
                $where['o.is_loss'] = $is_loss;
            } else {
                $is_loss = 1;
                $where['_string'] = "(o.collection_status != 10 OR o.is_loss = ".$is_loss.")";
            }
        }

        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }

        if (trim($param['business_order_number'])) {
            $where['_complex'] = [
                'o.order_number' => ['like', '%' . trim($param['business_order_number']) . '%'],
                'o.source_order_number' => ['like', '%' . trim($param['business_order_number']) . '%'],
                '_logic'=>'or'
            ];
        }

        if (trim($param['is_void']) == 1) {
            $where['o.is_void'] = trim($param['is_void']);
            unset($where['o.is_loss']);
            unset($where['o.order_status']);
            unset($where['_string']);
        }

        # 搜索 - 实际收款时间 - qianbin - 2017.10.11
        if (!empty(trim($param['put_start_time'])) || !empty(trim($param['put_end_time'])))  {
            if (trim($param['put_start_time']) && !trim($param['put_end_time'])) {
                $where['sc.collect_time'] = ['egt', trim($param['put_start_time'])];
            } else if (!trim($param['put_start_time']) && trim($param['put_end_time'])) {
                $where['sc.collect_time'] = ['elt',date('Y-m-d H:i:s', strtotime(trim($param['put_end_time']))+3600*24)];
            } else if (trim($param['put_start_time']) && trim($param['put_end_time'])) {
                $where['sc.collect_time'] = ['between', [trim($param['put_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['put_end_time']))+3600*24)]];
            }
            unset($where['_string']);
            $where['collection_status'] = ['gt',1];
        }
        //我的销售单
        if (trim($param['dealer_id'])) {
            $where['o.dealer_id'] = trim($param['dealer_id']);
        }
        if (trim($param['is_update_price'])) {
            $where['o.is_update_price'] = intval(trim($param['is_update_price']));
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        //过滤零售订单
        $where['o.order_type'] = 1;

        //$field = 'o.*,sc.creator as c_creator,MAX(collect_time) as collect_time,d.depot_name,cs.tax_num,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';
        $field = 'o.*,sc.creator as c_creator,MAX(collect_time) as collect_time,d.depot_name,ec.company_name b_company_name,
        g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';

        if ($param['export']) {
            $field = true;
            //$data = $this->getModel('ErpSaleOrder')->getAllSaleOrderList($where, $field);
            $data = $this->getModel('ErpSaleOrder')->getAllSaleCollectionList($where, $field);
        } else {
            $field = true;
            //$data['data'] = $this->getModel('sale_returned_order_view')->alias('o')->field($field)->limit($param['start'], $param['length'])->where($where)->select();
            //$data['recordsTotal'] = $this->getModel('sale_returned_order_view')->alias('o')->where($where)->count();
            $data = $this->getModel('ErpSaleOrder')->getSaleCollectionList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];
            //查找商品
            $goods_ids = array_unique(array_column($data['data'], 'goods_id'));
            $goods_data = $this->getModel('ErpGoods')->where(['id'=>['in', $goods_ids]])->getField('id,goods_code,source_from,goods_name,grade,level');
            //print_r($goods_data);
            //查找公司

            //$company_data = $this->getModel('Clients')->where(['id'=>['in', $company_ids]])->getField('id,company_name');

            $company_ids = array_unique(array_column($data['data'], 'company_id'));
            $user_ids = array_unique(array_column($data['data'], 'user_id'));
            $companyArr = [];
            $userArr = [];
            if(!empty($company_ids)){
                $companyArr = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1);
            }
            if(!empty($company_ids)){
                $userArr = $this->getEvent('ErpCommon')->getUserData($user_ids, 1);
            }
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                //print_r($value);
                $data['data'][$key]['No'] = $i;
                $goods_string = $goods_data[$value['goods_id']];
                unset($goods_string['id']);
                $data['data'][$key]['source_order_number'] = $data['data'][$key]['source_order_number'] ? $data['data'][$key]['source_order_number'] : '——';
                $data['data'][$key]['goods_info'] = implode('/',$goods_string);
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['collection_status_font'] = saleCollectionStatus($value['collection_status']);
                $data['data'][$key]['collection_status'] = empty($value['returned_order_number']) ? saleCollectionStatus($value['collection_status'], true) : returnedAmountStatus($value['collection_status'], true);
                $data['data'][$key]['invoice_status'] = purchaseInvoiceStatus($value['invoice_status'], true);
                $data['data'][$key]['pay_type'] = purchasePayType($value['pay_type']);
                $data['data'][$key]['order_status'] = SaleOrderStatus($value['order_status'], true);
                $data['data'][$key]['is_void'] = purchaseContract($value['is_void'], true);
                $data['data'][$key]['is_upload_contract'] = purchaseContract($value['is_upload_contract'], true);
                //$data['data'][$key]['from_sale_order_number'] = $value['from_sale_order_number'] ? $value['from_sale_order_number'] : '--';
                $data['data'][$key]['price'] = $value['price'] > 0 ? round(getNum($value['price']),2) : '0.00';
                $data['data'][$key]['order_amount'] = $value['order_amount'] > 0 ? round(getNum($value['order_amount']),2) : '0';
                $data['data'][$key]['total_amount'] = $value['order_amount'] > 0 ? round(getNum($value['order_amount'] - getNum($value['returned_goods_num'] * $value['price'])),2) : '0';
                //$data['data'][$key]['no_collect_amount'] = round(getNum($value['order_amount'] - getNum($value['returned_goods_num'] * $value['price']) - $value['collected_amount']),2);
                //$data['data'][$key]['no_collect_amount'] = round(getNum($value['order_amount'] - $value['collected_amount']),2);
                $data['data'][$key]['collected_amount'] = $value['collected_amount']  > 0 ? round(getNum($value['collected_amount']),2) : '0';
                $data['data'][$key]['no_collect_amount'] = round(getNum($value['order_amount'] - $value['collected_amount']),2);
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? round(getNum($value['buy_num']),4) : '0';
                $data['data'][$key]['returned_goods_num'] = $value['returned_goods_num'] > 0 ? getNum($value['returned_goods_num']) : '0';
                $data['data'][$key]['delivery_money'] = $value['delivery_money'] > 0 ? getNum($value['delivery_money']) : '0';
                $data['data'][$key]['is_loss'] = lossStatus($value['is_loss'],true);
                $data['data'][$key]['is_loss_font'] = lossStatus($value['is_loss']);
                $data['data'][$key]['loss_amount'] = $value['loss_num'] > 0 ? round(getNum(getNum($value['loss_num'] * $value['price'])), 2) : '0';
                $data['data'][$key]['entered_loss_amount'] = $value['entered_loss_amount'] > 0 ? getNum($value['entered_loss_amount']) : '0';
                $data['data'][$key]['outbound_quantity'] = $value['outbound_quantity'] > 0 ? getNum($value['outbound_quantity']) : '0';
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];

                #$data['data'][$key]['s_company_name'] = $value['company_id'] == 99999 ? '不限' : $data['data'][$key]['s_company_name'];
                //=============930改造=================================
                $data['data'][$key]['s_company_name'] = $value['company_id'] == 99999 ? '不限' : $companyArr[$value['company_id']]['company_name'];
                $data['data'][$key]['s_user_name'] = $userArr[$value['user_id']]['user_name'];
                $data['data'][$key]['s_user_phone'] = $userArr[$value['user_id']]['user_phone'];
                //============end 930改造===============================
                $data['data'][$key]['order_source'] = saleOrderSourceFrom($value['order_source'], true);
                $data['data'][$key]['pay_type'] = saleOrderPayType($value['pay_type'], true);
                $data['data'][$key]['residue_time'] = getSaleOrderResidueTime($value['end_order_time'], currentTime()); //截单剩余时间 精确到分
                //是否二次定价 edit xiaowen 2017-7-24
                $data['data'][$key]['is_update_price'] = orderUpdatePriceStatus($value['is_update_price'], true);
                //$data['data'][$key]['c_creator'] = $value['creater'];

                $i++;
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 退货单整单收款
     * @param $param
     * @author xiaowen
     * @param $order_info
     * @return bool
     */
    public function confirmReturnedCollection($order_info = [], $param = [],$bank_info = [])
    {
        $pre_status = true;
        $erp_collect_data = [
            //'collect_money' => setNum($param['collect_money']),
            'collect_money' => getNum($order_info['return_goods_num'] * $order_info['return_price']),
            //'sale_order_id' => $param['sale_order_id'],
            'sale_order_id' => $order_info['id'],
            //'sale_order_number' => $param['sale_order_number'],
            'sale_order_number' => $order_info['order_number'],
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'collect_time' => currentTime(),
            'status' => 1,
            'remark' => '',
            'source_order_type' => 2,
            'from_sale_order_number' => $order_info['source_order_number'],
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'bank_id'          => intval($param['bank_id']),
            'bank_simple_name' => intval($param['bank_id']) <= 0 ? "" : trim($param['bank_simple_name']),
            'is_prestore_money'=> intval($param['is_prestore_money']),
            'bank_info'        => intval($param['bank_id']) <= 0 ? " " : json_encode($bank_info,JSON_UNESCAPED_UNICODE),
            /** 银行账套信息改版 - qianbin - 2018.08.07 */
            'company_id' => $order_info['company_id'],
            'our_company_id' => $order_info['our_company_id'],
        ];
        # 如果为退款转预收，则不需要更新银行账套
        if(intval($param['is_prestore_money']) == 1){
            unset($erp_collect_data['bank_id'],$erp_collect_data['bank_simple_name'],$erp_collect_data['bank_info']);
            $pre_status = $this->addPreStoreOrderRecord($erp_collect_data,$order_info);
        }
        //添加erp收款信息
        $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collect_data);

        //修改原订单收款状态
        $returned_order_data = [
            //'collection_status' => $collection_status,
            'return_amount_status' => 10,
            'return_payed_amount' => getNum($order_info['return_goods_num'] * $order_info['return_price']),
            'updater_id' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $status_order = $this->getModel('ErpReturnedOrder')->saveReturnedOrder(['id' => $order_info['id']],$returned_order_data);

        //添加log日志
        $log_data = [
            'return_order_id' => $order_info['id'],
            'return_order_number' => $order_info['order_number'],
            'return_order_type' => 1,
            'log_type' => 9,
            'log_info' => serialize($order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $status_log = $this->getModel('ErpReturnedOrderLog')->add($log_data);

        if($status_collection && $status_order && $status_log && $pre_status){
            $status = true;
        }else{
            $status = false;
        }
        return $status;
    }

    /**
     * 应收列表 明细查询
     * @author qianbin
     * @time 2018-07-13
     */
    public function erpSaleReceivables($param = [])
    {
        $where = [];

        if (trim($param['order_number'])) {
            $where['c.sale_order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        if (trim($param['sale_company_id'])) {
            $where['c.company_id'] = intval(trim($param['sale_company_id']));
        }
        if (trim($param['dealer_id'])) {
            $where['c.creator_id'] = intval(trim($param['dealer_id']));
        }
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['c.collect_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['c.collect_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['c.collect_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }

        //当前登陆选择的我方公司
        $where['c.our_company_id'] = session('erp_company_id');
        $field = true;
        $data  = $this->getModel('ErpSaleCollection')->getNewSaleCollectionList($where, $field, $param['start'], $param['length']);

        if (count($data['data']) > 0) {
            //查找销售单信息
            $sale_order_number_data = array_unique(array_column($data['data'], 'from_sale_order_number'));
            $sale_order_data        = $this->getModel('ErpSaleOrder')->where(['order_number'=>['in', $sale_order_number_data]])->getField('order_number,add_order_time,pay_type,dealer_name,order_amount,user_bank_info,collection_status');
            //查找公司
            $company_ids  = array_unique(array_column($data['data'], 'company_id'));
            //$company_data = $this->getModel('Clients')->where(['id'=>['in', $company_ids]])->getField('id,company_name');
            $company_data = $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1);
            // 查询退货单 数量、单价、退款状态
            $return_order_number_data = [];
            $return_order_data        = [];
            foreach ($data['data'] as $k => $v){
                if($v['source_order_type'] == 2 )$return_order_number_data[] = $v['sale_order_number'];
            }
            if(count($return_order_number_data) > 0){
                $return_order_data = $this->getModel('ErpReturnedOrder')->where(['order_number'=>['in', $return_order_number_data]])->getField('order_number,return_goods_num,return_price,return_amount_status');
            }

            foreach ($data['data'] as $k => $v) {
                if ($k == 0) {
                    $data['data'][$k]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$k]['add_order_time']      = date('Y-m-d', strtotime($sale_order_data[$v['from_sale_order_number']]['add_order_time']));
                $data['data'][$k]['pay_type']            = saleOrderPayType($sale_order_data[$v['from_sale_order_number']]['pay_type']);
                $data['data'][$k]['dealer_name']         = $sale_order_data[$v['from_sale_order_number']]['dealer_name'];
                //$data['data'][$k]['company_name']        = $company_data[$v['company_id']];
                $data['data'][$k]['company_name']        = $company_data[$v['company_id']]['company_name'];
                $data['data'][$k]['user_bank_info']      = $sale_order_data[$v['from_sale_order_number']]['user_bank_info'];
                $data['data'][$k]['order_amount']        = getNum($sale_order_data[$v['from_sale_order_number']]['order_amount']);
                $data['data'][$k]['total_collect_money'] = getNum($v['collect_money'] + $v['balance_deduction']);
                $data['data'][$k]['balance_deduction']   = getNum($v['balance_deduction']);
                $data['data'][$k]['collect_money']       = getNum($v['collect_money']);
                $data['data'][$k]['order_nocollect_money'] = $v['source_order_type'] == 1 ? getNum($sale_order_data[$v['from_sale_order_number']]['order_amount'] - $v['order_collected_money']) : ($return_order_data[$v['sale_order_number']]['return_amount_status'] == 10 ? 0 : getNum(getNum($return_order_data[$v['sale_order_number']]['return_goods_num'] * $return_order_data[$v['sale_order_number']]['return_price'])));
                $data['data'][$k]['collection_status'] = $v['source_order_type'] == 1 ? saleCollectionStatus($sale_order_data[$v['from_sale_order_number']]['collection_status'],true) : returnedAmountStatus($return_order_data[$v['sale_order_number']]['return_amount_status'],true);
                /* 应付管理，添加银行账号 qianbin 2018.08.09 */
                $data['data'][$k]['bank_simple_name']       = empty(trim($v['bank_simple_name'])) ? '--' : trim($v['bank_simple_name']);
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /*
     * ------------------------------------------
     * 采退转预付，生成预付记录，充值金额
     * Author：qianbin        Time：2017-08-09
     * ------------------------------------------
     */
    private function addPrepayOrderRecord($payment_info = [],$order_info = [])
    {
        if(count($payment_info) <= 0) return false;
        //新增单据
        $data = [
            'order_number'    => erpCodeNumber(14)['order_number'],
            'add_order_time'  => currentTime(),
            'our_company_id'  => session('erp_company_id'),
            'region'          => $order_info['region'],
            'user_id'         => $order_info['sale_user_id'],
            'company_id'      => $order_info['sale_company_id'],
            'collection_info' => $order_info['sale_collection_info'],
            'order_type'      => 2 ,
            'recharge_type'   => 21 , // 预付款
            'recharge_amount' => $payment_info['pay_money'],
            'order_status'    => 10,
            'finance_status'  => 10,
            'dealer_name'     => $this->getUserInfo('dealer_name'),
            'creater'         => $this->getUserInfo('id'),
            'create_time'     => currentTime(),
            'remark'          => '来源采购退货单号: '.trim($payment_info['purchase_order_number']).'&采退转预付自动生成',
            'from_order_number' => trim($payment_info['purchase_order_number']),
            'apply_finance_time'     => currentTime(),
        ];
        $order_status = $id = $this->getModel('ErpRechargeOrder')->addRechargeOrder($data);

        //新增log
        $log_data = [
            'recharge_id'           => $id,
            'recharge_order_number' => $data['order_number'],
            'order_type'            => 2,
            'log_info'              => serialize($data),
            'log_type'              => 1,
            'create_time'           =>  currentTime(),
            'operator'              =>  $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0,
            'operator_id'           =>  $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0,
        ];
        $status = $this->getModel('ErpRechargeOrderLog')->add($log_data);

        //修改账户
        $data['object_type'] = 2;//单据类型：预付申请单
        $account_status = $this->getEvent('ErpAccount')->changeAccount($data, PREPAY_TYPE, $data['recharge_amount']);
        $status = ($order_status && $status && $account_status) ? true : false;
        return $status ;
    }

    /*
     * ------------------------------------------
     * 销退转预收，生成预存记录，充值金额
     * Author：qianbin        Time：2017-08-09
     * ------------------------------------------
     */
    private function addPreStoreOrderRecord($payment_info = [],$order_info = [])
    {
        if(count($payment_info) <= 0) return false;
        //新增单据
        $data = [
            'order_number'    => erpCodeNumber(13)['order_number'],
            'add_order_time'  => currentTime(),
            'our_company_id'  => session('erp_company_id'),
            'region'          => $order_info['region'],
            'user_id'         => $order_info['user_id'],
            'company_id'      => $order_info['company_id'],
            'collection_info' => $order_info['user_bank_info'],
            'order_type'      => 1 ,
            'recharge_type'   => 11 , // 预存款
            'recharge_amount' => $payment_info['collect_money'],
            'order_status'    => 10,
            'finance_status'  => 10,
            'dealer_name'     => $this->getUserInfo('dealer_name'),
            'creater'         => $this->getUserInfo('id'),
            'create_time'     => currentTime(),
            'remark'          => '来源销售退货单号: '.trim($payment_info['sale_order_number']).'&销退转预收自动生成',
            'from_order_number' => trim($payment_info['sale_order_number']),
            'apply_finance_time'=> currentTime(),
        ];
        $order_status = $id = $this->getModel('ErpRechargeOrder')->addRechargeOrder($data);

        //新增log
        $log_data = [
            'recharge_id'           => $id,
            'recharge_order_number' => $data['order_number'],
            'order_type'            => 2,
            'log_info'              => serialize($data),
            'log_type'              => 1,
            'create_time'           =>  currentTime(),
            'operator'              =>  $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0,
            'operator_id'           =>  $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0,
        ];
        $status = $this->getModel('ErpRechargeOrderLog')->add($log_data);

        //修改账户
        $data['object_type'] = 1;//单据类型：预存申请单
        $account_status = $this->getEvent('ErpAccount')->changeAccount($data, PRESTORE_TYPE, $data['recharge_amount']);
        $status = ($order_status && $status && $account_status) ? true : false;
        return $status ;
    }

    /*
     * ------------------------------------------
     * 销退单收款
     * Author：qianbin        Time：2018-08-09
     * ------------------------------------------
     */
    public function confirmSaleReturnCollection($param = [])
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        // 验证银行账套信息是否有效
        $bank_info = $this->getEvent('ErpBank')->getErpBankInfoById(intval($param['bank_id']));
        if (intval($param['bank_id']) > 0 && (empty($bank_info) || intval($bank_info['status']) != 1)) {
            $result['status']  = 21;
            $result['message'] = '该笔银行账号信息有误，请刷新后重试！';
            return $result;
        }
        //销退的整单收款加入流程限制——销退单对应的入库单未审核不允许退款---------------------------
        $returned_order_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id' => intval($param['id'])]);
        // edit xiaowen 2019-3-18 销退的退款处理，要先验证销退的入库单是否都审核-----------------
        $returned_stock_in = $this->getModel('ErpStockIn')->where(['source_number'=>$returned_order_info['order_number'],'storage_type'=>3,'storage_status'=>1])->find();
        if (!empty($returned_stock_in)) {
            return ['status' => 2 , 'message' => '销退单存在未审核入库单，请先审核入库单后再退款！'];
        }
        //-----------------------end-------------------------------------------------------------------
//        $order_info = $this->getModel('ErpSaleOrder')->where(['id' => $returned_order_info['source_order_id'],'order_number' => $returned_order_info['source_order_number']])->find();
//        if ($order_info['is_returned'] == 1 && $order_info['returned_goods_num'] == 0) {
//            return ['status' => 2 , 'message' => '退货单对应入库单未审核，无法退款！'];
//        }
        if (getCacheLock('ErpFinance/confirmSaleReturnCollection')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/confirmSaleReturnCollection', 1);
        M()->startTrans();
        //edit xiaowen 如果是销退单确认收款，走单独处理
        $field = 'ro.*,o.company_id,o.user_id,o.user_bank_info';
        $returned_order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrderJoinOrder(['ro.id'=>intval($param['id']),'ro.order_type' => 1],$field);
        $status = $this->confirmReturnedCollection($returned_order_info,$param,$bank_info);
        if($status){
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        }else{
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpFinance/confirmSaleReturnCollection');
        return $result;
    }

    /*
     * ------------------------------------------
     * 上传销售发票 发送给爱共享
     * Author：qianbin        Time：2018-09-20
     * ------------------------------------------
     */
    public function uploadSaleOrderTickets($param = [])
    {
        $result = ['status' => 0 , 'message' => '参数错误，请刷新后重试！'];
        # 参数验证 ------------------------
        if(empty($param)){
            return $result;
        }
        $string_order_number = '';
        # 验证是全部上传，还是选择批量上传
        if(empty($param['id']) && intval($param['all']) == 1 ){
            # 全部上传
            $where = [
                'collection_status' => 10 ,
                'invoice_status'    => 1 ,
                'is_returned'       => 2 ,
            ];

            $sale_order_data = $this->getModel('ErpSaleOrder')->field(true)->where($where)->select();

        }else if (!empty($param['id']) && intval($param['all']) == 2 ){
            # 批量上传
            $where = [
                'id'                => ['in' , $param['id']],
                'collection_status' => 10 ,
                'invoice_status'    => 1 ,
                'is_returned'       => 2 ,
            ];
            $sale_order_data = $this->getModel('ErpSaleOrder')->field(true)->where($where)->select();

            # 查询出不符合条件的订单
            $diff_order_number = [];
            if(count($sale_order_data) > 0 ) {
                $sale_order_number = array_column($sale_order_data, 'order_number');
                $diff_order_number = array_diff($param['order_number'],$sale_order_number);
            }

            if(count($diff_order_number) > 0){
                $string_order_number     = implode("，<br /> ",$diff_order_number);
                $message = '以下订单:<br />'.$string_order_number.' <br />存在收款状态或发票状态错误，无法上传！';
                return ['status' => 2 , 'message' => $message];
            }
        }else{

            return $result;
        }
        if(empty($sale_order_data)){
            return ['status' => 2 , 'message' => '未查询到符合条件的销售发票，上传失败！'];
        }
        # -----------------------------校验通过，匹配数据

        # ---匹配商品代码-----
        $goods_data = [];
        $goods_id = array_unique(array_column($sale_order_data, 'goods_id'));
        if(count($goods_id) > 0 ){
            $goods_data = $this->getModel('ErpGoods')->where(['id' => ['in' , $goods_id]])->getField('id,goods_code,goods_name',true);
        }

        # ---匹配商品税号-----
        $goods_name         = array_unique(array_column($goods_data, 'goods_name'));
        $goods_name_number  = getSaleInvoiceGoodsNumber();
        $check_goods_number = [];
        foreach ($goods_name as $k => $v){
            if(!array_key_exists(trim($v),$goods_name_number))$check_goods_number[] = $v;
        }
        if(count($check_goods_number) > 0){
            $check_goods_number     = implode("，<br /> ",$check_goods_number);
            $message = '以下商品:<br />&nbsp;&nbsp;&nbsp;&nbsp;'.$check_goods_number.'<br />未匹配到合法的税号，无法上传！';
            return ['status' => 4 , 'message' => $message];
        }
        # ---匹配客户信息-----
        $company_data = [];
        $company_id = array_merge(array_unique(array_column($sale_order_data, 'company_id')),array_unique(array_column($sale_order_data, 'our_company_id')));
        if(count($company_id) > 0 ){
            $company_data = $this->getModel('Clients')->where(['id' => ['in' , $company_id]])->getField('id,company_name,tax_num,bank_name,bank_num',true);
        }
        $data = [];
        foreach ($sale_order_data as $k => $v){

            $data[$k]['orderNumber']     = $v['order_number'];                                  # 销售订单
            $data[$k]['orderDate']       = date('Y-m-d', strtotime($v['add_order_time']));      # 订单日期
            $data[$k]['trader']          = $v['dealer_name'];                                   # 交易员
            $data[$k]['salesCompTaxCode'] = $company_data[$v['our_company_id']]['tax_num'];      # 销方公司税号
            $data[$k]['custName']        = $company_data[$v['company_id']]['company_name'];     # 客户
            $data[$k]['custTaxCode']     = $company_data[$v['company_id']]['tax_num'];          # 客户国税号
            $data[$k]['custBankName']    = $company_data[$v['company_id']]['bank_name'];        # 客户开户银行
            $data[$k]['custBankAcc']     = $company_data[$v['company_id']]['bank_num'];         # 客户银行账号
            $data[$k]['goodsCode']       = $goods_data[$v['goods_id']]['goods_code']." - ".$goods_data[$v['goods_id']]['goods_name']; # 商品代码
            $data[$k]['goodsPrice']      = getNum($v['price']);                                                 # 单价    decimal(19,8)
            $data[$k]['goodsQuantity']   = getNum($v['buy_num']);                                               # 数量    decimal(19,2)
            $data[$k]['returnQuantity']  = $v['returned_goods_num'] > 0 ? getNum($v['returned_goods_num']) : '0';   # 退货数量	decimal(19,2)
            $data[$k]['realQuantity']    = getNum($v['outbound_quantity']);                                     # 实际销售数量	decimal(19,2)
            $data[$k]['freight']         = $v['delivery_money'] > 0 ? getNum($v['delivery_money']) : '0';       # 运费	decimal(19,2)
            $data[$k]['orderTotal']      = getNum($v['order_amount']);                                          # 订单总额	decimal(19,2)
            $data[$k]['invoicedAmount']  = $v['invoiced_amount'] > 0 ? getNum($v['invoiced_amount']) : '0';     # 已开票金额	decimal(19,2)
            $data[$k]['unbilledAmount']  = round(getNum($v['order_amount'] - $v['invoiced_amount']),2);        # 未开票金额	decimal(19,2)
            $data[$k]['receiptStatus']   = saleCollectionStatus($v['collection_status']);                       # 收款状态
            $data[$k]['billingStatus']   = purchaseInvoiceStatus($v['invoice_status']);                         # 开票状态
            $data[$k]['receiptType']     = saleOrderPayType($v['pay_type']);                                    # 收款方式
            $data[$k]['specialNeeds']    = $v['invoice_remark'];                                                # 开票特殊需求
            $data[$k]['taxonomyCoding']  = $goods_name_number[trim($goods_data[$v['goods_id']]['goods_name'])];                                                                  # 税收分类编码
            $data[$k]['custAddrPhone']   = $v['company_info'];

            $data[$k]['secondPrice']     = '';

        }
        $i_share_data = [
            'rows' => "".count($data),
            'data' => base64_encode(json_encode($data,true)),
        ];
        # 组装信息完毕，发送信息到爱共享
        $url    = C('ASHARE_API_SERVER') . '/api/ERP/SendSalesData';

        $headers = array(
            "Content-type: application/json;charset='utf-8'",
            "Accept: application/json",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
        );
        $result = http($url, json_encode($i_share_data),'POST',$headers,true);
        $log_info_data = array(
            'event'   => '上传待开票信息接口-爱共享',
            'key'     => '销售单号：' . $string_order_number . ', URL：' . $url,
            'request' => '参数：' . var_export($i_share_data,true),
            'response'=> '爱共享返回值：'.var_export(json_decode($result,true),true),
        );
        log_write($log_info_data);
        $result = json_decode($result,true);
        if($result['code'] == 'SUCCESS'){
            $result = ['status' => 1 , 'message' => isset($result['msg']) ? $result['msg'] : $result['message']];
        }else{
            $result = ['status' => 3 , 'message' => isset($result['msg']) ? $result['msg'] : $result['message']];
        }
        return $result ;
    }
    /*
     * ------------------------------------------
     * 取消发票 发送给爱共享
     * Author：qianbin        Time：2018-09-20
     * ------------------------------------------
     */
    public function cancleSaleOrderTickets($param = [])
    {
        $result = ['status' => 0 , 'message' => '参数错误，请刷新后重试！'];
        # 参数验证 ------------------------
        if(empty($param) || empty($param['order_number'])){
            return $result;
        }
        # 批量上传
        $where = [
            'id'                => ['in' , $param['id']],
            'collection_status' => 10 ,
            'invoice_status'    => 1 ,
            'is_returned'       => 2 ,
        ];
        $sale_order_data = $this->getModel('ErpSaleOrder')->field(true)->where($where)->select();
        if(empty($sale_order_data)){
            return ['status' => 0 , 'message' => '未查询到可以取消的发票数据！'];
        }

        # 查询出不符合条件的订单
        $diff_order_number = [];
        $sale_order_number = array_column($sale_order_data, 'order_number');
        $diff_order_number = array_diff($param['order_number'],$sale_order_number);

        if(count($diff_order_number) > 0){
            $string_order_number     = implode("，<br /> ",$diff_order_number);
            $message = '以下订单:<br />'.$string_order_number.' <br />存在收款状态或发票状态错误，无法取消！';
            return ['status' => 2 , 'message' => $message];
        }
        $i_share_data     = ['ordernumbers' => implode(",",$sale_order_number)];

        # 组装信息完毕，发送信息到爱共享
        $url    = C('ASHARE_API_SERVER') . '/api/ERP/CancelMakeInvoice';
        $headers = array(
            "Content-type: application/json;charset='utf-8'",
            "Accept: application/json",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
        );
        $result = http($url, json_encode($i_share_data),'POST',$headers,true);
        $log_info_data = array(
            'event'   => '取消开票信息接口-爱共享',
            'key'     => '销售单号：' . $i_share_data . ', URL：' . $url,
            'request' => '参数：' . var_export($i_share_data,true),
            'response'=> '爱共享返回值：'.var_export(json_decode($result,true),true),
        );
        log_write($log_info_data);
        $result = json_decode($result,true);
        if($result['code'] == 'SUCCESS'){
            $result = ['status' => 1 , 'message' => isset($result['msg']) ? $result['msg'] : $result['message']];
        }else{
            $result = ['status' => 3 , 'message' => isset($result['msg']) ? $result['msg'] : $result['message']];
        }
        return $result ;
    }
    /*
     * @params:
     *      $id:损耗金额编号
     * @return : json
     * @auth:小黑
     * @time:2019-5-9
     * @desc:损耗单得红冲
     */
    public function returnedSaleLoss($params){
        $id = isset($params['id'])? $params['id']: "";
        if(empty($id)){
            return ['status' => 2 , "message" => "请选择正确得销售损耗单信息"] ;
        }
        $whereSaleLoss = [
            "id" => $id ,
            "is_reverse" => 2 ,
            "reverse_status" => 2
        ];
        $field = "id , sale_order_id , sale_order_number ,  loss_amount ";
        $saleLossOrder = $this->getModel("ErpSaleLoss")->findSaleLoss($whereSaleLoss , $field);
        if(empty($saleLossOrder)){
            return ['status' => 3 , "message" => "损耗单数据信息不正确，请查询"] ;
        }
        $whereSaleOrder = [
            "id" => $saleLossOrder['sale_order_id'] ,
            "order_status" => ['neq' , 2] ,
            "is_loss" =>['neq' , 2] ,
            "is_void" => 2 ,
            "is_returned" =>2
        ];
        $saleOrderField  = "id , entered_loss_amount";
        $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder($whereSaleOrder , $saleOrderField) ;
        if(empty($saleOrderInfo)){
            return ['status' => 4 , "message" => "销售单数据信息不正确，请查询"] ;
        }
        //新增红冲损耗单信息
        $addLossData = [
            'sale_order_id' => $saleLossOrder['sale_order_id'],
            'sale_order_number' => $saleLossOrder['sale_order_number'],
            'order_lossed_money' => $saleOrderInfo['entered_loss_amount'] - $saleLossOrder['loss_amount'],
            'loss_amount' => -$saleLossOrder['loss_amount'],
            'creator' => $this->getUserInfo('dealer_name'),
            'creator_id' => $this->getUserInfo('id'),
            'create_time' => currentTime(),
            'return_time' => currentTime(),
            'status' => 1,
            'remark' => "损耗单金额编号：".$saleLossOrder['id']."红冲" ,
            "is_reverse" => 1 ,
            "reverse_source" => $saleLossOrder['id']
        ];
        //修改损耗单红冲状态
        $updateLossData=[
            "reverse_status" => 1
        ];
        //订单信息修改
        $updateSaleOrderData = [
            'is_loss' => 1,
            'entered_loss_amount' => $saleOrderInfo['entered_loss_amount'] - $saleLossOrder['loss_amount'],
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        //新增订单修改日志信息
        $addSaleOrderLogData = [
            'sale_order_id' =>  $saleLossOrder['sale_order_id'],
            'sale_order_number' => $saleLossOrder['sale_order_number'],
            'log_type' => 20,
            'log_info' => serialize($updateSaleOrderData),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        if (getCacheLock('ErpFinance/returnedSaleLoss'))
            return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpFinance/returnedSaleLoss', 1);
        M()->startTrans();
        if(!$this->getModel('ErpSaleLoss')->addSaleLoss($addLossData)){
            M()->rollback() ;
            cancelCacheLock('ErpFinance/returnedSaleLoss');
            return ["status" => 5 , "message" => "添加红冲数据失败，请重新尝试"];
        }
        $whereSaleLoss = ["id" => $id];
        if($this->getModel('ErpSaleLoss')->saveSaleLoss($whereSaleLoss , $updateLossData)===false){
            M()->rollback() ;
            cancelCacheLock('ErpFinance/returnedSaleLoss');
            return ["status" => 6 , "message" => "添加红冲数据失败，请重新尝试"];
        }
        if( $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' =>$saleLossOrder['sale_order_id']],$updateSaleOrderData)=== false){
            M()->rollback() ;
            cancelCacheLock('ErpFinance/returnedSaleLoss');
            return ["status" => 7 , "message" => "销售单数据更新失败，请重新尝试"];
        }
        if(!$this->getModel('ErpSaleOrderLog')->addSaleOrderLog($addSaleOrderLogData)){
            M()->rollback() ;
            cancelCacheLock('ErpFinance/returnedSaleLoss');
            return ["status" => 8 , "message" => "添加销售单操作数据更新失败，请重新尝试"];
        }
        M()->commit() ;
        cancelCacheLock('ErpFinance/returnedSaleLoss');
        return ["status" => 1 , "message" => "成功"] ;
    }
}
?>
