<?php
namespace Common\Event;

//edit by guanyu    Common->Home
use Home\Controller\BaseController;

class ErpStockInApplyEvent extends BaseController
{
	/*****************************************
		@ Content 生成入库是申请单
		@ Author  YF
		@ Time    2019-04-12
		@ Param [string] $[may_apply_num] 申请入库数量
		@ Param [int]    $[purchase_order_id] 采购单ID
		@ Param [string] $[stock_in_apply_remark] 入库申请单备注
	******************************************/
	public function addStockInApplyOrder($param)
	{
		if ( !isset($param['purchase_order_id']) || empty($param['purchase_order_id']) ) {
			return ['status' => 12,'message' => '缺少采购单ID！'];
		}

		// 申请入库数量
		if ( !isset($param['apply_num']) || empty($param['apply_num']) ) {
			return ['status' => 13,'message' => '缺少申请数量字段！'];
		}

		// 入库申请单备注
		if ( !isset($param['stock_in_apply_remark']) ) {
			return ['status' => 14,'message' => '缺少备注字段!'];
		}

		if ( !isset($param['depot_id']) || empty($param['depot_id']) ) {
			return ['status' => 15,'message' => '缺少油库字段!'];
		}

		/* -------- 申请入库数量不能>可申请数量 -------------*/
		$count_may_apply_num_result = $this->countMayApplyNum(trim($param['purchase_order_id']));
		if ( $count_may_apply_num_result['status'] != 1 ) {
			return $count_may_apply_num_result;
		}
		$apply_num = trim($param['apply_num']);
		if ( (float)$apply_num > (float)$count_may_apply_num_result['data'] ) {
			return ['status' => 4,'message' => '申请数量不能大于可申请数量！'];
		}
		// 获取单信息
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $order_info = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $param['purchase_order_id']], $field);
        /* ---------------- 验证采购单入库条件是否达标 ------------------------ */
        if (
            (	($order_info['pay_type'] == 1 && $order_info['pay_status'] != 10)   ||
                ($order_info['pay_type'] == 2 && $order_info['pay_status'] <= 2)    ||
                ($order_info['pay_type'] == 3 && $order_info['order_status'] != 10) ||
                ($order_info['pay_type'] == 4 && $order_info['pay_status'] != 10)   ||
                ($order_info['pay_type'] == 5 && $order_info['pay_status'] <= 2)
            ) || $order_info['order_status'] != 10
        ) {
        	return ['status' => 5, 'message' => '该采购单未达到入库条件，无法生成入库单申请单！'];
        }
        if ($order_info['is_returned'] == 1) {
        	return ['status' => 6, 'message' => '已退货订单无法生成入库申请单'];
        }

        $stock_not_in = $order_info['pay_type'] == 5 ? $order_info['total_purchase_wait_num'] - $order_info['storage_quantity'] : $order_info['goods_num'] - $order_info['storage_quantity'];
        if ( (int)setNum($param['apply_num']) > (int)$stock_not_in ) {
        	return ['status' => 14,'message' => '入库申请数量不能超出待入数量!'];
        }
        /* -------------------- 生成入库申请单 --------------------------- */
        $erp_stock_in_apply_arr = [
			'storage_apply_code'  => erpCodeNumber(22)['order_number'],
			'storage_apply_type'  => 1,
			'status'			  => 1,
			'remark'			  => trim($param['stock_in_apply_remark']),
			'source_number'		  => $order_info['order_number'],
			'source_object_id'	  => $order_info['id'],
			'our_company_id'	  => $order_info['our_buy_company_id'],
			'goods_id'            => $order_info['goods_id'],
			'depot_id'			  => trim($param['depot_id']),
			'storage_apply_num'   => setNum($param['apply_num']),
			'create_time'         => nowTime(),
			'creater_id'          => $this->getUserInfo('id'),
			'creater_name'        => $this->getUserInfo('dealer_name'),
			'storehouse_id'       => $order_info['storehouse_id'],
			'stock_type'		  => $order_info['type'] == 1 ? getAllocationStockType($order_info['storehouse_id']) : 2,
			'data_source'		  => 1,
			'region'			  => $order_info['region'],
			'is_shipping'		  => 2,
		];
		$add_result = $this->getModel('ErpStockInApply')->add($erp_stock_in_apply_arr);
		if ( !$add_result ) {
			return ['status' => 7,'message' => '入库申请单添加失败！'];
		}
		return ['status' => 1,'message' => '入库申请单添加成功！'];
	}

	/*****************************************
		@ Content 计算可申请数量
		@ Time    2019-04-12
		@ Author  YF
		@ Param purchase_order_id [int] 采购单ID
		@ Return  => [
				'status' 	=> 状态码
				'message' 	=> 提示语
				'data'    	=> 数据
		]
	******************************************/
	public function countMayApplyNum( $purchase_order_id = 0 )
	{
		if ( $purchase_order_id == 0 ) {
			return ['status' => 3 ,'message' => '缺少采购单ID！'];
		}
		/* ------------------- 采购单 ----------------------- */
		$find_order_where = [
			'id' => ['eq',$purchase_order_id]
		];
		$field = "id,storage_quantity,goods_num,pay_type,total_purchase_wait_num";
		$order_info = $this->getModel('ErpPurchaseOrder')->where($find_order_where)->field($field)->find();
		if ( !isset($order_info['id']) ) {
			return ['status' => 4,'message' => '未查询到所对应的采购单！'];
		}
		
		/* -------------- 未审核入库单数量 ------------------ */
		$stock_in_where = [
			'source_object_id' => ['eq',$order_info['id']],
			'storage_status'   => ['eq',1],
		];
		$stock_in_info = $this->getModel('ErpStockIn')->where($stock_in_where)->field("actual_storage_num,storage_code")->select();

		$storage_num = 0;
		if ( !empty($stock_in_info) ) {
			// 未审核入库单数量
			$storage_num = array_sum(array_column($stock_in_info,'actual_storage_num'));
		}
		
		/* -------------- 已创建入库单申请数量 ------------------ */
		$stock_in_apply_where = [
			'status'  		   => ['eq',1],
			'source_object_id' => ['eq',$purchase_order_id]
		];
		$stock_in_apply_arr = $this->getModel('ErpStockInApply')->where($stock_in_apply_where)->field('storage_apply_num')->select();
		$all_storage_apply_num = 0;
		if ( !empty($stock_in_apply_arr) ) {
			$all_storage_apply_num = array_sum(array_column($stock_in_apply_arr,'storage_apply_num'));
		}
		/* -------------- 已创建的损耗单数量 ------------------ */
		$stock_in_where_to = [
			'source_object_id' => ['eq',$order_info['id']],
			'storage_status'   => ['neq',2],
		];
		$stock_in_arr = $this->getModel('ErpStockIn')->where($stock_in_where_to)->field('storage_code')->select();
		$all_loss_num = 0;
		if ( !empty($stock_in_arr) ) {
			// 入库单号
			$source_number_arr  = array_column($stock_in_arr,'storage_code');
			$loss_where = [
				'source_number' => ['in',$source_number_arr],
				'order_status'  => ['neq',2],
			];
			$loss_order_arr = $this->getModel("ErpLossOrder")->where($loss_where)->field('loss_num')->select();
			if ( !empty($loss_order_arr) ) {
				$all_loss_num = array_sum(array_column($loss_order_arr,'loss_num'));
			}
		}
		$good_num = $order_info['pay_type'] == 5 ? $order_info['total_purchase_wait_num'] - $order_info['storage_quantity'] : $order_info['goods_num']-$order_info['storage_quantity'];
		// 计算 可申请数量
		$countMayApplyNum = getNum($good_num -$all_storage_apply_num-$storage_num-$all_loss_num);
		return ['status' => 1,'message' => '获取可申请数量成功！','data' => round($countMayApplyNum,4)];
	}


	/**********************************
		@ Content 入库申请单列表
		@ Author  YF
		@ Time    2019-04-15
	***********************************/
	public function stockInApplyList( $param = [])
	{
		if ( !isset($param['start']) ) {
			return ['status' => 12,'message' => '缺少start字段！'];
		}
		if ( !isset($param['length']) || empty($param['length']) ) {
			return ['status' => 13,'message' => '缺少length字段！'];
		}
		if ( !isset($param['draw']) ) {
			$param['draw'] = 1;
		}
		$result_where = $this->handleStockInApplyListWhere($param);
		$data = $this->getModel('ErpStockInApply')->stockInApplyList($result_where,'*',$param['start'],$param['length']);

		$handle_result = $this->handleStockInApplyList($data['data']);
		if ( $handle_result['status'] == 1 ) {
			$data['data'] = $handle_result['data'];
		} else {
			$data['data'] = [];
		}
		$data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $param['draw'];
        return $data;
	}


	/**************************************
		@ Content 处理查询数据
		@ Author YF
		@ Time   2019-04-15
	***************************************/
	public function handleStockInApplyList( $data = [])
	{	
		if ( !empty($data) )  {
			/* ---------- 查询采购单数据 --------------- */
			$source_object_id = array_unique(array_column($data ,"source_object_id"));
			if ( empty($source_object_id) ) {
				return ['status' => 21 ,'message' => '请联系管理员！'];
			}
			$purchase_order_arr = $this->getModel("ErpPurchaseOrder")->where(['id'=>['in',$source_object_id]])->field('id,sale_user_id,sale_company_id,buyer_dealer_name')->select();
			if ( empty($purchase_order_arr) ) {
				return ['status' => 22 ,'message' => '未查询到所对应的采购单！'];
			}
			/* ------------- 供应商 ----------------- */
			$sale_user_id = array_unique(array_column($purchase_order_arr ,"sale_user_id"));
			$sale_company_id = array_unique(array_column($purchase_order_arr ,"sale_company_id"));
			$supplier_name_arr = $this->getModel('ErpSupplier')->where(['id'=>['in',$sale_company_id]])->getField('id,supplier_name');
			$user_name_arr = $this->getModel('ErpSupplierUser')->where(['id'=>['in',$sale_user_id]])->getField('id,user_name');
			$supplier_arr = [];
			foreach ($purchase_order_arr as $key => $value) {
				$supplier_arr[$value['id']]['supplier_name'] = isset($supplier_name_arr[$value['sale_company_id']]) ? $supplier_name_arr[$value['sale_company_id']] : '--';
				$supplier_arr[$value['id']]['user_name'] = isset($user_name_arr[$value['sale_user_id']]) ? $user_name_arr[$value['sale_user_id']] : '--';
                //edit by guanyu 采购员
                $dealer_name_arr[$value['id']] = $value['buyer_dealer_name'];
			}
			/* ------------- 仓库 ------------------ */
			$storehouse_id = array_unique(array_column($data ,"storehouse_id"));
			$storehouse_name_arr = $this->getModel('ErpStorehouse')->where(['id'=>['in',$storehouse_id]])->getField('id,storehouse_name');
			/* ------------- 油库 ------------------ */
			$depot_id = array_unique(array_column($data ,"depot_id"));
			$depot_name_arr = $this->getModel('Depot')->where(['id'=> ['in',$depot_id]])->getField('id,depot_name');
			/* ------------- 商品代码 --------------- */
			$goods_id = array_unique(array_column($data ,"goods_id"));
			$good_list = $this->getModel("ErpGoods")->where(['id'=>['in',$goods_id]])->field('id,goods_code,goods_name,source_from,grade,level')->select();
            foreach ($good_list as $value){
               $good_list[$value['id']] = $value['goods_code']."/".$value['goods_name']."/".$value['source_from']
                   ."/".$value['grade']."/".$value['level'];
            }
			foreach ($data as &$value) {
                //edit by guanyu 采购员
			    $value['dealer_name']       = $dealer_name_arr[$value['source_object_id']];
				$value['user_name'] 	    = isset($supplier_arr[$value['source_object_id']]) ? $supplier_arr[$value['source_object_id']]['user_name'] : '--';
				$value['supplier_name']     = isset($supplier_arr[$value['source_object_id']]) ? $supplier_arr[$value['source_object_id']]['supplier_name'] : '--';
				$value['goods_name']        = isset($good_list[$value['goods_id']]) ? $good_list[$value['goods_id']] : '--';
				//edit by guanyu 不限油库
				$value['depot_name']        = isset($depot_name_arr[$value['depot_id']]) ? $depot_name_arr[$value['depot_id']] : ($value['depot_id'] == 99999 ? '不限油库' : '--');
				$value['storehouse_name']   = isset($storehouse_name_arr[$value['storehouse_id']]) ? $storehouse_name_arr[$value['storehouse_id']] : '--';
				$value['status']            = stockInApplyListStatus($value['status']);
				$value['storage_apply_num'] = getNum($value['storage_apply_num']);
			}
			return ['status' => 1,'message' => '处理成功','data' =>$data];
		}
		
	}


	/*************************************
		@ Content 处理入库申请单列表条件
		@ Author  YF
		@ Time    2019-04-15
		@ Param  [
			'storage_apply_code' => 入库申请单号
			'source_number'		 => 来源单号
			'goods_id'			 => 商品ID
			'storehouse_id'		 => 仓库ID
			.......
		]
		@ Return [array] $[where] 查询条件
	**************************************/
	public function handleStockInApplyListWhere( $param = [] )
	{
		$where = [];
		/* -------- 账套信息 -----------*/
		$where['our_company_id'] = ['eq',session('erp_company_id')];
		if ( isset($param['creater_id']) && !empty($param['creater_id']) ) {
			$where['creater_id'] = ['eq',trim($param['creater_id'])];
		}
		/* -------- 入库申请单号 -----------*/
		if ( isset($param['storage_apply_code']) && !empty($param['storage_apply_code']) ) {
			 $where['storage_apply_code'] = ['like', ['%' . trim($param['storage_apply_code']) . '%']];
		}
		/* -------- 来源单号 -----------*/
		if ( isset($param['source_number']) && !empty($param['source_number']) ) {
			$where['source_number'] = ['like', ['%' . trim($param['source_number']) . '%']];
		}

		/* ----------- 商品ID ------------- */
		if ( isset($param['goods_id']) && !empty($param['goods_id']) ) {
			$where['goods_id'] = ['eq',trim($param['goods_id'])];
		}

		/* ----------- 供应商公司 --------------- */
		if ( isset($param['sale_company_id']) && !empty($param['sale_company_id']) ) {
			$order_ids = $this->getModel('ErpPurchaseOrder')->where(['sale_company_id'=>['eq',trim($param['sale_company_id'])]])->Field('id')->select();
			if ( !empty($order_ids) ) {
				$where['source_object_id'] = ['in',array_column($order_ids,'id')];
			} else {
				$where['source_object_id'] = ['eq',0];
			}
		}

		/* -------------- 仓库ID -------------- */
		if ( isset($param['storehouse_id']) && !empty($param['storehouse_id']) ) {
			$where['storehouse_id'] = ['eq',trim($param['storehouse_id'])];
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

		/* -------------- 入库申请单状态 ------------------ */ 
		if ( isset($param['status']) && !empty($param['status']) ) {
			$where['status'] = ['eq',trim($param['status'])];
		} elseif ( !isset($param['search_type']) && $param['search_type'] != 2 && empty($param['status']) && (count($where) == 1 || (isset($where['creater_id']) && count($where) == 2) ) ) {
			$where['status'] = ['eq',1];
		}
	
		return $where;
	}


	/***********************************
		@ Content 取消入库申请单
		@ Time    2019-04-16
		@ Author  YF
		@ Param   [array] [
			stockin_apply_id => 入库申请单的ID
		]
	************************************/
	public function cancelStockInApply( $param )
	{
		if ( !isset($param['stockin_apply_id']) || empty($param['stockin_apply_id']) ) {
			return ['status' => 2,'message' => '缺少必填字段！'];
		}

		$stock_in_apply_where = [
			'id' => ['eq',trim($param['stockin_apply_id'])]
		];

		$order_info = $this->getModel('ErpStockInApply')->where($stock_in_apply_where)->find();
		if ( !isset($order_info['status']) ) {
			return ['status' => 12,'message' => '未查询到入库申请单！'];
		}

		if ( $order_info['status'] != 1 ) {
			return ['status' => 13 , 'message' => '已创建的入库申请单，才能取消！'];
		}

		$update_arr = [
			'status' => 2
		];
		$update_result = $this->getModel('ErpStockInApply')->where($stock_in_apply_where)->save($update_arr);
		if ( !$update_result ) {
			return ['status' => 14, 'message' => '入库申请单取消失败！'];
		}

		return ['status' => 1 , 'message' => '取消成功！'];

	}

	/***********************************
		@ Content 编辑入库申请单
		@ Time    2019-04-16
		@ Author  YF
		@ Param   [array] [
			stockin_apply_id => 入库申请单的ID
			apply_num        => 申请数量
		]
	************************************/
	public function editStockInApply ($param = [])
	{
		if ( !isset($param['stockin_apply_id']) || empty($param['stockin_apply_id']) ) {
			return ['status' => 2,'message' => '缺少必填字段！'];
		}

		if ( !isset($param['apply_num']) || empty($param['apply_num']) ) {
			return ['status' => 3 ,'message' => '缺少申请数量字段！'];
		}

		if ( !isset($param['depot_id']) || empty($param['depot_id']) ) {
			return ['status' => 5 ,'message' => '缺少油库ID！'];
		}

		if ( !isset( $param['remark']) ) {
			return ['status' => 4, 'message' => '缺少备注字段！'];
		}

		$stock_in_apply_where = [
			'id' => ['eq',trim($param['stockin_apply_id'])]
		];

		$order_info = $this->getModel('ErpStockInApply')->where($stock_in_apply_where)->find();
		if ( !isset($order_info['status']) ) {
			return ['status' => 12,'message' => '未查询到入库申请单！'];
		}

		if ( $order_info['status'] != 1 ) {
			return ['status' => 13 , 'message' => '已创建的入库申请单，才能编辑！'];
		}
		/* -------- 申请入库数量不能>可申请数量 -------------*/
		$count_may_apply_num_result = $this->countMayApplyNum(trim($order_info['source_object_id']));
		if ( $count_may_apply_num_result['status'] != 1 ) {
			return $count_may_apply_num_result;
		}
		$may_apply_num = trim($param['apply_num']);
		if ( $may_apply_num > ($count_may_apply_num_result['data']+ $order_info['storage_apply_num']) ) {
			return ['status' => 4,'message' => '申请数量不能大于可申请数量！'];
		}

		$update_arr = [
			'depot_id'			=> trim($param['depot_id']),
			'storage_apply_num' => setNum($may_apply_num),
			'update_time'       => nowTime(),
			'remark'            => trim($param['remark']),
		];

		$update_result = $this->getModel('ErpStockInApply')->where($stock_in_apply_where)->save($update_arr);
		if ( !$update_result ) {
			return ['status' => 14, 'message' => '入库申请单修改失败！'];
		}

		return ['status' => 1 , 'message' => '入库申请单修改成功！'];

	}

	/***********************************
		@ Content 入库申请单生成 入库单
		@ Author  YF
		@ Time    2019-04-16
		@ Param  
			$[stock_in_arr] 		[array]    入库单数据
			$[stock_in_apply_id] 	[Int]      入库申请单ID
			$[outbound_density] 	[string]   密度
			$[storage_remark] 		[string]   备注
	************************************/
	public function addStockInOrder( $param )
	{
		if ( !isset($param['stock_in_arr']) || empty($param['stock_in_arr']) ) {
			return ['status' => 2,'message' => '缺少必要字段！'];
		}

		if ( !isset($param['stock_in_apply_id']) || empty($param['stock_in_apply_id']) ) {
			return ['status' => 3, 'message' => '缺少必要字段！'];
		}
		$stock_in_arr = $param['stock_in_arr'];
		if ( !is_array($stock_in_arr) ) {
			return ['status' => 4,'message' => 'stock_in_arr必须为数组！'];
		}

		if ( !isset($param['storage_density']) ) {
			if ( !empty($param['storage_density']) && ($param['storage_density'] < 0.7 || $param['storage_density'] > 1) ) {
				return ['status' => 7,'message' => '请输入0.7 ~ 1 之间的密度值！'];
			}
			return ['status' => 5, 'message' => '缺少storage_density字段！'];
		}
		if ( !isset($param['storage_remark']) ) {
			return ['status' => 6 ,'message' => '缺少storage_remark字段！'];
		}
		
		$stock_in_apply_arr = $this->getModel('ErpStockInApply')->where(['id' => ['eq',trim($param['stock_in_apply_id'])]])->find();
		if ( !isset($stock_in_apply_arr['id']) ) {
			return ['status' => 12, 'message' => '不存在入库申请单！'];
		}

		$stock_in_num = array_sum(array_column($stock_in_arr,'stock_in_num'));
		if ( (int)setNum($stock_in_num) > (int)$stock_in_apply_arr['storage_apply_num'] ) {
			return ['status' => 13,'message' => '不允许超过入库申请单的申请入库数量！'];
		}
		// 添加损耗功能判断
		$loss_num = array_sum(array_column($stock_in_arr,'loss_num'));
		if ( ( (int)setNum($loss_num) + (int)setNum($stock_in_num) ) > (int)$stock_in_apply_arr['storage_apply_num'] ) {
			return ['status' => 31,'message' => '入库数量+损耗数量不能大于可申请数量！'];
		}
		if ( (int)trim($param['is_loss']) == 1 && (float)$loss_num <= 0 ) {
			return ['status' => 41,'message' => '请输入损耗数量！'];
		}
		/* -------------------- 获取采购单信息 --------------------- */ 
        $field = 'o.*,d.depot_name,s.supplier_name s_company_name,su.user_name s_user_name,su.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $order_info = $this->getModel('ErpPurchaseOrder')->findOnePurchaseOrder(['o.id' => $stock_in_apply_arr['source_object_id']], $field);

        /* ---------------- 验证采购单入库条件是否达标 ------------------------ */
        if (
            (	($order_info['pay_type'] == 1 && $order_info['pay_status'] != 10)   ||
                ($order_info['pay_type'] == 2 && $order_info['pay_status'] <= 2)    ||
                ($order_info['pay_type'] == 3 && $order_info['order_status'] != 10) ||
                ($order_info['pay_type'] == 4 && $order_info['pay_status'] != 10)   ||
                ($order_info['pay_type'] == 5 && $order_info['pay_status'] <= 2)
            ) || $order_info['order_status'] != 10
        ) {
        	return ['status' => 5, 'message' => '该采购单未达到入库条件，无法生成入库单申请单！'];
        }
        if ($order_info['is_returned'] == 1) {
        	return ['status' => 6, 'message' => '已退货订单无法生成入库单'];
        }

        /* -------------- 验证带入数量 ------------------*/
       	$stock_not_in = $order_info['pay_type'] == 5 ? $order_info['total_purchase_wait_num'] - $order_info['storage_quantity'] : $order_info['goods_num'] - $order_info['storage_quantity'];
        if ( (int)setNum($stock_in_num) > (int)$stock_not_in ) {
        	return ['status' => 14,'message' => '入库数量不能超出待入数量!'];
        }
        if( $order_info['business_type'] == 4 && empty(trim($param['storage_density'])) ){
        	return ['status' => 15, 'message' => '加油站业务，密度不能为空！'];
        }
        // 判断是否有重复的 货号号 和 货权类型 (不在事物里,自认为效率会高一些)
        $cargo_arr = [];
        foreach ($stock_in_arr as $key => $value) {
        	$cargo_arr[$value['cargo_bn'].$value['cargo_bn_type']] = 1;
        }
        if ( count($stock_in_arr) != count($cargo_arr) ) {
        	return ['status' => 16,'message' => '提交明细中存在重复货权号信息，请检查！' ];
        }
        // end
        // cancelCacheLock('ErpStockInApply/addStockInOrder');
		if (getCacheLock('ErpStockInApply/addStockInOrder')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpStockInApply/addStockInOrder', 1);
		M()->startTrans();
		// 损耗单数据
	    $insert_loss_order_data = [];
		foreach ($stock_in_arr as $key => $value) {
			/* -------------- 入库数量 ------------------ */
			if ( !isset($value['stock_in_num']) || empty($value['stock_in_num']) ) {
				M()->rollback();
				cancelCacheLock('ErpStockInApply/addStockInOrder');
				return ['status' => 3,',message' => '缺少入库数量！'];
			}
			/* -------------- 货权号 ------------------ */
			if ( !isset($value['cargo_bn']) || empty($value['cargo_bn']) ) {
				M()->rollback();
				cancelCacheLock('ErpStockInApply/addStockInOrder');
				return ['status' => 4,',message' => '缺少货权号！'];
			}
			/* -------------- 货权类型 ------------------ */
			if ( !isset($value['cargo_bn_type']) || empty($value['cargo_bn_type']) ) {
				M()->rollback();
				cancelCacheLock('ErpStockInApply/addStockInOrder');
				return ['status' => 5,',message' => '缺少货权类型！'];
			}
			$cargo_bn_result = $this->getEvent('ErpBatch',MODULE_NAME)->handleBatchCargoBn($value);
		    if($cargo_bn_result['status'] != 1){
		    	M()->rollback();
		    	cancelCacheLock('ErpStockInApply/addStockInOrder');
		    	$cargo_bn_result['message'] = '提交明细中存在重复货权号信息，请检查！';
		    	
	            return $cargo_bn_result;
	        }
	   //      if ( (int)trim($param['is_loss']) == 1 && (float)$value['loss_num'] == 0 ) {
	   //      	M()->rollback();
	   //      	cancelCacheLock('ErpStockInApply/addStockInOrder');
				// return ['status' => 7,'message' => '损耗数量不能为空！'];
	   //      }
	        // 入库单号
	        $stock_in_order_number = erpCodeNumber(8)['order_number'];
	        // 损耗单号
	        $loss_order_number    = '';
	        // 证明存在损耗 生成损耗单
	        if ( $value['loss_num'] != 0 && !empty($value['loss_num']) ) {
	        	if ( !isset($param['rational_loss_beliel']) || empty($param['rational_loss_beliel']) ) {
	        		M()->rollback();
					cancelCacheLock('ErpStockInApply/addStockInOrder');
					return ['status' => 2,'message' => '缺少比列！'];
				}
				/* ------------ 重新计算损耗的 合理损耗 和超损 （废弃 版本改动）-------------- */
				// $loss_num = trim($value['loss_num']);
				// $all_stock_num = $loss_num+$value['stock_in_num'];
				// // 合理损耗数量
				// $rational_loss = round($all_stock_num*lossRatioMath(trim($param['rational_loss_beliel'])),4);
				// // 损耗数量 - 合理损耗 = 超损
				// $exceed_loss_num = round($loss_num - $rational_loss,4);
				// // 超损 假如 < 0 那么 超损 = 0
				// if ( (float)$exceed_loss_num < (float)0 ) {
		  //           $exceed_loss_num = 0;
		  //       }
		  //       // 假如 合理损耗 > 损耗数量 那么 合理损耗 = 损耗数量
		  //       if ( (float)$rational_loss > (float)$loss_num ) {
		  //       	$rational_loss = $loss_num;
		  //       }
		  //       if ( (int)setNum($value['rational_loss']) != (int)setNum($rational_loss) || 
		  //       	 (int)setNum($value['supper_loss']) != (int)setNum($exceed_loss_num)
		  //   	) {
		  //   	// 	var_dump($value['rational_loss'],$rational_loss);
		  //   	// var_dump($value['supper_loss'],$exceed_loss_num); die;
		  //   		M()->rollback();
				// 	cancelCacheLock('ErpStockInApply/addStockInOrder');
		  //       	return ['status' => 22,'message' => '数据异常，请重新尝试'];
		  //       }
				/* ------------------- 验证合理损耗 + 超损 是否大于 损耗数量 ------------- */	
				if ( ((float)$value['rational_loss'] + (float)$value['supper_loss']) != (float)$value['loss_num'] ) {
					M()->rollback();
					cancelCacheLock('ErpStockInApply/addStockInOrder');
					return ['status' => 2,'message' => '存在合理损耗 + 超损 不等于 损耗数量 的数据！'];
				}
	        	// 损耗单号
	        	$loss_order_number = erpCodeNumber(23)['order_number'];
	        	/* ---------------—— 组装损耗单数据 ------------------ */
	        	$insert_loss_order_data[$key] = [
	        		'order_number'    => $loss_order_number,
	        		'source_number'   => $stock_in_order_number,
	        		// 'cargo_bn_id'     => $cargo_bn_result['cargo_bn_id'],
	        		'our_company_id'  => session('erp_company_id'),
	        		'type'            => 1,
	        		'company_id'      => $order_info['sale_company_id'],
	        		'dealer_id' 	  => $this->getUserInfo('id'),
	            	'dealer_name' 	  => $this->getUserInfo('dealer_name'),
	            	'region'          => $order_info['region'],
	            	'storehouse_id'   => $order_info['storehouse_id'],
	            	'goods_id'        => $stock_in_apply_arr['goods_id'],
	            	'loss_num'        => setNum($value['loss_num']),
	            	'reasonable_loss_num' => setNum($value['rational_loss']),
	            	'exceed_loss_num' => setNum($value['supper_loss']),
	            	'price'           => $order_info['price'],
	            	'creater'         => $this->getUserInfo('id'),
	            	'create_time'     => nowTime(),
	            	'loss_ratio'      => trim($param['rational_loss_beliel']),
	        	];
	        }
	        /* ---------------—— 组装入库单数据 ------------------ */
			$insert_stock_in_data[$key] = [
				'storage_code' 			=> $stock_in_order_number,
	            'storage_type' 			=> 1,
	            'storage_status' 		=> 1,
	            'storage_remark' 		=> $param['storage_remark'],
	            'source_number' 		=> $order_info['order_number'],
	            'source_object_id' 		=> $order_info['id'],
	            'our_company_id' 		=> session('erp_company_id'),
	            'goods_id' 				=> $stock_in_apply_arr['goods_id'],
	            'storage_num' 			=> setNum($value['stock_in_num']),
	            'actual_storage_num' 	=> setNum($value['stock_in_num']),
	            'outbound_density' 		=> $param['storage_density'],
	            'creater_id' 			=> $this->getUserInfo('id'),
	            'create_time' 			=> nowTime(),
	            'dealer_id' 			=> $this->getUserInfo('id'),
	            'dealer_name' 			=> $this->getUserInfo('dealer_name'),
	            'storehouse_id' 		=> $order_info['storehouse_id'],
	            'stock_type' 			=> $order_info['type'] == 1 ? getAllocationStockType($order_info['storehouse_id']) : 2,
	            'region' 				=> $order_info['region'],
	            'cargo_bn_id'			=> $cargo_bn_result['cargo_bn_id'],
	            'source_apply_number'   => $stock_in_apply_arr['storage_apply_code'],
	            // 'loss_order_number'     => $loss_order_number,
 			];
		}
		/* --------------- 修改入库申请单状态 ---------------- */
		$update_result = $this->getModel('ErpStockInApply')->where(['id'=>['eq',$stock_in_apply_arr['id']]])->save(['status' => 10]);
		if ( !$update_result ) {
			M()->rollback();
			cancelCacheLock('ErpStockInApply/addStockInOrder');
			return ['status' => 17, 'message' => '入库单状态更改失败！'];
		}

		/* --------------- 添加入库单 ---------------- */
		$add_result = $this->getModel('ErpStockIn')->addAll(array_merge($insert_stock_in_data));
		if ( !$add_result ) {
			M()->rollback();
			cancelCacheLock('ErpStockInApply/addStockInOrder');
			return ['status' => 16, 'message' => '入库单生成失败！'];
		}
		/* --------------- 添加损耗单 ---------------- */
		if ( !empty($insert_loss_order_data) ) {
			$add_loss_order_status = $this->getModel('ErpLossOrder')->addAll(array_merge($insert_loss_order_data));
	    	if ( !$add_loss_order_status ) {
	    		M()->rollback();
				cancelCacheLock('ErpStockInApply/addStockInOrder');
				return ['status' => 16, 'message' => '损耗单生成失败！'];
	    	}
		}
		M()->commit();
		cancelCacheLock('ErpStockInApply/addStockInOrder');
		return ['status' => 1, 'message' => '入库单生成成功！'];

		

	}

	/*********************************
		@ Content 查询入库单详情
		@ Author  YF
		@ Time    2019-04-16
		@ Param   [int] $[stock_in_apply_id] 入库申请单ID
	**********************************/
	public function findStockInApply( $param )
	{
		if ( !isset($param['stock_in_apply_id']) || empty($param['stock_in_apply_id']) ) {
			return ['status' => 2,'message' => '缺少ID字段！'];
		}
		$stock_in_apply_arr = $this->getModel("ErpStockInApply")->where(['id' => ['eq',trim($param['stock_in_apply_id'])]])->find();
		if ( !isset($stock_in_apply_arr['id']) ) {
			return ['status' => 11, 'message' => '入库申请单未查询到！'];
		}
		/* ----- 查询采购单信息 ------- */
		$field = 'id,sale_company_id,sale_user_id,goods_num,total_purchase_wait_num,storage_quantity,pay_type,buyer_dealer_name,buyer_dealer_id,type,add_order_time,
		business_type,delivery_method';
		$order_info = $this->getModel('ErpPurchaseOrder')->where(['id'=>['eq',$stock_in_apply_arr['source_object_id']]])->field($field)->find();
		if ( !isset($order_info['id']) ) {
			return ['status' => 12, 'message' => '未查询到采购单信息！'];
		}
		/* ----- 查询油库信息 ------- */
		if ( $stock_in_apply_arr['depot_id'] != 99999 ) {
			$depot_arr = $this->getModel('Depot')->where(['id'=>['eq',$stock_in_apply_arr['depot_id']]])->field('id,depot_name')->find();
			if ( !isset($depot_arr['id'])) {
				return ['status' => 13, 'message' => '未查询到油库信息！'];
			}
		} else {
			$depot_arr['depot_name'] = "不限油库";
		}
		
		if ( $order_info['business_type'] == 4 ) {
			$config_density = getConfig('Config_Density');
			$stock_in_apply_arr['good_density'] = $config_density;
		}		
		/* ----- 查询供应商公司 ------- */
		$supplier_name_arr = $this->getModel('ErpSupplier')->where(['id'=>['eq',$order_info['sale_company_id']]])->field('id,supplier_name')->find();
		if ( !isset($supplier_name_arr['id']) ) {
			return ['status' => 14, 'message' => '未查询到供应商 公司！'];
		}
		$user_name_arr = $this->getModel('ErpSupplierUser')->where(['id'=>['eq',$order_info['sale_user_id']]])->field('id,user_name')->find();
		if ( !isset($user_name_arr['id']) ) {
			return ['status' => 15 ,'message' => '为查询到用户信息！'];
		}
		/* ----- 查询商品信息 ------- */
		$goods_arr = $this->getModel('ErpGoods')->where(['id'=>['eq',$stock_in_apply_arr['goods_id']]])->find();
		if ( !isset($goods_arr['id']) ) {
			return ['status' => 16 ,'message' => '商品信息未查询到！'];
		}
		/* ----- 查询仓库信息 ------- */
		$storehouse_arr = $this->getModel('ErpStorehouse')->where(['id' => ['eq',$stock_in_apply_arr['storehouse_id']]])->field('id,storehouse_name')->find();
		if ( !isset($storehouse_arr['id']) ) {
			return ['status' => 17 ,'message' => '未查询到仓库信息！'];
		}
		$stock_not_in = $order_info['pay_type'] == 5 ? getNum($order_info['total_purchase_wait_num'] - $order_info['storage_quantity']) : getNum($order_info['goods_num'] - $order_info['storage_quantity']);
		$stock_in_apply_arr['surplus_stock_num'] = $stock_not_in;
		$stock_in_apply_arr['goods_num']         = getNum($order_info['goods_num']);
		$stock_in_apply_arr['depot_name'] 		 = $depot_arr['depot_name'];
		$stock_in_apply_arr['supplier_name'] 	 = $supplier_name_arr['supplier_name'];
		$stock_in_apply_arr['user_name'] 		 = $user_name_arr['user_name'];
		$stock_in_apply_arr['goods_name'] 		 = $goods_arr['goods_code']."/".$goods_arr['goods_name']."/".$goods_arr['source_from']."/".$goods_arr['grade']."/".$goods_arr['level'];
		$stock_in_apply_arr['storage_apply_num'] = getNum($stock_in_apply_arr['storage_apply_num']);
		$stock_in_apply_arr['storehouse_name']   = $storehouse_arr['storehouse_name'];
		$stock_in_apply_arr['buyer_dealer_name'] = $order_info['buyer_dealer_name'];
		$stock_in_apply_arr['buyer_dealer_id']	 = $order_info['buyer_dealer_id'];
		$stock_in_apply_arr['is_agent']          = $order_info['type'];
		$stock_in_apply_arr['business_type']     = $order_info['business_type'];
		$stock_in_apply_arr['add_order_time']    = date('Y-m-d',strtotime($order_info['add_order_time']));
		$stock_in_apply_arr['delivery_method']   = $order_info['delivery_method'];
		return ['status' => 1,'message' => '查询成功！','data' => $stock_in_apply_arr];
	}

	/**********************************
         @ Content 采购单生成入库申请单验证
         @ Time    2019-04-24
         @ Author  YF
    ***********************************/
	public function checkAddStockInApply( $param )
	{
		if ( !isset( $param['purchase_order_id']) || empty($param['purchase_order_id']) ) {
			return ['status' =>12,'message' =>'缺少采购单ID！'];
		}
		$data['may_apply_num'] = $this->countMayApplyNum(trim($param['purchase_order_id']))['data'];
	    // The following is not written by me. It's copied.
		$status = $this->getEvent('ErpPurchase')->getPurchaseOrderStatus( trim($param['purchase_order_id']) );
        $data['is_inner_order'] = 2;
        if ( !empty($status['retail_inner_order_number']) && $status['business_type'] == 6 ) {
            $data['is_inner_order'] = 1;
        }
        $data['status'] = $status['order_status'];
        $data['pay_type'] = $status['pay_type'];
        $data['pay_status'] = $status['pay_status'];
        return $data;
	}

	/*************************************
		@ Content 生成入库单之前验证
        @ Author  Yf
        @ Time    2019-04-18
	**************************************/
	public function checkAddStockIn($param)
	{
		if ( !isset($param['stock_in_apply_id']) || empty($param['stock_in_apply_id']) ) {
			return ['status' => 11 ,'message' => '缺少ID字段！'];
		}
		$apply_info = $this->getModel("ErpStockInApply")->where(['id' => ['eq',trim($param['stock_in_apply_id'])]])->find();
		if ( !isset($apply_info['id']) ) {
			return ['status' => 21 , "message" => "未获取到入库申请单，请核查!"];
		}
		if( $apply_info['status'] == 10 ){
            return ['status' => 22 , "message" => "该笔申请单已完成入库单转换!"];
        } elseif ( $apply_info['status'] == 2 ) {
        	return ['status' => 22 , "message" => "只有已创建的申请单才能生成入库单！"];
        }

        //判断申请单是否生成出库单
        $stock_in_Where = [
            "is_reverse" 			=> 2 ,
            "reversed_status" 		=> 2 ,
            "source_apply_number" 	=> $apply_info['outbound_apply_code'],
            "outbound_status"		=> ['neq' , 2]
        ];
        $stock_in_count = $this->getModel('ErpStockIn')->where($stock_in_Where)->count();
        if($stock_in_count > 0){
            return ['status' => 23 , "message" => "申请入库单信息已生成入库单，不可继续申请入库单"] ;
        }

        $order_info = $this->getModel('ErpPurchaseOrder')->where(['id' => ['eq',$apply_info['source_object_id']]] )->find();
        
		if (
            (	($order_info['pay_type'] == 1 && $order_info['pay_status'] != 10)   ||
                ($order_info['pay_type'] == 2 && $order_info['pay_status'] <= 2)    ||
                ($order_info['pay_type'] == 3 && $order_info['order_status'] != 10) ||
                ($order_info['pay_type'] == 4 && $order_info['pay_status'] != 10)   ||
                ($order_info['pay_type'] == 5 && $order_info['pay_status'] <= 2)
            ) || $order_info['order_status'] != 10
        ) {
        	return ['status' => 5, 'message' => '该采购单未达到入库条件，无法生成入库单申请单！'];
        }
        if ($order_info['is_returned'] == 1) {
        	return ['status' => 6, 'message' => '已退货订单无法生成入库申请单'];
        }
        return ['status' => 1,'message' => '可以生成入库单！'];
	}
}