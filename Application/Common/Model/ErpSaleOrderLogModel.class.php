<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 采购付款申请模型
 */
class ErpSaleOrderLogModel extends BaseModel
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
    public function getSaleOrderLogList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'i.id desc')
    {
        $SaleOrderLogObj = M('ErpSaleOrderLog');
        $data['recordsTotal'] = $this->getSaleOrderLogCount($where);
        $data['data'] = $SaleOrderLogObj->alias('l')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = l.purchase_id', 'left')
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
    public function getSaleOrderLogCount($where = [])
    {
        return $this->where($where)->count();
    }

    /**
     *  修改保存采购付款申请
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveSaleOrderLog($where = [], $data = [])
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
    public function addSaleOrderLog($data = [])
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
    public function findSaleOrderLog($where = [])
    {
        return $this->where($where)->find();
    }

}
