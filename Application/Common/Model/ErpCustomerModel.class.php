<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 供应商模型
 */
class ErpCustomerModel extends BaseModel
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
    public function getCustomerList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getCustomerCount($where);
        if ($data['recordsTotal']) {

            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
//                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//                ->join('oil_clients ec on o.our_buy_company_id = ec.id', 'left')
//                ->join('oil_user us on o.sale_user_id = us.id', 'left')
//                ->join('oil_clients cs on o.sale_company_id = cs.id', 'left')
//                ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
//                ->join('oil_depot d on o.depot_id = d.id', 'left')
//                ->join('oil_dealer dl on o.buyer_dealer_id = dl.id', 'left')
//                ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
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
    public function getCustomerCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
//            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//            //->join('oil_erp_company ec on o.our_buy_company_id = ec.company_id', 'left')
//            ->join('oil_clients ec on o.our_buy_company_id = ec.id', 'left')
//            ->join('oil_user us on o.sale_user_id = us.id', 'left')
//            ->join('oil_clients cs on o.sale_company_id = cs.id', 'left')
//            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->count();
        return $result;
    }

    /**
     *  修改供应商
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function saveCustomer($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加供应商
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addCustomer($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findCustomer($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findOnePurchaseOrder($where = [], $field = '*')
    {

        $data = $this->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_clients ec on o.our_buy_company_id = ec.id', 'left')
            ->join('oil_user us on o.sale_user_id = us.id', 'left')
            ->join('oil_dealer dl on o.buyer_dealer_id = dl.id', 'left')
            ->find();
        return $data;
    }

}
