<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpGoodsController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP商品逻辑层
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------

    // +----------------------------------
    // |Facilitator:商品列表页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function erpGoodsList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpGoods')->erpGoodsList($param);
            $this->echoJson($data);
        }
        $data['oilSource'] = oilSource();
        $data['oilLabel'] = oilLabel();
        $data['erpGoodsStatus'] = erpGoodsStatus();
        $access_node = $this->getUserAccessNode('ErpGoods/erpGoodsList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign("data", $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:添加erp商品页面
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function showAddErpGoods()
    {
        $data['oilSource'] = oilSource();
        $data['oilLabel'] = oilLabel();
        $this->assign("data", $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:添加erp商品操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function actAddErpGoods()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpGoods')->actAddErpGoods($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:编辑erp商品页面
    // +----------------------------------
    // |Author:senpai Time:2017.3.12
    // +----------------------------------
    public function showUpdateErpGoods()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        $is_show = intval(I('param.is_show', 0));
        $data = $this->getEvent('ErpGoods')->showUpdateErpGoods($id);
        $data['oilSource'] = oilSource();
        $data['oilLabel'] = oilLabel();
        $this->assign("data", $data);
        $this->assign("is_show", $is_show);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:编辑erp商品操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.13
    // +----------------------------------
    public function actUpdateErpGoods()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpGoods')->actUpdateErpGoods($param);
            $this->echoJson($data);
        }
        $test_data = 1 * 2;
        $test_data2 = $test_data;
        $data = $this->getEvent('ErpGoods')->actUpdateErpGoods($test_data2);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:删除erp商品操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.13
    // +----------------------------------
    public function actDeleteErpGoods()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        $data = $this->getEvent('ErpGoods')->actDeleteErpGoods($id);
        $this->echoJson($data);
    }

    // +----------------------------------
    // |Facilitator:审核erp商品操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.13
    // +----------------------------------
    public function actAuditErpGoods()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        $data = $this->getEvent('ErpGoods')->actAuditErpGoods($id);
        $this->echoJson($data);
    }

    /**
     *通过商品代码获取商品信息
     * @author xiaowen
     * @time 2017-4-1
     */
    public function getGoodsByCode()
    {
        $goods_code = strHtml(I('post.goods_code', ''));

        if ($goods_code) {
            $data = $this->getEvent('ErpGoods')->findGoodsByCode($goods_code);
            $data = !empty($data) ? $data : [];
        } else {
            $data = [];
        }

        $this->echoJson($data);
    }

    /**
     * 导入商品盘点数据
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importGoodsData(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        $filePath = './Public/Uploads/Erp/goods_data.xlsx';
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            //print_r($data);
            unset($data[0]);
            //print_r($data);

            $goods_data = [];
            $i = 0;
            foreach($data as $key=>$value){
                $goods_data[$i]['goods_code'] = erpCodeNumber(4, trim($value[1]))['order_number'];
                $goods_data[$i]['goods_name'] = trim($value[1]);
                $goods_data[$i]['level'] = trim($value[2]);
                $goods_data[$i]['grade'] = trim($value[3]);
                $goods_data[$i]['source_from'] = trim($value[4]);
                $goods_data[$i]['label'] = trim($value[5]);
                $goods_data[$i]['density_value'] = trim($value[6]);
                $goods_data[$i]['status'] = 10;
                $goods_data[$i]['create_time'] = currentTime();
                $goods_data[$i]['creater'] = 142;

                $i++;
            }
            $status = $this->getModel('ErpGoods')->addAll($goods_data);
            $status_str = $status ? '成功' : '失败';

            echo '商品数据全部导入'.$status_str;
        }

    }
}