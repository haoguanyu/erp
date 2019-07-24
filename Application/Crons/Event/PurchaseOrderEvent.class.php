<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 13:49
 */

namespace Crons\Event;


class PurchaseOrderEvent extends BaseEvent
{

		/*************************************
	      @ Content 生成采购单
	      @ Author YF
	      @ Time 2018-12-06
	      @ Param [array] [
	          $arr[0] = [
	          		'order_number'		 => 12312312, 采购单号
	                'our_buy_company_id' => 2, //我方采购公司
	                'region'             => 1, //城市id
	                'sale_user_id'       => 2, // 供应商用户id
	                'sale_company_id'    => 3, // 供应商 id 
	                'depot_id'           => 5, //油库id
	                'storehouse_id'      => 5, // 仓库id
	                'order_amount'       => 2890000,// 订单金额 （处理好之后的金额）,
	                'storage_quantity'   => 56776868, // 入库数量 (处理好之后的金额)
	                'goods_id'           => 3, // 商品id，
	                'price'              => 1231209, // 采购单价
	                'retail_inner_order_number' => 456789898765768,//内部交易单号
	                'goods_num'          => 2313,// 采购数量
	                'stock_in'			 => 入库单数据,
	                'stock_id'			 => 234,// 仓库id
	          ];
	      ]
	      @ Return ['status' => 状态码,'message' => 提示语 ];
	      [
			status => message [
				2 => 参数不正确！
				3 => 参数不能为空！
				4 => 数组建 0 - region 参数错误！
				7 => 无默认银行账号信息！
				9 => 账套 ID 2268 ： 存在多个默认银行账号！
				8 => 账套 ID 2268 ：无默认银行账号！
				5 => 采购单批量添加 失败！
				6 =>采购单日志表 批量添加失败！
				1 => 成功！
				10 => 账套ID：2131 不存在此账套！
				11 => 账套ID：12412 不符合确认条件！
			]
	      ]
	    **************************************/
		public function generatePurchaseOrder( $param )
		{
			(array)$params = ['order_number','our_buy_company_id','region','sale_user_id','sale_company_id','depot_id','storehouse_id','order_amount','storage_quantity','goods_id','price','retail_inner_order_number','goods_num','stock_in','stock_id'];
			// 错误日志
			(array)$err_log = [];
	        // 循环判断 参数
	        foreach ($param as $key => $value) {
	            if ( count($value) != count($params) ) {
	                return ['status' => 2,'message' => '参数不正确！'];
	            }
	            foreach ($value as $k => $v) {
	                if ( empty($v) ) {
	                	log_info('内部交易单：请求参数【'.json_encode($value).'】');
	                	$err_log[] = '账套ID：'.$value['our_buy_company_id'].'-库存id:'.$value['stock_id'].'-'.$k.'参数不能为空！友情提示（ region：'.$value['region'].',sale_company_id:'.$value[' sale_company_id'].',storehouse_id:'.$value['storehouse_id'].',goods_id:'.$value['goods_id'].')';
	                    // return ['status' => 3,'message' => '账套ID：'.$value['our_buy_company_id'].'-库存id:'.$value['stock_id'].'-'.$k.'参数不能为空！'];
	                }
	                if ( !in_array($k, $params) ) {
	                	$err_log[] = '账套ID：'.$value['our_buy_company_id'].'-库存id:'.$value['stock_id'].'-'.$k.'参数错误！';
	                    // return ['status' => 4,'message' => '账套ID：'.$value['our_buy_company_id'].'-库存id:'.$value['stock_id'].'-'.$k.'参数错误！'];
	                }
	            }
	        }
	        /**************** 获取所有的账套公司 进行匹配 ******************/
	        // 获取所有账套公司
	        (array)$company_arr = getAllErpCompany(); 
	        foreach ($param as $key => $value) {
	        	if ( !isset($company_arr[$value['our_buy_company_id']]) ) {
	        		$err_log[] = '账套ID：'.$value['our_buy_company_id'].'不存在此账套！';
	        		// return ['status' => 10,'message' => '账套ID：'.$value['our_buy_company_id'].'不存在此账套！'];
	        	}
	        	$param[$key]['our_buy_company_name'] = $company_arr[$value['our_buy_company_id']];
	        	unset($param[$key]['stock_id']);
	        }

	        /********************* 查询账套公司是否符合条件 ********************/
	        // 获取字段中的 账套名称
	        $our_company_name_arr = array_column($param, 'our_buy_company_name');
	        (array)$supplier_where = [
	        		'supplier_name' 	=> ['in',$our_company_name_arr],
	        		'is_inner' 			=> 1,
	        		'status'  			=> 1,
	        ];
	        (array)$supplier_arr = $this->getModel('ErpSupplier')->where($supplier_where)->getField('supplier_name,id');
	        foreach ($param as $key => $value) {
	        	if ( !isset($supplier_arr[$value['our_buy_company_name']]) ) {
	        		$err_log[] = '账套ID：'.$value['our_buy_company_id'].'不符合确认条件！';
	        		// return ['status' => 11,'message' => '账套ID：'.$value['our_buy_company_id'].'不符合确认条件！'];
	        	}
	        	unset($param[$key]['our_buy_company_name']);
	        }

	       	/******************* 查询所对应的 银行账号信息 ************************/
	       	// 获取字段中的 账套id
	        $our_company_id_arr = array_column($param, 'our_buy_company_id');
	        (array)$bank_where = [
	        			'our_company_id' => ['in',$our_company_id_arr],
	        			'status'		 => 1,
	        			'pay_type'		 =>	2,
	        			'default_bank'	 => 1,
	        ];
	        $banks_arr = $this->getModel('ErpBank')->where($bank_where)->select();
	        if ( empty($banks_arr) ) {
	        	$err_log[] = '账套ID：'.json_encode($our_company_id_arr).'不存在银行信息！';
	        	// return ['status' => 12,'message' => '账套ID：'.json_encode($our_company_id_arr).'不存在银行信息！'];
	        }

	        (array)$bank_arr = [];
	        foreach ($banks_arr as $key => $value1) {
	        	// 一个供应商只能 对应一个 默认账号
	        	foreach ( $banks_arr as $k => $v ) {
	        		if ( $key != $k && $value1['our_company_id'] == $v['our_company_id'] ) {
	        			$err_log[] = '账套 ID '.$value1['our_company_id'].':存在多个默认银行账号！';
	        			// return ['status' => 9,'message' => '账套 ID '.$value1['our_company_id'].':存在多个默认银行账号！'];
	        		}
	        	}
	        	$bank_arr[$value1['our_company_id']] = $value1['bank_name'].'--'.$value1['bank_num'];
	        }
	        /*------------------ END ----------------*/

	        $purchase_order_arr = $param;
	        foreach ($purchase_order_arr as $key => $value2) {
	        	if ( !isset($bank_arr[$value2['our_buy_company_id']]) ) {
	        		$err_log[] = '账套 ID'.$value2['our_buy_company_id'].':无默认银行账号！';
	        		// return ['status' => 8,'message' => '账套 ID'.$value2['our_buy_company_id'].':无默认银行账号！'];
	        	}
	        	unset($purchase_order_arr[$key]['stock_in']);
	        	// unset($value2['stock_in']);
	        	/************* 内部交易单 ***************/
	        	$$purchase_order_arr[$key]['order_amount']     = floor($value2['order_amount']); 
	        	$$purchase_order_arr[$key]['storage_quantity'] = $value2['storage_quantity'];
	        	$$purchase_order_arr[$key]['goods_num']        = $value2['goods_num'];
	        	/***************************************/
	        	$purchase_order_arr[$key]['sale_collection_info'] = $bank_arr[$value2['our_buy_company_id']];
	        	$purchase_order_arr[$key]['type']            = 1;
	        	$purchase_order_arr[$key]['pay_type']        = 3;
	        	$purchase_order_arr[$key]['account_period']  = 30; //账期天数
	        	$purchase_order_arr[$key]['buyer_dealer_id'] = 0; //采购人 默认 0
	        	$purchase_order_arr[$key]['buyer_dealer_name'] = 'SYS';
	        	$purchase_order_arr[$key]['create_time']     = nowTime(); //创建时间
	        	$purchase_order_arr[$key]['add_order_time']  = nowTime(); //下单时间
	        	$purchase_order_arr[$key]['creater']         = 0; // 创建人
	        	$purchase_order_arr[$key]['confirm_time']    = nowTime(); //确定时间
	        	$purchase_order_arr[$key]['check_time']      = nowTime();// 复核时间
	        	$purchase_order_arr[$key]['audit_time']      = nowTime(); // 审核时间
	        	$purchase_order_arr[$key]['order_status']    = 10; //订单状态 10 已确认
	        	$purchase_order_arr[$key]['remark']          = '内部采购单！';
	        	$purchase_order_arr[$key]['business_type']   = 6;
	        	// log_info('内部交易单：采购单执行中！');
	        }
	        // 判断 是否有错误记录
	        if ( !empty($err_log) ) {
	        	return ['status' => 8,'message' => '采购单错误记录：'.json_encode($err_log,JSON_UNESCAPED_UNICODE)];
	        }

	        $id = $this->getModel('ErpPurchaseOrder')->PurchaseOrderAddAll($purchase_order_arr);
	        if ( !$id ) {
	        	return ['status' => 5,'message' => '采购单批量添加 失败！'];
	        }

	        (array)$stock_in_arr = [];
	        (array)$log_arr = [];
	        $i = 0;
	        foreach ($purchase_order_arr as $key => $value) {
	        	// 采购日志
	        	$log_arr[$key]['purchase_id']           = $id;
	        	$log_arr[$key]['purchase_order_number'] = $value['order_number'];
	        	$log_arr[$key]['log_type']              = 1;
	        	$log_arr[$key]['log_info']              = serialize($value);
	        	$log_arr[$key]['create_time']           = nowTime();
	        	$log_arr[$key]['operator']              = 'SYS';
	        	// 入库单数据
	        	foreach ($param[$key]['stock_in'] as $k => $value6) {
	        		$stock_in_arr[$i]['storage_code']		= $value6['storage_code'];
		        	$stock_in_arr[$i]['source_number'] 		= $value['order_number'];
		            $stock_in_arr[$i]['source_object_id'] 	= $id;
		            $stock_in_arr[$i]['our_company_id']   	= $value['our_buy_company_id'];
		            $stock_in_arr[$i]['storehouse_id'] 		= $value['storehouse_id'];
		            $stock_in_arr[$i]['storage_num'] 		= $value6['storage_num'];
		            $stock_in_arr[$i]['goods_id'] 			= $value['goods_id'];
		            $stock_in_arr[$i]['region'] 			= $value['region'];
		            $stock_in_arr[$i]['price']				= $value['price'];
		            $stock_in_arr[$i]['retail_inner_order_number'] = $value['retail_inner_order_number'];
		            $stock_in_arr[$i]['cargo_bn_id']		= $value6['cargo_bn_id'];
		            $i++;
	        	}
	        	$id++;
	        }
	       	// 添加 采购日志
            $log_status = $this->getModel('ErpPurchaseLog')->addAll($log_arr);
            if ( !$log_status ) {
            	return ['status' => 6,'message' => '采购单日志表 批量添加失败！'];
            }
            return ['status' => 1,'message' => '成功！','data' => $stock_in_arr];
		}



























}