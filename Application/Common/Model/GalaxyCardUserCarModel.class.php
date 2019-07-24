<?php
namespace Common\Model;
use Common\Model\BaseModel;


class GalaxyCardUserCarModel extends BaseModel
{


    public function _initialize()
    {
        $this->galaxy_card_user_car = M('galaxy_card_user_car');
    }

/////////////////////////////////基础层///////////////////////////////////////////////


    public function selectAll($where = [], $order = 'id DESC')
    {
        if (!is_array($where)) return [];
        return $this->galaxy_card_user_car->where($where)->order($order)->select();
    }

    public function findOne($where = [])
    {
        if (!is_array($where) || empty($where)) return [];

        return $this->galaxy_card_user_car->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (empty($where) || empty($data)) return false;

        return $this->galaxy_card_user_car->where($where)->save($data);
    }


}


?>
