<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
use Home\Lib\csvLib;
class ErpReportController extends BaseController
{

    /**
     * 城市仓库存盘点
     * @author guanyu
     * @time 2017-12-15
     */
    public function stockChecks ()
    {
        $param = $_REQUEST;
        $param['is_agent'] = 2;
        $param['stock_type'] = $param['stock_type'] ? $param['stock_type'] : 1;

        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->stockChecks($param);
            $this->echoJson($data);
        }

        //账套公司搜索条件
        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');
        $this->assign('erp_company', $erp_company);
        $this->assign('stock_type', $param['stock_type']);
        $this->display();
    }

    /**
     * 城市仓库存盘点
     * @author guanyu
     * @time 2017-12-15
     */
    public function agentStockChecks ()
    {
        $param = $_REQUEST;
        $param['is_agent'] = 1;

        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->stockChecks($param);
            $this->echoJson($data);
        }

        //账套公司搜索条件
        $erp_company = $this->getModel('ErpCompany')->getField('company_id,company_name');
        $this->assign('erp_company', $erp_company);
        $this->display();
    }

    /**
     * 导出城市仓库存盘点报表
     * @author guanyu
     * @time 2017-12-15
     */
    public function exportstockChecks(){
        $param = I('param.');
        $data = $this->getEvent('ErpReport')->stockChecks($param);
        if($data){
            foreach($data['data'] as $key=>$value){
                $export_data[$key]['id'] = $value['id'];
                $export_data[$key]['our_company_name'] = $value['our_company_name'];
                $export_data[$key]['region_name'] = $value['region_name'];
                $export_data[$key]['stock_type'] = $value['stock_type'];
                $export_data[$key]['storehouse_name'] = $value['storehouse_name'];
                $export_data[$key]['goods_code'] = $value['goods_code'];
                $export_data[$key]['goods_info'] = $value['goods_name'].'/'.$value['source_from'].'/'.$value['grade'].'/'.$value['level'];
                $export_data[$key]['stock_num'] = $value['stock_num'] ? $value['stock_num'] : '0';
                $export_data[$key]['inventory_stock_num'] = $value['inventory_stock_num'] ? $value['inventory_stock_num'] : '0';
                $export_data[$key]['first_stock'] = $value['first_stock'] ? $value['first_stock'] : '0';
                $export_data[$key]['transportation_num'] = $value['transportation_num'] ? $value['transportation_num'] : '0';
                $export_data[$key]['inventory_transportation_num'] = $value['inventory_transportation_num'] ? $value['inventory_transportation_num'] : '0';
                $export_data[$key]['sale_reserve_num'] = $value['sale_reserve_num'] ? $value['sale_reserve_num'] : '0';
                $export_data[$key]['inventory_sale_reserve_num'] = $value['inventory_sale_reserve_num'] ? $value['inventory_sale_reserve_num'] : '0';
                $export_data[$key]['sale_wait_num'] = $value['sale_wait_num'] ? $value['sale_wait_num'] : '0';
                $export_data[$key]['inventory_sale_wait_num'] = $value['inventory_sale_wait_num'] ? $value['inventory_sale_wait_num'] : '0';
                $export_data[$key]['allocation_reserve_num'] = $value['allocation_reserve_num'] ? $value['allocation_reserve_num'] : '0';
                $export_data[$key]['inventory_allocation_reserve_num'] = $value['inventory_allocation_reserve_num'] ? $value['inventory_allocation_reserve_num'] : '0';
                $export_data[$key]['allocation_wait_num'] = $value['allocation_wait_num'] ? $value['allocation_wait_num'] : '0';
                $export_data[$key]['inventory_allocation_wait_num'] = $value['inventory_allocation_wait_num'] ? $value['inventory_allocation_wait_num'] : '0';
                $export_data[$key]['available_num'] = $value['available_num'] ? $value['available_num'] : '0';
                $export_data[$key]['current_available_sale_num'] = $value['current_available_sale_num'] ? $value['current_available_sale_num'] : '0';
            }
        }

        $header = [
            'ID','账套公司','地区','仓库类型','仓库','产品代码','产品信息','物理库存','盘点物理库存','物理期初库存','在途库存','盘点在途库存',
            '销售预留','盘点销售预留','销售待提','盘点销售待提','配货预留','盘点配货预留','配货待提','盘点配货待提','可用库存','可售库存',
        ];
        array_unshift($export_data,  $header);
        $file_name_arr = '城市仓库存报表'.currentTime().'.xls';
        create_xls($export_data, $filename=$file_name_arr);
    }

    /**
     * 导出代采仓库存盘点报表
     * @author guanyu
     * @time 2017-12-15
     */
    public function exportagentStockChecks(){
        $param = I('param.');
        $data = $this->getEvent('ErpReport')->stockChecks($param);
        if($data){
            foreach($data['data'] as $key=>$value){
                $export_data[$key]['id'] = $value['id'];
                $export_data[$key]['our_company_name'] = $value['our_company_name'];
                $export_data[$key]['region_name'] = $value['region_name'];
                $export_data[$key]['stock_type'] = $value['stock_type'];
                $export_data[$key]['storehouse_name'] = $value['storehouse_name'];
                $export_data[$key]['goods_code'] = $value['goods_code'];
                $export_data[$key]['goods_info'] = $value['goods_name'].'/'.$value['source_from'].'/'.$value['grade'].'/'.$value['level'];
                $export_data[$key]['stock_num'] = $value['stock_num'] ? $value['stock_num'] : '0';
                $export_data[$key]['inventory_stock_num'] = $value['inventory_stock_num'] ? $value['inventory_stock_num'] : '0';
                $export_data[$key]['transportation_num'] = $value['transportation_num'] ? $value['transportation_num'] : '0';
                $export_data[$key]['inventory_transportation_num'] = $value['inventory_transportation_num'] ? $value['inventory_transportation_num'] : '0';
                $export_data[$key]['sale_reserve_num'] = $value['sale_reserve_num'] ? $value['sale_reserve_num'] : '0';
                $export_data[$key]['inventory_sale_reserve_num'] = $value['inventory_sale_reserve_num'] ? $value['inventory_sale_reserve_num'] : '0';
                $export_data[$key]['sale_wait_num'] = $value['sale_wait_num'] ? $value['sale_wait_num'] : '0';
                $export_data[$key]['inventory_sale_wait_num'] = $value['inventory_sale_wait_num'] ? $value['inventory_sale_wait_num'] : '0';
                $export_data[$key]['allocation_reserve_num'] = $value['allocation_reserve_num'] ? $value['allocation_reserve_num'] : '0';
                $export_data[$key]['inventory_allocation_reserve_num'] = $value['inventory_allocation_reserve_num'] ? $value['inventory_allocation_reserve_num'] : '0';
                $export_data[$key]['allocation_wait_num'] = $value['allocation_wait_num'] ? $value['allocation_wait_num'] : '0';
                $export_data[$key]['inventory_allocation_wait_num'] = $value['inventory_allocation_wait_num'] ? $value['inventory_allocation_wait_num'] : '0';
                $export_data[$key]['available_num'] = $value['available_num'] ? $value['available_num'] : '0';
                $export_data[$key]['current_available_sale_num'] = $value['current_available_sale_num'] ? $value['current_available_sale_num'] : '0';
            }
        }

        $header = [
            'ID','账套公司','地区','仓库类型','仓库','产品代码','产品信息','物理库存','盘点物理库存','在途库存','盘点在途库存',
            '销售预留','盘点销售预留','销售待提','盘点销售待提','配货预留','盘点配货预留','配货待提','盘点配货待提','可用库存','可售库存',
        ];
        array_unshift($export_data,  $header);
        $file_name_arr = '代采仓库存报表'.currentTime().'.xls';
        create_xls($export_data, $filename=$file_name_arr);
    }

    /*
     * ------------------------------------------
     * 库存查询
     * Author：qianbin        Time：2018-04-16
     * ------------------------------------------
     */
    public function erpStockList()
    {
        if(IS_AJAX){
            $param = $_REQUEST;
            $data = $this->getEvent('ErpReport')->getStockList($param);
            $this->echoJson($data);
        }

        $data['stockType'] = stockType();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['provinceList'] = provinceCityZone()['province'];

        //搜索条件加入全国，目前仅仅在库存查询中有需求
        $data['provinceList'][1] = '全国';
        $city2 = cityLevelData()['city2'];
        $city2[1][] = ['id' => 1,'parent_id' => 1,'area_name' => '全国'];
        $data['city2'] = json_encode($city2);

        //将全国属性仓库，整合到region 为 1 键值对中
        $getStoreHouseData = getStoreHouseData();

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                if ($value['whole_country'] == 1) {
                    $new_storehouse[1][] = $value;
                } else {
                    $new_storehouse[$value['region']][] = $value;
                }
            }
        }
        $data['regionStorehouse'] = json_encode($new_storehouse);

        $new_stocktype = storehouseTypeToStockType();
        $data['stockTypeToStorehouseType'] = json_encode($new_stocktype);

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 导出库存数据
     * @author xiaowen
     * @time 2017-5-24
     */
    public function exportStockData(){
        $param = I('param.');
        $param['export'] = 1;
        $data = $this->getEvent('ErpReport')->getStockList($param);
        $arr  = [];
        foreach ($data['data'] as $k => $v) {
            $arr[$k]['id'] = $v['id'];
            $arr[$k]['region_name'] = $v['region_name'];
            $arr[$k]['stock_type'] = $v['stock_type'];
            $arr[$k]['facilitator_name'] = $v['facilitator_name'];
            $arr[$k]['object_name'] = $v['object_name'];
            $arr[$k]['goods_code'] = $v['goods_code']  . '/' .$v['source_from']. '/'  .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
            $arr[$k]['stock_num'] = "".$v['stock_num'];
            $arr[$k]['transportation_num'] = "".$v['transportation_num'];
            $arr[$k]['sale_reserve_num'] = "".$v['sale_reserve_num'];
            $arr[$k]['allocation_reserve_num'] = "".$v['allocation_reserve_num'];
            $arr[$k]['sale_wait_num'] = "".$v['sale_wait_num'];
            $arr[$k]['allocation_wait_num'] = "".$v['allocation_wait_num'];
            $arr[$k]['available_num'] = "".$v['available_num'];
            $arr[$k]['current_available_sale_num'] = "".$v['current_available_sale_num'];
            $arr[$k]['cost'] = "".$v['cost'];
        }
        $header = ['序号','地区','仓库类型','服务商','仓库','产品代码','物理库存','在途库存','销售预留','配货预留','销售待提','配货待提','可用库存','可售库存','成本'];
        array_unshift($arr,  $header);
        create_xls($arr,$filename='库存记录'.currentTime().'.xls');
    }

    /**
     * 加油网点升量报表盘点
     * @author guanyu
     * @time 2017-12-15
     */
    public function facilitatorSkidStockChecks ()
    {
        $param = $_REQUEST;

        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->facilitatorSkidStockChecks($param);
            $this->echoJson($data);
        }

        $this->display();
    }

    /**
     * 导出加油网点升量报表报表
     * @author guanyu
     * @time 2017-12-15
     */
    public function exportFacilitatorSkidStockChecks(){
        $param = I('param.');
        $data = $this->getEvent('ErpReport')->facilitatorSkidStockChecks($param);
        if($data){
            foreach($data['data'] as $key=>$value){
                $export_data[$key]['id'] = $value['id'];
                $export_data[$key]['stock_type'] = $value['stock_type'];
                $export_data[$key]['facilitator_name'] = $value['facilitator_name'];
                $export_data[$key]['object_name'] = $value['object_name'];
                $export_data[$key]['goods_code'] = $value['goods_code'];
                $export_data[$key]['goods_name'] = $value['goods_name'];
                $export_data[$key]['source_from'] = $value['source_from'];
                $export_data[$key]['grade'] = $value['grade'];
                $export_data[$key]['level'] = $value['level'];
                $export_data[$key]['stock_first'] = $value['stock_first'] ? $value['stock_first'] : '0';
                $export_data[$key]['allocation_stock_in_num'] = $value['allocation_stock_in_num'] ? $value['allocation_stock_in_num'] : '0';
                $export_data[$key]['allocation_stock_out_num'] = $value['allocation_stock_out_num'] ? $value['allocation_stock_out_num'] : '0';
                $export_data[$key]['retail_stock_out_num'] = $value['retail_stock_out_num'] ? $value['retail_stock_out_num'] : '0';
                $export_data[$key]['galaxy_stock_out_num'] = $value['galaxy_stock_out_num'] ? $value['galaxy_stock_out_num'] : '0';
                $export_data[$key]['inventory_balance'] = $value['inventory_balance'] ? $value['inventory_balance'] : '0';
            }
        }

        $header = [
            '序号','仓库类型','服务商名称','加油网点名称','商品代码','商品名称','商品来源','商品标号','商品级别',
            '8月2日期初库存','加油网点调拨入','加油网点调拨出','小微零售出','集团零售出','结余'
        ];
        array_unshift($export_data,  $header);
        $file_name_arr = '加油网点升量报表'.currentTime().'.xls';
        create_xls($export_data, $filename=$file_name_arr);
    }

    /**
     * 历史库存查询
     * @author guanyu
     * @time 2018-05-04
     */
    public function historyStockList ()
    {
        $param = $_REQUEST;

        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->historyStockList($param);
            $this->echoJson($data);
        }
        $data['stockType'] = stockType();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['provinceList'] = provinceCityZone()['province'];

        //搜索条件加入全国，目前仅仅在库存查询中有需求
        $city2 = cityLevelData()['city2'];
        $data['city2'] = json_encode($city2);

        //将全国属性仓库，整合到region 为 1 键值对中
        $getStoreHouseData = getStoreHouseData();

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                $new_storehouse[$value['region']][] = $value;
            }
        }
        $data['regionStorehouse'] = json_encode($new_storehouse);

        $new_stocktype = storehouseTypeToStockType();
        $data['stockTypeToStorehouseType'] = json_encode($new_stocktype);

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出出入库明细报表
     * @author guanyu
     * @time 2018-05-10
     */
    public function exportHistoryStockList(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '出入库明细报表'. currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','仓库类型','仓库','产品代码','产品信息','业务单号','来源单号','变动数量','单价','库存数结存','单位成本','库存成本'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->historyStockList($param)['data']) && ($count = $this->getEvent('ErpReport')->historyStockList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['stock_type']          = $v['stock_type'];
                $arr[$k]['storehouse_name']     = $v['storehouse_name'];
                $arr[$k]['goods_code']          = $v['goods_code'];
                $arr[$k]['goods_name']          = $v['goods_name'].'/'.$v['source_from'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['source_order_number'] = $v['source_order_number'];
                $arr[$k]['source_number']       = $v['source_number'];
                $arr[$k]['change_num']          = $v['change_num'];
                $arr[$k]['price']               = $v['price'];
                $arr[$k]['after_stock_num']     = $v['after_stock_num'];
                $arr[$k]['after_price']         = $v['after_price'];
                $arr[$k]['after_stock_price']   = $v['after_stock_price'];
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

    /*
     * ------------------------------------------
     * 库存查询 (包含资金占用待提， 资金占用在途)
     * Author：qianbin        Time：2018-07-11
     * ------------------------------------------
     */
    public function erpStockAllList()
    {
        if(IS_AJAX){
            $param = $_REQUEST;
            $data = $this->getEvent('ErpReport')->getStockAllList($param);
            $this->echoJson($data);
        }

        $data['stockType'] = stockType();
        # $data['regionList'] = provinceCityZone()['city'];
        $data['provinceList'] = provinceCityZone()['province'];

        //搜索条件加入全国，目前仅仅在库存查询中有需求
        $data['provinceList'][1] = '全国';
        $city2 = cityLevelData()['city2'];
        $city2[1][] = ['id' => 1,'parent_id' => 1,'area_name' => '全国'];
        $data['city2'] = json_encode($city2);

        //将全国属性仓库，整合到region 为 1 键值对中
        $getStoreHouseData = getStoreHouseData();

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                if ($value['whole_country'] == 1) {
                    $new_storehouse[1][] = $value;
                } else {
                    $new_storehouse[$value['region']][] = $value;
                }
            }
        }
        $data['regionStorehouse'] = json_encode($new_storehouse);

        $new_stocktype = storehouseTypeToStockType();
        $data['stockTypeToStorehouseType'] = json_encode($new_stocktype);

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();

    }


    /**
     * 导出库存数据
     * @author qianbin
     * @time 2017-7-18
     */
    public function exportStockAllData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = $_REQUEST;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '库存记录'. currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成菜单行
        $header = [
            '序号','地区','仓库类型','服务商','仓库','产品代码',
            '物理库存','在途库存','销售预留','配货预留','销售待提',
            '配货待提','可用库存','可售库存','定金在途库存','定金待提库存','成本',
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while (($data = $this->getEvent('ErpReport')->getStockAllList($param)['data']) && ($count = $this->getEvent('ErpReport')->getStockAllList($param)['recordsTotal'])) {
            $arr = [];
            foreach ($data as $k=>$v) {
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['region_name'] = $v['region_name'];
                $arr[$k]['stock_type'] = $v['stock_type'];
                $arr[$k]['facilitator_name'] = $v['facilitator_name'];
                $arr[$k]['object_name'] = $v['object_name'];
                $arr[$k]['goods_code'] = $v['goods_code']  . '/' .$v['source_from']. '/'  .$v['goods_name']. '/' .$v['grade']. '/' .$v['level'];
                $arr[$k]['stock_num'] = "".$v['stock_num'];
                $arr[$k]['transportation_num'] = "".$v['transportation_num'];
                $arr[$k]['sale_reserve_num'] = "".$v['sale_reserve_num'];
                $arr[$k]['allocation_reserve_num'] = "".$v['allocation_reserve_num'];
                $arr[$k]['sale_wait_num'] = "".$v['sale_wait_num'];
                $arr[$k]['allocation_wait_num'] = "".$v['allocation_wait_num'];
                $arr[$k]['available_num'] = "".$v['available_num'];
                $arr[$k]['current_available_sale_num'] = "".$v['current_available_sale_num'];
                $arr[$k]['purchase_wait_num_prepay'] = "".$v['purchase_wait_num_prepay'];
                $arr[$k]['sale_wait_num_prepay'] = "".$v['sale_wait_num_prepay'];
                $arr[$k]['cost'] = "".$v['cost'];
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

    /**
     * 一级仓库存出入库汇总报表
     * @author guanyu
     * @time 2018-08-09
     */
    public function stockInOutSummaryLevelOne()
    {
        $param = $_REQUEST;
        $param['stock_type'] = 1;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->stockInOutSummary($param);
            $this->echoJson($data);
        }

        $data['storehouseData'] = getStoreHouseData(['type'=>['neq',2]]);

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出一级仓库存出入库汇总数据
     * @author guanyu
     * @time 2018-08-23
     */
    public function exportStockInOutSummaryLevelOne(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['stock_type'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '一级仓库存出入库汇总报表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','仓库','商品','期初数量','期初成本','本期入库数量','本期入库金额','本期出库数量','本期出库成本',
            '期末数量','期末成本'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        $data = $this->getEvent('ErpReport')->stockInOutSummary($param)['data'];
        $arr = [];
        foreach($data as $k=>$v){
            $arr[$k]['id']                      = $v['id'];
            $arr[$k]['object_name']             = $v['object_name'];
            $arr[$k]['goods_code']              = $v['goods_code'];
            $arr[$k]['first_stock_num']         = $v['first_stock_num'];
            $arr[$k]['first_stock_price']       = $v['first_stock_price'];
            $arr[$k]['total_in_stock_num']      = $v['total_in_stock_num'];
            $arr[$k]['total_in_stock_price']    = $v['total_in_stock_price'];
            $arr[$k]['total_out_stock_num']     = $v['total_out_stock_num'];
            $arr[$k]['total_out_stock_price']   = $v['total_out_stock_price'];
            $arr[$k]['last_stock_num']          = $v['last_stock_num'];
            $arr[$k]['last_stock_price']        = $v['last_stock_price'];
        }
        //分批生成CSV内容
        $csvObj->exportCsv($arr);
        //查询下一页数据，设置起始偏移量
        $page++;
        $param['start'] = ($page - 1 ) * $param['length'];

        //关闭文件句柄
        $csvObj->closeFile();
    }

    /**
     * 二级仓库存出入库汇总报表
     * @author guanyu
     * @time 2018-08-09
     */
    public function stockInOutSummaryLevelTwo()
    {
        $param = $_REQUEST;
        $param['stock_type'] = 4;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->stockInOutSummary($param);
            $this->echoJson($data);
        }

        //获取所有网点数据
        $getStoreHouseData = getStoreHouseData(['type'=>7]);

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                $new_storehouse[$key] = ['id'=>$key,'name'=>$value['storehouse_name']];
            }
        }
        $data['facilitatorSkidData'] = $new_storehouse;
        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出二级仓库存出入库汇总数据
     * @author guanyu
     * @time 2018-08-23
     */
    public function exportStockInOutSummaryLevelTwo(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['stock_type'] = 4;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '一级仓库存出入库汇总报表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','仓库','商品','期初数量','期初成本','本期入库数量','本期入库金额','本期出库数量','本期出库成本',
            '期末数量','期末成本'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        $data = $this->getEvent('ErpReport')->stockInOutSummary($param)['data'];
        $arr = [];
        foreach($data as $k=>$v){
            $arr[$k]['id']                      = $v['id'];
            $arr[$k]['object_name']             = $v['object_name'];
            $arr[$k]['goods_code']              = $v['goods_code'];
            $arr[$k]['first_stock_num']         = $v['first_stock_num'];
            $arr[$k]['first_stock_price']       = $v['first_stock_price'];
            $arr[$k]['total_in_stock_num']      = $v['total_in_stock_num'];
            $arr[$k]['total_in_stock_price']    = $v['total_in_stock_price'];
            $arr[$k]['total_out_stock_num']     = $v['total_out_stock_num'];
            $arr[$k]['total_out_stock_price']   = $v['total_out_stock_price'];
            $arr[$k]['last_stock_num']          = $v['last_stock_num'];
            $arr[$k]['last_stock_price']        = $v['last_stock_price'];
        }
        //分批生成CSV内容
        $csvObj->exportCsv($arr);
        //查询下一页数据，设置起始偏移量
        $page++;
        $param['start'] = ($page - 1 ) * $param['length'];

        //关闭文件句柄
        $csvObj->closeFile();
    }

    /**
     * 一级仓库存变动明细报表
     * @author guanyu
     * @time 2018-08-23
     */
    public function stockInOutDetailLevelOne()
    {
        $param = $_REQUEST;
        $param['stock_type'] = 1;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->stockInOutDetail($param);
            $this->echoJson($data);
        }

        $data['storehouseData'] = getStoreHouseData(['type'=>['neq',2]]);

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出一级仓库存变动明细报表
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportStockInOutDetailLevelOne(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['stock_type'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '一级仓库存变动明细报表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','日期','单据编号','单据类型','仓库','商品','期初数量','期初成本','本期发生数量','本期发生金额','期末数量',
            '期末成本'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->stockInOutDetail($param)['data']) && ($count = $this->getEvent('ErpReport')->stockInOutDetail($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['create_time']         = $v['create_time'];
                $arr[$k]['source_number']       = $v['source_number'];
                $arr[$k]['type_font']           = $v['type_font'];
                $arr[$k]['object_name']         = $v['object_name'];
                $arr[$k]['goods_code']          = $v['goods_code'];
                $arr[$k]['before_stock_num']    = $v['before_stock_num'];
                $arr[$k]['before_stock_price']  = $v['before_stock_price'];
                $arr[$k]['change_num']          = $v['change_num'];
                $arr[$k]['change_stock_price']  = $v['change_stock_price'];
                $arr[$k]['after_stock_num']     = $v['after_stock_num'];
                $arr[$k]['stock_price']         = $v['stock_price'];
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

    /**
     * 二级仓库存变动明细报表
     * @author guanyu
     * @time 2018-08-23
     */
    public function stockInOutDetailLevelTwo()
    {
        $param = $_REQUEST;
        $param['stock_type'] = 4;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->stockInOutDetail($param);
            $this->echoJson($data);
        }

        //获取所有网点数据
        $getStoreHouseData = getStoreHouseData(['type'=>7]);

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                $new_storehouse[$key] = ['id'=>$key,'name'=>$value['storehouse_name']];
            }
        }
        $data['facilitatorSkidData'] = $new_storehouse;

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出二级仓库存变动明细报表
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportStockInOutDetailLevelTwo(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['stock_type'] = 4;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '二级仓库存变动明细报表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','日期','单据编号','单据类型','仓库','商品','期初数量','期初成本','本期发生数量','本期发生金额','期末数量',
            '期末成本'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->stockInOutDetail($param)['data']) && ($count = $this->getEvent('ErpReport')->stockInOutDetail($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['create_time']         = $v['create_time'];
                $arr[$k]['source_number']       = $v['source_number'];
                $arr[$k]['type_font']           = $v['type_font'];
                $arr[$k]['object_name']         = $v['object_name'];
                $arr[$k]['goods_code']          = $v['goods_code'];
                $arr[$k]['before_stock_num']    = $v['before_stock_num'];
                $arr[$k]['before_stock_price']  = $v['before_stock_price'];
                $arr[$k]['change_num']          = $v['change_num'];
                $arr[$k]['change_stock_price']  = $v['change_stock_price'];
                $arr[$k]['after_stock_num']     = $v['after_stock_num'];
                $arr[$k]['stock_price']         = $v['stock_price'];
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

    /**
     * 批发销售分析报表
     * @author guanyu
     * @time 2018-08-28
     */
    public function saleAnalysisWhole()
    {
        $param = $_REQUEST;
        $param['type'] = 'whole';
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->saleAnalysis($param);
            $this->echoJson($data);
        }

        $data['city'] = provinceCityZone()['city'];

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出批发销售分析报表
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportSaleAnalysisWhole(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['type'] = 'whole';
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '批发销售分析报表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','日期','城市','销售单号','单据类型','商品','出库数量','销售单价','销售金额','销售成本','毛利','毛利率'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->saleAnalysis($param)['data']) && ($count = $this->getEvent('ErpReport')->saleAnalysis($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['create_time']         = $v['create_time'];
                $arr[$k]['region_name']         = $v['region_name'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['order_type']          = $v['order_type'];
                $arr[$k]['goods_code']          = $v['goods_code'];
                $arr[$k]['outbound_quantity']   = $v['outbound_quantity'];
                $arr[$k]['price']               = $v['price'];
                $arr[$k]['order_amount']        = $v['order_amount'];
                $arr[$k]['sale_cost']           = $v['sale_cost'];
                $arr[$k]['gross_profit']        = $v['gross_profit'];
                $arr[$k]['gross_profit_rate']   = $v['gross_profit_rate'];
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

    /**
     * 零售销售分析报表
     * @author guanyu
     * @time 2018-08-28
     */
    public function saleAnalysisRetail()
    {
        $param = $_REQUEST;
        $param['type'] = 'retail';
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->saleAnalysis($param);
            $this->echoJson($data);
        }

        $data['city'] = provinceCityZone()['city'];

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出零售销售分析报表
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportSaleAnalysisRetail(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['type'] = 'retail';
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '零售销售分析报表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','日期','城市','销售单号','单据类型','商品','出库数量','销售单价','销售金额','销售成本','毛利','毛利率'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->saleAnalysis($param)['data']) && ($count = $this->getEvent('ErpReport')->saleAnalysis($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['create_time']         = $v['create_time'];
                $arr[$k]['region_name']         = $v['region_name'];
                $arr[$k]['order_number']        = $v['order_number'];
                $arr[$k]['order_type']          = $v['order_type'];
                $arr[$k]['goods_code']          = $v['goods_code'];
                $arr[$k]['outbound_quantity']   = $v['outbound_quantity'];
                $arr[$k]['price']               = $v['price'];
                $arr[$k]['order_amount']        = $v['order_amount'];
                $arr[$k]['sale_cost']           = $v['sale_cost'];
                $arr[$k]['gross_profit']        = $v['gross_profit'];
                $arr[$k]['gross_profit_rate']   = $v['gross_profit_rate'];
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

    /**
     * 一级仓历史库存查询
     * @author guanyu
     * @time 2018-08-30
     */
    public function historyStockSearchLevelOne()
    {
        $param = $_REQUEST;
        $param['stock_type'] = 1;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->historyStockSearch($param);
            $this->echoJson($data);
        }

        $data['storehouseData'] = getStoreHouseData(['type'=>['neq',2]]);

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出一级仓历史库存查询
     * @author guanyu
     * @time 2018-08-30
     */
    public function exportHistoryStockSearchLevelOne(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['stock_type'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '一级仓历史库存查询' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            '序号','仓库','商品','物理库存'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->historyStockSearch($param)['data']) && ($count = $this->getEvent('ErpReport')->historyStockSearch($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                      = $v['id'];
                $arr[$k]['object_name']             = $v['object_name'];
                $arr[$k]['goods_code']              = $v['goods_code'];
                $arr[$k]['last_stock_num']          = $v['last_stock_num'];
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

    /**
     * 二级仓历史库存查询
     * @author guanyu
     * @time 2018-08-30
     */
    public function historyStockSearchLevelTwo()
    {
        $param = $_REQUEST;
        $param['stock_type'] = 4;
        if (IS_AJAX) {
            $data = $this->getEvent('ErpReport')->historyStockSearch($param);
            $this->echoJson($data);
        }

        //获取所有网点数据
        $getStoreHouseData = getStoreHouseData(['type'=>7]);

        $new_storehouse = [];
        if ($getStoreHouseData) {
            foreach ($getStoreHouseData as $key => $value) {
                $new_storehouse[$key] = ['id'=>$key,'name'=>$value['storehouse_name']];
            }
        }
        $data['facilitatorSkidData'] = $new_storehouse;

        $data['erpGoods'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 导出一级仓历史库存查询
     * @author guanyu
     * @time 2018-08-30
     */
    public function exportHistoryStockSearchLevelTwo(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['stock_type'] = 4;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name = '二级仓历史库存查询' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成菜单行
        $header = [
            '序号','仓库','商品','物理库存'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpReport')->historyStockSearch($param)['data']) && ($count = $this->getEvent('ErpReport')->historyStockSearch($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                      = $v['id'];
                $arr[$k]['object_name']             = $v['object_name'];
                $arr[$k]['goods_code']              = $v['goods_code'];
                $arr[$k]['last_stock_num']          = $v['last_stock_num'];
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
