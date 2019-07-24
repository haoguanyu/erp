<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 盘点单模型
 */
class ErpInventoryOrderModel extends BaseModel
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
    public function getErpInventoryOrderList($where = [], $field = '*', $offset = 0, $limit = 10, $order = 'id desc')
    {
        $data['recordsTotal'] = $this->getErpInventoryOrderCount($where);
        $data['data'] = $this->alias('o')->field($field)->where($where)
            ->join('oil_erp_inventory_plan as p on o.inventory_plan_id = p.id', 'left')
            ->limit($offset, $limit)->order($order)->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author qianbin
     * @time 2017-01-03
     */
    public function getErpInventoryOrderCount($where = [])
    {
        $count = $this->alias('o')->where($where)
                ->join('oil_erp_inventory_plan as p on o.inventory_plan_id = p.id', 'left')
                ->count();
        return $count;
    }


    /**
     *  修改保存盘点单
     * @param array $where
     * @param array $data
     * @return bool
     * @author qianbin
     * @time 2017-01-03
     */
    public function saveErpInventoryOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加盘点单
     * @param array $data
     * @return bool
     * @author qianbin
     * @time 2017-01-03
     */
    public function addErpInventoryOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条盘点单信息
     * @param $where
     * @param $field
     * @return array
     * @author qianbin
     * @time 2017-01-03
     */
    public function findErpInventoryOrder($where = [],$field = true)
    {
       return $this->field($field)->where($where)->find();
 
    }

}
