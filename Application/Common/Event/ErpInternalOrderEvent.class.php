<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpInternalOrderEvent extends BaseController
{

		/***************************************
			@ Content 查询内部交易单 列表
			@ Author YF
			@ Time 2018-12-21
			@ Param [
				AJAX => [
					'length' => 条数
					'start'  => 开始页数
					 ------ where --------
					 demand_our_company_id => 库存主体id
					 supply_our_company_id => 销售主体id
					 storehouse_id		   => 仓库id 
					 order_status		   => 内部订单状态
					 order_number		   => 订单号
				]
			@ Return [
				@arr => [
				    ..........
				    ..........
				    ..........
				    data => [ 
					    0 => [
					    	映射 内部交易单所有数据 及 以下
							'region' 				=  城市名称
							'goods_name' 			=  商品名称
							'source_from' 			=  如：中国石化
							'grade' 				=  标号
							'level' 				=  级别
							'goods_code'			=  商品编码
							'label'					=  标注
							demand_our_company_id'  =  库存主体名称																	
							supply_our_company_id'  =  销售主体名称					
							storehouse_id' 		    =  仓库名称
							status' 			    =  状态名称
					    ]
					]
			    ]
			]
		****************************************/
		public function getInternalOrderList( $param )
		{
			$where = $this->handleInternalOrderWhere($param);
			// 查询内部交易单 
			$data = $this->getModel('ErpRetailInnerOrder')->getInternalOrderList($where,'',$param['start'], $param['length']);

			$data['data'] = $this->InternalOrderHandle($data['data']);
			$data['recordsFiltered'] = $data['recordsTotal'];
        	$data['draw'] = $param['draw'];
        	return $data;
		}

		/************************************
			@ Content 处理where 条件
			@ Author YF
			@ Time 2018-12-21
			@ Param [
					 ------ where --------
					 demand_our_company_id => 库存主体id
					 supply_our_company_id => 销售主体id
					 storehouse_id		   => 仓库id 
					 order_status		   => 内部订单状态
					 order_number		   => 订单号
				]
			@ Return [


			]
		************************************/
		public function handleInternalOrderWhere($param)
		{
			$where = [];
			if ( !empty($param['order_number']) && isset($param['order_number']) ) {
				$where['order_number'] = ['like',['%'.trim($param['order_number']).'%']];
			}

			if ( !empty($param['order_status']) && isset($param['order_status']) ) {
				$where['status'] = ['eq',trim($param['order_status'])];
			}

			if ( !empty($param['demand_our_company_id']) && isset($param['demand_our_company_id']) ) {
				$where['demand_our_company_id'] = ['eq',trim($param['demand_our_company_id'])];
			}

			if ( !empty($param['supply_our_company_id']) && isset($param['supply_our_company_id']) ) {
				$where['supply_our_company_id'] = ['eq',trim($param['supply_our_company_id'])];
			}

			if ( !empty($param['storehouse_id']) && isset($param['storehouse_id']) ) {
				$where['storehouse_id'] = ['eq',trim($param['storehouse_id'])];
			}
			return $where;
		}

		/**********************************************
			@ Content 处理 内部交易单 数组
			@ Author YF
			@ Time 2018-12-21
		***********************************************/
		public function InternalOrderHandle( $data = [] )
		{
			if ( empty($data) ) {
				return [];
			}
			$city_arr = provinceCityZone()['city']; // 城市
			/*---------------------- 商品 --------------------------*/
			(array)$goods_ids = array_column($data, 'goods_id'); // 商品id
			(array)$goods = $this->getModel('ErpGoods')->where(['id'=>['in',$goods_ids]])->select();
			(array)$goods_arr = [];
			if (!empty($goods)) {
				foreach ($goods as $key => $value) {
					$goods_arr[$value['id']] = $value;
				}
			}
			/*---------------------- END --------------------------*/

			/*---------------------- 需求方账套 --------------------------*/
			$demand_our_company_ids = array_column($data, 'demand_our_company_id'); // 需求方账套id
			$demand_our_company_arr = $this->getCompanyName(['o.company_id'=>['in',$demand_our_company_ids]],1);
			/*---------------------- 供应方方账套 --------------------------*/
			$supply_our_company_ids = array_column($data, 'supply_our_company_id'); // 供应商方账套id
			$supply_our_company_arr = $this->getCompanyName(['o.company_id'=>['in',$supply_our_company_ids]],2);
			/*---------------------- END ----------------------------------*/

			/*---------------------- 获取仓库名称 --------------------------*/
			$storehouse_ids = array_column($data, 'storehouse_id'); // 仓库id
			(array)$storehouse_arr = $this->getModel('ErpStorehouse')->where(['id'=>['in',$storehouse_ids]])->getField('id,storehouse_name');

			/*---------------------- 处理数组 --------------------------*/
			foreach ($data as &$value) {
				$value['region'] 				= isset($city_arr[$value['region']]) ? $city_arr[$value['region']] : '-';
				$value['goods_name'] 			= isset($goods_arr[$value['goods_id']]) ? $goods_arr[$value['goods_id']]['goods_name'] : '-';
				$value['source_from'] 			= isset($goods_arr[$value['goods_id']]) ? $goods_arr[$value['goods_id']]['source_from'] : '-';
				$value['grade'] 				= isset($goods_arr[$value['goods_id']]) ? $goods_arr[$value['goods_id']]['grade'] : '-';
				$value['level'] 				= isset($goods_arr[$value['goods_id']]) ? $goods_arr[$value['goods_id']]['level'] : '-';
				$value['goods_code']			= isset($goods_arr[$value['goods_id']]) ? $goods_arr[$value['goods_id']]['goods_code'] : '-';
				$value['label']					= isset($goods_arr[$value['goods_id']]) ? $goods_arr[$value['goods_id']]['label'] : '-';
				$value['demand_our_company_id'] = isset($demand_our_company_arr[$value['demand_our_company_id']]) 
																			? $demand_our_company_arr[$value['demand_our_company_id']] : '-';
				$value['supply_our_company_id'] = isset($supply_our_company_arr[$value['supply_our_company_id']]) 
																			? $supply_our_company_arr[$value['supply_our_company_id']] : '-';
				$value['storehouse_id'] 		= isset($storehouse_arr[$value['storehouse_id']]) ? $storehouse_arr[$value['storehouse_id']] : '-';
				$value['status'] 				= internalOrderStatus($value['status']);
				$value['goods_num']				= round($value['goods_num'] / 10000,4);
				$value['amount']				= round($value['amount'] / 10000,2);
			}
			return $data;
		}

		public function getCompanyName($where = [],$type = 0)
		{
			$type_name = $type == 1 ? 'oil_erp_customer' : 'oil_erp_supplier';
			$company_name = $type == 1 ? 'customer_name' : 'supplier_name';
			(array)$company_arr = $this->getModel('ErpCompany')
							->alias('o')
							->join($type_name.' g on o.company_name = g.'.$company_name, 'left')
							->where($where)->getField('o.company_id,g.'.$company_name);
			if ( empty( $company_arr ) ) {
				$company_arr = [];
			}
			return $company_arr;
		}

		/*************************************
			@ Content 获取公司id
			@ Author SYF
			@ Time 2018-12-20
			@ Return [
				$arr [company_id] => company_name =>  [
					[3372] => 汇油
				]
			] 
		**************************************/
		public function getCompanyId()
		{
			$arr = $this->getModel('erp_company')->where(['status'=>1])->getField('company_id,company_name');
			return $arr;
		}

		/**************************************
			@ Content 查询 仓库信息
			@ Author YF
			@ Time 2018-12-21
			@ Param [ 
				storehouse_name => 名称 （模糊查询）
			]
			@ Return [
				[
					0 => [
						id 				=> id
						storehouse_name => 仓库名称
					]

				]
			]
		**************************************/
		public function getStorehouseId( $param )
		{
			if ( !isset( $param) ) {
				return [];
			}
			$storehouse_name = trim($param['storehouse_name']);
			$where['storehouse_name'] = ['like', '%' . $storehouse_name . '%'];
			$arr = $this->getModel('ErpStorehouse')->where($where)->field('id,storehouse_name')->select();
			if ( empty($arr) ) {
				return [];
			}
			return $arr;
		}



		/**************************************
			@ Content 根据 内部交易单 id 查询 零售订单
			@ Author YF
			@ Time 2018-12-21
			@ Param [ 
				id => 内部交易单id
			]
			@ Return [
				 [retail_order] => Array
		        (
		            [0] => Array
		                (
		                    [id] 			=> 31019
		                    [order_number] 	=> 2017070700000123
		                    [user_name] 	=> 陈刚
		                    [user_phone] 	=> 13375226588
		                    [retail_adress_id] => 12062
		                    [retail_goods_id] => 163
		                    [dealer_id] 	=> 76
		                    [source] 		=> 中国石化
		                    [location] 		=> 不限
		                    [type] 			=> 柴油
		                    [rank] 			=> 国Ⅳ
		                    [level] 		=> 0#
		                    [actual_price] 	=> 1653.30
		                    [company_name] 	=> 上海冰团冷藏运输有限公司
		                    [trans_true_time] => 2017-08-09 15:13:07
		                    [number] 		=> 334.00000
		                )

		        )

		    [galaxy_order] => Array
		        (
		            [0] => Array
		                (
		                    [id] 			=> 31019
		                    [order_number] 	=> 2017070700000123
		                    [user_name] 	=> 陈刚
		                    [user_phone] 	=> 13375226588
		                    [retail_adress_id] => 12062
		                    [retail_goods_id] => 163
		                    [dealer_id] 	=> 76
		                    [source] 		=> 中国石化
		                    [location] 		=> 不限
		                    [type] 			=> 柴油
		                    [rank] 			=> 国Ⅳ
		                    [level] 		=> 0#
		                    [actual_price] 	=> 1653.30
		                    [company_name] 	=> 上海冰团冷藏运输有限公司
		                    [trans_true_time] => 2017-08-09 15:13:07
		                    [number] 		=> 334.00000
		                )
				]
			]
		**************************************/
		public function getOrderByInternalOrder($param)
		{
			if ( !isset($param['id']) || empty($param['id']) ) {
				return [];
			}
			$internal_order = $this->getModel('ErpRetailInnerOrder')->where(['id'=>['eq',trim($param['id'])]])->field('order_number')->find();
			(string)$order_number = $internal_order['order_number'];
			(array)$storage_code_arr = $this->getModel('ErpStockIn')->where(['retail_inner_order_number'=>['eq',$order_number]])->field('storage_code')->select();
			if ( empty($storage_code_arr) ) {
				return [];
			}
			(array)$storage_codes = array_column($storage_code_arr, 'storage_code');
			(array)$retail_source_number_arr = $this->getModel('ErpStockOutRetail')->where(['source_stock_in_number'=>['in',$storage_codes]])->field('source_number')->select();
			if ( empty($retail_source_number_arr) ) {
				return [];
			}
			(string)$source_numbers = array_column($retail_source_number_arr, 'source_number');

			(array)$retail_order = $this->getModel('ErpSaleRetailOrder')
								->where(['order_number'=>['in',$source_numbers]])
								->select();
			$company_ids = array_column($retail_order,'company_id');
			$user_ids = array_column($retail_order,'user_id');
			$company_arr = $this->getModel('ErpCustomer')->where(['id'=>['in',$company_ids]])->getField('id,customer_name');
			$user_arr = $this->getModel('ErpCustomerUser')->where(['id'=>['in',$user_ids]])->field('id,user_name,user_phone')->select();
			(array)$customer_user = [];
			foreach ($user_arr as $key => $value1) {
				$customer_user[$value1['id']] = $value1;
			}
			foreach ($retail_order as $key => $value) {
				$retail_order[$key]['company_name'] = isset($company_arr[$value['company_id']]) ? $company_arr[$value['company_id']] : '-';
				$retail_order[$key]['user_name']    = isset($customer_user[$value['user_id']]) ? $customer_user[$value['user_id']]['user_name'] : '-';
				$retail_order[$key]['user_phone']   = isset($customer_user[$value['user_id']]) ? $customer_user[$value['user_id']]['user_phone'] : '-';
				$retail_order[$key]['type']	        = $value['goods_name'];
				$retail_order[$key]['rank']	        = $value['goods_rank'];
				$retail_order[$key]['level']	    = $value['goods_level'];
				$retail_order[$key]['source']	    = $value['goods_source_from'] != '未知' ? $value['goods_source_from'] : '--';
				if ($value['order_source'] == 7 && $retail_order[$key]['company_name'] == '-' ) {
					$retail_order[$key]['company_name'] = 'C端公司';
				}
				$retail_order[$key]['order_source'] = saleOrderSourceFrom($value['order_source'],true);
				$retail_order[$key]['actual_price'] = round($value['order_amount'] / 10000,2);
				$retail_order[$key]['number']       = $value['buy_num_retail'] / 10000;
				$retail_order[$key]['dealer_name']  = $value['dealer_name'] == '' ? '--' : $value['dealer_name'];
 			}
			return $retail_order;
			// (array)$retail_order = $this->getModel('ErpSaleRetailOrder')
			// 					->where(['order_number'=>['in',$source_numbers],'order_source'=>['eq',4]])
			// 					->field('from_order_number')->select();
			// (array)$galaxy_order = $this->getModel('ErpSaleRetailOrder')
			// 					->where(['order_number'=>['in',$source_numbers],'order_source'=>['eq',5]])
			// 					->field('from_order_number')->select();
			// (array)$arr = [];
			// $arr['retail_order'] = $this->getRetailOrder($retail_order);
			// $arr['galaxy_order'] = $this->getGalaxyOrder($galaxy_order);
			// return $arr;
		}

		/***************************************
			@ Content 查询小微订单 废弃
		****************************************/
		public function getRetailOrder( $arr )
		{
			if ( empty($arr) ) {
				return [];
			}
			(array)$retail_order_numbers = array_column($arr, 'from_order_number');
			(array)$retail_order_arr = $this->getModel('RetailOrder')->where(['order_number'=>['in',$retail_order_numbers]])->select();
			return $retail_order_arr;
		}

		/***************************************
			@ Content 查询集团订单 废弃
		****************************************/
		public function getGalaxyOrder( $arr )
		{
			if ( empty($arr) ) {
				return [];
			}
			/*--------------- 查询集团订单号 ------------------------*/
			(array)$galaxy_order_number = array_column($arr, 'from_order_number');
			(array)$galaxy_order_arr = $this->getModel('GalaxyOrder')->where(['order_number'=>['in',$galaxy_order_number]])->select();
			/*--------------- 查询用户信息 ------------------------*/
			(array)$user_ids = array_column($galaxy_order_arr,'user_id');
			(array)$user_arr = $this->getModel('User')->where(['id'=>['in',$user_ids]])->field('id,user_name,user_phone')->select();
			(array)$user_arrs = [];
			foreach ($user_arr as $key => $value) {
				$user_arrs[$value['id']] = $value;
			}
			/*--------------- 查询公司名称 ------------------------*/
			(array)$user_company_ids = array_column($galaxy_order_arr,'user_company_id');
			$company_arr = $this->getModel('Clients')->where(['id'=>['in',$user_company_ids]])->getField('id,company_name');

			/*--------------- 查询商品信息 ------------------------*/
			(array)$goods_ids = array_column($galaxy_order_arr,'galaxy_goods_id');
			$goods_arr = $this->getModel('GalaxyGoods')->where(['id'=>['in',$goods_ids]])->field('id,type,rank,level,source')->select();
			(array)$goods_arrs = [];
			foreach ($goods_arr as $key => $value) {
				$goods_arrs[$value['id']] = $value;
			}
			/*--------------- 处理数据 ------------------------*/
			foreach ($galaxy_order_arr as &$value ) {
				$value['company_name'] = isset($company_arr[$value['user_company_id']]) ? $company_arr[$value['user_company_id']] : '-';
				$value['user_name']	   = isset($user_arrs[$value['user_id']]['user_name']) ? $user_arrs[$value['user_id']]['user_name'] : '-';
				$value['user_phone']   = isset($user_arrs[$value['user_id']]['user_phone']) ? $user_arrs[$value['user_id']]['user_phone'] : '-';
				$value['type']	       = isset($goods_arrs[$value['galaxy_goods_id']]['type']) ? $goods_arrs[$value['galaxy_goods_id']]['type'] : '-';
				$value['rank']	       = isset($goods_arrs[$value['galaxy_goods_id']]['rank']) ? $goods_arrs[$value['galaxy_goods_id']]['rank'] : '-';
				$value['level']	       = isset($goods_arrs[$value['galaxy_goods_id']]['level']) ? $goods_arrs[$value['galaxy_goods_id']]['level'] : '-';
				$value['source']	   = isset($goods_arrs[$value['galaxy_goods_id']]['source']) ? $goods_arrs[$value['galaxy_goods_id']]['source'] : '-';
			}
			return $galaxy_order_arr;
		}






















}