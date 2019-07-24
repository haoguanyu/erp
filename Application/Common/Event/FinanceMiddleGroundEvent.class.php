<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/7
 * Time: 16:01
 */

namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class FinanceMiddleGroundEvent extends BaseController
{
    /**
     * 销售订单信息查询
     * @author guanyu
     * @time 2019-06-18
     */
    public function fmgSelectInvoice($order_id = 1,$order_number = '')
    {
        $url = 'invoiceSale/salesStatus';
        $param[] = [
            "order_id"=>$order_id,
            "order_num"=>$order_number,
        ];
        $result = fmdInterface($param,$url);
        $log_info_data = array(
            'event'=> '财务中台开票状态查询',
            'key'=> '推送路径：' . $url,
            'request'=> $param,
            'response'=> $result['code'],
        );
        log_write($log_info_data);
        return $result;
    }

    /**
     * 撤销开票申请
     * @author guanyu
     * @time 2019-06-18
     */
    public function fmgCancelInvoice($order_id = 1,$order_number = '')
    {
        $url = 'invoiceSale/recall';
        $param[] = [
            "order_id"=>$order_id,
            "order_num"=>$order_number,
        ];
        $result = fmdInterface($param,$url);
        $log_info_data = array(
            'event'=> '撤销开票申请',
            'key'=> '推送路径：' . $url,
            'request'=> $param,
            'response'=> $result['code'],
        );
        log_write($log_info_data);
        return $result;
    }
}