<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 15:31
 */

namespace Crons\Event;


class StockInEvent extends BaseEvent
{

		/*************************************
	      @ Content 生成入库单
	      @ Author YF
	      @ Time 2018-12-07
	      @ Param [array] [
	          $arr[0] = [
	                [source_number] 	=> PO2018120700000039  	// 来源 采购单号
		            [source_object_id] 	=> 18744 				// 来源采购单id
		            [our_company_id] 	=> 2 					// 账套id
		            [storehouse_id]	 	=> 5 					// 仓库id
		            [storage_num] 		=> 2313					// 入库数量
		            [goods_id] 			=> 3					// 商品 ID
		            [region] 			=> 1					// 城市ID 
		            [retail_inner_order_number] => 456789898765768 // 内部交易单号
		            [storage_code]		=> 入库单号
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
		public function generateStockIn( $param )
		{
			// 验证参数
			$params = ['source_number','source_object_id','our_company_id','goods_id','storage_num','region','storehouse_id','retail_inner_order_number','price','storage_code','cargo_bn_id'];
			foreach ($param as $key => $value) {
				if ( count($value) != count($params) ) {
	                return ['status' => 101,'message' => '参数不正确！'];
	            }
	            foreach ($params as $k => $v) {
					if ( !isset($value[$v]) || empty($value[$v]) ) {
						return ['status' => 102,'message' => '数组建-'.$key.'-'.$v.'缺少此参数！'];
					}
				}
			}
			// 查询 仓库内存
			(array)$storehouse_id_arr = array_column($param,'storehouse_id');
			if ( empty($storehouse_id_arr) ) {
				return ['status' => 111 ,'message' => '缺少storehouse_id！'];
			}
			(array)$stock_where = [
				'id'     => ['in',$storehouse_id_arr],
				'status' => 1,
			];
			$stock_arr = $this->getModel('ErpStorehouse')->where($stock_where)->getField('id,type');
			if ( empty($stock_arr) ) {
				return ['status' => 103,'message' => '无此仓库 IDs-'.json_encode($storehouse_id_arr) ];
			}

			$outbound_density = getConfig('Inner_Density');
			foreach ($param as &$value) {
				if ( !isset($stock_arr[$value['storehouse_id']]) ) {
					return ['status' => 104,'message' => 'ID'.$value['storehouse_id'].'无此仓库!' ];
				}
				$value['storage_type'] 		= 1;
				$value['storage_status'] 	= 10; // 入库单状态
				$value['storage_remark'] 	= '内部交易单';
				$value['actual_storage_num'] = $value['storage_num']; // 实际入库数量
				$value['creater_id'] 		= 0;
				$value['create_time'] 		= nowTime();
				$value['dealer_id'] 		= 0;
				$value['dealer_name'] 		= 'SYS';
				$value['stock_type'] 		= storehouseTypeToStockType($stock_arr[$value['storehouse_id']]); // 库存类型
				$value['finance_status'] 	= 10; // 财务审核
				$value['audit_time'] 		= nowTime();
				$value['auditor_id'] 		= 0; //审核人ID
				$value['balance_num']		= $value['storage_num'];
				$value['balance_num_litre'] = $value['storage_num']*1000/$outbound_density;
				$value['outbound_density']  = $outbound_density;

				// 生成批次数据
				$batch_result = $this->getEvent('ErpBatch','Home')->addBatch($value);
				if ( $batch_result['status'] != 1 ) {
					return $batch_result;
				}
				$value['batch_sys_bn'] 		= $batch_result['data']['sys_bn'];
				$value['batch_id']          = $batch_result['data']['id'];
			}
			$status = $this->getModel('ErpStockIn')->addAll($param);
			if ( !$status ) {
				return ['status' => 105,'message' => '入库单生成失败！'];
			}
			return ['status' => 1,'message' => '入库单生成成功！'];
		}

















}