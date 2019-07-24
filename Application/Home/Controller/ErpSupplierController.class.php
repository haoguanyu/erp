<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpSupplierController extends BaseController
{

    /*
    * +----------------------------------
    * 供应商控制器
    * @author xiaowen
    * @time 2018-9-21
    * +----------------------------------
    */

    /***********************************
        @ Content 查询 供应商名称
        @ Author Syf
        @ Time 2018-11-29
        @ Param [
            'customer_name' => 客户名称
        ]
        @ Return [
            'status'  => 状态码
            'message' => 提示语
        ]
    ************************************/
    public function findSupplierName()
    {
        $param = I('post.');
        $result = $this->getEvent('ErpSupplier')->findSupplierName($param);
        $this->echoJson($result);
    }



    /**
     * 新增供应商
     * @author xiaowen
     * @time 2018-9-21
     */
    public function addSupplier(){
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->addSupplier($param);
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
     * 编辑供应商
     * @author xiaowen
     * @time 2018-9-21
     */
    public function updateSupplier(){
        $id = intval(I('param.id', 0));
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->updateSupplier($id,$param);
            $this->echoJson($data);
        }

        $data['id'] = $id;
        $data['data'] = $this->getEvent('ErpSupplier')->getOneSupplierData(['id'=>$id]);
        $data['isInner'] = supplierInner();
        $data['level'] = supplierLevel();
        $data['businessAttributes'] = supplierBusinessAttributes();
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

    /**********************************************
        @ Content 查看 供应商
        @ Time 2018-11-19
        @ Author Syf
        @ Param [
            id => 供应商id
        ]
    ***********************************************/
    public function selectSupplier()
    {
        $id = intval(I('param.id', 0));
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->updateSupplier($id,$param);
            $this->echoJson($data);
        }

        $data['id'] = $id;
        $data['data'] = $this->getEvent('ErpSupplier')->getOneSupplierData(['id'=>$id]);
        $data['isInner'] = supplierInner();
        $data['level'] = supplierLevel();
        $data['businessAttributes'] = supplierBusinessAttributes();
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
     * 供应商列表
     * @author xiaowen
     * @time 2018-9-21
     */
    public function getSupplierList(){
        //$id = intval(I('param.id', 0));
        $param = $_REQUEST;
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->getSupplierList($param);
            $this->echoJson($data);
        }
        $data['businessAttributesArr'] = supplierBusinessAttributes();
        $data['statusArr'] = supplierStatus();
        $data['auditStatusArr'] = supplierAuditStatus();
        $access_node = $this->getUserAccessNode('ErpSupplier/getSupplierList');
        $this->assign('data', $data);
        $this->assign('access_node', json_encode($access_node));
        $this->display();
    }

    /**
     * 供应商联系人列表
     * @author xiaowen
     * @time 2018-9-21
     */
    public function getSupplierUserList(){
        $id = intval(I('param.supplier_id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->getSupplierUserList($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 新增供应商联系人
     * @author xiaowen
     * @time 2018-9-21
     */
    public function saveSupplierUser(){
        $param = I('param.', 0);
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->saveSupplierUser($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 新增供应商联系人
     * @author xiaowen
     * @time 2018-9-21
     */
    public function updateSupplierUser(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->updateSupplierUser($id);
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
            $data = $this->getEvent('ErpSupplier')->UserDetail($id);
            $this->echoJson($data);
        }
    }

    /**
     * 供应商证件列表
     * @author xiaowen
     * @time 2018-9-21
     */
    public function supplierCertificateList(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->supplierCertificateList($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 供应商审核
     * @author xiaowen
     * @time 2018-9-21
     */
    public function auditSupplier(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->auditSupplier($id);
            $this->echoJson($data);
        }
        $this->display();
    }

    /**
     * 上传供应商凭证
     * @author xiaowen
     * @time 2018-9-21
     */
    public function uploadVoucher(){
        $id = intval(I('param.id', 0));
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->uploadVoucher($id, $_FILES);
            $this->echoJson($data);
        }

        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 供应商凭证预览
     * @author xiaowen
     * @time 2018-9-21
     */
    public function voucherList(){
        $id = intval(I('param.id', 0));

        $data['data'] = $this->getEvent('ErpSupplier')->voucherList($id);
        $data['base_url'] = $this->uploads_path['supplier_attach']['url'];
        $this->assign('id', $id);
        $this->assign('data', $data);
        $this->display();
    }

   /******************************************************
        @ Content 处理 供应商数据
        @ Author SYF
        @ Time 2018-11-16
        @ Param  [
            get_supplier_arr => [
                    0 => [
                        'id'            => 供应商id
                        'supplier_name' => 供应商简称

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
                    'id'            => 供应商id
                        'supplier_name' => 供应商简称

                        .......
                ]
            ]
        ]
        ]

    *******************************************************/
    public function handleExportData($get_supplier_arr = [],$area_arr = [])
    {
        if ( empty($get_supplier_arr) || empty($area_arr) ) {
            return ['status' => 1,'message' => '缺少参数!','data' => []];
        }
        foreach ($get_supplier_arr as &$value) {
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
            $value['audit_status'] =  trim(strip_tags($audit_status));
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
        return ['status' => 0 ,'message' => '成功','data' => $get_supplier_arr];
    }

    /**
     * 导出供应商数据
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
            'ID','供应商名称','供应商简称','税号','注册银行名称','注册银行账号','注册电话','注册地址','公司电话','邮编','是否团内','省份','地级市','区县','公司详细地址','公司级别','业务属性','公司备注','创建人','创建时间','更新人','更新时间','审核人','审核时间','审核状态','数据来源','数据来源id','是否新数据','状态'
        ];
        // 获取城市
        (string)$area_field = 'id,area_name';
        $area_arr = $this->getModel('Area')->getAreaByField($area_field);

        (string)$field = '*';
        (array)$supplier_arr = [];
        // 获取总条数
        $num = $this->getModel('ErpSupplier')->count();
        $page_size = ceil($num/$param['length']);
        for ($i=1; $i <=$page_size; $i++) {
            $param['start'] = ($i - 1 ) * $param['length'];
            $get_supplier_arr = $this->getEvent('ErpSupplier')->getSupplierList($param,$field)['data'];
            // 处理供应商数据
            $get_supplier_arr_result = $this->handleExportData($get_supplier_arr,$area_arr);
            if ( $get_supplier_arr_result['status'] != 0 ) {
                // 跳出循环
                continue;
            }
            foreach ($get_supplier_arr_result['data'] as $key => $value) {
                unset($value['data_source_name']);
                $supplier_arr[] = $value;
            }
        }
        /********************* END *********************/
        $file_name_arr = '供应商' . currentTime('Ymd').'.csv';
        require_once(APP_PATH . 'Home/Lib/csvLib.php');
        $csvObj = new \csvLib($file_name_arr);
        //生成输出流头部信息
        $csvObj->printHeaderInfo();
        //生成csv文件头
        $csvObj->csvHeader($header);
        //分批生成数据
        $page = 1;
        //分批生成CSV内容
        $csvObj->exportCsv($supplier_arr);

        //关闭文件句柄
        $csvObj->closeFile();
    }

    /**
     * select2获取下拉框信息
     * @author guanyu
     * @time 2018-10-10
     */
    public function getSupplierData()
    {
        $company_name = trim(I('get.q'));
        $restrict = trim(I('get.restrict', 1));
        $where['supplier_name'] = ['like', '%' . $company_name . '%'];
        if($restrict == 1){
            $where['status'] = 1;
        }
        $where['business_attributes'] = 1;
        $field = 'id, supplier_name as company_name';
        $customer_data['data'] = $this->getEvent('ErpSupplier')->getSupplierData($where,$field);
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
     * 根据网点获取对应的服务商信息
     * @author guanyu
     * @time 2018-10-10
     */
    public function getSupplierByStorehouse()
    {
        if (IS_AJAX) {
            $facilitator_skid_id = $_POST['facilitator_skid_id'];
            $data = $this->getEvent('ErpSupplier')->getSupplierByStorehouse($facilitator_skid_id);
            $this->echoJson($data);
        }
    }

    /**
     * ajax 通过联系人手机号或姓名搜索联系人
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getSupplierUserByPhoneName(){
        $q = trim(I('get.q'));
        $field = 'id, user_name, user_phone';
        $customer_data['data'] = $this->getEvent('ErpSupplier')->getSupplierUserByPhoneName($q, $field);
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
    public function getSupplierInfoByUserId(){
        $user_id  = intval(I('param.id', 0));

        $supplier_data['c_name'] = $this->getEvent('ErpSupplier')->getSupplierInfoByUserId($user_id);

        $this->echoJson($supplier_data);
    }

    /**
     * ajax 模糊查找供应商名称
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getSupplierByName(){
        $q = trim(I('get.q'));
        $field = 'id, user_name, user_phone';
        $customer_data['data'] = $this->getEvent('ErpSupplier')->getSupplierByName($q, $field);
        /******************************************
            @ Content 供应商名称拼接来源
            @ Time 2018-11-20
            @ Author SYF
        *******************************************/
        foreach ($customer_data['data'] as &$value) {
            (string)$str = '';
            $value['data_source'] = dataSourceName( $value['data_source'] );
        }
        /******************************************
                        END
        *******************************************/
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
     * 通过供应商公司查找联系人
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getUserInfoBySupplierId(){
        $company_id = intval(I('param.company'));

        $supplier_data = $this->getEvent('ErpSupplier')->getUserInfoBySupplierId($company_id);

        $this->echoJson($supplier_data);
    }

    /**
     * ajax 获取银行帐号信息
     * @author xiaowen
     * @time 2018-10-10
     */
    public function getSupplierBankInfo(){
        $id = intval(I('param.company'));
//        $where['id'] = $id;
//        $field = 'id, registered_bank, registered_bank_number';
//        $bank_info = $this->getEvent('ErpSupplier')->getOneSupplierData($where, $field);
//        $data[] = [
//            'content' => $bank_info['registered_bank'] . '--' . $bank_info['registered_bank_number'],
//        ];
        $where=[
            "supplier_id" => $id ,
            "ext_info_type" => 6 ,
            "status" => 1
        ];
        $file = "ext_info_content as backName , ext_info_content_two as backNum";
        $bank_info = $this->getEvent('ErpSupplier')->getSupplierBackAll($where, $file);

        $this->echoJson($bank_info);
    }
    /*
     * 获取供应商的银行卡号信息
     * @author：小黑
     * @data:2018-11-20
     */
    public function getSupplierBackList(){
        $params = I("post.");
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->getSupplierBackList($params);
            $this->echoJson($data);
        }
        $this->display();
    }
    /*
     * 添加公司的银行账号
     * @author:小黑
     * 2018-11-20
     */
    public function saveSupplierBack(){
        $param = I('param.', 0);
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->saveSupplierBack($param);
            $this->echoJson($data);
        }
        $this->display();
    }
    /*
         * 添加公司的银行账号
         * @author:小黑
         * 2018-11-20
         */
    public function backDetail(){
        $param = I('param.', 0);
        if(IS_AJAX){
            $data = $this->getEvent('ErpSupplier')->backDetail($param['id']);
            $this->echoJson($data);
        }
        $this->display();
    }

}