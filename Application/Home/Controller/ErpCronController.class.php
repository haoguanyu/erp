<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * Class ErpCronController
 * @package Home\Controller
 * 定时任务控制器
 */
class ErpCronController extends BaseController
{
    /**
     * 定时取消超时订单(包含定金锁价定金锁价)
     * @author xiaowen
     * @time 2017-4-17
     */
    public function cancelTimeOutSaleOrder()
    {
        //取消超时订单
        $this->getEvent('ErpCron')->handleTimeOutSaleOrder();

    }

    /**
     * 定时提醒即将超时订单(不包含定金锁价)
     * @author xiaowen
     * @time 2017-4-17
     */
    public function remindTimeOutSaleOrder()
    {

        //提醒即将超时订单
        $this->getEvent('ErpCron')->handleSoonTimeOutSaleOrder();

    }

    /**
     * 定时提醒即将超时订单(定金锁价)
     * @author xiaowen
     * @time 2017-4-17
     */
    public function remindTimeOutEarnestSaleOrder()
    {

        //提醒即将超时订单(定金锁价)
        $this->getEvent('ErpCron')->handleSoonTimeOutEarnestSaleOrder();

    }


    /**
     * 定时取消超时商城发布单
     * @author xiaowen
     * @time 2017-5-24
     */
    public function cancelTimeOutSupplyMall()
    {

        //提醒即将超时订单
        $this->getEvent('ErpCron')->handleCancelTimeOutSupplyMall();

    }

    /**
     * 下架超时的供货单
     * @author xiaowen
     * @time 2017-5-24
     */
    public function downUpTimeOutSupply(){

        $this->getEvent('ErpCron')->handleDownUpTimeOutSupply();
    }

    /**
     * 区域商品维护价格清零
     * @author guanyu
     * @time 2017-11-21
     */
    public function clearRegionGoodsPrice()
    {

        //提醒即将超时订单
        $this->getEvent('ErpCron')->clearRegionGoodsPrice();

    }
}
