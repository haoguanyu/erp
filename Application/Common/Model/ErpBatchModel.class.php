<?php
namespace Common\Model;

use Common\Model\BaseModel;

class ErpBatchModel extends BaseModel
{

	/******************************
		@ Content 获取批次列表
	*******************************/
	public function erpBatchList($where = [], $field = '', $offset = 0, $limit = 0, $order = 'b.id desc'){
		$data['recordsTotal'] = $this->alias('b')->where($where)->field('b.id')->count();
        if ($data['recordsTotal']) {
            $batchSql = $this
                ->alias('b')
                ->field($field)
                ->where($where);
            if( !empty($limit) && $limit > 0){
                $batchSql = $batchSql->limit($offset, $limit);
            }
            $data['data'] = $batchSql
                ->order($order)
                ->select();
        } else {
            $data['data'] = [];
            $data['recordsTotal'] = 0;
        }
        return $data;
	}

    /******************************
    @ Content 获取批次被出库单使用列表
     *******************************/
    public function erpBatchUseList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'b.id desc'){
        $data['recordsTotal'] = $this->getBatchCount($where);
        if ($data['recordsTotal']) {
            $sql = $this
                ->alias('b')
                ->join('oil_erp_stock_out so on so.batch_id = b.id', 'left')
                ->field($field)
                ->where($where) ;
            if(!empty($limit) && $limit > 0){
                $sql = $sql->limit($offset, $limit);
            }
            $data['data'] = $sql->group("b.id")
                ->order($order)
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /*******************************
        @ Content 根据采购退货单 获取 批次列表
    ********************************/
    public function erpBatchListByPurchaseReturnOrder( $where = [] ,$field = '',$offset = 0, $limit = 0, $order = 'b.id desc'){
        $data['recordsTotal'] = $this->alias('b')
                                    ->join('oil_erp_stock_in so on so.batch_id = b.id', 'left')
                                    ->where($where)
                                    ->order($order)
                                    ->count();
        if ( $data['recordsTotal'] ) {
            $batch_sql = $this->alias('b')
            ->join('oil_erp_stock_in so on so.batch_id = b.id', 'left')
            ->field($field)
            ->where($where);
            if ( !empty($limit) ) {
                $batch_sql->limit($offset, $limit);
            } 
            $data['data'] = $batch_sql
            ->order($order)
            ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

	/*******************************
		@ Content 根据条件查询条数
	********************************/
	public function getBatchCount( $where = [] ){
		return  $this->alias('b')
            ->join('oil_erp_stock_out so on so.batch_id = b.id', 'left')
            ->where($where)
            ->field('b.id')
            ->count();
	}
	/*
	 * @params
	 *  where:条件
	 *  field：查询信息
	 * @return：array
	 * @desc:查询相关的数据
	 * @author:小黑
	 * @time:2019-2-25
	 */
	public function getFieldList($field , $where){
	    return $this->where($where)->getField($field) ;
    }
    /*
	 * @params
	 *  where:条件
	 *  field：查询信息
	 * @return：array
	 * @desc:查询相关的数据
	 * @author:小黑
	 * @time:2019-3-12
	 */
    public function getFieldInfo($field , $where){
        return $this->where($where)->getField($field) ;
    }
    /*
    * @params:
    *      $field，查询的字段
    *      $where:查询条件
    * @return: array
    * @auth:小黑
    * @time:2019-5-7
    * @desc:根据条件查询批次和货权号的信息
    */
    public function getBatchCargo($field , $where){
        return  $this->alias("b")
            ->join("oil_erp_cargo_bn c on c.id = b.cargo_bn_id")
            ->where($where)->getField($field);
    }
}