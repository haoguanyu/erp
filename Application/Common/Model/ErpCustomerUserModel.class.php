<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 供应商联系人模型
 */
class ErpCustomerUserModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getCustomerUserList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getCustomerUserCount($where);
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
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getCustomerUserCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->count();
        return $result;
    }

    /**
     *  修改供应商
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function saveCustomerUser($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加供应商
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addCustomerUser($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findCustomerUser($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

}
