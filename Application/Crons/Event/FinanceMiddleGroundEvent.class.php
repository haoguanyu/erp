<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/7
 * Time: 16:01
 */

namespace Crons\Event;


class FinanceMiddleGroundEvent extends BaseEvent
{
    /**
     * 申请发票
     * @author guanyu
     * @time 2019-06-18
     */
    public function fmgApplicationInvoice()
    {
        M()->startTrans();
        $url = 'invoiceSale/add';
        $field = 'o.order_number,o.id,o.company_id,o.order_amount,o.goods_id,o.buy_num,o.price,o.collection_status,
        o.our_company_id,o.add_order_time,o.pay_type,c.customer_name,c.tax_number,c.registered_address,c.registered_bank,
        c.registered_bank_number,c.registered_tel,oc.customer_name as our_company_name,oc.tax_number as our_tax_number,
        g.goods_code,g.goods_name,g.source_from,g.level,g.grade';
        $where = [
            'o.order_type' => 1,
            'o.order_status' => 10,
            'o.invoice_status' => ['neq',10],
            'o.order_amount - o.price * o.returned_goods_num / 10000' => ['neq',0],
            'o.finance_invoice_status' => 2,
            'o.our_company_id' => ['not in','70,22,18']
//            'o.our_company_id' => 15412,
//            '_string' => '!ISNULL(c.id)',
        ];
        //查询所有满足待开票状态的销售订单
        $sale_order_info = $this->getModel('ErpSaleOrder')
            ->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
            ->join('oil_erp_customer oc on ec.company_name = oc.customer_name', 'left')
            ->join('oil_erp_customer c on o.company_id = c.id', 'left')
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->order('o.id desc')
            ->select();
        $invoice_data = [];
        $index = 0;
        foreach ($sale_order_info as $value) {
            //除账期付款类型以外的销售单，必须已收款才会进入财务中台（之后可能有调整）
            if ($value['pay_type'] != 2 && $value['collection_status'] != 10) {
                continue;
            }
            //基础资料不全的会过滤掉，等补全以后再推到财务中台
            if (trim($value['tax_number']) == '') {
                continue;
            }
            if (trim($value['registered_address']) == '') {
                continue;
            }
            if (trim($value['registered_bank']) == '') {
                continue;
            }
            if (trim($value['registered_bank_number']) == '') {
                continue;
            }
            $invoice_data[] = [
                "order_num"=>$value['order_number'],
                "order_id"=>$value['id'],
                "order_time"=>$value['add_order_time'],
                "seller_id"=>$value['our_company_id'],
                "seller_name"=>trim($value['our_company_name']),
                "seller_taxno"=>trim($value['our_tax_number']),
                "buyer_id"=>$value['company_id'],
                "buyer_name"=>trim($value['customer_name']),
                "buyer_taxno"=>trim($value['tax_number']),
                "buyer_address"=>trim($value['registered_address']),
                "buyer_tel"=>trim($value['registered_tel']),
                "buyer_bank"=>trim($value['registered_bank']),
                "buyer_bankno"=>trim($value['registered_bank_number']),
                "invoice_type"=>"1",
                "remarks"=>"",
                "request_amount"=>getNum($value['order_amount']),
                "source"=>"1",
                "order_details"=>[
                    [
                        "order_detail_num"=>$value['goods_id'],
                        "item_cate"=>$value['goods_name'],
                        "goods_name"=>$value['source_from'].'/'.$value['goods_name'].'/'.$value['grade'].'/'.$value['level'],
                        "goods_num"=>getNum($value['buy_num']),
                        "goods_unit"=>1,
                        "goods_cost"=>getNum($value['order_amount']),
                        "goods_price"=>getNum($value['price']),
                        "tax_sign"=>"1",
                        "goods_rate"=>"13",
                        "discount_sum"=>"0",
                        "level"=>$value['level']
                    ],
                ],
            ];
            log_info('推送到财务中台的销售单号：'.$value['order_number']);
            //推到财务中台后给销售单加一个标记
            $sale_order_data = [
                'finance_invoice_status' => 1,
                'update_time' => currentTime()
            ];
            $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $value['id'],'order_number' => $value['order_number']], $sale_order_data);
            $index ++;
            //每十五条调用一次
            if ($index == 15) {
                log_info('本次推送数据：'.json_encode($invoice_data));
                $result = fmdInterface($invoice_data,$url);
                $log_info_data = array(
                    'event'=> '推送发票数据到财务中台',
                    'key'=> '推送路径：' . $url,
                    'request'=> $invoice_data,
                    'response'=> $result['code'],
                );
                log_write($log_info_data);
                if ($result['code'] != 200) {
                    M()->rollback();
                    echo $result['message'];exit;
                }
                //调用接口完毕后返回正确提交本次事务，开启下次事务
                M()->commit();
                M()->startTrans();
                $index = 0;
                $invoice_data = [];
            }
        }
        if (!empty($invoice_data)) {
            log_info('本次推送数据：'.json_encode($invoice_data));
            $result = fmdInterface($invoice_data,$url);
            $log_info_data = array(
                'event'=> '推送发票数据到财务中台',
                'key'=> '推送路径：' . $url,
                'request'=> $invoice_data,
                'response'=> $result['code'],
            );
            log_write($log_info_data);
            if ($result['code'] != 200) {
                M()->rollback();
                echo $result['message'];exit;
            }
        }
        M()->commit();
        echo $result['message'];exit;
    }

    /**
     * 接收发票回传信息
     * @author guanyu
     * @time 2019-06-18
     */
    public function fmgReceiveInvoicePostBack($params)
    {
        log_info(json_encode($params));
        $invoice_status_all = true;
        $sale_order_status_all = true;
        if (empty($params['action_list'])) {
            $result = [
                "code" => 1002,
                "message" => "action_list参数错误",
                "data" => [],
            ];
            return $result;
        }
        M()->startTrans();
        foreach ($params['action_list'] as $invoice) {
            $invoice_number = $invoice['invoice_number'];
            if (empty($invoice['order_list'])) {
                M()->rollback();
                $result = [
                    "code" => 1003,
                    "message" => "order_list参数错误",
                    "data" => [],
                ];
                return $result;
            }
            foreach ($invoice['order_list'] as $sale_order) {
                $sale_order_id = $sale_order['order_id'];
                $sale_order_info = $this->getModel('ErpSaleOrder')->where(['id'=>$sale_order_id])->find();
                //销售单总金额
                $order_amount = intval($sale_order_info['order_amount'] - setNum(round(getNum($sale_order_info['loss_num']) * getNum($sale_order_info['price']) + getNum($sale_order_info['returned_goods_num']) * getNum($sale_order_info['price']),2)));
                if (empty($sale_order['order_detail_ids'])) {
                    M()->rollback();
                    $result = [
                        "code" => 1003,
                        "message" => "order_detail_ids参数错误",
                        "data" => [],
                    ];
                    return $result;
                }
                foreach ($sale_order['order_detail_ids'] as $order_detail) {
                    $realized_amount = $order_detail['realized_amount'];
                    $tax_money = $realized_amount / (1 + 0.13) * 0.13;
                    $notax_invoice_money = $realized_amount - $tax_money ;
                    //添加销售发票信息
                    $invoice_data = [
                        'sale_order_id' => $sale_order_info['id'],
                        'sale_order_number' => $sale_order_info['order_number'],
                        'invoice_sn' => $invoice_number,
                        'notax_invoice_money' => setNum($notax_invoice_money),
                        'tax_money' => setNum($tax_money),
                        'invoice_money' => setNum($realized_amount),
                        'order_invoiced_money' => $sale_order_info['invoiced_amount'] + setNum($realized_amount),
                        'invoice_type' => 7,//默认13%增票
                        'creator' => 'SYS',//
                        'creator_id' => 0,//
                        'create_time' => currentTime(),//
                        'status' => 1,
                        'source' => 3,
                        'remark' => '',//
                    ];
                    log_info('销售单id：'.$sale_order_id.'的发票信息：'.json_encode($invoice_data));
                    $invoice_status = $this->getModel('ErpSaleInvoice')->add($invoice_data);
                    $invoice_status_all = $invoice_status && $invoice_status_all ? true : false;
                    //已开金额
                    $invoiced_money = intval(round($invoice_data['invoice_money'])) + intval($sale_order_info['invoiced_amount']);
                    if (intval($invoiced_money == 0)) {
                        $invoice_status = 1;
                    }
                    elseif (intval($invoiced_money) < intval($order_amount)) {
                        $invoice_status = 2;
                    }
                    elseif (intval($invoiced_money) == intval($order_amount)) {
                        $invoice_status = 10;
                    }

                    $sale_order_data = [
                        'invoice_status' => $invoice_status,
                        'invoiced_amount' => $invoice_data['order_invoiced_money'],
                        'updater' => 0,
                        'update_time' => currentTime()
                    ];

                    //修改销售单状态
                    $sale_order_status = $this->getModel('ErpSaleOrder')->saveSaleOrder(['id' => $sale_order_id],$sale_order_data);
                    log_info('销售单id：'.$sale_order_id.'的订单状态：'.json_encode($sale_order_data).'，修改是否成功？'.$sale_order_status);
                    $sale_order_status_all = $sale_order_status && $sale_order_status_all ? true : false;
                }
            }
        }
        if ($invoice_status_all && $sale_order_status_all) {
            M()->commit();
            $result = [
                "code" => 200,
                "message" => "成功",
                "data" => [],
            ];
        } else {
            M()->rollback();
            $result = [
                "code" => 1000,
                "message" => "失败",
                "data" => [],
            ];
        }
        return $result;
    }
}