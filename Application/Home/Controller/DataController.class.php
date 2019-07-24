<?php
namespace Home\Controller;

use Think\Cache;
use Think\Controller;
use Home\Controller\BaseController;

/**
 * Class DataController
 * @package Home\Controller
 *
 * 成本、库存 数据处理
 *
 */

class DataController extends BaseController
{
    public function index(){
        echo 'test';
    }
    //public  $limitNum;

    //const Limit = 100;
    /**
     * 导出区域密度
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportRegionGoodsData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');

        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib('商品区域维护' . currentTime('Ymd').'.csv');
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '地区ID','地区','省份','商品ID','商品编号','商品名称','商品来源','级别','标号','密度',
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);

        //分批生成数据
        //$page = 1;
        $data = D('area')->field('a.*,aa.area_name as parent_name')->alias('a')->join('oil_area aa on a.parent_id = aa.id', 'left')->where(['a.area_type'=>2])->select();
        //print_r($data);
        //exit;
        $goods_data = D('ErpGoods')->field(true)->where(['status'=>10])->select();
        //print_r($goods_data);
        $arr = [];
        //exit;
        $i = 0;
        foreach($data as $k=>$v){
            foreach($goods_data as $key=>$value){
                $arr[$i]['region_id']           = $v['id'];
                $arr[$i]['region_name']        = $v['area_name'];
                $arr[$i]['parent_name']      = $v['parent_name'];
                $arr[$i]['goods_id']        = $value['id'];
                $arr[$i]['goods_code']        = $value['goods_code'];
                $arr[$i]['goods_name']         = $value['goods_name'];
                $arr[$i]['source_from']         = $value['source_from'];
                $arr[$i]['level']        = $value['level'];
                $arr[$i]['grade']      = $value['grade'];
                $arr[$i]['density_value']         = '';//$value['density_value'];
                $i++;
            }

        }
        //分批生成CSV内容
        $csvObj->exportCsv($arr);
        //查询下一页数据，设置起始偏移量
        //$page++;
        //$param['start'] = ($page - 1 ) * $param['length'];
        //关闭文件句柄
        $csvObj->closeFile();
    }

    /**
     * 网点库存重算并生成零售销售单及出库单
     */
    public function stockDataOrder(){
        set_time_limit(10000);
        ini_set('memory_limit', '528M');
        $field = 's.id,s.goods_id,s.object_id,s.stock_type,s.region,s.facilitator_id,s.stock_num,s.our_company_id,g.goods_code,g.level,g.grade,g.goods_name,g.source_from';
        $stock_info = D('ErpStock')->alias('s')->field($field)->join('oil_erp_goods g on s.goods_id = g.id')->where(['s.status'=>1, 's.stock_type'=>4])->select();
        foreach($stock_info as $k=>$v){
            $stock_allocation_in_where = [
                'in_facilitator_skid_id'=>$v['object_id'],
                'status'=>10,
                'storage_status'=>1,
                'allocation_type'=>['in','1,2'],
                'in_region'=>$v['region'],
                'goods_id'=>$v['goods_id'],
            ];
            $allocation_in_total = D('ErpAllocationOrder')->where($stock_allocation_in_where)->sum('actual_in_num');
            $stock_allocation_out_where = [
                'out_facilitator_skid_id'=>$v['object_id'],
                'status'=>10,
                'outbound_status'=>1,
                'allocation_type'=>['in','2,3'],
                'out_region'=>$v['region'],
                'goods_id'=>$v['goods_id'],
            ];
            $allocation_out_total = D('ErpAllocationOrder')->where($stock_allocation_out_where)->sum('actual_out_num');

            $stock_info[$k]['allocation_in_total'] = $allocation_in_total ? $allocation_in_total : 0;
            $stock_info[$k]['allocation_out_total'] = $allocation_out_total ? $allocation_out_total : 0;

            $init_stock_where = [
                'goods_id'=>$v['goods_id'],
                'object_id'=>$v['object_id'],
                'our_company_id'=>$v['our_company_id'],
                'region'=>$v['region'],
                'stock_type'=>$v['stock_type'],
            ];
            $stock_info[$k]['init_num'] = D('erp_stock_skid_data')->where($init_stock_where)->getField('stock_num');
            $stock_info[$k]['init_num'] = $stock_info[$k]['init_num'] ? $stock_info[$k]['init_num'] : 0;
            $stock_info[$k]['allocation_balance_total'] = $stock_info[$k]['allocation_in_total'] + $stock_info[$k]['init_num'] - $stock_info[$k]['allocation_out_total'];

            //更新重算后的物理库存;
            //$stock_info[$k]['available_num'] = $stock_info[$k]['allocation_balance_total'] + $stock_info[$k]['transportation_num'] - $stock_info[$k]['sale_reserve_num'] - $stock_info[$k]['allocation_reserve_num']- $stock_info[$k]['sale_wait_num']- $stock_info[$k]['allocation_wait_num'];

            $status = D('ErpStock')->where(['id'=>$v['id']])->save(['stock_num'=>$stock_info[$k]['allocation_balance_total'],'available_num'=>$stock_info[$k]['allocation_balance_total'],'update_time'=> currentTime()]);
            echo $status ? "调拨节余计算成功\n\r" : "调拨节余计算失败" ."\n\r";
        }

        //print_r($stock_info);
        exit;

    }

    public function stockDataHandel()
    {
        set_time_limit(10000);
        ini_set('memory_limit', '528M');
        $where = array('stock_type'=>4, 'status'=>1);
        $object_id = D("erp_stock")->where($where)->getField('object_id', true);

        //$this->stockDataByObjectId(91);
        //print_r($object_id);
        //exit;
        foreach($object_id as $id){
            $this->stockDataByObjectId($id);
        }
    }

    /**
     * 处理重算后的网点负库存
     */
    public function stockDataByObjectId($object_id)
    {

        set_time_limit(10000);
        ini_set('memory_limit', '528M');
//        $stock_where = [
//            'status' => 1,
//            'stock_type' => 4,
//        ];
        $where = array('object_id'=>$object_id, 'stock_type'=>4, 'status'=>1);
        $erp_stock = D("erp_stock")->where($where)->select();

        foreach ($erp_stock as $k=>$v) {
            if ($v['stock_num'] > 0) {
                $stock_num_gt[] = array(
                    'id' => $v['id'],
                    'object_id' => $v['object_id'],
                    'stock_num' => $v['stock_num']
                );
            } elseif($v['stock_num'] < 0) {
                $stock_num_et[] = array(
                    'id' => $v['id'],
                    'object_id' => $v['object_id'],
                    'stock_num' => $v['stock_num']
                );
            }
        }

        //print_r($stock_num_gt);
        //print_r($stock_num_et);
        //exit;
        foreach($stock_num_et as $k=>&$v) {
            foreach($stock_num_gt as $kk=>$vv) {
                if ($vv['stock_num'] + $v['stock_num'] == 0) {
                    $stock_num_gt[$kk]['stock_num'] = 0;
                    $stock_num_et[$k]['stock_num'] = 0;
                } elseif ($vv['stock_num'] + $v['stock_num'] > 0) {
                    $stock_num_gt[$kk]['stock_num'] = $vv['stock_num'] + $v['stock_num'];
                    $stock_num_et[$k]['stock_num'] = 0;
                } elseif ($vv['stock_num'] + $v['stock_num'] < 0) {
                    $stock_num_gt[$kk]['stock_num'] = 0;
                    $stock_num_et[$k]['stock_num'] = $vv['stock_num'] + $v['stock_num'];
                }
                //echo ($stock_num_et[$k]['id']."====".$stock_num_et[$k]['stock_num'])."\n";
                //echo ($stock_num_gt[$kk]['id']."====".$stock_num_gt[$kk]['stock_num'])."\n";

            }
            //echo ($stock_num_et[$k]['id']."====".$stock_num_et[$k]['stock_num'])."\n";
            //echo '================'."\n";
        }

        $row = array(
            'gt' => $stock_num_gt,
            'et' => $stock_num_et
        );
        foreach($row as $key=>$value){
            foreach($value as $ks=>$vs){
                //print_r($vs);
                $status = D("erp_stock")->where(['id'=>$vs['id']])->save(['stock_num'=>$vs['stock_num'], 'available_num'=>$vs['stock_num'],'update_time'=>currentTime()]);
                //echo $status ? "库存调整成功\n\r" : "库存调整失败\n\r";
            }
        }
        echo $status ? "库存调整成功\n\r" : "库存调整失败\n\r";
        //print_r($row);

        //exit;


//        $field = 's.id,s.goods_id,s.object_id,s.stock_type,s.region,s.facilitator_id,s.stock_num,s.our_company_id,g.goods_code,g.level,g.grade,g.goods_name,g.source_from';
//        //查询该网点下的负库存
//        $stock_info = D('ErpStock')->alias('s')->field($field)->join('oil_erp_goods g on s.goods_id = g.id')->where(['s.status' => 1, 's.stock_type' => 4, 's.stock_num'=>['lt', 0]])->select();
//        //print_r($stock_info);
//        //exit;
//        foreach ($stock_info as $k => &$v) {
//            //查询该网点下的正库存
//            $data =  D('ErpStock')->alias('s')->where(['s.status' => 1, 's.stock_type' => 4, 's.stock_num'=>['gt', 0], 's.object_id'=>$v['object_id']])->getField('id,object_id, stock_num');
//            //print_r($data);
//            //exit;
//            log_info("库存ID{$v['id']},  网点ID{$v['object_id']},  原始负库存数：". $v['stock_num']);
//            if($data){
//                //$old_stock_num = $v;
//                M()->startTrans();
//                $tmp_stock_num = $v['stock_num'];
//                $change_stock_status = true;
//                //$tmp_current_num = $v['stock_num'];
//                foreach($data as $key=>$value) {
//
//                    log_info("库存ID:{$value['id']},  网点ID:{$value['object_id']}, 每次冲抵前的正库存数：{$value['stock_num']}");
//                    if ($value['stock_num'] + $v['stock_num'] == 0) {
//                        $stock_new_data = [
//                            'stock_num' => 0,
//                            'available_num' => $value['stock_num'] + $tmp_stock_num,
//                            'update_time' => currentTime(),
//                        ];
//                    } else if($value['stock_num'] + $v['stock_num'] > 0){
//                        $stock_new_data = [
//                            'stock_num' => $value['stock_num'] + $v['stock_num'],
//                            'available_num' => $value['stock_num'] + $v['stock_num'],
//                            'update_time' => currentTime(),
//                        ];
//                    }else{
//                        $stock_new_data = [
//                            'stock_num' => $value['stock_num'] + $v['stock_num'],
//                            'available_num' => $value['stock_num'] + $v['stock_num'],
//                            'update_time' => currentTime(),
//                        ];
//                    }
//
//                    $tmp_stock_num = $tmp_stock_num + $value['stock_num'];
//
//                    log_info("网点ID:{$v['object_id']}  ----：{$tmp_stock_num}");
//
//                    $status = D('ErpStock')->where(['id' => $key])->save($stock_new_data);
//                    $change_stock_status = $change_stock_status && $status;
//                    log_info("库存ID:{$value['id']},  网点ID:{$value['object_id']}, 每次冲抵后的负库存数：". $tmp_stock_num . ", 冲抵后该库存数量：{$stock_new_data['stock_num']}");
//
//                }
//
//
//
////                    if($value['stock_num'] > 0 && $tmp_stock_num < 0){
////                        //判断该正库存是否全部满足负库存，满足 负库存更新为0，不满足负库存 = 正库存 - abs(负库存)
////                        //$v['stock_num'] = $value['stock_num'] >= $v['stock_num'] ? 0 : $value['stock_num'] - abs($v['stock_num']);
////                        log_info("库存ID:{$value['id']},  网点ID:{$value['object_id']}, 每次冲抵前的正库存数：{$value['stock_num']}");
////                        $stock_new_data = [
////                            'stock_num' => abs($tmp_stock_num) <= $value['stock_num'] ? $value['stock_num'] - abs($tmp_stock_num) : 0,
////                            'available_num' => abs($tmp_stock_num) <= $value['stock_num'] ? $value['stock_num'] - abs($tmp_stock_num) : 0,
////                            'update_time'=> currentTime(),
////                        ];
////
////                        $tmp_stock_num = $tmp_stock_num + $value['stock_num'];//$value['stock_num'] >= $tmp_stock_num ? 0 : $tmp_stock_num - abs($v['stock_num']);
////                        log_info("网点ID:{$v['object_id']}  ----：{$tmp_stock_num}");
////
////                        $status = D('ErpStock')->where(['id' => $key])->save($stock_new_data);
////                        $change_stock_status = $change_stock_status && $status;
////                        //$change_stock_status? "负库存调节成功\n\r" : "负库存调节失败" . "\n\r";
////                        log_info("正库存扣减状态：" . intval($change_stock_status));
////                        //echo $status ? "负库存调节成功\n\r" : "负库存调节失败" . "\n\r";
////                    }
////                    log_info("库存ID:{$value['id']},  网点ID:{$value['object_id']}, 每次冲抵后的负库存数：". $tmp_stock_num . ", 冲抵后该库存数量：{$stock_new_data['stock_num']}");
//               //}
//                $update_status = D('ErpStock')->where(['id' => $v['id']])->save(['stock_num'=>$tmp_stock_num, 'available_num'=>$tmp_stock_num]);
//                log_info("库存ID:{$v['id']},  网点ID:{$v['object_id']}, 负库存更新状态:" . $update_status);
//                if($update_status && $change_stock_status){
//                    M()->commit();
//                    echo "负库存调节成功\n\r" ;
//                }else{
//                    M()->rollback();
//                    echo  "负库存调节失败" . "\n\r";
//                }
//                //exit;
//            }else{
//                //没有正数库存冲减 直接显示调节成功
//                echo "负库存调节成功\n\r" ;
//            }

        //}
    }
    /**
     * 零售销售单生成出库单并扣减库存
     */
    public function createRetailStockOut(){
        set_time_limit(1000000);
        ini_set('memory_limit', '1024M');
        //$retail_order = D('erp_sale_retail_order')->where(['is_all_outbound'=>0])->select();
        //print_r($retail_order);
        $outbound_density = 0.833;
        //$url = 'http://192.168.40.164:8087/stock/modify';
        $url = C("CREATE_STOCK_OUT");
        $page = 1;
        while($retail_order = $retail_order = D('erp_sale_retail_order')->where(['is_all_outbound'=>0,'storehouse_id'=>['gt', 0]])->page($page, 3000)->select()){
            foreach($retail_order as $k=>$value){
                //$retail_order[$k]['buy_num'] = setNum(literToTon($value['buy_num_retail'],  $outbound_density));
                $par = [
                    'objectId'=>$value['storehouse_id'],
                    'actualOutboundNum'=>$value['buy_num'],//$retail_order[$k]['buy_num'],//$value['buy_num'],
                    'sourceObjectId'=>$value['id'],
                    'sourceNumber'=>$value['order_number'],
                    'outboundDensity'=>$outbound_density,
                    'dealerName'=>$value['dealer_name'],
                    'grade'=>$value['goods_level'],
                    'level'=>$value['goods_rank'],
                    'actualOutboundLitre'=>$value['buy_num_retail'],
                    'orderSource'=>$value['order_source'],
                ];
                $params = [
                    'params' => json_encode($par, JSON_UNESCAPED_UNICODE)
                ];
                //log_info(print_r($value, true));

                log_info('请求url：' . $url);
                log_info('请求参数：' . print_r($params, true));
                $result = http($url, $params);
                $result = json_decode($result, true);
                //log_info(print_r($result, true));
                $log_info_data = array(
                    'event'=> '刷零售出库单',
                    'key'=> '零售单号：' . $value['order_number'] . ",请求URL：" . $url,
                    'request'=> '请求参数：' . json_encode($par, JSON_UNESCAPED_UNICODE) ,
                    'response'=> $result,
                );
                log_write($log_info_data);
                if(isset($result['code']) && $result['code'] == 0){
                    //echo $order_status ? "该订单处理成功\n\r" : "更新订单失败\n";
                    echo "该订单处理成功\n\r";
                }else{
                    echo "该订单处理失败\n\r";
                }

                //exit;
            }
            $page++;
            //exit;
        }

    }
    /**
     * 生成期初网点库存的入库单
     */
    public function createInitStockInData(){
        set_time_limit(1000000);
        ini_set('memory_limit', '1024M');
        $field = 's.id,s.goods_id,s.object_id,s.stock_type,s.region,s.facilitator_id,s.stock_num,s.our_company_id,g.goods_code,g.level,g.grade,g.goods_name,g.source_from';
        $stock_where = [
            's.status'=>1,
            's.stock_type'=>4,
            's.stock_num'=>['gt', 0],
        ];
        $stock_info = D('ErpStock')->alias('s')->field($field)->join('oil_erp_goods g on s.goods_id = g.id')->where($stock_where)->select();
        $outbound_density = 0.833;
        if(!session('erp_company_id')) {
            session('erp_company_id', 3372);
        };
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
                'create_time' => currentTime(),
                'dealer_id' => 0,
                'dealer_name' => '',
                'auditor_id' => '',
                'audit_time' => currentTime(),
                'storage_remark' => '网点库存初化入库单，库存ID：' . $value['id'],
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
                echo "库存ID：" . $value['id'] . " , 期初入库单生成成功\n\r";
            }else{
                echo "库存ID：" . $value['id'] . " , 期初入库单生成失败\n\r";
            }

            //exit;
        }

    }

    /**
     * 刷零售小微订单进ERP零售销售单
     */
    public function createSaleRetailOrder(){
        set_time_limit(100000);
        ini_set('memory_limit', '1024M');
        $outbound_density = 0.833;
        $xw_where = [
            'o.zhaoyou_status'=>10,
            'o.status'=>100,
            //'o.create_time'=>['egt', '2017-8-2 22:00:00'],
            //'o.trans_true_time'=>['egt', '2017-08-02 22:00:00'],
            'o.trans_true_time'=>['gt', '2017-08-02 00:00:00'],
            'e.status'=>1,
            'e.type_goods'=>6,
            'e.num'=>['gt', 10],
           // 'ob.facilitator_skid_id' => $_GET['skid_id'],
        ];
        $xw_field = 'o.trans_true_time,o.region,o.user_id,o.company_id,o.sale_company_id,o.dealer_id,o.operation_dealer_id,
        o.create_time,o.type,o.level,o.rank,o.source,o.id,o.order_number,e.price,e.num';//,ob.facilitator_skid_id
        //$i = 0;
        $xw_order_pre = 'HY_XO' . date("Ymd");
        //echo $xw_order_pre . sprintf("%07d", $i);
        //exit;
        $dealer_data = D('dealer')->getField('id,dealer_name');
        //$page = 1;
        $n = $_GET['n']; //起始页数
        $m = $_GET['m']; //截止页数

        $limit_num = 500;
        $i = ($n - 1) * $limit_num;
        for ($page=$n; $page<= $m; $page++) {
            $xw_order = $this->selectXwOrder($xw_field, $xw_where, $page);
            $order_data = [];
            $index = 0;
            if($xw_order){
                foreach($xw_order as $k=>$v){

                    $order_data[$index]['order_number'] = $xw_order_pre . sprintf("%07d", $i+1); //erpCodeNumber();
                    $order_data[$index]['add_order_time'] = $v['trans_true_time'] ? $v['trans_true_time'] : date('Y-m-d H:is'); //erpCodeNumber();
                    $order_data[$index]['order_type'] = 2; //erpCodeNumber();
                    $order_data[$index]['our_company_id'] = 3372; //erpCodeNumber();
                    $order_data[$index]['region'] = $v['region']; //erpCodeNumber();
                    $order_data[$index]['user_id'] = $v['user_id'];
                    $order_data[$index]['company_id'] = $v['company_id'];
                    $order_data[$index]['depot_id'] = 99999;
                    $order_data[$index]['facilitator_id'] = $v['sale_company_id'];
                    //$order_data[$index]['storehouse_id'] = $v['facilitator_skid_id'];
                    $order_data[$index]['storehouse_id'] = M('retail_order_bind_skid')->where(['retail_order_id'=>$v['id']])->getField('facilitator_skid_id');
                    $order_data[$index]['dealer_id'] = $v['dealer_id'];
                    $order_data[$index]['dealer_name'] = $dealer_data[$v['dealer_id']] ? $dealer_data[$v['dealer_id']] : '';

                    $order_data[$index]['order_status'] = 10;
                    $order_data[$index]['order_amount'] = setNum($v['price'] * $v['num']);
                    $order_data[$index]['creater'] = $v['operation_dealer_id'];
                    $order_data[$index]['create_time'] = $v['create_time'];

                    $order_data[$index]['order_source'] = 4;
                    $order_data[$index]['goods_id'] = 0;
                    $order_data[$index]['buy_num'] = setNum(literToTon($v['num'], $outbound_density));
                    $order_data[$index]['buy_num_retail'] = setNum($v['num']);
                    $order_data[$index]['price'] = setNum($v['price']);
                    $order_data[$index]['goods_price'] = setNum($v['price']);
                    $order_data[$index]['goods_name'] = $v['type'];
                    $order_data[$index]['goods_rank'] = $v['level'];
                    $order_data[$index]['goods_level'] = $v['rank'];
                    $order_data[$index]['goods_source_from'] = $v['source'];
                    $order_data[$index]['dev_dealer_id'] = $v['dealer_id'];
                    $order_data[$index]['dev_dealer_name'] = $order_data[$index]['dealer_name'];
                    $order_data[$index]['from_order_id'] = $v['id'];
                    $order_data[$index]['from_order_number'] = $v['order_number'];
                    //$status = D('erp_sale_retail_order')->add($order_data[$i]);
                    //echo $status ? '该批导入成功' ."\n\r" : '导入失败' ."\n\r";

                    $index++;
                    $i++;
                }
                //print_r($order_data);
                //exit;
                $status = D('erp_sale_retail_order')->addAll($order_data);

                echo $status ? "page: {$page} success .\n\r" : "page: {$page} fail \n\r";
            }else{
                echo "page: $n to $m handled! \n\r";
            }

        }

    }

    /**
     * 刷集团订单进ERP
     */
    public function createSaleGtOrder(){
        set_time_limit(100000);
        ini_set('memory_limit', '1024M');
        $dealer_data = D('dealer')->getField('id,dealer_name');
        $gt_where = [
            'o.party_status'=>10,
            'o.zhaoyou_status'=>10,
            'o.status'=>100,
            //'o.create_time'=>['egt', '2017-8-2 22:00:00'],
            'o.trans_true_time'=>['gt', '2017-08-02 00:00:00'],
            'o.num'=>['gt', 10],
           // 'o.facilitator_skid_id'=>$_GET['skid_id']
        ];
        $gt_field = 'o.trans_true_time,o.region,o.user_id,o.user_company_id,o.sale_company_id,o.facilitator_skid_id,o.dealer_id,o.price,o.num,o.create_time,o.id,o.order_number,g.type,g.rank,g.level,g.source';
       // $gt_order = D('galaxy_order')->alias('o')->field($gt_field)->join('oil_galaxy_goods g on o.galaxy_goods_id = g.id')->where($gt_where)->limit(0,100)->select();
        $gt_order_pre = 'HY_GO' . date("Ymd");
        $n = $_GET['n']; //起始页数
        $m = $_GET['m']; //截止页数

        $limit_num = 500;
        $i = ($n - 1) * $limit_num;
        $outbound_density = 0.833;
        $is_repeat = $_GET['is_repeat'] ? $_GET['is_repeat'] : 0;
        for ($page=$n; $page<= $m; $page++) {
            $gt_order = $this->selectGtOrder($gt_field, $gt_where, $page);
            $order_data = [];
            $index = 0;
            if($gt_order){

                foreach($gt_order as $k=>$v){
                    if($is_repeat){
                        $retail_order_id = D('erp_sale_retail_order')->where(['from_order_number'=>trim($v['order_number']),'order_source'=>5])->getField('id');
                    }else{
                        $retail_order_id = 0;
                    }
                    if($retail_order_id){
                        echo $v['order_number'] . "is haved.\n\r";
                        continue;
                    }else {
                        $order_data[$index]['order_number'] = $gt_order_pre . sprintf("%07d", $i+1); //erpCodeNumber();
                        $order_data[$index]['add_order_time'] = $v['trans_true_time'] ? $v['trans_true_time'] : date('Y-m-d H:is'); //erpCodeNumber();
                        $order_data[$index]['order_type'] = 2; //erpCodeNumber();
                        $order_data[$index]['our_company_id'] = 3372; //erpCodeNumber();
                        $order_data[$index]['region'] = $v['region']; //erpCodeNumber();
                        $order_data[$index]['user_id'] = $v['user_id'];
                        $order_data[$index]['company_id'] = $v['user_company_id'];
                        $order_data[$index]['depot_id'] = 99999;
                        $order_data[$index]['facilitator_id'] = $v['sale_company_id'];
                        $order_data[$index]['storehouse_id'] = $v['facilitator_skid_id'];
                        $order_data[$index]['dealer_id'] = $v['dealer_id'];
                        $order_data[$index]['dealer_name'] = $dealer_data[$v['dealer_id']];

                        $order_data[$index]['order_status'] = 10;
                        $order_data[$index]['order_amount'] = setNum($v['price'] * $v['num']);
                        $order_data[$index]['creater'] = $dealer_data[$v['dealer_id']] ? $dealer_data[$v['dealer_id']] : '';
                        $order_data[$index]['create_time'] = $v['create_time'];

                        $order_data[$index]['order_source'] = 5;
                        $order_data[$index]['goods_id'] = 0;
                        $order_data[$index]['buy_num'] = setNum(literToTon($v['num'], $outbound_density));
                        $order_data[$index]['buy_num_retail'] = setNum($v['num']);
                        $order_data[$index]['price'] = setNum($v['price']);
                        $order_data[$index]['goods_price'] = setNum($v['price']);
                        $order_data[$index]['goods_name'] = $v['type'];
                        $order_data[$index]['goods_rank'] = $v['level'];
                        $order_data[$index]['goods_level'] = $v['rank'];
                        $order_data[$index]['goods_source_from'] = $v['source'];
                        $order_data[$index]['dev_dealer_id'] = $v['dealer_id'];
                        $order_data[$index]['dev_dealer_name'] = $order_data[$i]['dealer_name'];
                        $order_data[$index]['from_order_id'] = $v['id'];
                        $order_data[$index]['from_order_number'] = $v['order_number'];

                        //echo $status ? "该订单处理成本\n": "该订单处理失败\n";
                        //$status = D('erp_sale_retail_order')->add($order_data[$index]);
                        $index++;
                        $i++;
                    }

                }
                $status = D('erp_sale_retail_order')->addAll($order_data);
                echo $status ? "page: {$page} success.\n\r": "page: {$page} fail.\n\r";
            }else{
                echo "该区间导入完成";
            }

        }

    }

    public function selectXwOrder($xw_field, $xw_where, $page){
        $limit_num = 500;
        $offset = ($page - 1) * $limit_num;
        $xw_order = D('retail_order')->alias('o')
            ->field($xw_field)
            ->join('oil_retail_equipment e on o.id = e.retail_order_id','left')
            #->join('oil_retail_order_bind_skid ob on o.id = ob.retail_order_id', 'left')
            ->where($xw_where)
            ->limit($offset, $limit_num)
            ->select();
        //echo "小微订单SQL：" . D('retail_order')->getLastSql();
        return $xw_order;
    }

    public function selectGtOrder($gt_field, $gt_where, $page){
        $limit_num = 500;
        $offset = ($page - 1) * $limit_num;
        $gt_order = D('galaxy_order')->alias('o')
            ->field($gt_field)
            ->join('oil_galaxy_goods g on o.galaxy_goods_id = g.id', 'left')
            ->where($gt_where)
            ->limit($offset,$limit_num)
            ->select();
        return $gt_order;
    }

    /**
     * 处理重复进入ERP的零售订单
     * @author xiaowen
     * @time 2018-3-16
     */

    public function handelRepeatOrder(){
        echo "此方法用于刷零售重复出库数据，已无效";
        exit;
        set_time_limit(1000);
        ini_set('memory_limit', '512M');
        echo time() . '<br/>';
        $sql = "SELECT o.order_number,o.buy_num,o.outbound_quantity,o.from_order_number,r.outbound_code,r.goods_id,r.our_company_id,r.region,r.storehouse_id,r.cost, r.stock_type,r.outbound_num,r.source_stock_in_number FROM `oil_erp_sale_retail_order` o INNER JOIN ( SELECT from_order_number, COUNT(*), order_source FROM `oil_erp_sale_retail_order` WHERE is_reverse = 2 AND reversed_status = 2 AND outbound_quantity > 0 GROUP BY from_order_number, order_source HAVING COUNT(*) > 1 ORDER BY id DESC ) s ON o.from_order_number = s.from_order_number AND o.param_data <> '' AND o.order_status <> 2 LEFT JOIN oil_erp_stock_out_retail r ON r.source_number = o.order_number";

        $data = M()->query($sql);
        //$order_list = array_column($data, 'order_number');
        //$stock_out_list = array_column($data, 'outbound_code');
        //$order_data = M('erp_sale_retail_order')->where(['order_number'=>['in', [$order_list]]])->select();
        //$order_data = M('erp_stock_out_retail')->where(['outbound_code'=>['in', [$order_list]]])->select();
        if(empty($data)){
            echo "没有符合条件的重复订单";
            return;
        }
        foreach($data as $key=>$value){
            print_r($value);
            M()->startTrans();
            $stock_out_data = M('erp_stock_out_retail')->where(['outbound_code'=>$value['outbound_code']])->find();

            $update_stock_out = [
                'outbound_status'=>2,
                'update_time'=>currentTime(),
                'outbound_remark'=>'修正重复出库单，对应重复订单号：' . $value['order_number'],
            ];

            $stock_out_status = M('erp_stock_out_retail')->where(['outbound_code'=>$value['outbound_code']])->save($update_stock_out);

            $stock_in_data = M('erp_stock_in')->where(['storage_code'=>$stock_out_data['source_stock_in_number']])->find();
            $update_stock_in = [
                'balance_num' => $stock_in_data['balance_num'] + $stock_out_data['outbound_num'],
                'balance_num_litre' => $stock_in_data['balance_num_litre'] + $stock_out_data['actual_outbound_litre'],
                'update_time' => currentTime(),
            ];
            $stock_in_status = M('erp_stock_in')->where(['storage_code'=>$stock_out_data['source_stock_in_number']])->save($update_stock_in);

            $stock_where = [
                'object_id' => $stock_out_data['storehouse_id'],
                'goods_id' => $stock_out_data['goods_id'],
                'region' => $stock_out_data['region'],
                'our_company_id' => $stock_out_data['our_company_id'],
                'stock_type' => 4,
                'status'=>1
            ];

            $stock_data = M('erp_stock')->where($stock_where)->find();

            $update_stock_data = [
                'stock_num' => $stock_data['stock_num'] + $stock_out_data['outbound_num'],
                'available_num' => $stock_data['available_num'] + $stock_out_data['outbound_num'],
                'update' => currentTime(),
            ];
            $stock_status = M('erp_stock')->where($stock_where)->save($update_stock_data);

            $update_sale_order = [
                'is_void' => 1,
                'update_time' => currentTime(),
                'order_status' => 2,
            ];
            $order_status = M('erp_sale_retail_order')->where(['order_number'=>$value['order_number']])->save($update_sale_order);

            if($stock_status && $stock_out_status && $stock_in_status && $order_status){
                //重新计算加权成本---------------------------------------
                $params = [
                    'goodsId' => $value['goods_id'],
                    'objectId' => $value['storehouse_id'],
                    'region' => $value['region'],
                    'ourCompanyId' => 3372,
                    'stockType' => 4,
                    //'facilitatorId'=>$value['facilitator_id'],
                    'beforeCostNum'=>$stock_data['stock_num'],
                    'newCostNum'=>$value['outbound_quantity'],
                    'newPrice'=>$value['cost'],
                    'stockId'=>$stock_data['id'],
                ];
                //updateStockInNewCost($params);
                //updateNewCost($params);
                calculateNewCost($params);
                //end 结束计算成本---------------------------------------
                M()->commit();
                echo "订单：{$value['order_number']},出库单：{$value['outbound_code']}, 处理成功\n";
            }else{
                M()->rollback();
                echo "订单：{$value['order_number']},出库单：{$value['outbound_code']}, 处理失败\n";
            }

            exit;
        }
        echo time() . '<br/>';
    }

    /**
     * 处理front零售订单未进入NC、ERP数据
     */
    public function handelFrontRetailData(){

        set_time_limit(100000);
        ini_set('memory_limit', '1024M');
        $api = [
            'front_nc' => C('FRONT_NC'),
        ];
        $xw_where = [
            //'o.order_number'=>'2017092100000785',
            'o.zhaoyou_status'=>10,
            'o.status'=>100,
            'o.create_time'=>['egt', '2017-8-2 22:00:00'],
            'e.status'=>1,
            'e.type_goods'=>6,
            'e.num'=>['gt', 10],
        ];
//        $xw_field = 'o.trans_true_time,o.region,o.user_id,o.company_id,o.sale_company_id,o.dealer_id,o.operation_dealer_id,
//        o.create_time,o.type,o.level,o.rank,o.source,o.id,o.order_number,e.price,e.num';//,ob.facilitator_skid_id
        $xw_field = 'o.trans_true_time,o.region,o.create_time,o.id,o.order_number';//,ob.facilitator_skid_id
        $n = $_GET['n']; //起始页数
        $m = $_GET['m']; //截止页数

        $limit_num = 500;
        $i = ($n - 1) * $limit_num;
        for ($page=$n; $page<= $m; $page++) {
            echo "第{$page}页数据查询开始：" . DateTime() . "\n\r";
            $xw_order = $this->selectXwOrder($xw_field, $xw_where, $page);
            echo "第{$page}页数据查询SQL:" . M()->getLastSql() . "\n\r";
            echo "第{$page}页数据查询结束：" . DateTime()  . "\n\r";
            if($xw_order){
                echo "第{$page}页数据处理开始：" . DateTime() . "\n\r";
                foreach($xw_order as $key=>$value){
                    $params['params']=json_encode(['orderNumber'=>$value['order_number'], 'customerType'=>1, 'requestType'=>2],JSON_UNESCAPED_UNICODE);
                    //post_async($api['front_nc'],$params, 8089);
                    $data = [
                        'key'=>$value['order_number'],
                        'request'=>$params['params'],
                    ];
                    log_write($data);
                    //post_async($api['front_nc'],$params, 8089);
                    //http($api['front_nc'],$params);
                    http($api['front_nc'],$params);

                }
                echo "第{$page}页数据处理结束：" . DateTime() . "\n\r";
            }
            sleep(1);
            echo "第{$page}页数据处理完成\n\r";
            $i++;
        }
    }

    /**
     * 处理front零售订单未进入NC、ERP数据
     */
    public function handelFrontGalaxyData(){

        set_time_limit(100000);
        ini_set('memory_limit', '1024M');
        $api = [
            'front_nc' => C('FRONT_NC'),
        ];
        $type = isset($_GET['type']) && $_GET['type'] ? $_GET['type'] : 1; //参数 type 1: 8-2 22:00到 3-11日订单 2： 3-11日到当前订单
        if($type == 1){
            $gt_where = [
                //'o.order_number'=>'2017091800001305',
                //'o.party_status'=>10,
                'o.zhaoyou_status'=>10,
                'o.status'=>100,
                'o.create_time'=>['between', ['2017-8-2 22:00:00','2018-3-11 20:00:00']],
                'o.num'=>['gt', 10],
            ];
        }else{
            $gt_where = [
                'o.party_status'=>10,
                'o.zhaoyou_status'=>10,
                'o.status'=>100,
                'o.create_time'=>['egt', '2018-3-11 22:00:00'],
                'o.num'=>['gt', 10],
            ];
        }
        $gt_field = 'o.order_number,o.id';
        $n = $_GET['n']; //起始页数
        $m = $_GET['m']; //截止页数

        $limit_num = 500;
        $i = ($n - 1) * $limit_num;
        for ($page=$n; $page<= $m; $page++) {
            echo "第{$page}页数据查询开始：" . DateTime() . "\n\r";
            $gt_order = $this->selectGtOrder($gt_field, $gt_where, $page);
            echo "第{$page}页数据查询SQL:" . M()->getLastSql() . "\n\r";
            echo "第{$page}页数据查询结束：" . DateTime() . "\n\r";
            echo "第{$page}页数据处理开始：" . DateTime() . "\n\r";
            if($gt_order){
                foreach($gt_order as $k=>$v){
                    $params['params'] = json_encode(['orderNumber'=>$v['order_number'], 'customerType'=>2, 'requestType'=>2], JSON_UNESCAPED_UNICODE);
                    //echo $params['params'];
                    //post_async($api['front_nc'],$params, 8089);
                    http($api['front_nc'],$params);
                   // exit;
                }
            }
            echo "第{$page}页数据处理结束：" . DateTime() . "\n\r";
            sleep(1);
            echo "第{$page}页数据处理完成\n\r";
            $i++;
        }
    }

    /**
     * 导入期初库存到临时盘点库存表
     * @param $data
     * @author guanyu
     * @time 2018-09-14
     */
    public function importInitStockDataToInventory()
    {
        $this->getEvent('Data')->importInitStockDataToInventory();
    }

    /**
     * 导入出入库明细数据(20180311之前)
     * @time 2018-09-12
     * @author guanyu
     */
    public function handleHistoryStockDataFirstSingle()
    {
        $param['start'] = I('param.start', 0);
        $param['end'] = I('param.end', 0);

        if (!empty($param['start']) && !empty($param['end'])) {
            $this->getEvent('Data')->handleHistoryStockDataFirst($param);
        } else {
            echo '请输入时间区间参数';
        }
    }

    /**
     * 导入出入库明细数据(20180311之前)
     * @time 2018-09-12
     * @author guanyu
     */
    public function handleHistoryStockDataFirst()
    {
        $param['start'] = I('param.start', 0);
        $param['end'] = I('param.end', 0);

        $this->getEvent('Data')->handleHistoryStockDataFirst($param);

        $new_start = $param['start'] + 3000;
        $new_end = $param['end'];

        $url = 'handleHistoryStockDataFirst?start='.$new_start.'&end='.$new_end;
        $this->redirect($url);
    }

    /**
     * 导入出入库明细数据(20180311之前)
     * @time 2018-09-12
     * @author guanyu
     */
    public function handleHistoryStockDataSecondSingle()
    {
        $param['start'] = I('param.start', 0);
        $param['end'] = I('param.end', 0);

        $this->getEvent('Data')->handleHistoryStockDataSecond($param);
    }

    /**
     * 导入出入库明细数据(20180311之前)
     * @time 2018-09-12
     * @author guanyu
     */
    public function handleHistoryStockDataSecond()
    {
        $param['start'] = I('param.start', 0);
        $param['end'] = I('param.end', 0);

        $this->getEvent('Data')->handleHistoryStockDataSecond($param);

        $new_start = $param['start'] + 2000;
        $new_end = $param['end'];

        $url = 'handleHistoryStockDataSecond?start='.$new_start.'&end='.$new_end;
        $this->redirect($url);
    }

    /**
     * 导入期初成本到临时盘点库存表
     * @param $data
     * @author guanyu
     * @time 2018-05-16
     */
    public function importCostInfoToInventory()
    {
        $this->getEvent('Data')->importCostInfoToInventory();
    }

    /**
     * 导入期初成本到临时盘点库存表
     * @param $data
     * @author guanyu
     * @time 2018-05-16
     */
    public function updateCostByInventory()
    {
        $this->getEvent('Data')->updateCostByInventory();
    }

    /**
     * 导入期初成本到临时盘点库存表
     * @param $data
     * @author guanyu
     * @time 2018-05-16
     */
    public function importRetailCostInfoToInventory()
    {
        $this->getEvent('Data')->importRetailCostInfoToInventory();
    }

    /**
     * 2018-03-11网点库存平账
     * @time 2019-01-16
     * @author guanyu
     */
    public function settleUpSkidStock(){
        $this->getEvent('Data')->settleUpSkidStock();
    }

    /**
     * 导入网点历史出库明细数据（2017-08-02 到 2018-03-11）
     * @time 2018-06-13
     * @author guanyu
     */
    public function handleHistoryRetailAllocationDataBefore()
    {
        $param['start'] = I('param.start', 0);
        $param['end'] = I('param.end', 0);
        $param['start'] = str_replace('+',' ',$param['start']);
        $param['end'] = str_replace('+',' ',$param['end']);

        if (!empty($param['start']) && !empty($param['end'])) {
            $this->getEvent('Data')->handleHistoryRetailAllocationDataBefore($param);
        } else {
            echo '请输入时间区间参数';
        }

        $new_start = date("Y-m-d H:i:s",strtotime('+2 days',strtotime($param['start'])));
        $new_end = date("Y-m-d H:i:s",strtotime('+2 days',strtotime($param['end'])));
        if (strtotime($new_end) > strtotime('2018-03-11 22:00:00')) {
            $new_end = '2018-03-11 22:00:00';
        }

        if (strtotime($new_start) > strtotime('2018-03-11 22:00:00')) {
            $new_start = '2018-03-11 22:00:01';
            $new_end = '2018-03-13 00:00:00';
            $url = 'handleHistoryRetailAllocationDataAfter?start='.$new_start.'&end='.$new_end;
        } else {
            $url = 'handleHistoryRetailAllocationDataBefore?start='.$new_start.'&end='.$new_end;
        }
        $this->redirect($url);
    }

    /**
     * 测试java接口
     * @time 2018-05-14
     * @author guanyu
     */
    public function testJavaInterface()
    {
//        "goodsId": 46, 	"region": 321, 	"objectId": 54, 	"newPrice": 487978, 	"newCostNum": "5421", 	"beforeCostNum": "5432
        $param = $_GET;
        $param['ourCompanyId'] = 3372;
        $param['stockType'] = 4;
        $url = C('COST_API_SERVER') . 'cost/queue';
        $params['params'] = json_encode($param, JSON_UNESCAPED_UNICODE);
//        var_dump($url);
//        var_dump($params);exit;
        $result = http($url, $params);
        var_dump($result);
    }

    /**
     * 网点期初正库存生成期初入库单
     * @author xiaowen
     * @time 2017-7-16
     */
    public function skidDataToStockIn(){
        $this->getEvent('Data')->skidDataToStockIn();
    }

    /**
     * 调拨出及正负冲减入库单
     */
    public function stockInDeductionData(){
        set_time_limit(100000);
        ini_set('memory_limit', '1024M');
        //echo microtime(true) . "<hr/>";
        $stime = microtime(true);
        $type = $_GET['type'] ? intval($_GET['type']) : 1;
        if($type){
            $this->getEvent('Data')->stockInDeductionData($type);
        }
        $etime = microtime(true);
        $times = $etime - $stime;
        echo '<hr/>';
        echo "运行耗时{$times}秒";
    }

    /**
     * 调拨出、期初负冲减完后，重算更新当前库存
     * @time 2018-7-18
     * @author xiaowen
     */
    public function resetSumSkidData(){
        set_time_limit(1000000);
        ini_set('memory_limit', '1024M');
        $storehouse_id = intval($_GET['storehouse_id']) ? intval($_GET['storehouse_id']) : 0;
        $stime = microtime(true);
        //echo '00000'; exit;
        echo "start time:" . DateTime() . "\n\r";
        //$this->getEvent('Data')->resetSumSkidDataNew();
        $this->getEvent('Data')->resetSumSkidData($storehouse_id);
        $etime = microtime(true);
        $times = $etime - $stime;
        echo '<hr/>';
        echo "end time:" . DateTime() . "\n\r";
        echo "use times:{$times}";
    }

    /**
     * 验证调拨出库数据
     * @time 2018-7-27
     * @author xiaowen
     */
    public function yzStockOut(){
        $data = D('erp_stock_out')->where(['stock_type'=>4,'is_reverse'=> 2, 'reversed_status'=>2, 'outbound_status'=>10, 'source_stock_in_number'=>['neq','']])->select();
        foreach ($data as $key=>$dd){
            $dd['stock_in_data'] = array_sum(json_decode($dd['source_stock_in_number'], true));
            //print_r(json_decode($dd['source_stock_in_number']));
            if($dd['actual_outbound_num'] != $dd['stock_in_data']){
                echo "{$dd['outbound_code']}, {$dd['source_stock_in_number']}， " . $dd['actual_outbound_num'] . ', ' . $dd['stock_in_data'] . '<br/>';
            }

        }
    }

    /**
     * 验证ERP零售数据和NC表数据一致性
     * @time 2018-08-27
     * @author guanyu
     */
    public function checkErpRetailAndNcRetail(){
        $this->getEvent('Data')->checkErpRetailAndNcRetail();
    }

    /**
     * 合并网点
     * @author xiaowen
     * @time 2018-12-27
     */
    public function mergeStorehouseData(){
        set_time_limit(10000000);
        $stime = microtime(true);
        $storehouseDataArr = S('storehouseDataArr');

        $storehouseIdsArr = S('storehouseIdsArr');
        log_info(print_r($storehouseDataArr, true));

        log_info(print_r($storehouseIdsArr, true));

        $this->getEvent('Data')->mergeSaveLastStorehouse($storehouseDataArr);
        $this->getEvent('Data')->mergeStorehouseData($storehouseIdsArr);
        $etime = microtime(true);
        $times = $etime - $stime;
        echo '<hr/>';
        echo "end time:" . DateTime() . "\n\r";
        echo "use times:{$times}";

    }

    /**
     * 合并服务商
     * @author xiaowen
     * @time 2018-12-27
     */
    public function mergeSupplierData(){
        set_time_limit(10000000);
        $stime = microtime(true);
        $supplierDataArr = S('supplierDataArr');
        //新老服务商替换数组   老ID =》 新 ID


        $supplierIdsArr = S('supplierIdsArr');

        log_info(print_r($supplierDataArr, true));

        log_info(print_r($supplierIdsArr, true));

        $this->getEvent('Data')->mergeSaveLastSupplier($supplierDataArr);
        $this->getEvent('Data')->mergeSupplierData($supplierIdsArr);

        $etime = microtime(true);
        $times = $etime - $stime;
        echo '<hr/>';
        echo "end time:" . DateTime() . "\n\r";
        echo "use times:{$times}";
    }

    /**
     * 导入服务商合并数据XLS
     * @author xiaowen
     * @time 2019-1-9
     * @return array
     */
    public function importMergeSupplierData(){
        set_time_limit(10000000);
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './merge_supplier_data.xls';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './merge_supplier_data.xlsx';
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
            $newSupplierData = $currentSheet->toArray();

            //print_r($data);
            unset($newSupplierData[0]); //删除第一条表头
            //unset($data[1]);

            $currentSheet = $PHPExcel->getSheet(1);
            $supplierIdMapData = $currentSheet->toArray();
            //print_r($data);
            unset($supplierIdMapData[0]); //删除第一条表头

            $tmp_new_data = [];
            if ($newSupplierData){
                foreach ($newSupplierData as $key=>$value){
                    $tmp_new_data[] = [
                        'id'=>$value[0],
                        'data_source'=>trim($value[2]),
                        'supplier_name'=>trim($value[1]),
                        'data_source_front_id'=>$value[3] ? $value[3] : 0,
                        'data_source_oms_id'=>$value[4] ? $value[4] : 0,
                        'data_source_front_clients_id'=>$value[5] ? $value[5] : 0,
                    ];
                }
            }
            S('supplierDataArr', $tmp_new_data);
            print_r($tmp_new_data);

            $tpm_ids = [];
            //数据组装成 老ID =》 新 ID
            if(!empty($supplierIdMapData)){
                foreach ($supplierIdMapData as $key=>$value){
                    $tpm_ids[$value[0]] = $value[1];
                }
            }

            S('supplierIdsArr', $tpm_ids);
            print_r($tpm_ids);
            echo "服务商导入文件读取完成！<br/>";
            exit();

        }

    }

    /**
     * 导入服务商合并数据XLS
     * @author xiaowen
     * @time 2019-1-9
     * @return array
     */
    public function importMergeStorehouseData(){
        set_time_limit(10000000);
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './merge_storehouse_data.xls';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './merge_storehouse_data.xlsx';
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
            $newSupplierData = $currentSheet->toArray();

            //print_r($data);
            unset($newSupplierData[0]); //删除第一条表头
            //unset($data[1]);

            $currentSheet = $PHPExcel->getSheet(1);
            $supplierIdMapData = $currentSheet->toArray();
            //print_r($data);
            unset($supplierIdMapData[0]); //删除第一条表头

            $tmp_new_data = [];
            if ($newSupplierData){
                foreach ($newSupplierData as $key=>$value){
                    $tmp_new_data[] = [
                        'id'=>$value[0],
                        'is_new'=>trim($value[2]) ? $value[2] : 0,
                        'storehouse_name'=>trim($value[1]),
                        'data_source_front_id'=>$value[3] ? $value[3] : 0,
                        'data_source_oms_id'=>$value[4] ? $value[4] : 0,
                        'company_id'=>$value[5] ? $value[5] : 0,
                    ];
                }
            }
            S('storehouseDataArr', $tmp_new_data);
           print_r($tmp_new_data);

            $tpm_ids = [];
            //数据组装成 老ID =》 新 ID
            if(!empty($supplierIdMapData)){
                foreach ($supplierIdMapData as $key=>$value){
                    $tpm_ids[$value[0]] = $value[1];
                }
            }

            S('storehouseIdsArr', $tpm_ids);
            print_r($tpm_ids);
            echo "网点导入文件读取完成！<br/>";
            exit();

        }
    }

    /**
     * 初始化批次数据
     * @author xiaowen
     * @time 2019-3-4
     */
    public function initBatchData(){
        set_time_limit(10000000);
        ini_set('memory_limit', '1024M');
        $stockModel = $this->getModel('ErpStock');
        $batchModel = $this->getModel('ErpBatch');

        $stockData = $stockModel->where(['stock_type'=>['neq', 3], 'status'=>1])->select();

        if($stockData){
            $batchData = [];
            $batchLog = [];
            $i = 0;
            //按库存五要素汇总入库总数 edit xiaowen 2019-3-18
            $stockInData = $this->getModel('ErpStockIn')->field("storehouse_id,our_company_id,goods_id,stock_type, sum(actual_storage_num) as total_storage_num")->where("storage_status = 10 AND is_reverse = 2 AND reversed_status = 2")->group('storehouse_id,our_company_id,goods_id,stock_type')->select();

            $stockInDataMap = [];
            foreach ($stockInData as $k=>$v){
                $stockInDataMap[$v['our_company_id'] . '_' . $v['goods_id'] . '_' . $v['storehouse_id'] . '_' . $v['stock_type']] = $v['total_storage_num'];
            }
            unset($stockInData);
            foreach ($stockData as $key=>$value){
                $tmp = [
                    'sys_bn'=>erpCodeNumber(20,'',$value['our_company_id'])['order_number'],
                    'cargo_bn_id'=>1,
                    'stock_id'=>$value['id'],
                    'goods_id'=>$value['goods_id'],
                    'storehouse_id'=>$value['object_id'],
                    'our_company_id'=>$value['our_company_id'],
                    'region'=>$value['region'],
                    'stock_type'=>$value['stock_type'],
                    //'total_num'=>$value['stock_num'],
                    'total_num'=>$stockInDataMap[$value['our_company_id'] . '_' . $value['goods_id'] . '_' . $value['object_id'] . '_' . $value['stock_type']],
                    'balance_num'=>$value['stock_num'],
                    'create_time'=>DateTime(),
                    'data_source'=>'99',
                ];
                //if($value['region']== )
                $batchData[$i] = $tmp;
                $i++;
            }

            $batch_status = $batchModel->addAll($batchData);

            $batchRows = $batchModel->select();
            $i = 0;
            if($batchRows){

                foreach ($batchRows as $k=>$v){
                    $tmp = [
                        'batch_id' => $v['id'],
                        'batch_sys_bn' => $v['sys_bn'],
                        'change_num' => $v['total_num'],
                        'before_balance_num' => 0,
                        'balance_num' => $v['total_num'],
                        'before_reserve_num' => 0,
                        'reserve_num' => 0,
                        'change_type' => 99,
                        'change_number' => '',
                        'create_time' => currentTime(),
                    ];
                    $batchLog[$i] = $tmp;
                    $i++;
                }
            }
            $log_status = $this->getModel('ErpBatchLog')->addAll($batchLog);
            if($batch_status && $log_status){
            //if($log_status){
                echo "批次数据初始化成功！";
            }else{
                echo "批次数据初始化失败！";
            }
        }
    }

}