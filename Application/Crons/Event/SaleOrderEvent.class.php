<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 15:31
 */

namespace Crons\Event;


class SaleOrderEvent extends BaseEvent
{

	/************************************
      @ Content 生成销售单
      @ Author YF
      @ Time 2018-12-07
      @ Param [
          $arr[0] = [
            'order_number'  => 234234, // 销售单
          	'our_company_id'=> 21, // 账套id
            'region'        => 1, // 城市
            'user_id'       => 1, //用户id
            'company_id'    => 123, // 公司id
            'depot_id'      => 123, // 油库id
            'storehouse_id' => 4324, //仓库id
            'order_amount'  => 213, //订单金额
            'buy_num'       => 513445, // 购买数量
            'price'         => 324522, //单价
            'retail_inner_order_number' => 3421412, //内部交易单号
            'order_source'  => 2, // 订单来源
            'goods_id'  	=> 2, // 商品id
            'stock_out_order_number' 	=> 2342342, // 出库单号
            'cost'			=> 213, // 成本价
            'stock_id'           => 234,// 仓库id
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
		(array)$params = ['order_number','our_company_id','region','user_id','company_id','depot_id','storehouse_id',
            'order_amount','buy_num','price','retail_inner_order_number','order_source','goods_id',
            'stock_out','cost','stock_id'];
        // 错误日志
        (array)$err_log = [];
		foreach ($param as $key => $value) {
			if ( count($value) != count($params) ) {
	                return ['status' => 201,'message' => '参数不正确！'];
	        }
            foreach ($params as $k => $v) {
				if ( !isset($value[$v]) || empty($value[$v]) ) {
                    $err_log[] = '账套ID：'.$value['our_company_id'].'-库存id:'.$value['stock_id'].'-'.$v.':缺少此参数！';
					// return ['status' => 202,'message' => '账套ID：'.$value['our_company_id'].'-库存id:'.$value['stock_id'].'-'.$v.':缺少此参数！'];
				}
			}
		}

         /**************** 获取所有的账套公司 进行匹配 ******************/
        // 获取所有账套公司
        (array)$company_arr = getAllErpCompany(); 
        foreach ($param as $key => $value) {
            if ( !isset($company_arr[$value['our_company_id']]) ) {
                $err_log[] = '账套ID：'.$value['our_company_id'].'不存在此账套！';
                // return ['status' => 210,'message' => '账套ID：'.$value['our_company_id'].'不存在此账套！'];
            }
            $param[$key]['our_buy_company_name'] = $company_arr[$value['our_company_id']];
            unset($param[$key]['stock_id']);
        }

        /********************* 查询账套公司是否符合条件 ********************/
        // 获取字段中的 账套名称
        $our_company_name_arr = array_column($param, 'our_buy_company_name');
        (array)$customer_where = [
                'supplier_name'     => ['in',$our_company_name_arr],
                'is_inner'          => 1,
                'status'            => 1,
        ];
        (array)$supplier_arr = $this->getModel('ErpCustomer')->where($customer_where)->getField('customer_name,id');
        foreach ($param as $key => $value) {
            if ( !isset($supplier_arr[$value['our_buy_company_name']]) ) {
                $err_log[] = '账套ID：'.$value['our_company_id'].'不符合确认条件！';
                // return ['status' => 209,'message' => '账套ID：'.$value['our_company_id'].'不符合确认条件！'];
            }
            unset($param[$key]['our_buy_company_name']);
        }

        /******************* 查询所对应的 银行账号信息 ************************/
        // 获取字段中的 账套id
        $our_company_id_arr = array_column($param, 'our_company_id');
        (array)$bank_where = [
                    'our_company_id' => ['in',$our_company_id_arr],
                    'status'         => 1,
                    'pay_type'       => 1,
                    'default_bank'   => 1,
        ];
        $banks_arr = $this->getModel('ErpBank')->where($bank_where)->select();
        if ( empty($banks_arr) ) {
            $err_log[] = '账套ID：'.json_encode($our_company_id_arr).'不存在银行信息！';
            // return ['status' => 208,'message' => '账套ID：'.json_encode($our_company_id_arr).'不存在银行信息！'];
        }
        (array)$bank_arr = [];
        foreach ($banks_arr as $key => $value1) {
            // 一个供应商只能 对应一个 默认账号
            foreach ( $banks_arr as $k => $v ) {
                if ( $key != $k && $value1['our_company_id'] == $v['our_company_id'] ) {
                    $err_log[] = '账套 ID '.$value1['our_company_id'].':存在多个默认银行账号！';
                    // return ['status' => 207,'message' => '账套 ID '.$value1['our_company_id'].':存在多个默认银行账号！'];
                }
            }
            $bank_arr[$value1['our_company_id']] = $value1['bank_name'].'--'.$value1['bank_num'];
        }
        /*------------------ END ----------------*/

		// 查询 公司信息
        (array)$company_id_arr = array_column($param,'company_id');

        (string)$field = 'id, registered_bank, registered_bank_number,registered_tel,registered_address';

        (array)$customer_where = [
        	  		'id'   	 => ['in',$company_id_arr],
        	  		'status' => 1,
        		];
        $customer_arr = $this->getModel('ErpCustomer')->where($customer_where)->field($field)->select();
        if ( empty($customer_arr) ) {
        	return ['status' => 203,'message'=> '无公司 信息！'];
        }
        (array)$company_info = [];
        foreach ($customer_arr as $key => $value2) {
        	$company_info[$value2['id']]['company_info'] = '注册电话：' . $value2['registered_tel'] . ' 注册地址：' . $value2['registered_address'];
        }
        /*------------- END -----------------*/
        $sale_order_arr = $param;
		foreach ($sale_order_arr as &$value) {
			if ( !isset($company_info[$value['company_id']]) ) {
                $err_log[] = '公司 ID '.$value['company_id'].':无银行账号！';
				// return ['status' => 204,'message' => '公司 ID '.$value['company_id'].':无银行账号！'];
			}
            // 删除 出库单数据
			unset($value['stock_out']);
			unset($value['cost']);
			unset($value['cost_log_id']);
            /*******************/
            $value['order_source']      = 1; // 7 来源为内部交易单
            // $value['collected_amount']  = $value['order_amount']; // 已收款金额
            // $value['invoiced_amount']   = $value['order_amount']; // 开票金额
            $value['buy_num']           = $value['buy_num'];
            $value['order_amount']      = floor($value['order_amount']);
            /*******************/
			$value['add_order_time'] 	= nowTime(); // 下单时间
			$value['order_type'] 		= 1; // 订单类型
			$value['pay_type'] 			= 2; // 付款类型
			$value['account_period'] 	= 30; // 账期天数
			$value['user_bank_info'] 	= $bank_arr[$value['our_company_id']]; // 银行信息
			$value['order_remark'] 		= '内部交易单';
			$value['order_status'] 		= 10; // 订单状态
			$value['delivery_method'] 	= 2; // 配送方式
			$value['creater'] 			= 0; // 创建人
			$value['create_time'] 		= nowTime(); // 创建时间
			$value['audit_time'] 		= nowTime(); // 审核时间
			$value['auditor'] 			= 0; // 审核人
			$value['outbound_quantity'] = $value['buy_num']; // 出库数量
			$value['goods_price'] 		= $value['price']; // 商品原始价格
			$value['dev_dealer_id'] 	= 0;
			$value['dev_dealer_name'] 	= 'SYS';
			$value['business_type'] 	= 4; // 业务类型
			$value['dealer_name']       = 'SYS';
			$value['company_info']      = $company_info[$value['company_id']]['company_info'];
            // log_info('内部交易单：销售单执行中！');
 		}
        // 销售单 错误记录
        if ( !empty($err_log) ) {
            return ['status' => 204,'message' => '销售单错误记录：'.json_encode($err_log,JSON_UNESCAPED_UNICODE)];
        }
		$id = $this->getModel('ErpSaleOrder')->addAll($sale_order_arr);
		if ( !$id ) {
			return ['status' => 205,'message' => '销售单批量添加失败！'];
		}
        log_info('内部交易单 销售单数据:'.json_encode($sale_order_arr));
		(array)$log_arr = [];
		(array)$stock_out_arr = [];
        $i = 0;
		foreach ($sale_order_arr as $key => $value5) {
			// 销售单日志
			$log_arr[$key]['sale_order_id'] 		= $id;
            $log_arr[$key]['sale_order_number'] 	= $value5['order_number'];
            $log_arr[$key]['log_type'] 				= 1;
            $log_arr[$key]['log_info'] 				= serialize($value5);
            $log_arr[$key]['create_time'] 			= nowTime();
            $log_arr[$key]['operator'] 				= 'SYS';
            $log_arr[$key]['operator_id'] 			= 0;

            // 出库单数据
            foreach ($param[$key]['stock_out'] as $k => $value6) {
                $stock_out_arr[$i]['outbound_code']       = $value6['outbound_code'];
                $stock_out_arr[$i]['source_number']       = $value5['order_number'];
                $stock_out_arr[$i]['source_object_id']    = $id;
                $stock_out_arr[$i]['our_company_id']      = $value5['our_company_id'];
                $stock_out_arr[$i]['goods_id']            = $value5['goods_id'];
                $stock_out_arr[$i]['depot_id']            = $value5['depot_id'];
                $stock_out_arr[$i]['actual_outbound_num'] = $value6['actual_outbound_num'];
                $stock_out_arr[$i]['storehouse_id']       = $value5['storehouse_id'];
                $stock_out_arr[$i]['region']              = $value5['region'];
                $stock_out_arr[$i]['retail_inner_order_number'] = $value5['retail_inner_order_number'];
                $stock_out_arr[$i]['storehouse_id']       = $value5['storehouse_id'];
                $stock_out_arr[$i]['cost']                = $param[$key]['cost'];
                $stock_out_arr[$i]['batch_id']            = $value6['batch_id'];
                $stock_out_arr[$i]['batch_sys_bn']        = $value6['batch_sys_bn'];
                $i++;
            }
            $id++;
		}
        log_info('内部交易单 出库单数据：'.json_encode($stock_out_arr));
		$log_id = $this->getModel('ErpSaleOrderLog')->addAll($log_arr);
		if ( !$log_id ) {
			return ['status'=> 206 ,'message' => '销售单日志批量添加失败！'];
		}

		return ['status' => 1,'message' => '销售单 添加成功！','data' => $stock_out_arr];
	}

















}