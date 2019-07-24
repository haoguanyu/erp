<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 11:26
 */

namespace Crons\Controller;

class InsiderDealingController extends BaseController
{

    /*
      * @detail:内部交易入口
      * @auther:关羽
      * @dete:2018-12-10
      */
    public function insiderDealing()
    {
        sendEmailByJava('内部交易单','内部交易单开始启动！',mailSender(3),true);
        M()->startTrans();
        //获取需求数据
        $demand_data = $this->needToAllocate();
        //根据需求数据匹配入库单，得到匹配数组
        $match_data = $this->getEvent('Stock')->matchDemandDataAndStock($demand_data);
        log_info('匹配数据结果：'. print_r($match_data,true));
        if (!empty($match_data)) {
            //根据匹配数组生成内部交易单
            //根据匹配数组组装销售参数并修改对应库存
            //根据匹配数据组装采购参数并修改对应库存
            $inner_order_result = $this->getEvent('ErpRetailInnerOrder')->matchInnerData($match_data);
            $match_data = $inner_order_result['match_data'];
            $sale_data = $inner_order_result['sale_data'];
            $purchase_data = $inner_order_result['purchase_data'];
            $cost_data = $inner_order_result['cost_data'];
            $inner_order_status = $inner_order_result['status'];
            if (!$inner_order_status) {

                sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(2),true);
                sendEmailByJava('内部交易单','内部交易单错误：'.$inner_order_result['message'],mailSender(1),true);

                log_info('内部交易单错误：'.$inner_order_result['message']);
                M()->rollback();
                return;
            }
            log_info('内部交易单：采购数据-'.json_encode($purchase_data));
            log_info('内部交易单：生成采购、入库单。条数：'.count($purchase_data));
            $purchase_result = $this->generatePurchaseOrder($purchase_data);
            if ($purchase_result['status'] != 1) {
                M()->rollback();
                return;
            }
            log_info('内部交易单：生成销售、出库单。条数：'.count($sale_data));
            $sale_result = $this->generateSaleOrder($sale_data);
            if ($sale_result['status'] != 1) {
                M()->rollback();
                return;
            }
            if ($inner_order_status && $purchase_result['status'] == 1 && $sale_result['status'] == 1 ) {
                //重新计算加权成本
                foreach ($cost_data as $cost) {
                    updateStockInCost($cost);
                }
                M()->commit();
                sendEmailByJava('内部交易单','内部交易单执行成功！',mailSender(3),true);
                log_info('内部交易单执行成功!');
            }else {
                sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(3),true);
                M()->rollback();
            }
            return;
        } else {
            sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(2),true);
            sendEmailByJava('内部交易单','库存不满足需求量，无法完成内部交易',mailSender(1),true);
            log_info('库存不满足需求量，无法完成内部交易');
            return;
        }
    }

    /*
      * @detail:获取零售订单需要调拨的数据
      * @auther:小黑
      * @dete:2018-12-6
      */
    public function needToAllocate(){
        if($returnData = $this->getEvent("ErpRetailInnerDemand")->needToAllocate()){
            return $returnData ;
        }else{
            return [];
        }
    }

    /*************************************
      @ Content 生成采购单
      @ Author YF
      @ Time 2018-12-06
      @ Param [array] [
          $arr[0] = [
                'order_number'       => 12312312, 采购单号
                'our_buy_company_id' => 2, //我方采购公司
                'region'             => 1, //城市id
                'sale_user_id'       => 2, // 供应商用户id
                'sale_company_id'    => 3, // 供应商 id 
                'depot_id'           => 5, //油库id
                'storehouse_id'      => 5, // 仓库id
                'order_amount'       => 2890000,// 订单金额 （处理好之后的金额）,
                'storage_quantity'   => 56776868, // 入库数量 (处理好之后的数量)
                'goods_id'           => 3, // 商品id，
                'price'              => 1231209, // 采购单价
                'retail_inner_order_number' => 456789898765768,//内部交易单号
                'goods_num'          => 2313,// 采购数量
                'stock_in_order_number'     => 2141234123, // 入库单号
                'stock_id'            => 234,// 仓库id
          ];
      ]
      @ Return ['status' => 状态码,'message' => 提示语 ];
        [
          status => message [
              2 => 参数不正确！
              3 => 参数不能为空！
              4 => 数组建 0 - region 参数错误！
              7 => 无默认银行账号信息！
              9 => 供应商 ID 2268 ： 存在多个默认银行账号！
              8 => 供应商 ID 2268 ：无默认银行账号！
              5 => 采购单批量添加 失败！
              6 =>采购单日志表 批量添加失败！
              1 => 成功！
          ]
      ]
    **************************************/
    public function generatePurchaseOrder( $param )
    {
        // $param[0] = [
        //     'our_buy_company_id' => 3372, //我方采购公司
        //     'region'             => 1, //城市id
        //     'sale_user_id'       => 2, // 供应商用户id
        //     'sale_company_id'    => 20680, // 供应商 id 
        //     'depot_id'           => 5, //油库id
        //     'storehouse_id'      => 2016, // 仓库id
        //     'order_amount'       => 2890000,// 订单金额 （处理好之后的金额）,
        //     'storage_quantity'   => 56776868, // 入库数量 (处理好之后的金额)
        //     'goods_id'           => 3, // 商品id，
        //     'price'              => 1231209, // 采购单价
        //     'retail_inner_order_number' => 456789898765768,//内部交易单号
        //     'goods_num'          => 2313,// 采购数量
        //     'stock_in_order_number' => 12345123142,
        //     'order_number'       => 3242342344234,
        // ];
        // $param[1] = [
        //     'our_buy_company_id' => 3372, //我方采购公司
        //     'region'             => 1, //城市id
        //     'sale_user_id'       => 2, // 供应商用户id
        //     'sale_company_id'    => 20680, // 供应商 id 
        //     'depot_id'           => 5, //油库id
        //     'storehouse_id'      => 2016, // 仓库id
        //     'order_amount'       => 2890000,// 订单金额 （处理好之后的金额）,
        //     'storage_quantity'   => 56776868, // 入库数量 (处理好之后的金额)
        //     'goods_id'           => 3, // 商品id，
        //     'price'              => 1231209, // 采购单价
        //     'retail_inner_order_number' => 456789898765768,//内部交易单号
        //     'goods_num'          => 2313,// 采购数量
        //     'stock_in_order_number'   => 2346754645645,
        //     'order_number'       => 32423423423452324,
        // ];
        $result = $this->getEvent("PurchaseOrder")->generatePurchaseOrder($param);
        if ( $result['status'] != 1 ) {
          sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(2),true);
          sendEmailByJava('内部交易单','内部交易单错误：'.$result['message'],mailSender(1),true);
          log_info('内部交易单：生成采购单，返回错误信息:'.$result['message']);
          return $result;
        }
        $stock_in_result = $this->generateStockIn( $result['data'] );
        if ( $stock_in_result['status'] != 1 ) {
          sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(2),true);
          sendEmailByJava('内部交易单','内部交易单错误：'.$stock_in_result['message'],mailSender(1),true);
          log_info('内部交易单：生成入库单，返回错误信息:'.$stock_in_result['message']);
        }
        return $stock_in_result;
    }

    /*************************************
        @ Content 生成入库单
        @ Author YF
        @ Time 2018-12-07
        @ Param [array] [
            $arr[0] = [
                [source_number]     => PO2018120700000039   // 来源 采购单号
                [source_object_id]  => 18744        // 来源采购单id
                [our_company_id]    => 2          // 账套id
                [storehouse_id]     => 5          // 仓库id
                [storage_num]       => 2313         // 入库数量
                [goods_id]          => 3          // 商品 ID
                [region]            => 1          // 城市ID 
                [retail_inner_order_number] => 456789898765768 // 内部交易单号
                [storage_code]      => 入库单号
            ];
        ]
        @ Return ['status' => 状态码,'message' => 提示语 ];
        [
          status => message [
            101 => 参数不正确！
            102 => 数组建-0-our_company_id缺少此参数！
            103 => 无此仓库 IDs-[1213,123]！
            104 => ID 234 无此仓库！
            105 => 入库单生成失败！
            1   => 入库单生成成功！
          ]
        ]
    **************************************/
    private function generateStockIn( $param )
    {
        log_info('内部交易单：入库单数据-'.$param);
        $result = $this->getEvent("StockIn")->generateStockIn($param);
        return $result;
    }


    /************************************
      @ Content 生成销售单
      @ Author YF
      @ Time 2018-12-07
      @ Param [
          $arr[0] = [
            'order_number'  => 234234, // 销售单
            'region'        => 1,// 城市
            'user_id'       => 1,//用户id
            'company_id'    => 123,// 公司id
            'depot_id'      => 123,// 油库id
            'storehouse_id' => 4324, //仓库id
            'order_amount'  => 213,//订单金额
            'buy_num'       => 513445, // 购买数量
            'price'         => 324522,//单价
            'retail_inner_order_number' => 3421412,//内部交易单号
            'stock_out_order_number'    => 2342342, // 出库单号
            'cost'          => 213, // 成本价
            'stock_id'       => 234,// 仓库id
          ]
      ]
      @ Return [
          status => message [
              201 => 参数不正确！
              202 => 数组建-0-user_id 缺少此参数！
              203 => 无银行账号信息！
              204 => 公司ID 12 ：无银行账号！
              205 => 销售单批量添加失败！
              206 => 销售单日志 添加失败！
              1   => 销售单 添加成功;
          ]
      ]

    *************************************/
    public function generateSaleOrder( $param )
    {
        // $param[0] = [
        //     'region'        => 1,// 城市
        //     'user_id'       => 1,//用户id
        //     'company_id'    => 16776,// 公司id
        //     'depot_id'      => 123,// 油库id
        //     'storehouse_id' => 2016, //仓库id
        //     'order_amount'  => 213,//订单金额
        //     'buy_num'       => 513445, // 购买数量
        //     'price'         => 324522,//单价
        //     'retail_inner_order_number' => 3421412,//内部交易单号
        //     'order_source'  => 2,
        //     'our_company_id'=> 3372,
        //     'goods_id'      => 2,
        //     'stock_out_order_number' => 'HY_DO2318121000000002',
        //     'cost'          => 3212,
        //     'cost_log_id'   => 34123,
        //     'order_number'  => 32423523534564,
        // ];
        // $param[1] = [
        //     'region'        => 1,// 城市
        //     'user_id'       => 1,//用户id
        //     'company_id'    => 16776,// 公司id
        //     'depot_id'      => 123,// 油库id
        //     'storehouse_id' => 2016, //仓库id
        //     'order_amount'  => 213,//订单金额
        //     'buy_num'       => 513445, // 购买数量
        //     'price'         => 324522,//单价
        //     'retail_inner_order_number' => 3421412,//内部交易单号
        //     'order_source'  => 2,
        //     'our_company_id'=> 3372,
        //     'goods_id'      => 2,
        //     'stock_out_order_number' => 'HY_DO0158121000000002',
        //     'cost'          => 3212,
        //     'cost_log_id'   => 34123,
        //     'order_number'  => 3242352353456,
        // ];
        $result = $this->getEvent("SaleOrder")->generateSaleOrder($param);
        if ( $result['status'] != 1 ) {
            sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(2),true);
            sendEmailByJava('内部交易单','内部交易单错误：'.$result['message'],mailSender(1),true);
            log_info('内部交易单：生成销售单，返回错误信息:'.$result['message']);
            return $result;
        }
        log_info('内部交易单：生成出库单数据-'.json_encode($result['data']));
        // 生成 出库单
        $stock_out_result = $this->generateStockOut( $result['data'] );
        if ( $stock_out_result['status'] != 1 ) {
            sendEmailByJava('内部交易单','执行失败！请稍后，技术人员正在处理。',mailSender(2),true);
            sendEmailByJava('内部交易单','内部交易单错误：'.$stock_out_result['message'],mailSender(1),true);
            log_info('内部交易单：生成出库单，返回错误信息:'.$stock_out_result['message']);
        }
        return $stock_out_result;
    }

    /********************************** 
      @ Content 批量生成 出库单
      @ Author YF
      @ Time 2018-12-10
      @ Param [
        'source_number'         => 来源销售单号,
        'source_object_id'      => 来源销售单id,
        'our_company_id'        => 账套id,
        'goods_id'              => 商品id,
        'depot_id'              => 油库id,
        'actual_outbound_num'   => 实际出库数量,
        'storehouse_id'         => 仓库id,
        'region'                => 城市id,
        'retail_inner_order_number' => 内部交易单号,
        'outbound_code'         => 2342342, // 出库单号
        'cost'                  => 213, // 成本价
      ]
      @ Ruturn [
        status => message [
          201 => 参数不正确！,
          202 => 数组建 -0-good_id ：缺少此参数！,
          203 => 无此仓库 IDs - [213,123]！,
          204 => ID 21312 无此仓库!,
          205 => 出库单批量添加 失败！,
          1   => 出库单 生成 成功！,
        ]
      ]
    ***********************************/
    private function generateStockOut( $param )
    {
        $result = $this->getEvent("StockOut")->generateStockOut( $param );
        return $result;
    }

    /*
     * @detail:零售订单出库的脚本
     * @author:小黑
     * @data:2018-12-10
     */
    public function stockOut(){
        $this->getEvent("RetailOrder")->stockOut();
        echo "ok" ;
        exit ;
    }
}