<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 岗位基础资料模型
 */
class ErpKpiPlanModel extends BaseModel
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
    public function getKpiPlanList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'id desc')
    {
        $data['recordsTotal'] = $this->getKpiPlanCount($where);
        $data['data']         = $this->field($field)->where($where)->limit($offset, $limit)->order($order)->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getKpiPlanCount($where = [], $field = true)
    {
        return $this->where($where)->count();
    }


    /**
     *  修改保存岗位基础资料
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveKpiPlan($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加岗位基础资料
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addKpiPlan($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条岗位基础资料信息
     * @param $where
     * @param $field
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findKpiPlan($where = [],$field = true)
    {
       return $this->field($field)->where($where)->find();
 
    }

}
