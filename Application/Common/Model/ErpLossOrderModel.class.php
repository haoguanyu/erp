<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/5/8
 * Time: 14:08
 */

namespace Common\Model;


class ErpLossOrderModel extends BaseModel
{
    /*
    * @params:
    *      $where:查询条件
    * @return:int
    * @auth:小黑
    * @time:2019-5-9
    * @desc:根据条件查询有效的损耗单数量
    */
    public function getLossOrder($where){
        return $this->where($where)->count() ;
    }
}