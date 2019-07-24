<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class DepotController extends BaseController
{
    /**
     *油库列表
     *Return: json
     *DATE:2016-11-23 Time:11:00
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function depotList()
    {
        if (IS_AJAX) {
            $data['data'] = $this->getEvent('Depot')->depotList();
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     *油库列表
     *Return: json
     *DATE:2016-11-23 Time:11:00
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function addDepot()
    {
        $data = [
            'product' => $this->getModel('Depot')->getDepotData('product'),           # 主营
            'pick_type' => $this->getModel('Depot')->getDepotData('pick_type'),         # 提货方式
            'deliver_type' => $this->getModel('Depot')->getDepotData('deliver_type'),      # 发货方式
            'area' => $this->getModel('Depot')->getArea(),
        ];
        // AJAX表单提交
        if (IS_AJAX) {
            $param = ['product', 'depot_name', 'tel', 'work_time', 'pick_type', 'deliver_type', 'depot_area', 'address', 'remarks'];
            if (count(I('post.')) != count($param)) $this->ajaxReturn(['status' => 2, 'message' => $this->getModel('Depot')->getMessage('a')]);
            foreach (I('post.') as $k => $v) {
                if (!in_array($k, $param)) $this->ajaxReturn(['status' => 3, 'message' => $this->getModel('Depot')->getMessage('a')]);
                $post[$k] = htmlspecialchars(trim($v));
            }
            $w_f['depot_name'] = array('eq', $post['depot_name']);
            $w_data = $this->getModel('Depot')->getOneDepot($w_f);
            if (!empty($w_data)) $this->ajaxReturn(['status' => 5, 'message' => $this->getModel('Depot')->getMessage('g')]);
            $post['add_time'] = currentTime();
            $post['dealer_name'] = htmlspecialchars(trim(session('adminInfo')['dealer_name']));
            //dump($post);die;
            $result = $this->getModel('Depot')->addDepot($post);
            $return = ['status' => 1, 'message' => $this->getModel('Depot')->getMessage('c')];
            if (!$result) $return = ['status' => 4, 'message' => $this->getModel('Depot')->getMessage('d')];

            $this->ajaxReturn($return);
        }
        $this->assign('data', $data);
        $this->display('addDepot');
    }

    /**
     *删除油库
     *DATE:2016-11-23 Time:11:11
     *Return: json
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function deleteDepot()
    {
        if (IS_AJAX) {
            $param = I('param.', '', 'htmlspecialchars');
            $data = $this->getEvent('Depot')->deleteDepot($param);
            $this->echoJson($data);
        }
    }

    /**
     *油库信息修改
     *DATE:2016-11-23 Time:15:20
     *Return: json
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function updateDepot()
    {
        $data = [];
        $where['id'] = array('eq', intval(I('get.id')));
        if (intval(I('get.id')) > 0) $data['data'] = $this->getModel('Depot')->getOneDepot($where);

        $data['product'] = $this->getModel('Depot')->getDepotData('product');
        $data['pick_type'] = $this->getModel('Depot')->getDepotData('pick_type');
        $data['deliver_type'] = $this->getModel('Depot')->getDepotData('deliver_type');
        $data['area'] = $this->getModel('Depot')->getArea();
        $data['area_s'] = $this->getModel('Depot')->getArea('', $data['data']['depot_area']);
        $data['city'] = CityToPro()['provincename2citynamelist'][$data['area_s']];

        // AJAX提交修改
        if (IS_AJAX) {
            $param = ['product', 'depot_name', 'tel', 'work_time', 'pick_type', 'deliver_type', 'depot_area', 'address', 'remarks'];
            $update_id = intval(I('post.id'));
            $p_d = I('post.');
            unset($p_d['id']);
            if (count($p_d) != count($param)) $this->ajaxReturn(['status' => 2, 'message' => $this->getModel('Depot')->getMessage('a')]);
            foreach ($p_d as $k => $v) {
                if (!in_array($k, $param)) $this->ajaxReturn(['status' => 3, 'message' => $this->getModel('Depot')->getMessage('a')]);
                $post[$k] = htmlspecialchars(trim($v));
            }
            $w_f['depot_name'] = array('eq', $post['depot_name']);
            $w_f['id'] = array('neq', $update_id);
            $w_data = $this->getModel('Depot')->getOneDepot($w_f);
            if (!empty($w_data)) $this->ajaxReturn(['status' => 5, 'message' => $this->getModel('Depot')->getMessage('g')]);
            $post['update_time'] = currentTime();
            $post['dealer_name'] = htmlspecialchars(trim(session('adminInfo')['dealer_name']));
            $w['id'] = array('eq', intval($update_id));
            $result = $this->getModel('Depot')->updateDepot($w, $post);
            $return = ['status' => 1, 'message' => $this->getModel('Depot')->getMessage('e')];
            if (!$result) $return = ['status' => 4, 'message' => $this->getModel('Depot')->getMessage('f')];

            $this->ajaxReturn($return);
        }
        $this->assign('data', $data);
        $this->display('updateDepot');
    }

    /**
     *AJAX获取城市
     *DATE:2016-11-23 Time:17:39
     *Return: json
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function ajaxCity()
    {
        $s_id = I('post.s_id/d');
        if (empty($s_id) || $s_id === 0) return $this->ajaxReturn(['status' => 0, 'message' => $this->getModel('Depot')->getMessage('b'), 'info' => []]);

        $info = $this->getModel('Depot')->getArea($s_id);
        $this->ajaxReturn(['status' => 1, 'message' => '', 'info' => $info]);
    }

}
