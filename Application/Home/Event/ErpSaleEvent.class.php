<?php
/**
 * 销售单业务处理层
 * @author xiaowen
 * @time 2017-04-17
 */
namespace Home\Event;

use Think\Controller;
use Home\Controller\BaseController;

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
                $data['data'][$key]['price'] = $value['price'] > 0 ? round(getNum($value['price']),2) : '0.00';
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
     * 删除销售单
     * @author xiaowen
     * @param $id
     * @return array
     */
    public function delSaleOrder($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if ($order_info['order_status'] != 1) {
                $result = [
                    'status' => 2,
                    'message' => '只允许未审核单据进行删除',
                ];
            } else if($this->isCanceledOrder($order_info['order_number'])){
                $result = [
                    'status' => 4,
                    'message' => '该销售单已经取消，无法再删除',
                ];
            }
            else {
                if (getCacheLock('ErpSale/delSaleOrder')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpSale/delSaleOrder', 1);
                M()->startTrans();
                $data = [
                    'order_status' => 2,
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $data);

                $log = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'log_info' => serialize($order_info),
                    'log_type' => 3,
                ];
                $log_status = $this->addSaleOrderLog($log);

                $cancel_log = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'type' => 1, //取消类型 1 手动取消 2 自动取消
                ];
                $cancel_status = $this->addCancelLog($cancel_log);

                //=========================订单取消，减少库存的销售预留，回滚可用库存=========================

                $stock_status = $this->cancelOrderRollbackStock($order_info,6);

                //======================================================================================================

                //删除审批流的流程
                $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id, 'workflow_type'=>1, 'status'=>['neq', 2]])->order('id desc')->find();

                if ($workflow && $workflow['status'] == 1) {
                    $workflow['status'] = 2;
                    $status_work = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
                } else {
                    $status_work = true;
                }

                if ($status && $log_status && $stock_status && $cancel_status && $status_work) {
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

                cancelCacheLock('ErpSale/delSaleOrder');
            }

            return $result;

        }
    }

    /**
     * 审核销售单
     * @author xiaowen
     * @param $id
     * @return array
     * @time 2017-4-5
     */
    public function auditSaleOrder($id)
    {
        if ($id) {
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if ($order_info['order_status'] != 1) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单不是未审核状态，无法审核',
                ];
            } elseif (!$order_info['company_id']) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单未确定公司，无法审核',
                ];
            } else {
                if (getCacheLock('ErpSale/auditSaleOrder')) return ['status' => 99, 'message' => $this->running_msg];
                # 判断公司账套之间 ， 不走审批
                # qianbin
                # 2017.11.06
                $company_id = getErpCompanyList('company_id');
                //------------------------验证公司交易类型是否允许做销售业务，edit xiaowen 2018-5-23---------------------//
//               if(!$this->getEvent('Clients')->checkCompanyTransactionType($order_info['company_id'], 1)){
//                    $result = [
//                        'status' => 4,
//                        'message' => '对不起，该公司交易类型不允许做销售业务，请修改该公司交易类型后再操作！',
//                    ];
//                    cancelCacheLock('ErpSale/auditSaleOrderData');
//                    return $result;
//                }
                //----------------------end 验证公司交易类型是否允许做销售业务，edit xiaowen 2018-5-23---------------------//
                if(in_array(intval($order_info['company_id']),$company_id)){
                    $result = $this->getEvent('ErpWorkFlow')->updateErpWorkFlowOrderStatus(1,intval($order_info['id']),4);
                    log_info('--------->公司账套之间 ， 不走审批!');
                    if($result){
                        $result = ['status' => 1 , 'message' => '操作成功！'];
                    }else{
                        $result = ['status' => 222 , 'message' => '操作失败！'];
                    }
                    return $result;
                }
                setCacheLock('ErpSale/auditSaleOrder', 1);
                //$this->getEvent('ErpWorkFlow')->checkWorkflowPosition();
                M()->startTrans();
                $data = [
                    'order_status' => 3,
                    //'order_status' => 4, //暂时不做工作流，交易员审核直接状态改为已复核
                    'audit_time' => currentTime(),
                    'auditor' => $this->getUserInfo('id'),
                ];

                //生成销售单审批流程
                //$workflow_result = $this->createWorkflow($order_info);
                //销售单生成新审批流，2018-7-2 改造 肖文
                //print_r($order_info);
                $workflow_result = $this->createWorkflowNew($order_info);
                if($workflow_result['check_order'] == 1){
                    $data['order_status'] = 4;
                }
                $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $data);

                $log = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'log_info' => serialize($order_info),
                    'log_type' => 4,
                ];
                $log_status = $this->addSaleOrderLog($log);
                log_info('生成审批' . $workflow_result['status'] ? '成功' : '失败');
                if ($workflow_result['status'] && $status && $log_status) {
                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                    ];
                } else {
                    M()->rollback();
                    $result = [
                        'status' => 0,
                        'message' => $workflow_result['message'] ? $workflow_result['message'] :  '操作失败',
                    ];
                }

                cancelCacheLock('ErpSale/auditSaleOrder');
            }

        } else {
            $result = [
                'status' => 0,
                'message' => '参数有误，请选择销售单',
            ];
        }
        return $result;
    }

    /**
     * 确认销售单
     * @param id
     * @return array $result
     * @author xiaowen
     * @time 2017-4-5
     */
    public function confirmSaleOrder($id)
    {
        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误，无法获取销售单ID',
            ];
        } else {
            $order_info = $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            //print_r($saleOrderInfo);
            if ($saleOrderInfo['order_status'] != 4) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单不是已复核状态，请稍后操作',
                ];
            }
//            else if($saleOrderInfo['is_upload_contract'] != 1){
//                $result = [
//                    'status'=>3,
//                    'message'=>'该销售单未上传合同，请稍后操作',
//                ];
//            }
            else {
                if (getCacheLock('ErpSale/confirmPurchase')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpSale/confirmPurchase', 1);
                M()->startTrans();

                $u_id = $this->getModel('Clients')->field('u_id')->where(['company_id' => trim($saleOrderInfo['company_id']), 'is_available' => 0])->find();

                if ($u_id) {
                    $dealer_name = $this->getModel('User')->field('dealer_name')->where(['id' => trim($u_id['u_id']), 'is_available' => 0])->find();
                    $dealer = M('Dealer')->where(['is_available' => 0, 'dealer_name' => $dealer_name['dealer_name']])->find();
                } else {
                    $dealer = [
                        'id' => 0,
                        'dealer_name' => ''
                    ];
                }

                $data = [
                    'order_status' => 10,
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                    'dev_dealer_id' => $dealer['id'],
                    'dev_dealer_name' => $dealer['dealer_name'],
                ];

                $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $data);

                $log = [
                    'sale_order_id' => $saleOrderInfo['id'],
                    'sale_order_number' => $saleOrderInfo['order_number'],
                    'log_info' => serialize($saleOrderInfo),
                    'log_type' => 6,
                ];
                $log_status = $this->addSaleOrderLog($log);
                //=========================根据销售单付款方式，判断是否增加库存的销售待提=========================
                $stock_log_info = D('ErpStockLog')->where(['object_number'=>$order_info['order_number'], 'log_type'=>['in',[1,2]]])->order('id desc')->find();
                //付款方式是账期并且之前已有销售预留，则要把销售预留转为销售待提
                if($order_info['pay_type'] == 2 || $order_info['pay_type'] == 4){
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

                    if (!empty($stock_log_info) && $stock_log_info['change_num']) {
                        $data['sale_reserve_num'] = $stock_info['sale_reserve_num'] - $order_info['buy_num'];
                        $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留
                    }

                    $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的销售待提
                    //------------------计算出新的可用库存----------------------------
                    $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                    $orders = [
                        'object_number' => $order_info['order_number'],
                        'object_type' => 1,
                        'log_type' => 3,
                    ];
                    $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['buy_num'], $orders);
                } else{
                    $stock_status = true;
                }

                //======================================================================================================
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

            cancelCacheLock('ErpSale/confirmPurchase');
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

        //$result = [];
        if ($id && $attach) {

            if (count($attach) > 1) {
                return $result = [

                    'status' => 4,
                    'message' => '对不起，同时只能上传一份合同',

                ];
            }

            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if (!in_array($saleOrderInfo['order_status'], [4, 10])) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单未通过复核，请稍后操作',

                ];
            } else {
                if (getCacheLock('ErpSale/uploadContract')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpSale/uploadContract', 1);

                M()->startTrans();

                $data = [
                    'is_upload_contract' => 1,
                    'contract_url' => $attach[0],
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];
                $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $data);

                $log_data = [
                    'sale_order_id' => $saleOrderInfo['id'],
                    'sale_order_number' => $saleOrderInfo['order_number'],
                    'log_info' => serialize($saleOrderInfo),
                    'log_type' => 7,
                ];
                $log_status = $this->addSaleOrderLog($log_data);

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
                cancelCacheLock('ErpSale/uploadContract');
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
     * 插入销售单操作日志
     * @author xiaowen
     * @param $data
     * @return mixed
     */
    public function addSaleOrderLog($data)
    {
        if ($data) {
            $data['create_time'] = currentTime();
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0;
            $data['operator_id'] = $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0;
            $status = $this->getModel('ErpSaleOrderLog')->add($data);
        }
        return $status;
    }

    /**
     * 复制销售单(已弃用)
     * @param id
     * @return array $result
     * @author xiaowen
     * @time 2017-4-5
     */
    public function copySaleOrder($id)
    {

        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误，无法获取销售单ID',
            ];
        } else {
            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if (empty($saleOrderInfo)) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单不存在，请稍后操作',
                ];
            } else {
                if (getCacheLock('ErpSale/copySaleOrder')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpSale/copySaleOrder', 1);
                M()->startTrans();
                $data = $saleOrderInfo;

                $data['create_time'] = currentTime();
                $data['creater'] = $this->getUserInfo('id');
                $data['order_number'] = erpCodeNumber(6)['order_number'];
                $data['end_order_time'] = defaultSaleOrderEndTime(); //初始化截止 默认2小时截单
                $data['dealer_id'] = $this->getUserInfo('id');
                $data['dealer_name'] = $this->getUserInfo('dealer_name');
                $data['goods_price'] = $this->getModel('ErpRegionGoods')->where(['goods_id'=>$param['goods_id'],'region'=>$param['region'],'our_company_id'=>session('erp_company_id')])->find()['price'];

                unset($data['id']);
                unset($data['update_time']);
                unset($data['updater']);
                unset($data['order_status']);
                unset($data['order_remark']);
                unset($data['invoice_remark']);
                unset($data['collection_status']);
                unset($data['collected_amount']);
                unset($data['invoice_status']);
                unset($data['invoice_money']);
                unset($data['is_upload_contract']);
                unset($data['contract_url']);
                unset($data['confirm_time']);
                unset($data['check_time']);
                unset($data['audit_time']);
                $data['update_time'] = currentTime();
                $status = $this->getModel('ErpSaleOrder')->addSaleOrder($data);

                $log = [
                    'sale_order_id' => $saleOrderInfo['id'],
                    'sale_order_number' => $saleOrderInfo['order_number'],
                    'log_info' => serialize($saleOrderInfo),
                    'log_type' => 12,
                ];
                $log_status = $this->addSaleOrderLog($log);

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
                cancelCacheLock('ErpSale/copySaleOrder');
            }
        }

        return $result;

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
    public function isInternalTransactionOrder( $id )
    {
        $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
        if ( !empty($sale_order_info) && $sale_order_info['business_type'] == 4 ) {
            return ['status' => 3 , 'message' => '此销售单属于 内部交易单!'];
        }
        return ['status' => 1 ,'message' => '成功!'];
    }

    /**
     * 返回销售单状态
     * @param $id
     * @return mixed
     */
    public function getSaleOrderStatus($id)
    {
        $order_status = $this->getModel('ErpSaleOrder')->where(['id' => $id])->getField('order_status');
        return $order_status;
    }

    /**
     * 变更交易员
     * @param $param
     * @author xiaowen
     * @return array $result
     * @time 2017-4-18
     */
    public function changeDealer($param)
    {
        if ($param['id'] && $param['new_dealer_id']) {
            if(!$param['new_dealer_id']){
                $result = [
                    'status' => 2,
                    'message' => '新交易员信息有误，请检查后操作',
                ];
            } else {

                $orderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($param['id'])]);
                if(empty($orderInfo)){
                    $result = [
                        'status' => 3,
                        'message' => '该销售单不存在，请检查后操作',
                    ];
                } else {
                    if ($orderInfo['order_status'] != 1) {
                        $result = [
                            'status' => 4,
                            'message' => '销售单不是未审核状态，请稍后操作',
                        ];
                    } else if($orderInfo['dealer_id'] == intval($param['new_dealer_id'])){
                        $result = [
                            'status' => 5,
                            'message' => '销售单不能变更为同一交易员',
                        ];
                    } else {
                        if (getCacheLock('ErpSale/changeDealer')) return ['status' => 99, 'message' => $this->running_msg];

                        setCacheLock('ErpSale/changeDealer', 1);

                        M()->startTrans();
                        $param['new_dealer_name'] = D('Dealer')->where(['id'=>intval($param['new_dealer_id'])])->getField('dealer_name');
                        $data['dealer_id'] = intval($param['new_dealer_id']);
                        $data['dealer_name'] = $param['new_dealer_name'];
                        $data['update_time'] = currentTime();
                        $data['updater'] = $this->getUserInfo('id');
                        $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $orderInfo['id']], $data);
                        $change_data = [
                            'sale_order_id' => $orderInfo['id'],
                            'sale_order_number' => $orderInfo['order_number'],
                            'old_dealer_id' => $orderInfo['dealer_id'],
                            'old_dealer_name' => $orderInfo['dealer_name'],
                            'new_dealer_id' => $param['new_dealer_id'],
                            'new_dealer_name' => $param['new_dealer_name'],
                            'remark' => $param['remark'],

                        ];
                        $change_status = $this->addChangeDealer($change_data);
                        $new_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($param['id'])]);
                        $log_data = [
                            'sale_order_id' => $orderInfo['id'],
                            'sale_order_number' => $orderInfo['order_number'],
                            'log_type' => 15,
                            'log_info' => serialize($new_order_info),

                        ];
                        $log_status = $this->addSaleOrderLog($log_data);

                        if ($status && $log_status && $change_status) {
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

                        cancelCacheLock('ErpSale/changeDealer');
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 是否为新用户
     * @param int $uid
     * @return bool
     * @author xiaowen
     * @time 2017-4-18
     */
    public function isNewCustomer($uid = 0)
    {
        if (intval($uid)) {
            $order = $this->getModel('ErpSaleOrder')->findSaleOrder(['user_id' => intval($uid), 'order_status' => 10]);
            return empty($order) ? true : false;
        }

    }

    /**
     * 添加交易员变更记录
     * @param array $data
     * @author xiaowen
     * @time 2017-4-20
     * @return bool
     */
    public function addChangeDealer($data = [])
    {
        $status = false;
        if ($data) {
            $data['operator'] = $this->getUserInfo('dealer_name');
            $data['operator_id'] = $this->getUserInfo('id');
            $data['create_time'] = currentTime();
            $status = $this->getModel('ErpSaleChangeDealer')->add($data);
        }
        return $status;
    }

    /**
     * 添加交易员变更记录
     * @param array $data
     * @author xiaowen
     * @time 2017-4-20
     * @return bool
     */
    public function addOrderDelayLog($data = [])
    {
        $status = false;
        if ($data) {
            $data['operator'] = $this->getUserInfo('dealer_name');
            $data['operator_id'] = $this->getUserInfo('id');
            $data['create_time'] = currentTime();
            $status = $this->getModel('ErpSaleOrderDelay')->add($data);
        }
        return $status;
    }

    /**
     * 变更交易员记录列表
     * @param $param
     * @author xiaowen
     * @time 2017-4-20
     * @return array
     */
    public function changeDealerList($param){
        $order_id = intval($param['order_id']);
        $data['data'] = $this->getModel('ErpSaleChangeDealer')->where(['sale_order_id'=>$order_id])->limit($param['start'], $param['length'])->order('id desc')->select();
        if(empty($data)){
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'] = count($data['data']);
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 订单延长记录列表
     * @param $param
     * @author xiaowen
     * @time 2017-4-20
     * @return array
     */
    public function orderDelayList($param){
        $order_id = intval($param['order_id']);
        $data['data'] = $this->getModel('ErpSaleOrderDelay')->where(['sale_order_id'=>$order_id])->limit($param['start'], $param['length'])->order('id desc')->select();
        if(empty($data)){
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'] = count($data['data']);
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 订单延时
     * @param $param
     * @author xiaowen
     * @return array $result
     * @time 2017-4-18
     */
    public function orderDelay($param)
    {
        if(!$param['id']){
            $result = [
                'status' => 2,
                'message' => '订单参数有误，请检查后操作',
            ];
        } else if(!trim($param['increase_time'])) {
            $result = [
                'status' => 3,
                'message' => '请输入延长时间',
            ];
        } else {

            $orderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($param['id'])]);
            if(empty($orderInfo)){
                $result = [
                    'status' => 4,
                    'message' => '该销售单不存在，请检查后操作',
                ];
            } else if($orderInfo['order_status'] == 2){
                $result = [
                    'status' => 5,
                    'message' => '该销售单已取消，无法延时',
                ];
            } else if(time() >= strtotime($orderInfo['end_order_time'])){
                $result = [
                    'status' => 6,
                    'message' => '该销售单已超时，无法延时',
                ];
            }
            else {
                if (getCacheLock('ErpSale/orderDelay')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpSale/orderDelay', 1);

                M()->startTrans();
                $new_time = strtotime($orderInfo['end_order_time']) + (intval($param['increase_time']) * 60);
                $data['end_order_time'] = date('Y-m-d H:i:s', $new_time);
                //log_info('时间戳：'.$new_time);
                //log_info('最新截止时间：'.strtotime($orderInfo['end_order_time']) + (intval($param['increase_time']) * 60));
                $data['update_time'] = currentTime();
                $data['updater'] = $this->getUserInfo('id');
                $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $orderInfo['id']], $data);
                $change_data = [
                    'sale_order_id' => $orderInfo['id'],
                    'sale_order_number' => $orderInfo['order_number'],
                    'old_end_time' => $orderInfo['end_order_time'],
                    'current_end_time' => $data['end_order_time'],
                    'increase_time' => $param['increase_time'],
                    'remark' => $param['remark'],
                ];
               // print_r($change_data);

                $change_status = $this->addOrderDelayLog($change_data);
                //echo M()->getLastSql();
                $new_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => intval($param['id'])]);
                $log_data = [
                    'sale_order_id' => $orderInfo['id'],
                    'sale_order_number' => $orderInfo['order_number'],
                    'log_type' => 14,
                    'log_info' => serialize($new_order_info),

                ];
                $log_status = $this->addSaleOrderLog($log_data);

                if ($status && $log_status && $change_status) {
                    M()->commit();
                    $result = [
                        'status' => 1,
                        'message' => '操作成功',
                        'end_order_time' => $data['end_order_time'],
                    ];
                } else {
                    M()->rollback();
                    $result = [
                        'status' => 0,
                        'message' => '操作失败',
                    ];
                }

                cancelCacheLock('ErpSale/orderDelay');
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

    /**
     * 通过ID保存订单
     * @param $id
     * @param $data
     * @return bool
     * @author xiaowen
     * @time 2017-4-21
     */
    public function saveSaleOrderById($id, $data = []){
        $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id'=>intval($id)], $data);
        return $status;
    }

    /**
     * 取消超时订单
     * @param $id
     * @return bool
     * @author xiaowen
     * @time 2017-4-21
     */
    public function cancelTimeOutOrder($id){
        $result = false;
        if($id){
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>$id]);
            //订单已超时且订单未取消
            if($order_info['order_status'] != 2 && endOrderTimeOut($order_info['end_order_time'])){
                M()->startTrans();
                $data['update_time'] = currentTime();
                $data['order_status'] = 2;

                $status = $this->saveSaleOrderById($id, $data);
                $new_order_info = $order_info;
                $new_order_info['order_status'] = $data['order_status'];
                $new_order_info['update_time'] = $data['update_time'];
                $log_data = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'log_info' => serialize($order_info),
                    'log_type' => 13,
                ];
                $log_status = $this->addSaleOrderLog($log_data);
                //添加订单取消操作记录 edit xiaowen 2017-6-14
                $cancel_log = [
                    'sale_order_id' => $order_info['id'],
                    'sale_order_number' => $order_info['order_number'],
                    'type' => 2, //取消类型 1 手动取消 2 自动取消
                ];
                $cancel_status = $this->addCancelLog($cancel_log);
                //----------------------订单超时自动取消，回滚库存------------------------------
                $stock_status = $this->cancelOrderRollbackStock($order_info,6,false);
                //------------------------------------------------------------------------------

                //删除审批流的流程
                $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id, 'workflow_type'=>1, 'status'=>['neq', 2]])->order('id desc')->find();
                if ($workflow && $workflow['status'] == 1) {

                    $workflow['status'] = 2;
                    $status_work = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
                } else {
                    $status_work = true;
                }

                if($log_status && $status && $stock_status && $cancel_status && $status_work){
                    M()->commit();
                    $result = true;
                }else{
                    M()->rollback();
                    $result = false;
                }
            }else{
                $result = true;
            }

        }

        return $result;
    }

    /**
     * 返回申请付款及发票信息
     * @param $id
     * @return mixed
     */
    public function getSaleOrderInfo($id,$invoice_id = false)
    {
        //获取订单信息
        $field = 'o.*,c.tax_number,c.customer_name company_name,cu.user_name';
        $sale_order_info = $this->getModel('ErpSaleOrder')->findOneSaleOrder(['o.id' => $id],$field);
        $data = $sale_order_info;
        $data['order_amount'] = getNum($sale_order_info['order_amount']);
        $data['collected_amount'] = getNum($sale_order_info['collected_amount']);
        //$data['no_collect_amount'] = round(getNum($sale_order_info['order_amount'] - $sale_order_info['collected_amount'] - getNum($sale_order_info['returned_goods_num'] * $sale_order_info['price'])),2);
        $data['no_collect_amount'] = round(getNum($sale_order_info['order_amount'] - $sale_order_info['collected_amount']),2);
        $data['invoiced_amount'] = getNum($sale_order_info['invoiced_amount']);
        $data['loss_num'] = getNum(round($sale_order_info['loss_num']));
        $data['loss_amount'] = round(getNum(getNum($sale_order_info['loss_num'] * $sale_order_info['price'])), 2);
        $data['price'] = getNum($sale_order_info['price']);
        $data['pay_type_name'] = empty($sale_order_info['pay_type']) ? '--' : saleOrderPayType($sale_order_info['pay_type']);
        $data['entered_loss_amount'] = getNum($sale_order_info['entered_loss_amount']);
        $data['no_entered_loss_amount'] = round(getNum(getNum($sale_order_info['loss_num'] * $sale_order_info['price'])) - $data['entered_loss_amount'], 2);
        $data['no_invoice_amount'] = round($data['order_amount'] - getNum(round($sale_order_info['loss_num'] * getNum($sale_order_info['price']) + $sale_order_info['returned_goods_num'] * getNum($sale_order_info['price']))) - $data['invoiced_amount'], 2);
        
        //开票祥情的订单总金额，改为由后台计算 edit xiaowen
        $data['total_amount'] = round($data['order_amount'] - $data['loss_amount'], 2);
        //修改发票信息时显示历史发票信息
        if ($invoice_id) {
            $invoice_data = $this->getModel('ErpSaleInvoice')->findSaleInvoice(['id'=>$invoice_id]);
            $invoice_data['notax_invoice_money'] = getNum($invoice_data['notax_invoice_money']);
            $invoice_data['tax_money'] = getNum($invoice_data['tax_money']);
            $data['invoice_data'] = $invoice_data;
        }

        //获取预存账户信息
        $account_where = [
            'account_type' => 1,
            'our_company_id' => $sale_order_info['our_company_id'],
            'company_id' => $sale_order_info['company_id'],
        ];
        $account_info = $this->getModel('ErpAccount')->findAccount($account_where);

        $data['account_balance'] = $account_info ? getNum($account_info['account_balance']) : 0;

        return $data;
    }

    /**
     * 订单取消，减少库存的销售预留，回滚可用库存
     * @param $order_info
     * @return bool
     * @author xiaowen
     * @time 2017-05-05
     */
    public function cancelOrderRollbackStock($order_info,$log_type = 6,$check_per = true){
        //=========================订单取消，减少库存的销售预留，回滚可用库存=========================
        $stock_log_info = D('ErpStockLog')->where(['object_number'=>$order_info['order_number']])->find();
        if(!empty($stock_log_info) && $stock_log_info['change_num']){
            $stock_where = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['storehouse_id'],
                'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
                'our_company_id' => $order_info['our_company_id'], //edit xiaowen 2017-6-11 自动取消回滚库存 加上我方公司条件
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

            $data = [
                'goods_id' => $order_info['goods_id'],
                'object_id' => $order_info['storehouse_id'],
                'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
                'region' => $order_info['region'],
                'sale_reserve_num' => $stock_info['sale_reserve_num'] - $order_info['buy_num'],
            ];
            $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留

            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            $orders = [
                'object_number' => $order_info['order_number'],
                'object_type' => 1,
                'log_type' => $log_type,
                'our_company_id' => $order_info['our_company_id'],
            ];

            //自动取消不需要验证单据前缀
            $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $order_info['buy_num'], $orders, $check_per);
        }else{
            $stock_status = true;
        }

        //======================================================================================================
        return $stock_status;
    }

    /**
     * 生成销售单审批步骤
     * @param array $sale_order_info
     * @author xiaowen
     * @time 2017-05-09
     * @return bool $check_status
     */
    public function createWorkflow($sale_order_info = []){
        $data['status'] = false; //审批流程创建状态
        $data['check_order'] = 0; //是否复核订单状态
        $data['message'] = '';
        if($sale_order_info){

            //销售单售价大于等于采购定价
            if($sale_order_info['price'] >= $sale_order_info['goods_price']){
                if($sale_order_info['pay_type'] == 1){
                    $data['status'] = true;
                    $data['check_order'] = 1;
                    $data['message'] = '';
                }else{
                    $workflow_data = [
                        'workflow_type' => 1,
                        'workflow_order_number' => $sale_order_info['order_number'],
                        'workflow_order_id' => $sale_order_info['id'],
                        'our_company_id' => $sale_order_info['our_company_id'],
                        'creater' => $sale_order_info['dealer_name'],
                        'creater_id' => $sale_order_info['dealer_id'],
                    ];
                    $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

                    if($sale_order_info['pay_type'] == 5){

                        $workflow_step = workflowTypeStepPosition($sale_order_info['pay_type'])[$sale_order_info['is_agent']];
                    }else{
                        $workflow_step = workflowTypeStepPosition($sale_order_info['pay_type']);

                    }

                    $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $sale_order_info, 1);

                    $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
                    $data['message'] = $step_status_data['message'];
                }
            }else{  //销售单售价小于采购定价

               if(in_array($sale_order_info['pay_type'], [4, 5])){ //货到付款，定金锁价 不需要审批
                   $data['status'] = true;
                   $data['check_order'] = 1;
                   $data['message'] = '';
               }else { //代采现结

                   $workflow_data = [
                       'workflow_type' => 1,
                       'workflow_order_number' => $sale_order_info['order_number'],
                       'workflow_order_id' => $sale_order_info['id'],
                       'our_company_id' => $sale_order_info['our_company_id'],
                       'creater' => $sale_order_info['dealer_name'],
                       'creater_id' => $sale_order_info['dealer_id'],
                   ];
                   $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

                   if(in_array($sale_order_info['pay_type'], [3])){
                       $workflow_step = workflowTypeStepLowPricePosition($sale_order_info['pay_type']);
                   }else{

                       log_info("是否掌握市场信息：", $sale_order_info['provide_market_info']);
                       $workflow_step = workflowTypeStepLowPricePosition($sale_order_info['pay_type'])[$sale_order_info['provide_market_info']];

                   }
                   $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $sale_order_info, 1);

                   $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
                   $data['message'] = $step_status_data['message'];
               }
            }
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
     * 提交审核保存市场信息
     * @param $id
     * @param $param
     * @return array
     */
    public function auditSaleOrderData($id, $param){
        if(!$id){
            $result = [
                'status' => 99,
                'message' => '参数有误',
            ];
        }else if($param['provide_market_info'] == 1 && trim($param['market_info']) == ''){
            $result = [
                'status' => 2,
                'message' => '请输入备注信息',
            ];
        }else{
            if (getCacheLock('ErpSale/auditSaleOrderData')) return ['status' => 99, 'message' => $this->running_msg];
            # 判断公司账套之间 ， 不走审批
            # qianbin
            # 2017.11.06
            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>$id]);
            //------------------------验证公司交易类型是否允许做销售业务，edit xiaowen 2018-5-23---------------------//
//            if(!$this->getEvent('Clients')->checkCompanyTransactionType($saleOrderInfo['company_id'], 1)){
//                $result = [
//                    'status' => 4,
//                    'message' => '对不起，该公司交易类型不允许做销售业务，请修改该公司交易类型后再操作！',
//                ];
//                cancelCacheLock('ErpSale/auditSaleOrderData');
//                return $result;
//            }
            //----------------------end 验证公司交易类型是否允许做销售业务，edit xiaowen 2018-5-23---------------------//
            $company_id = getErpCompanyList('company_id');
            if(in_array(intval($saleOrderInfo['company_id']),$company_id)){
                $result = $this->getEvent('ErpWorkFlow')->updateErpWorkFlowOrderStatus(1,intval($saleOrderInfo['id']),4);
                log_info('--------->公司账套之间 ， 不走审批!');
                if($result){
                    $result = ['status' => 1 , 'message' => '操作成功！'];
                }else{
                    $result = ['status' => 222 , 'message' => '操作失败！'];
                }
                return $result;
            }
            setCacheLock('ErpSale/auditSaleOrderData', 1);
            M()->startTrans();
            //$order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            $data = [
                'order_status' => 3,
                'provide_market_info' => $param['provide_market_info'],
                'market_info' => trim($param['market_info']),
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('id'),
            ];

            $status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $data);
            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>$id]);
            $log = [
                'sale_order_id' => $saleOrderInfo['id'],
                'sale_order_number' => $saleOrderInfo['order_number'],
                'log_info' => serialize($saleOrderInfo),
                'log_type' => 4,
            ];
            $log_status = $this->addSaleOrderLog($log);

            //生成销售单审批流程
            //$workflow_result = $this->createWorkflow($saleOrderInfo);
            $workflow_result = $this->createWorkflowNew($saleOrderInfo);
            if($workflow_result['check_order'] == 1){
                $data['order_status'] = 4;
            }
            if($workflow_result['status'] && $status && $log_status){
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '操作成功';
            }else{
                M()->rollback();
                $result['status'] = 0;
                $result['message'] =  $workflow_result['message'] ? $workflow_result['message'] :  '操作失败';
            }

            cancelCacheLock('ErpSale/auditSaleOrderData');
        }

        return $result;
    }

    /**
     * 销售单补贴金额
     * @author xiaowen
     * @time 2017-5-24
     * @param $param $param['id'] 订单ID  $param['subsidy_money'] 补贴金额
     * @return array
     */
    public function subsidyMoney($param){
        if (getCacheLock('ErpSale/subsidyMoney')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpSale/subsidyMoney', 1);
        if(!$param['id']){
            $result = [
                'status' => 2,
                'message' => '订单参数有误',
            ];
        }else if(!$param['subsidy_money']){
            $result = [
                'status' => 3,
                'message' => '请输入补贴金额且必须大于0',
            ];
        }else if(!is_numeric($param['subsidy_money'])){
            $result = [
                'status' => 4,
                'message' => '请输入大于0的数字',
            ];
        }else {
            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>intval($param['id'])]);
            if($saleOrderInfo['order_status'] != 10){
                $result = [
                    'status' => 5,
                    'message' => '该销售单不是已确认状态',
                ];
            }else{
                M()->startTrans();
                $update_data = [
                    'subsidy_money'=> setNum($param['subsidy_money']),
                    'update_time'=> currentTime(),

                ];
                $update_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id'=>intval($param['id'])], $update_data);

                $log_data = [
                    'sale_order_id' => $saleOrderInfo['id'],
                    'sale_order_number' => $saleOrderInfo['order_number'],
                    'log_info' => serialize($saleOrderInfo),
                    'log_type' => 16,
                ];
                $log_status = $this->addSaleOrderLog($log_data);
                if($update_status && $log_status){
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
            }

        }

        cancelCacheLock('ErpSale/subsidyMoney');
        return $result;
    }

    /**
     * 录入损耗
     * @author senpai
     * @time 2017-5-25
     * @param $param $param['id'] 订单ID  $param['loss_num'] 补贴金额
     * @return array
     */
    public function entryLoss($param){
        if (getCacheLock('ErpSale/entryLoss')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpSale/entryLoss', 1);
        if(!$param['id']){
            $result = [
                'status' => 2,
                'message' => '订单参数有误',
            ];
        }else if(!$param['loss_num']){
            $result = [
                'status' => 3,
                'message' => '请输入损耗吨数且必须大于0',
            ];
        }else if(!is_numeric($param['loss_num'])){
            $result = [
                'status' => 4,
                'message' => '请输入大于0的数字',
            ];
        }else {
            $saleOrderInfo = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>intval($param['id'])]);
            if ($saleOrderInfo['outbound_quantity'] != $saleOrderInfo['buy_num']) {
                $result = [
                    'status' => 5,
                    'message' => '该销售单不是已确认状态',
                ];
            } elseif (setNum(trim($param['loss_num'])) > $saleOrderInfo['buy_num']) {
                $result = [
                    'status' => 6,
                    'message' => '损耗吨数不能超过下单总数',
                ];
            } elseif ($saleOrderInfo['entered_loss_amount'] > 0) {
                $result = [
                    'status' => 7,
                    'message' => '已经录入了损耗详情，无法修改损耗，请申请技术支持',
                ];
            } else {
                M()->startTrans();
                $update_data = [
                    'is_loss' => 1,
                    'loss_num' => setNum($param['loss_num']),
                    'update_time' => currentTime(),
                ];
                $update_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id'=>intval($param['id'])], $update_data);

                $log_data = [
                    'sale_order_id' => $saleOrderInfo['id'],
                    'sale_order_number' => $saleOrderInfo['order_number'],
                    'log_info' => serialize($saleOrderInfo),
                    'log_type' => 17,
                ];
                $log_status = $this->addSaleOrderLog($log_data);
                if($update_status && $log_status){
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
            }

        }

        cancelCacheLock('ErpSale/entryLoss');
        return $result;
    }

    /**
     * 添加订单取消记录
     * @author xiaowen
     * @time 2017-6-14
     * @param $data
     * @return status bool
     */
    protected function addCancelLog($data){
        $status = false;
        if($data){
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : '系统';
            $data['operator_id'] = $this->getUserInfo('id') ? $this->getUserInfo('id') : 0;
            $data['operate_time'] = currentTime();
            $status = M('erp_sale_order_cancel')->add($data);
        }
        return $status;
    }

    /**
     * 查询订单是否已取消
     * @param $sale_order_number
     * @author xiaowen
     * @time 2017-6-14
     * @return bool $status
     */
    public function isCanceledOrder($sale_order_number){
        $where['sale_order_number'] = $sale_order_number;
        $cancel_info = M('erp_sale_order_cancel')->where($where)->find();
        $status = !empty($cancel_info) ? true : false;
        return $status;
    }

    /**
     * 销售单回滚
     * @author senpei
     * @time 2017-06-20
     */
    public function rollBackSaleOrder($id)
    {
        if (getCacheLock('ErpSale/rollBackSaleOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpSale/rollBackSaleOrder', 1);
        $id = intval($id);
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误，无法获取销售单ID',
            ];
        } else {
            $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $id]);
            if (empty($sale_order_info)) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单不存在，请稍后操作',
                ];
            } elseif ($sale_order_info['order_status'] == 2) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单已取消，无法作废',
                ];
            } elseif ($sale_order_info['order_status'] == 1) {
                $result = [
                    'status' => 2,
                    'message' => '未审核状态下请自行手动取消',
                ];
            } elseif ($sale_order_info['invoice_status'] != 1) {
                $result = [
                    'status' => 2,
                    'message' => '已开票单据无法作废，请联系财务进行发票冲平',
                ];
            } elseif ($sale_order_info['outbound_quantity'] > 0) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单已出库，请先取消审核出库单后再作废订单',
                ];
            } elseif ($sale_order_info['is_returned'] == 1) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单已退货，无法作废',
                ];
            } else {

                M()->startTrans();

                //所有特殊状况下的status
                $stock_status = true;
                $stock_in_status = true;
                $purchase_stock_status = true;
                $purchase_order_status = true;
                $status_payment = true;
                $payment_status = true;
                $purchase_work_status = true;
                $purchase_log_status = true;

                if (((in_array($sale_order_info['pay_type'],[1,3]) && $sale_order_info['collection_status'] < 10)
                        || (in_array($sale_order_info['pay_type'],[2,4]) && $sale_order_info['order_status'] != 10)
                        || ($sale_order_info['pay_type'] == 5 && $sale_order_info['collection_status'] < 4))
                    && $sale_order_info['order_status'] != 2
                ) {

                    /** 未转销售待提，作废只需要扣减预留 */
                    $stock_status = $this->cancelOrderRollbackStock($sale_order_info,9);

                    //代采现结方式的，已确认就会进入代采需求并生成代采采购单，所以这里特殊处理
                    if ($sale_order_info['pay_type'] == 3) {
                        /** 若为代采的销售单，且生成了对应的代采采购单，需要回滚对应的代采采购单，若没有生成对应的采购单则不需要操作 */
                        if ($sale_order_info['acting_purchase_num'] > 0) {
                            //查询出所有的对应的代采采购单
                            $purchase_order_where = [
                                'from_sale_order_number' => $sale_order_info['order_number'],
                                'from_sale_order_id' => $sale_order_info['id'],
                            ];
                            $purchase_orders = $this->getModel('ErpPurchaseOrder')->where($purchase_order_where)->select();

                            log_info('代采采购单数量:'. count($purchase_orders));

                            if (count($purchase_orders) > 0) {
                                foreach ($purchase_orders as $purchase_order) {

                                    log_info('代采的采购单重置:'. $purchase_order['order_number']);

                                    /** 采购单已转在途 */
                                    if (((in_array($purchase_order['pay_type'],[1,4]) && $purchase_order['pay_status'] == 10)
                                            || ($purchase_order['pay_type'] == 2 && $purchase_order['pay_status'] > 2)
                                            || ($purchase_order['pay_type'] == 3 && $purchase_order['order_status'] == 10)
                                            || ($purchase_order['pay_type'] == 5 && $purchase_order['pay_status'] > 3))
                                        && $purchase_order['order_status'] != 2
                                    ) {
                                        /** ----------------------回滚对应的代采采购单相应的库存start------------------------------------------------------------ */
                                        $stock_where = [
                                            'goods_id' => $purchase_order['goods_id'],
                                            'object_id' => $purchase_order['storehouse_id'],
                                            'stock_type' => $purchase_order['type'] == 1 ? 1 : 2,
                                            'our_company_id' => $purchase_order['our_company_id'],
                                        ];
                                        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

                                        $data = [
                                            'goods_id' => $purchase_order['goods_id'],
                                            'object_id' => $purchase_order['storehouse_id'],
                                            'stock_type' => $purchase_order['type'] == 1 ? 1 : 2,
                                            'region' => $purchase_order['region'],
                                        ];

                                        /** 若已入库则回滚物理并减少在途，若没有入库则减少在途 */
                                        if ($purchase_order['storage_quantity'] > 0) {
                                            $data['stock_num'] = $stock_info['stock_num'] - $purchase_order['storage_quantity'];

                                            //定金锁价逻辑不同，在途减少数量用已转在途数量计算
                                            if ($purchase_order['pay_type'] == 5) {
                                                $data['transportation_num'] = $stock_info['transportation_num'] - ($purchase_order['total_purchase_wait_num'] - $purchase_order['storage_quantity']);
                                            } else {
                                                $data['transportation_num'] = $stock_info['transportation_num'] - ($purchase_order['goods_num'] - $purchase_order['storage_quantity']);
                                            }

                                            $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
                                            $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的采购在途
                                        } else {
                                            //定金锁价逻辑不同，在途减少数量用已转在途数量计算
                                            if ($purchase_order['pay_type'] == 5) {
                                                $data['transportation_num'] = $stock_info['transportation_num'] - $purchase_order['total_purchase_wait_num'];
                                            } else {
                                                $data['transportation_num'] = $stock_info['transportation_num'] - $purchase_order['goods_num'];
                                            }

                                            $stock_info['transportation_num'] = $data['sale_wait_num']; //重置最新的销售待提
                                        }

                                        //------------------计算出新的可用库存----------------------------
                                        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                                        $orders = [
                                            'object_number' => $purchase_order['order_number'],
                                            'object_type' => 2,
                                            'log_type' => 9,
                                            'our_company_id' => $purchase_order['our_company_id'],
                                        ];
                                        $purchase_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $purchase_order['goods_num'], $orders);
                                        /** ----------------------回滚对应的代采采购单相应的库存end------------------------------------------------------------ */
                                    }

                                    //查看是否有对应的入库单
                                    $stock_in_info = $this->getModel('ErpStockIn')->where(['source_number'=>$purchase_order['order_number'],'source_object_id'=>$purchase_order['id']])->find();
                                    if ($stock_in_info) {
                                        //将对应的入库单取消
                                        $stock_in_data = [
                                            'storage_status' => 2,
                                            'update_time' => currentTime(),
                                        ];
                                        $stock_in_where = [
                                            'source_number'=>$purchase_order['order_number'],
                                            'source_object_id'=>$purchase_order['id'],
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
                                        'no_payed_money' => $purchase_order['order_amount'],
                                        'storage_quantity' => 0,
                                        'is_void' => 1,
                                        'updater' => $this->getUserInfo('id'),
                                        'update_time' => DateTime()
                                    ];
                                    $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($purchase_order['id'])], $purchase_order_data);

                                    //如果有未审核的付款申请，则将对应申请驳回
                                    $purchase_payment_info = $this->getModel('ErpPurchasePayment')->where(['purchase_id'=>$purchase_order['id'],'purchase_order_number'=>$purchase_order['order_number'],'status'=>['in',[1,3]]])->find();
                                    if ($purchase_payment_info) {
                                        $purchase_payment_data = [
                                            'status' => 2,
                                            'auditor' => $this->getUserInfo('dealer_name'),
                                            'auditor_id' => $this->getUserInfo('id'),
                                            'audit_time' => currentTime()
                                        ];
                                        $payment_status = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['purchase_id'=>$purchase_order['id'],'purchase_order_number'=>$purchase_order['order_number']], $purchase_payment_data);
                                    } else {
                                        $payment_status = true;
                                    }

                                    //如果有付款，则录一条负付款冲平
                                    if ($purchase_order['pay_status'] > 1) {
                                        $erp_payment_data = [
                                            'pay_money' => $purchase_order['payed_money'] * -1,
                                            'purchase_id' => $purchase_order['id'],
                                            'purchase_order_number' => $purchase_order['order_number'],
                                            'create_time' => DateTime(),
                                            'creator' => $this->getUserInfo('dealer_name'),
                                            'creator_id' => $this->getUserInfo('id'),
                                            'apply_pay_time' => date('Y-m-d', time()) . ' 23:59:59',
                                            'our_company_id' => session('erp_company_id'),
                                            'auditor' => $this->getUserInfo('dealer_name'),
                                            'auditor_id' => $this->getUserInfo('id'),
                                            'audit_time' => DateTime(),
                                            'status' => 10,
                                        ];
                                        $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);
                                    } else {
                                        $status_payment = true;
                                    }

                                    //将代采采购单审批流置为无效
                                    $purchase_workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$purchase_order['id'], 'workflow_type'=>2, 'status'=>['neq', 2]])->order('id desc')->find();
                                    if ($purchase_workflow && $purchase_workflow['status'] == 1) {
                                        $purchase_workflow['status'] = 2;
                                        $purchase_work_status = $this->getModel('ErpWorkflow')->where(['id'=>$purchase_workflow['id']])->save($purchase_workflow);
                                    } else {
                                        $purchase_work_status = true;
                                    }

                                    //保存操作到采购单日志
                                    $purchase_log_data = [
                                        'purchase_id' => $purchase_order['id'],
                                        'purchase_order_number' => $purchase_order['order_number'],
                                        'log_type' => 15,
                                        'log_info' => serialize($purchase_order),
                                        'create_time' => currentTime(),
                                        'operator' => $this->getUserInfo('dealer_name'),
                                        'operator_id' => $this->getUserInfo('id'),
                                    ];
                                    $purchase_log_status = $this->getModel('ErpPurchaseLog')->add($purchase_log_data);

                                    if (!$stock_in_status || !$purchase_stock_status || !$purchase_order_status || !$status_payment || !$purchase_work_status) {
                                        break;
                                    }
                                }
                            }
                        }
                    }

                } elseif (((in_array($sale_order_info['pay_type'],[1,3]) && $sale_order_info['collection_status'] == 10)
                        || (in_array($sale_order_info['pay_type'],[2,4]) && $sale_order_info['order_status'] == 10)
                        || ($sale_order_info['pay_type'] == 5 && $sale_order_info['collection_status'] > 3))
                    && $sale_order_info['order_status'] != 2
                ) {

                    /** 已转销售待提，作废需要扣减待提 */
                    $stock_where = [
                        'goods_id' => $sale_order_info['goods_id'],
                        'object_id' => $sale_order_info['storehouse_id'],
                        'stock_type' => $sale_order_info['is_agent'] == 1 ? 2 : 1,
                        'our_company_id' => $sale_order_info['our_company_id'],
                    ];
                    $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

                    $data = [
                        'goods_id' => $sale_order_info['goods_id'],
                        'object_id' => $sale_order_info['storehouse_id'],
                        'stock_type' => $sale_order_info['is_agent'] == 1 ? 2 : 1,
                        'region' => $sale_order_info['region'],
                    ];

                    /** 若已出库则回滚物理并减少待提，若没有出库则减少待提 */
                    if ($sale_order_info['outbound_quantity'] > 0) {
                        $data['stock_num'] = $stock_info['stock_num'] + $sale_order_info['outbound_quantity'];

                        //定金锁价逻辑不同，待提减少数量用已转待提数量计算，并且减少预留
                        if ($sale_order_info['pay_type'] == 5) {
                            $data['sale_reserve_num'] = $stock_info['sale_reserve_num'] - ($sale_order_info['buy_num'] - $sale_order_info['total_sale_wait_num']);
                            $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留
                            $data['sale_wait_num'] = $stock_info['sale_wait_num'] - ($sale_order_info['total_sale_wait_num'] - $sale_order_info['outbound_quantity']);
                        } else {
                            $data['sale_wait_num'] = $stock_info['sale_wait_num'] - ($sale_order_info['buy_num'] - $sale_order_info['outbound_quantity']);
                        }

                        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
                        $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的销售待提

                    } else {
                        //定金锁价逻辑不同，待提减少数量用已转待提数量计算，并且减少预留
                        if ($sale_order_info['pay_type'] == 5) {
                            $data['sale_reserve_num'] = $stock_info['sale_reserve_num'] - ($sale_order_info['buy_num'] - $sale_order_info['total_sale_wait_num']);
                            $stock_info['sale_reserve_num'] = $data['sale_reserve_num']; //重置最新的销售预留
                            $data['sale_wait_num'] = $stock_info['sale_wait_num'] - $sale_order_info['total_sale_wait_num'];
                        } else {
                            $data['sale_wait_num'] = $stock_info['sale_wait_num'] - $sale_order_info['buy_num'];
                        }

                        $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的销售待提

                    }

                    //------------------计算出新的可用库存----------------------------
                    $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                    $orders = [
                        'object_number' => $sale_order_info['order_number'],
                        'object_type' => 1,
                        'log_type' => 9,
                        'our_company_id' => $sale_order_info['our_company_id'],
                    ];
                    $stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $sale_order_info['buy_num'], $orders);

                    /** 若为代采的销售单，且生成了对应的代采采购单，需要回滚对应的代采采购单，若没有生成对应的采购单则不需要操作 */
                    if ($sale_order_info['is_agent'] == 1 && $sale_order_info['acting_purchase_num'] > 0) {
                        //查询出所有的对应的代采采购单
                        $purchase_order_where = [
                            'from_sale_order_number' => $sale_order_info['order_number'],
                            'from_sale_order_id' => $sale_order_info['id'],
                        ];
                        $purchase_orders = $this->getModel('ErpPurchaseOrder')->where($purchase_order_where)->select();

                        log_info('代采采购单数量:'. count($purchase_orders));

                        if (count($purchase_orders) > 0) {
                            foreach ($purchase_orders as $purchase_order) {

                                log_info('代采的采购单重置:'. $purchase_order['order_number']);

                                /** 采购单已转在途 */
                                if (((in_array($purchase_order['pay_type'],[1,4]) && $purchase_order['pay_status'] == 10)
                                        || ($purchase_order['pay_type'] == 2 && $purchase_order['pay_status'] > 2)
                                        || ($purchase_order['pay_type'] == 3 && $purchase_order['order_status'] == 10)
                                        || ($purchase_order['pay_type'] == 5 && $purchase_order['pay_status'] > 3))
                                    && $purchase_order['order_status'] != 2
                                ) {
                                    /** ----------------------回滚对应的代采采购单相应的库存start------------------------------------------------------------ */
                                    $stock_where = [
                                        'goods_id' => $purchase_order['goods_id'],
                                        'object_id' => $purchase_order['storehouse_id'],
                                        'stock_type' => $purchase_order['type'] == 1 ? 1 : 2,
                                        'our_company_id' => $purchase_order['our_company_id'],
                                    ];
                                    $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

                                    $data = [
                                        'goods_id' => $purchase_order['goods_id'],
                                        'object_id' => $purchase_order['storehouse_id'],
                                        'stock_type' => $purchase_order['type'] == 1 ? 1 : 2,
                                        'region' => $purchase_order['region'],
                                    ];

                                    /** 若已入库则回滚物理并减少在途，若没有入库则减少在途 */
                                    if ($purchase_order['storage_quantity'] > 0) {
                                        $data['stock_num'] = $stock_info['stock_num'] - $purchase_order['storage_quantity'];

                                        //定金锁价逻辑不同，在途减少数量用已转在途数量计算
                                        if ($purchase_order['pay_type'] == 5) {
                                            $data['transportation_num'] = $stock_info['transportation_num'] - ($purchase_order['total_purchase_wait_num'] - $purchase_order['storage_quantity']);
                                        } else {
                                            $data['transportation_num'] = $stock_info['transportation_num'] - ($purchase_order['goods_num'] - $purchase_order['storage_quantity']);
                                        }

                                        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
                                        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的采购在途
                                    } else {
                                        //定金锁价逻辑不同，在途减少数量用已转在途数量计算
                                        if ($purchase_order['pay_type'] == 5) {
                                            $data['transportation_num'] = $stock_info['transportation_num'] - $purchase_order['total_purchase_wait_num'];
                                        } else {
                                            $data['transportation_num'] = $stock_info['transportation_num'] - $purchase_order['goods_num'];
                                        }

                                        $stock_info['transportation_num'] = $data['sale_wait_num']; //重置最新的销售待提
                                    }

                                    //------------------计算出新的可用库存----------------------------
                                    $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                                    $orders = [
                                        'object_number' => $purchase_order['order_number'],
                                        'object_type' => 2,
                                        'log_type' => 9,
                                        'our_company_id' => $purchase_order['our_company_id'],
                                    ];
                                    $purchase_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $purchase_order['goods_num'], $orders);
                                    /** ----------------------回滚对应的代采采购单相应的库存end------------------------------------------------------------ */
                                }

                                //查看是否有对应的入库单
                                $stock_in_info = $this->getModel('ErpStockIn')->where(['source_number'=>$purchase_order['order_number'],'source_object_id'=>$purchase_order['id']])->find();
                                if ($stock_in_info) {
                                    //将对应的入库单取消
                                    $stock_in_data = [
                                        'storage_status' => 2,
                                        'update_time' => currentTime(),
                                    ];
                                    $stock_in_where = [
                                        'source_number'=>$purchase_order['order_number'],
                                        'source_object_id'=>$purchase_order['id'],
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
                                    'no_payed_money' => $purchase_order['order_amount'],
                                    'storage_quantity' => 0,
                                    'is_void' => 1,
                                    'updater' => $this->getUserInfo('id'),
                                    'update_time' => DateTime()
                                ];
                                $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id' => intval($purchase_order['id'])], $purchase_order_data);

                                //如果有未审核的付款申请，则将对应申请驳回
                                $purchase_payment_info = $this->getModel('ErpPurchasePayment')->where(['purchase_id'=>$purchase_order['id'],'purchase_order_number'=>$purchase_order['order_number'],'status'=>['in',[1,3]]])->find();
                                if ($purchase_payment_info) {
                                    $purchase_payment_data = [
                                        'status' => 2,
                                        'auditor' => $this->getUserInfo('dealer_name'),
                                        'auditor_id' => $this->getUserInfo('id'),
                                        'audit_time' => currentTime()
                                    ];
                                    $payment_status = $this->getModel('ErpPurchasePayment')->savePurchasePayment(['purchase_id'=>$purchase_order['id'],'purchase_order_number'=>$purchase_order['order_number']], $purchase_payment_data);
                                } else {
                                    $payment_status = true;
                                }

                                //如果有付款，则录一条负付款冲平
                                if ($purchase_order['pay_status'] > 1) {
                                    $erp_payment_data = [
                                        'pay_money' => $purchase_order['payed_money'] * -1,
                                        'purchase_id' => $purchase_order['id'],
                                        'purchase_order_number' => $purchase_order['order_number'],
                                        'create_time' => DateTime(),
                                        'creator' => $this->getUserInfo('dealer_name'),
                                        'creator_id' => $this->getUserInfo('id'),
                                        'apply_pay_time' => date('Y-m-d', time()) . ' 23:59:59',
                                        'our_company_id' => session('erp_company_id'),
                                        'auditor' => $this->getUserInfo('dealer_name'),
                                        'auditor_id' => $this->getUserInfo('id'),
                                        'audit_time' => DateTime(),
                                        'status' => 10,
                                    ];
                                    $status_payment = $this->getModel('ErpPurchasePayment')->addPurchasePayment($erp_payment_data);
                                } else {
                                    $status_payment = true;
                                }

                                //将代采采购单审批流置为无效
                                $purchase_workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$purchase_order['id'], 'workflow_type'=>2, 'status'=>['neq', 2]])->order('id desc')->find();
                                if ($purchase_workflow && $purchase_workflow['status'] == 1) {
                                    $purchase_workflow['status'] = 2;
                                    $purchase_work_status = $this->getModel('ErpWorkflow')->where(['id'=>$purchase_workflow['id']])->save($purchase_workflow);
                                } else {
                                    $purchase_work_status = true;
                                }

                                //保存操作到采购单日志
                                $purchase_log_data = [
                                    'purchase_id' => $purchase_order['id'],
                                    'purchase_order_number' => $purchase_order['order_number'],
                                    'log_type' => 15,
                                    'log_info' => serialize($purchase_order),
                                    'create_time' => currentTime(),
                                    'operator' => $this->getUserInfo('dealer_name'),
                                    'operator_id' => $this->getUserInfo('id'),
                                ];
                                $purchase_log_status = $this->getModel('ErpPurchaseLog')->add($purchase_log_data);

                                if (!$stock_in_status || !$purchase_stock_status || !$purchase_order_status || !$status_payment || !$purchase_work_status) {
                                    break;
                                }
                            }
                        }
                    }
                }

                //查看是否有对应的出库单
                $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number'=>$sale_order_info['order_number'],'source_object_id'=>$sale_order_info['id']])->find();
                if ($stock_out_info) {
                    $stock_out_data = [
                        'outbound_status' => 2,
                        'update_time' => currentTime(),
                    ];
                    $stock_out_where = [
                        'source_number'=>$sale_order_info['order_number'],
                        'source_object_id'=>$sale_order_info['id'],
                        'outbound_type'=>1,
                    ];
                    $stock_out_status = $this->getModel('ErpStockOut')->saveStockOut($stock_out_where, $stock_out_data);
                } else {
                    $stock_out_status = true;
                }


                //修改销售单状态
                $sale_order_data = [
                    'order_status' => 2,
                    'collection_status' => 1,
                    'collected_amount' => 0,
                    'outbound_quantity' => 0,
                    'is_void' => 1,
                    'updater' => $this->getUserInfo('id'),
                    'update_time' => DateTime()
                ];
                $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($id)], $sale_order_data);

                //如果有收款，则录一条负收款冲平
                if ($sale_order_info['collection_status'] > 1) {
                    $erp_collect_data = [
                        'collect_money' => $sale_order_info['collected_amount'] * -1,
                        'order_collected_money' => 0,
                        'sale_order_id' => $sale_order_info['id'],
                        'sale_order_number' => $sale_order_info['order_number'],
                        'creator' => $this->getUserInfo('dealer_name'),
                        'creator_id' => $this->getUserInfo('id'),
                        'create_time' => DateTime(),
                        'collect_time' => DateTime(),
                        'status' => 1,
                    ];
                    $status_collection = $this->getModel('ErpSaleCollection')->addSaleCollection($erp_collect_data);
                } else {
                    $status_collection = true;
                }

                //将审批流置为无效
                $workflow = $this->getModel('ErpWorkflow')->where(['workflow_order_id'=>$id, 'workflow_type'=>1, 'status'=>['neq', 2]])->order('id desc')->find();
                if ($workflow && $workflow['status'] == 1) {
                    $workflow['status'] = 2;
                    $work_status = $this->getModel('ErpWorkflow')->where(['id'=>$workflow['id']])->save($workflow);
                } else {
                    $work_status = true;
                }

                //保存操作到销售单日志
                $log_data = [
                    'sale_order_id' => $sale_order_info['id'],
                    'sale_order_number' => $sale_order_info['order_number'],
                    'log_type' => 18,
                    'log_info' => serialize($sale_order_info),
                ];
                $log_status = $this->addSaleOrderLog($log_data);

                if ($sale_order_status && $work_status != false && $log_status && $status_collection && $stock_status
                    && $stock_out_status !== false && $stock_in_status && $purchase_stock_status && $purchase_order_status
                    && $status_payment && $payment_status && $purchase_work_status && $purchase_log_status) {
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
        }
        cancelCacheLock('ErpSale/rollBackSaleOrder');
        return $result;

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
            $data['data'] = $this->getModel('ErpUpdatePriceLog')->getUpdatePriceLogList(['order_id' => intval($id), 'order_type'=>1], $field, $_REQUEST['start'], $_REQUEST['length']);
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
     * 销售单二次定价
     * @param $param
     * @author xiaowen
     * @time 2017-7-17
     * @return array $data
     */
    public function updatePrice($param = []){
        $data = [];

        if($param['id'] && trim($param['price'])){
            $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>intval($param['id'])]);
            $collection_status_check  = $order_info['is_update_price'] == 2 ? 10 : 4;
            log_info('参数'. var_export($param, true));
            if($order_info['price'] == $param['price']){
                $data['status'] = 2;
                $data['message'] = '更新价格不能与原价格一致';
            } else if(trim($param['remark']) == ''){
                $data['status'] = 3;
                $data['message'] = '请输入二次定价备注信息';
            } else if($order_info['order_status'] != 10){
                $data['status'] = 4;
                $data['message'] = '订单不是已确认,无法修改价格';
            }else if($order_info['invoice_status'] != 1){
                $data['status'] = 5;
                $data['message'] = '订单不是未收票,无法修改价格';
            }
            //update by guanyu @ 2017-09-04 已退货订单无法二次定价
            else if($order_info['is_returned'] == 1){
                $data['status'] = 8;
                $data['message'] = '已退货订单无法二次定价';
            }
            else if($order_info['collection_status'] == 10 && $order_info['is_update_price'] == 1){
                $data['status'] = 6;
                $data['message'] = '二次定价已处理,无法再修改价格';
            }
            else if($order_info['collection_status'] != $collection_status_check){
                $arr = [
                    4 => '部分收款',
                    10 => '已收款',
                ];
                $data['status'] = 7;
                $data['message'] = '订单不是'.$arr[$collection_status_check].',无法修改价格';

            } else{
                if (getCacheLock('ErpSale/updatePrice')) return ['status' => 99, 'message' => $this->running_msg];
                setCacheLock('ErpSale/updatePrice', 1);

                M()->startTrans();
                //-------------------------------更新订单信息-------------------------------------------------

                //查询该订单的二次定价修改日志，判断是否需要根据订单的原始单价
                $update_price_log = $this->getModel('ErpUpdatePriceLog')->getUpdatePriceLogList(['order_id' => intval($param['id']), 'order_type'=>1]);
                $order_data = [
                    'update_time' => currentTime(),
                    'is_update_price' => 1,
                    'collection_status' => 4,
                    'price' => setNum(trim($param['price'])),
                    'order_amount' => setNum(round(getNum($order_info['buy_num']) * $param['price'], 2)) + $data['delivery_money'],

                ];
                //如果之前没有修改过价格，则要保存订单的原始价格。否则不用保存
                if(empty($update_price_log)){
                    $order_data['original_price'] = $order_info['price'];
                }
                //如果订单当前是二次定价状态，修改的价格与原始价格相同，则要取消二次定价标识
                if($order_info['is_update_price'] == 1 && setNum(trim($param['price'])) == $order_info['original_price']){
                    $order_data['is_update_price'] = 2;

                }
                //如果已收金额 等于 当前的订单金额 则重置订单收款状态为已收款
                if($order_info['collected_amount'] == $order_data['order_amount']){
                    $order_data['collection_status'] = 10;
                }
                $order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id'=>$param['id']], $order_data);
                //-------------------------------end 更新订单信息----------------------------------------------
                //--------------------------------保存变价操作日志---------------------------------------------
                $update_price_log_data = [
                    'order_type' => '1',
                    'order_id' => $order_info['id'],
                    'order_number' => $order_info['order_number'],
                    'old_price' => $order_info['price'],
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

                cancelCacheLock('ErpSale/updatePrice');
            }

        }
        return $data;
    }

    /**
     * 验证销售是否满足退款条件
     * @param $order_info
     * @author guanyu
     * @time 2017-08-17
     * @return bool
     */
    public function checkOrderCanReturn($id)
    {
        //销售单信息
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>$id]);

        if ( $order_info['business_type'] == 4 ) {
            return ['status' => 6,'message' => '该销售单 属于 内部交易单，不允许生成销售退货单！'];
        }

        //已收款未开票才可退款
        if ($order_info['collection_status'] != 10 || $order_info['invoice_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '销售退货单无法创建，销售单必须满足已收款，未开票，涉及开票或部分开票必须通知财务红冲发票，再进行创建'
            ];
            return $result;
        }

        //查询该销售单是否已出库
        if ($order_info['outbound_quantity'] == 0) {
            $result = [
                'status' => 3,
                'message' => '没有出库记录的销售单不允许创建销退'
            ];
            return $result;
        }

        //一笔销售单只能创建一笔销售退货单
        if ($order_info['is_returned'] == 1) {
            $result = [
                'status' => 4,
                'message' => '一笔销售单只能创建一笔对应的销售退货单'
            ];
            return $result;
        }

        //查询该销售单是否有未审核的出库单
        $stock_out_info = $this->getModel('ErpStockOut')->getOneStockOut(['source_number'=>$order_info['order_number'],'source_object_id'=>$order_info['id'],'outbound_status'=>1]);
        if ($stock_out_info) {
            $result = [
                'status' => 5,
                'message' => '该订单还有未处理的出库单，请取消相应的未审核出库单后再退货'
            ];
            return $result;
        }

        $result = [
            'status' => 1,
            'message' => '该销售单可退款'
        ];
        return $result;
    }

    /**
     * 验证销售单是否使用余额抵扣
     * @param $order_info
     * @author guanyu
     * @time 2017-11-21
     * @return bool
     */
    public function checkBalanceDeduction($id)
    {
        //销售单信息
        $order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>$id]);

        if ($order_info['collection_status'] == 1) {
            //订单未收款
            $result = [
                'status' => 1,
                'message' => '该销售单无余额抵扣'
            ];
        } else {
            //查询订单所有收款信息中利用余额抵扣的数据
            $where = [
                'sale_order_id' => $id,
                'sale_order_number' => $order_info['order_number'],
                'balance_deduction' => ['neq',0]
            ];
            $sale_collection = $this->getModel('ErpSaleCollection')->findSaleCollection($where);
            if ($sale_collection) {
                $result = [
                    'status' => 2,
                    'message' => '该销售单有余额抵扣,请在作废后重新录单充值账户'
                ];
            } else {
                $result = [
                    'status' => 1,
                    'message' => '该销售单无余额抵扣'
                ];
            }
        }
        return $result;
    }


    /**
     * 生成销售单审批步骤(2018.7.2改造)
     * @param array $sale_order_info
     * @author xiaowen
     * @time 2018-07-02
     * @return bool $check_status
     */
    public function createWorkflowNew($sale_order_info = []){
        $data['status'] = false; //审批流程创建状态
        $data['check_order'] = 0; //是否复核订单状态
        $data['message'] = '';
        if($sale_order_info){
            //销售单类型为属地，走营销中心销售单审批
            if($sale_order_info['business_type'] == 1){
                //数量大于等于5，小于500 走二级审批
                if($sale_order_info['buy_num'] >= setNum(5) && $sale_order_info['buy_num'] < setNum(500)){
                    $workflow_step = dependencyPurchaseSaleWorkflow(1);
                }else{  //其他情况 走三级审批
                    $workflow_step = dependencyPurchaseSaleWorkflow(2);
                }
            }else if($sale_order_info['business_type'] == 2){   //销售单类型为大宗，走供应链销售单审批
                $workflow_step = gylSaleWorkflow();
            }else if($sale_order_info['business_type'] == 3){   //销售单类型为小十代，走小十代销售单审批
                $workflow_step = LabECOPurchaseSaleWorkflow(1);
            }

            $workflow_data = [
                'workflow_type' => 1,
                'workflow_order_number' => $sale_order_info['order_number'],
                'workflow_order_id' => $sale_order_info['id'],
                'our_company_id' => $sale_order_info['our_company_id'],
                'creater' => $sale_order_info['dealer_name'],
                'creater_id' => $sale_order_info['dealer_id'],
            ];
            //print_r($workflow_data);
            //var_dump($this->getEvent('ErpWorkflow'));
            $work_status = $workflow_id = $this->getEvent('ErpWorkFlow')->addWorkFlow($workflow_data);

            $step_status_data = $this->getEvent('ErpWorkFlow')->createWorkflowStepData($workflow_id, $workflow_step, $sale_order_info, 1);

            $data['status'] = $step_status_data['status'] == 1 && $work_status ? true : false;
            $data['message'] = $step_status_data['message'];

        }
        return $data;
    }
}
