<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 15:31
 */

namespace Crons\Event;


class StockOutEvent extends BaseEvent
{


	/**********************************	
		@ Content 批量生成 出库单
		@ Author YF
		@ Time 2018-12-10
		@ Param [
			'source_number' 			=> 来源销售单号,
			'source_object_id' 			=> 来源销售单id,
			'our_company_id' 			=> 账套id,
			'goods_id' 					=> 商品id,
			'depot_id' 					=> 油库id,
			'actual_outbound_num' 		=> 实际出库数量,
			'storehouse_id' 			=> 仓库id,
			'region' 					=> 城市id,
			'retail_inner_order_number' => 内部交易单号,
			'outbound_code' 			=> 2342342, // 出库单号
            'cost'						=> 213, // 成本价
		]
		@ Ruturn [
			status => message [
				201 => 参数不正确！,
				202 => 数组建 -0-good_id ：缺少此参数！,
				203 => 无此仓库 IDs - [213,123]！,
				204 => ID 21312 无此仓库!,
				205 => 出库单批量添加 失败！,
				1 	=> 出库单 生成 成功！,
			]
		]
	***********************************/
	public function generateStockOut( $param )
	{
		(array)$params = ['source_number','source_object_id','our_company_id','goods_id','depot_id','actual_outbound_num','storehouse_id','region','retail_inner_order_number','outbound_code','cost','batch_id','batch_sys_bn'];
		foreach ($param as $key => $value) {
			if ( count($value) != count($params) ) {
	                return ['status' => 201,'message' => '参数不正确！'];
	        }
            foreach ($params as $k => $v) {
				if ( !isset($value[$v]) || empty($value[$v]) ) {
					return ['status' => 202,'message' => '数组建-'.$key.'-'.$v.':缺少此参数！'];
				}
			}
		}
		/******************** 查询仓库 类型 ***********************/
		(array)$storehouse_id_arr = array_column($param,'storehouse_id');
		(array)$stock_where = [
			'id'     => ['in',$storehouse_id_arr],
			'status' => 1,
		];
		$stock_arr = $this->getModel('ErpStorehouse')->where($stock_where)->getField('id,type');
		if ( empty($stock_arr) ) {
			return ['status' => 203,'message' => '无此仓库 IDs-'.json_encode($storehouse_id_arr) ];
		}
		foreach ($param as &$value) {
			if ( !isset( $stock_arr[$value['storehouse_id']] )  ) {
				return ['status' => 204,'message' => 'ID'.$value['storehouse_id'].'无此仓库!' ];
			}
			$value['outbound_type'] 	= 1; // 销售类型
			$value['outbound_status'] 	= 10; // 出库单状态
			$value['outbound_remark'] 	= '内部交易单'; // 出库备注
			$value['outbound_num'] 		= $value['actual_outbound_num']; // 计划出库数量
			$value['deduction_num'] 	= $value['actual_outbound_num']; // 入库单抵扣数量
			$value['create_time'] 		= nowTime();
			$value['stock_type'] 		= storehouseTypeToStockType($stock_arr[$value['storehouse_id']]); // 库存类型 
			$value['dealer_id'] 		= 0;
			$value['dealer_name'] 		= 'SYS';
			$value['creater_id'] 		= 0;
			$value['creater_name'] 		= 'SYS';
			$value['audit_time'] 		= nowTime(); // 审核时间
			$value['finance_status']    = 10;

			$batch_change_data = [
                'batch_id' 				=> $value['batch_id'],
                'change_balance_num' 	=> plusConvert($value['actual_outbound_num']), //减少批次可用
                'change_reserve_num' 	=> plusConvert($value['actual_outbound_num']), //减少批次预留
                'change_type' 			=> 2,
                'change_number' 		=> $value['outbound_code'],
            ];
            $batch_result = $this->getEvent('ErpBatch','Common')->commonChangeBatchNum($batch_change_data);
            if($batch_result['status'] != 1){
                return $batch_result;
            }
		}
		// 生成出库单
		$id = $this->getModel('ErpStockOut')->addAll($param);
		if ( !$id ) {
			return ['status' => 205, 'message' => '出库单添加失败！请回滚！'];
		}
		return ['status' => 1 ,'message' => '出库单 生成 成功！']; 
	}

























}