<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class IndexController extends BaseController
{

    private  $_ZHAOYOUWANG_PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDL1IVsrplq21DPMugSWJme+3UyZd
QOe6SChWbcStd3czbz1myfhkeT18OoinzGXkobJsAxW6IlnIiZ91mm394IfyK5nKrQ
Nj0babFK6zd7tbyXD1FKZ27EitJMmL/rIo+/oo+fik4dT+1QRmrcldcFzkgcrky2jb
lOmAack11RwwIDAQAB
-----END PUBLIC KEY-----';
    /*
     * ---------------------------------------------------
     * 公钥加密
     * Author:ypm
     * ---------------------------------------------------
     */
    private function pu_encrypt($originalData){
        $crypto = '';

        foreach(str_split($originalData, 117) as $chunk){
            openssl_public_encrypt($chunk, $encryptData, $this->_ZHAOYOUWANG_PUBLIC_KEY);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }
    public function index()
    {

        //dump($this->roles);die;
        $data = D('Role')->getRoleNodeList($this->roles, 1);
        //dump($data);die;
        $arr = GetTree($data);
        $dealer_name = $this->user_name ? $this->user_name : '';
        //获取所有子公司
        $erpCompany = getAllErpCompany();
        $this->assign('erpCompany', $erpCompany);
        $this->assign('selectedErpCompany', session('erp_company_id'));
        $this->assign('dealer_name', $dealer_name);
        $this->assign('menu', $arr);
        //var_dump($arr);die;
        $this->display();
    }

    public function welcome()
    {
        $this->display();
    }

    public function fixAllocationOrder() {
//        $allocation_order = $this->getModel('ErpAllocationOrder')->where(['_string'=>'num <> actual_out_num','status'=>10])->select();
//        foreach ($allocation_order as $value) {
//            $change_num = $value['num'] - $value['actual_out_num'];
//            if ($value['out_storehouse']) {
//                $status = $this->getModel('ErpStock')->where(['our_company_id'=>$value['our_company_id'],'goods_id'=>$value['goods_id'],'object_id'=>$value['out_storehouse'],'stock_type'=>1])->setInc('allocation_reserve_num',$change_num);
//                log_info("调拨单号：".$value['order_number']."，账套：".$value['our_company_id']."，商品ID：".$value['goods_id']."，仓库ID：".$value['out_storehouse']."，变动数量：".$change_num."，执行状态：".$status);
//            } elseif ($value['out_facilitator_id']) {
//                $status = $this->getModel('ErpStock')->where(['our_company_id'=>$value['our_company_id'],'goods_id'=>$value['goods_id'],'object_id'=>$value['out_facilitator_id'],'stock_type'=>3])->setInc('allocation_reserve_num',$change_num);
//                log_info("调拨单号：".$value['order_number']."，账套：".$value['our_company_id']."，商品ID：".$value['goods_id']."，服务商ID：".$value['out_facilitator_id']."，变动数量：".$change_num."，执行状态：".$status);
//            }
//        }
    }

    //盘点库存
    public function stocktaking () {

        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');

        if (IS_AJAX) {

            set_time_limit(0);

            $is_agent = I('get.is_agent',2);

            if ($is_agent == 2) {
                Vendor('PHPExcel.Classes.PHPExcel');
                Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
                Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
                $filePath1 = './stock_data_one.xlsx';
                $filePath2 = './stock_data_one.xls';
                $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
                if(is_file($filePath)){
                    $PHPReader = new \PHPExcel_Reader_Excel2007();
                    if (!$PHPReader->canRead($filePath)) {
                        $PHPReader = new \PHPExcel_Reader_Excel5();
                        if (!$PHPReader->canRead($filePath)) {
                            return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                        }
                    }
                    $PHPExcel = $PHPReader->load($filePath);
                    # @读取excel文件中的第一个工作表
                    $currentSheet = $PHPExcel->getSheet(0);
                    $skid_data_one = $currentSheet->toArray();
                    unset($skid_data_one[0]);
                }else{
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }

                $filePath1 = './stock_data_two.xlsx';
                $filePath2 = './stock_data_two.xls';
                $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
                if(is_file($filePath)){
                    $PHPReader = new \PHPExcel_Reader_Excel2007();
                    if (!$PHPReader->canRead($filePath)) {
                        $PHPReader = new \PHPExcel_Reader_Excel5();
                        if (!$PHPReader->canRead($filePath)) {
                            return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                        }
                    }
                    $PHPExcel = $PHPReader->load($filePath);
                    # @读取excel文件中的第一个工作表
                    $currentSheet = $PHPExcel->getSheet(0);
                    $stock_data_two = $currentSheet->toArray();
                    unset($stock_data_two[0]);
                }else{
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }

                $filePath1 = './stock_data_three.xlsx';
                $filePath2 = './stock_data_three.xls';
                $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
                if(is_file($filePath)){
                    $PHPReader = new \PHPExcel_Reader_Excel2007();
                    if (!$PHPReader->canRead($filePath)) {
                        $PHPReader = new \PHPExcel_Reader_Excel5();
                        if (!$PHPReader->canRead($filePath)) {
                            return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                        }
                    }
                    $PHPExcel = $PHPReader->load($filePath);
                    # @读取excel文件中的第一个工作表
                    $currentSheet = $PHPExcel->getSheet(0);
                    $stock_data_three = $currentSheet->toArray();
                    unset($stock_data_three[0]);
                }else{
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }

                foreach ($skid_data_one as $value) {
                    if ($value[2]) {
                        $skid_data_first[trim($value[2]).trim($value[3])] = $value;
                    }
                }
                foreach ($stock_data_two as $value) {
                    if ($value[2]) {
                        $skid_data_second[trim($value[2]).trim($value[3])] = $value;
                    }
                }
                foreach ($stock_data_three as $value) {
                    if ($value[2]) {
                        $skid_data_third[trim($value[2]).trim($value[3])] = $value;
                    }
                }
            }

            $where = [
                'stock_type' => $is_agent == 1 ? 2 : 1,
            ];
            $param = I('get.',0);
            if ($param['search_company']) {
                $where['s.our_company_id'] = $param['search_company'];
            }
            $where['s.region'] = ['neq', 1877]; //库存核对不包含江阴库存 edit xiaowen 2017-8-4
            $field = 's.*, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,es.storehouse_name,rg.available_sale_stock, rg.available_use_stock';
            $data = $this->getModel('ErpStock')->stocktaking($where,$field);
            $getAllRegion = provinceCityZone()['city'];

            foreach($data['data'] as $key=>$value){
                if ($is_agent == 2) {
                    if ($skid_data_first[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $skid_data_first[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                        $data['data'][$key]['first_stock'] = $skid_data_first[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                    } elseif ($skid_data_second[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $skid_data_second[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                        $data['data'][$key]['first_stock'] = $skid_data_second[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                    } else {
                        $data['data'][$key]['first_stock'] = 0;
                    }
                    if ($skid_data_third[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $skid_data_third[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                        $data['data'][$key]['first_stock'] += $skid_data_third[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                    }
                }
                $data['data'][$key]['our_company_name'] = $erp_company[$value['our_company_id']];
                $data['data'][$key]['region_name'] = $getAllRegion[$value['region']];
                $data['data'][$key]['stock_type'] = stockType($value['stock_type']);
                $data['data'][$key]['stock_num'] = getNum(round($value['stock_num']));
                $data['data'][$key]['transportation_num'] = getNum(round($value['transportation_num']));
                $data['data'][$key]['sale_reserve_num'] = getNum(round($value['sale_reserve_num']));
                $data['data'][$key]['allocation_reserve_num'] = getNum(round($value['allocation_reserve_num']));
                $data['data'][$key]['sale_wait_num'] = getNum(round($value['sale_wait_num']));
                $data['data'][$key]['allocation_wait_num'] = getNum(round($value['allocation_wait_num']));
                $data['data'][$key]['available_num'] = getNum(round($value['available_num']));
                $data['data'][$key]['current_available_sale_num'] = getNum(round($value['available_sale_stock'] + $value['available_num'] - $value['available_use_stock'])); //当前可售库存
                $data['data'][$key]['object_name'] = $data['data'][$key]['storehouse_name'];

                //统计销售预留
                $where = [
                    'order_type'=>1,
                    'region'=>$value['region'],
                    'goods_id'=>$value['goods_id'],
                    'our_company_id'=>$value['our_company_id'],
                    'storehouse_id'=>$value['object_id'],
                    'is_agent' => $is_agent,
                    '_string' => '((pay_type IN (1, 3) AND collection_status <> 10 AND order_status <> 2) OR (pay_type IN (2, 4) AND order_status NOT IN (2, 10)) OR (pay_type = 5 AND collection_status <> 10 AND order_status <> 2))'
                ];
                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['add_order_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }
                $inventory_sale_reserve_num = $this->getModel('ErpSaleOrder')->field('SUM(IF(pay_type = 5, buy_num - total_sale_wait_num, buy_num)) as total')->where($where)->find();
                $data['data'][$key]['inventory_sale_reserve_num'] = getNum($inventory_sale_reserve_num['total']);

                //统计销售待提
                $where = [
                    'order_type'=>1,
                    'region'=>$value['region'],
                    'goods_id'=>$value['goods_id'],
                    'our_company_id'=>$value['our_company_id'],
                    'storehouse_id'=>$value['object_id'],
                    'is_agent' => $is_agent,
                    '_string' => '((pay_type IN (1, 3) AND collection_status = 10 AND order_status <> 2) OR (pay_type IN (2, 4) AND order_status = 10) OR (pay_type = 5 AND collection_status > 3 AND order_status = 10))'
                ];
                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['add_order_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }
                $inventory_sale_wait_num = $this->getModel('ErpSaleOrder')->field('SUM(IF(is_returned = 1, 0, IF(pay_type = 5, total_sale_wait_num, buy_num) - outbound_quantity)) as total')->where($where)->find();
                $data['data'][$key]['inventory_sale_wait_num'] = getNum($inventory_sale_wait_num['total']);

                //统计采购在途
                $where = [
                    'type'=>$is_agent == 1 ? 2 : 1,
                    'region'=>$value['region'],
                    'goods_id'=>$value['goods_id'],
                    'our_buy_company_id'=>$value['our_company_id'],
                    'storehouse_id'=>$value['object_id'],
                    '_string' => '((pay_type IN (1, 4) AND pay_status = 10 AND order_status = 10) OR (pay_type = 2 AND pay_status > 2 AND order_status = 10) OR (pay_type = 3 AND order_status = 10) OR (pay_type = 5 AND pay_status > 3 AND order_status = 10))'
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['add_order_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $inventory_transportation_num = $this->getModel('ErpPurchaseOrder')->field('SUM(IF(is_returned = 1, 0, IF(pay_type = 5, total_purchase_wait_num, goods_num) - storage_quantity)) as total')->where($where)->find();
                $data['data'][$key]['inventory_transportation_num'] = getNum($inventory_transportation_num['total']);

                //统计配货预留
                $where = [
                    'out_region'=>$value['region'],
                    'goods_id'=>$value['goods_id'],
                    'our_company_id'=>$value['our_company_id'],
                    'out_storehouse'=>$value['object_id'],
                    '_string'=>'status NOT IN (2, 10)'
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $inventory_allocation_reserve_num = $this->getModel('ErpAllocationOrder')->field('SUM(num) as total')->where($where)->find();
                $data['data'][$key]['inventory_allocation_reserve_num'] = getNum($inventory_allocation_reserve_num['total']);

                //统计配货待提
                $where = [
                    'out_region'=>$value['region'],
                    'goods_id'=>$value['goods_id'],
                    'our_company_id'=>$value['our_company_id'],
                    'out_storehouse'=>$value['object_id'],
                    'status'=>10,
                    'outbound_status'=>2,
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $inventory_allocation_wait_num = $this->getModel('ErpAllocationOrder')->field('SUM(num) as total')->where($where)->find();
                $data['data'][$key]['inventory_allocation_wait_num'] = getNum($inventory_allocation_wait_num['total']);

                /** ----------------------------------------统计物理库存start---------------------------------------- */
                //统计采购入库
                $where = [
                    'si.our_company_id'=>$value['our_company_id'],
                    'si.goods_id'=>$value['goods_id'],
                    'si.storage_status'=>10,
                    'o.region'=>$value['region'],
                    'o.storehouse_id'=>$value['object_id'],
                    'o.type'=>$is_agent == 1 ? 2 : 1,
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['si.create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $purchase_stock_in_num = $this->getModel('ErpStockIn')
                    ->alias('si')
                    ->field('SUM(actual_storage_num) as total')
                    ->where($where)
                    ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')
                    ->find();

                //统计调拨入库
                $where = [
                    'si.our_company_id'=>$value['our_company_id'],
                    'si.goods_id'=>$value['goods_id'],
                    'si.storage_status'=>10,
                    'a.in_region'=>$value['region'],
                    'a.in_storehouse'=>$value['object_id'],
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['si.create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $allocation_stock_in_num = $this->getModel('ErpStockIn')
                    ->alias('si')
                    ->field('SUM(actual_storage_num) as total')
                    ->where($where)
                    ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                    ->find();

                //统计销售退货
                $where = [
                    'si.our_company_id'=>$value['our_company_id'],
                    'si.goods_id'=>$value['goods_id'],
                    'si.storage_status'=>10,
                    'o.region'=>$value['region'],
                    'o.storehouse_id'=>$value['object_id'],
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['si.create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $sale_return_num = $this->getModel('ErpStockIn')
                    ->alias('si')
                    ->field('SUM(actual_storage_num) as total')
                    ->where($where)
                    ->join('oil_erp_returned_order r on r.id = si.source_object_id and si.source_number = r.order_number', 'left')
                    ->join('oil_erp_sale_order o on o.id = r.source_order_id and r.source_order_number = o.order_number', 'left')
                    ->find();

                //统计销售出库
                $where = [
                    'so.our_company_id'=>$value['our_company_id'],
                    'so.goods_id'=>$value['goods_id'],
                    'so.outbound_status'=>10,
                    'o.region'=>$value['region'],
                    'o.storehouse_id'=>$value['object_id'],
                    'o.order_type'=>1,
                    'o.is_agent'=>$is_agent,
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['so.create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $sale_stock_out_num = $this->getModel('ErpStockOut')
                    ->alias('so')
                    ->field('SUM(actual_outbound_num) as total')
                    ->where($where)
                    ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number', 'left')
                    ->find();

                //统计调拨出库
                $where = [
                    'so.our_company_id'=>$value['our_company_id'],
                    'so.goods_id'=>$value['goods_id'],
                    'so.outbound_status'=>10,
                    'a.out_region'=>$value['region'],
                    'a.out_storehouse'=>$value['object_id'],
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['so.create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $allocation_stock_out_num = $this->getModel('ErpStockOut')
                    ->alias('so')
                    ->field('SUM(actual_outbound_num) as total')
                    ->where($where)
                    ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                    ->find();

                //统计采购退货
                $where = [
                    'so.our_company_id'=>$value['our_company_id'],
                    'so.goods_id'=>$value['goods_id'],
                    'so.outbound_status'=>10,
                    'o.region'=>$value['region'],
                    'o.storehouse_id'=>$value['object_id'],
                ];

                if (isset($param['start_time']) || isset($param['end_time'])) {
                    if (trim($param['start_time']) && !trim($param['end_time'])) {

                        $where['so.create_time'] = ['egt', trim($param['start_time'])];
                    } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                        $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                    } else if (trim($param['start_time']) && trim($param['end_time'])) {

                        $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                    }
                }

                $purchase_return_num = $this->getModel('ErpStockOut')
                    ->alias('so')
                    ->field('SUM(actual_outbound_num) as total')
                    ->where($where)
                    ->join('oil_erp_returned_order r on r.id = so.source_object_id and so.source_number = r.order_number', 'left')
                    ->join('oil_erp_purchase_order o on o.id = r.source_order_id and r.source_order_number = o.order_number', 'left')
                    ->find();

                $inventory_stock_num = $purchase_stock_in_num['total'] + $allocation_stock_in_num['total'] + $sale_return_num['total'] - $sale_stock_out_num['total'] - $allocation_stock_out_num['total'] - $purchase_return_num['total'];
                $data['data'][$key]['inventory_stock_num'] = getNum($inventory_stock_num);
                /** -----------------------------------------统计物理库存end----------------------------------------- */
            }
            $this->echoJson($data);
        }
        $this->assign('erp_company', $erp_company);
        $this->display();
    }

    //盘点代采库存
    public function agentstocktaking () {
        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');
        $this->assign('erp_company', $erp_company);
        $this->display();
    }

    //导出盘点库存
    public function exportstocktaking ()
    {
        set_time_limit(0);

        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');

        $is_agent = I('get.is_agent',2);

        if ($is_agent == 2) {
            Vendor('PHPExcel.Classes.PHPExcel');
            Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
            Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
            $filePath1 = './stock_data_one.xlsx';
            $filePath2 = './stock_data_one.xls';
            $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
            if(is_file($filePath)){
                $PHPReader = new \PHPExcel_Reader_Excel2007();
                if (!$PHPReader->canRead($filePath)) {
                    $PHPReader = new \PHPExcel_Reader_Excel5();
                    if (!$PHPReader->canRead($filePath)) {
                        return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                    }
                }
                $PHPExcel = $PHPReader->load($filePath);
                # @读取excel文件中的第一个工作表
                $currentSheet = $PHPExcel->getSheet(0);
                $skid_data_one = $currentSheet->toArray();
                unset($skid_data_one[0]);
            }else{
                return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
            }

            $filePath1 = './stock_data_two.xlsx';
            $filePath2 = './stock_data_two.xls';
            $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
            if(is_file($filePath)){
                $PHPReader = new \PHPExcel_Reader_Excel2007();
                if (!$PHPReader->canRead($filePath)) {
                    $PHPReader = new \PHPExcel_Reader_Excel5();
                    if (!$PHPReader->canRead($filePath)) {
                        return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                    }
                }
                $PHPExcel = $PHPReader->load($filePath);
                # @读取excel文件中的第一个工作表
                $currentSheet = $PHPExcel->getSheet(0);
                $stock_data_two = $currentSheet->toArray();
                unset($stock_data_two[0]);
            }else{
                return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
            }

            $filePath1 = './stock_data_three.xlsx';
            $filePath2 = './stock_data_three.xls';
            $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
            if(is_file($filePath)){
                $PHPReader = new \PHPExcel_Reader_Excel2007();
                if (!$PHPReader->canRead($filePath)) {
                    $PHPReader = new \PHPExcel_Reader_Excel5();
                    if (!$PHPReader->canRead($filePath)) {
                        return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                    }
                }
                $PHPExcel = $PHPReader->load($filePath);
                # @读取excel文件中的第一个工作表
                $currentSheet = $PHPExcel->getSheet(0);
                $stock_data_three = $currentSheet->toArray();
                unset($stock_data_three[0]);
            }else{
                return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
            }

            foreach ($skid_data_one as $value) {
                if ($value[2]) {
                    $skid_data_first[trim($value[2]).trim($value[3])] = $value;
                }
            }
            foreach ($stock_data_two as $value) {
                if ($value[2]) {
                    $skid_data_second[trim($value[2]).trim($value[3])] = $value;
                }
            }
            foreach ($stock_data_three as $value) {
                if ($value[2]) {
                    $skid_data_third[trim($value[2]).trim($value[3])] = $value;
                }
            }
        }

        $where = [
            'stock_type' => $is_agent == 1 ? 2 : 1,
        ];
        $param = I('get.',0);
        if ($param['search_company']) {
            $where['s.our_company_id'] = $param['search_company'];
        }
        $where['s.region'] = ['neq', 1877]; //库存核对不包含江阴库存 edit xiaowen 2017-8-4
        $field = 's.*, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,es.storehouse_name,rg.available_sale_stock, rg.available_use_stock';
        $data = $this->getModel('ErpStock')->stocktaking($where,$field);
        $getAllRegion = provinceCityZone()['city'];

        $num = 0;
        foreach($data['data'] as $key=>$value){
            if ($is_agent == 2) {
                if ($skid_data_first[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $skid_data_first[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                    $data['data'][$key]['first_stock'] = $skid_data_first[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                } elseif ($skid_data_second[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $skid_data_second[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                    $data['data'][$key]['first_stock'] = $skid_data_second[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                } else {
                    $data['data'][$key]['first_stock'] = 0;
                }
                if ($skid_data_third[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $skid_data_third[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                    $data['data'][$key]['first_stock'] += $skid_data_third[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                }
            }
            $data['data'][$key]['our_company_name'] = $erp_company[$value['our_company_id']];
            $data['data'][$key]['region_name'] = $getAllRegion[$value['region']];
            $data['data'][$key]['stock_type'] = stockType($value['stock_type']);
            $data['data'][$key]['stock_num'] = getNum(round($value['stock_num']));
            $data['data'][$key]['transportation_num'] = getNum(round($value['transportation_num']));
            $data['data'][$key]['sale_reserve_num'] = getNum(round($value['sale_reserve_num']));
            $data['data'][$key]['allocation_reserve_num'] = getNum(round($value['allocation_reserve_num']));
            $data['data'][$key]['sale_wait_num'] = getNum(round($value['sale_wait_num']));
            $data['data'][$key]['allocation_wait_num'] = getNum(round($value['allocation_wait_num']));
            $data['data'][$key]['available_num'] = getNum(round($value['available_num']));
            $data['data'][$key]['current_available_sale_num'] = getNum(round($value['available_sale_stock'] + $value['available_num'] - $value['available_use_stock'])); //当前可售库存
            $data['data'][$key]['object_name'] = $data['data'][$key]['storehouse_name'];

            //统计销售预留
            $where = [
                'order_type'=>1,
                'region'=>$value['region'],
                'goods_id'=>$value['goods_id'],
                'our_company_id'=>$value['our_company_id'],
                'storehouse_id'=>$value['object_id'],
                'is_agent' => $is_agent,
                '_string' => '((pay_type IN (1, 3) AND collection_status <> 10 AND order_status <> 2) OR (pay_type IN (2, 4) AND order_status NOT IN (2, 10)) OR (pay_type = 5 AND collection_status <> 10 AND order_status <> 2))'
            ];
            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['add_order_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }
            $inventory_sale_reserve_num = $this->getModel('ErpSaleOrder')->field('SUM(IF(pay_type = 5, buy_num - total_sale_wait_num, buy_num)) as total')->where($where)->find();
            $data['data'][$key]['inventory_sale_reserve_num'] = getNum($inventory_sale_reserve_num['total']);

            //统计销售待提
            $where = [
                'order_type'=>1,
                'region'=>$value['region'],
                'goods_id'=>$value['goods_id'],
                'our_company_id'=>$value['our_company_id'],
                'storehouse_id'=>$value['object_id'],
                'is_agent' => $is_agent,
                '_string' => '((pay_type IN (1, 3) AND collection_status = 10 AND order_status <> 2) OR (pay_type IN (2, 4) AND order_status = 10) OR (pay_type = 5 AND collection_status > 3 AND order_status = 10))'
            ];
            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['add_order_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }
            $inventory_sale_wait_num = $this->getModel('ErpSaleOrder')->field('SUM(IF(is_returned = 1, 0, IF(pay_type = 5, total_sale_wait_num, buy_num) - outbound_quantity)) as total')->where($where)->find();
            $data['data'][$key]['inventory_sale_wait_num'] = getNum($inventory_sale_wait_num['total']);

            //统计采购在途
            $where = [
                'type'=>$is_agent == 1 ? 2 : 1,
                'region'=>$value['region'],
                'goods_id'=>$value['goods_id'],
                'our_buy_company_id'=>$value['our_company_id'],
                'storehouse_id'=>$value['object_id'],
                '_string' => '((pay_type IN (1, 4) AND pay_status = 10 AND order_status = 10) OR (pay_type = 2 AND pay_status > 2 AND order_status = 10) OR (pay_type = 3 AND order_status = 10) OR (pay_type = 5 AND pay_status > 3 AND order_status = 10))'
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['add_order_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['add_order_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $inventory_transportation_num = $this->getModel('ErpPurchaseOrder')->field('SUM(IF(is_returned = 1, 0, IF(pay_type = 5, total_purchase_wait_num, goods_num) - storage_quantity)) as total')->where($where)->find();
            $data['data'][$key]['inventory_transportation_num'] = getNum($inventory_transportation_num['total']);

            //统计配货预留
            $where = [
                'out_region'=>$value['region'],
                'goods_id'=>$value['goods_id'],
                'our_company_id'=>$value['our_company_id'],
                'out_storehouse'=>$value['object_id'],
                '_string'=>'status NOT IN (2, 10)'
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $inventory_allocation_reserve_num = $this->getModel('ErpAllocationOrder')->field('SUM(num) as total')->where($where)->find();
            $data['data'][$key]['inventory_allocation_reserve_num'] = getNum($inventory_allocation_reserve_num['total']);

            //统计配货待提
            $where = [
                'out_region'=>$value['region'],
                'goods_id'=>$value['goods_id'],
                'our_company_id'=>$value['our_company_id'],
                'out_storehouse'=>$value['object_id'],
                'status'=>10,
                'outbound_status'=>2,
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $inventory_allocation_wait_num = $this->getModel('ErpAllocationOrder')->field('SUM(num) as total')->where($where)->find();
            $data['data'][$key]['inventory_allocation_wait_num'] = getNum($inventory_allocation_wait_num['total']);
            /** ----------------------------------------统计物理库存start---------------------------------------- */
            //统计采购入库
            $where = [
                'si.our_company_id'=>$value['our_company_id'],
                'si.goods_id'=>$value['goods_id'],
                'si.storage_status'=>10,
                'o.region'=>$value['region'],
                'o.storehouse_id'=>$value['object_id'],
                'o.type'=>$is_agent == 1 ? 2 : 1,
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['si.create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $purchase_stock_in_num = $this->getModel('ErpStockIn')
                ->alias('si')
                ->field('SUM(actual_storage_num) as total')
                ->where($where)
                ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')
                ->find();

            //统计调拨入库
            $where = [
                'si.our_company_id'=>$value['our_company_id'],
                'si.goods_id'=>$value['goods_id'],
                'si.storage_status'=>10,
                'a.in_region'=>$value['region'],
                'a.in_storehouse'=>$value['object_id'],
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['si.create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $allocation_stock_in_num = $this->getModel('ErpStockIn')
                ->alias('si')
                ->field('SUM(actual_storage_num) as total')
                ->where($where)
                ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                ->find();

            //统计销售退货
            $where = [
                'si.our_company_id'=>$value['our_company_id'],
                'si.goods_id'=>$value['goods_id'],
                'si.storage_status'=>10,
                'o.region'=>$value['region'],
                'o.storehouse_id'=>$value['object_id'],
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['si.create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $sale_return_num = $this->getModel('ErpStockIn')
                ->alias('si')
                ->field('SUM(actual_storage_num) as total')
                ->where($where)
                ->join('oil_erp_returned_order r on r.id = si.source_object_id and si.source_number = r.order_number', 'left')
                ->join('oil_erp_sale_order o on o.id = r.source_order_id and r.source_order_number = o.order_number', 'left')
                ->find();

            //统计销售出库
            $where = [
                'so.our_company_id'=>$value['our_company_id'],
                'so.goods_id'=>$value['goods_id'],
                'so.outbound_status'=>10,
                'o.region'=>$value['region'],
                'o.storehouse_id'=>$value['object_id'],
                'o.order_type'=>1,
                'o.is_agent'=>$is_agent,
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['so.create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $sale_stock_out_num = $this->getModel('ErpStockOut')
                ->alias('so')
                ->field('SUM(actual_outbound_num) as total')
                ->where($where)
                ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number', 'left')
                ->find();

            //统计调拨出库
            $where = [
                'so.our_company_id'=>$value['our_company_id'],
                'so.goods_id'=>$value['goods_id'],
                'so.outbound_status'=>10,
                'a.out_region'=>$value['region'],
                'a.out_storehouse'=>$value['object_id'],
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['so.create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $allocation_stock_out_num = $this->getModel('ErpStockOut')
                ->alias('so')
                ->field('SUM(actual_outbound_num) as total')
                ->where($where)
                ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                ->find();

            //统计采购退货
            $where = [
                'si.our_company_id'=>$value['our_company_id'],
                'si.goods_id'=>$value['goods_id'],
                'si.outbound_status'=>10,
                'o.region'=>$value['region'],
                'o.storehouse_id'=>$value['object_id'],
            ];

            if (isset($param['start_time']) || isset($param['end_time'])) {
                if (trim($param['start_time']) && !trim($param['end_time'])) {

                    $where['si.create_time'] = ['egt', trim($param['start_time'])];
                } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
                } else if (trim($param['start_time']) && trim($param['end_time'])) {

                    $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
                }
            }

            $purchase_return_num = $this->getModel('ErpStockOut')
                ->alias('si')
                ->field('SUM(actual_outbound_num) as total')
                ->where($where)
                ->join('oil_erp_returned_order r on r.id = si.source_object_id and si.source_number = r.order_number', 'left')
                ->join('oil_erp_purchase_order o on o.id = r.source_order_id and r.source_order_number = o.order_number', 'left')
                ->find();

            $inventory_stock_num = $purchase_stock_in_num['total'] + $allocation_stock_in_num['total'] + $sale_return_num['total'] - $sale_stock_out_num['total'] - $allocation_stock_out_num['total'] + $purchase_return_num['total'];
            $data['data'][$key]['inventory_stock_num'] = getNum($inventory_stock_num);
            /** -----------------------------------------统计物理库存end----------------------------------------- */

            $export_data[$num]['id'] = $data['data'][$key]['id'];
            $export_data[$num]['our_company_name'] = $data['data'][$key]['our_company_name'];
            $export_data[$num]['region_name'] = $data['data'][$key]['region_name'];
            $export_data[$num]['stock_type'] = $data['data'][$key]['stock_type'];
            $export_data[$num]['storehouse_name'] = $data['data'][$key]['storehouse_name'];
            $export_data[$num]['goods_code'] = $data['data'][$key]['goods_code'];
            $export_data[$num]['goods_info'] = $data['data'][$key]['goods_name'].'/'.$data['data'][$key]['source_from'].'/'.$data['data'][$key]['grade'].'/'.$data['data'][$key]['level'];
            $export_data[$num]['stock_num'] = $data['data'][$key]['stock_num'] ? $data['data'][$key]['stock_num'] : '0';
            $export_data[$num]['inventory_stock_num'] = $data['data'][$key]['inventory_stock_num'] ? $data['data'][$key]['inventory_stock_num'] : '0';
            $export_data[$num]['first_stock'] = $data['data'][$key]['first_stock'] ? $data['data'][$key]['first_stock'] : '0';
            $export_data[$num]['transportation_num'] = $data['data'][$key]['transportation_num'] ? $data['data'][$key]['transportation_num'] : '0';
            $export_data[$num]['inventory_transportation_num'] = $data['data'][$key]['inventory_transportation_num'] ? $data['data'][$key]['inventory_transportation_num'] : '0';
            $export_data[$num]['sale_reserve_num'] = $data['data'][$key]['sale_reserve_num'] ? $data['data'][$key]['sale_reserve_num'] : '0';
            $export_data[$num]['inventory_sale_reserve_num'] = $data['data'][$key]['inventory_sale_reserve_num'] ? $data['data'][$key]['inventory_sale_reserve_num'] : '0';
            $export_data[$num]['sale_wait_num'] = $data['data'][$key]['sale_wait_num'] ? $data['data'][$key]['sale_wait_num'] : '0';
            $export_data[$num]['inventory_sale_wait_num'] = $data['data'][$key]['inventory_sale_wait_num'] ? $data['data'][$key]['inventory_sale_wait_num'] : '0';
            $export_data[$num]['allocation_reserve_num'] = $data['data'][$key]['allocation_reserve_num'] ? $data['data'][$key]['allocation_reserve_num'] : '0';
            $export_data[$num]['inventory_allocation_reserve_num'] = $data['data'][$key]['inventory_allocation_reserve_num'] ? $data['data'][$key]['inventory_allocation_reserve_num'] : '0';
            $export_data[$num]['allocation_wait_num'] = $data['data'][$key]['allocation_wait_num'] ? $data['data'][$key]['allocation_wait_num'] : '0';
            $export_data[$num]['inventory_allocation_wait_num'] = $data['data'][$key]['inventory_allocation_wait_num'] ? $data['data'][$key]['inventory_allocation_wait_num'] : '0';
            $export_data[$num]['available_num'] = $data['data'][$key]['available_num'] ? $data['data'][$key]['available_num'] : '0';
            $export_data[$num]['current_available_sale_num'] = $data['data'][$key]['current_available_sale_num'] ? $data['data'][$key]['current_available_sale_num'] : '0';
            $num ++;
            /** -----------------------------------------统计物理库存end----------------------------------------- */
        }
        $header = [
            'ID','账套公司','地区','仓库类型','仓库','产品代码','产品信息','物理库存','盘点物理库存','物理期初库存','在途库存','盘点在途库存',
            '销售预留','盘点销售预留','销售待提','盘点销售待提','配货预留','盘点配货预留','配货待提','盘点配货待提','可用库存','可售库存',
        ];
        array_unshift($export_data,  $header);
        $file_name_arr = '库存报表（物理库存有误）'.currentTime().'.xls';
        create_xls($export_data, $filename=$file_name_arr);
    }

    public function test()
    {
        $m = 19;
        for ($i=1;$i<=$m;$i++) {
            if ($i == 1) {
                for ($j=1;$j<($m+1)/2;$j++) {
                    echo '　';
                }
                echo '*<br/>';
            }
            else if ($i<=($m+1)/2) {
                for ($j=$i;$j<($m+1)/2;$j++) {
                    echo '　';
                }
                echo '*';
                for ($j=1;$j<=$i*2-2;$j++) {
                    echo '　';
                }
                echo '*<br/>';
            }
            else if ($i>($m+1)/2 && $i < $m) {
                for ($j=$i;$j>($m+1)/2&&$j<$m;$j--) {
                    echo '　';
                }
                echo '*';
                for ($j=$m;$j>$i*2-$m;$j--) {
                    echo '　';
                }
                echo '*<br/>';
            }
            else if ($i == $m) {
                for ($j=1;$j<($m+1)/2;$j++) {
                    echo '　';
                }
                echo '*<br/>';
            }
        }
        exit;

        $file_img = 'http://src.51zhaoyou.com/erp/2018814/0f0bc707923342cea89c8af5499dafac.png';
       // echo base64_encode($file_img);
        //exit;
        //header('content-type:image/jpeg');
        $str = 'a:45:{s:12:"order_number";s:21:"HY_LO2018121700000013";s:13:"source_number";s:0:"";s:19:"source_order_number";s:21:"HY_FO2018121400000009";s:11:"source_type";s:1:"2";s:13:"shipping_type";i:1;s:9:"is_retail";i:1;s:15:"is_send_message";i:1;s:14:"our_company_id";s:4:"3372";s:8:"goods_id";s:3:"189";s:7:"user_id";i:0;s:9:"user_name";s:0:"";s:10:"user_phone";i:0;s:10:"company_id";s:5:"20348";s:12:"company_name";s:16:"王俊杰-上海";s:12:"shipping_num";s:6:"200000";s:11:"loading_num";i:0;s:13:"unloading_num";i:0;s:11:"temperature";i:0;s:7:"density";s:0:"";s:19:"distribution_status";i:1;s:12:"order_status";i:1;s:6:"region";s:3:"321";s:9:"dealer_id";s:2:"56";s:11:"dealer_name";s:6:"苏菲";s:16:"shipping_address";s:13:"煤电路1号";s:17:"receiving_address";s:16:"华腾路1838号";s:7:"shipper";s:1:"1";s:11:"driver_name";s:10:" 陆黄荣";s:12:"driver_phone";s:11:"13310128818";s:10:"car_number";s:19:"沪DL9569 沪DB0671";s:7:"freight";i:9900000;s:10:"incidental";i:0;s:8:"loss_num";i:0;s:11:"seal_number";s:0:"";s:6:"remark";s:0:"";s:20:"business_create_time";s:19:"2018-12-17 14:10:00";s:16:"business_creater";s:3:"185";s:11:"create_time";s:19:"2018-12-17 17:06:52";s:7:"creater";s:2:"56";s:22:"business_shipping_time";s:17:"2018年12月15号";s:13:"shipping_city";s:11:"25_321_2717";s:14:"shipping_phone";s:11:"13311772795";s:14:"receiving_city";s:11:"25_321_2718";s:15:"receiving_phone";s:11:"18916389999";s:13:"shipper_phone";s:11:"13311772795";}';
        print_r(unserialize($str));
        exit;
        $a = Array
        (
            "goodsId" => 3,
            "objectId" => 15,
            "region" => 43,
            "ourCompanyId" => 3372,
            "storageCode" => HY_RO2018110700000005,
            "stockType" => 1,
            "beforeCostNum" => 20100000,
            "newCostNum" => 100000,
            "newPrice" => 30000,
            "stockId" => 564367,
    );
        echo json_encode($a);
        exit;
        $img_content = file_get_contents('http://src.51zhaoyou.com/erp/2018814/0f0bc707923342cea89c8af5499dafac.png');
//        $img_info = getimagesize($img_content);
//        print_r($img_info);
//        exit;
        echo 'data:image/png;base64,' . base64_encode($img_content);
//
        exit;
        $data = [
            'content' => '123455',
            'fileType' => 'jpg',
        ];
        //加密完后结果作为mac参数值
        $data['mac'] = $this->pu_encrypt(json_encode($data));
        print_r($data);
        exit;
        echo '<hr/>';
        echo http('http://service.51zhaoyou.me/tms/uploadFile', $data);

        exit;

        $params = ['a'=>"运动", 'c'=>"舞蹈", 'b'=>"计算机",'i'=>'info','m'=>'ming','e'=>'email'];
        echo getTmsApiSign($params);
        //-------------------------tms接口请求示例--------------------------------------------------------------------
        $order = $this->getModel('ErpShippingOrder')->findShippingOrder(['id' => 139]);
        $order['goods_info'] = $this->getModel('ErpGoods')->where(['id'=>$order['goods_id']])->find();

        $params_order = $this->getEvent('ErpShipping')->getDataForTmsAddOrder($order);
        $params['data'] = json_encode($params_order, JSON_UNESCAPED_UNICODE);
        //echo getTmsApiSign($params);
        echo '<hr/>';
        print_r(getTmsAllParams($params));
        $api_params = getTmsAllParams($params);


        echo '<hr/>';
        $result = postTmsApiAddOrder($api_params);
        print_r($result);
        echo '<hr/>';
        //-------------------------end:tms接口请求示例--------------------------------------------------------------------
        exit;
        echo  json_encode(["运动", "舞蹈", "计算机"]);
        print_r(getErpCompanyList('company_id'));
        print_r(getErpCompanyList('company_id, company_name, pre_code'));
        print_r(S('ErpCompanyPreCode'));
        echo date('Y-m', strtotime('1 month', strtotime('2017-11')));
        echo '<hr/>';
        echo intval(date('Ym', strtotime('1 month', strtotime('2017-11')))) > 201711 ? "真" : '假';
        //$user_ids = $this->getModel('User')->where()->getField('id,dealer_name,user_phone');
        //print_r($user_ids);
        exit;
        $region = '湖北';
        $areas = CityToPro();
        $areas[ 'provincename2id' ]['湖北'] = 13;
        //print_r($areas);

        $citiesString = ltrim ( $areas[ 'provinceid2cityid' ][ $areas[ 'provincename2id' ][ $region ] ] , ',' );
        //print_r($citiesString);
        $citiesArray  = explode(',', $citiesString);
        print_r($citiesArray);
        exit;
        echo pw_encode('123456', 0);
        exit;
        echo -205191700 > -205191700 ? '大' : '小';
        exit;
        $Model = D("ErpUserWaitNumView");
        $data = $Model->field('*')->order('id desc')->select();
        var_dump($data);exit;

        $str = 'a:62:{s:2:"id";s:3:"896";s:12:"order_number";s:18:"SO2017061500000140";s:14:"add_order_time";s:19:"2017-06-15 23:59:59";s:10:"order_type";s:1:"1";s:10:"is_special";s:1:"2";s:14:"our_company_id";s:2:"70";s:6:"region";s:3:"221";s:7:"user_id";s:5:"13778";s:10:"company_id";s:4:"7124";s:8:"depot_id";s:5:"99999";s:13:"storehouse_id";s:3:"371";s:8:"pay_type";s:1:"1";s:12:"prepay_ratio";s:1:"0";s:14:"account_period";s:1:"0";s:14:"user_bank_info";s:53:"中国农业银行吴江同里支行--543301040010007";s:14:"invoice_remark";s:0:"";s:12:"order_remark";s:0:"";s:9:"dealer_id";s:3:"174";s:11:"dealer_name";s:9:"赵泽越";s:17:"collection_status";s:1:"1";s:14:"invoice_status";s:1:"1";s:12:"order_status";s:1:"4";s:12:"order_amount";s:7:"4320000";s:16:"collected_amount";s:1:"0";s:15:"invoiced_amount";s:1:"0";s:13:"delivery_date";s:19:"2017-06-16 00:00:00";s:15:"delivery_method";s:1:"2";s:14:"delivery_money";s:1:"0";s:7:"creater";s:3:"174";s:11:"create_time";s:19:"2017-06-15 16:35:37";s:10:"audit_time";s:19:"2017-06-16 16:49:22";s:7:"auditor";s:3:"174";s:7:"updater";s:3:"174";s:11:"update_time";s:19:"2017-06-16 16:34:40";s:10:"check_time";s:19:"0000-00-00 00:00:00";s:12:"confirm_time";s:19:"0000-00-00 00:00:00";s:14:"end_order_time";s:19:"2017-06-16 20:35:37";s:12:"order_source";s:1:"1";s:8:"goods_id";s:3:"138";s:7:"is_loss";s:1:"2";s:8:"loss_num";s:1:"0";s:19:"entered_loss_amount";s:1:"0";s:7:"buy_num";s:3:"900";s:19:"acting_purchase_num";s:1:"0";s:14:"buy_num_retail";s:1:"0";s:5:"price";s:6:"480000";s:12:"company_info";s:0:"";s:17:"outbound_quantity";s:1:"0";s:18:"is_upload_contract";s:1:"2";s:12:"contract_url";s:0:"";s:8:"is_agent";s:1:"2";s:11:"goods_price";s:6:"460000";s:11:"market_info";s:0:"";s:19:"provide_market_info";s:1:"2";s:13:"dev_dealer_id";s:3:"119";s:15:"dev_dealer_name";s:6:"顾来";s:19:"total_sale_wait_num";s:1:"0";s:13:"subsidy_money";s:1:"0";s:13:"from_order_id";s:1:"0";s:17:"from_order_number";s:0:"";s:10:"param_data";s:0:"";s:9:"is_remind";s:1:"0";}';
        var_dump(unserialize($str));exit;
        echo floor(setNum(85860 / getNum(477000)));
        exit;
        $a = sprintf('%.2f', 0.66);
        $b = sprintf('%.2f', 2.1);
        echo $a + $b;
        echo $a + $b == '2.76' ? '对' : '错';
        echo '<br/>';
        $a = 1000.4567 + 2.22;
        echo $b = sprintf('%.2f', $a);
        echo '<br/>';
        echo sprintf('%.3f', $a);
        echo '<br/> $b == 1002.67';
        if($b == 1002.67){
            echo '对';
        }else{
            echo '错';
        }
        echo '<br/>';
        echo substr(sprintf('%.3f', $a), 0, -1);
        echo '<hr/>';
        ECHO sprintf('%.2f', substr(sprintf('%.3f', $a), 0, -1));
        exit;
        $data = M('erp_user_wait_num_view')->where("our_company_id = 3372")->limit(10, 10)->select();
        print_r($data);
        exit;
        echo getNum('8196');
        exit();
        echo date('H');
        if(date('H') > 20){
            echo 1;
        }else{
            echo 2;
        }
        exit;
        echo currentTime();
        exit;
        $a = 0.66;
        $b = 2.1;
        echo floor($a*100)/100;
        exit;
        //$num = bcadd();
        echo $a + $b;
        var_dump(bccomp($a + $b,3.4));
        exit;
        header("content-type:image.gif");
        $im = imagecreatefromjpeg('./Public/desert.jpg');
        $red = imagecolorallocate($im, 255, 0, 0);
        imagestring($im, 5, 0, 0, iconv('utf-8', 'gbk', "php爱好者"), $red);
        imagegif($im);
        imagedestroy($im);
        exit;
        echo '111';
        $city = provinceCityZone()['city'];
        print_r($city);
        exit;
        $status = D('ErpStock')->saveStock(['goods_id'=>1, 'object_id'=>3, 'stock_type'=>1, 'stock_num'=>1200, 'transportation_num'=>200, 'create_time'=>currentTime()]);
        echo D('ErpStock')->getLastSql();
        var_dump($status);
        echo $status;
        echo $status ? '成功' : '失败';

        //$client = ClientBuilder::create()->setHosts(['http://42.51.8.153:9200'])->build();
        //var_dump($client);
        //$a = 2348.44782934;
        //echo round($a, 2);
        // print_r(getDepotRegionData());
//        print_r($this->permissionAll());
//        $child = $this->getChildCode('admin/manage');
//
//        print_r($child);
//
//        //$nodes = array_column($child['erp'][1],'node_code');
//        $per = $this->getUserPermissionArr($child['erp'][1]);
//
//        print_r($per);
        //echo getNum(590512);
        //$info = erpCodeNumber(2, '船用油');
        // print_r($info);

//        $data = S('CacheLock001');
//        if(!$data) $data = [];
//        print_r($data);
//        echo '--------';
//        $data[] = 'we3irw';
//        $dd = S('CacheLock001',$data);

//        print_r(S('CacheLock'));
//        echo '===========';
        //echo S('CacheLock', null);
        //print_r(S('CacheLock'));

    }

    public function testRpc(){
        Vendor('phpRPC.phprpc_client');
        $client = new \PHPRPC_Client('http://api.51zhaoyou.me/ErpRetailOrder');
        // 或者采用
        //$client = new \PHPRPC_Client();
        //$client->useService('http://serverName/index.php/Home/Server');
//        vendor('Hprose.HproseHttpClient');
//        $client = new \HproseHttpClient('http://api.51zhaoyou.me/ErpRetailOrder');
        $result = $client->test();
        print_r($result);
    }

    public function testOrder(){
        $url = 'http://api.51zhaoyou.me/ErpRetailOrder/addOrder';
        $param = D('RetailOrder')->where(['zhaoyou_status'=>10, 'status'=>100])->find();
        print_r($param);
        //$result = apiPost($url, $param);
        //var_dump($result);
    }

    /**
     * 统一清除缓存方法
     * @author xiaowen
     * @time 2017-7-8-2
     * @ return json
     */
    public function clearCacheData(){
        //缓存类型 cache_type = 'city', 省市数据缓存，其他类型待定

        $cache_type = trim(I('param.cache_type', ''));
        if($cache_type){
            switch($cache_type){
                case 'city':
                    S('provinceData', null);
                    S('cityData', null);
                    S('zoneData', null);
                    S('cityLevel1', null);
                    S('cityLevel2', null);
                    S('cityLevel3', null);
                    break;
            }
            $this->echoJson(['status'=>1, 'message'=>'缓存清除成功']);
        }else{
            $this->echoJson(['status'=>0, 'message'=>'缺失缓存类型参数，无法清除对应缓存']);
        }
    }
    public function clearLock()
    {
        clearCacheLock();
        if(IS_AJAX){
            $this->echoJson(['status'=>1, 'message'=>'程序缓存清除成功']);
        }
        $this->display();
    }

    public function changeCompany()
    {
        $id = $_POST['id'];
        $company_name = $_POST['name'];

        session('erp_company_id',$id) ;
        session('erp_company_name',$company_name) ;
        $this->echoJson(['status' => 1, 'message' => '切换成功']);
    }

    public function retailStockTaking () {
        if (IS_AJAX) {
            $param = $_REQUEST;
            set_time_limit(0);
            //读取8月2日加油网点期初库存excel
            Vendor('PHPExcel.Classes.PHPExcel');
            Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
            Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
            $filePath1 = './stock_skid_data.xlsx';
            $filePath2 = './stock_skid_data.xls';
            $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
            if (is_file($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel2007();
                if (!$PHPReader->canRead($filePath)) {
                    $PHPReader = new \PHPExcel_Reader_Excel5();
                    if (!$PHPReader->canRead($filePath)) {
                        return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                    }
                }
                $PHPExcel = $PHPReader->load($filePath);
                # @读取excel文件中的第一个工作表
                $currentSheet = $PHPExcel->getSheet(0);
                $data = $currentSheet->toArray();
                unset($data[0]);
                foreach ($data as $key=>$value) {
                    $skid_data[trim($value[0]).trim($value[5])] = $value;
                }
            } else {
                return false;
            }

            //读取服务商期初库存facilitator_stock
            $filePath1 = './facilitator_stock.xlsx';
            $filePath2 = './facilitator_stock.xls';
            $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
            if (is_file($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel2007();
                if (!$PHPReader->canRead($filePath)) {
                    $PHPReader = new \PHPExcel_Reader_Excel5();
                    if (!$PHPReader->canRead($filePath)) {
                        return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                    }
                }
                $PHPExcel = $PHPReader->load($filePath);
                # @读取excel文件中的第一个工作表
                $currentSheet = $PHPExcel->getSheet(0);
                $data = $currentSheet->toArray();
                unset($data[0]);
                foreach ($data as $key=>$value) {
                    $facilitator_data[trim($value[0]).trim($value[4])] = $value;
                }
            } else {
                return false;
            }

            $where = [
                'stock_type' => 4
            ];

            $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');

            $field = 's.*,g.goods_name,g.level,g.goods_code,g.grade,g.source_from,fs.name,f.name as facilitator_name,rg.available_sale_stock,rg.available_use_stock';
            $data = $this->getModel('ErpStock')->retailstocktaking($where, $field, $param['start'], $param['length']);
            $getAllRegion = provinceCityZone()['city'];

            $arr = [];

            foreach($data['data'] as $key=>$value){
                $arr[$key]['id'] = $value['id'];
                $arr[$key]['our_company_name'] = $erp_company[$value['our_company_id']];
                $arr[$key]['region_name'] = $getAllRegion[$value['region']];
                $arr[$key]['stock_type'] = stockType($value['stock_type']);
                $arr[$key]['facilitator_name'] = $value['facilitator_name'];
                $arr[$key]['object_name'] = $value['name'];
                $arr[$key]['goods_code'] = $value['goods_code'];
                $arr[$key]['goods_name'] = $value['goods_name'];
                $arr[$key]['source_from'] = $value['source_from'];
                $arr[$key]['grade'] = $value['grade'];
                $arr[$key]['level'] = $value['level'];
                $arr[$key]['stock_num'] = $value['stock_num']/10000;

                /**
                 * 判断该加油网点是否有期初库存
                 * 若有，则该加油网点期初为服务商期初库存
                 * 若没有，则该加油网点期初为0
                 **/
                if ($skid_data[$value['object_id'].$value['goods_code']][5] == $value['goods_code'] && $facilitator_data[$value['facilitator_id'].$value['goods_code']][4] == $value['goods_code']) {

                    //获取6月12日到8月2日期间该服务商单据的平均密度
                    $density_where = [
                        'si.storage_type'=>2,
                        'si.goods_id'=>$value['goods_id'],
                        'a.in_facilitator_id'=>$value['facilitator_id'],
                        'a.in_region'=>$value['region'],
                        'a.in_facilitator_skid_id'=>0,
                    ];
                    $average_in_density = $this->getModel('ErpStockIn')
                        ->alias('si')
                        ->field('SUM(outbound_density)/COUNT(si.id) as average_in_density')
                        ->where($density_where)
                        ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                        ->find();

                    $density_where = [
                        'so.outbound_type'=>2,
                        'so.goods_id'=>$value['goods_id'],
                        'a.out_facilitator_id'=>$value['facilitator_id'],
                        'a.out_region'=>$value['region'],
                        'a.out_facilitator_skid_id'=>0,
                    ];
                    $average_out_density = $this->getModel('ErpStockOut')
                        ->alias('so')
                        ->field('SUM(outbound_density)/COUNT(so.id) as average_out_density')
                        ->where($density_where)
                        ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                        ->find();

                    //区域商品维护的密度
                    $region_goods_density_where = [
                        'goods_id'=>$value['goods_id'],
                        'region'=>$value['region'],
                    ];
                    $region_goods_density = $this->getModel('ErpRegionGoods')
                        ->field('SUM(density)/COUNT(id) as average_region_density')
                        ->where($region_goods_density_where)
                        ->find();

                    //商品密度
                    $goods_density_where = [
                        'id'=>$value['goods_id'],
                    ];
                    $goods_density = $this->getModel('ErpGoods')
                        ->field('density_value')
                        ->where($goods_density_where)
                        ->find();

                    if ($average_in_density['average_in_density'] && $average_out_density['average_out_density']) {
                        $average_density = ($average_in_density['average_in_density'] + $average_out_density['average_out_density']) / 2;
                    } elseif ($average_in_density['average_in_density'] || $average_out_density['average_out_density']) {
                        $average_density = $average_in_density['average_in_density'] ? $average_in_density['average_in_density'] : $average_out_density['average_out_density'];
                    } elseif ($region_goods_density['average_region_density']) {
                        $average_density = $region_goods_density['average_region_density'];
                    } elseif ($goods_density['density_value']) {
                        $average_density = $goods_density['density_value'];
                    }

                    $arr[$key]['stock_first'] = $facilitator_data[$value['facilitator_id'].$value['goods_code']][9] ? $facilitator_data[$value['facilitator_id'].$value['goods_code']][9]/$average_density*1000 : '0';
                } else {
                    $arr[$key]['stock_first'] = '0';
                }


                /**
                 * 判断该加油网点是否有期初库存
                 * 若有，则该加油网点8月2日前的调拨单取对应服务商的调拨单
                 * 若没有，则该加油网点8月2日前的调拨单为0
                 **/
                if ($skid_data[$value['object_id'].$value['goods_code']][5] == $value['goods_code']) {
                    //统计服务商调拨入库
                    $where = [
                        'si.our_company_id'=>$value['our_company_id'],
                        'si.goods_id'=>$value['goods_id'],
                        'si.storage_type'=>2,
                        'si.storage_status'=>10,
                        'a.in_region'=>$value['region'],
                        'a.in_facilitator_id'=>$value['facilitator_id'],
                        'a.in_facilitator_skid_id'=>0,
                    ];

                    $facilitator_allocation_in_num = $this->getModel('ErpStockIn')
                        ->alias('si')
                        ->field('SUM(actual_in_num_liter) as total')
                        ->where($where)
                        ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                        ->find();

                    //统计服务商调拨出库
                    $where = [
                        'so.our_company_id'=>$value['our_company_id'],
                        'so.goods_id'=>$value['goods_id'],
                        'so.outbound_type'=>2,
                        'so.outbound_status'=>10,
                        'a.out_region'=>$value['region'],
                        'a.out_facilitator_id'=>$value['facilitator_id'],
                        'a.out_facilitator_skid_id'=>0,
                    ];

                    $facilitator_allocation_out_num = $this->getModel('ErpStockOut')
                        ->alias('so')
                        ->field('SUM(actual_out_num_liter) as total')
                        ->where($where)
                        ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                        ->find();

                    $arr[$key]['facilitator_allocation_in_num'] = $facilitator_allocation_in_num['total'] ? $facilitator_allocation_in_num['total']/10000 : '0';
                    $arr[$key]['facilitator_allocation_out_num'] = $facilitator_allocation_out_num['total'] ? $facilitator_allocation_out_num['total']/10000 : '0';

                } else {
                    $arr[$key]['facilitator_allocation_in_num'] = '0';
                    $arr[$key]['facilitator_allocation_out_num'] = '0';
                }

                //统计加油网点调拨入库
                $where = [
                    'si.our_company_id'=>$value['our_company_id'],
                    'si.goods_id'=>$value['goods_id'],
                    'si.storage_type'=>2,
                    'si.storage_status'=>10,
                    'a.in_region'=>$value['region'],
                    'a.in_facilitator_skid_id'=>$value['object_id'],
                ];

                $skid_allocation_in_num = $this->getModel('ErpStockIn')
                    ->alias('si')
                    ->field('SUM(actual_in_num_liter) as total')
                    ->where($where)
                    ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                    ->find();

                //统计加油网点调拨出库
                $where = [
                    'so.our_company_id'=>$value['our_company_id'],
                    'so.goods_id'=>$value['goods_id'],
                    'so.outbound_type'=>2,
                    'so.outbound_status'=>10,
                    'a.out_region'=>$value['region'],
                    'a.out_facilitator_skid_id'=>$value['object_id'],
                ];

                $skid_allocation_out_num = $this->getModel('ErpStockOut')
                    ->alias('so')
                    ->field('SUM(actual_out_num_liter) as total')
                    ->where($where)
                    ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                    ->find();

                //统计小微零售出库
                $where = [
                    'ro.type'=>$value['goods_name'] == '柴油' ? ['like', '%' . $value['goods_name'] . '%'] : $value['goods_name'],
                    'ro.source'=>$value['source_from'],
                    'ro.rank'=>$value['level'] == '普通柴油' ? ['in','国Ⅲ,国Ⅳ'] : $value['level'],
                    'ro.level'=>$value['grade'],
                    'ro.region'=>$value['region'],
                    'u.facilitator_skid_id'=>$value['object_id'],
                    'ro.create_time'=>['gt','2017-06-10'],
                    'ro.zhaoyou_status'=>10,
                ];

                $retail_stock_out_num = $this->getModel('RetailOrder')
                    ->alias('ro')
                    ->field('SUM(ro.number) as retail_total')
                    ->where($where)
                    ->join('oil_retail_goods g on g.id = ro.retail_goods_id', 'left')
                    ->join('oil_facilitator_user u on u.facilitator_user_id = ro.sale_company_user_id', 'left')
                    ->find();

                //统计集团零售出库
                $where = [
                    'g.type'=>$value['goods_name'] == '柴油' ? ['like', '%' . $value['goods_name'] . '%'] : $value['goods_name'],
                    'g.source'=>$value['source_from'],
                    'g.rank'=>$value['level'] == '普通柴油' ? ['in','国Ⅲ,国Ⅳ'] : $value['level'],
                    'g.level'=>$value['grade'],
                    'go.region'=>$value['region'],
                    'u.facilitator_skid_id'=>$value['object_id'],
                    'go.create_time'=>['gt','2017-06-10'],
                    'go.zhaoyou_status'=>10,
                ];

                $galaxy_stock_out_num = $this->getModel('GalaxyOrder')
                    ->alias('go')
                    ->field('SUM(go.num) as galaxy_total')
                    ->where($where)
                    ->join('oil_galaxy_goods g on g.id = go.galaxy_goods_id', 'left')
                    ->join('oil_facilitator_user u on u.facilitator_user_id = go.sale_company_user_id', 'left')
                    ->find();

                $arr[$key]['skid_allocation_in_num'] = $skid_allocation_in_num['total'] ? $skid_allocation_in_num['total']/10000 : '0';
                $arr[$key]['skid_allocation_out_num'] = $skid_allocation_out_num['total'] ? $skid_allocation_out_num['total']/10000 : '0';
                $arr[$key]['retail_stock_out_num'] = $retail_stock_out_num['retail_total'] ? $retail_stock_out_num['retail_total'] : '0';
                $arr[$key]['galaxy_stock_out_num'] = $galaxy_stock_out_num['galaxy_total'] ? $galaxy_stock_out_num['galaxy_total'] : '0';
                $arr[$key]['inventory_balance'] = $arr[$key]['stock_first'] + $arr[$key]['facilitator_allocation_in_num'] - $arr[$key]['facilitator_allocation_out_num'] + $arr[$key]['skid_allocation_in_num'] - $arr[$key]['skid_allocation_out_num'] - $arr[$key]['retail_stock_out_num'] - $arr[$key]['galaxy_stock_out_num'];
            }
            $data['data'] = $arr;
            $data['recordsFiltered'] = $data['recordsTotal'];
            $data['draw'] = $_REQUEST['draw'];
            $this->echoJson($data);
        }
        $this->display();
    }

    public function exportRetailStockTaking () {
        $param = $_REQUEST;
        set_time_limit(500);
        //读取8月2日加油网点期初库存excel
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        $filePath1 = './stock_skid_data.xlsx';
        $filePath2 = './stock_skid_data.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if (is_file($filePath)) {
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            unset($data[0]);
            foreach ($data as $key=>$value) {
                $skid_data[trim($value[0]).trim($value[5])] = $value;
            }
        } else {
            return false;
        }

        //读取服务商期初库存facilitator_stock
        $filePath1 = './facilitator_stock.xlsx';
        $filePath2 = './facilitator_stock.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if (is_file($filePath)) {
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            unset($data[0]);
            foreach ($data as $key=>$value) {
                $facilitator_data[trim($value[0]).trim($value[4])] = $value;
            }
        } else {
            return false;
        }

        $where = [
            'stock_type' => 4
        ];

        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');

        $field = 's.*,g.goods_name,g.level,g.goods_code,g.grade,g.source_from,fs.name,f.name as facilitator_name,rg.available_sale_stock,rg.available_use_stock';
        $data = $this->getModel('ErpStock')->retailstocktaking($where,$field, $param['start'], $param['length']);
        $getAllRegion = provinceCityZone()['city'];

        $arr = [];

        foreach($data['data'] as $key=>$value){
            $arr[$key]['id'] = $value['id'];
            $arr[$key]['our_company_name'] = $erp_company[$value['our_company_id']];
            $arr[$key]['region_name'] = $getAllRegion[$value['region']];
            $arr[$key]['stock_type'] = stockType($value['stock_type']);
            $arr[$key]['facilitator_name'] = $value['facilitator_name'];
            $arr[$key]['object_name'] = $value['name'];
            $arr[$key]['goods_code'] = $value['goods_code'];
            $arr[$key]['goods_name'] = $value['goods_name'];
            $arr[$key]['source_from'] = $value['source_from'];
            $arr[$key]['grade'] = $value['grade'];
            $arr[$key]['level'] = $value['level'];
            $arr[$key]['stock_num'] = $value['stock_num']/10000;

            /**
             * 判断该加油网点是否有期初库存
             * 若有，则该加油网点期初为服务商期初库存
             * 若没有，则该加油网点期初为0
             **/
            if ($skid_data[$value['object_id'].$value['goods_code']][5] == $value['goods_code'] && $facilitator_data[$value['facilitator_id'].$value['goods_code']][4] == $value['goods_code']) {
                //获取6月12日到8月2日期间该服务商单据的平均密度
                $density_where = [
                    'si.storage_type'=>2,
                    'si.goods_id'=>$value['goods_id'],
                    'a.in_facilitator_id'=>$value['facilitator_id'],
                    'a.in_region'=>$value['region'],
                    'a.in_facilitator_skid_id'=>0,
                ];
                $average_in_density = $this->getModel('ErpStockIn')
                    ->alias('si')
                    ->field('SUM(outbound_density)/COUNT(si.id) as average_in_density')
                    ->where($density_where)
                    ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                    ->find();

                $density_where = [
                    'so.outbound_type'=>2,
                    'so.goods_id'=>$value['goods_id'],
                    'a.out_facilitator_id'=>$value['facilitator_id'],
                    'a.out_region'=>$value['region'],
                    'a.out_facilitator_skid_id'=>0,
                ];
                $average_out_density = $this->getModel('ErpStockOut')
                    ->alias('so')
                    ->field('SUM(outbound_density)/COUNT(so.id) as average_out_density')
                    ->where($density_where)
                    ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                    ->find();

                //区域商品维护的密度
                $region_goods_density_where = [
                    'goods_id'=>$value['goods_id'],
                    'region'=>$value['region'],
                ];
                $region_goods_density = $this->getModel('ErpRegionGoods')
                    ->field('SUM(density)/COUNT(id) as average_region_density')
                    ->where($region_goods_density_where)
                    ->find();

                //商品密度
                $goods_density_where = [
                    'id'=>$value['goods_id'],
                ];
                $goods_density = $this->getModel('ErpGoods')
                    ->field('density_value')
                    ->where($goods_density_where)
                    ->find();

                if ($average_in_density['average_in_density'] && $average_out_density['average_out_density']) {
                    $average_density = ($average_in_density['average_in_density'] + $average_out_density['average_out_density']) / 2;
                } elseif ($average_in_density['average_in_density'] || $average_out_density['average_out_density']) {
                    $average_density = $average_in_density['average_in_density'] ? $average_in_density['average_in_density'] : $average_out_density['average_out_density'];
                } elseif ($region_goods_density['average_region_density']) {
                    $average_density = $region_goods_density['average_region_density'];
                } elseif ($goods_density['density_value']) {
                    $average_density = $goods_density['density_value'];
                }

                $arr[$key]['stock_first'] = $facilitator_data[$value['facilitator_id'].$value['goods_code']][9] ? $facilitator_data[$value['facilitator_id'].$value['goods_code']][9]/$average_density*1000 : '0';
            } else {
                $arr[$key]['stock_first'] = '0';
            }


            /**
             * 判断该加油网点是否有期初库存
             * 若有，则该加油网点8月2日前的调拨单取对应服务商的调拨单
             * 若没有，则该加油网点8月2日前的调拨单为0
             **/
            if ($skid_data[$value['object_id'].$value['goods_code']][5] == $value['goods_code']) {
                //统计服务商调拨入库
                $where = [
                    'si.our_company_id'=>$value['our_company_id'],
                    'si.goods_id'=>$value['goods_id'],
                    'si.storage_type'=>2,
                    'si.storage_status'=>10,
                    'a.in_region'=>$value['region'],
                    'a.in_facilitator_id'=>$value['facilitator_id'],
                    'a.in_facilitator_skid_id'=>0,
                ];

                $facilitator_allocation_in_num = $this->getModel('ErpStockIn')
                    ->alias('si')
                    ->field('SUM(actual_in_num_liter) as total')
                    ->where($where)
                    ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                    ->find();

                //统计服务商调拨出库
                $where = [
                    'so.our_company_id'=>$value['our_company_id'],
                    'so.goods_id'=>$value['goods_id'],
                    'so.outbound_type'=>2,
                    'so.outbound_status'=>10,
                    'a.out_region'=>$value['region'],
                    'a.out_facilitator_id'=>$value['facilitator_id'],
                    'a.out_facilitator_skid_id'=>0,
                ];

                $facilitator_allocation_out_num = $this->getModel('ErpStockOut')
                    ->alias('so')
                    ->field('SUM(actual_out_num_liter) as total')
                    ->where($where)
                    ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                    ->find();

                $arr[$key]['facilitator_allocation_in_num'] = $facilitator_allocation_in_num['total'] ? $facilitator_allocation_in_num['total']/10000 : '0';
                $arr[$key]['facilitator_allocation_out_num'] = $facilitator_allocation_out_num['total'] ? $facilitator_allocation_out_num['total']/10000 : '0';

            } else {
                $arr[$key]['facilitator_allocation_in_num'] = '0';
                $arr[$key]['facilitator_allocation_out_num'] = '0';
            }

            //统计加油网点调拨入库
            $where = [
                'si.our_company_id'=>$value['our_company_id'],
                'si.goods_id'=>$value['goods_id'],
                'si.storage_type'=>2,
                'si.storage_status'=>10,
                'a.in_region'=>$value['region'],
                'a.in_facilitator_skid_id'=>$value['object_id'],
            ];

            $skid_allocation_in_num = $this->getModel('ErpStockIn')
                ->alias('si')
                ->field('SUM(actual_in_num_liter) as total')
                ->where($where)
                ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
                ->find();

            //统计加油网点调拨出库
            $where = [
                'so.our_company_id'=>$value['our_company_id'],
                'so.goods_id'=>$value['goods_id'],
                'so.outbound_type'=>2,
                'so.outbound_status'=>10,
                'a.out_region'=>$value['region'],
                'a.out_facilitator_skid_id'=>$value['object_id'],
            ];

            $skid_allocation_out_num = $this->getModel('ErpStockOut')
                ->alias('so')
                ->field('SUM(actual_out_num_liter) as total')
                ->where($where)
                ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
                ->find();

            //统计小微零售出库
            $where = [
                'ro.type'=>$value['goods_name'] == '柴油' ? ['like', '%' . $value['goods_name'] . '%'] : $value['goods_name'],
                'ro.source'=>$value['source_from'],
                'ro.rank'=>$value['level'] == '普通柴油' ? ['in','国Ⅲ,国Ⅳ'] : $value['level'],
                'ro.level'=>$value['grade'],
                'ro.region'=>$value['region'],
                'u.facilitator_skid_id'=>$value['object_id'],
                'ro.create_time'=>['gt','2017-06-10'],
                'ro.zhaoyou_status'=>10,
            ];

            $retail_stock_out_num = $this->getModel('RetailOrder')
                ->alias('ro')
                ->field('SUM(ro.number) as retail_total')
                ->where($where)
                ->join('oil_retail_goods g on g.id = ro.retail_goods_id', 'left')
                ->join('oil_facilitator_user u on u.facilitator_user_id = ro.sale_company_user_id', 'left')
                ->find();

            //统计集团零售出库
            $where = [
                'g.type'=>$value['goods_name'] == '柴油' ? ['like', '%' . $value['goods_name'] . '%'] : $value['goods_name'],
                'g.source'=>$value['source_from'],
                'g.rank'=>$value['level'] == '普通柴油' ? ['in','国Ⅲ,国Ⅳ'] : $value['level'],
                'g.level'=>$value['grade'],
                'go.region'=>$value['region'],
                'u.facilitator_skid_id'=>$value['object_id'],
                'go.create_time'=>['gt','2017-06-10'],
                'go.zhaoyou_status'=>10,
            ];

            $galaxy_stock_out_num = $this->getModel('GalaxyOrder')
                ->alias('go')
                ->field('SUM(go.num) as galaxy_total')
                ->where($where)
                ->join('oil_galaxy_goods g on g.id = go.galaxy_goods_id', 'left')
                ->join('oil_facilitator_user u on u.facilitator_user_id = go.sale_company_user_id', 'left')
                ->find();

            $arr[$key]['skid_allocation_in_num'] = $skid_allocation_in_num['total'] ? $skid_allocation_in_num['total']/10000 : '0';
            $arr[$key]['skid_allocation_out_num'] = $skid_allocation_out_num['total'] ? $skid_allocation_out_num['total']/10000 : '0';
            $arr[$key]['retail_stock_out_num'] = $retail_stock_out_num['retail_total'] ? $retail_stock_out_num['retail_total'] : '0';
            $arr[$key]['galaxy_stock_out_num'] = $galaxy_stock_out_num['galaxy_total'] ? $galaxy_stock_out_num['galaxy_total'] : '0';
            $arr[$key]['inventory_balance'] = $arr[$key]['stock_first'] + $arr[$key]['facilitator_allocation_in_num'] - $arr[$key]['facilitator_allocation_out_num'] + $arr[$key]['skid_allocation_in_num'] - $arr[$key]['skid_allocation_out_num'] - $arr[$key]['retail_stock_out_num'] - $arr[$key]['galaxy_stock_out_num'];
        }

        $header = [
            '序号','我方公司','地区','仓库类型','服务商名称','加油网点名称','商品代码','商品名称','商品来源','商品标号','商品级别',
            '6月2日期初库存','服务商调拨入','服务商调拨出','加油网点调拨入','加油网点调拨出','小微零售出','集团零售出','结余'
        ];
        array_unshift($arr,  $header);
        $file_name = '服务商库存盘点'.currentTime().'.xls';
        create_xls($arr, $filename = $file_name);
    }


}