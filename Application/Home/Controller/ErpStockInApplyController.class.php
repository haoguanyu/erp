<?php
namespace Home\Controller;
use Home\Controller\BaseController;

class ErpStockInApplyController extends BaseController
{

    /**********************************
        @ Content 我的入库申请单列表
        @ Author  YF
        @ Time  2019-04-18
    ***********************************/
    public function myStockInApplyList()
    {
        if ( IS_AJAX ) {
            $param = $_REQUEST;
            $param['creater_id'] = $this->getUserInfo('id'); 
            $return_data = $this->getEvent('ErpStockInApply')->stockInApplyList($param);
            $this->echoJson($return_data);
        }
        $data['storehouse'] = $this->getEvent("ErpStorehouse")->getListField("id, storehouse_name") ;
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['stockOutApplyList'] = erpStockOutApplyStatus();
        $this->assign('data' , $data) ;
        $this->display();
    }

    /**********************************
         @ Content 采购单生成入库申请单验证
         @ Time    2019-04-24
         @ Author  YF
    ***********************************/
    public function checkAddStockInApply()
    {
        $param = I('post.');
        $return_data = $this->getEvent("ErpStockInApply")->checkAddStockInApply($param);
        $this->echoJson($return_data);
    }

    /**********************************
        @ Content 生成入库单之前验证
        @ Author  Yf
        @ Time    2019-04-18
    ***********************************/
    public function checkAddStockIn()
    {
        $param = I('post.');
        $return_data = $this->getEvent("ErpStockInApply")->checkAddStockIn($param);
        $this->echoJson($return_data);
    }

    /***********************************
        @ Content 查询入库申请单详情
        @ Author  YF
        @ Time  2019-04-18
    ************************************/
    public function findStockInApply()
    {
        $param = I('post.');
        $return_data = $this->getEvent("ErpStockInApply")->findStockInApply($param);
        $this->echoJson($return_data);
    }

	/***********************************
		@ Content 入库申请单生成 入库单
        @ Author  YF
        @ Time    2019-04-16
	************************************/
	public function addStockInOrder()
	{
		$param = $_REQUEST;
        if ( IS_AJAX ) {
            $result_data = $this->getEvent("ErpStockInApply")->addStockInOrder($param);
            $this->echoJson($result_data);
        }
        $find_stock_in_order_result = $this->getEvent("ErpStockInApply")->findStockInApply($param);
        $data = [];
        if ( $find_stock_in_order_result['status'] == 1 ) {
            $data['order'] = $find_stock_in_order_result['data'];
        }
        $data['lossRatio'] = lossRatio();
        /* --------- 货权类型 ------------ */
        $data['cargo_bn_type'] = getConfigByType(2);
         /* --------- 货权城市 ------------ */
        $region = provinceCityZone()['city'];
        $this->assign(['region'=>$region]);
        $this->assign(['data' => $data]);
        $this->display();
	}

	/***********************************
		@ Content 编辑入库申请单
	************************************/
	public function editStockInApply()
	{
		$param = $_REQUEST;
        if ( IS_AJAX ) {
            $result_data = $this->getEvent('ErpStockInApply')->editStockInApply($param);
            $this->echoJson($result_data);
        }
       /* --------------------------------
            Content    渲染页面
            Author     GY
        ----------------------------------- */
        $id = intval(I('param.id', 0));
        $stock_in_apply = $this->getModel('ErpStockInApply')->where(['id' => ['eq' ,$id]])->field('id,source_object_id,storage_apply_num,status')->find();
        $order_info = $this->getEvent('ErpStock')->showAddErpStockIn($stock_in_apply['source_object_id']);
        $order_info['may_apply_num'] = ($this->getEvent('ErpStockInApply')->countMayApplyNum($stock_in_apply['source_object_id'])['data']+getNum($stock_in_apply['storage_apply_num']));
        $stock_in_apply['storage_apply_num'] = getNum($stock_in_apply['storage_apply_num']);
        $DepotData = getDepotData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                if ($value['depot_area'] == $order_info['order']['region']) {
                    $new_depots[] = $value;
                }
            }
            $new_depots[] = ['depot_name'=>'不限油库','id' => 99999];
        }
        $order_info['order']['add_order_time'] = date('Y-m-d',strtotime($order_info['order']['add_order_time']));
        $this->assign('stock_in_apply_arr',$stock_in_apply);
        $this->assign('depots', $new_depots);
        $this->assign('data', $order_info);
        $this->display();
	}

	/***********************************
		@ Content 取消出库申请单
	************************************/
	public function cancelStockInApply()
	{
		$param = $_REQUEST;
		$result_data = $this->getEvent('ErpStockInApply')->cancelStockInApply($param);
		$this->echoJson($result_data);
	}

	/**************************************
		@ Content 导出入库申请单列表
		@ Author  YF
		@ Time    2019-04-15
	***************************************/
	public function exportStockInApply()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        if ( isset($param['search_type']) && $param['search_type'] == 2 ) {
            $param['creater_id'] = $this->getUserInfo('id');
        }
        $param['start'] = 0;
        $param['length'] = 4000;
        // 生成菜单头
        (array)$header = [
            '序号','订单日期','入库申请单号','来源单号','采购员','用户','公司','仓库','油库','商品代码','申请入库数量','订单状态','备注'
        ];
        (array)$stock_in_arr = [];
        $k = 0;
        // // 获取总条数
        $where = $this->getEvent('ErpStockInApply')->handleStockInApplyListWhere($param);
        $num = $this->getModel('ErpStockInApply')->where($where)->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_stock_in_arr = $this->getEvent('ErpStockInApply')->stockInApplyList($param)['data'];
            foreach ($get_stock_in_arr as $key => $value) {
                $stock_in_arr[$k]['id'] 					= $value['id'];
                $stock_in_arr[$k]['create_time'] 			= $value['create_time'];
                $stock_in_arr[$k]['storage_apply_code'] 	= $value['storage_apply_code'];
                $stock_in_arr[$k]['source_number'] 			= $value['source_number'];
                $stock_in_arr[$k]['creater_name'] 			= $value['creater_name'];
                $stock_in_arr[$k]['user_name'] 				= $value['user_name'];
                $stock_in_arr[$k]['supplier_name'] 			= $value['supplier_name'];
                $stock_in_arr[$k]['storehouse_name'] 		= $value['storehouse_name'];
                $stock_in_arr[$k]['depot_name'] 			= $value['depot_name'];
                $stock_in_arr[$k]['goods_name'] 			= $value['goods_name'];
                $stock_in_arr[$k]['storage_apply_num'] 		= ($value['storage_apply_num']);
                $stock_in_arr[$k]['status'] 				= ($value['status']);
                $stock_in_arr[$k]['remark'] 				= ($value['remark']);
                $k++;
            }
        }
        /********************* END *********************/
        $file_name_arr = '入库申请单列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成CSV内容
        $csvObj->exportCsv($stock_in_arr);
        //关闭文件句柄
        $csvObj->closeFile();
    }

	/*************************************
		@ Content   创建入库申请单
		@ Time 		2019-04-12
		@ Author 	YF
	**************************************/
	public function addStockInApplyOrder()
	{
		$param = $_REQUEST;
		 if ( IS_AJAX ) {
			$return_data = $this->getEvent('ErpStockInApply')->addStockInApplyOrder($param);
			$this->echoJson($return_data);
		 }
		/* --------------------------------
			Content    渲染页面
			Author     GY
		----------------------------------- */
        $id = intval(I('param.id', 0));
        $order_info = $this->getEvent('ErpStock')->showAddErpStockIn($id);
        $order_info['may_apply_num'] = $this->getEvent('ErpStockInApply')->countMayApplyNum($id)['data'];
        $order_info['order']['stock_not_in'] = $order_info['may_apply_num'];
        $DepotData = getDepotData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                if ($value['depot_area'] == $order_info['order']['region']) {
                    $new_depots[] = $value;
                }
            }
            $new_depots[] = ['depot_name'=>'不限油库','id' => 99999];
        }
        $order_info['order']['add_order_time'] = date('Y-m-d',strtotime($order_info['order']['add_order_time']));
        $this->assign('depots', $new_depots);
        $this->assign('data', $order_info);
        $this->display();
	}

	/********************************
		@ Content 入库申请单列表
		@ Time 		2019-04-15
		@ Author 	YF
	*********************************/
	public function stockInApplyList()
	{
        if (IS_AJAX) {
            $param = $_REQUEST;
            $return_data = $this->getEvent('ErpStockInApply')->stockInApplyList($param);
            $this->echoJson($return_data);
        }
        $data['storehouse'] = $this->getEvent("ErpStorehouse")->getListField("id, storehouse_name") ;
        $data['goodsList'] = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['stockOutApplyList'] = erpStockOutApplyStatus();
        $this->assign('data' , $data) ;
        $this->display();
	}

	/**************************************
		@ Content 计算可申请数量
		@ Time    2019-04-12
		@ Author  Yf
	**************************************/	
	public function countMayApplyNum()
	{
		$purchase_order_id = trim($_REQUEST['purchase_order_id;']);
		$return_data = $this->getEvent("ErpStockInApply")->countMayApplyNum($purchase_order_id);
		$this->echoJson($return_data);
	}


}