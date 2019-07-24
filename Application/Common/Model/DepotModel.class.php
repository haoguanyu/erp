<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * --------------------------------------------------------------
 * 用户预存管理表oil_retail_balance | 模型
 * Author：jk        Time：2016-09-08
 * --------------------------------------------------------------
 */

class DepotModel extends BaseModel
{

    /*
    * -----------------------------------------------------
    * 油库主营信息|提货方式|发货方式转义
    * Author:<jk>   Time:2016-04-08
    * -----------------------------------------------------
    */
    public function getDepotData($type = '')
    {
        if (empty($type)) return [];
        switch ($type) {
            case 'product':
                $data = [
                    '中国石化' => '中国石化',
                    '中国石油' => '中国石油',
                    '中国海油' => '中国海油',
                    '中国化工' => '中国化工',
                    '中国中化' => '中国中化',
                    '其它' => '其它'
                ];
                break;
            case 'pick_type':
                $data = [
                    '电子卡' => '电子卡',
                    '提油卡' => '提油卡',
                    '报车号' => '报车号',
                    '纸质订单' => '纸质订单',
                    '纸质订单,报车号' => '纸质订单,报车号',
                    '报车号+油单' => '报车号+油单',
                    '订单号+车号' => '订单号+车号',
                    '报企业名称+车号' => '报企业名称+车号',
                    '报计划+车号' => '报计划+车号',
                    '报车号,联系人电话物权交接' => '报车号,联系人电话物权交接',
                    '其它' => '其它'
                ];
                break;
            case 'deliver_type':
                $data = [
                    '过磅发货' => '过磅发货',
                    '流量发货' => '流量发货',
                    '按升数发货' => '按升数发货',
                    '过磅发货,5点半之前开单' => '过磅发货,5点半之前开单',
                    '按升数发货,19号平台按斤数发货' => '按升数发货,19号平台按斤数发货',
                    '其它' => '其它'
                ];
                break;
            default:
                $data = [];
                break;
        }
        return $data;
    }

    /*
    * -----------------------------------------------------
    * 获取地区数据
    * $id空值时查询省份 不为空时查询市
    * Author:<jk>   Time:2016-04-08
    * -----------------------------------------------------
    */
    public function getArea($id = '', $city = '')
    {
        if (!S('depot_area')) {
            $where['area_type'] = array('neq', 0);
            $where['is_branch'] = 1;
            $area = $this->table('oil_area')->field('id,parent_id,area_name,area_type')->where($where)->select();
            S('area_type', $area, 36000);
        } else {
            $area = S('depot_area');
        }

        if (empty($id)) {  # 省数据
            foreach ($area as $k => $v) {
                if ($v['parent_id'] == 1) {
                    $s[$v['id']] = $v['area_name'];
                }
            }
        } else {            # 市数据
            foreach ($area as $k => $v) {
                if ($v['parent_id'] == $id) {
                    $s[$v['id']] = $v['area_name'];
                }
            }
        }

        if (!empty($city)) {
            foreach ($area as $k => $v) {
                if ($v['id'] == $city) {
                    return $v['parent_id'];
                }
            }
        }
        return $s;
    }

    /*
    * -----------------------------------------------------
    * 添加油库
    * Author:<jk>   Time:2016-04-11
    * -----------------------------------------------------
    */
    public function addDepot(array $arr)
    {
        return $this->table('oil_depot')->add($arr);
    }

    /*
    * -----------------------------------------------------
    * 油库管理模块提示信息返回
    * Author:<jk>   Time:2016-04-11
    * -----------------------------------------------------
    */
    public function getMessage($k)
    {
        $message = [
            'a' => '别浪费时间了，你盗不了的！',
            'b' => '选择错误，请重新尝试！',
            'c' => '油库添加成功！',
            'd' => '油库添加失败！',
            'e' => '修改油库成功！',
            'f' => '修改油库失败！',
            'g' => '油库已存在！',
        ];

        return $message[$k];
    }

    /*
    * -----------------------------------------------------
    * 油库管理模型 | 查看列表
    * Author:<jk>   Time:2016-04-11
    * -----------------------------------------------------
    */
    public function depotList($where = "1=1", $limit = false, $order = array('id' => 'desc'))
    {
        $depot = $this->table('oil_depot')->field('id,product,depot_name,tel,work_time,pick_type,deliver_type,depot_area,address,add_time,dealer_name,remarks');
        $depot->where($where)->order($order);
        if ($limit) {
            $depot->limit($limit);
        }
        return $depot->select();
    }

    public function countDepot($where = '1=1')
    {
        $this->table('oil_depot')->where($where)->count();
    }

    /*
    * -----------------------------------------------------
    * 油库管理模型 | 删除
    * Author:<jk>   Time:2016-04-11
    * -----------------------------------------------------
    */
    public function deleteDepot($where)
    {
        $depot = $this->table('oil_depot');
        if (!empty($where)) $depot->where($where);

        return $depot->delete();
    }

    /*
    * -----------------------------------------------------
    * 油库管理模型 | 删除
    * Author:<jk>   Time:2016-04-11
    * -----------------------------------------------------
    */
    public function getOneDepot($where)
    {
        if (empty($where)) return [];
        return $this->table('oil_depot')->field('id,product,depot_name,tel,work_time,pick_type,deliver_type,depot_area,address,add_time,dealer_name,remarks')->where($where)->find();
    }

    /*
    * -----------------------------------------------------
    * 油库管理模型 | 删除
    * Author:<jk>   Time:2016-04-12
    * -----------------------------------------------------
    */
    public function updateDepot($where, $data)
    {
        return $this->table('oil_depot')->where($where)->save($data);
    }
    /*
   *  @params:
   *      data:
   *         array
   *  @return :
   *  @desc:获取油库的信息
   *  @author:小黑
   *  @time:2019-2-19
   */
    public function getListField($field , $where){
        return $this->table('oil_depot')->where($where)->getField($field);
    }
    /*
   *  @params:
   *      data:
   *         array
   *  @return :
   *  @desc:获取油库的信息
   *  @author:小黑
   *  @time:2019-2-19
   */
    public function getInfoField($field , $where){
        return $this->table('oil_depot')->field($field)->where($where)->select();
    }
}


?>
