<?php
namespace Home\Controller;
use Home\Controller\BaseController;
/**
 * 
 * 运费单控制器层
 * @author xiaowen
 * @time 2019-5-14
 * 
 */
class ErpFreightController extends BaseController
{
	/**********************************
		@ Content 运费单列表
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function freightOrderList(){
		$param = $_REQUEST;
		if ( IS_AJAX ) {
			$result_data = $this->getEvent("ErpFreight")->freightOrderList($param);
			$this->echoJson($result_data);
		}
		$data['order_status'] = freightOrderStatus();
		$access_node = $this->getUserAccessNode('ErpFreight/freightOrderList');
		$this->assign('access_node', json_encode($access_node));
		$this->assign(['data' => $data]);
		$this->display();
	}


	/**********************************
		@ Content 运费单编辑
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function editfreightOrder(){
		$param = $_REQUEST;
		if ( IS_AJAX ) {
			$return_data = $this->getEvent("ErpFreight")->editfreightOrder($param);
			$this->echoJson($return_data);
		}
		// 这里的 loss_order_number 是 运费单单号
		if ( isset($param['loss_order_number']) ) {
			$where = [
				'order_number' => ['eq',trim($param['loss_order_number'])],
			]; 
		} else {
			$where = ['id'=>['eq',$param['freight_id']]];
		}
		$freight_arr = $this->getModel('ErpFreightOrder')->where($where)->find();
		$return_data = $this->getEvent("ErpFreight")->findSuperStockInOrder(['loss_order_number'=>$freight_arr['source_number']]);
		$data = [];
		if ( $return_data['status'] == 1 ) {
			$data['stock_in'] = $return_data['data'];
		}
		$freight_arr['transport_amount']  	= getNum($freight_arr['transport_amount']);
		$freight_arr['order_status_name'] 	= freightOrderStatus($freight_arr['order_status']);
		$data['freight_order_arr'] 			= $freight_arr;
		$this->assign(['data'=>$data]);
		// 提示 此页面 和 新增页面一致。 感觉分开一下比较好
		$this->display();
	}

	/**********************************
		@ Content 运费单审核
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function freightAudit(){
		$param = $_REQUEST;
		if ( IS_AJAX ) {
			$return_data = $this->getEvent("ErpFreight")->freightAudit($param);
			$this->echoJson($return_data);
		}
		$where = [
			'id' => ['eq',trim($param['freight_id'])],
		];
		$freight_arr = $this->getModel("ErpFreightOrder")->where($where)->field('transport_num,transport_amount,id')->find();
		$freight_arr['num']   = getNum($freight_arr['transport_num']);
		$freight_arr['price'] = getNum($freight_arr['transport_amount']);
		$this->assign(['data' => $freight_arr]);
		$this->display();
	}

	/**********************************
		@ Content 运费单前验证
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function freightAuditChecking(){
		$param = $_REQUEST;
		$return_data = $this->getEvent("ErpFreight")->freightAuditChecking($param);
		$this->echoJson($return_data);
	}

	/**********************************
		@ Content 运费单取消
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function freightDelete(){
		$param = $_REQUEST;
		$return_data = $this->getEvent("ErpFreight")->freightDelete($param);
		$this->echoJson($return_data);
	}


	/**********************************
		@ Content 运费单导出
		@ Author  Yf
		@ Time    2019-05-06
	***********************************/
	public function exportFreightOrderApply(){
		set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;
        // 生成菜单头
        (array)$header = [
            '序号','订单日期','单据编号','来源单号','承运商公司','数量','抵扣运费','状态','是否红冲'
        ];
        (array)$freight_arr = [];
        $k = 0;
        // 获取总条数
        $where = $this->getEvent('ErpFreight')->freightOrderWhere($param);
        $num = $this->getModel('ErpFreightOrder')->where($where)->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_loss_arr = $this->getEvent('ErpFreight')->freightOrderList($param)['data'];
            foreach ($get_loss_arr as $key => $value) {
                $freight_arr[$k]['id'] 					 = $value['id'];
                $freight_arr[$k]['create_time'] 		 = $value['create_time'];
                $freight_arr[$k]['order_number'] 		 = $value['order_number'];
                $freight_arr[$k]['source_number'] 		 = $value['source_number'];
                $freight_arr[$k]['carrier_company_name'] = $value['carrier_company_name'];
                $freight_arr[$k]['transport_num'] 		 = $value['transport_num'];
                $freight_arr[$k]['transport_amount'] 	 = $value['transport_amount'];
                $freight_arr[$k]['order_status_name'] 	 = $value['order_status_name'];
                $freight_arr[$k]['reversed_status'] 	 = $value['reversed_status'];
                $k++;
            }
        }
        /********************* END *********************/
        $file_name_arr = '运费单列表' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成CSV内容
        $csvObj->exportCsv($freight_arr);
        //关闭文件句柄
        $csvObj->closeFile();
	}

    /**
     * 运费单确认
     * @author xiaowen
     * @time 2019-5-17
     */
    public function freightConfirm(){
        $param = $_REQUEST;
        $return_data = $this->getEvent("ErpFreight")->freightConfirm($param);
        $this->echoJson($return_data);
    }

    /**
     * 运费单红冲
     * @author xiaowen
     * @time 2019-5-17
     */
    public function freightReverse(){
        $param = $_REQUEST;
        $return_data = $this->getEvent("ErpFreight")->freightReverse($param);
        $this->echoJson($return_data);
    }

}