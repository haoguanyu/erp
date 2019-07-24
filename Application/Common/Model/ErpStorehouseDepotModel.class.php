<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/27
 * Time: 14:40
 */

namespace Common\Model;


class ErpStorehouseDepotModel extends BaseModel
{
    //根据字段查询相关得字段
    public function getSomeWord($field , $where){
        return $this->where($where)->getField($field);
    }
    //根据字段查询相关得字段
    public function getInfo($field , $where){
        return $this->field($field)->where($where)->find();
    }
}