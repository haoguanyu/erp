<?php
namespace Common\Model;

use Common\Model\BaseModel;

class ErpShippingOrderModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getShippingOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getShippingOrderCount($where);
        $data['sumTotal'] = $this->getShippingOrderTotal($where);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->select();
        } else {

            $data['data'] = [];
        }
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getShippingOrderCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->count();
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getShippingOrderTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(o.shipping_num)/'.C('IO_NUM').' as total_shipping_num')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->find();
        return $result;
    }

    /**
     *  修改保存
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function saveShippingOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addShippingOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findShippingOrder($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 获取一条信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findOneShippingOrder($where = [], $field = true)
    {
        return $this->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->find();
    }
}
