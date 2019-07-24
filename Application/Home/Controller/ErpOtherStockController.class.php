<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * 其他入、其他出控制器
 * @author xiaowen
 * @time 2019-5-15
 */
class ErpOtherStockController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP库存逻辑层
    // +----------------------------------
    // |Author:senpai Time:2019.5.15
    // +----------------------------------

    /**
     *  其他入库单列表
     * @author yf
     * @time 2017-05-04
     */
    public function erpStockInList()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $param['otherStock'] = 1;
            $data = $this->getEvent('ErpOtherStock')->erpStockList($param);
            $this->echoJson($data);
        }
        $data['lossTypeStatus'] = lossTypeStatus();
        $data['stockInStatus']  = stockInStatus();
        $this->assign(['data' => $data]);
        $this->display();
    }

    /**
     * 其他出库单列表
     * @author yf
     * @time 2019-05-15
     */
    public function erpStockOutList()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $param['otherStock'] = 2;
            $data = $this->getEvent('ErpOtherStock')->erpStockList($param);
            $this->echoJson($data);
        }
        $data['lossTypeStatus'] = lossTypeStatus();
        $data['stockOutStatus']  = stockOutStatus();
        $this->assign(['data' => $data]);
        $this->display();
    }


    /**
     * 导出入\出库单
     * @author yf
     * @time 2019-05-15
     */
    public function exportStock()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;
        /* ---------- otherStock 1 : 入库单 2： 出库单 -------------*/
        $stock_type = $param['otherStock'];
        if ( $stock_type == 1 ) {
            // 生成菜单头
            (array)$header = [
                '序号','订单日期','单据编号','来源单号','入库类型','损耗类型','供应商公司','人员','城市','仓库','商品','入库数量（吨）','单价','入库金额','状态'
            ];
            $file_name_arr = '其他入库列表' . currentTime('Ymd').'.csv';
            $table_name = 'ErpStockIn';
        } else {
            // 生成菜单头
            (array)$header = [
                '序号','订单日期','单据编号','来源单号','来源运费单号','出库类型','损耗类型','供应商公司','人员','城市','仓库','商品','出库数量（吨）','单价','出库成本','状态'
            ];
            $file_name_arr = '其他出库列表' . currentTime('Ymd').'.csv';
            $table_name = 'ErpStockOut';
        }
        
        (array)$stock_arr = [];
        $k = 0;
        // 获取总条数
        $where = $this->getEvent('ErpOtherStock')->stockWhere($param);
        $num = $this->getModel($table_name)->alias('stock')
                ->join('oil_erp_loss_order loss on loss.order_number = stock.source_number', 'left')
                ->where($where)->field('stock.id')->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_loss_arr = $this->getEvent('ErpOtherStock')->erpStockList($param)['data'];
            foreach ($get_loss_arr as $key => $value) {
                $stock_arr[$k]['id']                    = $value['id'];
                $stock_arr[$k]['create_time']           = $value['create_time'];
                if ( $stock_type == 1 ) {
                    $stock_arr[$k]['storage_code']      = $value['storage_code'];
                } else {
                    $stock_arr[$k]['outbound_code']     = $value['outbound_code'];
                }
                $stock_arr[$k]['source_number']         = $value['source_number'];
                if ( $stock_type == 2 ) {
                    $stock_arr[$k]['source_freight_order']      = $value['source_freight_order'];
                }
                $stock_arr[$k]['type_name']             = $value['type_name'];
                $stock_arr[$k]['loss_type_name']        = $value['loss_type_name'];
                $stock_arr[$k]['company_name']          = $value['company_name'];
                $stock_arr[$k]['dealer_name']           = $value['dealer_name'];
                $stock_arr[$k]['region_name']           = $value['region_name'];
                $stock_arr[$k]['storehouse_name']       = ($value['storehouse_name']);
                $stock_arr[$k]['goods_name']            = ($value['goods_name']);
                $stock_arr[$k]['stock_num']             = ($value['stock_num']);
                $stock_arr[$k]['price']                 = ($value['price']);
                if ( $stock_type == 1 ) {
                    $stock_arr[$k]['all_price']         = ($value['all_price']);
                } else {
                    $stock_arr[$k]['cost']              = $value['cost'];
                }
               
                $stock_arr[$k]['order_status_name']     = ($value['order_status_name']);
                $k++;
            }
        }
        /********************* END *********************/
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成CSV内容
        $csvObj->exportCsv($stock_arr);
        //关闭文件句柄
        $csvObj->closeFile();
    }
   
}