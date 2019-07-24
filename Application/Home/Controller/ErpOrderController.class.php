<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpOrderController extends BaseController
{

    /**
     *交易单列表
     *DATE:2017-03-10 Time:11:00
     *Author: xiaowen <xiaowen@51zhaoyou.com>
     */
    public function orderList()
    {

        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpOrder')->orderList($param);
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
        $data['erpOrderStatus'] = ErpOrderStatus();
        $data['regionList'] = provinceCityZone()['city'];
        //$data['companyList'] = getClientsData(1);
        $access_node = $this->getUserAccessNode('ErpOrder/orderList');
        $this->assign('access_node', json_encode($access_node));
        $this->assign('data', $data);
        $this->display();
    }

    /**
     *我的交易单
     *DATE:2017-03-29
     *Author: senpai
     */
    public function myOrderList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['dealer_id'] = session('erp_adminInfo')['id'];
            $data = $this->getEvent('ErpOrder')->orderList($param);
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
        $data['erpOrderStatus'] = ErpOrderStatus();
        $data['regionList'] = provinceCityZone()['city'];
        //$data['companyList'] = getClientsData(1);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 新增交易单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function addOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            $data = $this->getEvent('ErpOrder')->addOrder($param);

            $this->echoJson($data);
        }
        //获取交易单详细信息
        $data = $this->getEvent('ErpSupply')->findSupply($id, true);
        //print_r($data);
        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        //print_r($new_depots);
        $data['depots'] = json_encode($new_depots);

        $select_data['dealer']['dealer_id'] = session('erp_adminInfo')['id'];
        $select_data['dealer']['dealer_name'] = session('erp_adminInfo')['dealer_name'];
        $select_data['user'] = getUserByDealer(session('erp_adminInfo')['dealer_name']);

        $this->assign('select_data', $select_data);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->display();
    }

    /**
     * 添加交易单操作
     * @author senpai
     * @time 2017-03-15
     */
    public function actAddOrder()
    {

    }

    /**
     * 编辑交易单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function updateOrder()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $param = I('param.');
            $data = $this->getEvent('ErpOrder')->updateOrder($param);

            $this->echoJson($data);
        }

        $data = $this->getEvent('ErpOrder')->findOrder($id);
        $data['sale_num'] = getNum($data['sale_num']);
        $data['lock_num'] = getNum($data['lock_num']);
        $data['pay_img_list'] = $this->getEvent('ErpOrder')->getOrderPayImg($id);
        //print_r($data['pay_img_list']);
        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }
        $data['img_url'] = $this->uploads_path['order_img']['url'];
        $data['depots'] = json_encode($new_depots);

        $select_data['dealer']['dealer_id'] = session('erp_adminInfo')['id'];
        $select_data['dealer']['dealer_name'] = session('erp_adminInfo')['dealer_name'];
        $select_data['user'] = getUserByDealer($data['dealer_name']);

        $this->assign('select_data', $select_data);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->assign('id', $id);

        $this->display();

    }

    /**
     * 交易单详情
     * @author senpai
     * @time 2017-03-29
     */
    public function orderDetail()
    {
        $id = intval(I('param.id', 0));

        //获取单条数据
        $data = $this->getEvent('ErpOrder')->findOrder($id);

        //设置数字
        $data['sale_num'] = getNum($data['sale_num']);
        $data['lock_num'] = getNum($data['lock_num']);

        //设置图片、地址、油库
        $data['pay_img_list'] = $this->getEvent('ErpOrder')->getOrderPayImg($id);
        $data['img_url'] = $this->uploads_path['order_img']['url'];
        $region['region_list'] = provinceCityZone()['city'];
        $data['depots'] = getDepotData();
        $data['depots']['99999'] = array('id' => 99999, 'depot_name' => '不限油库');

        //设置公司信息
        $data['buy_company_name'] = $this->getModel('Clients')->where(['id'=>$data['buy_company_id']])->getField('company_name');

        //设置交易员信息和用户信息
        $select_data['dealer']['dealer_id'] = session('erp_adminInfo')['id'];
        $select_data['dealer']['dealer_name'] = session('erp_adminInfo')['dealer_name'];
        $select_data['user'] = getUserByDealer($data['dealer_name']);

        $this->assign('select_data', $select_data);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->assign('id', $id);

        $this->display();

    }

    /**
     * 编辑交易单操作
     * @author senpai
     * @time 2017-03-15
     */
    public function actUpdateOrder()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpOrder')->updateSupply($param);
            $this->echoJson($data);
        }
    }

    /**
     * 删除交易单
     * @author xiaowen
     * @time 2017-3-10
     */
    public function delOrder()
    {
        $id = intval(I('post.id', 0));

        if (IS_AJAX) {
            $data = $this->getEvent('ErpOrder')->delOrder($id);
            $this->echoJson($data);
        }
    }

    /**
     * 审核交易单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function auditOrder()
    {
        $id = intval(I('post.id', 0));
        if ($id) {
            $data = $this->getEvent('ErpOrder')->auditOrder($id);
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
     * 预审交易单
     * @author xiaowen
     * @time 2017-03-13
     */
    public function preAuditOrder()
    {
        $id = intval(I('post.id', 0));
        if ($id) {
            $data = $this->getEvent('ErpOrder')->preAuditOrder($id);
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
     * 上传付款截图
     */
    public function uploadPayImg()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

            $param = I('param.attach');
            $data = $this->getEvent('ErpOrder')->uploadPayImg($id, $param);

            $this->echoJson($data);
        }

        $data = $this->getEvent('ErpOrder')->findOrder($id);

        $region['region_list'] = provinceCityZone()['city'];
        $DepotData = getDepotData();
        if ($DepotData) {
            foreach ($DepotData as $key => $value) {
                $new_depots[$value['depot_area']][] = $value;
            }
        }

        $data['depots'] = json_encode($new_depots);
        $this->assign('data', $data);
        $this->assign('region', $region);
        $this->assign('id', $id);
        $this->display();
    }

    public function delPayImg()
    {
        $id = intval(I('param.id', 0));

        if ($id) {
            $data = $this->getEvent('ErpOrder')->delPayImg($id);
            $this->echoJson($data);
        } else {
            $data['status'] = 0;
            $data['message'] = '无法获取要删除的图片ID';

        }
        $this->echoJson($data);
    }

    /**
     * 配送批次
     * @author senpai
     * @time 2017-03-20
     */
    public function distributionBatch()
    {
        $id = intval(I('param.id', 0));
        $param = $_REQUEST;
        $delivery = $this->getEvent('ErpOrder')->distributionBatch($param);
        if (IS_AJAX) {
            $this->echoJson($delivery);
        }
        $data = $data = $this->getEvent('ErpOrder')->findOrder($id, true);
        $delivery_num = 0;
        if (count($delivery['data']) > 0) {
            foreach ($delivery['data'] as $k => $v) {
                $delivery_num += $v['delivery_num'];
            }
        }
        $data['buy_num'] = getNum($data['buy_num']) - $delivery_num;
        $this->assign('data', $data);
        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 验证交易单是否可以配送
     * @author xiaowen
     * @time 2017-03-13
     */
    public function ajaxOrderCanDelivery()
    {
        $id = intval(I('param.id', 0));
        $data = $data = $this->getEvent('ErpOrder')->findOrder($id, true);
        if (empty($data)) {
            $result = ['status' => 0, 'message' => '交易单不存在,请稍后尝试'];
        } else {
            if ($data['status'] != 10) {
                $result = ['status' => 2, 'message' => '交易单不是已审核,请审核后操作'];
            } else {
                $result = ['status' => 1, 'message' => ''];
            }
        }
        $result['data'] = $data;
        $this->echoJson($result);
    }

    /**
     * 配送批次更新操作
     * @author senpai
     * @time 2017-03-20
     */
    public function updateDelivery()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpOrder')->updateDelivery($param);
            $this->echoJson($data);
        }
    }

}
