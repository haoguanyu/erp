<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * ERP红冲管理控制器
 * @author xiaowen
 * Class ErpReverseController
 * @package Home\Controller
 */
class ErpReverseController extends BaseController
{

    /** --------------------------------------------------文哥------------------------------------------------------- */
    /**
     * 销售订单红冲
     * @author xiaowen
     */
    public function saleOrderReverse(){
        $id = intval(I('post.id', 0));
        if($id){
            $data = $this->getEvent('ErpReverse')->saleOrderReverse($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销售收款红冲
     * @author xiaowen
     */
    public function saleCollectionReverse(){
        $id = intval(I('post.id', 0));
        if($id){
            $data = $this->getEvent('ErpReverse')->saleCollectionReverse($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销售发票红冲
     * @author xiaowen
     */
    public function saleInvoiceReverse(){
        $id = intval(I('post.id', 0));
        if($id){
            $data = $this->getEvent('ErpReverse')->saleInvoiceReverse($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销售出库红冲
     * @author xiaowen
     */
    public function saleStockOutReverse(){
        $id = intval(I('post.id', 0));
        if($id){
            $data = $this->getEvent('ErpReverse')->saleStockOutReverse($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销售退货红冲
     * @author xiaowen
     */
    public function saleReturnedReverse(){
        $id = intval(I('post.id', 0));
        if($id){
            $data = $this->getEvent('ErpReverse')->saleStockOutReverse($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销退退款红冲
     */
    public function returnedCollectionReverse(){
        $id = intval(I('post.id', 0));
        if($id){
            $data = $this->getEvent('ErpReverse')->returnedCollectionReverse($id);
            $this->echoJson($data);
        }
    }

    /** --------------------------------------------------乾斌------------------------------------------------------- */

    /**
     * 调拨单红冲
     * @author qianbin
     * @time 2018-01-21
     */
    public function reverseAllocationOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReverse')->reverseAllocationOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 销退单红冲
     * @author qianbin
     * @time 2018-01-21
     */
    public function reverseReturnSaleOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReverse')->reverseReturnSaleOrder($id);
            $this->echoJson($data);
        }
    }




    /** --------------------------------------------------冠宇------------------------------------------------------- */

    /**
     * 采购单回滚
     * @author guanyu
     * @time 2018-01-18
     */
    public function reversePurchaseOrder(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReverse')->reversePurchaseOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 付款红冲
     * @author guanyu
     * @time 2018-01-19
     */
    public function reversePayment(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReverse')->reversePayment($id);
            $this->echoJson($data);
        }
    }

    /**
     * 入库单红冲
     * @author guanyu
     * @time 2018-01-21
     */
    public function reverseStockIn(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReverse')->reverseStockIn($id);
            $this->echoJson($data);
        }
    }

    /**
     * 采购发票红冲
     * @author guanyu
     * @time 2018-01-21
     */
    public function reversePurchaseInvoice(){
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReverse')->reversePurchaseInvoice($id);
            $this->echoJson($data);
        }
    }

}