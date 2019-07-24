<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpShippingEvent extends BaseController
{

    /** --------------------------------------------------文哥------------------------------------------------------- */



    /** --------------------------------------------------乾斌------------------------------------------------------- */
    /**
     * 配送单需求池
     * @author qianbin
     * @time 2018-03-21
     */
    public function shippingDemandPool($params = [])
    {
        # 如果业务单据类型为空，则返回空
        if (!in_array(intval($params['order_type']),[1,2,3])) {
            $data['data'] = [];
            $data['recordsFiltered'] = 0;
            $data['recordsTotal']    = 0;
            return $data;
        }
        # 筛选条件
        if(trim($params['order_number']) && $params['order_type'] == 1){
            $where['so.outbound_apply_code'] = ['like','%'.trim($params['order_number']).'%'];
        }else if(trim($params['order_number']) && $params['order_type'] == 2){
            $where['so.outbound_code'] = ['like','%'.trim($params['order_number']).'%'];
        }else if(trim($params['order_number']) && $params['order_type'] == 3){
            // $where['si.storage_code'] = ['like','%'.trim($params['order_number']).'%'];
            // 替换成 入库申请单
            $where['si.storage_apply_code'] = ['like','%'.trim($params['order_number']).'%'];
        }
        if(trim($params['source_order_number'])){
            $where['so.source_number'] = ['like','%'.trim($params['source_order_number']).'%'];
        }

        if( trim($params['creater']) ){
            $dealer_where['dealer_name'] = ['like','%'.trim($params['creater']).'%'];
            $dealer_arr = $this->getModel('Dealer')->where($dealer_where)->getField('dealer_name,id');
            if ( !empty($dealer_arr) ) {
                $where['o.creater'] = ['in',$dealer_arr];
            } else {
                $where['o.creater'] = ['eq',0];
            }
        }

        if ( isset($params['start_time']) && !empty($params['start_time']) &&  isset($params['end_time']) && !empty($params['end_time']) ) {
            $where['so.create_time'] = ['between',[trim($params['start_time']),trim($params['end_time']." 23:59:59")]];
        } else if ( isset($params['start_time']) && !empty($params['start_time']) ) {
            $where['so.create_time'] = ['GT',trim($params['start_time'])];
        } elseif ( isset($params['end_time']) && !empty($params['end_time']) ) {
            $where['so.create_time'] = ['ELT',trim($params['end_time']." 23:59:59")];
        }

        if(intval($params['province']) > 0  && intval($params['region']) <= 0 ){
            $city_id = D('Area')->where(['parent_id' => intval($params['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['so.region'] = ['in',$city_id];
        }
        if (intval($params['region']) > 0) {
            $where['so.region'] = intval($params['region']);
        }

        //分账套显示
        $where['o.our_company_id'] = session('erp_company_id');
        switch (intval($params['order_type'])){
            case 1:
                # 获取销售单关联出库信息
                $data = $this->getSaleOrderInfo($params,$where);

                break;
            case 2:
                # 获取调拨单关联入库信息
                $data = $this->getAllocationOrderInfo($params);

                break;
            case 3:
                # 获取采购单关联入库信息
                $data = $this->getPurchaseOrderInfo($params,$where);

                break;
            default:
                $data = [];
                break;
        }
        # 组装数据 ---------------------------------------
        if(count($data['data']) > 0){
            $dealer_id = array_unique(array_column($data['data'],'creater'));
            $dealer    = $this->getModel('Dealer')->where(['id' => ['in' , $dealer_id]])->getField('id,dealer_name',true);
            $city_data        = provinceCityZone()['city'];
            $our_company_name = getAllErpCompany();
            foreach ($data['data'] as $k => $v ){
                $data['data'][$k]['outbound_code']       = isset($v['outbound_code']) ? $v['outbound_code'] : '--';
                $data['data'][$k]['region']              = $city_data[$v['region']];
                $data['data'][$k]['actual_outbound_num'] = $v['actual_outbound_num'] > 0 ? getNum($v['actual_outbound_num']) : 0;
                $data['data'][$k]['delivery_method']     = ShippingType($params['order_type'] == 3 ? 2 : 1) ;//ShippingType($v['delivery_method']);
                $data['data'][$k]['outbound_type_font']  = SourceType($v['outbound_type']);
                $data['data'][$k]['our_company_name']    = $our_company_name[$v['our_company_id']];
                $data['data'][$k]['creater_name']        = $dealer[$v['creater']];
                $data['recordsFiltered'] = $data['recordsTotal'];
            }
        }else{
            $data['data'] = [];
            $data['recordsFiltered'] = 0;
            $data['recordsTotal']    = 0;
        }
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /*
     * ------------------------------------------
     * 获取销售单关联出库信息
     * Author：qianbin        Time：2018-03-21
     * ------------------------------------------
     */
    private function getSaleOrderInfo($params = [],$where = [])
    {
        $data = [];
        $where['o.order_status']     = 10 ;
        $where['o.delivery_method']  = 1 ; 
                /* ----- 类型 1：销售单 ------- */
        // $where['so.outbound_type']   = 1 ; // 出库单
                /* - 配送单：1 已生成 2 未生成 - */
        // $where['so.is_shipping']     = 2 ;
        # 仓管二期
        # 除已取消的出库单，都要进入配送需求 qianbin 2018-04-26
                /* -出库单状态 1 未审核  2 已取消 10 已审核 - */
        // $where['so.outbound_status'] = ['neq',2]; // 出库单
                /* - 是否红冲 1是 2否 - */
        // $where['so.is_reverse']      = 2;
                /* - 被红冲状态 1 已被戏冲 2未被红冲 - */
        // $where['so.reversed_status'] = 2;
        $where['o.our_company_id']   = session('erp_company_id');
        // $field = 'so.id,so.create_time as audit_time,so.outbound_code,so.source_number,so.outbound_type,o.delivery_method,o.creater,so.region,so.our_company_id,g.goods_code,so.actual_outbound_num';
        // $data  = $this->getModel('ErpStockOut')->getSaleStockOutPoolList($where, $field, $params['start'], $params['length']);
        $order = "so.id desc";
        /******************************************
            @ Content 出库单 更改为 出库销售单
            @ Time    2019-02-25
            @ Author  YF
        *******************************************/
        $where['so.is_shipping']         = 2 ;
        $where['so.outbound_apply_type'] = 1; // 出库申请单
        $where['so.status']              = ['neq',2];
        $field = 'so.id,so.create_time as audit_time,so.outbound_apply_code as outbound_code,so.source_number,so.outbound_apply_type as outbound_type,o.delivery_method,o.creater,so.region,so.our_company_id,g.goods_code,so.outbound_apply_num as actual_outbound_num';
        $data['recordsTotal'] = $this->getModel('ErpStockOutApply')->alias('so')
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number','left')
            ->count();
        $data['data'] = $this->getModel('ErpStockOutApply')->alias('so')
        ->field($field)
        ->where($where)
        ->join('oil_erp_sale_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
        ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
        ->limit($params['start'],$params['length'])
        ->order($order)
        ->select();
        /******************************************
                         END
        *******************************************/
        return $data ;
    }

    /*
     * ------------------------------------------
     * 获取调拨单关联出库信息
     * Author：qianbin        Time：2018-03-21
     * ------------------------------------------
     */
    private function getAllocationOrderInfo($params = [],$where = [])
    {
        $data = [];
        $where = [];
        # 重置筛选条件
        if(trim($params['source_order_number'])){
            $where['o.order_number'] = ['like','%'.trim($params['source_order_number']).'%'];
        }
        if( trim($params['creater']) ){
            $dealer_where['dealer_name'] = ['like','%'.trim($params['creater']).'%'];
            $dealer_arr = $this->getModel('Dealer')->where($dealer_where)->getField('dealer_name,id');
            if ( !empty($dealer_arr) ) {
                $where['o.creater'] = ['in',$dealer_arr];
            } else {
                $where['o.creater'] = ['eq',0];
            }

        }
        if (isset($params['start_time']) || isset($params['end_time'])) {
            if (trim($params['start_time']) && !trim($params['end_time'])) {

                $where['o.audit_time'] = ['egt', trim($params['start_time'])];
            } else if (!trim($params['start_time']) && trim($params['end_time'])) {

                $where['o.audit_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($params['end_time']))+3600*24)];
            } else if (trim($params['start_time']) && trim($params['end_time'])) {

                $where['o.audit_time'] = ['between', [trim($params['start_time']), date('Y-m-d H:i:s', strtotime(trim($params['end_time']))+3600*24)]];
            }
        }
        if(intval($params['province']) > 0  && intval($params['region']) <= 0 ){
            $city_id = D('Area')->where(['parent_id' => intval($params['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['o.in_region'] = ['in',$city_id];
        }
        if (intval($params['region']) > 0) {
            $where['o.in_region'] = intval($params['region']);
        }
        //分账套显示
        $where['o.our_company_id']  = session('erp_company_id');
        # 仓管二期
        # 已确认、未入库、配送的调拨单都会进入需求
        # 调拨类型：城市->服务商  服务商->服务商
        $where['o.delivery_method'] = 1 ;
        $where['o.status']          = 10;
        $where['o.is_shipping']     = 2;
        # $where['_string']    = 'o.allocation_type = 2 or o.allocation_type = 1 ';

        $field = 'o.id,o.audit_time,o.order_number as source_number,o.delivery_method,o.creater,o.in_region as region,o.our_company_id,o.goods_id,o.num as actual_outbound_num,g.goods_code,2 as outbound_type';
        $data  = $this->getModel('ErpNewAllocationOrder')->getAllocationPoolList($where, $field, $params['start'], $params['length']);
        return $data ;

    }

    /*
     * ------------------------------------------
     * 获取采购单关联出库信息
     * Author：qianbin        Time：2018-03-21
     * ------------------------------------------
     */
    private function getPurchaseOrderInfo($params = [],$where =[])
    {
        unset($where['o.our_company_id']);
        $data = [];
        $where['o.order_status']     = 10 ;
        $where['o.delivery_method']  = 1 ;
        $where['o.our_buy_company_id']   = session('erp_company_id');
        // $where['si.storage_type']    = 1 ;
        // $where['si.is_shipping']     = 2 ;
        // $where['si.storage_status']  = ['neq',2];
        // $where['si.is_reverse']      = 2;
        // $where['si.reversed_status'] = 2;

        # 配合最后处理数据，这里和出库单统一别名
        // $field = 'si.id,si.create_time as audit_time,si.storage_code as outbound_code,si.source_number,3 as outbound_type,o.delivery_method,o.creater,si.region,si.our_company_id,g.goods_code,si.actual_storage_num as actual_outbound_num';
        // $data  = $this->getModel('ErpStockIn')->getPurchaseOrderPoolList($where, $field, $params['start'], $params['length']);
        /***********************************
            @ Content 入库单更改为入库申请单
            @ Author  YF
            @ Time    2019-04-17
        ************************************/
        if ( isset($where['so.region']) ) {
            $where['si.region'] = $where['so.region'];
            unset($where['so.region']);
        }

        if ( isset($where['so.create_time']) ) {
            $where['si.create_time'] = $where['so.create_time'];
            unset($where['so.create_time']);
        }
        
        if ( isset($where['so.source_number']) ) {
            $where['si.source_number'] = $where['so.source_number'];
            unset($where['so.source_number']);
        }
        $where['si.storage_apply_type']    = 1 ;
        $where['si.status']                = ['neq',2];
        $where['si.is_shipping']           = 2 ;
        // si 代表入库申请单 o 采购单 g 商品
        $field = 'si.id,si.create_time as audit_time,si.storage_apply_code as outbound_code,si.source_number,3 as outbound_type,o.delivery_method,o.creater,si.region,si.our_company_id,g.goods_code,si.storage_apply_num as actual_outbound_num';
        $data['recordsTotal'] = $this->getModel('ErpStockInApply')
            ->alias('si')
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number','left')
            ->count();
        $data['data'] = $this->getModel('ErpStockInApply')
            ->alias('si')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and o.order_number = si.source_number','left')
            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->limit($params['start'], $params['length'])
            ->order('si.id desc')
            ->select();
        return $data ;
    }

    /** --------------------------------------------------冠宇------------------------------------------------------- */

    /**
     * 配送单列表
     * @author guanyu
     * @time 2018-03-19
     */
    public function ShippingOrderList($param = [])
    {
        $where = [];

        //订单号
        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        //出库单号
        if (trim($param['source_number'])) {
            $where['o.source_number'] = ['like', '%' . trim($param['source_number']) . '%'];
        }
        //来源单号
        if (trim($param['source_order_number'])) {
            $where['o.source_order_number'] = ['like', '%' . trim($param['source_order_number']) . '%'];
        }
        //时间区间
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['o.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['o.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        // 单据类型
        if(trim($param['order_type'])){
            $where['o.source_type'] = intval($param['order_type']);
        }
        //城市
        if(intval($param['province']) > 0  && intval($param['region']) <= 0 ){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['o.region'] = ['in',$city_id];
        }
        if (intval($param['region']) > 0) {
            $where['o.region'] = intval($param['region']);
        }
        //订单状态
        if (trim($param['status'])) {
            $where['o.order_status'] = intval(trim($param['status']));
        }
        //当前登陆选择的我方公司
        $where['o.our_company_id'] = session('erp_company_id');

        $field = 'o.*,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        if ($param['export']) {
            $data = $this->getModel('ErpShippingOrder')->getAllShippingOrderList($where, $field);
        } else {
            $data = $this->getModel('ErpShippingOrder')->getShippingOrderList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];

            $creater_arr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', array_unique(array_column($data['data'],'creater'))]])->getField('id,dealer_name');
            $business_creater_arr = M('Dealer')->where(['is_available' => 0, 'id' => ['in', array_unique(array_column($data['data'],'business_creater'))]])->getField('id,dealer_name');
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['source_number'] = empty($value['source_number']) ? '--' : $value['source_number'];
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['shipping_num'] = $value['shipping_num'] > 0 ? getNum($value['shipping_num']) : '0';
                $data['data'][$key]['unloading_num'] = $value['unloading_num'] > 0 ? getNum($value['unloading_num']) : '0';
                $data['data'][$key]['temperature'] = $value['temperature'] > 0 ? getNum($value['temperature']) : '0';
                $data['data'][$key]['source_type'] = SourceType($value['source_type']);
                $data['data'][$key]['shipping_type'] = ShippingType($value['shipping_type']);
                $data['data'][$key]['order_status_font'] = RechargeOrderStatus($value['order_status']);
                $data['data'][$key]['order_status'] = RechargeOrderStatus($value['order_status'],true);
                $data['data'][$key]['distribution_status_font'] = DistributionStatus($value['distribution_status']);
                $data['data'][$key]['distribution_status'] = DistributionStatus($value['distribution_status'],true);
                $data['data'][$key]['creater_name'] = $creater_arr[$value['creater']];
                $data['data'][$key]['business_creater_name'] = $business_creater_arr[$value['business_creater']];
                $data['data'][$key]['voucher_status_font'] = VoucherStatus($value['voucher_status']);
                $data['data'][$key]['voucher_status'] = VoucherStatus($value['voucher_status'],true);

                $data['data'][$key]['user_name'] = empty($value['user_name']) ? '--' : $value['user_name'];
                $data['data'][$key]['user_phone'] = empty($value['user_phone']) ? '--' : $value['user_phone'];
                $data['data'][$key]['company_name'] = empty($value['company_name']) ? '--' : $value['company_name'];
            }
        } else {
            $data['data'] = [];
        };
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 新增配送单
     * @author guanyu
     * @time 2018-03-19
     */
    public function addShippingOrder($param)
    {
        //参数验证
        if (empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['shipping_address']) == '') {
            $result = [
                'status' => 2,
                'message' => '请填写提油地址'
            ];
            return $result;
        }
        if (strHtml($param['receiving_address']) == '') {
            $result = [
                'status' => 3,
                'message' => '请填写送油地址'
            ];
            return $result;
        }
        if (strHtml($param['shipper']) == '') {
            $result = [
                'status' => 4,
                'message' => '请填写承运商'
            ];
            return $result;
        }
        if (strHtml($param['driver_name']) == '') {
            $result = [
                'status' => 5,
                'message' => '请填写司机姓名'
            ];
            return $result;
        }
        if (strHtml($param['driver_phone']) == '') {
            $result = [
                'status' => 6,
                'message' => '请填写司机联系方式'
            ];
            return $result;
        }
        if (strHtml($param['car_number']) == '') {
            $result = [
                'status' => 7,
                'message' => '请填写车牌号'
            ];
            return $result;
        }
        if (strHtml($param['business_shipping_time']) == '') {
            $result = [
                'status' => 13,
                'message' => '请填写业务需求配送时间'
            ];
            return $result;
        }
        if (intval($param['shipping_county']) == 0 || intval($param['shipping_region']) == 0 || intval($param['shipping_province']) == 0 ) {
            $result = [
                'status' => 14,
                'message' => '提油城市信息有误，请刷新后重试！'
            ];
            return $result;
        }
        if (intval($param['receiving_county']) == 0 || intval($param['receiving_region']) == 0 || intval($param['receiving_province']) == 0 ) {
            $result = [
                'status' => 15,
                'message' => '送油城市信息有误，请刷新后重试！'
            ];
            return $result;
        }
        if (strHtml($param['shipper_phone']) == '') {
            $result = [
                'status' => 16,
                'message' => '承运方联系电话有误，请刷新后重试！'
            ];
            return $result;
        }
        if (strHtml($param['shipping_phone']) == '') {
            $result = [
                'status' => 17,
                'message' => '提油地址电话有误，请刷新后重试！'
            ];
            return $result;
        }
        if (strHtml($param['receiving_phone']) == '') {
            $result = [
                'status' => 18,
                'message' => '收油地址电话有误，请刷新后重试！'
            ];
            return $result;
        }
        // 检查订单是否符合状态
        $order_info = $this->getSourceOrderInfo($param);
        if ($order_info['status'] != 1 ) {
            $result = [
                'status' => $order_info['status'],
                'message' => $order_info['message']
            ];
            return $result;
        }
        //获取来源单据信息
        $source_order_info = $order_info['data'];
        if (getCacheLock('ErpShipping/addShippingOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpShipping/addShippingOrder', 1);

        M()->startTrans();

        # 如果为调拨单，并且调拨方式是城市仓->服务商 推送到front  qianbin   2018-03-22
        $is_send_message = (isset($source_order_info['allocation_type']) && intval($source_order_info['allocation_type']) == 1 ) ? 1 : 2 ;
        //新增单据
        $data = [
            'order_number'              => erpCodeNumber(17)['order_number'],
            'source_number'             => $param['source_number'],
            'source_order_number'       => $source_order_info['source_order_number'],
            'source_type'               => $param['source_type'],
            'shipping_type'             => $param['source_type'] == 3 ? 2 : 1,
            'is_retail'                 => $param['source_type'] == 2 ? 1 : 2,
            'is_send_message'           => $is_send_message,
            'our_company_id'            => session('erp_company_id'),
            'goods_id'                  => $source_order_info['goods_id'],
            'user_id'                   => $source_order_info['user_id'] ? $source_order_info['user_id'] : 0,//user_id 问题
            'user_name'                 => $source_order_info['user_name'] ? $source_order_info['user_name'] : '',
            'user_phone'                => $source_order_info['user_phone'] ? $source_order_info['user_phone'] : 0,
            'company_id'                => $source_order_info['company_id'] ? $source_order_info['company_id'] : 0,
            'company_name'              => $source_order_info['company_name'] ? $source_order_info['company_name'] : '',
            'shipping_num'              => $source_order_info['shipping_num'],
            'loading_num'               => setNum($param['loading_num']),
            'unloading_num'             => setNum($param['unloading_num']),
            'temperature'               => setNum($param['temperature']),
            'density'                   => $param['density'],
            'distribution_status'       => 1,
            'order_status'              => 1,
            'region'                    => $param['region'],
            'dealer_id'                 => $this->getUserInfo('id'),
            'dealer_name'               => $this->getUserInfo('dealer_name'),
            'shipping_address'          => $param['shipping_address'],
            'receiving_address'         => $param['receiving_address'],
            'shipper'                   => $param['shipper'],
            'driver_name'               => $param['driver_name'],
            'driver_phone'              => $param['driver_phone'],
            'car_number'                => $param['car_number'],
            'freight'                   => setNum($param['freight']),
            'incidental'                => setNum($param['incidental']),
            'loss_num'                  => setNum($param['loss_num']),
            'seal_number'               => $param['seal_number'],
            'remark'                    => $param['remark'],
            'business_create_time'      => $source_order_info['audit_time'],
            'business_creater'          => $source_order_info['creater'],
            'create_time'               => currentTime(),
            'creater'                   => $this->getUserInfo('id'),
            'business_shipping_time'    => $param['business_shipping_time'],
            // 配送单对接第三方tms qianbin 2018-09-04
            'shipping_city'             => intval($param['shipping_province']).'_'.intval($param['shipping_region']).'_'.intval($param['shipping_county']),
            'shipping_phone'            => trim($param['shipping_phone']),
            'receiving_city'            => intval($param['receiving_province']).'_'.intval($param['receiving_region']).'_'.intval($param['receiving_county']),
            'receiving_phone'           => trim($param['receiving_phone']),
            'shipper_phone'             => trim($param['shipper_phone']),
        ];
        # 如果为调拨单，则确认出库时，更新此字段
        if($param['source_type'] != 2){
            $data['actual_shipping_num']    = $source_order_info['shipping_num'];
        }
        $order_status = $id = $this->getModel('ErpShippingOrder')->addShippingOrder($data);

        //新增log
        $log_data = [
            'shipping_order_id' => $id,
            'shipping_order_number' => $data['order_number'],
            'log_info' => serialize($data),
            'log_type' => 1,
        ];
        $log_status = $this->addShippingOrderLog($log_data);

        // 修改原出库单状态
        // 调拨单修改调拨单上的状态
        // 销售采修改出库单上的状态  is_shipping
        $source_status = 0 ;
        switch ($data['source_type']){
            case 1: # 销售单
                // $where = [
                //     'outbound_code' => $data['source_number']
                // ];
                // $data = [
                //     'is_shipping' => 1,
                //     'update_time' => currentTime(),
                // ];
                // $source_status = $this->getModel('ErpStockOut')->saveStockOut($where,$data);
                /***************************************
                    @ Content 出库单更改为出库申请单数据
                ****************************************/
                $where = [
                    'outbound_apply_code' => $data['source_number']
                ];
                $data = [
                    'is_shipping' => 1,
                    'update_time' => currentTime(),
                ];
                $source_status = $this->getModel('ErpStockOutApply')->where($where)->save($data);
                /***************************************
                                END
                ****************************************/
                break;
            case 2: # 调拨单
                $where = [
                    'order_number' => $data['source_order_number']
                ];
                $data = [
                    'is_shipping' => 1,
                    'update_time' => currentTime(),
                ];
                $source_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder($where,$data);
                break;
            case 3: # 采购单
                // $where = [
                //     'storage_code' => $data['source_number']
                // ];
                // $data = [
                //     'is_shipping' => 1,
                //     'update_time' => currentTime(),
                // ];
                // $source_status = $this->getModel('ErpStockIn')->saveStockIn($where,$data);
                /***********************************
                    @ Content 入库单更改为入库申请单数据
                ************************************/
                $where = [
                    'storage_apply_code' => $data['source_number']
                ];
                $data = [
                    'is_shipping' => 1,
                    'update_time' => currentTime(),
                ];
                $source_status = $this->getModel('ErpStockInApply')->where($where)->save($data);
                /***********************************
                                END
                ************************************/
                break;
            default:
                break;
        }
        if ($order_status && $log_status && $source_status) {
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

        cancelCacheLock('ErpShipping/addShippingOrder');
        return $result;
    }

    /**
     * 根据单号和来源单据类型获取单据信息
     * @author guanyu
     * @time 2018-03-19
     */
    public function getSourceOrderInfo($param)
    {
        $result = ['status' => 20 , 'message' => '数据错误，请联系管理员！'];
        switch ($param['source_type']){
            //来源业务单据为销售单
            case 1:
                /*********************************
                    @ Content 出库单更改为 出库申请单
                    @ Time 2019-02-10
                    @ Author YF
                **********************************/
                $stock_out_apply_arr = $this->getModel('ErpStockOutApply')->where(['outbound_apply_code' => $param['source_number']])->find();
                $source_order_number = $stock_out_apply_arr['source_number'];

                $field = 'so.outbound_apply_code as source_number,so.outbound_apply_type as source_type,so.outbound_apply_num as shipping_num,
                so.create_time as audit_time,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
                o.order_number as source_order_number,o.region,o.company_id,o.creater,o.user_id,
                so.status,so.is_shipping,o.order_status';
                $where = [
                    'order_number'  => $source_order_number,
                    'outbound_apply_code' => $param['source_number']
                ];
                $source_order_info = $this->getModel('ErpSaleOrder')
                ->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_user u on o.user_id = u.id', 'left')
                ->join('oil_clients c on o.company_id = c.id', 'left')
                ->join('oil_erp_stock_out_apply so on o.order_number = so.source_number')
                ->find();
                /**********************************
                             END
                ***********************************/
                             /* -------------- 更改部分 start------------------ */
                // $source_order_number = $this->getModel('ErpStockOut')->getOneStockOut(['outbound_code' => $param['source_number']])['source_number'];
                // $where = [
                //     'order_number' => $source_order_number,
                //     'outbound_code' => $param['source_number']
                // ];
               
                //so.create_time as audit_time,c.company_name,u.user_name,u.user_phone,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
                // $field = 'so.outbound_code as source_number,so.outbound_type as source_type,so.actual_outbound_num as shipping_num,
                // so.create_time as audit_time,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
                // o.order_number as source_order_number,o.region,o.company_id,o.creater,o.user_id,
                // so.is_reverse,so.reversed_status,so.outbound_status,so.is_shipping,o.order_status';
                // $source_order_info = $this->getModel('ErpSaleOrder')->findSaleShippingOrder($where,$field);
                             /* -------------- 更改部分 END ------------------ */
                $userInfo = $this->getEvent('ErpCommon')->getUserData([$source_order_info['user_id']], 1);
                $companyInfo = $this->getEvent('ErpCommon')->getCompanyData([$source_order_info['company_id']], 1);
                $source_order_info['user_name'] = $userInfo[$source_order_info['user_id']]['user_name'];
                $source_order_info['user_phone'] = $userInfo[$source_order_info['user_id']]['user_phone'];
                $source_order_info['company_name'] = $companyInfo[$source_order_info['company_id']]['company_name'];
                # 检查单据是否合规
                if(empty($source_order_info)){
                    $result = [
                        'status'  => 21 ,
                        'message' => '来源单据异常，请查看！'
                    ];
                }else if($source_order_info['outbound_status'] == 2 ){
                    $result = [
                        'status'  => 22 ,
                        'message' => '来源出库单状态为已取消，请刷新后重试！'
                    ];
                /* -------------- 更改部分 start------------------ */
                // }else if($source_order_info['is_reverse'] == 1 || $source_order_info['reversed_status'] == 1 ){
                //     $result = [
                //         'status'  => 23 ,
                //         'message' => '来源出库单存在红冲情况，请检查！'
                //     ];
                /* -------------- 更改部分 END ------------------ */
                }else if($source_order_info['is_shipping'] == 1){
                    $result = [
                        'status'  => 24 ,
                        'message' => '来源出库单已生成配送单，请刷新后重试！'
                    ];
                }else if($source_order_info['order_status'] != 10){
                    $result = [
                        'status'  => 25 ,
                        'message' => '业务销售单据非已确认状态，请检查销售单状态！'
                    ];
                }else{
                    $result = [
                        'status' => 1 ,
                        'message'=> '单据正常！',
                        'data'   => $source_order_info
                    ];
                }

                break;
            //来源业务单据为调拨单
            case 2:
                /*
                    $where = [
                        'so.outbound_code' => $param['source_number'],
                    ];
                    $field = 'so.outbound_code as source_number,so.outbound_type as source_type,so.actual_outbound_num as shipping_num,
                    so.audit_time,ao.order_number as source_order_number,ao.in_region as region,ao.in_facilitator_id as company_id,ao.creater,
                    ao.allocation_type,ift.name as company_name,ifu.facilitator_user_id as user_id,ifu.name as user_name,
                    ifu.phone as user_phone,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
                    $source_order_info = $this->getModel('ErpAllocationOrder')->findAllocationShippingOrder($where,$field);
                */
                # 仓管二期，调拨单生成配送单前，未出库
                $where = [
                    'o.order_number' => $param['source_order_number'],
                ];

                $field = 'o.order_number as source_order_number,o.in_region as region,o.in_facilitator_id as company_id,
                o.allocation_type,o.creater,ift.supplier_name as company_name,ifu.id as user_id,ifu.user_name,
                ifu.user_phone,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
                2 as source_type,o.num as shipping_num,o.status,o.is_shipping,o.audit_time,s.storehouse_name,o.in_storehouse';
                $source_order_info = $this->getModel('ErpNewAllocationOrder')->findAllocationShippingOrder($where,$field);
                //print_r($source_order_info);
                if (!$source_order_info['company_name']) {
                    $source_order_info['company_name'] = $source_order_info['storehouse_name'];
                }
                if (!$source_order_info['company_id']) {
                    $source_order_info['company_id'] = $source_order_info['in_storehouse'];
                }
                # 检查单据是否合规
                if(empty($source_order_info)){
                    $result = [
                        'status'  => 21 ,
                        'message' => '来源调拨单据异常，请查看！'
                    ];
                }else if($source_order_info['is_shipping'] == 1){
                    $result = [
                        'status'  => 24 ,
                        'message' => '来源调拨单已生成配送单，请刷新后重试！'
                    ];
                }else if($source_order_info['status'] != 10){
                    $result = [
                        'status'  => 25 ,
                        'message' => '业务销售单据非已确认状态，请检查销售单状态！'
                    ];
                }else{
                    $result = [
                        'status' => 1 ,
                        'message'=> '单据正常！',
                        'data'   => $source_order_info
                    ];
                }
                break;
            //来源业务单据为采购单
            case 3:
                /*********************************
                    @ Content 出库单更改为 出库申请单
                    @ Time 2019-02-10
                    @ Author YF
                **********************************/
                $stock_in_apply = $this->getModel('ErpStockInApply')->where(['storage_apply_code' => $param['source_number']])->find();
                $source_order_number = $stock_in_apply['source_number'];
                $where = [
                    'o.order_number' => $source_order_number,
                    'si.storage_apply_code' => $param['source_number']
                ];
                $field = 'si.storage_apply_code as source_number,3 as source_type,si.storage_apply_num as shipping_num,
                si.create_time as audit_time,c.company_name,u.user_name,u.user_phone,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
                o.order_number as source_order_number,o.region,o.sale_company_id as company_id,o.creater,o.sale_user_id as user_id,
                si.status as storage_status,si.is_shipping,o.order_status';
                $source_order_info = $this->getModel('ErpPurchaseOrder')
                                        ->alias('o')
                                        ->field($field)
                                        ->where($where)
                                        ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                                        ->join('oil_user u on o.sale_user_id = u.id', 'left')
                                        ->join('oil_clients c on o.sale_company_id = c.id', 'left')
                                        ->join('oil_erp_stock_in_apply si on o.order_number = si.source_number')
                                        ->find();
                /**********************************
                                END
                ************************************/
                /* --------------- 更新前 (作废)------------------ */
                // $source_order_number = $this->getModel('ErpStockIn')->getOneStockIn(['storage_code' => $param['source_number']])['source_number'];
                // $where = [
                //     'o.order_number' => $source_order_number,
                //     'si.storage_code' => $param['source_number']
                // ];
                // $field = 'si.storage_code as source_number,3 as source_type,si.actual_storage_num as shipping_num,
                // si.create_time as audit_time,c.company_name,u.user_name,u.user_phone,g.id as goods_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
                // o.order_number as source_order_number,o.region,o.sale_company_id as company_id,o.creater,o.sale_user_id as user_id,
                // si.is_reverse,si.reversed_status,si.storage_status,si.is_shipping,o.order_status';

                // $source_order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseShippingOrder($where,$field);
                /* --------------- END ------------------ */
                $userInfo    = $this->getEvent('ErpCommon')->getUserData([$source_order_info['user_id']], 2);
                $companyInfo = $this->getEvent('ErpCommon')->getCompanyData([$source_order_info['company_id']], 2);
                $source_order_info['user_name'] = $userInfo[$source_order_info['user_id']]['user_name'];
                $source_order_info['user_phone'] = $userInfo[$source_order_info['user_id']]['user_phone'];
                $source_order_info['company_name'] = $companyInfo[$source_order_info['company_id']]['company_name'];
                # 检查单据是否合规
                if(empty($source_order_info)) {
                    $result = [
                        'status' => 21,
                        'message' => '来源单据异常，请查看！'
                    ];
                }else{
                    $result = [
                        'status' => 1 ,
                        'message'=> '单据正常！',
                        'data'   => $source_order_info
                    ];
                }
                break;
            default:
                $source_order_info = [];
                break;
        }
        return $result;
    }

    /**
     * 编辑配送单
     * @author guanyu
     * @time 2018-03-19
     */
    public function updateShippingOrder($id,$param = [])
    {
        //参数验证
        if (!$id || empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['shipping_address']) == '') {
            $result = [
                'status' => 3,
                'message' => '请填写提油地址'
            ];
            return $result;
        }
        if (strHtml($param['receiving_address']) == '') {
            $result = [
                'status' => 4,
                'message' => '请填写送油地址'
            ];
            return $result;
        }
        if (strHtml($param['shipper']) == '') {
            $result = [
                'status' => 5,
                'message' => '请填写承运商'
            ];
            return $result;
        }
        if (strHtml($param['driver_name']) == '') {
            $result = [
                'status' => 6,
                'message' => '请填写司机姓名'
            ];
            return $result;
        }
        if (strHtml($param['driver_phone']) == '') {
            $result = [
                'status' => 7,
                'message' => '请填写司机联系方式'
            ];
            return $result;
        }
        if (strHtml($param['car_number']) == '') {
            $result = [
                'status' => 8,
                'message' => '请填写车牌号'
            ];
            return $result;
        }
        if (strHtml($param['business_shipping_time']) == '') {
            $result = [
                'status' => 14,
                'message' => '请填写业务需求配送时间'
            ];
            return $result;
        }

        if (getCacheLock('ErpShipping/updateShippingOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpShipping/updateShippingOrder', 1);

        M()->startTrans();

        //新增单据
        $data = [
            'loading_num'               => setNum($param['loading_num']),
            'unloading_num'             => setNum($param['unloading_num']),
            'temperature'               => setNum($param['temperature']),
            'density'                   => $param['density'],
            'shipping_address'          => $param['shipping_address'],
            'receiving_address'         => $param['receiving_address'],
            'shipper'                   => $param['shipper'],
            'driver_name'               => $param['driver_name'],
            'driver_phone'              => $param['driver_phone'],
            'car_number'                => $param['car_number'],
            'freight'                   => setNum($param['freight']),
            'incidental'                => setNum($param['incidental']),
            'loss_num'                  => setNum($param['loss_num']),
            'seal_number'               => $param['seal_number'],
            'remark'                    => $param['remark'],
            'business_shipping_time'    => $param['business_shipping_time'],
            'update_time'               => currentTime(),
            'updater'                   => $this->getUserInfo('id'),
            // 配送单对接第三方tms qianbin 2018-09-04
            'shipping_city'             => intval($param['shipping_province']).'_'.intval($param['shipping_region']).'_'.intval($param['shipping_county']),
            'shipping_phone'            => trim($param['shipping_phone']),
            'receiving_city'            => intval($param['receiving_province']).'_'.intval($param['receiving_region']).'_'.intval($param['receiving_county']),
            'receiving_phone'           => trim($param['receiving_phone']),
            'shipper_phone'             => trim($param['shipper_phone']),
        ];
        $order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder(['id' => $id],$data);

        $RechargeOrderInfo_new = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => $id]);
        //新增log
        $log_data = [
            'shipping_order_id' => $id,
            'shipping_order_number' => $RechargeOrderInfo_new['order_number'],
            'log_info' => serialize($RechargeOrderInfo_new),
            'log_type' => 2,
        ];
        $log_status = $this->addShippingOrderLog($log_data);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpShipping/updateShippingOrder');
        return $result;
    }

    /**
     * 审核配送单
     * @param array $param
     * @author guanyu
     * @time 2018-03-20
     * @return array
     */
    public function auditShippingOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => $id]);
        if ($order_info['order_status'] != 1) {
            $result = [
                'status' => 2,
                'message' => '该配送单不是未审核状态，无法审核',
            ];
            return $result;
        }

        if (getCacheLock('ErpShipping/auditShippingOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpShipping/auditShippingOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 3,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder(['id' => $id], $data);

        //新增log
        $log = [
            'shipping_order_id' => $order_info['id'],
            'shipping_order_number' => $order_info['order_number'],
            'log_info' => serialize($order_info),
            'log_type' => 4,
        ];
        $log_status = $this->addShippingOrderLog($log);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpShipping/auditShippingOrder');
        return $result;
    }

    /**
     * 取消配送单
     * @param array $param
     * @author guanyu
     * @time 2018-03-20
     * @return array
     */
    public function delShippingOrder($id,$param)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => $id]);
        if ($order_info['order_status'] == 2 ) {
            $result = [
                'status' => 2,
                'message' => '该配送单为已取消，无法再次取消',
            ];
            return $result;
        }
        if ($order_info['facilitator_in_time'] != '0000-00-00 00:00:00') {
            $result = [
                'status' => 3,
                'message' => '服务商待收货，无法取消！',
            ];
            return $result;
        }

        if ($order_info['distribution_status'] == 3 ) {
            $result = [
                'status' => 4,
                'message' => '该配送单已完成配送，无法取消',
            ];
            return $result;
        }
        $stock_out = [];
        # 调拨单的配送单取消要判断是否出库
        # 已出库的调拨单会更新收油单实际出库信息，所以已出库的调拨单，无法取消！
        # 如果已出库调拨单对应的配送单还可以取消，重新生成配送单时，无法再次更新实际出库信息。
        if($order_info['source_type'] == 2){
            $outbound_status  = $this->getModel('ErpNewAllocationOrder')->where(['order_number' => $order_info['source_order_number']])->getField('outbound_status');
            $stock_out = $outbound_status == 1 ? ['status' => 5 ,'message' => '该配送单对应单据已出库，无法取消！'] : [];
        }
        if(!empty($stock_out)) return $stock_out;

        if (getCacheLock('ErpShipping/delShippingOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpShipping/delShippingOrder', 1);

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 2,
            'distribution_status' => 1,
            'cancell_remark' => $param['cancell_remark'],
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder(['id' => $id], $data);

        //新增log
        $log = [
            'shipping_order_id' => $order_info['id'],
            'shipping_order_number' => $order_info['order_number'],
            'log_info' => serialize($order_info),
            'log_type' => 3,
        ];
        $log_status = $this->addShippingOrderLog($log);

        // 修改原出库单状态
        // 调拨单修改调拨单上的状态
        // 销售采修改出库单上的状态  is_shipping
        switch ($order_info['source_type']){
            case 1: # 销售单
                $where = [
                    //'outbound_code' => $order_info['source_number']
                    'outbound_apply_code' => $order_info['source_number']
                ];
                $data = [
                    'is_shipping' => 2,
                    'update_time' => currentTime(),
                ];
                //$source_status = $this->getModel('ErpStockOut')->saveStockOut($where,$data);
                $source_status = $this->getModel('ErpStockOutApply')->updateApply($data, $where);

                break;
            case 2: # 调拨单
                $where = [
                    'order_number' => $order_info['source_order_number']
                ];
                $data = [
                    'is_shipping' => 2,
                    'update_time' => currentTime(),
                ];
                $source_status = $this->getModel('ErpNewAllocationOrder')->saveAllocationOrder($where,$data);
                break;
            case 3: # 采购单
                $where = [
                    'storage_code' => $order_info['source_number']
                ];
                $data = [
                    'is_shipping' => 2,
                    'update_time' => currentTime(),
                ];
                $source_status = $this->getModel('ErpStockIn')->saveStockIn($where,$data);

                break;
            default:
                break;
        }

        # 如果是调拨单，并且为城市->服务商，配送方式
        # 取消收油单
        $data = ['status' => 1 ,'message' =>''];
        if($order_info['source_type'] == 2 && $order_info['is_send_message'] == 1 && $order_info['order_status'] ==10) {
            $data = resetOilReceiptStatus(['shipping_order_number' => $order_info['order_number'] ,'create_name' => $this->getUserInfo('dealer_name')]);
        }

        if ($order_status && $log_status && $source_status && $data['status'] == 1) {
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
            if($data['status'] != 1) $result['message'] = $data['message'];
        }

        cancelCacheLock('ErpShipping/delShippingOrder');
        return $result;
    }

    /**
     * 确认配送单
     * @param array $param
     * @author guanyu
     * @time 2018-03-20
     * @return array
     */
    public function confirmShippingOrder($id)
    {
        //参数验证
        if (!$id) {
            $result = [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        $order_info = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => intval($id)]);
        if ($order_info['order_status'] != 3) {
            $result = [
                'status' => 2,
                'message' => '该配送单不是已审核状态，无法确认',
            ];
            return $result;
        }

        if (getCacheLock('ErpShipping/confirmShippingOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpShipping/confirmShippingOrder', 1);

        # --------------------------确认配送单，推送到tms---------------------------------------
        # $order = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => intval($id)]);
        $order = $order_info;
        $order['goods_info'] = $this->getModel('ErpGoods')->where(['id'=>$order['goods_id']])->find();
        $params_order  = $this->getEvent('ErpShipping')->getDataForTmsAddOrder($order);
        $params['data']= json_encode($params_order, JSON_UNESCAPED_UNICODE);
        $api_params    = getTmsAllParams($params);
        $tms_result    = postTmsApiAddOrder($api_params);
        $log_info_data = array(
            'event'   => '配送单确认,推送到tms生成单据',
            'key'     => '配送单号：' . trim($order_info['order_number']),
            'request' => '参数：' . var_export($api_params,true),
            'response'=> 'tms返回值：'.var_export($tms_result,true),
        );
        log_write($log_info_data);
        /*
            YF 添加
            判断是否请求成功
        */
        if ( !isset($tms_result['code']) || $tms_result['code'] != 0 ) {
            $result = [
                'status' => 13,
                'message' => "TMS系统请求失败:".$tms_result['message'],
            ];
            cancelCacheLock('ErpShipping/confirmShippingOrder');
            return $result;
        }
        /*
            END
        */

        if( !isset($tms_result['data']['code']) || $tms_result['data']['code'] != 0) {
            $result = [
                'status' => 3,
                'message' => "TMS系统".$tms_result['data']['message'],
            ];
            cancelCacheLock('ErpShipping/confirmShippingOrder');
            return $result;
        }
        # -------------------------end:tms接口--------------------------------------------------------------------

        M()->startTrans();

        //审核单据
        $data = [
            'order_status' => 10,
            'distribution_status' => 2,
            'updater' => $this->getUserInfo('id'),
            'update_time' => currentTime(),
        ];
        $order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder(['id' => $id], $data);

        //新增log
        $log = [
            'recharge_id' => $order_info['id'],
            'recharge_order_number' => $order_info['order_number'],
            'log_info' => serialize($order_info),
            'log_type' => 5,
        ];
        $log_status  = $this->addShippingOrderLog($log);

        # 是否需要生成收油单
        $result_front = ['status' => 1];
        if(intval($order_info['is_send_message']) == 1){
            # 生成收油单
            # 1. ERP城市仓->服务商 确认出库 ，调Front生成收油单 qianbin 2018-03-16
            # 2. 发送极光推送
            $allocation_order_info = [];
            $erp_goods_info        = [];

            $where_order = ['order_number' => $order_info['source_order_number']];
            $field_order = 'order_number,in_facilitator_id,in_storehouse,allocation_type,goods_id,num,audit_time,create_time';
            $allocation_order_info = $this->getModel('ErpNewAllocationOrder')->getOneAllocationOrder($where_order,$field_order);
            if(!empty($allocation_order_info) && !empty($allocation_order_info['goods_id'])){
                $erp_goods_info = $this->getModel('ErpGoods')->field('goods_name,source_from,grade,level')->where(['id'=>$order_info['goods_id']])->find();
            }
            $num_kg = getNum($allocation_order_info['num']) * 1000;
            $params = [
                'allocation_order_number'  => trim($allocation_order_info['order_number']),
                'shipping_order_number'    => trim($order_info['order_number']),
                'stock_out_number'         => '',
                'facilitator_id'           => $allocation_order_info['in_facilitator_id'],
                'facilitator_skid_id'      => $allocation_order_info['in_storehouse'],
                'allocation_order_type'    => $allocation_order_info['allocation_type'],
                'create_name'              => $this->getUserInfo('dealer_name'),
                'goods_name'               => $erp_goods_info['goods_name'],
                'goods_source_from'        => $erp_goods_info['source_from'],
                'goods_grade'              => $erp_goods_info['grade'],
                'goods_level'              => $erp_goods_info['level'],
                'stock_time'               => $allocation_order_info['audit_time'],
                'goods_num'                => $num_kg,
                'stock_out_weight'         => $num_kg,
                'source'                   => 3,
                'delivery_method'          => $allocation_order_info['delivery_method'],
                'expect_deliver_time'      => $order_info['business_shipping_time'],
                'driver_name'               => $order_info['driver_name'],
                'driver_phone'             => $order_info['driver_phone'],
            ];
            # 收油二期上线之前的单子，不用影响收油单
            if(getCompareOilTime($allocation_order_info['create_time'])){
                $result_front = addOilReceipt($params);
            }
        }
        # end ---------------------------
        if ($order_status && $log_status && $result_front['status'] == 1) {
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
            if($result_front['status'] != 1 ) $result['message'] = $result_front['message'];
        }

        cancelCacheLock('ErpShipping/confirmShippingOrder');
        return $result;
    }

    /**
     * 物流信息完善
     * @author guanyu
     * @time 2018-03-19
     */
    public function consummateShippingOrder($id,$param = [])
    {
        //参数验证
        if (!$id || empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }
        if (strHtml($param['unloading_num']) == '') {
            $result = [
                'status' => 2,
                'message' => '请填写卸货量'
            ];
            return $result;
        }
        if (strHtml($param['loading_num']) == '') {
            $result = [
                'status' => 3,
                'message' => '请填写装载量'
            ];
            return $result;
        }
        if (strHtml($param['seal_number']) == '') {
            $result = [
                'status' => 4,
                'message' => '请填写铅封号'
            ];
            return $result;
        }
        if (strHtml($param['depot_out_time']) == '') {
            $result = [
                'status' => 5,
                'message' => '请填写油库确认出库时间'
            ];
            return $result;
        }
        if (strHtml($param['actual_in_time']) == '') {
            $result = [
                'status' => 6,
                'message' => '实际送达时间'
            ];
            return $result;
        }
        if (strHtml($param['actual_in_time']) < strHtml($param['depot_out_time']) ) {
            $result = [
                'status' => 7,
                'message' => '实际送达时间必须大于油库出库时间'
            ];
            return $result;
        }
        if (getCacheLock('ErpShipping/consummateShippingOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpShipping/consummateShippingOrder', 1);

        M()->startTrans();

        //新增单据
        $data = [
            'loading_num'               => setNum($param['loading_num']),
            'unloading_num'             => setNum($param['unloading_num']),
            'temperature'               => setNum($param['temperature']),
            'density'                   => $param['density'],
            'freight'                   => setNum($param['freight']),
            'incidental'                => setNum($param['incidental']),
            'loss_num'                  => setNum($param['loss_num']),
            'seal_number'               => $param['seal_number'],
            'remark'                    => $param['remark'],
            'distribution_status'       => 3,
            'update_time'               => currentTime(),
            'updater'                   => $this->getUserInfo('id'),
            'depot_out_time'            => $param['depot_out_time'],
            'actual_in_time'            => $param['actual_in_time']
        ];
        $order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder(['id' => $id],$data);

        $RechargeOrderInfo_new = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => $id]);
        //新增log
        $log_data = [
            'shipping_order_id' => $id,
            'shipping_order_number' => $RechargeOrderInfo_new['order_number'],
            'log_info' => serialize($RechargeOrderInfo_new),
            'log_type' => 6,
        ];
        $log_status = $this->addShippingOrderLog($log_data);

        if ($order_status && $log_status) {
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

        cancelCacheLock('ErpShipping/consummateShippingOrder');
        return $result;
    }

    /**
     * 插入配送单日志
     * @author guanyu
     * @param $data
     * @return mixed
     */
    public function addShippingOrderLog($data)
    {
        if ($data) {
            $data['create_time'] = currentTime();
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0;
            $data['operator_id'] = $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0;
            $status = $this->getModel('ErpShippingOrderLog')->add($data);
        }
        return $status;
    }

    /**
     * 获取一条完整的订单信息（包含关联信息）
     * @param $id
     * @param $field
     * @return mixed
     * @author guanyu
     * @time 2018-03-20
     */
    public function findOneShippingOrder($id, $field)
    {
        $order_info = $this->getModel('ErpShippingOrder')->findOneShippingOrder(['o.id'=>intval($id)], $field);
        $order_info['outbound_status']  = $this->getModel('ErpNewAllocationOrder')->where(['order_number' => $order_info['source_order_number']])->getField('outbound_status');
        return $order_info;
    }

    /**
     * 上传退货凭证
     *
     * tips:上线后重构
     *
     * @param  $id 调拨单ID
     * @param  array $attach 凭证附件
     * @param  int $type 类型 1 出库 2 入库
     * @return array
     * @author xiaowen
     * @time 2017-8-23
     */
    public function uploadVoucher($id, $param = [])
    {
        //参数验证
        if (!$id || empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误'
            ];
            return $result;
        }

        $shipping_order_info = $this->getModel('ErpShippingOrder')->findShippingOrder(['id'=>$id]);

        if (!isset($param['outbound_order_photo']) && $shipping_order_info['outbound_order_photo'] == '') {
            $result = [
                'status' => 2,
                'message' => '出库单凭证必传'
            ];
            return $result;
        }

        if (!isset($param['seal_photo0']) && $shipping_order_info['seal_photo'] == '') {
            $result = [
                'status' => 3,
                'message' => '铅封图必传'
            ];
            return $result;
        }

        $arr = [
            'outbound_order_photo' => '出库单凭证',
            'seal_photo' => '铅封图',
            'temperature_density_photo' => '视温图',
            'oil_sample_photo' => '油样图',
        ];

        //验证文件大小
        foreach ($param as $key => $value) {
            if ($value) {
                //铅封图可多张上传
                $name = strpos($key,'seal_photo') !== false ? '铅封图' : $arr[$key];
                if($value['size'] > 2*1024*1024) {
                    $result = [
                        'status' => 4,
                        'message' => $name.'文件过大，不能上传大于2M的文件'
                    ];
                    return $result;
                }
                if($value['type'] != "image/jpeg" && $value['type'] != 'image/gif' && $value['type'] != 'image/png') {
                    $result = [
                        'status' => 5,
                        'message' => $name.'文件格式上传有误，只能上传图片文件'
                    ];
                    return $result;
                }
            } else {
                continue;
            }
        }

        if (getCacheLock('ErpShipping/uploadVoucher')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpShipping/uploadVoucher', 1);

        $upload_status_all = true;
        $data = [];
        $delete_photo = [];
        $error_photo = [];
        foreach ($param as $key => $value) {
            $uploaded_file = $value['tmp_name'];
            $user_path = $this->uploads_path['shipping_attach']['src'];
            //判断该用户文件夹是否已经有这个文件夹
            if (!file_exists($user_path)) {
                mkdir($user_path, 0777, true);
            }
            $current_date = date('Y-m-d');
            if (!is_dir($user_path . $current_date)) {
                mkdir($user_path . $current_date, 0777, true);
            }
            $current_date = date('Y-m-d');
            //后缀
            $type = substr($value['name'],strripos($value['name'],'.')+1);
            $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
            $upload_status = move_uploaded_file($uploaded_file, $user_path . $file_name);
            $upload_status_all = $upload_status && $upload_status_all ? true : false;

            //已上传文件，如果操作失败要删除
            array_push($error_photo,$file_name);

            //铅封图可多张上传，路径存为json串
            if (strpos($key,'seal_photo') !== false) {
                if (!is_array($data['seal_photo'])) {
                    $data['seal_photo'] = [];
                }
                array_push($data['seal_photo'],$file_name);
                $key = 'seal_photo';
            } else {
                $data[$key] = $file_name;
            }
            //将原图覆盖，且删除原图片
            if ($shipping_order_info[$key]) {
                array_push($delete_photo,$key);
            }
        }

        M()->startTrans();
        if ($data['seal_photo']) {
            $data['seal_photo'] = json_encode($data['seal_photo']);
        }
        $data['update_time'] = currentTime();
        $data['updater'] = $this->getUserInfo('id');
        //凭证上传状态
        $i = 0;
        foreach ($arr as $key => $value) {
            if ($shipping_order_info[$key] || $data[$key]) {
                $i++;
            }
        }
        if ($i == 0) {
            $voucher_status = 1;
        } elseif ($i < 4) {
            $voucher_status = 2;
        } elseif ($i == 4) {
            $voucher_status = 10;
        }
        $data['voucher_status'] = $voucher_status;
        $order_status = $this->getModel('ErpShippingOrder')->saveShippingOrder(['id' => intval($id)], $data);

        $RechargeOrderInfo_new = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => $id]);
        //新增log
        $log_data = [
            'shipping_order_id' => $id,
            'shipping_order_number' => $RechargeOrderInfo_new['order_number'],
            'log_info' => serialize($RechargeOrderInfo_new),
            'log_type' => 8,
        ];
        $log_status = $this->addShippingOrderLog($log_data);

        if ($order_status && $log_status) {
            //操作成功后删除原文件
            foreach ($delete_photo as $value) {
                if ($value == 'seal_photo') {
                    $photos = json_decode($shipping_order_info['seal_photo']);
                    foreach ($photos as $photo) {
                        unlink($user_path.$photo);
                    }
                } else {
                    unlink($user_path.$shipping_order_info[$value]);
                }
            }
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            //操作失败后删除文件
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            M()->rollback();
            $result = [
                'status' => 2,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpShipping/uploadVoucher');
        return $result;
    }

    /**
     * 获取tms开单接口需要的业务数据
     * @param  array $shippingData 配送单数据
     * @return array $result
     * @author xiaowen
     * @time 2018-8-13
     */
    function getDataForTmsAddOrder($shippingData){
        // $cityArr = provinceCityZone();
        $shipping_city = explode('_', $shippingData['shipping_city']);
        $receiving_city = explode('_', $shippingData['receiving_city']);
        $receiving_city[3] = 1981;
        $city_ids = array_unique(array_merge($shipping_city,$receiving_city));
        # 查询地区
        $area_name = $this->getModel('area')->where(['id' => ['in' ,$city_ids]])->getField('id,map_name,area_name',true);
        $tms_data = [
            'processingtime' => $shippingData['create_time'],
            //'orgrootname' => '上海找油信息科技有限公司',
            'orgrootname' => '上海找油网',
            'companyname' => $shippingData['company_name'],
            //'customerorderno' => $shippingData['source_number'],
            'customerorderno' => $shippingData['order_number'],
            'providercompanyname' => $shippingData['shipper'] == 1 ? '找罐车公司':'自营运输',//$shippingData['shipper'] == 1 ? '找罐车公司':'自营运输',
            'taskType' => '配送',
            'loadnum' => 1,     // 装货地数量
            'unloadnum' => 1,   // 卸货地数量
            'storeroom_keeper' => $shippingData['creater'],
            'initiator' => $shippingData['our_company_name'],
            'goodsname' => $shippingData['goods_info']['goods_name'],
            'goodsnumber' => $shippingData['goods_info']['goods_code'],
            'brand' => $shippingData['goods_info']['source_from'],
            'grade' => $shippingData['goods_info']['grade'],
            'level' => $shippingData['goods_info']['level'],
            'price' => '',               // 单价
            'specification' => '',      // 包装规格
            'weight' => getNum($shippingData['shipping_num']),
            'volume' => '',             // 体积
            'loss' => '0.003',          //约定损耗
            'loadinfo' => [[
                'loadprovince' => empty($area_name[$shipping_city[0]]['map_name']) ? '' : $area_name[$shipping_city[0]]['map_name'],//$shippingData['shipping_num'],
                'loadcity' => empty($area_name[$shipping_city[1]]['map_name']) ? '' :  $area_name[$shipping_city[1]]['map_name'], //$shippingData['shipping_num'],
                'loadarea' => empty($area_name[$shipping_city[2]]['map_name']) ? '' :  $area_name[$shipping_city[2]]['map_name'],//$shippingData['shipping_num'],
                'loadaddress' => $shippingData['shipping_address'],
                'customer' => '',
                'loadconnectperson' => '',
                'loadconnectphone' => $shippingData['shipping_phone'],
                //'loaddocktime' => $shippingData['business_shipping_time'] . ' - ' . date('Y-m-d H:i:s', strtotime('+1 days', strtotime($shippingData['business_shipping_time']))),
                'loaddocktime' => '',
            ]],
            'unloadinfo' => [[
                'unloadprovince' => empty($area_name[$receiving_city[0]]['map_name']) ? '' :  $area_name[$receiving_city[0]]['map_name'],
                'unloadcity' => empty($area_name[$receiving_city[1]]['map_name']) ? '' :  $area_name[$receiving_city[1]]['map_name'],
                'unloadarea' => empty($area_name[$receiving_city[2]]['map_name']) ? '' :  $area_name[$receiving_city[2]]['map_name'],
                'unloadaddress' => $shippingData['receiving_address'],
                'uncustomer' => $shippingData['company_name'],
                'unloadconnectperson' => $shippingData['user_name'],
                'unloadconnectphone' => $shippingData['receiving_phone'],
                'unloaddocktime' => $shippingData['business_shipping_time'] . ' - ' . date('Y-m-d H:i:s', strtotime('+1 days', strtotime($shippingData['business_shipping_time']))),
            ]],

        ];
        return $tms_data;

    }

}

