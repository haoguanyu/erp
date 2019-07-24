<?php
/**
 * 公司管理控制器
 * @author xiaowen
 * @time 2016-10-11
 */
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ClientsController extends BaseController
{
    /**
     * 添加公司
     * @author xiaowen
     * @time 2016-10-20
     */
    public function addClients()
    {
        //--------------------提交添加公司的后台处理-----------------------------------------------------
        if (IS_AJAX) {
            $data = I('post.');
            $result = $this->getEvent('Clients')->addClients($data);
            $this->echoJson($result);
        }
        //-------------------提交添加公司后台处理--------------------------------------------------------
        //获取添加公司页面需要的数据
        $data = $this->getClientsStaticData();
        //--------------渲染模板-------------------------------------------------------------------------
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 修改公司
     * @author xiaowen
     * @time 2016-10-20
     */
    public function editClients()
    {
        $id = intval($_REQUEST['id']);

        //--------------------提交添加公司的后台处理-----------------------------------------------------
        if (IS_AJAX) {
            $data = I('post.');
            $result = $this->getEvent('Clients')->editClients($id, $data);
            $this->echoJson($result);
        }
        //-------------------提交添加公司后台处理--------------------------------------------------------

        //获取编辑公司页面需要的静态数据
        $data = $this->getClientsStaticData();
        //查询公司详情数据
        $data['detail'] = $this->getEvent('Clients')->detailClients($id, false);

        //--------------渲染模板-------------------------------------------------------------------------
        //print_r($data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 修改公司(全部权限)
     * @author xiaowen
     * @time 2016-10-20
     */
    public function editAllClients()
    {
        $id = intval($_REQUEST['id']);

        //--------------------提交添加公司的后台处理-----------------------------------------------------
        if (IS_AJAX) {
            $data = I('post.');
            $result = $this->getEvent('Clients')->editClients($id, $data, 1);
            $this->echoJson($result);
        }
        //-------------------提交添加公司后台处理--------------------------------------------------------

        //获取编辑公司页面需要的静态数据
        $data = $this->getClientsStaticData();
        //查询公司详情数据
        $data['detail'] = $this->getEvent('Clients')->detailClients($id, false);

        //--------------渲染模板-------------------------------------------------------------------------
        //print_r($data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 我的公司列表
     * @author xiaowen
     * @time 2016-10-20
     */
    public function myClientsList()
    {
        $statusArr = ClientStatus();
        $source_from_Arr = ClientSourceFrom();
        if (IS_AJAX) {
            $params = $_REQUEST;
            //查找当前交易员自己的管理的公司列表
            $params['dealer_name'] = session('adminInfo')['dealer_name'];
            //$params['dealer_name'] =  '王伟';
            $data = $this->getEvent('Clients')->getClientsList($params);
            $this->echoJson($data);
        }
        $this->assign('status', $statusArr);
        $this->assign('source_from', $source_from_Arr);
        $this->display();
    }

    /**
     * 全部公司列表
     * @author xiaowen
     * @time 2016-10-20
     */
    public function allClientsList()
    {
        $statusArr = ClientStatus();
        $source_from_Arr = ClientSourceFrom();
        if (IS_AJAX) {
            $data = $this->getEvent('Clients')->getClientsList($_REQUEST);

            $this->echoJson($data);
        }
        $this->assign('status', $statusArr);
        $this->assign('source_from', $source_from_Arr);
        $this->display();
    }

    /**
     * 公司详情
     * @author xiaowen
     * @time 2016-10-20
     */
    public function detailClients()
    {
        $id = intval(I('param.id'));
        $data['detail'] = $this->getEvent('Clients')->detailClients($id);
        $need_check_types = ClientsCtsType(1);
        $data['detail']['cts_list'] = [];
        if ($data['detail']['cts']) {
            foreach ($data['detail']['cts'] as $key => $val) {
                //只取待审核、审核通过的图片显示
                if (in_array($val['check_status'], [0, 1])) {
                    //unset($data['detail']['cts'][$key]);
                    $data['detail']['cts_list'][] = $val;
                }
            }
        }
        //------------------------------省市区ID转成对应名称---------------------------------------------------------
        //$city = CityToPro();
        $city = provinceCityZone();
        $data['detail']['clients']['region_province'] = $data['detail']['clients']['region_province'] ? $city['province'][$data['detail']['clients']['region_province']] : '';
        $data['detail']['clients']['region'] = $data['detail']['clients']['region'] ? $city['city'][$data['detail']['clients']['region']] : '';
        $data['detail']['clients']['region_sub'] = $data['detail']['clients']['region_sub'] ? $city['zone'][$data['detail']['clients']['region_sub']] : '';
        //------------------------------------------------------------------------------------------------------------
        $data['branchCity'] = array_flip(branchCity()); //分公司
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 审核公司三证
     * @author xiaowen
     * @time 2016-10-20
     */
    public function auditClients()
    {
        $id = intval(I('param.id', 0));
        $is_pass = intval(I('param.is_pass', 0));
        if (IS_AJAX) {
            if (!$id) {

                $this->echoJson(['status' => 2, 'message' => '公司ID无法获取，参数有误']);
            }
            if (!$is_pass) {
                $this->echoJson(['status' => 2, 'message' => '审核操作参数有误']);
            }
            $result = $this->getEvent('Clients')->auditClients($id, $is_pass);
            $this->echoJson($result);
        }

        $data['detail'] = $this->getEvent('Clients')->detailClients($id);
        $need_check_types = ClientsCtsType(1);
        $data['detail']['cts_list'] = [];
        if ($data['detail']['cts']) {
            foreach ($data['detail']['cts'] as $key => $val) {
                //只取类型是三证的图片并且待审核的图片显示
                if (in_array($val['cts_type'], $need_check_types) && $val['check_status'] == 0) {
                    //unset($data['detail']['cts'][$key]);
                    $data['detail']['cts_list'][] = $val;
                }
            }
        }
        //------------------------------省市区ID转成对应名称---------------------------------------------------------
        $city = provinceCityZone();
        $data['detail']['clients']['region_province'] = $data['detail']['clients']['region_province'] ? $city['province'][$data['detail']['clients']['region_province']] : '';
        $data['detail']['clients']['region'] = $data['detail']['clients']['region'] ? $city['city'][$data['detail']['clients']['region']] : '';
        $data['detail']['clients']['region_sub'] = $data['detail']['clients']['region_sub'] ? $city['zone'][$data['detail']['clients']['region_sub']] : '';
        //--------------------------------------------------------------------------------------------------------
        $data['branchCity'] = array_flip(branchCity()); //分公司
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 审核公司名称
     * @author xiaowen
     * @time 2016-10-20
     */
    public function auditClientsName()
    {
        $id = intval(I('param.id', 0));
        $is_pass = intval(I('param.is_pass', 0));
        if (IS_AJAX) {
            if (!$id) {

                $this->echoJson(['status' => 2, 'message' => '公司ID无法获取，参数有误']);
            }
            if (!$is_pass) {
                $this->echoJson(['status' => 2, 'message' => '审核操作参数有误']);
            }
            $result = $this->getEvent('Clients')->auditClientsName($id, $is_pass);
            $this->echoJson($result);
        }

    }

    /**
     * 上传三证
     * @author xiaowen
     * @time 2016-10-20
     */
    public function uploadCts()
    {
        $id = intval($_REQUEST['id']);
        if (IS_AJAX) {
            $data = $this->getEvent('Clients')->uploadCts(intval($id));
            $this->echoJson($data);
        }
        $data['detail'] = $this->getEvent('Clients')->detailClients($id, false);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 根据用户手机号查询用户信息（用于录入公司时，关联公司联系人且用户必须是自己名下）
     * @author xiaowen
     * @time 2016-10-20
     */
    public function ajaxSelectUser()
    {
        $user_phone = strHtml($_REQUEST['user_phone']);
        $my_user = strHtml($_REQUEST['my_user']) ? strHtml($_REQUEST['my_user']) : 0;
        $data = $this->getEvent('Clients')->checkUser($user_phone, $my_user);
        $this->echoJson($data);
    }

    /**
     * 公司扩展信息
     * @author xiaowen
     * @time 2016-10-20
     */
    public function clientsInfo()
    {
        $id = intval($_REQUEST['id']);
        if (IS_AJAX) {

            $type = strHtml(I('param.type'));
            $bank_name = strHtml(I('param.bank_name'));
            $bank_num = strHtml(I('param.bank_num'));
            $data['type'] = $type;
            if ($type == 'bank') {
                if ($bank_name && $bank_num) {
                    $data['content'] = $bank_name . '--' . $bank_num;
                } else {
                    $result['status'] = 0;
                    $result['message'] = '帐号名称与帐号不能为空';
                    $this->echoJson($result);
                }
            } else if ($type == 'address') {
                $data['content'] = strHtml(I('param.clients_address'));
            }

            $result = $this->getEvent('Clients')->addClientsInfo($id, $data);
            $this->echoJson($result);
        }
        $data = $this->getEvent('Clients')->getClientsInfoList($id);
        $data['detail'] = $this->getEvent('Clients')->detailClients($id, false);
        # '-' 代替 空值
        # qianbin
        # 2017.7.17
        if(!empty($data['detail']['clients'])){
            foreach ($data['detail']['clients'] as $k => $v){
                $data['detail']['clients'][$k] = empty(trim($v)) ? '-' : $v;
            }
            if(!isset($data['detail']['crm']['short_name']))$data['detail']['crm']['short_name'] = '-';
            foreach ($data['detail']['crm'] as $k => $v){
                $data['detail']['crm'][$k] = empty(trim($v)) ? '-' : $v;
            }
        }
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 异步删除公司帐号地址信息
     * @author xiaowen
     * @time 2016-10-20
     */
    public function ajaxDelClientsInfo()
    {
        $id = intval($_REQUEST['id']);
        if (IS_AJAX) {
            $result = $this->getEvent('Clients')->delClientsInfo($id);
            $this->echoJson($result);
        }

    }

    /**
     * 查询公司
     * @author xiaowen
     * @time 2016-10-27
     */
    public function queryClientsList()
    {

        $keyword = strHtml($_REQUEST['company_name']) ? strHtml($_REQUEST['company_name']) : '';

        if (IS_AJAX) {

            $data['data'] = [];
            if ($keyword) {

                $where['company_name'] = ['like', "%$keyword%"];
                $limit = 3;
                $data = $this->getEvent('Clients')->queryClientsList($where, $limit);
            }

            $this->echoJson($data);
        }

        $this->display();
    }

    /**
     * 图片上传处理方法：配合webuploader上传插件
     * @author xiaowen
     * @time 2016-10-20
     */
    public function uploadCtsFile()
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
        if ($upload_type == 'clients_cts') {

            //$upload_dir = "./Uploads/Clients/front/";
            $upload_dir = "./Public/Uploads/Clients/Photo/";
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
     * 导出有交易未上传三证公司数据
     * @author xiaowen
     * @time 2016-10-20
     */
    public function noUploadClients()
    {
        set_time_limit(100000);


        $buyCompanyIds = $this->getModel('Clients')->query("SELECT DISTINCT id,buy_coid,dealer_name,buy_name,buy_id,region FROM oil_order WHERE is_available = 0 AND is_pass = 2 GROUP by buy_coid ORDER BY id DESC");
        //$buyCompanyId = array_column($buyCompanyId, 'buy_coid');
        //print_r($buyCompanyIds);
        $user_data = $this->getModel('User')->field('id,user_phone,dealer_name')->where(['is_available' => ['neq', 1]])->select();
        $region_data = D('area')->where(['area_type' => 2])->field('id,area_name')->select();
        foreach ($region_data as $key => $val) {
            $region[$val['id']] = $val['area_name'];
        }
        foreach ($buyCompanyIds as $key => $val) {
            $buyCompanyId[$val['buy_coid']]['dealer_name'] = $val['dealer_name'];
            $buyCompanyId[$val['buy_coid']]['buy_name'] = $val['buy_name'];
            $buyCompanyId[$val['buy_coid']]['buy_id'] = $val['buy_id'];
            $buyCompanyId[$val['buy_coid']]['order_id'] = $val['id'];
            $buyCompanyId[$val['buy_coid']]['region'] = $val['region'];
        }
        foreach ($user_data as $key => $val) {
            $users[$val['id']]['dealer_name'] = $val['dealer_name'];
            $users[$val['id']]['user_phone'] = $val['user_phone'];
        }
        //print_r($buyCompanyId);
        //exit;
        $data = $this->getEvent('Clients')->getClientsList($_REQUEST);
        $cids = [];
        $index = 0;
        $noupload['未上传'][$index] = [
            'id' => '公司id',
            'company_name' => '公司名',
            'company_address' => '公司地址',
            'company_level' => '公司等级',
            'company_tel' => '公司电话',
            'order_id' => '交易订单id',
            'dealer_name' => '有交易业务员',
            'buy_id' => '交易用户id',
            'buy_name' => '交易用户姓名',
            'user_phone' => '交易用户手机',
            'region_name' => '地区',
            'is_identification_complete' => '上传状态',
        ];
        $index++;

        foreach ($data['data'] as $key => $val) {
            if ($val['is_identification_complete'] == '未上传' && in_array($val['id'], array_keys($buyCompanyId))) {
                $cids[] = $val['id'];
                $noupload[$val['is_identification_complete']][$index] = $val;
                $noupload[$val['is_identification_complete']][$index]['dealer_name'] = $buyCompanyId[$val['id']]['dealer_name'];
                $noupload[$val['is_identification_complete']][$index]['order_id'] = $buyCompanyId[$val['id']]['order_id'];
                $noupload[$val['is_identification_complete']][$index]['buy_id'] = $buyCompanyId[$val['id']]['buy_id'];
                $noupload[$val['is_identification_complete']][$index]['buy_name'] = $buyCompanyId[$val['id']]['buy_name'];
                $noupload[$val['is_identification_complete']][$index]['user_phone'] = $users[$buyCompanyId[$val['id']]['buy_id']]['user_phone'];
                $noupload[$val['is_identification_complete']][$index]['region_name'] = $region[$buyCompanyId[$val['id']]['region']];
                $index++;
            }
            //$noupload[$val['is_identification_complete']][] = $val;
        }
//		echo $cids = implode(',', $cids);
//		print_r($noupload);
//		exit;
        /**
         * 生成默认以逗号分隔的CSV文件
         * 解决：内容中包含逗号(,)、双引号("")
         * @author zf
         * @version 2012-11-14
         */
        header("Content-Type: application/vnd.ms-excel; charset=GB2312");
        header("Content-Disposition: attachment;filename=CSV数据.csv ");

//		$rs = array(
//			array('aa', "I'm li lei", '"boy"', '￥122,300.00'),
//			array('cc', 'I\'m han mei', '"gile"', '￥122,500.00'),
//		);
        $str = '';
        foreach ($noupload['未上传'] as $row) {
            $str_arr = array();
            $rs = [
                $this->striconv($row['id']),
                $this->striconv($row['company_name']),
                $this->striconv($row['company_address']),
                $this->striconv($row['company_level']),
                ' ' . $this->striconv($row['company_tel']),
                $this->striconv($row['order_id']),
                $this->striconv($row['dealer_name']),
                $this->striconv($row['buy_id']),
                $this->striconv($row['buy_name']),
                $this->striconv($row['user_phone']),
                $this->striconv($row['region_name']),
                $this->striconv($row['is_identification_complete']),
            ];
            foreach ($rs as $column) {
                $str_arr[] = '"' . str_replace('"', '""', $column) . '"';
            }
            $str .= implode(',', $str_arr) . PHP_EOL;

        }
        echo $str;
        exit;

    }

    /**
     * 整理公司老数据
     * @author xiaowen
     * @time 2016-10-22
     */
    public function clearData()
    {
        set_time_limit(100000);

        //所有 公司与用户从属关系数据
        $uc_data = D('Uc')->where(['is_available' => 0, 'deal_status' => 0])->order('id desc')->select();
        //所有的公司数据
        $clients = $this->getModel('Clients')->where('is_available = 0')->select();

        $ClientsCtsModel = D('ClientsCts');
        $clients_user = [];
        $clients_all_user = [];

        foreach ($uc_data as $k => $value) {
            //遍历关系表，取新一条UC关系作为公司表u_id的三证用户
            if (!$clients_user[$value['company_id']] && $value['user_id']) {
                $clients_user[$value['company_id']] = $value['user_id'];
            }
            //遍历关系表，取公司对应的所有用户
            //$clients_all_user[$value['company_id']][] = $value['user_id'];
            //$clients[$value['company_id']][$value['deal_status']][] = $value['user_id'];
        }
        $i = 0;
        //遍历公司数据
        $have_cts_coid = [];
        $no_cts_coid = [];
        //遍历公司数据
        foreach ($clients as $key => $value) {
            //查出所有该公司对应的图片类型
            $cts_types = $ClientsCtsModel->where(['cts_coid' => $value['id']])->getField('cts_type', true);
            //如果已有三证或三合一证，则是认证通过
            if ((in_array('营业执照', $cts_types) && in_array('税务登记证', $cts_types) && in_array('组织机构代码证', $cts_types)) || in_array('三合一', $cts_types)) {
                $update_clients = [
                    'status' => 1,
                    'u_id' => $clients_user[$value['id']],
                    'time' => date('Y-m-d H:i:s'),
                ];
                //更新公司认证状态及公司负责人u_id
                $this->getModel('Clients')->where(['id' => $value['id'], 'u_id' => 0])->save($update_clients);
                //更改公司的图片资料为审核通过
                $ClientsCtsModel->where(['cts_coid' => $value['id']])->save(['check_status' => 1]);
//				if($clients_all_user[$value['id']]){
//					$this->getModel('User')->where(['id' => ['in', $clients_all_user[$value['id']]], 'is_auth'=>['neq', 3]])->save(['is_auth'=>3, 'time'=>date('Y-m-d H:i:s')]);
//				}
                $i++;
                echo '已处理完' . $i . '条数据...<br/>';
                $have_cts_coid [] = $value['id'];
            } else {
                log_info('公司ID：' . $value['id'] . '，未找到三证资料');
                $no_cts_coid[] = $value['id'];
            }
        }
        log_info('所有未找到三证公司ID:(' . implode(',', $no_cts_coid) . ')');
        log_info('所有有三证公司ID:(' . implode(',', $have_cts_coid) . ')');
    }

    private function getClientsStaticData()
    {
        //-------------------页面显示处理----------------------------------------------------------------
        $data['company_size'] = companySize(); //公司规模
        $data['position'] = companyPosition(); //职位
        $data['company_level'] = companyLevel(); //职位
        $data['relation'] = relation(); //关系
        $data['industry'] = industry(); //所属行业
        $data['saleAmount'] = saleAmount(); //年销售额
        $data['branchCity'] = branchCity(); //分公司

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
        return $data;
    }

    private function striconv($colunm)
    {
        return mb_convert_encoding(trim($colunm), 'gbk', 'utf-8');
    }

    /*
     * ------------------------------------------
     * 用户管理 | 公司-用户
     * Author：jk        Time：2016-10-19
     * ------------------------------------------
     */
    public function getClientsToUser()
    {
        $param = $_REQUEST;
        if (IS_AJAX) {
            $data['data'] = [];
            if (isset($param['company_name'])) $data['data'] = getClientsToUser($param['company_name']);

            $this->echoJson($data);
        }

        $this->display();
    }

    /*
     * ------------------------------------------
     * 用户管理 | 公司-用户 | 模糊查询公司全称
     * Author：jk        Time：2016-10-20
     * ------------------------------------------
     */
    public function getCompanyName()
    {
        if (IS_AJAX) {
            $param = $_POST;

            $data = getCompanyName(strHtml($param['company_name']));
            $this->echoJson($data);
        }
    }

    /**
     * 公司提交审核
     * @author xiaowen
     * @time 2016-10-31
     */
    public function submitClients()
    {
        $id = intval($_REQUEST['id']);
        $data = $this->getEvent('Clients')->submitClients($id);
        $this->echoJson($data);

    }

    /**
     * 根据公司名称查找公司
     * @author senpai
     * @time 2017-04-05
     */
    public function getCompanyByName()
    {
        $q = strHtml(I('param.q', ''));
        $restrict = strHtml(I('param.restrict', 1));
        if ($q) {
            $data['data'] = $this->getEvent('Clients')->getCompanyByName($q,$restrict);
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
     * 根据公司获取汇款信息
     * @author senpai
     * @return json
     * @time 2017-03-14
     */
    public function getRemittance()
    {
        $company = trim(I('post.company', '', 'htmlspecialchars'));
        $all = intval(I('post.all', 0));
        $data = getCompanyInfo($company);
        //-----------------------------edit: xiaowen 2017-4-11----------------------------------------------------------
        $info = $this->getModel('Clients')->field('bank_name,bank_num,company_tel,company_address')->where(['id' => $company, 'is_available' => 0])->find();
        if ($all && trim($info['bank_name']) && trim($info['bank_num'])) {
            $bank_info['content'] = $info['bank_name'] . '--' . $info['bank_num'];
            $clientsInfoList = array_column($data, 'content');
            //如果clients表的银行帐号信息在clientsInfo表不存在，则放入到$data的最开始位置
            if (!in_array($bank_info['content'], $clientsInfoList)) {
                array_unshift($data, $bank_info);
            }
        }
        //保存公司注册电话与地址，供后面方法使用，减少再次查询 edit xiaowen

        $company_tel_address = '注册电话：'. $info['company_tel'] . ' 注册地址：'.$info['company_address'];
        S($company . '&&company_info', $company_tel_address);

        $data = unique_multidim_array($data, 'content');
        //-----------------------------end: xiaowen 2017-4-11-----------------------------------------------------------
        $this->ajaxReturn($data);
    }

    /**
     * 获取公司注册电话与注册地址
     * @author xiaowen
     * @return json
     * @time 2017-03-14
     */
    public function getCompanyTelAddress()
    {
        $company = trim(I('post.company', '', 'htmlspecialchars'));
        $data['company_info'] = S($company . '&&company_info') ? S($company . '&&company_info') : '暂无注册信息';
        //-----------------------------end: xiaowen 2017-4-11-----------------------------------------------------------
        $this->ajaxReturn($data);
    }

    /**
     * 根据地区获取公司
     * @author senpei
     * @time 2017-06-11
     */
    public function getCompanyByRegion()
    {
        $region = intval($_REQUEST['region']);
        $data = $this->getEvent('Clients')->getCompanyByRegion($region);
        $this->echoJson($data);

    }

    /**
     * 导入库存公司分类数据
     * @author guanyu
     * @time 2017-11-06
     */
    public function importCompanyTransactionType(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './company_transaction_type.xls';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './company_transaction_type.xls';
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
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
            //print_r($data);

            $this->getEvent('Clients')->importCompanyTransactionType($data);
        }
    }

    /**
     * 导入公司分级数据
     * @author guanyu
     * @time 2017-11-06
     */
    public function importCompanyLevel(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
        die('已无用，不要来调戏我^___^');
        $filePath1 = './company_level.xls';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './company_level.xlsx';
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
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
            //print_r($data);

            $this->getEvent('Clients')->importCompanyLevel($data);
        }
    }
}
