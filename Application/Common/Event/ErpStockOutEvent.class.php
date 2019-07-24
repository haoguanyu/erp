<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/5/6
 * Time: 17:36
 */

namespace Common\Event;


use Common\Controller\BaseController;

class ErpStockOutEvent extends BaseController
{
    /*
     * @params
     *      $field:需要查询的参数信息
     *      $where:查询的条件
     * @return：array
     * @desc:根据条件获取出库单信息
     * @auth:小黑
     * @time:2019-5-6
     */
    public function getStockOutLists($field = "" , $where= []){
        if(empty($where)){
            return [];
        }
        if(!empty($field)) {
            $stockOutList = $this->getModel("ErpStockOut")->stockOutGetField($where, $field);
        }else{
            $stockOutList = $this->getModel("ErpStockOut")->stockOutLists($where);
        }
        return $stockOutList ;
    }
}