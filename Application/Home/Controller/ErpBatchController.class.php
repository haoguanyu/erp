<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpBatchController extends BaseController
{

    /***********************************
    @ Content 批次列表导出
     ************************************/
    public function exportBatchData()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;

        // 生成菜单头
        (array)$header = [
            '序号','系统批次号','货权类型','外部货权号','城市','商品代码','商品名称','商品来源','商品标号','商品级别','仓库','批次数量','批次库存','预留数量','实际可用数量','批次状态'
        ];
        (array)$batch_arr = [];
        $k = 0;
        // // 获取总条数
        $where = $this->getEvent('ErpBatch')->handleBatchWhere($param);
        $num = $this->getModel('ErpBatch')->alias('b')->where($where)->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_batch_arr = $this->getEvent('ErpBatch')->erpBatchList($param)['data'];
            foreach ($get_batch_arr as $key => $value) {
                $batch_arr[$k]['id'] 			= $value['id'];
                $batch_arr[$k]['sys_bn'] 		= $value['sys_bn'];
                $batch_arr[$k]['cargo_bn_type'] = $value['cargo_bn_type'];
                $batch_arr[$k]['cargo_bn'] 		= $value['cargo_bn'];
                $batch_arr[$k]['region'] 		= $value['region'];
                $batch_arr[$k]['goods_code'] 	= $value['goods_code'];
                $batch_arr[$k]['goods_name'] 	= $value['goods_name'];
                $batch_arr[$k]['source_from'] 	= $value['source_from'];
                $batch_arr[$k]['grade'] 		= $value['grade'];
                $batch_arr[$k]['level'] 		= $value['level'];
                $batch_arr[$k]['storehouse_name'] = $value['storehouse_name'];
                $batch_arr[$k]['total_num'] 	= ($value['total_num']);
                $batch_arr[$k]['balance_num'] 	= ($value['balance_num']);
                $batch_arr[$k]['reserve_num'] 	= ($value['reserve_num']);
                $batch_arr[$k]['actual_balance_num'] 	= ($value['actual_balance_num']);
                $batch_arr[$k]['status'] 		= $value['status'];
                $k++;
            }
        }
        /********************* END *********************/
        $file_name_arr = '批次列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成CSV内容
        $csvObj->exportCsv($batch_arr);
        //关闭文件句柄
        $csvObj->closeFile();
    }

    /**************************************
        @ Content 根据采购退货单查询批次列表
    ***************************************/
    public function erpBatchListByPurchaseReturnOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent("ErpBatch")->erpBatchListByPurchaseReturnOrder($param);
            $this->echoJson($data);
        }
    }

    /**************************************
    @ Content 修改批次 and 货权
     ***************************************/
    public function updateBatchAndCargo()
    {
        $param = I('post.');
        if ( !isset($param['batch_id']) || empty($param['batch_id'])) {
            return ['status' => 2,'message' => '缺少批次ID！'];
        }
        if ( !isset($param['cargo_bn_type']) || empty($param['cargo_bn_type'])) {
            return ['status' => 3,'message' => '缺少货权类型！'];
        }
        if ( !isset($param['cargo_bn']) || empty($param['cargo_bn'])) {
            return ['status' => 4,'message' => '缺少货权号！'];
        }
        $data = $this->getEvent('ErpBatch')->updateBatchAndCargo($param);
        $this->echoJson($data);
    }


		/***************************************
			@ Content 批次列表
			@ Author YF
			@ Time 2019-02-18
		****************************************/
		public function erpBatchList()
		{
			if (IS_AJAX) {
				$param = $_REQUEST;
                if(isset($param['source'])){
                    if($param['source'] == 1){//来源嵌套使用，提出取消的批次信息
                        $param['status'] =  [1 , 2] ;
                    }
                }
                $param['show_all'] = isset($param['show_all']) && $param['show_all'] == 1 ? 1 : 0;
				$data = $this->getEvent("ErpBatch")->erpBatchList($param);
            	$this->echoJson($data);
			}
			(array)$data = [];
			// 查询货权类型
			$data['cargo_bn_type'] = getConfigByType(2);
            $data['cargo_bn_type'][] = [
                'id'    => 1,
                'value' => 0,
                'name'  => '货权初始化'
            ];
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
			// 查询商品
			$data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
            $data['regionList'] = provinceCityZone()['city'];
            $data['regionList'][1] = '全国';
            $data['batchStatusList'] = erpBatchStatus();
			$this->assign('data', $data);
			$this->display();
		}

    /***************************************
    @ Content 批次已使用列表（供调拨入和销退使用）
    @ Author guanyu
    @ Time 2019-02-26
     ****************************************/
    public function erpBatchUseList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent("ErpBatch")->erpBatchUseList($param);
            $this->echoJson($data);
        }
        (array)$data = [];
        // 查询货权类型
        $data['cargo_bn_type'] = getConfigByType(2);
        // 查询商品
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $this->assign('data', $data);
        $this->display();
    }

    /************************************
    @ Content 系统批次编辑
    @ Author YF
    @ Time 2019-02-19
    @ Param [
    'id' => 批次列表的ID
    ]
    @ Return [
    data => [
    batch_arr     => 批次数据，
    cargo_bn_type => 货权类型
    ]
    ]
     *************************************/
    public function updateErpBatch()
    {
        $batch_id = I('get.id');
        (array)$data = [];
        $where['o.id'] = ['eq',$batch_id];
        // 获取批次信息
        $data['batch_arr'] = $this->getEvent('ErpBatch')->FindBatch($where);
        $data['batch_operate_log'] = [];
        if ( isset( $data['batch_arr']['sys_bn'] ) ) {
            $data['batch_operate_log'] = $this->getEvent('ErpBatch')->getBatchOperateLog($data['batch_arr']['sys_bn']);
        }
        // 查询货权类型
        $data['cargo_bn_type'] = getConfigByType(2);
        $this->assign('data', $data);
        $this->display();
    }


    /*************************************
    @ Content 模糊查询系统批次
    @ Author Yf
    @ Time 2019-02-18
     **************************************/
    public function getBatchByBatchNum()
    {
        // search
        $param = trim(I('get.q'));
        $field = 'id,sys_bn';
        $where['sys_bn'] = ['like',['%'.trim($param).'%']];
        $data = $this->getEvent('ErpBatch')->searchBatch($where,$field);
        $batch_data = [];
        if ( $data['code'] == 0 && !empty($data['data']) ) {
            $batch_data['incomplete_results'] = true;
            $batch_data['total_count'] = count($data['data']);
            $batch_data['data'] = $data['data'];
        } else {
            $batch_data['incomplete_results'] = true;
            $batch_data['total_count'] = 0;
            $batch_data['data'] = [];
        }
        $this->echoJson($batch_data);

    }




}