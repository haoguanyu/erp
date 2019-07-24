<?php
namespace Common\Model;
use Common\Model\BaseModel;


class SmsListModel extends BaseModel
{


/////////////////////////////////基础层///////////////////////////////////////////////


    public function selectData($field = '*', $where = [])
    {
        return $this->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return $this->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return $this->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return $this->where($where)->save($data);
    }


}


?>
