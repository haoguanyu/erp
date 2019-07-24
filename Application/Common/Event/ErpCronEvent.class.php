<?php
/**
 * 定时任务业务处理层
 * @author xiaowen
 * @time 2017-04-17
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpCronEvent extends BaseController
{

    /**
     * 处理超时订单
     * @author xiaowen
     * @time 2017-4-21
     */
    public function handleTimeOutSaleOrder(){

        $data = $this->getEvent('ErpSale')->getTimeOutOrderList();
        echo "begin \n\r";
        if($data){

            foreach($data as $key=>$value){
                if($value['order_type'] == 1){
                    //edit xiaowen in_array($value['pay_type'],[1, 3]) 修改为 in_array($value['pay_type'],[1]) 代采现结不用取消 2017-6-26
                    //如果已超时且订单未取消并且订单未付款
                    //if( (in_array($value['pay_type'],[1,3]) && $value['collection_status'] == 1 && $value['order_status'] != 2) || (in_array($value['pay_type'],[2, 4]) && !in_array($value['order_status'], [2, 10])) || (in_array($value['pay_type'],[5]) && $value['collection_status'] == 1 && $value['order_status'] != 2) ){
                    if( (in_array($value['pay_type'],[1,3,5]) && $value['collection_status'] == 1 && $value['order_status'] != 2) || (in_array($value['pay_type'],[2, 4]) && !in_array($value['order_status'], [2, 10])) ){

                        //edit xiaowen 自动取消需要先查看 oil_erp_sale_order_cancel 里该订单是否存在取消记录，只有不存在取消记录才能取消------
                        if(!$this->getEvent('ErpSale')->isCanceledOrder($value['order_number'])){

                            $collection_count = $this->getModel('ErpSaleCollection')->where(['sale_order_number'=>$value['order_number']])->count();
                            //edit xiaowen 2018-4-27 销售单自动取消条件：1、现结 3、代采现结 5、定金锁价 订单已确认且产生收款记录无法取消； 2 账期、4 货到付款 订单已确认无法取消
                            if((in_array($value['pay_type'],[1,3,5]) && $collection_count == 0) || in_array($value['pay_type'],[2,4])){
                                $status = $this->getEvent('ErpSale')->cancelTimeOutOrder($value['id']);
                                $str = $status ? '成功' : '失败';
                                echo ("销售单【".$value['order_number']."】, 因超时未处理, 系统自动取消，已处理" . $str ."\n\r");
                                log_info("销售单【".$value['order_number']."】, 因超时未处理, 系统自动取消，已处理" . $str);
                            }
//                          $this->sendDealerEmail($value['dealer_id'], '您有超时未处理订单已自动取消', "您的销售订单{$value['order_number']}因超时未处理，系统已自动取消，请悉知。");
                        }else{
                            echo ("销售单【".$value['order_number']."】, 已经被取消，无需再处理" . $str ."\n\r");
                            log_info("销售单【".$value['order_number']."】,  已经被取消，无需再处理" . $str);
                        }
                        //---------------------------------------------------------------------------------------------------------------------
                    }
                }

            }

        }
        echo "end \n\r";

    }

    /**
     * 返回即将超时订单列表
     * @author xiaowen
     * @time 2017-4-21
     */
    public function handleSoonTimeOutSaleOrder(){

        $data = $this->getEvent('ErpSale')->getSoonTimeOutOrderList();
        echo "begin \n";
        if($data){

            foreach($data as $key=>$value){
                if($value['order_type'] == 1) {
                    $collection_count = $this->getModel('ErpSaleCollection')->where(['sale_order_number'=>$value['order_number']])->count();
                    if($collection_count == 0){

                        //如果已超时且订单未取消并且订单未付款
                        if( (in_array($value['pay_type'],[1,3]) && $value['collection_status'] == 1 && $value['order_status'] != 2) || (in_array($value['pay_type'],[2, 4]) && !in_array($value['order_status'], [2, 10]))){
                            //把该订单 标识为已提醒
                            $this->getEvent('ErpSale')->saveSaleOrderById($value['id'], ['is_remind' => 1, 'update_time' => currentTime()]);

                            log_info("销售单【" . $value['order_number'] . "】, 即将超时，发送邮件提醒。");
                            echo("销售单【" . $value['order_number'] . "】, 即将超时，发送邮件提醒。" . "\n");
                            $this->sendDealerEmail($value['dealer_id'], '您的销售即将超时,请及时处理', "您的销售订单{$value['order_number']}将在{$value['end_order_time']}超时，请及时处理, 超时后系统将自动取消，请悉知。");
                            S('remind_order_' . $value['order_number'], 1); //标识该订单已提醒
                        }

                    }

                }
            }
        }
        echo "end \n";

    }

    /**
     * 返回即将超时的定金锁价订单
     * @author xiaowen
     * @time 2017-4-21
     */
    public function handleSoonTimeOutEarnestSaleOrder(){

        $data = $this->getEvent('ErpSale')->getSoonTimeOutOrderList(2);
        echo "begin \n";
        if($data){

            foreach($data as $key=>$value){
                if($value['order_type'] == 1) {
                    //如果已超时且订单未取消并且订单未付款
                    if(in_array($value['pay_type'],[5]) && $value['collection_status'] == 1 && $value['order_status'] != 2){
                        //把该订单 标识为已提醒
                        $this->getEvent('ErpSale')->saveSaleOrderById($value['id'], ['is_remind' => 1, 'update_time' => currentTime()]);

                        log_info("销售单【" . $value['order_number'] . "】, 即将超时，发送邮件提醒。");
                        echo("销售单【" . $value['order_number'] . "】, 即将超时，发送邮件提醒。" . "\n");
                        $this->sendDealerEmail($value['dealer_id'], '您的销售即将超时,请及时处理', "您的销售订单{$value['order_number']}将在{$value['end_order_time']}超时，请及时处理, 超时后系统将自动取消，请悉知。");
                    }
                }
            }

        }

        echo "end \n";

    }

    /**
     * 给交易员发送邮件
     * @param $dealer_id
     * @param $title
     * @param $content
     * @author xiaowen
     * @time 2017-4-21
     */
    public function sendDealerEmail($dealer_id, $title, $content){
        $dealer_email = $this->getModel('Dealer')->where(['id' => $dealer_id])->getField('dealer_email');
        sendEmail($title, $content, $dealer_email);
    }

    /**
     * 取消超时的商城发布单
     * @author xiaowen
     * @time 2017-5-24
     */
    public function handleCancelTimeOutSupplyMall(){

        echo "begin \n";
        $where = [
            's.is_available' => 1,
            's.create_time' => ['lt', date("Y-m-d 23:59:59", strtotime('-1 days'))],
        ];

        $data = $this->getModel('ErpSupplyMall')->getSupplyByWhere($where, 's.id');
        if($data){
            $ids = array_column($data, 'id');
            $update_data = [
                'is_available' => 2,
                'show_front' => 2,
                'update_time' => currentTime(),
            ];
            $status = $this->getModel('ErpSupplyMall')->saveSupply(['id'=>['in', $ids]], $update_data);
            $status_str = $status == 1 ? '成功' : '失败';
            echo ('自动取消超时商城发布单'.$status_str.'！已取消的发布单ID(' . implode(',', $ids) . ')' . "\n");
            log_info('自动取消超时商城发布单'.$status_str.'！已取消的发布单ID(' . implode(',', $ids) . ')');
        }

        echo "end \n";
    }

    /**
     * 下架超时的供货单
     * @author xiaowen
     * @time 2017-5-24
     */
    public function handleDownUpTimeOutSupply(){

        echo "begin \n";
        $where = [
            's.status' => 10,
            's.show_front' => 1,
            's.create_time' => ['lt', date("Y-m-d 23:59:59", strtotime('-1 days'))],
        ];

        $data = $this->getModel('ErpSupply')->getSupplyByWhere($where, 's.id');
        if($data){
            $ids = array_column($data, 'id');
            $update_data = [
                'show_front' => 2,
                //'status' => 1,
                'update_time' => currentTime(),
            ];
            $status = $this->getModel('ErpSupply')->saveSupply(['id'=>['in', $ids]], $update_data);
            $status_str = $status == 1 ? '成功' : '失败';
            echo ('自动取消超时商城发布单'.$status_str.'！已取消的发布单ID(' . implode(',', $ids) . ')' . "\n");
            log_info('自动取消超时商城发布单'.$status_str.'！已取消的发布单ID(' . implode(',', $ids) . ')');
        }

        echo "end \n";
    }

    /**
     * 区域商品维护价格清零
     * @author guanyu
     * @time 2017-11-21
     */
    public function clearRegionGoodsPrice(){

        echo "begin \n";
        $where = [
            'price' => ['neq',0],
            'status' => 1,
        ];

        $data = $this->getModel('ErpRegionGoods')->getAllRegionGoodsList($where, 'id');
        if($data['data']){
            $ids = array_column($data['data'], 'id');
            $ids = implode(',',$ids);
            $sql = "Update oil_erp_region_goods set last_price = price, price = 0, update_time = NOW() where id in (" . $ids . ")";
            $status = $this->getModel('ErpRegionGoods')->execute($sql);
            $status_str = $status ? '成功' : '失败';
            echo ('区域商品维护价格清零'.$status_str.'！已清零的区域商品维护ID(' . $ids . ')' . "\n");
            log_info('区域商品维护价格清零'.$status_str.'！已清零的区域商品维护ID(' . $ids . ')');
        }

        echo "end \n";
    }

}
