<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 15:31
 */

namespace Crons\Event;


class StockEvent extends BaseEvent
{
    /*
     * @detail:获取网点的库存
     * @auther:小黑
     * @dete:2018-12-6
     * @params
     *      stockId = []
     */
    public function getStockNumber($stockId= []){
        if(empty($stockId)){
            return false ;
        }
        $file = "sum(stock_num) as stock_number, object_id , our_company_id" ;
        $where = [
            "stock_type" => 4 ,
            "status" => 1 ,
            "object_id" => ["in" , $stockId]
        ];
        $group = "object_id , our_company_id" ;
        $order = "object_id , our_company_id" ;
        $stockNumer = $this->getModel("ErpStock")->getStockGroup($file , $where, $group , $order);
        $stockAllNumer = [] ;
        $stockObjectNumer = [] ;
        //整理数据
        if(!empty($stockNumer)){
            foreach ($stockNumer as $value){
                if(empty($value['stock_number'])){
                    continue ;
                }
                //主体网点库存之和
                if(isset($stockObjectNumer[$value['object_id']]) &&
                    isset($stockObjectNumer[$value['object_id']][$value['our_company_id']])){
                    $stockObjectNumer[$value['object_id']][$value['our_company_id']] += $value['stock_number'] ;
                }else{
                    $stockObjectNumer[$value['object_id']][$value['our_company_id']] = $value['stock_number'] ;
                }
                //网点的库存之和
                if(isset($stockAllNumer[$value['object_id']])){
                    $stockAllNumer[$value['object_id']] += $value['stock_number'] ;
                }else{
                    $stockAllNumer[$value['object_id']] = $value['stock_number'] ;
                }
            }
        }
        return [$stockAllNumer , $stockObjectNumer];
    }

    /*
     * @detail:根据零售需求数量来匹配供方库存
      * @auther:郝冠宇
      * @dete:2018-12-7
     * @params
     * $demand_data[0] = [
            'demand_id' => 1,
            'demand_number' => 'HZ_IO2018120700000001',
            'need_number' => 23540,
            'our_company_id' => 13440,
            'storehouse_id' => 1163
        ];
     */
    public function matchDemandDataAndStock($demand_data = [])
    {
        //组装数据，查询出关联库存
        $storehouse_ids = array_column($demand_data, 'storehouse_id');
        $stockin_where = [
            'si.storehouse_id'      => ['in',$storehouse_ids],
            'si.storage_status'     => 10,
            'si.reversed_status'    => 2,
            'si.is_reverse'         => 2,
            'si.balance_num'        => ['gt',1000],
            's.status'              => 1,
        ];
        $stockin_data = $this->getModel('ErpStockIn')
            ->alias('si')
            ->join('oil_erp_stock as s on s.object_id = si.storehouse_id and s.region = si.region and s.goods_id = si.goods_id 
            and s.stock_type = si.stock_type and s.our_company_id = si.our_company_id')
            ->where($stockin_where)->order('audit_time asc')->select();
        $match_data = [];
        //先排除掉自身账套的需求
        foreach ($demand_data as $demand_key => $demand_value) {
            $storehouse_id = $demand_value['storehouse_id'];
            $our_company_id = $demand_value['our_company_id'];
            foreach ($stockin_data as $stockin_key => $stockin_value) {
                if ($stockin_value['balance_num'] <= 0) {
                    continue;
                }
                //过滤同账套下同网点库存，这里的库存已经在计算需求量时使用掉了
                if ($storehouse_id == $stockin_value['storehouse_id'] && $our_company_id == $stockin_value['our_company_id']) {
                    $supply_num = $demand_value['need_number'] <= $stockin_value['balance_num'] ? $demand_value['need_number'] : $stockin_value['balance_num'];
                    //扣减入库单可用
                    $stockin_data[$stockin_key]['balance_num'] -= $supply_num;
                    $demand_value['need_number'] = bcsub($demand_value['need_number'], $stockin_value['balance_num'], 4);
                }
                if ($demand_value['need_number'] <= 0) {
                    break;
                }
            }
            if ($demand_value['need_number'] <= 0) {
                continue;
            }
        }
        //判断供方库存是否满足需求，若不满足则unset掉该组需求
        foreach ($demand_data as $demand_key => $demand_value) {
            $storehouse_id = $demand_value['storehouse_id'];
            $our_company_id = $demand_value['our_company_id'];
            // log_info('内部交易单：网点-'. $storehouse_id.'的需求量'.$demand_value['need_number']);
            foreach ($stockin_data as $stockin_key => $stockin_value) {
                if ($stockin_value['balance_num'] <= 0) {
                    continue;
                }
                if ($demand_value['need_number'] < 1000) {
                    break;
                }
                //过滤同账套下同网点库存，这里的库存已经在计算需求量时使用掉了
                if ($storehouse_id == $stockin_value['storehouse_id'] && $our_company_id == $stockin_value['our_company_id']) {
                    log_info('内部交易单：网点-'. $storehouse_id.'本账套库存满足需求，无需内部交易');
//                    unset($stockin_data[$stockin_key]);
                    continue;
                }
                //找到其他账套下同网点库存并进行分配
                if ($storehouse_id == $stockin_value['storehouse_id'] && $our_company_id != $stockin_value['our_company_id']) {
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['demand_id'] = $demand_value['demand_id'];
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['demand_number'] = $demand_value['demand_number'];
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['storehouse_id'] = $storehouse_id;
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['region'] = $stockin_value['region'];
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['goods_id'] = $stockin_value['goods_id'];
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['demand_our_company_id'] = $our_company_id;
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['supply_our_company_id'] = $stockin_value['our_company_id'];

                    $supply_num = $demand_value['need_number'] <= $stockin_value['balance_num'] ? $demand_value['need_number'] : $stockin_value['balance_num'];
                    $match_data[$storehouse_id][$our_company_id][$stockin_value['our_company_id']][$stockin_value['goods_id']]['data'][] = [
                        'storage_code'      => $stockin_value['storage_code'],
                        'balance_num'       => $stockin_value['balance_num'],
                        'outbound_density'  => $stockin_value['outbound_density'],
                        'supply_num'        => $supply_num,
                        'batch_sys_bn'      => $stockin_value['batch_sys_bn'],
                        'batch_id'          => $stockin_value['batch_id'],
                        'cargo_bn_id'       => $stockin_value['cargo_bn_id'],
                    ];

                    //扣减入库单可用
                    $stockin_data[$stockin_key]['balance_num'] -= $supply_num;

                    $demand_value['need_number'] = bcsub($demand_value['need_number'], $stockin_value['balance_num'], 4);
                    log_info('内部交易单：入库单-'. $stockin_value['storage_code'].'的供应量：'. $stockin_value['storage_code'].'的供应量：'.$supply_num);
                }
                if ($demand_value['need_number'] <= 0) {
                    break;
                }
            }
            if ($demand_value['need_number'] <= 0) {
                continue;
            }
        }
        return $match_data;
    }

    /*
     * @detail:处理供应方库存变动
      * @auther:关羽
      * @dete:2018-12-12
     * @params
     * $stock_info 库存表所有字段
     * $change_num 库存变动数量
     * $order_info = [
     *     'sale_order_number' => 'HY_SO2018121300000001';
     *     'outbound_code' => 'HY_DO2018121300000001';
     * ];
     */
    public function handleSupplyStockChange($stock_info = [],$change_num = 0,$order_info)
    {
        //处理日志
        $stock_log = [
            //销售单新增（增加预留）
            '0' => [
                'stock_id' => $stock_info['id'],
                'log_type' => 1,//创建
                'object_number' => $order_info['sale_order_number'],
                'object_type' => 1,//销售单
                'change_num' => $change_num,
                'before_stock_num' => $stock_info['stock_num'],
                'before_transportation_num' => $stock_info['transportation_num'],
                'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'before_sale_wait_num' => $stock_info['sale_wait_num'],
                'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'before_available_num' => $stock_info['available_num'],
                'before_init_stock_num' => $stock_info['init_stock_num'],
                'after_stock_num' => $stock_info['stock_num'],
                'after_transportation_num' => $stock_info['transportation_num'],
                'after_sale_reserve_num' => $stock_info['sale_reserve_num'] + $change_num,
                'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'after_sale_wait_num' => $stock_info['sale_wait_num'],
                'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'after_available_num' => $stock_info['available_num'] - $change_num,
                'after_init_stock_num' => $stock_info['init_stock_num'],
                'create_time' => currentTime(),
                'operator_id' => 0,
                'operator' => 0,
                'remark' => '',
            ],
            //销售单确认（账期类型，预留转待提）
            '1' => [
                'stock_id' => $stock_info['id'],
                'log_type' => 3,//确认
                'object_number' => $order_info['sale_order_number'],
                'object_type' => 1,//销售单
                'change_num' => $change_num,
                'before_stock_num' => $stock_info['stock_num'],
                'before_transportation_num' => $stock_info['transportation_num'],
                'before_sale_reserve_num' => $stock_info['sale_reserve_num'] + $change_num,
                'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'before_sale_wait_num' => $stock_info['sale_wait_num'],
                'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'before_available_num' => $stock_info['available_num'],
                'before_init_stock_num' => $stock_info['init_stock_num'],
                'after_stock_num' => $stock_info['stock_num'],
                'after_transportation_num' => $stock_info['transportation_num'],
                'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'after_sale_wait_num' => $stock_info['sale_wait_num'] + $change_num,
                'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'after_available_num' => $stock_info['available_num'] - $change_num,
                'after_init_stock_num' => $stock_info['init_stock_num'],
                'create_time' => currentTime(),
                'operator_id' => 0,
                'operator' => 0,
                'remark' => '',
            ],
            //出库单审核（减待提，减物理）
            '2' => [
                'stock_id' => $stock_info['id'],
                'log_type' => 2,//审核
                'object_number' => $order_info['outbound_code'],
                'object_type' => 3,//出库单
                'change_num' => $change_num,
                'before_stock_num' => $stock_info['stock_num'],
                'before_transportation_num' => $stock_info['transportation_num'],
                'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'before_sale_wait_num' => $stock_info['sale_wait_num'] + $change_num,
                'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'before_available_num' => $stock_info['available_num'],
                'before_init_stock_num' => $stock_info['init_stock_num'],
                'after_stock_num' => $stock_info['stock_num'] - $change_num,
                'after_transportation_num' => $stock_info['transportation_num'],
                'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'after_sale_wait_num' => $stock_info['sale_wait_num'],
                'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'after_available_num' => $stock_info['available_num'] - $change_num,
                'after_init_stock_num' => $stock_info['init_stock_num'],
                'create_time' => currentTime(),
                'operator_id' => 0,
                'operator' => 0,
                'remark' => '',
            ],
        ];
        $stock_log_status = $this->getModel('ErpStockLog')->addAll($stock_log);
        //处理库存
        $stock_info['stock_num'] = $stock_info['stock_num'] - $change_num;
        $stock_info['available_num'] = $stock_info['available_num'] - $change_num;
        $stock_status = $this->getModel('ErpStock')->where(['id'=>$stock_info['id']])->save($stock_info);
        $stock_end_sql = $this->getModel('ErpStock')->getLastSql();
        //处理变动明细
        $stock_detail_info = $this->getModel('ErpStockDetailRetail')->where(['stock_id'=>$stock_info['id']])->order('id desc')->find();
        $before_stock_num = $stock_detail_info['after_stock_num'] ? $stock_detail_info['after_stock_num'] : 0;
        $before_price = $stock_detail_info['after_price'] ? $stock_detail_info['after_price'] : 0;
        $after_stock_num = $before_stock_num - $change_num;
        $after_price = $price = $stock_detail_info['after_price'] ? $stock_detail_info['after_price'] : 0;
        $type = 1;
        if ($stock_detail_info) {
            $stock_price = $stock_detail_info['stock_price'] - getNum($change_num * $price);
        } else {
            $stock_price = getNum($change_num * $price);
        }

        $detail_data = [
            'stock_id' => $stock_info['id'],
            'type' => $type,
            'source_number' => $order_info['outbound_code'],
            'source_order_number' => $order_info['sale_order_number'],
            'change_num' => $change_num,
            'price' => $price,
            'before_stock_num' => $before_stock_num,
            'after_stock_num' => $after_stock_num,
            'before_price' => $before_price,
            'after_price' => $after_price,
            'stock_price' => $stock_price,
            'create_time' => currentTime(),
            'operator_id' => 0,
            'operator' => '',
        ];
        $stock_detail_status = $this->getModel('ErpStockDetailRetail')->add($detail_data);
        if (!$stock_log_status) {
            return ['status'=>2,'message'=>'库存日志操作失败'];
        }
        if (!$stock_status) {
            return ['status'=>2,'message'=>'one仓库ID:'.$stock_info['id'].'库存变动操作失败,更新内容：【'.json_encode($stock_info).'】最后一条sql:'.$stock_end_sql];
        }
        if (!$stock_detail_status) {
            return ['status'=>2,'message'=>'库存明细操作失败'];
        }
        return ['status'=>1,'message'=>'操作成功'];
    }

    /*
     * @detail:处理需求方库存变动
      * @auther:关羽
      * @dete:2018-12-12
     * @params
     * $stock_info 库存表所有字段
     * $change_num 库存变动数量
     * $order_info = [
     *     'purchase_order_number' => 'HY_PO2018121300000001';
     *     'storage_code' => 'HY_RO2018121300000001';
     * ];
     */
    public function handleDemandStockChange($stock_info = [],$change_num = 0,$order_info,$price)
    {
        //处理日志
        $stock_log = [
            //采购单确认（账期类型，增加在途）
            '0' => [
                'stock_id' => $stock_info['id'],
                'log_type' => 3,//确认
                'object_number' => $order_info['purchase_order_number'],
                'object_type' => 2,//采购单
                'change_num' => $change_num,
                'before_stock_num' => $stock_info['stock_num'],
                'before_transportation_num' => $stock_info['transportation_num'],
                'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'before_sale_wait_num' => $stock_info['sale_wait_num'],
                'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'before_available_num' => $stock_info['available_num'],
                'before_init_stock_num' => $stock_info['init_stock_num'],
                'after_stock_num' => $stock_info['stock_num'],
                'after_transportation_num' => $stock_info['transportation_num'] + $change_num,
                'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'after_sale_wait_num' => $stock_info['sale_wait_num'],
                'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'after_available_num' => $stock_info['available_num'] + $change_num,
                'after_init_stock_num' => $stock_info['init_stock_num'],
                'create_time' => currentTime(),
                'operator_id' => 0,
                'operator' => 0,
                'remark' => '',
            ],
            //出库单审核（在途转物理）
            '1' => [
                'stock_id' => $stock_info['id'],
                'log_type' => 2,//审核
                'object_number' => $order_info['storage_code'],
                'object_type' => 4,//入库单
                'change_num' => $change_num,
                'before_stock_num' => $stock_info['stock_num'],
                'before_transportation_num' => $stock_info['transportation_num'] + $change_num,
                'before_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'before_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'before_sale_wait_num' => $stock_info['sale_wait_num'],
                'before_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'before_available_num' => $stock_info['available_num'],
                'before_init_stock_num' => $stock_info['init_stock_num'],
                'after_stock_num' => $stock_info['stock_num'] + $change_num,
                'after_transportation_num' => $stock_info['transportation_num'],
                'after_sale_reserve_num' => $stock_info['sale_reserve_num'],
                'after_allocation_reserve_num' => $stock_info['allocation_reserve_num'],
                'after_sale_wait_num' => $stock_info['sale_wait_num'],
                'after_allocation_wait_num' => $stock_info['allocation_wait_num'],
                'after_available_num' => $stock_info['available_num'] + $change_num,
                'after_init_stock_num' => $stock_info['init_stock_num'],
                'create_time' => currentTime(),
                'operator_id' => 0,
                'operator' => 0,
                'remark' => '',
            ],
        ];
        $stock_log_status = $this->getModel('ErpStockLog')->addAll($stock_log);
        //处理库存
        $stock_info['stock_num'] = $stock_info['stock_num'] + $change_num;
        $stock_info['available_num'] = $stock_info['available_num'] + $change_num;
        $stock_status = $this->getModel('ErpStock')->where(['id'=>$stock_info['id']])->save($stock_info);
        //处理变动明细
        $stock_detail_info = $this->getModel('ErpStockDetailRetail')->where(['stock_id'=>$stock_info['id']])->order('id desc')->find();
        $before_stock_num = $stock_detail_info['after_stock_num'] ? $stock_detail_info['after_stock_num'] : 0;
        $before_price = $stock_detail_info['after_price'] ? $stock_detail_info['after_price'] : 0;
        $after_stock_num = $before_stock_num + $change_num;
        $after_price = setNum(round((getNum($before_price) * getNum($before_stock_num) + getNum($change_num) * getNum($price)) / getNum($before_stock_num + $change_num),2));
        $type = 2;

        //影响库存成本
        if ($stock_detail_info) {
            $stock_price = $stock_detail_info['stock_price'] + getNum($change_num * $price);
        } else {
            $stock_price = getNum($change_num * $price);
        }

        $detail_data = [
            'stock_id' => $stock_info['id'],
            'type' => $type,
            'source_number' => $order_info['storage_code'],
            'source_order_number' => $order_info['purchase_order_number'],
            'change_num' => $change_num,
            'price' => $price,
            'before_stock_num' => $before_stock_num,
            'after_stock_num' => $after_stock_num,
            'before_price' => $before_price,
            'after_price' => $after_price,
            'stock_price' => $stock_price,
            'create_time' => currentTime(),
            'operator_id' => 0,
            'operator' => '',
        ];
        $stock_detail_status = $this->getModel('ErpStockDetailRetail')->add($detail_data);
        if (!$stock_log_status) {
            return ['status'=>2,'message'=>'库存日志操作失败'];
        }
        if (!$stock_status) {
            return ['status'=>2,'message'=>'two仓库ID:'.$stock_info['id'].'库存变动操作失败,更新内容：【'.json_encode($stock_info).'】'];
        }
        if (!$stock_detail_status) {
            return ['status'=>2,'message'=>'库存明细操作失败'];
        }
        return ['status'=>1,'message'=>'操作成功'];
    }
}