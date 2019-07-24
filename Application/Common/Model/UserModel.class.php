<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * --------------------------------------------------------------
 * 用户表oil_user | 模型
 * Author：jk        Time：2016-09-08
 * --------------------------------------------------------------
 */

class UserModel extends BaseModel
{


////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('user')->field($field)->where($where)->order('id DESC')->select();
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('user')->where($where)->find();
    }

    public function OrderTable()
    {
        return D('Order');
    }






/////////////////////////////////兼容Common下模型///////////////////////////////////////

    /**
     * 返回用户地址列表
     * @param array $condition
     * @return array
     */
    public function getAddressInfoList($field, $group, $condition)
    {
        // @ user_phone -> user_name  <lizhipeng | 2016.10.26>
        $all_names = getAllNames();
        $result = M('retail_address')->field($field)->where($condition)->group($group)->select();
        foreach ($result as $k => $v) {
            $result[$k]['user_name'] = $all_names[$v['user_phone']];
        }
        // @end
        return $result;
    }


    /**
     * 插入地址数据
     * @param array $data
     * @retun 影响行数
     * @Time  20160829
     */
    public function insertAddressInfo($data)
    {
        if (empty($data)) return false;
        $result = M('retail_address')->add($data);
        return $result;
    }

    /**
     * 删除
     * @retun 影响行数
     * @Time  20160829
     */
    public function delAddressInfo($condition)
    {
        if (empty($condition)) return false;
        $result = M('retail_address')->where($condition)->delete();
        return $result;
    }

    /**
     * 修改
     * User:lizhipeng
     * Time:20160909
     * Rertun:影响行数
     */
    public function saveAddress($condition, $data)
    {
        if (empty($condition) || empty($data)) return false;
        $result = M('retail_address')->where($condition)->save($data);
        return $result;
    }

    public function buy_week($condition)
    {//本周采购笔数
        return $this->OrderTable()->where($condition)->where("YEARWEEK(date_format(create_time,'%Y-%m-%d')) = YEARWEEK(DATE_SUB(curdate(),INTERVAL 0 day))")->count();
    }

    public function buy_format($condition)
    {//本月采购订单笔数
        return $this->OrderTable()->where($condition)->where("DATE_FORMAT(create_time,'%Y-%m')=DATE_FORMAT(DATE_SUB(curdate(),INTERVAL 0 day),'%Y-%m') ")->count();
    }

    public function buy_week_num($condition)
    {//统计本周采购吨数
        return $this->OrderTable()->where($condition)->where("YEARWEEK(date_format(create_time,'%Y-%m-%d')) = YEARWEEK(DATE_SUB(curdate(),INTERVAL 0 day))")->sum(count);
    }

    public function buy_format_num($condition)
    {//统计本月采购吨数
        return $this->OrderTable()->where($condition)->where("DATE_FORMAT(create_time,'%Y-%m')=DATE_FORMAT(DATE_SUB(curdate(),INTERVAL 0 day),'%Y-%m') ")->sum(count);
    }

    public function sell_week($condition)
    {//本周出货笔数
        return $this->OrderTable()->where($condition)->where("YEARWEEK(date_format(create_time,'%Y-%m-%d')) = YEARWEEK(DATE_SUB(curdate(),INTERVAL 0 day))")->count();
    }

    public function sell_format($condition)
    {//本月出货笔数
        return $this->OrderTable()->where($condition)->where("DATE_FORMAT(create_time,'%Y-%m')=DATE_FORMAT(DATE_SUB(curdate(),INTERVAL 0 day),'%Y-%m') ")->count();
    }

    public function sell_week_num($condition)
    {//本周出货吨数
        return $this->OrderTable()->where($condition)->where("YEARWEEK(date_format(create_time,'%Y-%m-%d')) = YEARWEEK(DATE_SUB(curdate(),INTERVAL 0 day))")->sum(count);
    }

    public function sell_format_num($condition)
    {//本月出货吨数
        return $this->OrderTable()->where($condition)->where("DATE_FORMAT(create_time,'%Y-%m')=DATE_FORMAT(DATE_SUB(curdate(),INTERVAL 0 day),'%Y-%m') ")->sum(count);
    }

    public function time_order($condition)
    {
        return $this->OrderTable()->where($condition)->order('create_time desc')->field('create_time')->select();
    }

    public function cont_order($condition)
    {
        return $this->OrderTable()->where($condition)->order('create_time desc')->field('create_time')->select();
    }


}


?>
