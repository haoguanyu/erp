<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/4/26
 * Time: 18:43
 */

namespace Common\Event;


use Common\Controller\BaseController;

class CargoBnEvent extends BaseController
{
    /*
     * @params :
     *      $field:查询得字段
     *      $where:查询条件
     * @return:array
     * @auth:小黑
     *
     */
    public function getFields($field , $where){
        return $this->getModel('ErpCargoBn')->where($where)->getField($field);
    }
}