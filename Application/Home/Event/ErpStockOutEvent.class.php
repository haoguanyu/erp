<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/13
 * Time: 11:52
 */

namespace Home\Event;


use Home\Controller\BaseController;

class ErpStockOutEvent extends BaseController
{
    public function updateAttachment($iamgeUrl , $id){
        $data = ["attachment" => $iamgeUrl];
        $where = ["id" => $id ] ;
        return $this->getEvent("ErpStock")->updateStockOutInfo($data , $where);
    }
}