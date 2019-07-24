<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/19
 * Time: 15:00
 */

namespace Home\Event;


use Home\Controller\BaseController;

class ErpStockOutApplyEvent extends BaseController
{
    /*
     *  @params:
     *      data:
     *          source_object_id:来源编号
     *          remark：备注
     *          apply_num：购买的数量
     *  @return :
     *  @desc:添加申请销售单
     *  @author:小黑
     *  @time:2019-2-19
     */
    public function addApply($data){
        if(!isset($data['source_object_id']) || empty($data['source_object_id'])){
            return   $result = [
                'status' => 5,
                'message' => '请选择一个编号',
            ];
        }
        if(!isset($data['apply_num']) || empty($data['apply_num'])){
            return   $result = [
                'status' => 5,
                'message' => '请填写申请数量',
            ];
        }
        $data['data_source'] =  1 ;//订单的来源，erp后台
        $data['outbound_apply_type'] = 1 ;//销售单来源
        $data['creater_id'] = $this->getUserInfo('id') ;
        $data['creater_name'] = $this->getUserInfo('dealer_name') ;
        $returnData = $this->getEvent("ErpStockOutApply")->addApply($data);
        return  $returnData ;
    }
    /*
     *  @params:
     *      params:
     *      source:来源，1：全部，2：个人
     *  @return :
     *  @desc:获取申请单的基本信息
     *  @author:小黑
     *  @time:2019-2-19
     */
    public function getList($params , $source= 1){
//        $where = [
//            "esoa.outbound_apply_type" => 1 ,
//            "esoa.status" => 1
//        ];
        $where = [] ;
        if(isset($params['apply_number']) && !empty($params['apply_number'])){
            $where['esoa.outbound_apply_code'] =  ['like', '%' .trim($params["apply_number"])."%"];
        }
        if(isset($params['order_number']) && !empty($params['order_number'])){
            $where['esoa.source_number'] = ['like', '%' .trim($params["order_number"])."%"];
        }
        if(isset($params['goods_id']) && !empty($params['goods_id'])){
            $where['esoa.goods_id'] = $params["goods_id"];
        }
        if(isset($params['sale_company_id']) && !empty($params['sale_company_id'])){
            $where['so.company_id'] = $params["sale_company_id"];
        }
        if(isset($params['apply_status']) && !empty($params['apply_status'])){
            $where['esoa.status'] = $params["apply_status"];
        }
        if(isset($params['storehouse_id']) && !empty($params['storehouse_id'])){
            $where['so.storehouse_id'] = $params['storehouse_id'] ;
        }
        if(isset($params['start_time']) && !empty($params['start_time'])){
            $endtime= date("Y-m-d H:i:s");
            if(isset($params['end_time']) && !empty($params['end_time'])){
                $endtime = $params['end_time']." 23:59:59" ;
            }
            $where['esoa.create_time'] = ["between" , [$params['start_time'] , $endtime]];
        }
        if(empty($where)){
            $where['esoa.status'] = 1 ;
        }elseif(!isset($params['apply_status'])){
            $where['esoa.status'] = ['in' , [1, 10]] ;
        }
        if($source ==2){
            $where['esoa.creater_id'] = $this->getUserInfo('id') ;
        }
        $where['esoa.our_company_id'] = session('erp_company_id') ;
        $where['esoa.outbound_apply_type'] = 1 ;
        //获取总数据
        $data['recordsFiltered'] = $data['recordsTotal'] = $this->getModel("ErpStockOutApply")->getCount($where) ;//获取总数
        if($data['recordsFiltered'] > 0) {
            $field = "esoa.id as apply_id , esoa.outbound_apply_code , esoa.status , esoa.remark , "
                . "esoa.source_number , esoa.source_object_id ,esoa.goods_id , esoa.depot_id ,"
                . "esoa.outbound_apply_num , esoa.outbound_actual_num , esoa.create_time , "
                . "so.storehouse_id , so.dealer_name , so.user_id, so.company_id ";
            $orderList = $this->getModel("ErpStockOutApply")->getList($field, "esoa.id desc", $where , $params['start'], $params['length']);
            if($orderList){
               //获取用户信息
               $userIds = array_unique(array_column($orderList , "user_id")) ;
               $userWhere =["id"=>["in" , $userIds]];
               $userList = $this->getEvent("ErpCustomer")->getCustomerUserDataField($userWhere , "id , user_name");
               //获取公司信息
               $companyIds = array_unique(array_column($orderList , "company_id")) ;
               $companyWhere =["id"=>["in" , $companyIds]];
               $companyList = $this->getEvent("ErpCustomer")->getCustomerDataField($companyWhere , "id , customer_name");
               //获取商品信息
               $goodIds = array_unique(array_column($orderList , "goods_id")) ;
               $goodWhere = [
                   "id" => ["in" , $goodIds]
               ];
               $goodList = $this->getEvent("ErpGoods")->getAllGoods($goodWhere);
               foreach ($goodList as $value){
                   $goodLists[$value['id']] = $value['goods_code']."/".$value['goods_name']."/".$value['source_from']
                       ."/".$value['grade']."/".$value['level'];
               }
               //油库信息
               $depotIds = array_unique(array_column($orderList , "depot_id")) ;
               $depotWhere = ["id" => ['in' , $depotIds]];
               $deportList = $this->getEvent("Depot")->getListField("id , depot_name" , $depotWhere);
               //仓库信息
               $storehouseId = array_unique(array_column($orderList , "storehouse_id")) ;
               $storehouseWhere = ["id" =>["in", $storehouseId]] ;
               $storehouseList = $this->getEvent("ErpStorehouse")->getListField("id , storehouse_name" , $storehouseWhere);
               foreach ($orderList as &$v){
                   $v['userName'] = isset($userList[$v['user_id']]) ? $userList[$v['user_id']] : "-" ;
                   $v['companyName'] = isset($companyList[$v['company_id']])?$companyList[$v['company_id']]:"-" ;
                   $v['goodsName'] = isset($goodLists[$v['goods_id']]) ? $goodLists[$v['goods_id']] : "-";
                   $v['depotName'] = isset($deportList[$v['depot_id']])? $deportList[$v['depot_id']]: "-";
                   $v['storehouseName'] = isset($storehouseList[$v['storehouse_id']]) ? $storehouseList[$v['storehouse_id']]: "-" ;
                   $v['outbound_apply_num'] = getNum($v['outbound_apply_num']) ;
                   $v['outbound_actual_num'] = getNum($v['outbound_actual_num']) ;
                   $v['status'] = erpStockOutApplyStatus($v['status']);
               }
               $data['data'] = $orderList ;
           }else{
               $data['data']= [];
           }
        }else{
            $data['data']= [];
        }
        $data['draw'] = $_REQUEST['draw'];
        return $data ;
    }
    /*
   * @params:
        id:申请出库单的编号
   * @return:
   * @desc:我的申请出库单的取消
   * @author:小黑
   * @time:2019-2-20
   */
    public function delApply($id){
        if(empty($id)){
          return   $result = [
                'status' => 2,
                'message' => '请选择一个编号',
            ];

        }
        $data = $this->getEvent("ErpStockOutApply")->delApply($id);
        return $data ;
    }
    /*
   * @params:
        id:申请出库单的编号
   * @return:
   * @desc:我的申请出库单的详情
   * @author:小黑
   * @time:2019-2-20
   */
    public function getInfo($id){
        if(empty($id) ){
           return  $result = [
                'status' => 2,
                'message' => '请选择一个编号',
            ];
        }
        $where['id'] = $id ;
        $data = $this->getModel("ErpStockOutApply")->info("*" , $where);
        return $data ;
    }
    /*
  * @params:
       code:申请出库单的编号
  * @return:
  * @desc:我的申请出库单的详情
  * @author:小黑
  * @time:2019-2-20
  */
    public function getInfoCode($code){
        if(empty($code) ){
            return  $result = [
                'status' => 2,
                'message' => '请选择一个编号',
            ];
        }
        $where['outbound_apply_code'] = $code ;
        $data = $this->getModel("ErpStockOutApply")->info("*" , $where);
        return $data ;
    }
    /*
   * @params:
        id:申请出库单的编号
   * @return:
   * @desc:我的申请出库单的详情
   * @author:小黑
   * @time:2019-2-20
   */
    public function getApplyExtendData($saleOrderId){
        $region['region_list'] = provinceCityZone()['city'];
        $field = 'o.*,d.depot_name,c.customer_name s_company_name,cu.user_name s_user_name,cu.user_phone s_user_phone,
        ec.company_name b_company_name,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,
        es.storehouse_name,es.type as storehouse_type';

        $data['order'] = $this->getEvent('ErpSale')->findOneSaleOrder($saleOrderId, $field);

        $data['order']['price'] = getNum($data['order']['price']);

        $data['order']['prepay_ratio'] = getNum($data['order']['prepay_ratio']);
        $data['order']['order_amount'] = getNum($data['order']['order_amount']);
        $data['order']['delivery_money'] = getNum($data['order']['delivery_money']);
        //------------未出库数量 = 购买总数 - 已出库数量----------------------------------------------------------------
        if ($data['order']['pay_type'] == 5) {
            //获取所有该销售单生成的并且已审核的出库单
            $all_stockout_num = $this->getModel('ErpStockOut')->field('sum(actual_outbound_num) as total')->where(['source_object_id' => $data['order']['id'], 'source_number' => $data['order']['order_number'], 'outbound_status' => ['eq', 10]])->group('source_object_id')->find();
            $data['order']['no_outbound_quantity'] = getNum($data['order']['total_sale_wait_num'] - $all_stockout_num['total']);
        } else {
            $data['order']['no_outbound_quantity'] = getNum($data['order']['buy_num'] - $data['order']['outbound_quantity']);
        }
        //$useNum = $this->getEvent("ErpStockOutApply")->getApplyNum($data['order']) ;
        $useNum = $this->getEvent("ErpStockOutApply")->getStockOutApplyNum($data['order']) ;
        $data['useNum'] = getNum($useNum) ;
        //--------------------------------------------------------------------------------------------------------------
        $data['order']['buy_num'] = round(getNum($data['order']['buy_num']),4);
        $data['order']['add_order_time'] = date('Y-m-d', strtotime($data['order']['add_order_time']));
        $is_edit = $data['order']['order_status'] == 1 ? 1 : 0;

        $data['is_edit'] = $is_edit;
        if ($data['order']['is_upload_contract'] == 1) {
            $data['order']['contract_url'] = $this->uploads_path['sale_attach']['url'] . $data['order']['contract_url'];
        }
        if(empty($data['order']['depot_name'])){
            $data['order']['depot_name'] = "不限油库" ;
        }
        $data['order']['stock_type'] = StorehouseTypeToStockType($data['order']['storehouse_type']);
        //获取商品的密度

        $whereDensity = [
            "goods_id" => $data['order']['goods_id'],
            "region" => $data['order']['region']
        ];
        $density = $this->getEvent('ErpRegionGoods')->getOneGoodsByRegion($whereDensity) ;
        $data['order']['good_density'] = $density['density'];
        //按地区重新组装油库和仓库数量
        # 修改为  根据订单仓库id查询仓库
        $DepotData = getDepotData();
        $new_depots = getDepotToRegion($DepotData);
        $data['depots'] = json_encode($new_depots);
        $data['orderType'] = saleOrderType();

        $data['pay_type_list'] = saleOrderPayType();
        $data['saleOrderDeliveryMethod'] = saleOrderDeliveryMethod();
        $data['today'] = date('Y-m-d');
        $data['today'] = date('Y-m-d');
        $data['dealer_id'] = $this->getUserInfo('id');
        $data['dealer_name'] = $this->getUserInfo('dealer_name');
        return [$region , $data] ;
    }
    /*
     * @params:
          params:提交得参数
     * @return:
     * @desc:我的申请出库单的详情
     * @author:小黑
     * @time:2019-2-20
     */
    public function saveApply($params){
        if(!isset($params['id']) || empty($params['id'])){
            return   $result = [
                'status' => 5,
                'message' => '请选择一个编号',
            ];
        }
        if(!isset($params['apply_num']) || empty($params['apply_num'])){
            return   $result = [
                'status' => 6,
                'message' => '请填写申请数量',
            ];
        }
        $where = ["id" => $params['id'] , "status" => 1];
        $data['outbound_apply_num'] = $params["apply_num"] ;
        $data['remark'] = $params['remark'] ;
        $returnData = $this->getEvent('ErpStockOutApply')->saveApply($where , $data);
        return $returnData ;
    }
}