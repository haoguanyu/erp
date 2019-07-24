<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 用户授信管理使用与还款明细表oil_retail_credit_change | 模型
 * Author：jk        Time：2016-09-09
 * ----------------------------------------
 */

class RetailCreditChangeModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];

        $this->User = D('user');
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_credit_change')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_credit_change')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_credit_change')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_credit_change')->where($where)->save($data);
    }


//////////////////////////////////业务处理层/////////////////////////////////////////

    /*
     * ----------------------------------------------
     * 用户授信管理列表数据 | 使用详情
     * Author：jk        Time：2016-09-11
     * ----------------------------------------------
     */
    public function retailCreditDetails($user_id)
    {
        // @默认查询还款数据
        $data = $this->selectData('*', ['user_id' => intval($user_id), 'type' => 2]);
        $user_data = $this->User->findData(['id' => intval($user_id)]);
        foreach ($data as $k => $v) {
            $data[$k]['user_phone'] = strHtml($user_data['user_phone']);
            $data[$k]['user_name'] = strHtml($user_data['user_name']);
            $data[$k]['befor_credit'] = number_format($v['befor_credit'], 2);
            $data[$k]['after_credit'] = number_format($v['after_credit'], 2);
            $data[$k]['play_money_time'] = date('Y-m-d', strtotime($v['play_money_time']));
            $data[$k]['fee'] = number_format($v['fee'], 2);
            $data[$k]['play_number'] = trim($v['play_number']);
        }

        return $data;
    }


}


?>
