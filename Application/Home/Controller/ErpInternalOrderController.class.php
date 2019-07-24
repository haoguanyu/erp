<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpInternalOrderController extends BaseController
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
		public function getInternalOrderList()
		{
			if (IS_AJAX) {
				$param = $_REQUEST;
				$data = $this->getEvent('ErpInternalOrder')->getInternalOrderList($param);
            	$this->echoJson($data);
			}
			(array)$arr = [];
			$arr['company'] = $this->getEvent('ErpInternalOrder')->getCompanyId();
			$this->assign('data',$arr);
			$this->display();
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
		public function getStorehouseId()
		{
			$param = I('get.');
	        $arr['data'] = $this->getEvent('ErpInternalOrder')->getStorehouseId($param);
	       	$this->echoJson($arr);
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
		public function getOrderByInternalOrder()
		{
			$param = I('get.');
			$arr = $this->getEvent('ErpInternalOrder')->getOrderByInternalOrder($param);
			$this->assign('arr',$arr);
			$this->display();
		}

















}