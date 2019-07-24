<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpCustomerController extends BaseController
{

    /*
    * +----------------------------------
    * 客户控制器
    * @author xiaowen
    * @time 2018-9-21
    * +----------------------------------
    */

    /***********************************
        @ Content 查询 客户名称
        @ Author Syf
        @ Time 2018-11-29
        @ Param [
            'supplier_name' => 供应商名称
        ]
        @ Return [
            'status'  => 状态码
            'message' => 提示语
        ]
    ************************************/
    public function findCustomerName()
    {
        $param = I('post.');
        $result = $this->getEvent('ErpCustomer')->findCustomerName($param);
        $this->echoJson($result);
    }

    /**
     * 新增客户
     * @author xiaowen
     * @time 2018-9-21
     */
    public function addCustomer(){
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->addCustomer($param);
            $this->echoJson($data);
        }

        $data['isInner'] = supplierInner();
        $data['level'] = supplierLevel();
        $data['businessAttributes'] = supplierBusinessAttributes();
        $data['province_list'] = provinceCityZone()['province'];
        $data['city']        = json_encode(cityLevelData()['city2']);
        $data['county']      = json_encode(cityLevelData()['city3']);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑客户
     * @author xiaowen
     * @time 2018-9-21
     */
    public function updateCustomer(){
        $id = intval(I('param.id', 0));
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->updateCustomer($id,$param);
            $this->echoJson($data);
        }

        $data['id'] = $id;
        $data['data'] = $this->getEvent('ErpCustomer')->getOneCustomerData(['id'=>$id]);
        $data['isInner'] = supplierInner();
        $data['level'] = supplierLevel();
        $data['province_list'] = provinceCityZone()['province'];
        $data['city']        = json_encode(cityLevelData()['city2']);
        $data['county']      = json_encode(cityLevelData()['city3']);
        $data['businessAttributesArr'] = supplierBusinessAttributes();
        $data['statusArr'] = supplierStatus();
        $data['auditStatusArr'] = supplierAuditStatus();
        $this->assign('data', $data);
        $this->assign('data', $data);
        $this->display();
    }

    /*************************************
        @ Content 查看客户
        @ Time 2018-11-19
        @ Author SYF
    **************************************/
    public function selectCustomer(){
        $id = intval(I('param.id', 0));
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->updateCustomer($id,$param);
            $this->echoJson($data);
        }

        $data['id'] = $id;
        $data['data'] = $this->getEvent('ErpCustomer')->getOneCustomerData(['id'=>$id]);
        $data['isInner'] = supplierInner();
        $data['level'] = supplierLevel();
        $data['province_list'] = provinceCityZone()['province'];
        $data['city']        = json_encode(cityLevelData()['city2']);
        $data['county']      = json_encode(cityLevelData()['city3']);
        $data['businessAttributesArr'] = supplierBusinessAttributes();
        $data['statusArr'] = supplierStatus();
        $data['auditStatusArr'] = supplierAuditStatus();
        $this->assign('data', $data);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 客户列表
     * @author xiaowen
     * @time 2018-9-21
     */
    public function getCustomerList(){
        //$id = intval(I('param.id', 0));
        $param = $_REQUEST;
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->getCustomerList($param);
            $this->echoJson($data);
        }
        $data['businessAttributesArr'] = supplierBusinessAttributes();
        $data['statusArr'] = supplierStatus();
        $data['auditStatusArr'] = supplierAuditStatus();
        $access_node = $this->getUserAccessNode('ErpCustomer/getCustomerList');
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 客户联系人列表
     * @author xiaowen
     * @time 2018-9-21
     */
    public function getCustomerUserList(){
        $id = intval(I('param.customer_id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->getCustomerUserList($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 新增客户联系人
     * @author xiaowen
     * @time 2018-9-21
     */
    public function saveCustomerUser(){
        $param = I('param.', 0);
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->saveCustomerUser($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 新增客户联系人
     * @author xiaowen
     * @time 2018-9-21
     */
    public function updateCustomerUser(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->updateCustomerUser($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 异步获取用户信息
     */
    public function UserDetail(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->UserDetail($id);
            $this->echoJson($data);
        }
    }

    /**
     * 客户证件列表
     * @author xiaowen
     * @time 2018-9-21
     */
    public function customerCertificateList(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->customerCertificateList($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 客户审核
     * @author xiaowen
     * @time 2018-9-21
     */
    public function auditCustomer(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->auditCustomer($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 上传客户凭证
     * @author xiaowen
     * @time 2018-9-21
     */
    public function uploadVoucher(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpCustomer')->uploadVoucher($id, $_FILES);
            $this->echoJson($data);
        }

        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 客户凭证预览
     * @author xiaowen
     * @time 2018-9-21
     */
    public function voucherList(){
        $id = intval(I('param.id', 0));

        $data['data'] = $this->getEvent('ErpCustomer')->voucherList($id);
        $data['base_url'] = $this->uploads_path['customer_attach']['url'];
        $this->assign('id', $id);
        $this->assign('data', $data);
        $this->display();
    }

     /******************************************************
        @ Content 处理 客户数据
        @ Author SYF
        @ Time 2018-11-16
        @ Param  [
            get_customer_arr => [
                    0 => [
                        'id'            => 客户id
                        'customer_name' => 客户简称

                        .......
                    ]
            ]
            area_arr => [
                    1(主键id) => 中国(地区)
            ]
        @ Return [
            'code' => 状态码
            'msg'  => 提示信息
            'data' => [
                    0 => [
                        'id'            => 客户id
                        'customer_name' => 客户简称

                            .......
                    ]
                ]
            ]
        ]

    *******************************************************/
    public function handleExportData($get_customer_arr = [],$area_arr = [])
    {
        if ( empty($get_customer_arr) || empty($area_arr) ) {
            return ['status' => 1,'message' => '缺少参数!','data' => []];
        }
        foreach ($get_customer_arr as &$value) {
            // 城市
            $value['address_province'] = isset($area_arr[$value['address_province']]) ? $area_arr[$value['address_province']] : '-';
            $value['address_city'] = isset($area_arr[$value['address_city']]) ? $area_arr[$value['address_city']] : '-';
            $value['address_zone'] = isset($area_arr[$value['address_zone']]) ? $area_arr[$value['address_zone']] : '-';
            // 是否是团内
            (int)$is_inner = $value['is_inner'];
            $value['is_inner'] = $is_inner == 1 ? '是' : '否';
          
            // 业务属性
            (string)$business_attributes = $value['business_attributes'];
            $value['business_attributes'] = trim(strip_tags($business_attributes));
            // 审核状态
            (string)$audit_status = $value['audit_status'];
            $value['audit_status'] = trim(strip_tags($audit_status));
            // 数据来源
            $value['data_source'] = dataSourceName($value['data_source']);

            // 是否新数据
            (int)$is_new_data = $value['is_new_data'];
            $value['is_new_data'] = $is_new_data == 1 ? '是' : '否';
            // 是否有效
            (string)$status = $value['status'];
            $value['status'] = trim(strip_tags($status));
            /********* 为空数据 已 ‘-’ 展示*************/
            foreach ($value as &$v) {
                if ( $v == '' ) {
                    $v = '-';
                }
                $v = "\t".$v;
            }
            /***************** end *******************/
        }
        return ['status' => 0 ,'message' => '成功','data' => $get_customer_arr];
    }

   /**
     * 导出客户数据
     * @author xiaowen
     * @time 2018-9-28
     */
    public function exportData(){
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $param = I('param.');
        $param['start'] = 0;
        $param['length'] = 4000;


        /********************************************
            @ Author syf 改造
            @ Time 2018-11-16  Start
        *********************************************/
        // 生成菜单头
        (array)$header = [
            'ID','客户名称','客户简称','税号','注册银行名称','注册银行账号','注册电话','注册地址','公司电话','邮编','是否团内','省份','地级市','区县','公司详细地址','公司级别','业务属性','公司备注','创建人','创建时间','更新人','更新时间','审核人','审核时间','审核状态','数据来源','数据来源id','是否新数据','状态'
        ];
        // 获取城市
        (string)$area_field = 'id,area_name';
        $area_arr = $this->getModel('Area')->getAreaByField($area_field);

        (string)$field = '*';
        (array)$customer_arr = [];
        // 获取总条数
        $num = $this->getModel('ErpCustomer')->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_customer_arr = $this->getEvent('ErpCustomer')->getCustomerList($param,$field)['data'];
            // 处理供应商数据
            $get_customer_arr_result = $this->handleExportData($get_customer_arr,$area_arr);
            if ( $get_customer_arr_result['status'] != 0 ) {
                // 跳出循环
                continue;
            }
            foreach ($get_customer_arr_result['data'] as $key => $value) {
                unset($value['data_source_name']);
                $customer_arr[] = $value;
            }
        }
        /********************* END *********************/
        $file_name_arr = '客户' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        //分批生成CSV内容
        $csvObj->exportCsv($customer_arr);
        //关闭文件句柄
        $csvObj->closeFile();
    }

    /**
     * select2获取下拉框信息
     * @author guanyu
     * @time 2018-10-10
     */
    public function getCustomerData()
    {
        $company_name = trim(I('get.q'));
        $restrict = trim(I('get.restrict', 1));
        $type = trim(I('get.type', 1));
        $where['customer_name'] = ['like', '%' . $company_name . '%'];
        if($restrict == 1){
            $where['status'] = 1;
        }
        $where['business_attributes'] = $type;
        $field = 'id, customer_name as company_name';
        $customer_data['data'] = $this->getEvent('ErpCustomer')->getCustomerData($where,$field);
        if ($restrict == 2) {
            $limit_data = array('id'=>'99999','company_name'=>'不限');
            array_unshift($customer_data['data'],$limit_data);
        }
        if (count($customer_data['data'])) {
            $customer_data['incomplete_results'] = true;
            $customer_data['total_count'] = count($customer_data['data']);
        } else {
            $customer_data['incomplete_results'] = true;
            $customer_data['total_count'] = 0;
        }
        $this->echoJson($customer_data);
    }

    /**
     * ajax 通过联系人手机号或姓名搜索联系人
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getCustomerUserByPhoneName(){
        $q = trim(I('get.q'));
        $field = 'id, user_name, user_phone';
        $customer_data['data'] = $this->getEvent('ErpCustomer')->getCustomerUserByPhoneName($q, $field);
        if (count($customer_data['data'])) {
            $customer_data['incomplete_results'] = true;
            $customer_data['total_count'] = count($customer_data['data']);
        } else {
            $customer_data['incomplete_results'] = true;
            $customer_data['total_count'] = 0;
        }
        $this->echoJson($customer_data);
    }

    /**
     * 通过下拉联系人选择公司
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getCustomerInfoByUserId(){
        $user_id  = intval(I('param.id', 0));

        $customer_data['c_name'] = $this->getEvent('ErpCustomer')->getCustomerInfoByUserId($user_id);

        $this->echoJson($customer_data);
    }

    /**
     * 模糊查找客户名称
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getCustomerByName(){
        $q = trim(I('get.q'));
        $field = 'id, user_name, user_phone';
        $customer_data['data'] = $this->getEvent('ErpCustomer')->getCustomerByName($q, $field);
        if (count($customer_data['data'])) {
            $customer_data['incomplete_results'] = true;
            $customer_data['total_count'] = count($customer_data['data']);
        } else {
            $customer_data['incomplete_results'] = true;
            $customer_data['total_count'] = 0;
        }
        $this->echoJson($customer_data);
    }

    /**
     * 通过联系人查找客户公司
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getUserInfoByCustomerId(){
        $company_id = intval(I('param.company'));

        $customer_data = $this->getEvent('ErpCustomer')->getUserInfoByCustomerId($company_id);

        $this->echoJson($customer_data);
    }

    /**
     * ajax 获取银行帐号信息
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getCustomerBankInfo(){
        $id = intval(I('param.company'));
        $where['id'] = $id;
        $field = 'id, registered_bank, registered_bank_number';
        $bank_info = $this->getEvent('ErpCustomer')->getOneCustomerData($where, $field);
        $data[] = [
            'content' => $bank_info['registered_bank'] . '--' . $bank_info['registered_bank_number'],
        ];
        $this->echoJson($data);
    }

    /**
     * ajax 获取银行帐号信息
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getCustomerTelAddress(){
        $id = intval(I('param.company'));
        $where['id'] = $id;
        $field = 'id, registered_tel, registered_address';
        $bank_info = $this->getEvent('ErpCustomer')->getOneCustomerData($where, $field);
        $data[] = [
            'content' => '注册电话：' . $bank_info['registered_tel'] . ' 注册地址：' . $bank_info['registered_address'],
        ];
        $this->echoJson($data);
    }
}