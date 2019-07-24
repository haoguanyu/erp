<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 调拨单日志表模型
 */
class ErpAllocationOrderLogModel extends BaseModel
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
    public function getAllocationOrderLogList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'i.id desc')
    {

        $data['recordsTotal'] = $this->getAllocationOrderLogCount($where);
        $data['data'] = $this->alias('al')
            ->field($field)
            ->where($where)
            ->join('oil_erp_allocation_order o on o.id = al.allocation_id', 'left')
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
    public function getAllocationOrderLogCount($where = [])
    {
        return $this->where($where)->count();
    }

    /**
     *  修改保存调拨日志
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveAllocationOrderLog($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加调拨日志
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addAllocationOrderLog($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条调拨日志
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findAllocationOrderLog($where = [])
    {
        return $this->where($where)->find();
    }

}
