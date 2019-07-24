<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpRegionGoodsController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP商品逻辑层
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    /**
     * +----------------------------------
     * |Facilitator:商品列表页面及数据
     * +----------------------------------
     * |Author:xiaowen Time:2017.3.24
     * +----------------------------------
     */
    public function erpRegionGoodsList()
    {
        if (IS_AJAX) {
            if (empty($_GET)) {
                $data['data'] = [];
                $data['recordsFiltered'] = 0;
                $data['recordsTotal'] = 0;
                $this->echoJson($data);
            }
            $param = $_REQUEST;
            $data = $this->getEvent('ErpRegionGoods')->erpRegionGoodsList($param);
            $this->echoJson($data);
        }
        $data['oilSource'] = oilSource();
        $data['oilLabel'] = oilLabel();
        $data['erpGoodsStatus'] = erpRegionGoodsStatus(0, false);
        $data['region_list'] = provinceCityZone()['city'];
        $access_node = $this->getUserAccessNode('ErpRegionGoods/erpRegionGoodsList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign("data", $data);
        $this->display();
    }

    /**
     * 修改商品
     * @author xiaowen
     * @time 2017-3-24
     */
    public function updateGoods()
    {
        $goods_id = intval(I('param.id', 0));
        $region = intval(I('param.region', 0));
        $region_goods_id = intval(I('param.region_goods_id', 0));
        $is_region_goods = intval(I('param.is_region_goods', 0));
        if (IS_AJAX) {
            $param = I('param.');
            $data = $this->getEvent('ErpRegionGoods')->updateGoods($param);

            $this->echoJson($data);
        } else {
            if (($is_region_goods == 0) && $goods_id && $region) {
                $param = [
                    'goods_id' => $goods_id,
                    'region' => $region,
                ];

                $data = $this->getEvent('ErpRegionGoods')->getGoodsByRegion($param);

            } else if ($is_region_goods == 1 && $region_goods_id) {
                $data = $this->getEvent('ErpRegionGoods')->findOneRegionGoods($region_goods_id);
                //如果是没选择城市，编辑当前区域商品，用区域商品表的region值替换参数region (因为这种情况参数region是没有值的)
                $region = $data['region'];

            }
            $data['price'] = getNum($data['price']);
            $data['last_price'] = $data['last_price'] ? getNum($data['last_price']) : '0';
            $data['available_sale_stock'] = $data['available_sale_stock'] ? getNum($data['available_sale_stock']) : '';
            //$data['available_use_stock'] = $data['available_use_stock'] ? getNum($data['available_use_stock']) : '';
            //取该商品在该地区所有城市仓的可用库存 做为区域维护的快照可用数量 edit xiaowen time 2017-05-03
            $stock_info = $this->getEvent('ErpStock')->getStockInfo(['goods_id'=>$param['goods_id'], 'region'=>$param['region'], 'stock_type'=>1]);
            $data['available_use_stock'] = !empty($stock_info) && isset($stock_info['available_num']) ? getNum($stock_info['available_num']) : 0;
            $data['density'] = trim($data['density']) ? $data['density'] : $data['density_value']; //如果区域维护表里没有密度 density，则取商品基础表的密度 density_value
            $data['region_list'] = provinceCityZone()['city'];

            $data['oilSource'] = oilSource();
            $data['oilLabel'] = oilLabel();
            $this->assign('data', $data);
            $this->assign('region', $region);
            $this->assign('goods_id', $goods_id);
            $this->assign('region_goods_id', $region_goods_id);
            $this->assign('is_region_goods', $is_region_goods);
            $this->display();
        }

    }

    /**
     *通过商品代码获取商品信息
     * @author xiaowen
     * @time 2017-4-1
     */
    public function getGoodsByCode()
    {
        //$goods_code = strHtml(I('post.goods_code', ''));
        $region = intval(I('param.region', 0));
        $param = [
            'rg.region' => $region,
            'our_company_id'=>session('erp_company_id')
        ];
        $param['rg.status'] = 1;
        $data['data'] = $this->getEvent('ErpRegionGoods')->getRegionGoodsByParams($param);
        if(empty($data['data'])){
            $data['total_count'] = 0;
            $data['incomplete_results'] = true;
        }else{
            foreach($data['data'] as $key=>$value){
                $data['data'][$key]['price'] = getNum($value['price']);
            }
            $data['total_count'] = count($data['data']);
            $data['incomplete_results'] = true;
        }
//        if ($goods_code) {
//            $goods_info = $this->getEvent('ErpGoods')->findGoodsByCode($goods_code);
//
//            if ($goods_info['id'] && $region) {
//                $param['goods_id'] = $goods_info['id'];
//                $param['region'] = $region;
//                $data = $this->getEvent('ErpRegionGoods')->getGoodsByRegion($param);
//                $data['price'] = getNum($data['price']);
//                $data = ($data['price'] > 0) && ($data['region'] == $region) ? $data : [];
//            }
//
//        } else {
//            $data = [];
//        }

        $this->echoJson($data);
    }

    /**
     * 导入区域商品维护密度
     * @author guanyu
     * @time 2018-02-27
     */
    public function importRegionGoodsDensity(){
        set_time_limit(0);
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './region_goods_density.xlsx';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './region_goods_density.xls';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
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
            unset($data[0]);

            $this->getEvent('ErpRegionGoods')->importRegionGoodsDensity($data);
        }

    }

}