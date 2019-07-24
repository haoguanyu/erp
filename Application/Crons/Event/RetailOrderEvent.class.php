<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 13:49
 */

namespace Crons\Event;


class RetailOrderEvent extends BaseEvent
{
    /*
     * @detail:获取零售订单的需要核销的数量
     * @auther:小黑
     * @dete:2018-12-6
     */
    public function getRetailOrderNum(){
        $file = "sum(buy_num_retail-outbound_num_litre) as need_number,  our_company_id , storehouse_id";
        $where= [
            "is_reverse"=> 2,
            "is_all_outbound" => 0 ,
            "is_void" => 2 ,
            "is_returned" => 2 ,
            "reversed_status" => 2 ,
            "order_status" => 10 ,
        ];
        $group = "our_company_id , storehouse_id";
        $order  = "our_company_id , storehouse_id";
        $retailOrderNumber = $this->getModel('ErpSaleRetailOrder')->getOrderListGroup($file , $where , $group, $order);
        return $retailOrderNumber ;
    }
    /*
     * @detail:获取零售订单的网点数量
     * @auther:小黑
     * @dete:2018-12-6
     */
    public function getRetailOrderStork(){
        $file = "id, storehouse_id,our_company_id";
        $where= [
            "is_reverse"=> 2,
            "is_all_outbound" => 0 ,
            "is_void" => 2 ,
            "is_returned" => 2 ,
            "reversed_status" => 2 ,
            "order_status" => 10 ,
        ];
        $group = "storehouse_id , our_company_id";
        $retailOrderNumber = $this->getModel('ErpSaleRetailOrder')->getRetailOrderField($file , $where , $group);
        return $retailOrderNumber ;
    }
    /*
     * @detail:零售出入库单
     * @auther:小黑
     * @dete:2018-12-6
     */
    public function stockOut()
    {
        $statesTime = date('Y-m-d H:i:s');
        //获取零售订单的网点数据
        $retailOrderNum = $this->getRetailOrderStork();
        if (empty($retailOrderNum)) {//未出库的订单主体网点不存在
            return [];
        }
        $notOrderStorkId = [];
        foreach ($retailOrderNum as $value) {
            $storeId = $value['storehouse_id'];
            $ourCompanyId = $value['our_company_id'];
            $whereStcok = [
                "stock_num" => ['gt' , 0] ,
                "object_id" => $storeId ,
                "our_company_id" => $ourCompanyId ,
                "status" => 1
            ];
            $countNum = M("ErpStock")->where($whereStcok)->count();
            if($countNum < 1){
                continue ;
            }
            $outInfo = json_decode(retailOrderCreate($storeId, $ourCompanyId), true);
            /*
            $storeTimeEnd = date("Y-m-d H:i:s");
            if(isset($outInfo['code']) && ($outInfo['code'] == 0)){
                continue ;
            }else{
                $notOrderStorkId[]= array(
                    "message"=> $outInfo['message'],
                    "storeId"=> $storeId ,
                    "ourCompanyId" => $ourCompanyId ,
                    "startTime" => $storeTimeStart ,
                    "endTime" => $storeTimeEnd
                );
            }
        }
        $endTime = date("Y-m-d H:i:s") ;
        $list = [
            "startTime" => $statesTime ,
            "endTime" => $endTime ,
            "list" => $notOrderStorkId
        ];
        $title = "零售订单出库的脚本失败" ;
        $code = json_encode($list);
        $dealer = "yuguangwei@51zhaoyou.com" ;
        $copyCeiver = ["251326587@qq.com"];
        sendEmail($title, $code, $dealer, $copyCeiver);
        print_r($code) ;*/
        }
        return true;
    }
}