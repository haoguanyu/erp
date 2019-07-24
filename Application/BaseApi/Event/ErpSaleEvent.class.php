<?php
/**
 * 销售单业务处理层
 * @author xiaowen
 * @time 2017-04-17
 */
namespace BaseApi\Event;

use Think\Controller;
use BaseApi\Controller\BaseController;

class ErpSaleEvent extends BaseController
{

    /**
     * 销售单列表
     * @author xiaowen 2017-03-16
     * @param $param
     */
    public function saleOrderList($param = [])
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
        if(trim($param['from_order_number'])) {
            $where['o.from_order_number'] = trim($param['from_order_number']);
        }
        if (intval(trim($param['goods_id']))) {
            $where['o.goods_id'] = intval(trim($param['goods_id']));
        }
        if (trim($param['type'])) {
            $where['o.order_type'] = trim($param['type']);
        }
        if (trim($param['pay_type'])) {
            $where['o.pay_type'] = intval(trim($param['pay_type']));
        }
        if (trim($param['collection_status'])) {
            $where['o.collection_status'] = intval(trim($param['collection_status']));
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
            $where['o.dealer_name'] = ['like', '%' . trim($param['dealer_name']) . '%'];
        }
        if (trim($param['sale_user'])) {
            $user_ids = D("ErpCustomerUser")->where(['user_name'=>['like', '%'.trim($param['sale_user']).'%'], 'status'=>1])->getField('id', true);
            if ($user_ids) {
                $where['o.user_id'] = ['in', $user_ids];
            } else {
                $data['recordsFiltered'] = $data['recordsTotal'] = 0;
                $data['data'] = [];
                $data['draw'] = $_REQUEST['draw'];
                return $data;
            }

        }
        if (trim($param['sale_company_id'])) {
            $where['o.company_id'] = intval(trim($param['sale_company_id']));
        }

        if ($param['status']) {
            $where['o.order_status'] = intval($param['status']);
        }

        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }

        if (trim($param['is_void'])) {
            $where['o.is_void'] = trim($param['is_void']);
        }

        if (trim($param['is_returned'])) {
            $where['o.is_returned'] = trim($param['is_returned']);
        }
        if(trim($param['delivery_method'])){
            $where['o.delivery_method'] = intval($param['delivery_method']);
        }
        //我的销售单
        if (trim($param['dealer_id'])) {
            $where['o.dealer_id'] = trim($param['dealer_id']);
        }
        if (trim($param['is_update_price'])) {
            $where['o.is_update_price'] = intval(trim($param['is_update_price']));
        }
        if(trim($param['is_agent'])){
            $where['o.is_agent'] = intval(trim($param['is_agent']));
        }
        if(trim($param['business_type'])){
            $where['o.business_type'] = intval($param['business_type']);
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        //查询批发销售单
        if($where['o.order_type'] == 1){
            $field = 'o.*,d.depot_name,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,
            g.level,es.storehouse_name,
            r.return_payed_amount';
            if ($param['export']) {
                $data = $this->getModel('ErpSaleOrder')->getAllSaleOrderList($where, $field);
            } else {
                $data = $this->getModel('ErpSaleOrder')->getSaleOrderList($where, $field, $param['start'], $param['length']);
            }
        }else if($where['o.order_type'] == 2){ //查询零售销售单

            if (trim($param['facilitator_id'])) {
                $where['o.facilitator_id'] = intval(trim($param['facilitator_id']));
            }
            if (trim($param['facilitator_skid'])) {
                $where['o.storehouse_id'] = intval(trim($param['facilitator_skid']));
            }
            $field = 'o.*,d.depot_name,ec.company_name b_company_name,es.storehouse_name';
            if ($param['export']) {
                $data = $this->getModel('ErpSaleOrder')->getAllSaleRetailOrderList($where, $field);
            } else {
                $data = $this->getModel('ErpSaleOrder')->getSaleRetailOrderList($where, $field, $param['start'], $param['length']);
            }
        }

        $userIdArr = array_column($data['data'], 'user_id');
        $companyIdArr = array_column($data['data'], 'company_id');
        $facilitatorIdArr = array_column($data['data'], 'facilitator_id');
        $userInfo = $this->getEvent('ErpCommon')->getUserData($userIdArr, 1);
        $companyInfo = $this->getEvent('ErpCommon')->getCompanyData($companyIdArr, 1);
        $facilitatorInfo = $this->getEvent('ErpCommon')->getCompanyData($facilitatorIdArr, 2);

        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];
            # 匹配发票号====================
            # qianbin
            # 2017.07.20
            $sale_order_number  = array_column($data['data'],'order_number');
            $invoice_data       = $this->getModel('ErpSaleInvoice')->where(['sale_order_number' => ['in',$sale_order_number]])->getField('id,sale_order_number,invoice_sn');
            $sale_order_invoice = [];
            foreach ($invoice_data as $key=>$value){
                if(trim($value['invoice_sn']) != ''){
                    $sale_order_invoice[$value['sale_order_number']][] = $value['invoice_sn'];
                }

            }
            # end==========================
            $creater_arr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', array_unique(array_column($data['data'],'creater'))]])->getField('id,dealer_name');
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['No'] = $i;
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['add_order_time'] = date('Y-m-d', strtotime($value['add_order_time']));
                $data['data'][$key]['collection_status'] = saleCollectionStatus($value['collection_status'], true);
                $data['data'][$key]['invoice_status'] = saleInvoiceStatus($value['invoice_status'], true);
                $data['data'][$key]['pay_type'] = purchasePayType($value['pay_type']);
                $data['data'][$key]['order_status'] = SaleOrderStatus($value['order_status'], true);
                $data['data'][$key]['is_upload_contract'] = purchaseContract($value['is_upload_contract'], true);
                $data['data'][$key]['is_void'] = purchaseContract($value['is_void'], true);
                //$data['data'][$key]['from_sale_order_number'] = $value['from_sale_order_number'] ? $value['from_sale_order_number'] : '--';
                $data['data'][$key]['price'] = $value['price'] > 0 ? getNum($value['price']) : '0.00';
                $data['data'][$key]['order_amount'] = $value['order_amount'] > 0 ? getNum($value['order_amount']) : '0';
                $data['data'][$key]['total_amount'] = getNum($value['order_amount']) - round(getNum(getNum($value['returned_goods_num'] * $value['price'])) , 2);
                $data['data'][$key]['loss_amount'] = round(getNum(getNum($value['loss_num'] * $value['price'])), 2);
                $data['data'][$key]['loss_num'] = getNum($value['loss_num']);
                $data['data'][$key]['entered_loss_amount'] = getNum($value['entered_loss_amount']);
                $data['data'][$key]['returned_goods_num'] = getNum($value['returned_goods_num']);
                $data['data'][$key]['no_collect_amount'] = $data['data'][$key]['total_amount'] - round(getNum($value['collected_amount'] - $value['return_payed_amount']),2);
                //$data['data'][$key]['no_invoice_amount'] = getNum($value['order_amount'] - $value['invoiced_amount']) - getNum(round($value['loss_num'] * getNum($value['price']), 2));
                $data['data'][$key]['s_company_name']    = empty(trim($data['data'][$key]['s_company_name'])) ? '——' : $data['data'][$key]['s_company_name'];
                //$data['data'][$key]['no_invoice_amount'] = $data['data'][$key]['total_amount'] - round(getNum($value['invoiced_amount']), 2);
                $data['data'][$key]['no_invoice_amount'] = round($data['data'][$key]['total_amount'] - getNum($value['invoiced_amount']), 2);
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? getNum($value['buy_num']) : 0;
                $data['data'][$key]['delivery_money'] = $value['delivery_money'] > 0 ? getNum($value['delivery_money']) : 0;
                $data['data'][$key]['outbound_quantity'] = $value['outbound_quantity'] > 0 ? getNum($value['outbound_quantity']) : 0;
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];
                $data['data'][$key]['delivery_method'] = saleOrderDeliveryMethod($value['delivery_method'],1);
                $data['data'][$key]['s_company_name'] = $value['company_id'] == 99999 ? '不限' : $companyInfo[$value['company_id']]['company_name'];
                $data['data'][$key]['order_source'] = saleOrderSourceFrom($value['order_source'], true);
                $data['data'][$key]['pay_type'] = saleOrderPayType($value['pay_type'], true);
                //$data['data'][$key]['creater_name'] = M('Dealer')->where(['is_available' => 0, 'id' => $value['creater']])->field('dealer_name')->find()['dealer_name'];
                $data['data'][$key]['creater_name'] = $creater_arr[$value['creater']];
                //edit xiaowen 2017-6-7 根据销售单类型 显示不同的仓库名/服务商
                $data['data'][$key]['storehouse_name'] = $value['order_type'] == 1 ? $data['data'][$key]['storehouse_name'] : $facilitatorInfo[$value['facilitator_id']]['company_name'];
                $data['data'][$key]['facilitator_skid_name'] = $value['order_type'] == 2 ? $value['storehouse_name'] : '——';
                $data['data'][$key]['retail_inner_order_number'] = $value['business_type'] == 4 ? $value['retail_inner_order_number'] : '——';

                $data['data'][$key]['s_user_name'] = $userInfo[$value['user_id']]['user_name'];
                $data['data'][$key]['s_user_phone'] = $userInfo[$value['user_id']]['user_phone'];

                //--截单时间：（代采现结、账期、货到付款 销售单已确认， 现结 已付款 ， 定金锁价，已预付 部分付款 已收款 ）不显示，其他情况需要显示--
                if( (in_array($value['pay_type'], [2, 3, 4]) && $value['order_status'] == 10) ||
                    ($value['pay_type'] == 1 && $value['collection_status'] == 10) ||
                    ($value['pay_type'] == 5 && in_array($value['collection_status'], [3, 4, 10])) || $value['order_status'] == 2
                    )
                {
                    $data['data'][$key]['residue_time'] = '——';

                }else{
                    $data['data'][$key]['residue_time'] = getSaleOrderResidueTime($value['end_order_time'], currentTime()); //截单剩余时间 精确到分
                }
                //发票号
                $data['data'][$key]['invoice_sn'] = empty($sale_order_invoice[$data['data'][$key]['order_number']]) ? '——' : implode(',',$sale_order_invoice[$data['data'][$key]['order_number']]);

                # 零售销售单红冲添加字段
                $data['data'][$key]['goods_name_retail']  = isset($value['goods_source_from']) && !empty($value['goods_source_from']) ?  $value['goods_source_from'].'/'.$value['goods_name'].'/'.$value['goods_rank'].'/'.$value['goods_level'] : $value['goods_name'].'/'.$value['goods_rank'].'/'.$value['goods_level'];
                # 零售销售单数量字段别名
                # 升数 保留2位，吨数保留4位 qianbin 2018-04-09
                $data['data'][$key]['buy_num_retail'] = $value['buy_num_retail'] > 0 ?  round(getNum($value['buy_num_retail']),2) : 0;
                $data['data'][$key]['buy_num_tun'] = $value['buy_num'] > 0 ? ErpFormatFloat(getNum($value['buy_num'])) : 0;
                $data['data'][$key]['outbound_quantity_retail'] = $value['outbound_quantity'] > 0 ? ErpFormatFloat(getNum($value['outbound_quantity'])) : 0;

                $data['data'][$key]['outbound_num_litre'] = (isset($value['outbound_num_litre']) && $value['outbound_num_litre'] > 0) ?  round(getNum($value['outbound_num_litre']),2) : 0;
                # end
                $data['data'][$key]['is_all_outbound'] = isset($value['is_all_outbound']) ?  isAllOutbound($value['is_all_outbound']) : '';
                $data['data'][$key]['is_reverse']      = isset($value['is_reverse']) ?  isReverse($value['is_reverse']) : '';
                $data['data'][$key]['retail_pay_type'] = '现结';
                #   end --------------
                $data['data'][$key]['is_agent']        = isset($value['is_agent']) ?  saleAgentType($value['is_agent'],1) : '';
                # 添加业务类型 qianbin 2018-07-02
                $data['data'][$key]['business_type']        = isset($value['business_type']) ?  getSaleOrderBusinessType($value['business_type']) : '';
                /*************author :yf. time 2018-12-26 ****************/
                if ( $value['order_source'] == 7 ) {
                    $data['data'][$key]['s_company_name'] = 'C端公司';
                    $data['data'][$key]['creater_name']   = '--';
                }
                /******************** end *******************************/
                //-----------------------------------------------------------------------------------------------------------------------------------
                $data['data'][$key]['order_amount'] = round($data['data'][$key]['order_amount'],2);
                /*-------------------------------------------
                 * yf  有关内部交易单  2018-01-09
                /*------------------------------------------*/
                if ( $value['business_type'] == 4 ) {
                    $data['data'][$key]['no_collect_amount'] = round($data['data'][$key]['no_collect_amount'],2);
                    $data['data'][$key]['order_amount']      = round($data['data'][$key]['order_amount'],2);
                    $data['data'][$key]['total_amount']      = round($data['data'][$key]['total_amount'],2);
                    $data['data'][$key]['outbound_quantity'] = round($data['data'][$key]['outbound_quantity'],4);
                    $data['data'][$key]['buy_num']           = round($data['data'][$key]['buy_num'],4);
                }
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
     * 新增销售单
     * @param array $param
     * @author xiaowen
     * @time 2017-4-1
     * @return array
     */
    public function addSaleOrder($param = [])
    {
        $goods_price = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$param['goods_id'],'region'=>$param['region'],'our_company_id'=>session('erp_company_id')])->find()['price'];

        $storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['storehouse_id']])->find();
        if (empty($param)) {
            return ['status' => 0, 'message' => '参数有误'];
        } else if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择下单时间'
            ];
        } else if (!strHtml($param['our_company_id'])) {
            $result = [
                'status' => 3,
                'message' => '请选择我方公司'
            ];
        } else if ($param['our_company_id'] != session('erp_company_id')) {
            $result = [
                'status' => 4,
                'message' => '我方公司与当前登录账套公司不一致，请刷新页面'
            ];
        } else if (strHtml($param['user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择用户'
            ];
        } else if (strHtml($param['company_id']) == '') {
            $result = [
                'status' => 6,
                'message' => '请选择公司'
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
        } else if ($param['pay_type'] == 5 && !trim($param['prepay_ratio']) && $param['prepay_ratio'] != 0) {
            $result = [
                'status' => 11,
                'message' => '请输入定金比例'
            ];
        } else if ($param['pay_type'] == 5 && trim($param['prepay_ratio']) >= 100) {
            $result = [
                'status' => 12,
                'message' => '定金比例不能大于100'
            ];
        } else if ($param['pay_type'] == 2 && !trim($param['account_period'])) {
            $result = [
                'status' => 13,
                'message' => '请输入帐期'
            ];
        } else if (!strHtml($param['goods_id'])) {
            $result = [
                'status' => 14,
                'message' => '请输入商品信息'
            ];
        } else if ($param['price'] == 0) {
            $result = [
                'status' => 15,
                'message' => '请输入销售单价'
            ];
        } else if (!strHtml($param['buy_num'])) {
            $result = [
                'status' => 16,
                'message' => '请输入数量'
            ];
        } else if ($goods_price == 0) {
            $result = [
                'status' => 17,
                'message' => '区域商品维护价格为0，请先维护后再下单！'
            ];
        } else if (in_array($param['pay_type'], [4, 5]) && ($param['price'] < getNum($goods_price))) {
            $result = [
                'status' => 18,
                'message' => '货到付款与定金锁价销售价格不能低于采购定价，请重新操作'
            ];
        } else if ($storehourse['is_sale'] != 1) {
            $result = [
                'status' => 19,
                'message' => '该仓库不能做销售业务，请检查'
            ];
        } else if ($storehourse['region'] != $param['region'] && $storehourse['whole_country'] != 1) {
            $result = [
                'status' => 19,
                'message' => '该仓库不是全国仓库，请检查'
            ];
        } else if ($storehourse['status'] == 2) {
            $result = [
                'status' => 20,
                'message' => '该仓库已禁用，请检查'
            ];
        } else if ($param['business_type'] == 0) {
            $result = [
                'status' => 21,
                'message' => '请选择正确的业务类型！'
            ];
        } else {
            //--------------------------------------验证可售数量是否满足订单购买数量----------------------------------
            $stock_where = [
                'goods_id' => $param['goods_id'],
                'object_id' => $param['storehouse_id'],
                'region' => $param['region'],
                'stock_type' => $param['is_agent'] == 1 ? 2 : getAllocationStockType($storehourse['id']),
                'status' => 1,
            ];
            $stockSaleNum = $this->getEvent('ErpStock')->getStockSaleNum($stock_where);

            $data = $param;
            //根据销售单选择的城市，查找该地方对应的城市仓和代采仓
            $storehouse_data = $this->getEvent('ErpStorehouse')->getStorehouseByRegion($param['region'], 1);

            $storehouse_type = $param['is_agent'] == 1 ? 2 : 1;
            //根据销售单是否代采，确定使用该城市哪个仓库
            if ($param['is_agent'] == 1) {
                $data['storehouse_id'] = $storehouse_data[$storehouse_type]['id'];
            } else {
                $data['storehouse_id'] = $param['storehouse_id'];
            }

            if (!$data['storehouse_id']) {
                $result['status'] = 101;
                $result['message'] = "该地区没有代采仓，无法生成代采销售单";
                return $result;
            }
//            if(!$this->isNewCustomer($param['user_id']) && setNum($param['buy_num']) > $stockSaleNum && $storehouse_type == 1){ //新客户也预留，date:2017/05/25
            if(setNum($param['buy_num']) > $stockSaleNum && $storehouse_type == 1){
                $result = [
                    'status' => 17,
                    'message' => '商品可售数量不足，请稍后再试，当前可售数量为'.getNum($stockSaleNum)
                ];
                return $result;
            }
            //验证商品是否在区域商品维护中设置
            $region_goods = D('ErpRegionGoods')->where(['goods_id'=>$param['goods_id'],'region'=>$param['region'],'status'=>1,'our_company_id'=>session('erp_company_id')])->find();
            if (!$region_goods) {
                $result['status'] = 101;
                $result['message'] = "该商品未在区域维护中设置";
                return $result;
            }
            //--------------------------------------------------------------------------------------------------------
            if (getCacheLock('ErpSale/addSaleOrder')) return ['status' => 99, 'message' => $this->running_msg];

            setCacheLock('ErpSale/addSaleOrder', 1);

            M()->startTrans();

            $data['price'] = setNum($param['price']);
            if ($param['pay_type'] == 5) {
                $data['prepay_ratio'] = setNum($param['prepay_ratio']);
            }
            $data['add_order_time'] = date('Y-m-d H:i:s', strtotime('+1 days', strtotime($param['add_order_time']) - 1));
            $data['price'] = setNum($param['price']);
            $data['goods_price'] = $goods_price;
            $data['buy_num'] = setNum($param['buy_num']);

            $data['delivery_money'] = trim($param['delivery_money']) ? setNum($param['delivery_money']) : 0;

            $data['order_amount'] = setNum(round($param['buy_num'] * $param['price'], 2)) + $data['delivery_money']; //订单总额 = 购买数量 * 单价 + 运费

            $data['order_number'] = erpCodeNumber(6)['order_number'];
            $data['create_time'] = currentTime();
            $data['update_time'] = currentTime();
            $data['end_order_time'] = $param['pay_type'] != 5 ? defaultSaleOrderEndTime() : todayLastTime(); //除定金锁价外 默认2小时截单 定金锁价 当天最后一秒

            $data['creater'] = $this->getUserInfo('id');
            unset($data['goods_name']);
            unset($data['goods_code']);
            unset($data['goods_from']);
            unset($data['goods_grade']);
            unset($data['goods_level']);
            //$stock_log_type = isset($param['id']) && $param['id'] > 0 ? 8 : 1; //库存影响类型 1 新增 8 复制 通过复制新增销售单类型为8
            $stock_log_type = 1; //库存影响类型 1 统一为新增
            //复制新增的销售单，要去除参数中的ID
            if(isset($param['id'])){
                unset($param['id']);
                unset($data['id']);
            }
            $order_status = $this->getEvent('ErpSale', 'Common')->CommonAddSaleOrder($data);
            //if ($status && $log_status && $stock_status) {
            if ($order_status) {
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

            cancelCacheLock('ErpSale/addSaleOrder');
        }
        return $result;
    }

    /**
     * 编辑销售单
     * @param $id
     * @param array $param
     * @return array
     */
    public function updateSaleOrder($id, $param = [])
    {
        $goods_price = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$param['goods_id'],'region'=>$param['region'],'our_company_id'=>session('erp_company_id')])->find()['price'];

        $storehourse = $this->getModel('ErpStorehouse')->where(['id'=>$param['storehouse_id']])->find();
        if (!$id || empty($param)) {
            return ['status' => 0, 'message' => '参数有误'];
        } else if (strHtml($param['add_order_time']) == '') {
            $result = [
                'status' => 2,
                'message' => '请选择下单时间'
            ];
        } else if (!strHtml($param['our_company_id'])) {
            $result = [
                'status' => 3,
                'message' => '请选择我方公司'
            ];
        } else if (strHtml($param['user_id']) == '') {
            $result = [
                'status' => 5,
                'message' => '请选择用户'
            ];
        } else if (strHtml($param['company_id']) == '') {
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
        } else if ($param['pay_type'] == 5 && !trim($param['prepay_ratio']) && $param['prepay_ratio'] != 0) {
            $result = [
                'status' => 11,
                'message' => '请输入定金比例'
            ];
        }  else if ($param['pay_type'] == 5 && trim($param['prepay_ratio']) >= 100) {
            $result = [
                'status' => 11,
                'message' => '定金比例不能大于100'
            ];
        } else if ($param['pay_type'] == 2 && !trim($param['account_period'])) {
            $result = [
                'status' => 12,
                'message' => '请输入帐期'
            ];
        } else if (!strHtml($param['goods_id'])) {
            $result = [
                'status' => 14,
                'message' => '请输入商品信息'
            ];
        } else if ($param['price'] == 0) {
            $result = [
                'status' => 15,
                'message' => '请输入销售单价'
            ];
        } else if (!strHtml($param['buy_num'])) {
            $result = [
                'status' => 16,
                'message' => '请输入销售数量'
            ];
        } else if ($goods_price == 0) {
            $result = [
                'status' => 17,
                'message' => '区域商品维护价格为0，请先维护后再下单！'
            ];
        } else if (in_array($param['pay_type'], [4, 5]) && ($param['price'] < getNum($goods_price))) {
            $result = [
                'status' => 102,
                'message' => '货到付款与定金锁价销售价格不能低于采购定价，请重新操作'
            ];
        } else if ($storehourse['is_sale'] != 1) {
            $result = [
                'status' => 19,
                'message' => '该仓库不能做销售业务，请检查'
            ];
        } else if ($storehourse['region'] != $param['region'] && $storehourse['whole_country'] != 1) {
            $result = [
                'status' => 19,
                'message' => '该仓库不是全国仓库，请检查'
            ];
        } else if ($storehourse['status'] == 2) {
            $result = [
                'status' => 20,
                'message' => '该仓库已禁用，请检查'
            ];
        } else if ($param['business_type'] == 0) {
            $result = [
                'status' => 21,
                'message' => '请选择正确的业务类型'
            ];
        } else {
            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            $stock_where = [
                'goods_id' => $param['goods_id'],
                'object_id' => $param['storehouse_id'],
                'stock_type' => $param['is_agent'] == 2 ? getAllocationStockType($param['storehouse_id']) : 2,
                'region' => $param['region'],
            ];
            $stockSaleNum = $this->getEvent('ErpStock')->getStockSaleNum($stock_where);
            log_info('可售数量：'. $stockSaleNum);
            log_info('购买数量：'. setNum($param['buy_num']));

            //验证商品是否在区域商品维护中设置
            $region_goods = D('ErpRegionGoods')->where(['goods_id'=>$param['goods_id'],'region'=>$param['region'],'status'=>1,'our_company_id'=>session('erp_company_id')])->find();

            $data = $param;
            //根据销售单选择的城市，查找该地方对应的城市仓和代采仓
            $storehouse_data = $this->getEvent('ErpStorehouse')->getStorehouseByRegion($param['region'], 1);

            $storehouse_type = $param['is_agent'] == 1 ? 2 : 1;
            //根据销售单是否代采，确定使用该城市哪个仓库
            if ($param['is_agent'] == 1) {
                $data['storehouse_id'] = $storehouse_data[$storehouse_type]['id'];
            } else {
                $data['storehouse_id'] = $param['storehouse_id'];
            }

            /**
             * 判断修改后的商品和仓库是否保持一致，
             * 若保持一致，则取差值进行与可售数量的判断，
             * 若不同则取全值
             */
            if ($saleOrderInfo['goods_id'] == $param['goods_id'] && $saleOrderInfo['storehouse_id'] == $data['storehouse_id']) {
                $changenum = $param['buy_num'] - getNum($saleOrderInfo['buy_num']);
            } else {
                $changenum = $param['buy_num'];
            }

            if (!$data['storehouse_id']) {
                $result['status'] = 101;
                $result['message'] = "该地区没有代采仓，无法生成代采销售单";
            } elseif (!$region_goods) {
                $result['status'] = 101;
                $result['message'] = "该商品未在区域维护中设置";
            } elseif (empty($saleOrderInfo)) {
                $result = [
                    'status' => 17,
                    'message' => '该销售单不存在'
                ];
            }
            //现结并且是特需已确认，价格不允许修改为比原来低 edit xiaowen 2017-6-10
            elseif($saleOrderInfo['pay_type'] == 1 && $saleOrderInfo['is_special'] == 1 && $saleOrderInfo['order_status'] == 10 && setNum($param['price']) < $saleOrderInfo['price']){
                $result = [
                    'status' => 18,
                    'message' => '现结特需销售单已确认后不允许价格低于原订单价格'
                ];
            }
//            else if ($saleOrderInfo['order_status'] != 1 && !($saleOrderInfo['is_special'] == 1 && $saleOrderInfo['collection_status'] == 1 && $saleOrderInfo['invoice_status'] == 1)) {
//                $result = [
//                    'status' => 18,
//                    'message' => '该销售单不是未审核状态，无法编辑'
//                ];
//
//            }
            else if(setNum($changenum) > $stockSaleNum && $storehouse_type == 1){
                if ($saleOrderInfo['goods_id'] == $param['goods_id'] && $saleOrderInfo['storehouse_id'] == $data['storehouse_id']) {
                    $result = [
                        'status' => 19,
                        'message' => '商品可售数量不足，请稍后再试，当前可售数量为'.getNum($stockSaleNum+$saleOrderInfo['buy_num'])
                    ];
                } else {
                    $result = [
                        'status' => 19,
                        'message' => '商品可售数量不足，请稍后再试，当前可售数量为'.getNum($stockSaleNum)
                    ];
                }
            } else {

                if (getCacheLock('ErpSale/updateSaleOrder')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpSale/updateSaleOrder', 1);

                M()->startTrans();
                //=========================订单修改回滚原库存=========================
                $stock_log_info = D('ErpStockLog')->where(['object_number'=>$saleOrderInfo['order_number']])->find();
                if(!empty($stock_log_info) && $stock_log_info['change_num']){
                    $stock_where = [
                        'goods_id' => $saleOrderInfo['goods_id'],
                        'object_id' => $saleOrderInfo['storehouse_id'],
                        'stock_type' => $saleOrderInfo['is_agent'] == 2 ? getAllocationStockType($saleOrderInfo['storehouse_id']) : 2,
                        'region' => $saleOrderInfo['region'],
                    ];
                    $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

                    $stock_data = [
                        'goods_id' => $saleOrderInfo['goods_id'],
                        'object_id' => $saleOrderInfo['storehouse_id'],
                        'stock_type' => $saleOrderInfo['is_agent'] == 2 ? getAllocationStockType($saleOrderInfo['storehouse_id']) : 2,
                        'region' => $saleOrderInfo['region'],
                        'sale_reserve_num' => $stock_info['sale_reserve_num'] - $saleOrderInfo['buy_num'],

                    ];
                    $stock_info['sale_reserve_num'] = $stock_data['sale_reserve_num']; //重置最新的销售预留

                    //------------------计算出新的可用库存----------------------------
                    $stock_data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                    $orders = [
                        'object_number' => $saleOrderInfo['order_number'],
                        'object_type' => 1,
                        'log_type' => 7,
                    ];
                    $old_stock_status = $this->getEvent('ErpStock')->saveStockInfo($stock_data, $saleOrderInfo['buy_num'], $orders);
                }else{
                    $old_stock_status = true;
                }
                //=====================================================================================================
                $data['price'] = setNum($param['price']);
                $data['goods_price'] = $goods_price;
                //账期的
                if ($param['pay_type'] == 5) {
                    $data['prepay_ratio'] = setNum($param['prepay_ratio']);
                }
                $data['add_order_time'] = date('Y-m-d H:i:s', strtotime('+1 days', strtotime($param['add_order_time']) - 1));
                $data['price'] = setNum($param['price']);
                $data['buy_num'] = setNum($param['buy_num']);
                $data['delivery_money'] = trim($param['delivery_money']) ? setNum($param['delivery_money']) : 0;
                $data['order_amount'] = setNum(round($param['buy_num'] * $param['price'], 2)) + $data['delivery_money']; //订单总额 = 购买数量 * 单价 + 运费

                $data['update_time'] = currentTime();
                $data['updater'] = $this->getUserInfo('id');
                //----------如果修改了付款方式,原为定金锁价，修改为其他截止时间为2小时，原为其他 修改为定金锁价截止时间为当天最后一秒 eidt xiaowen 2017-5-26---
                if($param['pay_type'] != $saleOrderInfo['pay_type']){
                    if($saleOrderInfo['pay_type'] == 5 && $param['pay_type'] != 5){
                        $data['end_order_time'] = defaultSaleOrderEndTime();
                    }else if($saleOrderInfo['pay_type'] != 5 && $param['pay_type'] == 5){
                        $data['end_order_time'] = todayLastTime();
                    }
                }
                //-----------------------------------------------------------------------------------------------------------------------------------------------
                unset($data['goods_name']);
                unset($data['goods_code']);
                unset($data['goods_from']);
                unset($data['goods_grade']);
                unset($data['goods_level']);

                $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $data);

                $saleOrderInfo_new = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
                $log_data = [
                    'sale_order_id' => $id,
                    'sale_order_number' => $saleOrderInfo['order_number'],
                    'log_type' => 2,
                    'log_info' => serialize($saleOrderInfo_new),
                ];
                $log_status = $this->addSaleOrderLog($log_data);

                //=====================================订单修改 影响新库存=============================================
                //新老用户验证先去除，后续启用时接口也要加上此验证
                //if (!$this->isNewCustomer($param['user_id']) || $param['pay_type'] == 5) {
                    $stock_where = [
                        'goods_id' => $saleOrderInfo_new['goods_id'],
                        'object_id' => $saleOrderInfo_new['storehouse_id'],
                        'stock_type' => $saleOrderInfo_new['is_agent'] == 2 ? getAllocationStockType($saleOrderInfo_new['storehouse_id']) : 2,
                    ];
                    $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

                    $data = [
                        'goods_id' => $saleOrderInfo_new['goods_id'],
                        'object_id' => $saleOrderInfo_new['storehouse_id'],
                        'stock_type' => $saleOrderInfo_new['is_agent'] == 2 ? getAllocationStockType($saleOrderInfo_new['storehouse_id']) : 2,
                        'region' => $saleOrderInfo_new['region'],
                        'sale_reserve_num' => $stock_info['sale_reserve_num'] + $saleOrderInfo_new['buy_num'], //新库存要增加销售预留

                    ];
                    $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留

                    //------------------计算出新的可用库存----------------------------
                    $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                    $orders = [
                        'object_number' => $saleOrderInfo_new['order_number'],
                        'object_type' => 1,
                        'log_type' => 7,
                    ];
                    $new_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $saleOrderInfo_new['buy_num'], $orders);
                    //=====================================================================================================
                //} else {
                    //$new_stock_status = true;
                //}
                if ($status && $log_status && $old_stock_status && $new_stock_status) {
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

                cancelCacheLock('ErpSale/updateSaleOrder');
            }

        }
        return $result;
    }

    /**
     * 获取一条订单信息（不含关联信息）
     * @param $id
     * @return mixed
     * @author xiaowen
     * @time 2017-4-21
     */
    public function findSaleOrder($id){
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>intval($id)]);
        return $order_info;
    }

    /**
     * 获取一条完整的订单信息（包含关联信息）
     * @param $id
     * @param $field
     * @return mixed
     * @author xiaowen
     * @time 2017-4-21
     */
    public function findOneSaleOrder($id, $field){
        //$field = 'o.*,d.depot_name,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';

        $order_info = $this->getModel('ErpSaleOrder')->findOneSaleOrder(['o.id'=>intval($id)], $field);
        return $order_info;
    }

    public function getSaleOrderInfoList($param, $order = 'id desc', $field = true){
        $data = $this->getModel('ErpSaleOrder')->getSaleOrderInfoList($param, $order, $field);
        log_info("定时获取销售单SQL：".M()->getLastSql());
        return $data;
    }

    /**
     * 返回超时订单列表(除定金锁价外) $type = 2 定金锁价
     * @param int $type
     * @author xiaowen
     * @time 2017-5-27
     */
    public function getTimeOutOrderList($type = 1){
        //edit xiaowen pay_type in (1, 3) 修改为 pay_type in (1) 代采现结不用取消 2017-6-26  edit 二次定价后的不用取消
        //$where['_string'] = 'end_order_time <= "' . currentTime() . '" and ((pay_type in (1, 3, 5) and collection_status = 1 and order_status <> 2) or (pay_type in (2, 4) and order_status not in (2, 10)))  and order_type = 1 and is_update_price = 2 and is_returned = 2';
        //edit xiaowen 2018-3-13 修改为 已到截止时间 且销售单不为已取消，已确认 状态，可自动取消
        //$where['_string'] = 'end_order_time <= "' . currentTime() . '" and (order_status not in (2, 10))';
        $where['_string'] = 'end_order_time <= "' . currentTime() . '" and (order_status != 2) and order_type = 1 and collection_status = 1';

        $data = $this->getSaleOrderInfoList($where, 'id asc');
        return $data;

    }


    /**
     * 返回即将超时订单列表
     * @param int $type
     * @author xiaowen
     * @time 2017-5-27
     */
    public function getSoonTimeOutOrderList($type = 1){
        if($type == 1){
            $time = date('Y-m-d H:i:s', time()-60 * 30);
            $where['_string'] = 'end_order_time <= "'.$time . '" and  ((pay_type in (1, 3) and collection_status = 1 and order_status <> 2) or (pay_type in (2, 4) and order_status not in (2, 10))) and order_type = 1 and is_remind = 0 and is_update_price = 2 and is_returned = 2';
        }else if($type == 2){
            $where['_string'] = '"'.date('Y-m-d H:i:s', strtotime('+1day')) . '" >= end_order_time and (pay_type = 5 and collection_status = 1 and order_status <> 2)  and order_type = 1 and is_remind = 0 and is_update_price = 2 and is_returned = 2';
        }

        $data = $this->getSaleOrderInfoList($where, 'id asc');
        return $data;
    }
    /*
     *
     */
    public function CommonAddSaleOrder($data){
        $returnData = ["status" => 1 , "message" => "生成订单成功"] ;
        M()->startTrans();
        $return = $this->getEvent("ErpSale")->CommonAddSaleOrder($data);
        if(!$return){
            $returnData['status'] =0 ;
            $returnData['message'] ="生成订单失败，请重新尝试" ;
            M()->rollback() ;
            return $returnData ;
        }
        //影响地区得
        M()->commit() ;
        return $returnData ;
    }
}
