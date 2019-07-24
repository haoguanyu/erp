<?php
namespace Common\Model;
use Common\Model\BaseModel;

class GalaxyOrderModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->galaxy_order = M('galaxy_order');
    }


/////////////////////////////////基础层///////////////////////////////////////////////


    public function findOne($where = [], $field = '*')
    {
        if (!is_array($where) || empty($where)) return [];

        return $this->galaxy_order->field($field)->where($where)->find();
    }

    public function selectData($where = [], $field = '*', $order = 'id DESC')
    {

        return $this->galaxy_order->field($field)->where($where)->order($order)->select();
    }


}


?>
