<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/27
 * Time: 15:36
 */

namespace Home\Event;


use Home\Controller\BaseController;

class ErpStorehouseDepotEvent extends BaseController
{
    public function getStorehouseDepot($storehouseId){
        if(empty($storehouseId)){
            return ['status' => 1, "message" => "缺少仓库信息"];
        }
        $depotIds = $this->getEvent('ErpStorehouseDepot')->getStorehouseToDepotId($storehouseId);
        if(empty($depotIds)){
            $data =  ['status'=> 2 , "message" => "没有油库信息"] ;
            return $data;
        }
        //获取油库的信息
        $depotField = "id , concat(depot_name,'--',product) as depot_name" ;
        $depotWhere = [
            "id" => ['in' , $depotIds]
        ];
        $depotList = $this->getEvent("Depot")->getField($depotField, $depotWhere);
        $data=[
            "status" => 0 ,
            "message" => "成功" ,
            "list"=> $depotList
        ];
        return $data ;
    }
}