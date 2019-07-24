<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpSaleInvoiceModel extends BaseModel
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
    public function getSaleInvoiceList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'i.id desc')
    {
        $sale_Invoice = M('ErpSaleInvoice');
        $data['recordsTotal'] = $this->getSaleInvoiceCount($where);
        $data['data'] = $sale_Invoice->alias('i')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = i.sale_order_id', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_clients cs on o.company_id = cs.id', 'left')
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
    public function getAllSaleInvoiceList($where = [], $field = true, $order = 'i.id desc')
    {
        $sale_Invoice = M('ErpSaleInvoice');
        $data['data'] = $sale_Invoice
            ->field($field)
            ->where($where)
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
    public function getSaleInvoiceCount($where = [])
    {
        return $this->where($where)->alias('i')
            ->join('oil_erp_sale_order o on o.id = i.sale_order_id', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_clients cs on o.company_id = cs.id', 'left')
            ->count();
    }

    /**
     *  修改保存发票
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveSaleInvoice($where = [], $data = [])
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
    public function addSaleInvoice($data = [])
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
    public function findSaleInvoice($where = [])
    {
        return $this->where($where)->find();
    }

}
