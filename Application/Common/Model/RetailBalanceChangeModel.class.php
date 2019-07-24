<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 用户预存管理明细表oil_retail_balance_change | 模型
 * Author：jk        Time：2016-09-08
 * ----------------------------------------
 */

class RetailBalanceChangeModel extends BaseModel
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
        return M('retail_balance_change')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_balance_change')->add($data);
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_balance_change')->where($where)->save($data);
    }

///////////////////////////////////业务处理层//////////////////////////////////////////


    /*
     * ----------------------------------------------
     * 用户预存管理列表数据 | 详情
     * Author：jk        Time：2016-09-09
     * ----------------------------------------------
     */
    public function retailBalanceDetails($param = [])
    {
        if (!isset($param['user_id']) || intval($param['user_id']) < 0) return $this->arr;
        // @默认查询存入类型数据
        $data = $this->selectData('*', ['user_id' => intval($param['user_id']), 'type' => ['IN', [1, 2, 3]]]);
        $user_data = $this->User->findData(['id' => $param['user_id'], 'is_available' => ['neq', 1]]);
        if (count($data) > 0) {
            foreach ($data as $k => $v) {
                $data[$k]['befor_price'] = number_format($v['befor_price'], 2);
                $data[$k]['after_price'] = number_format($v['after_price'], 2);
                $data[$k]['user_name'] = $user_data['user_name'];
                $data[$k]['user_phone'] = $user_data['user_phone'];
                $data[$k]['play_money_time'] = $v['play_money_time'] != '0000-00-00 00:00:00' ? date('Y-m-d', strtotime($v['play_money_time'])) : '';
                $data[$k]['fee'] = number_format($v['fee'], 2);
            }
        }

        return $data;
    }


}


?>
