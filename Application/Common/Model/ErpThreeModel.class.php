<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/8
 * Time: 8:49
 */

namespace Common\Model;


class ErpThreeModel extends BaseModel
{
    public function addAllData($data){
        return $this->addAll($data) ;
    }
    /*
     * @params:
     *    $where
     * @return
     *      count
     * @auth:小黑
     * @tim：2019-3-16
     * 根据条件获取数量
     */
    public function countErpThree($where){
        return $this->where($where)->count() ;
    }
}