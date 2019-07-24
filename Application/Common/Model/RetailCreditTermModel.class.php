<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 用户授信额度到期重新授信记录表oil_retail_credit_term | 模型
 * Author：jk        Time：2016-09-09
 * ----------------------------------------
 */

class RetailCreditTermModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];

        $this->User = D('User');
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_credit_term')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_credit_term')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_credit_term')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_credit_term')->where($where)->save($data);
    }


/////////////////////////////////////业务处理层///////////////////////////////////////

    /*
     * ----------------------------------------------
     * 用户授信管理列表 | 延期授信到期时间列表详情
     * Author：jk        Time：2016-09-11
     * ----------------------------------------------
     */
    public function retailCreditTermDetails($user_id)
    {
        $data = $this->selectData('*', ['user_id' => intval($user_id)]);
        $user_data = $this->User->findData(['id' => intval($user_id)]);
        foreach ($data as $k => $v) {
            $data[$k]['user_phone'] = $user_data['user_phone'];
            $data[$k]['user_name'] = $user_data['user_name'];
            $data[$k]['befor_credit'] = number_format($v['befor_credit'], 2);
            $data[$k]['after_credit'] = number_format($v['after_credit'], 2);
        }

        return $data;
    }


}


?>
