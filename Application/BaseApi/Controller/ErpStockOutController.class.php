<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/7
 * Time: 18:40
 */

namespace BaseApi\Controller;


class ErpStockOutController extends BaseController
{
    public function addStockOut(){
        $params = $_REQUEST ;
        $number = json_decode($params['num'] , true);
        $params['number'] = $number ;
        $addStock = $this->getEvent("ErpStock")->addStockOutAll($params);
        $this->echoJson($addStock) ;
//        echo json_encode($addStock) ;exit ;
    }
}