<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/27
 * Time: 14:33
 */

namespace Common\Event;


use Common\Controller\BaseController;

class ErpStorehouseDepotEvent extends BaseController
{
    /*
     * @params:
     *  $storehouseId:仓库信息
     * @return;json
     * @desc:根据仓库信息获取对应得油库得编号
     * @auth:小黑
     * @time:2019-3-27
     */
    public function getStorehouseToDepotId($storehouseId){
        $field = "id , depot_id" ;
        $where = [
            "storehouse_id" => $storehouseId ,
            "status" => 1
        ];
        $depotIds = $this->getModel("ErpStorehouseDepot")->getSomeWord($field , $where);
        return $depotIds ;
    }
    /*
     * @params:
     *  $storehouseId:仓库信息
     * @return;json
     * @desc:根据仓库信息获取对应得油库得编号
     * @auth:小黑
     * @time:2019-3-27
     */
    public function getStorehouseDepotInfo($field,$where){
        $where['status'] = 1 ;
        $depotIds = $this->getModel("ErpStorehouseDepot")->getInfo($field , $where);
        return $depotIds ;
    }
}