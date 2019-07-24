<?php
/**
 * 供应商业务处理层
 * @author xiaowen
 * @time 2019-09-21
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpSupplierEvent extends BaseController
{

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
    public function findSupplierName( $param )
    {
        if ( isset($param['customer_id']) && !empty($param['customer_id']) ) {
            // 获取 供应商名称
            $supplier_arr = $this->getModel('ErpCustomer')->field('customer_name')->where(['id' => trim($param['customer_id']),'business_attributes' => 1,'audit_status' => ['neq',1]])->find();
            if ( !isset($supplier_arr['customer_name']) ) {
                return ['status' => 3,'message' => '暂无此供应商！'];
            }
            $param['customer_name'] = $supplier_arr['customer_name'];
        }

        if ( !isset($param['customer_name']) || empty($param['customer_name']) ) {
            return ['status' => 2,'message' => '供应商名称不能为空！'];
        }
        (string)$customer_name = trim($param['customer_name']);

        (array)$where = [
            'supplier_name'       => $customer_name,
            'business_attributes' => 1,
            'status'              => 1,
        ];

        (string)$field = 'supplier_name';
        $find_customer_result = $this->getOneSupplierData($where,$field);

        if ( !isset($find_customer_result['supplier_name']) ) {
            return ['status' => 3,'message' => '暂无此供应商！'];
        }

        return ['status' => 1,'message' => '请求成功！'];

    }



    /**
     * 新增供应商
     * @param array $params
     * @return array
     */
    public function addSupplier($params = []){
        $data = $params;
        if(empty(trim($data['supplier_name']))){
            $result = [
                'status'=>2,
                'message'=>'供应商名称不能为空'
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


        if(!empty($data['backName']) && empty($data['backNum'])){
            $result = [
                'status' => 14,
                'message' => '银行名称存在，银行账号不能为空'
            ];
            return $result;
        }
        if(empty($data['backName']) && !empty($data['backNum'])){
            $result = [
                'status' => 14,
                'message' => '银行账号存在，银行名称不能为空'
            ];
            return $result ;
        }

         /******************************************************
            @ Content 更换判断唯一条件 （供应商名称+业务属性）
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
            'supplier_name'       => trim($data['supplier_name']),
            'business_attributes' => $data['business_attributes'],
            // 数据来源 99 属于erp
            'data_source'         => 99,
        ];
        /******************************************************
                            END
        *******************************************************/
        //验证供应商名字是否已存在
        if($this->getOneSupplierData($where)){
            $result = [
                'status' => 13,
                'message' => '该供应商已存在'
            ];
            return $result;
        }
        if (getCacheLock('ErpSupplier/addSupplier')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpSupplier/addSupplier', 1);

        M()->startTrans();

        $data['status'] = $data['status'] && isset($data['status']) ? intval($data['status']) : 1;
        $data['update_time'] = $data['create_time'] = DateTime();
        $data['updater'] = $data['creater'] = $this->getUserInfo('dealer_name');
        $backName = $data['backName'] ;
        $backNum = $data['backNum'] ;
        unset($data['backName']) ;
        unset($data['backNum']) ;
        $status = $id = $this->getModel('ErpSupplier')->addSupplier($data);
        $user_data = [];
        foreach ($data['userInfo']['user_name'] as $key=>$value){
            if(trim($value) && trim($data['userInfo']['user_phone'][$key])){
                $user_data[] = [
                    'supplier_id' => $id,
                    'user_name'   => trim($value),
                    'user_phone' => trim($data['userInfo']['user_phone'][$key]),
                    'create_time' => DateTime(),
                    'creater' => $this->getUserInfo('dealer_name'),
                    'updater' => $this->getUserInfo('dealer_name'),
                    'update_time' => DateTime(),
                ];
            }
        }
        $user_status = $this->getModel('ErpSupplierUser')->addAll($user_data);
        //银行的信息
        $backData[] = [
            'supplier_id' => $id,
            'ext_info_type'   => 6 ,
            'ext_info_content' => trim($data['registered_bank']),
            'ext_info_content_two' => trim($data['registered_bank_number']),
            'create_time' => DateTime(),
            'creater' => $this->getUserInfo('dealer_name'),
            'updater' => $this->getUserInfo('dealer_name'),
            'update_time' => DateTime(),
            'status' => 1
        ];
        if(!empty($backName) && !empty($backNum)){
            if(($backName == $data['registered_bank']) && ($backNum == $data['registered_bank_number'])){
            }else{
                $backData[] = [
                    'supplier_id' => $id,
                    'ext_info_type'   => 6 ,
                    'ext_info_content' => trim($backName),
                    'ext_info_content_two' => trim($backNum),
                    'create_time' => DateTime(),
                    'creater' => $this->getUserInfo('dealer_name'),
                    'updater' => $this->getUserInfo('dealer_name'),
                    'update_time' => DateTime(),
                    'status' => 1
                ];
            }

        }
        $add_back_status = $this->getModel('ErpSupplierExt')->addAll($backData);
        if($status && $user_status && $add_back_status){
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
        cancelCacheLock('ErpSupplier/addSupplier');


        return $result;
    }

    /**
     * 编辑供应商
     * @param int $id 供应商ID
     * @param array $params
     * @return array
     */
    public function updateSupplier($id, $params = []){
        $data = $params;
        if(empty($id)){
            $result = [
                'status'=>2,
                'message'=>'供应商ID参数错误'
            ];
            return $result;
        }
        if(empty(trim($data['supplier_name']))){
            $result = [
                'status'=>2,
                'message'=>'供应商名称不能为空'
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
        if (empty(trim($data['address_province'])) || empty(trim($data['address_city']))|| empty(trim($data['address_zone']))){
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

        if (getCacheLock('ErpSupplier/updateSupplier')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpSupplier/updateSupplier', 1);

        M()->startTrans();

        $data['status'] = $data['status'] && isset($data['status']) ? intval($data['status']) : 1;
        $data['update_time'] = DateTime();
        $data['updater'] = $this->getUserInfo('dealer_name');

        $status = $this->getModel('ErpSupplier')->saveSupplier(['id'=>$id], $data);

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
        cancelCacheLock('ErpSupplier/updateSupplier');


        return $result;
    }

    /**
     * 供应商列表
     * @author xiaowen 2018-09-21
     * @param $param
     */
    public function getSupplierList($param = [],$field = NULL)
    {
        $where = [];
        if(trim($param['supplier_name'])){
            $where['supplier_name'] = ['like', '%' . trim($param['supplier_name'] . '%')];
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
            $field = 'o.id,o.supplier_name,o.business_attributes,o.creater,o.create_time,o.updater,o.update_time,o.status, o.audit_status, o.audit_time, o.auditer ,o.data_source';
        }
        
        $data = $this->getModel('ErpSupplier')->getSupplierList($where, $field, $param['start'], $param['length']);
        //print_r($where);
        if ($data['data']) {
            //$cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                $data['data'][$key]['supplier_name'] = trim($value['supplier_name']);
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
     * 供应商审核
     * @param $id
     * @return array
     */
    public function auditSupplier($id){
        if(!$id){
            $result = [
                'status'=>2,
                'message'=>'ID参数错误',
            ];
            return $result;
        }
        $extType = supplierVoucher() ;
        $extTypeId = array_slice(array_keys($extType) , 0 , 5);
        $where = [
            'supplier_id'=>intval($id) ,
            'ext_info_type'=> ['in' , $extTypeId],
            "status" => 1
        ];
        $ext_info = $this->getModel('ErpSupplierExt')->where($where)->getField('id,ext_info_type');
        $order_info = $this->getModel('ErpSupplier')->where(['id'=>intval($id)])->field('audit_status,data_source')->find();
        log_info(print_r($ext_info, true));
        if($order_info['audit_status'] == 1){
            $result = [
                'status'=>3,
                'message'=>'此供应商已审核，请勿重复操作',
            ];
            return $result;
        }
        if(empty($ext_info) && $order_info['data_source'] == 99){
            $result = [
                'status'=>3,
                'message'=>'未上传任何证件,无法审核',
            ];
            return $result;
        }
        if(array_diff(array_keys(array_pop(array_pop(array_pop(supplierVoucher())))), $ext_info) && $order_info['data_source'] == 99){
            $result = [
                'status'=>4,
                'message'=>'请上传必要证件',
            ];
            return $result;
        }
        if (getCacheLock('ErpSupplier/auditSupplier')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpSupplier/auditSupplier', 1);

        M()->startTrans();
        $data = ['audit_status'=>1, 'audit_time'=>DateTime(), 'auditer'=>$this->getUserInfo('dealer_name')];
        $status = $this->getModel('ErpSupplier')->saveSupplier(['id'=>intval($id)], $data);

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
        cancelCacheLock('ErpSupplier/auditSupplier');



        return $result;
    }

    /**
     * 上传凭证
     * @param  $id 供应商ID
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

        $supplier_info = $this->getModel('ErpSupplier')->findSupplier(['id'=>$id]);

        if (!isset($param['voucher_type_1']) && $supplier_info['voucher_type_1'] == '') {
            $result = [
                'status' => 2,
                'message' => '三证合一必传'
            ];
            return $result;
        }

        if (!isset($param['voucher_type_2']) && $supplier_info['voucher_type_5'] == '') {
            $result = [
                'status' => 2,
                'message' => '开票资料必传'
            ];
            return $result;
        }
//        if (!isset($param['voucher_type_3']) && $supplier_info['voucher_type_3'] == '') {
//            $result = [
//                'status' => 2,
//                'message' => '开户许可证必传'
//            ];
//            return $result;
//        }
//        if (!isset($param['voucher_type_4']) && $supplier_info['voucher_type_4'] == '') {
//            $result = [
//                'status' => 3,
//                'message' => '成品油经营许可证必传'
//            ];
//            return $result;
//        }

        //验证文件大小
        foreach ($param as $key => $value) {
            if ($value) {
                //铅封图可多张上传
                //$name = strpos($key,'seal_photo') !== false ? '铅封图' : $arr[$key];
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

        if (getCacheLock('ErpSupplier/uploadVoucher')) return ['status' => 99, 'message' => $this->running_msg];

        setCacheLock('ErpSupplier/uploadVoucher', 1);

        $upload_status_all = true;
        $data = [];
        $delete_photo = [];
        $error_photo = [];
        //print_r($param);
        log_info(print_r($param, true));
        //exit;
        foreach ($param as $key => $value) {
            $uploaded_file = $value['tmp_name'];
            $user_path = $this->uploads_path['supplier_attach']['src'];
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
            if ($supplier_info[$key]) {
                array_push($delete_photo,$key);
            }
        }
        log_info(print_r($data, true));
        if(empty($data)){
            cancelCacheLock('ErpSupplier/uploadVoucher');
            return $result = [
                'status' => 4,
                'message' => '图片资源上传失败',
            ];
        }
        M()->startTrans();
        $data_update_status = true;
        $extType = supplierVoucher();
        $extTypeId = array_slice(array_keys($extType) , 0 , 5) ;
        $whereExtInfo =[
            'supplier_id'=>$id ,
            "ext_info_type" => ['in' , $extTypeId] ,
            "status" => 1
        ];
        $ext_info = $this->getModel('ErpSupplierExt')->where($whereExtInfo)->getField('ext_info_type, ext_info_content');
        //$dealer_id = $this->getUserInfo('id');
        if($ext_info){
            $row_status = $this->getModel('ErpSupplierExt')->where($whereExtInfo)->save(['status'=>2, 'update_time'=>DateTime()]);
        }else{
            $row_status = 1;
        }
        $dealer_name = $this->getUserInfo('dealer_name');
        foreach($data as $key=>$value){
            $ext_info_type = explode('_', $key)[2];
            $row[] = [
                'supplier_id' => $id,
                'ext_info_type' => $ext_info_type,
                'ext_info_content' => trim($value),
                //'create_time' => DateTime(),
                'update_time' => DateTime(),
                'updater' => $dealer_name,
                'create_time'=> DateTime(),
                'creater'=> $dealer_name,
            ];
        }
        $add_status = $this->getModel('ErpSupplierExt')->addAll($row);
        if ($row_status && $add_status) {
            //操作成功后删除原文件
            foreach ($delete_photo as $value) {
                unlink($user_path.$supplier_info[$value]);
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
        cancelCacheLock('ErpSupplier/uploadVoucher');
        return $result;
    }

    /**
     * 证件预览
     * @param $id 供应商ID
     * @return array|mixed
     */
    public function voucherList($id){
        $result = [];
        if($id){
            $extType = supplierVoucher();
            $extTYpeId = array_slice(array_keys($extType), 0 , 5);
            $where = [
                'supplier_id'=>$id,
                'status'=>1 ,
                'ext_info_type'=>['in' , $extTYpeId]
            ];
            $result = $this->getModel('ErpSupplierExt')->where($where)->getField('ext_info_type, ext_info_content');
        }

        return $result;
    }

    /**
     * 联系人列表
     * @param $id
     * @return array|mixed
     */
    public function getSupplierUserList($id){
        $data = [];
        if($id){
            //$data = $this->getModel('ErpSupplierUser')->where(['supplier_id'=>$id])->select();
            $where['supplier_id'] = intval($id);
            $data = $this->getModel('ErpSupplierUser')->getSupplierUserList($where);
            if ($data['data']) {
                //$cityArr = provinceCityZone()['city'];
                foreach ($data['data'] as $key => $value) {
                    $data['data'][$key]['supplier_name'] = trim($value['supplier_name']);
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
     * @param string $field 返回的字段
     * @return array
     */
    public function UserDetail($id, $field = ''){
        $data = [];

        if($id){
            $data = $this->getModel('ErpSupplierUser')->findSupplierUser(['id'=>intval($id)]);
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
    public function saveSupplierUser($param){
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
        $model = $this->getModel('ErpSupplierUser') ;
        if($param['id'] > 0){
            $status = $model->saveSupplierUser(['id'=>$data['id']], $data);
        }else{
            //判断用户是否已经存在
            $where = [
                "user_name" => $data['user_name'] ,
                'user_phone' => $data['user_phone'],
                'supplier_id' => $data['supplier_id'] ,
            ] ;
            $userCount = $model->getSupplierUserCount($where);
            if($userCount > 0){//用户存在则返回错误数据信息
                $result = [
                    'status' => 0,
                    'message' => '用户已经存在，请确认！',
                ];
                return $result ;
            }
            $data['create_time'] = DateTime();
            $data['creater'] = $this->getUserInfo('dealer_name');
            $status = $model->addSupplierUser($data);
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
     * 获取多个供应商数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    public function getSupplierData($where = [], $field = true){
        return $this->getModel('ErpSupplier')->field($field)->where($where)->order('id desc')->select();
    }

    /**
     * 获取多个供应商数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    public function getSupplierDataField($where = [], $field = true){
        $data = $this->getModel('ErpSupplier')->where($where)->getField($field);
        //return !empty($data) ? $data : [];
        return $data;
    }

    /**
     * 获取多个供应商联系人数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    function getSupplierUserDataField($where = [], $field = true){
        $data = $this->getModel('ErpSupplierUser')->where($where)->getField($field);
        return !empty($data) ? $data : [];
    }

    /**
     * 获取一条供应商数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    public function getSupplierByStorehouse($facilitator_skid_id){
        $storehouse_data = $this->getModel('ErpStorehouse')->where(['id'=>$facilitator_skid_id])->find();
        $supplier_data = $this->getModel('ErpSupplier')->where(['id'=>$storehouse_data['company_id']])->find();
        $supplier_data['data_source'] = storehouseSource($storehouse_data['is_new']);
        return $supplier_data;
    }

    /**
     * 获取一条供应商数据
     * @param array $where 查询条件
     * @param bool $field 查询字段
     * @return mixed
     */
    public function getOneSupplierData($where = [], $field = true){
        return $this->getModel('ErpSupplier')->field($field)->where($where)->find();
    }

    /**
     * 模糊查找姓名或手机搜索联系人
     * @param $q
     * @param $field
     * @return mixed
     */
    public function getSupplierUserByPhoneName($q, $field = true){
        $where = "(user_phone like '%{$q}%' or user_name like '%{$q}%') and user_name <> '' and status = 1";
        $where_company = [
            'business_attributes' => 1,
            'status' => 1,
        ];
        $company_id_arr = $this->getSupplierData($where_company, 'id');
        $company_id_arr = array_column($company_id_arr, 'id');
        if($company_id_arr){
            $where = $where . " and supplier_id in (" . implode(',', $company_id_arr) . ")";
            //echo $where;
        }else{
            return [];
        }
        $data = $this->getModel('ErpSupplierUser')->field($field)->where($where)->order('id desc')->select();
        return $data;
    }

    /**
     * 通过联系人查找供应商公司
     * @param $user_id
     * @return array|mixed
     */
    public function getSupplierInfoByUserId($user_id){
        $data = [];
        if($user_id){
            $customer_id = $this->getModel('ErpSupplierUser')->where(['id'=>$user_id])->getField('supplier_id');
            $field = 'id, supplier_name as company_name';
            $where = [
                'id' => $customer_id,
                'status' => 1,
                'audit_status' => 1,
                'business_attributes' => 1,
            ];
            $data = $this->getSupplierData($where, $field);
        }
        return $data;
    }

    /**
     * 模糊查找供应商名称
     * @param $q
     * @param $field
     * @return mixed
     */
    public function getSupplierByName($q, $field = true){
        $where = "supplier_name like '%{$q}%' and supplier_name <> '' and audit_status = 1 and status = 1 and business_attributes = 1";
        $data = $this->getSupplierData($where, '*');
        return $data;
    }

    /**
     * 通过供应商公司查找联系人
     * @param $user_id
     * @return array|mixed
     */
    public function getUserInfoBySupplierId($company_id){
        $data = [];
        if($company_id){
            $field = '*';
            $where = [
                'supplier_id' => $company_id,
                'status' => 1,
            ];
            $data = $this->getModel('ErpSupplierUser')->field($field)->where($where)->order('id desc')->select();
        }
        return $data;
    }
    /*
     * 获取供应商的银行信息
     */
    public function getSupplierBackList($request){
        $id = isset($request['supplier_id']) ? $request['supplier_id'] : "" ;
        $data = [];
        if($id){
            $where['supplier_id'] = intval($id);
            $where['ext_info_type'] = 6  ;
            $data = $this->getModel('ErpSupplierExt')->getSupplierBackList($where, "", $request['start'], $request['length']);
            if (!$data['data']) {
                $data['data'] = [];
            }
            $data['recordsFiltered'] = $data['recordsTotal'];
            $data['draw'] = $_REQUEST['draw'];
        }

        return $data;
    }
    /*
     * 添加银行账号信息
     * @author:小黑
     * 2018-11-20
     */
    public function saveSupplierBack($param){
        $data['ext_info_content'] = trim($param['backName']);
        $data['ext_info_content_two'] = trim($param['backNum']);
        $data['update_time'] = DateTime();
        $data['updater'] = $this->getUserInfo('dealer_name');
        $data['supplier_id'] = $param['supplier_id'] ;
        $data['id'] = $param['id'] ;
        if(empty(trim($data['ext_info_content']))){
            $result = [
                'status' => 2,
                'message' => '银行名称不能为空',
            ];
            return $result;
        }
        if(empty(trim($data['ext_info_content_two']))){
            $result = [
                'status' => 3,
                'message' => '银行账号不能为空',
            ];
            return $result;
        }
        $model = $this->getModel('ErpSupplierExt') ;
        //判断用户是否已经存在
        $where = [
            "ext_info_content" => $data['ext_info_content'] ,
            'ext_info_content_two' => $data['ext_info_content_two'],
            'ext_info_type' => 6 ,
            'supplier_id' => $param['supplier_id'] ,
        ] ;
        $userCount = $model->getSupplierBackCount($where);
        if($userCount > 0){//用户存在则返回错误数据信息
            $result = [
                'status' => 0,
                'message' => '供应商的银行账号已经存在，请确认！',
            ];
            return $result ;
        }
        if(isset($param['id']) && ($param['id'] > 0)){
            $status = $model->saveSupplierBack(['id'=>$data['id'] ,'ext_info_type' => 6], $data);
        }else{

            $data['create_time'] = DateTime();
            $data['creater'] = $this->getUserInfo('dealer_name');
            $data['ext_info_type'] = 6 ;
            $status = $model->addSupplierBack($data);
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
    /*
     * 获取账户的详情
     * @author：小黑
     * 2018-11-20
     */
    public function backDetail($id, $field = ''){
        $data = [];
        if($id){
            $data = $this->getModel('ErpSupplierExt')->findSupplierback(['id'=>intval($id)]);
            if($field){
                return $data[$field];
            }
        }
        return $data;
    }
    /*
     * 获取账户的详情
     * @author：小黑
     * 2018-11-20
     */
    public function getSupplierBackAll($where, $field = ''){
        $data = $this->getModel('ErpSupplierExt')->field($field)->where($where)->order('id desc')->select() ;
        return $data;
    }
}
