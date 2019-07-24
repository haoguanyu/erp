<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/7
 * Time: 16:01
 */

namespace Crons\Event;


class ErpRetailInnerOrderEvent extends BaseEvent
{
    /*
     * @detail:
     * 根据库存匹配数组来组装内部交易单参数
     * 根据匹配数组组装销售参数并修改对应库存
     * 根据匹配数据组装采购参数并修改对应库存
     * @auther:郝冠宇
     * @dete:2018-12-11
     * @params
     *  $match_data[1163][3372][0] = [
            'storage_code' => 'HY_RO2018042800000002',
            'storehouse_id' => 1163,
            'demand_our_company_id' => 13440,
            'supply_our_company_id' => 3372,
            'supply_num' => 23540,
        ];
     */
    public function matchInnerData($match_data = [])
    {
        $density = getConfig("Config_Density") ;//获取密度【为吨升转化用】
        $message = '';
        $sale_data = [];
        $purchase_data = [];
        $deduction_data = [];
        $cost_data = [];
        $supply_stock_status_all = true;
        $demand_stock_status_all = true;
        $stockin_status_all = true;
        $deduction_status_all = true;
        foreach ($match_data as $storehouse_id => $value1) {
            foreach ($value1 as $demand_our_company_id => $value2) {
                foreach ($value2 as $supply_our_company_id => $value3) {
                    foreach ($value3 as $goods_id => $value4) {
                        //交易单号
                        $order_number = erpCodeNumber(19 , "" , $supply_our_company_id)['order_number'];
                        //销售单号
                        $sale_order_number = erpCodeNumber(6,'',$supply_our_company_id)['order_number'];
                        //采购单号
                        $purchase_order_number = erpCodeNumber(5,'',$demand_our_company_id)['order_number'];
                        $demand_id = $value4['demand_id'];
                        $demand_number = $value4['demand_number'];
                        $region = $value4['region'];
                        $goods_id = $value4['goods_id'];
                        //查询供应方库存id（交易单和抵扣记录使用）
                        $supply_stock_where = [
                            'goods_id'          => $goods_id,
                            'object_id'         => $storehouse_id,
                            'stock_type'        => 4,
                            'region'            => $region,
                            'our_company_id'    => $supply_our_company_id,
                            'status'            => 1,
                        ];
                        $supply_stock = $this->getModel('ErpStock')->where($supply_stock_where)->find();
                        //查询需求方库存id（交易单和抵扣记录使用）
                        $demand_stock_where = [
                            'goods_id'          => $goods_id,
                            'object_id'         => $storehouse_id,
                            'stock_type'        => 4,
                            'region'            => $region,
                            'our_company_id'    => $demand_our_company_id,
                            'status'            => 1,
                        ];
                        $demand_stock = $this->getModel('ErpStock')->where($demand_stock_where)->find();
                        if (empty($demand_stock)) {
                            $facilitator_data = $this->getModel('ErpStorehouse')->where(['id'=>$storehouse_id])->find();
                            $demand_stock = [
                                'goods_id' => $goods_id,
                                'object_id' => $storehouse_id,
                                'stock_type' => 4,
                                'region' => $region,
                                'facilitator_id' => $facilitator_data['company_id'],
                                'stock_num' => 0,
                                'transportation_num' => 0,
                                'sale_reserve_num' => 0,
                                'allocation_reserve_num' => 0,
                                'sale_wait_num' => 0,
                                'allocation_wait_num' => 0,
                                'available_num' => 0,
                                'init_stock_num' => 0,
                                'our_company_id' => $demand_our_company_id,
                                'create_time' => currentTime(),
                                'update_time' => currentTime(),
                                'status' => 1,
                            ];
                            $demand_stock_id = $this->getModel('ErpStock')->add($demand_stock);
                            $demand_stock['id'] = $demand_stock_id;
                        }
                        //获取供应方成本（销售单价、出库成本、采购单价、入库单价）
                        $stock_info = [
                            'goods_id' => $goods_id,
                            'storehouse_id' => $storehouse_id,
                            'region' => $region,
                            'our_company_id' => $supply_our_company_id,
                            'stock_type' => 4,
                        ];
                        $stock_out_cost = getStockOutCost($stock_info);
                        // 如果成本值为空，则return edit qianbin 2018-03-09
                       // if($stock_out_cost['price']===null){
                       //     return  [
                       //         'status' => 2,
                       //         'message' => '成本获取失败，请联系管理员！',
                       //     ];
                       // }
                        $price = $stock_out_cost['price'] ? $stock_out_cost['price'] : 0;
                        $cost_log_id = $stock_out_cost['logId'] ? $stock_out_cost['logId'] : 0;

                        //获取客户信息
                        $customer_data = $this->getModel('ErpCompany')
                            ->alias('ec')
                            ->field('c.id as company_id,cu.id as user_id')
                            ->where(['ec.company_id'=>$demand_our_company_id])
                            ->join('oil_erp_customer as c on ec.company_name = c.customer_name', 'left')
                            ->join('oil_erp_customer_user as cu on c.id = cu.customer_id', 'left')
                            ->find();
                        //获取供应商信息
                        $supplier_data = $this->getModel('ErpCompany')
                            ->alias('ec')
                            ->field('s.id as company_id,su.id as user_id')
                            ->where(['ec.company_id'=>$supply_our_company_id])
                            ->join('oil_erp_supplier as s on ec.company_name = s.supplier_name', 'left')
                            ->join('oil_erp_supplier_user as su on s.id = su.supplier_id', 'left')
                            ->find();
                        //交易数量（销售数量、购买数量)
                        $goods_num = 0;
                        $stock_out_arr = [];
                        $stock_in_arr  = [];
                        foreach ($value4['data'] as $key => $detail) {
                            /* ------------------- 根据批次 生成出库单 入库单 ---------------------*/
                            /* Author YF */
                            /* ---------- 操作 组装出库单号 ---------- */
                            $outbound_code = erpCodeNumber(7,'',$supply_our_company_id)['order_number'];
                            $stock_out_arr[$key] = [
                                'batch_sys_bn'        => $detail['batch_sys_bn'],
                                'batch_id'            => $detail['batch_id'],
                                'actual_outbound_num' => $detail['supply_num'],
                                'outbound_code'       => $outbound_code,
                            ];
                            $batch_change_data = [
                                'batch_id'           => $detail['batch_id'],
                                'change_reserve_num' => $detail['supply_num'],
                                'change_type'        => 2,
                                'change_number'      => $outbound_code,
                            ];
                            $batch_result = $this->getEvent('ErpBatch','Common')->commonChangeBatchNum($batch_change_data);
                            if ( $batch_result['status'] != 1 ) {
                                return $batch_result;
                            }

                            /* ---------- 操作 组装入库单号 ---------- */
                            //入库单号
                            $storage_code = erpCodeNumber(8,'',$demand_our_company_id)['order_number'];
                            $stock_in_arr[$key] = [
                                'storage_code' => $storage_code,
                                'storage_num'  => $detail['supply_num'],
                                'cargo_bn_id'  => $detail['cargo_bn_id'],
                            ];

                            /* ------------------------------ END ------------------------------------ */

                            //网点出入库抵扣数据
                            $deduction_data = [
                                'source_stock_in_number' => $detail['storage_code'],
                                'type' => 3,
                                'stock_id' => $supply_stock['id'],
                                'outbound_code' => $outbound_code,
                                'before_balance_num' => $detail['balance_num'],
                                'after_balance_num' => $detail['balance_num'] - $detail['supply_num'],
                                'deduction_num' => $detail['supply_num'],
                                'deduction_type' => 1,
                                'create_time' => currentTime(),
                            ];
                            $deduction_status = $this->getModel('ErpStockInDeduction')->add($deduction_data);
                            $deduction_status_all = $deduction_status_all && $deduction_status ? true : false;
                            if (!$deduction_status_all) {
                                $message = '入库单抵扣新增失败 新增数据：'.json_encode($deduction_data);
                                break 5;
                            }
                            $goods_num += $detail['supply_num'];
                            if ($detail['storage_code'] == 'HZ_RO2019011600000001') {
                                log_info('测试记录数据，可用数量：'.$detail['balance_num'].',供应量：'.$detail['supply_num']);
                            }
                            //更新入库单可用数量
                            $data = [
                                'balance_num' => $detail['balance_num'] - $detail['supply_num'],
                                'balance_num_litre' => tonToLiter($detail['balance_num'] - $detail['supply_num'],$density),
                                'deduction_num' => $detail['supply_num'],
                            ];
                            $stockin_status = $this->getModel('ErpStockIn')
                                ->where(['storage_code'=>$detail['storage_code']])
                                ->save($data);
                            $stockin_status_all = $stockin_status_all && $stockin_status ? true : false;
                            if (!$stockin_status_all) {
                                $message = '内部交易单：入库单可用更新失败 更新字段：'.json_encode($data).'- 入库单号：'.json_encode($detail['storage_code']);
                                break 5;
                            }
                        }

                        $match_data[$storehouse_id][$demand_our_company_id][$supply_our_company_id][$goods_id]['stock_id'] = $supply_stock['id'];
                        $match_data[$storehouse_id][$demand_our_company_id][$supply_our_company_id][$goods_id]['cost'] = $price;
                        $match_data[$storehouse_id][$demand_our_company_id][$supply_our_company_id][$goods_id]['cost_log_id'] = $stock_out_cost['logId'];
                        $match_data[$storehouse_id][$demand_our_company_id][$supply_our_company_id][$goods_id]['inner_order_number'] = $order_number;
                        //需求方入库成本参数
                        $cost_data[] = [
                            'goods_id' => $goods_id,
                            'storehouse_id' => $storehouse_id,
                            'region' => $region,
                            'our_company_id' => $demand_our_company_id,
                            'storage_code' => $storage_code,
                            'stock_type' => 4,
                            'facilitator_id' => $demand_stock['facilitator_id'],
                            'before_stock_num' => $demand_stock['stock_num'],
                            'change_num' => $goods_num,
                            'price' => $price,
                            'stock_id' => $demand_stock['id'],
                        ];
                        //内部交易单参数
                        $erp_retail_inner_order_data[] = [
                            'order_number' => $order_number,
                            'inner_demand_id' => $demand_id,
                            'inner_demand_order' => $demand_number,
                            'storehouse_id' => $storehouse_id,
                            'demand_our_company_id' => $demand_our_company_id,
                            'supply_our_company_id' => $supply_our_company_id,
                            'region' => $region,
                            'goods_id' => $goods_id,
                            'stock_id' => $supply_stock['id'],
                            'goods_num' => $goods_num,
                            'price' => $price,
                            'amount' => getNum($goods_num * $price),
                            'creater' => 0,
                            'create_time' => currentTime(),
                            'audit_time' => currentTime(),
                            'auditer' => 0,
                            'status' => 10,
                        ];
                        //组装销售单参数
                        $sale_data[] = [
                            'order_number'              => $sale_order_number,
                            'stock_out'                 => $stock_out_arr,
                            'region'                    => $value4['region'],// 城市
                            'user_id'                   => $customer_data['user_id'],//用户id
                            'company_id'                => $customer_data['company_id'],// 公司id
                            'depot_id'                  => 99999,// 油库id
                            'storehouse_id'             => $storehouse_id, //仓库id
                            'order_amount'              => round(getNum($price * $goods_num)/100)*100,//订单金额
                            'buy_num'                   => $goods_num, // 购买数量
                            'price'                     => $price,//单价
                            'retail_inner_order_number' => $order_number,//内部交易单号
                            'our_company_id'            => $supply_our_company_id,
                            'goods_id'                  => $goods_id,
                            'order_source'              => 1,
                            'cost'                      => $price,
                            'stock_id'                  => $supply_stock['id'],
                        ];
                        //组装采购单参数
                        $purchase_data[] = [
                            'order_number'              => $purchase_order_number,
                            'stock_in'                  => $stock_in_arr,
                            'region'                    => $value4['region'],// 城市
                            'sale_user_id'              => $supplier_data['user_id'],//用户id
                            'sale_company_id'           => $supplier_data['company_id'],// 公司id
                            'depot_id'                  => 99999,// 油库id
                            'storehouse_id'             => $storehouse_id, //仓库id
                            'order_amount'              => round(getNum($price * $goods_num)/100)*100,//订单金额
                            'goods_num'                 => $goods_num, // 购买数量
                            'storage_quantity'          => $goods_num, // 购买数量
                            'price'                     => $price,//单价
                            'retail_inner_order_number' => $order_number,//内部交易单号
                            'our_buy_company_id'        => $demand_our_company_id,
                            'goods_id'                  => $goods_id,
                            'stock_id'                  => $demand_stock['id'],
                        ];
                        //处理出库方库存
                        $supply_stock_result = $this->getEvent('Stock')->handleSupplyStockChange($supply_stock,$goods_num,['sale_order_number'=>$sale_order_number,'outbound_code'=>$outbound_code]);
                        $supply_stock_status_all = $supply_stock_status_all && $supply_stock_result['status'] == 1 ? true : false;
                        if (!$supply_stock_status_all) {
                            $message = $supply_stock_result['message'];
                            break 4;
                        }
                        //处理入库方库存
                        $demand_stock_result = $this->getEvent('Stock')->handleDemandStockChange($demand_stock,$goods_num,['purchase_order_number'=>$purchase_order_number,'storage_code'=>$storage_code],$price);
                        $demand_stock_status_all = $demand_stock_status_all && $demand_stock_result['status'] == 1 ? true : false;
                        if (!$demand_stock_status_all) {
                            $message = $demand_stock_result['message'];
                            break 4;
                        }
                    }
                }
            }
        }
        //新增内部交易单
        $inner_order_status = $this->getModel('ErpRetailInnerOrder')->addAll($erp_retail_inner_order_data);
        $status_all = $inner_order_status && $supply_stock_status_all && $demand_stock_status_all && $stockin_status_all && $deduction_status_all ? true : false;
        $result = [
            'match_data' => $match_data,
            'sale_data' => $sale_data,
            'purchase_data' => $purchase_data,
            'cost_data' => $cost_data,
            'status' => $status_all,
            'message' => $message,
        ];
        return $result;
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
}