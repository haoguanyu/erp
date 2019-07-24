<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/7
 * Time: 9:59
 */

namespace BaseApi\Controller;



use Think\Log;

class ErpBatchController extends BaseController
{
    /*
     * @params [array]
     *    $stock_id:库存编号
     *    $goods_id:商品编号
     *    $storehouse_id:仓库信息
     *    $our_company_id:账套信息
     * @return :array
     * @auth:小黑
     * @time:2019-3-7
     * @desc:根据参数信息，获取相关的数据
     */
    public function getBatchList(){
        $params = $_REQUEST ;
        $returnData = $bactchList = $this->getEvent("ErpBatch")->erpBatchList($params);
        $returnData['return_data'] = date("Y-m-d H:i:s");
        Log::write(json_encode($returnData)) ;
        $this->echoJson($bactchList) ;
//        echo json_encode($bactchList) ;exit ;
    }
}