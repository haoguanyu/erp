<?php
/**
 * 采购单业务处理层
 * @author xiaowen
 * @time 2017-03-31
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpPurchaseEvent extends BaseController
{

    /**
     * 采购单列表
     * @author xiaowen 2017-03-16
     * @param $param
     */
    public function purchaseOrderList($param = [])
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

        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['o.region'] = ['in',$city_id];
        }
        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }
        if (trim($param['goods_code'])) {
            $where['o.goods_id'] = $param['goods_code'];
        }

        if (trim($param['type'])) {
            $where['o.type'] = trim($param['type']);
        }
        if (trim($param['pay_type'])) {
            $where['o.pay_type'] = intval(trim($param['pay_type']));
        }
        if (trim($param['pay_status'])) {
            $where['o.pay_status'] = intval(trim($param['pay_status']));
        }
        if (trim($param['invoice_status'])) {
            $where['o.invoice_status'] = intval(trim($param['invoice_status']));
        }

        if (trim($param['is_upload_contract'])) {
            $where['o.is_upload_contract'] = intval(trim($param['is_upload_contract']));
        }

        if (trim($param['depot_id'])) {

            $where['o.depot_id'] = intval($param['depot_id']);
        }
        if (trim($param['storehouse_id'])) {
            $where['o.storehouse_id'] = intval($param['storehouse_id']);
        }
        if (trim($param['dealer_name'])) {
            $where['o.buyer_dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];

        }
        if (trim($param['sale_user'])) {
            $user_ids = D("ErpSupplierUser")->where(['user_name' => ['like', '%' . trim($param['sale_user']) . '%'], 'status' => 1])->getField('id', true);
            if ($user_ids) {
                $where['o.sale_user_id'] = ['in', $user_ids];
            } else {
                $data['recordsFiltered'] = $data['recordsTotal'] = 0;
                $data['data'] = [];
                $data['draw'] = $_REQUEST['draw'];
                return $data;
            }

        }
        if (trim($param['sale_company_id'])) {
            $where['o.sale_company_id'] = intval(trim($param['sale_company_id']));
        }

        if ($param['status']) {

            $where['o.order_status'] = intval($param['status']);
        }

        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        # -----------------------------------------是否作废<qianbin> 2017/07/17-------------------------------------------
        if (intval($param['is_void'])) {
            $where['o.is_void']    = intval($param['is_void']);
        }

        //我的采购单
        if (trim($param['dealer_id'])) {
            $where['o.buyer_dealer_id'] = trim($param['dealer_id']);
        }
        if (trim($param['from_sale_order_number'])) {
            $where['o.from_sale_order_number'] = ['like', '%' . trim($param['from_sale_order_number']) . '%'];
        }
        if (trim($param['is_update_price'])) {
            $where['o.is_update_price'] = intval(trim($param['is_update_price']));
        }
        if (trim($param['business_type'])) {
            $where['o.business_type'] = intval(trim($param['business_type']));
        }
        //当前登陆选择的我方公司
        $where['o.our_buy_company_id'] = session('erp_company_id');

        $field = 'o.*,d.depot_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name,r.return_payed_amount';
        $data = $this->getModel('ErpPurchaseOrder')->getPurchaseOrderList($where, $field, $param['start'], $param['length']);

        $userIdArr = array_column($data['data'], 'sale_user_id');
        $companyIdArr = array_column($data['data'], 'sale_company_id');
        $userInfo = $this->getEvent('ErpCommon')->getUserData($userIdArr, 2);
        $companyInfo = $this->getEvent('ErpCommon')->getCompanyData($companyIdArr, 2);

        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                    $data['data'][$key]['sumTotal']['total_real_amount'] = $data['sumTotal']['total_order_amount'] - $data['sumTotal']['total_returned_money'];
                    //$data['data'][$key]['sumTotal']['total_no_payed_money'] = $data['sumTotal']['total_real_amount'] - $data['sumTotal']['total_payed_money'];
                }
                $data['data'][$key]['No'] = $i;
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['pay_status'] = purchasePayStatus($value['pay_status'], true);
                $data['data'][$key]['invoice_status'] = purchaseInvoiceStatus($value['invoice_status'], true);
                $data['data'][$key]['type'] = purchaseType($value['type'], true);
                $data['data'][$key]['business_type'] = empty($value['business_type']) ? '--' : getBusinessType($value['business_type']);
                $data['data'][$key]['type_font'] = purchaseType($value['type']);
                $data['data'][$key]['pay_type'] = purchasePayType($value['pay_type']);
                $data['data'][$key]['status']   = $value['order_status'];
                $data['data'][$key]['order_status'] = purchaseOrderStatus($value['order_status'], true);
                $data['data'][$key]['is_upload_contract'] = purchaseContract($value['is_upload_contract'], true);
                $data['data'][$key]['from_sale_order_number'] = $value['from_sale_order_number'] ? $value['from_sale_order_number'] : '--';
                $data['data'][$key]['price'] = $value['price'] > 0 ? round(getNum($value['price']),2) : '0.00';
                $data['data'][$key]['order_amount'] = $value['order_amount'] > 0 ? getNum($value['order_amount']) : '0';
                $data['data'][$key]['buy_num_liter'] = $value['buy_num_liter'] > 0 ? getNum($value['buy_num_liter']) : '0';
                $data['data'][$key]['price_liter'] = $value['price_liter'] > 0 ? getNum($value['price_liter']) : '0';
                //$data['data'][$key]['no_payed_money'] = $value['no_payed_money'] > 0 ? getNum($value['no_payed_money']) : '0';
                $data['data'][$key]['payed_money'] = $value['payed_money'] > 0 ? getNum($value['payed_money']) : '0';
                $data['data'][$key]['no_payed_money'] = round($data['data'][$key]['order_amount'] - $data['data'][$key]['payed_money'], 2);
                $data['data'][$key]['invoice_money'] = $value['invoice_money'] > 0 ? getNum($value['invoice_money']) : '0';
                $data['data'][$key]['storage_quantity'] = $value['storage_quantity'] > 0 ? getNum($value['storage_quantity']) : '0';
                $data['data'][$key]['goods_num'] = $value['goods_num'] > 0 ? round(getNum($value['goods_num']),4) : '0';
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];
                # -----------------------------------------是否作废<qianbin> 2017/07/17-----------------------------------------
                $data['data'][$key]['is_void']    = purchaseContract(intval($value['is_void']), true);
                $data['data'][$key]['s_company_name'] = $value['sale_company_id'] == 99999 ? '不限' : $companyInfo[$value['sale_company_id']]['company_name'];
                $data['data'][$key]['creater_name'] = M('Dealer')->where(['is_available' => 0, 'id' => $value['creater']])->field('dealer_name')->find()['dealer_name'];
                $data['data'][$key]['returned_goods_num'] = getNum($value['returned_goods_num']);
                $data['data'][$key]['total_order_amount'] = round($data['data'][$key]['order_amount']-getNum($value['return_payed_amount']), 2);
                $data['data'][$key]['retail_inner_order_number'] = $value['business_type'] == 6 ? $value['retail_inner_order_number'] : '——';
                //$data['data'][$key]['no_payed_money'] = round($data['data'][$key]['total_order_amount'] - $data['data'][$key]['payed_money'], 2);

                $data['data'][$key]['s_user_name'] = $userInfo[$value['sale_user_id']]['user_name'];
                $data['data'][$key]['s_user_phone'] = $userInfo[$value['sale_user_id']]['user_phone'];
                /*************************************
                    @ Content  内部交易单使用 YF
                **************************************/
                if ( $value['business_type'] == 6 ) {
                    $data['data'][$key]['payed_money']  = round($data['data'][$key]['payed_money'],2);
                    $data['data'][$key]['order_amount'] = round($data['data'][$key]['order_amount'],2);
                }
                /*************************************
                                END
                **************************************/
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
     * 新增采购单
     * @param array $param
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function addPurchaseOrder($param = [])
    {
        $storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['storehouse_id']])->find();
        if (empty($param)) {
            return ['status' => 0, 'message' => '参数有误'];
        } else if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择下单时间'
            ];
        } else if (!strHtml($param['our_buy_company_id'])) {
            $result = [
                'status' => 3,
                'message' => '请选择我方公司'
            ];
        } else if ($param['our_buy_company_id'] != session('erp_company_id')) {
            $result = [
                'status' => 4,
                'message' => '我方公司与当前登录账套公司不一致，请刷新页面'
            ];
        } else if (strHtml($param['sale_user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择用户'
            ];
        } else if (strHtml($param['sale_company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择供应商'
            ];
        } else if (!strHtml($param['region'])) {
            $result = [
                'status' => 7,
                'message' => '请选择城市'
            ];
        } else if (!strHtml($param['storehouse_id'])) {
            $result = [
                'status' => 8,
                'message' => '请选择仓库'
            ];
        } else if (!strHtml($param['depot_id'])) {
            $result = [
                'status' => 9,
                'message' => '请选择油库'
            ];
        } else if (!strHtml($param['pay_type'])) {
            $result = [
                'status' => 10,
                'message' => '请选择付款方式'
            ];
        } else if ($param['pay_type'] == 2 && !trim($param['prepay_ratio'])) {
            $result = [
                'status' => 11,
                'message' => '请输入预付比例'
            ];
        } else if ($param['pay_type'] == 3 && !trim($param['account_period'])) {
            $result = [
                'status' => 12,
                'message' => '请输入帐期'
            ];
        } else if ($param['sale_company_id'] != 99999 && !strHtml($param['sale_collection_info'])) {
            $result = [
                'status' => 13,
                'message' => '请选择银行帐号'
            ];
        } else if (!strHtml($param['goods_id'])) {
            $result = [
                'status' => 14,
                'message' => '请输入商品信息'
            ];
        } else if (!strHtml($param['price'])) {
            $result = [
                'status' => 15,
                'message' => '请输入采购单价'
            ];
        } else if (!strHtml($param['goods_num'])) {
            $result = [
                'status' => 16,
                'message' => '请输入采购数量'
            ];
        } else if (!strHtml($param['remark'])) {
            $result = [
                'status' => 16,
                'message' => '请输入备注'
            ];
        } else if ($storehourse['is_purchase'] != 1) {
            $result = [
                'status' => 17,
                'message' => '该仓库不能做采购业务，请检查'
            ];
        } else if ($storehourse['region'] != $param['region'] && $storehourse['whole_country'] != 1) {
            $result = [
                'status' => 18,
                'message' => '该仓库不是全国仓库，请检查'
            ];
        } else if ($storehourse['status'] == 2) {
            $result = [
                'status' => 19,
                'message' => '该仓库已禁用，请检查'
            ];
        } else {
            //如果供应商公司在黑名单中，则返回验证提示信息 edit xiaowen 2017-7-18
            if($this->isInBackList(intval($param['sale_company_id']))){
                return $result = [
                    'status' => 17,
                    'message' => '该公司已纳入黑名单，请联系采购员石姣'
                ];
            }
            if (getCacheLock('ErpPurchase/addPurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];

            setCacheLock('ErpPurchase/addPurchaseOrder', 1);

            M()->startTrans();
            $data = $param;

            $data['price'] = setNum($param['price']);
            $data['add_order_time'] = date('Y-m-d H:i:s', strtotime('+1 days', strtotime($param['add_order_time']) - 1));
            $data['price'] = setNum($param['price']);
            $data['prepay_ratio'] = setNum($param['prepay_ratio']);
            $data['goods_num'] = setNum($param['goods_num']);
            $data['no_payed_money'] = setNum(round($param['goods_num'] * $param['price'], 2));
            $data['order_number'] = erpCodeNumber(5)['order_number'];
            $data['create_time'] = currentTime();
            $data['creater'] = $this->getUserInfo('id');
            # 加油站采购 ， 总金额手动输入
            $data['order_amount'] = !empty(trim($param['order_amount_liter'])) ? setNum(round($param['order_amount_liter'],2)) : setNum(round($param['goods_num'] * $param['price'], 2));
            # 加油站采购，新增字段 qianbin 2018-05-06  --
            $data['buy_num_liter'] = setNum($param['buy_num_liter']);
            $data['price_liter']   = setNum($param['price_liter']);
            unset($data['order_amount_liter']);
            # end---------------------------------------
            unset($data['goods_name']);
            unset($data['goods_code']);
            unset($data['goods_from']);
            unset($data['goods_grade']);
            unset($data['goods_level']);
            $status = $id = $this->getModel('ErpPurchaseOrder')->addPurchaseOrder($data);

            $log_data = [
                'purchase_id' => $id,
                'purchase_order_number' => $data['order_number'],
                'log_type' => 1,
                'log_info' => serialize($data),
                'create_time' => currentTime(),
                'operator' => $this->getUserInfo('dealer_name'),
                'operator_id' => $this->getUserInfo('id'),
            ];
            $log_status = $this->getModel('ErpPurchaseLog')->add($log_data);

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

            cancelCacheLock('ErpPurchase/addPurchaseOrder');
        }
        return $result;
    }

    /**
     * 编辑采购单
     * @param $id
     * @param array $param
     * @return array
     */
    public function updatePurchaseOrder($id, $param = [])
    {
        $storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['storehouse_id']])->find();
        if (!$id || empty($param)) {
            return ['status' => 0, 'message' => '参数有误'];
        } else if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择下单时间'
            ];
        } else if (!strHtml($param['our_buy_company_id'])) {
            $result = [
                'status' => 3,
                'message' => '请选择我方公司'
            ];
        }

        else if (strHtml($param['sale_user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择用户'
            ];
        } else if (strHtml($param['sale_company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择供应商'
            ];
        } else if (!strHtml($param['region'])) {
            $result = [
                'status' => 7,
                'message' => '请选择城市'
            ];
        } else if (!strHtml($param['storehouse_id'])) {
            $result = [
                'status' => 8,
                'message' => '请选择仓库'
            ];
        } else if (!strHtml($param['depot_id'])) {
            $result = [
                'status' => 9,
                'message' => '请选择油库'
            ];
        } else if (!strHtml($param['pay_type'])) {
            $result = [
                'status' => 10,
                'message' => '请选择付款方式'
            ];
        } else if ($param['pay_type'] == 2 && !trim($param['prepay_ratio'])) {
            $result = [
                'status' => 11,
                'message' => '请输入预付比例'
            ];
        } else if ($param['pay_type'] == 3 && !trim($param['account_period'])) {
            $result = [
                'status' => 12,
                'message' => '请输入帐期'
            ];
        } else if ($param['sale_company_id'] != 99999 && !strHtml($param['sale_collection_info'])) {
            $result = [
                'status' => 13,
                'message' => '请选择银行帐号'
            ];
        } else if (!strHtml($param['goods_id'])) {
            $result = [
                'status' => 14,
                'message' => '请输入商品信息'
            ];
        } else if (!strHtml($param['price'])) {
            $result = [
                'status' => 15,
                'message' => '请输入采购单价'
            ];
        } else if (!strHtml($param['goods_num'])) {
            $result = [
                'status' => 16,
                'message' => '请输入采购数量'
            ];
        } else if (!strHtml($param['remark'])) {
            $result = [
                'status' => 16,
                'message' => '请输入备注'
            ];
        }  else if (!strHtml($param['business_type'])) {
            $result = [
                'status' => 16,
                'message' => '请选择业务类型！'
            ];
        } else if ($storehourse['is_purchase'] != 1) {
            $result = [
                'status' => 17,
                'message' => '该仓库不能做采购业务，请检查'
            ];
        } else if ($storehourse['region'] != $param['region'] && $storehourse['whole_country'] != 1) {
            $result = [
                'status' => 18,
                'message' => '该仓库不是全国仓库，请检查'
            ];
        } else if ($storehourse['status'] == 2) {
            $result = [
                'status' => 19,
                'message' => '该仓库已禁用，请检查'
            ];
        } //如果供应商公司在黑名单中，则返回验证提示信息 edit xiaowen 2017-7-18
        else if($this->isInBackList(intval($param['sale_company_id']))){
           $result = [
                'status' => 17,
                'message' => '该公司已纳入黑名单，请联系采购员石姣'
            ];
        } else {
            $purchaseInfo = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);

            if (empty($purchaseInfo)) {
                $result = [
                    'status' => 17,
                    'message' => '该采购单不存在'
                ];
            } else if ($purchaseInfo['order_status'] != 1 && !($purchaseInfo['order_status'] == 10 && $purchaseInfo['is_special'] == 1 && $purchaseInfo['pay_status'] == 1 && $purchaseInfo['invoice_status'] == 1)) {
                $result = [
                    'status' => 18,
                    'message' => '该采购单不是未审核状态，无法编辑'
                ];

            }
            //现结并且是特需已确认，价格不允许修改为比原来低 edit xiaowen 2017-6-10
            elseif($purchaseInfo['pay_type'] == 1 && $purchaseInfo['is_special'] == 1 && $purchaseInfo['order_status'] == 10 && setNum($param['price']) < $purchaseInfo['price']){
                $result = [
                    'status' => 19,
                    'message' => '现结特需采购单已确认后不允许价格低于原订单价格'
                ];
            } else {
                if (getCacheLock('ErpPurchase/updatePurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpPurchase/updatePurchaseOrder', 1);

                M()->startTrans();
                $data = $param;
                $data['price'] = setNum($param['price']);
                $data['prepay_ratio'] = setNum($param['prepay_ratio']);
                $data['add_order_time'] = date('Y-m-d H:i:s', strtotime('+1 days', strtotime($param['add_order_time']) - 1));
                $data['price'] = setNum($param['price']);
                $data['goods_num'] = setNum($param['goods_num']);
                $data['no_payed_money'] = setNum(round($param['goods_num'] * $param['price'], 2));
                // $data['order_amount'] = setNum(round($param['goods_num'] * $param['price'], 2));
                $data['update_time'] = currentTime();
                $data['updater'] = $this->getUserInfo('id');
                //根据采购单选择的城市，查找该地方对应的城市仓和代采仓
                #$storehouse_data = $this->getEvent('ErpStorehouse')->getStorehouseByRegion($param['region']);
                //根据采购单是否代采，确定使用该城市哪个仓库

                //$data['storehouse_id'] = $storehouse_data[$purchaseInfo['type']]['id'];
                $data['storehouse_id'] = intval($param['storehouse_id']);
                # 加油站采购 ， 总金额手动输入
                $data['order_amount'] = !empty(trim($param['order_amount_liter'])) ? setNum(round($param['order_amount_liter'],2)) : setNum(round($param['goods_num'] * $param['price'], 2));
                # 加油站采购，新增字段 qianbin 2018-05-06  --
                $data['buy_num_liter'] = setNum($param['buy_num_liter']);
                $data['price_liter']   = setNum($param['price_liter']);
                unset($data['order_amount_liter']);
                # end---------------------------------------
                unset($data['goods_name']);
                unset($data['goods_code']);
                unset($data['goods_from']);
                unset($data['goods_grade']);
                unset($data['goods_level']);

                $status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($id)], $data);
                $status_str = $status ? '成功' : '失败';
                log_info("采购单更新状态：". $status_str);
                $purchaseInfo_new = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
                $log_data = [
                    'purchase_id' => $id,
                    'purchase_order_number' => $purchaseInfo['order_number'],
                    'log_type' => 2,
                    'log_info' => serialize($purchaseInfo_new),
                ];
                $log_status = $this->addPurchaseLog($log_data);
                $log_str = $log_status ? '成功' : '失败';
                log_info("采购单日志更新状态：". $log_str);
                if ($purchaseInfo_new['type'] == 2) {
                    $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $purchaseInfo_new['from_sale_order_id']]);
                    $sale_order_data = [
                        'acting_purchase_num' => $sale_order_info['acting_purchase_num'] - $purchaseInfo['goods_num'] + $purchaseInfo_new['goods_num'],
                        'update_time' => currentTime(),
                    ];
                    $require_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $purchaseInfo_new['from_sale_order_id']],$sale_order_data);
                    $require_str = $require_status ? '成功' : '失败';
                    log_info("采购单对应销售单更新状态：" . $require_str);
                } else {
                    $require_status = true;
                }

                if ($status && $log_status && $require_status) {
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

                cancelCacheLock('ErpPurchase/updatePurchaseOrder');
            }

        }
        return $result;
    }

    /**
     * 删除采购单
     * @author xiaowen
     * @param $id
     * @return array
     */
    public function delPurchaseOrder($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
            if ($order_info['order_status'] == 10) {
                $result = [
                    'status' => 2,
                    'message' => '该采购单已确认，无法删除',
                ];
            } else if ($order_info['order_status'] == 2) {
                $result = [
                    'status' => 2,
                    'message' => '该采购单已取消，无法重复取消',
                ];
            } else {
                if (getCacheLock('ErpPurchase/delPurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpPurchase/delPurchaseOrder', 1);
                M()->startTrans();
                $data = [
                    'order_status' => 2,
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($id)], $data);

                $log = [
                    'purchase_id' => $order_info['id'],
                    'purchase_order_number' => $order_info['order_number'],
                    'log_info' => serialize($order_info),
                    'log_type' => 3,
                ];
                $log_status = $this->addPurchaseLog($log);

                if ($order_info['type'] == 2) {
                    $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $order_info['from_sale_order_id']]);
                    $sale_order_data = [
                        'acting_purchase_num' => $sale_order_info['acting_purchase_num'] - $order_info['goods_num'],
                        'update_time' => currentTime(),
                    ];
                    $require_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $order_info['from_sale_order_id']],$sale_order_data);
                } else {
                    $require_status = true;
                }

                //删除审批流的流程
                $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id, 'status'=>['neq', 2]])->order('id desc')->find();

                if ($workflow && $workflow['status'] == 1) {
                    $workflow['status'] = 2;
                    $status_work = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
                } else {
                    $status_work = true;
                }

                if ($status && $log_status && $require_status && $status_work) {
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

                cancelCacheLock('ErpPurchase/delPurchaseOrder');
            }

            return $result;

        }
    }

    /**
     * 审核采购单
     * @author xiaowen
     * @param $id
     * @return array
     * @time 2017-4-5
     */
    public function auditPurchaseOrder($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
            //判断是否开启合同审核
            $config_value = $this->getModel('ErpConfig')->where(['type'=>3, 'purchase_business_type'=>$order_info['business_type'], 'status'=>'1'])->getField('value');
            if($config_value == 1 && $order_info['is_upload_contract'] == 2){
                return $result = [
                    'status' => 4,
                    'message' => '请上传合同后再审核',
                ];
            }
            if ($order_info['order_status'] != 1) {
                $result = [
                    'status' => 2,
                    'message' => '该采购单不是未审核状态，无法审核',
                ];
            } else {
                if (getCacheLock('ErpPurchase/auditPurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];

                # 判断公司账套之间 ， 不走审批
                # qianbin
                # 2017.11.06
                //$company_id = getErpCompanyList('company_id');
                //判断供应商公司是否为内部公司，判断是否走审核流 edit xiaowen 2019-3-28
                $order_info['company_name'] = $this->getModel('ErpSupplier')->where(['id'=>$order_info['sale_company_id']])->getField('supplier_name');
                //if(in_array(intval($order_info['sale_company_id']),$company_id)){
                if(checkInErpCompany($order_info['company_name'])){
                    $result = $this->getEvent('ErpWorkFlow')->updateErpWorkFlowOrderStatus(2,intval($order_info['id']),4);
                    log_info('--------->公司账套之间 ， 不走审批!');
                    if($result){
                        $result = ['status' => 1 , 'message' => '操作成功！'];
                    }else{
                        $result = ['status' => 222 , 'message' => '操作失败！'];
                    }
                    return $result;
                }

                setCacheLock('ErpPurchase/auditPurchaseOrder', 1);
                M()->startTrans();
                $data = [
                    'order_status' => 3,
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($id)], $data);
                //---------------------生成审批流程------------------------------------------------

                //$workflow_status = $this->createWorkflow($order_info);
                $workflow_status = $this->createWorkflowNew($order_info);

                //----------------------------------------------------------------------------------
                $log = [
                    'purchase_id' => $order_info['id'],
                    'purchase_order_number' => $order_info['order_number'],
                    'log_info' => serialize($order_info),
                    'log_type' => 4,
                ];
                $log_status = $this->addPurchaseLog($log);
                if ($status && $log_status && $workflow_status['status']) {
                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                    ];
                } else {
                    M()->rollback();
                    $result = [
                        'status' => 0,
                        'message' => $workflow_status['message'] ?  $workflow_status['message'] : '操作失败',
                    ];
                }

                cancelCacheLock('ErpPurchase/auditPurchaseOrder');
            }

        } else {
            $result = [
                'status' => 0,
                'message' => '参数有误，请选择采购单',
            ];
        }
        return $result;
    }

    /**
     * 确认采购单
     * @param id
     * @return array $result
     * @author xiaowen
     * @time 2017-4-5
     */
    public function confirmPurchaseOrder($id)
    {

        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误，无法获取采购单ID',
            ];
        } else {
            $purchaseInfo = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);

            if ($purchaseInfo['order_status'] != 4) {
                $result = [
                    'status' => 2,
                    'message' => '该采购单不是已复核状态，请稍后操作',
                ];
            }
//            else if($purchaseInfo['is_upload_contract'] != 1){
//                $result = [
//                    'status'=>3,
//                    'message'=>'该采购单未上传合同，请稍后操作',
//                ];
//            }
            else {
                if (getCacheLock('ErpPurchase/confirmPurchase')) return ['status' => 99, 'message' => $this->running_msg];


                setCacheLock('ErpPurchase/confirmPurchase', 1);
                M()->startTrans();
                $data = [
                    'order_status' => 10,
                    'confirm_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];

                $status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($id)], $data);

                $log = [
                    'purchase_id' => $purchaseInfo['id'],
                    'purchase_order_number' => $purchaseInfo['order_number'],
                    'log_info' => serialize($purchaseInfo),
                    'log_type' => 6,
                ];
                $log_status = $this->addPurchaseLog($log);

                /** --------付款方式为账期单据在确认时影响在途库存-start------- **/
                if ($purchaseInfo['pay_type'] == 3) {
                    //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                    $stock_where = [
                        'goods_id' => $purchaseInfo['goods_id'],
                        'object_id' => $purchaseInfo['storehouse_id'],
                        'stock_type' => $purchaseInfo['type'] == 1 ? getAllocationStockType($purchaseInfo['storehouse_id']) : 2,
                    ];
                    $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                    //------------------组装库存表的字段值--------------------------
                    $data = [
                        'goods_id' => $purchaseInfo['goods_id'],
                        'object_id' => $purchaseInfo['storehouse_id'],
                        'stock_type' => $purchaseInfo['type'] == 1 ? getAllocationStockType($purchaseInfo['storehouse_id']) : 2,
                        'region' => $purchaseInfo['region'],
                        'transportation_num' => $stock_info['transportation_num'] + $purchaseInfo['goods_num'],
                    ];
                    $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的预留库存
                    //------------------计算出新的可用库存----------------------------
                    $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                    //----------------------------------------------------------------
                    $orders = [
                        'object_number' => $purchaseInfo['order_number'],
                        'object_type' => 2,
                        'log_type' => 3,
                    ];
                    //----------------更新库存，并保存库存日志-------------------------
                    $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $purchaseInfo['goods_num'], $orders);
                } else {
                    $stock_status = true;
                }
                /** --------付款方式为账期单据在确认时影响在途库存-end------- **/

                if ($status && $log_status && $stock_status) {
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
            }

            cancelCacheLock('ErpPurchase/confirmPurchase');
        }

        return $result;

    }

    /**
     * 上传合同
     * @param $id
     * @param array $attach
     * @return array
     * @author xiaowen
     * @time 2017-4-5
     */
    public function uploadContract($id, $attach = [])
    {


        if ($id && $attach) {

            if (count($attach) > 1) {
                return $result = [

                    'status' => 4,
                    'message' => '对不起，同时只能上传一份合同',

                ];
            }

            $purchaseInfo = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
            //取消上传合同必须是已确认限制 xiaowen 2019-2-22
            //if ($purchaseInfo['order_status'] != 10) {
            if (false) {

                $result = [
                    'status' => 2,
                    'message' => '该采购单不是已确认状态，请稍后操作',

                ];
            } else {
                if (getCacheLock('ErpPurchase/uploadContract')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpPurchase/uploadContract', 1);

                M()->startTrans();

                $data = [
                    'is_upload_contract' => 1,
                    'contract_url' => $attach[0],
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($id)], $data);

                $log_data = [
                    'purchase_id' => $purchaseInfo['id'],
                    'purchase_order_number' => $purchaseInfo['order_number'],
                    'log_info' => serialize($purchaseInfo),
                    'log_type' => 7,
                ];
                $log_status = $this->addPurchaseLog($log_data);

                if ($status && $log_status) {
                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                    ];
                } else {
                    M()->rollback();
                    $result = [
                        'status' => 2,
                        'message' => '操作失败',
                    ];
                }
                cancelCacheLock('ErpPurchase/uploadContract');
            }

        } else {
            $result = [
                'status' => 0,
                'message' => '参数有误',
            ];
        }
        return $result;
    }


    /**
     * 插入采购单操作日志
     * @author xiaowen
     * @param $data
     * @return mixed
     */
    public function addPurchaseLog($data)
    {
        if ($data) {
            $data['create_time'] = currentTime();
            $data['operator'] = $this->getUserInfo('dealer_name');
            $data['operator_id'] = $this->getUserInfo('id');
            $status = $this->getModel('ErpPurchaseLog')->add($data);
        }
        return $status;
    }

    /**
     * 复制采购单
     * @param id
     * @return array $result
     * @author xiaowen
     * @time 2017-4-5
     */
    public function copyPurchaseOrder($id)
    {

        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误，无法获取采购单ID',
            ];
        } else {
            $purchaseInfo = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
            if ( !empty($purchaseInfo) && $purchaseInfo['business_type'] == 6 ) {
                return ['status' => 4,'message' => '该采购单属于内部交易单，不能复制！'];
            }

            if (empty($purchaseInfo)) {
                $result = [
                    'status' => 2,
                    'message' => '该采购单不存在，请稍后操作',
                ];
            }else if ($purchaseInfo['type'] == 2) {
                $result = [
                    'status' => 3,
                    'message' => '代采采购单不能复制，请重新操作',
                ];
            }else if ($purchaseInfo['business_type'] == 4 ) {
                $result = [
                    'status' => 3,
                    'message' => '加油站业务采购单不能复制，请新增！',
                ];
            }else {
                //如果供应商公司在黑名单中，则返回验证提示信息 edit xiaowen 2017-7-18


               if($this->isInBackList(intval($purchaseInfo['sale_company_id']))){
                   return $result = [
                       'status' => 17,
                       'message' => '该公司已纳入黑名单，请联系采购员石姣'
                   ];
               }
                if (getCacheLock('ErpPurchase/copyPurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpPurchase/copyPurchaseOrder', 1);
                M()->startTrans();
                $data = $purchaseInfo;

                $data['create_time'] = currentTime();
                $data['creater'] = $this->getUserInfo('id');
                $data['order_number'] = erpCodeNumber(5)['order_number'];
                $data['no_payed_money'] = $data['order_amount'];
                $data['buyer_dealer_id'] = $this->getUserInfo('id');
                $data['buyer_dealer_name'] = $this->getUserInfo('dealer_name');
                $data['add_order_time'] = date('Y-m-d 23:59:59');

                unset($data['id']);
                unset($data['update_time']);
                unset($data['updater']);
                unset($data['order_status']);
                unset($data['remark']);
                unset($data['pay_status']);
                unset($data['payed_money']);
                //unset($data['no_payed_money']);

                unset($data['invoice_status']);
                unset($data['invoice_money']);
                unset($data['from_sale_order_number']);
                unset($data['from_sale_order_id']);
                unset($data['is_upload_contract']);
                unset($data['contract_url']);
                unset($data['confirm_time']);
                unset($data['check_time']);
                unset($data['audit_time']);
                unset($data['is_void']);
                unset($data['storage_quantity']); //重置入库数量
                unset($data['total_purchase_wait_num']); //重置已转在途数量
                //edit xiaowen 2017-9-1
                unset($data['is_update_price']); //重置已转在途数量
                unset($data['original_price']); //重置已转在途数量
                unset($data['returned_goods_num']); //重置退货数量
                unset($data['is_returned']); //重置退货标识

                $status = $this->getModel('ErpPurchaseOrder')->addPurchaseOrder($data);

                $log = [
                    'purchase_id' => $purchaseInfo['id'],
                    'purchase_order_number' => $purchaseInfo['order_number'],
                    'log_info' => serialize($purchaseInfo),
                    'log_type' => 14,
                ];
                $log_status = $this->addPurchaseLog($log);

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
                cancelCacheLock('ErpPurchase/copyPurchaseOrder');
            }
        }

        return $result;

    }

    /**
     * 申请付款
     * @author senpai
     */
    public function applicationPayment($param)
    {
        if (getCacheLock('ErpPurchase/applicationPayment')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpPurchase/applicationPayment', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $all_pay_money = $this->getModel('ErpPurchasePayment')->field('sum(pay_money) as total')->where(['purchase_id' => $param['purchase_id'], 'status' => ['neq', 2], 'source_order_type' => 1])->group('purchase_id')->find();
            $order_info = $this->getModel('ErpPurchaseOrder')->where(['id' => $param['purchase_id']])->find();
            $date = date('Y-m-d');
            //@ edit xiaowen 2017-9-4 订单总金额 = 订单金额 - 退货金额
            //$order_info['order_amount'] = $order_info['order_amount'] - (getNum($order_info['price']) * $order_info['returned_goods_num']);
            if (trim($param['pay_money']) == "") {
                $result['status'] = 101;
                $result['message'] = "请输入申请金额";
            } elseif ($order_info['pay_status'] == 10) {
                $result['status'] = 101;
                $result['message'] = "采购单为已付款状态，无法再申请付款";
            }
//            elseif (trim($param['pay_money'] <= 0)) {
//                $result['status'] = 101;
//                $result['message'] = "申请金额不能小于0";
//            }
            elseif (intval(setNum(trim($param['pay_money']))) > $order_info['order_amount'] - $all_pay_money['total']) {
                $result['status'] = 101;
                $result['message'] = "已申请金额不能超出订单总金额";
            } elseif (trim($param['apply_pay_time']) == "") {
                $result['status'] = 101;
                $result['message'] = "请选择申请付款日期";
            } elseif (trim($param['apply_pay_time']) < $date) {
                $result['status'] = 101;
                $result['message'] = "申请付款日期必须为今天或今天之后";
            }
            //edit xiaowen 2017-7-27 如果是二次定价订单，付款申请金额必须等于待付金额
            else if($order_info['is_update_price'] == 1 && intval(setNum(trim($param['pay_money']))) != ($order_info['order_amount'] - $all_pay_money['total'])){
                $result['status'] = 102;
                $result['message'] = "二次定价订单，申请付款金额必须等于未付金额";
            }
            else {
                M()->startTrans();
                $erp_payment_data = [
                    'pay_money' => setNum($param['pay_money']),
                    'purchase_id' => $param['purchase_id'],
                    'purchase_order_number' => $param['purchase_order_number'],
                    'create_time' => DateTime(),
                    'creator' => $this->getUserInfo('dealer_name'),
                    'creator_id' => $this->getUserInfo('id'),
                    'apply_pay_time' => $param['apply_pay_time'] . ' 23:59:59',
                    'status' => 1,
                    'remark' => $param['remark'],
                    'our_company_id' => $order_info['our_buy_company_id'],
                    'sale_company_id' => $order_info['sale_company_id'],
                    'dealer_id' => $order_info['buyer_dealer_id'],
                    'from_purchase_order_number' => $param['purchase_order_number'],
                ];
                if (trim($param['id']) == "") {
                    //添加erp付款申请信息
                    $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);
                } else {
                    //编辑erp付款申请信息
                    $status_payment = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['id' => $param['id']], $erp_payment_data);
                }
                $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $param['purchase_id']]);
                $log_data = [
                    'purchase_id' => $param['purchase_id'],
                    'purchase_order_number' => $param['purchase_order_number'],
                    'log_type' => 9,
                    'log_info' => serialize($order_info),
                    'create_time' => DateTime(),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                ];
                $status_log = $this->getModel('ErpPurchaseLog')->add($log_data);
                if ($status_payment && $status_log) {
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
        cancelCacheLock('ErpPurchase/applicationPayment');
        return $result;
    }

    /**
     * 返回采购单状态
     * @param $id
     * @return mixed
     */
    public function getPurchaseOrderStatus($id)
    {
        $order_status = $this->getModel('ErpPurchaseOrder')->where(['id' => $id])->field('order_status,pay_type,pay_status,business_type,retail_inner_order_number')->find();
        return $order_status;
    }

    /**
     * 返回申请付款及发票信息
     * @param $id
     * @return mixed
     */
    public function getPurchaseOrderInfo($id)
    {
        $order_info = $this->getModel('ErpPurchaseOrder')->alias('o')
            ->where(['o.id' => $id])
            ->field('o.order_amount,r.return_payed_amount')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
            ->find();
        $all_invoice_money = $this->getModel('ErpPurchaseInvoice')->field('sum(apply_invoice_money) as total')->where(['purchase_id' => $id, 'status' => ['neq', 2]])->group('purchase_id')->find();
        $all_pay_money = $this->getModel('ErpPurchasePayment')->field('sum(pay_money) as total')->where(['purchase_id' => $id, 'status' => ['neq', 2], 'source_order_type'=>1])->group('purchase_id')->find();
        $data['order_amount'] = getNum($order_info['order_amount']);
        //@ 订单总金额 = 订单金额 - 退货金额 edit xiaowen 2017-9-4 ---------------------------
        $data['total_order_amount'] = $data['order_amount'] - round(getNum($order_info['return_payed_amount']),2);
        //@----------------------------------------------------------
        $data['all_invoice_money'] = getNum($all_invoice_money['total']);
        $data['all_pay_money'] = getNum($all_pay_money['total']);
        return $data;
    }

    /**
     * 采购需求
     * @author senpai
     * @time 2017-05-08
     */
    public function purchaseRequireList($param)
    {
        $where = [];

        if (trim($param['region'])) {
            $where['o.region'] = intval($param['region']);
        }
        if (trim($param['goods_code'])) {
            $where['o.goods_id'] = $param['goods_code'];
        }
        if (trim($param['depot_id'])) {
            $where['o.depot_id'] = intval($param['depot_id']);
        }
        if (trim($param['storehouse_id'])) {
            $where['o.storehouse_id'] = intval($param['storehouse_id']);
        }
        if (trim($param['dealer_name'])) {
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        $where['o.order_status'] = 10;
        $where['o.is_agent'] = 1;
        $where['o.our_company_id'] = session('erp_company_id');
        /**
         * 付款方式为代采现结，单据状态为已确认
         * 付款方式为现结，经过审批流后，单据状态为已确认，存在代采标识，并且财务收款状态为已收款
         * 付款方式为账期，经过审批流后，单据状态为已确认，存在代采标识。
         * 代采销售单已生成的采购单数量未达到销售数量
         */
        $where['_string'] = 'o.buy_num > o.acting_purchase_num  AND ((o.pay_type = 1 and o.collection_status = 10) OR (o.pay_type = 3) OR (o.pay_type = 2) OR (o.pay_type = 5 and o.collection_status > 2))';

        $field = 'o.*,d.depot_name,es.storehouse_name,g.goods_code,g.goods_name,g.source_from,g.level,g.grade';
        $data = $this->getModel('ErpSaleOrder')->getPurchaseRequireList($where, $field, $param['start'], $param['length']);

        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['price'] = $value['price'] > 0 ? getNum($value['price']) : '0.00';
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? getNum($value['buy_num'] - $value['acting_purchase_num']) : 0;
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 新增代采采购单
     * @param array $param
     * @author senpai
     * @time 2017-5-9
     * @return array
     */
    public function addActingPurchaseOrder($param = [])
    {
        if (empty($param)) {
            return ['status' => 0, 'message' => '参数有误'];
        } else if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择下单时间'
            ];
        } else if (!strHtml($param['our_buy_company_id'])) {
            $result = [
                'status' => 3,
                'message' => '请选择我方公司'
            ];
        }

        else if (strHtml($param['sale_user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择用户'
            ];
        }
        else if (strHtml($param['sale_company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择供应商'
            ];
        } else if (!strHtml($param['region'])) {
            $result = [
                'status' => 7,
                'message' => '请选择城市'
            ];
        } else if (!strHtml($param['storehouse_id'])) {
            $result = [
                'status' => 8,
                'message' => '请选择仓库'
            ];
        } else if (!strHtml($param['depot_id'])) {
            $result = [
                'status' => 9,
                'message' => '请选择油库'
            ];
        } else if (!strHtml($param['pay_type'])) {
            $result = [
                'status' => 10,
                'message' => '请选择付款方式'
            ];
        } else if (($param['pay_type'] == 2 || $param['pay_type'] == 5) && !trim($param['prepay_ratio'])) {
            $result = [
                'status' => 11,
                'message' => '请输入预付比例'
            ];
        } else if (($param['pay_type'] == 3 || $param['pay_type'] == 5) && !trim($param['account_period'])) {
            $result = [
                'status' => 12,
                'message' => '请输入帐期'
            ];
        }else if (($param['pay_type'] == 2 || $param['pay_type'] == 5) && ((trim($param['prepay_ratio']) >= 100 || trim($param['prepay_ratio']) < 0))) {
            $result = [
                'status' => 12,
                'message' => '比例只能在0-100之间'
            ];
        } else if ($param['sale_company_id'] != 99999 && !strHtml($param['sale_collection_info'])) {
            $result = [
                'status' => 13,
                'message' => '请选择银行帐号'
            ];
        } else if (!strHtml($param['goods_id'])) {
            $result = [
                'status' => 14,
                'message' => '请输入商品信息'
            ];
        } else if (!strHtml($param['price'])) {
            $result = [
                'status' => 15,
                'message' => '请输入采购单价'
            ];
        } else if (!strHtml($param['goods_num'])) {
            $result = [
                'status' => 16,
                'message' => '请输入采购数量'
            ];
        } else if (!strHtml($param['remark'])) {
            $result = [
                'status' => 16,
                'message' => '请输入备注'
            ];
        } else {
            //如果供应商公司在黑名单中，则返回验证提示信息 edit xiaowen 2017-7-18
            if($this->isInBackList(intval($param['sale_company_id']))){
                return $result = [
                    'status' => 17,
                    'message' => '该公司已纳入黑名单，请联系采购员石姣'
                ];
            }
            //一张代采销售单只能生成唯一一张对应的代采采购单 update by guanyu @ 2017-09-06
            $exist = $this->getModel('ErpPurchaseOrder')->where(['from_sale_order_id'=>$param['from_sale_order_id'],'from_sale_order_number'=>$param['from_sale_order_number'],'order_status'=>['neq',2]])->find();
            if ($exist) {
                return $result = [
                    'status' => 18,
                    'message' => '一笔代采销售单只能生成一张对应的代采采购单'
                ];
            }

            if (getCacheLock('ErpPurchase/addActingPurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];

            setCacheLock('ErpPurchase/addActingPurchaseOrder', 1);

            M()->startTrans();
            $data = $param;
            $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['from_sale_order_id']]);

            $data['price'] = setNum($param['price']);
            if ($param['pay_type'] == 2 || $param['pay_type'] == 5) {
                $data['prepay_ratio'] = setNum($param['prepay_ratio']);
            }

            $data['add_order_time'] = date('Y-m-d H:i:s', strtotime('+1 days', strtotime($param['add_order_time']) - 1));
            $data['price'] = setNum($param['price']);
            $data['goods_num'] = setNum($param['goods_num']);
            $data['no_payed_money'] = setNum(round($param['goods_num'] * $param['price'], 2));
            $data['order_amount'] = setNum(round($param['goods_num'] * $param['price'], 2));
            $data['order_number'] = erpCodeNumber(5)['order_number'];
            $data['create_time'] = currentTime();
            $data['update_time'] = currentTime();
            $data['creater'] = $this->getUserInfo('id');
            # 代采采购单 业务类型取原销售单业务类型 qianbin 2018.07.20
            $data['business_type']  = $sale_order_info['business_type'] ;
            unset($data['goods_name']);
            unset($data['goods_code']);
            unset($data['goods_from']);
            unset($data['goods_grade']);
            unset($data['goods_level']);
            $status = $id = $this->getModel('ErpPurchaseOrder')->addPurchaseOrder($data);


            $sale_order_data = [
                'acting_purchase_num' => $data['goods_num'] + $sale_order_info['acting_purchase_num'],
                'update_time' => currentTime() //更新修改销售单时间
            ];
            $status_sale = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $param['from_sale_order_id']],$sale_order_data);

            $log_data = [
                'purchase_id' => $id,
                'purchase_order_number' => $data['order_number'],
                'log_type' => 1,
                'log_info' => serialize($data),
                'create_time' => currentTime(),
                'operator' => $this->getUserInfo('dealer_name'),
                'operator_id' => $this->getUserInfo('id'),
            ];
            $log_status = $this->getModel('ErpPurchaseLog')->add($log_data);

            if ($status && $log_status && $status_sale) {
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

            cancelCacheLock('ErpPurchase/addActingPurchaseOrder');
        }
        return $result;
    }

    /**
     * 采购计划
     * @author senpai
     * @time 2017-05-08
     */
    public function purchasePlanList($param)
    {
        $where = [];
        if (trim($param['region'])) {
            $where['s.region'] = $param['region'];
        }
        if (trim($param['storehouse_id'])) {
            $where['s.object_id'] = $param['storehouse_id'];
        }

        if (trim($param['goods_code'])) {
            $where['s.goods_id'] = $param['goods_code'];
        }

        //采购计划只显示城市仓
        $where['stock_type'] = 1;

        //显示缺货仓库
        $where['available_num'] = ['lt', 0];

        $where['s.our_company_id'] = session('erp_company_id');

        $field = 's.*,es.storehouse_name,g.goods_name,g.goods_code,g.source_from,g.level,g.grade';
        $data = $this->getModel('ErpStock')->getStockList($where, $field, $param['start'], $param['length']);

        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['region_font'] = $cityArr[$value['region']];
                $data['data'][$key]['stock_num'] = getNum(round($value['stock_num']));
                $data['data'][$key]['transportation_num'] = getNum(round($value['transportation_num']));
                $data['data'][$key]['sale_reserve_num'] = getNum(round($value['sale_reserve_num']));
                $data['data'][$key]['allocation_reserve_num'] = getNum(round($value['allocation_reserve_num']));
                $data['data'][$key]['sale_wait_num'] = getNum(round($value['sale_wait_num']));
                $data['data'][$key]['allocation_wait_num'] = getNum(round($value['allocation_wait_num']));
                $data['data'][$key]['available_num'] = abs(getNum(round($value['available_num'])));
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 采购计划详情
     * @author senpai
     * @time 2017-05-08
     */
    public function purchasePlanDetail($param)
    {
        $where = [];

        $where['s.id'] = $param['id'];

        $field = 's.*,es.storehouse_name,g.goods_name,g.goods_code,g.source_from,g.level,g.grade';
        $data = $this->getModel('ErpStock')->getStockList($where, $field, $param['start'], $param['length']);

        $stockout_where = [
            'o.storehouse_id' => $data['data'][0]['object_id'],
            'o.goods_id' => $data['data'][0]['goods_id'],
            'o.our_company_id' => session('erp_company_id'),
            'so.outbound_status' => 1,
            'so.create_time' => ['between', [date('Y-m-d'), date('Y-m-d', strtotime('+1day'))]],
        ];
        $field = 'sum(actual_outbound_num) as total';
        $stockout_data = $this->getModel('ErpStockOut')->getStockOutPlanList($stockout_where, $field);
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                $data['data'][$key]['region_font'] = $cityArr[$value['region']];
                $data['data'][$key]['stock_num'] = getNum(round($value['stock_num']));
                $data['data'][$key]['transportation_num'] = getNum(round($value['transportation_num']));
                $data['data'][$key]['sale_reserve_num'] = getNum(round($value['sale_reserve_num']));
                $data['data'][$key]['allocation_reserve_num'] = getNum(round($value['allocation_reserve_num']));
                $data['data'][$key]['sale_wait_num'] = getNum(round($value['sale_wait_num']));
                $data['data'][$key]['allocation_wait_num'] = getNum(round($value['allocation_wait_num']));
                $data['data'][$key]['available_num'] = getNum(round($value['available_num']));
                $data['data'][$key]['plan_num'] = getNum(round($stockout_data['total']));
                $data['data'][$key]['min_num'] = getNum($value['stock_num'] + $value['transportation_num'] - $stockout_data['total'] - $value['allocation_reserve_num']) > 0 ? 0 : abs(getNum(round($value['stock_num'] + $value['transportation_num'] - $stockout_data['total'] - $value['allocation_reserve_num'])));
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 生成采购单审批步骤
     * @param array $purchase_order_info
     * @author xiaowen
     * @time 2017-05-09
     * @return bool $check_status
     */
    public function createWorkflow($purchase_order_info = []){
        //$status = false;
        $data['status'] = false; //审批流程创建状态
        $data['message'] = '';
        if($purchase_order_info){
            $workflow_data = [
                'workflow_type' => 2,
                'workflow_order_number' => $purchase_order_info['order_number'],
                'workflow_order_id' => $purchase_order_info['id'],
                'our_company_id' => $purchase_order_info['our_buy_company_id'],
                'creater' => $purchase_order_info['buyer_dealer_name'],
                'creater_id' => $purchase_order_info['buyer_dealer_id'],
            ];
            $workflow_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            $workflow_step = purchaseWorkflowStepPosition();
            # ---------新的采购单审批需求 - 所有采购订单（不区分自营代采）---- < qianbin - 2017.08.11 >
            # 1)  | -----所有采购订单（不区分自营代采）采购员创建->城市地区采购负责人->分公司经理审批->采销中心总经理（不区分商品，第一次采购的供应商需由大伟审批）
            # 2)  | -----所有采购订单（不区分自营代采）采购员创建->城市地区采购负责人->分公司经理审批->采销中心总经理（按照省份判断，不区分商品，如果当日的采购单量大于2000吨）
            # 3)  | -----所有采购订单（不区分自营代采）采购员创建->城市地区采购负责人->分公司经理审批（当日每个城市的采购量（不区分商品，有效订单）≤800吨）
            # 4)  | -----所有采购订单（不区分自营代采）采购员创建->城市地区采购负责人->分公司经理审批->采销中心总经理（当日每个城市的采购量（不区分商品，有效订单）＞ 800吨）

            # $workflow_step   = ['PurchaseManager','AreaManager'];
            # 计算供应商是否是第一次提供商品
            $where_purchase_order = [
                'sale_company_id'        => intval($purchase_order_info['sale_company_id']),
                'order_status'           => ['neq',2],                                              # 未取消
                'order_number'           => ['neq',$purchase_order_info['order_number']]
            ];
            $sale_company_count   = $this->getModel('ErpPurchaseOrder')->where($where_purchase_order)->count();
            if(intval($sale_company_count) == 0){
                array_push($workflow_step,'MiningSalesManager');
                log_info('-------------------->审批流：第一次购买的供应商，最后由郭大伟审批！');
            }else{
                # 计算该省当日的采购量
                $region     = D('Area')->where(['id' => intval($purchase_order_info['region'])])->getField('parent_id');
                $province   = getProvinceCityId($region);
                if(empty($province)) {
                    $data['status']  = false; //审批流程创建状态
                    $data['message'] = '未查询所在订单城市的省份，请联系管理员！';
                    return $data;
                }
                $where_purchase_order = [
                    'our_buy_company_id'     => intval($purchase_order_info['our_buy_company_id']),     # 账套公司
                    # 'goods_id'               => intval($purchase_order_info['goods_id']) ,              # 商品id
                    'region'                 => ['IN',$province],                                       # 城市id
                    'order_status'           => ['neq',2],                                              # 未取消
                    'create_time'            => ['between', [date('Y-m-d 00:00:00',time()), date('Y-m-d 23:59:59',time())]]
                ];
                $purchase_order_sum  = $this->getModel('ErpPurchaseOrder')->where($where_purchase_order)->getField('sum(goods_num) as  goods_sum');
                $purchase_order_sum  = empty($purchase_order_sum) ? 0 : ($purchase_order_sum < 0 ? '-'.abs(getNum($purchase_order_sum)) : getNum($purchase_order_sum));
                log_info('该省当天的采购量：'.$purchase_order_sum);
                if($purchase_order_sum > 2000){
                    array_push($workflow_step,'MiningSalesManager');
                    log_info('-------------------->审批流：该城市id -> '.intval($purchase_order_info['region']).' ,对应的省份id -> '.$region.' ,当天采购量大于2000吨，最后由郭大伟审批！');
                }else{
                    /* # 计算当前可用库存
                    $where_stock = [
                        'goods_id'              => intval($purchase_order_info['goods_id']),
                        'object_id'             => intval($purchase_order_info['storehouse_id']),
                        'stock_type'            => intval($purchase_order_info['type']),
                        'our_company_id'        => intval($purchase_order_info['our_buy_company_id'])
                    ];
                    $available_num = $this->getModel('ErpStock')->where($where_stock)->getField('available_num');
                    */
                    # 计算当天采购当前商品的量
                    $where_purchase_order = [
                        'our_buy_company_id'     => intval($purchase_order_info['our_buy_company_id']),     # 账套公司
                        # 'goods_id'               => intval($purchase_order_info['goods_id']) ,            # 商品id
                        # 'type'                   => intval($purchase_order_info['type']),                 # 采购类型：1 自采 ， 2 待采
                        # 'storehouse_id'          => intval($purchase_order_info['storehouse_id']) ,       # 仓库id
                        'region'                 => intval($purchase_order_info['region']),                 # 城市id
                        'order_status'           => ['neq',2],                                              # 未取消
                        'create_time'            => ['between', [date('Y-m-d 00:00:00',time()), date('Y-m-d 23:59:59',time())]]
                    ];
                    $purchase_order_sum = $this->getModel('ErpPurchaseOrder')->where($where_purchase_order)->getField('sum(goods_num) as  goods_sum');
                    # 数量 / 10000
                    # $available_num       = empty($available_num)      ? 0 : ($available_num      < 0 ? '-'.abs(getNum($available_num))      : getNum($available_num));
                    $purchase_order_sum  = empty($purchase_order_sum) ? 0 : ($purchase_order_sum < 0 ? '-'.abs(getNum($purchase_order_sum)) : getNum($purchase_order_sum));
                    # log_info('-------------------->审批流：当前可用库存 -- '.$available_num);
                    log_info('-------------------->审批流：计算该城市当天采购量 ： '.$purchase_order_sum);
                    if($purchase_order_sum <= 800 ){
                        log_info('-------------------->审批流：该城市当日的采购量（有效订单）≤800吨，不需要郭大伟审批');
                        # 不做处理
                    }else{
                        # 添加采销中心总经理
                        array_push($workflow_step,'MiningSalesManager');
                        log_info('-------------------->审批流：该城市当日的采购量（有效订单）> 800吨，需要郭大伟审批');
                    }
                }

            }
            log_info('审批人--->'.var_export($workflow_step,true));
			//$workflow_step = purchaseWorkflowStepPosition($purchase_order_info['type']);
			//$step_status = $this->getEvent('ErpWorkflow')->createWorkflowStepData($workflow_id, $workflow_step, $purchase_order_info);
            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $purchase_order_info, 2);
            $data['status']   = $step_status_data['status'] == 1 && $workflow_status ? true : false;
            $data['message']  = $step_status_data['message'];
            //$status = $workflow_status && $data['status'] ? true :false;
        }

        return $data;
    }

    /**
     * 返回ErpWorkflowEvent实例
     * @author xiaowen
     * @return \Controller|false|Controller
     */
    public function getWorkflowEvent(){
        return A('ErpWorkFlow', 'Event');
    }

    /**
     * 采购单回滚
     * @author senpei
     * @time 2017-06-20
     */
    public function rollBackPurchaseOrder($id)
    {
        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 2,
                'message' => '参数有误，无法获取采购单ID',
            ];
            return $result;
        }

        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $id]);
        if (empty($purchase_order_info)) {
            $result = [
                'status' => 3,
                'message' => '该采购单不存在，请稍后操作',
            ];
            return $result;
        }

        if ($purchase_order_info['invoice_status'] != 1) {
            $result = [
                'status' => 4,
                'message' => '该采购单已开发票，请财务处理后再进行作废',
            ];
            return $result;
        }

        if ($purchase_order_info['order_status'] == 1) {
            $result = [
                'status' => 5,
                'message' => '未审核状态下请自行手动取消',
            ];
            return $result;
        }

        if ($purchase_order_info['order_status'] == 2) {
            $result = [
                'status' => 5,
                'message' => '该采购单已取消，无法作废',
            ];
            return $result;
        }

        if ($purchase_order_info['is_void'] == 1) {
            $result = [
                'status' => 5,
                'message' => '该采购单已作废，不能重复作废',
            ];
            return $result;
        }

        if ($purchase_order_info['is_returned'] == 1) {
            $result = [
                'status' => 6,
                'message' => '该采购单已退货，不能作废',
            ];
            return $result;
        }

        if (getCacheLock('ErpPurchase/rollBackPurchaseOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpPurchase/rollBackPurchaseOrder', 1);

        M()->startTrans();

        /** 采购单已转在途 */
        if (((in_array($purchase_order_info['pay_type'],[1,4]) && $purchase_order_info['pay_status'] == 10)
                || ($purchase_order_info['pay_type'] == 2 && $purchase_order_info['pay_status'] > 2)
                || ($purchase_order_info['pay_type'] == 3 && $purchase_order_info['order_status'] == 10)
                || ($purchase_order_info['pay_type'] == 5 && $purchase_order_info['pay_status'] > 3))
            && $purchase_order_info['order_status'] != 2
        ) {
            /** ----------------------回滚对应的采购单相应的库存start------------------------------------------------------------ */
            $stock_where = [
                'goods_id' => $purchase_order_info['goods_id'],
                'object_id' => $purchase_order_info['storehouse_id'],
                'stock_type' => $purchase_order_info['type'] == 1 ? getAllocationStockType($purchase_order_info['storehouse_id']) : 2,
                'our_company_id' => $purchase_order_info['our_company_id'],
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

            $data = [
                'goods_id' => $purchase_order_info['goods_id'],
                'object_id' => $purchase_order_info['storehouse_id'],
                'stock_type' => $purchase_order_info['type'] == 1 ? getAllocationStockType($purchase_order_info['storehouse_id']) : 2,
                'region' => $purchase_order_info['region'],
            ];

            /** 若已入库则回滚物理并减少在途，若没有入库则减少在途 */
            if ($purchase_order_info['storage_quantity'] > 0) {

                $data['stock_num'] = $stock_info['stock_num'] - $purchase_order_info['storage_quantity'];

                //若当前物理库存不足则不允许回滚，提示先补全后再回滚
                if ($data['stock_num'] < 0) {
                    $result = [
                        'status' => 7,
                        'message' => '当前物理库存不足，请补仓后再进行作废',
                    ];
                    cancelCacheLock('ErpPurchase/rollBackPurchaseOrder');
                    return $result;
                }

                //定金锁价逻辑不同，在途减少数量用已转在途数量计算
                if ($purchase_order_info['pay_type'] == 5){
                    $data['transportation_num'] = $stock_info['transportation_num'] - ($purchase_order_info['total_purchase_wait_num'] - $purchase_order_info['storage_quantity']);
                } else {
                    $data['transportation_num'] = $stock_info['transportation_num'] - ($purchase_order_info['goods_num'] - $purchase_order_info['storage_quantity']);
                }

                $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
                $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的采购在途
            } else {
                //定金锁价逻辑不同，在途减少数量用已转在途数量计算
                if ($purchase_order_info['pay_type'] == 5) {
                    $data['transportation_num'] = $stock_info['transportation_num'] - $purchase_order_info['total_purchase_wait_num'];
                } else {
                    $data['transportation_num'] = $stock_info['transportation_num'] - $purchase_order_info['goods_num'];
                }

                $stock_info['transportation_num'] = $data['sale_wait_num']; //重置最新的销售待提
            }

            if ($purchase_order_info['pay_type'] == 5){
                $change_num = $purchase_order_info['total_purchase_wait_num'];
            } else {
                $change_num = $purchase_order_info['goods_num'];
            }

            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            $orders = [
                'object_number' => $purchase_order_info['order_number'],
                'object_type' => 2,
                'log_type' => 9,
                'our_company_id' => $purchase_order_info['our_company_id'],
            ];
            $purchase_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $change_num, $orders);
            /** ----------------------回滚对应的代采采购单相应的库存end------------------------------------------------------------ */
        } else {
            $purchase_stock_status = true;
        }

        //若为代采，则将采购需求回滚
        if ($purchase_order_info['type'] == 2) {
            $sale_order_data = [
                'acting_purchase_num' => 0,
                'updater' => $this->getUserInfo('id'),
                'update_time' => DateTime()
            ];
            $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($purchase_order_info['from_sale_order_id'])], $sale_order_data);
        } else {
            $sale_order_status = true;
        }

        //查看是否有对应的入库单
        $stock_in_info = $this->getModel('ErpStockIn')->where(['source_number'=>$purchase_order_info['order_number'],'source_object_id'=>$purchase_order_info['id']])->find();
        if ($stock_in_info) {
            //将对应的入库单取消
            $stock_in_data = [
                'storage_status' => 2,
                'update_time' => currentTime(),
            ];
            $stock_in_where = [
                'source_number'=>$purchase_order_info['order_number'],
                'source_object_id'=>$purchase_order_info['id'],
                'storage_type'=>1,
            ];
            $stock_in_status = $this->getModel('ErpStockIn')->saveStockIn($stock_in_where, $stock_in_data);
        } else {
            $stock_in_status = true;
        }

        //修改采购单状态
        $purchase_order_data = [
            'order_status' => 2,
            'pay_status' => 1,
            'payed_money' => 0,
            'no_payed_money' => $purchase_order_info['order_amount'],
            'storage_quantity' => 0,
            'is_void' => 1,
            'updater' => $this->getUserInfo('id'),
            'update_time' => DateTime()
        ];
        $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($purchase_order_info['id'])], $purchase_order_data);

        //如果有未审核的付款申请，则将对应申请驳回
        $purchase_payment_info = $this->getModel('ErpPurchasePayment')->where(['purchase_id'=>$purchase_order_info['id'],'purchase_order_number'=>$purchase_order_info['order_number'],'status'=>['in',[1,3]]])->find();
        if ($purchase_payment_info) {
            $purchase_payment_data = [
                'status' => 2,
                'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => currentTime()
            ];
            $payment_status = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['purchase_id'=>$purchase_order_info['id'],'purchase_order_number'=>$purchase_order_info['order_number'],'status'=>['in',[1,3]]], $purchase_payment_data);
        } else {
            $payment_status = true;
        }

        //如果有付款，则录一条负付款冲平
        if ($purchase_order_info['pay_status'] > 1) {
            $erp_payment_data = [
                'pay_money' => $purchase_order_info['payed_money'] * -1,
                'purchase_id' => $purchase_order_info['id'],
                'purchase_order_number' => $purchase_order_info['order_number'],
                'create_time' => DateTime(),
                'creator' => $this->getUserInfo('dealer_name'),
                'creator_id' => $this->getUserInfo('id'),
                'apply_pay_time' => date('Y-m-d', time()) . ' 23:59:59',
                'pay_time' => DateTime(),
                'our_company_id' => session('erp_company_id'),
                'auditor' => $this->getUserInfo('dealer_name'),
                'auditor_id' => $this->getUserInfo('id'),
                'audit_time' => DateTime(),
                'status' => 10,
                'sale_company_id' => $purchase_order_info['sale_company_id'],
                'dealer_id' => $purchase_order_info['buyer_dealer_name'],
                'from_purchase_order_number' => $purchase_order_info['order_number'],
            ];
            $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);
        } else {
            $status_payment = true;
        }

        //将采购单审批流置为无效
        $purchase_workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$purchase_order_info['id'], 'workflow_type'=>2, 'status'=>['neq', 2]])->order('id desc')->find();
        if ($purchase_workflow && $purchase_workflow['status'] == 1) {
            $purchase_workflow['status'] = 2;
            $purchase_work_status = $this->getModel('ErpWorkflow')->where(['id'=>$purchase_workflow['id']])->save($purchase_workflow);
        } else {
            $purchase_work_status = true;
        }

        //保存操作到采购单日志
        $purchase_log_data = [
            'purchase_id' => $purchase_order_info['id'],
            'purchase_order_number' => $purchase_order_info['order_number'],
            'log_type' => 15,
            'log_info' => serialize($purchase_order_info),
            'create_time' => currentTime(),
            'operator' => $this->getUserInfo('dealer_name'),
            'operator_id' => $this->getUserInfo('id'),
        ];
        $purchase_log_status = $this->getModel('ErpPurchaseLog')->add($purchase_log_data);


        if ($stock_in_status && $purchase_stock_status && $purchase_order_status && $status_payment
            && $payment_status && $purchase_work_status && $purchase_log_status && $sale_order_status) {
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
        cancelCacheLock('ErpPurchase/rollBackPurchaseOrder');
        return $result;

    }

    /**
     * 验证公司是否在黑名单中
     * @param $company_id 公司ID
     * @author xiaowen
     * @time 2017-7-18
     * @return bool
     */
    protected function isInBackList($company_id){
        $backList = array_keys(getBackListCompany());
        log_info('打印数据'.var_export($backList, true));
        return in_array(intval($company_id), $backList) ? true : false;
    }

    /**
     * 获取二次定价操作日志
     * @author xiaowen
     * @time 2017-7-17
     * @param int $id
     */
    public function getUpdatePriceLogList($id = 0){

        $data['data'] = [];
        if($id){
            $field = '*';
            $data['data'] = $this->getModel('ErpUpdatePriceLog')->getUpdatePriceLogList(['order_id' => intval($id), 'order_type'=>2], $field, $_REQUEST['start'], $_REQUEST['length']);
            if($data['data']){
                foreach($data['data']  as $key=>$value){
                    $data['data'][$key]['old_price'] = getNum($value['old_price']);
                    $data['data'][$key]['new_price'] = getNum($value['new_price']);
                }
            }

        }
        $data['recordsFiltered'] = $data['recordsTotal'] = count($data['data']);
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 二次定价
     * @author xiaowen
     * @param $param
     * @time 2017-7-19
     * @return array $data
     */
    public function updatePrice($param = []){

        $data = [];

        if($param['id'] && $param['price']){
            $purchase_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id'=>intval($param['id'])]);
            $pay_status_check  = $purchase_order_info['is_update_price'] == 2 ? 10 : 4;
            log_info('参数'. var_export($param, true));
            if($purchase_order_info['price'] == $param['price']){
                $data['status'] = 2;
                $data['message'] = '更新价格不能与原价格一致';
            } else if(trim($param['remark']) == ''){
                $data['status'] = 3;
                $data['message'] = '请输入二次定价备注信息';
            } else if($purchase_order_info['order_status'] != 10){
                $data['status'] = 4;
                $data['message'] = '订单不是已确认,无法修改价格';
            }else if($purchase_order_info['invoice_status'] != 1){
                $data['status'] = 5;
                $data['message'] = '订单不是未收票,无法修改价格';
            }
            else if($purchase_order_info['pay_status'] == 10 && $purchase_order_info['is_update_price'] == 1){
                $data['status'] = 6;
                $data['message'] = '二次定价已处理,无法再修改价格';
            }
            else if($purchase_order_info['pay_status'] != $pay_status_check){
                log_info('订单付款状态：' .$purchase_order_info['pay_status']);
                log_info('检查状态：' .$pay_status_check);
                $arr = purchasePayStatus();
                log_info('付款状态：' .var_export($arr, true));
                $data['status'] = 7;
                $data['message'] = '订单不是'.$arr[$pay_status_check].',无法修改价格';

            }else if($purchase_order_info['is_returned'] == 1){
                $data['status'] = 9;
                $data['message'] = "已生成退货单，无法二次定价";
            } else{
                //验证是否有已申请的付款记录 edit xiaowen 2017-7-25
                $payment_count = $this->getModel('ErpPurchasePayment')->where(['purchase_order_number'=>$purchase_order_info['order_number'], 'status'=>1])->find();
                if(!empty($payment_count)){
                    $data['status'] = 7;
                    $data['message'] = '对不起，该订单存在已申请的付款记录，请联系财务进行驳回';
                    return $data;
                }
                //验证是否有已申请的开发发票记录 edit xiaowen 2017-7-25
                $invoice_count = $this->getModel('ErpPurchaseInvoice')->where(['purchase_order_number'=>$purchase_order_info['order_number'], 'status'=>1])->find();
                if(!empty($invoice_count)){
                    $data['status'] = 8;
                    $data['message'] = '对不起，该订单存在已申请的发票记录，请联系财务进行驳回';
                    return $data;
                }
                if (getCacheLock('ErpPurchase/updatePrice')) return ['status' => 99, 'message' => $this->running_msg];
                setCacheLock('ErpPurchase/updatePrice', 1);

                M()->startTrans();
                //-------------------------------更新订单信息-------------------------------------------------

                //查询该订单的二次定价修改日志，判断是否需要根据订单的原始单价
                $update_price_log = $this->getModel('ErpUpdatePriceLog')->getUpdatePriceLogList(['order_id' => intval($param['id']), 'order_type'=>2]);
                $order_data = [
                    'update_time' => currentTime(),
                    'is_update_price' => 1,
                    'pay_status' => 4,
                    'price' => setNum(trim($param['price'])),
                    'order_amount' => setNum(round(getNum($purchase_order_info['goods_num']) * $param['price'], 2)) + $data['delivery_money'],

                ];
                $order_data['no_payed_money'] = $order_data['order_amount'] - $purchase_order_info['payed_money'];
                //如果之前没有修改过价格，则要保存订单的原始价格。否则不用保存
                if(empty($update_price_log)){
                    $order_data['original_price'] = $purchase_order_info['price'];
                }
                //如果订单当前是二次定价状态，修改的价格与原始价格相同，则要取消二次定价标识
                if($purchase_order_info['is_update_price'] == 1 && setNum(trim($param['price'])) == $purchase_order_info['original_price']){
                    $order_data['is_update_price'] = 2;

                }
                //如果已收金额 等于 当前的订单金额 则重置订单收款状态为已收款
                if($purchase_order_info['payed_money'] == $order_data['order_amount']){
                    $order_data['pay_status'] = 10;
                }
                $order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id'=>intval($param['id'])], $order_data);
                //-------------------------------end 更新订单信息----------------------------------------------
                //--------------------------------保存变价操作日志---------------------------------------------
                $update_price_log_data = [
                    'order_type' => '2',
                    'order_id' => $purchase_order_info['id'],
                    'order_number' => $purchase_order_info['order_number'],
                    'old_price' => $purchase_order_info['price'],
                    'new_price' => setNum(trim($param['price'])),
                    'remark' => trim($param['remark']),
                    'operator' => $this->getUserInfo('dealer_name'),
                    'operator_id' => $this->getUserInfo('id'),
                    'create_time' => currentTime(),
                ];

                $update_price_log_status = $this->getModel('ErpUpdatePriceLog')->addErpUpdatePriceLog($update_price_log_data);
                //----------------------------------end 保存变价操作日志------------------------------------------
                if($update_price_log_status && $order_status){
                    M()->commit();
                    $data['status'] = 1;
                    $data['message'] = '操作成功';
                }else{
                    M()->rollback();
                    $data['status'] = 0;
                    $data['message'] = '操作失败';
                }

                cancelCacheLock('ErpPurchase/updatePrice');
            }

        }

        return $data;
    }

    /**
     * 检验采购单是否可退货
     * @param $id
     * @author xiaowen
     * @time 2017-8-24
     * @return array
     */
    public function checkOrderCanReturn($id){
        $purchase_order = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id'=>intval($id)]);
        if ( $purchase_order['business_type'] == 6 ) {
            return ['status' => 9,'message' => '此采购单 属于内部交易单，不允许采购退货！'];
        }
        if($purchase_order['order_status'] != 10 || $purchase_order['pay_status'] != 10 || $purchase_order['pay_status'] != 10){
            return $result = ['status' => 2, 'message' => '订单必须同时满足已确认、已收款、未收票，才能进行退货操作'];
        }else if($purchase_order['is_returned'] == 1){
            return $result = ['status' => 3, 'message' => '一笔采购单只能生成一张对应的采购退货单'];
        }else if($purchase_order['storage_quantity'] == 0){
            return $result = ['status' => 4, 'message' => '该采购单未入库，请通过作废处理'];
        }else if($purchase_order['type'] == 2){
            return $result = ['status' => 5, 'message' => '代采采购单请通过代采销售单进行退货操作'];
        }
        //未审核的发票存在不能创建退货单
        $purchase_payment = $this->getModel('ErpPurchasePayment')->where(['purchase_id'=>intval($id), 'source_order_type'=>1,'status'=>1])->count();
        if($purchase_payment){
            return $result = ['status' => 6, 'message' => '该订单存在未确认付款申请，请先联系财务驳回后再退货'];
        }
        //查询采购单是否有未审核的入库单
        $stock_in_count = $this->getModel('ErpStockIn')->where(['source_object_id'=>intval($id), 'storage_type'=>1, 'storage_status'=>1])->count();
        if($stock_in_count){
            return $result = ['status' => 7, 'message' => '该订单存在未审核入库单，无法退货'];
        }
        //未审核的发票存在不能创建退货单
        $purchase_invoice = $this->getModel('ErpPurchaseInvoice')->where(['purchase_id'=>intval($id), 'status'=>1])->count();
        if($purchase_invoice){
            return $result = ['status' => 8, 'message' => '该订单存在未审核发票，无法退货'];
        }
        return $result = ['status' => 1, 'message' => '该订单可退货'];
    }

    /**
     * 验证采购单单是否使用余额抵扣
     * @param $order_info
     * @author guanyu
     * @time 2017-11-21
     * @return bool
     */
    public function checkBalanceDeduction($id)
    {
        //销售单信息
        $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id'=>$id]);

        if ($order_info['pay_status'] == 1) {
            //订单未收款
            $result = [
                'status' => 1,
                'message' => '该采购单无余额抵扣'
            ];
        } else {
            //查询订单所有收款信息中利用余额抵扣的数据
            $where = [
                'purchase_id' => $id,
                'purchase_order_number' => $order_info['order_number'],
                'balance_deduction' => ['neq',0]
            ];
            $purchase_payment = $this->getModel('ErpPurchasePayment')->findPurchasePayment($where);
            if ($purchase_payment) {
                $result = [
                    'status' => 2,
                    'message' => '该采购单有余额抵扣,请在作废后重新录单充值账户'
                ];
            } else {
                $result = [
                    'status' => 1,
                    'message' => '该采购单无余额抵扣'
                ];
            }
        }
        return $result;
    }

    /**
     * 生成采购单审批步骤(2018.7.2改造)
     * @param array $purchase_order_info
     * @author xiaowen
     * @time 2018-07-02
     * @return bool $check_status
     */
    public function createWorkflowNew($purchase_order_info = []){
        $data['status'] = false; //审批流程创建状态
        $data['check_order'] = 0; //是否复核订单状态
        $data['message'] = '';
        if($purchase_order_info){
            //采购单类型为属地，走营销中心销售单审批
            if($purchase_order_info['business_type'] == 1){
                //数量小于1000 走二级审批
                if($purchase_order_info['goods_num'] < setNum(1000)){
                    $workflow_step = dependencyPurchaseSaleWorkflow(1);
                }else{  //其他情况 走三级审批
                    $workflow_step = dependencyPurchaseSaleWorkflow(2);
                }
            }else if(in_array($purchase_order_info['business_type'],[2,3,4])){   //采购单大宗/炼厂
                //采购退货类型为大宗地炼，走供应链销售单审批，加油站审批
                $type = $purchase_order_info['business_type'] == 4 ? 2 : 1;
                $workflow_step = gylPurchaseWorkflow($type);
            }else if($purchase_order_info['business_type'] == 5){   //采购单小十代
                $workflow_step = LabECOPurchaseSaleWorkflow(1);
            }else if($purchase_order_info['business_type'] == 7){   //IDS采购采购单审批流
                $workflow_step = IdsWorkflow(1);
            }
            $workflow_order_type = 2;
            $workflow_data = [
                'workflow_type' => $workflow_order_type,
                'workflow_order_number' => $purchase_order_info['order_number'],
                'workflow_order_id' => $purchase_order_info['id'],
                'our_company_id' => $purchase_order_info['our_buy_company_id'],
                'creater' => $purchase_order_info['buyer_dealer_name'],
                'creater_id' => $purchase_order_info['buyer_dealer_id'],
            ];
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $purchase_order_info, $workflow_order_type);

            $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];

        }
        return $data;
    }
    /*
     * @params:
     *  where:查询条件
     *  field:查询字段
     * @return:
     * @desc:根据查询相关的数据信息
     * @author:小黑
     * @time:2019-2-25
     */
    public function getPurchase($field ,$where){
        return $this->getModel('ErpPurchaseOrder')->where($where)->field($field)->find();
    }

}
