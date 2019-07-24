<?php
namespace Common\Model;

use Common\Model\BaseModel;

class ErpStockInApplyModel extends BaseModel
{

	/******************************
		@ Content 入库申请单列表
        @ Author  YF
        @ Time    2019-04-15
	*******************************/
	public function stockInApplyList($where = [], $field = '*', $offset = 0, $limit = 0, $order = 'id desc'){
		$data['recordsTotal'] = $this->where($where)->field('id')->count();
        if ($data['recordsTotal']) {
            $sql = $this->field($field)->where($where)->limit($offset, $limit);
            $data['data'] = $sql->order($order)->select();
        } else {
            $data['data'] = [];
            $data['recordsTotal'] = 0;
        }
        return $data;
	}

}