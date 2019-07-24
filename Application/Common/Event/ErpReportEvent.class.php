<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpReportEvent extends BaseController
{

    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
    }

    /**
     * 城市仓库存盘点报表
     * @author guanyu
     * @time 2017-12-15
     */
    public function stockChecks($param)
    {
        set_time_limit(0);

        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');

        //获取城市仓期初库存
        if ($param['stock_type'] == 1) {
            $first_stock = $this->searchFirstStock();
        } else {
            $first_stock = [];
        }

        //获取所有维度的库存数据
        $stock_info = $this->searchStockData($param);

        //查询目前城市仓库存信息
        $where = [
            'stock_type' => $param['stock_type'],
        ];
        $param = I('get.',0);
        if ($param['search_company']) {
            $where['s.our_company_id'] = $param['search_company'];
        }
        $where['s.region'] = ['neq', 1877]; //库存核对不包含江阴库存 edit xiaowen 2017-8-4
        $where['s.status'] = 1;
        $field = 's.*, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,es.storehouse_name,rg.available_sale_stock, rg.available_use_stock';
        $data = $this->getModel('ErpStock')->stocktaking($where,$field);
        $getAllRegion = provinceCityZone()['city'];

        //拼装数据
        foreach($data['data'] as $key=>$value){
            //城市仓捕捉期初库存
            if ($param['stock_type'] == 1) {
                if ($first_stock[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']] && $first_stock[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][15] == $erp_company[$data['data'][$key]['our_company_id']]) {
                    $data['data'][$key]['first_stock'] = $first_stock[$data['data'][$key]['storehouse_name'].$data['data'][$key]['goods_code']][8];
                } else {
                    $data['data'][$key]['first_stock'] = 0;
                }
            } else {
                $data['data'][$key]['first_stock'] = 0;
            }

            //拼装系统中存储的库存数据
            $data['data'][$key]['our_company_name'] = $erp_company[$value['our_company_id']];
            $data['data'][$key]['region_name'] = $getAllRegion[$value['region']] ? $getAllRegion[$value['region']] : '全国';
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

            $k = $value['region'].' '.$value['goods_id'].' '.$value['object_id'].' '.$value['our_company_id'];
            //盘点销售预留
            $data['data'][$key]['inventory_sale_reserve_num'] = $stock_info['inventory_sale_reserve_num'][$k] ? getNum($stock_info['inventory_sale_reserve_num'][$k]) : 0;
            //盘点销售待提
            $data['data'][$key]['inventory_sale_wait_num'] = $stock_info['inventory_sale_wait_num'][$k] ? getNum($stock_info['inventory_sale_wait_num'][$k]) : 0;
            //盘点采购在途
            $data['data'][$key]['inventory_transportation_num'] = $stock_info['inventory_purchase_transportation_num'][$k] + $stock_info['inventory_allocation_transportation_num'][$k] ? getNum($stock_info['inventory_purchase_transportation_num'][$k] + $stock_info['inventory_allocation_transportation_num'][$k]) : 0;
            //盘点调拨预留
            $data['data'][$key]['inventory_allocation_reserve_num'] = $stock_info['inventory_allocation_reserve_num'][$k] ? getNum($stock_info['inventory_allocation_reserve_num'][$k]) : 0;
            //盘点调拨待提
            $data['data'][$key]['inventory_allocation_wait_num'] = $stock_info['inventory_allocation_wait_num'][$k] ? getNum($stock_info['inventory_allocation_wait_num'][$k]) : 0;

            /** ----------------------------------------统计物理库存start---------------------------------------- */
            $data['data'][$key]['inventory_stock_num'] = getNum($stock_info['total_stock_in_num'][$k] - $stock_info['total_stock_out_num'][$k]);
            /** -----------------------------------------统计物理库存end----------------------------------------- */
        }
        return $data;
    }

    /**
     * 统计所有期初数据
     * @author guanyu
     * @time 2017-12-15
     */
    public function searchFirstStock()
    {
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        $filePath1 = './stock_data_final.xlsx';
        $filePath2 = './stock_data_final.xls';
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
            $first_stock_data = $currentSheet->toArray();
            unset($first_stock_data[0]);
        }else{
            $first_stock_data = [];
        }

        $first_stock = [];
        foreach ($first_stock_data as $value) {
            if ($value[2]) {
                $first_stock[trim($value[2]).trim($value[3])] = $value;
            }
        }
        return $first_stock;
    }

    /**
     * 统计所有维度库存数据
     * @author guanyu
     * @time 2017-12-15
     */
    public function searchStockData($param)
    {
        //查询所有库存销售预留数量
        $where = [
            'order_type' => 1,
            'is_agent' => $param['stock_type'] == 2 ? 1 : 2,
            'is_update_price' => 2,
            'is_returned' => 2,
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
        $inventory_sale_reserve_num = $this->getModel('ErpSaleOrder')
            ->field('region,goods_id,storehouse_id,our_company_id,SUM(IF(pay_type = 5, buy_num - total_sale_wait_num, buy_num)) as total')
            ->where($where)
            ->group('region,goods_id,storehouse_id,our_company_id')
            ->select();
        $stock_info['inventory_sale_reserve_num'] = $this->fixStockData($inventory_sale_reserve_num);


        //查询所有库存销售待提数量
        $where = [
            'order_type' => 1,
            'is_agent' => $param['stock_type'] == 2 ? 1 : 2,
            '_string' => '((pay_type IN (1, 3) AND collection_status = 10 AND order_status <> 2) OR (pay_type IN (2, 4) AND order_status = 10) OR (pay_type = 5 AND collection_status > 3 AND order_status = 10) OR (is_returned = 1 AND order_status = 10))'
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
        $inventory_sale_wait_num = $this->getModel('ErpSaleOrder')
            ->field('region,goods_id,storehouse_id,our_company_id,SUM(IF(is_returned = 1 and returned_goods_num > 0, 0, IF(pay_type = 5, total_sale_wait_num, buy_num) - outbound_quantity)) as total')
            ->where($where)
            ->group('region,goods_id,storehouse_id,our_company_id')
            ->select();
        $stock_info['inventory_sale_wait_num'] = $this->fixStockData($inventory_sale_wait_num);


        //查询所有库存采购在途数量
        $where = [
            'type' => $param['stock_type'] == 2 ? 2 : 1,
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
        $inventory_purchase_transportation_num = $this->getModel('ErpPurchaseOrder')
            ->field('region,goods_id,storehouse_id,our_buy_company_id as our_company_id,SUM(IF(is_returned = 1 and returned_goods_num > 0, 0, IF(pay_type = 5, total_purchase_wait_num, goods_num) - storage_quantity)) as total')
            ->where($where)
            ->group('region,goods_id,storehouse_id,our_buy_company_id')
            ->select();
        $stock_info['inventory_purchase_transportation_num'] = $this->fixStockData($inventory_purchase_transportation_num);


        //查询所有库存调拨在途数量
        $where = [
            'status' => 10,
            'outbound_status' => 1,
            'storage_status' => 2,
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
        $inventory_allocation_transportation_num = $this->getModel('ErpAllocationOrder')
            ->field('in_region as region,goods_id,in_storehouse as storehouse_id,our_company_id,SUM(actual_out_num) as total')
            ->where($where)
            ->group('in_region,goods_id,in_storehouse,our_company_id')
            ->select();
        $stock_info['inventory_allocation_transportation_num'] = $this->fixStockData($inventory_allocation_transportation_num);


        //统计配货预留
        $where = [
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
        $inventory_allocation_reserve_num = $this->getModel('ErpAllocationOrder')
            ->field('out_region as region,goods_id,out_storehouse as storehouse_id,our_company_id,SUM(num) as total')
            ->where($where)
            ->group('out_region,goods_id,out_storehouse,our_company_id')
            ->select();
        $stock_info['inventory_allocation_reserve_num'] = $this->fixStockData($inventory_allocation_reserve_num);

        //统计配货待提
        $where = [
            'status' => 10,
            'outbound_status' => 2,
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
        $inventory_allocation_wait_num = $this->getModel('ErpAllocationOrder')
            ->field('out_region as region,goods_id,out_storehouse as storehouse_id,our_company_id,SUM(num) as total')
            ->where($where)
            ->group('out_region,goods_id,out_storehouse,our_company_id')
            ->select();
        $stock_info['inventory_allocation_wait_num'] = $this->fixStockData($inventory_allocation_wait_num);


        //统计所有入库
        $where = [
            'si.storage_status' => 10,
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
        $total_stock_in_num = $this->getModel('ErpStockIn')
            ->alias('si')
            ->field('si.region,si.goods_id,si.storehouse_id,si.our_company_id,SUM(actual_storage_num) as total')
            ->where($where)
            ->group('si.region,si.goods_id,si.storehouse_id,si.our_company_id')
            ->select();
        $stock_info['total_stock_in_num'] = $this->fixStockData($total_stock_in_num);


//        //统计采购入库
//        $where = [
//            'si.storage_status' => 10,
//            'o.type'=>$param['stock_type'] == 2 ? 2 : 1,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['si.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $purchase_stock_in_num = $this->getModel('ErpStockIn')->alias('si')
//            ->field('o.region,si.goods_id,o.storehouse_id,si.our_company_id,SUM(actual_storage_num) as total')
//            ->where($where)
//            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')
//            ->group('o.region,si.goods_id,o.storehouse_id,si.our_company_id')
//            ->select();
//        $stock_info['purchase_stock_in_num'] = $this->fixStockData($purchase_stock_in_num);
//
//
//        //统计调拨入库
//        $where = [
//            'si.storage_status' => 10,
//            'si.stock_type' => ['neq',4],
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['si.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $allocation_stock_in_num = $this->getModel('ErpStockIn')
//            ->alias('si')
//            ->field('a.in_region as region,si.goods_id,a.in_storehouse as storehouse_id,si.our_company_id,SUM(actual_storage_num) as total')
//            ->where($where)
//            ->join('oil_erp_allocation_order a on a.id = si.source_object_id and si.source_number = a.order_number', 'left')
//            ->group('a.in_region,si.goods_id,a.in_storehouse,si.our_company_id')
//            ->select();
//        $stock_info['allocation_stock_in_num'] = $this->fixStockData($allocation_stock_in_num);
//
//
//        //统计销售退货
//        $where = [
//            'si.storage_status' => 10,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['si.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $sale_return_num = $this->getModel('ErpStockIn')
//            ->alias('si')
//            ->field('o.region,si.goods_id,o.storehouse_id,si.our_company_id,SUM(actual_storage_num) as total')
//            ->where($where)
//            ->join('oil_erp_returned_order r on r.id = si.source_object_id and si.source_number = r.order_number', 'left')
//            ->join('oil_erp_sale_order o on o.id = r.source_order_id and r.source_order_number = o.order_number', 'left')
//            ->group('o.region,si.goods_id,o.storehouse_id,si.our_company_id')
//            ->select();
//        $stock_info['sale_return_num'] = $this->fixStockData($sale_return_num);
//
//
//        //统计盘点入库
//        $where = [
//            'si.storage_status' => 10,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['si.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $inventory_stock_in = $this->getModel('ErpStockIn')
//            ->alias('si')
//            ->field('i.region,si.goods_id,si.storehouse_id,si.our_company_id,SUM(actual_storage_num) as total')
//            ->where($where)
//            ->join('oil_erp_inventory_order_detail i on i.id = si.source_object_id and si.source_number = i.inventory_order_number', 'left')
//            ->group('o.region,si.goods_id,o.storehouse_id,si.our_company_id')
//            ->select();
//        $stock_info['inventory_stock_in'] = $this->fixStockData($inventory_stock_in);


        //统计所有出库
        $where = [
            'so.outbound_status' => 10,
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
        $total_stock_out_num = $this->getModel('ErpStockOut')
            ->alias('so')
            ->field('so.region,so.goods_id,so.storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
            ->where($where)
            ->group('so.region,so.goods_id,so.storehouse_id,so.our_company_id')
            ->select();
        $stock_info['total_stock_out_num'] = $this->fixStockData($total_stock_out_num);


//        //统计销售出库
//        $where = [
//            'so.outbound_status' => 10,
//            'o.order_type' => 1,
//            'o.is_agent' => $param['stock_type'] == 2 ? 1 : 2,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['so.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $sale_stock_out_num = $this->getModel('ErpStockOut')
//            ->alias('so')
//            ->field('o.region,so.goods_id,o.storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
//            ->where($where)
//            ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number', 'left')
//            ->group('o.region,so.goods_id,o.storehouse_id,so.our_company_id')
//            ->select();
//        $stock_info['sale_stock_out_num'] = $this->fixStockData($sale_stock_out_num);
//
//
//        //统计调拨出库
//        $where = [
//            'so.outbound_status' => 10,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['so.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $allocation_stock_out_num = $this->getModel('ErpStockOut')
//            ->alias('so')
//            ->field('a.out_region as region,so.goods_id,a.out_storehouse as storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
//            ->where($where)
//            ->join('oil_erp_allocation_order a on a.id = so.source_object_id and so.source_number = a.order_number', 'left')
//            ->group('a.out_region,so.goods_id,a.out_storehouse,so.our_company_id')
//            ->select();
//        $stock_info['allocation_stock_out_num'] = $this->fixStockData($allocation_stock_out_num);
//
//
//        //统计采购退货
//        $where = [
//            'so.outbound_status' => 10,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['so.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['so.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['so.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $purchase_return_num = $this->getModel('ErpStockOut')
//            ->alias('so')
//            ->field('o.region,so.goods_id,o.storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
//            ->where($where)
//            ->join('oil_erp_returned_order r on r.id = so.source_object_id and so.source_number = r.order_number', 'left')
//            ->join('oil_erp_purchase_order o on o.id = r.source_order_id and r.source_order_number = o.order_number', 'left')
//            ->group('o.region,so.goods_id,o.storehouse_id,so.our_company_id')
//            ->select();
//        $stock_info['purchase_return_num'] = $this->fixStockData($purchase_return_num);
//
//
//        //统计盘点入库
//        $where = [
//            'so.outbound_status' => 10,
//        ];
//        if (isset($param['start_time']) || isset($param['end_time'])) {
//            if (trim($param['start_time']) && !trim($param['end_time'])) {
//                $where['si.create_time'] = ['egt', trim($param['start_time'])];
//            } else if (!trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
//            } else if (trim($param['start_time']) && trim($param['end_time'])) {
//                $where['si.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
//            }
//        }
//        $inventory_stock_out = $this->getModel('ErpStockOut')
//            ->alias('so')
//            ->field('i.region,so.goods_id,so.storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
//            ->where($where)
//            ->join('oil_erp_inventory_order_detail i on i.id = so.source_object_id and so.source_number = i.inventory_order_number', 'left')
//            ->group('o.region,so.goods_id,o.storehouse_id,so.our_company_id')
//            ->select();
//        $stock_info['inventory_stock_out'] = $this->fixStockData($inventory_stock_out);

        return $stock_info;
    }

    /**
     * 处理库存二维数组
     * @author guanyu
     * @time 2017-12-15
     */
    public function fixStockData($data)
    {
        $new_data = [];
        foreach ($data as $k=>$v) {
            $new_data[$v['region'].' '.$v['goods_id'].' '.$v['storehouse_id'].' '.$v['our_company_id']] = $v['total'];
        }
        return $new_data;
    }

    /**
     * 库存列表
     * @param array $param 查询参数
     * @param $order
     * @author qianbin
     * @time 2018-04-16
     */
    public function getStockList($param = [], $order = 's.id desc')
    {
        $where = [];
        if($param['goods_id']){
            $where['s.goods_id'] = intval($param['goods_id']);
        }
        if($param['stock_type']){
            $where['s.stock_type'] = intval($param['stock_type']);
        }else{
            $where['s.stock_type'] = ['neq',3];
        }
        if($param['region']){
            $where['s.region'] = intval($param['region']);
        }else{
            $where['s.region'] = ['neq', 1877]; // edit xiaowen 2017-8-4 库存查询不包含江阴库存
        }
        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id))$where['s.region'] = ['in',$city_id];
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
        }else{
            $data = $this->getModel('ErpStock')->getStockList($where, $field, $_REQUEST['start'], $_REQUEST['length'], $order, $group);
        }
        if($data['data']){

            $stock_id_where  = array_column($data['data'],'id');

            $cost_data = D('CostInfo')->where(['stock_id' => ['in',$stock_id_where]])->getField('stock_id,price',true);
            $getAllRegion = provinceCityZone()['city'];
            $getAllRegion[1] = '全国';
            foreach($data['data'] as $key=>$value){
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                if($value['region']){
                    $data['data'][$key]['region_name'] = $getAllRegion[$value['region']];
                }else{
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
//                else if($value['stock_type'] == 3){ //服务商
//                    $data['data'][$key]['object_name'] = $data['data'][$key]['facilitator_name'];
//                }
                # 成本字段
                $data['data'][$key]['cost'] = $cost_data[$value['id']] === Null ? '--' : getNum($cost_data[$value['id']]) ;
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
     * 加油网点升量报表
     * @author guanyu
     * @time 2018-04-25
     */
    public function facilitatorSkidStockChecks ($param)
    {
        set_time_limit(0);

        //获取加油网点8月2日期初库存
        $first_stock = $this->searchFacilitatorSkidFirstStock();

        //获取所有加油网点需要维度的库存数据
        $stock_info = $this->searchFacilitatorSkidStockData();

//        var_dump($stock_info);exit;
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
            $arr[$key]['stock_first'] = $first_stock[$value['object_id']][7] ? round($first_stock[$value['object_id']][7],4) : 0;

            $k = $value['goods_id'].' '.$value['object_id'].' '.$value['our_company_id'];
            //盘点调拨入库
            $arr[$key]['allocation_stock_in_num'] = $stock_info['allocation_stock_in_num'][$k] ? getNum(round($stock_info['allocation_stock_in_num'][$k])) : 0;
            //盘点调拨出库
            $arr[$key]['allocation_stock_out_num'] = $stock_info['allocation_stock_out_num'][$k] ? getNum(round($stock_info['allocation_stock_out_num'][$k])) : 0;
            //盘点小微出库
            $arr[$key]['retail_stock_out_num'] = $stock_info['retail_stock_out_num'][$k] ? getNum(round($stock_info['retail_stock_out_num'][$k])) : 0;
            //盘点集团出库
            $arr[$key]['galaxy_stock_out_num'] = $stock_info['galaxy_stock_out_num'][$k] ? getNum(round($stock_info['galaxy_stock_out_num'][$k])) : 0;

            $arr[$key]['inventory_balance'] = round($arr[$key]['stock_first'] + $arr[$key]['allocation_stock_in_num'] - $arr[$key]['allocation_stock_out_num'] - $arr[$key]['retail_stock_out_num'] - $arr[$key]['galaxy_stock_out_num'],4);
        }
        $data['data'] = $arr;
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 统计所有期初数据
     * @author guanyu
     * @time 2018-04-25
     */
    public function searchFacilitatorSkidFirstStock()
    {
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        $filePath1 = './stock_skid_data.xlsx';
        $filePath2 = './stock_skid_data.xls';
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
            $first_stock_data = $currentSheet->toArray();
            unset($first_stock_data[0]);
        }else{
            $first_stock_data = [];
        }

        $first_stock = [];
        foreach ($first_stock_data as $value) {
            $first_stock[trim($value[0])] = $value;
        }
        return $first_stock;
    }

    /**
     * 获取所有加油网点需要维度的库存数据
     * @author guanyu
     * @time 2018-04-25
     */
    public function searchFacilitatorSkidStockData()
    {
        //统计向加油网点的调拨入库
        $where = [
            'storage_status'=>10,
            'stock_type'=>4,
            'storage_type'=>['neq',6],
        ];
        $allocation_stock_in_num = $this->getModel('ErpStockIn')
            ->field('goods_id,storehouse_id,our_company_id,SUM(actual_storage_num) as total')
            ->where($where)
            ->group('goods_id,storehouse_id,our_company_id')
            ->select();
        $stock_info['allocation_stock_in_num'] = $this->fixFacilitatorSkidStockData($allocation_stock_in_num);


        //统计从加油网点的调拨出库
        $where = [
            'outbound_status'=>10,
            'stock_type'=>4,
        ];
        $allocation_stock_out_num = $this->getModel('ErpStockOut')
            ->field('goods_id,storehouse_id,our_company_id,SUM(actual_outbound_num) as total')
            ->where($where)
            ->group('goods_id,storehouse_id,our_company_id')
            ->select();
        $stock_info['allocation_stock_out_num'] = $this->fixFacilitatorSkidStockData($allocation_stock_out_num);


        //统计小微零售出库单
        $where = [
            'so.outbound_status'=>10,
            'o.order_source'=>4,
        ];
        $allocation_stock_out_num = $this->getModel('ErpStockOutRetail')
            ->alias('so')
            ->field('so.goods_id,so.storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
            ->where($where)
            ->join('oil_erp_sale_retail_order o on o.id = so.source_object_id and o.order_number = so.source_number', 'left')
            ->group('so.goods_id,so.storehouse_id,so.our_company_id')
            ->select();
        $stock_info['retail_stock_out_num'] = $this->fixFacilitatorSkidStockData($allocation_stock_out_num);


        //统计集团零售出库单
        $where = [
            'so.outbound_status'=>10,
            'o.order_source'=>5,
        ];
        $allocation_stock_out_num = $this->getModel('ErpStockOutRetail')
            ->alias('so')
            ->field('so.goods_id,so.storehouse_id,so.our_company_id,SUM(actual_outbound_num) as total')
            ->where($where)
            ->join('oil_erp_sale_retail_order o on o.id = so.source_object_id and o.order_number = so.source_number', 'left')
            ->group('so.goods_id,so.storehouse_id,so.our_company_id')
            ->select();
        $stock_info['galaxy_stock_out_num'] = $this->fixFacilitatorSkidStockData($allocation_stock_out_num);

        return $stock_info;
    }

    /**
     * 处理网点库存二维数组
     * @author guanyu
     * @time 2018-04-26
     */
    public function fixFacilitatorSkidStockData($data)
    {
        $new_data = [];
        foreach ($data as $k=>$v) {
            $new_data[$v['goods_id'].' '.$v['storehouse_id'].' '.$v['our_company_id']] = $v['total'];
        }
        return $new_data;
    }

    /**
     * 历史库存列表
     * @author guanyu 2018-05-04
     * @param $param
     */
    public function historyStockList($param = [])
    {
        $where = [];

        //必须按照仓库和商品精确查询
        if (trim($param['storehouse_id'])) {
            $where['s.object_id'] = intval($param['storehouse_id']);
        } else {
            $data['data'] = [];
            return $data;
        }
        if (intval(trim($param['goods_id']))) {
            $where['s.goods_id'] = intval(trim($param['goods_id']));
        } else {
            $data['data'] = [];
            return $data;
        }
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['d.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['d.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['d.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        //当前登陆选择的我方公司
        $where['s.our_company_id'] = session('erp_company_id');

        $field = 'd.*,s.stock_type,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,es.storehouse_name,f.name as facilitator_skid_name';
        if ($param['export']) {
            $data = $this->getModel('ErpStockDetail')->getAllStockDetailList($where, $field);
        } else {
            $data = $this->getModel('ErpStockDetail')->getStockDetailList($where, $field);
        }
        if ($data['data']) {
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['No'] = $value['stock_id'];
                $data['data'][$key]['stock_type'] = stockType($value['stock_type']);
                $data['data'][$key]['change_num'] = getNum($value['change_num']);
                $data['data'][$key]['price'] = $value['price'] > 0 ? getNum($value['price']) : '0.00';
                $data['data'][$key]['after_stock_num'] = $value['after_stock_num'] > 0 ? getNum($value['after_stock_num']) : '0.00';
                $data['data'][$key]['after_price'] = $value['after_price'] > 0 ? getNum($value['after_price']) : '0.00';
                $data['data'][$key]['stock_price'] = $value['stock_price'] > 0 ? getNum($value['stock_price']) : '0.00';
                $data['data'][$key]['storehouse_name'] = $value['stock_type'] != 4 ? $data['data'][$key]['storehouse_name'] : $data['data'][$key]['facilitator_skid_name'];
            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 库存列表 (包含资金占用待提， 资金占用在途)
     * 逻辑：1. 查询采购单符合定金锁价条件的商品数量
     *       2. 查询仓库库存信息
     *       3. 查询销售单符合定金锁价条件的商品数量
     *       4. 统计采购单和销售单商品数量
     *       5. 匹配到仓库库存信息，进行展示
     * @param array $param 查询参数
     * @param $order
     * @author qianbin
     * @time 2018-07-11
     */
    public function getStockAllList($param = [], $order = 's.id desc')
    {
        $where                = [];
        $where_purchase_order = [
            'o.order_status'       => 10,
            'o.pay_type'           => 5,
            'o.pay_status'         => 2,
        ];
        if($param['goods_id']){
            $where['s.goods_id']              = intval($param['goods_id']);
            $where_purchase_order['o.goods_id'] = intval($param['goods_id']);
        }
        if($param['stock_type']){
            $where['s.stock_type']            = intval($param['stock_type']);
        }else{
            $where['s.stock_type']            = ['neq',3];
        }
        if($param['region']){
            $where['s.region']                = intval($param['region']);
            $where_purchase_order['o.region']   = intval($param['region']);
        }else{
            $where['s.region']                = ['neq', 1877]; // edit xiaowen 2017-8-4 库存查询不包含江阴库存
            $where_purchase_order['o.region']   = ['neq', 1877];
        }
        if(intval(trim($param['province'])) && !intval(trim($param['region']))){
            $city_id = D('Area')->where(['parent_id' => intval($param['province']),'area_type' => 2])->getField('id',true);
            if(!empty($city_id)){
                $where['s.region']                = ['in',$city_id];
                $where_purchase_order['o.region']   = ['in',$city_id];
            }
        }
        //按仓库查询，需配合仓库类型
        if($where['s.stock_type'] && $param['storehouse_id']){
            if ($where['s.stock_type'] == 4) {
                $where['s.facilitator_id']  = intval($param['storehouse_id']);
            } else {
                $where['s.object_id']       = intval($param['storehouse_id']);
                $where_purchase_order['o.storehouse_id']  =   intval($param['storehouse_id']);
            }
        }
        $where['s.our_company_id']                  = session('erp_company_id');//增加当前公司帐号条件 edit xiaowen 2017-5-22
        $where_purchase_order['o.our_buy_company_id'] = session('erp_company_id');//增加当前公司帐号条件 edit xiaowen 2017-5-22

        $where['s.status'] = 1;

        # -------------------------------------------采购单-------------------------------------#
        /*
            1.资金占用在途
                a、定金在途库存统计逻辑：按产品+仓库统计满足以下条件的采购单将对应数量进行汇总
                1）采购单订单状态为已确认
                2）付款方式为定金锁价
                3）付款状态满足部分预付，根据对应采购单已付金额/单价=统计出数量
                4）付款状态满足已预付，和部分付款的，根据采购单中的定金比例X采购单订单金额/采购单价 = 统计出数量
                5）将上述2部分数量统计出后进行汇总，并根据产品+仓库关联原有的库存ID进行显示
        */
        $not_reach_prepay_order = [];   # 采购单 未完成定金比例变量
        $reach_prepay_order     = [];   # 采购单 已完成定金比例变量
        $reach_prepay_data      = [];   # 采购单 未完成定金比例变量 拼装key   storehouse_id.'_'.goods_id.'_'.region.'_'.our_buy_company_id
        $not_reach_prepay_data  = [];   # 采购单 未完成定金比例变量 拼装key   仓库id_商品id_城市id_我方公司id
        $purchase_order_data    = [];   # 采购单 资金占用待提

        # 付款未达到定金比例
        $field = 'o.order_number,sum((o.payed_money/10000) / (o.price/10000)) as total_num,o.storehouse_id as object_id,o.goods_id,o.region,
                  o.our_buy_company_id as our_company_id,g.goods_name,g.level,g.goods_code,g.grade,g.source_from,es.storehouse_name,es.type as stock_type';
        $group = 'o.storehouse_id,o.goods_id,o.region,o.our_buy_company_id';
        $not_reach_prepay_order = $this->getModel('ErpPurchaseOrder')->getStockPurchaseOrder($where_purchase_order,$field,'o.id desc',$group);
        # 付款已达到定金比例
        $where_purchase_order['o.pay_status'] = ['in' ,[3,4]];
        $field = 'o.order_number,sum((o.prepay_ratio/10000/100) * (o.order_amount / 10000) / (o.price/10000)) as total_num,
                  o.storehouse_id as object_id,o.goods_id,o.region,o.our_buy_company_id as our_company_id,g.goods_name,g.level,
                  g.goods_code,g.grade,g.source_from,es.storehouse_name,es.type as stock_type';
        $reach_prepay_order     = $this->getModel('ErpPurchaseOrder')->getStockPurchaseOrder($where_purchase_order,$field,'o.id desc',$group);
        # 组装key
        if(count($not_reach_prepay_order) > 0){
            foreach ($not_reach_prepay_order as $k => $v){
                $not_reach_prepay_data[$v['object_id'].'_'.$v['goods_id'].'_'.$v['region'].'_'.$v['our_company_id']] = $v;
            }
        }
        if(count($reach_prepay_order) > 0){
            foreach ($reach_prepay_order as $k => $v){
                $reach_prepay_data[$v['object_id'].'_'.$v['goods_id'].'_'.$v['region'].'_'.$v['our_company_id']] = $v;
            }
        }
        # 判断是否有重复仓库+商品+城市+我方公司，如果有，把数量想加
        if(count($not_reach_prepay_data) > 0 && count($reach_prepay_data) > 0 ){
            foreach ($not_reach_prepay_data as $k => $v){
                array_key_exists($k,$reach_prepay_data) && ($not_reach_prepay_data[$k]['total_num'] = bcadd($v['total_num'],$reach_prepay_data[$k]['total_num'],4));
                unset($reach_prepay_data[$k]);
            }
            $purchase_order_data = array_merge($not_reach_prepay_data,$reach_prepay_data);
        }else{
            $purchase_order_data = array_merge($not_reach_prepay_data,$reach_prepay_data);
        }
        //update by guanyu @ 2017-12-19
        //按加油网点查询，将同一网点在不同地区的库存合并显示
        $group = '';
        if ($where['s.stock_type'] == 4 && $param['search_facilitator_skid']) {

            $group = 's.goods_id,s.object_id,s.stock_type,s.our_company_id';
            unset($where['s.region']);

            $field = 's.*,SUM(s.stock_num) as stock_num,SUM(s.transportation_num) as transportation_num,
            SUM(s.sale_reserve_num) as sale_reserve_num,SUM(s.allocation_reserve_num) as allocation_reserve_num,
            SUM(s.sale_wait_num) as sale_wait_num,SUM(s.allocation_wait_num) as allocation_wait_num,
            SUM(s.available_num) as available_num, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,es.storehouse_name,
            rg.available_sale_stock, rg.available_use_stock, su.supplier_name';
        } else {
            $field = 's.*, g.goods_name, g.level, g.goods_code, g.grade,g.source_from,es.storehouse_name,
            rg.available_sale_stock, rg.available_use_stock, su.supplier_name';
        }
        $data = $this->getModel('ErpStock')->getStockList($where, $field, $param['start'], $param['length'], $order, $group);

        # ------------------------------匹配存在 在途库存，但未生成库存记录的数据--------------------------------------#
        if(intval($param['start']) == 0) {
            # 导出防止重复加载 未生成库存记录的数据
            $data['data'] = $this->getStockInfoByorder($where,$purchase_order_data,$data['data']);
        }
        if($data['data']){

            # 查询对应仓库
            # 包含了加油网点
            $store_hourse_data    = array_unique(array_column($data['data'],'object_id'));
            $goods_ids_data       = array_unique(array_column($data['data'],'goods_id'));
            $region_ids_data      = array_unique(array_column($data['data'],'region'));
            $our_company_ids_data = array_unique(array_column($data['data'],'our_company_id'));

            # ---------------------------销售单------------------------------------------------ #
            /*
                2.资金占用待提
                    b、定金待提库存统计逻辑：按产品+仓库统计满足以下条件的销售单将对应数量进行汇总
                    1）销售单订单状态为已确认
                    2）付款方式为定金锁价
                    3）收款状态满足部分预收，根据对应销售单已收金额/单价=统计出数量
                    4）收款状态满足已预收，和部分收款的，根据对应销售单中的定金比例X销售单订单金额/销售单价 = 统计出数量
                    5）将上述2部分数量统计出后进行汇总，并根据产品+仓库关联原有的库存ID进行显示

             */
            $where_sale_order = [
                'storehouse_id'     => ['in',$store_hourse_data],
                'goods_id'          => ['in',$goods_ids_data],
                'region'            => ['in',$region_ids_data],
                'our_company_id'    => ['in',$our_company_ids_data],
                'order_status'      => 10,
                'pay_type'          => 5,
                'collection_status' => 2,
            ];

            $not_reach_prepay_order = [];   # 销售单 未完成定金比例变量
            $reach_prepay_order     = [];   # 销售单 已完成定金比例变量
            $reach_prepay_data      = [];   # 销售单 未完成定金比例变量 拼装key   storehouse_id.'_'.goods_id.'_'.region.'_'.our_company_id
            $not_reach_prepay_data  = [];   # 销售单 未完成定金比例变量 拼装key   仓库id_商品id_城市id_我方公司id
            $sale_order_data        = [];   # 销售单 资金占用待提

            # 收款未达到定金比例
            $not_reach_prepay_order = $this->getModel('ErpSaleOrder')
                                        ->field('id,sum((collected_amount/10000) / (price/10000)) as total_num,storehouse_id,goods_id,region,our_company_id')
                                        ->where($where_sale_order)
                                        ->group('storehouse_id,goods_id,region,our_company_id')
                                        ->select();
            # 收款已达到定金比例
            $where_sale_order['collection_status'] = ['in' ,[3,4]];
            $reach_prepay_order     = $this->getModel('ErpSaleOrder')
                                        ->field('id,sum((prepay_ratio/10000/100) * (order_amount / 10000) / (price/10000)) as total_num,storehouse_id,goods_id,region,our_company_id')
                                        ->where($where_sale_order)
                                        ->group('storehouse_id,goods_id,region,our_company_id')
                                        ->select();

            # 组装key
            if(count($not_reach_prepay_order) > 0){
                foreach ($not_reach_prepay_order as $k => $v){
                    $not_reach_prepay_data[$v['storehouse_id'].'_'.$v['goods_id'].'_'.$v['region'].'_'.$v['our_company_id']] = $v['total_num'];
                }
            }
            if(count($reach_prepay_order) > 0){
                foreach ($reach_prepay_order as $k => $v){
                    $reach_prepay_data[$v['storehouse_id'].'_'.$v['goods_id'].'_'.$v['region'].'_'.$v['our_company_id']] = $v['total_num'];
                }
            }
            # 判断是否有重复仓库+商品+城市+我方公司，如果有，把数量想加
            if(count($not_reach_prepay_data) > 0 && count($reach_prepay_data) > 0 ){
                foreach ($not_reach_prepay_data as $k => $v){
                    array_key_exists($k,$reach_prepay_data) && ($not_reach_prepay_data[$k] = bcadd($v,$reach_prepay_data[$k],4));
                    unset($reach_prepay_data[$k]);
                }
                $sale_order_data = array_merge($not_reach_prepay_data,$reach_prepay_data);
            }else{
                $sale_order_data = array_merge($not_reach_prepay_data,$reach_prepay_data);
            }

            # 待提 在途占用资金 end ------------------------------------------------------------------------------------------------------
            $stock_id_where  = array_column($data['data'],'id');
            $cost_data       = [];
            if(count($stock_id_where) > 0){
                $cost_data   = D('CostInfo')->where(['stock_id' => ['in',$stock_id_where]])->getField('stock_id,price',true);
            }
            $getAllRegion    = provinceCityZone()['city'];
            $getAllRegion[1] = '全国';

            foreach($data['data'] as $key=>$value){
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                if($value['region']){
                    $data['data'][$key]['region_name'] = $getAllRegion[$value['region']];
                }else{
                    $data['data'][$key]['region_name'] = '--';
                }
                $data['data'][$key]['id']        = isset($value['id']) ? $value['id'] : '0';
                $data['data'][$key]['stock_type'] = stockType($value['stock_type']);
                $data['data'][$key]['stock_num'] = round(getNum($value['stock_num'])*10000)/10000;
                $data['data'][$key]['transportation_num'] = getNum($value['transportation_num']);
                $data['data'][$key]['sale_reserve_num'] = getNum($value['sale_reserve_num']);
                $data['data'][$key]['allocation_reserve_num'] = getNum($value['allocation_reserve_num']);
                $data['data'][$key]['sale_wait_num'] = round(getNum($value['sale_wait_num'])*10000)/10000;
                $data['data'][$key]['allocation_wait_num'] = round(getNum($value['allocation_wait_num']) * 10000)/10000;
                $data['data'][$key]['facilitator_name'] = $value['supplier_name'] ? $value['supplier_name'] : '—';
                $data['data'][$key]['current_available_sale_num'] = $value['stock_type'] == 1 ? getNum($value['available_sale_stock'] + $value['available_num'] - $value['available_use_stock']) : 0; //当前可售库存
                //$data['data'][$key]['sal_available_num'] = $this->getStockSaleNum(['goods_id'=>$value['goods_id'], 'object_id'=>$value['object_id'], $value['stock_type'], $value['region']]);
                $data['data'][$key]['object_name'] = $data['data'][$key]['storehouse_name'];
                # 成本字段
                $data['data'][$key]['cost'] = $cost_data[$value['id']] === Null ? '--' : getNum($cost_data[$value['id']]) ;

                # 资金占用在途 qianbin 2018.07.24
                # 资金占用待提
                if($value['stock_type'] != 4){
                    $sale_wait_num_prepay     = $sale_order_data[$value['object_id'].'_'.$value['goods_id'].'_'.$value['region'].'_'.$value['our_company_id']];
                    $purchase_wait_num_prepay = isset($value['total_num']) ? $value['total_num'] : $purchase_order_data[$value['object_id'].'_'.$value['goods_id'].'_'.$value['region'].'_'.$value['our_company_id']]['total_num'];
                    $data['data'][$key]['purchase_wait_num_prepay'] = empty($purchase_wait_num_prepay) ? 0 : floatval($purchase_wait_num_prepay);
                    $data['data'][$key]['sale_wait_num_prepay']     = empty($sale_wait_num_prepay) ? 0 : floatval($sale_wait_num_prepay);
                }else{
                    $data['data'][$key]['purchase_wait_num_prepay'] = 0;
                    $data['data'][$key]['sale_wait_num_prepay']     = 0;
                }

                # 可用库存修改 ：可用库存 = 物理 + 在途 + 定金在途库存 - 销售预留 -配货预留 - 销售待提 - 配合待提 - 定金待提库存
                # (仅修改资金占用库存这个页面)
                # qianbin 2018.07.25
                $data['data'][$key]['available_num'] = $data['data'][$key]['stock_num']
                    + $data['data'][$key]['transportation_num']
                    + $data['data'][$key]['purchase_wait_num_prepay']
                    - $data['data'][$key]['sale_reserve_num']
                    - $data['data'][$key]['allocation_wait_num']
                    - $data['data'][$key]['sale_wait_num']
                    - $data['data'][$key]['allocation_wait_num']
                    - $data['data'][$key]['sale_wait_num_prepay'];
            }

        }else{
            $data['data'] = [];
            $data['recordsTotal'] = 0;
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;

    }


    /*
     * ------------------------------------------
     * 获取存在在途库存信息，但未生成库存信息的仓库
     * Author：qianbin        Time：2018-07-24
     * ------------------------------------------
     */
    private function getStockInfoByorder($where = [], $purchase_order = [],$stock_orders = [])
    {
        if(count($purchase_order) <= 0) return $stock_orders;

        $stock_data = [];
        $stock_diffent_data = [];
        # 查询仓库信息，获取五要素，仓库id + 商品id + stock_type + 城市id + 公司账套id
        $field = 's.goods_id,s.object_id,s.stock_type,s.region,s.our_company_id';
        $order = 's.id desc';
        $group = 's.goods_id,s.object_id,s.stock_type,s.our_company_id';
        # 排除服务网点的仓库类型
        $where['s.stock_type'] = ['neq' , 4 ];
        $data = $this->getModel('ErpStock')->getStockData($where ,$field,$order, $group);
        # 拼装五要素为key
        if(count($data) > 0 ){
            foreach ($data as $k => $v){
                $stock_data[$v['object_id'].'_'.$v['goods_id'].'_'.$v['region'].'_'.$v['our_company_id']] = $v;
            }
        }
        # 和采购单查询的库存取差集
        $stock_diffent_data = array_diff_key($purchase_order,$stock_data);
        # 重置数组为索引数组
        $reset_stock_diffent_data = array_values($stock_diffent_data);
        $stock_orders             = array_merge( $reset_stock_diffent_data,$stock_orders);
        return $stock_orders;
    }

    /**
     * 库存出入库汇总报表
     * @author guanyu
     * @time 2018-08-09
     */
    public function stockInOutSummary($param)
    {
        $where = [];

        if (isset($param['start_time']) && isset($param['end_time'])) {
            $where['d.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
        }
        if (isset($param['search_storehouse'])) {
            $storehouse = implode(',',$param['search_storehouse']);
            $where['s.object_id'] = ['in',$storehouse];
        }
        if (($param['goods_id']) != 0) {
            $where['s.goods_id'] = $param['goods_id'];
        }

        if (!isset($where['d.create_time'])) {
            $data['data'] = [];
            $data['draw'] = $_REQUEST['draw'];
            $data['recordsFiltered'] = 0;
            $data['recordsTotal'] = 0;
            return $data;
        }
        $where['s.status'] = 1;
        $where['s.our_company_id'] = session('erp_company_id');

        $order = 's.id asc';
        $group = 'd.stock_id';

        if ($param['stock_type'] == 4) {
            $where['s.stock_type'] = 4;
            //查询表
            $table = 'ErpStockDetailRetail';
            //链接表
            $join_table = 'oil_erp_stock_detail_retail';
            //仓库表
            $object_table = 'oil_erp_storehouse sh on s.object_id = sh.id';
            $last_field = 'd.stock_id,d.after_stock_num,stock_price,sh.storehouse_name AS object_name,g.goods_code';
        } else {
            $where['s.stock_type'] = ['neq',4];
            //查询表
            $table = 'ErpStockDetail';
            //链接表
            $join_table = 'oil_erp_stock_detail';
            //仓库表
            $object_table = 'oil_erp_storehouse sh on s.object_id = sh.id';
            $last_field = 'd.stock_id,d.after_stock_num,stock_price,sh.storehouse_name AS object_name,g.goods_code';
        }

        //期初数据
        $first_field = 'd.stock_id,d.after_stock_num,stock_price';
        $first_where = $where;
        unset($first_where['d.create_time']);
        $first_data = $this->getModel($table)->alias('d')
            ->where($first_where)
            ->join("(SELECT stock_id, MAX(id) AS tmp_id FROM ".$join_table." WHERE create_time < '".$param['start_time']."' GROUP BY stock_id ORDER BY create_time DESC) tmp on d.stock_id = tmp.stock_id AND d.id = tmp.tmp_id", 'inner')
            ->join('oil_erp_stock s on d.stock_id = s.id', 'left')
            ->order($order)
            ->group($group)
            ->getField($first_field);

        //期末数据
        $last_where = $where;
        unset($last_where['d.create_time']);
        $last_data = $this->getModel($table)->alias('d')
            ->where($last_where)
            ->join("(SELECT stock_id, MAX(id) AS tmp_id FROM ".$join_table." WHERE create_time < '".$param['end_time']."' GROUP BY stock_id ORDER BY create_time DESC) tmp on d.stock_id = tmp.stock_id AND d.id = tmp.tmp_id", 'inner')
            ->join('oil_erp_stock s on d.stock_id = s.id', 'left')
            ->join('oil_erp_goods g on g.id = s.goods_id', 'left')
            ->join($object_table, 'left')
            ->order($order)
            ->group($group)
            ->getField($last_field);

        //变动数据
        $change_field = 'd.stock_id, 
            SUM(IF(d.type != 2, d.change_num, 0)) AS total_out_stock_num, 
            SUM(IF(d.type != 2, d.change_num * d.price / 10000, 0)) AS total_out_stock_price, 
            SUM(IF(d.type = 2, d.change_num, 0)) AS total_in_stock_num, 
            SUM(IF(d.type = 2, d.change_num * d.price / 10000, 0)) AS total_in_stock_price';
        $change_data = $this->getModel($table)->alias('d')
            ->where($where)
            ->join('oil_erp_stock s on d.stock_id = s.id', 'left')
            ->order($order)
            ->group($group)
            ->getField($change_field);

        if (!empty($last_data)) {
            $index = 0;
            $summary_data = [];
            $sum_first_stock_num = 0;
            $sum_first_stock_price = 0;
            $sum_in_stock_num = 0;
            $sum_in_stock_price = 0;
            $sum_out_stock_num = 0;
            $sum_out_stock_price = 0;
            $sum_last_stock_num = 0;
            $sum_last_stock_price = 0;
            foreach ($last_data as $key => $value) {
                $summary_data['data'][$index]['id'] = $key;
                $summary_data['data'][$index]['object_name']            = $value['object_name'];
                $summary_data['data'][$index]['goods_code']             = $value['goods_code'];
                $summary_data['data'][$index]['first_stock_num']        = getNum($first_data[$key]['after_stock_num']);
                $summary_data['data'][$index]['first_stock_price']      = getNum($first_data[$key]['stock_price']);
                $summary_data['data'][$index]['total_in_stock_num']     = getNum($change_data[$key]['total_in_stock_num']);
                $summary_data['data'][$index]['total_in_stock_price']   = getNum($change_data[$key]['total_in_stock_price']);
                $summary_data['data'][$index]['total_out_stock_num']    = getNum($change_data[$key]['total_out_stock_num']);
                $summary_data['data'][$index]['total_out_stock_price']  = getNum($change_data[$key]['total_out_stock_price']);
                $summary_data['data'][$index]['last_stock_num']         = getNum($last_data[$key]['after_stock_num']);
                $summary_data['data'][$index]['last_stock_price']       = getNum($last_data[$key]['stock_price']);

                $sum_first_stock_num += $summary_data['data'][$index]['first_stock_num'];
                $sum_first_stock_price += $summary_data['data'][$index]['first_stock_price'];
                $sum_in_stock_num += $summary_data['data'][$index]['total_in_stock_num'];
                $sum_in_stock_price += $summary_data['data'][$index]['total_in_stock_price'];
                $sum_out_stock_num += $summary_data['data'][$index]['total_out_stock_num'];
                $sum_out_stock_price += $summary_data['data'][$index]['total_out_stock_price'];
                $sum_last_stock_num += $summary_data['data'][$index]['last_stock_num'];
                $sum_last_stock_price += $summary_data['data'][$index]['last_stock_price'];

                $index++;
            }
            $summary_data['data'][0]['sumTotal']['sum_first_stock_num']     = $sum_first_stock_num;
            $summary_data['data'][0]['sumTotal']['sum_first_stock_price']   = $sum_first_stock_price;
            $summary_data['data'][0]['sumTotal']['sum_in_stock_num']        = $sum_in_stock_num;
            $summary_data['data'][0]['sumTotal']['sum_in_stock_price']      = $sum_in_stock_price;
            $summary_data['data'][0]['sumTotal']['sum_out_stock_num']       = $sum_out_stock_num;
            $summary_data['data'][0]['sumTotal']['sum_out_stock_price']     = $sum_out_stock_price;
            $summary_data['data'][0]['sumTotal']['sum_last_stock_num']      = $sum_last_stock_num;
            $summary_data['data'][0]['sumTotal']['sum_last_stock_price']    = $sum_last_stock_price;
        } else {
            $summary_data['data'] = [];
        }
        $summary_data['recordsFiltered'] = $summary_data['recordsTotal'] = count($last_data);
        $summary_data['draw'] = $_REQUEST['draw'];
        return $summary_data;
    }

    /**
     * 库存变动明细报表
     * @author guanyu
     * @time 2018-08-09
     */
    public function stockInOutDetail($param)
    {
        $where = [];

        if (isset($param['start_time']) && isset($param['end_time'])) {
            $where['d.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
        }
        if ($param['search_storehouse'] != 0) {
            $where['s.object_id'] = $param['search_storehouse'];
        }
        if ($param['goods_id'] != 0) {
            $where['s.goods_id'] = $param['goods_id'];
        }

        if (count($where) < 3) {
            $data['data'] = [];
            $data['draw'] = $_REQUEST['draw'];
            $data['recordsFiltered'] = 0;
            $data['recordsTotal'] = 0;
            return $data;
        }
        $where['s.status'] = 1;
        $where['s.our_company_id'] = session('erp_company_id');

        if ($param['stock_type'] == 4) {
            $where['s.stock_type'] = 4;
            $field = 'd.*,sh.storehouse_name AS object_name,g.goods_code';
            $detail_data = $this->getModel('ErpStockDetailRetail')->getStockDetailRetailList($where, $field, $param['start'], $param['length'], 'd.id asc');
        } else {
            $where['s.stock_type'] = ['neq',4];
            $field = 'd.*,es.storehouse_name AS object_name,g.goods_code';
            $detail_data = $this->getModel('ErpStockDetail')->getStockDetailList($where, $field, $param['start'], $param['length'], 'd.id asc');
        }
        if (!empty($detail_data['data'])) {
            $index = 0;
            foreach ($detail_data['data'] as $key => $value) {
                $detail_data['data'][$index]['id']                  = $value['id'];
                $detail_data['data'][$index]['create_time']         = $value['create_time'];
                $detail_data['data'][$index]['type_font']           = stockDetailType($value['type']);
                $detail_data['data'][$index]['before_stock_num']    = getNum($detail_data['data'][$index]['before_stock_num']);
                $detail_data['data'][$index]['change_num']          = getNum($detail_data['data'][$index]['change_num']);
                $detail_data['data'][$index]['change_stock_price']  = getNum($detail_data['data'][$index]['change_num'] * $detail_data['data'][$index]['price']);
                $detail_data['data'][$index]['after_stock_num']     = getNum($detail_data['data'][$index]['after_stock_num']);
                $detail_data['data'][$index]['stock_price']         = getNum($detail_data['data'][$index]['stock_price']);
                if ($param['stock_type'] == 4) {
                    $before_detail = $this->getModel('ErpStockDetailRetail')->where(['stock_id'=>$value['stock_id'],'id'=>['lt',$value['id']]])->order('id desc')->find();
                } else {
                    $before_detail = $this->getModel('ErpStockDetail')->where(['stock_id'=>$value['stock_id'],'id'=>['lt',$value['id']]])->order('id desc')->find();
                }
                $detail_data['data'][$index]['before_stock_price']  = empty($before_detail) ? 0 : getNum($before_detail['stock_price']);
                $index++;
            }
        } else {
            $detail_data['data'] = [];
        }
        $detail_data['recordsFiltered'] = $detail_data['recordsTotal'];
        $detail_data['draw'] = $_REQUEST['draw'];
        return $detail_data;
    }
    /**
     * 批发销售分析报表
     * @author guanyu
     * @time 2018-08-28
     */
    public function saleAnalysis($param)
    {
        set_time_limit(0);
        $where = [];

        if (isset($param['start_time']) || isset($param['end_time'])) {
            if ($param['type'] == 'whole') {
                $where['o.add_order_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            } else if ($param['type'] == 'retail') {
                $where['o.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        if (isset($param['region'])) {
            $where['o.region'] = $param['region'];
        }
        if (($param['goods_id']) != 0) {
            $where['o.goods_id'] = $param['goods_id'];
        }

        if (count($where) < 3) {
            $data['data'] = [];
            $data['draw'] = $_REQUEST['draw'];
            $data['recordsFiltered'] = 0;
            $data['recordsTotal'] = 0;
            return $data;
        }
        $where['o.order_status'] = 10;
        $where['o.our_company_id'] = session('erp_company_id');
        $where['o.outbound_quantity'] = ['neq',0];

        if ($param['type'] == 'whole') {
            $where['o.order_type'] = 1;
            $field = 'o.*,g.goods_code,SUM(so.actual_outbound_num * so.cost) as sale_cost';
            $order_data = $this->getModel('ErpSaleOrder')->saleAnalysisWhole($where, $field, $param['start'], $param['length']);
        } else if ($param['type'] == 'retail') {
            $field = 'o.*,g.goods_code,SUM(so.actual_outbound_litre * (so.cost * so.outbound_density / 1000)) as sale_cost';
            $order_data = $this->getModel('ErpSaleRetailOrder')->saleAnalysisRetail($where, $field, $param['start'], $param['length']);
        }
        if (!empty($order_data['data'])) {
            $cityArr = provinceCityZone()['city'];
            $index = 0;
            foreach ($order_data['data'] as $key => $value) {
                if ($key == 0) {
                    $order_data['data'][$key]['sumTotal'] = $order_data['sumTotal'];
                    $order_data['data'][$key]['sumTotal']['total_gross_profit_rate'] = round($order_data['data'][$key]['sumTotal']['total_gross_profit_rate'] * 100,2) . '%';
                }
                $order_data['data'][$index]['id']                   = $value['id'];
                $order_data['data'][$index]['create_time']          = $value['create_time'];
                $order_data['data'][$index]['region_name']          = $cityArr[$value['region']];
                $order_data['data'][$index]['order_number']         = $value['order_number'];
                $order_data['data'][$index]['order_type']           = '销售单';
                $order_data['data'][$index]['goods_code']           = $value['goods_code'];
                $order_data['data'][$index]['outbound_quantity']    = getNum($value['outbound_quantity']);
                $order_data['data'][$index]['outbound_num_litre']   = getNum($value['outbound_num_litre']);
                $order_data['data'][$index]['price']                = getNum($value['price']);
                $order_data['data'][$index]['order_amount']         = getNum($value['order_amount']);
                $order_data['data'][$index]['sale_cost']            = getNum(getNum($value['sale_cost']));
                $order_data['data'][$index]['gross_profit']         = $order_data['data'][$index]['order_amount'] - $order_data['data'][$index]['sale_cost'];
                $order_data['data'][$index]['gross_profit_rate']    = $order_data['data'][$index]['gross_profit'] / $order_data['data'][$index]['order_amount'] * 100 . '%';
                $index++;
            }
        } else {
            $order_data['data'] = [];
        }
        $order_data['recordsFiltered'] = $order_data['recordsTotal'];
        $order_data['draw'] = $_REQUEST['draw'];
        return $order_data;
    }

    /**
     * 历史库存查询
     * @author guanyu
     * @time 2018-08-30
     */
    public function historyStockSearch($param)
    {
        $where = [];

        if (isset($param['search_time'])) {
            $where['d.create_time'] = ['lt', date('Y-m-d H:i:s', strtotime(trim($param['search_time']))+3600*24)];
        }
        if ($param['search_storehouse'] != 0) {
            $where['s.object_id'] = $param['search_storehouse'];
        }
        if (($param['goods_id']) != 0) {
            $where['s.goods_id'] = $param['goods_id'];
        }
        if (!isset($where['d.create_time'])) {
            $data['data'] = [];
            $data['draw'] = $_REQUEST['draw'];
            $data['recordsFiltered'] = 0;
            $data['recordsTotal'] = 0;
            return $data;
        }
        $where['s.status'] = 1;
        $where['s.our_company_id'] = session('erp_company_id');

        $order = 's.id asc';
        $group = 'd.stock_id';

        if ($param['stock_type'] == 4) {
            $where['s.stock_type'] = 4;
            //查询表
            $table = 'ErpStockDetailRetail';
            //链接表
            $join_table = 'oil_erp_stock_detail_retail';
            //仓库表
            $object_table = 'oil_erp_storehouse sh on s.object_id = sh.id';
            $last_field = 'd.stock_id,d.after_stock_num,stock_price,sh.storehouse_name AS object_name,g.goods_code';
        } else {
            $where['s.stock_type'] = ['neq',4];
            //查询表
            $table = 'ErpStockDetail';
            //链接表
            $join_table = 'oil_erp_stock_detail';
            //仓库表
            $object_table = 'oil_erp_storehouse sh on s.object_id = sh.id';
            $last_field = 'd.stock_id,d.after_stock_num,stock_price,sh.storehouse_name AS object_name,g.goods_code';
        }
        //期末数据
        $last_data = $this->getModel($table)->alias('d')
            ->where($where)
            ->join("(SELECT stock_id, MAX(id) AS tmp_id FROM ".$join_table." WHERE create_time < '".$param['search_time']."' GROUP BY stock_id ORDER BY create_time DESC) tmp on d.stock_id = tmp.stock_id AND d.id = tmp.tmp_id", 'inner')
            ->join('oil_erp_stock s on d.stock_id = s.id', 'left')
            ->join('oil_erp_goods g on g.id = s.goods_id', 'left')
            ->join($object_table, 'left')
            ->order($order)
            ->group($group)
            ->getField($last_field);

        if (!empty($last_data)) {
            $index = 0;
            $summary_data = [];
            $sum_last_stock_num = 0;
            foreach ($last_data as $key => $value) {
                $summary_data['data'][$index]['id'] = $key;
                $summary_data['data'][$index]['object_name']            = $value['object_name'];
                $summary_data['data'][$index]['goods_code']             = $value['goods_code'];
                $summary_data['data'][$index]['last_stock_num']         = getNum($last_data[$key]['after_stock_num']);

                $sum_last_stock_num += $summary_data['data'][$index]['last_stock_num'];

                $index++;
            }
            $summary_data['data'][0]['sumTotal']['sum_last_stock_num']      = $sum_last_stock_num;
        } else {
            $summary_data['data'] = [];
        }
        $summary_data['recordsFiltered'] = $summary_data['recordsTotal'] = count($last_data);
        $summary_data['draw'] = $_REQUEST['draw'];
        return $summary_data;
    }
}

?>
