<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * User: lizhipeng
 * Date: 2016/8/16
 * Time: 11:28
 */
class AreaModel extends BaseModel
{
    /**
     * 返回地区列表
     * @param bool|true $field
     * @param array $condition
     * @return array
     */
    public function getArea($field, $condition)
    {
        $result = M('area')->field($field)->where($condition)->find();
        return $result;
    }

    /*******************************************
        @ Content 获取所有的城市
        @ Author Syf
        @ Time 2018-11-16
        @ Param $field [sting]  字段名称
        @ Return [
                1(主键id) => 中国 (地区名称)  
        ]
    ********************************************/
    public function getAreaByField( $field = NULL )
    {
        if ( $field == NULL ) {
            return false;
        }
        $result = $this->getField($field);
        return $result;
    }

}