<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * ERP模型
 */
class ErpErrorDataMappingModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author hanfeng
     * @time 2019-01-15
     */
    public function getErpErrorDataMappingList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getErpErrorDataMappingCount($where);
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
     * @time 2019-01-15
     */
    public function getErpErrorDataMappingCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->count();
        return $result;
    }

    /**
     *  修改映射表的数据
     * @param array $where
     * @param array $data
     * @return bool
     * @author hanfeng
     * @time 2019-01-15
     */
    public function saveErpErrorDataMapping($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加映射表的数据
     * @param array $data
     * @return bool
     * @author hanfeng
     * @time 2019-01-15
     */
    public function addErpErrorDataMapping($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条映射表的数据
     * @param $where
     * @param $field
     * @return array
     * @author hanfeng
     * @time 2019-01-15
     */
    public function findErpErrorDataMapping($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

}
