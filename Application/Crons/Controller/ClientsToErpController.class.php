<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/11/21
 * Time: 9:55
 */

namespace Crons\Controller;

class ClientsToErpController extends BaseController
{
    /*
     * 银行的数据信息同步到erp中
     * @author:小黑
     * @data:2018-11-21
     */
    public function backToErp(){
        $this->getEvent("ClientsToErp")->backToErp() ;
        exit ;
    }
}