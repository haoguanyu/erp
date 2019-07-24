<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/11/21
 * Time: 10:01
 */

namespace Crons\Event;


class ClientsToErpEvent extends BaseEvent
{
    /*
     * 企业的银行卡号转移到erp
     * @author:小黑
     * @data:2018-11-21
     */
    Public function backToErp(){
        $clientLastId = $this->getModel("Clients")->getClientsLim('id' , "" ,1, "id desc");
        print_r($clientLastId) ;
    }
}