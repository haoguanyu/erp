<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpSaleLossModel extends BaseModel
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
    public function getSaleLossList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'l.id desc')
    {
        $sale_collection = M('ErpSaleLoss');
        $data['recordsTotal'] = $this->getSaleLossCount($where);
        $data['data'] = $sale_collection->alias('l')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = l.sale_order_id', 'left')
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
    public function getSaleLossCount($where = [])
    {
        return $this->where($where)->alias('l')
            ->join('oil_erp_sale_order o on o.id = l.sale_order_id', 'left')
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
    public function saveSaleLoss($where = [], $data = [])
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
    public function addSaleLoss($data = [])
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
    public function findSaleLoss($where = [] , $field = "")
    {
        $sql = $this ;
        if(!empty($field))
            $sql = $sql->field($field) ;
        return $sql->where($where)->find();
    }

}
