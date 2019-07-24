<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/18
 * Time: 16:01
 */

namespace Home\Controller;
use Home\Controller\BaseController;
use MongoDB\Operation\Count;

class ErpStockOutApplyController extends BaseController
{
    /*
     *@params:
     *     id:订单编号
     * @return:json
     * @deac:判断销售订单是否可以创建申请单
     * @author:小黑
     * @time:2019-2-18
     */
    public function isStockOutApply()
    {
        $id = intval(I('param.id', 0));
        $order_info = $this->getEvent('ErpSale')->findSaleOrder($id);//获取订单的详情
        $status = $this->getEvent('ErpStock')->checkOrderCanOutStock($order_info);//获取订单的状态
        $data['status'] = $status;
        if(!$status){//订单状态不可用
            $this->echoJson($data);
        }
        //$data['getNum'] = $this->getEvent("ErpStockOutApply")->getApplyNum($order_info) ;
        $data['getNum'] = $this->getEvent("ErpStockOutApply")->getStockOutApplyNum($order_info) ;
        $this->echoJson($data);
    }
    /*
     * @params
     *      id:销售单编号
     * @return:
     *      array or json
     * @desc:添加出库申请单
     * @author:小黑
     * @time:2019-2-19
     */
    public function addStockOutRequisition(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $param = I('param.');
            $data = $this->getEvent('ErpStockOutApply' , "Home")->addApply($param);
            $this->echoJson($data);
        }
        list($region , $data) = $this->getEvent("ErpStockOutApply", "Home")->getApplyExtendData($id);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }
    /*
     * @params:
     * @return:
     * @desc:申请出库单的列表
     * @author:小黑
     * @time:2019-2-20
     */
    public function getList(){
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent("ErpStockOutApply" , "Home")->getList($param);
            $this->echoJson($data);
        }
        $data['storehouse'] = $this->getEvent("ErpStorehouse")->getListField("id, storehouse_name , region") ;
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['stockOutApplyList'] = erpStockOutApplyStatus();
        $this->assign('data' , $data) ;
        $this->display();
    }

    /*
    * @params:
    * @return:
    * @desc:我的申请出库单的列表
    * @author:小黑
    * @time:2019-2-20
    */
    public function getMyList(){
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent("ErpStockOutApply" , "Home")->getList($param  , 2);
            $this->echoJson($data);
        }
        $data['storehouse'] = $this->getEvent("ErpStorehouse")->getListField("id, storehouse_name , region") ;

        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['stockOutApplyList'] = erpStockOutApplyStatus();
        $this->assign('data' , $data);
        $this->display();
    }
    /*
   * @params:
   * @return:
   * @desc:我的申请出库单的取消
   * @author:小黑
   * @time:2019-2-20
   */
    public function delApply(){
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpStockOutApply' , "Home")->delApply($id);
            $this->echoJson($data);
        }
    }
    /*
    * @params:
    * @return:
    * @desc:我的申请出库单的修改
    * @author:小黑
    * @time:2019-2-20
    */
    public function updateStockOutApply(){
        $id = intval(I('get.id', 0));
        if (IS_AJAX) {
            $param = I('param.');
            $data = $this->getEvent('ErpStockOutApply' , "Home")->saveApply($param);
            $this->echoJson($data);
        }
        $applyInfo = $this->getEvent("ErpStockOutApply", "Home")->getInfo($id);
        list($region , $data) = $this->getEvent("ErpStockOutApply", "Home")->getApplyExtendData($applyInfo['source_object_id']);
        $outbound_apply_num = getNum($applyInfo['outbound_apply_num']) ;
        $data['useNum'] = $data['useNum'] + $outbound_apply_num ;
        $applyInfo['outbound_apply_num'] = $outbound_apply_num;
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->assign('applyInfo', $applyInfo);
        $this->display();
    }
    /*
    * @params:
    * @return:
    * @desc:我的申请出库单的修改
    * @author:小黑
    * @time:2019-2-20

    public function saveApply(){
        if (IS_AJAX) {
            $param = I('param.');
            $data = $this->getEvent('ErpStockOutApply' , "Home")->saveApply($param);
            $this->echoJson($data);
        }
    }
    */
    /*
    * @params:
    * @return:
    * @desc:我的申请出库单的取消
    * @author:小黑
    * @time:2019-2-20
    */
    public function exportSaleOrderData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $search_type = intval(I('param.search_type', 1)); //默认导出所有
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name_arr = [
            1 => '申请出库单'   . currentTime('Ymd').'.csv',
            2 => '我的申请出库单'  . currentTime('Ymd').'.csv',
        ];
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr[$search_type]);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成菜单行
        $header = [
            'ID','订单时间' , "出库申请单号" , "来源单号" , "交易员" , "用户" ,"公司" , "仓库" ,
            "油库" , "商品代码" , "申请出库数量"  , "订单状态"
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpStockOutApply' , "Home")->getList($param , $search_type))){
            if(empty($data['data'])){
                break ;
            }
            $arr = [] ;
            foreach($data['data'] as $k=>$v){
                $arr[$k] = [
                    "id" => $v['apply_id'] ,
                    "create_time" => "\t".$v['create_time'] ,
                    "outbound_apply_code" => $v['outbound_apply_code'] ,
                    "source_number" => $v['source_number'] ,
                    "dealer_name" => $v['dealer_name'] ,
                    "userName" => $v['userName'] ,
                    "companyName" => $v['companyName'] ,
                    "storehouseName"=> $v['storehouseName'],
                    "depotName"=> $v['depotName'],
                    "goodsName"=> $v['goodsName'],
                    "outbound_apply_num"=> $v['outbound_apply_num'],
                    // "outbound_actual_num"=> $v['outbound_actual_num'],
                    "status"=> $v['status'],
                    "remark"=> $v['remark'],
                ];
            }
            //分批生成CSV内容
            $csvObj->exportCsv($arr);
            //查询下一页数据，设置起始偏移量
            $page++;
            $param['start'] = ($page - 1 ) * $param['length'];
        }
        //关闭文件句柄
        $csvObj->closeFile();
    }

}