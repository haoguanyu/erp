<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 * 供货单模型
 */
class ErpOrderModel extends BaseModel
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
    public function getOrderList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $orderObj = M('ErpOrder');
        $data['recordsTotal'] = $this->getOrderCount($where);
        $data['sumTotal'] = $this->getOrderTotal($where);
        $data['data'] = $orderObj->alias('o')
            ->field($field)
            ->where($where)
            // ->join('oil_erp_order_extend e on e.order_id = o.id', 'left')
            ->join('oil_erp_supply s on s.id = o.supply_id', 'left')
            //->join('oil_erp_supply_log sl on sl.supply_id = s.id', 'left')
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_user us on o.sale_user_id = us.id', 'left')
            ->join('oil_clients cs on o.sale_company_id = cs.id', 'left')
            ->join('oil_user ub on o.buy_user_id = ub.id', 'left')
            ->join('oil_clients cb on o.buy_company_id = cb.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-13
     */
    public function getOrderCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('__ERP_SUPPLY__ s on s.id = o.supply_id', 'left')
            ->join('__ERP_GOODS__ g on s.goods_id = g.id', 'left')
            ->count();
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-13
     */
    public function getOrderTotal($where)
    {
        return $this->alias('o')
            ->field('sum(buy_num)/'.C('IO_NUM').' as total_buy_num')
            ->where($where)
            ->join('__ERP_SUPPLY__ s on s.id = o.supply_id', 'left')
            ->join('__ERP_GOODS__ g on s.goods_id = g.id', 'left')
            ->find();
    }

    /**91229
     *  修改保存供货单
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-13
     */
    public function saveOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加供货单
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-13
     */
    public function addOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-13
     */
    public function findOrder($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-13
     */
    public function findOneOrderInfo($where = [], $field = '*, o.id as erp_order_id')
    {
        $orderObj = M('ErpOrder');
        $data = $orderObj->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_supply s on s.id = o.supply_id', 'left')
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_user us on o.sale_user_id = us.id', 'left')
            ->join('oil_clients cs on o.sale_company_id = cs.id', 'left')
            ->join('oil_user ub on o.buy_user_id = ub.id', 'left')
            ->join('oil_clients cb on o.buy_company_id = cb.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
            ->find();
        return $data;
    }
}
