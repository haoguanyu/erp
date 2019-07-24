<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 零售订单预结算单表oil_retail_settlement_order | 模型
 * Author：jk        Time：2016-09-18
 * ----------------------------------------
 */

class RetailSettlementOrderModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];

    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_settlement_order')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_settlement_order')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_settlement_order')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        return M('retail_settlement_order')->where($where)->save($data);
    }

    public function deleteData($where = [])
    {
        if (count($where) <= 0) return false;

        return M('retail_settlement_order')->where($where)->delete();
    }

/////////////////////////////////////业务处理层///////////////////////////////////////


}


?>
