<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/7
 * Time: 16:01
 */

namespace Crons\Event;


class ErpRetailInnerDemandEvent extends BaseEvent
{
    /*
    * @detail::获取零售订单需要调拨的数据
    * @auther:小黑
    * @dete:2018-12-6
    */
    public function needToAllocate(){
        $nowTime = date("Y-m-d H:i:s") ;
        //判断当天的数据是否存在
        $filedInnerDemand = "id as demand_id , demand_number , real_goods_num as need_number , storehouse_id , our_company_id";
        $startTime = date("Y-m-d 00:00:00" , strtotime($nowTime)) ;
        $endTime = date("Y-m-d 00:00:00" , strtotime("+1 day",strtotime($nowTime))) ;
        $whereInnerDemand = [
            "create_time"=> ['between' , [$startTime, $endTime]],
        ];
        $orderNeedList = $this->getNeedList($filedInnerDemand ,$whereInnerDemand);
        if(!empty($orderNeedList)){
            return $orderNeedList ;
        }
        //获取零售订单的数据总和
        $retailOrderNum = $this->getEvent("RetailOrder")->getRetailOrderNum();
        if(empty($retailOrderNum)){//未出库的订单主体网点不存在
            return [] ;
        }
//        $storeHouseId = array_unique(array_column($retailOrderNum, 'storehouse_id'));
        //网点的库存数|网点对于的主体的库存数
//        list($storeNum , $storeObjectNum) = $this->getEvent("Stock")->getStockNumber($storeHouseId);
//        if(empty($storeNum)){//主体库存未查询到
//            return [] ;
//        }
        $density = getConfig("Config_Density") ;//获取密度【为吨升转化用】
        //申明需要内部交易库存数变量
        $orderNeed = [] ;
        $notNeed = [] ;

        foreach ($retailOrderNum as &$value){
            $objectId = $value['storehouse_id'] ;
            $ourCompanyId = $value['our_company_id'] ;
            if(empty($value['need_number'])){//需求量为0
                continue ;
            }
            $demand_num_litre = $value['need_number'] ;
            /******************************获取需要内部交易真正库存数逻辑开始*****************************/
            $needTon = $value['need_number'] = round(literToTon($value['need_number'] , $density),4);//吨升转换

//            if(!isset($storeNum[$objectId]) || ($needTon > $storeNum[$objectId])){//需求量大于当前的网点库存量
//                $value['currentStoreNum'] =  isset($storeNum[$objectId]) ? $storeNum[$objectId] : 0 ;
//                $notNeed[] = $value ;
//                continue ;
//            }
            $objectNum = isset($storeObjectNum[$objectId][$ourCompanyId]) ? $storeObjectNum[$objectId][$ourCompanyId] : 0 ;
//            $storeNum[$objectId] -= $needTon ;//网点库存量减去当前需求量
            $value['objectStockOld'] = $objectNum;
//            if($needTon > $objectNum){//需求库存数量高于当前主体网点库存量
                $value['need_number'] -= $objectNum;
                $orderNumber = erpCodeNumber(18 , "" , $ourCompanyId)['order_number'] ;
                //入库参数整理
                $orderNeed[] = [
                    "demand_number" =>  $orderNumber,
                    "storehouse_id" => $objectId ,
                    "our_company_id" => $ourCompanyId ,
                    "demand_num" => $needTon ,
                    "real_goods_num" => $value['need_number'] ,
                    "current_stock_num" => $objectNum ,
                    "demand_num_litre" => $demand_num_litre,
                    "density" => $density ,
                    "create_time" =>$nowTime
                ];
                $storeObjectNum[$objectId][$ourCompanyId] = 0 ;
//            }else{
//                $value['need_number'] = 0 ;
//                $storeObjectNum[$objectId][$ourCompanyId] -= $needTon ;
//            }
            /******************************获取需要内部交易真正库存数逻辑结束*****************************/
        }
        if(!empty($orderNeed)){
            if(!$this->addLists($orderNeed)){
                return [];
            }
        }
        //库存数不足的发送邮件进行人工干预处理
//        $title = "无法生成内部交易单" ;
//        $code = json_encode($notNeed);
//        $dealer = "yuguangwei@51zhaoyou.com" ;
//        $copyCeiver = ["251326587@qq.com"];
//        sendEmail($title, $code, $dealer, $copyCeiver);
        //数据返回
        $whereInnerDemand = [
            "create_time"=> $nowTime
        ];
        $orderNeedList = $this->getEvent("ErpRetailInnerDemand")->getNeedList($filedInnerDemand ,$whereInnerDemand);

        return $orderNeedList ;
    }
    /*
   * @detail:根据需求获取内部需求单的数据
   * @auther:小黑
   * @dete:2018-12-6
   * @params
   *      filed :查询数据
   *      where：查询条件
   */
    public function getNeedList($field , $where){
        return M("erp_retail_inner_demand")->field($field)->where($where)->select();
    }
    /*
  * @detail:批量添加数据
  * @auther:小黑
  * @dete:2018-12-6
  * @params
  *     data数组
  */
    public function addLists($data = []){
        if(empty($data)){
            return false ;
        }
        return M("erp_retail_inner_demand")->addAll($data) ;
    }
}