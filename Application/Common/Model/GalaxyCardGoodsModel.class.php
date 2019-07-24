<?php
namespace Common\Model;
use Common\Model\BaseModel;


class GalaxyCardGoodsModel extends BaseModel
{


    public function _initialize()
    {
        $this->galaxy_card_goods = M('galaxy_card_goods');
    }

/////////////////////////////////基础层///////////////////////////////////////////////


    public function selectAll($where = [], $order = 'id DESC')
    {
        if (!is_array($where)) return [];
        return $this->galaxy_card_goods->where($where)->order($order)->select();
    }

    public function findOne($where = [])
    {
        if (!is_array($where) || empty($where)) return [];

        return $this->galaxy_card_goods->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (empty($where) || empty($data)) return false;

        return $this->galaxy_card_goods->where($where)->save($data);
    }


}


?>
