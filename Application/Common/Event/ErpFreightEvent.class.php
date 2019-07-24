<?php
namespace Common\Event;

use Home\Controller\BaseController;
/**
 * 
 * 运费单逻辑层
 * @author xiaowen
 * @time 2019-5-14
 * 
 */
class ErpFreightEvent extends BaseController
{
  	    /*
		* @ Content 运费单列表
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function freightOrderList( $param ){
	    	$where = $this->freightOrderWhere( $param );
	    	if ( isset($where['message']) ) {
				return ['data' => [],'recordsFiltered' => 0,'recordsTotal'=> 0,'draw' => $param['draw']];
			}
	    	/* --------——— 查询数据 ------------- */
			$field  = "*";
			$offset = $param['start'];
			$limit  = $param['length'];
			$freight_order_arr = $this->getModel("ErpFreightOrder")->where($where)->field($field)->limit($offset, $limit)->order('id desc')->select();
			if ( empty($freight_order_arr) ) {
				return ['data' => [],'recordsFiltered' => 0,'recordsTotal'=> 0,'draw' => $param['draw']];
			}
			$result_arr 	   = $this->handlefreightOrder($freight_order_arr);
			$freight_order_num = $this->getModel("ErpFreightOrder")->where($where)->count();
			if ( isset($param['draw']) ) {
				$sum_total = [];
				$count_freight_order = $this->getModel("ErpFreightOrder")->where($where)->field('transport_num,transport_amount')->select();
				$sum_total['pageAll_transport_num']  	= array_sum(array_column($result_arr,'transport_num'));
				$sum_total['pageAll_transport_amount'] 	= round(array_sum(array_column($result_arr,'transport_amount')),2);
				$all_transport_amount = 0;
				$all_transport_num = 0;
				foreach ($count_freight_order as $key => $value) {
					$all_transport_num 		+= getNum($value['transport_num']);
					$all_transport_amount 	+= getNum($value['transport_amount']);
				}
				$sum_total['all_transport_num'] 		= $all_transport_num;
				$sum_total['all_transport_amount'] 		= round($all_transport_amount,2);
				$result_arr[0]['sum_total'] = $sum_total;
			}
			return ['data' => $result_arr,'recordsFiltered' => $freight_order_num,'recordsTotal'=> $freight_order_num,'draw' => $param['draw']];
	    }

	   /*
		* @ Content 运费单筛选条件
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function freightOrderWhere( $param ) {
	    	$where = [];
	    	// 账套区分
			$where['our_company_id'] = ['eq',session('erp_company_id')];
	    	if ( isset($param['order_number']) && !empty($param['order_number']) ) {
	    		$where['order_number'] = ['like', ['%' . trim($param['order_number']) . '%']];
	    	}
	    	
	    	$loss_arr = [];
	    	if ( isset($param['shipping_order_number']) && !empty($param['shipping_order_number']) ) {
	    		$shipping_where = [
	    			'order_number' => ['eq',trim($param['shipping_order_number'])]
	    		];
	    		$shipping_arr = $this->getModel('ErpShippingOrder')->where($shipping_where)->field('source_type,source_number,source_order_number')->find();
	    		if ( empty($shipping_arr) ) {
	    			return ['status' => 2,'message' => '未查询到配送单！'];
	    		}
	    		// 销售类型
	    		if ( $shipping_arr['source_type'] == 1 ) {
	    			// 出库申请单
	    			$order_number = $shipping_arr['source_number'];
	    			$stock_out_where = [
	    				'source_apply_number' => ['eq',$order_number]
	    			];
	    			$stock_arr = $this->getModel('ErpStockOut')->where($stock_out_where)->getField('id,outbound_code');
	    		// 调拨类型
	    		}elseif ( $shipping_arr['source_type'] == 2 ) {
	    			$order_number = $shipping_arr['source_order_number'];
	    			$stock_in_arr = [
	    				'source_number' => ['eq',$order_number]
	    			];
	    			$stock_arr = $this->getModel('ErpStockIn')->where($stock_in_arr)->getField('id,storage_code');
	    		// 采购类型
	    		}elseif ( $shipping_arr['source_type'] == 3 ) {
	    			// 入库申请单
	    			$order_number = $shipping_arr['source_number'];
	    			$stock_in_arr = [
	    				'source_apply_number' => ['eq',$order_number]
	    			];
	    			$stock_arr = $this->getModel('ErpStockIn')->where($stock_in_arr)->getField('id,storage_code');
	    		}
	    		if ( empty($stock_arr) ) {
	    			return ['status' => 3,'message' => '未查询到出\入库单！'];
	    		}
	    		$loss_where = [
	    			'source_number' => ['in',$stock_arr]
	    		];
	    		$loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->getField('id,order_number');
	    		if ( empty($loss_arr) ) {
	    			return ['status' => 4,'message' => '未查询损耗单！'];
	    		}
	    	}

	    	if ( isset($param['source_number']) && !empty($param['source_number']) && empty($loss_arr) ) {
	    		$where['source_number'] = ['like', ['%' . trim($param['source_number']) . '%']];
	    	} else if (isset($param['source_number']) && !empty($param['source_number']) && !empty($loss_arr) ) {
	    		if ( !in_array(trim($param['source_number']),$loss_arr)) {
	    			return ['status' => 5,'message' => '未查询到所对应的数据'];
	    		}
	    		$where['source_number'] = ['eq',trim($param['source_number'])];
	    		
	    	} elseif ( empty($param['source_number']) && !empty($loss_arr) ) {
	    		$where['source_number'] = ['in',$loss_arr];
	    	}
	    	
	    	if ( isset($param['order_status']) && !empty($param['order_status']) ) {
	    		$where['order_status'] = ['eq',$param['order_status']];
	    	}

	    	if ( isset($param['reversed_status']) && !empty($param['reversed_status']) ) {
	    		$where['reversed_status'] = ['eq',$param['reversed_status']];
	    	}

	    	if ( isset($param['carrier_company_name']) && !empty($param['carrier_company_name']) ) {
	    		$where['carrier_company_name'] = ['like', ['%' . trim($param['carrier_company_name']) . '%']];
	    	}

	    	if ( (isset($param['start_time']) &&  !empty($param['start_time'])) && ( isset($param['end_time']) && !empty($param['end_time'])) ) {
				$where['create_time'] = ['between',[trim($param['start_time']),trim($param['end_time']." 23:59:59")]];
			} else {
				if ( isset($param['start_time']) && !empty($param['start_time']) ) {
					$where['create_time'] = ['GT',trim($param['start_time'])];
				} elseif ( isset($param['end_time']) && !empty($param['end_time']) ) {
					$where['create_time'] = ['ELT',trim($param['end_time']." 23:59:59")];
				}
			}
			return $where;
	    }

	    /*
		* @ Content 处理运费单数据
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function handlefreightOrder( $arr = [] ){
	    	if ( empty($arr) ) {
	    		return $arr;
	    	}
	    	// 损耗单号
	    	$loss_order_number = array_column($arr,'source_number');
	    	$loss_where = [
	    		'order_number' => ['in',$loss_order_number]
	    	];
	    	$loss_arr = $this->getModel('ErpLossOrder')->where($loss_where)->field('order_number,source_number,type')->select();
	    	// 损耗数据
	    	$purchase_order = [];
	    	$allocation_order = [];
	    	$sale_order = [];
	    	foreach ($loss_arr as $key => $value) {
	    		switch ($value['type']) {
	    			// 采购
	    			case 1:
	    				$purchase_order[$key]['order_number'] = $value['order_number'];
	    				$purchase_order[$key]['storage_code'] = $value['source_number'];
	    				break;
	    			// 调拨
	    			case 2:
	    				$allocation_order[$key]['order_number']  = $value['order_number'];
	    				$allocation_order[$key]['storage_code'] = $value['source_number'];
	    				break;
	    			// 销售
	    			case 3:
	    				$sale_order[$key]['order_number']  = $value['order_number'];
	    				$sale_order[$key]['outbound_code'] = $value['source_number'];
	    				break;
	    		}
	    	}
	    	$shipping_arr = [];
	    	// 采购类型
	    	if ( !empty($purchase_order) ) {
	    		$storage_code = array_column($purchase_order, 'storage_code');
	    		$stock_in_where = [
	    			'stock.storage_code' => ['in',$storage_code],
	    		];
	    		$shipping_order = $this->getModel('ErpStockIn')->alias('stock')->join('oil_erp_shipping_order shipping on stock.source_apply_number = shipping.source_number', 'left')->where($stock_in_where)->getField('stock.storage_code,shipping.order_number');
	    		foreach ($purchase_order as $key => $value) {
	    			// order_number = 损耗单单号
	    			$shipping_arr[$value['order_number']] = isset($shipping_order[$value['storage_code']]) ? $shipping_order[$value['storage_code']] : '--';
	    		}
	    	}
	    	// 调拨类型
	    	if ( !empty($allocation_order) ) {
	    		$stock_in_where = ['stock.storage_code'=>['in',array_column($allocation_order,'storage_code')]];
	    		$shipping_order = $this->getModel('ErpStockIn')->alias('stock')->join('oil_erp_shipping_order shipping on stock.source_number = shipping.source_order_number', 'left')->where($stock_in_where)->getField('stock.storage_code,shipping.order_number');
	    		foreach ($allocation_order as $key => $value) {
	    			// order_number = 损耗单单号
	    			$shipping_arr[$value['order_number']] = isset($shipping_order[$value['storage_code']]) ? $shipping_order[$value['storage_code']] : '--';
	    		}
	    	}
	    	// 销售类型
	    	if ( !empty($sale_order) ) {
	    		$outbound_code = array_column($sale_order,'outbound_code');
	    		$stock_out_where = [
	    			'stock.outbound_code' => ['in',$outbound_code],
	    		];
	    		$shipping_order = $this->getModel('ErpStockOut')->alias('stock')->join('oil_erp_shipping_order shipping on stock.source_apply_number = shipping.source_number', 'left')->where($stock_out_where)->getField('stock.outbound_code,shipping.order_number');
	    		foreach ($sale_order as $key => $value) {
	    			// order_number = 损耗单单号
	    			$shipping_arr[$value['order_number']] = isset($shipping_order[$value['outbound_code']]) ? $shipping_order[$value['outbound_code']] : '--';
	    		}
	    	}
	    	foreach ($arr as $key => &$value) {
	    		$value['shipping_order_number'] = $shipping_arr[$value['source_number']];
 	    		$value['order_status_name'] = freightOrderStatus($value['order_status']);
	    		$value['transport_num']     = getNum($value['transport_num']);
	    		$value['transport_amount']  = round(getNum($value['transport_amount']),2);
	    		if ( $value['reversed_status'] == 1 ) {
	    			$value['reversed_status'] = '是';
	    		} elseif ($value['reversed_status'] == 2) {
	    			$value['reversed_status'] = '否';
	    		}
	    	}
	    	return $arr;
	    }

	   /*
		* @ Content 查询超损入库单
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function findSuperStockInOrder( $param ){
	    	if ( !isset($param['loss_order_number']) ) {
	    		return ['status' => 2 ,'message' => '缺少损耗单数据！'];
	    	}
	    	// 损耗单单号
	    	$loss_order_number = $param['loss_order_number'];
	    	// 其他超损入库条件
	    	$stock_where = [
				'is_other' 		=> ['eq',1],
				'loss_type' 	=> ['eq',1],
				'source_number' => ['eq',$loss_order_number],
			];
			$stock_arr = $this->getModel('ErpStockIn')->where($stock_where)->find();
			if ( !isset($stock_arr['id']) ) {
				return ['status' => 4, 'message' => '缺少其他入库单数据'];
			}
			/* ------------- 获取下面所需要的数据 ------------ */
			$storehouse_arr = $this->getModel("ErpStorehouse")->where(['id' => ['eq',$stock_arr['storehouse_id']]])->getField('id,storehouse_name');
			$region_arr     = $this->getModel("Area")->where(['id' => ['eq',$stock_arr['region']]])->getField('id,area_name');
			$good_list      = $this->getModel("ErpGoods")->where(['id'=>['eq',$stock_arr['goods_id']]])->field('id,goods_code,goods_name,source_from,grade,level')->find();
	        $goods_name[$good_list['id']] = $good_list['goods_code']."/".$good_list['goods_name']."/".$good_list['source_from']
	                ."/".$good_list['grade']."/".$good_list['level'];
	        $stock_arr['storehouse_name'] = isset($storehouse_arr[$stock_arr['storehouse_id']]) ? $storehouse_arr[$stock_arr['storehouse_id']] : '--';
	        $stock_arr['region_name']     = isset($region_arr[$stock_arr['region']]) ? $region_arr[$stock_arr['region']] : '--';
	        $stock_arr['goods_name']      = isset($goods_name[$stock_arr['goods_id']]) ? $goods_name[$stock_arr['goods_id']] : '--';
	        $stock_arr['storage_num']     = getNum($stock_arr['storage_num']);
	        $stock_arr['price']			  = getNum($stock_arr['price']);
	        $stock_arr['all_price']       = round($stock_arr['storage_num']  * $stock_arr['price'],2);
	        return ['status' => 1,'message' => '成功！','data' => $stock_arr];
	    }

	    /*
		* @ Content 运费单编辑
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function editfreightOrder( $param ){
	    	if ( !isset($param['freight_order_id']) || empty($param['freight_order_id']) ) {
	    		return ['status' => 11, 'message' => "缺少运费单ID！"];
	    	}
	    	if ( !isset($param['carrier_company_name']) || empty($param['carrier_company_name']) ) {
	    		return ['status' => 12, 'message' => "缺少运营商名称！"];
	    	}
	    	if ( !isset($param['transport_amount']) || empty($param['transport_amount']) ) {
	    		return ['status' => 13, 'message' => "缺少抵扣金额！"];
	    	}
	    	$where = ['id'=>['eq',$param['freight_order_id']]];
	    	$freight_order_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
	    	if ( !isset($freight_order_arr['order_status']) ) {
	    		return ['status' => 14, 'message' => '数据异常！'];
	    	}
	    	if ( $freight_order_arr['order_status'] != 1 ) {
	    		return ['status' => 15,'message' => '只有未审核状态 才可编辑！'];
	    	}
	    	$save_data = [
	    		'carrier_company_name' => trim($param['carrier_company_name']),
	    		'transport_amount'     => setNum(trim($param['transport_amount'])),
	    		'update_time'		   => nowTime(),
	    	];
	    	$save_result = $this->getModel('ErpFreightOrder')->where($where)->save($save_data);
	    	if ( !$save_result ) {
	    		return ['status' => 2,'message' => '编辑失败！'];
	    	}
	    	return ['status' => 1,'message' => '编辑成功！'];
	    }

	    /*
		* @ Content 运费单审核前验证
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function freightAuditChecking( $param ){
	    	if ( !isset($param['freight_id']) || empty($param['freight_id']) ) {
	    		return ['status' => 11 ,'message' => '缺少运费单ID'];
	    	}
	    	$where = [
	    		'id' => ['eq',trim($param['freight_id'])],
	    	];
	    	$freight_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
	    	if ( $freight_arr['order_status'] != 1 ) {
	    		return ['status' => 12,'message' => '只允许在未审核状态下审核！'];
	    	}
	    	if ( empty($freight_arr['carrier_company_name']) || empty($freight_arr['transport_amount']) ) {
	    		return ['status' => 13,'message' => '缺少必填信息！'];
	    	}
	    	return ['status' => 1,'message' => '可以审核'];
	    }

	   /*
		* @ Content 运费单审核
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function freightAudit( $param ){
	    	$return_data = $this->freightAuditChecking($param);
	    	if ( $return_data['status'] != 1 ) {
	    		return $return_data;
	    	}
	    	$where = [
	    		'id' => ['eq',trim($param['freight_id'])],
	    	];
	    	$save_data = [
	    		'order_status' => 3,
	    	];
	    	$save_result = $this->getModel('ErpFreightOrder')->where($where)->save($save_data);
	    	if ( !$save_result ) {
	    		return ['status' => 14,'message' => '审核失败！'];
	    	}
	    	return ['status' => 1,'message' => '审核成功！'];
	    }


	    /*
		* @ Content 运费单取消
		* @ Author  YF
		* @ Time    2019-05-07
		*/
	    public function freightDelete( $param ){
	    	if ( !isset($param['freight_id']) || empty($param['freight_id']) ) {
	    		return ['status' => 11 ,'message' => '缺少运费单ID'];
	    	}
	    	$where = [
	    		'id' => ['eq',trim($param['freight_id'])],
	    	];
	    	$freight_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
	    	if ( $freight_arr['order_status'] != 1 ) {
	    		return ['status' => 12,'message' => '只允许在未审核状态下取消！'];
	    	}
	    	M()->startTrans();
	    	$loss_where = [
	    		'order_number' => ['eq',$freight_arr['source_number']]
	    	];
	    	$save_loss_result = $this->getModel('ErpLossOrder')->where($loss_where)->save(['exceed_status' => 1]);
	    	if ( !$save_loss_result ) {
	    		M()->rollback();
	    		return ['status' => 15,'message' => "损耗单超损状态更新失败！"];
	    	}
	    	$save_data = [
	    		'order_status' => 2,
	    	];
	    	$save_result = $this->getModel('ErpFreightOrder')->where($where)->save($save_data);
	    	if ( !$save_result ) {
	    		M()->rollback();
	    		return ['status' => 14,'message' => '取消失败！'];
	    	}
	    	M()->commit();
	    	return ['status' => 1,'message' => '取消成功！'];
	    }

    /**
     * 运费单确认
     * @author xiaowen
     * @time 2019-5-17
     * @param $param
     * @return array
     */
    public function freightConfirm( $param ){

        if(getCacheLock('ErpFreight/freightConfirm'))  return ['status' => 99, 'message' => $this->running_msg];

        if ( !isset($param['freight_id']) || empty($param['freight_id']) ) {
            return ['status' => 11 ,'message' => '缺少运费单ID'];
        }
        $where = [
            'id' => ['eq',trim($param['freight_id'])],
        ];
        $freight_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
        if ( $freight_arr['order_status'] != 3 ) {
            return ['status' => 12,'message' => '只允许在已审核状态才能确认！'];
        }
        $loss_order_info = $this->getModel('ErpLossOrder')->field(true)->where(['order_number'=>$freight_arr['source_number']])->find();
        M()->startTrans();
        setCacheLock('ErpFreight/freightConfirm', 1);
        //更新运费单
        $save_data = [
            'order_status' => 10,
            'update_time' => DateTime(),
        ];
        $save_result = $this->getModel('ErpFreightOrder')->where($where)->save($save_data);

        //更新损耗单
        $update_loss_order = [
            'exceed_status' =>10,
            'update_time' => DateTime(),
        ];
        $update_loss_order_status = $this->getModel('ErpLossOrder')->where(['order_number'=>$freight_arr['source_number']])->save($update_loss_order);
        //查出损耗单生成的合理损耗 其他出库单
        $reasonable_stock_out = $this->getModel('ErpStockOut')->where(['source_number'=>$loss_order_info['order_number'], 'is_other'=>1, 'loss_type'=>2])->find();
        // echo "<pre>";
        // print_r($reasonable_stock_out);die;
        //将合理损耗 其他出库单赋值给 超损出库单
        $exceed_stock_out = $reasonable_stock_out;
        /* -------------------------
			YF更改 （可能存在 合理损耗为0不生成其他出库单）
        */
        if ( empty($reasonable_stock_out) ) {
        	if ($loss_order_info['type'] == 1) {
	            $where = [
	                'storage_code' => $loss_order_info['source_number']
	            ];
	            $source_order = $this->getModel('ErpStockIn')->where($where)->find();
	            $source_number = $source_order['storage_code'];
	        }
	        if ($loss_order_info['type'] == 2) {
	            $where = [
	                'storage_code' => $loss_order_info['source_number']
	            ];
	            $source_order = $this->getModel('ErpStockIn')->where($where)->find();
	            $source_number = $source_order['storage_code'];
	        }
	        if ($loss_order_info['type'] == 3) {
	            $where = [
	                'outbound_code' => $loss_order_info['source_number']
	            ];
	            $source_order = $this->getModel('ErpStockOut')->alias('so')
	                ->field('so.*,b.cargo_bn_id')
	                ->where($where)
	                ->join('oil_erp_batch b on b.id = so.batch_id','left')
	                ->find();
	            $source_number = $source_order['outbound_code'];
	        }
	        $density = $source_order['outbound_density'];
        	$exceed_stock_out['goods_id']         = $loss_order_info['goods_id'];
        	$exceed_stock_out['outbound_type']    = 6;
        	$exceed_stock_out['outbound_status']  = 10;
        	$exceed_stock_out['source_number']    = $loss_order_info['order_number'];
        	$exceed_stock_out['source_object_id'] = $loss_order_info['id'];
        	$exceed_stock_out['our_company_id']   = $loss_order_info['our_company_id'];
        	$exceed_stock_out['outbound_density'] = $density;
        	$exceed_stock_out['dealer_name'] 	  = $this->getUserInfo('dealer_name');
        	$exceed_stock_out['region']           = $source_order['region'];
        	$exceed_stock_out['batch_sys_bn']     = $source_order['batch_sys_bn'];
        	$exceed_stock_out['batch_id']         = $source_order['batch_id'];
        	$exceed_stock_out['parent_source_number'] = $source_number;
        }

        //查询损耗仓信息
        $storehouse_info = $this->getModel('ErpStorehouse')->where(['region'=>$loss_order_info['region'], 'type'=>8, 'status'=>1])->find();

        unset($exceed_stock_out['id']);

        //重置超损出库单数据
        $exceed_stock_out['outbound_code'] = erpCodeNumber(7)['order_number'];
        $exceed_stock_out['creater_id'] = $this->getUserInfo('id');
        $exceed_stock_out['auditor_id'] = $this->getUserInfo('id');
        $exceed_stock_out['creater_name'] = $this->getUserInfo('dealer_name');
        $exceed_stock_out['storehouse_id'] = $storehouse_info['id'];
        $exceed_stock_out['stock_type'] = storehouseTypeToStockType($storehouse_info['type']);
        $exceed_stock_out['auditor_id'] = $this->getUserInfo('id');
        $exceed_stock_out['audit_time'] =  currentTime();
        $exceed_stock_out['is_other'] =  1;
        $exceed_stock_out['loss_type'] =  1;
        $exceed_stock_out['outbound_num'] =  $loss_order_info['exceed_loss_num'];
        $exceed_stock_out['actual_outbound_num'] =  $loss_order_info['exceed_loss_num'];
        $exceed_stock_out['audit_time'] = DateTime();
        $exceed_stock_out['create_time'] = DateTime();
        $exceed_stock_out['source_freight_order'] = $freight_arr['order_number']; //超损运费单生成的其他出库，存来源运费单号
        $exceed_stock_out['cost'] = $loss_order_info['type'] == 3 ? 0 : getStockOutCost($exceed_stock_out)['price']; //销售超损出库单 成本 0 ，采购、调拨取当前成本
        //生成超损出库单
        $exceed_stock_out_create = $this->getModel('ErpStockOut')->addStockOut($exceed_stock_out);

        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $exceed_stock_out['goods_id'],
            'object_id' => $exceed_stock_out['storehouse_id'],
            'stock_type' => $exceed_stock_out['stock_type'],
            'region' => $exceed_stock_out['region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        //------------------组装库存表的字段值--------------------------
        if($exceed_stock_out['stock_type'] == 4){
            $facilitator_id = $storehouse_info['company_id'];
        }else {
            $facilitator_id = 0;
        }
        $data = [
            'goods_id' => $exceed_stock_out['goods_id'],
            'object_id' => $exceed_stock_out['storehouse_id'],
            'stock_type' => $exceed_stock_out['stock_type'],
            'facilitator_id' => $facilitator_id,
            'region' => $exceed_stock_out['region'],
            'stock_num' => $stock_info['stock_num'] - $exceed_stock_out['actual_outbound_num'],
        ];

        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $exceed_stock_out['outbound_code'],
            'object_type' => 3,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $exceed_out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $exceed_stock_out['actual_outbound_num'], $orders);
        if ( $save_result && $update_loss_order_status && $exceed_stock_out_create && $exceed_out_stock_status ) {
            M()->commit();
            $result = ['status' => 1,'message' => '运费单确认成功'];
        }else {
            M()->rollback();
            $result = ['status' => 0,'message' => '运费单确认失败'];
        }
        cancelCacheLock('ErpFreight/freightConfirm');
        return $result;

    }

    /**
     * 运费单红冲
     * @author xiaowen
     * @time 2019-5-17
     * @param $param
     * @return array
     */
    public function freightReverse( $param )
    {
        if(getCacheLock('ErpFreight/freightReverse'))  return ['status' => 99, 'message' => $this->running_msg];

        if ( !isset($param['freight_id']) || empty($param['freight_id']) ) {
            return ['status' => 11 ,'message' => '缺少运费单ID'];
        }
        $where = [
            'id' => ['eq',trim($param['freight_id'])],
        ];
        $freight_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
        if ( $freight_arr['order_status'] != 10 ) {
            return ['status' => 12,'message' => '只允许在已确认状态才能红冲'];
        }
        $loss_order_info = $this->getModel('ErpLossOrder')->field(true)->where(['order_number'=>$freight_arr['source_number']])->find();
        M()->startTrans();
        setCacheLock('ErpFreight/freightReverse', 1);
        //更新运费单
        $save_data = [
            'order_status' => 2,
            'reversed_status' => 1,
            'update_time' => DateTime(),
        ];
        $save_result = $this->getModel('ErpFreightOrder')->where($where)->save($save_data);

        //更新损耗单
        $update_loss_order = [
            'exceed_status' =>1,
            'update_time' => DateTime(),
        ];
        $update_loss_order_status = $this->getModel('ErpLossOrder')->where(['order_number'=>$freight_arr['source_number']])->save($update_loss_order);
        //查出损耗单生成的超损 其他出库单
        $exceed_stock_out = $this->getModel('ErpStockOut')->where(['source_number'=>$loss_order_info['order_number'], 'is_other'=>1, 'loss_type'=>1])->find();

        //将合理损耗 其他出库单赋值给 超损出库单
        $exceed_stock_out_reverse = $exceed_stock_out;

        unset($exceed_stock_out_reverse['id']);

        //构造红冲超损出库单数据
        $exceed_stock_out_reverse['outbound_code'] = erpCodeNumber(7)['order_number'];

        $exceed_stock_out_reverse['creater_id'] = $this->getUserInfo('id');
        $exceed_stock_out_reverse['creater_name'] = $this->getUserInfo('dealer_name');
        $exceed_stock_out_reverse['create_time'] =  currentTime();
        $exceed_stock_out_reverse['auditor_id'] = $this->getUserInfo('id');
        $exceed_stock_out_reverse['audit_time'] =  currentTime();
        $exceed_stock_out_reverse['outbound_num'] = plusConvert($exceed_stock_out['outbound_num']);
        $exceed_stock_out_reverse['actual_outbound_num'] = plusConvert($exceed_stock_out['actual_outbound_num']);
        $exceed_stock_out_reverse['is_reverse'] =  1;
        $exceed_stock_out_reverse['reverse_source'] =  $exceed_stock_out['outbound_code'];

        //生成红冲超损出库单
        $exceed_stock_out_reverse_create = $this->getModel('ErpStockOut')->addStockOut($exceed_stock_out_reverse);

        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
        $stock_where = [
            'goods_id' => $exceed_stock_out_reverse['goods_id'],
            'object_id' => $exceed_stock_out_reverse['storehouse_id'],
            'stock_type' => $exceed_stock_out_reverse['stock_type'],
            'region' => $exceed_stock_out_reverse['region'],
        ];
        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
        $beforeNum = $stock_info['stock_num'];
        $stockId = $stock_info['id'];
        //------------------组装库存表的字段值--------------------------
        if($exceed_stock_out['stock_type'] == 4){
            $facilitator_id = $stock_info['facilitator_id'];
        } else {
            $facilitator_id = 0;
        }
        $data = [
            'goods_id' => $exceed_stock_out_reverse['goods_id'],
            'object_id' => $exceed_stock_out_reverse['storehouse_id'],
            'stock_type' => $exceed_stock_out_reverse['stock_type'],
            'facilitator_id' => $facilitator_id,
            'region' => $exceed_stock_out_reverse['region'],
            'stock_num' => $stock_info['stock_num'] - $exceed_stock_out_reverse['actual_outbound_num'],
        ];

        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
        //------------------计算出新的可用库存----------------------------
        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
        //----------------------------------------------------------------
        $orders = [
            'object_number' => $exceed_stock_out_reverse['outbound_code'],
            'object_type' => 3,
            'log_type' => 2,
        ];
        //----------------更新库存，并保存库存日志-------------------------
        $exceed_out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $exceed_stock_out_reverse['actual_outbound_num'], $orders);



        if ($save_result && $update_loss_order_status && $exceed_stock_out_reverse_create && $exceed_out_stock_status) {
            M()->commit();
            //采购、调拨运费单红冲，超损其他出库，要重新计算加权成本 edit xiaowen 2019-5-31
            $exceed_stock_out_reverse['before_stock_num'] = $beforeNum;
            $exceed_stock_out_reverse['stock_id'] = $stockId ? $stockId : 0;
            $exceed_stock_out_reverse['change_num'] = abs($exceed_stock_out_reverse['actual_outbound_num']);
            updateStockInCost($exceed_stock_out_reverse);
            $result = ['status' => 1,'message' => '运费单红冲成功'];
        } else {
            M()->rollback();
            $message = '运费单红冲失败';
            $result = ['status' => 0,'message' => $message];
        }
        cancelCacheLock('ErpFreight/freightReverse');

        return $result;
    }
}