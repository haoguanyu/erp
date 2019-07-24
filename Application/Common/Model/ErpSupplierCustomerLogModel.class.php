<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 新零售变动记录模型
 */
class ErpSupplierCustomerLogModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author hanfeng
     * @time 2018-10-17
     */
    public function getSupplierCustomerLogList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSupplierCustomerLogCount($where);
        if ($data['recordsTotal']) {

            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->limit($offset, $limit)
                ->order($order)
                ->select();
        } else {

            $data['data'] = [];
        }
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author hanfeng
     * @time 2018-10-17
     */
    public function getSupplierCustomerLogCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->count();
        return $result;
    }

    /**
     *  修改新零售变动记录
     * @param array $where
     * @param array $data
     * @return bool
     * @author hanfeng
     * @time 2018-10-17
     */
    public function saveSupplierCustomerLog($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加新零售变动记录
     * @param array $data
     * @return bool
     * @author hanfeng
     * @time 2018-10-17
     */
    public function addSupplierCustomerLog($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条新零售变动记录信息
     * @param $where
     * @param $field
     * @return array
     * @author hanfeng
     * @time 2018-10-17
     */
    public function findSupplierCustomerLog($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

}
