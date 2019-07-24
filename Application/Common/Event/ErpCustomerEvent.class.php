<?php
/**
 * 客户业务处理层
 * @author xiaowen
 * @time 2019-09-21
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpCustomerEvent extends BaseController
{


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
    public function findCustomerName( $param )
    {
        if ( isset($param['supplier_id']) && !empty($param['supplier_id']) ) {
            // 获取 供应商名称
            $supplier_arr = $this->getModel('ErpSupplier')->field('supplier_name')->where(['id' => trim($param['supplier_id']),'business_attributes' => 1,'audit_status' => ['neq',1]])->find();
            if ( !isset($supplier_arr['supplier_name']) ) {
                return ['status' => 3,'message' => '暂无此客户！'];
            }
            $param['supplier_name'] = $supplier_arr['supplier_name'];
        }

        if ( !isset($param['supplier_name']) || empty($param['supplier_name']) ) {
            return ['status' => 2,'message' => '服务商名称不能为空！'];
        }
        (string)$supplier_name = trim($param['supplier_name']);

        (array)$where = [
            'customer_name'       => $supplier_name,
            'business_attributes' => 1,
            'status'              => 1,
        ];

        (string)$field = 'customer_name';
        $find_customer_result = $this->getOneCustomerData($where,$field);

        if ( !isset($find_customer_result['customer_name']) ) {
            return ['status' => 3,'message' => '暂无此客户！'];
        }

        return ['status' => 1,'message' => '请求成功！'];

    }

    /**
     * 新增客户
     * @param array $params
     * @return array
     */
    public function addCustomer($params = []){
        $data = $params;
        if(empty(trim($data['customer_name']))){
            $result = [
                'status'=>2,
                'message'=>'客户名称不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['tax_number']))){
            $result = [
                'status'=>3,
                'message'=>'税号不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['registered_bank']))){
            $result = [
                'status'=>4,
                'message'=>'注册银行名称不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['registered_bank_number']))){
            $result = [
                'status'=>5,
                'message'=>'注册银行账号不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['registered_address']))){
            $result = [
                'status'=>6,
                'message'=>'注册地址不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['is_inner']))){
            $result = [
                'status'=>7,
                'message'=>'是否团内为必选项'
            ];
            return $result;
        }
        if (empty(trim($data['address_province'])) || empty(trim($data['address_city'])) || empty(trim($data['address_zone']))){
            $result = [
                'status'=>8,
                'message'=>'公司地址省市区必选'
            ];
            return $result;
        }
        if (empty(trim($data['company_address']))){
            $result = [
                'status'=>9,
                'message'=>'公司地址不能为空'
            ];
            return $result;
        }
//        if (empty(trim($data['company_level']))){
//            $result = [
//                'status'=>10,
//                'message'=>'请选公司级别'
//            ];
//            return $result;
//        }
        if (empty(trim($data['business_attributes']))){
            $result = [
                'status'=>10,
                'message'=>'请选业务属性'
            ];
            return $result;
        }
        if (empty($data['userInfo']['user_name'])){
            $result = [
                'status'=>11,
                'message'=>'请至少输入一个联系人'
            ];
            return $result;
        }
        if (empty($data['userInfo']['user_phone'])) {
            $result = [
                'status' => 12,
                'message' => '请至少输入一个联系人电话'
            ];
            return $result;
        }
        /******************************************************
            @ Content 更换判断唯一条件 （客户名称+业务属性）
            @ Author SYF    START
            @ Time 2018-11-20
        *******************************************************/
        // erp 后台只支持 批发业务
        if ( trim($data['business_attributes']) != 1 ){
            $result = [
                'status'  => 14,
                'message' =>'业务属性 需是（批发）!',
            ];
            return $result;
        }
        (array)$where = [
            'customer_name'       => trim($data['customer_name']),
            'business_attributes' => $data['business_attributes'],
        ];
        /******************************************************
                            END
        *******************************************************/
        //验证供应商名字是否已存在
        if($this->getOneCustomerData($where)){
            return $result = [
                'status' => 13,
                'message' => '该客户已存在'
            ];
        }

        if (getCacheLock('ErpCustomer/addCustomer')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpCustomer/addCustomer', 1);

        M()->startTrans();

        $data['status'] = $data['status'] && isset($data['status']) ? intval($data['status']) : 1;
        $data['update_time'] = $data['create_time'] = DateTime();
        $data['updater'] = $data['creater'] = $this->getUserInfo('dealer_name');

        $status = $id = $this->getModel('ErpCustomer')->addCustomer($data);
        $user_data = [];
        foreach ($data['userInfo']['user_name'] as $key=>$value){
            if(trim($value) && trim($data['userInfo']['user_phone'][$key])){
                $user_data[] = [
                    'customer_id' => $id,
                    'user_name'   => trim($value),
                    'user_phone' => trim($data['userInfo']['user_phone'][$key]),
                    'create_time' => DateTime(),
                    'creater' => $this->getUserInfo('dealer_name'),
                    'updater' => $this->getUserInfo('dealer_name'),
                    'update_time' => DateTime(),
                ];
            }

        }
        $user_status = $this->getModel('ErpCustomerUser')->addAll($user_data);
        if($status && $user_status){
            M()->commit();
            $result = [
                'status'=>1,
                'message'=>'操作成功',
            ];
        }else{
            M()->rollback();
            $result = [
                'status'=>0,
                'message'=>'操作失败',
            ];
        }
        cancelCacheLock('ErpCustomer/addCustomer');


        return $result;
    }

    /**
     * 新增客户
     * @param int $id 客户ID
     * @param array $params
     * @return array
     */
    public function updateCustomer($id, $params = []){
        $data = $params;
        if(empty($id)){
            $result = [
                'status'=>2,
                'message'=>'客户ID参数错误'
            ];
            return $result;
        }
        if(empty(trim($data['customer_name']))){
            $result = [
                'status'=>2,
                'message'=>'客户名称不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['tax_number']))){
            $result = [
                'status'=>3,
                'message'=>'税号不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['registered_bank']))){
            $result = [
                'status'=>4,
                'message'=>'注册银行名称不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['registered_bank_number']))){
            $result = [
                'status'=>5,
                'message'=>'注册银行账号不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['registered_address']))){
            $result = [
                'status'=>6,
                'message'=>'注册地址不能为空'
            ];
            return $result;
        }
        if (empty(trim($data['is_inner']))){
            $result = [
                'status'=>7,
                'message'=>'是否团内为必选项'
            ];
            return $result;
        }
        if (empty(trim($data['address_province'])) || empty(trim($data['address_city'])) || empty(trim($data['address_zone']))){
            $result = [
                'status'=>8,
                'message'=>'公司地址省市区必选'
            ];
            return $result;
        }
        if (empty(trim($data['company_address']))){
            $result = [
                'status'=>9,
                'message'=>'公司地址不能为空'
            ];
            return $result;
        }
//        if (empty(trim($data['company_level']))){
//            $result = [
//                'status'=>10,
//                'message'=>'请选公司级别'
//            ];
//            return $result;
//        }
        if (empty(trim($data['business_attributes']))){
            $result = [
                'status'=>10,
                'message'=>'请选业务属性'
            ];
            return $result;
        }

        if (getCacheLock('ErpCustomer/updateCustomer')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpCustomer/updateCustomer', 1);

        M()->startTrans();

        $data['status'] = $data['status'] && isset($data['status']) ? intval($data['status']) : 1;
        $data['update_time'] = DateTime();
        $data['updater'] = $this->getUserInfo('dealer_name');

        $status = $this->getModel('ErpCustomer')->saveCustomer(['id'=>$id], $data);

        if($status){
            M()->commit();
            $result = [
                'status'=>1,
                'message'=>'操作成功',
            ];
        }else{
            M()->rollback();
            $result = [
                'status'=>0,
                'message'=>'操作失败',
            ];
        }
        cancelCacheLock('ErpCustomer/updateCustomer');


        return $result;
    }

    /**
     * 客户列表
     * @author xiaowen 2018-09-21
     * @param $param
     */
    public function getCustomerList($param = [],$field = NULL)
    {
        $where = [];
        if(trim($param['customer_name'])){
            $where['customer_name'] = ['like', '%' . trim($param['customer_name'] . '%')];
        }
        if(trim($param['business_attributes'])){
            $where['business_attributes'] = trim($param['business_attributes']);
        }
        if(trim($param['audit_status'])){
            $where['audit_status'] = trim($param['audit_status']);
        }
        if(trim($param['status'])){
            $where['status'] = trim($param['status']);
        }
        if ( $field == NULL ) {
            $field = 'o.id,o.customer_name,o.business_attributes,o.creater,o.create_time,o.updater,o.update_time,o.status, o.audit_status, o.audit_time, o.auditer ,o.data_source';
        }
        
        $data = $this->getModel('ErpCustomer')->getCustomerList($where, $field, $param['start'], $param['length']);
        //print_r($where);
        if ($data['data']) {
            //$cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                $data['data'][$key]['customer_name'] = trim($value['customer_name']);
                $data['data'][$key]['business_attributes'] = supplierBusinessAttributes($value['business_attributes'], true);
                $data['data'][$key]['status'] = supplierStatus($value['status'], true);
                $data['data'][$key]['audit_status'] = supplierAuditStatus($value['audit_status'], true);
                $data['data'][$key]['auditer'] = trim($value['auditer']) ? trim($value['auditer']) : '--';
                // 供应商来源
                $data['data'][$key]['data_source_name'] = dataSourceName($value['data_source']);
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 客户审核
     * @param $id
     * @return array
     */
    function auditCustomer($id){
        if(!$id){
            $result = [
                'status'=>2,
                'message'=>'ID参数错误',
            ];
        }else{
            $customer_info = $this->getOneCustomerData(['id'=>$id]);
            $ext_info = $this->getModel('ErpCustomerExt')->where(['customer_id'=>intval($id)])->getField('id,ext_info_type');
            log_info(print_r($ext_info, true));
            if(empty($ext_info) && $customer_info['data_source'] == 99){
                $result = [
                    'status'=>3,
                    'message'=>'未上传任何证件,无法审核',
                ];
            }else if($customer_info['audit_status'] == 1){
                $result = [
                    'status'=>5,
                    'message'=>'该客户已审核',
                ];
            }else if(array_diff(array_keys(customerVoucher()), $ext_info) && $customer_info['data_source'] == 99){
                $result = [
                    'status'=>4,
                    'message'=>'请上传必要证件',
                ];
            }else{
                if (getCacheLock('ErpCustomer/auditCustomer')) return ['status' => 99, 'message' => $this->running_msg];

                setCacheLock('ErpCustomer/auditCustomer', 1);

                M()->startTrans();
                $data = ['audit_status'=>1, 'audit_time'=>DateTime(), 'auditer'=>$this->getUserInfo('dealer_name')];
                $status = $this->getModel('ErpCustomer')->saveCustomer(['id'=>intval($id)], $data);

                if($status){
                    M()->commit();
                    $result = [
                        'status'=>1,
                        'message'=>'操作成功',
                    ];
                }else{
                    M()->rollback();
                    $result = [
                        'status'=>0,
                        'message'=>'操作失败',
                    ];
                }
                cancelCacheLock('ErpCustomer/auditCustomer');
            }
        }

        return $result;
    }

    /**
     * 上传凭证
     * @param  $id 客户ID
     * @param  array $param 凭证附件
     * @return array
     * @author xiaowen
     * @time 2018-9-27
     */
    public function uploadVoucher($id, $param = [])
    {
        log_info(print_r($param, true));
        //参数验证
        if (!$id || empty($param)) {
            return [
                'status' => 0,
                'message' => '参数有误或未上传任何证件'
            ];
            return $result;
        }

        $customer_info = $this->getModel('ErpCustomer')->findCustomer(['id'=>$id]);

        if (!isset($param['voucher_type_1']) && $customer_info['voucher_type_1'] == '') {
            $result = [
                'status' => 2,
                'message' => '三证合一必传'
            ];
            return $result;
        }

        if (!isset($param['voucher_type_2']) && $customer_info['voucher_type_5'] == '') {
            $result = [
                'status' => 2,
                'message' => '开票资料必传'
            ];
            return $result;
        }

        //验证文件大小
        foreach ($param as $key => $value) {
            if ($value) {

                if($value['size'] > 2*1024*1024) {
                    $result = [
                        'status' => 4,
                        'message' => '文件过大，不能上传大于2M的文件'
                    ];
                    return $result;
                }
                if($value['type'] != "image/jpeg" && $value['type'] != 'image/gif' && $value['type'] != 'image/png') {
                    $result = [
                        'status' => 5,
                        'message' => '文件格式上传有误，只能上传图片文件'
                    ];
                    return $result;
                }
            } else {
                continue;
            }
        }

        if (getCacheLock('ErpCustomer/uploadVoucher')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpCustomer/uploadVoucher', 1);

        $upload_status_all = true;
        $data = [];
        $delete_photo = [];
        $error_photo = [];
        //print_r($param);
        log_info(print_r($param, true));
        //exit;
        foreach ($param as $key => $value) {
            $uploaded_file = $value['tmp_name'];
            $user_path = $this->uploads_path['customer_attach']['src'];
            //判断该用户文件夹是否已经有这个文件夹
            if (!file_exists($user_path)) {
                mkdir($user_path, 0777, true);
            }
            $current_date = date('Y-m-d');
            if (!is_dir($user_path . $current_date)) {
                mkdir($user_path . $current_date, 0777, true);
            }
            $current_date = date('Y-m-d');
            //后缀
            $type = substr($value['name'],strripos($value['name'],'.')+1);
            $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
            $upload_status = move_uploaded_file($uploaded_file, $user_path . $file_name);
            $upload_status_all = $upload_status && $upload_status_all ? true : false;

            //已上传文件，如果操作失败要删除
            array_push($error_photo,$file_name);

            $data[$key] = $file_name;

            //将原图覆盖，且删除原图片
            if ($customer_info[$key]) {
                array_push($delete_photo,$key);
            }
        }
        log_info(print_r($data, true));
        if(empty($data)){
            cancelCacheLock('ErpCustomer/uploadVoucher');
            return $result = [
                'status' => 4,
                'message' => '图片资源上传失败',
            ];
        }
        M()->startTrans();
        $data_update_status = true;
        $ext_info = $this->getModel('ErpCustomerExt')->where(['customer_id'=>$id])->getField('id, ext_info_type');
        //$dealer_id = $this->getUserInfo('id');
        if($ext_info){
            $row_status = $this->getModel('ErpCustomerExt')->where(['customer_id'=>$id])->save(['status'=>2, 'update_time'=>DateTime()]);
        }else{
            $row_status = 1;
        }
        $dealer_name = $this->getUserInfo('dealer_name');
        foreach($data as $key=>$value){
            $ext_info_type = explode('_', $key)[2];
//            print_r($ext_info);
//            echo $ext_info_type . '==>' . array_search($ext_info_type,$ext_info);
//            exit;

            $row[] = [
                'customer_id' => $id,
                'ext_info_type' => $ext_info_type,
                'ext_info_content' => trim($value),
                //'create_time' => DateTime(),
                'update_time' => DateTime(),
                'updater' => $dealer_name,
                'create_time'=> DateTime(),
                'creater'=> $dealer_name,
            ];
//            if(isset($ext_info[$ext_info_type]) && $ext_info[$ext_info_type]){
//                //$row_status = $this->getModel('ErpCustomerExt')->where(['customer_id'=>$id])->save($row);
//                $row_status = $this->getModel('ErpCustomerExt')->where(['customer_id'=>$id,'id'=>array_search($ext_info_type,$ext_info)])->save($row);
//            }else{
//                $row['create_time'] = DateTime();
//                $row['creater'] = $dealer_name;
//                $row_status = $this->getModel('ErpCustomerExt')->add($row);
//            }
//            $data_update_status = $row_status && $data_update_status;

        }
        $add_status = $this->getModel('ErpCustomerExt')->addAll($row);
        if ($row_status && $add_status) {
            //操作成功后删除原文件
            foreach ($delete_photo as $value) {
                unlink($user_path.$customer_info[$value]);
            }
            M()->commit();
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        } else {
            //操作失败后删除文件
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            M()->rollback();
            $result = [
                'status' => 2,
                'message' => '操作失败',
            ];
        }
        cancelCacheLock('ErpCustomer/uploadVoucher');
        return $result;
    }

    /**
     * 证件预览
     * @param $id 客户ID
     * @return array|mixed
     */
    function voucherList($id){
        $result = [];
        if($id){
            $result = $this->getModel('ErpCustomerExt')->where(['customer_id'=>$id, 'status'=>1])->getField('ext_info_type, ext_info_content');
            foreach ($result as &$v){
                if(strpos($v ,"http://") === false){
//                    $v = substr($v,strripos($v,'/')+1);
                    $v = $this->uploads_path['customer_attach']['url'] . $v;
                }
            }
        }

        return $result;
    }

    /**
     * 联系人列表
     * @param $id
     * @return array|mixed
     */
    function getCustomerUserList($id){
        $data = [];
        if($id){
            //$data = $this->getModel('ErpCustomerUser')->where(['customer_id'=>$id])->select();
            $where['customer_id'] = intval($id);
            $data = $this->getModel('ErpCustomerUser')->getCustomerUserList($where);
            if ($data['data']) {
                //$cityArr = provinceCityZone()['city'];
                foreach ($data['data'] as $key => $value) {
                    $data['data'][$key]['customer_name'] = trim($value['customer_name']);
                    $data['data'][$key]['business_attributes'] = supplierBusinessAttributes($value['business_attributes'], true);
                    $data['data'][$key]['status'] = supplierStatus($value['status'], true);
                    $data['data'][$key]['audit_status'] = supplierAuditStatus($value['audit_status'], true);

                }
            } else {
                $data['data'] = [];
            }

            $data['recordsFiltered'] = $data['recordsTotal'];
            $data['draw'] = $_REQUEST['draw'];
        }

        return $data;
    }

    /**
     * 单条联系人信息
     * @param $id
     * @param string $field
     * @return array
     */
    function UserDetail($id, $field = ''){
        $data = [];

        if($id){
            $data = $this->getModel('ErpCustomerUser')->findCustomerUser(['id'=>intval($id)]);
            if($field){
                return $data[$field];
            }
        }
        return $data;
    }

    /**
     * 编辑、添加联系人
     * @param $param
     * @return array
     */
    function saveCustomerUser($param){
        $data = $param;
        $data['user_name'] = trim($param['user_name']);
        $data['user_phone'] = trim($param['user_phone']);
        $data['update_time'] = DateTime();
        $data['updater'] = $this->getUserInfo('dealer_name');
        if(empty(trim($data['user_name']))){
            $result = [
                'status' => 2,
                'message' => '联系人不能为空',
            ];
            return $result;
        }
        if(empty(trim($data['user_phone']))){
            $result = [
                'status' => 3,
                'message' => '联系人电话不能为空',
            ];
            return $result;
        }
        $model = $this->getModel('ErpCustomerUser') ;
        $where = [
            "user_name" => $data['user_name'] ,
            'user_phone' => $data['user_phone'],
            "customer_id" => $data['customer_id']
        ] ;
        $userCount = $model->getCustomerUserCount($where);
        if($userCount > 0){
            $result = [
                'status' => 0,
                'message' => '用户已经存在，请确认！',
            ];
            return $result ;
        }
        if($param['id'] > 0){
            $status = $model->saveCustomerUser(['id'=>$data['id']], $data);
        }else{

            $data['create_time'] = DateTime();
            $data['creater'] = $this->getUserInfo('dealer_name');
            $status = $model->addCustomerUser($data);
        }
        if($status){
            $result = [
                'status' => 1,
                'message' => '操作成功',
            ];
        }else{
            $result = [
                'status' => 0,
                'message' => '操作失败',
            ];
        }
        return $result;
    }

    /**
     * 获取多个客户数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    function getCustomerData($where = [], $field = true){
        return $this->getModel('ErpCustomer')->field($field)->where($where)->select();
    }

    /**
     * 获取多个客户数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    function getCustomerDataField($where = [], $field = true){
        $data = $this->getModel('ErpCustomer')->where($where)->getField($field);
        //return !empty($data) ? $data : [];
        return $data;
    }

    /**
     * 获取多个客户联系人数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    function getCustomerUserDataField($where = [], $field = true){
        $data = $this->getModel('ErpCustomerUser')->where($where)->getField($field);
        return !empty($data) ? $data : [];
    }

    /**
     * 获取一条客户数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    function getOneCustomerData($where = [], $field = true){
        return $this->getModel('ErpCustomer')->field($field)->where($where)->find();
    }

    /**
     * 模糊查找姓名或手机搜索联系人
     * @param $q
     * @param $field
     * @return mixed
     */
    function getCustomerUserByPhoneName($q, $field = true){

        $where = "(user_phone like '%{$q}%' or user_name like '%{$q}%') and user_name <> '' and status = 1";

        $where_company = [
            'business_attributes' => 1,
            'status' => 1,
        ];
        $company_id_arr = $this->getCustomerData($where_company, 'id');
        $company_id_arr = array_column($company_id_arr, 'id');
        if($company_id_arr){
            $where = $where . " and customer_id in (" . implode(',', $company_id_arr) . ")";
            //echo $where;
        }else{
            return [];
        }

        $data = $this->getModel('ErpCustomerUser')->field($field)->where($where)->order('id desc')->select();
        return $data;
    }

    /**
     * 通过联系人查找客户公司
     * @param $user_id
     * @return array|mixed
     */
    function getCustomerInfoByUserId($user_id){
        $data = [];
        if($user_id){
            $customer_id = $this->getModel('ErpCustomerUser')->where(['id'=>$user_id])->getField('customer_id');
            $field = 'id, customer_name as company_name';
            $where['id'] = $customer_id;
            $where = [
                'id' => $customer_id,
                'status' => 1,
                'audit_status' => 1,
                'business_attributes' => 1,
            ];
            $data = $this->getCustomerData($where, $field);
        }
        return $data;
    }

    /**
     * 模糊查找客户名称
     * @param $q
     * @param $field
     * @return mixed
     */
    function getCustomerByName($q, $field = true){
        $where = "customer_name like '%{$q}%' and customer_name <> '' and status = 1 and business_attributes = 1 and audit_status = 1";
        $data = $this->getCustomerData($where, '*');
        return $data;
    }

    /**
     * 通过联系人查找客户公司
     * @param $user_id
     * @return array|mixed
     */
    function getUserInfoByCustomerId($company_id){
        $data = [];
        if($company_id){
            $field = '*';
            $where = [
                'customer_id' => $company_id,
                'status' => 1,
            ];
            $data = $this->getModel('ErpCustomerUser')->field($field)->where($where)->order('id desc')->select();
        }
        return $data;
    }

}
