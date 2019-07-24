<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpStockDetailRetailModel extends BaseModel
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
    public function getStockDetailRetailList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'd.id asc')
    {
        $data['recordsTotal'] = $this->getStockDetailRetailCount($where);
        $data['data'] = $this->alias('d')
            ->field($field)
            ->where($where)
            ->join('oil_erp_stock s on s.id = d.stock_id', 'left')
            ->join('oil_erp_goods g on g.id = s.goods_id', 'left')
            ->join('oil_erp_storehouse sh on s.object_id = sh.id and s.stock_type = 4', 'left')
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
    public function getAllStockDetailRetailList($where = [], $field = '', $order = 'd.id desc')
    {
        $data['recordsTotal'] = $this->getStockDetailRetailCount($where);
        $data['data'] = $this->alias('d')
            ->field($field)
            ->where($where)
            ->join('oil_erp_stock s on s.id = d.stock_id', 'left')
            ->join('oil_erp_goods g on g.id = s.goods_id', 'left')
            ->join('oil_erp_storehouse sh on s.object_id = sh.id and s.stock_type = 4', 'left')
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
    public function getStockDetailRetailCount($where = [])
    {
        return $this->alias('d')
            ->where($where)
            ->join('oil_erp_stock s on s.id = d.stock_id', 'left')
            ->join('oil_erp_goods g on g.id = s.goods_id', 'left')
            ->join('oil_erp_storehouse sh on s.object_id = sh.id and s.stock_type = 4', 'left')
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
    public function saveStockDetail($where = [], $data = [])
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
    public function addStockDetail($data = [])
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
    public function findStockDetail($where = [])
    {
        return $this->where($where)->find();
    }

}
