<?php
namespace Common\Event;

use Symfony\Component\HttpKernel\Tests\EventListener\MockDumper;
use Think\Controller;
use Home\Controller\BaseController;

class ErpStockEvent extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP库存逻辑处理层
    // +----------------------------------
    // |Author:senpai Time:2017.5.3
    // +----------------------------------
    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
    }

    /**
     * 入库单列表
     * @author senpai
     * @time 2017-05-04
     */
    public function erpStockInList($param = [])
    {
        $where = [];

        // 系统批次号搜索
        if ( isset($param['batch_sys_bn']) && !empty($param['batch_sys_bn']) ) {
            $where['si.batch_sys_bn'] = ['like', ['%' . trim($param['batch_sys_bn']) . '%']];
        }

        //来源申请单
        if ( isset($param['source_apply_number']) && !empty($param['source_apply_number']) ) {
            $where['si.source_apply_number'] = ['like', ['%' . trim($param['source_apply_number']) . '%']];
        }

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['si.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }

        if (trim($param['goods_code'])) {
            $where['si.goods_id'] = $param['goods_code'];
        }

        if (trim($param['source_number'])) {
            $where['si.source_number'] = ['like', '%' . trim($param['source_number']) . '%'];
        }
        if (trim($param['storage_code'])) {
            $where['si.storage_code'] = ['like', '%' . trim($param['storage_code']) . '%'];
        }
        if (trim($param['storage_status'])) {
            $where['si.storage_status'] = intval(trim($param['storage_status']));
        }
        if (trim($param['finance_status'])) {
            $where['si.finance_status'] = intval(trim($param['finance_status']));
        }
        if (trim($param['attachment']) == 1) {
            $where['si.attachment'] = '';
        }
        if (trim($param['attachment']) == 10) {
            $where['si.attachment'] = ['neq',''];
        }
        if (trim($param['storage_type'])) {
            $where['si.storage_type'] = intval(trim($param['storage_type']));
        }

        # -----------------增加省份、审核日期筛选-----------------------------------
        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id)) {
                $cid = implode(',', $city_id);
                $where['si.region'] = ['in',$cid];
            }
        }
        if (trim($param['region'])) {
            $where['si.region'] = $param['region'];
        }
        if (isset($param['examine_start_time']) || isset($param['examine_end_time'])) {
            if (trim($param['examine_start_time']) && !trim($param['examine_end_time'])) {

                $where['si.audit_time'] = ['egt', trim($param['examine_start_time'])];
            } else if (!trim($param['examine_start_time']) && trim($param['examine_end_time'])) {

                $where['si.audit_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['examine_end_time'])) + 3600 * 24)];
            } else if (trim($param['examine_start_time']) && trim($param['examine_end_time'])) {

                $where['si.audit_time'] = ['between', [trim($param['examine_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['examine_end_time'])) + 3600 * 24)]];
            }
        }
        # --------------------< qianbin -   2017.08.15>--------------------------

        /**-------------------------------------新增搜索条件放到此行之上---------------------------------------------*/
        if ((trim($param['def']) == 1 && !trim($param['storage_status'])) || (trim($param['def']) == 2 && count($where) == 0)) {
            $where['si.storage_status'] = 1;
        }

        //我的入库单
        if (trim($param['dealer_id'])) {
            $where['si.dealer_id'] = trim($param['dealer_id']);
            if (!trim($param['storage_status'])) {
                unset($where['si.storage_status']);
            }
        }
        // 过滤其他入库单
        $where['is_other'] = ['neq',1];

        //当前登陆选择的我方公司
        $where['si.our_company_id'] = session('erp_company_id');

        $field = 'si.*,
        o.buyer_dealer_name, o.sale_company_id as o_company_id, o.sale_user_id as o_user_id,
        g.goods_code, g.goods_name, g.source_from, g.grade, g.level, g.label,
        ao.dealer_name as f_dealer_name, ao.actual_in_num_liter,
        so.company_id as return_company_id,so.user_id as return_user_id, so.dealer_name as return_dealer_name';

        if ($param['export']) {
            $data = $this->getModel('ErpStockIn')->getAllStockInList($where, $field);
        } else {
            $data = $this->getModel('ErpStockIn')->getStockInList($where, $field, $param['start'], $param['length']);
        }

        $user_name_type = [
            1 => 'o_user_id',
            2 => 'user_name',
            3 => 'return_user_id',
        ];
        $company_name_type = [
            1 => 'o_company_id',
            2 => 'company_name',
            3 => 'return_company_id',
        ];

        $dealer_name_type = [
            1 => 'buyer_dealer_name',
            2 => 'f_dealer_name',
            3 => 'return_dealer_name',
            4 => 'dealer_name',
            5 => 'dealer_name',
        ];

        //log_info("SQL语句:" .$this->getModel('ErpPurchaseOrder')->getLastSql());
        if ($data['data']) {
            $cityArr = provinceCityZone()['city'];
            //查询仓库、用户、公司数据
            $storehouse_ids = array_unique(array_filter(array_column($data['data'], 'storehouse_id')));
            $storehouse_arr = [];
            if($storehouse_ids){
                $storehouse_arr = $this->getModel('ErpStorehouse')->where(['id' => ['in', $storehouse_ids]])->getField('id,storehouse_name');
            }
            /* ----------- 查看是否存在损耗 -------------- */
            $loss_where = [
                'source_number' => ['in',array_column($data['data'],'storage_code')],
                'order_status'  => ['neq',2],
            ];
            $loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->getField('source_number,id');
            /* ------------------- END ------------------ */
            $supplierUserIdArr = array_column($data['data'], 'o_user_id');
            $customerUserIdArr = array_column($data['data'], 'return_user_id');
            $supplierIdArr = array_column($data['data'], 'o_company_id');
            $customerIdArr = array_column($data['data'], 'return_company_id');
            $facilitatorSkidIdArr = array_column($data['data'], 'storehouse_id');
            $supplierUserInfo = $this->getEvent('ErpCommon')->getUserData($supplierUserIdArr, 2);
            $customerUserInfo = $this->getEvent('ErpCommon')->getUserData($customerUserIdArr, 1);
            $supplierCompanyInfo = $this->getEvent('ErpCommon')->getCompanyData($supplierIdArr, 2);
            $customerCompanyInfo = $this->getEvent('ErpCommon')->getCompanyData($customerIdArr, 1);
            $facilitatorCompanyInfo = $this->getModel('ErpSupplier')->alias('s')
                ->where(['es.id'=>['in',$facilitatorSkidIdArr]])
                ->join('oil_erp_storehouse es on s.id = es.company_id', 'left')
                ->getField('es.id,s.id as supplier_id,s.supplier_name as company_name');

            $dealer_ids = array_unique(array_filter(array_column($data['data'], 'auditor_id')));
            $dealer_arr = [];
            if($dealer_ids){
                $dealer_arr = $this->getModel('Dealer')->where(['id' => ['in', $dealer_ids]])->getField('id,dealer_name');
            }
            //获取货权信息 xiaowen 2019-2-21
            $cargo_bn_ids = array_unique(array_column($data['data'], 'cargo_bn_id'));
            $cargo_bn_arr = [];
            if($cargo_bn_ids){
                $cargo_bn_arr = $this->getModel('ErpCargoBn')->where(['id' => ['in', $cargo_bn_ids]])->getField('id,cargo_bn');
            }
            foreach ($data['data'] as $key => &$value) {
                // 判断是否有损耗
                $data['data'][$key]['is_loss'] = isset($loss_arr[$value['storage_code']]) ? '是' : '否';
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                if ( $value['source_apply_number'] == '' ) {
                    $value['source_apply_number'] = '--';
                }
                $data['data'][$key]['region_font'] = $value['region'] == 1 ? '全国' : $cityArr[$value['region']];
                //$data['data'][$key]['storehouse_name'] = $value[$storehouse_type[$value['outbound_type']]];
                $data['data'][$key]['storehouse_name'] = $value['stock_type'] == 4 ? '——' : $storehouse_arr[$value['storehouse_id']];
                //$data['data'][$key]['user_name'] = $value[$user_name_type[$value['outbound_type']]];
                $data['data'][$key]['user_name'] = $value['storage_type'] == 3 ? $customerUserInfo[$value[$user_name_type[$value['storage_type']]]]['user_name'] : $supplierUserInfo[$value[$user_name_type[$value['storage_type']]]]['user_name'];
                //$data['data'][$key]['company_name'] = $value[$company_name_type[$value['outbound_type']]];
                $data['data'][$key]['company_name'] = $value['storage_type'] == 3 ? $customerCompanyInfo[$value[$company_name_type[$value['storage_type']]]]['company_name'] : $supplierCompanyInfo[$value[$company_name_type[$value['storage_type']]]]['company_name'];
                //$data['data'][$key]['sale_dealer_name'] = $value[$dealer_name_type[$value['outbound_type']]];
                $data['data'][$key]['sale_dealer_name'] = $value[$dealer_name_type[$value['storage_type']]];
                $data['data'][$key]['auditor'] = $dealer_arr[$value['auditor_id']] ? $dealer_arr[$value['auditor_id']] : '——';

                $data['data'][$key]['storage_status'] = stockInStatus($value['storage_status'], true);
                $data['data'][$key]['storage_status_font'] = stockInStatus($value['storage_status']);
                $data['data'][$key]['finance_status'] = financeStatus($value['finance_status'],true);
                $data['data'][$key]['finance_status_font'] = financeStatus($value['finance_status']);
                $data['data'][$key]['storage_type_font'] = stockInType($value['storage_type']);
                $data['data'][$key]['storage_num'] = round(ErpFormatFloat(getNum($value['storage_num'])),4);
                $data['data'][$key]['actual_in_num_liter'] = $value['actual_storage_num'] < 0 ? getNum($value['actual_storage_num_litre']) * -1 : getNum($value['actual_storage_num_litre']);
                $data['data'][$key]['attachment'] = $data['data'][$key]['attachment'] ? '已上传' : '未上传';
                $data['data'][$key]['actual_storage_num'] = round(ErpFormatFloat(getNum($value['actual_storage_num'])),4);
                // 添加单价显示 2018-02-27 qianbin
                $data['data'][$key]['price'] = isset($value['price']) && $value['price'] > 0 ?  ErpFormatFloat(getNum($value['price'])) : '——';

                $data['data'][$key]['facilitator_name'] = $value['stock_type'] == 4 ? $facilitatorCompanyInfo[$value['storehouse_id']]['company_name'] : '--';
                $data['data'][$key]['facilitator_skid_name'] = $value['stock_type'] == 4 ? $storehouse_arr[$value['storehouse_id']] : '--';
                $data['data'][$key]['facilitator_id'] = $value['stock_type'] == 4 ? $facilitatorCompanyInfo[$value['storehouse_id']]['supplier_id'] : '--';
                //判断用户是否为团内
                $is_inner = 2 ;
                if($value['storage_type'] == 3){
                    $is_inner = $customerCompanyInfo[$value[$company_name_type[$value['storage_type']]]]['is_inner'] ;
                }else{
                    $is_inner = $supplierCompanyInfo[$value[$company_name_type[$value['storage_type']]]]['is_inner'];
                }
                $value['is_inner'] = ($is_inner==1) ? "是":"否" ;
                /******************************************
                @ Content 支持 内部交易单
                 *******************************************/
                if ( $value['retail_inner_order_number'] != '' ) {
                    $data['data'][$key]['actual_storage_num'] = round($data['data'][$key]['actual_storage_num'],4);
                    $data['data'][$key]['storage_num']        = round($data['data'][$key]['storage_num'],4);
                }
                //显示货权
                $data['data'][$key]['cargo_bn'] = $cargo_bn_arr[$value['cargo_bn_id']] ? $cargo_bn_arr[$value['cargo_bn_id']] : '--';
                $data['data'][$key]['batch_sys_bn'] = $value['batch_sys_bn'] ? $value['batch_sys_bn'] : '--';
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 生成入库单需要显示数据
     * @param $id
     * @return mixed
     */
    public function showAddErpStockIn($id)
    {
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name';
        $data['order'] = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $id], $field);

        $cityArr = provinceCityZone()['city'];
        $data['order']['region_font'] = $cityArr[$data['order']['region']];
        $data['order']['storage_quantity'] = getNum($data['order']['storage_quantity']);
        $data['order']['goods_num'] = getNum($data['order']['goods_num']);
        $data['order']['stock_not_in'] = round($data['order']['goods_num'] - $data['order']['storage_quantity'], 4);
        return $data;
    }

    /**
     * 新增入库单操作
     * @author senpai
     * @time 2017-05-04
     */
    public function actAddErpStockIn($param,$files)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        //获取单信息
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $order_info = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $param['source_object_id']], $field);
        if (
            (($order_info['pay_type'] == 1 && $order_info['pay_status'] != 10) ||
                ($order_info['pay_type'] == 2 && $order_info['pay_status'] <= 2) ||
                ($order_info['pay_type'] == 3 && $order_info['order_status'] != 10) ||
                ($order_info['pay_type'] == 4 && $order_info['pay_status'] != 10) ||
                ($order_info['pay_type'] == 5 && $order_info['pay_status'] <= 2))
            || $order_info['order_status'] != 10
        ) {
            $result['status'] = 101;
            $result['message'] = "该采购单未达到入库条件，无法生成入库单";
            return $result;
        }
        if ($order_info['is_returned'] == 1) {
            $result['status'] = 101;
            $result['message'] = "已退货订单无法生成入库单";
            return $result;
        }

        $stock_not_in = $order_info['pay_type'] == 5 ? getNum($order_info['total_purchase_wait_num'] - $order_info['storage_quantity']) : getNum($order_info['goods_num'] - $order_info['storage_quantity']);
        if (trim($param['storage_num']) == "" || trim($param['storage_num']) <= 0) {
            $result['status'] = 101;
            $result['message'] = "请输入入库数量";
            return $result;
        }
        if (trim($param['actual_storage_num']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入实际入库数量";
            return $result;
        }
        if (trim($param['storage_num'] > $stock_not_in)) {
            $result['status'] = 101;
            $result['message'] = "入库数量不能超出待入数量";
            return $result;
        }
        if($order_info['business_type'] == 4 && empty(trim($param['outbound_density']))){
            $result['status']  = 101;
            $result['message'] = "加油站业务，密度不能为空！";
            return $result;
        }

        $upload_status = true;
        $error_photo = [];
        $attachment = [];
        if (!empty($files)) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if($value['size'] > 2*1024*1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
//                    if ($value['type'] != "image/jpeg" && $value['type'] != 'image/gif' && $value['type'] != 'image/png') {
//                        $result = [
//                            'status' => 5,
//                            'message' => '文件格式上传有误，只能上传图片文件'
//                        ];
//                        return $result;
//                    }
                } else {
                    continue;
                }
            }

            //上传文件
            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_in_attach']['src'];
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
                //已上传文件，如果操作失败要删除
                array_push($error_photo,$file_name);
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        }
        if (getCacheLock('ErpStock/actAddErpStockIn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStock/actAddErpStockIn', 1);
        $stock_in_data = array_merge([
            'storage_code' => erpCodeNumber(8)['order_number'],
            'storage_type' => $param['storage_type'],
            'storage_status' => 1,
            'storage_remark' => $param['storage_remark'],
            'source_number' => $param['source_number'],
            'source_object_id' => $param['source_object_id'],
            'our_company_id' => session('erp_company_id'),
            'goods_id' => $param['goods_id'],
            'storage_num' => setNum($param['storage_num']),
            'actual_storage_num' => setNum($param['actual_storage_num']),
            'outbound_density' => $param['outbound_density'],
            'creater_id' => $this->getUserInfo('id'),
            'create_time' => $this->date,
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'storehouse_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['type'] == 1 ? getAllocationStockType($order_info['storehouse_id']) : 2,
            'region' => $order_info['region'],
        ],$attachment);

        $status_stockin = $this->getModel('ErpStockIn')->addStockIn($stock_in_data);
        if ($status_stockin && $upload_status) {
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            //操作失败后删除文件
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpStock/actAddErpStockIn');
        return $result;
    }

    /**
     * 生编辑入库单需要显示数据
     * @param $id
     * @return mixed
     */
    public function showUpdateErpStockIn($id)
    {
        $field = 'si.*,o.region as p_region,o.storage_quantity,o.goods_num,ao.in_region,ao.actual_in_num,g.goods_code,g.goods_name,
        g.source_from,g.grade,g.level,so.region as s_region,ro.return_goods_num,o.business_type,o.config_density';
        $data['order'] = $this->getModel('ErpStockIn')->findStockIn(['si.id' => $id], $field);

        $cityArr = provinceCityZone()['city'];
        switch ($data['order']['storage_type']) {
            case 1:
                $region = $data['order']['p_region'];
                $goods_num = $data['order']['goods_num'];
                break;
            case 2:
                $region = $data['order']['in_region'];
                $goods_num = $data['order']['actual_in_num'];
                break;
            case 3:
                $region = $data['order']['s_region'];
                $goods_num = $data['order']['return_goods_num'];
                break;
            default:
                $region = $data['order']['p_region'];
        }
        $data['order']['region_font'] = $cityArr[$region];
        $data['order']['storage_quantity'] = $data['order']['storage_quantity'] ? getNum($data['order']['storage_quantity']) : 0;
        $data['order']['actual_storage_num'] = getNum($data['order']['actual_storage_num']);
        $data['order']['goods_num'] = getNum($goods_num);
        $data['order']['stock_not_in'] = $data['order']['storage_type'] == 1 ? $data['order']['goods_num'] - $data['order']['storage_quantity'] : $data['order']['actual_storage_num'];

        //查询入库单货权信息
        $data['order']['cargo_info'] = $this->getModel('ErpCargoBn')->find($data['order']['cargo_bn_id']);

        return $data;
    }

    /**
     * 编辑入库单操作
     * @author senpai
     * @time 2017-05-04
     */
    public function actUpdateErpStockIn($param,$files)
    {
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $order_info = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $param['source_object_id']], $field);
        $stock_in_info = $this->getModel('ErpStockIn')->where(['id' => $param['id']])->find();
        $stock_not_in = $order_info['pay_type'] == 5 ? getNum($order_info['total_purchase_wait_num'] - $order_info['storage_quantity']) : getNum($order_info['goods_num'] - $order_info['storage_quantity']);

        if (trim($param['storage_num']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入入库数量";
            return $result;
        }
        if (trim($param['actual_storage_num']) == "" || trim($param['actual_storage_num']) == 0) {
            $result['status'] = 101;
            $result['message'] = "请输入实际入库数量";
            return $result;
        }
        if (trim($param['storage_num'] > $stock_not_in)) {
            $result['status'] = 101;
            $result['message'] = "入库数量不能超出待入数量";
            return $result;
        }
        if (empty($param['cargo_bn_type'])) {
            $result['status'] = 101;
            $result['message'] = "请选择货权类型";
            return $result;
        }
        if (trim($param['cargo_bn']) == "") {
            $result['status'] = 101;
            $result['message'] = "请输入货权号";
            return $result;
        }
        /***************************************
        @ Content 判断有没有 入库申请单字段 是否为空（兼容老数据）
        START
         ***************************************/
        if ( !empty($stock_in_info['source_apply_number']) ) {
            $stock_in_apply = $this->getModel('ErpStockInApply')->where(['storage_apply_code' => ['eq',trim($stock_in_info['source_apply_number'])]])->field('storage_apply_num')->find();
            if ( !isset($stock_in_apply['storage_apply_num']) ) {
                return ['status' => 103,'message' => '未查询入库申请单，请联系技术人员！'];
            }
            $stock_in_where = [
                'source_apply_number' => ['eq',trim($stock_in_info['source_apply_number'])],
                'storage_status'      => ['neq',2],
            ];
            $stock_in_arr = $this->getModel('ErpStockIn')->where($stock_in_where)->getField('id,actual_storage_num');
            $stock_in_num = 0;
            if ( !empty($stock_in_arr) ) {
                $stock_in_num = array_sum($stock_in_arr);
            }

            $or_update_num = getNum($stock_in_apply['storage_apply_num'] - $stock_in_num + $stock_in_info['actual_storage_num']);

            if ( $param['actual_storage_num'] > $or_update_num ) {
                return ['status' => 102, 'message' => '入库数量不能大于'.$or_update_num.'!'];
            }
        }
        /***************************************
                        END
        ***************************************/
        /***************************************
            @ Content 存在损耗单 不允许编辑入库数量
                        START
        ***************************************/
        $loss_where = [
            'source_number' => ['eq',$stock_in_info['storage_code']],
            'order_status'  => ['neq',2],
        ];
        $loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->field('id')->find();
        if ( isset($loss_arr['id']) ) {
            $param['actual_storage_num'] = getNum($stock_in_info['actual_storage_num']);
        }
        /***************************************
                        END
        ***************************************/
        $upload_status = true;
        $data = [];
        $delete_photo = [];
        $error_photo = [];
        $attachment = [];
        if (!empty($files) && $stock_in_info['finance_status'] == 1 || $stock_in_info['finance_status'] == 2 ) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if ($value['size'] > 2 * 1024 * 1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
//                    if ($value['type'] != "image/jpeg" && $value['type'] != 'image/gif' && $value['type'] != 'image/png') {
//                        $result = [
//                            'status' => 5,
//                            'message' => '文件格式上传有误，只能上传图片文件'
//                        ];
//                        return $result;
//                    }
                } else {
                    continue;
                }
            }

            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_in_attach']['src'];
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

                //已上传文件，如果操作失败要删除
                array_push($error_photo, $file_name);

                $data[$key] = $file_name;
                //将原图覆盖，且删除原图片
                if (!empty($stock_in_info[$key])) {
                    array_push($delete_photo, $key);
                }
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        } elseif ($stock_in_info['finance_status'] == 10) {
            $result = [
                'status' => 6,
                'message' => '该单据已财务核对，不能再上传附件'
            ];
            return $result;
        }

        if (getCacheLock('ErpStock/actUpdateErpStockIn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStock/actUpdateErpStockIn', 1);
        M()->startTrans();
        //========== edit xiaowen 2019-2-19 本次编辑前没有货权信息，要生成货权数据，否则更新货权数据=====================================
//        if($stock_in_info['cargo_bn_id'] == 0 && $param['cargo_bn_type'] && trim($param['cargo_bn'])){
//            $cargo_bn_result = $this->getEvent('ErpBatch',MODULE_NAME)->addBatchCargoBn($param);
//        }else{
//            $cargo_bn_result = $this->getEvent('ErpBatch',MODULE_NAME)->updateBatchCargoBn($stock_in_info['cargo_bn_id'], $param);
//        }
        $cargo_bn_result = $this->getEvent('ErpBatch',MODULE_NAME)->handleBatchCargoBn($param);
        //如货权操作失败 返回失败信息，回滚事务
        if($cargo_bn_result['status'] != 1){
            M()->rollback();
            cancelCacheLock('ErpStock/actUpdateErpStockIn');
            return $cargo_bn_result;
        }
        //===============================================================end=============================================================
        $stock_in_data = array_merge([
            'storage_remark' => $param['storage_remark'],
            'actual_storage_num' => setNum($param['actual_storage_num']),
            'outbound_density' => $param['outbound_density'],
            'update_time' => $this->date,
            'cargo_bn_id'=> $cargo_bn_result['cargo_bn_id'],
        ],$attachment);

        $status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$param['id']],$stock_in_data);
        if ($status_stockin && $upload_status) {
            //操作成功后删除原文件
            foreach ($delete_photo as $value) {
                unlink($user_path.$stock_in_info[$value]);
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
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpStock/actUpdateErpStockIn');
        return $result;
    }

    /**
     * 取消入库单操作
     * @author senpai
     * @time 2017-05-05
     */
    public function actDeleteErpStockIn($id)
    {
        if (!$id) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }

        $stockin_info = $this->getModel('ErpStockIn')->findStockIn(['si.id'=>$id],'si.*');
        /*
            YF 注释 Time：2019-07-02
        （原因：有损耗的调拨入库单 可以取消）
        */
        // if ($stockin_info['storage_type'] == 2) {
        //     $result['status'] = 100;
        //     $result['message'] = '调拨单对应入库单不能取消！';
        //     return $result;
        // }
     
        /*---------------- END -----------------*/
        if ($stockin_info['storage_status'] != 1) {
            $result['status'] = 101;
            $result['message'] = "只有未审核状态下才可取消";
            return $result;
        }
        if (in_array($stockin_info['storage_type'],[4,5])) {
            $result['status'] = 103;
            $result['message'] = "盘点单对应的入库单不能取消";
            return $result;
        }
        if($stockin_info['is_shipping'] == 1){
            $result['status'] = 104;
            $result['message'] = "该笔入库单已生成配送单，请先取消配送单！";
            return $result;
        }
        /*----------- 判断是否有损耗 ------------*/
        // Author: Yf  Time: 2019-05-10
        $loss_where = [
            'source_number' => ['eq',$stockin_info['storage_code']],
        ];
        $loss_arr = $this->getModel("ErpLossOrder")->where($loss_where)->field('order_status')->find();
        if ( isset($loss_arr['order_status']) && $loss_arr['order_status'] != 2 ) {
            cancelCacheLock('ErpStock/actDeleteErpStockIn');
            return ['status' => 111,'message' => '请先取消所对应的损耗单！'];
        }

        if (getCacheLock('ErpStock/actDeleteErpStockIn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStock/actDeleteErpStockIn', 1);
        /*
          YF 添加 （调拨单入库单 更改调拨单入库状态）
          Time 2019-07-03
        */
        M()->startTrans();
        if ($stockin_info['storage_type'] == 2) {
           $allocation_order_where = ['order_number'=> $stockin_info['source_number']];
           $stock_where = [
                'source_number'     => ['eq',$stockin_info['source_number']],
                'storage_status'    => ['neq',2],
                'reversed_status'   => ['eq',2],
                'is_reverse'        => ['eq',2],
            ];
            $stock_num = $this->getModel('ErpStockIn')->where($stock_where)->count();
            if ( $stock_num < 2 ) {
                $save_data = ['storage_status' => 2];
            }
            $allocation_Order_arr = $this->getModel("ErpAllocationOrder")->where($allocation_order_where)->field('actual_in_num_liter','actual_in_num')->find();
            if ( isset($allocation_Order_arr['actual_in_num_liter']) ) {
                M()->rollback();
                cancelCacheLock('ErpStock/actDeleteErpStockIn');
                return ['status' => 113,'message' => '未查询到调拨单！'];
            }     
            $save_data['actual_in_num'] = ($allocation_Order_arr['actual_in_num'] - $stockin_info['storage_num']);
            $save_data['actual_in_num_liter'] = ( $allocation_Order_arr['actual_in_num_liter'] - ($stockin_info['storage_num']*1000) );
            $save_status = $this->getModel("ErpAllocationOrder")->where($allocation_order_where)->save($save_data);
            if ( !$save_status ) {
                M()->rollback();
                cancelCacheLock('ErpStock/actDeleteErpStockIn');
                return ['status' => 112,'message' => '调拨单入库状态更改失败！'];
            }
        }
        /*
            END
        */
        $stock_in_data = [
            'storage_status' => 2,
        ];
        $status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$id],$stock_in_data);
        if ($status_stockin) {
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

        cancelCacheLock('ErpStock/actDeleteErpStockIn');
        return $result;
    }

    /**
     * 审核入库单
     * @author senpai
     * @time 2017-05-05
     */
    public function actAuditErpStockIn($id)
    {
        if (!$id) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result;
        }
        $stockin_info = $this->getModel('ErpStockIn')->findStockIn(['si.id'=>$id],'si.*');

        if ($stockin_info['storage_status'] != 1) {
            $result['status'] = 101;
            $result['message'] = "只有未审核状态下才可审核";
            return $result;
        }

        if ($stockin_info['storage_type'] == 1) {
            //目前只有采购单在审核时才会去验证订单数量是否满足入库数量
            $field = '*';
            $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $stockin_info['source_object_id']], $field);
            //定金锁价入库单限制不同。根据已转待提来计算
            if ($order_info['pay_type'] == 5) {
                $no_in_num = $order_info['total_purchase_wait_num'] - $order_info['storage_quantity'];
            } else {
                $no_in_num = $order_info['goods_num'] - $order_info['storage_quantity'];
            }
            log_info("订单未入库数量：" . $no_in_num);
            log_info("实际入库数量：" . $stockin_info['actual_storage_num']);
            if ($stockin_info['actual_storage_num'] > $no_in_num) {
                $result['status'] = 101;
                $result['message'] = "审核入库单数量不能大于订单数量";
                return $result;
            }
        }
        //验证库存调整\实物盘赢类型入库 价格为0时，不能审核通过，必须财务先编辑价格后再提交审核 edit xiaowen 2018-02-9
        if(in_array($stockin_info['storage_type'], [4,5]) && $stockin_info['price'] == 0){
            $result['status'] = 102;
            $result['message'] = "盘点入库单请先联系财务编辑价格后，再审核";
            return $result;
        }
        //销退入库审核，需验证销退单状态是否已确认 edit xiaowen 2019-3-11
        if($stockin_info['storage_type'] == 3){
            $returned_order_info = $this->getModel('ErpReturnedOrder')->where(['order_number'=>$stockin_info['source_number'], 'order_type'=>1])->find();

            if($returned_order_info['order_status'] != 10){
                $result['status'] = 103;
                $result['message'] = "请先确认销退货单后，再审核";
                return $result;
            }
        }
        //采购入库单验证是否填写货权信息 2019-2-19 xiaowen
        if($stockin_info['storage_type'] == 1 && empty($stockin_info['cargo_bn_id'])){
            $result['status'] = 103;
            $result['message'] = "请完善入库单货权信息后，再审核";
            return $result;
        }

        //除采购入库单外，实际入库数量大于0，必须存在批次id edit xiaowen 2019-2-28
        //加入调拨  guanyu 2019-05-23
        if($stockin_info['storage_type'] != 1 && $stockin_info['storage_type'] != 2 && $stockin_info['actual_storage_num'] > 0 && empty($stockin_info['batch_id'])){
            $result['status'] = 104;
            $result['message'] = "入库单货权批次信息不完善，无法审核";
            return $result;
        }

        $loss_where = [
            'source_number' => $stockin_info['storage_code'],
            'order_status' => ['not in',[2,10]],
        ];
        $loss_order = $this->getModel('ErpLossOrder')->where($loss_where)->find();
        if (!empty($loss_order)) {
            $result['status'] = 105;
            $result['message'] = "请先完成对应损耗单的确认";
            return $result;
        }

        // cancelCacheLock('ErpStock/actAuditErpStockIn');
        if (getCacheLock('ErpStock/actAuditErpStockIn')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStock/actAuditErpStockIn', 1);
        M()->startTrans();

        switch ($stockin_info['storage_type']) {
            case 1 :
                $stockin_info['price'] = $order_info['price'];
                $status = $this->auditPurchaseStockIn($stockin_info);
                break;
            case 2 :
                $status = $this->auditAllocationStockIn($stockin_info);
                break;
            case 3 :
                $status = $this->auditSaleReturnStockIn($stockin_info);
                break;
            default:
                $status = $this->auditOtherStockIn($stockin_info);
        }
        if ($status['status']) {
            M()->commit();
            //=============网点的入库审核，需要调用零售出库接口，生成零售出库单 edit xiaowen 2019-3-6===
            if($stockin_info['stock_type'] == 4){
                retailOrderCreate($stockin_info['storehouse_id'], $stockin_info['our_company_id']);
            }
            //=============end 零售出库==================================================================
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status' => 0,
                'message' => $status['message'],
            ];
        }

        cancelCacheLock('ErpStock/actAuditErpStockIn');
        return $result;
    }

    /**
     * 采购单对应入库单审核操作
     */
    public function auditPurchaseStockIn($stockin_info)
    {
        $field = '*';
        $order_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $stockin_info['source_object_id']], $field);
        //入库单审核生成批次数据 xiaowen 2019-2-19
        $batch_result = $this->getEvent('ErpBatch', MODULE_NAME)->addBatch($stockin_info);
        //print_r($batch_result);
        //修改入库单状态
        $stock_in_data = [
            'storage_status' => 10,
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            //将采购单价填充在入库单价格上
            'price'     => $stockin_info['price'],
            //保存入库单批次信息 xiaowen 2019-2-19
            'batch_sys_bn' => $batch_result['data']['sys_bn'],
            'batch_id' => $batch_result['data']['id'],
        ];
        $status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$stockin_info['id']],$stock_in_data);

        $loss_order = $this->getModel('ErpLossOrder')->where(['source_number'=>$stockin_info['storage_code'],'order_status'=>10])->find();
        $loss_num = 0;
        if (!empty($loss_order)) {
            $loss_num += $loss_order['loss_num'];
        }
        /** --------入库单审核影响库存-start------- **/
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['type'] == 1 ? getAllocationStockType($order_info['storehouse_id']) : 2,
        ];
        $stock_info = $this->getStockInfo($stock_where);
        //获取入库单改变物理库存之前的物理库存 edit xiaowen
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['type'] == 1 ? getAllocationStockType($order_info['storehouse_id']) : 2,
            'region' => $order_info['region'],
            'transportation_num' => $stock_info['transportation_num'] - $stockin_info['actual_storage_num'] - $loss_num,
            'stock_num' => $stock_info['stock_num'] + $stockin_info['actual_storage_num'],
        ];

        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的库存信息
        $stock_info['stock_num'] = $data['stock_num'];

        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stockin_info['storage_code'],
            'object_type' => 4,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->saveStockInfo($data, $stockin_info['actual_storage_num'], $orders);
        /** --------入库单审核影响库存-start------- **/

        //更新采购单入库数量
        $order_data = [
            'storage_quantity' => $stockin_info['actual_storage_num'] + $order_info['storage_quantity'],
            'update_time' => $this->date,
        ];
        $status_purchase = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id'=>$stockin_info['source_object_id']],$order_data);

        if ($status_stockin && $stock_status && $status_purchase && $batch_result['status'] == 1) {
            //计算加权成本 edit xiaowen 2018-2-7
            $stockin_info['before_stock_num'] = $beforeNum;
            $stockin_info['stock_id'] = $stockId ? $stockId : 0;
            $stockin_info['change_num'] = $stockin_info['actual_storage_num'];
            //重新计算加权成本
            updateStockInCost($stockin_info);

            //是否有损耗？若有损耗，则进行三笔加权（或者两笔，取决于是否有超损）
            if (!empty($loss_order) && $loss_order['reasonable_loss_num'] > 0) {
                //合理损耗影响成本
                $reasonable_stock_in_info = $stockin_info;
                $reasonable_stock_in_info['before_stock_num'] = $stockin_info['before_stock_num'] + $stockin_info['change_num'];
                $reasonable_stock_in_info['change_num'] = $loss_order['reasonable_loss_num'];
                updateStockInCost($reasonable_stock_in_info);

                //这里合理出单价固定为0，当做0成本入库红冲处理
                $reasonable_stock_out_info = $stockin_info;
                $reasonable_stock_out_info['before_stock_num'] = $reasonable_stock_in_info['before_stock_num'] + $reasonable_stock_in_info['change_num'];
                $reasonable_stock_out_info['change_num'] = $loss_order['reasonable_loss_num'] * -1;
                $reasonable_stock_out_info['price'] = 0;
                updateStockInCost($reasonable_stock_out_info);
            }
            $status = true;
        } else {
            $status = false;
        }
        return ['status' => $status, 'message' => ''];
    }

    /**
     * 调拨单对应入库单审核操作
     */
    public function auditAllocationStockIn($stockin_info)
    {
        $field = '*';
        $order_info = $this->getModel('ErpAllocationOrder')->where(['id' => $stockin_info['source_object_id']], $field)->find();
        //修改入库单状态
        $stock_in_data = [
            'storage_status' => 10,
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
        ];
        $status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$stockin_info['id']],$stock_in_data);

        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'region' => $order_info['in_region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //入库方变动前的物理库存 edit xiaowen 2018-2-9
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        //对应批次的出库单信息
        $stock_out_info = $this->getModel('ErpStockOut')->where(['source_number'=>$order_info['order_number'],'batch_sys_bn'=>$stockin_info['batch_sys_bn'],'reversed_status'=>2,'is_reverse'=>2])->find();
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['in_storehouse'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'facilitator_id' => $order_info['allocation_type'] == 1 || $order_info['allocation_type'] == 2 ? $order_info['in_facilitator_id'] : '',
            'region' => $order_info['in_region'],
            'transportation_num' => $stock_info['transportation_num'] - $stock_out_info['actual_outbound_num'], //入库方在途 - 实际出库数量（也是入库方在途数量）
            'stock_num' => $stock_info['stock_num'] + $stockin_info['actual_storage_num'], //入库方的实际出库数量加到入库方的物理
        ];

        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的预留库存
        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stockin_info['storage_code'],
            'object_type' => 4,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $stockin_info['actual_storage_num'], $orders);

        //查询调入方是否有该批次数据
        $where = [
            'storehouse_id' => $order_info['in_storehouse'],
            'goods_id' => $order_info['goods_id'],
            'our_company_id' => $order_info['our_company_id'],
            'stock_type' => getAllocationStockType($order_info['in_storehouse']),
            'sys_bn' => $stockin_info['batch_sys_bn'],
        ];
        $in_batch = $this->getModel('ErpBatch')->where($where)->find();
        if (!empty($in_batch)) {
            //更新批次数据
            $batch_data = [
                'batch_id' => $in_batch['id'],
                'change_total_num' => $stockin_info['actual_storage_num'],
                'change_balance_num' => $stockin_info['actual_storage_num'],
                'change_reserve_num' => 0,
                'change_type' => 1,
                'change_number' => $stockin_info['storage_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_data);
            $batch_status = $batch_result['status'] == 1 ? true : false;
            $new_batch_id = $in_batch['id'];
        } else {
            //新增批次数据
            $batch_data = [
                'sys_bn' => $stockin_info['batch_sys_bn'],
                'cargo_bn_id' => $stockin_info['cargo_bn_id'],
                'goods_id' => $order_info['goods_id'],
                'storehouse_id' => $order_info['in_storehouse'],
                'our_company_id' => $order_info['our_company_id'],
                'region' => $order_info['in_region'],
                'stock_type' => getAllocationStockType($order_info['in_storehouse']),
                'total_num' => $stockin_info['actual_storage_num'],
                'balance_num' => $stockin_info['actual_storage_num'],
                'reserve_num' => 0,
                'status' => 1,
                'data_source' => 1,
                'data_source_number' => $stockin_info['storage_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->AddBatch($batch_data);
            $batch_status = $batch_result['status'] ? true : false;
            $new_batch_id = $batch_result['batch_id'];
        }
        $stock_in_data['batch_id'] = $new_batch_id;
        $this->getModel('ErpStockIn')->where(['storage_code'=>$stockin_info['storage_code']])->save($stock_in_data);

        //更新调拨单入库状态
        $stock_in_where = [
            'source_number' => $stockin_info['source_number'],
            'storage_status' => ['not in',[2,10]]
        ];
        $stock_in_order = $this->getModel('ErpStockIn')->where($stock_in_where)->find();
        if (empty($stock_in_order)) {
            $storage_status = 1;
        } else {
            $storage_status = 3;
        }
        $order_data = [
            'storage_status' => $storage_status,
            'update_time' => $this->date,
        ];
        $status_allocation = $this->getModel('ErpAllocationOrder')->saveAllocationOrder(['id'=>$stockin_info['source_object_id']],$order_data);

        if ($status_stockin && $in_stock_status && $batch_status && $batch_result['status'] == 1 && $status_allocation) {
            //计算加权成本 edit xiaowen 2018-2-7
            $stockin_info['before_stock_num'] = $beforeNum;
            $stockin_info['stock_id'] = $stockId ? $stockId : 0;
            $stockin_info['change_num'] = $stockin_info['actual_storage_num'];
            //重新计算加权成本
            updateStockInCost($stockin_info);

            //是否有损耗？若有损耗，则进行三笔加权（或者两笔，取决于是否有超损）
            $loss_order = $this->getModel('ErpLossOrder')->where(['source_number'=>$stockin_info['storage_code'],'order_status'=>10])->find();
            if (!empty($loss_order) && $loss_order['reasonable_loss_num'] > 0) {
                //合理损耗影响成本
                $reasonable_stock_in_info = $stockin_info;
                $reasonable_stock_in_info['before_stock_num'] = $stockin_info['before_stock_num'] + $stockin_info['change_num'];
                $reasonable_stock_in_info['change_num'] = $loss_order['reasonable_loss_num'];
                updateStockInCost($reasonable_stock_in_info);

                //这里合理出单价固定为0，当做0成本入库红冲处理
                $reasonable_stock_out_info = $stockin_info;
                $reasonable_stock_out_info['before_stock_num'] = $reasonable_stock_in_info['before_stock_num'] + $reasonable_stock_in_info['change_num'];
                $reasonable_stock_out_info['change_num'] = $loss_order['reasonable_loss_num'] * -1;
                $reasonable_stock_out_info['price'] = 0;
                updateStockInCost($reasonable_stock_out_info);
            }

            if(intval($order_info['allocation_type']) == 1) {
                //如果是调入到网点，生成零售出库单--------------------------------------
                retailOrderCreate($order_info['in_storehouse'], $order_info['our_company_id']);
                //----------------------------------------------------------------------
            }

            $status = true;
        } else {
            $status = false;
        }
        return ['status' => $status, 'message' => ''];
    }

    /**
     * 销退单对应入库单审核操作
     */
    public function auditSaleReturnStockIn($stockin_info)
    {
        $field = 'ro.*,o.goods_id,o.storehouse_id,o.is_agent,o.region';
        $order_info = $this->getModel('ErpReturnedOrder')->findReturnedOrder(['ro.id'=>$stockin_info['source_object_id'],'ro.order_number'=>$stockin_info['source_number'],'ro.order_type'=>1,'ro.our_company_id'=>session('erp_company_id')],$field);
        if($order_info['order_status'] != 10){
            return ['status' => false, 'message' => '请先确认销退单，再审核入库单'];
        }
        //根据当前的退货数量来计算平均成本（可能来源于多张出库单）
        $price = $this->getSaleReturnPrice($order_info,$stockin_info);
        $stockin_info['price'] = $price;
        //---------------------------------------------------------
        log_info("入库单：成本" . $price);

        //修改入库单状态
        $stock_in_data = [
            'storage_status' => 10,
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'price' => $price,
        ];
        $status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$stockin_info['id']],$stock_in_data);

        /** --------入库单审核影响库存-start------- **/
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
        ];
        $stock_info = $this->getStockInfo($stock_where);
        //保存变动物理库存之前的物理数量 eidt xiaowen---------------
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //----------------------------------------------------------
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $order_info['goods_id'],
            'object_id' => $order_info['storehouse_id'],
            'stock_type' => $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']),
            'region' => $order_info['region'],
            'sale_wait_num' => $stock_info['sale_wait_num'] - ($stockin_info['storage_num'] - $stockin_info['actual_storage_num']),
            'stock_num' => $stock_info['stock_num'] + $stockin_info['actual_storage_num'],
        ];

        $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的库存信息
        $stock_info['stock_num'] = $data['stock_num'];

        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stockin_info['storage_code'],
            'object_type' => 4,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->saveStockInfo($data, $stockin_info['actual_storage_num'], $orders);

        $where = [
            'goods_id' => $data['goods_id'],
            'object_id' => $data['object_id'],
            'stock_type' => $data['stock_type'],
            'our_company_id' => session('erp_company_id') ? session('erp_company_id') : $orders['our_company_id'],//增加当前公司帐号条件 edit xiaowen 2017-5-22
        ];

        $stock_info = $this->getModel('ErpStock')->findStock($where);

        $log_data = [
            'stock_id' => $stock_info['id'],
            'object_number' => $orders['object_number'],
            'object_type' => $orders['object_type'],
            'log_type' => $orders['log_type'],
            'change_num' => $stockin_info['storage_num'] - $stockin_info['actual_storage_num'],
            'before_stock_num' => $stock_info['stock_num'],
            'before_transportation_num' => $stock_info['transportation_num'],
            'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
            'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
            'before_sale_wait_num' => $stock_info['sale_wait_num'],
            'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
            'before_available_num' => $stock_info['available_num'],
            'after_stock_num' => $stock_info['stock_num'],
            'after_transportation_num' => $stock_info['transportation_num'],
            'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
            'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
            'after_sale_wait_num' => $stock_info['sale_wait_num'],
            'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
            'after_available_num' => $stock_info['available_num'],
        ];

        $stock_log_status = $this->addStockLog($log_data);
        /** --------入库单审核影响库存-end------- **/
        // =======edit xiaowen 2019-2-25 销退入库更新批次数量============================
        if($stockin_info['actual_storage_num'] > 0 && $stockin_info['batch_id'] > 0){
            $batch_change_data = [
                'batch_id' => $stockin_info['batch_id'],
                'change_balance_num' => $stockin_info['actual_storage_num'],
                'change_type' => 1,
                'change_number' => $stockin_info['storage_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);

            if($batch_result['status'] !=1){
                $batch_result['status'] = false;
                return $batch_result;
            }

        }else{
            $batch_result['status'] = 1;
        }
        //==========end 批次处理==========================================================
        //判断原订单是否为代采，若为代采，则判断是否存在对应的代采采购单，若存在，则生成已确认的采购退货单及对应出库单
        if ($order_info['is_agent'] == 1) {
            $purchase_order_info = $this->getModel('ErpPurchaseOrder')
                ->where(['from_sale_order_id' => $order_info['source_order_id'], 'from_sale_order_number' => $order_info['source_order_number'], 'our_buy_company_id' => session('erp_company_id'), 'order_status' => 10])
                ->find();
            if($purchase_order_info && $purchase_order_info['storage_quantity'] > 0){
                $purchase_order_status = $this->agentRsStockInAudit($order_info,$stockin_info, $purchase_order_info);
                $err_message = $purchase_order_status ? '代采采购退货处理成功' : '代采采购退货处理失败';
            }else{
                $purchase_order_status = false;
                $err_message = '代采采购单未入库，无法退货';
            }

        }else{
            $purchase_order_status = true;
        }
        $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id'=>intval($order_info['source_order_id'])], 'returned_goods_num');
        //修改原订单的退货数量,因批次原因，销退与入库为一对多，入库审核应在销售单退货数量上进行累加，xiaowen 2019-2-27
        $sale_order_data = [
            'returned_goods_num' => $sale_order_info['returned_goods_num'] + $stockin_info['storage_num'],
//            'collection_status' => 4, //edit xiaowen 销退单入库单审核通过不影响原订单收款状态
            'update_time' => $this->date,
            'updater' => $this->getUserInfo('id'),
        ];
        $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => intval($order_info['source_order_id']),'order_number'=>$order_info['source_order_number']], $sale_order_data);

        if ($status_stockin && $stock_status && $sale_order_status && $purchase_order_status && $stock_log_status && $batch_result['status'] == 1) {
            //重新计算加权成本
            $stockin_info['before_stock_num'] = $beforeNum;
            $stockin_info['stock_id'] = $stockId ? $stockId : 0;
            $stockin_info['change_num'] = $stockin_info['actual_storage_num'];
            updateStockInCost($stockin_info);
            $status = true;
        } else {
            $status = false;
        }
        return ['status' => $status, 'message' => $err_message ? $err_message : ''];
    }

    /**
     * 根据当前的退货数量来计算平均成本（可能来源于多张出库单）
     * 此处的需求为：
     * 销售退货时，入库单的单价取对应销售单出库单的平均加权单价
     * @param array $order_info  array $stock_in_info
     * @return int
     */
    public function getSaleReturnPrice($order_info,$stock_in_info)
    {
        $where = [
            'source_number' => $order_info['source_order_number'],
            'outbound_status' => 10,
            'is_reverse' => 2,
            'reversed_status' => 2
        ];
        $stock_out_info = $this->getModel('ErpStockOut')->where($where)->order('id desc')->select();

        /**
         * $num 当前剩余量
         * $total_num 入库单总量
         * $price 成本
         * $difference_value 本次循环之前的总量
         */
        $num = $total_num = $stock_in_info['actual_storage_num'];
        $price = 0;
        $difference_value = 0;

        foreach ($stock_out_info as $value) {
            if ($num <= $value['actual_outbound_num']) {
                if ($price == 0) {
                    $price = $value['cost'];
                } else {
                    $difference_value = $total_num - $num;
                    $price = ($price * $difference_value + $num * $value['cost']) / $total_num;
                }
                break;
            } else {
                if ($price == 0) {
                    $price = $value['cost'];
                } else {
                    $difference_value = $total_num - $num;
                    $price = ($price * $difference_value + $value['actual_outbound_num'] * $value['cost']) / ($difference_value + $value['actual_outbound_num']);
                }
                $num = $num - $value['actual_outbound_num'];
            }
        }

        return $price;
    }

    /**
     * 生成库存操作日志
     * @param array $data
     * @return bool
     */
    public function getStockInInfo($id)
    {
        $data = $this->getModel('ErpStockIn')->findStockIn(['si.id'=>$id],'si.*');
        return $data;
    }

    /**
     * 计算库存可售数量
     * @param array $where 数据中必须包含 $where['goods_id']， $where['object_id'] ， $where['stock_type'], $where['region']
     * @author xiaowen
     * @time 2017-05-03
     * @return bool
     */
    public function getStockSaleNum($where = []){
        if($where['goods_id'] && $where['object_id'] && $where['stock_type']){
//            $where['our_company_id'] = session('erp_company_id'); //增加当前公司帐号条件 edit xiaowen 2017-5-22
            $where['our_company_id'] = session('erp_company_id') ? session('erp_company_id') : $where['our_company_id']; //增加当前公司帐号条件 edit xiaowen 2017-5-22
            $stock_info = $this->getModel('ErpStock')->findStock($where);
            $goods_info = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion(['goods_id'=>$where['goods_id'], 'region'=>$where['region'], 'our_company_id'=>session('erp_company_id')]);

            if(empty($stock_info)){

                $stock_info['available_num'] = 0;

            }
            //$num = $goods_info['available_sale_stock'] + $stock_info['available_num'] - $goods_info['current_use_stock']; //可售库存 = 区域商品维护可售数量 + 库存表可用数量 - 区域商品快照可用数量
            //$num = $goods_info['available_sale_stock'] + $stock_info['available_num'] - $goods_info['available_use_stock']; //可售库存 = 区域商品维护可售数量 + 库存表可用数量 - 区域商品快照可用数量 - (当前可用-快照可用 > 0 差值)

            //当前可用与快照可用的差值：当前可用 > 快照可用 ? 当前可用 - 快照可用 : 0  edit xiaowen 2019-3-28 修正入库导致当前可售增加的问题
            $change_num = $stock_info['available_num'] > $goods_info['available_use_stock'] ? $stock_info['available_num'] - $goods_info['available_use_stock'] : 0;
            $num = $goods_info['available_sale_stock'] + $stock_info['available_num'] - $goods_info['available_use_stock'] - $change_num; //可售库存 = 区域商品维护可售数量 + 库存表可用数量 - 区域商品快照可用数量 - (当前可用-快照可用 > 0 差值)
        }else{

            $num = 0;
        }

        return $num;
    }

    /**
     * 保存更新库存
     * @param array $data 数据中必须包含 $data['goods_id']， $data['object_id'] ， $data['stock_type']这三个值做为唯一索引
     * @param int $change_num
     * @param array $orderInfo $orderInfo['object_number'] 单据号 $orderInfo['object_type'] 单据类型 $orderInfo['log_type'] 操作类型
     * @author xiaowen
     * @time 2017-05-03
     * @return bool
     */
    public function saveStockInfo($data = [], $change_num = 0, $orderInfo = [], $check_per = true)
    {
        $status = false;
        if(session('erp_company_id')) {
            $match_our_company_id = (ourCompanyPer(session('erp_company_id')) == substr($orderInfo['object_number'], 0, 3));
            if (!$match_our_company_id && $check_per) {
                return false;
            }
        }
        if($data['goods_id'] && $data['object_id'] && $data['stock_type']) {
            log_info("1111");
            $data['our_company_id'] = $orderInfo['our_company_id'] ? $orderInfo['our_company_id'] : session('erp_company_id');//增加当前公司帐号条件 edit xiaowen 2017-5-22 当session中不存在时，取$orderInfo,定时取消订单回滚库存时必须包含$orderInfo['our_company_id']

            //全国类仓库库存记录region = 1;
            if ($data['stock_type'] != 4) {
                $data['region'] = $this->getEvent('ErpStorehouse')->getRegionByStorehouseAttribute($data['object_id']);
            }

            $where = [
                'region' => $data['region'],
                'goods_id' => $data['goods_id'],
                'object_id' => $data['object_id'],
                'stock_type' => $data['stock_type'],
                'our_company_id' => $data['our_company_id'],//增加当前公司帐号条件 edit xiaowen 2017-5-22
            ];

            $before_stock_info = $this->getModel('ErpStock')->findStock($where);

            if ($data['stock_num']) {
                $data['stock_num'] = round($data['stock_num'], 4);
            }
            if ($data['transportation_num']) {
                $data['transportation_num'] = round($data['transportation_num'], 4);
            }
            if ($data['sale_reserve_num']) {
                $data['sale_reserve_num'] = round($data['sale_reserve_num'], 4);
            }
            if ($data['allocation_reserve_num']) {
                $data['allocation_reserve_num'] = round($data['allocation_reserve_num'], 4);
            }
            if ($data['sale_wait_num']) {
                $data['sale_wait_num'] = round($data['sale_wait_num'], 4);
            }
            if ($data['allocation_wait_num']) {
                $data['allocation_wait_num'] = round($data['allocation_wait_num'], 4);
            }
            if ($data['available_num']) {
                $data['available_num'] = round($data['available_num'], 4);
            }
            $status = $this->getModel('ErpStock')->saveStock($data);
            log_info("库存状态：". $status);
            $stock_info = $this->getModel('ErpStock')->findStock($where);

            //影响物理库存时插入库存出入明细
            if (in_array($orderInfo['object_type'],[3,4])) {
                $detail_status = $this->addStockDetail($stock_info,$orderInfo,$change_num);
            } else {
                $detail_status = true;
            }

            if (!empty($before_stock_info)) {

                $log_data = [
                    'stock_id' => $stock_info['id'],
                    'object_number' => $orderInfo['object_number'],
                    'object_type' => $orderInfo['object_type'],
                    'log_type' => $orderInfo['log_type'],
                    'change_num' => $change_num,
                    'before_stock_num' => $before_stock_info['stock_num'],
                    'before_transportation_num' => $before_stock_info['transportation_num'],
                    'before_sale_reserve_num' => $before_stock_info['sale_reserve_num'],
                    'before_allocation_reserve_num' => $before_stock_info['allocation_reserve_num'],
                    'before_sale_wait_num' => $before_stock_info['sale_wait_num'],
                    'before_allocation_wait_num' => $before_stock_info['allocation_wait_num'],
                    'before_available_num' => $before_stock_info['available_num'],
                    'before_init_stock_num' => $before_stock_info['init_stock_num'], //增加期初库存变动前数量(盘点期初) 2018-1-12 xiaowen
                ];

            }else{

                $log_data = [
                    'stock_id' => $stock_info['id'],
                    'object_number' => $orderInfo['object_number'],
                    'object_type' => $orderInfo['object_type'],
                    'log_type' => $orderInfo['log_type'],
                    'change_num' => $change_num,
                    'before_stock_num' => 0,
                    'before_transportation_num' => 0,
                    'before_sale_reserve_num' => 0,
                    'before_allocation_reserve_num' => 0,
                    'before_sale_wait_num' => 0,
                    'before_allocation_wait_num' => 0,
                    'before_available_num' => 0,
                    'before_init_stock_num' => 0,
                ];

            }

            $log_data['after_stock_num'] = $stock_info['stock_num'];
            $log_data['after_transportation_num'] = $stock_info['transportation_num'];
            $log_data['after_sale_reserve_num'] = $stock_info['sale_reserve_num'];
            $log_data['after_allocation_reserve_num'] = $stock_info['allocation_reserve_num'];
            $log_data['after_sale_wait_num'] = $stock_info['sale_wait_num'];
            $log_data['after_allocation_wait_num'] = $stock_info['allocation_wait_num'];
            $log_data['after_available_num'] = $stock_info['available_num'];
            $log_data['after_init_stock_num'] = $stock_info['init_stock_num']; //增加期初库存变动后数量(盘点期初) 2018-1-12 xiaowen

            $log_status = $this->addStockLog($log_data);
            log_info("库存日志状态：". $log_status);
            $status = $status && $log_status && $detail_status ? true : false;

        }

        return $status;
    }

    /**
     * 生成库存操作日志
     * @param array $data
     * @return bool
     */
    public function addStockLog($data = []){

        $status = false;
        if($data){
            $data['create_time'] = currentTime();
            $data['operator_id'] = $this->getUserInfo('id') ? $this->getUserInfo('id') : 0;
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : '';
            $status = $this->getModel('ErpStockLog')->addStockLog($data);
        }
        return $status;
    }

    /**
     * 生成出入库明细记录
     * @param array $data
     * @return bool
     */
    public function addStockDetail($stock_info,$orderInfo,$change_num)
    {
        if ($stock_info['stock_type'] != 4) {
            $model = 'ErpStockDetail';
        } else {
            $model = 'ErpStockDetailRetail';
        }
        $stock_detail_info = $this->getModel($model)->where(['stock_id'=>$stock_info['id']])->order('id desc')->find();

        if ($orderInfo['object_type'] == 3) {
            $stock_out_info = $this->getModel('ErpStockOut')->where(['outbound_code'=>$orderInfo['object_number']])->find();
        } elseif ($orderInfo['object_type'] == 4) {
            $stock_in_info = $this->getModel('ErpStockIn')->where(['storage_code' => $orderInfo['object_number']])->find();
        }

        $before_stock_num = $stock_detail_info['after_stock_num'] ? $stock_detail_info['after_stock_num'] : 0;
        $before_price = $stock_detail_info['after_price'] ? $stock_detail_info['after_price'] : 0;
        //销售产生的合理入合理出超损入超损出不影响库存成本
        $is_sale = 2;
        //出库审核带出成本
        if ($orderInfo['object_type'] == 3 && $orderInfo['log_type'] != 12) {
            $source_order_number = $stock_out_info['source_number'];
            $after_stock_num = $before_stock_num - $change_num;
            $after_price = $price = $stock_detail_info['after_price'] ? $stock_detail_info['after_price'] : $stock_out_info['cost'];

            //销售对应的超损出为0成本
            if ($stock_out_info['is_other'] == 1 && $stock_out_info['loss_type'] == 1) {
                $loss_order = $this->getModel('ErpLossOrder')->where(['order_number'=>$stock_out_info['source_number']])->find();
                if ($loss_order['type'] == 3) {
                    $is_sale = 1;
                    $price = 0;
                }
            }
            $type = 1;
        }
        //出库红冲参与加权
        elseif ($orderInfo['object_type'] == 3 && $orderInfo['log_type'] == 12) {
            $source_order_number = $stock_out_info['source_number'];
            $after_stock_num = $before_stock_num - $change_num;
            $price = $stock_out_info['cost'];
            $after_price = setNum(round((getNum($before_price) * getNum($before_stock_num) - getNum($change_num) * getNum($price)) / getNum($before_stock_num - $change_num),2));
            //销售对应的合理出不参与加权
            if ($stock_out_info['is_other'] == 1) {
                $loss_order = $this->getModel('ErpLossOrder')->where(['order_number'=>$stock_out_info['source_number']])->find();
                if ($loss_order['type'] == 3) {
                    $is_sale = 1;
                    $price = 0;
                    $after_price = $stock_detail_info['after_price'] ? $stock_detail_info['after_price'] : $stock_out_info['cost'];
                }
            }
            $type = 1;
        }
        //入库审核参与加权
        elseif ($orderInfo['object_type'] == 4) {
            $source_order_number = $stock_in_info['source_number'];
            $after_stock_num = $before_stock_num + $change_num;
            $price = $stock_in_info['price'];
            $after_price = setNum(round((getNum($before_price) * getNum($before_stock_num) + getNum($change_num) * getNum($price)) / getNum($before_stock_num + $change_num),2));
            //销售对应的合理入和超损入不参与加权
            if ($stock_in_info['is_other'] == 1) {
                $loss_order = $this->getModel('ErpLossOrder')->where(['order_number'=>$stock_in_info['source_number']])->find();
                if ($loss_order['type'] == 3) {
                    $is_sale = 1;
                    $after_price = $before_price;
                }
            }
            //采购入库判断是否有损耗且变动前物理库存是否为0，若都满足，则进行特殊处理
            if (in_array($stock_in_info['storage_type'],[1,2]) && $before_stock_num == 0) {
                $loss_order = $this->getModel('ErpLossOrder')->where(['source_number'=>$stock_in_info['storage_code']])->find();
                if (!empty($loss_order)) {
                    $after_price = setNum(round(getNum($change_num + $loss_order['reasonable_loss_num']) * getNum($price) / getNum($change_num),2));
                }
            }
            $type = 2;
        }

        //影响库存成本
        if ($stock_detail_info) {
            if ($orderInfo['object_type'] == 3) {
                $stock_price = $stock_detail_info['stock_price'] - getNum($change_num * $price);
            } elseif ($orderInfo['object_type'] == 4) {
                $stock_price = $stock_detail_info['stock_price'] + getNum($change_num * $price);
            }
            if ($is_sale == 1) {
                $stock_price = $stock_detail_info['stock_price'];
            }
        } else {
            $stock_price = getNum($change_num * $price);
        }

        $detail_data = [
            'stock_id' => $stock_info['id'],
            'type' => $type,
            'source_number' => $orderInfo['object_number'],
            'source_order_number' => $source_order_number,
            'change_num' => $change_num,
            'price' => $price,
            'before_stock_num' => $before_stock_num,
            'after_stock_num' => $after_stock_num,
            'before_price' => $before_price,
            'after_price' => $after_price,
            'stock_price' => $stock_price,
        ];

        $detail_data['create_time'] = currentTime();
        $detail_data['operator_id'] = $this->getUserInfo('id') ? $this->getUserInfo('id') : 0;
        $detail_data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : '';
        $status = $this->getModel($model)->addStockDetail($detail_data);

        return $status;
    }

    /**
     * 返回一条库存信息
     * @param $where
     * @return mixed
     * @author xiaowen
     * @time 2017-05-03
     */
    public function getStockInfo($where){
        $where['status'] = 1;
        $where['our_company_id'] = $where['our_company_id'] ? $where['our_company_id'] : session('erp_company_id');//增加当前公司帐号条件 edit xiaowen 2017-5-22
        //全国类仓库库存记录region = 1;
        if (($where['stock_type'] != 4) && isset($where['object_id']) ) {
            $where['region'] = $this->getEvent('ErpStorehouse')->getRegionByStorehouseAttribute($where['object_id']);
        }

        return $this->getModel('ErpStock')->findStock($where);
    }

    /**
     * 计算可用库存
     * @param $stock_info
     * @author xiaowen
     */
    public function calculateAvailableNum($stock_info = []){
        if($stock_info){
            return ($stock_info['stock_num'] + $stock_info['transportation_num']) - ($stock_info['sale_reserve_num'] + $stock_info['allocation_reserve_num'] + $stock_info['sale_wait_num'] + $stock_info['allocation_wait_num']);//$stock_info['available_num'];
        }
    }

    /**
     * 生成出库单
     * @param $order_info
     * @author xiaowen
     * @time 2017-5-5
     * @return array $result
     */
//    public function addStockOut($param,$files)
    public function addStockOut($param)
    {
        $sale_order_info = $this->getModel('ErpSaleOrder')->findSaleOrder(['id' => $param['source_object_id']]);
        //定金锁价未出库数量 = 已转待提数量 - 所有该销售单已审核出库单数量
        if ($sale_order_info['pay_type'] == 5) {
            $no_outbound_quantity = getNum($sale_order_info['total_sale_wait_num'] - $sale_order_info['outbound_quantity']);
        } else {
            $no_outbound_quantity = getNum($sale_order_info['buy_num'] - $sale_order_info['outbound_quantity']);
        }
        if(!self::checkOrderCanOutStock($sale_order_info)){
            return ["status" => 14 , "message" => "销售订单不可生成出库单"];
        }
        if(!$param['goods_id']){
            $result = [
                'status' => 2,
                'message' => '商品信息有误，请重新操作',
            ];
            return $result;
        }
        if(!$param['source_object_id']){
            $result = [
                'status' => 3,
                'message' => '来源单据有误，请重新操作',
            ];
            return $result;
        }
        if(!$param['outbound_type']){
            $result = [
                'status' => 4,
                'message' => '单据类型误，请重新操作',
            ];
            return $result;
        }
        if(!$param['depot_id']){
            $result = [
                'status' => 4,
                'message' => '请选择油库',
            ];
            return $result;
        }
        if(!$param['outbound_num']){
            $result = [
                'status' => 5,
                'message' => '请输入出库数量',
            ];
            return $result;
        }
        if($param['outbound_num'] > $no_outbound_quantity){ //outbound_quantity
            $result = [
                'status' => 6,
                'message' => '出库数量不能大于待出库数量，请重新操作',
            ];
            return $result;
        }
        if(!$this->checkOrderCanOutStock($sale_order_info)){
            $result = [
                'status' => 7,
                'message' => '订单不满足出库条件，请重新操作',
            ];
            return $result;
        }
        if(!isset($param['batch']) || empty($param['batch']) || !is_array($param['batch'])){
            return ['status' => 9, 'message' => '批次信息不正确'];
        }
        $applyWhere = [
            "outbound_apply_code" => $param['apply_code'] ,
            "status" => 1
        ];
        $applyInfo =  $this->getModel("ErpStockOutApply")->info("*" , $applyWhere);
        if(!$applyInfo){
            return ['status' => 7, 'message' => '出库申请信息不正确，请核查'];
        }
        if($param['outbound_num'] > $applyInfo['outbound_apply_num'] ){
            return ['status' => 8, 'message' => '出库的数量不能高于申请出库单的数量'];
        }
        $stockOutWhere = [
            "source_apply_number" =>  $param['apply_code'] ,
            "outbound_status" => ['in' , [1, 10]] ,
            "is_reverse" => 2 ,
            "reversed_status" => 2 ,
            "finance_status" => ['in' , [1 , 10]]
        ] ;
        $stockOutCount = $this->getModel("ErpStockOut")->stockOutCount($stockOutWhere);
        if($stockOutCount > 0){
            return ['status' => 10, 'message' => '申请出库单已经存在出库单，请核查相关数据'];
        }
        /*
         * 附件上传代码删除

        $upload_status = true;
        $error_photo = [];
        $attachment = [];
        if (!empty($files)) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if($value['size'] > 2*1024*1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
                } else {
                    continue;
                }
            }

            //上传文件
            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_out_attach']['src'];
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
                //已上传文件，如果操作失败要删除
                array_push($error_photo,$file_name);
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        }
        if(!$upload_status){
            return ['status' => 11, 'message' => '图片上次失败'];
        }
        */
        $branchIds = array_keys($param['batch']);
        $branchWhere = [
            "id" => ['in' , $branchIds]
        ] ;
        $branchField = "id , sys_bn";
        $batchInfos = $this->getModel("ErpBatch")->getFieldList($branchField , $branchWhere);
//        cancelCacheLock('ErpStock/addStockOut');
        if(getCacheLock('ErpStock/addStockOut'))
            return ['status' => 99, 'message' => $this->running_msg];
        M()->startTrans();
        setCacheLock('ErpStock/addStockOut', 1);
        //整理数据
        foreach ($param['batch'] as $key => $value){
            $actual_outbound_num = setNum($value) ;
            $stock_out_code = erpCodeNumber(7)['order_number'];
            $order_info[] = [
                "outbound_code" => $stock_out_code,
                "outbound_type" => $param['outbound_type'] ,
                "outbound_remark" => $param['outbound_remark'] ,
                "source_number" => $param['source_number'] ,
                "source_object_id" => $param['source_object_id'] ,
                "our_company_id" => $param['our_company_id'] ,
                "goods_id" => $param['goods_id'] ,
                "depot_id" => $param['depot_id'] ,
                'outbound_num' => $actual_outbound_num,
                'actual_outbound_num' => $actual_outbound_num ,
                "outbound_density" => $param['outbound_density'] ,
                'create_time' => currentTime(),
                'dealer_id' => $sale_order_info['dealer_id'] ,
                'dealer_name' => $sale_order_info['dealer_name'],///$this->getUserInfo('dealer_name');
                'creater_id' => $this->getUserInfo('id'),
                'creater_name' => $this->getUserInfo('dealer_name'),
                'storehouse_id' => $sale_order_info['storehouse_id'],
                'stock_type' => $sale_order_info['is_agent'] == 2 ? getAllocationStockType($sale_order_info['storehouse_id']) : 2 ,
                'region' => $sale_order_info['region'],
                "batch_sys_bn" => $batchInfos[$key] ,
                "batch_id" => $key ,
                "source_apply_number" => $param['apply_code'],
                "attachment" => isset($attachment["attachment"]) ? $attachment["attachment"] : "" ,
            ];
            $updateBatch = [
                "batch_id" => $key ,
                "change_balance_num" => 0 ,
                "change_reserve_num" => $actual_outbound_num ,
                "change_type" => 2 ,
                "change_number" => $stock_out_code //影响批次的出库单号
            ];

//            if(!$this->getEvent("ErpBatch")->commonChangeBatchNum($updateBatch)){
//                M()->rollback();
//                cancelCacheLock('ErpStock/addStockOut');
//                return  ['status' => 12, 'message' => '占用批次信息不正确'];
//            }
            $batch_result = $this->getEvent("ErpBatch")->commonChangeBatchNum($updateBatch);
            if($batch_result['status'] != 1){
                M()->rollback();
                cancelCacheLock('ErpStock/addStockOut');
                return  $batch_result;
            }
        }
        /******************************************************
         * 出库成功后，需要更新出库申请单状态 edit xiaowen 2019-3-10
         * ****************************************************
         */
        $update_stock_out_apply = $this->getModel('ErpStockOutApply')->where(['outbound_apply_code'=>$param['apply_code']])->save(
            [
                'status' => 10,
                'update_time' => currentTime(),
            ]
        );
        if(!$update_stock_out_apply){
            M()->rollback();
            cancelCacheLock('ErpStock/addStockOut');
            return  ['status' => 12, 'message' => '出库申请单更新失败'];
        }
        //******************end  更新出库申请单状态*****************************************

        if(!$this->getModel('ErpStockOut')->addStockOutAll($order_info)){//添加出库单数据
            M()->rollback();
            cancelCacheLock('ErpStock/addStockOut');
            return  ['status' => 13, 'message' => '生成出库单失败'];
        }
        cancelCacheLock('ErpStock/addStockOut');
        M()->commit() ;
        return ['status' => 1, 'message' => '操作成功'];
    }

    /**
     * 验证销售是否满足出库条件
     * @param $order_info
     * @author xiaowen
     * @time 2017-5-5
     * @return bool
     */
    public function checkOrderCanOutStock($order_info){
        $status = false;
        if(($order_info['pay_type'] == 2 ||$order_info['pay_type'] == 4 ) && $order_info['order_status'] == 10){ //账期和货到付款且订单已确认 可出库
            $status = true;
        }else if($order_info['pay_type'] == 5 && $order_info['collection_status'] == 4){ //定金锁价部分付款 可出库
            $status = true;
        }else if($order_info['pay_type'] != 2 && $order_info['collection_status'] == 10){ //非账期且订单已收款 可出库
            $status = true;
        }
        //若该订单已退货，则不能生成出库单
        if ($order_info['is_returned'] == 1) {
            $status = false;
        }
        return $status;
    }

    /**
     * 出库单列表
     * @param $param
     * @author xiaowen
     * @time 2017-05-08
     */
    public function erpStockOutList($param = [])
    {
        $where = [];

        // 系统批次号搜索
        if ( isset($param['batch_sys_bn']) && !empty($param['batch_sys_bn']) ) {
            $where['so.batch_sys_bn'] = ['like', ['%' . trim($param['batch_sys_bn']) . '%']];
        }

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['so.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }

        if (trim($param['goods_code'])) {
            $where['so.goods_id'] = $param['goods_code'];
        }

        if (trim($param['source_number'])) {
            $where['so.source_number'] = ['like', '%' . trim($param['source_number']) . '%'];
        }
        if (trim($param['outbound_code'])) {
            $where['so.outbound_code'] = ['like', '%' . trim($param['outbound_code']) . '%'];
        }
        if(trim($param['source_apply_number'])){
            $where['so.source_apply_number'] = ['like', '%' . trim($param['source_apply_number']) . '%'];
        }
        if (trim($param['outbound_status'])) {
            $where['so.outbound_status'] = intval(trim($param['outbound_status']));
        }
        if (trim($param['finance_status'])) {
            $where['so.finance_status'] = intval(trim($param['finance_status']));
        }
        if (trim($param['attachment']) == 1) {
            $where['so.attachment'] = '';
        }
        if (trim($param['attachment']) == 10) {
            $where['so.attachment'] = ['neq',''];
        }
        if (trim($param['outbound_type'])) {
            $where['so.outbound_type'] = intval(trim($param['outbound_type']));
        }
        if(isset($param['sale_company_id']) && intval($param['sale_company_id'])){
            $where['o.company_id|po.sale_company_id']      = intval($param['sale_company_id']);
            //$where['_string'] = '(o.region in ('.$cid . ') or  eo.out_region in (' . $cid . '))';
        }

        # -----------------增加省份、审核日期筛选-----------------------------------
        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id)) {
                $cid = implode(',', $city_id);
                $where['so.region'] = ['in',$cid];
            }
        }
        if (trim($param['region'])) {
            $where['so.region'] = $param['region'];
        }
        if (isset($param['examine_start_time']) || isset($param['examine_end_time'])) {
            if (trim($param['examine_start_time']) && !trim($param['examine_end_time'])) {
                $where['so.audit_time'] = ['egt', trim($param['examine_start_time'])];
            } else if (!trim($param['examine_start_time']) && trim($param['examine_end_time'])) {

                $where['so.audit_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['examine_end_time'])) + 3600 * 24)];
            } else if (trim($param['examine_start_time']) && trim($param['examine_end_time'])) {

                $where['so.audit_time'] = ['between', [trim($param['examine_start_time']), date('Y-m-d H:i:s', strtotime(trim($param['examine_end_time'])) + 3600 * 24)]];
            }
        }
        if(intval($param['def']) == 2 && trim($param['facilitator_id']) && empty($param['facilitator_skid'])){
            $facilitator_skid_id = $this->getModel('FacilitatorSkid')->where(['facilitator_id' => trim($param['facilitator_id'])])->getField('facilitator_skid_id',true);
            if(count($facilitator_skid_id) > 0){
                $where['so.storehouse_id'] = ['in',$facilitator_skid_id];
            }
        }
        if (intval($param['def']) == 2 && trim($param['facilitator_id']) && trim($param['facilitator_skid'])) {
            $where['so.storehouse_id'] = $param['facilitator_skid'];
        }

        # --------------------< qianbin -   2017.08.15>--------------------------


        /**-----------------------------------------新增搜索条件放到此行之上------------------------------------------*/
        if ((trim($param['def']) == 1 && !trim($param['outbound_status'])) || (trim($param['def']) == 2 && count($where) == 0)) {
            $where['so.outbound_status'] = 1;
        }

        if (isset($param['dealer_id']) && trim($param['dealer_id'])) {
            $where['so.dealer_id'] = intval(trim($param['dealer_id']));
            if (!trim($param['outbound_status'])) {
                unset($where['so.outbound_status']);
            }
        }
        
        //当前登陆选择的我方公司
        $where['so.our_company_id'] = session('erp_company_id');
        // edit xiaowen 2017-9-19 重构出库单列表
        $field = 'so.*,o.dealer_name as sale_dealer_name,o.company_id as o_company_id,o.user_id as o_user_id,o.price,
        g.goods_code,g.goods_name,g.source_from,g.grade,g.level,g.label,
        eo.dealer_name as f_dealer_name,
        eo.out_storehouse,eo.actual_out_num_liter,eo.out_facilitator_id,
        ro.return_goods_num,ro.return_price,
        po.sale_company_id as return_company_id,po.sale_user_id as return_user_id, po.buyer_dealer_name as return_dealer_name';
        //分别查询，ERP批发销售/调拨出库 和 零售销售出库单
        if ($param['type'] == 1) { //ERP批发销售/调拨出库
            //正常销售的出库单
            $where['so.is_other'] = 2 ;
            $where['so.loss_type'] = 0 ;
            $where['IF(so.outbound_type = 1, o.order_type, 1)'] = 1;
            if ($param['export']) {
                $data = $this->getModel('ErpStockOut')->getAllStockOutList($where, $field);
            } else {
                $data = $this->getModel('ErpStockOut')->getStockOutList($where, $field, $param['start'], $param['length']);
            }
        } else if($param['type'] == 2) { //零售销售出库单
            $field = 'so.*,o.region,o.price,o.facilitator_id as out_facilitator_id,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,g.label';
            $where['so.outbound_type'] = 1;
            $where['so.outbound_status'] = 10;
            $where['o.order_type'] = 2;
            if ($param['export']) {
                $data = $this->getModel('ErpStockOut')->getAllRetailStockOutList($where, $field);
            } else {
                $data = $this->getModel('ErpStockOut')->getRetailStockOutList($where, $field, $param['start'], $param['length']);
            }
        }
        $user_name_type = [
            1 => 'o_user_id',
            2 => 'user_name',
            3 => 'return_user_id',
        ];
        $company_name_type = [
            1 => 'o_company_id',
            2 => 'company_name',
            3 => 'return_company_id',
        ];

        $dealer_name_type = [
            1 => 'sale_dealer_name',
            2 => 'f_dealer_name',
            3 => 'return_dealer_name',
            4 => 'dealer_name',
            5 => 'dealer_name',
        ];


        if ($data['data']) {

            $cityArr = provinceCityZone()['city'];
            //查询仓库、用户、公司数据
            $storehouse_ids = array_unique(array_filter(array_column($data['data'], 'storehouse_id')));
            $storehouse_arr = [];
            if($storehouse_ids){
                $storehouse_arr = $this->getModel('ErpStorehouse')->where(['id' => ['in', $storehouse_ids]])->getField('id,storehouse_name');
            }

            $supplierUserIdArr = array_column($data['data'], 'return_user_id');
            $customerUserIdArr = array_column($data['data'], 'o_user_id');
            $supplierIdArr = array_column($data['data'], 'return_company_id');
            $customerIdArr = array_column($data['data'], 'o_company_id');
            $facilitatorSkidIdArr = array_column($data['data'], 'storehouse_id');
            $supplierUserInfo = $this->getEvent('ErpCommon')->getUserData($supplierUserIdArr, 2);
            $customerUserInfo = $this->getEvent('ErpCommon')->getUserData($customerUserIdArr, 1);
            $supplierCompanyInfo = $this->getEvent('ErpCommon')->getCompanyData($supplierIdArr, 2);
            $customerCompanyInfo = $this->getEvent('ErpCommon')->getCompanyData($customerIdArr, 1);
            $facilitatorCompanyInfo = $this->getModel('ErpSupplier')->alias('s')
                ->where(['es.id'=>['in',$facilitatorSkidIdArr]])
                ->join('oil_erp_storehouse es on s.id = es.company_id', 'left')
                ->getField('es.id,s.id as supplier_id,s.supplier_name as company_name');

            $dealer_ids = array_unique(array_filter(array_column($data['data'], 'auditor_id')));
            $dealer_arr = [];
            if($dealer_ids){
                $dealer_arr = $this->getModel('Dealer')->where(['id' => ['in', $dealer_ids]])->getField('id,dealer_name');
            }

            //匹配出库单批次、货权信息 xiaowen 2019-3-1
            $batch_ids = array_unique(array_column($data['data'], 'batch_id'));
            $batch_data = [];
            if($batch_ids) {
                $batch_data = $this->getModel('ErpBatch')->where(['id' => ['in', $batch_ids]])->getField('id,sys_bn,cargo_bn_id');

            }
            //获取批次货权id
            $cargo_bn_ids = array_unique(array_column($batch_data, 'cargo_bn_id'));
            $cargo_data = [];
            if($cargo_bn_ids){
                $cargo_data = $this->getModel('ErpCargoBn')->where(['id' => ['in', $cargo_bn_ids]])->getField('id,cargo_bn');
            }
            foreach ($data['data'] as $key => &$value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['region_font'] = $value['region'] == 1 ? '全国' : $cityArr[$value['region']];
                //$data['data'][$key]['storehouse_name'] = $value[$storehouse_type[$value['outbound_type']]];
                $data['data'][$key]['storehouse_name'] = $value['stock_type'] == 4 ? '——' : $storehouse_arr[$value['storehouse_id']];
                //$data['data'][$key]['user_name'] = $value[$user_name_type[$value['outbound_type']]];
                $data['data'][$key]['user_name'] = $value['outbound_type'] == 3 ? $supplierUserInfo[$value[$user_name_type[$value['outbound_type']]]]['user_name'] : $customerUserInfo[$value[$user_name_type[$value['outbound_type']]]]['user_name'];
                //$data['data'][$key]['company_name'] = $value[$company_name_type[$value['outbound_type']]];
                $data['data'][$key]['company_name'] = $value['outbound_type'] == 3 ? $supplierCompanyInfo[$value[$company_name_type[$value['outbound_type']]]]['company_name'] : $customerCompanyInfo[$value[$company_name_type[$value['outbound_type']]]]['company_name'];
                //$data['data'][$key]['sale_dealer_name'] = $value[$dealer_name_type[$value['outbound_type']]];
                $data['data'][$key]['sale_dealer_name'] = $value[$dealer_name_type[$value['outbound_type']]];
                $data['data'][$key]['auditor'] = $dealer_arr[$value['auditor_id']] ? $dealer_arr[$value['auditor_id']] : '——';

                $data['data'][$key]['outbound_status'] = stockInStatus($value['outbound_status'], true);
                $data['data'][$key]['outbound_status_font'] = stockInStatus($value['outbound_status']);
                $data['data'][$key]['finance_status'] = financeStatus($value['finance_status'],true);
                $data['data'][$key]['finance_status_font'] = financeStatus($value['finance_status']);
                $data['data'][$key]['outbound_type_font'] = stockOutType($value['outbound_type']);
                $data['data'][$key]['outbound_num'] = ErpFormatFloat(getNum($value['outbound_num']));
                $data['data'][$key]['actual_out_num_liter'] = $value['actual_outbound_num'] < 0 ? getNum($value['actual_out_num_liter']) * -1 : getNum($value['actual_out_num_liter']);
                $data['data'][$key]['actual_outbound_num'] = ErpFormatFloat(getNum($value['actual_outbound_num']));

                $data['data'][$key]['facilitator_name'] = $value['stock_type'] == 4 ? $facilitatorCompanyInfo[$value['storehouse_id']]['company_name'] : '--';
                $data['data'][$key]['facilitator_skid_name'] = $value['stock_type'] == 4 ? $storehouse_arr[$value['storehouse_id']] : '--';
                $data['data'][$key]['facilitator_id'] = $value['stock_type'] == 4 ? $facilitatorCompanyInfo[$value['storehouse_id']]['supplier_id'] : '--';
                # 零售销售出库单 - 红冲 添加显示字段
                $value['price'] = $value['price'] > 0 ? round(getNum($value['price']),2) : round(getNum($value['return_price']),2) ;
                $data['data'][$key]['price'] =  $value['price'] > 0 ?  $value['price'] : '0.00';
                $data['data'][$key]['is_reverse']       = isset($value['is_reverse']) ?  isReverse($value['is_reverse']) : '';
                $data['data'][$key]['reverse_source']   = isset($value['reverse_source']) ?  $value['reverse_source'] : '';
                $data['data'][$key]['actual_outbound_litre'] = isset($value['actual_outbound_litre']) ?  round(getNum($value['actual_outbound_litre']),2) : '';
                //$data['data'][$key]['actual_retail_num'] = round(getNum($value['actual_outbound_num']),4);
                $data['data'][$key]['actual_retail_num'] = ErpFormatFloat($value['actual_outbound_num']);
                $data['data'][$key]['cost'] = isset($value['cost']) ?  round(getNum($value['cost']),2) : '——';
                $data['data'][$key]['cost_litre'] = isset($value['actual_outbound_litre']) && isset($value['cost']) ? round(getNum($value['cost']) / 1000 * ($value['outbound_density']) , 2) : '';
                $data['data'][$key]['outbound_density'] = $value['outbound_density'];
                $data['data'][$key]['attachment'] = $data['data'][$key]['attachment'] ? '已上传' : '未上传';
                //返回批次、货权信息 xiaowen 2019-3-1
                $data['data'][$key]['batch_sys_bn'] = $value['batch_id'] ? $batch_data[$value['batch_id']]['sys_bn'] : '--';
                $data['data'][$key]['batch_cargo_bn'] = $value['batch_id'] ? $cargo_data[$batch_data[$value['batch_id']]['cargo_bn_id']] : '--';
                //判断出库单类型
                $is_inner = 2 ;
                if($value['outbound_type'] == 3){
                    $is_inner = $supplierCompanyInfo[$value[$company_name_type[$value['outbound_type']]]]['is_inner'] ;
                }else{
                    $is_inner = $customerCompanyInfo[$value[$company_name_type[$value['outbound_type']]]]['is_inner'];
                }
                $value['inner'] = ($is_inner==1) ? "是":"否";
                /************************************
                @ Content 支持内部交易单
                 *************************************/
                if ( $value['retail_inner_order_number'] != '' ) {
                    $data['data'][$key]['outbound_num']        = round($data['data'][$key]['outbound_num'],4);
                    $data['data'][$key]['actual_outbound_num'] = round($data['data'][$key]['actual_outbound_num'],4);
                }
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 或取一条出库单信息
     * @param $id
     * @author xiaowen
     * @return $data
     */
    public function getStockOutInfo($id)
    {
        $data = $this->getModel('ErpStockOut')->findStockOut(['so.id'=>$id],'so.*');
        return $data;
    }

    /**
     * 编辑非销售类型出库单需要显示数据
     * @param $id
     * @author xiaowen
     * @time 2019-5-15
     * @return mixed
     */
    public function showUpdateErpStockOut($id,$type)
    {
        $cityArr = provinceCityZone()['city'];
        if ($type == 2) {
            $field = 'so.*,o.region,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,g.label,es.storehouse_name,
            oes.storehouse_name as oes_storehouse_name,eo.out_region, eo.num as out_num,eo.actual_out_num';
            $data['order'] = $this->getModel('ErpStockOut')->findStockOut(['so.id' => $id], $field);
            $data['order']['region_font'] = $cityArr[$data['order']['out_region']];
            $data['order']['out_num'] = getNum($data['order']['actual_out_num']);
            $data['order']['storehouse_name'] = $data['order']['oes_storehouse_name'] ? $data['order']['oes_storehouse_name'] : '--';
        } elseif (in_array($type,[3,4,5])) {
            $field = 'so.*,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,g.label,sh.storehouse_name';
            $data['order'] = $this->getModel('ErpStockOut')->findStockOut(['so.id' => $id], $field);
            $data['order']['region_font'] = $cityArr[$data['order']['region']];
            $data['order']['out_num'] = getNum($data['order']['actual_out_num']);
            $data['order']['storehouse_name'] = $data['order']['storehouse_name'] ? $data['order']['storehouse_name'] : '--';
        }
        return $data;
    }

    /**
     * 编辑出库单
     * @param $order_info
     * @author xiaowen
     * @time 2017-5-5
     * @return array $result
     */
//    public function updateStockOut($param,$files){
    public function updateStockOut($param){
        if(!$param['id']){
            $result = [
                'status' => 2,
                'message' => '出库单信息有误，请重新操作',
            ];
            return $result;
        }
        if(!isset($param['batch']) || empty($param['batch']) || !is_array($param['batch'])){
            $result = [
                'status' => 3,
                'message' => '来源单据有误，请重新操作',
            ];
            return $result;
        }
        if(!$param['outbound_num']){
            $result = [
                'status' => 4,
                'message' => '出库数量不正确，请重新操作',
            ];
            return $result;
        }
        //查找该条出库信息
        $stock_out_info = $this->getStockOutInfo($param['id']);
        if($stock_out_info['outbound_status'] != 1){
            return ["status" => 5 , "message" => "出库单只能是未审核才可以进行编辑"];
        }
        $whereStockOutSum =[
            "source_apply_number" => $stock_out_info['source_apply_number'] ,
            "outbound_status" => ['in' , [1, 10]]
        ];
        $stockOutSum = $this->getModel("ErpStockOut")->stockOutGetField($whereStockOutSum , "sum(actual_outbound_num)");
        $actual_outbound_num = setNum($param['outbound_num']) + $stockOutSum - $stock_out_info['actual_outbound_num'] ;

        $whereApply['outbound_apply_code'] = $stock_out_info['source_apply_number'] ;
        $fieldApply = "id , outbound_apply_num";
        $dataApply = $this->getModel("ErpStockOutApply")->info($fieldApply , $whereApply);
        if($dataApply['outbound_apply_num'] < $actual_outbound_num){
            $userOutboundNum = getNum($dataApply['outbound_apply_num']-($stockOutSum - $stock_out_info['actual_outbound_num'])) ;
            return ["status" => 6 , "message" => "已超过可编辑数量上限，目前出库上限数量为：".$userOutboundNum] ;
        }
        /*
        $upload_status = true;
        $data = [];
        $delete_photo = [];
        $error_photo = [];
        $attachment = [];
        //判断图片数据信息
        if (!empty($files) && $stock_out_info['finance_status'] == 1
            || $stock_out_info['finance_status'] == 2) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if ($value['size'] > 2 * 1024 * 1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
                } else {
                    continue;
                }
            }

            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_out_attach']['src'];
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

                //已上传文件，如果操作失败要删除
                array_push($error_photo, $file_name);

                $data[$key] = $file_name;
                //将原图覆盖，且删除原图片
                if (!empty($stock_out_info[$key])) {
                    array_push($delete_photo, $key);
                }
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        } elseif ($stock_out_info['finance_status'] == 10) {
            $result = [
                'status' => 7,
                'message' => '该单据已财务核对，不能再上传附件'
            ];
            return $result;
        }
        if(!$upload_status){//图片上传失败删除数据信息
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            return ["status" => 8 , "message" => "图片上传失败"];
        }
        */
        $branchIds = array_keys($param['batch']);
        $branchWhere = [
            "id" => ['in' , $branchIds]
        ] ;
        $branchField = "id , sys_bn";
        $batchInfos = $this->getModel("ErpBatch")->getFieldList($branchField , $branchWhere);

        $rollbackData = [
            "batch_id" => $stock_out_info['batch_id'] ,
            "change_balance_num" => 0 ,
            "change_reserve_num" => plusConvert($stock_out_info['actual_outbound_num']) ,
            "change_type" => 2 ,
            "change_number" => $stock_out_info['outbound_code']
        ];
        foreach ($param['batch'] as $key => $value){
            $actual_outbound_num = setNum($value) ;
            $commitData = [
                "batch_id" => $key ,
                "change_balance_num" => 0 ,
                "change_reserve_num" => $actual_outbound_num ,
                "change_type" => 2 ,
                "change_number" => $stock_out_info['outbound_code']
            ] ;
            $updateStockOut = [
                'outbound_density'=> $param['outbound_density'] ,
                "outbound_num" => $actual_outbound_num ,
                "actual_outbound_num"=>$actual_outbound_num,
                "update_time" => date("Y-m-d H:i:s"),
                "outbound_remark" => $param['outbound_remark'] ,
                "batch_sys_bn" => $batchInfos[$key] ,
                "batch_id" =>  $key
            ];
//            if (!empty($attachment)) {
//                $updateStockOut = array_merge($updateStockOut,$attachment);
//            }
        }
        if(getCacheLock('ErpStock/updateStockOut'))
            return ['status' => 99, 'message' => $this->running_msg];
        M()->startTrans();
        setCacheLock('ErpStock/updateStockOut', 1);
        if($this->getEvent("ErpBatch")->commonChangeBatchNum($rollbackData)['status'] != 1){
            cancelCacheLock('ErpStock/updateStockOut');
            M()->rollback() ;
            return ["status" => 9 , "message"=> "回滚占用批次出库单数量失败，请重新操作"];
        }
        if($this->getEvent("ErpBatch")->commonChangeBatchNum($commitData)['status'] != 1){
            cancelCacheLock('ErpStock/updateStockOut');
            M()->rollback() ;
            return ["status" => 9 , "message"=> "占用批次出库单数量，请重新操作"];
        }
        if(!$this->getModel('ErpStockOut')->saveStockOut(['id'=>intval($stock_out_info['id'])], $updateStockOut)){
            cancelCacheLock('ErpStock/updateStockOut');
            M()->rollback() ;
            return ["status" => 9 , "message"=> "出库单修改失败，请重新操作"];
        }
        cancelCacheLock('ErpStock/updateStockOut');
        M()->commit();
        return ['status' => 1 , "message" => "操作成功"];

    }

    public function getSaleEvent(){
        return A('ErpSale', 'Event');
    }

    /**
     * 取消出库单
     * @param $id
     * @author xiaowen
     * @return array $result
     * @time 2017-5-8
     */
    public function cancelStockOut($id){
        if (getCacheLock('ErpStock/cancelStockOut'))
            return ['status' => 99, 'message' => $this->running_msg];
        if (!$id) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
            return $result ;
        } else {
            $stockOut_info = $this->getModel('ErpStockOut')->getOneStockOut(['id'=>$id]);
            if ($stockOut_info['outbound_status'] != 1) {
                $result['status'] = 101;
                $result['message'] = "只有未审核状态下才可取消";

            } else if(!in_array($stockOut_info['outbound_type'], [1,3])){ //销售出库\采退出库才能取消
                $result['status'] = 102;
                $result['message'] = "只有销售、采退出库单才能取消";
            }else if($stockOut_info['actual_outbound_num'] > 0 && empty($stockOut_info['batch_id'])){ //出库单实际出库数量大于0，但没有批次id，数据有误，不能取消
                $result['status'] = 103;
                $result['message'] = "该出库没有批次，无法取消";
            }
            else {
                /****************************
                Author YF 废弃
                只有未审核的采购退货单 出库单才能取消！
                 *****************************/
                // if ( $stockOut_info['outbound_type'] == 3 ) {
                //    $returned_order_arr = $this->getModel('ErpReturnedOrder')->where(['id',$stockOut_info['source_object_id']])->find();
                //    if ( !isset($returned_order_arr['id']) ) {
                //        return ['status' => 201, 'message' => '未查询到采购退货单！'];
                //    }
                //    if ( $returned_order_arr['order_status'] != 1 ) {
                //        return ['status' => 201 ,'message' => '采购退货单不是未审核状态，不能取消此出库单！'];
                //    }
                // }
                /****************************
                END
                 *****************************/
                setCacheLock('ErpStock/cancelStockOut', 1);
                M()->startTrans() ;
                $stock_out_data = [
                    'outbound_status' => 2,
                    'update_time' => currentTime(),
                ];
                $status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$id], $stock_out_data);
                $batch_result['status'] = 1;
                // =======edit xiaowen 2019-2-25 采退出库更新批次数量============================
                if($stockOut_info['actual_outbound_num'] > 0 && $stockOut_info['batch_id'] > 0){
                    $batch_change_data = [
                        'batch_id' => $stockOut_info['batch_id'],
                        'change_reserve_num' => plusConvert($stockOut_info['actual_outbound_num']), //减少批次预留
                        'change_type' => 2,
                        'change_number' => $stockOut_info['outbound_code'],
                    ];
                    $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);

                    if($batch_result['status'] != 1){
                        M()->rollback();
                        return $batch_result;
                    }
                }

                //==========end 批次处理==========================================================

                if ($status && $batch_result['status'] == 1) {
                    M()->commit() ;
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
        cancelCacheLock('ErpStock/cancelStockOut');

        return $result;
    }

    /**
     * 审核出库单
     * @param $id
     * @author xiaowen
     * @return array $result
     */
    public function auditErpStockOut($id){
        if (getCacheLock('ErpStock/auditErpStockOut')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStock/auditErpStockOut', 1);
        if (!$id) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $stockOut_info = $this->getModel('ErpStockOut')->getOneStockOut(['id'=>$id]);
            if ($stockOut_info['outbound_status'] != 1) {
                $result['status'] = 101;
                $result['message'] = "只有未审核状态下才可审核";
            }else if($stockOut_info['actual_outbound_num'] > 0 && empty($stockOut_info['batch_id'])){
                $result['status'] = 102;
                $result['message'] = "出库货权批次信息不完善，无法审核";
            } else {
                /*************************************
                @ Content 采退出库单 未退款 阻止审核
                 *************************************/
                if ( $stockOut_info['outbound_type'] == 3 ) {
                    $returned_order = $this->getModel('ErpReturnedOrder')->where(['order_number' =>['eq',$stockOut_info['source_number']]])->find();
                    if ( !isset($returned_order['return_amount_status']) ) {
                        cancelCacheLock('ErpStock/auditErpStockOut');
                        return ['status' => 150,'message' => '退货单不存在！'];
                    }
                    if ( $returned_order['return_amount_status'] != 10 ) {
                        cancelCacheLock('ErpStock/auditErpStockOut');
                        return ['status' => 151,'message' => '退货单是未付款状态，不能审核！'];
                    }
                }
                /*************************************
                END
                 **************************************/
                $sale_order_info = $this->getEvent('ErpSale')->findSaleOrder($stockOut_info['source_object_id']);
                if ($stockOut_info['outbound_type'] == 1 && $sale_order_info['pay_type'] == 5) {
                    $no_out_num = $sale_order_info['total_sale_wait_num'] - $sale_order_info['outbound_quantity'];
                } else {
                    $no_out_num = $sale_order_info['buy_num'] - $sale_order_info['outbound_quantity'];
                }
                log_info("订单总数：" . $sale_order_info['buy_num'] . " 已出库数量：" . $sale_order_info['outbound_quantity']);
                log_info("未出库数量：" . $no_out_num . "  实际出库数量：" .$stockOut_info['actual_outbound_num']);
                if($stockOut_info['outbound_type'] == 1 && $no_out_num < $stockOut_info['actual_outbound_num']){
                    $result['status'] = 102;
                    $result['message'] = "审核出库单数量不能大于订单数量";
                }else{

                    M()->startTrans();

//                    $stock_out_data = [
//                        'outbound_status' => 10,
//                        'auditor_id' => $this->getUserInfo('id'),
//                        'audit_time' => currentTime(),
//                    ];
//
//                    $status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$id], $stock_out_data);
                    //==================================出库单审核确认，影响库存==========================================
//                    if($stockOut_info['outbound_type'] == 1){
//                        $this->auditSaleStockOut($stockOut_info);
//                    }else if($stockOut_info['outbound_type'] == 3){
//                        $this->auditReturnedStockOut($stockOut_info);
//                    }
                    $auditStockType = [
                        1 => 'auditSaleStockOut',
                        3 => 'auditReturnedStockOut',
                    ];
                    $auditStockTypeFunction = $auditStockType[$stockOut_info['outbound_type']];
                    $result = $this->$auditStockTypeFunction($stockOut_info);
                    //return $result;
                    # 审核通过，增加操作日志
                    # qianbin
                    # 2017.7.17
//                    $erp_stock_option_log = [
//                        'order_type'    => 1,
//                        'order_number'  => trim($stockOut_info['outbound_code']),
//                        'log_type'      => 1,
//                        'operator_id'   => session('erp_adminInfo')['id'],
//                        'operator'      => session('erp_adminInfo')['dealer_name'],
//                        'create_time'   => date('Y-m-d H:i:s',time())
//                    ];
//                    $add_log = M('erpStockOptionLog')->add($erp_stock_option_log);
                    //=====================================================================================================
                    //判断出库单及库存是否都更新成功
//                    if ($stock_out_status && $add_log) {
//                        M()->commit();
//                        $result = [
//                            'status' => 1,
//                            'message' => '操作成功',
//                        ];
//                    } else {
//                        M()->rollback();
//                        $result = [
//                            'status' => 0,
//                            'message' => '操作失败',
//                        ];
//                    }
                }
            }
        }

        cancelCacheLock('ErpStock/auditErpStockOut');
        return $result;
    }

    /**
     * 库存列表
     * @param array $param 查询参数
     * @param $order
     * @author xiaowen
     * @time 2017-05-09
     */
    public function getStockList($param = [], $order = 's.id desc')
    {
        $where = [];
        if ($param['goods_id']) {
            $where['s.goods_id'] = intval($param['goods_id']);
        }
        if ($param['stock_type']) {
            $where['s.stock_type'] = intval($param['stock_type']);
        } else {
            $where['s.stock_type'] = ['neq', 3];
        }
        if ($param['region']) {
            $where['s.region'] = intval($param['region']);
        } else {
            $where['s.region'] = ['neq', 1877]; // edit xiaowen 2017-8-4 库存查询不包含江阴库存
        }
        if (intval(trim($param['province'])) && !intval(trim($param['region']))) {
            $city_id = D('Area')->where(['parent_id' => intval($param['province']), 'area_type' => 2])->getField('id',
                true);
            if (!empty($city_id)) {
                $where['s.region'] = ['in', $city_id];
            }
        }
        //按仓库查询，需配合仓库类型
        if ($param['storehouse_id']) {
            $where['s.object_id'] = intval($param['storehouse_id']);
        }

        // 库存多选查询
        //if($where['s.stock_type'] && $param['store_house']){
        //    $where['s.object_id'] = ['in',$param['store_house']];
        //}
        $where['s.our_company_id'] = session('erp_company_id');//增加当前公司帐号条件 edit xiaowen 2017-5-22
        $where['s.status'] = 1;

        //update by guanyu @ 2017-12-19
        //按加油网点查询，将同一网点在不同地区的库存合并显示
        if ($where['s.stock_type'] == 4 && $param['storehouse_id']) {

            $group = 's.goods_id,s.object_id,s.stock_type,s.our_company_id';
            unset($where['s.region']);

            $field = 's.*,SUM(s.stock_num) as stock_num,SUM(s.transportation_num) as transportation_num,
            SUM(s.sale_reserve_num) as sale_reserve_num,SUM(s.allocation_reserve_num) as allocation_reserve_num,
            SUM(s.sale_wait_num) as sale_wait_num,SUM(s.allocation_wait_num) as allocation_wait_num,
            SUM(s.available_num) as available_num, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,
            es.storehouse_name,rg.available_sale_stock,rg.available_use_stock,su.supplier_name';
        } else {
            $field = 's.*, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,es.storehouse_name,
            rg.available_sale_stock, rg.available_use_stock,su.supplier_name';
        }
        if ($param['export']) {
            $data = $this->getModel('ErpStock')->getAllStockList($where, $field, $order, $group);
        } else {
            $data = $this->getModel('ErpStock')->getStockList($where, $field, $_REQUEST['start'], $_REQUEST['length'],
                $order, $group);
        }
        if ($data['data']) {

            $getAllRegion = provinceCityZone()['city'];
            $getAllRegion[1] = '全国';
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                if ($value['region']) {
                    $data['data'][$key]['region_name'] = $getAllRegion[$value['region']];
                } else {
                    $data['data'][$key]['region_name'] = '--';
                }
                $data['data'][$key]['stock_type'] = stockType($value['stock_type']);
                //$data['data'][$key]['stock_num'] = round(getNum($value['stock_num'])*10000)/10000;
                $data['data'][$key]['stock_num'] = ErpFormatFloat(getNum($value['stock_num']), 8);
                $data['data'][$key]['transportation_num'] = ErpFormatFloat(getNum($value['transportation_num']), 8);
                $data['data'][$key]['sale_reserve_num'] = ErpFormatFloat(getNum($value['sale_reserve_num']), 8);
                $data['data'][$key]['allocation_reserve_num'] = ErpFormatFloat(getNum($value['allocation_reserve_num']),
                    8);
                $data['data'][$key]['sale_wait_num'] = ErpFormatFloat(getNum($value['sale_wait_num']), 8);
                $data['data'][$key]['allocation_wait_num'] = ErpFormatFloat(getNum($value['allocation_wait_num']), 8);
                $data['data'][$key]['available_num'] = ErpFormatFloat(getNum($value['available_num']), 8);
                $data['data'][$key]['facilitator_name'] = $value['supplier_name'] ? $value['supplier_name'] : '—';
                $data['data'][$key]['current_available_sale_num'] = $value['stock_type'] == 1 ? ErpFormatFloat(getNum($value['available_sale_stock'] + $value['available_num'] - $value['available_use_stock']),
                    8) : 0; //当前可售库存
                //$data['data'][$key]['sal_available_num'] = $this->getStockSaleNum(['goods_id'=>$value['goods_id'], 'object_id'=>$value['object_id'], $value['stock_type'], $value['region']]);
                $data['data'][$key]['object_name'] = $data['data'][$key]['storehouse_name'];
            }

        } else {
            $data['data'] = [];
            $data['recordsTotal'] = 0;
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;

    }

    /**
     * 采购计划显示当天出库单
     * @author senpai
     * @time 2017-05-22
     */
    public function stockOutDetail($param = [])
    {
        $where = [
            'o.storehouse_id' => $param['storehouse_id'],
            'o.goods_id' => $param['goods_id'],
            'so.outbound_status' => 1,
            'so.create_time' => ['between', [date('Y-m-d'), date('Y-m-d', strtotime('+1day'))]],
        ];

        //当前登陆选择的我方公司
        $where['so.our_company_id'] = session('erp_company_id');

        //$field = 'so.*,cs.company_name,d.depot_name,o.depot_id';
        $field = 'so.*,o.company_id,d.depot_name,o.depot_id';
        $data = $this->getModel('ErpStockOut')->getStockOutList($where, $field, $param['start'], $param['length']);
        if ($data['data']) {
            $companyIdArr = array_column($data['data'], 'company_id');
            $companyInfo = $this->getEvent('ErpCommon')->getCompanyData($companyIdArr, 1);
            foreach ($data['data'] as $key => $value) {
                $data['data'][$key]['company_name'] = trim($companyInfo[$value['company_id']]['company_name']);
                $data['data'][$key]['actual_outbound_num'] = getNum($value['actual_outbound_num']);
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
     * 出库单取消审核
     * @author xiaowen
     * @time 2017-05-22
     * @param $id
     * @return array
     */
    public function cancelAuditErpStockOut($id){
        if (getCacheLock('ErpStock/cancelAuditErpStockOut')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStock/cancelAuditErpStockOut', 1);
        if (!$id) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $stockOut_info = $this->getModel('ErpStockOut')->getOneStockOut(['id'=>$id]);
            if ($stockOut_info['outbound_status'] != 10) {
                $result['status'] = 101;
                $result['message'] = "只有已审核状态才可取消审核";
            }else if ($stockOut_info['outbound_type'] != 1) {
                $result['status'] = 102;
                $result['message'] = "只有销售出库单才能取消审核";
            }else if(($this->getModel('ErpSaleOrder')->where(['order_number' => trim($stockOut_info['source_number']),'order_type' => 1 ])->getField('is_returned')) == 1 ){
                // 生成了销退单之后， 不允许取消出库单
                $result['status'] = 103;
                $result['message'] = "该笔交易已产生了销退单，无法取消审核！";
            }
            else {

                M()->startTrans();

                $stock_out_data = [
                    'outbound_status' => 1,
                    'update_time' => currentTime(),
                ];

                $status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$id], $stock_out_data);
                //==================================出库单取消审核，影响库存==========================================

                $stock_data['goods_id'] = $stockOut_info['goods_id'];
                $field_stock = '';
                $order_info = [];
                $region = 0;
                if($stockOut_info['outbound_type'] == 1){ //出库单类型是销售，影响城市仓库存
                    $field_stock = 'sale_wait_num';
                    $order_info = $this->getEvent('ErpSale')->findSaleOrder($stockOut_info['source_object_id']);
                    $stock_data['stock_type'] = $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($order_info['storehouse_id']);
                    $stock_data['object_id'] = $order_info['storehouse_id'];
                    $region = $order_info['region'];
                }
                $stock_where = $stock_data;
                //查询当前库存的各个库存字段的值
                $old_stock_info = $this->getStockInfo($stock_where);

                $stock_data['region'] = $region;
                //出库单取消审核，增加销售待提
                $stock_data[$field_stock] = $old_stock_info[$field_stock] + $stockOut_info['actual_outbound_num'];
                //出库单取消审核，同时增加物理库存
                $stock_data['stock_num'] = $old_stock_info['stock_num'] + $stockOut_info['actual_outbound_num'];
                //保存更新后的库存，及记录库存变动日志
                $orders = [
                    'object_number' =>$stockOut_info['outbound_code'],
                    'object_type' =>3,
                    'log_type' => 8,
                ];
                $stock_status = $this->saveStockInfo($stock_data, $stockOut_info['actual_outbound_num'], $orders);
                //如果是销售单出库，需要更新销售的出库数量
                if($stockOut_info['outbound_type'] == 1 && $order_info){
                    $outbound_quantity = $order_info['outbound_quantity'];
                    $order_update_data = [
                        'outbound_quantity'=> $outbound_quantity - $stockOut_info['actual_outbound_num'],
                        'update_time'=> currentTime(),
                    ];
                    $order_status = $this->getEvent('ErpSale')->saveSaleOrderById($stockOut_info['source_object_id'], $order_update_data);
                }
                # 审核通过，增加操作日志
                # qianbin
                # 2017.7.17
                $erp_stock_option_log = [
                    'order_type'    => 1,                                         # 1 出库单  2 入库单
                    'order_number'  => trim($stockOut_info['outbound_code']),     # 单据号
                    'log_type'      => 2,                                         # 操作类型 1 审核  2 取消审核
                    'operator_id'   => session('erp_adminInfo')['id'],
                    'operator'      => session('erp_adminInfo')['dealer_name'],
                    'create_time'   => date('Y-m-d H:i:s',time())
                ];
                $add_log = M('erpStockOptionLog')->add($erp_stock_option_log);
                //=====================================================================================================
                //判断出库单及库存是否都更新成功
                if ($status && $stock_status && $order_status && $add_log) {
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

        cancelCacheLock('ErpStock/cancelAuditErpStockOut');
        return $result;
    }

    /**
     * 导入库存盘点数据
     * @param $data
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importStockData($data){
        if($data){

            $city_all = provinceCityZone()['city'];

            $storeHouseData = D('ErpStorehouse')->where(['status' => 1, 'type'=>1])->order('id desc')->getField('id, region');

            $stock_type = [
                1 => '城市仓',
                3 => '服务商',
            ];

            $goods_data = D('ErpGoods')->where(['status' => 10])->getField('id, goods_code');

            $Facilitator_data = D('Facilitator')->where(['status'=> 1])->getField('facilitator_id, name');

            $ourCompanyData = getOurCompany();
            $i = 1;
            $k = 0;
            $stock_data = [];
            $status_all = true;
            M()->startTrans();
            foreach($data as $key=>$value){

                if(trim($value[0]) && trim($value[1])  && trim($value[2]) && trim($value[3])){
                    if(!in_array(trim($value[0]), $city_all)){
                        echo trim($value[0]);
                        die('地区不存在系统中');
                    }
                    if(!in_array(trim($value[1]), $stock_type)){
                        die('仓库类型有误，只能是'.implode(',' , $stock_type));
                    }else{

                        $region = array_search(trim($value[0]), $city_all);

                        if(array_search(trim($value[1]), $stock_type) == 1){

                            if(!in_array($region, $storeHouseData)){
                                die('仓库:'.trim($value[2]).' 仓库不存在');
                            }else{
                                $stock_data[$k]['object_id'] = array_search($region, $storeHouseData);
                            }
                        } else if(array_search(trim($value[1]), $stock_type) == 3){
                            if(!in_array(trim($value[2]), $Facilitator_data)){
                                die('服务商:'.trim($value[2]).' 服务商不存在');
                            }else{
                                $stock_data[$k]['object_id'] = array_search(trim($value[2]), $Facilitator_data);
                            }
                        }else{
                            die('仓库数据有误');
                        }
                    }
                    if(!in_array(trim($value[3]), $goods_data)){
                        die('商品代码不存在');
                    }
                    if(!in_array(trim($value[15]), $ourCompanyData)){
                        die('我方帐套公司不存在');
                    }
                    //----------------------组装数据------------------------------------------------------------------
                    $stock_data[$k]['goods_id'] = array_search(trim($value[3]), $goods_data);
                    $stock_data[$k]['stock_type'] = array_search(trim($value[1]), $stock_type);
                    $stock_data[$k]['region'] = $region;
                    $stock_data[$k]['facilitator_id'] = array_search(trim($value[1]), $stock_type) == 3 ? $stock_data[$k]['object_id'] : 0;
                    $stock_data[$k]['stock_num'] = setNum($value[8]);
                    $stock_data[$k]['transportation_num'] = setNum($value[9]);
                    $stock_data[$k]['sale_reserve_num'] = setNum($value[10]);
                    $stock_data[$k]['allocation_reserve_num'] = setNum($value[11]);
                    $stock_data[$k]['sale_wait_num'] = setNum($value[12]);
                    $stock_data[$k]['allocation_wait_num'] = setNum($value[13]);
                    $stock_data[$k]['available_num'] = ($stock_data[$k]['stock_num'] + $stock_data[$k]['transportation_num']) - ($stock_data[$k]['sale_reserve_num'] + $stock_data[$k]['allocation_reserve_num'] + $stock_data[$k]['sale_wait_num'] + $stock_data[$k]['allocation_wait_num']); //setNum(intval($value[14]));
                    $stock_data[$k]['our_company_id'] = array_search(trim($value[15]), $ourCompanyData) ? array_search(trim($value[15]), $ourCompanyData) : 70;
                    $stock_data[$k]['create_time'] = currentTime();

                    if($stock_data[$k]['stock_type'] == 1){
                        $stock_xs_info = D('ErpStock')->where(['goods_id' => $stock_data[$k]['goods_id'], 'object_id'=>$stock_data[$k]['object_id'], 'stock_type'=>1, 'our_company_id'=>$stock_data[$k]['our_company_id']])->find();
                        log_info("已存在库存，库存ID:" . $stock_xs_info['id'] . "商品ID：{$stock_data[$k]['goods_id']}, 仓库ID：{$stock_data[$k]['object_id']}, 地区：{$region}, 物理库存：{$stock_xs_info['stock_num']}, 可用库存：{$stock_xs_info['available_num']}");
                        if(!empty($stock_xs_info)){
                            //如果已存在，则加上物理，加上可用
                            $stock_data[$k]['stock_num'] = $stock_xs_info['stock_num'] + $stock_data[$k]['stock_num'];
                            $stock_data[$k]['available_num'] = $stock_xs_info['available_num'] + $stock_data[$k]['available_num'];

                            unset($stock_data[$k]['transportation_num']);
                            unset($stock_data[$k]['sale_reserve_num']);
                            unset($stock_data[$k]['allocation_reserve_num']);
                            unset($stock_data[$k]['sale_wait_num']);
                            unset($stock_data[$k]['allocation_wait_num']);
                            //更新保存新库存数据
                            $status_update = D('ErpStock')->where(['id' => $stock_xs_info['id']])->save($stock_data[$k]);
                            log_info("更新后库存，库存ID:" . $stock_xs_info['id'] . "商品ID：{$stock_data[$k]['goods_id']}, 仓库ID：{$stock_data[$k]['object_id']}, 地区：{$region}, 物理库存：{$stock_data[$k]['stock_num']}, 可用库存：{$stock_data[$k]['available_num']}");
                            $status_all = $status_all && $status_update ? true :false;
                        }else{
                            $status_add = D('ErpStock')->add($stock_data[$k]);
                            log_info("新增库存，库存ID:" . $stock_xs_info['id'] . "商品ID：{$stock_data[$k]['goods_id']}, 仓库ID：{$stock_data[$k]['object_id']}, 地区：{$region}, 物理库存：{$stock_data[$k]['stock_num']}, 可用库存：{$stock_data[$k]['available_num']}");
                            $status_all = $status_all && $status_add ? true :false;
                        }
                    }

                    //------------------------------------------------------------------------------------------------
                    echo '第'. $i . '条数据已处理！<br/>';
                }else{
                    echo trim($value[0]) . '&&' . trim($value[1])  . '&&' .   trim($value[2])  . '&&' .  trim($value[3]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（地区-仓库类型-仓库-商品代码） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
            }

            //print_r($stock_data);
            //$status = D('ErpStock')->addAll($stock_data);
            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }

    /**
     * 附件详情
     * @param $param
     * id：出入库单id  type：出库单-1  入库单-2
     * @return 图片路径
     */
    public function attachmentDetail($param)
    {
        $attachment = '';
        if ($param['type'] == 1) {
            $order_info = $this->getModel('ErpStockOut')->where(['id'=>$param['id']])->find();
            if(strpos($order_info['attachment'] ,"http://") === false){
                $attachment['name'] = substr($order_info['attachment'],strripos($order_info['attachment'],'/')+1);
                $attachment['url'] = $this->uploads_path['stock_out_attach']['url'] . $order_info['attachment'];
            }else{
                $attachment['name'] = "图片";
                $attachment['url'] = $order_info['attachment'];
            }
        } elseif ($param['type'] == 2) {
            $order_info = $this->getModel('ErpStockIn')->where(['id'=>$param['id']])->find();
            $attachment['name'] = substr($order_info['attachment'],strripos($order_info['attachment'],'/')+1);
            $attachment['url'] = $this->uploads_path['stock_in_attach']['url'] . $order_info['attachment'];
        }
        return $attachment;
    }

    /**
     * 财务核对（出/入库单）
     * @param $param
     * id：出入库单id  type：出库单-1  入库单-2
     * @return boolean
     */
    public function financeConfirm($param)
    {
        if ($param['type'] == 1) {
            $model = 'ErpStockOut';
            $order_number = 'outbound_code';
        } elseif ($param['type'] == 2) {
            $model = 'ErpStockIn';
            $order_number = 'storage_code';
        }

        $order_info = $this->getModel($model)->where(['id'=>$param['id']])->find();
        if ($order_info['finance_status'] == 10) {
            $result = [
                'status' => 2,
                'message' => '该单据已财务核对，请勿重复操作。',
            ];
            return $result;
        }
        if (empty($order_info['attachment'])) {
            $result = [
                'status' => 3,
                'message' => '该单据未上传附件，请检查。',
            ];
            return $result;
        }
        M()->startTrans();
        $data = [
            'update_time' => currentTime(),
            'finance_status' => 10,
        ];
        $status = $this->getModel($model)->where(['id'=>$param['id']])->save($data);

        $status_log = $this->addStockOptionLog($order_info[$order_number], $param['type'], 5);

        if($status && $status_log){
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        }else{
            M()->rollback();
            $result = [
                'status' => 2,
                'message' => '操作失败',
            ];
        }

        return $result;
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
    public function financialRejection( $param = [] )
    {
        if ( !isset($param['type']) || !in_array($param['type'], [1,2]) ) {
            return [ 'status' => 2,'message' => '参数错误！'];
        }

        if ( !isset($param['id']) || empty($param['id']) ) {
            return [ 'status' => 3,'message' => '参数错误！' ];
        }

        if ( $param['type'] == 1 ) {
            $model = 'ErpStockOut';
            $order_number = 'outbound_code';
        } elseif ( $param['type'] == 2 ) {
            $model = 'ErpStockIn';
            $order_number = 'storage_code';
        }

        $arr = $this->getModel($model)->field('finance_status,'.$order_number)->where(['id'=>$param['id']])->find();
        if ( $arr['finance_status'] == 2 ) {
            return [ 'status' => 5,'message' => '该单据已财务驳回，请勿重复操作！'];
        }
        M()->startTrans();
        /* --------------- 操作财物驳回日志 ---------------- */
        $insert_arr = [
            'order_type' => $param['type'],
            'order_number' => $arr[$order_number],
            'log_type'      => 6,
            'operator_id'   => $this->getUserInfo('id'),
            'operator'      => $this->getUserInfo('dealer_name'),
            'create_time'   => nowTime(),
        ];

        $insert_result = $this->getModel("ErpStockOptionLog")->add($insert_arr);
        if ( !$insert_result ) {
            return ['status' => 8,'message' => '添加财务驳回日志失败！'];
        }

        (array)$update_data = [
            'update_time' => currentTime(),
            'finance_status' => 2, // 2 属于驳回状态
        ];

        $status = $this->getModel($model)->where(['id'=>$param['id']])->save($update_data);
        if ( $status ) {
            M()->commit();
            return [ 'status' => 1,'message' => '操作成功！'];
        }
        M()->rollback();
        return [ 'status' => 4,'message' => '操作失败！'];
    }

    /**
     * 导入库存盘点数据
     * @param $data
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importSkidData($data){
        if($data){

            $city_all = provinceCityZone()['city'];

            $facilitatorData = D('Facilitator')->where(['status' => 1])->order('facilitator_id desc')->getField('facilitator_id, name');

            $stock_type = [
                1 => '城市仓',
                4 => '加油网点',
            ];

            $goods_data = D('ErpGoods')->where(['status' => 10])->getField('id, goods_code');

            $facilitator_skid_data = D('FacilitatorSkid')->where(['status'=> 1])->getField('facilitator_skid_id, name');
            var_dump($facilitator_skid_data);
            $ourCompanyData = getOurCompany();
            $i = 1;
            $k = 0;
            $stock_data = [];
            $status_all = true;
            M()->startTrans();
            foreach($data as $key=>$value){

                if(trim($value[0]) && trim($value[1])  && trim($value[2]) && trim($value[3]) && trim($value[4])){
                    if(!in_array(trim($value[0]), $city_all)){
                        echo trim($value[0]);
                        die('地区不存在系统中');
                    }
                    if(!in_array(trim($value[1]), $stock_type)){
                        die('仓库类型有误，只能是'.implode(',' , $stock_type));
                    }else{

                        $region = array_search(trim($value[0]), $city_all);

                        if(array_search(trim($value[1]), $stock_type) == 4){
                            var_dump($value[3]);
                            var_dump(in_array(trim($value[3]), $facilitator_skid_data));
                            if(!in_array(trim($value[3]), $facilitator_skid_data)){
                                die('加油网点:'.trim($value[3]).' 加油网点不存在');
                            }else{
                                $stock_data[$k]['object_id'] = array_search(trim($value[3]), $facilitator_skid_data);
                                $stock_data[$k]['facilitator_id'] = trim($value[2]);
                            }
                        }else{
                            die('仓库数据有误');
                        }
                    }
                    if(!in_array(trim($value[4]), $goods_data)){
                        die('商品代码不存在');
                    }
//                    if(!in_array(trim($value[16]), $ourCompanyData)){
//                        die('我方帐套公司不存在');
//                    }
                    //----------------------组装数据------------------------------------------------------------------
                    $stock_data[$k]['goods_id'] = array_search(trim($value[4]), $goods_data);
                    $stock_data[$k]['stock_type'] = array_search(trim($value[1]), $stock_type);
                    $stock_data[$k]['region'] = $region;
                    $stock_data[$k]['stock_num'] = setNum($value[9]);
                    $stock_data[$k]['transportation_num'] = setNum($value[10]);
                    $stock_data[$k]['sale_reserve_num'] = setNum($value[11]);
                    $stock_data[$k]['allocation_reserve_num'] = setNum($value[12]);
                    $stock_data[$k]['sale_wait_num'] = setNum($value[13]);
                    $stock_data[$k]['allocation_wait_num'] = setNum($value[14]);
                    $stock_data[$k]['available_num'] = ($stock_data[$k]['stock_num'] + $stock_data[$k]['transportation_num']) - ($stock_data[$k]['sale_reserve_num'] + $stock_data[$k]['allocation_reserve_num'] + $stock_data[$k]['sale_wait_num'] + $stock_data[$k]['allocation_wait_num']); //setNum(intval($value[14]));
                    $stock_data[$k]['our_company_id'] = array_search(trim($value[16]), $ourCompanyData) ? array_search(trim($value[16]), $ourCompanyData) : 70;
                    $stock_data[$k]['create_time'] = currentTime();

                    if($stock_data[$k]['stock_type'] == 4){
                        $stock_xs_info = D('ErpStock')->where(['goods_id' => $stock_data[$k]['goods_id'], 'object_id'=>$stock_data[$k]['object_id'], 'stock_type'=>4, 'our_company_id'=>$stock_data[$k]['our_company_id']])->find();
                        log_info("已存在库存，库存ID:" . $stock_xs_info['id'] . "商品ID：{$stock_data[$k]['goods_id']}, 仓库ID：{$stock_data[$k]['object_id']}, 地区：{$region}, 物理库存：{$stock_xs_info['stock_num']}, 可用库存：{$stock_xs_info['available_num']}");
                        if(!empty($stock_xs_info)){
                            //如果已存在，则加上物理，加上可用
                            $stock_data[$k]['stock_num'] = $stock_xs_info['stock_num'] + $stock_data[$k]['stock_num'];
                            $stock_data[$k]['available_num'] = $stock_xs_info['available_num'] + $stock_data[$k]['available_num'];

                            unset($stock_data[$k]['transportation_num']);
                            unset($stock_data[$k]['sale_reserve_num']);
                            unset($stock_data[$k]['allocation_reserve_num']);
                            unset($stock_data[$k]['sale_wait_num']);
                            unset($stock_data[$k]['allocation_wait_num']);
                            //更新保存新库存数据
                            $status_update = D('ErpStock')->where(['id' => $stock_xs_info['id']])->save($stock_data[$k]);
                            log_info("更新后库存，库存ID:" . $stock_xs_info['id'] . "商品ID：{$stock_data[$k]['goods_id']}, 仓库ID：{$stock_data[$k]['object_id']}, 地区：{$region}, 物理库存：{$stock_data[$k]['stock_num']}, 可用库存：{$stock_data[$k]['available_num']}");
                            $status_all = $status_all && $status_update ? true :false;
                        }else{
                            $status_add = D('ErpStock')->add($stock_data[$k]);
                            log_info("新增库存，库存ID:" . $stock_xs_info['id'] . "商品ID：{$stock_data[$k]['goods_id']}, 仓库ID：{$stock_data[$k]['object_id']}, 地区：{$region}, 物理库存：{$stock_data[$k]['stock_num']}, 可用库存：{$stock_data[$k]['available_num']}");
                            $status_all = $status_all && $status_add ? true :false;
                        }
                    }

                    //------------------------------------------------------------------------------------------------
                    echo '第'. $i . '条数据已处理！<br/>';
                }else{
                    echo trim($value[0]) . '&&' . trim($value[1])  . '&&' .   trim($value[2])  . '&&' .  trim($value[3]) . '&&' .  trim($value[4]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（地区-仓库类型-仓库-商品代码） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
            }

            //print_r($stock_data);
            //$status = D('ErpStock')->addAll($stock_data);
            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }

    /**
     * 生成销售单
     * @param $data
     * @author xiaowen
     * @time 2017-6-21
     * @return bool $status
     */
    public function importSaleOrderData($data){
        $status = false;
        if($data){
            $city_all = provinceCityZone()['city'];
            $storeHouseData = D('ErpStorehouse')->where(['status' => 1, 'type'=>1])->order('id desc')->getField('id, region');
            //$ourCompanyData = getOurCompany();
            $i = 1;
            $k = 0;
            $order_data = [];
            //$status_all = true;

            $allGoods = D('ErpGoods')->where(['status'=>10])->getField('id,goods_code');
            //print_r($allGoods);
            M()->startTrans();

            foreach($data as $key=>$value){
                if(trim($value[0]) && trim($value[1]) && trim($value[2]) && trim($value[3]) && trim($value[4]) && trim($value[5]) && trim($value[6])){

                    if(!in_array(trim($value[2]), $city_all)){
                        echo trim($value[2]);
                        die('地区不存在系统中');
                    }
                    $region = array_search(trim($value[2]), $city_all);

                    if(!in_array($region, $storeHouseData)){
                        echo trim($value[3]);
                        die('仓库不存在系统中');
                    }
                    $storehouse_id = array_search($region, $storeHouseData);

                    $clients_info = M('Clients')->where(['company_name'=>trim($value[6])])->order('id desc')->find();
                    if(empty($clients_info)){
                        die(trim($value[6]) . '不存在');
                    }
                    $company_id = $clients_info['id'];
                    //$uc_user_id = M('Uc')->where(['company_id'=>intval($company_id), 'is_available'=>0])->getField('user_id', true);
                    $user_info_id = M('User')->where(['user_phone'=>trim($value[5]), 'is_available'=>['in', [0, 2]]])->order('id desc')->getField('id');
                    if(!$user_info_id){
                        die('用户:' . trim($value[4]) .' 手机：'. trim($value[5]). '不存在');
                    }

                    if(!in_array(trim($value[7]), $allGoods)){
                        echo trim($value[7]);
                        die('商品不存在系统中');
                    }

                    $goods_id = array_search(trim($value[7]), $allGoods);

                    $order_data[$k]['order_number'] = erpCodeNumber(6)['order_number'];
                    $order_data[$k]['user_id'] = $user_info_id;
                    $order_data[$k]['company_id'] = $company_id;
                    $order_data[$k]['goods_id'] = $goods_id;
                    $order_data[$k]['our_company_id'] = 3372;
                    $order_data[$k]['add_order_time'] = '2017-06-11 23:59:59';
                    $order_data[$k]['region'] = $region;
                    $order_data[$k]['depot_id'] = 99999;
                    $order_data[$k]['pay_type'] = 1;
                    $order_data[$k]['storehouse_id'] = $storehouse_id;
                    $order_data[$k]['user_bank_info'] = "{$clients_info['bank_name']}--{$clients_info['bank_num']}";
                    $order_data[$k]['company_info'] = '注册电话:' . $clients_info['company_tel'] . ' 注册地址：' . $clients_info['company_address'];
                    $order_data[$k]['price'] = 0;
                    $order_data[$k]['buy_num'] = setNum(intval(trim($value[12])));
                    $order_data[$k]['order_status'] = 10;
                    $order_data[$k]['collection_status'] = 10;
                    $order_data[$k]['dealer_id'] = 32;
                    $order_data[$k]['dealer_name'] = '邬龙吟';
                    $order_data[$k]['create_time'] = currentTime();
                    $order_data[$k]['creater'] = $this->getUserInfo('id');
                    $order_data[$k]['update_time'] = currentTime();
                    $order_data[$k]['confirm_time'] = currentTime();
                    $order_data[$k]['check_time'] = currentTime();
                    $order_data[$k]['end_order_time'] = currentTime();
                    $order_data[$k]['delivery_method'] = 2;
                    $order_data[$k]['delivery_date'] = '2017-06-11 00:00:00';

                    echo "第{$i}条数据导入成功<br/>";
                    $i++;
                    $k++;
                }else{
                    die("(订单日期	交易员	城市	上海仓	客户	公司名称	产品代码) 必须填写");
                }
            }
            $status = $this->getModel('ErpSaleOrder')->addAll($order_data);
            $status_str = $status ? '成功' : '失败';
            if($status){
                M()->commit();
            }else{
                M()->rollback();
            }
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;
            //return $status;
        }
        //return $status;
    }

    /**
     * 客户待提详情
     * @param array $param 查询参数
     * @param $order
     * @author senpai
     * @time 2017-06-29
     */
    public function showUserDetail($param = [], $order = 'id desc')
    {
        $stock_info = $this->getModel('ErpStock')->where(['id'=>$param['id']])->find();

        $where = [
            'goods_id' => $stock_info['goods_id'],
//            'region' => $stock_info['region'],
            'storehouse_id' => $stock_info['object_id'],
            'our_company_id' => session('erp_company_id')
        ];

//        if ($stock_info['stock_type'] == 1) {
//            $where['is_agent'] = 2;
//        }elseif ($stock_info['stock_type'] == 2) {
//            $where['is_agent'] = 1;
//        }

        if (trim($param['sale_user'])) {
            $where['user_name'] = ['like', '%' . trim($param['sale_user']) . '%'];
        }
        if (trim($param['sale_company_id'])) {
            $where['company_id'] = intval(trim($param['sale_company_id']));
        }

        $where['buy_num'] = ['neq',0];

        $field = '*,sum(user_total_wait_num - outbound_quantity) as total_wait_num';

        $offset = $_REQUEST['start'] ? $_REQUEST['start'] : 0;
        $limit = $_REQUEST['length'] ? $_REQUEST['length'] : 10;

        $StockObj = D("ErpUserWaitNumView");
        $data['recordsTotal'] = $StockObj->where($where)->count();
        $data['data'] = $StockObj
            ->field($field)
            ->where($where)
            ->limit($offset, $limit)
            ->order($order)
            ->group('user_id,company_id')
            ->select();

        if($data['data']){

            foreach($data['data'] as $key=>$value){
                $data['data'][$key]['total_wait_num'] = getNum($value['total_wait_num']);
            }

        }else{
            $data['data'] = [];
            $data['recordsTotal'] = 0;
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;

    }

    /**
     * 审核采退出单库(未完成) 周末或周一完成
     * @param $stockOut_info
     * @author xiaowen
     * @time 2017-9-1
     * @return array
     */
    public function auditReturnedStockOut($stockOut_info){
        if($stockOut_info){

            $returned_info = $this->getModel('ErpReturnedOrder')->findOneReturnedOrder(['id'=>$stockOut_info['source_object_id']]);
            //log_info('采退单'.print_r($returned_info, true));
            if($returned_info) {
                $purchase_info = $this->getModel('ErpPurchaseOrder')->findPurchaseOrder(['id' => $returned_info['source_order_id']]);

                //出库单类型是采退，影响城市仓库存
                //log_info('采购单'.print_r($purchase_info, true));
                $stock_data['goods_id'] = $stockOut_info['goods_id'];
                $stock_data['stock_type'] = $purchase_info['type'] == 1 ? getAllocationStockType($purchase_info['storehouse_id']) : 2;
                $stock_data['object_id'] = $purchase_info['storehouse_id'];
                $stock_data['region'] = $purchase_info['region'];
                //$region = $purchase_info['region'];

                //查询当前库存的各个库存字段的值
                $stock_info = $this->getStockInfo($stock_data);

                //保存变动物理库存之前的物理数量 eidt xiaowen---------------
                $beforeNum = $stock_info['stock_num'];
                $stockId = $stock_info['id'];

                //$stock_log_data = [];
                //采购数量-已入库数量 = 该采购单在途数量
                //$transportation_num = intval($purchase_info['goods_num'] - $purchase_info['storage_quantity']);
                //$actual_outbound_num = 0;
//                if($returned_info['return_goods_num'] <= $transportation_num && $transportation_num > 0){
//                    //log_info('回滚库存1');
//                    //判断当前物理库存是否满足出库数量
//                    if($stock_info['transportation_num'] < $stockOut_info['actual_outbound_num']){
//                        M()->rollback();
//                        cancelCacheLock('ErpStock/auditErpStockOut');
//                        return  $result = [
//                            'status' => 4,
//                            'message' => '当前在途库存不足，无法出库',
//                            'message' => '当前在途库存不足，请联系采购员进行自采或入库开卡',
//                        ];
//                    }else{
//                        //全部从在途出库
//                        $stock_data['transportation_num'] = $stock_info['transportation_num'] = $stock_info['transportation_num'] - $stockOut_info['actual_outbound_num'];
//                        $actual_outbound_num = 0;
//                    }
//
//                }else if($transportation_num > 0 && $returned_info['return_goods_num'] > $transportation_num && $returned_info['return_goods_num'] <= $purchase_info['goods_num']){
//                    //log_info('回滚库存2');
//                    if($stock_info['transportation_num'] < $transportation_num){
//                        M()->rollback();
//                        cancelCacheLock('ErpStock/auditErpStockOut');
//                        return  $result = [
//                            'status' => 4,
//                            'message' => '当前在途库存不足，无法出库',
//                            //'message' => '当前在途库存不足，请联系采购员进行自采或入库开卡',
//                        ];
//                    //}else if($stock_info['transportation_num'] < $purchase_info['storage_quantity']){
//                    }else if($stock_info['transportation_num'] < $purchase_info['goods_num'] - $transportation_num){
//                        M()->rollback();
//                        cancelCacheLock('ErpStock/auditErpStockOut');
//                        return  $result = [
//                            'status' => 4,
//                            'message' => '当前物理库存不足，请联系采购员进行自采或入库开卡',
//                        ];
//
//                    }else{
//                        //出在途部分
//                        $stock_data['transportation_num'] = $stock_info['transportation_num'] = $stock_info['transportation_num'] - $transportation_num;
//                        //出物理部分  退货数量-在途数量 = 实际物理应退数量
//                        $actual_outbound_num = $returned_info['return_goods_num'] - $transportation_num;
//                        $stock_data['stock_num'] = $stock_info['stock_num'] = $stock_info['stock_num'] - $actual_outbound_num;
//
//                        //在途、物理同时需要退回库存的情况下，多记录一条日志，因为变动数量不一样
//                        $stock_log_data = [
//                            'stock_id' => $stock_info['id'],
//                            'object_number' => $stockOut_info['outbound_code'],
//                            'object_type' => 3,
//                            'log_type' => 2,
//                            'change_num' => $transportation_num,
//                            'before_stock_num' => $stock_info['stock_num'],
//                            'before_transportation_num' => $stock_info['transportation_num'],
//                            'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
//                            'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
//                            'before_sale_wait_num' => $stock_info['sale_wait_num'],
//                            'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
//                            'before_available_num' => $stock_info['available_num'],
//                            'after_stock_num' => $stock_data['stock_num'],
//                            'after_transportation_num' => $stock_data['transportation_num'],
//                            'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
//                            'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
//                            'after_sale_wait_num' => $stock_info['sale_wait_num'],
//                            'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
//                            //'after_available_num' => $old_stock_info['available_num'],
//                        ];
//                    }
//                }else if($transportation_num == 0 && $returned_info['return_goods_num'] <= $purchase_info['goods_num']){
//                    //log_info('回滚库存3');
//                    if($stock_info['stock_num'] < $stockOut_info['actual_outbound_num']){
//                        M()->rollback();
//                        cancelCacheLock('ErpStock/auditErpStockOut');
//                        return  $result = [
//                            'status' => 6,
//                            'message' => '当前物理库存不足，请联系采购员进行自采或入库开卡',
//                        ];
//                    }else{
//                        //全部从物理出库
//                        $stock_data['stock_num'] = $stock_info['stock_num'] = $stock_info['stock_num'] - $stockOut_info['actual_outbound_num'];
//                        $actual_outbound_num = $stockOut_info['outbound_num'];
//                    }
//                }
                $transportation_num = $stockOut_info['outbound_num'] - $stockOut_info['actual_outbound_num'];
                if ($stock_info['transportation_num'] < $transportation_num) {
                    M()->rollback();
                    cancelCacheLock('ErpStock/auditErpStockOut');
                    return $result = [
                        'status' => 4,
                        'message' => '当前在途库存不足，无法出库',
                        // 'message' => '当前在途库存不足，请联系采购员进行自采或入库开卡',
                    ];
                } else if($stock_info['stock_num'] < ($stockOut_info['actual_outbound_num'])){
                    M()->rollback();
                    cancelCacheLock('ErpStock/auditErpStockOut');
                    return $result = [
                        'status' => 4,
                        'message' => '当前物理库存不足，无法出库',
                        // 'message' => '当前在途库存不足，请联系采购员进行自采或入库开卡',
                    ];

                }else{
                    //出在途库存
                    $stock_data['transportation_num'] = $stock_info['transportation_num'] = $stock_info['transportation_num'] - $transportation_num;
                    //出物理库存
                    $stock_data['stock_num'] = $stock_info['stock_num'] = $stock_info['stock_num'] - $stockOut_info['actual_outbound_num'];
                }


                //--------------- 更新出库单，实际出库数量，可能跟生成出库单时不准，全部出在途时 实际出库数量为0------
                //通过java接口获取出库单成本 edit xiaowen 2018-2-4  【edit xiaowen 2019-6-6 采退出库直接取采购单价，不用调成本接口】
                //$stock_out_cost = getStockOutCost($stockOut_info);

                $stockOut_info['cost'] = $purchase_info['price'] ? $purchase_info['price'] : 0;
                //$stockOut_info['cost_log_id'] = $stock_out_cost['logId'] ? $stock_out_cost['logId'] : 0;
                $stockOut_info['cost_log_id'] = 0;

                $stock_out_update_data = [
                    'outbound_status'   => 10,
                    'auditor_id'        => $this->getUserInfo('id'),
                    'audit_time'        => currentTime(),
                    'update_time'       => currentTime(),
                    'cost'              => $stockOut_info['cost'], //edit xiaowen 2018-2-4
                    'cost_log_id'       => $stockOut_info['cost_log_id'], //edit xiaowen 2018-4-9
                ];

                $stock_out_status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$stockOut_info['id']], $stock_out_update_data);

                //---------------- end 出库单更新完成------------------------------------------------------------------

                //------------------计算出新的可用库存-----------------------------------
                $stock_data['available_num'] = $this->calculateAvailableNum($stock_info);
                //-----------------------------------------------------------------------
                //-----------------退货单出库单审核通过需要记录两条库存日志，分别反映，在途、物理的变动数量-------------
                $stock_log_data = [
                    'stock_id' => $stock_info['id'],
                    'object_number' => $stockOut_info['outbound_code'],
                    'object_type' => 3,
                    'log_type' => 2,
                    'change_num' => $transportation_num,
                    'before_stock_num' => $stock_info['stock_num'],
                    'before_transportation_num' => $stock_info['transportation_num'],
                    'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
                    'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                    'before_sale_wait_num' => $stock_info['sale_wait_num'],
                    'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
                    'before_available_num' => $stock_info['available_num'],
                    'after_stock_num' => $stock_data['stock_num'],
                    'after_transportation_num' => $stock_data['transportation_num'],
                    'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
                    'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                    'after_sale_wait_num' => $stock_info['sale_wait_num'],
                    'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
                    'after_available_num' => $stock_data['available_num'],
                ];
                $other_stock_log_status = $this->addStockLog($stock_log_data);

                //保存更新后的库存，及记录库存变动日志
                $orders = [
                    'object_number' =>$stockOut_info['outbound_code'],
                    'object_type' =>3,
                    'log_type' => 12,
                ];
                //log_info('回库存数据：' . print_r($stock_data, true));
                $stock_status = $this->saveStockInfo($stock_data, $stockOut_info['actual_outbound_num'], $orders);

                //----------------库存更新完成后，需要同步更新采购单-----------------------
                $purchase_order_data = [
                    //'returned_goods_num'=>$returned_info['return_goods_num'],
                    //更新采购单退货数量 改为累加（增加批次后多产生多张采退出库）edit xiaowen 2019-2-27
                    // 'returned_goods_num'=>$purchase_info['returned_goods_num'] + $returned_info['return_goods_num'],
                    'returned_goods_num'=>$purchase_info['returned_goods_num'] + $stockOut_info['outbound_num'],
                    //'pay_status'        => 4,
                    'update_time'       => currentTime(),
                ];
                $purchase_order_status = $this->getModel('ErpPurchaseOrder')->savePurchaseOrder(['id'=>$returned_info['source_order_id']], $purchase_order_data);
                //---------------- end 库存更新完成后，需要同步更新采购单-------------------
            }

            # 审核通过，增加操作日志

            $add_log_status = $this->addStockOptionLog($stockOut_info['outbound_code'], 1, 1);
            //=====================================================================================================
            // =======edit xiaowen 2019-2-25 采退出库更新批次数量============================
            if($stockOut_info['actual_outbound_num'] > 0 && $stockOut_info['batch_id'] > 0){
                $batch_change_data = [
                    'batch_id' => $stockOut_info['batch_id'],
                    'change_balance_num' => plusConvert($stockOut_info['actual_outbound_num']), //减少批次可用
                    'change_reserve_num' => plusConvert($stockOut_info['actual_outbound_num']), //减少批次预留
                    'change_type' => 2,
                    'change_number' => $stockOut_info['outbound_code'],
                ];
                $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
                if($batch_result['status'] != 1){
                    M()->rollback();
                    return $batch_result;
                }
            }else{
                $batch_result['status'] = 1;
            }

            //==========end 批次处理==========================================================
            $status = $stock_status && $stock_out_status && $purchase_order_status && $other_stock_log_status && $add_log_status && $batch_result['status'] == 1 ? true : false;
            if($status){
                //重新计算加权成本
                $stockOut_info['before_stock_num'] = $beforeNum;
                $stockOut_info['stock_id'] = $stockId ? $stockId : 0;
                //edit xiaowen 2019-6-6 采退出库审核，计算成本，应该以负数入库计算
                $stockOut_info['change_num'] = plusConvert($stockOut_info['actual_outbound_num']);
                updateStockInCost($stockOut_info);

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
            return $result;
        }
    }

    /**
     * 审核销售出库单
     * @param $stockOut_info
     * @time 2017-9-1
     * @return array
     */
    public function auditSaleStockOut($stockOut_info){
        $stock_data['goods_id'] = $stockOut_info['goods_id'];
        $field_stock = '';
        $order_info = [];
        $region = 0;
        if($stockOut_info['outbound_type'] == 1){ //出库单类型是销售，影响城市仓库存
            $field_stock = 'sale_wait_num';
            $order_info = $this->getEvent('ErpSale')->findSaleOrder($stockOut_info['source_object_id']);
            $stock_data['stock_type'] = $order_info['is_agent'] == 1 ? 2 : getAllocationStockType($stockOut_info['storehouse_id']);
            $stock_data['object_id'] = $order_info['storehouse_id'];
            $region = $order_info['region'];
        }

        //查询当前库存的各个库存字段的值
        $stock_info = $this->getStockInfo($stock_data);
        //判断当前物理库存是否满足出库数量
        if($stock_info['stock_num'] < $stockOut_info['actual_outbound_num']){
            M()->rollback();
            cancelCacheLock('ErpStock/auditErpStockOut');
            return  $result = [
                'status' => 4,
                'message' => '当前物理库存不足，请联系采购员进行自采或入库开卡',
            ];
        }

        //--------------- 更新出库单，实际出库数量，可能跟生成出库单时不准，全部出在途时 实际出库数量为0------
        //通过java接口获取出库单成本 edit xiaowen 2018-2-4
        $stock_out_cost = getStockOutCost($stockOut_info);
        //如果成本值为空，则return edit qianbin 2018-03-09
        if($stock_out_cost['price']===null){
            return  [
                'status' => false,
                'message' => '成本获取失败，请联系管理员！',
            ];
        }
        log_info("出库单[".$stockOut_info['outbound_code'] ." ]成本：" . $stock_out_cost['price']);
        $stock_out_update_data = [
            'outbound_status' => 10,
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'update_time' => currentTime(),
            //将成本保存在出库单上 edit xiaowen 2018-2-4
            'cost'  => $stock_out_cost['price'],
            'cost_log_id'  => $stock_out_cost['logId'],
        ];
        //网点销售要进行入库可用抵扣
        if ($stockOut_info['stock_type'] == 4) {
//            $stock_in_result = $this->getEvent('ErpNewAllocationOrder')->influenceStockIn($stockOut_info);
            $stock_in_result = $this->getEvent('ErpNewAllocation')->influenceStockIn($stockOut_info);
            $stock_out_update_data['deduction_num'] = $stock_in_result['deduction_num'];
        } else {
            $stock_in_result['status'] = true;
        }

        $stock_out_status = $this->getModel('ErpStockOut')->saveStockOut(['id'=>$stockOut_info['id']], $stock_out_update_data);
        //---------------- end 出库单更新完成------------------------------------------------------------------
        # 审核通过，增加操作日志

        $add_log_status = $this->addStockOptionLog($stockOut_info['outbound_code'], 1, 1);

        $stock_data['region'] = $region;
        //出库单审核，减少销售待提或配货待提
        $stock_data[$field_stock] = $stock_info[$field_stock] = $stock_info[$field_stock] - $stockOut_info['actual_outbound_num'];
        //出库单审核，同时减少物理库存
        $stock_data['stock_num'] = $stock_info['stock_num'] = $stock_info['stock_num'] - $stockOut_info['actual_outbound_num'];
        //------------------计算出新的可用库存----------------------------
        $stock_data['available_num'] = $this->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        //保存更新后的库存，及记录库存变动日志
        $orders = [
            'object_number' =>$stockOut_info['outbound_code'],
            'object_type' =>3,
            'log_type' => 2,
        ];
        $stock_status = $this->saveStockInfo($stock_data, $stockOut_info['actual_outbound_num'], $orders);
        //如果是销售单出库，需要更新销售的出库数量
        if($stockOut_info['outbound_type'] == 1 && $order_info){
            $outbound_quantity = $order_info['outbound_quantity'];
            $order_update_data = [
                'outbound_quantity'=> $outbound_quantity + $stockOut_info['actual_outbound_num'],
                'update_time'=> currentTime(),
            ];
            $order_status = $this->getEvent('ErpSale')->saveSaleOrderById($stockOut_info['source_object_id'], $order_update_data);
        }
        /** --------出库单审核影响库存-end------- **/
        // =======edit xiaowen 2019-2-25 销售出库更新批次数量============================
        //实际出库数据大于 0 且存在出库批次 才影响批次数量
        if($stockOut_info['actual_outbound_num'] > 0 && $stockOut_info['batch_id'] > 0){
            $batch_change_data = [
                'batch_id' => $stockOut_info['batch_id'],
                'change_balance_num' => plusConvert($stockOut_info['actual_outbound_num']), //减少批次可用
                'change_reserve_num' => plusConvert($stockOut_info['actual_outbound_num']), //减少批次预留
                'change_type' => 2,
                'change_number' => $stockOut_info['outbound_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
            if($batch_result['status'] != 1){
                M()->rollback();
                return $batch_result;
            }
        }else{
            $batch_result['status'] = 1;
        }


        //==========end 批次处理==========================================================
        $status = $stock_status && $order_status && $stock_out_status && $add_log_status &&  $batch_result['status'] == 1 && $stock_in_result['status'] ? true : false;
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
        return $result;
    }

    /**
     * 添加出入库单操作日志
     * @param $stock_option_code 库存单据编号
     * @param $log_type 1 审核 2 取消审核 3 取消
     * @param $code_type 库存单据类型 1 出库单 2 入库单
     * @author xiaowen
     * @time 2017-9-4
     * @return bool
     */
    public function addStockOptionLog($stock_option_code, $code_type, $log_type = 1){
        # 审核通过，增加操作日志
        # qianbin
        # 2017.7.17
        $erp_stock_option_log = [
            'order_type'    => $code_type,
            'order_number'  => trim($stock_option_code),
            'log_type'      => $log_type,
            'operator_id'   => session('erp_adminInfo')['id'],
            'operator'      => session('erp_adminInfo')['dealer_name'],
            'create_time'   => date('Y-m-d H:i:s',time())
        ];
        $add_log = M('erpStockOptionLog')->add($erp_stock_option_log);

        return $add_log;
        //=====================================================================================================
    }

    /**
     * 出入库报表
     * @author qianbin
     * @time   2017-10-25
     * @return bool
     */
    public function reportFormsList($param = [],$start = 0, $length = 10 , $count = 1)
    {
        $where = [];
        $data  = [];
        if(trim($param['order_number'])){
            $where['source_number']  = ['like', '%' . trim($param['order_number']) . '%'];
        }
        # -------------------------------------------------测试时间搜索----------------------------------------
        if (isset($param['create_start_time']) || isset($param['create_end_time'])) {
            if (trim($param['create_start_time']) && !trim($param['create_end_time'])) {
                $where['create_time'] = ['egt', trim($param['create_start_time'])];
            } else if (!trim($param['create_start_time']) && trim($param['create_end_time'])) {
                $where['create_time'] = ['elt', date('Y-m-d 23:59:59', strtotime(trim($param['create_end_time'])))];
            } else if (trim($param['create_start_time']) && trim($param['create_end_time'])) {
                $where['create_time'] = ['between', [trim($param['create_start_time']), date('Y-m-d 23:59:59', strtotime(trim($param['create_end_time'])))]];
            }
        }
        if (isset($param['audit_start_time']) || isset($param['audit_end_time'])) {
            if (trim($param['audit_start_time']) && !trim($param['audit_end_time'])) {
                $where['audit_time'] = ['egt', trim($param['audit_start_time'])];
            } else if (!trim($param['audit_start_time']) && trim($param['audit_end_time'])) {
                $where['audit_time'] = ['elt', date('Y-m-d 23:59:59', strtotime(trim($param['audit_end_time'])))];
            } else if (trim($param['audit_start_time']) && trim($param['audit_end_time'])) {
                $where['audit_time'] = ['between', [trim($param['audit_start_time']), date('Y-m-d 23:59:59', strtotime(trim($param['audit_end_time'])))]];
            }
        }
        if(intval($param['goods_id'])){
            $where['goods_id'] = intval($param['goods_id']);
        }
        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['region'] = ['in',$city_id];
        }
        if(intval($param['region'])){
            $where['region'] = intval($param['region']);
        }
        $data['recordsTotal'] = M('ErpStockFormsView')->where($where)->count();
        if($count == 2) return $data['recordsTotal'];
        $data['data']         = M('ErpStockFormsView')->field(true)->where($where)->limit($start, $length)->order('create_time desc')->select();
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw']            = $_REQUEST['draw'];
        if(count($data['data']) < 0) return $data;
        # 处理数据------------------------------------------------------------------------
        $goods_data       = [];
        $storehouse_data  = [];
        $facilitator_skid_data = [];
        $dealer_data      = [];
        $city_data        = provinceCityZone()['city'];
        # 单据类型
        $storage_type = stockInType();
        $outbound_type = stockOutType();
        # 出入库类型
        $stock_order_type = [
            1 => '入库单',
            2 => '出库单'
        ];
        # 查询商品
        $goods_id = array_unique(array_column($data['data'],'goods_id'));
        if(count($goods_id) > 0) {
            $goods_data = $this->getModel('ErpGoods')->where(['id' => ['in',$goods_id]])->getField('id,goods_code,goods_name,source_from,grade,level',true);
        }
        # 查询仓库
        $storehouse_id  = array_unique(array_column($data['data'],'storehouse_id'));
        if(count($storehouse_id) > 0){
            $storehouse_data = $this->getModel('ErpStorehouse')->where(['id' => ['in',$storehouse_id]])->getField('id,storehouse_name',true);
        }
//        # 服务网点
//        $facilitator_skid_id = array_unique(array_column($data['data'],'storehouse_id'));
//        if(count($facilitator_skid_id) > 0){
//            $facilitator_skid_data = $this->getModel('FacilitatorSkid')->where(['facilitator_skid_id' => ['in',$facilitator_skid_id]])->getField('facilitator_skid_id,name',true);
//        }
        # 审核人
        $dealer_id    = array_unique(array_column($data['data'],'auditor_id'));
        if(count($dealer_id) > 0){
            $dealer_data = $this->getModel('Dealer')->where(['id' => ['in',$dealer_id]])->getField('id,dealer_name',true);
        }
        foreach ($data['data'] as $k => $v) {
            $data['data'][$k]['stock_order_type']   = $stock_order_type[$v['stock_order_type']];
            $data['data'][$k]['type']               = $v['stock_order_type'] == 1 ? $storage_type[$v['type']] : $outbound_type[$v['type']];
            $data['data'][$k]['goods_id']           = $goods_data[$v['goods_id']]['goods_code'].'/'.$goods_data[$v['goods_id']]['source_from'].'/'.$goods_data[$v['goods_id']]['goods_name'].'/'.$goods_data[$v['goods_id']]['grade'].'/'.$goods_data[$v['goods_id']]['level'];
            $data['data'][$k]['region']             = $city_data[$v['region']];
//            $data['data'][$k]['storehouse_id']      = $v['stock_type'] != 4 ? $storehouse_data[$v['storehouse_id']] : $facilitator_skid_data[$v['storehouse_id']];
            $data['data'][$k]['storehouse_id']      = $storehouse_data[$v['storehouse_id']];
            $data['data'][$k]['goods_num']          = getNum($v['goods_num']);
            $data['data'][$k]['num_liter']          = empty($v['num_liter']) ? '-' : getNum($v['num_liter']);
            $data['data'][$k]['auditor_id']         = empty($dealer_data[$v['auditor_id']]) ? '-' : $dealer_data[$v['auditor_id']];
            $data['data'][$k]['remark']             = trim($v['remark']);
            $data['data'][$k]['goods_density']      = empty($v['goods_density']) ? '-':$v['goods_density'];
            $data['data'][$k]['stock_type']         = stockType($v['stock_type']);
            $data['data'][$k]['num_liter']          = round(tonToLiter($data['data'][$k]['goods_num'],$data['data'][$k]['goods_density']),2);
        }
        return $data;
    }

    /**
     * 编辑入库单价格
     * @param int $id
     * @param int $price
     * @author xiaowen
     * @param $id
     * @param $price
     * @return array
     */
    public function updateErpStockInPrice($id, $price){
        if($price <= 0){
            return $result = [
                'status' => 2,
                'message' => '价格必须大于0',
            ];
        }
        M()->startTrans();
        $stock_in_data = $this->getModel('ErpStockIn')->where(['id'=>$id])->find();
        if((!in_array($stock_in_data['storage_type'],[4,5])  && $stock_in_data['price'] == 0)|| $stock_in_data['storage_status'] != 1){
            return $result = [
                'status' => 2,
                'message' => '只有盘点入库单且价格为0，才需要财务编辑价格',
            ];
        }
        $data = [
            'update_time'=>currentTime(),
            'price'=>setNum($price),
        ];
        $status = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$id],$data);
        $stock_option_log = [
            'order_type' => 2,
            'order_number' => $stock_in_data['storage_code'],
            'log_type' => 4,
            'operator_id' => $this->getUserInfo('id'),
            'operator' => $this->getUserInfo('dealer_name'),
            'create_time' => currentTime(),
        ];
        $log_status = $this->getModel('ErpStockOptionLog')->add($stock_option_log);
        log_info("入库单更新状态：" . $status);
        log_info("操作日志状态：" . $log_status);
        if($status && $log_status){
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        }else{
            M()->rollback();
            $result = [
                'status' => 2,
                'message' => '操作失败',
            ];
        }
        return $result;
    }

    /**
     * 盘点入库单审核
     * @param $stockin_info
     * @return array
     */
    public function auditOtherStockIn($stockin_info){
        //修改入库单状态
        $stockin_info['outbound_density'] = $stockin_info['outbound_density'] == 0 ? getConfig('Config_Density') : $stockin_info['outbound_density'];
        $stock_in_data = [
            'actual_storage_num_litre' => $stockin_info['actual_storage_num'] / $stockin_info['outbound_density'] * 1000,
            'balance_num' => $stockin_info['actual_storage_num'],
            'balance_num_litre' => $stockin_info['actual_storage_num'] / $stockin_info['outbound_density'] * 1000,
            'storage_status' => 10,
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
        ];
        $status_stockin = $this->getModel('ErpStockIn')->saveStockIn(['id'=>$stockin_info['id']],$stock_in_data);

        /** --------入库单审核影响库存-start------- **/
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $stockin_info['goods_id'],
            'object_id' => $stockin_info['storehouse_id'],
            'stock_type' => $stockin_info['stock_type'],
            'region' => $stockin_info['region'],
            'our_company_id' => $stockin_info['our_company_id'],
        ];
        $stock_info = $this->getStockInfo($stock_where);
        //获取入库单改变物理库存之前的物理库存 edit xiaowen
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $stockin_info['goods_id'],
            'object_id' => $stockin_info['storehouse_id'],
            'stock_type' => $stockin_info['stock_type'],
            'region' => $stockin_info['region'],
            'stock_num' => $stock_info['stock_num'] + $stockin_info['actual_storage_num'],
        ];
        //$stock_info['transportation_num'] = $data['transportation_num']; //重置最新的库存信息
        $stock_info['stock_num'] = $data['stock_num'];

        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stockin_info['storage_code'],
            'object_type' => 4,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $stock_status = $this->saveStockInfo($data, $stockin_info['actual_storage_num'], $orders);
        //log_info("增加库存SQL：". $this->getModel('ErpStock')->getLastSql());
        //log_info("入库单状态：". $status_stockin);
        //log_info("库存状态：". $stock_status);
        /** --------入库单审核影响库存-start------- **/

        // =======edit xiaowen 2019-2-25 盘点入库更新批次数量============================
        if($stockin_info['actual_storage_num'] > 0 && $stockin_info['batch_id'] > 0){
            $batch_change_data = [
                'batch_id' => $stockin_info['batch_id'],
                'change_balance_num' => $stockin_info['actual_storage_num'],
                'change_type' => 1,
                'change_number' => $stockin_info['storage_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
            if($batch_result['status'] != 1){
                M()->rollback();
                return $batch_result;
            }
        }
        //==========end 批次处理==========================================================

        if ($status_stockin && $stock_status && $batch_result['status'] == 1) {
            //计算加权成本 edit xiaowen 2018-2-7
            $stockin_info['before_stock_num'] = $beforeNum;
            $stockin_info['stock_id'] = $stockId ? $stockId : 0;
            $stockin_info['change_num'] = $stockin_info['actual_storage_num'];
            //重新计算加权成本
            updateStockInCost($stockin_info);
            $status = true;
            $message = '操作成功';
        } else {
            $status = false;
            $message = '操作失败';
        }
        return ['status' => $status, 'message' => $message];
    }

    /**
     * 导入成本期初数据
     * @param $data
     * @author guanyu
     * @time 2018-02-26
     */
    public function importCostInfo($data)
    {
        if($data){

            $stock_info = D('ErpStock')->where(['status' => 1])->getField('id, goods_id, object_id, stock_type, region, facilitator_id, our_company_id');

            $i = 1;
            $k = 0;
            $cost_data = [];
            $status_all = true;
            M()->startTrans();
            $erp_company = [
                1 => 3372,
                2 => 70,
                3 => 22,
                4 => 18,
                5 => 12664,
            ];
            foreach($data as $key => $cost){
                $our_company = $erp_company[$key];
                foreach ($cost as $value) {
                    if(trim($value[0]) && trim($value[6])){
                        $value[0] = trim($value[0]);
                        //----------------------组装数据------------------------------------------------------------------
                        $cost_data[$k]['stock_id']         = $stock_info[$value[0]]['id'];
                        $cost_data[$k]['goods_id']         = $stock_info[$value[0]]['goods_id'];
                        $cost_data[$k]['object_id']        = $stock_info[$value[0]]['object_id'];
                        $cost_data[$k]['stock_type']       = $stock_info[$value[0]]['stock_type'];
                        $cost_data[$k]['region']           = $stock_info[$value[0]]['region'];
                        $cost_data[$k]['facilitator_id']   = $stock_info[$value[0]]['facilitator_id'];
                        $cost_data[$k]['our_company_id']   = $our_company;
                        $cost_data[$k]['price']            = trim($value[6]) * 10000;
                        $cost_data[$k]['gmt_create']       = currentTime();
                        $cost_data[$k]['gmt_modified']     = currentTime();

                        $cost_status = D('CostInfo')->add($cost_data[$k]);
                        $status_all = $status_all && $cost_status ? true :false;
                        //------------------------------------------------------------------------------------------------
                        echo '第'. $i . '条数据已处理！<br/>';
                    }else{
                        echo trim($value[0]) . '&&' . trim($value[6]);
                        echo '<br/>';
                        echo '第'. $i . '条数据导入有误，（id - 成本） 必须填写，请重新导入！';
                        exit;
                    }
                    $i++;
                    $k++;
                }
            }

            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }
    /*
     * @params:
     *  where：查询条件
     * @return:
     * @desc:根据条件获取数据和
     * @author:小黑
     * @time：2019-2-21
     */
    public function stockOutCoutn($where){
        return $this->getModel("ErpStockOut")->stockOutCount($where) ;
    }
    /*
     * @params:
     *  where：查询条件
     * @return:
     * @desc:根据条件获取数据和
     * @author:小黑
     * @time：2019-2-21
     */
    public function getStcokInLists($field , $where){
        return $this->getModel("ErpStockIn")->field($field)->where($where)->select() ;
    }
    /**
     * 入库审核生成批次数据
     * @author xiaowen
     * @time 2019-2-20
     * @param $stockin_info
     * @return array
     */
    public function AuditStockInCreateBatch($stockin_info){
        //入库单审核生成批次数据 xiaowen 2019-2-19
        $batch_result = $this->getEvent('ErpBatch', MODULE_NAME)->addBatch($stockin_info);
//        if($batch_result['status'] == 1){
//            $this->getModel('ErpStockIn')->where(['id'=>$stockin_info['id']])->save(['batch_sys_bn'=>$batch_result['data'], 'batch_id'=>$batch_result['data']['id']]);
//        }
        if($batch_result['status'] != 1){
            return  [
                'status' => 104,
                'message' => '批次生成失败，请联系管理员',
            ];
        }
    }

    /**
     * 代采销退入库单审核处理方法
     * @param $order_info 代采销退单信息
     * @param $stockin_info 销退入库单信息
     * @param $purchase_order_info 代采采购单信息
     * @return boolean
     */
    protected function agentRsStockInAudit($order_info, $stockin_info, $purchase_order_info){

        //判断是否已生成代采采购退货单
        $returned_order_info = $this->getModel('ErpReturnedOrder')->where(['order_type'=>2, 'source_order_number'=> $purchase_order_info['order_number']])->find();

        if(empty($returned_order_info)){
            //自动生成代采采购单的退货单
            $purchase_return_data = [
                'order_number' => erpCodeNumber(11)['order_number'],
                'order_type' => 2,
                'source_order_id' => $purchase_order_info['id'],
                'source_order_number' => $purchase_order_info['order_number'],
                'our_company_id' => session('erp_company_id'),
                'order_time' => currentTime('Y-m-d'),
                'return_goods_time' => $order_info['return_goods_time'],
                'return_goods_num' => $order_info['return_goods_num'],
                'return_price' => $purchase_order_info['price'],
                'return_type' => $order_info['return_type'],
                'order_status' => 10,
                'refund_remark' => $order_info['refund_remark'],
                'create_time' => currentTime(),
                'creater_id' => $this->getUserInfo('id'),
                'region' => $order_info['region'],
            ];
            $purchase_return_status = $returned_order_id = $this->getModel('ErpReturnedOrder')->addReturnedOrder($purchase_return_data);
            $returned_order_number = $purchase_return_data['order_number'];
            //生成退货单log
            $log_data = [
                'return_order_id' => $returned_order_id,
                'return_order_number' => $purchase_return_data['order_number'],
                'return_order_type' => 2,
                'log_info' => serialize($purchase_return_data),
                'log_type' => 1,
            ];
            $purchase_return_log_status = $this->getEvent('ErpReturned')->addReturnedLog($log_data);
        }else{
            $purchase_return_status = true;
            $purchase_return_log_status = true;
            $returned_order_id = $returned_order_info['id'];
            $returned_order_number = $returned_order_info['order_number'];
        }

        //更新原代采采购单信息
        $order_update_data  = [
            'is_returned' => 1,
            //'returned_goods_num' => $purchase_return_data['return_goods_num'],
            'returned_goods_num' => $purchase_order_info['returned_goods_num'] + $stockin_info['actual_outbound_num'], //代采采购单 退货数量因批次改为累加
            'update_time' => currentTime()
        ];
        $purchase_status = $this->getModel('ErpPurchaseOrder')->where(['id'=>$purchase_order_info['id']])->save($order_update_data);

        //生成采购退货单对应的出库单
        $stock_out_data = [
            'outbound_code' => erpCodeNumber(7)['order_number'],
            'outbound_type' => 3,
            'outbound_status' => 10,
            'outbound_remark' => '',
            'source_number' => $returned_order_number,
            'source_object_id' => $returned_order_id,
            'our_company_id' => session('erp_company_id'),
            'goods_id' => $purchase_order_info['goods_id'],
            'depot_id' => $purchase_order_info['depot_id'],
            'outbound_num' => $stockin_info['storage_num'],
            //'actual_outbound_num' => $purchase_return_data['return_goods_num'] - ($purchase_order_info['goods_num'] - $purchase_order_info['storage_quantity']),
            'actual_outbound_num' => $stockin_info['actual_storage_num'],
            'create_time' => currentTime(),
            'creater_id' => $this->getUserInfo('id'),
            'creater_name' => $this->getUserInfo('dealer_name'),
            'auditor_id' => $this->getUserInfo('id'),
            'audit_time' => currentTime(),
            'dealer_id' => $this->getUserInfo('id'),
            'dealer_name' => $this->getUserInfo('dealer_name'),
            'storehouse_id' => $purchase_order_info['storehouse_id'],
            //'stock_type' => $purchase_order_info['type'] == 1 ? getAllocationStockType($purchase_order_info['storehouse_id']) : 2,
            'stock_type' => getAllocationStockType($purchase_order_info['storehouse_id']),
            'region' => $purchase_order_info['region'],
            'batch_id' => $stockin_info['batch_id'],
            'batch_sys_bn' => $stockin_info['batch_sys_bn'],
        ];
        //通过java接口获取出库单成本 edit xiaowen 2018-2-4
        $stock_out_cost = getStockOutCost($stock_out_data);
        //如果成本值为空，则return edit qianbin 2018-03-09
        if($stock_out_cost['price']=== null){
            return  [
                'status' => false,
                'message' => '成本获取失败，请联系管理员！',
            ];
        }
        $stock_out_data['cost'] = $stock_out_cost['price'] ? $stock_out_cost['price'] : 0;
        $stock_out_data['cost_log_id'] = $stock_out_cost['logId']  ? $stock_out_cost['logId'] : 0;

        $stock_out_status = $this->getModel('ErpStockOut')->addStockOut($stock_out_data);

        /** --------入库单审核影响库存-start------- **/
        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $purchase_order_info['goods_id'],
            'object_id' => $purchase_order_info['storehouse_id'],
            'stock_type' => $purchase_order_info['type'] == 1 ? getAllocationStockType($purchase_order_info['storehouse_id']) : 2,
        ];
        $stock_info = $this->getStockInfo($stock_where);
        //保存变动物理库存之前的物理数量 eidt xiaowen
//        $beforeNum = $stock_info['stock_num'];
//        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        $data = [
            'goods_id' => $purchase_order_info['goods_id'],
            'object_id' => $purchase_order_info['storehouse_id'],
            'stock_type' => $purchase_order_info['type'] == 1 ? getAllocationStockType($purchase_order_info['storehouse_id']) : 2,
            'region' => $purchase_order_info['region'],
            'transportation_num' => $stock_info['transportation_num'] - ($stock_out_data['outbound_num'] - $stock_out_data['actual_outbound_num']),
            'stock_num' => $stock_info['stock_num'] - $stock_out_data['actual_outbound_num'],
        ];

        $stock_info['sale_wait_num'] = $data['sale_wait_num']; //重置最新的库存信息
        $stock_info['stock_num'] = $data['stock_num'];

        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $stock_out_data['outbound_code'],
            'object_type' => 3,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $purchase_stock_status = $this->saveStockInfo($data, $stockin_info['actual_storage_num'], $orders);
        /** --------入库单审核影响库存-end------- **/

        // =======edit xiaowen 2019-2-25 代采采退出库单更新批次数量============================
        if($stock_out_data['actual_outbound_num'] > 0 && $stock_out_data['batch_id'] > 0){
            $batch_change_data = [
                'batch_id' => $stock_out_data['batch_id'],
                'change_balance_num' => plusConvert($stock_out_data['actual_outbound_num']),
                'change_type' => 2,
                'change_number' => $stock_out_data['outbound_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch')->commonChangeBatchNum($batch_change_data);
        }else{
            $batch_result['status'] = 1;
        }

        //==========end 批次处理==================================================================
        log_info("代采采购退货处理状态：" . $purchase_return_status);
        log_info("代采采购处理状态：" . $purchase_status);
        log_info("代采采购退货出库处理状态：" . $stock_out_status);
        log_info("代采采购退货库存处理状态：" . $purchase_stock_status);
        log_info("代采采购退货批次处理状态：" . $batch_result['status']);
        if ($purchase_return_status && $purchase_return_log_status && $purchase_status && $stock_out_status && $purchase_stock_status && $batch_result['status'] == 1) {
            $purchase_order_status = true;
        } else {
            $purchase_order_status = false;
        }

        return $purchase_order_status;

    }

    /*
     * 生成出库单申请单和出库单集合
     */
    public function addStockOutAll($data)
    {
        //获取订单信息
        $orderWhere = [
            "order_number" => $data['orderNumber'],
            "order_status" => 10,
            "is_void" => 2,
            "is_returned" => 2
        ];
        //销售单查询
        $orderInfo = $this->getModel("ErpSaleOrder")->findSaleOrder($orderWhere);
        $stockType = getAllocationStockType($orderInfo['storehouse_id']);
        if (empty($orderInfo)) {
            return [
                "status" => 1004,
                "message" => "订单存在异常信息，请联系管理员"
            ];
        }
        if($orderInfo['company_id'] != $data['companyId']){
            return ["status" => 1004 , "message" => "订单信息不匹配"];
        }
        if (!self::checkOrderCanOutStock($orderInfo)) {
            return ["status" => 1012, "message" => "销售订单不可生成出库单"];
        }
        //批次数量判断
        $outNum = 0;
        $batchIds = array_column($data['number'], "batchId");
        $whereBatch = [
            "id" => ['in', $batchIds],
            "status" => ['in', [1, 2]],
            "goods_id" => $orderInfo['goods_id'],
            "storehouse_id" => $orderInfo['storehouse_id'],
            "our_company_id" => $orderInfo['our_company_id'],
            "stock_type" => $stockType
//            "region" => $orderInfo['region']
        ];
        $field = "id ,sys_bn, (balance_num-reserve_num) , cargo_bn_id";
        $batchInfos = $batchInfo = $this->getModel("ErpBatch")->getFieldList($field, $whereBatch);
        foreach ($data['number'] as $value) {
            $number = setNum($value['number']);
            $outNum += $number;
            $key = $value['batchId'];
            if (!isset($batchInfo[$key]) || ($batchInfo[$key] < $number)) {
                return [
                    "status" => 1005,
                    "message" => "批次数量不满足出库"
                ];
            }
        }
        //申请出库总数
        if (empty($outNum)) {
            return [
                "status" => 1006,
                "message" => "申请出库数量不正确"
            ];
        }
        $field = "id ,sys_bn";
        $batchInfo = $this->getModel("ErpBatch")->getFieldList($field, $whereBatch);
        //获取外部货权号信息
        $cargoField = "id , cargo_bn" ;
        $cargoIds = array_unique(array_column($batchInfos , "cargo_bn_id"));
        $cargoWhere = [
            "id" => ['in' , $cargoIds]
        ];
        $cargo_bn_arr = $this->getEvent("CargoBn")->getFields($cargoField , $cargoWhere);

        //销售单可以生成出库单进行判断
//        $useNumber = $this->getEvent("ErpStockOutApply")->getApplyNum($orderInfo);
        $useNumber = $this->getEvent("ErpStockOutApply")->getStockOutApplyNum($orderInfo);
        if ($useNumber <= 0 || $useNumber < $outNum) {
            return [
                "status" => 1007,
                "message" => "销售订单可用数量不满足出库"
            ];
        }
        $addTime = date("Y-m-d H:i:s");
        //出库单申请
        $orderApplyNumber = erpCodeNumber(21, '', $orderInfo['our_company_id'])['order_number'];
        $stockOutApplyData = [
            "outbound_apply_code" => $orderApplyNumber,
            "outbound_apply_type" => $data['order_type'],
            "status" => 10 ,
            "source_number" => $orderInfo['order_number'],
            "source_object_id" => $orderInfo['id'],
            "remark" => $data["remark"] ,
            "our_company_id" => $orderInfo['our_company_id'],
            "goods_id" => $orderInfo['goods_id'],
            "depot_id" => $orderInfo['depot_id'],
            "outbound_apply_num" => $outNum,
            "create_time" => date("Y-m-d H:i:s"),
            "creater_id" => $data['creater_id'],
            "creater_name" => $data['creater_name'],
            "storehouse_id" => $orderInfo['storehouse_id'],
            "stock_type" => $stockType,
            "region" => $orderInfo['region'],
            "data_source" => $data['data_source'],
            "is_shipping" => 2
        ];

        //出库单
        foreach ($data['number'] as $value) {
            $stockOutNumber = setNum($value['number']);
            $orderOutOrderNumber = erpCodeNumber(7,'', $orderInfo['our_company_id'])['order_number'];
            $order_info[] = [
                "outbound_code" => $orderOutOrderNumber,
                "outbound_type" => $data['order_type'],
                "outbound_remark" => $data['remark'],
                "source_number" => $orderInfo['order_number'],
                "source_object_id" => $orderInfo['id'],
                "our_company_id" => $orderInfo['our_company_id'],
                "goods_id" => $orderInfo['goods_id'],
                "depot_id" => $orderInfo['depot_id'],
                'outbound_num' => $stockOutNumber,
                'actual_outbound_num' => $stockOutNumber,
                'create_time' => $addTime,
                "update_time" => $addTime,
                "creater_id" => $data['creater_id'],
                "creater_name" => $data['creater_name'],
                'storehouse_id' => $orderInfo['storehouse_id'],
                'stock_type' => $stockType,
                'region' => $orderInfo['region'],
                "batch_sys_bn" => $batchInfo[$value['batchId']],
                "batch_id" => $value['batchId'],
                "source_apply_number" => $orderApplyNumber,
                "attachment" => $data['attachmentUrl'] ,
            ];
            $updateBatch[] = [
                "batch_id" => $value['batchId'],
                "change_balance_num" => 0,
                "change_reserve_num" => $stockOutNumber,
                "change_type" => 2,
                //"change_number" => $batchInfo[$key]
                "change_number" => $orderOutOrderNumber
            ];

            $erpThreeData[] = [ //入库单和第三方订单的关系
                "erp_number" => $orderOutOrderNumber,
                "source_number" => $data['dispatchList'],
                "type" => 3,
                "add_user_id" => $data['userId'],
                "add_time" => $addTime,
                "update_user_id" => $data['creater_id'],
                "update_time" => $addTime
            ];
            //出库单数据
            $outbountList[] = [
                "batchId" => (int)$value['batchId'],
                "outboundOrder" => $orderOutOrderNumber ,
                "batchNumber" => $batchInfo[$value['batchId']],
                "cargoBn" => $cargo_bn_arr[$batchInfos[$value['batchId']]['cargo_bn_id']] ,
                "number" => $value['number']
            ];
        }
        if(empty($stockOutApplyData) || empty($order_info)
            || empty($updateBatch) || empty($erpThreeData)){
            return  ['status' => 1013, 'message' => '数据信息错误，请重新提交'];
        }
        M()->startTrans();
        //生成出库单
        if (!$this->getModel("ErpStockOutApply")->addApply($stockOutApplyData)) {
            M()->rollback();
            return [
                "status" => 1008,
                "message" => "生成出库申请单失败,请重新操作"
            ];
        }
        //生成出库单
        if(!$this->getModel("ErpStockOut")->addStockOutAll($order_info)){
            M()->rollback();
            return [
                "status" => 1009,
                "message" => "生成出库单失败,请重新操作"
            ];
        }
        //修改批次数据
        foreach($updateBatch as $value){
            if($this->getEvent("ErpBatch")->commonChangeBatchNum($value)['status'] != 1){
                M()->rollback();
                return  ['status' => 1010, 'message' => '占用批次信息不正确',"data"=> $value];
            }
        }
        //添加第三方数据信息
        if(!$this->getModel("ErpThree")->addAllData($erpThreeData)){
            M()->rollback();
            return  ['status' => 1011, 'message' => '报错信息错误'];
        }
        M()->commit();
        $returnData = [
            "applyOrderNum" => $orderApplyNumber ,
            "outboundList" => $outbountList ,
        ];
        return $returnData ;
    }
    /*
     * @params:
     *  $data:修改的信息
     *  $where:修改的条件
     * @return:array
     * @desc:修改出库单表的信息
     * @auth:小黑
     * @time:2019-3-13
     */
    public function updateStockOutInfo($data , $where){
        if(empty($data) || empty($where)){
            return ['status'=> 2 , "message"=> "传递数据异常"] ;
        }
        if(!$this->getModel("ErpStockOut")->saveStockOut($where, $data)){
            return ['status'=>3 , "message"=> "修改数据失败"] ;
        }
        return ['status'=> 1 , "message"=> "成功"] ;
    }
    /*
     * @params:
     *     $filed:查找的字段
     *     $where:条件
     *     $group:
     * @retrun: array
     * @desc:出库单getField的方法
     * $auth:小黑
     * @time:20193-14
     */
    public function stockOutGetField($field , $where , $group){
        if(empty($field) || empty($where)){
            return ['status' => 2 , "message" => "参数信息不正确"] ;
        }
        return $this->getModel("ErpStockOut")->stockOutGetField($where , $field , $group);
    }
}


