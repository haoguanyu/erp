<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 账户模型
 */
class ErpAccountModel extends BaseModel
{

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author guanyu
     * @time 2017-11-10
     */
    public function getAccountList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'a.id desc')
    {
        $AccountObj = M('ErpAccount');
        $data['recordsTotal'] = $this->getAccountCount($where);
        $data['sumTotal'] = $this->getAccountTotal($where);
        $data['data'] = $AccountObj->alias('a')
            ->field($field)
            ->where($where)
            ->join('oil_erp_customer c on a.company_id = c.id and a.account_type = 1', 'left')
            ->join('oil_erp_supplier s on a.company_id = s.id and a.account_type = 2', 'left')
            ->join('oil_erp_company ec on a.our_company_id = ec.company_id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author guanyu
     * @time 2017-11-10
     */
    public function getAllAccountList($where = [], $field = true, $order = 'a.id desc')
    {
        $where['a.status'] = 1;
        $AccountObj = M('ErpAccount');
        $data['recordsTotal'] = $this->getAccountCount($where);
        $data['data'] = $AccountObj->alias('a')
            ->field($field)
            ->where($where)
            ->join('oil_erp_customer c on a.company_id = c.id and a.account_type = 1', 'left')
            ->join('oil_erp_supplier s on a.company_id = s.id and a.account_type = 2', 'left')
            ->join('oil_erp_company ec on a.our_company_id = ec.company_id', 'left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2017-11-10
     */
    public function getAccountCount($where = [])
    {
        $where['a.status'] = 1;
        return $this->alias('a')->where($where)
            ->join('oil_erp_customer c on a.company_id = c.id and a.account_type = 1', 'left')
            ->join('oil_erp_supplier s on a.company_id = s.id and a.account_type = 2', 'left')
            ->join('oil_erp_company ec on a.our_company_id = ec.company_id', 'left')
            ->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2017-11-10
     */
    public function getAccountTotal($where = [])
    {
        $where['a.status'] = 1;
        return $this->alias('a')
            ->field('sum(a.account_balance)/'.C('IO_NUM').' as total_account_balance')
            ->where($where)
            ->join('oil_erp_customer c on a.company_id = c.id and a.account_type = 1', 'left')
            ->join('oil_erp_supplier s on a.company_id = s.id and a.account_type = 2', 'left')
            ->join('oil_erp_company ec on a.our_company_id = ec.company_id', 'left')
            ->find();
    }

    /**
     *  修改保存账户
     * @param array $data
     * @return bool
     * @author guanyu
     * @time 2017-11-10
     * @return bool $status
     */

    public function saveAccount($data = [])
    {
        $status = false;
        if ($data) {
            $add_data = $data;
            unset($data['account_type']);
            unset($data['company_id']);
            unset($data['our_company_id']);
            unset($data['create_time']);
            $update_data = $data;
            $add_data['create_time'] = currentTime();
            $update_data['update_time'] = date('Y-m-d H:i:s', time()+1);
            $status = $this->add($add_data, [], $update_data);
            if($status !== false){
                $status = 1;
            }else{
                $status = 0;
            }
        }
        return $status;
    }

    /**
     * 获取一条账户信息
     * @param $where
     * @param $field
     * @return array
     * @author guanyu
     * @time 2017-11-10
     */
    public function findAccount($where = [], $field = true)
    {
        $where['status'] = 1;
        $data = $this->field($field)->where($where)->find();
        return $data;
    }

}
