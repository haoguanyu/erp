<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;


class DataEvent extends BaseController
{

    /**
     * 导入期初库存到临时盘点表
     * @param $data
     * @author guanyu
     * @time 2018-09-13
     */
    public function importInitStockDataToInventory()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        //一级仓期初
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        $file_path1 = './stock_data_final.xlsx';
        $file_path2 = './stock_data_final.xls';
        $file_path = is_file($file_path1) ? $file_path1 : $file_path2;
        if (is_file($file_path)) {
            $php_reader = new \PHPExcel_Reader_Excel2007();
            if (!$php_reader->canRead($file_path)) {
                $php_reader = new \PHPExcel_Reader_Excel5();
                if (!$php_reader->canRead($file_path)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $php_reader = $php_reader->load($file_path);
            # @读取excel文件中的第一个工作表
            $current_sheet = $php_reader->getSheet(0);
            $first_stock_data = $current_sheet->toArray();
            unset($first_stock_data[0]);
        } else {
            $first_stock_data = [];
        }

        $detail_status_all = true;
        $inventory_data = [];
        $index = 0;
        foreach ($first_stock_data as $key => $value) {
            $stock_data = $this->getModel('ErpStock')
                ->alias('s')
                ->join('oil_erp_storehouse es on s.object_id = es.id and s.stock_type != 4', 'left')
                ->join('oil_erp_goods as g on s.goods_id = g.id','left')
                ->field('s.*')
                ->where(['es.storehouse_name'=>trim($value[2]),'g.goods_code'=>trim($value[3]),'our_company_id'=>3372])
                ->find();
            $detail_data = [
                'stock_id' => $stock_data['id'],
                'type' => 3,
                'source_number' => '',
                'source_order_number' => '',
                'change_num' => setNum($value[8]),
                'price' => 0,
                'before_stock_num' => 0,
                'after_stock_num' => setNum($value[8]),
                'before_price' => 0,
                'after_price' => 0,
                'stock_price' => 0,
                'create_time' => currentTime(),
                'operator_id' => '',
                'operator' => '',
            ];
            $detail_status = $this->getModel('ErpStockDetail')->add($detail_data);
            $detail_status_all = $detail_status_all && $detail_status ? true : false;
            $inventory_data[$index]['stock_id'] = $stock_data['id'];
            $inventory_data[$index]['stock_num'] = setNum($value[8]);
            $inventory_data[$index]['price'] = 0;
            $inventory_data[$index]['update_time'] = currentTime();
            $index++;
        }
        $status_inventory = D('ErpStockInventory')->addAll($inventory_data);
        echo $detail_status_all && $status_inventory ? "期初库存导入成功\n\r": "期初库存导入失败\n\r";


        //二级仓期初
        $first_stock = $this->getModel('ErpStockSkidData')->where(['stock_num'=>['lt',0]])->select();

        $detail_status_all = true;
        $inventory_data = [];
        $index = 0;
        foreach ($first_stock as $key => $value) {
            $detail_data = [
                'stock_id' => $value['stock_id'],
                'type' => 3,
                'source_number' => '',
                'source_order_number' => '',
                'change_num' => $value['stock_num'],
                'price' => 0,
                'before_stock_num' => 0,
                'after_stock_num' => $value['stock_num'],
                'before_price' => 0,
                'after_price' => 0,
                'stock_price' => 0,
                'create_time' => currentTime(),
                'operator_id' => '',
                'operator' => '',
            ];
            $detail_status = $this->getModel('ErpStockDetailRetail')->add($detail_data);
            $detail_status_all = $detail_status_all && $detail_status ? true : false;
            $inventory_data[$index]['stock_id'] = $value['stock_id'];
            $inventory_data[$index]['stock_num'] = $value['stock_num'];
            $inventory_data[$index]['price'] = 0;
            $inventory_data[$index]['update_time'] = currentTime();
            $index++;
        }
        $status_inventory = D('ErpStockInventoryRetail')->addAll($inventory_data);
        echo $detail_status_all ? "网点负期初导入成功\n\r": "网点负期初导入失败\n\r";
        echo $status_inventory ? "期初库存导入成功\n\r": "期初库存导入失败\n\r";
    }

    /**
     * 导入历史出库明细数据（2018-06-12 到 2017-08-02）
     * @time 2018-09-27
     * @author guanyu
     *
     * $param[
     * start:2018-03-11 22:00:00 开始时间
     * end:2018-04-01 00:00:00 结束时间
     * ]
     */
    public function handleHistoryStockDataFirst($param)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        //==========================================获取临时盘点库存数据start===========================================
        $stock_inventory_field = 'sin.*,s.goods_id,s.object_id,s.our_company_id,s.stock_type';
        $stock_inventory_data = D('ErpStockInventory')->alias('sin')
            ->field($stock_inventory_field)
            ->join('oil_erp_stock s on sin.stock_id = s.id', 'left')
            ->select();
        $stock_inventory = [];
        if ($stock_inventory_data) {
            foreach ($stock_inventory_data as $value) {
                $stock_inventory[$value['object_id'].$value['our_company_id'].$value['goods_id'].$value['stock_type']] = $value;
            }
        }

        $stock_inventory_retail_field = 'sin.*,s.goods_id,s.object_id,s.our_company_id,s.stock_type';
        $stock_inventory_retail_data = D('ErpStockInventoryRetail')->alias('sin')
            ->field($stock_inventory_retail_field)
            ->join('oil_erp_stock s on sin.stock_id = s.id', 'left')
            ->select();
        $stock_inventory_retail = [];
        if ($stock_inventory_retail_data) {
            foreach ($stock_inventory_retail_data as $value) {
                $stock_inventory_retail[$value['object_id'].$value['our_company_id'].$value['goods_id'].$value['stock_type']] = $value;
            }
        }
        //============================================获取临时盘点库存数据end===========================================

        //交易员id对应姓名数组
        $dealer = dealerIdTologinName();

        //=================================获取出入库数据并按照时间及出入库类型排序start================================
//        $stock_out = $this->selectAllStockOut($param);
//
//        $stock_in = $this->selectAllStockIn($param);
//
//        $stock_out_retail = $this->selectAllRetailOut($param);

        $stock_data = M('ErpStockDetailView')
            ->field(true)
            ->where(['_string'=>'IF(audit_time = "0000-00-00 00:00:00",create_time,audit_time) <= "2018-03-11 22:00:00"'])
            ->order("CONCAT(IF(audit_time = '0000-00-00 00:00:00',create_time,audit_time),IF(type = 1,IF(storage_type = 2,2,4),IF(storage_type = 2,3,1)))")
            ->limit($param['start'], $param['end'])
            ->select();
//        var_dump($stock_data);exit;

//        $stock_info = array_merge($stock_out,$stock_in,$stock_out_retail);
//        $stock_data = [];
//        foreach ($stock_info as $value) {
//            $type = $value['type'] == 1 ? ($value['outbound_type'] == 2 ? 2 : 4) : ($value['storage_type'] == 2 ? 3 : 1);
//            $sort = $value['audit_time'] == '0000-00-00 00:00:00' ? $value['create_time'] : $value['audit_time'].$type.$value['stock_type'];
//            if (isset($stock_data[$sort])) {
//                $sort .= substr($value['source_number'],5);
//            }
//            $stock_data[$sort] = $value;
//        }
//        ksort($stock_data);
        //=================================获取出入库数据并按照时间及出入库类型排序end==================================

        M()->startTrans();
        $status_all = true;
        $detail_data = [];
        $detail_retail_data = [];
        $adjustment_stock_in_arr = [];
        $index = 0;
        foreach ($stock_data as $v) {
            $key = $v['storehouse_id'].$v['our_company_id'].$v['goods_id'].$v['stock_type'];

            if ($v['stock_type'] == 4) {
                $inventory = $stock_inventory_retail;
                $detail = $detail_retail_data;
            } else {
                $inventory = $stock_inventory;
                $detail = $detail_data;
            }

            //判断是否存在临时盘点库存
            if (isset($inventory[$key])) {
                $before_stock_num = $inventory[$key]['stock_num'];
            }
            //无临时盘点库存，无期初库存，新库存插入到临时盘点表中
            else {
                $before_stock_num = 0;
                $inventory[$key]['stock_num'] = 0;
                $inventory[$key]['stock_id'] = $v['stock_id'];
            }

            //出库单处理
            if ($v['type'] == 1) {
                if ($v['change_num'] > 0) {
                    //当前物理库存是否满足出库，若不满足，则拉后一条入库单来优先入库
                    if (bccomp($inventory[$key]['stock_num'],$v['change_num']) === -1 && $v['stock_type'] != 4) {
                        $adjustment_stock_in_where = [
                            'storehouse_id'     => $v['storehouse_id'],
                            'our_company_id'    => $v['our_company_id'],
                            'goods_id'          => $v['goods_id'],
                            'stock_type'        => $v['stock_type'],
                            'storage_status'    => 10,
                            'is_reverse'        => 2,
                            '_string'           => 'IF(audit_time = "0000-00-00 00:00:00",create_time,audit_time) > "'.
                                ($v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time']).'"',
                        ];
                        if ($v['stock_type'] == 4) {
                            unset($adjustment_stock_in_where['stock_type']);
                        }
                        $adjustment_stock_in = $this->getModel('ErpStockIn')->where($adjustment_stock_in_where)->select();
                        foreach ($adjustment_stock_in as $val) {
                            $after_stock_num = $inventory[$key]['stock_num'] += $val['actual_storage_num'];
                            $detail[$index]['stock_id']            = $v['stock_id'];
                            $detail[$index]['type']                = 2;
                            $detail[$index]['source_number']       = $val['storage_code'];
                            $detail[$index]['source_order_number'] = $val['source_number'];
                            $detail[$index]['change_num']          = $val['actual_storage_num'];
                            $detail[$index]['price']               = $v['price'];
                            $detail[$index]['before_stock_num']    = $before_stock_num;
                            $detail[$index]['after_stock_num']     = $after_stock_num;
                            $detail[$index]['before_price']        = 0;
                            $detail[$index]['after_price']         = 0;
                            $detail[$index]['stock_price']         = 0;
                            $detail[$index]['create_time']         = $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'];
                            $detail[$index]['operator_id']         = $v['auditor_id'] ? $v['auditor_id'] : $v['creater_id'];
                            $detail[$index]['operator']            = $dealer[$detail[$index]['operator_id']] ? $dealer[$detail[$index]['operator_id']] : '';
                            $index++;
                            $before_stock_num += $val['actual_storage_num'];
                            array_push($adjustment_stock_in_arr,$val['storage_code']);

                            //优先入库后，更改原入库单的创建时间和审核时间
                            $adjustment_stock_in_data = [
                                'old_create_time' => $val['create_time'],
                                'old_audit_time' => $val['audit_time'],
                                'create_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                                'audit_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                            ];
                            $this->getModel('ErpStockIn')->where(['storage_code'=>$val['storage_code']])->save($adjustment_stock_in_data);
                            if (bccomp($inventory[$key]['stock_num'],$v['change_num']) != -1) {
                                break;
                            }
                        }
                    }
                }
                $after_stock_num = $inventory[$key]['stock_num'] -= $v['change_num'];
            }
            //入库单处理
            elseif($v['type'] == 2) {
                if (in_array($v['source_number'],$adjustment_stock_in_arr)) {
                    continue;
                }
                if ($v['change_num'] < 0) {
                    //当前物理库存是否满足出库，若不满足，则拉后一条入库单来优先入库
                    if (bccomp($inventory[$key]['stock_num'],abs($v['change_num'])) === -1 && $v['stock_type'] != 4) {
                        $adjustment_stock_in_where = [
                            'storehouse_id'     => $v['storehouse_id'],
                            'our_company_id'    => $v['our_company_id'],
                            'goods_id'          => $v['goods_id'],
                            'stock_type'        => $v['stock_type'],
                            'storage_status'    => 10,
                            'is_reverse'        => 2,
                            '_string'           => 'IF(audit_time = "0000-00-00 00:00:00",create_time,audit_time) > "'.($v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time']).'"',
                        ];
                        if ($v['stock_type'] == 4) {
                            unset($adjustment_stock_in_where['stock_type']);
                        }
                        $adjustment_stock_in = $this->getModel('ErpStockIn')->where($adjustment_stock_in_where)->select();

                        foreach ($adjustment_stock_in as $val) {
                            $after_stock_num = $inventory[$key]['stock_num'] += $val['actual_storage_num'];
                            $detail[$index]['stock_id']            = $v['stock_id'];
                            $detail[$index]['type']                = 2;
                            $detail[$index]['source_number']       = $val['storage_code'];
                            $detail[$index]['source_order_number'] = $val['source_number'];
                            $detail[$index]['change_num']          = $val['actual_storage_num'];
                            $detail[$index]['price']               = $v['price'];
                            $detail[$index]['before_stock_num']    = $before_stock_num;
                            $detail[$index]['after_stock_num']     = $after_stock_num;
                            $detail[$index]['before_price']        = 0;
                            $detail[$index]['after_price']         = 0;
                            $detail[$index]['stock_price']         = 0;
                            $detail[$index]['create_time']         = $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'];
                            $detail[$index]['operator_id']         = $v['auditor_id'] ? $v['auditor_id'] : $v['creater_id'];
                            $detail[$index]['operator']            = $dealer[$detail[$index]['operator_id']] ? $dealer[$detail[$index]['operator_id']] : '';
                            $index++;
                            $before_stock_num += $val['actual_storage_num'];
                            array_push($adjustment_stock_in_arr,$val['storage_code']);

                            //优先入库后，更改原入库单的创建时间和审核时间
                            $adjustment_stock_in_data = [
                                'old_create_time' => $val['create_time'],
                                'old_audit_time' => $val['audit_time'],
                                'create_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                                'audit_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                            ];
                            $this->getModel('ErpStockIn')->where(['storage_code'=>$val['storage_code']])->save($adjustment_stock_in_data);
                            if (bccomp($inventory[$key]['stock_num'],abs($v['change_num'])) != -1) {
                                break;
                            }
                        }
                    }
                }
                $after_stock_num = $inventory[$key]['stock_num'] += $v['change_num'];
            }

            $detail[$index]['stock_id']            = $v['stock_id'];
            $detail[$index]['type']                = $v['type'];
            $detail[$index]['source_number']       = $v['source_number'];
            $detail[$index]['source_order_number'] = $v['source_order_number'];
            $detail[$index]['change_num']          = $v['change_num'];
            $detail[$index]['price']               = $v['price'];
            $detail[$index]['before_stock_num']    = $before_stock_num;
            $detail[$index]['after_stock_num']     = $after_stock_num;
            $detail[$index]['before_price']        = 0;
            $detail[$index]['after_price']         = 0;
            $detail[$index]['stock_price']         = 0;
            $detail[$index]['create_time']         = $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'];
            $detail[$index]['operator_id']         = $v['auditor_id'] ? $v['auditor_id'] : $v['creater_id'];
            $detail[$index]['operator']            = $dealer[$detail[$index]['operator_id']] ? $dealer[$detail[$index]['operator_id']] : '';
            $index++;
            if ($v['stock_type'] == 4) {
                $stock_inventory_retail = $inventory;
                $detail_retail_data = $detail;
            } else {
                $stock_inventory = $inventory;
                $detail_data = $detail;
            }
        }
        if (!empty($detail_data)) {
            $status_detail = $this->getModel('ErpStockDetail')->addAll(array_values($detail_data));
        } else {
            $status_detail = true;
        }
        if (!empty($detail_retail_data)) {
            $status_detail_retail = $this->getModel('ErpStockDetailRetail')->addAll(array_values($detail_retail_data));
        } else {
            $status_detail_retail = true;
        }
        $stock_status_all = true;
        $stock_status_all_retail = true;

        foreach ($stock_inventory as $k=>$v) {
            if ($v['stock_id']) {
                $add_data = $update_data = [
                    'stock_id'  => $v['stock_id'],
                    'stock_num' => $v['stock_num'],
                    'price'     => 0,
                ];
                $update_data['update_time'] = currentTime();
                $stock_status = D('ErpStockInventory')->add($add_data, [], $update_data);
                $stock_status_all = $stock_status && $stock_status_all ? true : false;
                echo "库存id".$v['stock_id']."\n\r库存数量".$v['stock_num']."更新状态".$stock_status."</br>";
            }
        }

        foreach ($stock_inventory_retail as $k=>$v) {
            if ($v['stock_id']) {
                $add_data = $update_data = [
                    'stock_id'  => $v['stock_id'],
                    'stock_num' => $v['stock_num'],
                    'price'     => 0,
                ];
                $update_data['update_time'] = currentTime();
                $stock_status_retail = D('ErpStockInventoryRetail')->add($add_data, [], $update_data);
                $stock_status_all_retail = $stock_status_retail && $stock_status_all_retail ? true : false;
                echo "库存id".$v['stock_id']."\n\r库存数量".$v['stock_num']."更新状态".$stock_status_retail."</br>";
            }
        }
        echo $status_detail ? "该批订单处理成功\n\r": "该批订单处理失败\n\r";
        echo $status_detail_retail ? "该批订单处理成功\n\r": "该批订单处理失败\n\r";
        echo $stock_status_all ? "库存校对完成\n\r": "库存校对失败\n\r";
        echo $stock_status_all_retail ? "库存校对完成\n\r": "库存校对失败\n\r";
        $status = $status_detail && $status_detail_retail && $stock_status_all && $stock_status_all_retail ? true : false;

        $status_all = $status && $status_all ? true : false;
        if ($status_all) {
            M()->commit();
            echo "全部导入完成";
        } else {
            M()->rollback();
            echo "导入失败，已回滚";
        }
    }

    /**
     * 导入一级仓期初成本到临时盘点库存表
     * @param $data
     * @author guanyu
     * @time 2018-05-16
     */
    public function importCostInfoToInventory()
    {
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './cost_info.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './cost_info.xls';
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
            $sheet_one = $currentSheet->toArray();
            unset($sheet_one[0]);

            $currentSheet = $PHPExcel->getSheet(1);
            $sheet_two = $currentSheet->toArray();
            unset($sheet_two[0]);

            $currentSheet = $PHPExcel->getSheet(2);
            $sheet_three = $currentSheet->toArray();
            unset($sheet_three[0]);

            $currentSheet = $PHPExcel->getSheet(3);
            $sheet_four = $currentSheet->toArray();
            unset($sheet_four[0]);

            $currentSheet = $PHPExcel->getSheet(4);
            $sheet_five = $currentSheet->toArray();
            unset($sheet_five[0]);

            $data = [
                1 => $sheet_one,
                2 => $sheet_two,
                3 => $sheet_three,
                4 => $sheet_four,
                5 => $sheet_five,
            ];
        }

        $i = 1;
        $k = 0;
        $cost_data = [];
        $status_all = true;
        M()->startTrans();
        foreach($data as $key => $cost){
            foreach ($cost as $value) {
                if(trim($value[0]) && !is_nan(trim($value[6]))){
                    if ($value[2] == '加油网点'){
                        continue;
                    }
                    $value[0] = trim($value[0]);
                    //----------------------组装数据------------------------------------------------------------------
                    $cost_data[$k]['price']         = trim($value[6]) * 10000;
                    $cost_data[$k]['update_time']   = currentTime();

                    $cost_status = D('ErpStockInventory')->where(['stock_id'=>$value[0]])->save($cost_data[$k]);
//                    if ($value[0] == 419955) {
//                        var_dump(D('ErpStockInventory')->getLastSql());exit;
//                    }
                    $status_all = $status_all && $cost_status !== false ? true :false;
                    //------------------------------------------------------------------------------------------------
                    var_dump($cost_status);
                    var_dump($status_all);
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

    /**
     * 导入网点期初成本到临时盘点库存表
     * @param $data
     * @author guanyu
     * @time 2018-05-16
     */
    public function importRetailCostInfoToInventory()
    {
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './cost_info.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './cost_info.xls';
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
            $sheet_one = $currentSheet->toArray();
            unset($sheet_one[0]);

            $currentSheet = $PHPExcel->getSheet(1);
            $sheet_two = $currentSheet->toArray();
            unset($sheet_two[0]);

            $currentSheet = $PHPExcel->getSheet(2);
            $sheet_three = $currentSheet->toArray();
            unset($sheet_three[0]);

            $currentSheet = $PHPExcel->getSheet(3);
            $sheet_four = $currentSheet->toArray();
            unset($sheet_four[0]);

            $currentSheet = $PHPExcel->getSheet(4);
            $sheet_five = $currentSheet->toArray();
            unset($sheet_five[0]);

            $data = [
                1 => $sheet_one,
                2 => $sheet_two,
                3 => $sheet_three,
                4 => $sheet_four,
                5 => $sheet_five,
            ];
        }

        $i = 1;
        $k = 0;
        $cost_data = [];
        $status_all = true;
        M()->startTrans();
        foreach($data as $key => $cost){
            foreach ($cost as $value) {
                if(trim($value[0]) && !is_nan(trim($value[6]))){
                    if ($value[2] != '加油网点'){
                        continue;
                    }
                    $value[0] = trim($value[0]);
                    //----------------------组装数据------------------------------------------------------------------
                    $cost_data[$k]['price']         = trim($value[6]) * 10000;
                    $cost_data[$k]['update_time']   = currentTime();

                    $cost_status = D('ErpStockInventoryRetail')->where(['stock_id'=>$value[0]])->save($cost_data[$k]);

                    $status_all = $status_all && $cost_status !== false ? true :false;
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

    /**
     * 导入历史出库明细数据（2018-06-12 到 2017-08-02）
     * @time 2018-09-27
     * @author guanyu
     *
     * $param[
     * start:2018-03-11 22:00:00 开始时间
     * end:2018-04-01 00:00:00 结束时间
     * ]
     */
    public function handleHistoryStockDataSecond($param)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        //==========================================获取临时盘点库存数据start===========================================
        $stock_inventory_field = 'sin.*,s.goods_id,s.object_id,s.our_company_id,s.stock_type';
        $stock_inventory_data = D('ErpStockInventory')->alias('sin')
            ->field($stock_inventory_field)
            ->join('oil_erp_stock s on sin.stock_id = s.id', 'left')
            ->select();
        $stock_inventory = [];
        if ($stock_inventory_data) {
            foreach ($stock_inventory_data as $value) {
                $stock_inventory[$value['object_id'].$value['our_company_id'].$value['goods_id'].$value['stock_type']] = $value;
            }
        }

        $stock_inventory_retail_field = 'sin.*,s.goods_id,s.object_id,s.our_company_id,s.stock_type';
        $stock_inventory_retail_data = D('ErpStockInventoryRetail')->alias('sin')
            ->field($stock_inventory_retail_field)
            ->join('oil_erp_stock s on sin.stock_id = s.id', 'left')
            ->select();
        $stock_inventory_retail = [];
        if ($stock_inventory_retail_data) {
            foreach ($stock_inventory_retail_data as $value) {
                $stock_inventory_retail[$value['object_id'].$value['our_company_id'].$value['goods_id'].$value['stock_type']] = $value;
            }
        }
        //============================================获取临时盘点库存数据end===========================================

        //交易员id对应姓名数组
        $dealer = dealerIdTologinName();

        //=================================获取出入库数据并按照时间及出入库类型排序start================================
//        $stock_out = $this->selectAllStockOut($param);
//
//        $stock_in = $this->selectAllStockIn($param);
//
//        $stock_out_retail = $this->selectAllRetailOut($param);

        $stock_data = M('ErpStockDetailView')
            ->field(true)
            ->where(['_string'=>'IF(audit_time = "0000-00-00 00:00:00",create_time,audit_time) > "2018-03-11 22:00:00"'])
            ->order("CONCAT(IF(audit_time = '0000-00-00 00:00:00',create_time,audit_time),IF(type = 1,IF(storage_type = 2,2,4),IF(storage_type = 2,3,1)))")
            ->limit($param['start'], $param['end'])
            ->select();

//        $stock_info = array_merge($stock_out,$stock_in,$stock_out_retail);
//        $stock_data = [];
//        foreach ($stock_info as $value) {
//            $type = $value['type'] == 1 ? ($value['outbound_type'] == 2 ? 2 : 4) : ($value['storage_type'] == 2 ? 3 : 1);
//            $sort = $value['audit_time'] == '0000-00-00 00:00:00' ? $value['create_time'] : $value['audit_time'].$type.$value['stock_type'];
//            if (isset($stock_data[$sort])) {
//                $sort .= substr($value['source_number'],5);
//            }
//            $stock_data[$sort] = $value;
//        }
//        ksort($stock_data);
        //=================================获取出入库数据并按照时间及出入库类型排序end==================================

        M()->startTrans();
        $status_all = true;
        $detail_data = [];
        $detail_retail_data = [];
        $adjustment_stock_in_arr = [];
        $index = 0;
        foreach ($stock_data as $v) {
            $key = $v['storehouse_id'].$v['our_company_id'].$v['goods_id'].$v['stock_type'];

            if ($v['stock_type'] == 4) {
                $inventory = $stock_inventory_retail;
                $detail = $detail_retail_data;
            } else {
                $inventory = $stock_inventory;
                $detail = $detail_data;
            }

            //判断是否存在临时盘点库存
            if (isset($inventory[$key])) {
                $before_stock_num = $inventory[$key]['stock_num'];
                $before_price = $inventory[$key]['price'];
            }
            //无临时盘点库存，无期初库存，新库存插入到临时盘点表中
            else {
                $before_stock_num = 0;
                $before_price = 0;
                $inventory[$key]['stock_num'] = 0;
                $inventory[$key]['stock_id'] = $v['stock_id'];
            }

            //出库单处理
            if ($v['type'] == 1) {
                //正向出库单
                if ($v['change_num'] > 0) {
                    //当前物理库存是否满足出库，若不满足，则拉后一条入库单来优先入库
                    if (bccomp($inventory[$key]['stock_num'], $v['change_num']) === -1 && $v['stock_type'] != 4) {
                        $adjustment_stock_in_where = [
                            'storehouse_id' => $v['storehouse_id'],
                            'our_company_id' => $v['our_company_id'],
                            'goods_id' => $v['goods_id'],
                            'stock_type' => $v['stock_type'],
                            'storage_type' => ['neq', 3],
                            'actual_storage_num' => ['gt', 0],
                            'storage_status' => 10,
                            'is_reverse' => 2,
                            '_string' => 'IF(audit_time = "0000-00-00 00:00:00",create_time,audit_time) > "' . ($v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time']) . '"',
                        ];
                        $adjustment_stock_in = $this->getModel('ErpStockIn')->where($adjustment_stock_in_where)->select();


                        foreach ($adjustment_stock_in as $val) {
                            $after_stock_num = $inventory[$key]['stock_num'] += $val['actual_storage_num'];
                            //调拨入库单单价重置
                            if ($val['storage_type'] == 2) {
                                $val['price'] = $this->getAllocationPrice($val['source_number'], $stock_inventory,
                                    $stock_inventory_retail);
                            }
                            //参与加权
                            $after_price = $inventory[$key]['price'] = $this->calculateAfterPrice($before_stock_num,
                                $before_price, $val['actual_storage_num'], $val['price'], $after_stock_num);
                            $detail[$index]['stock_id'] = $v['stock_id'];
                            $detail[$index]['type'] = 2;
                            $detail[$index]['source_number'] = $val['storage_code'];
                            $detail[$index]['source_order_number'] = $val['source_number'];
                            $detail[$index]['change_num'] = $val['actual_storage_num'];
                            $detail[$index]['price'] = $v['price'] ? $v['price'] : 0;
                            $detail[$index]['before_stock_num'] = $before_stock_num;
                            $detail[$index]['after_stock_num'] = $after_stock_num;
                            $detail[$index]['before_price'] = $before_price;
                            $detail[$index]['after_price'] = $after_price;
                            $detail[$index]['stock_price'] = $after_stock_num * $after_price / 10000;
                            $detail[$index]['create_time'] = $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'];
                            $detail[$index]['operator_id'] = $v['auditor_id'] ? $v['auditor_id'] : $v['creater_id'];
                            $detail[$index]['operator'] = $dealer[$detail[$index]['operator_id']] ? $dealer[$detail[$index]['operator_id']] : '';
                            $index++;
                            $before_stock_num += $val['actual_storage_num'];
                            $before_price = $after_price;

                            array_push($adjustment_stock_in_arr, $val['storage_code']);

                            //优先入库后，更改原入库单的创建时间和审核时间
                            $adjustment_stock_in_data = [
                                'old_create_time' => $val['create_time'],
                                'old_audit_time' => $val['audit_time'],
                                'create_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                                'audit_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                            ];
                            $this->getModel('ErpStockIn')->where(['storage_code' => $val['storage_code']])->save($adjustment_stock_in_data);
                            if (bccomp($inventory[$key]['stock_num'], $v['change_num']) != -1) {
                                break;
                            }
                        }
                    }
                    $after_stock_num = $inventory[$key]['stock_num'] -= $v['change_num'];
                    //采退出库单
                    if ($v['outbound_type'] == 3) {
                        $v['price'] = $this->getPurchaseReturnPrice($v['source_order_number']);
                        $after_price = $inventory[$key]['price'] = $this->calculateAfterPrice($before_stock_num,
                            $before_price, $v['change_num'] * -1, $v['price'], $after_stock_num);
                    } else {
                        if (empty($inventory[$key]['price'])) {
                            $inventory[$key]['price'] = 0;
                        }
                        $after_price = $v['price'] = $inventory[$key]['price'];
                    }
                }
                //红冲出库单
                else {
                    $after_stock_num = $inventory[$key]['stock_num'] -= $v['change_num'];

                    $v['price'] = $this->getReverseStockOutPrice($v['reverse_source']);
                    if ($v['price'] == 0) {
                        $after_price = $inventory[$key]['price'] = $before_price;
                    } else {
                        $after_price = $inventory[$key]['price'] = $this->calculateAfterPrice($before_stock_num,
                            $before_price,$v['change_num']*-1,$v['price'],$after_stock_num);
                    }
                }
            }
            //入库单处理
            elseif($v['type'] == 2) {

                if (in_array($v['source_number'],$adjustment_stock_in_arr)) {
                    continue;
                }
                if ($v['change_num'] < 0) {
                    //当前物理库存是否满足出库，若不满足，则拉后一条入库单来优先入库
                    if (bccomp($inventory[$key]['stock_num'],abs($v['change_num'])) === -1 && $v['stock_type'] != 4) {
                        $adjustment_stock_in_where = [
                            'storehouse_id'         => $v['storehouse_id'],
                            'our_company_id'        => $v['our_company_id'],
                            'goods_id'              => $v['goods_id'],
                            'stock_type'            => $v['stock_type'],
                            'storage_type'          => ['neq',3],
                            'actual_storage_num'    => ['gt',0],
                            'storage_status'        => 10,
                            'is_reverse'            => 2,
                            'storage_type'          => ['not in','2,3'],
                            '_string'               => 'IF(audit_time = "0000-00-00 00:00:00",create_time,audit_time) > "'.($v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time']).'"',
                        ];
                        $adjustment_stock_in = $this->getModel('ErpStockIn')->where($adjustment_stock_in_where)->select();

                        foreach ($adjustment_stock_in as $val) {
                            $after_stock_num = $inventory[$key]['stock_num'] += $val['actual_storage_num'];
                            //调拨入库单单价重置
                            if ($val['storage_type'] == 2) {
                                $val['price'] = $this->getAllocationPrice($val['source_number'], $stock_inventory,
                                    $stock_inventory_retail);
                            }
                            $after_price = $inventory[$key]['price'] = $this->calculateAfterPrice($before_stock_num,
                                $before_price,$val['actual_storage_num'],$val['price'],$after_stock_num);
                            $detail[$index]['stock_id']            = $v['stock_id'];
                            $detail[$index]['type']                = 2;
                            $detail[$index]['source_number']       = $val['storage_code'];
                            $detail[$index]['source_order_number'] = $val['source_number'];
                            $detail[$index]['change_num']          = $val['actual_storage_num'];
                            $detail[$index]['price']               = $v['price'] ? $v['price'] : 0;
                            $detail[$index]['before_stock_num']    = $before_stock_num;
                            $detail[$index]['after_stock_num']     = $after_stock_num;
                            $detail[$index]['before_price']        = $before_price;
                            $detail[$index]['after_price']         = $after_price;
                            $detail[$index]['stock_price']         = $after_stock_num * $after_price / 10000;
                            $detail[$index]['create_time']         = $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'];
                            $detail[$index]['operator_id']         = $v['auditor_id'] ? $v['auditor_id'] : $v['creater_id'];
                            $detail[$index]['operator']            = $dealer[$detail[$index]['operator_id']] ? $dealer[$detail[$index]['operator_id']] : '';
                            $index++;
                            $before_stock_num += $val['actual_storage_num'];
                            $before_price = $after_price;

                            array_push($adjustment_stock_in_arr,$val['storage_code']);

                            //优先入库后，更改原入库单的创建时间和审核时间
                            $adjustment_stock_in_data = [
                                'old_create_time' => $val['create_time'],
                                'old_audit_time' => $val['audit_time'],
                                'create_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                                'audit_time' => $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'],
                            ];
                            $this->getModel('ErpStockIn')->where(['storage_code'=>$val['storage_code']])->save($adjustment_stock_in_data);
                            if (bccomp($inventory[$key]['stock_num'],abs($v['change_num'])) != -1) {
                                break;
                            }
                        }
                    }
                    $v['price'] = $this->getReverseStockInPrice($v['reverse_source']);
                } else {
                    //销退入库单单价重置
                    if ($v['storage_type'] == 3) {
                        $v['price'] = $this->getSaleReturnPrice($v);
                    }
                    //调拨入库单单价重置
                    elseif ($v['storage_type'] == 2) {
                        $v['price'] = $this->getAllocationPrice($v['source_order_number'],$stock_inventory,$stock_inventory_retail);
                    }
                }
                $after_stock_num = $inventory[$key]['stock_num'] += $v['change_num'];
                if ($v['price'] == 0) {
                    $after_price = $inventory[$key]['price'] = $before_price;
//                    if (!in_array($v['source_number'],['HY_RO2018031900000058','HY_RO2018031200000002','HY_RO2018032000000118',
//                        'HY_RO2018032300000018','HY_RO2018032600000043','HY_RS2018040300000001','HY_RO2018040300000045',
//                        'YX_RO2018040900000001','HY_RO2018053100000001','YX_RO2018072300000005','HY_RO2018081500000018',
//                        'HY_RO2018082300000012','HY_RO2018090300000018','HY_RO2018090600000028','HY_RO2018091300000039',
//                        'HY_RO2018091300000040','HY_RO2018091300000041','HY_RO2018091400000040','HY_RO2018092000000030',
//                        'HY_RO2018092000000036','HY_RO2018092000000041','HY_RO2018092500000001','HY_RO2018101500000012',
//                        'HZ_RO2018102400000010'])) {
//                        var_dump($v);exit;
//                    }
                } else {
                    $after_price = $inventory[$key]['price'] = $this->calculateAfterPrice($before_stock_num,
                        $before_price,$v['change_num'],$v['price'],$after_stock_num);
                }
            }

            $detail[$index]['stock_id']            = $v['stock_id'];
            $detail[$index]['type']                = $v['type'];
            $detail[$index]['source_number']       = $v['source_number'];
            $detail[$index]['source_order_number'] = $v['source_order_number'];
            $detail[$index]['change_num']          = $v['change_num'];
            $detail[$index]['price']               = $v['price'];
            $detail[$index]['before_stock_num']    = $before_stock_num;
            $detail[$index]['after_stock_num']     = $after_stock_num;
            $detail[$index]['before_price']        = $before_price;
            $detail[$index]['after_price']         = $after_price;
            $detail[$index]['stock_price']         = $after_stock_num * $after_price / 10000;
            $detail[$index]['create_time']         = $v['audit_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['audit_time'];
            $detail[$index]['operator_id']         = $v['auditor_id'] ? $v['auditor_id'] : $v['creater_id'];
            $detail[$index]['operator']            = $dealer[$detail[$index]['operator_id']] ? $dealer[$detail[$index]['operator_id']] : '';
            $index++;
            if ($v['stock_type'] == 4) {
                $stock_inventory_retail = $inventory;
                $detail_retail_data = $detail;
            } else {
                $stock_inventory = $inventory;
                $detail_data = $detail;
            }
        }
        if (!empty($detail_data)) {
            $status_detail = $this->getModel('ErpStockDetail')->addAll(array_values($detail_data));
        } else {
            $status_detail = true;
        }
        if (!empty($detail_retail_data)) {
            $status_detail_retail = $this->getModel('ErpStockDetailRetail')->addAll(array_values($detail_retail_data));
        } else {
            $status_detail_retail = true;
        }
        $stock_status_all = true;
        $stock_status_all_retail = true;

        foreach ($stock_inventory as $k=>$v) {
            if ($v['stock_id']) {
                $add_data = $update_data = [
                    'stock_id'  => $v['stock_id'],
                    'stock_num' => $v['stock_num'],
                    'price'     => is_nan($v['price']) ? 0 : $v['price'],
                ];
                $update_data['update_time'] = currentTime();
                $stock_status = D('ErpStockInventory')->add($add_data, [], $update_data);
                $stock_status_all = $stock_status && $stock_status_all ? true : false;
                echo "库存id".$v['stock_id']."\n\r库存数量".$v['stock_num']."更新状态".$stock_status."</br>";
            }
        }

        foreach ($stock_inventory_retail as $k=>$v) {
            if ($v['stock_id']) {
                $add_data = $update_data = [
                    'stock_id'  => $v['stock_id'],
                    'stock_num' => $v['stock_num'],
                    'price'     => is_nan($v['price']) ? 0 : $v['price'],
                ];
                $update_data['update_time'] = currentTime();
                $stock_status_retail = D('ErpStockInventoryRetail')->add($add_data, [], $update_data);
                $stock_status_all_retail = $stock_status_retail && $stock_status_all_retail ? true : false;
                echo "库存id".$v['stock_id']."\n\r库存数量".$v['stock_num']."更新状态".$stock_status_retail."</br>";
            }
        }
        echo $status_detail ? "该批订单处理成功\n\r": "该批订单处理失败\n\r";
        echo $status_detail_retail ? "该批订单处理成功\n\r": "该批订单处理失败\n\r";
        echo $stock_status_all ? "库存校对完成\n\r": "库存校对失败\n\r";
        echo $stock_status_all_retail ? "库存校对完成\n\r": "库存校对失败\n\r";
        $status = $status_detail && $status_detail_retail && $stock_status_all && $stock_status_all_retail ? true : false;

        $status_all = $status && $status_all ? true : false;
        if ($status_all) {
            M()->commit();
            echo "全部导入完成";
        } else {
            M()->rollback();
            echo "导入失败，已回滚";
        }
    }

    /**
     * 2018-03-11网点库存平账
     * @time 2019-01-16
     * @author guanyu
     */
    public function settleUpSkidStock()
    {
        //第一步，查询出所有盘点库存为负数的网点
        $demand_skid_stock = $this->getModel('ErpStockInventoryRetail')
            ->alias('si')
            ->field('si.stock_id,s.object_id,s.goods_id,s.our_company_id,s.region,si.stock_num')
            ->join('oil_erp_stock as s on si.stock_id = s.id','left')
            ->where('si.stock_num < 0 and s.stock_type = 4')
            ->select();
        //第二步，循环处理，查询同网点不同品项的非负库存
        M()->startTrans();
        $status_all = true;
        foreach ($demand_skid_stock as $demand_key => $demand_value) {
            $demand_stock_id = $demand_value['stock_id'];
            $storehouse_id = $demand_value['object_id'];
            $goods_id = $demand_value['goods_id'];
            $our_company_id = $demand_value['our_company_id'];
            $region = $demand_value['region'];
            $demand_num = abs($demand_value['stock_num']);
            $supply_where = [
                's.object_id' => $storehouse_id,
                's.goods_id' => ['neq',$goods_id],
                's.our_company_id' => $our_company_id,
                's.region' => $region,
                'si.stock_num' => ['gt',0],
            ];
            $supply_skid_stock = $this->getModel('ErpStockInventoryRetail')
                ->alias('si')
                ->field('si.stock_id,s.goods_id,si.stock_num')
                ->join('oil_erp_stock as s on si.stock_id = s.id','left')
                ->where($supply_where)
                ->select();
            $supply_status = true;
            foreach ($supply_skid_stock as $supply_key => $supply_value) {
                $supply_stock_id = $supply_value['stock_id'];
                $supply_num = $supply_value['stock_num'] > $demand_num ?  $demand_num : $supply_value['stock_num'];
                if ($supply_num < 1) {
                    continue;
                }
                //第三步，生成调拨单，出库单，入库单
                $allocation_data = [
                    'order_number' => erpCodeNumber(9,'',$our_company_id)['order_number'],
                    'our_company_id' => $our_company_id,
                    'out_region' => $region,
                    'out_storehouse' => $storehouse_id,
                    'in_region' => $region,
                    'in_storehouse' => $storehouse_id,
                    'status' => 10,
                    'remark' => '网点库存平账，系统自生成',
                    'goods_id' => $supply_value['goods_id'],
                    'order_time' => '2018-03-11 21:59:59',
                    'outbound_status' => 1,
                    'storage_status' => 1,
                    'allocation_type' => 2,
                    'num' => $supply_num,
                    'actual_out_num' => $supply_num,
                    'actual_in_num' => $supply_num,
                    'actual_in_num_liter' => $supply_num/0.833*1000,
                    'actual_out_num_liter' => $supply_num/0.833*1000,
                    'dealer_name' => 'SYS',
                    'create_time' => '2018-03-11 21:59:59',
                    'update_time' => '2018-03-11 21:59:59',
                    'audit_time' => '2018-03-11 21:59:59',
                    'check_time' => '2018-03-11 21:59:59',
                    'confirm_time' => '2018-03-11 21:59:59',
                    'actual_allocation_time' => '2018-03-11 21:59:59',
                    'business_type' => 2,
                ];
                $allocation_status = $allocation_id = $this->getModel('ErpAllocationOrder')->add($allocation_data);

                $stock_out_data = [
                    'outbound_code' => erpCodeNumber(7,'',$our_company_id)['order_number'],
                    'outbound_type' => 2,
                    'outbound_status' => 10,
                    'outbound_remark' => '网点库存平账，系统自生成',
                    'source_number' => $allocation_data['order_number'],
                    'source_object_id' => $allocation_id,
                    'our_company_id' => $our_company_id,
                    'goods_id' => $supply_value['goods_id'],
                    'depot_id' => 99999,
                    'outbound_num' => $supply_num,
                    'actual_outbound_num' => $supply_num,
                    'outbound_density' => 0.833,
                    'create_time' => '2018-03-11 21:59:59',
                    'audit_time' => '2018-03-11 21:59:59',
                    'storehouse_id' => $storehouse_id,
                    'stock_type' => 4,
                    'region' => $region,
                ];

                $stock_out_status = $this->getModel('ErpStockOut')->add($stock_out_data);

                $stock_in_data = [
                    'storage_code' => erpCodeNumber(8,'',$our_company_id)['order_number'],
                    'storage_type' => 2,
                    'storage_status' => 10,
                    'storage_remark' => '网点库存平账，系统自生成',
                    'source_number' => $allocation_data['order_number'],
                    'source_object_id' => $allocation_id,
                    'our_company_id' => $our_company_id,
                    'goods_id' => $goods_id,
                    'storage_num' => $supply_num,
                    'actual_storage_num' => $supply_num,
                    'outbound_density' => 0.833,
                    'create_time' => '2018-03-11 21:59:59',
                    'audit_time' => '2018-03-11 21:59:59',
                    'storehouse_id' => $storehouse_id,
                    'stock_type' => 4,
                    'region' => $region,
                    'actual_storage_num_litre' => $supply_num/0.833*1000,
                    'balance_num' => 0,
                    'balance_num_litre' => 0,
                ];

                $stock_in_status = $this->getModel('ErpStockIn')->add($stock_in_data);

                //第四步，生成库存明细数据
                $detail_data = [
                    //出库
                    0 => [
                        'stock_id' => $supply_stock_id,
                        'type' => 1,
                        'source_number' => $stock_out_data['outbound_code'],
                        'source_order_number' => $stock_out_data['source_number'],
                        'change_num' => $supply_num,
                        'price' => 0,
                        'before_stock_num' => $supply_value['stock_num'],
                        'after_stock_num' => $supply_value['stock_num'] - $supply_num,
                        'before_price' => 0,
                        'after_price' => 0,
                        'stock_price' => 0,
                        'create_time' => '2018-03-11 21:59:59',
                    ],
                    //入库
                    1 => [
                        'stock_id' => $demand_stock_id,
                        'type' => 2,
                        'source_number' => $stock_in_data['storage_code'],
                        'source_order_number' => $stock_in_data['source_number'],
                        'change_num' => $supply_num,
                        'price' => 0,
                        'before_stock_num' => $demand_num * -1,
                        'after_stock_num' => $demand_num * -1 + $supply_num,
                        'before_price' => 0,
                        'after_price' => 0,
                        'stock_price' => 0,
                        'create_time' => '2018-03-11 21:59:59',
                    ],
                ];

                $detail_status = $this->getModel('ErpStockDetailRetail')->addAll($detail_data);
                //第五步，修改供应方和需求方库存
                $supply_stock_status = $this->getModel('ErpStockInventoryRetail')
                    ->where(['stock_id'=>$supply_stock_id])
                    ->save(['stock_num'=>$supply_value['stock_num'] - $supply_num]);
                $demand_stock_status = $this->getModel('ErpStockInventoryRetail')
                    ->where(['stock_id'=>$demand_stock_id])
                    ->save(['stock_num'=>$demand_num * -1 + $supply_num]);
                $status = $allocation_status && $stock_out_status && $stock_in_status && $detail_status
                && $supply_stock_status && $demand_stock_status ? true : false;
                $supply_status = $supply_status && $status ? true : false;
                $demand_num -= $supply_num;
                if ($demand_num <= 0) {
                    break;
                }
            }
            $status_all = $status_all && $supply_status ? true : false;
        }
        if ($status_all) {
            M()->commit();
        } else {
            M()->rollback();
        }
    }

    //根据临时库存成本盘点表修复线上成本表
    public function updateCostByInventory()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $inventory = $this->getModel('ErpStockInventory')
            ->field('si.stock_id,s.object_id,s.goods_id,s.region,s.stock_type,s.our_company_id,si.stock_num,si.price')
            ->alias('si')
            ->join('oil_erp_stock as s on si.stock_id = s.id')
            ->select();
        $inventory_retail = $this->getModel('ErpStockInventoryRetail')
            ->field('si.stock_id,s.object_id,s.goods_id,s.region,s.stock_type,s.our_company_id,si.stock_num,si.price')
            ->alias('si')
            ->join('oil_erp_stock as s on si.stock_id = s.id')
            ->select();
        $cost_data = [];
        $stock_status_all = true;
        M()->startTrans();
        foreach ($inventory as $key => $val) {
            $cost_data[] = [
                'stock_id'          => $val['stock_id'],
                'goods_id'          => $val['goods_id'],
                'object_id'         => $val['object_id'],
                'stock_type'        => $val['stock_type'],
                'region'            => $val['region'],
                'our_company_id'    => $val['our_company_id'],
                'price'             => $val['price'],
                'gmt_create'        => currentTime(),
                'gmt_modified'      => currentTime(),
            ];
            $stock_info = $this->getModel('ErpStock')->where(['id'=>$val['stock_id']])->find();
            $stock_data = [
                'stock_num' => $val['stock_num'],
                'available_num' => $stock_info['available_num'] + ($stock_info['stock_num'] - $val['stock_num']),
                'update_time' => currentTime(),
            ];
            $stock_status = $this->getModel('ErpStock')->where(['id'=>$val['stock_id']])->save($stock_data);
            $stock_status_all = $stock_status_all && $stock_status ? true : false;
        }
        foreach ($inventory_retail as $k => $v) {
            $cost_data[] = [
                'stock_id'          => $v['stock_id'],
                'goods_id'          => $v['goods_id'],
                'object_id'         => $v['object_id'],
                'stock_type'        => $v['stock_type'],
                'region'            => $v['region'],
                'our_company_id'    => $v['our_company_id'],
                'price'             => $v['price'],
                'gmt_create'        => currentTime(),
                'gmt_modified'      => currentTime(),
            ];
            $stock_info = $this->getModel('ErpStock')->where(['id'=>$val['stock_id']])->find();
            $stock_data = [
                'stock_num' => $v['stock_num'],
                'available_num' => $stock_info['available_num'] + ($stock_info['stock_num'] - $v['stock_num']),
                'update_time' => currentTime(),
            ];
            $stock_retail_status = $this->getModel('ErpStock')->where(['id'=>$val['stock_id']])->save($stock_data);
            $stock_status_all = $stock_status_all !== false && $stock_retail_status !== false ? true : false;
        }
        $status = M('erp_cost.cost_info','oil_')->addAll($cost_data);
        $status_all = $stock_status_all && $status ? true : false;
        if ($status_all) {
            M()->commit();
            echo '成本库存覆盖成功';
        } else {
            M()->rollback();
            echo '操作失败';
        }

    }

    //计算加权成本
    public function calculateAfterPrice($before_stock_num,$before_price,$change_num,$price,$after_stock_num)
    {
        if ($after_stock_num == 0) {
            $after_price = 0;
        } else {
            $after_price = ($before_stock_num * $before_price + $change_num * $price) / $after_stock_num;
        }
        return $after_price;
    }
    //提交一次数据到明细表
    //原因：要从表中获取准确成本，在数组中无法操作
    public function commitRequest($detail_data,$detail_retail_data)
    {
        if (!empty($detail_data)) {
            $status_detail = $this->getModel('ErpStockDetail')->addAll(array_values($detail_data));
        } else {
            $status_detail = true;
        }
        if (!empty($detail_retail_data)) {
            $status_detail_retail = $this->getModel('ErpStockDetailRetail')->addAll(array_values($detail_retail_data));
        } else {
            $status_detail_retail = true;
        }
        $status = $status_detail && $status_detail_retail ? true : false;
        return $status;
    }
    public function getAllocationPrice($source_number,$stock_inventory,$stock_inventory_retail)
    {
        $where = [
            'source_order_number' => $source_number,
            'type' => 1
        ];
        $price = $this->getModel('ErpStockDetail')->where($where)->find()['price'];
        if ($price == 0) {
            $price = $this->getModel('ErpStockDetailRetail')->where($where)->find()['price'];
        }
        if ($price == 0) {
            $where = [
                'source_number' => $source_number,
            ];
            $stock_out = $this->getModel('ErpStockOut')->where($where)->find();

            $key = $stock_out['storehouse_id'].$stock_out['our_company_id'].$stock_out['goods_id'].$stock_out['stock_type'];

            if ($stock_out['stock_type'] == 4) {
                $price = $stock_inventory_retail[$key]['price'];
            } else {
                $price = $stock_inventory[$key]['price'];
            }
        }
        return $price;
    }

    public function getSaleReturnPrice($stock_in_info)
    {
        $where = [
            'r.order_number' => $stock_in_info['source_order_number'],
            'o.outbound_status' => 10,
        ];
        $stock_out_info = $this->getModel('ErpStockDetail')
            ->alias('d')
            ->field('d.*')
            ->join('oil_erp_returned_order as r on r.source_order_number = d.source_order_number','left')
            ->join('oil_erp_stock_out as o on o.outbound_code = d.source_number','left')
            ->where($where)
            ->order('id desc')
            ->select();

        /**
         * $num 当前剩余量
         * $total_num 入库单总量
         * $price 成本
         * $difference_value 本次循环之前的总量
         */
        $num = $total_num = $stock_in_info['change_num'];
        $price = 0;
        $difference_value = 0;

        foreach ($stock_out_info as $value) {
            if ($num <= $value['change_num']) {
                if ($price == 0) {
                    $price = $value['price'];
                } else {
                    $difference_value = $total_num - $num;
                    $price = ($price * $difference_value + $num * $value['price']) / $total_num;
                }
                break;
            } else {
                if ($price == 0) {
                    $price = $value['price'];
                } else {
                    $difference_value = $total_num - $num;
                    $price = ($price * $difference_value + $value['change_num'] * $value['price']) / ($difference_value + $value['change_num']);
                }
                $num = $num - $value['change_num'];
            }
        }

        return $price;
    }

    public function getPurchaseReturnPrice($source_order_number)
    {
        $purchase_order_info = $this->getModel('ErpPurchaseOrder')->alias('p')
            ->field('p.*')
            ->join('oil_erp_returned_order r on r.source_order_number = p.order_number','left')
            ->where(['r.order_number'=>$source_order_number])
            ->find();

        return $purchase_order_info['price'];
    }

    public function getReverseStockOutPrice($reverse_source)
    {
        $stock_out_info = $this->getModel('ErpStockDetail')->where(['source_number'=>$reverse_source])->find();

        if (!$stock_out_info) {
            $stock_out_info = $this->getModel('ErpStockDetailRetail')->where(['source_number'=>$reverse_source])->find();
        }
        return $stock_out_info['price'];
    }

    public function getReverseStockInPrice($reverse_source)
    {
        $stock_in_info = $this->getModel('ErpStockDetail')->where(['source_number'=>$reverse_source])->find();
        if (!$stock_in_info) {
            $stock_in_info = $this->getModel('ErpStockDetailRetail')->where(['source_number'=>$reverse_source])->find();
        }
        return $stock_in_info['price'];
    }

    public function getReverseRetailStockOutPrice($reverse_source)
    {
        $stock_out_info = $this->getModel('ErpStockDetailRetail')->where(['source_number'=>$reverse_source])->find();

        return $stock_out_info['price'];
    }

    public function getReverseRetailStockInPrice($reverse_source)
    {
        $stock_in_info = $this->getModel('ErpStockDetailRetail')->where(['source_number'=>$reverse_source])->find();

        return $stock_in_info['price'];
    }

    /**
     * 获取非网点出库单信息关联库存id
     * @time 2018-05-14
     * @author guanyu
     *
     * $param[
     * start:2018-03-11 22:00:00 开始时间
     * end:2018-04-01 00:00:00 结束时间
     * ]
     */
    public function selectAllStockOut($param)
    {
        $stock_out_where = [
            'so.outbound_status'    => 10,
            'so.stock_type'         => ['neq',3],
            '_string'               => 'IF(so.audit_time = "0000-00-00 00:00:00",so.create_time,so.audit_time) BETWEEN "'.$param['start'].'" AND "'.$param['end'].'"',
        ];
        $stock_out_field = 'so.id,so.outbound_code as source_number,so.source_number as source_order_number,so.our_company_id,
        so.actual_outbound_num as change_num,so.create_time,so.audit_time,so.creater_id,so.auditor_id,so.cost as price,
        so.reverse_source,so.outbound_type,so.storehouse_id,so.is_reverse,s.id as stock_id,s.goods_id,s.stock_type,1 as type';

        $stock_out_info = D('erp_stock_out')->alias('so')
            ->field($stock_out_field)
            ->join('oil_erp_stock s on so.our_company_id = s.our_company_id and so.goods_id = s.goods_id and so.storehouse_id = s.object_id and so.stock_type = s.stock_type', 'left')
            ->where($stock_out_where)
            ->order('so.id asc')
            ->select();
        return $stock_out_info;
    }

    /**
     * 获取非网点入库单信息关联库存id
     * @time 2018-05-14
     * @author guanyu
     *
     * $param[
     * start:2018-03-11 22:00:00 开始时间
     * end:2018-04-01 00:00:00 结束时间
     * ]
     */
    public function selectAllStockIn($param)
    {
        $stock_in_where = [
            'si.storage_status' => 10,
            'si.stock_type'     => ['neq',3],
            '_string'           => 'IF(si.audit_time = "0000-00-00 00:00:00",si.create_time,si.audit_time) BETWEEN "'.$param['start'].'" AND "'.$param['end'].'"',
        ];

        $stock_in_field = 'si.id,si.storage_code as source_number,si.source_number as source_order_number,si.our_company_id,
        si.actual_storage_num as change_num,si.create_time,si.audit_time,si.creater_id,si.auditor_id,si.price,
        si.reverse_source,si.storage_type,si.storehouse_id,si.is_reverse,s.id as stock_id,s.goods_id,s.stock_type,2 as type';

        $stock_in_info = D('erp_stock_in')->alias('si')
            ->field($stock_in_field)
            ->join('oil_erp_stock s on si.our_company_id = s.our_company_id and si.goods_id = s.goods_id and si.storehouse_id = s.object_id and si.stock_type = s.stock_type', 'left')
            ->where($stock_in_where)
            ->order('si.id asc')
            ->select();
        return $stock_in_info;
    }

    /**
     * 获取非网点入库单信息关联库存id
     * @time 2018-05-14
     * @author guanyu
     *
     * $param[
     * start:2018-03-11 22:00:00 开始时间
     * end:2018-04-01 00:00:00 结束时间
     * ]
     */
    public function selectAllRetailOut($param)
    {
        $stock_out_where = [
            'so.outbound_status'    => 10,
            '_string'               => 'IF(so.audit_time = "0000-00-00 00:00:00",so.create_time,so.audit_time) BETWEEN "'.$param['start'].'" AND "'.$param['end'].'"',
        ];
        $stock_out_field = 'so.id,so.outbound_code as source_number,so.source_number as source_order_number,so.our_company_id,
        so.actual_outbound_num as change_num,so.create_time,so.audit_time,so.creater_id,so.auditor_id,so.cost as price,
        so.reverse_source,so.outbound_type,so.storehouse_id,so.is_reverse,s.id as stock_id,s.goods_id,s.stock_type,1 as type';

        $stock_out_info = D('erp_stock_out_retail')->alias('so')
            ->field($stock_out_field)
            ->join('oil_erp_stock s on so.our_company_id = s.our_company_id and so.goods_id = s.goods_id and 
            so.storehouse_id = s.object_id and so.stock_type = s.stock_type', 'left')
            ->where($stock_out_where)
            ->order('so.id asc')
            ->select();
        return $stock_out_info;
    }

    /**
     * 网点期初正库存生成期初入库单
     * @author xiaowen
     * @time 2017-7-16
     */
    function skidDataToStockIn(){
        set_time_limit(1000000);
        ini_set('memory_limit', '1024M');
        $field = 's.stock_id,s.goods_id,s.object_id,s.stock_type,s.region,s.stock_num,s.our_company_id,g.goods_code,g.level,g.grade,g.goods_name,g.source_from';
        $stock_where = [
            //'s.status'=>1,
            's.stock_type'=>4,
            's.stock_num'=>['gt', 0],
        ];
        //$stock_info = D('ErpStock')->alias('s')->field($field)->join('oil_erp_goods g on s.goods_id = g.id')->where($stock_where)->select();
        $stock_info = D('erp_stock_skid_data')->alias('s')->field($field)->join('oil_erp_goods g on s.goods_id = g.id')->where($stock_where)->select();
        //$stock_info = D('erp_stock_skid_data')->where(['stock_num'=>['gt', 0]])->select();
        $outbound_density = 0.833;
        if(!session('erp_company_id')) {
            session('erp_company_id', 3372);
        };
        $i = 1;
        foreach($stock_info as $key=>$value){
            $stock_in_data = [
                'storage_type' => 6,//2,
                'storage_code' => erpCodeNumber(8)['order_number'],
                'storage_status' => 10,
                'source_number' => '',
                // 'source_object_id' => ,
                'our_company_id' => 3372,
                'goods_id' => $value['goods_id'],
                'storage_num' => $value['stock_num'],
                'actual_storage_num' => $value['stock_num'],
                'outbound_density' => $outbound_density,
                'creater_id' => 0,
                'create_time' => '2017-08-02 00:00:00',
                'dealer_id' => 0,
                'dealer_name' => '',
                'auditor_id' => '',
                'audit_time' => '2017-08-02 00:00:00',
                'storage_remark' => '网点库存初化入库单，库存ID：' . $value['stock_id'],
                'storehouse_id' => $value['object_id'],
                'stock_type' => 4,
                'region' => $value['region'],
                'price'  => 0,
                'actual_storage_num_litre'  => tonToLiter($value['stock_num'], $outbound_density),//empty($order_data['actual_in_num_liter']) ? '0' : trim($order_data['actual_in_num_liter']),
                'balance_num'  => $value['stock_num'],//empty($order_data['actual_in_num']) ? '0' : trim($order_data['actual_in_num']),
                'balance_num_litre'  => tonToLiter($value['stock_num'], $outbound_density),//empty($order_data['actual_in_num_liter']) ? '0' : trim($order_data['actual_in_num_liter']),
            ];
            $stock_in_status = D('ErpStockIn')->addStockIn($stock_in_data);

            if($stock_in_status){
                echo $i . '<br/>'."stock ID：" . $value['stock_id'] . " , success<br/>\n\r";
            }else{
                echo $i . '<br/>'."stock ID：" . $value['stock_id'] . " , fail<br/>\n\r";
            }
            $i++;
            //exit;
        }
    }

    /**
     * 网点入库单可用库存正负冲减
     * @param $type 1 调拨出库单冲减 2 期初冲减 3 同网点其他负库存冲减
     * 2018.7.13
     */
    function stockInDeductionData($type = 1){

        if($type == 1){
            //处理网点调拨出与调拨入的可用库存数据处理========================================================
//            $stock_allocation_out_where = [
//                'o.out_facilitator_skid_id'=>['gt', 0],
//                'o.status'=>10,
//                'o.outbound_status'=>1,
//                'o.allocation_type'=>['in','2,3'],
//                'o.out_region'=>['gt', 0],
//                'o.goods_id'=>['gt', 0],
//                'eso.outbound_status'=>10,
//                'eso.is_reverse'=>2,
//                'eso.reversed_status'=>2,
//                //'eso.outbound_code' => 'HY_DO2017080900000093',
//            ];
//            $stock_out_allocation = D('ErpAllocationOrder')->alias('o')->field('eso.*, o.id as allocation_order_id')
//                ->join('oil_erp_stock_out eso on eso.source_number = o.order_number')
//                ->where($stock_allocation_out_where)
//                ->select();
            //print_r($stock_out_allocation);
            //exit;
            $stock_allocation_out_where = [
                'eso.outbound_status'=>10,
                'eso.is_reverse'=>2,
                'eso.reversed_status'=>2,
                'eso.stock_type'=>4,
                'eso.outbound_type'=>2,
                //'eso.outbound_code' => 'HY_DO2017080900000114',
            ];
            $stock_out_allocation = D('ErpStockOut')->alias('eso')->field('eso.*')
                ->where($stock_allocation_out_where)
                ->select();
//            print_r($stock_out_allocation);
//            exit;
            foreach ($stock_out_allocation as $key=>$value){
                M()->startTrans();
                //$result = $this->influenceStockIn($value);
                $result = $this->_influenceStockIn($value);
                if($result){
                    M()->commit();
                    echo "序号{$key}调拨出库单处理成功，出库单号：{$value['outbound_code']}\n\r";
                }else{
                    M()->rollback();
                    echo "序号{$key}调拨出库单处理失败，出库单号：{$value['outbound_code']}\n\r";
                }
            }
            //结束处理网点调拨出与调拨入的可用库存数据处理=====================================================
        }else if($type == 2){

            //处理网点盘亏出与调拨入的可用库存数据处理========================================================
            $stock_allocation_out_where = [
                'eso.goods_id'=>['gt', 0],
                'eso.outbound_status'=>10,
                'eso.stock_type'=>4,
                'eso.outbound_type'=>['in',[4,5]],
                'eso.is_reverse'=>2,
                'eso.reversed_status'=>2,
            ];
            $stock_out_deficit = D('ErpStockOut')->alias('eso')->field('eso.*')
                //->join('oil_erp_stock_out eso on eso.source_number = o.order_number')
                ->where($stock_allocation_out_where)
                ->select();
            foreach ($stock_out_deficit as $key=>$value){
                M()->startTrans();
                //$result = $this->influenceStockIn($value);
                $result = $this->_influenceStockIn($value);

                if($result){
                    M()->commit();
                    echo "盘亏出库单处理成功，出库单号：{$value['outbound_code']}\n\r";
                }else{
                    M()->rollback();
                    echo "盘亏出库单处理失败，出库单号：{$value['outbound_code']}\n\r";
                }
            }
            //结束处理网点盘亏出与调拨入的可用库存数据处理=====================================================
        }else if($type == 3){
            $this->initSkidDataStockInDedution();
        }

    }


    /**
     * 服务商出的调拨单影响库存对应入库单的可用数量
     * @author xiaowen
     * @time 2018-07-16
     * @param $stock_out
     * @return array $result
     */
    public function influenceStockIn($stock_out)
    {
        $where = [
            'goods_id'          => $stock_out['goods_id'],
            'our_company_id'    => $stock_out['our_company_id'],
            'storehouse_id'     => $stock_out['storehouse_id'],
            'stock_type'        => $stock_out['stock_type'],
            'region'            => $stock_out['region'],
            'storage_status'    => 10,
            'is_reverse'        => 2,
            'reversed_status'   => 2,
            'balance_num'       => ['gt',0],
        ];
        $stock_in = $this->getModel('ErpStockIn')->where($where)->order('audit_time')->select();

        $outbound_num = $stock_out['actual_outbound_num'];
        $stock_in_numbers = [];
        $stock_in_status_all = true;
        //循环扣减入库单可用
        foreach ($stock_in as $key => $value) {
            if ($outbound_num == 0) {
                break;
            } elseif ($outbound_num <= $value['balance_num']) {
                $stock_in_numbers[$value['storage_code']] = $outbound_num;
                $new_balance_num = $value['balance_num'] - $outbound_num;
                $new_balance_num_litre = $value['balance_num_litre'] - tonToLiter($outbound_num,$value['outbound_density']);
                $outbound_num = 0;
            } elseif ($outbound_num > $value['balance_num']) {
                $outbound_num -= $value['balance_num'];
                $stock_in_numbers[$value['storage_code']] = $value['balance_num'];
                $new_balance_num = 0;
                $new_balance_num_litre = 0;
            }
            //修改入库单可用数量
            $stock_in_data = [
                'update_time'   => currentTime(),
                'balance_num'   => $new_balance_num,
                'balance_num_litre'   => $new_balance_num_litre,
            ];
            $stock_in_status = $this->getModel('ErpStockIn')->saveStockIn(['id' => $value['id']],$stock_in_data);
            $stock_in_status_all = $stock_in_status && $stock_in_status_all ? true : false;
        }
        return ['status' => $stock_in_status_all,'source_stock_in_number' => $stock_in_numbers];
    }

    /**
     * 期初负库存冲减
     * @author xiaowen
     * @time 2018-7-16
     */
    public function initSkidDataStockInDedution(){
        //处理期初负库存===================================================================================

        $init_stock_list = M('erp_stock_skid_data')->where(['stock_num'=>['lt', 0]])->select();

        if($init_stock_list){
            foreach ($init_stock_list as $key=>$value){
                M()->startTrans();
                $handle_status = $this->_handleInitSkidData($value);

                if($handle_status){
                    M()->commit();
                    echo "期初负数冲减成功，期初库存ID{$value['stock_id']}\n\r";
                }else{
                    M()->rollback();
                    echo "期初负数冲减失败，期初库存ID{$value['stock_id']}\n\r";
                }
            }
        }
        //结束 处理期初负库存==============================================================================
    }

    /**
     * 重算ERP库存，调拨出、期初负处理完成后的入库单可用 + 未抹完的期初负
     * @author xiaowen
     * @param  int $storehouse_id 网点ID
     * @time 2018-7-17
     */
    public function resetSumSkidData($storehouse_id = 0){
        $page = $_GET['page'] ?  intval($_GET['page']) : 1;
        $where = ['stock_type'=>4, 'status'=>1];
        if($storehouse_id){
            $where['object_id'] = intval($storehouse_id);
        }
        $skid_stock_data = D('erp_stock')->where($where)->page($page, 1000)->select();
        if(empty($skid_stock_data)){
            echo "^__^  no data 。。。<hr/>";
            return;
        }

        foreach ($skid_stock_data as $k=>$v){
            //匹配该库存下所有入库单可用数量---------------------------------------------------------------------------
            $where_stock_in = [
                'storehouse_id'=>$v['object_id'],
                'stock_type'=>$v['stock_type'],
                'our_company_id'=>$v['our_company_id'],
                'region' => $v['region'],
                'goods_id' => $v['goods_id'],
                'is_reverse' => 2,
                'reversed_status' => 2,
                'storage_status' => 10,
            ];
            $data[$k]['balance_num'] = D('erp_stock_in')->where($where_stock_in)->sum('balance_num');
            $data[$k]['balance_num'] = $data[$k]['balance_num'] ? $data[$k]['balance_num'] : 0;
            // end 匹配该库存下所有入库单可用数量----------------------------------------------------------------------

            //匹配该库存的未抵扣出库数量-------------------------------------------------------------------------------
            $where_stock_out = [
                'stock_type'=>4,
                'outbound_status'=>10,
                'is_reverse'=>2,
                'reversed_status'=>2,
                'goods_id'=>$v['goods_id'],
                'storehouse_id'=>$v['object_id'],
                'our_company_id'=>$v['our_company_id'],
                'region'=>$v['region'],
            ];
            //$stock_out_no_dedution['actual_outbound_num'] = D('erp_stock_out')->where($where_stock_out)->sum('actual_outbound_num');
            //$stock_out_no_dedution['deduction_num'] = D('erp_stock_out')->where($where_stock_out)->sum('deduction_num');
            $stock_out_no_deduction_num = D('erp_stock_out')->field("sum(actual_outbound_num - deduction_num) as no_deduction_num")->where($where_stock_out)->find();

            $stock_out_no_deduction_num = $stock_out_no_deduction_num['no_deduction_num'] ? $stock_out_no_deduction_num['no_deduction_num'] : 0;
            //$stock_out_no_deduction_num = $stock_out_no_dedution['actual_outbound_num'] - $stock_out_no_dedution['deduction_num'];
            //end 匹配该库存的未抵扣出库数量---------------------------------------------------------------------------

            //匹配该库存的期初负库存数量-------------------------------------------------------------------------------
            $init_data = D('erp_stock_skid_data')->where(['stock_id'=>$v['id'], 'stock_num'=>['lt', 0]])->find();
            $init_data['stock_num'] = $init_data['stock_num'] ? $init_data['stock_num'] : 0;
            $init_data['deduction_num'] = $init_data['deduction_num'] ? $init_data['deduction_num'] : 0;
            $data[$k]['init_num'] = $init_data['stock_num'] + $init_data['deduction_num'];
            //end 匹配该库存的期初负库存数量---------------------------------------------------------------------------

            //重新计算物理库存 = 入库单可用数量 + 期初负库存-----------------------------------------------------------
            $data[$k]['stock_num'] = $data[$k]['balance_num'] - $stock_out_no_deduction_num + $data[$k]['init_num'];
            //重新计算物理库存 = 入库单可用数量 + 期初负库存-----------------------------------------------------------

            //重新计算可用库存 = 物理 + 在途 - 调拨预留 - 调拨待提-----------------------------------------------------
            $data[$k]['available_num'] = $data[$k]['stock_num'] + $v['transportation_num'] - $v['allocation_reserve_num'] - $v['allocation_wait_num'];
            //end 重新计算可用库存 = 物理 + 在途 - 调拨预留 - 调拨待提-------------------------------------------------
            D('erp_stock')->where(['id'=>$v['id']])->save(['stock_num'=>$data[$k]['stock_num'], 'available_num'=>$data[$k]['available_num'], 'update_time'=>DateTime()]);
            echo "stock ID: {$v['id']}, OK.\n\r";
        }
    }

    private function _handleInitSkidData($skid_stock_info){

        $where_stock_in = [
            'storehouse_id'=>$skid_stock_info['object_id'],
            'stock_type'=>4,
            'our_company_id'=>$skid_stock_info['our_company_id'],
            'region' => $skid_stock_info['region'],
            'is_reverse' => 2,
            'reversed_status' => 2,
            'storage_status' => 10,
            'balance_num' => ['gt', 0],
        ];
        $all_stock_in = D('erp_stock_in')->where($where_stock_in)
            ->order('audit_time')
            ->select();
        $init_deduction_num = 0;
        $want_deduction_num = plusConvert($skid_stock_info['stock_num']);
        $init_stock_num = $skid_stock_info['stock_num'];
        $init_stock_all_status = true;
        log_info("总负库存数：" . $init_stock_num);
        //期初 + 抵扣 < 0 才进行入库单冲减
        if($skid_stock_info['stock_num'] + $skid_stock_info['deduction_num'] < 0){
            log_info("匹配入库单开始...");
            foreach ($all_stock_in as $k=>$stock_in_data){
                //$dedution_diff_num = $init_deduction_num + $init_stock_num;
                if($want_deduction_num == 0){
                    echo '正确冲完<br/>';
                    break;
                }else {
                    if($stock_in_data['balance_num'] >= $want_deduction_num){
                        $deduction_num = $want_deduction_num;
                        $init_deduction_num += $deduction_num;
                        $want_deduction_num = 0;
                        log_info("累计已冲减-1：" . $init_deduction_num);
                        log_info("剩余冲减值-1：" . $want_deduction_num);
                    }else{
                        $deduction_num = $stock_in_data['balance_num'];
                        $init_deduction_num += $stock_in_data['balance_num'];
                        $want_deduction_num -= $stock_in_data['balance_num'];

                        log_info("累计已冲减-2：" . $init_deduction_num);
                        log_info("剩余冲减值-2 ：" . $want_deduction_num);
                    }
                }
                $stock_in_status = D('erp_stock_in')->where(['storage_code'=>$stock_in_data['storage_code']])
                    ->save(
                        [
                            'balance_num'=>$stock_in_data['balance_num']- $deduction_num,
                            'update_time'=>DateTime(),
                            'deduction_num'=>$stock_in_data['deduction_num'] + $deduction_num,
                        ]
                    );

                $stock_init_status = D('erp_stock_skid_data')->where(['stock_id'=>$skid_stock_info['stock_id']])->save([
                    'deduction_num'=>$init_deduction_num
                ]);
                $change_log = [
                    'source_stock_in_number' => $stock_in_data['storage_code'],
                    'type' => 1,
                    'stock_id' => $skid_stock_info['stock_id'],
                    'before_balance_num' => $stock_in_data['balance_num'],
                    'after_balance_num' => $stock_in_data['balance_num']- $deduction_num,
                    'deduction_num' => $deduction_num,
                    'create_time' => DateTime(),
                ];
                $stock_in_change_log = D('erp_stock_in_deduction')->add($change_log);

                $init_stock_all_status = $stock_in_status && $stock_init_status && $stock_in_change_log && $init_stock_all_status ? true : false;

            }
        }
        //exit;
        return $init_stock_all_status;

    }

    /**
     * 入库单抵扣网点调拨出库
     * @author xiaowen
     * @time 2018-07-16
     * @param $stock_out
     * @return array $result
     */
    public function _influenceStockIn($stock_out)
    {
        $where = [
            'goods_id'          => $stock_out['goods_id'],
            'our_company_id'    => $stock_out['our_company_id'],
            'storehouse_id'     => $stock_out['storehouse_id'],
            'stock_type'        => $stock_out['stock_type'],
            'region'            => $stock_out['region'],
            'storage_status'    => 10,
            'is_reverse'        => 2,
            'reversed_status'   => 2,
            'balance_num'       => ['gt',0],
        ];
        $stock_in = $this->getModel('ErpStockIn')->where($where)->order('audit_time')->select();

        $outbound_num = $stock_out['actual_outbound_num'];
        //$stock_in_numbers = [];
        $stock_in_status_all = true;
        $out_deduction_num = 0;
        //print_r($stock_in);
        //exit;
        //循环扣减入库单可用
        foreach ($stock_in as $key => $value) {
            if ($outbound_num == 0) {
                break;
            } elseif ($outbound_num <= $value['balance_num']) {
                $out_deduction_num += $outbound_num;
                $deduction_num = $outbound_num;
//                log_info("出库数量-1 {$outbound_num}");
//                log_info("本次抵扣-1 {$out_deduction_num}");
//                log_info("累计抵扣-1 {$out_deduction_num}");
                $outbound_num = 0;
            } elseif ($outbound_num > $value['balance_num']) {
                $outbound_num -= $value['balance_num'];
                $out_deduction_num += $value['balance_num'];
                $deduction_num = $value['balance_num'];
//                log_info("出库数量-2 {$outbound_num}");
//                log_info("本次抵扣-2 {$out_deduction_num}");
//                log_info("累计抵扣-2 {$out_deduction_num}");

            }
            //修改入库单可用数量
            //$stock_in_status_all = $stock_in_status && $stock_in_status_all ? true : false;
            $stock_in_status = D('erp_stock_in')->where(['storage_code'=>$value['storage_code']])
                ->save(
                    [
                        'balance_num'=>$value['balance_num']- $deduction_num,
                        'update_time'=>DateTime(),
                        'deduction_num'=>$value['deduction_num'] + $deduction_num,
                    ]
                );

            $change_log = [
                'source_stock_in_number' => $value['storage_code'],
                'outbound_code'=>$stock_out['outbound_code'],
                'type' => 2,
                'stock_id' => 0,
                'before_balance_num' => $value['balance_num'],
                'after_balance_num' => $value['balance_num']- $deduction_num,
                'deduction_num' => $deduction_num,
                'create_time' => DateTime(),
            ];
            $stock_in_change_log = D('erp_stock_in_deduction')->add($change_log);
            $stock_in_status_all = $stock_in_status  && $stock_in_change_log && $stock_in_status_all ? true : false;
        }
        $stock_out_status = D('erp_stock_out')->where(['outbound_code'=>$stock_out['outbound_code']])->save([
            'update_time' => DateTime(),
            'deduction_num'=>$out_deduction_num
        ]);
        return $stock_in_status_all && $stock_out_status;
    }

    /**
     * 重算ERP库存，调拨出、期初负处理完成后的入库单可用 + 未抹完的期初负
     * @author xiaowen
     * @time 2018-7-17
     */
    public function resetSumSkidDataNew(){
        $page = $_GET['page'] ?  intval($_GET['page']) : 1;
        $skid_stock_data = D('erp_stock')->where(['stock_type'=>4, 'status'=>1])->page($page, 1000)->select();
        if(empty($skid_stock_data)){
            echo "^__^  no data 。。。<hr/>";
            return;
        }

        foreach ($skid_stock_data as $k=>$v){
            //匹配该库存下所有入库单可用数量---------------------------------------------------------------------------
            $where_stock_in = [
                'storehouse_id'=>$v['object_id'],
                'stock_type'=>$v['stock_type'],
                'our_company_id'=>$v['our_company_id'],
                'region' => $v['region'],
                'goods_id' => $v['goods_id'],
                'is_reverse' => 2,
                'reversed_status' => 2,
                'storage_status' => 10,
            ];
            $data[$k]['balance_num'] = D('erp_stock_in')->where($where_stock_in)->sum('balance_num');
            $data[$k]['balance_num'] = $data[$k]['balance_num'] ? $data[$k]['balance_num'] : 0;
            // end 匹配该库存下所有入库单可用数量----------------------------------------------------------------------

            //匹配该库存的未抵扣出库数量-------------------------------------------------------------------------------
            $where_stock_out = [
                'stock_type'=>4,
                'outbound_status'=>10,
                'is_reverse'=>2,
                'reversed_status'=>2,
                'goods_id'=>$v['goods_id'],
                'storehouse_id'=>$v['object_id'],
                'our_company_id'=>$v['our_company_id'],
                'region'=>$v['region'],
            ];
            //$stock_out_no_dedution['actual_outbound_num'] = D('erp_stock_out')->where($where_stock_out)->sum('actual_outbound_num');
            //$stock_out_no_dedution['deduction_num'] = D('erp_stock_out')->where($where_stock_out)->sum('deduction_num');
            //$stock_out_no_deduction_num = D('erp_stock_out')->field("sum(actual_outbound_num - deduction_num) as no_deduction_num")->where($where_stock_out)->find();
            $stock_out_no_deduction_num = 0;
            $stock_out_no_deduction_num = $stock_out_no_deduction_num['no_deduction_num'] ? $stock_out_no_deduction_num['no_deduction_num'] : 0;
            //$stock_out_no_deduction_num = $stock_out_no_dedution['actual_outbound_num'] - $stock_out_no_dedution['deduction_num'];
            //end 匹配该库存的未抵扣出库数量---------------------------------------------------------------------------

            //匹配该库存的期初负库存数量-------------------------------------------------------------------------------
//            $init_data = D('erp_stock_skid_data')->where(['stock_id'=>$v['id'], 'stock_num'=>['lt', 0]])->find();
//            $init_data['stock_num'] = $init_data['stock_num'] ? $init_data['stock_num'] : 0;
//            $init_data['deduction_num'] = $init_data['deduction_num'] ? $init_data['deduction_num'] : 0;
//            $data[$k]['init_num'] = $init_data['stock_num'] + $init_data['deduction_num'];
            $data[$k]['init_num'] = 0;
            //end 匹配该库存的期初负库存数量---------------------------------------------------------------------------

            //重新计算物理库存 = 入库单可用数量 + 期初负库存-----------------------------------------------------------
            $data[$k]['stock_num'] = $data[$k]['balance_num'] - $stock_out_no_deduction_num + $data[$k]['init_num'];
            //重新计算物理库存 = 入库单可用数量 + 期初负库存-----------------------------------------------------------

            //重新计算可用库存 = 物理 + 在途 - 调拨预留 - 调拨待提-----------------------------------------------------
            $data[$k]['available_num'] = $data[$k]['stock_num'] + $v['transportation_num'] - $v['allocation_reserve_num'] - $v['allocation_wait_num'];
            //end 重新计算可用库存 = 物理 + 在途 - 调拨预留 - 调拨待提-------------------------------------------------
            D('erp_stock')->where(['id'=>$v['id']])->save(['stock_num'=>$data[$k]['stock_num'], 'available_num'=>$data[$k]['available_num'], 'update_time'=>DateTime()]);
            echo "stock ID: {$v['id']}, OK.\n\r";
        }
    }

    /**
     * 验证ERP零售数据和NC表数据一致性
     * @time 2018-08-27
     * @author guanyu
     */
    public function checkErpRetailAndNcRetail(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $erp_retail_order_count = $this->getModel('ErpSaleRetailOrder')->count('id');
        $nc_count_sql = 'SELECT COUNT(id) FROM nc.nc_order';
        $nc_retail_order_count = M()->query($nc_count_sql);
        echo 'ERP零售数据总数：'.$erp_retail_order_count.'<br/>';
        echo 'NC中间表数据总数：'.$nc_retail_order_count[0]['count(id)'].'<br/><br/>';
        $erp_retail_order = $this->getModel('ErpSaleRetailOrder')
            ->where(['order_status'=>10])
            ->field('COUNT(id) AS erp_count,from_order_number AS erp_order_number')
            ->group('from_order_number')
            ->getField('from_order_number AS erp_order_number,COUNT(id) AS erp_count');
        $nc_data_sql = 'SELECT count(id) AS nc_count,order_number AS nc_order_number FROM nc.nc_order GROUP BY order_number';
        $nc_retail = M()->query($nc_data_sql);
        $nc_retail_order = [];
        foreach ($nc_retail as $value) {
            $nc_retail_order[$value['nc_order_number']] = $value['nc_count'];
        }
        $diff_data_erp_to_nc = [];
        $index = 0;
        foreach ($erp_retail_order as $key=>$value) {
            if (!isset($nc_retail_order[$key])) {
                $diff_data_erp_to_nc[$index]['order_number'] = $key;
                $diff_data_erp_to_nc[$index]['type'] = 'nc_order中没有该数据';
                $index++;
                continue;
            }
            if ($nc_retail_order[$key] != $value) {
                $diff_data_erp_to_nc[$index]['order_number'] = $key;
                $diff_data_erp_to_nc[$index]['erp_count'] = $value;
                $diff_data_erp_to_nc[$index]['nc_count'] = $nc_retail_order[$key];
                $diff_data_erp_to_nc[$index]['type'] = '该订单在两表出现次数不同';
                $index++;
                continue;
            }
        }

        $diff_data_nc_to_erp = [];
        $index = 0;
        foreach ($nc_retail_order as $key=>$value) {
            if (!isset($erp_retail_order[$key])) {
                $diff_data_nc_to_erp[$index]['order_number'] = $key;
                $diff_data_nc_to_erp[$index]['type'] = 'erp中没有该数据';
                $index++;
                continue;
            }
            if ($erp_retail_order[$key] != $value) {
                $diff_data_nc_to_erp[$index]['order_number'] = $key;
                $diff_data_nc_to_erp[$index]['erp_count'] = $erp_retail_order[$key];
                $diff_data_nc_to_erp[$index]['nc_count'] = $value;
                $diff_data_nc_to_erp[$index]['type'] = '该订单在两表出现次数不同';
                $index++;
                continue;
            }
        }

        var_dump($diff_data_erp_to_nc);
        echo '<br/><br/><br/><br/>';
        var_dump($diff_data_nc_to_erp);
    }

    /**
     * 合并网点-执行入口
     * @param $arr
     */
    function mergeStorehouseData($arr){
        $status = true;
        M()->startTrans();
        foreach ($arr as $key=>$value){

            $message = '';
            //刷网点基础资料
            $status_1 = $this->mergeStorehouseUpdateStorehouse($key, $value);
            $message .= "老网点：{$key} => 新网点：$value 基础资料处理" . ($status_1 ? '成功' : '失败');

            //刷网点出入调拨单
            $status_2 = $this->mergeStorehouseUpdateAllocation($key, $value);
            $message .= " ,调拨单处理".($status_2 ? '成功' : '失败');
            //刷网点入库单
            $status_3 = $this->mergeStorehouseUpdateStockIn($key, $value);
            $message .= " ,入库单处理".($status_3 ? '成功' : '失败');
            //刷网点出库单
            $status_4 = $this->mergeStorehouseUpdateStockOut($key, $value);
            $message .= " ,出库单处理".($status_4 ? '成功' : '失败');
            //刷网点库存记录
            $status_5 = $this->mergeStorehouseUpdateStockData($key, $value);
            $message .= " ,库存处理".($status_5 ? '成功' : '失败');
            //刷网点零售订单
            $status_6 = $this->mergeStorehouseUpdateSaleRetailOrder($key, $value);
            $message .= " ,零售订单处理".($status_6 ? '成功' : '失败');
            //刷网点零售出库单
            $status_7 = $this->mergeStorehouseUpdateStockOutRetail($key, $value);
            $message .= " ,零售出库单处理".($status_7 ? '成功' : '失败');
            //刷网点期初库存
            $status_8 = $this->mergeStorehouseUpdateStockSkidData($key, $value);
            $message .= " ,期初库存处理".($status_8 ? '成功' : '失败') . "<br/>";
            //刷网点成本表
            //$status_9 = $this->mergeStorehouseUpdateCostData($key, $value);
            //$message .= " ,成本处理".($status_9 ? '成功' : '失败')."<br/>";
            $status = $status && $status_1 && $status_2 && $status_3  && $status_4  && $status_5 && $status_6 && $status_7 && $status_8;// && $status_9;
            echo $message;
        }
        if($status){
            M()->commit();
            echo '数据已全部处理完成^__^<br/>';
        }else{
            M()->rollback();
            echo '数据已处理失败^__^<br/>';
        }

        return $status;
    }

    /**
     * 合并网点-执行入口
     * @param $arr
     */
    function mergeSupplierData($arr){
        $status = true;
        M()->startTrans();
        foreach ($arr as $key=>$value){
            $message = '';
            //刷服务商 基础资料表
            $status_11 = $this->mergeSupplierUpdateSupplier($key, $value);
            $message .= "老服务商：{$key} => 新服务商：$value 基础资料处理" . ($status_11 ? '成功' : '失败');

            //刷服务商 调拨单表
            $status_12 = $this->mergeSupplierUpdateAllocation($key, $value);
            $message .= ",调拨单处理" . ($status_12 ? '成功' : '失败');

            //刷服务商 采购、应付表
            $status_13 = $this->mergeSupplierUpdatePurchaseOrderPayment($key, $value);
            $message .= ",采购、应付处理" . ($status_13 ? '成功' : '失败');


            //刷服务商 预付表
            $status_14 = $this->mergeSupplierUpdateRechargeOrder($key, $value);
            $message .= ",预付单处理" . ($status_14 ? '成功' : '失败');


            //刷服务商 预付账户表
            $status_15 = $this->mergeSupplierUpdateAccount($key, $value);
            $message .= ",预付账户表处理" . ($status_15 ? '成功' : '失败') . '<br/>';
            echo $message;
        }
        if($status){
            M()->commit();
            echo '数据已全部处理完成^__^<br/>';
        }else{
            M()->rollback();
            echo '数据已处理失败^__^<br/>';
        }
    }

    /**
     * 合并网点-基础资料-保留最终网点
     * @param $new_data
     * @author xiaowen
     * @return bool
     */
    public function mergeSaveLastStorehouse($new_data){
        $status = true;
        M()->startTrans();
        if(!empty($new_data)){
            foreach ($new_data as $key=>$value){
                $tmp_data = [
                    //'status'=>1,
                    'update_time'=>DateTime(),
                    'is_new'=>$value['is_new'],
                    'storehouse_name'=>$value['storehouse_name'],
                    'company_id'=>$value['company_id'] ? $value['company_id'] : 0,
                    'data_source_oms_id'=>$value['data_source_oms_id'] ? $value['data_source_oms_id'] : 0,
                    'data_source_front_id'=>$value['data_source_front_id'] ? $value['data_source_front_id'] : 0,
                ];
                if($tmp_data['is_new']==0){
                    unset($tmp_data['is_new']);
                }
                $new_status = $this->getModel('ErpStorehouse')->where(['id'=>$value['id']])->save($tmp_data);
                $status = $status && ($new_status !== false);
                //echo $new_status ? "storehouse id:{$value['id']} is success. \n\r<br/>" : "storehouse id:{$value['id']} is fail. \n\r<br/>";
            }

        }

        echo $status ? '保留数据更新成功' : '保留数据更新失败'."<br/>";

        if($status){
            M()->startTrans();
        }else{
            M()->commit();
        }
        return $status ;

    }
    /**
     * 合并网点-基础资料
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeStorehouseUpdateStorehouse($old_id,$new_id){

        //echo '处理网点基础资料';
        if($old_id && !empty($new_id)){
            $old_status = $this->getModel('ErpStorehouse')->where(['id'=>$old_id, 'type'=>7])->save(['status'=>2, 'update_time'=>DateTime()]);

            return $old_status !== false ? true : false;
            //echo $old_status ? "storehouse id:{$old_id} is success. \n\r<br/>" : "storehouse id:{$old_id} is fail. \n\r<br/>";

        }
        return false;

    }

    /**
     * 合并服务商-保留最终服务商基础资料
     * @param $new_data
     * @return bool
     */
    public function mergeSaveLastSupplier($new_data){
        M()->startTrans();
        $status = true;
        if(!empty($new_data)){
            foreach ($new_data as $key=>$value){
                $new_status = $this->getModel('ErpSupplier')->where(['id'=>$value['id']])->save([
                    'update_time'=>DateTime(),
                    'supplier_name'=>trim($value['supplier_name']) ? trim($value['supplier_name']) : 0,
                    'data_source_oms_id'=>$value['data_source_oms_id'] ? $value['data_source_oms_id'] : 0,
                    'data_source_front_id'=>$value['data_source_front_id'] ? $value['data_source_front_id'] : 0,
                    'data_source_front_clients_id'=>$value['data_source_front_clients_id'] ? $value['data_source_front_clients_id'] : 0,
                    'business_attributes'=>1,
                    'data_source'=>3,
                    'is_new_data'=>1,
                ]);
                //return $status !== false ? true : false;
                //echo "storehouse id:{$value['id']} is handle.\n\r<br>";
                $status = $status && ($new_status !== false);
            }

        }
        if($status){
            M()->commit();
            echo "服务商基础保留数据更新成功！<br/>";
            return $status;
        }else{
            M()->rollback();
            echo "服务商基础保留数据更新失败！<br/>";
            exit;
            return false;
        }

    }
    /**
     * 合并服务商-基础资料
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeSupplierUpdateSupplier($old_id, $new_id){
        if($new_id && $old_id){

            $status = $this->getModel('ErpSupplier')->where(['id'=>$old_id])->save([
                'status'=>2,
                'update_time'=>DateTime(),
                'business_attributes'=>1,
                'data_source'=>3,
            ]);

            return $status !== false ? true : false;
            //echo "supplier id:{$old_id} 老服务商置为无效<br>";

        }
        return false;
    }

    /**
     * 合并网点-调拨单处理
     * @time 2018-12-27
     * @param $old_id
     * @param $new_id
     * @return  boolean
     */
    public function mergeStorehouseUpdateAllocation($old_id, $new_id){
        //更新调入网点数据
        $in_status = $this->getModel('ErpAllocationOrder')->where(['in_storehouse'=>$old_id])->save([
            'in_storehouse' => $new_id,
        ]);
        //更新调出网点数据
        $out_status = $this->getModel('ErpAllocationOrder')->where(['out_storehouse'=>$old_id])->save([
            'out_storehouse' => $new_id,
        ]);
        return $in_status !== false && $out_status !== false ? true : false;
    }

    /**
     * 合并服务商-调拨单处理
     * @time 2018-12-27
     * @param $old_id
     * @param $new_id
     * @return boolean
     */
    public function mergeSupplierUpdateAllocation($old_id, $new_id){
        //更新调入服务商数据
        $status_in = $this->getModel('ErpAllocationOrder')->where(['in_facilitator_id'=>$old_id])->save([
            'in_facilitator_id' => $new_id,
        ]);
        //更新调出服务商数据
        $status_out = $this->getModel('ErpAllocationOrder')->where(['out_facilitator_id'=>$old_id])->save([
            'in_facilitator_id' => $new_id,
        ]);

        return $status_in !== false && $status_out !== false ? true : false;
    }

    /**
     * 合并网点-入库单处理
     * @param $old_id
     * @param $new_id
     * @return boolean
     */
    public function mergeStorehouseUpdateStockIn($old_id, $new_id){
        if($old_id && !empty($new_id)){
            $status = $this->getModel('ErpStockIn')->where(['storehouse_id'=>$old_id, 'stock_type'=>4])->save([
                'storehouse_id'=>$new_id,
                'update_time'=>DateTime(),
            ]);
            return $status !== false ? true : false;
        }
    }

    /**
     * 合并网点-出库单处理
     * @time 2018-12-27
     * @param $old_id
     * @param $new_id
     */
    public function mergeStorehouseUpdateStockOut($old_id, $new_id){
        if($old_id && !empty($new_id)){
            $status = $this->getModel('ErpStockOut')->where(['storehouse_id'=>$old_id, 'stock_type'=>4])->save([
                'storehouse_id'=>$new_id,
                'update_time'=>DateTime(),
            ]);
            //echo $this->getModel('ErpStockOut')->getLastSql();
            return $status !== false ? true : false;
        }
    }

    /**
     * 合并服务商-采购、采购应付处理
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeSupplierUpdatePurchaseOrderPayment($old_id, $new_id){
        if($old_id && !empty($new_id)){
            //刷采购单服务商公司ID
            $order_status = $this->getModel('ErpPurchaseOrder')->where(['sale_company_id'=>$old_id])->save([
                'sale_company_id'=>$new_id,
                'update_time'=>DateTime(),
            ]);
            //刷采购单服务商公司ID
            $payment_status = $this->getModel('ErpPurchasePayment')->where(['sale_company_id'=>$old_id])->save([
                'sale_company_id'=>$new_id,
                //'update_time'=>DateTime(),
            ]);
            return $order_status !== false && $payment_status != false ? true : false;
        }
        return false;
    }

    /**
     * 合并服务商-刷预付单
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeSupplierUpdateRechargeOrder($old_id, $new_id){

        if($old_id && !empty($new_id)){
            $order_status = $this->getModel('ErpRechargeOrder')->where(['company_id'=>$old_id, 'order_type'=>2])->save([
                'company_id'=>$new_id,
                'update_time'=>DateTime(),
            ]);

            return $order_status !== false ? true : false;
        }
        return false;
    }

    /**
     * 合并网点-刷零售销售单
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeStorehouseUpdateSaleRetailOrder($old_id, $new_id){
        if($old_id && !empty($new_id)){
            $order_status = $this->getModel('ErpSaleRetailOrder')->where(['storehouse_id'=>$old_id])->save([
                'storehouse_id'=>$new_id,
                'update_time'=>DateTime(),
            ]);

            return $order_status !== false ? true : false;
        }
        return false;
    }

    /**
     * 合并网点-刷零售出库单
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeStorehouseUpdateStockOutRetail($old_id, $new_id){
        if($old_id && !empty($new_id)){
            $order_status = $this->getModel('ErpStockOutRetail')->where(['storehouse_id'=>$old_id])->save([
                'storehouse_id'=>$new_id,
                'update_time'=>DateTime(),
            ]);

            return $order_status !== false ? true : false;
        }
        return false;
    }

    /**
     * 网点合并-更新成本表
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeStorehouseUpdateCostData($old_id, $new_id){
        if($old_id && !empty($new_id)){
            //$order_status = '';
            $order_status = M()->table('erp_cost.oil_cost_info')->where(['object_id'=>$old_id, 'stock_type'=>4])->save([
                'object_id' => $new_id,
            ]);

            $order_log_status = M()->table('erp_cost.oil_cost_info_log')->where(['object_id'=>$old_id, 'stock_type'=>4])->save([
                'object_id' => $new_id,
            ]);
            return $order_status !== false && $order_log_status !== false ? true : false;
        }
        return false;
    }

    /**
     * 网点合并-更新网点期初库存表
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeStorehouseUpdateStockSkidData($old_id, $new_id){
        if($old_id && !empty($new_id)){
            //$order_status = '';
            $order_status = M('erp_stock_skid_data')->where(['object_id'=>$old_id, 'stock_type'=>4])->save([
                'object_id' => $new_id,
            ]);
            return $order_status !== false ? true : false;
        }
        return false;
    }

    /**
     * 合并服务商-预付余额
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeSupplierUpdateAccount($old_id, $new_id){
        if($old_id && !empty($new_id)){
            $old_account_data = M('erp_account')->where(['company_id'=>$old_id, 'account_type'=>2, 'status'=>1])->select();
            if(!empty($old_account_data)){
                $status = true;
                foreach ($old_account_data as $key=>$value){
                    //查询并判断新服务商是否存在预付余额记录
                    $new_account_data = M('erp_account')->where(['company_id'=>$new_id, 'our_company_id'=>$value['our_company_id'],'account_type'=>2])->find();
                    if($new_account_data){
                        //将老服务商余额加到新服务商上
                        $account_status = M('erp_account')->where(['company_id'=>$new_id, 'our_company_id'=>$new_account_data['our_company_id'], 'account_type'=>2])->save([
                            'account_balance' => $new_account_data['account_balance'] + $value['account_balance'],
                            'update_time' => DateTime(),
                        ]);
                        //老服务商账户设置为无效
                        $old_status = M('erp_account')->where(['id'=>$value['id'], 'account_type'=>2])->save([
                            'status' => 2,
                            'update_time' => DateTime(),
                        ]);
                        $status = $status && ($account_status !== false) && ($old_status !== false);
                    }else{
                        $account_status = true;
                            //沒有新服务商余额,则将老服务商直接修改为新服务商
                        $old_status = M('erp_account')->where(['id'=>$value['id'], 'account_type'=>2])->save([
                            'company_id' => $new_id,
                            'update_time' => DateTime(),
                        ]);
                        $status = $status && ($account_status !== false) && ($old_status !== false);
                    }
                }
                return $status;
            }
            else{
                return true;
            }

        }
        return false;

    }

    /**
     * 合并网点--处理库存数据
     * @param $old_id
     * @param $new_id
     * @return bool
     */
    public function mergeStorehouseUpdateStockData($old_id, $new_id){
        if($old_id && !empty($new_id)){
            $status = true;
            //查询新网点库存记录
            //$new_stock_data = M('erp_stock')->where(['object_id'=>$new_data['id'], 'stock_type'=>4, 'status'=>1])->select();
            $old_stock_data = M('erp_stock')->where(['object_id'=>$old_id, 'stock_type'=>4, 'status'=>1])->select();

            //判断是否存在新网点库存记录，如果存在，则将老网点对应的库存加到新网点上，否则直接将老网点ID更新为新网点ID
            if(!empty($old_stock_data)){
                foreach ($old_stock_data as $key=>$value){
                    $where_tmp = [
                        //'object_id'=>$old_id,
                        'object_id'=>$new_id,
                        'stock_type'=>4,
                        'goods_id'=>$value['goods_id'],
                        'region'=>$value['region'],
                        'our_company_id'=>$value['our_company_id'],
                        'status'=>1
                    ];
                    //查询新网点与老网点匹配的库存记录
                    $new_stock_data = M('erp_stock')->where($where_tmp)->find();

                    //如果新网点库存存在，则将老网点库存加到新网点上，且老记录设为无效状态
                    if($new_stock_data){
                        $new_status = M('erp_stock')->where(['id'=>$new_stock_data['id']])->save([
                            'stock_num' => $value['stock_num'] + $new_stock_data['stock_num'],
                            'transportation_num' => $value['stock_num'] + $new_stock_data['stock_num'],
                            'sale_reserve_num' => $value['sale_reserve_num'] + $new_stock_data['sale_reserve_num'],
                            'allocation_reserve_num' => $value['allocation_reserve_num'] + $new_stock_data['allocation_reserve_num'],
                            'sale_wait_num' => $value['sale_wait_num'] + $new_stock_data['sale_wait_num'],
                            'allocation_wait_num' => $value['allocation_wait_num'] + $new_stock_data['allocation_wait_num'],
                            'available_num' => $value['available_num'] + $new_stock_data['available_num'],
                            'update_time'=>DateTime()
                        ]);
                        //更新老库存状态
                        $old_status = M('erp_stock')->where(['id' => $value['id']])->save(['status'=>2, 'update_time'=>DateTime()]);
                        $status = $status && ($new_status !== false) && ($old_status !== false);
                    }else{
                        $new_status = true;
                        //如果不存在新网点库存，则直接将老网点ID更新为新网点ID
                        $old_status = M('erp_stock')->where(['id' => $value['id']])->save([
                            'object_id'=>$new_id
                        ]);
                        $status = $status && ($new_status !== false) && ($old_status !== false);
                    }

                }
            }

            return $status;

        }
        return false;

    }



}



