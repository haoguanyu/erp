<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 计提方案模型
 */
class ErpKpiBaseModel extends BaseModel
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
    public function getKpiBaseList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'id desc')
    {
        $erp_kpi_base = M('erp_kpi_base');
        $data['recordsTotal'] = $this->getKpiBaseCount($where);
        $data['data'] = $erp_kpi_base->field($field)->where($where)->limit($offset, $limit)->order($order)->select();
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
    public function getAllKpiBaseList($where = [], $field = true, $order = 'id desc')
    {
        $erp_kpi_base = M('erp_kpi_base');
        $data['recordsTotal'] = $this->getKpiBaseCount($where);
        $data['data'] = $erp_kpi_base->field($field)->where($where)->order($order)->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getKpiBaseCount($where = [] , $field = true)
    {
        return $this->field($field)->where($where)->count();
    }


    /**
     *  修改保存计提方案
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveKpiBase($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加计提方案
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addKpiBase($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条计提方案信息
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findKpiBase($where = [],$field)
    {
       return $this->field($field)->where($where)->find();
 
    }

}
