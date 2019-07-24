<?php
namespace Home\Controller;

class GalaxyController extends BaseController
{

    /**
     * 集团客户列表
     * @author xiaowen
     * @time 2016-12-14
     */
    public function companyList()
    {

        if (IS_AJAX) {

            $company_name = trim(I('get.company_name', ''));
            if ($company_name) {
                $where['c.company_name'] = ['like', '%' . $company_name . '%'];
            }
            $where['c.type'] = 2;
            $where['c.is_available'] = 0;
            $where['u.role'] = 1;
            $where['u.is_available'] = 0;
            $where['u.user_type'] = 2;
            $where['u.pid'] = 0;
            $data = $this->getEvent('Galaxy')->companyList($where);

            $companyIDs = array_column($data['data'], 'id');
            $adminRole = D('galaxy_role')->where(['type' => 1, 'company_id' => array('in', $companyIDs)])->getField('company_id', true);

            foreach ($data['data'] as $k => $val) {
                if (in_array($val[id], $adminRole)) {
                    $data['data'][$k]['setAdmin'] = 1;
                } else {
                    $data['data'][$k]['setAdmin'] = 0;
                }
            }
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 集团分公司列表
     * @author qianbin
     * @time 2017-02-27
     */
    public function companyClientList()
    {
        if (IS_AJAX) {
            $boss_id = $_POST['id'];
            $data = $this->getEvent('Galaxy')->getUserClients($boss_id);
            $this->echoJson($data);
        }
    }

    /**
     * 公司添加admin角色
     * @author ypm
     * @time 2017-02-21
     */
    public function addAdmin()
    {
        $companyID = I('param.companyid');
        if (empty($companyID)) {
            $this->echoJson(['status' => 0, 'msg' => '公司ID错误']);
        }
        $info = D('galaxy_role')->where(['type' => 1, 'company_id' => $companyID])->find();
        if (!empty($info)) {
            $this->echoJson(['status' => 0, 'msg' => '公司已有管理员，不要重复添加']);
        }

        $role = ['name' => 'admin', 'type' => 1, 'company_id' => $companyID, 'create_time' => date('Y-m-d H:i:s')];
        $resultRole = D('galaxy_role')->add($role);
        if ($resultRole) {
            $this->echoJson(['status' => 1, 'msg' => '添加成功']);
        } else {
            $this->echoJson(['status' => 0, 'msg' => '添加失败']);
        }
    }

    /**
     * 设置admin权限页面
     * @author ypm
     * @time 2017-02-21
     */
    public function setPower()
    {
        $companyID = I('param.companyid');
        $userid = I('param.userid');

        $userInfo = D('user')->where(['_complex' => array('pid' => $userid, 'id' => $userid, '_logic' => 'or'), 'is_available' => 0])->getField('id,user_name,role');

        $roleNode = array();
        $adminID = 0;
        $roleInfo = D('galaxy_role')->where(['type' => 1, 'company_id' => $companyID])->find();
        if (!empty($roleInfo)) {
            $roleNode = D('galaxy_role_node')->where(['role_id' => $roleInfo['id']])->getField('node_id', true);
            $adminID = D('galaxy_role_user')->where(['role_id' => $roleInfo['id']])->getField('user_id');
            if (!empty($adminID)) {
                $this->assign('adminID', $adminID);
            }
        }

        $nodeData = D('galaxy_node')->field('id,pid as parentId, node_name as name')->where(['is_show' => 1])->select();
        foreach ($nodeData as $key => $value) {
            $value['open'] = true;
            $value['checked'] = in_array($value['id'], $roleNode) ? true : false;
            $nodeData[$key] = $value;
        }

        $this->assign('userInfo', $userInfo);
        $this->assign('company_id', $companyID);
        $this->assign('role_id', $roleInfo['id']);
        $this->assign('nodeData', json_encode($nodeData));

        $this->display();
    }

    /**
     * 给admin角色添加权限节点
     * @author ypm
     * @time 2017-02-21
     */
    public function setRoleNode()
    {
        $role_id = I('param.role_id', 0, 'int');
        $node_id = I('param.node_id', 0, 'int');
        $where['role_id'] = $role_id;
        $where['node_id'] = $node_id;
        if (!($role_id && $node_id)) {
            $this->echoError('参数有误');
        }
        if (I('param.checked') == 'true') {
            $haveNode = M('galaxy_role_node')->where($where)->find();
            if (empty($haveNode)) {
                $data = $where;
                $data['create_at'] = date('Y-m-d H:i:s');
                $result = M('galaxy_role_node')->add($data);
            } else {
                $result = true;
            }
        } else {
            //echo 'mmbw';
            $result = M('galaxy_role_node')->where($where)->delete();
        }
        if ($result) {
            $this->echoJson(['status' => 1, 'msg' => '权限分配成功']);
        } else {
            $this->echoJson(['status' => 0, 'msg' => '权限分配失败']);
        }
    }

    /**
     * 给admin角色绑定用户
     * @author ypm
     * @time 2017-02-21
     */
    public function addRoleUser()
    {
        $param = I('param.', '');
        if (empty($param['user_id'])) {
            $this->echoJson(['status' => 0, 'msg' => '请选择用户']);
        }

        $info = D('galaxy_role_user')->where(['role_id' => $param['role_id'], 'user_id' => $param['user_id']])->find();
        if (empty($info)) {
            D('galaxy_role_user')->where(['role_id' => $param['role_id']])->delete();
            $roleUser = array(
                'user_id' => $param['user_id'],
                'role_id' => $param['role_id'],
                'create_time' => date('Y-m-d H:i:s')
            );
            $result = D('galaxy_role_user')->add($roleUser);
            if ($result) {
                $this->echoJson(['status' => 1, 'msg' => '权限分配成功']);
            } else {
                $this->echoJson(['status' => 0, 'msg' => '权限分配失败']);
            }
        } else {
            $this->echoJson(['status' => 1, 'msg' => '权限分配成功']);
        }
    }

    /**
     * 添加集团公司
     * @author xiaowen
     * @time 2016-12-16
     */
    public function addCompany()
    {
        if (IS_AJAX) {

            $param = I('param.', '');
            $data = $this->getEvent('Galaxy')->addCompany($param);
            $this->echoJson($data);

        }

        //返回所有未绑定公司的老板
        $data['boss'] = $this->getEvent('Galaxy')->getBossList(2);

        //-------------查询省市区三级数据----------------------------------------------------------------
        $cityLevelData = cityLevelData();
        $data['city1'] = $cityLevelData['city1'];
        $data['city2'] = $cityLevelData['city2'];
        $data['city3'] = $cityLevelData['city3'];
        //-------------查询省市区三级数据----------------------------------------------------------------

        //-------------将城市数据转换成json供页面js联动使用----------------------------------------------
        $data['city1json'] = json_encode($data['city1']);
        $data['city2json'] = json_encode($data['city2']);
        $data['city3json'] = json_encode($data['city3']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 添加人员
     * @author xiaowen
     * @time 2016-12-16
     */
    public function addUser()
    {
        if (IS_AJAX) {
            $param = I('param.', '');
            $data = $this->getEvent('Galaxy')->addUser($param);
            $this->echoJson($data);
        }
        $where['u.user_type'] = 2;
        $where['u.is_available'] = 0;
        $where['u.role'] = 1;

        $boss = $this->getEvent('Galaxy')->userList($where, $field = true);
        $data['boss_list'] = $boss['data'];
        $data['role_list'] = userRole();

        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑人员
     * @author xiaowen
     * @time 2016-12-16
     */
    public function editUser()
    {
        $param = I('param.', '');
        if (IS_AJAX) {

            $data = $this->getEvent('Galaxy')->editUser($param);
            $this->echoJson($data);

        }
        $data['info'] = $this->getEvent('Galaxy')->getOneUserInfo($param['id']);

        $where['u.user_type'] = 2;
        $where['u.is_available'] = 0;
        $where['u.role'] = 1;

        $boss = $this->getEvent('Galaxy')->userList($where, $field = true);
        $data['boss_list'] = $boss['data'];
        $data['role_list'] = userRole();

        $userid = $data['info']['pid'];
        $data['company_clients'] = $this->getEvent('Galaxy')->getUserClients($userid);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 集团用户员工列表
     * @author xiaowen
     * @time 2016-12-16
     */
    public function userList()
    {
        if (IS_AJAX) {
            $where = [];

            $where['u.user_type'] = 2;
            $where['u.is_available'] = 0;
            //$where['u.is_available'] = 0;

            $param = I('get.');
            if (trim($param['user_phone'])) {
                $where['u.user_phone'] = strHtml(trim($param['user_phone']));

            }
            if (trim($param['user_name'])) {
                $where['u.user_name'] = strHtml(trim($param['user_name']));
            }

            $data = $this->getEvent('Galaxy')->userList($where, $field = true);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 查询手机号对应的用户
     * @author xiaowen
     * @time 2016-12-16
     */
    public function ajaxSelectUser()
    {

        if (IS_AJAX) {
            $param['user_phone'] = trim(I('param.user_phone', ''));

            if (!$param['user_phone']) {
                $data['status'] = 0;
                $data['message'] = '手机号不能为空';
                $this->echoJson($data);

            }
            $data = $this->getEvent('Galaxy')->getBossUser($param['user_phone']);

            $this->echoJson($data);

        }

    }

    /**
     * 集团车辆列表
     * @author xiaowen
     * @time 2016-12-16
     */
    public function carList()
    {


        if (IS_AJAX) {
            $company_id = intval(I('param.company_id', 0));
            $param['car_number'] = trim(I('get.car_number', ''));
            //$param['region'] = intval(I('get.region', 0));
            $param['galaxy_clients_id'] = intval(I('get.galaxy_clients_id', 0));

            $data = $this->getEvent('Galaxy')->carList($company_id, $param);

            $this->echoJson($data);
        }

        $id = intval(I('param.id', 0));
        $data['clients_list'] = $this->getEvent('Galaxy')->getGalaxyClients($id);

        $data['region_list'] = provinceCityZone()['city'];
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 商品油价列表
     * @author xiaowen
     * @time 2016-12-16
     */
    public function goodsList()
    {

        if (IS_AJAX) {

            $param = $_GET;
            //print_r($_REQUEST);
            $data = $this->getEvent('Galaxy')->goodsList($param);
            $this->echoJson($data);
        }

        $data['region_list'] = provinceCityZone()['city'];
        $data['oilType'] = oilType();
        $data['oilLevel'] = oilLevel();
        $data['oilRank'] = oilRank();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 添加商品油价
     * @author xiaowen
     * @time 2016-12-16
     */
    public function addGoods()
    {

        if (IS_AJAX) {

            $param = I('param.');

            $data = $this->getEvent('Galaxy')->addGoods($param);

            $this->echoJson($data);
        }

        $where['c.type'] = 2;
        $where['c.is_available'] = 0;
        $where['u.role'] = 1;
        $where['u.is_available'] = 0;
        $where['u.user_type'] = 2;
        $where['u.pid'] = 0;
        $company = $this->getEvent('Galaxy')->companyList($where);
        $data['company_list'] = $company['data'];

        $data['region_list'] = provinceCityZone()['city'];
        $data['oilType'] = oilType();
        $data['oilLevel'] = oilLevel();
        $data['oilRank'] = oilRank();
        $data['oilSource'] = oilSource();
        //print_r($data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑商品油价
     * @author xiaowen
     * @time 2016-12-16
     */
    public function editGoods()
    {

        $id = intval(I('param.id', 0));
        if (IS_AJAX) {

//            $new_data = [
//                'price'=>floatval(I('param.price')),
//                'end_time'=>strHtml(I('param.end_time')),
//            ];
            $param = I('param.');
            $data = $this->getEvent('Galaxy')->editGoods($id, $param);

            $this->echoJson($data);
        }

        $param['id'] = $id;
        $data['info'] = $this->getEvent('Galaxy')->getOneGoods($param);
        $data['info']['start_time'] = date('Y-m-d', strtotime($data['info']['start_time']));
        $data['info']['end_time'] = date('Y-m-d', strtotime($data['info']['end_time']));

        $where['c.type'] = 2;
        $where['c.is_available'] = 0;
        $where['u.role'] = 1;
        $where['u.is_available'] = 0;
        $where['u.user_type'] = 2;
        $where['u.pid'] = 0;
        $company = $this->getEvent('Galaxy')->companyList($where);
        $data['company_list'] = $company['data'];

        $data['region_list'] = provinceCityZone()['city'];
        $data['oilType'] = oilType();
        $data['oilLevel'] = oilLevel();
        $data['oilRank'] = oilRank();
        $data['oilSource'] = oilSource();

        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 集团订单列表
     * @author xiaowen
     * @time 2016-12-16
     */
    public function orderList()
    {

        if (IS_AJAX) {

            //$param = I('param.');
            $param = I('get.');
            $where = [];
            if (trim($param['order_number'])) {
                $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
            }
            if (trim($param['user_company_id'])) {
                $where['o.user_company_id'] = trim($param['user_company_id']);
            }
            if (trim($param['region'])) {
                $where['o.region'] = trim($param['region']);
            }
            if (trim($param['rank'])) {
                $where['goods.rank'] = strHtml($param['rank']);
            }
            if (trim($param['sale_company'])) {
                $where['o.sale_company_id'] = strHtml($param['sale_company']);
            }
            if (isset($param['t_start_time']) && strHtml($param['t_start_time']) != 'null' && !empty(strHtml($param['t_start_time']))) {
                $t_start_time = true;
            }
            if (isset($param['t_end_time']) && strHtml($param['t_end_time']) != 'null' && !empty(strHtml($param['t_end_time']))) {
                $t_end_time = true;
            }
            if ($t_start_time && !$t_end_time) {
                $where['o.trans_true_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00'))];
            } elseif (!$t_start_time && $t_end_time) {
                $where['o.trans_true_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))];
            } elseif ($t_start_time && $t_end_time) {
                $where['o.trans_true_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))]];
            }
            $where['o.status'] = ['in', [20, 100]];
            $data = $this->getEvent('Galaxy')->orderList($where);
            $this->echoJson($data);
        }

        $where['c.type'] = 2;
        $where['c.is_available'] = 0;
        $where['u.role'] = 1;
        $where['u.is_available'] = 0;
        $where['u.user_type'] = 2;
        $where['u.pid'] = 0;
        $company = $this->getEvent('Galaxy')->companyList($where);
        $data['company_list'] = $company['data'];
        //$data['region_list'] = cityLevelData()['city2'];
        $data['region_list'] = provinceCityZone()['city'];
        $data['oilType'] = oilType();
        $data['oilLevel'] = oilLevel();
        $data['oilRank'] = oilRank();
        $data['oilSource'] = oilSource();
        $company_list = M('facilitator')->where(['status' => 1])->select();
        foreach ($company_list as $k => $v) {
            $data['sale_company_list'][$v['facilitator_id']] = $v['name'];
        }
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 审核商品油价
     * @author xiaowen
     * @time 2016-12-16
     */
    public function auditGoods()
    {
        $id = intval(I('param.id', 0));
        $status = intval(I('param.status', 0));
        if ($id && $status) {

            if (!in_array($status, [1, 20])) {
                $result['status'] = 0;
                $result['message'] = '状态有误';
                $this->echoJson($result);
            }
            $data = $this->getEvent('Galaxy')->auditGoods($id, $status);
            $this->echoJson($data);
        } else {
            $result['status'] = 0;
            $result['message'] = '参数有误';
            $this->echoJson($result);
        }

    }

    /**
     * 审核订单
     */
    public function auditOrder()
    {
        if (IS_AJAX) {
            $id = intval(I('param.id', 0));
            $status = intval(I('param.status', 0));
            if ($id && $status) {

                if (!in_array($status, [1, 10])) {
                    $result['status'] = 0;
                    $result['message'] = '状态有误';
                    $this->echoJson($result);
                }
                $zhaoyou_remark = trim(I('param.zhaoyou_remark', ''));

                if ($status == 1 && $zhaoyou_remark == '') {
                    $result['status'] = 2;
                    $result['message'] = '请输入审核不通过备注';
                    $this->echoJson($result);
                }
                $data = $this->getEvent('Galaxy')->auditOrder($id, $status, $zhaoyou_remark);
                $this->echoJson($data);
            } else {
                $result['status'] = 0;
                $result['message'] = '参数有误';
                $this->echoJson($result);
            }
        }

    }

    /**
     * 订单审核不通过界面
     */
    public function nexamineOrder()
    {

        $id = intval(I('param.id', 0));
        $status = intval(I('param.status', 0));
        if (IS_AJAX) {
            if ($id && $status) {

                if (!in_array($status, [1, 10])) {
                    $result['status'] = 0;
                    $result['message'] = '状态有误';
                    $this->echoJson($result);
                }
                $data = $this->getEvent('Galaxy')->auditOrder($id, $status);
                $this->echoJson($data);
            } else {
                $result['status'] = 0;
                $result['message'] = '参数有误';
                $this->echoJson($result);
            }
        }

        $data['id'] = $id;
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 订单详情
     * @author xiaowen
     * @time 2016-12-19
     */
    public function detailOrder()
    {

        $id = intval(I('param.id', 0));
        $data = [];
        if ($id) {
            $data['detail'] = $this->getEvent('Galaxy')->detailOrder($id);
        }
        //print_r($data['detail']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 集团用户菜单节点列表
     */
    public function nodeList()
    {

        if (IS_AJAX) {
            $where = ['is_show' => 1];
            $data['data'] = D('GalaxyNode')->where($where)->order('id desc')->select();
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 添加集团用户菜单节点
     */
    public function addNode()
    {

        if (IS_AJAX) {
            $data = I('param.');
            $this->checkInfo($data);
            $data['create_at'] = DateTime();
            $status = D('GalaxyNode')->add($data);
            if ($status) {
                $this->echoJson(['status' => 1, 'msg' => '权限添加成功']);
            } else {
                $this->echoJson(['status' => 0, 'msg' => '权限添加失败']);
            }
        }
        $menuData = D('GalaxyNode')->where(['is_show' => 1])->select();
        $menuArray = GetTree($menuData);
        $data = [];
        $id = I('param.id', 0, 'int');
        if ($id) {
            $data = D('GalaxyNode')->find($id);
        }
        unset($menuData);
        $this->assign('data', $data);
        $this->assign('menu', $menuArray);
        $this->display();
    }

    /**
     * 编辑集团用户菜单节点
     */
    public function editNode()
    {

        $data = I('param.');
        $this->checkInfo($data, 0);
        $data['update_at'] = DateTime();
        $status = D('GalaxyNode')->save($data);
        if ($status) {
            $this->echoJson(['status' => 1, 'msg' => '权限编辑成功']);
        } else {
            $this->echoJson(['status' => 0, 'msg' => '权限编辑失败']);
        }
    }

    /**
     * 删除节点
     */
    public function delNode()
    {
        $id = I('param.id');

        if ($id) {
            $status = D('GalaxyNode')->where(['id' => $id])->delete();
            if ($status) {
                $this->echoJson(['status' => 1, 'msg' => '权限删除成功']);
            } else {
                $this->echoJson(['status' => 0, 'msg' => '权限删除失败']);
            }
        }
        $this->echoJson(['status' => 0, 'msg' => '参数有误']);
    }

    /**
     * 检验参数是否有误
     * @param $data
     * @param int $is_add
     */
    protected function checkInfo($data, $is_add = 1)
    {
        if (!$is_add) {
            if (!trim($data['id'])) {
                $this->echoJson(['status' => 0, 'msg' => '参数有误，ID无法获取']);
            }
        }
        if (!trim($data['node_name'])) {
            $this->echoJson(['status' => 2, 'msg' => '权限名称不能为空']);
        }
        if (!trim($data['node_code'])) {
            $this->echoJson(['status' => 3, 'msg' => '权限码不能为空']);
        }

    }

    /**
     * 商品审核不通过界面
     */
    public function nexamineGoods()
    {

        $id = intval(I('param.id', 0));
        $status = intval(I('param.status', 0));
        if (IS_AJAX) {
            if ($id && $status) {

                if (!in_array($status, [1, 20])) {
                    $result['status'] = 0;
                    $result['message'] = '状态有误';
                    $this->echoJson($result);
                }
                $remark = trim(I('param.nexamine_remark', ''));
                $data = $this->getEvent('Galaxy')->auditGoods($id, $status, $remark);
                $this->echoJson($data);
            } else {
                $result['status'] = 0;
                $result['message'] = '参数有误';
                $this->echoJson($result);
            }
        }

        $data['id'] = $id;
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 录入订单
     * @author xiaowen
     * @time 2016-12-27
     */
    public function addOrder()
    {

        $data = I('param.');

        if (IS_AJAX) {
            $data = $this->getEvent('Galaxy')->addOrder($data);
            $this->echoJson($data);
        }

        $data['company'] = getCompanyNames();
        $data['company'] = getCompanyNames();
        $data['company'] = getCompanyNames();
        $data['company'] = getCompanyNames();
        $data['company'] = getCompanyNames();
        $this->assign('data', $data);
        $this->display();
    }

    public function ajaxGetCompanyUserByCar()
    {

        $car_number = strHtml(I('param.car_number', 0));
        if (IS_AJAX) {
            $data = $this->getEvent('Galaxy')->getCompanyUserByCar($car_number);
            $this->echoJson($data);
        }
    }

    /**
     * 异步获取商品
     * @author xiaowen
     * @time 2016-12-28
     */
    public function ajaxFindGoods()
    {
        if (IS_AJAX) {
            $param = I('param.');

            $data = $this->getEvent('Galaxy')->findGoods($param);
            $this->echoJson($data);
        }

    }

    /**
     * 图片上传处理方法：配合webuploader上传插件
     * @author xiaowen
     * @time 2016-10-20
     */
    public function uploadFile()
    {

        /**
         * webuploader 上传插件，后台文件上传处理方法
         */
        #!! 注意
        #!! 此文件只是个示例，不要用于真正的产品之中。
        #!! 不保证代码安全性。
        // Make sure file is not cached (as it happens for example on iOS devices)
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        // Support CORS
        // header("Access-Control-Allow-Origin: *");
        // other CORS headers if any...
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit; // finish preflight CORS requests here
        }

        if (!empty($_REQUEST['debug'])) {
            $random = rand(0, intval($_REQUEST['debug']));
            if ($random === 0) {
                header("HTTP/1.0 500 Internal Server Error");
                exit;
            }
        }

        // 5 minutes execution time
        @set_time_limit(5 * 60);

        // Get a file name
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }
        //切分图片名与图片类型
        $file_info = explode('.', $fileName);
        $type = $file_info[1];
        //前端定义的上传图片的业务类型： clients_cts 公司证件
        $upload_type = $_REQUEST['upload_type'];
        if ($upload_type == 'screenshot') {

            //$upload_dir = "./Uploads/Clients/front/";
            $upload_dir = "./Public/Uploads/Front/Order/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $current_date = date('Y-m-d');
            if (!is_dir($upload_dir . $current_date)) {
                mkdir($upload_dir . $current_date, 0777, true);
            }

        }
        $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
        if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
            $this->echoJson(['status' => 2, 'error' => '上传文件有误']);
        }
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
            $this->echoJson(['status' => 3, 'error' => '上传失败']);
        } else {
            $this->echoJson(['status' => 1, 'error' => '', 'file_url' => $file_name, 'file_type' => $file_info[0]]);
        }
    }

    /**
     * 面单上传
     * @author xiaowen
     * @time 2017-1-10
     */
    public function uploadScreenShot()
    {
        $id = intval(I('param.id', 0));
        if (IS_AJAX) {
            $param['screenshot_path'] = strHtml(I('param.screenshot_path', ''));
            $data = $this->getEvent('Galaxy')->saveScreenShot($id, $param);
            $this->echoJson($data);

        } else {

            $data['detail'] = $this->getEvent('Galaxy')->detailOrder($id);

            $this->assign('data', $data);
            $this->display();
        }
    }

    /**
     * Excel导出
     * @author lizhipeng
     * @time 2017-02-04
     */
    public function export()
    {
        $param = I('get.');
        $where = [];
        if (isset($param['order_number']) && strHtml($param['order_number']) != 'null' && !empty(strHtml($param['order_number']))) {
            $where['o.order_number'] = ['like', '%' . strHtml($param['order_number']) . '%'];
        }
        if (isset($param['user_company_id']) && strHtml($param['user_company_id']) != 'null' && !empty(strHtml($param['user_company_id']))) {
            $where['o.user_company_id'] = strHtml($param['user_company_id']);
        }
        if (isset($param['search_region']) && strHtml($param['search_region']) != 'null' && !empty(strHtml($param['search_region']))) {
            $where['o.region'] = strHtml($param['search_region']);
        }
        if (isset($param['search_company']) && strHtml($param['search_company']) != 'null' && !empty(strHtml($param['search_company']))) {
            $where['o.sale_company_id'] = strHtml($param['search_company']);
        }
        if (isset($param['rank']) && strHtml($param['rank']) != 'null' && !empty(strHtml($param['rank']))) {
            $where['goods.rank'] = strHtml($param['rank']);
        }
        if (isset($param['t_start_time']) && strHtml($param['t_start_time']) != 'null' && !empty(strHtml($param['t_start_time']))) {
            $t_start_time = true;
        }
        if (isset($param['t_end_time']) && strHtml($param['t_end_time']) != 'null' && !empty(strHtml($param['t_end_time']))) {
            $t_end_time = true;
        }
        if ($t_start_time && !$t_end_time) {
            $where['o.trans_true_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00'))];
        } elseif (!$t_start_time && $t_end_time) {
            $where['o.trans_true_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))];
        } elseif ($t_start_time && $t_end_time) {
            $where['o.trans_true_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))]];
        }
        $where['o.status'] = ['in', [20, 100]];
        $data = $this->getEvent('Galaxy')->orderList($where);
        $arr = [];
        $zhaoyou = zhaoyou_status();
        $party = party_status();
        $status = status();
        foreach ($data['data'] as $k => $v) {
            $arr[$k]['order_number'] = "\t".$v['order_number'];
            $arr[$k]['company_name'] = $v['company_name'];
            $arr[$k]['galaxy_clients_name'] = $v['galaxy_clients_name'];
            $arr[$k]['area_name'] = $v['area_name'];
            $arr[$k]['car_number'] = $v['car_number'];
            $arr[$k]['goods_type'] = $v['type'] . $v['level'] . $v['rank'];
            $arr[$k]['num'] = $v['num'];
            $arr[$k]['from'] = $v['from'];
            $arr[$k]['price'] = $v['price'];
            $arr[$k]['actual_price'] = $v['actual_price'];
            $arr[$k]['payable_price'] = $v['payable_price'];
            $arr[$k]['payment_price'] = $v['payment_price'];
            $arr[$k]['trans_true_time_date'] = substr($v['trans_true_time'], 0, 10);
            $arr[$k]['trans_true_time_hour'] = substr($v['trans_true_time'], 11);
            $arr[$k]['status'] = $status[$v['status']];
            $arr[$k]['zhaoyou_status'] = $zhaoyou[$v['zhaoyou_status']];
            $arr[$k]['party_status'] = $party[$v['party_status']];
        }

        $header = ['订单号', '公司名称', '分公司名称', '区域', '车牌号', '用油类型', '加油数量(升)', '订单来源', '单价(元)', '应付金额(元)', '结算单价(元)', '结算金额(元)', '加油日期', '加油时间', '订单状态', '找油审核状态', '客户审核状态'];
        array_unshift($arr, $header);
        create_xls($arr, $filename = '集团订单.xls');
    }

    /**
     * 集团订单对账列表
     * @author xiaowen
     * @time 2017-2-14
     */
    public function orderFinanceList()
    {

        if (IS_AJAX) {

            $param = I('get.');
            $where = [];
            $where['o.zhaoyou_status'] = 10;
            $where['o.party_status'] = 10;
            if (isset($param['pay_status']) && trim($param['pay_status']) >= 0) {
                $where['o.pay_status'] = intval($param['pay_status']);
            }
            if (trim($param['order_number'])) {
                $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
            }
            if (trim($param['user_company_id'])) {
                $where['o.user_company_id'] = trim($param['user_company_id']);
            }
            if (trim($param['region'])) {
                $where['o.region'] = trim($param['region']);
            }
            if (trim($param['rank'])) {
                $where['goods.rank'] = strHtml($param['rank']);
            }
            if (trim($param['sale_company'])) {
                $where['o.sale_company_id'] = strHtml($param['sale_company']);
            }
            if (isset($param['t_start_time']) && strHtml($param['t_start_time']) != 'null' && !empty(strHtml($param['t_start_time']))) {
                $t_start_time = true;
            }
            if (isset($param['t_end_time']) && strHtml($param['t_end_time']) != 'null' && !empty(strHtml($param['t_end_time']))) {
                $t_end_time = true;
            }
            if ($t_start_time && !$t_end_time) {
                $where['o.trans_true_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00'))];
            } elseif (!$t_start_time && $t_end_time) {
                $where['o.trans_true_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))];
            } elseif ($t_start_time && $t_end_time) {
                $where['o.trans_true_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))]];
            }
            $where['o.status'] = ['in', [20, 100]];
            //print_r($where);
            $data = $this->getEvent('Galaxy')->orderList($where);
            $this->echoJson($data);
        }

        $where['c.type'] = 2;
        $where['c.is_available'] = 0;
        $where['u.role'] = 1;
        $where['u.is_available'] = 0;
        $where['u.user_type'] = 2;
        $where['u.pid'] = 0;
        $company = $this->getEvent('Galaxy')->companyList($where);
        $data['company_list'] = $company['data'];
        //$data['region_list'] = cityLevelData()['city2'];
        $data['region_list'] = provinceCityZone()['city'];
        $data['oilType'] = oilType();
        $data['oilLevel'] = oilLevel();
        $data['oilRank'] = oilRank();
        $data['oilSource'] = oilSource();
        $company_list = M('facilitator')->where(['status' => 1])->select();
        foreach ($company_list as $k => $v) {
            $data['sale_company_list'][$v['facilitator_id']] = $v['name'];
        }
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 批量订单对账
     */
    public function batchOrderReconciliation()
    {

        $data = [];
        if (IS_AJAX) {
            $order_id = I('post.order_id', '');
            if (empty($order_id)) {
                $this->echoJson(['status' => 0, 'message' => '订单参数有误']);
            }
            if (!is_array($order_id)) {
                $order_id = [$order_id];
            }
            if ($order_id) {
                $data = $this->getEvent('RetailFinance')->batchGalaxyReconciliation($order_id);
                $this->echoJson($data);
            }
        }
        $this->assign('data', $data);
        $this->display();

    }

    /**
     * 异步获取卡片信息
     * @author qianbin
     * @time 2017-3-3
     */
    public function ajaxGetCards()
    {
        if (IS_AJAX) {
            $param = I('param.');
            $data = $this->getEvent('Galaxy')->GetCards($param);
            $this->echoJson($data);
        }

    }

    /**
     *
     */
    public function getFacilitatorUser()
    {
        $sale_company_id = intval($_POST['sale_company_id']);

        $result = getGalaxyFacilitatorUser($sale_company_id);
        $data['is_null'] = count($result) <= 0 ? 1 : 2;
        $data['data'] = $result;

        $this->echoJson($data);
    }

}
