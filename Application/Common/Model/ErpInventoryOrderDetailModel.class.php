<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 盘点单详情模型
 */
class ErpInventoryOrderDetailModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author qianbin
     * @time 2017-01-03
     */
    public function getErpInventoryOrderDetailList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'd.id desc')
    {
        $data['recordsTotal'] = $this->getErpInventoryOrderDetailCount($where);
        $data['data'] = $this->field($field)
                ->alias('d')
                ->where($where)
                ->join('oil_erp_inventory_order o on d.inventory_order_id = o.id','left')
                ->join('oil_erp_goods g on d.goods_id = g.id','left')
                ->join('oil_erp_stock s on d.stock_id = s.id','left')
                ->limit($offset, $limit)
                ->order($order)
                ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param string $order
     * @return array
     * @author guanyu
     * @time 2017-01-08
     */
    public function getAllErpInventoryOrderDetailList($where = [], $field = true, $order = 'd.id desc')
    {
        $data['recordsTotal'] = $this->getErpInventoryOrderDetailCount($where);
        $data['data'] = $this->field($field)
            ->alias('d')
            ->where($where)
            ->join('oil_erp_inventory_order o on d.inventory_order_id = o.id','left')
            ->join('oil_erp_goods g on d.goods_id = g.id','left')
            ->join('oil_erp_stock s on d.stock_id = s.id','left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author qianbin
     * @time 2017-01-03
     */
    public function getErpInventoryOrderDetailCount($where = [])
    {
        return $this->alias('d')
                ->where($where)
                ->join('oil_erp_inventory_order o on d.inventory_order_id = o.id','left')
                ->join('oil_erp_goods g on d.goods_id = g.id','left')
                ->join('oil_erp_stock s on d.stock_id = s.id','left')
                ->count();
    }


    /**
     *  修改保存盘点单详情
     * @param array $where
     * @param array $data
     * @return bool
     * @author qianbin
     * @time 2017-01-03
     */
    public function saveErpInventoryOrderDetail($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加盘点单详情
     * @param array $data
     * @return bool
     * @author qianbin
     * @time 2017-01-03
     */
    public function addErpInventoryOrderDetail($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条盘点单详情信息
     * @param $where
     * @param $field
     * @return array
     * @author qianbin
     * @time 2017-01-03
     */
    public function findErpInventoryOrderDetail($where = [],$field = true)
    {
       return $this->field($field)->where($where)->find();
 
    }

}
