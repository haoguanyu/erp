<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpCompanyEvent extends BaseController
{

    const COMPANY_ID_NUM = 20000;
    // +----------------------------------
    // |ErpCompanyEvent:ERP公司帐套逻辑处理层
    // +----------------------------------
    // |Author:xiaowen Time:2018.6.7
    // +----------------------------------

    /**
     * 添加公司帐套
     * @author xiaowen
     * @time 2018-6-7
     * @param array $param = [
     *  'company_id' => '' //公司id(oil_clients表ID)
     *  'company_name' => '' //公司名称(oil_clients表company_name)
     *  'status' => '' //状态 1 启用 2 禁用
     *  'pre_code' => '' //帐套前缀
     * ]
     * @return array $result
     */
    public function addCompany($param){

        if(getCacheLock('ErpCompany/addCompany')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpCompany/addCompany', 1);

        $result = $this->saveCompany($param, 1);

        cancelCacheLock('ErpCompany/addCompany');
        return $result;
    }

    /**
     * 编辑公司帐套
     * @author xiaowen
     * @time 2018-6-7
     * @param array $param = [
     *  'company_id' => '' //公司id(oil_clients表ID)
     *  'company_name' => '' //公司名称(oil_clients表company_name)
     *  'status' => '' //状态 1 启用 2 禁用
     *  'pre_code' => '' //帐套前缀
     * ]
     * @return array $result
     */
    public function updateCompany($param){
        if(getCacheLock('ErpCompany/updateCompany')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpCompany/updateCompany', 1);

        $result = $this->saveCompany($param, 2);

        cancelCacheLock('ErpCompany/updateCompany');
        return $result;
    }

    /**
     * @desc 帐套信息保存（新增、编辑）
     * @param array $param = [
     *  'company_id' => '' //公司id(oil_clients表ID)
     *  'company_name' => '' //公司名称(oil_clients表company_name)
     *  'status' => '' //状态 1 启用 2 禁用
     *  'pre_code' => '' //帐套前缀
     * ]
     * @param int $type 操作类型 1 新增 2 编辑
     * @return array
     */
    public function saveCompany($param, $type = 1){
        $result = [
            'status' => 2,
            'message' => '参数格式有误',
        ];
        if(is_array($param) && !empty($param)){
            $pre_code_arr = explode('_', trim($param['pre_code']));
            //print_r($pre_code_arr);
//            if(empty(trim($param['company_id']))){
//                $result = [
//                    'status' => 3,
//                    'message' => '公司ID有误',
//                ];
//            }else
            if(empty(trim($param['company_name']))){
                $result = [
                    'status' => 4,
                    'message' => '公司名称不能为空',
                ];
            }else if(empty(trim($param['pre_code'])) || strpos(trim($param['pre_code']), '_') === false || strlen(trim($param['pre_code'])) != 3 || $pre_code_arr[1] != ''){
                $result = [
                    'status' => 5,
                    'message' => '帐套前缀不能为空且长度必须3位,以下划线结尾，例：HY_',
                ];
            }else if(empty(trim($param['status']))){
               $result = [
                   'status' => 9,
                   'message' => '请选择状态',
               ];
           }else{

                if($type == 1){ //添加操作
                    $isExistCompany = $this->checkCompanyExist(trim($param['company_name']),[]);
                    if($isExistCompany){
                        return $result = [
                            'status' => 6,
                            'message' => '该公司已设置为内部帐套',
                        ];
                    }
                    $isExistPreCode = $this->checkPreCodeExist(trim($param['pre_code']),[]);
                    if($isExistPreCode){
                        return $result = [
                            'status' => 7,
                            'message' => '该前缀已存在',
                        ];
                    }
                    $param['create_time'] = DateTime();
                    $param['creater'] = $this->getUserInfo('dealer_name');
                    $param['creater_id'] = $this->getUserInfo('id');
                    $last_id = $this->getModel('ErpCompany')->order('id desc')->limit(1)->getField('id');
                    $now_id = $last_id + 1;
                    $param['company_id'] = self::COMPANY_ID_NUM + $now_id;
                    $param['company_name'] = trim($param['company_name']);
                    $param['short_name'] = trim($param['short_name']);
                    $company_status = $id = $this->getModel('ErpCompany')->addCompany($param);

                    $logData = [
                        'config_id' => $id,
                        'operate_object' => 2, //配置操作日志关联erp_company表
                        'log_type' => 1,
                        'log_info' => json_encode($param, JSON_UNESCAPED_UNICODE),
                        'operator' => $this->getUserInfo('dealer_name'),
                        'operator_id' => $this->getUserInfo('id'),
                    ];
                    $log_status = $this->_createOperateLog($logData);
                }else{         //更新操作
                    if(empty(trim($param['id']))){
                        return $result = [
                            'status' => 8,
                            'message' => '帐套ID有误',
                        ];
                    }
                    $isExistCompany = $this->checkCompanyExist(trim($param['company_name']), ['id'=>['neq', intval($param['id'])]]);
                    if($isExistCompany){
                        return $result = [
                            'status' => 6,
                            'message' => '该公司已设置为内部帐套',
                        ];
                    }
                    $isExistPreCode = $this->checkPreCodeExist(trim($param['pre_code']), ['id'=>['neq', intval($param['id'])]]);
                    if($isExistPreCode){
                        return $result = [
                            'status' => 7,
                            'message' => '该前缀已存在',
                        ];
                    }
                    $param['update_time'] = DateTime();
                    $param['company_name'] = trim($param['company_name']);
                    $param['short_name'] = trim($param['short_name']);
                    $company_status = $this->getModel('ErpCompany')->saveCompany(['id'=>intval($param['id'])], $param);
                    $logData = [
                        'config_id' => $param['id'],
                        'operate_object' => 2, //配置操作日志关联erp_company表
                        'log_type' => 2, //操作类型 2：修改
                        'log_info' => json_encode($param, JSON_UNESCAPED_UNICODE),
                        'operator' => $this->getUserInfo('dealer_name'),
                        'operator_id' => $this->getUserInfo('id'),
                    ];
                    $log_status = $this->_createOperateLog($logData);
                }

                if($company_status && $log_status){
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

            }
        }

        return $result;
    }
    /**
     * 公司帐套列表
     * @author xiaowen
     * @time 2018-6-7
     * @param array $param = [
     *  'company_name' => '' //名称
     * ]
     */
    public function companyList($param){
        $where = [];
        if(trim($param['company_name'])){
            $where['ec.company_name'] = ['like','%' . trim($param['company_name']) . '%'];
        }
        if(intval($param['status'])){
            $where['ec.status'] = intval($param['status']);
        }
        $field = 'ec.*';
        $data = $this->getModel('ErpCompany')->getCompanyList($where,$field,intval($_REQUEST['start']), intval($_REQUEST['length']));
        foreach($data['data'] as $key => $value){
            $data['data'][$key]['status'] = erpStorehouseStatus($value['status'], true);
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 检验公司是否存在
     * @param $company_id
     * @param $where
     * @return bool
     */
    public function checkCompanyExist($company_id,$where = []){
        //$erpCompanyList = getErpCompanyList('company_id',$where);
        $erpCompanyList = getErpCompanyList('company_name',$where);
        if(in_array(trim($company_id), $erpCompanyList)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 检验帐套前缀是否存在
     * @param $code
     * @param $where
     * @return bool
     */
    public function checkPreCodeExist($code,$where = []){
        $preCodeList = getErpCompanyList('pre_code',$where);
        if(in_array(trim($code), $preCodeList)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 生成操作日志
     * @param $param
     * @return bool $status
     */
    private function _createOperateLog($param){
        $param['create_time'] = DateTime();
        $status = $this->getModel('ErpConfigLog')->add($param);
        return $status;
    }

}
