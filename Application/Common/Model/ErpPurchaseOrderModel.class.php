<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 * 供货单模型
 */
class ErpPurchaseOrderModel extends BaseModel
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
    public function getPurchaseOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        //$orderObj = M('ErpPurchaseOrder');
        $data['recordsTotal'] = $this->getPurchaseOrderCount($where);
        $data['sumTotal'] = $this->getPurchaseOrderTotal($where);
        if ($data['recordsTotal']) {

            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer dl on o.buyer_dealer_id = dl.id', 'left')
                ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
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
    public function getPurchaseOrderCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.buyer_dealer_id = dl.id', 'left')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
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
    public function getPurchaseOrderTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(o.goods_num)/'.C('IO_NUM').' as total_goods_num, sum(o.storage_quantity)/'.C('IO_NUM').' as total_storage_quantity, sum(o.order_amount)/'.C('IO_NUM').' as total_order_amount,
             sum(o.payed_money)/'.C('IO_NUM').' as total_payed_money, sum(o.order_amount - o.payed_money)/'.C('IO_NUM').' as total_no_payed_money, sum(o.invoice_money)/'.C('IO_NUM').' as total_invoice_money,
             sum(o.returned_goods_num)/' . C('IO_NUM') . '  as total_returned_goods_num, sum(r.return_payed_amount) / '.C('IO_NUM').' as total_returned_money')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.buyer_dealer_id = dl.id', 'left')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
            ->find();
        return $result;
    }

    /**
     *  修改保存供货单
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function savePurchaseOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加供货单
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addPurchaseOrder($data = [])
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
    public function findPurchaseOrder($where = [], $field = true)
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
            ->join('oil_erp_company ec on o.our_buy_company_id = ec.company_id', 'left')
            ->join('oil_erp_supplier_user su on o.sale_user_id = su.id', 'left')
            ->join('oil_erp_supplier s on o.sale_company_id = s.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.buyer_dealer_id = dl.id', 'left')
            ->find();
        return $data;
    }

    /**
     * 获取五要素对应库存信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getStockPurchaseOrder($where = [], $field = true, $order = 'o.id desc' ,$group = '')
    {
        $data = $this->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->order($order)
            ->group($group)
            ->select();
        return $data;
    }

    /**
     * 获取一条调拨单配送信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findPurchaseShippingOrder($where = [], $field = 'o.*')
    {
        $data = $this
            ->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_user u on o.sale_user_id = u.id', 'left')
            ->join('oil_clients c on o.sale_company_id = c.id', 'left')
            ->join('oil_erp_stock_in si on o.order_number = si.source_number')
            ->find();
        return $data;
    }

    /***********************************
        @ Content 批量添加
        @ Author yF
        @ Time 2018-12-06
    ************************************/
    public function PurchaseOrderAddAll($add_arr)
    {
        return $this->addAll($add_arr);
    }
}
