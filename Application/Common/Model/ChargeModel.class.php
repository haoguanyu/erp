<?php
namespace Common\Model;

use Common\Model\BaseModel;

class ChargeModel extends BaseModel
{

    public function selectData($field = '*', $where = [])
    {
        return M('charge')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('charge')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('charge')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('charge')->where($where)->save($data);
    }

}
