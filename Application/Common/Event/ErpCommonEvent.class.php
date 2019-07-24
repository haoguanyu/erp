<?php
/**
 * 公共业务处理层
 * @author xiaowen
 * @time 2019-09-21
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpCommonEvent extends BaseController
{

    /**
     * 获取客户、供应商联系人数据
     * @param array $user_id 用户id(数组形式)
     * @param int $type 类型： 1 客户 2 供应商
     * @param string $field
     * @return array
     */
    public function getUserData($user_id = [], $type = 1, $field = 'id, user_name, user_phone'){
        $eventArr = [
            1 => 'ErpCustomer',
            2 => 'ErpSupplier',
        ];
        //==================匹配用户与公司信息 xiaowen 2019-10-15==============================
        $userInfo = [];

        if(!empty($user_id)){
            $user_where = [
                'id' => ['in', $user_id],
            ];
            $userFunction = [
                1 => 'getCustomerUserDataField',
                2 => 'getSupplierUserDataField'
            ];
            $functionStr = $userFunction[$type];
            $userInfo = $this->getEvent($eventArr[$type])->$functionStr($user_where, $field);
        }
        return $userInfo;
    }

    /**
     * 获取客户、供应商公司数据
     * @param array $companyIdArr 公司id(数组形式)
     * @param int $type 类型： 1 客户 2 供应商
     * @param string $field
     * @return array
     */
    public function getCompanyData($companyIdArr = [], $type = 1, $field = '', $data_source = false){
        $eventArr = [
            1 => 'ErpCustomer',
            2 => 'ErpSupplier',
        ];
        //==================匹配用户与公司信息 xiaowen 2019-10-15==============================
        $companyInfo = [];
        if(!empty($companyIdArr)){
            $company_where = [
                'id' => ['in', $companyIdArr],
            ];
            if(empty($field)){
                $company_field = [
                    1 => 'id, customer_name as company_name, customer_short_name, data_source , is_inner',
                    2 => 'id, supplier_name as company_name, supplier_short_name, data_source , is_inner'
                ];
                $field = $company_field[$type];
            }

            $companyFunction = [
                1 => 'getCustomerDataField',
                2 => 'getSupplierDataField'
            ];
            $functionStr = $companyFunction[$type];
            $companyInfo = $this->getEvent($eventArr[$type])->$functionStr($company_where, $field);
            if ( $data_source ) {
                foreach ($companyInfo as &$value) {
                    $value['data_source'] = dataSourceName( $value['data_source'] );
                }
            }

        }
        return $companyInfo == NULL ? [] : $companyInfo;
    }

}
