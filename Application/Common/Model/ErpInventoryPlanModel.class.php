<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 盘点计划模型
 */
class ErpInventoryPlanModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author qianbin
     * @time 2018-01-03
     */
    public function getErpInventoryPlanList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'id desc')
    {
        $data['recordsTotal'] = $this->getErpInventoryPlanCount($where);
        $data['data']         = $this->field($field)->where($where)->limit($offset, $limit)->order($order)->select();
   
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author qianbin
     * @time 2018-01-03
     */
    public function getErpInventoryPlanCount($where = [],$field = true)
    {
        return $this->field($field)->where($where)->count();
    }


    /**
     *  修改保存盘点计划盘点计划
     * @param array $where
     * @param array $data
     * @return bool
     * @author qianbin
     * @time 2018-01-03
     */
    public function saveErpInventoryPlan($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加盘点计划
     * @param array $data
     * @return bool
     * @author qianbin
     * @time 2018-01-03
     */
    public function addErpInventoryPlan($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条盘点计划信息
     * @param $where
     * @return array
     * @author qianbin
     * @time 2018-01-03
     */
    public function findErpInventoryPlan($where = [],$field = true)
    {
       return $this->field($field)->where($where)->find();
 
    }

}
