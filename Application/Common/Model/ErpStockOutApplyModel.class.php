<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/18
 * Time: 16:32
 */

namespace Common\Model;


class ErpStockOutApplyModel extends BaseModel
{
    /*
     * @params:
     *  field:需要查询求和的字段
     *  where:需要查询的条件
     * @return :
     * @desc:获取数据之和
     * @author：小黑
     * @time:2019-2-18
     */
    public function sumNum($filed , $where){
        return $this->where($where)->getField('sum('.$filed.')');
    }

    /*
     * @params:
     *  data:array
     * @return :
     * @desc:添加出库申请单
     * @author：小黑
     * @time:2019-2-18
     */
    public function addApply($data){
        return $this->add($data);
    }
    /*
    * @params:
    *  field:需要查询的字段
    *  order:排序
    *  where:查询的条件
    * @return :
    * @desc:获取销售计划单的列表
    * @author：小黑
    * @time:2019-2-18
    */
    public function getList($field ,$order, $where , $offset = 0, $limit = 10){
        return   $this->alias('esoa')
            ->field($field)
            ->join("oil_erp_sale_order so on esoa.source_object_id = so.id" , "left")
            ->where($where)
            ->limit($offset , $limit)
            ->order($order)
            ->select();
    }
    /*
    * @params:
    *  field:需要查询的字段
    *  order:排序
    *  where:查询的条件
    * @return :
    * @desc:获取销售计划单的列表
    * @author：小黑
    * @time:2019-2-18
    */
    public function getCount($where=[]){
        return   $this->alias('esoa')
            ->join("oil_erp_sale_order so on esoa.source_object_id = so.id" , "left")
            ->where($where)
            ->count();
    }
    /*
    * @params:
    *  field:需要查询的字段
    *  where:查询的条件
    * @return :
    * @desc:获取销售计划单的详情
    * @author：小黑
    * @time:2019-2-18
    */
    public function info($field , $where){
        return $this->field($field)->where($where)->find() ;
    }
    /*
   * @params:
   *  data:修改的数据
   *  where:查询的条件
   * @return :
   * @desc:修改销售计划单
   * @author：小黑
   * @time:2019-2-18
   */
    public function updateApply($field , $where){
        return $this->where($where)->save($field) ;
    }

}