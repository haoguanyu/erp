<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 多账套模型
 */
class ErpCompanyModel extends BaseModel
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
    public function getCompanyList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'ec.id desc')
    {
        $CompanyObj = M('ErpCompany');
        $data['recordsTotal'] = $this->getCompanyCount($where);
        $data['data'] = $CompanyObj->alias('ec')
            ->field($field)
            ->where($where)
            ->join('oil_clients c on c.id = ec.company_id', 'left')
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
    public function getCompanyCount($where = [])
    {
        return  M('ErpCompany')->alias('ec')

            ->where($where)
            ->join('oil_clients c on c.id = ec.company_id', 'left')
            ->count();
    }

    /**
     *  修改保存多账套
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveCompany($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加多账套
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addCompany($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条多账套信息
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findCompany($where = [])
    {
        return $this->where($where)->find();
    }

}
