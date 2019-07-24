<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 零售订单设备表oil_retail_equipment | 模型
 * Author：jk        Time：2016-09-13
 * ----------------------------------------
 */

class RetailEquipmentModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_equipment')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_equipment')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_equipment')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_equipment')->where($where)->save($data);
    }


}


?>
