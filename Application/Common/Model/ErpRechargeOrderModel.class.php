<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 * 预存/预付单模型
 */
class ErpRechargeOrderModel extends BaseModel
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
    public function getRechargeOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getRechargeOrderCount($where,$field);
        $data['sumTotal'] = $this->getRechargeOrderTotal($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_user u on o.user_id = u.id', 'left')
                ->join('oil_clients c on o.company_id = c.id', 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->group('o.id')
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllRechargeOrderList($where = [], $field = '', $order = 'o.id desc')
    {

        $data['recordsTotal'] = $this->getRechargeOrderCount($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_user u on o.user_id = u.id', 'left')
                ->join('oil_clients c on o.company_id = c.id', 'left')
                ->order($order)
                ->group('o.id')
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
    public function getRechargeOrderCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('oil_user u on o.user_id = u.id', 'left')
            ->join('oil_clients c on o.company_id = c.id', 'left')
            ->count('distinct o.id');
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getRechargeOrderTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(o.recharge_amount)/'.C('IO_NUM').' as total_recharge_amount')
            ->where($where)
            ->join('oil_user u on o.user_id = u.id', 'left')
            ->join('oil_clients c on o.company_id = c.id', 'left')
            ->find();
        return $result;
    }

    /**
     * 新增预存/预付申请单
     * @param array $data
     * @return bool
     * @author guanyu
     * @time 2017-10-31
     */
    public function addRechargeOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 修改保存预存/预付申请单
     * @param array $data
     * @return bool
     * @author guanyu
     * @time 2017-10-31
     */
    public function saveRechargeOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }


    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findRechargeOrder($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findOneRechargeOrder($where = [], $field = 'o.*')
    {
        $data = $this->alias('o')
            ->field($field)
            ->where($where)
            //->join('oil_user u on o.user_id = u.id', 'left')
            //->join('oil_clients c on o.company_id = c.id', 'left')
            ->find();
        return $data;
    }

}
