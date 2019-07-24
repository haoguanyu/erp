<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 退货单模型
 */
class ErpReturnedOrderModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author guanyu
     * @time 2017-08-17
     */
    public function getReturnedOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'ro.id desc')
    {
        $data['recordsTotal'] = $this->getReturnedOrderCount($where,$field);
        $data['sumTotal'] = $this->getReturnedOrderTotal($where,$field);
        if ($where['ro.order_type'] == 1) {
            $table = 'oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            $dealer = 'oil_dealer dl on o.dealer_id = dl.id';
        } elseif ($where['ro.order_type'] == 2) {
            $table = 'oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            $dealer = 'oil_dealer dl on o.buyer_dealer_id = dl.id';
        }
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('ro')
                ->field($field)
                ->where($where)
                ->join($table, 'left')
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_storehouse s on o.storehouse_id = s.id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer cdl on ro.creater_id = cdl.id', 'left')
                ->join($dealer, 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param string $order
     * @return array
     * @author guanyu
     * @time 2017-08-17
     */
    public function getAllReturnedOrderList($where = [], $field = '', $order = 'ro.id desc')
    {
        $data['recordsTotal'] = $this->getReturnedOrderCount($where,$field);
        if ($where['ro.order_type'] == 1) {
            $table = 'oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            $dealer = 'oil_dealer dl on o.dealer_id = dl.id';
        } elseif ($where['ro.order_type'] == 2) {
            $table = 'oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            $dealer = 'oil_dealer dl on o.buyer_dealer_id = dl.id';
        }
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('ro')
                ->field($field)
                ->where($where)
                ->join($table, 'left')
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_storehouse s on o.storehouse_id = s.id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer cdl on ro.creater_id = cdl.id', 'left')
                ->join($dealer, 'left')
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
     * @author guanyu
     * @time 2017-08-17
     */
    public function getReturnedOrderCount($where = [])
    {
        if ($where['ro.order_type'] == 1) {
            $table = 'oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            //$user = 'oil_user u on o.user_id = u.id';
            //$clients = 'oil_clients c on o.company_id = c.company_id';
            $dealer = 'oil_dealer dl on o.dealer_id = dl.id';
        } elseif ($where['ro.order_type'] == 2) {
            $table = 'oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            //$user = 'oil_user u on o.sale_user_id = u.id';
            //$clients = 'oil_clients c on o.sale_company_id = c.company_id';
            $dealer = 'oil_dealer dl on o.buyer_dealer_id = dl.id';
        }
        $result = $this->alias('ro')
            ->where($where)
            ->join($table, 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            //->join($clients, 'left')
            //->join($user, 'left')
            ->join('oil_erp_storehouse s on o.storehouse_id = s.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer cdl on ro.creater_id = cdl.id', 'left')
            ->join($dealer, 'left')
            ->count();
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2017-08-17
     */
    public function getReturnedOrderTotal($where = [])
    {
        if ($where['ro.order_type'] == 1) {
            $table = 'oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            //$user = 'oil_user u on o.user_id = u.id';
            //$clients = 'oil_erp_company c on o.our_company_id = c.company_id';
            $dealer = 'oil_dealer dl on o.dealer_id = dl.id';
        } elseif ($where['ro.order_type'] == 2) {
            $table = 'oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            //$user = 'oil_user u on o.sale_user_id = u.id';
            //$clients = 'oil_erp_company c on o.our_buy_company_id = c.company_id';
            $dealer = 'oil_dealer dl on o.buyer_dealer_id = dl.id';
        }
        $result = $this->alias('ro')
            ->field('sum(ro.return_goods_num)/'.C('IO_NUM').' as total_return_goods_num, sum(ro.return_price * ro.return_goods_num)/'.C('IO_NUM').'/'.C('IO_NUM').' as total_returned_amount')
            ->where($where)
            ->join($table, 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            //->join($clients, 'left')
            //->join($user, 'left')
            ->join('oil_erp_storehouse s on o.storehouse_id = s.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join($dealer, 'left')
            ->find();
        return $result;
    }

    /**
     *  修改保存退货单
     * @param array $where
     * @param array $data
     * @return bool
     * @author guanyu
     * @time 2017-08-17
     */
    public function saveReturnedOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加退货单
     * @param array $data
     * @return bool
     * @author guanyu
     * @time 2017-08-17
     */
    public function addReturnedOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条退货单信息
     * @param $where
     * @param $field
     * @return array
     * @author guanyu
     * @time 2017-08-17
     */
    public function findOneReturnedOrder($where = [], $field = true)
    {
        $result = $this->field($field)->where($where)->find();
        return $result;
    }

    /**
     * 获取一条完整退货单信息
     * @param $where
     * @param $field
     * @return array
     * @author guanyu
     * @time 2017-08-17
     */
    public function findReturnedOrder($where = [], $field = true)
    {
        if ($where['ro.order_type'] == 1) {
            $table = 'oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            //$user = 'oil_user u on o.user_id = u.id';
            //$clients = 'oil_clients c on o.company_id = c.company_id';
            $dealer = 'oil_dealer dl on o.dealer_id = dl.id';
        } elseif ($where['ro.order_type'] == 2) {
            $table = 'oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
            //$user = 'oil_user u on o.sale_user_id = u.id';
            //$clients = 'oil_clients c on o.sale_company_id = c.company_id';
            $dealer = 'oil_dealer dl on o.buyer_dealer_id = dl.id';
        }
        $result = $this->alias('ro')
            ->field($field)
            ->where($where)
            ->join($table, 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
           // ->join($clients, 'left')
           // ->join($user, 'left')
            ->join('oil_erp_storehouse s on o.storehouse_id = s.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer cdl on ro.creater_id = cdl.id', 'left')
            ->join($dealer, 'left')
            ->find();
        return $result;
    }

    /**
     * 获取一条退货单关联原单据信息
     * @param $where
     * @param $field
     * @return array
     * @author guanyu
     * @time 2017-08-17
     */
    public function findReturnedOrderJoinOrder($where = [], $field = true)
    {
        if ($where['ro.order_type'] == 1) {
            $table = 'oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
        } elseif ($where['ro.order_type'] == 2) {
            $table = 'oil_erp_purchase_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number';
        }
        $result = $this->alias('ro')
            ->field($field)
            ->where($where)
            ->join($table, 'left')
            ->find();
        return $result;
    }


}
