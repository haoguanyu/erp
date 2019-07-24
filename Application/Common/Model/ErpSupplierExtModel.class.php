<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/11/20
 * Time: 16:21
 */

namespace Common\Model;
use Common\Model\BaseModel;

class ErpSupplierExtModel  extends BaseModel
{
    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author 小黑
     * @time 2017-03-31
     */
    public function getSupplierBackList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSupplierBackCount($where);
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
     * @author 小黑
     * @time 2018-11-20
     */
    public function getSupplierBackCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->count();
        return $result;
    }
    /**
     *  修改银行账号
     * @param array $where
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2018-11-20
     */
    public function saveSupplierBack($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }
    /**
     * 添加银行账户
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2018-11-20
     */
    public function addSupplierBack($data = [])
    {
        return $this->add($data);
    }
    /**
     * 银行账户详情
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2018-11-20
     */
    public function findSupplierback($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     *  修改服务商扩展表
     * @param array $where
     * @param array $data
     * @return bool
     * @author hanfeng
     * @time 2018-12-18
     */
    public function saveSupplierExt($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加服务商扩展表
     * @param array $data
     * @return bool
     * @author hanfeng
     * @time 2018-12-18
     */
    public function addSupplierExt($data = [])
    {
        return $this->add($data);
    }
}