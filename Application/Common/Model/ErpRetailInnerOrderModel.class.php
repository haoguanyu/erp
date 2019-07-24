<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 内不交易单模型模型
 */
class ErpRetailInnerOrderModel extends BaseModel
{

		/**************************************
			@ Content 获取内部交易单
		***************************************/
		public function getInternalOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'id desc')
	    {
	        $data['recordsTotal'] = $this->getInternalOrderCount($where);
	        if ($data['recordsTotal']) {
	            $data['data'] = $this->field($field)
					                 ->where($where)
					                 ->limit($offset, $limit)
					                 ->order($order)
					                 ->select();
	        } else {
	            $data['data'] = [];
	        }
	        return $data;
	    }

	    /***********************************
			@ Content 获取 内部订单 数量
	    ***********************************/
	    public function getInternalOrderCount($where = [])
	    {
	    	return  $this->where($where)->count();
	    }

}