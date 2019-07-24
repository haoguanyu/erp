<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/27
 * Time: 14:26
 */

namespace Home\Controller;
use Think\Controller;
use Home\Controller\BaseController;

class ErpStorehouseDepotController extends BaseController
{
    /*
     * @params:
     *  $storehouseId:仓库信息
     * @return;json
     * @desc:根据仓库信息获取对应得油库信息
     * @auth:小黑
     * @time:2019-3-27
     */
    public function getStorehouseDepot(){
        $storehouseId = isset($_POST['storehouseId']) ? $_POST['storehouseId'] : "";
        $data = $this->getEvent("ErpStorehouseDepot","Home")->getStorehouseDepot($storehouseId);
        $this->echoJson($data);
    }
}