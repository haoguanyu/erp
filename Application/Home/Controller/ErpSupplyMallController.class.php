<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpSupplyMallController extends BaseController
{

    /**
     *商城自营供货单列表
     *Return: json
     *DATE:2017-03-10 Time:11:00
     *Author: xiaowen <xiaowen@51zhaoyou.com>
     */
    public function supplyList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpSupplyMall')->supplyList($param);
            $this->echoJson($data);
        }
        $data['depots'] = getDepotData();
        $new_depots = [];
        if ($data['depots']) {
            foreach ($data['depots'] as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        //print_r($new_depots);
        $data['depots'] = json_encode($new_depots);
        $data['source'] = oilSource();
        $data['supplyStatus'] = supplyStatus();
        $data['regionList'] = provinceCityZone()['city'];
        //$data['companyList'] = getClientsData(1);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 新增商城自营供货单
     * @author xiaowen
     * @time 2017-05-19
     */
    public function addSupply()
    {
        $goods_id = intval(I('param.id', 0));
        $region_id = intval(I('param.region', 0));
        $region['region_list'] = provinceCityZone()['city'];
        $select_data['dealer']['dealer_id'] = session('erp_adminInfo')['id'];
        $select_data['dealer']['dealer_name'] = session('erp_adminInfo')['dealer_name'];
        $select_data['user'] = getUserByDealer(session('erp_adminInfo')['dealer_name']);
        $select_data['goods_status'] = supplyGoodsStatus();
        $select_data['pick_up_way'] = supplyPickUpWay();
        $select_data['invoice_type'] = supplyInvoiceType();

        $data['our_company'] = getOurCompany();
        $data['our_company_id'] = session('erp_company_id');

        //查询区域维护的商品信息
        $data['region_goods'] = $this->getEvent('ErpRegionGoods')->getGoodsByRegion(['goods_id'=>$goods_id, 'region'=>$region_id]);
        $data['region_goods']['price'] = getNum($data['region_goods']['price']);
        //-----------------该商品、该地区的城市仓可用库存-----------------------------------------------------------
        $storehouse_data = $this->getEvent('ErpStorehouse')->getStorehouseByRegion($region_id);
        $stock_where = [
            'goods_id' => intval($goods_id),
            'object_id' => $storehouse_data[1]['id'],
            'stock_type' => 1,
            'region' => $region_id,
        ];
        //print_r($stock_where);
        $stock_available_sale_num = $this->getEvent('ErpStock')->getStockSaleNum($stock_where);
//        print_r($storehouse_data);
        $data['stock_available_sale_num'] = getNum($stock_available_sale_num);
        //-----------------------------------------------------------------------------------------------------------
        $this->assign("region", $region);
        //$this->assign("erpgoods", $erpgoods);
        $this->assign("select_data", $select_data);
        $this->assign("storehouse_data" , $storehouse_data);
        //$this->assign("erpgoods_json", json_encode($erpgoods));
        $this->assign("data", $data);
        $this->display();
    }

    /**
     * 添加商城自营供货单操作
     * @author senpai
     * @time 2017-03-15
     */
    public function actAddSupply()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpSupplyMall')->actAddSupply($param);
            $this->echoJson($data);
        }
    }

    /**
     * 判断是否已存在该地区的该商品商城供货单
     * @author senpai
     * @time 2017-06-03
     */
    public function checkSupplyMall()
    {
        if (IS_AJAX) {
            $id = $_POST['id'];
            $data = $this->getEvent('ErpSupplyMall')->checkSupplyMall($id);
            $this->echoJson($data);
        }
    }

    /**
     * 编辑商城自营供货单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function updateSupply()
    {
        //获取参数
        $id = intval(I('id', '', 'htmlspecialchars'));
        //获取数据
        $data = $this->getEvent('ErpSupplyMall')->showUpdateSupply($id);
        //print_r($data);
        $data['creater_name'] = getIdToDealerName()[$data['creater']];
        if ($data['updater'] > 0) {
            $data['updater_name'] = getIdToDealerName()[$data['updater']];
        }
        if ($data['auditor'] > 0) {
            $data['auditor_name'] = getIdToDealerName()[$data['auditor']];
        }
        //设置地区
        $region['region_list'] = provinceCityZone()['city'];

        //设置下拉元素
        $select_data['user'] = getUserByDealer(session('erp_adminInfo')['dealer_name']);
        $select_data['goods_status'] = supplyGoodsStatus();
        $select_data['pick_up_way'] = supplyPickUpWay();
        $select_data['invoice_type'] = supplyInvoiceType();

        $data['our_company'] = getOurCompany();
        $data['our_company_id'] = session('erp_company_id');

        //查询区域维护的商品信息
        $data['region_goods'] = $this->getEvent('ErpRegionGoods')->getGoodsByRegion(['goods_id'=>$data['goods_id'], 'region'=>$data['region']]);
        $data['region_goods']['price'] = getNum($data['region_goods']['price']);
        //-----------------该商品、该地区的城市仓可用库存-----------------------------------------------------------
        $storehouse_data = $this->getEvent('ErpStorehouse')->getStorehouseByRegion($data['region']);
        $stock_where = [
            'goods_id' => $data['goods_id'],
            'object_id' => $storehouse_data[1]['id'],
            'stock_type' => 1,
            'region' => $data['region'],
        ];

        $stock_available_sale_num = $this->getEvent('ErpStock')->getStockSaleNum($stock_where);
        $data['stock_available_sale_num'] = getNum($stock_available_sale_num);
        //-----------------------------------------------------------------------------------------------------------
        $depotList = [] ;
        if(!empty($data['storehouse_id'])){
            $returnDepot = $this->getEvent("ErpStorehouseDepot","Home")->getStorehouseDepot($data['storehouse_id']);
            if($returnDepot['status'] == 0){
                $depotList = $returnDepot['list'] ;
            }
        }
        if($data['depot_id']== 99999){
            $data['depot_name']= "不限油库";
        }
        $this->assign('depot' , $depotList);
        $this->assign("select_data", $select_data);
        $this->assign("data", $data);
        $this->assign("data_json", json_encode($data));
        $this->assign("region", $region);
        $this->assign("storehouse_data" , $storehouse_data);
        //$this->assign("erpgoods", $erpgoods);
        //$this->assign("erpgoods_json", json_encode($erpgoods));
        $this->display();
    }

    /**
     * 编辑商城自营供货单操作
     * @author senpai
     * @time 2017-03-15
     */
    public function actUpdateSupply()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpSupplyMall')->updateSupply($param);
            $this->echoJson($data);
        }
    }

    /**
     * 删除商城自营供货单
     * @author xiaowen
     * @time 2017-3-10
     */
    public function delSupply()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpSupplyMall')->delSupply($id);
            $this->echoJson($data);
        }
    }
    /**
     * 商城自营供货单设置是失效
     * @author 小黑
     * @time 2019-6-16
     */
    public function lowerShelf()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpSupplyMall')->lowerShelf($id);
            $this->echoJson($data);
        }
    }

    /**
     * 审核商城自营供货单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function auditSupply()
    {
        $id = intval(I('post.id', 0));
        if ($id) {
            $data = $this->getEvent('ErpSupplyMall')->auditSupply($id);
            $this->echoJson($data);
        } else {
            $data = [
                'status' => 0,
                'message' => '参数有误，请重新尝试',
            ];
            $this->echoJson($data);
        }
    }

    /**
     * 发送商城自营供货单短信给用户
     * @author xiaowen
     * @time 2017-03-13
     */
    public function sendSmsSupply()
    {
        if (IS_AJAX) {
            //$user_id = I('post.user_id');
            $user_phone = I('post.user_phone');
            $sms_text = I('post.sms_text');
            $data = $this->getEvent('ErpSupplyMall')->sendSmsSupply($user_phone, $sms_text);
            $this->echoJson($data);
        }
    }

    /**
     * 商城自营供货单详情
     */
    public function detailSupply()
    {

    }

    /**
     * 复制商城自营供货单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function copySupply()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpSupplyMall')->copySupply($id);
            $this->echoJson($data);
        }
    }

    /**
     * 商城自营供货单上下架
     * @author xiaowen
     * @time 2017-03-13
     */
    public function upDownSupply()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpSupplyMall')->upDownSupply($id);
            $this->echoJson($data);
        }
    }

    /**
     * 更新商城自营供货单价格库存
     * @author xiaowen
     * @time 2017-03-13
     */
    public function updatePriceNumSupply()
    {
        $id = intval(I('param.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpSupplyMall')->updatePriceNumSupply($id, I('post.'));
            $this->echoJson($data);
        }
        $data = $data = $this->getEvent('ErpSupplyMall')->findSupply($id, true);
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 验证商城自营供货单是否已审核
     * @author xiaowen
     * @time 2017-03-13
     */
    public function ajaxValidateCheckedSupply()
    {
        $id = intval(I('param.id', 0));
        $data = $data = $this->getEvent('ErpSupplyMall')->findSupply($id);
        if (empty($data)) {
            $result = ['status' => 0, 'message' => '商城自营供货单不存在,请稍后尝试'];
        } else {
            if ($data['status'] != 10) {
                $result = ['status' => 2, 'message' => '商城自营供货单不是已审核,请审核后操作'];
            } else {
                $result = ['status' => 1, 'message' => ''];
            }
        }

        $this->echoJson($result);
    }

    /**
     * 短信发送用户列表
     * @author xiaowen
     * @time 2017-03-15
     */
    public function sendSmsUserList()
    {
        $id = intval(I('get.id', 0));
        if (IS_AJAX) {
            $param = I('get.');
            $where = [];
            $where['dealer_name'] = $this->getUserInfo('dealer_name');

            if (trim($param['user_phone']) != '') {
                $where['user_phone'] = $param['user_phone'];
            }
            if (trim($param['user_name']) != '') {
                $where['user_name'] = $param['user_name'];
            }

            $data = $this->getEvent('User')->userList($where);
            if ($data['data']) {
                $i = 1;
                //$clients = getClientsData(1);
                //print_r($data);
                $uids = array_column($data['data'], 'id');
                $userCompanys = $this->getEvent('ErpSupplyMall')->getUserCompanys($uids);
                //print_r($userCompanys);
                foreach ($data['data'] as $key => $value) {
                    $data['data'][$key]['No'] = $key + 1;
                    $data['data'][$key]['company_name'] = $userCompanys[$value['id']] ? $userCompanys[$value['id']] : '--';
                    $data['data'][$key]['user_name'] = $value['user_name'] ? $value['user_name'] : '--';
                    $i++;
                }
                //print_r($data);
            }
            $this->echoJson($data);
        }

        $data = $this->getEvent('ErpSupplyMall')->findSupply($id, true);
        //print_r($data);
        $data['sms_text'] = setSendSupplySms($data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     *跟单
     *DATE:2017-03-20 Time:11:00
     *Author: senpai <haoguanyu@51zhaoyou.com>
     */
    public function followOrder()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpOrder')->orderList($param);
            $this->echoJson($data);
        }
        $id = trim(I('get.id', '', 'htmlspecialchars'));
        $data['depots'] = getDepotData();
        $new_depots = [];
        if ($data['depots']) {
            foreach ($data['depots'] as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        //print_r($new_depots);
        $data['depots'] = json_encode($new_depots);
        $data['source'] = oilSource();
        $data['erpOrderStatus'] = ErpOrderStatus();
        $data['regionList'] = provinceCityZone()['city'];
        $data['companyList'] = getClientsData(1);
        $this->assign('id', $id);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 根据地区获取油库
     * @author senpai
     * @return json
     * @time 2017-03-14
     */
    public function getDepot()
    {
        $region = trim(I('post.region', '', 'htmlspecialchars'));
        $data = $this->getEvent('ErpSupplyMall')->getDepot($region);
        $this->ajaxReturn($data);
    }

    /**
     * 验证商城自营供货单是否可以生成交易单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function ajaxValidateCanCreateOrder()
    {
        $id = intval(I('param.id', 0));
        $field = 's.*,d.depot_name,c.company_name,u.user_name,u.user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data = $this->getEvent('ErpSupplyMall')->getSupplyAllInfo($id, $field, false);
        if (empty($data)) {
            $result = ['status' => 0, 'message' => '商城自营供货单不存在,请稍后尝试'];
        } else {
            if ($data['status'] != 10) {
                $result = ['status' => 2, 'message' => '商城自营供货单不是已审核,请审核后操作'];
            } else if (($data['sale_num'] - $data['lock_num']) < $data['min_sale_num']) {
                $result = ['status' => 3, 'message' => '对不起，该商城自营供货单可售数量不足'];
            } else if ($data['background_buy'] == 2) {
                $result = ['status' => 4, 'message' => '对不起，该商城自营供货单不可在后台交易'];
            } else {
                $result = ['status' => 1, 'message' => ''];
            }
        }
        $result['data'] = $data;
        $this->echoJson($result);
    }


    /**
     * 通过用户ID查询公司信息
     * @author xiaowen
     * @time 2017-3-29
     */
    public function getInfoById()
    {
        //获取交易员信息
        $user_id = trim(I('post.id', '', 'htmlspecialchars'));
        if ($user_id) {
            $dealer = $this->getEvent('User')->getDealerInfoByUserId($user_id);
            $data['d_name'] = $dealer['dealer_name'];
            $data['d_id'] = $dealer['dealer_id'];
            //获取公司信息
            $company_id = getCompanyId($user_id);
            $data['c_name'] = getUserCompanys($company_id, ['status'=>['egt', 20]]);
        } else {
            $data = [];
        }

        $this->ajaxReturn($data);
    }

}
