<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 订单附表模型（图片，配送批次用）
 */
class ErpOrderExtendModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-13
     */
    public function getDeliveryList($where = [], $field = true, $order = 'delivery_time desc')
    {
        $orderObj = M('ErpOrderExtend');
        $data['data'] = $orderObj
            ->field($field)
            ->where($where)
            ->order($order)
            ->select();
        return $data;
    }


    /**
     *  修改保存
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-13
     */
    public function saveOrderExtend($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-13
     */
    public function addOrderExtend($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-13
     */
    public function findOrderExtend($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

}
