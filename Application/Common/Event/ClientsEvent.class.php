<?php
/**
 * 公司管理控制器
 * @author xiaowen
 * @time 2016-10-11
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ClientsEvent extends BaseController
{

    public $clientsModel, $clientsCtsModel, $ucModel, $userModel, $corporationModel, $clientsInfoModel, $orderModel, $retailOrderModel, $kefu;

    public function _initialize()
    {
        $this->kefu = ['seya@51zhaoyou.com', 'sufei@51zhaoyou.com'];
    }

    /**
     *  添加公司
     * @param array $data
     * @author xiaowen
     * @time 2016-10-20
     * @return mixed
     */
    public function addClients($data = [])
    {
        $result['status'] = 0;
        if ($data) {
            //----------------验证添加公司时的输入参数-------------------------
            $checkParamsResult = $this->checkClientsParams($data);
            if ($checkParamsResult['status'] != 1) {
                $result = $checkParamsResult;
                return $result;
            }
            //----------------验证参数结束--------------------------------------

            //验证手机号对应用户是否存在，是否属于自己名下
            $checkData = $this->checkUser(strHtml($data['user_phone']));
            if ($checkData['status'] != 1) {
                $result['status'] = $checkData['status'];
                $result['message'] = $checkData['message'];
                return $result;
            }
            $data['u_id'] = $checkData['user']['id'];
            $data['dealer_name'] = $checkData['user']['dealer_name'];

            $result['status'] = $this->saveClients(0, $data) ? 1 : 0;
            $result['message'] = $result['status'] ? '添加成功' : '添加失败';
        }
        return $result;
    }

    /**
     * 修改公司
     * @param $clients_id
     * @param array $data
     * @param int $is_all_update
     * @author xiaowen
     * @time 2016-10-20
     * @return mixed
     */
    public function editClients($clients_id, $data = [], $is_all_update = 0)
    {
        $result['status'] = 0;
        if ($data) {
            //----------------验证添加公司时的输入参数-------------------------
            $checkParamsResult = $this->checkClientsParams($data, 0);
            if ($checkParamsResult['status'] != 1) {
                $result = $checkParamsResult;
                return $result;
            }
            //----------------验证参数结束--------------------------------------
            //查询修改之前的公司信息
            $clients_info = $this->getModel('Clients')->where(['is_available' => 0, 'id' => $clients_id])->find();
            //查询相同公司名称存在的其他公司
            $have_other = $this->getModel('Clients')->where(['is_available' => 0, 'id' => ['neq', $clients_id], 'company_name' => $data['company_name']])->find();
            if ($have_other) {
                //如果公司名称未修改，存在同名其他公司，属于数据异常
                if ($clients_info['company_name'] == $data['company_name']) {
                    $result['status'] = 8;
                    $result['message'] = '对不起，该数据存在异常，请联系技术部！';
                    $title = '【[数据异常]公司名称重复】';
                    $content = '公司：【' . $clients_info['company_name'] . '】,id:' . $clients_info['id'] . '，正在修改公司，存在重名异常情况，请及时处理！';
                    $email = 'ewonee@51zhaoyou.com';
                    sendEmail($title, $content, $email);
                    return $result;
                } else {  //如果修改了，验证不能跟其他公司同名
                    $result['status'] = 9;
                    $result['message'] = '对不起，该公司名称已存在！';
                    return $result;
                }

            }

            //---------------验证该公司是否存在数据库中-------------------------

            if (!$clients_info) {
                $result['status'] = 7;
                $result['message'] = '对不起，该公司不存在或已删除！';
                return $result;
            }
            //全都修改权限，可以修改公司负责人ID
            if ($is_all_update == 1) {
                $userInfo = $this->getModel('User')->where(['user_phone' => $data['user_phone'], 'is_available' => 0])->find();
                $data['u_id'] = $userInfo['id'];
                $data['dealer_name'] = $userInfo['dealer_name'];
            }
            //---------------结束验证该公司是否存在数据库中---------------------

            $result['status'] = $this->saveClients($clients_id, $data, $is_all_update) ? 1 : 0;
            $result['message'] = $result['status'] ? '修改成功' : '修改失败';
        }
        return $result;
    }

    /**
     * 添加、修改公司公共验证参数合法性方法
     * @param $params
     * @param int $is_add
     * @author xiaowen
     * @time 2016-10-20
     * @return mixed
     */
    private function checkClientsParams($params, $is_add = 1)
    {
        $result['status'] = 1;
        $result['message'] = '参数验证通过';

        if ($is_add) {
            //验证手机格式
            if (!strHtml($params['company_name'])) {
                $result['status'] = 5;
                $result['message'] = '公司名称不能为空';
                return $result;
            }
            if ($this->isExistClients(['company_name' => $params['company_name']])) {
                $result['status'] = 7;
                $result['message'] = '该公司已经存在，不能再添加！';
                return $result;
            }
            //验证手机格式
            if (!isMobile(strHtml($params['user_phone']))) {
                $result['status'] = 4;
                $result['message'] = '手机号格式错误';
                return $result;
            }

        }

        //验证所属分公司
        if (!strHtml($params['branch_id'])) {
            $result['status'] = 6;
            $result['message'] = '请选择所属分公司';
            return $result;
        }
        //验证公司等级
        if (!strHtml($params['company_level'])) {
            $result['status'] = 8;
            $result['message'] = '请选择公司等级';
            return $result;
        }
        return $result;
    }

    /**
     * 添加、修改公司 公共私有方法
     * @param $clients_id
     * @param $data
     * @param $is_update_all
     * @author xiaowen
     * @time 2016-10-20
     * @return int
     */
    private function saveClients($clients_id, $data, $is_update_all = 0)
    {
        //-------------------过滤参数空格-------------------------------------
        foreach ($data as $key => $val) {
            $data[$key] = trim($val);
        }
        //-------------------过滤参数空格-------------------------------------
        $clients_data = [
            'company_name' => $data['company_name'],
            'company_level' => $data['company_level'],
            'tax_num' => $data['tax_num'],
            'company_tel' => $data['company_tel'], //注册电话
            'company_address' => $data['company_address'],
            'deal_address' => $data['deal_address'],
            'company_remark' => $data['company_remark'],
            'bank_name' => $data['bank_name'],
            'bank_num' => $data['bank_num'],
            'fax' => $data['fax'],
            'region_province' => $data['region_province'] ? $data['region_province'] : 0,
            'region' => $data['region'] ? $data['region'] : 0,
            'region_sub' => $data['region_sub'] ? $data['region_sub'] : 0,
            'add_time' => date('Y-m-d H:i:s'),
            'u_id' => $data['u_id'],
            'dealer_name' => $data['dealer_name'],
        ];

        $crm_data = [
            //'user_phone' => $data['user_phone'],
            'u_id' => $data['u_id'] ? $data['u_id'] : 0,
            'company_name' => $data['company_name'],
//			'relation' => $data['relation'],
//			'position' => $data['position'],
            'company_level' => $data['company_level'],
            'short_name' => $data['short_name'],
            'fax' => $data['fax'],
            'company_tel' => $data['crm_company_tel'], //公司电话 存CRM表
            'company_scale' => $data['company_scale'],
            'postcode' => $data['postcode'],
            'add_time' => date('Y-m-d H:i:s'),
            'branch_id' => $data['branch_id'],
            //'is_min' => $data['is_min'],
            'business_scale' => $data['business_scale'],
            'company_type' => $data['company_type'],
        ];
        if ($clients_id) {
            unset($clients_data['add_time']);
            if ($is_update_all == 0) {
                unset($clients_data['u_id']);
                unset($clients_data['dealer_name']);
            }
            //查询修改之前的公司信息
            $clients_info = $this->getOneClientsInfo($clients_id);
            //unset($clients_data['company_name']);
            $clients_data['time'] = date('Y-m-d H:i:s');

            $crm_data['company_id'] = intval($clients_id);

            $status = $cid = $this->getModel('Clients')->where(['id' => $clients_id, 'is_available' => 0])->save($clients_data);
            //-----------查看CRM公司信息是否存在（油沃客来源，crm表没有信息）---------------------------------------
            $crm_cid = D('Corporation')->where(['company_id' => $clients_id, 'is_available' => 0])->getField('company_id');
            if ($crm_cid) {  //存在CRM信息，则更新
                unset($crm_data['u_id']);
                unset($crm_data['user_phone']);
                unset($crm_data['add_time']);
                $crm_data['re_time'] = date('Y-m-d H:i:s');
                $crm_status = D('Corporation')->where(['company_id' => $clients_id, 'is_available' => 0])->save($crm_data);
            } else {    //不存在CRM信息，则插入
                $crm_status = D('Corporation')->where(['company_id' => $clients_id, 'is_available' => 0])->add($crm_data);
            }
            //--------------------------------------------------------------------------------------------------------
            //-----------------------------------如果公司名称修改了，需要刷订单表-------------------------------------

            if (strHtml($clients_data['company_name']) != '' && $clients_info['company_name'] != strHtml($clients_data['company_name'])) {

                $this->updateRetailOrderCompanyName($clients_id, strHtml($clients_data['company_name']));

                if ($clients_info['status'] >= 20 && $clients_info['status'] != 21) { //edit xiaowen 2016-11-01

                    $this->updateOrderCompanyName($clients_id, strHtml($clients_data['company_name']));
                }

            }

            //-----------------------------------如果公司名称修改了，需要刷订单表-------------------------------------

        } else {

            $status = $cid = $this->getModel('Clients')->add($clients_data);
            $crm_status = 0;
            if ($status) {
                //插入成功后，再更新下company_id字段值
                $this->getModel('Clients')->where(['id' => $cid, 'is_available' => 0])->save(['company_id' => $cid]);
                $crm_data['company_id'] = $cid;
                $crm_status = D('Corporation')->add($crm_data);
//				if($clients_data['bank_name'] && $clients_data['bank_num']){
//
//					$clientsInfo_data = [
//						'company_id'=>$cid,
//						'type'=>'bank',
//						'content'=>$clients_data['bank_name'] . '--' . $clients_data['bank_num'],
//						'time'=>date('Y-m-d H:i:s'),
//					];
//					$this->addClientsInfo($cid, $clientsInfo_data);
//				}
            }
        }
        return $status && $crm_status ? 1 : 0;
    }

    /**
     *  公司列表
     * @author xiaowen
     * @time 2016-10-20
     * @param array $params
     * @return mixed
     */
    public function getClientsList($params = [])
    {
        $statusArr = ClientStatus();
        $source_from_Arr = ClientSourceFrom();
        $where = [
            'c.is_available' => 0,
        ];
        if (trim($params['company_name']) && trim($params['company_name']) != '') {
            $where['c.company_name'] = ['like', '%' . trim($params['company_name']) . '%'];
        }
        if (trim($params['dealer_name']) && trim($params['dealer_name']) != '') {
            $where['u.dealer_name'] = trim($params['dealer_name']);
        }
        if ($params['status'] > -1) {
            $where['c.status'] = intval($params['status']);
        }
        if ($params['source_from'] > -1) {
            $where['c.source_from'] = intval($params['source_from']);
        }
        $data['data'] = $this->getModel('Clients')->getClientsList($where);

        $ids = array_column($data['data'], 'company_id');
        if (!empty($ids)) {
            $where['cts_coid'] = array('in', $ids);
        }
        $ctsWhere['cts_is_available'] = 1;
        $ctsData = $this->getModel('ClientsCts')->field('cts_coid,cts_type')->where($ctsWhere)->select();
        $ctsArr = [];
        foreach ($ctsData as $k => $v) {
            $ctsArr[$v['cts_coid']][] = $v['cts_type'];
        }

        if (!empty($data['data'])) {
            foreach ($data['data'] as $key => $value) {
                if ((in_array('营业执照', $ctsArr[$value['company_id']]) && in_array('税务登记证', $ctsArr[$value['company_id']]) && in_array('组织机构代码证', $ctsArr[$value['company_id']]))
                    || in_array('三合一', $ctsArr[$value['company_id']])
                ) {
                    $data['data'][$key]['is_identification_complete'] = '已上传';
                } else {
                    $data['data'][$key]['is_identification_complete'] = '未上传';
                }

                $data['data'][$key]['status_name'] = $statusArr[$value['status']];
                $data['data'][$key]['source_from'] = $source_from_Arr[$value['source_from']];
            }
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * 公司详情
     * @author xiaowen
     * @time 2016-10-20
     * @param int $id
     * @param bool|true $allInfo 是否返回所有信息
     * @return array
     */
    public function detailClients($id = 0, $allInfo = true)
    {
        $data = [];
        if ($id) {
            $data['clients'] = $this->getModel('Clients')->field(true)->where(['is_available' => 0, 'id' => $id])->find();


            $clients_userInfo = $this->getModel('User')->where(['is_available' => ['neq', 1], 'id' => $data['clients']['u_id']])->field('user_phone, user_name')->find();
            $data['crm'] = $this->getModel('Corporation')->field(true)->where(['is_available' => 0, 'company_id' => $id])->find();

            $data['clients']['user_phone'] = $clients_userInfo['user_phone'];
            $data['clients']['user_name'] = $clients_userInfo['user_name'];
            if ($allInfo) {
                $data['cts'] = $this->getModel('ClientsCts')->field(true)->where(['cts_is_available' => 1, 'cts_coid' => $id])->select();
                if ($data['cts']) {
                    foreach ($data['cts'] as $key => $val) {
                        $data['cts'][$key]['cts_img_url'] = '/Public/Uploads/Clients/Photo/' . $val['cts_url'];
                        //原来老后台公司图片，需要在新图片目录上创建软链接 pluto 指向原目录
//						if($val['source'] == 'pluto'){
//							$data['cts'][$key]['cts_img_url'] = '/Uploads/Clients/pluto/' . $val['cts_url'];
//						}else{
//							$data['cts'][$key]['cts_img_url'] = '/Uploads/Clients/' . $val['cts_url'];
//						}
                    }

                }
                //查询绑定在该公司下的用户，要去重
                $uc_data = $this->getModel('Uc')->distinct(true)->field('distinct user_id, deal_status')->where(['is_available' => 0, 'company_id' => $id])->group('user_id')->select();
                $data['user'] = [];
                $uc_uids = array_column($uc_data, 'user_id');
                foreach ($uc_data as $key => $val) {
                    $uc_status[$val['user_id']] = $val['deal_status'];
                }
                $deal_status_arr = ucDealStatus();
                if ($uc_uids) {
                    $data['user'] = $this->getModel('User')->field('id, user_name, user_phone,dealer_name')->where(['is_available' => 0, 'id' => ['in', $uc_uids]])->select();
                    foreach ($data['user'] as $key => $val) {
                        $data['user'][$key]['deal_status'] = $deal_status_arr[$uc_status[$val['id']]];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 审核公司三证
     * @author xiaowen
     * @time 2016-10-20
     * @param $id 公司ID
     * @param $is_pass 1 通过 ，2不通过
     * @return mixed
     */
    public function auditClients($id, $is_pass)
    {

        $data['status'] = 0;
        $data['message'] = '操作失败';
        if (!$this->isExistClients(['id' => intval($id)])) {
            $data['status'] = 2;
            $data['message'] = '该公司不存在或已删除，请重新输入后再审核';
            return $data;
        }

        $clients_info = $this->getModel('Clients')->where(['id' => $id, 'is_available' => 0])->find();
        if (empty($clients_info)) {
            $data['status'] = 2;
            $data['message'] = '该公司不存在或已删除，请重新输入后再审核';
            return $data;
        } else if ($clients_info['status'] != 30) {
            $data['status'] = 4;
            $data['message'] = '只有三证待审核状态，才能进行三证审核';
            return $data;
        } else if ($clients_info['status'] == 40) {
            $data['status'] = 1;
            $data['message'] = '三证已审核通过，无须再审核';
            return $data;
        }
        //--------------------------------更新公司审核状态----------------------------------------
        //$clients_status = $is_pass == 1 ? 1 : 3; //edit xiaowen 2016-10-31 上传三证状态改变 审核通过 status = 4 ,审核不通过 status = 2
        //$clients_status = $is_pass == 1 ? 4 : 2;
        $clients_status = $is_pass == 1 ? 40 : 21; //edit xiaowen 2016-11-01 上传三证状态改变 审核通过 status = 40 ,审核不通过 status = 21
        $clients_update_status = $this->getModel('Clients')->where(['id' => $id, 'is_available' => 0])->save(['status' => $clients_status, 'time' => date('Y-m-d H:i:s')]);

        //--------------------------------更新公司资料状态----------------------------------------
        $need_check_types = ClientsCtsType(1);
        $where = ['cts_coid' => $id, 'cts_is_available' => 1, 'check_status' => 0, 'cts_type' => ['in', $need_check_types]];
        $cts_update_status = $this->getModel('ClientsCts')->where($where)->save(['check_status' => $is_pass, 'update_time' => date('Y-m-d H:i:s')]);

        //---------------------------------审核通过，建立联系--------------------------------------

        if ($is_pass == 1) {
            //审核通过，如果来源是找油宝安卓或IOS，需要发送短信通知用户。并且要绑定公司
            if (in_array($clients_info['source_from'], [1, 2])) {

                $title = '恭喜，您的公司认证已经审核通过了';
                $code = 'Hi，亲！恭喜，您的公司认证已经审核通过了，赶快登陆找油宝APP查看吧。';
                $userInfo = $this->getModel('User')->field('id, user_phone')->where(['id' => $clients_info['u_id'], 'is_available' => 0])->find();

                sendPhone($code, $userInfo['user_phone']);
                //-----------------------------绑定公司------------------------------------------
                $uc_data = [
                    'user_id' => $clients_info['u_id'],
                    'company_id' => $id,
                    'reg_time' => date('Y-m-d H:i:s'),
                    'deal_status' => 1, //1 ： 批发 2 ： 零售
                ];
                M('Uc')->add($uc_data);
                //---------------------------end-------------------------------------------------
            }

        }

        $dealer_email = $this->getModel('Clients')->getClientsDealerEmail($id);
        $title = '公司三证审核结果通知';
        $pass_result = $is_pass == 1 ? '通过' : '不通过';
        $content = '审核结果：您好，【' . $clients_info['company_name'] . '】, 三证信息，审核' . $pass_result;
        sendEmail($title, $content, $dealer_email);
        //------------------------------------------------------------------------------------------
        $data['status'] = $clients_update_status && $cts_update_status ? 1 : 0;
        $data['message'] = $data['status'] ? '操作成功' : '操作失败';
        return $data;
    }

    /**
     * 上传三证
     * @param $id 公司ID
     * @author xiaowen
     * @time 2016-10-20
     * @return bool
     */
    public function uploadCts($id)
    {
        $result = [
            'status' => 0,
            'message' => '上传资料失败',
            //'message' => '',
        ];
        //---------------------验证公司------------------------------------------------------------------
        if (!$id) {
            $result['status'] = 4;
            $result['message'] = '无法获取公司ID';
            //$this->echoJson($result);
            return $result;
        } else {
            $clients_info = $this->getModel('Clients')->field('id, source_from, status,u_id')->where(['id' => intval($id), 'is_available' => 0])->find();
            if (empty($clients_info)) {
                $result['status'] = 5;
                $result['message'] = '对不起，公司不存在';
                return $result;
            }
        }

        $arr = ClientsCtsType();
        //	array('三合一','营业执照','税务登记证','组织机构代码证','成品油批发经营批准证书','开户许可证','开票资料','道路运输经营许可证','危险化学品经营许可证');
        //$auth_file = ['三合一','营业执照','税务登记证','组织机构代码证'];
        $imgName = I('post.imgName');
        $imgUrl = I('post.imgUrl');

        if (empty($imgUrl)) {
            $result['status'] = 2;
            $result['message'] = '无法获取到上传图片';
            return $result;
        }
        $is_upload_auth_file = 0;
        $cts_data = [];
        $clients_status = $this->getModel('Clients')->where(['id' => $id, 'is_available' => 0])->getField('status');
        $no_check_image_type = [];
        if (!empty($imgName)) {
            foreach ($imgName as $key => $val) {
                if (!in_array($val, $arr)) {
                    $result['status'] = 7;
                    $result['message'] = '图片名称不合法';
                    return $result;
                }
                //如果上传类型不在三证里面，无须审核，状态直接为通过
                $check_status = !in_array($val, [$arr[0], $arr[1], $arr[2], $arr[3]]) ? 1 : 0;

                $cts_data[$key]['cts_coid'] = $clients_info['id'];
                $cts_data[$key]['cts_type'] = $val;
                $cts_data[$key]['cts_url'] = $imgUrl[$key];
                $cts_data[$key]['cts_is_available'] = 1;
                $cts_data[$key]['check_status'] = $check_status;
                $cts_data[$key]['add_time'] = date('Y-m-d H:i:s');
                //将这次上传的其他资料放入数组中
                if (in_array($val, ClientsCtsType(2))) {
                    $no_check_image_type[] = $val;
                }

            }
            if (in_array($arr[0], $imgName) || (in_array($arr[1], $imgName) && in_array($arr[2], $imgName) && in_array($arr[3], $imgName))) {
                if ($clients_status < 20) {
                    $result['status'] = 6;
                    $result['message'] = '公司未审核通过，请先审核公司后上传三证';
                    return $result;
                }
                if ($clients_status == 30) {
                    $result['status'] = 5;
                    $result['message'] = '公司三证审核中，不能再上传三证';
                    return $result;
                }
                if ($clients_status == 40) {
                    $result['status'] = 4;
                    $result['message'] = '公司三证已审核通过，不能再上传三证';
                    return $result;
                }
                $is_upload_auth_file = 1;
            }
            //公司认证通过，属于三证的任何资料都不能再上传
            if (in_array($arr[1], $imgName) || in_array($arr[2], $imgName) || in_array($arr[3], $imgName)) {

                if ($clients_status < 20) {
                    $result['status'] = 6;
                    $result['message'] = '公司未审核通过，请先审核公司后上传三证';
                    return $result;
                }
                if ($clients_status == 30) {
                    $result['status'] = 5;
                    $result['message'] = '公司三证审核中，不能再上传三证';
                    return $result;
                }

                if ($clients_status == 40) { //edit xiaowen 2016-20-31 上传三证状态改变 已审核三证 status = 4
                    $result['status'] = 4;
                    $result['message'] = '公司三证已审核通过，不能再上传三证资料';
                    return $result;
                }
            }

        }
        //其他资料，重新上传后需要将原来对应类型的资料设置为无效
        if ($no_check_image_type) {

            $this->getModel('ClientsCts')->where(['cts_coid' => $clients_info['id'], 'cts_type' => ['in', $no_check_image_type], 'cts_is_available' => 1])->save(['check_status' => 2, 'cts_is_available' => 1, 'update_time' => date('Y-m-d H:i:s')]);

        }

        $status = $this->getModel('ClientsCts')->addAll($cts_data);
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $status ? '资料上传成功' : '资料上传失败';
        //上传了三证资料，需要更新公司状态为审核中
        if ($status && $is_upload_auth_file) {
            $clients_data = [
                'status' => 30,  //edit xiaowen 2016-11-01 上传三证状态改变 三证待审核 status = 30
                'time' => date('Y-m-d H:i:s'),
            ];
            $this->getModel('Clients')->where(['id' => intval($id), 'is_available' => 0])->save($clients_data);

            //----------上传了公司三证，状态变为审核中时，发送邮件给审核人-----------------
            $title = '公司三证信息提交审核';
            $dealer_name = $this->getModel('Clients')->getClientsDealerName($id);
            $code = '提交审核：您好，' . $dealer_name . '提交了新的公司【' . $clients_info['company_name'] . '】三证信息，请求审核，请及时处理。';
            $this->sendKefuEmail($title, $code);
            //----------------------------------------------------------------------------
        }
        return $result;
    }

    /**
     * 检查用户是否在自己名下
     * @param $user_phone string
     * @param $my_user int 是否要检查自己属于自己
     * @author xiaowen
     * @time 2016-10-20
     * @return bool
     */
    public function checkUser($user_phone, $my_user = 1)
    {
        $where = [
            'is_available' => 0,
            'user_phone' => $user_phone,
        ];
        $data['status'] = 1;
        $data['message'] = '用户信息返回正确';

        $field = 'id,user_name,user_phone,dealer_name';
        $data['user'] = $this->getModel('User')->where($where)->field($field)->find();
        if ($data['user']) {
            if ($my_user == 1) {
                $dealer_name = session('adminInfo')['dealer_name'];
                if ($data['user']['dealer_name'] != $dealer_name) {
                    $data['status'] = 3;
                    $data['message'] = '对不起，该用户不在您自己名下，请联系其所属交易员【' . $data['user']['dealer_name'] . '】';
                }
            }
        } else {
            $data['status'] = 2;
            $data['message'] = '对不起，该用户不存在或已流失';

        }
        return $data;
    }

    /**
     * 检查公司是否存在
     * @param array $params
     * @author xiaowen
     * @time 2016-10-20
     * @return bool
     */
    public function isExistClients($params = [])
    {
        $result = false;
        if ($params) {
            if (isset($params['company_name']) && strHtml($params['company_name'])) {
                $where['company_name'] = strHtml($params['company_name']);
            }
            if (isset($params['id']) && intval($params['id'])) {
                $where['id'] = intval($params['id']);
            }
            $where['is_available'] = 0;
            $id = $this->getModel('Clients')->where($where)->getField('id');
            $result = $id ? true : false;
        }
        return $result;

    }

    /**
     * 添加公司信息
     * @author xiaowen
     * @time 2016-10-20
     * @param $clients_id int 公司ID
     * @param $data array 添加的数据
     * @return mixed
     */
    public function addClientsInfo($clients_id = 0, $data = [])
    {

        if (!$clients_id) {
            $result['status'] = 2;
            $result['message'] = '公司ID无法获取';
            return $result;
        }
        if (!empty($data)) {
            if (!$data['type']) {
                $result['status'] = 3;
                $result['message'] = '信息类型不能为空';
                return $result;
            }
        } else {
            $result['status'] = 4;
            $result['message'] = '数据不能为空';
            return $result;
        }
        $data['company_id'] = $clients_id;
        $data['time'] = date('Y-m-d H:i:s');
        $status = $this->getModel('Clientsinfo')->add($data);
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '添加成功' : '添加失败';
        return $result;
    }

    /**
     * 返回公司信息列表
     * @author xiaowen
     * @time 2016-10-20
     * @param $id
     * @return mixed
     */
    public function getClientsInfoList($id)
    {
        $data = [];
        $where = [
            'company_id' => $id,
            'is_available' => 0,
        ];
        $info = $this->getModel('Clientsinfo')->where($where)->select();
        if ($info) {
            foreach ($info as $key => $val) {
                $data[$val['type']][] = $val;
            }
            if ($data) {
                foreach ($data as $k => $dd) {
                    foreach ($dd as $key => $value) {
                        if (strpos($value['content'], '--') !== false) {
                            $content_info = explode('--', $value['content']);
                            $data[$k][$key]['bank_name'] = $content_info[0];
                            $data[$k][$key]['bank_num'] = $content_info[1];
                        }
                    }

                }
            }
        }
        return $data;
    }

    /**
     * 删除公司信息
     * @author xiaowen
     * @time 2016-10-20
     * @param $info_id
     * @return mixed
     */
    public function delClientsInfo($info_id)
    {
        if (!$info_id) {
            $result['status'] = 2;
            $result['message'] = 'ID无法获取';
            return $result;
        }
        $data['is_available'] = 1;
        $data['time'] = date('Y-m-d H:i:s');

        $where = [
            'id' => $info_id,
            'is_available' => 0
        ];
        $info = $this->getModel('Clientsinfo')->where($where)->find();
        if ($info) {
            $status = $this->getModel('Clientsinfo')->where($where)->save($data);
            $result['status'] = $status ? 1 : 0;
            $result['message'] = $result['status'] ? '操作成功' : '操作失败';
        } else {
            $result['status'] = 3;
            $result['message'] = '该信息不存在或已删除';
        }

        return $result;
    }

    /**
     * 查询公司
     * @param array $where
     * @param int $limit
     * @author xiaowen
     * @time 2016-10-25
     * @return mixed
     */
    public function queryClientsList($where = [], $limit = 3)
    {
        $clientsList = [];
        if ($where) {
            $where['is_available'] = 0;
            $field = 'id,company_id,company_name,company_level, u_id, status';

            $clientsList = $this->getModel('Clients')->field($field)->where($where)->order('id desc, add_time desc')->limit($limit)->select();

            $clients_uids = array_column($clientsList, 'u_id');
            $company_ids = array_column($clientsList, 'id');
            $userArr = [];
            if ($clients_uids) {
                $statusArr = ClientStatus();
                $users = $this->getModel('User')->field('id, user_phone, user_name, dealer_name')->where(['id' => ['in', $clients_uids], 'is_available' => ['neq', 1]])->select();

                if ($users) {
                    foreach ($users as $key => $val) {
                        $userArr[$val['id']] = $val;
                    }
                }

            }
            $company_bank = [];
            if ($company_ids) {
                $companyInfoList = $this->getModel('Clientsinfo')->field(true)->where(['company_id' => ['in', $company_ids], 'type' => 'bank', 'is_available' => 0])->select();
                if ($companyInfoList) {
                    foreach ($companyInfoList as $key => $val) {
                        $company_bank[$val['company_id']][] = $val;
                    }
                }
            }
            foreach ($clientsList as $key => $val) {

                $clientsList[$key]['dealer_name'] = isset($userArr[$val['u_id']]['dealer_name']) && $userArr[$val['u_id']]['dealer_name'] ? $userArr[$val['u_id']]['dealer_name'] : '';
                $clientsList[$key]['status'] = $statusArr[$val['status']];

                $clientsList[$key]['bank'] = '--';
                if ($company_bank[$val['id']]) {

                    $bank = [];

                    foreach ($company_bank[$val['id']] as $k => $v) {
                        $bank[] = $v['content'];
                    }
                    $clientsList[$key]['bank'] = empty($bank) ? '--' : implode('<br/>', $bank);
                }
            }
        }
        $data['data'] = $clientsList;
        return $data;
    }

    /**
     * 提交公司审核
     * @param $id
     * @author xiaowen
     * @time 2016-10-31
     * @return array
     */
    public function submitClients($id)
    {
        $result = [
            'status' => 0,
            'message' => ''
        ];
        if ($id) {
            $clients_info = $this->getOneClientsInfo($id);
            if ($clients_info) {
                $clients_data = ['status' => 10, 'time' => date('Y-m-d H:i:s')];
                $status = $this->getModel('Clients')->where(['id' => intval($clients_info['id']), 'is_available' => 0])->save($clients_data);
                $result['status'] = $status ? 1 : 0;
                $result['message'] = $status ? '提交成功' : '提交失败';
                if ($status) {

                    //-----------提交审核成功，发送邮件至客服邮件
                    $this->sendKefuEmail('公司审核申请', '有公司提交审核申请，请登陆后台及时处理谢谢！');

                    $title = '公司基本信息提交审核';
                    $dealer_name = $this->getModel('Clients')->getClientsDealerName($id);
                    $code = '提交审核：您好，' . $dealer_name . '提交了新公司【' . $clients_info['company_name'] . '】基本信息，请求审核，请及时处理。';
                    $this->sendKefuEmail($title, $code);
                    //------------------------------------------------------

                }

            } else {
                $result['message'] = '公司不存在或已删除';
            }
        } else {
            $result['message'] = '公司ID参数有误';
        }
        return $result;
    }

    /**
     * 审核公司基本信息（主要是公司名称）
     * @author xiaowen
     * @time 2016-10-31
     * @param $id 公司ID
     * @param $is_pass 1 通过 ，2不通过
     * @return $result
     */
    public function auditClientsName($id, $is_pass = 1)
    {
        $id = intval($id);
        $result = [
            'status' => 0,
            'message' => '审核操作失败',
        ];
        if ($id) {

            $clients_info = $this->getOneClientsInfo($id);

            if (empty($clients_info)) {
                $result['message'] = '公司不存在或已删除';
                return $result;
            }
            if ($clients_info['status'] != 10) {
                $result['message'] = '只有公司待审核状态才能进行审核';
                return $result;

            }
            if ($clients_info['status'] == 20) {
                $result['status'] = 1;
                $result['message'] = '公司已审核，无须重复审核';
                return $result;

            }


            $clients_data['status'] = $is_pass == 1 ? 20 : 1;
            $clients_data['time'] = date('Y-m-d H:i:s');

            $status = $this->getModel('Clients')->where(['id' => $id, 'is_available' => 0])->save($clients_data);

            $result['status'] = $status ? 1 : 0;
            $result['message'] = $status ? '操作成功' : '操作失败';

            //---------------发送邮件给交易员------------------------------------------

            $dealer_email = $this->getModel('Clients')->getClientsDealerEmail($id);
            $title = '公司基本信息审核结果通知';
            $pass_result = $is_pass == 1 ? '通过' : '不通过';
            $content = '审核结果：您好，【' . $clients_info['company_name'] . '】, 公司信息审核' . $pass_result;
            sendEmail($title, $content, $dealer_email);
            //-------------------------------------------------------------------------
        }

        return $result;
    }

    /**
     * @author xiaowen
     * @time 2016-10-31
     * @param $id 公司ID
     * @param  $field 需要返回的字段，默认全部字段
     * @return mixed
     */
    public function getOneClientsInfo($id, $field = true)
    {
        $clients_info = [];
        if ($id) {

            $clients_info = $this->getModel('Clients')->field($field)->where(['id' => intval($id), 'is_available' => 0])->find();

        }

        return $clients_info;
    }

    /**
     *修改订单表公司名称
     * @param $clients_id 公司ID
     * @param $clients_name 公司新名称
     * @author xiaowen
     * @time 201-10-31
     * @return $result
     *
     */
    public function updateOrderCompanyName($clients_id, $clients_name = '')
    {

        $where['sale_coid|buy_coid'] = $clients_id;
        $orders = $status = $this->getModel('Order')->where($where)->find();
        if ($orders) {
            $sale_data = [
                'sale_coname' => trim($clients_name),
                'time' => date('Y-m-d H:i:s')
            ];
            $buy_data = [
                'buy_coname' => trim($clients_name),
                'time' => date('Y-m-d H:i:s')
            ];
            $status = $this->getModel('Order')->where(['sale_coid' => $clients_id])->save($sale_data);
            $status = $this->getModel('Order')->where(['buy_coid' => $clients_id])->save($buy_data);
            $status = $status && $status;
        } else {
            $status = 1;
        }

        return $status;
    }

    /**
     *修改零售订单表公司名称
     * @param $clients_id 公司ID
     * @param $clients_name 公司新名称
     * @author xiaowen
     * @time 201-10-31
     * @return $result
     *
     */
    public function updateRetailOrderCompanyName($clients_id, $clients_name = '')
    {
        $where = [
            'company_id' => $clients_id,
            //'status'=>['gt', 0],
        ];
        $orders = $this->getModel('retail_order')->where($where)->find();

        if ($orders) {
            $data = [
                'company_name' => trim($clients_name),
                'update_time' => date('Y-m-d H:i:s')
            ];
            $status = $this->getModel('retail_order')->where($where)->save($data);
        } else {
            $status = 1;
        }

        return $status;
    }

    /**
     * 发送客服邮件
     * @param $title
     * @param $content
     * @author xiaowen
     * @time 201-10-31
     */
    public function sendKefuEmail($title = '公司审核申请', $content = '有公司提交审核申请，请登陆后台及时处理谢谢！')
    {

        sendEmail($title, $content, $this->kefu);


    }

    /**
     * 根据公司名称查找公司
     * @author senpai
     * @time 2017-04-05
     */
    public function getCompanyByName($q,$restrict)
    {
        if ($q) {
            $where = "(company_name like '%{$q}%') and is_available = 0 and status >= 20 and company_name <> ''";
            $data = $this->getModel('Clients')->where($where)->select();
            if ($restrict == 2) {
                $limit_data = array('id'=>'99999','company_name'=>'不限');
                array_unshift($data,$limit_data);
            }
            return $data;
        }
    }

    /**
     * 根据地区获取公司
     * @author senpei
     * @time 2017-06-11
     */
    public function getCompanyByRegion($region)
    {
        if ($region) {
            $where = ['region'=>$region];
            $data = $this->getModel('Clients')->where($where)->select();
            return $data;
        }
    }

    /**
     * 导入公司分类盘点数据
     * @param $data
     * @author xiaowen
     * @time 2017-06-01
     */
    public function importCompanyTransactionType($data){
        if($data){
            $i = 1;
            $k = 0;
            $clients_data = [];
            $status_all = true;
            M()->startTrans();
            foreach($data as $key=>$value){

                if(trim($value[0]) && trim($value[2])){
                    //----------------------组装数据------------------------------------------------------------------
                    $clients_data[$k]['transaction_type'] = $value[2];

                    $clients_update = D('Clients')->where(['company_id' => $value[0]])->save($clients_data[$k]);
                    $status_all = $status_all && $clients_update ? true :false;
                    //------------------------------------------------------------------------------------------------
                    echo '第'. $i . '条数据已处理！<br/>';
                }else{
                    echo trim($value[0]) . '&&' . trim($value[2]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（company_id,公司名称,公司类型） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
            }

            //print_r($stock_data);
            //$status = D('ErpStock')->addAll($stock_data);
            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }

    /**
     * 导入公司分级数据
     * @author guanyu
     * @time 2017-11-06
     */
    public function importCompanyLevel($data)
    {
        set_time_limit(2000);
        if($data){
            $i = 1;
            $k = 0;
            $clients_data = [];
            $status_all = true;
            M()->startTrans();
            foreach($data as $key=>$value){
                if(trim($value[0]) && trim($value[1])){
                    //验证该公司是否有混合交易
                    $is_blend = $this->checkIsBend($value[0]);

                    $clients_data['company_level'] = $is_blend && strpos($value[1],'B') === false ? 'B1' : $value[1];

                    $clients_update = D('Clients')->where(['company_name' => $value[0], 'is_available' => 0])->save($clients_data);
                    $status_all = $status_all && $clients_update !== false ? true :false;
                    if ($is_blend && strpos($value[1],'B') === false) {
                        echo '第'. $i . '条数据已处理！，校验后结果为B1<br/>';
                    } else {
                        echo '第'. $i . '条数据已处理！<br/>';
                    }
                }else{
                    echo trim($value[0]) . '&&' . trim($value[2]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（company_id,公司名称,公司类型） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
                if (fmod($k,100) == 0 && $status_all) {
                    echo '提交<br/><br/>';
                    M()->commit();
                } else if (!$status_all) {
                    echo '回滚<br/><br/>';
                    M()->rollback();
                }
            }

            if($status_all){
                echo '提交<br/><br/>';
                M()->commit();
            }else{
                echo '回滚<br/><br/>';
                M()->rollback();
            }

            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;
        }
    }

    /**
     * 验证公司是否混合交易
     * @author guanyu
     * @time 2017-11-06
     */
    public function checkIsBend($company_name)
    {
        $purchase_where = [
            'o.order_status' => 10,
            'c.company_name' => $company_name,
            'c.is_available' => 0,
        ];
        $is_purchase = $this->getModel('ErpPurchaseOrder')->alias('o')
            ->join('oil_clients c on o.sale_company_id = c.id', 'left')
            ->where($purchase_where)
            ->find();

        $sale_where = [
            'o.order_status' => 10,
            'c.company_name' => $company_name,
            'c.is_available' => 0,
        ];
        $is_sale = $this->getModel('ErpSaleOrder')->alias('o')
            ->join('oil_clients c on o.company_id = c.id', 'left')
            ->where($sale_where)
            ->find();

        $is_blend = $is_purchase && $is_sale ? true : false;
        return $is_blend;
    }


    /**
     * 验证公司的交易类型
     * @param $id 公司ID
     * @param $transaction_type 验证的交易类型 1 销售 2 采购
     * @return bool $result
     * @author xiaowen
     * @time 2018-5-23
     */
    public function checkCompanyTransactionType($id, $transaction_type){
        $result = false;
        if($id && $transaction_type){
            $transaction_type_value = $this->getModel('Clients')->where(['id'=>$id])->getField('transaction_type');
            //$transaction_type == $transaction_type_value 交易类型符合 或 $transaction_type_value == 3 （贸易：采购、销售都能做）
            $result = ($transaction_type == $transaction_type_value || $transaction_type_value == 3) ? true : false;
        }
        return $result;
    }
}
