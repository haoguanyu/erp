<?php
namespace Home\Controller;
use Home\Controller\BaseController;

class ErpLossController extends BaseController
{

	/**********************************
		@ Content 损耗单导出
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function exportLossOrderApply()
	{
		set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;
        // 生成菜单头
        (array)$header = [
            '序号','订单日期','单据编号','来源单号','批次号','货权号','供应商公司','损耗类型','交易员','城市','仓库','商品代码','损耗数量','合理损耗（吨）','超损（吨）','比例','含税单价','合理损耗金额','超损金额','超损承担方','状态','合理处理状态','超损处理状态','是否红冲'
        ];
        (array)$loss_arr = [];
        $k = 0;
        // 获取总条数
        $where = $this->getEvent('ErpLoss')->lossOrderWhere($param);
        $num = $this->getModel('ErpLossOrder')->where($where)->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_loss_arr = $this->getEvent('ErpLoss')->lossOrderList($param)['data'];
            foreach ($get_loss_arr as $key => $value) {
                $loss_arr[$k]['id'] 					= $value['id'];
                $loss_arr[$k]['create_time'] 			= $value['create_time'];
                $loss_arr[$k]['order_number'] 			= $value['order_number'];
                $loss_arr[$k]['source_number'] 			= $value['source_number'];
                $loss_arr[$k]['sys_bn'] 				= "\t".$value['sys_bn'];
                $loss_arr[$k]['cargo_bn'] 				= "\t".$value['cargo_bn'];
                $loss_arr[$k]['company_name'] 			= $value['company_name'];
                $loss_arr[$k]['type_name'] 				= lossTypeStatus($value['type']);;
                $loss_arr[$k]['dealer_name'] 			= $value['dealer_name'];
                $loss_arr[$k]['region_name'] 			= $value['region_name'];
                $loss_arr[$k]['storehouse_name'] 		= $value['storehouse_name'];
                $loss_arr[$k]['goods_name'] 			= ($value['goods_name']);
                $loss_arr[$k]['loss_num'] 				= ($value['loss_num']);
                $loss_arr[$k]['reasonable_loss_num']	= ($value['reasonable_loss_num']);
                $loss_arr[$k]['exceed_loss_num'] 		= ($value['exceed_loss_num']);
                $loss_arr[$k]['loss_ratio'] 			= ($value['loss_ratio']);
                $loss_arr[$k]['price'] 					= ($value['price']);
                $loss_arr[$k]['reasonable_loss_price'] 	= ($value['reasonable_loss_price']);
                $loss_arr[$k]['exceed_loss_price'] 		= ($value['exceed_loss_price']);
                $loss_arr[$k]['responsible_party_name'] = ($value['responsible_party_name']);
                $loss_arr[$k]['order_status_name'] 		= lossOrderStatus($value['order_status']);
                $loss_arr[$k]['reasonable_status_name'] = lossReasonableStatus($value['reasonable_status']);
                $loss_arr[$k]['exceed_status_name'] 	= lossExceedStatus($value['exceed_status']);
                $loss_arr[$k]['reversed_status_name']   = ($value['reversed_status_name']);
                $k++;
            }
        }
        /********************* END *********************/
        $file_name_arr = '损耗单列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成CSV内容
        $csvObj->exportCsv($loss_arr);
        //关闭文件句柄
        $csvObj->closeFile();
	}

	/**********************************
		@ Content 损耗单列表
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function lossOrderList()
	{
		if ( IS_AJAX ) {
			$param = $_REQUEST;
			$return_data = $this->getEvent("ErpLoss")->lossOrderList($param);
			$this->echoJson($return_data);
		}
		$data['loss_status'] = lossOrderStatus();
		$data['source_type'] = lossTypeStatus();
		$data['responsible_party'] = lossResponsiblePartyStatus();
		$access_node = $this->getUserAccessNode('ErpLoss/lossOrderList');
		$this->assign('access_node', json_encode($access_node));
		$this->assign(['data' => $data]);
		$this->display();
	}

	/**********************************
		@ Content 损耗单审核
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function lossAudit(){
		$param = $_REQUEST;
		$return_data = $this->getEvent("ErpLoss")->lossAudit($param);
		$this->echoJson($return_data);
	}

    /**********************************
    @ Content 损耗单确认
    @ Author  guanyu
    @ Time    2019-05-13
     ***********************************/
    public function lossConfirm(){
        $param = $_REQUEST;
        $return_data = $this->getEvent("ErpLoss")->lossConfirm($param);
        $this->echoJson($return_data);
    }

	/**********************************
		@ Content 损耗单取消
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function lossDelete(){
		$param = $_REQUEST;
		$return_data = $this->getEvent("ErpLoss")->lossDelete($param);
		$this->echoJson($return_data);
	}


	/**********************************
		@ Content 新增运费单
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function addFreightOrder()
	{
		$param = $_REQUEST;
		if ( IS_AJAX ) {
			$return_data = $this->getEvent("ErpLoss")->addFreightOrder((array)$param);

			$this->echoJson($return_data);
		}
		// 损耗单数据
		$loss_data = $this->getModel("ErpLossOrder")->where(['id'=>['eq',trim($param['loss_id'])]])->field('order_number')->find();
		// 运费单数据
		$where = [
			'source_number' => ['eq',$loss_data['order_number']],
			'order_status'  => ['neq',2]
		];
		$freight_order_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
		$return_data = $this->getEvent("ErpFreight")->findSuperStockInOrder(['loss_order_number'=>$loss_data['order_number']]);
		$data = [];
		$data['loss_order_number'] = $loss_data['order_number'];
		if ( $return_data['status'] == 1 ) {
			$data['stock_in'] = $return_data['data'];
		}
		if ( !empty($freight_order_arr) ) {
			$freight_order_arr['transport_amount']  = getNum($freight_order_arr['transport_amount']);
			$freight_order_arr['order_status_name'] = freightOrderStatus($freight_order_arr['order_status']);
			$data['freight_order_arr'] = $freight_order_arr;
		}
		$this->assign(['data'=>$data]);
		$this->display();
	}

	/**********************************
		@ Content 新增运费单前验证
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function checkAddFreightOrder()
	{
		$param = $_REQUEST;
		$return_data = $this->getEvent("ErpLoss")->checkAddFreightOrder($param);
		$this->echoJson($return_data);
	}


	/**********************************
		@ Content 损耗单编辑
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function editLoss()
	{
		$param = $_REQUEST;
		if ( IS_AJAX ) {
			$result_data = $this->getEvent("ErpLoss")->editLoss($param);
			$this->echoJson($result_data);
		}
		if ( isset($param['loss_order_number']) ) {
			$loss_where['order_number'] = ['eq',trim($param['loss_order_number'])];
		} else {
			$loss_where = ['id'=>['eq',trim($param['id'])]];
		}
		$loss_data = $this->getModel("ErpLossOrder")->where($loss_where)->select();
		$freight_arr = $this->getModel("ErpFreightOrder")->where(
			['source_number'=>['eq',$loss_data[0]['order_number']]]
		)->field('carrier_company_name')->find();
		$data['carrier_company_name'] = '';
		if ( isset($freight_arr['carrier_company_name']) ) {
			$data['carrier_company_name'] = $freight_arr['carrier_company_name'];
		}
		$data['order'] = $this->getEvent("ErpLoss")->handleLossOrderArr($loss_data)[0];
		$data['responsible_party'] = lossResponsiblePartyStatus();
		$this->assign(['data'=>$data]);
		$this->display();
	}

	/**
	 * @desc 损耗单红冲
	 * @author xiaowen
	 * @time 2019-5-14
	 */
    public function lossReverse(){
        $param = $_REQUEST;
        $return_data = $this->getEvent("ErpLoss")->lossOrderReverse($param['loss_id']);
        $this->echoJson($return_data);
    }


}