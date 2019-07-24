<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * 其他入其他出逻辑层
 * @author xiaowen
 * @time 2019-5-15
 */
class ErpOtherStockEvent extends BaseController
{	
	/**
     *  其他入\出库单列表
     * 	@author yf
     * 	@time 2018-05-14
     */
	public function erpStockList( $param ){
		$where = $this->stockWhere($param);
		/* --------------——— 查询数据 -------------------- */
		$field  		= "stock.*,loss.type,loss.company_id,loss.price";
		$offset 		= $param['start'];
		$limit  		= $param['length'];
		/* ---------- 1 : 入库单 2： 出库单 -------------*/
		$stock_type 	= $param['otherStock'];
		$other_stock_arr = $this->getStockList($stock_type,$where,$offset,$limit,$field);
		if ( empty($other_stock_arr['data']) ) {
			return ['data' => [],'recordsFiltered' => 0,'recordsTotal'=> 0,'draw' => $param['draw']];
		}
		$result_arr = $this->handleStockIn($other_stock_arr['data'],$stock_type);
		if ( isset($param['draw']) ) {
			$stock_num_type = $param['otherStock'] == 1 ? 'actual_storage_num' : 'actual_outbound_num';
			$fields = 'stock.'.$stock_num_type.',loss.price';
			$count_stock = $this->getStockList($stock_type,$where,0,0,$fields);
			$sum_total = [];
			$all_price = 0;
			$stock_num = 0;
			foreach ($count_stock['data'] as $key => $value) {
				$all_price += round(getNum($value['price']) * getNum($value[$stock_num_type]),2);
				$stock_num += getNum($value[$stock_num_type]);
			}
			$sum_total['page_all_price'] 	 	= array_sum(array_column($result_arr, 'all_price'));
			$sum_total['page_all_stock_num'] 	= round(array_sum(array_column($result_arr, 'stock_num')),4);
			$sum_total['all_price'] 			= round($all_price,2);
			$sum_total['all_stock_num'] 		= round($stock_num,4);

			$result_arr[0]['sum_total'] 		= $sum_total;
		}
		return [
			'data' 				=> $result_arr,
			'recordsFiltered' 	=> $other_stock_arr['recordsTotal'],
			'recordsTotal'		=> $other_stock_arr['recordsTotal'],
			'draw' 				=> $param['draw'],
		];
	}

	/**
     *  其他入库单查询条件
     * 	@author yf
     * 	@time 2018-05-14
     */
	public function stockWhere($param){
		$where = [];
		// 账套区分
		$where['stock.our_company_id'] = ['eq',session('erp_company_id')];
		
		$where['stock.is_other'] = ['eq',1];
		// 入库单号
		if ( isset($param['storage_code']) && !empty($param['storage_code']) ) {
			$where['stock.storage_code'] = ['like', ['%' . trim($param['storage_code']) . '%']];
		}

		// 出库单号
		if ( isset($param['outbound_code']) && !empty($param['outbound_code']) ) {
			$where['stock.outbound_code'] = ['like', ['%' . trim($param['outbound_code']) . '%']];
		}

		// 来源单号
		if ( isset($param['source_number']) && !empty($param['source_number']) ) {
			$where['stock.source_number'] = ['like', ['%' . trim($param['source_number']) . '%']];
		}

		// 损耗单类型
		if ( isset($param['type']) && !empty($param['type']) ) {
			$where['loss.type'] = ['like', ['%' . trim($param['type']) . '%']];
		}

		// 出库单状态
		if ( isset($param['outbound_status']) && !empty($param['outbound_status']) ) {
			$where['stock.outbound_status'] = ['eq',$param['outbound_status']];
		}

		// 损耗类型
		if ( isset($param['loss_type']) && !empty($param['loss_type']) ) {
			$where['stock.loss_type'] = ['eq',$param['loss_type']];
		}

		// 入库单状态
		if ( isset($param['storage_status']) && !empty($param['storage_status']) ) {
			$where['stock.storage_status'] = ['eq',$param['storage_status']];
		}

		// 公司来源
		if ( (isset($param['company_name']) && !empty($param['company_name'])) && isset($param['type']) && !empty($param['type']) ) {
			$where['type'] = ['eq',trim($param['type'])];
			if ( $param['type'] == 1 || $param['type'] == 2 ) {
				$table = 'ErpSupplier';
				$where_name = 'supplier_name';			
			}elseif ($param['type'] == 3) {
				$table = 'ErpCustomer';
				$where_name = 'customer_name';
			}
			$company_where[$where_name] = ['like', ['%' . trim($param['company_name']) . '%']];
			$company_arr   = $this->getModel($table)->where($company_where)->field('id')->select();
			if ( !empty($company_arr) ) {
				$where['loss.company_id'] = ['in',array_column($company_arr, 'id')];
			}else{
				$where['loss.company_id'] = ['eq',0];
			}
		// 损耗来源
		}elseif ( isset($param['type']) && !empty($param['type']) ) {
			$where['loss.type'] = ['eq',trim($param['type'])];
		}


		if ( (isset($param['start_time']) &&  !empty($param['start_time'])) && ( isset($param['end_time']) && !empty($param['end_time'])) ) {
				$where['stock.create_time'] = ['between',[trim($param['start_time']),trim($param['end_time']." 23:59:59")]];
			} else {
			if ( isset($param['start_time']) && !empty($param['start_time']) ) {
				$where['stock.create_time'] = ['GT',trim($param['start_time'])];
			} elseif ( isset($param['end_time']) && !empty($param['end_time']) ) {
				$where['stock.create_time'] = ['ELT',trim($param['end_time']." 23:59:59")];
			}
		}
		return $where;
	}


	/**
     *  处理其他 出\入库单 数据
     * 	@author yf
     * 	@time 2018-05-14
     */
	public function handleStockIn( $stock_arr = [] ,$type = 0){
		/* ------------- 获取下面所需要的数据 ------------ */
		$storehouse_id = array_unique(array_column($stock_arr, 'storehouse_id'));
		$goods_id      = array_unique(array_column($stock_arr, 'goods_id'));
		$region        = array_unique(array_column($stock_arr, 'region'));
		$storehouse_arr = $this->getModel("ErpStorehouse")->where(['id' => ['in',$storehouse_id]])->getField('id,storehouse_name');
		$region_arr     = $this->getModel("Area")->where(['id' => ['in',$region]])->getField('id,area_name');
		$good_list      = $this->getModel("ErpGoods")->where(['id'=>['in',$goods_id]])->field('id,goods_code,goods_name,source_from,grade,level')->select();
        foreach ($good_list as $value){
            $good_list[$value['id']] = $value['goods_code']."/".$value['goods_name']."/".$value['source_from']
                ."/".$value['grade']."/".$value['level'];
        }
        $stock_arr_supplier = [];
        $stock_arr_customer = [];
        /* ------------------ end ------------------- */
        // echo "<pre>";
        // print_r($stock_arr);die;
		foreach ($stock_arr as $key => $value) {
			$value['type_name'] 		= lossTypeStatus($value['type']);
			$value['loss_type_name'] 	= $value['loss_type'] == 1 ? '超损' : '合理损耗';
			$value['region_name']  		= isset($region_arr[$value['region']]) ? $region_arr[$value['region']] : '--';
			$value['storehouse_name']  	= isset($storehouse_arr[$value['storehouse_id']]) ? $storehouse_arr[$value['storehouse_id']] : '--';
			$value['goods_name']  		= isset($good_list[$value['goods_id']]) ? $good_list[$value['goods_id']] : '--';
			
			$value['price'] 			= getNum($value['price']);
			// 入库单数据
			if ( $type == 1 ) {
				$value['order_status_name'] = stockInStatus($value['storage_status']);
				$value['stock_num'] 		= getNum($value['actual_storage_num']);
				$value['all_price']			= $value['price'];
			// 出库单数据
			} elseif ( $type == 2 ) {
				$value['cost']				  = round(getNum($value['cost']),2);
				$value['order_status_name']   = stockOutStatus($value['outbound_status']);
				$value['stock_num'] 		  = getNum($value['actual_outbound_num']);
				$value['all_price']			  = round($value['stock_num'] * $value['price'],2);
				$value['source_freight_order'] = trim($value['source_freight_order']) ? trim($value['source_freight_order']) : '--';
			}

			if ( $value['type'] == 1 || $value['type'] == 2 ) {
				$stock_arr_supplier[$key] = $value;
			}elseif ($value['type'] == 3 ) {
				$stock_arr_customer[$key] = $value;
			}
		}
		if ( !empty($stock_arr_supplier) ) {
			$supplier_id = array_unique(array_column($stock_arr_supplier, 'company_id'));
			$supper_arr    = $this->getModel('ErpSupplier')->where(['id'=>['in',$supplier_id]])->getField('id,supplier_name');
			foreach ($stock_arr_supplier as $key => $value) {
				$stock_arr[$key] = $value;
				$stock_arr[$key]['company_name'] = isset($supper_arr[$value['company_id']]) ? $supper_arr[$value['company_id']] : '--';
			}
		}
		if ( !empty($stock_arr_customer) ) {
			$customer_id       = array_unique(array_column($stock_arr_customer, 'company_id'));
			$customer_arr = $this->getModel('ErpCustomer')->where(['id'=>['in',$customer_id]])->getField('id,customer_name');
			foreach ($stock_arr_customer as $key => $value) {
				$stock_arr[$key] = $value;
				$stock_arr[$key]['company_name'] = isset($customer_arr[$value['company_id']]) ? $customer_arr[$value['company_id']] : '--';
			}
		}

		return $stock_arr;
	}

	/**
     *  查询数据
     * 	@author yf
     * 	@time 2018-05-14
     */
	public function getStockList($table_type = 0,$where = [],$page = 0 ,$limit = 0,$field = '*'){
		if ( $table_type == 1 ) {
			$table_name = 'ErpStockIn';
		}elseif ( $table_type == 2 ) {
			$table_name = 'ErpStockOut';
		}
		$stock_count = $this->getModel($table_name)->alias('stock')
		->join('oil_erp_loss_order loss on loss.order_number = stock.source_number', 'left')
		->where($where)->field('stock.id')->order('stock.id desc')->count();
		if ( empty($stock_count) ) {
			return ['recordsTotal' => 0,'data' => [] ];
		}
		$stock_arr = $this->getModel($table_name)->alias('stock')
		->join('oil_erp_loss_order loss on loss.order_number = stock.source_number', 'left')
		->where($where)->limit($page,$limit)->field($field)->order('stock.id desc')->select();
		return ['recordsTotal' => $stock_count,'data' => $stock_arr];
	}



}


