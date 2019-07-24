<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpSaleCollectionModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-04-17
     */
    public function getSaleCollectionList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'c.id desc')
    {
        $sale_collection = M('ErpSaleCollection');
        $data['recordsTotal'] = $this->getSaleCollectionCount($where);
        $data['sumTotal'] = $this->getSaleCollectionTotal($where);
        $data['data'] = $sale_collection->alias('c')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = c.sale_order_id and o.order_number = c.sale_order_number', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_clients cs on o.company_id = cs.id', 'left')
            ->limit($offset, $limit)
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
    public function getSaleCollectionCount($where = [])
    {
        return $this->where($where)->alias('c')
            ->join('oil_erp_sale_order o on o.id = c.sale_order_id', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_clients cs on o.company_id = cs.id', 'left')
            ->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getSaleCollectionTotal($where = [])
    {
        return $this->alias('c')
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = c.sale_order_id', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_clients cs on o.company_id = cs.id', 'left')
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
    public function saveSaleCollection($where = [], $data = [])
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
    public function addSaleCollection($data = [])
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
    public function findSaleCollection($where = [])
    {
        return $this->where($where)->find();
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-04-17
     */
    public function getSaleReturnedCollectionList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'c.id desc')
    {
        $sale_collection = M('ErpSaleCollection');
        $data['recordsTotal'] = $this->getSaleCollectionCount($where);
        $data['sumTotal'] = $this->getSaleCollectionTotal($where);
        $data['data'] = $sale_collection->alias('c')
            ->field($field)
            ->where($where)
            ->join('oil_erp_returned_order ro on ro.id = c.sale_order_id and ro.order_number = c.sale_order_number', 'left')
            ->join('oil_erp_sale_order o on o.id = ro.source_order_id and o.order_number = ro.source_order_number', 'left')
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
     * @author qianbin
     * @time 2017-07-17
     */
    public function getNewSaleCollectionList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'c.id desc')
    {
        $sale_collection = M('ErpSaleCollection');
        $data['recordsTotal'] = $this->getNewSaleCollectionCount($where);
        $data['sumTotal'] = $this->getNewSaleCollectionTotal($where);
        $data['data'] = $sale_collection->alias('c')
            ->field($field)
            ->where($where)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author qianbin
     * @time 2017-07-17
     */
    public function getNewSaleCollectionCount($where = [])
    {
        return $this->alias('c')->where($where)->count();
    }


    /**
     * 返回合计数
     * @param array $where
     * @return mixed
     * @author qianbin
     * @time 2017-07-17
     */
    public function getNewSaleCollectionTotal($where = [])
    {
        return $this->alias('c')
            ->field('
                sum(o.order_amount / '.C('IO_NUM').') as total_order_amount,
                sum(c.collect_money + c.balance_deduction)/'.C('IO_NUM').' as total_all_collect_money,
                sum(c.balance_deduction / '.C('IO_NUM').') as total_balance_deduction,
                sum(c.collect_money / '.C('IO_NUM').') as total_collect_money
                ')
            ->where($where)
            ->join('oil_erp_sale_order o on o.order_number = c.from_sale_order_number', 'left')
            ->find();
    }



}
