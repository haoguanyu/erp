<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpPurchaseInvoiceModel extends BaseModel
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
    public function getPurchaseInvoiceList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'i.id desc')
    {
        $PurchaseInvoiceObj = M('ErpPurchaseInvoice');
        $data['recordsTotal'] = $this->getPurchaseInvoiceCount($where);
        $data['sumTotal'] = $this->getPurchaseInvoiceTotal($where);
        $data['data'] = $PurchaseInvoiceObj->alias('i')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = i.purchase_id', 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id','left')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
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
    public function getAllPurchaseInvoiceList($where = [], $field = true, $order = 'i.id desc')
    {
        $PurchaseInvoiceObj = M('ErpPurchaseInvoice');
        $data['recordsTotal'] = $this->getPurchaseInvoiceCount($where);
        $data['data'] = $PurchaseInvoiceObj->alias('i')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = i.purchase_id', 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id','left')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
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
    public function getPurchaseInvoiceCount($where = [])
    {
        return $this->where($where)->alias('i')->join('oil_erp_purchase_order o on o.id = i.purchase_id', 'left')->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getPurchaseInvoiceTotal($where = [])
    {
        return $this->alias('i')
            ->field('sum(o.order_amount)/'.C('IO_NUM').' as total_order_amount,
            sum(o.order_amount)/'.C('IO_NUM').' - IF(ISNULL(sum(r.return_payed_amount)),0,sum(r.return_payed_amount))/'.C('IO_NUM').' as total_actual_order_amount,
            sum(i.tax_money)/'.C('IO_NUM').' as total_tax_money, 
            sum(i.notax_invoice_money)/'.C('IO_NUM').' as total_notax_invoice_money, 
            sum(i.apply_invoice_money)/'.C('IO_NUM').' as total_apply_invoice_money')
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = i.purchase_id', 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id','left')
            ->join('oil_erp_returned_order r on r.source_order_number = o.order_number and r.order_status = 10', 'left')
            ->find();
    }

    /**
     *  修改保存发票
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function savePurchaseInvoice($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加发票
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addPurchaseInvoice($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条发票信息
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findPurchaseInvoice($where = [])
    {
        return $this->where($where)->find();
    }

}
