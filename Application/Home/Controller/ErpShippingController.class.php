<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * ERP配送管理控制器
 * @author xiaowen
 * Class ErpShippingController
 * @package Home\Controller
 */
class ErpShippingController extends BaseController
{

    /** --------------------------------------------------文哥------------------------------------------------------- */



    /** --------------------------------------------------乾斌------------------------------------------------------- */
    /**
     * 配送单需求池
     * @author qianbin
     * @time 2018-03-21
     */
    public function shippingDemandPool ()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpShipping')->shippingDemandPool($param);
            $this->echoJson($data);
        }

        //查询页面所需要的销售单相关状态入类型数据
        $data['SourceType'] = SourceType();
        $data['ShippingType'] = ShippingType();
        $data['ShippingOrderStatus'] = ShippingOrderStatus();
        $data['regionList'] = provinceCityZone()['city'];

        //获取当前页面的按钮权限
        $access_node = $this->getUserAccessNode('ErpShipping/shippingOrderList');
        $data['provinceList'] = provinceCityZone()['province'];
        $data['goodsList']    = $this->getEvent('ErpGoods')->getAllGoods(['status'=>10]);
        $data['city2']        = json_encode(cityLevelData()['city2']);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }


    /** --------------------------------------------------冠宇------------------------------------------------------- */

    /**
     * 配送单列表
     * @author guanyu
     * @time 2018-03-17
     */
    public function shippingOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['type'] = 2;
            $data = $this->getEvent('ErpShipping')->ShippingOrderList($param);
            $this->echoJson($data);
        }

        //查询页面所需要的销售单相关状态入类型数据
        $data['SourceType'] = SourceType();
        $data['ShippingType'] = ShippingType();
        $data['ShippingOrderStatus'] = ShippingOrderStatus();
        $data['regionList'] = provinceCityZone()['city'];

        //获取当前页面的按钮权限
        $access_node = $this->getUserAccessNode('ErpShipping/shippingOrderList');
        $data['provinceList'] = provinceCityZone()['province'];
        $data['city2']        = json_encode(cityLevelData()['city2']);
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 新增配送单
     * @author guanyu
     * @time 2017-11-07
     */
    public function addShippingOrder()
    {
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpShipping')->addShippingOrder($param);

            $this->echoJson($data);
        }

        $param = I('get.');
        $region['region_list'] = provinceCityZone()['city'];

        //获取当前需求单据的信息
        $data['order'] = $this->getEvent('ErpShipping')->getSourceOrderInfo($param)['data'];
        $data['order']['shipping_num'] = getNum($data['order']['shipping_num']);
        $data['source_type']  = SourceType();
        $data['shipper_list'] = getShipperList();
        $data['today'] = date("Y-m-d H:i:s");
        $data['province_list'] = provinceCityZone()['province'];
        $data['city']        = json_encode(cityLevelData()['city2']);
        $data['county']      = json_encode(cityLevelData()['city3']);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 编辑配送单
     * @author guanyu
     * @time 2018-03-22
     */
    public function updateShippingOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpShipping')->updateShippingOrder($id, $param);

            $this->echoJson($data);
        }

        //获取配送单详情
        $field = 'o.*,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data['order'] = $this->getEvent('ErpShipping')->findOneShippingOrder($id,$field);
        $data['order']['loss_num'] = getNum($data['order']['loss_num']);
        $data['order']['freight'] = getNum($data['order']['freight']);
        $data['order']['incidental'] = getNum($data['order']['incidental']);
        $data['order']['loading_num'] = getNum($data['order']['loading_num']);
        $data['order']['unloading_num'] = getNum($data['order']['unloading_num']);
        $data['order']['temperature'] = getNum($data['order']['temperature']);
        $data['order']['shipping_num'] = getNum($data['order']['shipping_num']);
        $data['order']['density'] = $data['order']['density'] ? $data['order']['density'] : 0;
        $data['order']['shipping_city']  = explode('_' ,$data['order']['shipping_city']);
        $data['order']['receiving_city'] = explode('_' ,$data['order']['receiving_city']);
        $data['source_type'] = SourceType();
        $data['shipper_list'] = getShipperList();
        $data['today'] = date("Y-m-d H:i:s");
        $city      = provinceCityZone();
        $city_json = cityLevelData();
        $data['province_list'] = $city['province'];
        $data['region_list']   = $city['city'];
        $data['city']          = json_encode($city_json['city2']);
        $data['county']        = json_encode($city_json['city3']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 配送单详情
     * @author guanyu
     * @time 2018-03-22
     */
    public function detailShippingOrder()
    {
        $id = intval(I('param.id', 0));
        //获取预付申请单详情
        $field = 'o.*,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data['order'] = $this->getEvent('ErpShipping')->findOneShippingOrder($id,$field);
        //预览图片
        //$data['order']['outbound_order_photo'] = $data['order']['outbound_order_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['outbound_order_photo'] : '';
        # 配送单图片修改 qianbin 2018-09-10
        /*
        if ($data['order']['seal_photo']) {
            $seal_photo = json_decode($data['order']['seal_photo']);
            foreach ($seal_photo as $key => $value) {
                $seal_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['seal_photo'] = $seal_photo;
        }
        $data['order']['seal_photo'] = $data['order']['seal_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['seal_photo'] : '';
        $data['order']['temperature_density_photo'] = $data['order']['temperature_density_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['temperature_density_photo'] : '';
        $data['order']['oil_sample_photo'] = $data['order']['oil_sample_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['oil_sample_photo'] : '';
        */
        # 出库单凭证
        if(!empty($data['order']['outbound_order_photo'])){
            $outbound_order_photo = explode(",",$data['order']['outbound_order_photo']);
            foreach ($outbound_order_photo as $key => $value) {
                $outbound_order_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
                $data['order']['outbound_order_photo'] = $outbound_order_photo;
        }
        # 铅封图
        if(!empty($data['order']['seal_photo'])){
            $seal_photo = explode(",",$data['order']['seal_photo']);
            foreach ($seal_photo as $key => $value) {
                $seal_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['seal_photo'] = $seal_photo;
        }
        # 温密图凭证
        if(!empty($data['order']['temperature_density_photo'])){
            $temperature_density_photo = explode(",",$data['order']['temperature_density_photo']);
            foreach ($temperature_density_photo as $key => $value) {
                $temperature_density_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['temperature_density_photo'] = $temperature_density_photo;
        }
        # 油样图
        if(!empty($data['order']['oil_sample_photo'])){
            $oil_sample_photo = explode(",",$data['order']['oil_sample_photo']);
            foreach ($oil_sample_photo as $key => $value) {
                $oil_sample_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['oil_sample_photo'] = $oil_sample_photo;
        }
        $data['order']['loss_num'] = getNum($data['order']['loss_num']);
        $data['order']['freight'] = getNum($data['order']['freight']);
        $data['order']['incidental'] = getNum($data['order']['incidental']);
        $data['order']['loading_num'] = getNum($data['order']['loading_num']);
        $data['order']['unloading_num'] = getNum($data['order']['unloading_num']);
        $data['order']['temperature'] = getNum($data['order']['temperature']);
        $data['order']['shipping_num'] = getNum($data['order']['shipping_num']);
        $data['order']['density'] = $data['order']['density'] ? $data['order']['density'] : 0;
        $data['order']['shipping_city']  = explode('_' ,$data['order']['shipping_city']);
        $data['order']['receiving_city'] = explode('_' ,$data['order']['receiving_city']);
        $data['source_type'] = SourceType();
        $data['shipper_list'] = getShipperList();
        $data['today'] = date("Y-m-d H:i:s");
        $city      = provinceCityZone();
        $city_json = cityLevelData();
        $data['province_list'] = $city['province'];
        $data['region_list']   = $city['city'];
        $data['city']          = json_encode($city_json['city2']);
        $data['county']        = json_encode($city_json['city3']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 完善物流信息
     * @author guanyu
     * @time 2018-03-22
     */
    public function consummateShippingOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpShipping')->consummateShippingOrder($id, $param);

            $this->echoJson($data);
        }

        //获取配送单详情
        $field = 'o.*,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data['order'] = $this->getEvent('ErpShipping')->findOneShippingOrder($id,$field);
        $data['order']['loss_num'] = getNum($data['order']['loss_num']);
        $data['order']['freight'] = getNum($data['order']['freight']);
        $data['order']['incidental'] = getNum($data['order']['incidental']);
        $data['order']['loading_num'] = getNum($data['order']['loading_num']);
        $data['order']['unloading_num'] = getNum($data['order']['unloading_num']);
        $data['order']['temperature'] = getNum($data['order']['temperature']);
        $data['order']['shipping_num'] = getNum($data['order']['shipping_num']);
        $data['order']['density'] = $data['order']['density'] ? $data['order']['density'] : 0;
        $data['source_type'] = SourceType();
        $region['region_list'] = provinceCityZone()['city'];
        $data['today'] = date("Y-m-d H:i:s");
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 审核配送单
     * @author guanyu
     * @time 2018-03-22
     */
    public function auditShippingOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpShipping')->auditShippingOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 删除配送单
     * @author guanyu
     * @time 2018-03-22
     */
    public function delShippingOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpShipping')->delShippingOrder($id, $param);

            $this->echoJson($data);
        }
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 确认配送单
     * @author guanyu
     * @time 2018-03-22
     */
    public function confirmShippingOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpShipping')->confirmShippingOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 获取一条配送信息
     * @author guanyu
     * @time 2018-03-22
     */
    public function findOneShippingOrder()
    {
        $id = intval(I('post.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpShipping')->findOneShippingOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 查询上传凭证
     * @author xiaowen
     * @time 2017-8-31
     */
    public function uploadVoucher()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('ErpShipping')->uploadVoucher($id, $_FILES);
            $this->echoJson($data);
        }
        $data['order'] = $this->getModel('ErpShippingOrder')->findShippingOrder(['id'=>intval($id)], 'outbound_order_photo,seal_photo,temperature_density_photo,oil_sample_photo');
        //预览图片
        /*
        $data['order']['outbound_order_photo'] = $data['order']['outbound_order_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['outbound_order_photo'] : '';
        $data['order']['seal_photo'] = $data['order']['seal_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['seal_photo'] : '';
        $data['order']['temperature_density_photo'] = $data['order']['temperature_density_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['temperature_density_photo'] : '';
        $data['order']['oil_sample_photo'] = $data['order']['oil_sample_photo'] ? $this->uploads_path['shipping_attach_aliyun']['url'].$data['order']['oil_sample_photo'] : '';
        */
        # 图片查看修改 qianbin 2018-10-12
        # 出库单凭证
        if(!empty($data['order']['outbound_order_photo'])){
            $outbound_order_photo = explode(",",$data['order']['outbound_order_photo']);
            foreach ($outbound_order_photo as $key => $value) {
                $outbound_order_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['outbound_order_photo'] = $outbound_order_photo;
        }
        # 铅封图
        if(!empty($data['order']['seal_photo'])){
            $seal_photo = explode(",",$data['order']['seal_photo']);
            foreach ($seal_photo as $key => $value) {
                $seal_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['seal_photo'] = $seal_photo;
        }
        # 温密图凭证
        if(!empty($data['order']['temperature_density_photo'])){
            $temperature_density_photo = explode(",",$data['order']['temperature_density_photo']);
            foreach ($temperature_density_photo as $key => $value) {
                $temperature_density_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['temperature_density_photo'] = $temperature_density_photo;
        }
        # 油样图
        if(!empty($data['order']['oil_sample_photo'])){
            $oil_sample_photo = explode(",",$data['order']['oil_sample_photo']);
            foreach ($oil_sample_photo as $key => $value) {
                $oil_sample_photo[$key] = $this->uploads_path['shipping_attach_aliyun']['url'].$value;
            }
            $data['order']['oil_sample_photo'] = $oil_sample_photo;
        }
        $this->assign('data',$data);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 导出销售单
     * @author xiaowen
     * @time 2017-5-25
     */
    public function exportShippingData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        //$param['export'] = 1;
        $param['start'] = 0;
        $param['length'] = 4000;
        $file_name_arr = '配送单' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();

        //生成菜单行
        $header = [
            'ID','业务日期','业务发起日期','物流单号','物流单创建人','需求单号','业务单号','业务创建人','来源单据类型',
            '配送类型','商品','配送数量（吨）','公司','客户','客户联系方式','城市','业务需求配送时间','油库确认出库时间',
            '实际送达时间','服务商确认送达时间','订单状态','配送状态','凭证上传状态','备注','取消配送原因'
        ];
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        while(($data = $this->getEvent('ErpShipping')->ShippingOrderList($param)['data']) && ($count = $this->getEvent('ErpShipping')->ShippingOrderList($param)['recordsTotal'])){
            $arr = [];
            foreach($data as $k=>$v){
                $arr[$k]['id']                      = $v['id'];
                $arr[$k]['create_time']             = $v['create_time'];
                $arr[$k]['business_create_time']    = $v['business_create_time'];
                $arr[$k]['order_number']            = $v['order_number'];
                $arr[$k]['creater_name']            = $v['creater_name'];
                $arr[$k]['source_number']           = $v['source_number'];
                $arr[$k]['source_order_number']     = $v['source_order_number'];
                $arr[$k]['business_creater_name']   = $v['business_creater_name'];
                $arr[$k]['source_type']             = $v['source_type'];
                $arr[$k]['shipping_type']           = $v['shipping_type'];
                $arr[$k]['goods_code']              = $v['goods_code'].'/'.$v['source_from'].'/'.$v['goods_name'].'/'.$v['grade'].'/'.$v['level'];
                $arr[$k]['shipping_num']            = $v['shipping_num'];
                $arr[$k]['company_name']            = $v['company_name'];
                $arr[$k]['user_name']               = $v['user_name'];
                $arr[$k]['user_phone']              = number_format($v['user_phone'],0,'','');
                $arr[$k]['region_name']             = $v['region_name'];
                $arr[$k]['business_shipping_time']  = $v['business_shipping_time'];
                $arr[$k]['depot_out_time']          = $v['depot_out_time'];
                $arr[$k]['actual_in_time']          = $v['actual_in_time'];
                $arr[$k]['facilitator_in_time']     = $v['facilitator_in_time'];
                $arr[$k]['order_status']            = $v['order_status_font'];
                $arr[$k]['distribution_status']     = $v['distribution_status_font'];
                $arr[$k]['voucher_status']          = $v['voucher_status_font'];
                $arr[$k]['remark']                  = $v['remark'];
                $arr[$k]['cancell_remark']          = $v['cancell_remark'];
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