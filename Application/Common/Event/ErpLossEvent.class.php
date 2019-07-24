<?php
namespace Common\Event;

use Home\Controller\BaseController;
use Think\Page;

class ErpLossEvent extends BaseController
{
   /*
	* @ Content 获取损耗订单
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function lossOrderList( $param ){
		$where = $this->lossOrderWhere($param);
		if ( isset($where['message']) ) {
			return ['data' => [],'recordsFiltered' => 0,'recordsTotal'=> 0,'draw' => $param['draw']];
		}
		/* --------——— 查询数据 ------------- */
		$field  = "*";
		$offset = $param['start'];
		$limit  = $param['length'];
		$loss_order_arr = $this->getModel("ErpLossOrder")->where($where)->field($field)->limit($offset, $limit)->order('id desc')->select();
		$result_arr = $this->handleLossOrderArr($loss_order_arr);
		if ( empty($result_arr) ) {
			return ['data' => [],'recordsFiltered' => 0,'recordsTotal'=> 0,'draw' => $param['draw']];
		}
		$loss_count = $this->getModel("ErpLossOrder")->where($where)->field('id')->count();
		// key 值重置
		$result_arr = array_merge($result_arr);
		// 只有来源是列表 才统计数量
		if ( isset($param['draw']) ) {
			$all_loss_arr = $this->getModel("ErpLossOrder")->where($where)
							 ->field('reasonable_loss_num,exceed_loss_num,loss_num,price')
							 ->select();
			$sum_total = [];
			// 计算页数总和 及 所有总和
			$sum_total['pageAll_reasonable_loss_num'] 	= array_sum(array_column($result_arr,'reasonable_loss_num'));
			$sum_total['pageAll_exceed_loss_num'] 	  	= array_sum(array_column($result_arr,'exceed_loss_num'));
			$sum_total['pageAll_loss_num'] 	  		  	= array_sum(array_column($result_arr,'loss_num'));
			$sum_total['pageAll_reasonable_loss_price'] = array_sum(array_column($result_arr,'reasonable_loss_price'));
			$sum_total['pageAll_exceed_loss_price'] 	= array_sum(array_column($result_arr,'exceed_loss_price'));
			$sum_total['all_reasonable_loss_num'] 		= getNum(array_sum(array_column($all_loss_arr,'reasonable_loss_num')));
			$sum_total['all_exceed_loss_num'] 	  		= getNum(array_sum(array_column($all_loss_arr,'exceed_loss_num')));
			$sum_total['all_loss_num'] 	  		  		= getNum(array_sum(array_column($all_loss_arr,'loss_num')));
			$all_reasonable_loss_price = 0;
			$all_exceed_loss_price     = 0;
			foreach ($all_loss_arr as $key => $value) {
				$all_reasonable_loss_price += getNum($value['reasonable_loss_num']) * getNum($value['price']);
				$all_exceed_loss_price     += getNum($value['exceed_loss_num']) * getNum($value['price']);
			}
			$sum_total['all_reasonable_loss_price']   	= round($all_reasonable_loss_price,2);
			$sum_total['all_exceed_loss_price'] 	  	= round($all_exceed_loss_price,2);
			$result_arr[0]['sum_total'] 				= $sum_total;
		}
		return [
			'recordsFiltered' => $loss_count,
			'recordsTotal'    => $loss_count,
			'data' 			  => $result_arr,
			'draw' 			  => $param['draw'],
		];
		
	}


   /*
	* @ Content 组装查询条件
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function lossOrderWhere( $param ){
		$where = [];
		// 账套区分
		$where['our_company_id'] = ['eq',session('erp_company_id')];
		// 单据编号
		if ( isset($param['order_number']) && !empty($param['order_number']) ) {
			$where['order_number'] = ['like', ['%' . trim($param['order_number']) . '%']];
		}

		// 损耗单状态
		if ( isset($param['order_status']) && !empty($param['order_status']) ) {
			$where['order_status'] = ['eq',trim($param['order_status'])];
		}

		// 超损承担方
		if ( isset($param['responsible_party']) && $param['responsible_party'] != '' ) {
			$where['responsible_party'] = ['eq',trim($param['responsible_party'])];
		}

		$stock_arr = [];
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
    	}

		// 来源单号
		if ( isset($param['source_number']) && !empty($param['source_number']) && empty($stock_arr) ) {
			$where['source_number'] = ['like', ['%' . trim($param['source_number']) . '%']];
		}else if ( isset($param['source_number']) && !empty($param['source_number']) && !empty($stock_arr) ) {
			if ( !in_array(trim($param['source_number']),$stock_arr)) {
    			return ['status' => 5,'message' => '未查询到所对应的数据'];
    		}
    		$where['source_number'] = ['eq',trim($param['source_number'])];
		} elseif ( empty($param['source_number']) && !empty($stock_arr) ) {
			$where['source_number'] = ['in',$stock_arr];
		}

		// 是否红冲
		if ( isset($param['reversed_status']) && !empty($param['reversed_status']) ) {
			$where['reversed_status'] = ['eq',trim($param['reversed_status'])];
		}

		// 公司来源
		if ( (isset($param['company_name']) && !empty($param['company_name'])) && isset($param['source_type']) && !empty($param['source_type']) ) {
			$where['type'] = ['eq',trim($param['source_type'])];
			if ( $param['source_type'] == 1 || $param['source_type'] == 2 ) {
				$table = 'ErpSupplier';
				$where_name = 'supplier_name';			
			}elseif ($param['source_type'] == 3) {
				$table = 'ErpCustomer';
				$where_name = 'customer_name';
			}
			$company_where[$where_name] = ['like', ['%' . trim($param['company_name']) . '%']];
			$company_arr   = $this->getModel($table)->where($company_where)->field('id')->select();
			if ( !empty($company_arr) ) {
				$where['company_id'] = ['in',array_column($company_arr, 'id')];
			}else{
				$where['company_id'] = ['eq',0];
			}
		// 损耗来源
		}elseif ( isset($param['source_type']) && !empty($param['source_type']) ) {
			$where['type'] = ['eq',trim($param['source_type'])];
		}

		/* ----------------- 处理创建时间 -------------------- */
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
	* @ Content 组装查询条件
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function handleLossOrderArr( $loss_order_arr ) {
		if ( empty($loss_order_arr) ) {
			return [];
		}
		/* ------------- 获取下面所需要的数据 ------------ */
		$storehouse_id = array_unique(array_column($loss_order_arr, 'storehouse_id'));
		$goods_id      = array_unique(array_column($loss_order_arr, 'goods_id'));
		$region        = array_unique(array_column($loss_order_arr, 'region'));
		$storehouse_arr = $this->getModel("ErpStorehouse")->where(['id' => ['in',$storehouse_id]])->getField('id,storehouse_name');
		$region_arr     = $this->getModel("Area")->where(['id' => ['in',$region]])->getField('id,area_name');
		$good_list      = $this->getModel("ErpGoods")->where(['id'=>['in',$goods_id]])->field('id,goods_code,goods_name,source_from,grade,level')->select();
        foreach ($good_list as $value){
            $good_list[$value['id']] = $value['goods_code']."/".$value['goods_name']."/".$value['source_from']
                ."/".$value['grade']."/".$value['level'];
        }
        /* ------------------ end ------------------- */
        /* ----------- 查询运费单数据 (此处和运费单列表是面向过程开发)------------------*/
    	$purchase_order = [];
    	$allocation_order = [];
    	$sale_order = [];
    	foreach ($loss_order_arr as $key => $value) {
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
		// 采购损耗单
		$purchase_loss_order = [];
		// 销售损耗单
		$sale_loss_order = [];
		$result_arr = [];
		foreach ($loss_order_arr as $key => $value) {
			// 运费单单号
			$value['shipping_order_number']     = $shipping_arr[$value['order_number']]; 
			// 处理公共数据
			$value['storehouse_name'] 			= isset($storehouse_arr[$value['storehouse_id']]) ? $storehouse_arr[$value['storehouse_id']] : '--';
			$value['region_name']     			= isset($region_arr[$value['region']]) ? $region_arr[$value['region']] : '--';
			$value['goods_name']      			= isset($good_list[$value['goods_id']]) ? $good_list[$value['goods_id']] : '--';
			// 含税单价
			$value['price'] 					= getNum($value['price']);
			// 合理损耗
			$value['reasonable_loss_num'] 		= getNum($value['reasonable_loss_num']);
			$value['exceed_loss_num']     		= getNum($value['exceed_loss_num']);
			$value['loss_num']            		= getNum($value['loss_num']);
			// 合理损耗金额
			$value['reasonable_loss_price'] 	= round($value['reasonable_loss_num'] * $value['price'],2);
			// 超损金额	
			$value['exceed_loss_price'] 		= round($value['exceed_loss_num'] * $value['price'],2);
			$value['loss_num_price'] 			= round($value['loss_num'] * $value['price'],2);
			// 承担方
			$value['responsible_party_name'] 	= lossResponsiblePartyStatus((int)$value['responsible_party']);
			$value['type_name']   				= lossTypeStatus($value['type'],true);
			$value['order_status_name']      	= lossOrderStatus($value['order_status'],true);
			$value['reasonable_status_name'] 	= lossReasonableStatus($value['reasonable_status'],true);
			$value['exceed_status_name']     	= lossExceedStatus($value['exceed_status'],true);
			if ( $value['loss_ratio'] != 0 && $value['loss_ratio'] != '' ) {
				$value['loss_ratio']			= lossRatio($value['loss_ratio']);
			}
			//红冲状态
			if ( $value['reversed_status'] == 1 ) {
				$value['reversed_status_name'] 	= '已红冲';
			} else {
				$value['reversed_status_name'] 	= '未红冲';
			}
			// 采购损耗 // 调拨损耗
			if ( $value['type'] == 1 || $value['type'] == 2 ) {
				$purchase_loss_order[$key] 	= $value;
			
			// 销售损耗
			} elseif ( $value['type'] == 3 ) {
				$sale_loss_order[$key] 		= $value;
			}
		}
		
		/*------------- 处理采购损耗单数据 ------------- */
		if ( !empty($purchase_loss_order) ) {
			$company_id    = array_unique(array_column($purchase_loss_order, 'company_id'));
			$source_number = array_column($purchase_loss_order, 'source_number');
			$supper_arr    = $this->getModel('ErpSupplier')->where(['id'=>['in',$company_id]])->getField('id,supplier_name');
			// 处理批次货权信息
			$stock_in_arr  = $this->getModel('ErpStockIn')->where(['storage_code'=>['in',$source_number]])->field('storage_code,cargo_bn_id,batch_id')->select();
			$cargo_bn_id   = array_unique(array_column($stock_in_arr, 'cargo_bn_id'));
			$batch_id      = array_unique(array_column($stock_in_arr, 'batch_id'));
			$batch_arr     = $this->getModel('ErpBatch')->where(['id' => ['in',$batch_id]])->getField('id,sys_bn');
			$cargo_bn_arr  = $this->getModel('ErpCargoBn')->where(['id' => ['in',$cargo_bn_id]])->getField('id,cargo_bn');	
			foreach ($stock_in_arr as $key => $value) {
				$stock_in_arr[$value['storage_code']]['cargo_bn'] = isset($cargo_bn_arr[$value['cargo_bn_id']]) ? $cargo_bn_arr[$value['cargo_bn_id']] : '--';
				$stock_in_arr[$value['storage_code']]['sys_bn']   = isset($batch_arr[$value['batch_id']]) ? $batch_arr[$value['batch_id']] : '--';
			}
			foreach ($purchase_loss_order as $key => $value) {
				$loss_order_arr[$key] 				  = $value;
				$loss_order_arr[$key]['sys_bn']       = $stock_in_arr[$value['source_number']]['sys_bn'];
				$loss_order_arr[$key]['cargo_bn']     = $stock_in_arr[$value['source_number']]['cargo_bn'];
				$loss_order_arr[$key]['company_name'] = isset($supper_arr[$value['company_id']])? $supper_arr[$value['company_id']] : '--';
			}
		}
		/*-------------- 处理销售损耗单数据 ------------- */
		if ( !empty($sale_loss_order) ) {
			$company_id       = array_unique(array_column($sale_loss_order, 'company_id'));
			$customer_arr = $this->getModel('ErpCustomer')->where(['id'=>['in',$company_id]])->getField('id,customer_name');
			// 处理货权批次信息
			$stock_out_number = array_column($sale_loss_order,'source_number');
			$sale_batch_arr   = $this->getModel('ErpStockOut')->alias('stock')
			->join('oil_erp_batch batch on batch.id = stock.batch_id', 'left')
			->join('oil_erp_cargo_bn cargo on cargo.id = batch.cargo_bn_id', 'left')
			->where(['stock.outbound_code' => ['in',$stock_out_number]])->field('batch.id,batch.sys_bn,cargo.cargo_bn,stock.outbound_code')->select();
			foreach ($sale_batch_arr as $key => $value) {
				$sale_batch_arr[$value['outbound_code']]['sys_bn']   = !empty($value['sys_bn']) ? $value['sys_bn'] : '--';
				$sale_batch_arr[$value['outbound_code']]['cargo_bn'] = !empty($value['cargo_bn']) ? $value['cargo_bn'] : '--';
			}
			
			foreach ($sale_loss_order as $key => $value) {
				$loss_order_arr[$key] 					 = $value;
				$loss_order_arr[$key]['sys_bn']          = $sale_batch_arr[$value['source_number']]['sys_bn'];
				$loss_order_arr[$key]['cargo_bn']        = $sale_batch_arr[$value['source_number']]['cargo_bn'];
				$loss_order_arr[$key]['company_name']    = isset($customer_arr[$value['company_id']])? $customer_arr[$value['company_id']] : '--';
			}
		}

		return $loss_order_arr;

	}

	/*
	* @ Content 新增损耗单
	* @ Author  guanyu
	* @ Time    2019-05-10
	*/
    public function addLossOrder($param = [])
    {
        if (empty($param)) {
            return ['status' => 0, 'message' => '参数有误'];
        }
        foreach ($param as $value) {
            if ($value['source_number'] == '') {
                $result = [
                    'status' => 2,
                    'message' => '来源单号为空'
                ];
                return $result;
            }
            if ($value['type'] == '') {
                $result = [
                    'status' => 3,
                    'message' => '损耗类型为空'
                ];
                return $result;
            }
            if ($value['loss_ratio'] == '') {
                $result = [
                    'status' => 4,
                    'message' => '损耗比例为空'
                ];
                return $result;
            }
            if ($value['loss_num'] == '') {
                $result = [
                    'status' => 5,
                    'message' => '损耗数量为空'
                ];
                return $result;
            }
            if ($value['price'] == '') {
                $result = [
                    'status' => 6,
                    'message' => '含税单价为空'
                ];
                return $result;
            }
            if (!in_array($value['responsible_party'],[0,1,2])) {
                $result = [
                    'status' => 7,
                    'message' => '承担方为空'
                ];
                return $result;
            }
        }

        //--------------------------------------------------------------------------------------------------------
        if (getCacheLock('ErpLoss/addLossOrder')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpLoss/addLossOrder', 1);

        $loss_data = [];
        foreach ($param as $value) {
            if ($value['type'] == 1) {
                $source_info = $this->getModel('ErpStockIn')->where(['storage_code'=>$value['source_number']])->find();
                $field = 'sale_company_id as company_id,buyer_dealer_id as dealer_id,buyer_dealer_name as dealer_name';
                $source_order_info = $this->getModel('ErpPurchaseOrder')->field($field)->where(['order_number'=>$source_info['source_number']])->find();
            } else if ($value['type'] == 2) {
                $source_info = $this->getModel('ErpStockIn')->where(['storage_code'=>$value['source_number']])->find();
                $field = 'in_facilitator_id as company_id,dealer_id,dealer_name';
                $source_order_info = $this->getModel('ErpAllocationOrder')->field($field)->where(['order_number'=>$source_info['source_number']])->find();
            } else if ($value['type'] == 3) {
                $source_info = $this->getModel('ErpStockIn')->where(['storage_code'=>$value['source_number']])->find();
                $field = 'company_id,dealer_id,dealer_name';
                $source_order_info = $this->getModel('ErpSaleOrder')->field($field)->where(['order_number'=>$source_info['source_number']])->find();
            }

            $loss_data[] = [
                'order_number' => erpCodeNumber(23)['order_number'],
                'source_number' => $value['source_number'],
                'our_company_id' => session('erp_company_id'),
                'type' => $value['type'],
                'company_id' => $source_order_info['company_id'],
                'dealer_id' => $source_order_info['dealer_id'],
                'dealer_name' => $source_order_info['dealer_name'],
                'region' => $source_info['region'],
                'storehouse_id' => $source_info['storehouse_id'],
                'goods_id' => $source_info['goods_id'],
                'loss_ratio' => $value['loss_ratio'],
                'loss_num' => round($value['loss_num']),
                'reasonable_loss_num' => round($value['reasonable_loss_num']),
                'exceed_loss_num' => round($value['exceed_loss_num']),
                'price' => $value['price'],
                'responsible_party' => $value['responsible_party'],
                'order_status' => 1,
                'reasonable_status' => 1,
                'exceed_status' => 1,
                'reversed_status' => 2,
                'creater' => $this->getUserInfo('id'),
                'create_time' => currentTime(),
            ];
        }
        $loss_status = $this->getModel('ErpLossOrder')->addAll($loss_data);

        //======================================================================================================
        if ($loss_status) {
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }

        cancelCacheLock('ErpLoss/addLossOrder');

        return $result;
    }

   /*
	* @ Content 编辑损耗单
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function editLoss( $param ){
		if ( !isset($param['loss_id']) || empty($param['loss_id']) ) {
			return ['status' => 11 ,'message' => '请重新打开页面！'];
		}
		if ( !isset($param['responsible_partys']) || empty($param['responsible_partys'])) {
			return ['status' => 12,'message' => '请选择超损承担方！'];
		}

		$loss_status = $this->getModel('ErpLossOrder')->where(['id' => ['eq',trim($param['loss_id'])]])->field('id,order_status,exceed_loss_num')->find();
		if ( !isset($loss_status['order_status']) ) {
			return ['status' => 14,'message' => '损耗单有误！'];
		}
		if ( $loss_status['order_status'] != 1 ) {
			return ['status' => 15,'message' => '损耗单只有在未审核状态下才能编辑！'];
		}

		if ( (int)$loss_status['exceed_loss_num'] <= (int)0 ) {
			return ['status' => 17,'message' => '无超损数量，不允许编辑！'];
		}

		$save_data = [
			'responsible_party' => trim($param['responsible_partys']),
			'update_time'       => nowTime(),
		];
		$update_loss_status = $this->getModel('ErpLossOrder')->where(['id' => ['eq',$loss_status['id']]])->save($save_data);
		if ( !$update_loss_status ) {
			return ['status' => 16, 'message' => '修改超损承担方失败！'];
		}
		return ['status' => 1, 'message' => '编辑成功！'];
	}

	/*
	* @ Content 生成运费单前验证
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function checkAddFreightOrder( $param ){
		if ( !isset($param['loss_id']) || empty($param['loss_id']) ) {
			return ['status' => 11,'message' => '请重新打开页面！'];
		}
		$loss_where['id'] = ['eq',trim($param['loss_id'])];
		$loss_arr = $this->getModel("ErpLossOrder")->where($loss_where)->field('id,order_number,order_status,exceed_loss_num,responsible_party')->find();
		if ( !isset($loss_arr['id']) ) {
			return ['status' => 15 ,'message' => '数据异常！'];
		}
		if ( $loss_arr['order_status'] != 10 ) {
			return ['status' => 12 ,'message' => '状态必须是已确认状态！'];
		}
		if ( (int)$loss_arr['exceed_loss_num'] <= (int)0 ) {
			return ['status' => 14 ,'message' => '不存在超损数量！'];
		}
		if ( $loss_arr['responsible_party'] != 2 ) {
			return ['status' => 13,'message' => '承担方必须是他方承担！'];
		}
		// 判断是否已经生成了运费单
		$freight_where = [
			'source_number' => ['eq',$loss_arr['order_number']],
			'order_status'  => ['neq',2]
		];
		$freight_order_arr = $this->getModel('ErpFreightOrder')->where($freight_where)->field('id')->find();
		if ( isset($freight_order_arr['id']) ) {
			return ['status' => 16 ,'message' => '运费单已生成！'];
		}
		return ['status' => 1, 'message' => '允许生成运费单！'];
	}

   /*
	* @ Content 生成运费单
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function addFreightOrder( $param = [] ){
		if ( !isset($param['carrier_company_name']) || empty(trim($param['carrier_company_name'])) ) {
			return ['status' => 11, 'message' => '缺少供应商名称！'];
		}
		if ( !isset($param['transport_amount']) || empty($param['transport_amount']) ) {
			return ['status' => 12, 'message' => '缺少抵扣运费！'];
		}
		if ( !isset($param['loss_order_number']) || empty($param['loss_order_number']) ) {
			return ['status' => 13 ,'message' => '缺少损耗单单号！'];
		}
		$loss_arr = $this->getModel('ErpLossOrder')->where(['order_number' => ['eq',trim($param['loss_order_number'])]])->field('order_number,exceed_loss_num')->find();
		if ( !isset($loss_arr['order_number']) ) {
			return ['status' => 14 ,'message' => '数据异常，未找到损耗单！'];
		}
		if (getCacheLock('ErpLoss/addFreightOrder')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpLoss/addFreightOrder', 1);
        M()->startTrans();
        $save_result = $this->getModel('ErpLossOrder')->where(['order_number' => ['eq',trim($param['loss_order_number'])]])->save(['exceed_status' => 3]);
        if ( !$save_result ) {
        	M()->rollback();
        	cancelCacheLock('ErpLoss/addFreightOrder');
        	return ['status' => 16,'message' => '超损状态修改失败！'];
        }
		$add_freight_arr = [
			'order_number'   		=> erpCodeNumber(24)['order_number'],
			'source_number'  		=> $loss_arr['order_number'],
			'our_company_id' 		=> session('erp_company_id'),
			'carrier_company_name' 	=> trim($param['carrier_company_name']),
			'transport_num'  		=> $loss_arr['exceed_loss_num'],
			'transport_amount' 		=> setNum(trim($param['transport_amount'])),
			'order_status'   		=> 1,
			'creater'        		=> $this->getUserInfo('id'),
			'create_time'    		=> nowTime(),
		];
		$add_result = $this->getModel('ErpFreightOrder')->add($add_freight_arr);
		if ( !$add_result ) {
			M()->rollback();
			cancelCacheLock('ErpLoss/addFreightOrder');
			return ['status' => 15,'message' => '添加运费单失败！'];
		}
		cancelCacheLock('ErpLoss/addFreightOrder');
		M()->commit();
		return ['status' => 1, 'message' => '创建成功！'];

	}


   /*
	* @ Content 损耗单审核
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function lossAudit( $param ){
		if ( !isset($param['loss_id']) || empty($param['loss_id']) ) {
			return ['status' => 11 ,'message' => '请重新打开页面！'];
		}
		$loss_where['id'] = ['eq',trim($param['loss_id'])];
		$loss_arr = $this->getModel("ErpLossOrder")->where($loss_where)->field('id,order_status,exceed_loss_num,responsible_party')->find();
		if ( !isset($loss_arr['id']) ) {
			return ['status' => 15 ,'message' => '数据异常！'];
		}
		if ( $loss_arr['order_status'] != 1 ) {
			return ['status' => 12 ,'message' => '状态必须是未审核状态！'];
		}
		if ( (int)$loss_arr['exceed_loss_num'] > (int)0 && (int)$loss_arr['responsible_party'] == (int)0 ) {
			return ['status' => 14 ,'message' => '请先选择超损承担方！'];
		}
		$save_data = [
			"order_status" => 3,
		];
		$save_status = $this->getModel("ErpLossOrder")->where($loss_where)->save($save_data);

		if ( !$save_status ) {
			return ['status' => 15,'message' => '审核失败！'];
		}
		return ['status' => 1,'message' => '审核成功！'];
	}

    /*
    * @ Content 损耗单确认
    * @ Author  guanyu
    * @ Time    2019-05-13
    */
    public function lossConfirm( $param ){
        if ( !isset($param['loss_id']) || empty($param['loss_id']) ) {
            return ['status' => 11 ,'message' => '请重新打开页面！'];
        }
        $loss_where['id'] = ['eq',trim($param['loss_id'])];
        //损耗单
        $field = '*';
        $loss_arr = $this->getModel("ErpLossOrder")->where($loss_where)->field($field)->find();

        if ( !isset($loss_arr['id']) ) {
            return ['status' => 15 ,'message' => '数据异常！'];
        }
        if ( $loss_arr['order_status'] != 3 ) {
            return ['status' => 12 ,'message' => '状态必须是已审核状态！'];
        }

        $price = $loss_arr['price'];

        //来源单据
        if ($loss_arr['type'] == 1) {
            $where = [
                'storage_code' => $loss_arr['source_number']
            ];
            $source_order = $this->getModel('ErpStockIn')->where($where)->find();
            $source_number = $source_order['storage_code'];
        }
        if ($loss_arr['type'] == 2) {
            $where = [
                'storage_code' => $loss_arr['source_number']
            ];
            $source_order = $this->getModel('ErpStockIn')->where($where)->find();
            $source_number = $source_order['storage_code'];
        }
        if ($loss_arr['type'] == 3) {
            $where = [
                'outbound_code' => $loss_arr['source_number']
            ];
            $source_order = $this->getModel('ErpStockOut')->alias('so')
                ->field('so.*,b.cargo_bn_id')
                ->where($where)
                ->join('oil_erp_batch b on b.id = so.batch_id','left')
                ->find();
            $source_number = $source_order['outbound_code'];
        }
        if ($source_order['stock_type'] == 4) {
            $facilitator_id = $this->getModel('ErpStorehouse')->where(['id'=>$source_order['storehouse_id']])->find()['company_id'];
        } else {
            $facilitator_id = 0;
        }

        if(getCacheLock('ErpLoss/lossConfirm'))  return ['status'=>9999,'message'=>$this->running_msg];
        setCacheLock('ErpLoss/lossConfirm', 1);

        M()->startTrans();

        /*------------生成其他合理出，其他合理入，一出一入不影响库存，但是要留库存变动日志和变动明细------------------*/
        // 合理损耗 = 0 不生成 合理入\合理出
        $density = $source_order['outbound_density'];
        $reasonable_out_stock_status = true;
        $reasonable_in_stock_status = true;
        $reasonable_stock_in_status = true;
        $reasonable_stock_out_status = true;
        // 合理损耗处理状态
        $reasonable_status = 2;
        if ( $loss_arr['reasonable_loss_num'] > 0 ) {
        	$reasonable_status = 10;
        	//生成其他合理入库单
	        $itre_num = tonToLiter($loss_arr['reasonable_loss_num'],$source_order['density']);
	        $reasonable_stock_in_data = [
	            'storage_type'              => 6,
	            'storage_code'              => erpCodeNumber(8)['order_number'],
	            'storage_status'            => 10,
	            'source_number'             => $loss_arr['order_number'],
	            'source_object_id'          => $loss_arr['id'],
	            'our_company_id'            => $loss_arr['our_company_id'],
	            'goods_id'                  => $loss_arr['goods_id'],
	            'storage_num'               => $loss_arr['reasonable_loss_num'],
	            'actual_storage_num'        => $loss_arr['reasonable_loss_num'],
	            'outbound_density'          => $density,
	            'creater_id'                => $this->getUserInfo('id'),
	            'create_time'               => currentTime(),
	            'dealer_id'                 => $this->getUserInfo('id'),
	            'dealer_name'               => $this->getUserInfo('dealer_name'),
	            'auditor_id'                => $this->getUserInfo('id'),
	            'audit_time'                => currentTime(),
	            'storage_remark'            => trim($param['storage_remark']),
	            'storehouse_id'             => $loss_arr['storehouse_id'],
	            'stock_type'                => $source_order['stock_type'],
	            'region'                    => $source_order['region'],
	            'price'                     => $price,
	            'actual_storage_num_litre'  => $itre_num,
	            'balance_num'               => $loss_arr['reasonable_loss_num'],
	            'balance_num_litre'         => $itre_num,
	            'batch_sys_bn'              => $source_order['batch_sys_bn'],
	            'batch_id'                  => $source_order['batch_id'],
	            'cargo_bn_id'               => $source_order['cargo_bn_id'],
	            'is_other'                  => 1,
	            'loss_type'                 => 2,
	            'parent_source_number'      => $source_number,
	        ];
	        $reasonable_stock_in_status = $this->getModel('ErpStockIn')->addStockIn($reasonable_stock_in_data);

	        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
	        $stock_where = [
	            'goods_id' => $reasonable_stock_in_data['goods_id'],
	            'object_id' => $reasonable_stock_in_data['storehouse_id'],
	            'stock_type' => $reasonable_stock_in_data['stock_type'],
	            'region' => $reasonable_stock_in_data['region'],
	        ];
	        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
	        //------------------组装库存表的字段值--------------------------
	        $data = [
	            'goods_id' => $reasonable_stock_in_data['goods_id'],
	            'object_id' => $reasonable_stock_in_data['storehouse_id'],
	            'stock_type' => $reasonable_stock_in_data['stock_type'],
	            'facilitator_id' => $facilitator_id,
	            'region' => $reasonable_stock_in_data['region'],
	            'stock_num' => $stock_info['stock_num'] + $reasonable_stock_in_data['actual_storage_num'], //入库方的实际出库数量加到入库方的物理
	        ];

	        $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的预留库存
	        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
	        //------------------计算出新的可用库存----------------------------
	        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
	        //----------------------------------------------------------------
	        $orders = [
	            'object_number' => $reasonable_stock_in_data['storage_code'],
	            'object_type' => 4,
	            'log_type' => 2,
	        ];
	        //----------------更新库存，并保存库存日志-------------------------
	        $reasonable_in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $reasonable_stock_in_data['actual_storage_num'], $orders);

	        //生成其他合理出库单
	        $reasonable_stock_out_data = [
	            'outbound_type'         => 6,
	            'outbound_code'         => erpCodeNumber(7)['order_number'],
	            'outbound_status'       => 10,
	            'source_number'         => $loss_arr['order_number'],
	            'source_object_id'      => $loss_arr['id'],
	            'goods_id'              => $loss_arr['goods_id'],
	            'our_company_id'        => $loss_arr['our_company_id'],
	            'outbound_num'          => $loss_arr['reasonable_loss_num'],
	            'actual_outbound_num'   => $loss_arr['reasonable_loss_num'],
	            'deduction_num'         => $loss_arr['reasonable_loss_num'],
	            'outbound_density'      => $density,
	            'create_time'           => currentTime(),
	            'dealer_id'             => $this->getUserInfo('id'),
	            'dealer_name'           => $this->getUserInfo('dealer_name'),
	            'creater_id'            => $this->getUserInfo('id'),
	            'creater_name'          => $this->getUserInfo('dealer_name'),
	            'auditor_id'            => $this->getUserInfo('id'),
	            'audit_time'            => currentTime(),
	            'outbound_remark'       => trim($param['outbound_remark']),
	            'storehouse_id'         => $loss_arr['storehouse_id'],
	            'stock_type'            => $source_order['stock_type'],
	            'region'                => $source_order['region'],
	            'cost'                  => 0,//其他合理出的成本固定为0
	            'batch_sys_bn'          => $source_order['batch_sys_bn'],
	            'batch_id'              => $source_order['batch_id'],
	            'is_other'              => 1,
	            'loss_type'             => 2,
	            'parent_source_number'  => $source_number,
	        ];
	        $reasonable_stock_out_status = $this->getModel('ErpStockOut')->addStockOut($reasonable_stock_out_data);

	        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
	        $stock_where = [
	            'goods_id' => $reasonable_stock_out_data['goods_id'],
	            'object_id' => $reasonable_stock_out_data['storehouse_id'],
	            'stock_type' => $reasonable_stock_out_data['stock_type'],
	            'region' => $reasonable_stock_out_data['region'],
	        ];
	        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
	        //------------------组装库存表的字段值--------------------------
	        $data = [
	            'goods_id' => $reasonable_stock_out_data['goods_id'],
	            'object_id' => $reasonable_stock_out_data['storehouse_id'],
	            'stock_type' => $reasonable_stock_out_data['stock_type'],
	            'facilitator_id' => $facilitator_id,
	            'region' => $reasonable_stock_out_data['region'],
	            'stock_num' => $stock_info['stock_num'] - $reasonable_stock_out_data['actual_outbound_num'],
	        ];

	        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
	        //------------------计算出新的可用库存----------------------------
	        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
	        //----------------------------------------------------------------
	        $orders = [
	            'object_number' => $reasonable_stock_out_data['outbound_code'],
	            'object_type' => 3,
	            'log_type' => 12,
	        ];
	        //----------------更新库存，并保存库存日志-------------------------
	        $reasonable_out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $reasonable_stock_out_data['actual_outbound_num'], $orders);
        }
        

        if ($loss_arr['exceed_loss_num'] > 0) {
        	$itre_num = tonToLiter($loss_arr['exceed_loss_num'],$source_order['density']);
            //若有超损，则生成其他超损入（参与加权）
            $exceed_storehouse_id = $this->getModel('ErpStorehouse')->where(['region'=>$source_order['region'],'type'=>8])->find()['id'];
            $exceed_stock_in_data = [
                'storage_type'              => 6,
                'storage_code'              => erpCodeNumber(8)['order_number'],
                'storage_status'            => 10,
                'source_number'             => $loss_arr['order_number'],
                'source_object_id'          => $loss_arr['id'],
                'our_company_id'            => $loss_arr['our_company_id'],
                'goods_id'                  => $loss_arr['goods_id'],
                'storage_num'               => $loss_arr['exceed_loss_num'],
                'actual_storage_num'        => $loss_arr['exceed_loss_num'],
                'outbound_density'          => $density,
                'creater_id'                => $this->getUserInfo('id'),
                'create_time'               => currentTime(),
                'dealer_id'                 => $this->getUserInfo('id'),
                'dealer_name'               => $this->getUserInfo('dealer_name'),
                'auditor_id'                => $this->getUserInfo('id'),
                'audit_time'                => currentTime(),
                'storage_remark'            => trim($param['storage_remark']),
                'storehouse_id'             => $exceed_storehouse_id,
                'stock_type'                => 9,
                'region'                    => $source_order['region'],
                'price'                     => $price,
                'actual_storage_num_litre'  => $itre_num,
                'balance_num'               => $loss_arr['exceed_loss_num'],
                'balance_num_litre'         => $itre_num,
                'batch_sys_bn'              => $source_order['batch_sys_bn'],
                'batch_id'                  => $source_order['batch_id'],
                'cargo_bn_id'               => $source_order['cargo_bn_id'],
                'is_other'                  => 1,
                'loss_type'                 => 1,
                'parent_source_number'      => $source_number,
            ];
            $exceed_stock_in_status = $this->getModel('ErpStockIn')->addStockIn($exceed_stock_in_data);

            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $exceed_stock_in_data['goods_id'],
                'object_id' => $exceed_storehouse_id,
                'stock_type' => $exceed_stock_in_data['stock_type'],
                'region' => $exceed_stock_in_data['region'],
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //入库方变动前的物理库存 edit xiaowen 2018-2-9（计算成本用）
            $beforeNum = $stock_info['stock_num'];
            $stockId = $stock_info['id'];
            //------------------组装库存表的字段值--------------------------
            $data = [
                'goods_id' => $exceed_stock_in_data['goods_id'],
                'object_id' => $exceed_storehouse_id,
                'stock_type' => $exceed_stock_in_data['stock_type'],
                'facilitator_id' => $facilitator_id,
                'region' => $exceed_stock_in_data['region'],
                'stock_num' => $stock_info['stock_num'] + $exceed_stock_in_data['actual_storage_num'], //入库方的实际出库数量加到入库方的物理
            ];

            $stock_info['transportation_num'] = $data['transportation_num']; //重置最新的预留库存
            $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $exceed_stock_in_data['storage_code'],
                'object_type' => 4,
                'log_type' => 2,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $exceed_in_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $exceed_stock_in_data['actual_storage_num'], $orders);

            if (empty($stockId) || !isset($stockId)) {
                $stock_where = [
                    'goods_id' => $exceed_stock_in_data['goods_id'],
                    'object_id' => $exceed_storehouse_id,
                    'stock_type' => $exceed_stock_in_data['stock_type'],
                    'region' => $exceed_stock_in_data['region'],
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                $stockId = $stock_info['id'];
            }

            //根据承担方来判断是否生成其他超损出
            //我方承担则生成超损出
            if ($loss_arr['responsible_party'] == 1) {
                $exceed_stock_out_data = [
                    'outbound_type'         => 6,
                    'outbound_code'         => erpCodeNumber(7)['order_number'],
                    'outbound_status'       => 10,
                    'source_number'         => $loss_arr['order_number'],
                    'source_object_id'      => $loss_arr['id'],
                    'goods_id'              => $loss_arr['goods_id'],
                    'our_company_id'        => $loss_arr['our_company_id'],
                    'outbound_num'          => $loss_arr['exceed_loss_num'],
                    'actual_outbound_num'   => $loss_arr['exceed_loss_num'],
                    'deduction_num'         => $loss_arr['exceed_loss_num'],
                    'outbound_density'      => $density,
                    'create_time'           => currentTime(),
                    'dealer_id'             => $this->getUserInfo('id'),
                    'dealer_name'           => $this->getUserInfo('dealer_name'),
                    'creater_id'            => $this->getUserInfo('id'),
                    'creater_name'          => $this->getUserInfo('dealer_name'),
                    'auditor_id'            => $this->getUserInfo('id'),
                    'audit_time'            => currentTime(),
                    'outbound_remark'       => trim($param['outbound_remark']),
                    'storehouse_id'         => $exceed_storehouse_id,
                    'stock_type'            => 9,
                    'region'                => $source_order['region'],
                    'batch_sys_bn'          => $source_order['batch_sys_bn'],
                    'batch_id'              => $source_order['batch_id'],
                    'is_other'              => 1,
                    'loss_type'             => 1,
                    'parent_source_number'  => $source_number,
                ];
                //---------------确定该订单影响哪个库存，并查出该库存的信息-----
                $stock_where = [
                    'goods_id' => $exceed_stock_out_data['goods_id'],
                    'object_id' => $exceed_stock_out_data['storehouse_id'],
                    'stock_type' => $exceed_stock_out_data['stock_type'],
                    'region' => $exceed_stock_out_data['region'],
                ];
                $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
                $stock_out_cost = getStockOutCost($exceed_stock_out_data);
                $old_price = $stock_out_cost['price'];
                $new_price = calculateWeightingCost($stock_info['stock_num']-$exceed_stock_in_data['actual_storage_num'],$old_price,$exceed_stock_in_data['actual_storage_num'],$exceed_stock_in_data['price']);
                if ($loss_arr['type'] == 3) {
                    $new_price = 0;
                }
                $exceed_stock_out_data['cost'] = setNum(round($new_price/10000,2));
                $exceed_stock_out_status = $this->getModel('ErpStockOut')->addStockOut($exceed_stock_out_data);

                //------------------组装库存表的字段值--------------------------
                $data = [
                    'goods_id' => $exceed_stock_out_data['goods_id'],
                    'object_id' => $exceed_stock_out_data['storehouse_id'],
                    'stock_type' => $exceed_stock_out_data['stock_type'],
                    'facilitator_id' => $facilitator_id,
                    'region' => $exceed_stock_out_data['region'],
                    'stock_num' => $stock_info['stock_num'] - $exceed_stock_out_data['actual_outbound_num'],
                ];

                $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
                //------------------计算出新的可用库存----------------------------
                $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
                //----------------------------------------------------------------
                $orders = [
                    'object_number' => $exceed_stock_out_data['outbound_code'],
                    'object_type' => 3,
                    'log_type' => 2,
                ];
                //----------------更新库存，并保存库存日志-------------------------
                $exceed_out_stock_status = $this->getEvent('ErpStock')->saveStockInfo($data, $exceed_stock_out_data['actual_outbound_num'], $orders);
                $exceed_status = 10;
            } else {
                $exceed_stock_out_status = true;
                $exceed_out_stock_status = true;
                $exceed_status = 1;
            }
        } else {
            $exceed_stock_in_status = true;
            $exceed_in_stock_status = true;
            $exceed_stock_out_status = true;
            $exceed_out_stock_status = true;
            $exceed_status = 2;
        }

        //修改损耗单状态
        $save_data = [
            'order_status' => 10,
            'reasonable_status' => $reasonable_status,
            'exceed_status' => $exceed_status
        ];
        $save_status = $this->getModel("ErpLossOrder")->where($loss_where)->save($save_data);

        if ( !$reasonable_stock_in_status || !$reasonable_in_stock_status || !$reasonable_stock_out_status || !$reasonable_out_stock_status
            || !$exceed_stock_in_status || !$exceed_in_stock_status || !$exceed_stock_out_status || !$exceed_out_stock_status || !$save_status) {
            M()->rollback();
            $result = ['status' => 15,'message' => '操作失败！'];
        } else {
            //超损入计算加权成本
            //重新计算加权成本------------------------------------------------------
            if ($loss_arr['type'] != 3) {
                $exceed_stock_in_data['before_stock_num'] = $beforeNum;
                $exceed_stock_in_data['stock_id'] = $stockId ? $stockId : 0;
                $exceed_stock_in_data['change_num'] = $exceed_stock_in_data['actual_storage_num'];
                updateStockInCost($exceed_stock_in_data);
            }
            //----------------------------------------------------------------------
            M()->commit();
            $result = ['status' => 1,'message' => '操作成功！'];
        }

        cancelCacheLock('ErpLoss/lossConfirm');
        return $result;
    }

   /*
	* @ Content 损耗单取消
	* @ Author  YF
	* @ Time    2019-05-07
	*/
	public function lossDelete( $param ){
		if ( !isset($param['loss_id']) || empty($param['loss_id']) ) {
			return ['status' => 11 ,'message' => '请重新打开页面！'];
		}
		$loss_where['id'] = ['eq',trim($param['loss_id'])];
		$loss_arr = $this->getModel("ErpLossOrder")->where($loss_where)->field('id , source_number , type , loss_num ,order_status,exceed_loss_num,responsible_party')->find();

        if ( !isset($loss_arr['id']) ) {
			return ['status' => 15 ,'message' => '数据异常！'];
		}

		if ( $loss_arr['order_status'] != 1 ) {
			return ['status' => 12, 'message' => '只允许在未审核状态下取消！'];
		}


		$save_data = [
			"order_status" => 2,
		];
        M()->startTrans() ;
        //修改损耗状态
		$save_status = $this->getModel("ErpLossOrder")->where($loss_where)->save($save_data);
		if ( !$save_status ) {
		    M()->rollback() ;
			return ['status' => 15,'message' => '取消失败！'];
		}
        if($loss_arr['type'] == 3){//如果是销售订单取消，需要修改销售单得数量
            $updateSale = $this->getEvent("ErpSale")->saveLoss($loss_arr['source_number'] , $loss_arr['loss_num']);
            if($updateSale['status'] != 1){
                M()->rollback() ;
                return $updateSale ;
            }
        }
		M()->commit() ;
		return ['status' => 1,'message' => '取消成功！'];
	}

    /*
     * @params:
     *      $where:查询条件
     * @return:int
     * @auth:小黑
     * @time:2019-5-9
     * @desc:根据条件查询有效的损耗单数量
     */
    public function getLossOrderEffectiveCount($where = []){
        $where['order_status'] = ["neq" , 2];
        $where['reversed_status'] = 2 ;
        return $this->getModel("ErpLossOrder")->getLossOrder($where);
    }


     /**
     * @desc 损耗单红冲
     * @return array
     * @author xiaowen
     * @time 2019-5-14
      * @param int $loss_order_id 损耗单id
     *
     */
    public function lossOrderReverse($loss_order_id = 0){

        if(getCacheLock('ErpLoss/lossOrderReverse'))  return ['status' => 99, 'message' => $this->running_msg];

        if(empty($loss_order_id)){
            return [
                'status' => 2,
                'message' => '损耗单id参数错误',
            ];
        }

        $loss_order_info = $this->getModel('ErpLossOrder')->field(true)->find($loss_order_id);

        if($loss_order_info['order_status'] != 10){
            return [
                'status' => 3,
                'message' => '损耗单已确认状态才能红冲',
            ];
        }
        //超损处理状态为：转运费或 他方承担 && 超损已处理 必须先红冲运费单
        if(($loss_order_info['exceed_status'] == 3) || ($loss_order_info['exceed_status'] == 10 && $loss_order_info['responsible_party'] == 2)){
            //查询运费单信息
            $freight_order = $this->getModel('ErpFreightOrder')->where(['source_number'=>$loss_order_info['order_number'],'order_status' =>['neq',2]])->find();

            if ( isset($freight_order['id']) ) {
            	if( $freight_order['order_status'] == 10 ){
	                $message = '请先红冲运费单！';
	            }else if( $freight_order['order_status'] == 3 ){
	                $message = '请先完成运费单的确认后再红冲！';
	            } else if ( $freight_order['order_status'] == 1 ) {
	            	$message = '请先取消运费单！';
	            }
	            return [
	                'status' => 3,
	                'message' => $message,
	            ];
            }
            
        }
//        //验证是否要先红冲入库单
//        $source_stock_in = $this->getModel('ErpStockIn')->where(['storage_code'=>$loss_order_info['source_number']])->find();
//
//        if($source_stock_in['storage_status'] == 10 && $source_stock_in['is_reverse'] == 2 && $source_stock_in['reversed_status'] == 2){
//            return [
//                'status' => 4,
//                'message' => '请先红冲入库单',
//            ];
//        }
        //查询该损耗单对应的其他入库单（包含合理和超损）
        $other_stock_in_data = $this->getModel('ErpStockIn')->field(true)->where(['is_other'=>1, 'source_number'=>$loss_order_info['order_number'], 'storage_status'=>10])->select();


        $reasonable_stock_in = [];
        $exceed_stock_in = [];
        if($other_stock_in_data){
            foreach ($other_stock_in_data as $key=>$value){
                if($value['loss_type'] == 1){ //超损
                    $exceed_stock_in = $value;
                }else{  //合理
                    $reasonable_stock_in = $value;
                }
            }
        }

        M()->startTrans();
        setCacheLock('ErpLoss/lossOrderReverse', 1);

        $update_loss_order_data = [
            'reversed_status' => 1,
            'order_status' => 2,
            'reasonable_status' => 1,
            'exceed_status' => $loss_order_info['exceed_loss_num'] > 0 ? 1 : 2,
            'update_time' => DateTime(),
        ];
        $update_loss_order_status = $this->getModel('ErpLossOrder')->where(['id'=>$loss_order_info['id']])->save($update_loss_order_data);
        //====================构造 合理损耗 其他入红冲单数据==========================================================
        $reasonable_stock_in_reverse_status = true;
        $reasonable_stock_in_reverse_create = true;
        if ( !empty($reasonable_stock_in) ) {
        	$reasonable_stock_in_reverse = $reasonable_stock_in;

	        unset($reasonable_stock_in_reverse['id']);

	        $reasonable_stock_in_reverse['storage_code'] = erpCodeNumber(8)['order_number'];
	        $reasonable_stock_in_reverse['storage_type'] = 6;
	        $reasonable_stock_in_reverse['storage_num'] = plusConvert($reasonable_stock_in['storage_num']);
	        $reasonable_stock_in_reverse['actual_storage_num'] = plusConvert($reasonable_stock_in['actual_storage_num']);
	        $reasonable_stock_in_reverse['actual_storage_num_litre'] = plusConvert($reasonable_stock_in['actual_storage_num_litre']);
	        $reasonable_stock_in_reverse['is_other'] = 1;
	        $reasonable_stock_in_reverse['loss_type'] = 2;
	        $reasonable_stock_in_reverse['balance_num'] = 0;
	        $reasonable_stock_in_reverse['balance_num_litre'] = 0;
	        $reasonable_stock_in_reverse['deduction_num'] = 0;
	        $reasonable_stock_in_reverse['is_reverse'] = 1;
	        $reasonable_stock_in_reverse['reverse_source'] = $reasonable_stock_in['storage_code'];
	        $reasonable_stock_in_reverse['create_time'] = DateTime();
	        //=======================end==================================================================================

	        //生成合理其他入红冲单
	        $reasonable_stock_in_reverse_create = $this->getModel('ErpStockIn')->add($reasonable_stock_in_reverse);

	        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
	        $stock_where = [
	            'goods_id' => $reasonable_stock_in_reverse['goods_id'],
	            'object_id' => $reasonable_stock_in_reverse['storehouse_id'],
	            'stock_type' => $reasonable_stock_in_reverse['stock_type'],
	            'region' => $reasonable_stock_in_reverse['region'],
	            'status' => 1,
	        ];
	        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
	        //------------------组装库存表的字段值--------------------------
	        $data = [
	            'goods_id' => $reasonable_stock_in_reverse['goods_id'],
	            'object_id' => $reasonable_stock_in_reverse['storehouse_id'],
	            'stock_type' => $reasonable_stock_in_reverse['stock_type'],
	            'region' => $reasonable_stock_in_reverse['region'],
	            'stock_num' => $stock_info['stock_num'] + $reasonable_stock_in_reverse['actual_storage_num'], //入库方的实际出库数量加到入库方的物理
	        ];

	        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
	        //------------------计算出新的可用库存----------------------------
	        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
	        //----------------------------------------------------------------
	        $orders = [
	            'object_number' => $reasonable_stock_in_reverse['storage_code'],
	            'object_type' => 4,
	            'log_type' => 2,
	        ];
	        //----------------更新库存，并保存库存日志-------------------------
	        $reasonable_stock_in_reverse_status = $this->getEvent('ErpStock')->saveStockInfo($data, $reasonable_stock_in_reverse['actual_storage_num'], $orders);
        }
        

        $exceed_stock_out = []; //超损其他出
        $reasonable_stock_out = []; //合理其他出
        //查询损耗的其他出库单（含合理和超损）
        $other_stock_out_data = $this->getModel('ErpStockOut')->where(['is_other'=>1, 'source_number'=>$loss_order_info['order_number'], 'outbound_status'=>10])->select();

        if($other_stock_out_data){
            foreach ($other_stock_out_data as $key=>$value){
                if($value['loss_type'] == 1){
                    $exceed_stock_out = $value;
                }else if($value['loss_type'] == 2){
                    $reasonable_stock_out = $value;
                }
            }
        }

        //如果存在超损其他出库单 并且损耗是我方承担，须在损耗单红冲时将超损出库单红冲
        if($exceed_stock_out && $loss_order_info['responsible_party'] == 1){
            $exceed_stock_out_reverse = $exceed_stock_out;
            unset($exceed_stock_out_reverse['id']);

            $exceed_stock_out_reverse['outbound_code'] = erpCodeNumber(7)['order_number'];
            $exceed_stock_out_reverse['outbound_num'] = plusConvert($exceed_stock_out['outbound_num']);
            $exceed_stock_out_reverse['actual_outbound_num'] = plusConvert($exceed_stock_out['actual_outbound_num']);
            $exceed_stock_out_reverse['is_reverse'] = 1;
            $exceed_stock_out_reverse['reversed_status'] = 2;
            $exceed_stock_out_reverse['reverse_source'] = $exceed_stock_out['outbound_code'];
            $exceed_stock_out_reverse['is_other'] = 1;
            $exceed_stock_out_reverse['loss_type'] = 1;
            $exceed_stock_out_reverse['create_time'] = DateTime();

            //生成超损 其他出红冲单
            $exceed_stock_out_reverse_create = $this->getModel('ErpStockOut')->add($exceed_stock_out_reverse);
            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $exceed_stock_out_reverse['goods_id'],
                'object_id' => $exceed_stock_out_reverse['storehouse_id'],
                'stock_type' => $exceed_stock_out_reverse['stock_type'],
                'region' => $exceed_stock_out_reverse['region'],
                'status' => 1,
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);

            $before_exceed_stock_out_stock_num = $stock_info['stock_num'];
            $before_exceed_stock_out_stock_id = $stock_info['id'];

            //------------------组装库存表的字段值--------------------------
            $data = [
                'goods_id' => $exceed_stock_out_reverse['goods_id'],
                'object_id' => $exceed_stock_out_reverse['storehouse_id'],
                'stock_type' => $exceed_stock_out_reverse['stock_type'],
                'region' => $exceed_stock_out_reverse['region'],
                'stock_num' => $stock_info['stock_num'] - $exceed_stock_out_reverse['actual_outbound_num'], //入库方的实际出库数量加到入库方的物理
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
            $exceed_stock_out_reverse_status = $this->getEvent('ErpStock')->saveStockInfo($data, $exceed_stock_out_reverse['actual_outbound_num'], $orders);
        }else{
            $exceed_stock_out_reverse_create = true;
            $exceed_stock_out_reverse_status = true;
        }

        //合理损耗出库单红冲
        $reasonable_stock_out_reverse_create = true;
        $reasonable_stock_out_reverse_status = true;
        if ( !empty($reasonable_stock_out) ) {
        	$reasonable_stock_out_reverse = $reasonable_stock_out;

	        unset($reasonable_stock_out_reverse['id']);

	        $reasonable_stock_out_reverse['outbound_code'] = erpCodeNumber(7)['order_number'];
	        $reasonable_stock_out_reverse['outbound_num'] = plusConvert($reasonable_stock_out['outbound_num']);
	        $reasonable_stock_out_reverse['actual_outbound_num'] = plusConvert($reasonable_stock_out['actual_outbound_num']);
	        $reasonable_stock_out_reverse['is_reverse'] = 1;
	        $reasonable_stock_out_reverse['reversed_status'] = 2;
	        $reasonable_stock_out_reverse['reverse_source'] = $reasonable_stock_out['outbound_code'];
	        $reasonable_stock_out_reverse['is_other'] = 1;
	        $reasonable_stock_out_reverse['loss_type'] = 2;

	        $reasonable_stock_out_reverse['create_time'] = DateTime();

	        //生成合理损耗 其他出红冲单
	        $reasonable_stock_out_reverse_create = $this->getModel('ErpStockOut')->add($reasonable_stock_out_reverse);
	        //---------------确定该订单影响哪个库存，并查出该库存的信息-----
	        $stock_where = [
	            'goods_id' => $reasonable_stock_out_reverse['goods_id'],
	            'object_id' => $reasonable_stock_out_reverse['storehouse_id'],
	            'stock_type' => $reasonable_stock_out_reverse['stock_type'],
	            'region' => $reasonable_stock_out_reverse['region'],
	            'status' => 1,
	        ];
	        $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
	        //------------------组装库存表的字段值--------------------------
	        $data = [
	            'goods_id' => $reasonable_stock_out_reverse['goods_id'],
	            'object_id' => $reasonable_stock_out_reverse['storehouse_id'],
	            'stock_type' => $reasonable_stock_out_reverse['stock_type'],
	            'region' => $reasonable_stock_out_reverse['region'],
	            'stock_num' => $stock_info['stock_num'] - $reasonable_stock_out_reverse['actual_outbound_num'], //扣减库存
	        ];

	        $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
	        //------------------计算出新的可用库存----------------------------
	        $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
	        //----------------------------------------------------------------
	        $orders = [
	            'object_number' => $reasonable_stock_out_reverse['outbound_code'],
	            'object_type' => 3,
	            'log_type' => 12,
	        ];
	        //----------------更新库存，并保存库存日志-------------------------
	        $reasonable_stock_out_reverse_status = $this->getEvent('ErpStock')->saveStockInfo($data, $reasonable_stock_out_reverse['actual_outbound_num'], $orders);
        }
        


        if($exceed_stock_in){ //如果存在超损，则红冲超损入库单
            //====================构造 超损 其他入红冲单数据==========================================================
            $exceed_stock_in_reverse = $exceed_stock_in;

            unset($exceed_stock_in_reverse['id']);

            $exceed_stock_in_reverse['storage_code'] = erpCodeNumber(8)['order_number'];
            $exceed_stock_in_reverse['storage_type'] = 6;
            $exceed_stock_in_reverse['storage_num'] = plusConvert($exceed_stock_in['storage_num']);
            $exceed_stock_in_reverse['actual_storage_num'] = plusConvert($exceed_stock_in['actual_storage_num']);
            $exceed_stock_in_reverse['actual_storage_num_litre'] = plusConvert($exceed_stock_in['actual_storage_num_litre']);
            $exceed_stock_in_reverse['is_other'] = 1;
            $exceed_stock_in_reverse['loss_type'] = 1;
            $exceed_stock_in_reverse['balance_num'] = 0;
            $exceed_stock_in_reverse['balance_num_litre'] = 0;
            $exceed_stock_in_reverse['deduction_num'] = 0;

            $exceed_stock_in_reverse['is_reverse'] = 1;
            $exceed_stock_in_reverse['reverse_source'] = $exceed_stock_in['storage_code'];
            $exceed_stock_in_reverse['create_time'] = DateTime();
            //=======================end==================================================================================

            //生成超损其他入 红冲单
            $exceed_stock_in_reverse_create = $this->getModel('ErpStockIn')->add($exceed_stock_in_reverse);
            //---------------确定该订单影响哪个库存，并查出该库存的信息-----
            $stock_where = [
                'goods_id' => $exceed_stock_in_reverse['goods_id'],
                'object_id' => $exceed_stock_in_reverse['storehouse_id'],
                'stock_type' => $exceed_stock_in_reverse['stock_type'],
                'region' => $exceed_stock_in_reverse['region'],
                'status' => 1,
            ];
            $stock_info = $this->getEvent('ErpStock')->getStockInfo($stock_where);
            //------------------组装库存表的字段值--------------------------

            $before_exceed_stock_in_stock_num = $stock_info['stock_num'];
            $before_exceed_stock_in_stock_id = $stock_info['id'];

            $data = [
                'goods_id' => $exceed_stock_in_reverse['goods_id'],
                'object_id' => $exceed_stock_in_reverse['storehouse_id'],
                'stock_type' => $exceed_stock_in_reverse['stock_type'],
                'region' => $exceed_stock_in_reverse['region'],
                'stock_num' => $stock_info['stock_num'] + $exceed_stock_in_reverse['actual_storage_num'], //入库方的实际出库数量加到入库方的物理
            ];

            $stock_info['stock_num'] = $data['stock_num']; //重置最新的物理库存
            //------------------计算出新的可用库存----------------------------
            $data['available_num'] = $this->getEvent('ErpStock')->calculateAvailableNum($stock_info);
            //----------------------------------------------------------------
            $orders = [
                'object_number' => $exceed_stock_in_reverse['storage_code'],
                'object_type' => 4,
                'log_type' => 2,
            ];
            //----------------更新库存，并保存库存日志-------------------------
            $exceed_stock_in_reverse_status = $this->getEvent('ErpStock')->saveStockInfo($data, $exceed_stock_in_reverse['actual_storage_num'], $orders);

        }else{
            $exceed_stock_in_reverse_create = true;
            $exceed_stock_in_reverse_status = true;
        }

        //如果 是销售单损耗红冲，必须更新销售单损耗数量

        if($loss_order_info['type'] == 3){
            $update_sale_order_status = $this->getEvent('ErpSale')->saveLoss($loss_order_info['source_number'], $loss_order_info['loss_num']);
        }else{
            $update_sale_order_status = ['status'=>1, 'message'=>'更新销售单数据成功'];
        }

        if($update_loss_order_status && $exceed_stock_in_reverse_status && $reasonable_stock_in_reverse_status
            && $exceed_stock_in_reverse_create && $reasonable_stock_in_reverse_create && $exceed_stock_out_reverse_create && $exceed_stock_out_reverse_status
            && $reasonable_stock_out_reverse_create && $reasonable_stock_out_reverse_status && $update_sale_order_status['status'] == 1
        ){
            M()->commit();
            //采购、调拨损耗红冲 超损成本处理 edit xiaowen 2019-6-3----------
            if($loss_order_info['type'] != 3){
                //如果是我方承担，超损其他出红冲，进行成本 加权
                if($loss_order_info['type'] != 3 && $loss_order_info['responsible_party'] == 1){
                    $exceed_stock_out_reverse['before_stock_num'] = $before_exceed_stock_out_stock_num;
                    $exceed_stock_out_reverse['stock_id'] = $before_exceed_stock_out_stock_id ? $before_exceed_stock_out_stock_id : 0;
                    $exceed_stock_out_reverse['change_num'] = abs($exceed_stock_out_reverse['actual_outbound_num']);
                    updateStockInCost($exceed_stock_out_reverse);
                }
                //超损其他入红冲，进行成本 加权
                $exceed_stock_in_reverse['before_stock_num'] = $before_exceed_stock_in_stock_num;
                $exceed_stock_in_reverse['stock_id'] = $before_exceed_stock_in_stock_id ? $before_exceed_stock_in_stock_id : 0;
                $exceed_stock_in_reverse['change_num'] = $exceed_stock_in_reverse['actual_storage_num'];
                updateStockInCost($exceed_stock_in_reverse);
            }
            //------------------------------------------------------------------
            $result = [
                'status'=>1,
                'message'=>'红冲成功',
            ];
        } else {
            M()->rollback();
            $result = [
                'status'=>0,
                'message'=> $update_sale_order_status['status'] != 1 ? $update_sale_order_status['message'] : '红冲失败',
            ];
        }
        cancelCacheLock('ErpLoss/lossOrderReverse');
        return $result;
    }


}