<?php
namespace Home\Controller;

class UserController extends BaseController
{

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 用户列表
     * @Time    20160826
     * @author lizhipeng <lizhipeng@51zhaoyou.com>
     */

    public function relist()
    {
        // @ 列表修改 <lizhipeng | 2016.10.26>
        if (IS_AJAX) {
            $address_phone = trim(I('get.address_phone', ''));
            if ($address_phone && $address_phone != 'null' && $address_phone != '') {
                $where['address_phone'] = $address_phone;
            }
            $where['status'] = 1;

            $data['data'] = D('User')->getAddressInfoList("", "user_phone", $where);
            foreach ($data['data'] as $k => $v) {
                if (empty($v['user_name'])) {
                    $data['data'][$k]['user_name'] = "未知";
                }
            }
            $this->echoJson($data);
        }
        // @ end
        $this->display();
    }

    /**
     * 用户添加页面
     * @Time    20160829
     * @author lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function addUserInfo()
    {
        $this->display();
    }

    /**
     * 用户添加操作
     * @Time    20160829
     * @author lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function actAddAddressUser()
    {
        $data = I('post.', '', 'htmlspecialchars');
        //$data['region'] = getAllAreaId($data['region'])[0]['id'];
        $message = [
            'q' => "用户手机号输入有误,请重新输入!",
            'w' => "收货人手机号输入有误,请重新输入!",
            'e' => "收货人姓名不能为空,请重新输入!",
            'r' => "详细地址不能为空,请重新输入!",
            't' => "添加成功!",
            'y' => "添加失败,请重试!",
            'u' => "该用户未注册！",
            'i' => "该用户已流失！",
        ];
        if ($data['user_phone'] < 0 || strlen($data['user_phone']) != 11) {
            $this->ajaxReturn(['status' => 100, 'message' => $message['q']]);
        } elseif ($data['address_phone'] < 0 || strlen($data['address_phone']) != 11) {
            $this->ajaxReturn(['status' => 101, 'message' => $message['w']]);
        } elseif (!$data['address_name']) {
            $this->ajaxReturn(['status' => 102, 'message' => $message['e']]);
        } elseif (!$data['address_info']) {
            $this->ajaxReturn(['status' => 103, 'message' => $message['r']]);
        } else {
            if ($data['is_default'] == 1) {
                $res = D('User')->getAddressInfoList('', '', ['status' => 1, 'is_default' => 1]);
                if ($res) {
                    $reg = D('User')->saveAddress(['status' => 1, 'is_default' => 1, 'user_phone' => $data['user_phone']], ['is_default' => 0]);
                    if ($reg) {
                        $data['create_time'] = date("Y-m-d H:i:s");
                        $data['user_id'] = getAllUserId($data['user_phone'])[$data['user_phone']];
                        $data['status'] = 1;
                        $reg = M('User')->where(['user_phone' => ['eq', $data['user_phone']], 'is_available' => ['neq', 1]])->select();
                        if (empty($reg)) {
                            $this->ajaxReturn(['status' => 105, 'message' => $message['u']]);
                        } else if ($reg['0']['is_available'] == 2) {
                            $this->ajaxReturn(['status' => 106, 'message' => $message['i']]);
                        } else {
                            $result = D('User')->insertAddressInfo($data);
                            if ($result) {
                                $this->ajaxReturn(['status' => 1, 'message' => $message['t']]);
                            } else {
                                $this->ajaxReturn(['status' => 104, 'message' => $message['y']]);
                            }
                        }
                    }
                }
            }
            $data['create_time'] = date("Y-m-d H:i:s");
            $data['user_id'] = getAllUserId($data['user_phone'])[$data['user_phone']];
            $data['status'] = 1;
            $reg = M('User')->where(['user_phone' => ['eq', $data['user_phone']], 'is_available' => ['neq', 1]])->select();
            if (empty($reg)) {
                $this->ajaxReturn(['status' => 105, 'message' => $message['u']]);
            } else if ($reg['0']['is_available'] == 2) {
                $this->ajaxReturn(['status' => 106, 'message' => $message['i']]);
            } else {
                $result = D('User')->insertAddressInfo($data);
                if ($result) {
                    $this->ajaxReturn(['status' => 1, 'message' => $message['t']]);
                } else {
                    $this->ajaxReturn(['status' => 104, 'message' => $message['y']]);
                }
            }

        }
    }

    /**
     * 地址编辑
     */
    public function edit()
    {
        $id = intval(I('param.id', '', 'htmlspecialchars'));
        if (IS_AJAX) {
            $reg = D('User')->getAddressInfoList("", "", ['id' => ['eq', $id], 'status' => 1]);
            $result['data'] = D('User')->getAddressInfoList("", "", ['user_phone' => ['eq', $reg['0']['user_phone']], 'status' => 1]);
            foreach ($result['data'] as $k => $v) {
                $result['data'][$k]['address'] = getAllArea($v['region']) . " " . $v['address_info'];
            }
            $this->ajaxReturn($result);
        }
        $this->assign("id", $id);
        $this->display();
    }

    /**
     * 用户添加地址
     */
    public function addUserAddressInfo()
    {
        $id = intval(I('param.id', '', 'htmlspecialchars'));
        $result = D('User')->getAddressInfoList("", "", ['id' => ['eq', $id], 'status' => 1]);
        $this->assign("data", $result);
        $this->display();
    }


    /**
     * 用户删除
     */
    public function del()
    {
        $message = [
            'q' => "系统异常,请重新尝试!",
            't' => "删除成功!",
            'y' => "删除失败,请重试!",
            'z' => '该地址已有录单，无法删除！'
        ];
        $id = intval(I('post.id', '', 'htmlspecialchars'));
        if ($id < 0) {
            $this->ajaxReturn(['status' => 100, 'message' => $message['q']]);
        }
        $retail_order = D('retail_order')->findData(['status' => ['NEQ', 0], 'retail_adress_id' => $id]);
        if (!empty($retail_order)) {
            $this->ajaxReturn(['status' => 102, 'message' => $message['z']]);
        }
        $result = D('User')->delAddressInfo(['id' => ['eq', $id]]);
        if ($result) {
            $this->ajaxReturn(['status' => 1, 'message' => $message['t']]);
        } else {
            $this->ajaxReturn(['status' => 101, 'message' => $message['y']]);
        }
    }

    /**
     * 设置默认地址
     * User:lizhipeng
     * Time:20160909
     * Return: int $status
     */
    public function change()
    {
        $message = [
            'q' => "设置成功!",
            'y' => "设置失败,请重试!"
        ];
        $id = intval(I('param.id', '', 'htmlspecialchars'));
        $reg = D('User')->getAddressInfoList('', '', ['id' => ['eq', $id]]);
        $res = D('User')->getAddressInfoList('', '', ['user_phone' => ['eq', $reg['0']['user_phone']], 'status' => ['eq', 1], 'is_default' => 1]);

        if (count($res) == 0) {
            $result = D('User')->saveAddress(['id' => ['eq', $id]], ['is_default' => 1]);
            if ($result) {
                $this->ajaxReturn(['status' => 1, 'message' => $message['q']]);
            } else {
                $this->ajaxReturn(['status' => 100, 'message' => $message['y']]);
            }
        } else {
            foreach ($res as $k => $v) {
                $user_id[] = $v['id'];
            }
            $user_id = array_unique($user_id);
            $up_def = D('User')->saveAddress(['id' => ['IN', $user_id]], ['is_default' => 0]);
            if ($up_def) {
                $result = D('User')->saveAddress(['id' => ['eq', $id]], ['is_default' => 1]);
                if ($result) {
                    $this->ajaxReturn(['status' => 1, 'message' => $message['q']]);
                } else {
                    $this->ajaxReturn(['status' => 100, 'message' => $message['y']]);
                }
            }
        }
    }

    public function test()
    {
        $a = orderNumber($type = 1);
        var_dump($a);
        die;
    }


    /*
     * -------------------------------------
     * 短信发送列表
     * Author:jk    Time:2016-11-16
     * -------------------------------------
     */
    public function smsList()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $data['data'] = $this->getEvent('User')->smsList($param);
            $this->echoJson($data);
        }

        $this->display();
    }

    /**
     * 全都用户
     */
    public function userAllList()
    {
        if (IS_AJAX) {
            $param = I('get.');
            $where = [];
            if (trim($param['user_phone']) != '') {
                $where['user_phone'] = $param['user_phone'];
            }
            if (trim($param['user_name']) != '') {
                $where['user_name'] = $param['user_name'];
            }
            if (trim($param['dealer_name']) != '') {
                $where['dealer_name'] = $param['dealer_name'];
            }
            $data = $this->getEvent('User')->userList($where);
            $this->echoJson($data);
        }

        $this->display();
    }

    /**
     * 我的用户
     */
    public function userMyList()
    {
        if (IS_AJAX) {
            $param = I('get.');
            $where = [];
            $where['dealer_name'] = session('adminInfo')['dealer_name'];

            if (trim($param['user_phone']) != '') {
                $where['user_phone'] = $param['user_phone'];
            }
            if (trim($param['user_name']) != '') {
                $where['user_name'] = $param['user_name'];
            }

            $data = $this->getEvent('User')->userList($where);
            $this->echoJson($data);
        }

        $this->display();
    }

    /**
     * 添加用户
     * @author xiaowen
     */
    public function addUser()
    {
        if (IS_AJAX) {
            $param = I('param.');
            if ($param) {
                foreach ($param as $key => $val) {
                    $param[$key] = trim($val);
                }
            }

            $data = $this->getEvent('User')->addUser($param);

            $this->echoJson($data);
        }

        $data['userSex'] = userSex();
        $data['userSource'] = userSource();
        $area = CityToPro();
        $data['region'] = $area['cityid2cityname'];
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑用户
     * @author xiaowen
     */
    public function editUser()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param = I('param.');
            if ($param) {
                foreach ($param as $key => $val) {
                    $param[$key] = trim($val);
                }
            }

            $data = $this->getEvent('User')->editUser($param);

            $this->echoJson($data);
        }
        $data['detail'] = $this->getEvent('User')->userInfo($id);
        $data['userSex'] = userSex();
        $data['userSource'] = userSource();
        $area = CityToPro();
        $data['region'] = $area['cityid2cityname'];
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 绑定公司
     * @author xiaowen
     * @Time 2016-11-24
     */
    public function bindCompany()
    {
        $id = intval(I('get.id', 0));
        if (IS_AJAX) {
            $param = I('post.');
            if ($param) {
                foreach ($param as $key => $val) {
                    $param[$key] = trim($val);
                }
            }

            $company_info = $this->getModel('Clients')->field('id, status')->where(['company_name' => trim($param['company_name']), 'is_available' => 0])->find();
            if (empty($company_info)) {
                $data['status'] = 0;
                $data['message'] = '公司不存在，无法绑定！';
                $this->echoJson($data);
            }
            $param['company_id'] = $company_info['id'];
            $param['company_status'] = $company_info['status'];
            $data = $this->getEvent('User')->bindCompany($param);
            $this->echoJson($data);
        }
        $data['company'] = $this->getEvent('User')->bindCompanyList($id);
        $data['userPosition'] = userPosition();
        $data['userDealStatus'] = ucDealStatus();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 用户详情
     * @author xiaowen
     * @Time 2016-11-24
     */
    public function detailUser()
    {
        $id = intval(I('param.id', '0'));
        //echo $id;
        $data = $this->getEvent('User')->userInfo($id, 1);
        //print_r($data);
        $data['userSex'] = userSex();
        $data['userSource'] = userSource();

        // @用户扩展信息
        $data['constellation'] = ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座'];

        // @交易信息
        $data['deal_data'] = $this->getEvent('User')->getDealData($id);
        // @销售规律
        $data['sale_type'] = ['柴油', '汽油', '航煤', '原料', '燃料油', '船用油', '天然气']; //出货品类
        $data['sale_brand'] = ['中国石化', '中国石油', '中国化工', '中国中化', '中国海油', '地炼', '其他']; //出货品牌
        $data['auth_money'] = ['500万以下', '500万-1000万', '1000万-2000万', '2000万以上']; //授信金额
        $data['plan_time'] = ['下午2点', '下午3点', '下午4点', '下午5点']; //报计划时间
        $data['fapiao_time'] = ['随时开票', '月末开票', '下月开票']; //开票时间
        $data['sale_count'] = ['100吨以下', '100吨-500吨', '500-2000吨', '2000吨-5000吨', '5000吨-10000吨', '10000吨以上']; //月度出货量
        $data['sale_rule'] = ['小于每天', '小于每周', '小于每半月', '小于每月']; //出货频次

        // @采购规律
        $data['purchase_time'] = ['工作日上午', '工作日下午', '节假日'];

        $data['settlement_method'] = ['款到发货', '货到付款', '按协议结算', '其他'];
        $data['pay_type'] = ['网银转账', '现金', '刷卡', '支票', '贷记凭证', '其他'];

        // @小微客户需求
        $data['storehouse'] = $this->getModel('Depot')->where(['status' => 1])->select();
        $s_c = CityToPro();
        foreach ($data['storehouse'] as $k => $v) {
            $data['storehouse'][$k]['area_s'] = $s_c['cityid2provincename'][$data['storehouse'][$k]['depot_area']];
            $data['storehouse'][$k]['area_c'] = $s_c['cityid2cityname'][$data['storehouse'][$k]['depot_area']];
        }

        $data['equipment_type'] = ['油桶', '油罐', '塑料吨箱', '铁吨箱', '手摇泵', '手摇计量泵'];
        $data['product_channel'] = ['贸易商', '加油站', '主营'];
        $data['wastage'] = ['200L以下', '200L至1000L', '1000L至5000L', '5000L至10000L', '10000L以上'];
        $data['max_capacity'] = ['小于等于200L', '小于等于500L', '小于等于1000L', '小于等于2000L', '小于等于4000L', '小于等于8000L', '小于等于10000L', '小于等于12000L', '12000L以上'];
        $data['purchase_period'] = ['小于每天', '小于每三天', '小于每周', '小于每半月', '小于每月', '小于每两月'];
        $data['purchase_concern'] = ['价格', '品牌', '油质', '时间'];
        $data['serve_time'] = ['工作日上午', '工作日下午', '工作日晚上', '节假日上午', '节假日下午', '节假日晚上', '不限'];

        $area = CityToPro();
        $data['region'] = $area['cityid2cityname'];
        $data['clients'] = $this->getEvent('User')->bindCompanyList($id);
        if ($data['clients']) {
            foreach ($data['clients'] as $key => $val) {
                $data['clients'][$key]['deal_status_name'] = ucDealStatus($val['deal_status']);
            }
        }
        //print_r( $data['clients']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 保存扩展信息
     * @author xiaowen
     * @Time 2016-11-30
     */
    public function saveExtInfo()
    {
        $u_id = intval(I('post.u_id', 0));
        $data = I('post.');
        if (IS_AJAX) {
            if (!$u_id) {
                $this->echoJson(['status' => 0, 'message' => '参数有误,用户ID无法获取！']);
            }

            $result = $this->getEvent('User')->saveExtInfo($u_id, $data);
            $this->echoJson($result);
        }

    }

    /**
     * 保存拜访记录
     * @author xiaowen
     * @Time 2016-11-30
     */
    public function saveVisitInfo()
    {
        $u_id = I('post.u_id', 0);
        //$id = I('post.id', 0);
        $data = I('post.');
        if (IS_AJAX) {
            if (!$u_id) {
                $this->echoJson(['status' => 0, 'message' => '参数有误,用户ID无法获取！']);
            }

            $result = $this->getEvent('User')->saveVisitInfo($u_id, $data);
            $this->echoJson($result);
        }

    }

    /**
     * 拜访记录列表
     * @author xiaowen
     * @time 2016-11-30
     *
     */
    public function visitList()
    {
        //用户ID
        $u_id = I('param.id', 0);
        //echo $u_id;
        if (IS_AJAX) {
            if (!$u_id) {
                $result['data'] = [];
            } else {
                $result = $this->getEvent('User')->getVisitList($u_id);
            }
            $this->echoJson($result);
        }
    }

    /**
     * 获取拜访记录详情
     */
    public function ajaxGetVisitInfo()
    {
        //记录ID
        $id = I('param.id', 0);

        if (IS_AJAX) {
            if (!$id) {
                $result = [];

            } else {
                $result = $this->getEvent('User')->getVisitInfo($id);
            }
            $this->echoJson($result);
        }
    }

    public function ajaxDelVisitInfo()
    {

        //记录ID
        $id = I('param.id', 0);

        if (IS_AJAX) {
            if (!$id) {

                $result = ['status' => 0, 'message' => '参数有误,ID无法获取！'];
            } else {
                $result = $this->getEvent('User')->delVisitInfo($id);
            }
            $this->echoJson($result);
        }
    }

    /**
     * 保存销售规律
     * @author xiaowen
     * @Time 2016-11-30
     */
    public function saveSellInfo()
    {
        $u_id = I('post.u_id', 0);
        //$id = I('post.id', 0);
        $data = I('post.');
        if (IS_AJAX) {
            if (!$u_id) {
                $this->echoJson(['status' => 0, 'message' => '参数有误,用户ID无法获取！']);
            }

            $result = $this->getEvent('User')->saveSellInfo($u_id, $data);
            $this->echoJson($result);
        }

    }

    /**
     * 保存销售规律
     * @author xiaowen
     * @Time 2016-11-30
     */
    public function saveBuyInfo()
    {
        $u_id = I('post.u_id', 0);

        $data = I('post.');
        if (IS_AJAX) {
            if (!$u_id) {
                $this->echoJson(['status' => 0, 'message' => '参数有误,用户ID无法获取！']);
            }

            $result = $this->getEvent('User')->saveBuyInfo($u_id, $data);
            $this->echoJson($result);
        }

    }

    /**
     * 保存小微客户需求
     * @author xiaowen
     * @Time 2016-11-30
     */
    public function saveMicrocustomer()
    {
        $u_id = I('post.u_id', 0);

        $data = I('post.');
        if (IS_AJAX) {
            if (!$u_id) {
                $this->echoJson(['status' => 0, 'message' => '参数有误,用户ID无法获取！']);
            }

            $result = $this->getEvent('User')->saveMicrocustomer($u_id, $data);
            $this->echoJson($result);
        }
    }

    /**
     *  转接用户
     * @author xiaowen
     * @Time 2016-11-30
     */
    public function transferUser()
    {

        if (IS_AJAX) {

        }

        $data = [];
        //department_id in (26, 27) 为地推组、交易组 人员
        $data['dealer'] = getDealerCondition(['is_available' => 0, 'department_id' => ['in', [26, 27]]]);
        $data['area'] = CityToPro()['provinceid2provincename'];

        //print_r($data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 根据手机号或姓名关键词查找用户
     * @author xiaowen
     * @time 2017-3-28
     */
    public function getUserByPhoneName()
    {
        $q = strHtml(I('param.q', ''));
        if ($q) {
            $data['data'] = $this->getEvent('User')->getUserByPhoneName($q);
            if (count($data['data'])) {
                $data['incomplete_results'] = true;
                $data['total_count'] = count($data['data']);
            } else {
                $data['incomplete_results'] = true;
                $data['total_count'] = 0;
            }
            $this->echoJson($data);
        }
    }


    /**
     * 根据名称查找交易员
     * @author senpai
     * @time 2017-04-05
     */
    public function getDealerByName()
    {
        $q = strHtml(I('param.q', ''));
        if ($q) {
            $data['data'] = $this->getEvent('User')->getDealerByName($q);
            if (count($data['data'])) {
                $data['incomplete_results'] = true;
                $data['total_count'] = count($data['data']);
            } else {
                $data['incomplete_results'] = true;
                $data['total_count'] = 0;
            }
            $this->echoJson($data);
        }
    }

    /**
     * 根据手机获取用户姓名和公司名称
     * @author senpai
     * @return json
     * @time 2017-03-14
     */
    public function getInfo()
    {
        //获取交易员信息
        $tel = trim(I('post.phone', '', 'htmlspecialchars'));
        $dealer = getAllDealerId($tel);
        $data['d_name'] = $dealer['dealer_name'];
        $data['d_id'] = $dealer['id'];

        //获取用户id
        $user_id = getUserId($tel);
        $data['u_id'] = $user_id[0]['id'];

        //获取登陆信息
        $session = session();
        $data['s_id'] = $session['erp_adminInfo']['id'];

        //获取公司信息
        $company_id = getCompanyId($user_id['0']['id']);
        $data['c_name'] = getUserCompanys($company_id, ['status'=>['egt', 20]]); //必须是公司信息已审核后
        $this->ajaxReturn($data);
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
        $type = trim(I('post.type', '', 'htmlspecialchars'));
        if ($user_id) {
            $dealer = $this->getEvent('User')->getDealerInfoByUserId($user_id);
            $data['d_name'] = $dealer['dealer_name'];
            $data['d_id'] = $dealer['dealer_id'];
            //获取公司信息
            $company_id = getCompanyId($user_id);
//            $data['c_name'] = getUserCompanys($company_id, ['status' => 40]);  //关联出用户三证已审核公司 edit 2017-4-10
            $where = [
                'status' => ['egt', 20]
            ];
            $company_info = getUserCompanys($company_id, $where);  //不需要三证审核公司 edit 2017-05-24
            $erp_company = getAllErpCompany();
            if ($type) {
                foreach ($company_info as $key => $value) {
                    if ($value['transaction_type'] != $type && !array_key_exists($value['id'], $erp_company) && $value['transaction_type'] != 3) {
                        unset($company_info[$key]);
                    }
                }
            }
            $data['c_name'] = array_values($company_info);
        } else {
            $data = [];
        }

        $this->ajaxReturn($data);
    }
}
