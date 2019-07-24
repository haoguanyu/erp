<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 零售订单状态变化时间记录表oil_retail_order_status_time | 模型
 * Author：jk        Time：2016-09-14
 * ----------------------------------------
 */

class RetailOrderStatusTimeModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_order_status_time')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_order_status_time')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_order_status_time')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_order_status_time')->where($where)->save($data);
    }

    public function addAllData($data = [])
    {
        if (count($data) <= 0) return false;

        return M('retail_order_status_time')->addAll($data);
    }


}


?>
