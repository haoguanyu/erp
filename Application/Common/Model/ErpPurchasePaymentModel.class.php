<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 采购付款申请模型
 */
class ErpPurchasePaymentModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getPurchasePaymentList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'p.id desc')
    {
        $PurchasePaymentObj = M('ErpPurchasePayment');
        $data['recordsTotal'] = $this->getPurchasePaymentCount($where);
        $data['sumTotal'] = $this->getPurchasePaymentTotal($where);
        $data['data'] = $PurchasePaymentObj->alias('p')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = p.purchase_id and p.source_order_type = 1', 'left')
            ->join('oil_erp_returned_order ro on ro.id = p.purchase_id and p.source_order_type = 2', 'left')
            ->join('oil_erp_purchase_order o2 on o2.order_number = ro.source_order_number', 'left')
            ->join('oil_erp_storehouse es1 on es1.id = o.storehouse_id','left')
            ->join('oil_erp_storehouse es2 on es2.id = o2.storehouse_id','left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getAllPurchasePaymentList($where = [], $field = true, $order = 'p.id desc')
    {
        $PurchasePaymentObj = M('ErpPurchasePayment');
        $data['recordsTotal'] = $this->getPurchasePaymentCount($where);
        $data['data'] = $PurchasePaymentObj->alias('p')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = p.purchase_id and p.source_order_type = 1', 'left')
            ->join('oil_erp_returned_order ro on ro.id = p.purchase_id and p.source_order_type = 2', 'left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getPurchasePaymentCount($where = [])
    {
        return $this->where($where)->alias('p')->join('oil_erp_purchase_order o on o.id = p.purchase_id', 'left')->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getPurchasePaymentTotal($where = [])
    {
        return $this->alias('p')
            ->field('sum(p.pay_money)/'.C('IO_NUM').' as total_pay_money,
            sum(o.order_amount)/'.C('IO_NUM').' as total_order_amount,
            sum(p.balance_deduction)/'.C('IO_NUM').' as total_balance_deduction,
            sum(o.payed_money)/'.C('IO_NUM').' as total_payed_money')
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = p.purchase_id', 'left')
            ->find();
    }

    /**
     *  修改保存采购付款申请
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function savePurchasePayment($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加采购付款申请
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addPurchasePayment($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条采购付款申请信息
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findPurchasePayment($where = [])
    {
        return $this->where($where)->find();
    }

}
