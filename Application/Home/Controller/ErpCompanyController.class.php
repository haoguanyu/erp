<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * ERP账套管理控制器
 * @author xiaowen
 * Class ErpCompanyController
 * @package Home\Controller
 */
class ErpCompanyController extends BaseController
{

    /**
     * 账套列表
     * @time 2018-6-7
     * @author xiaowen
     */
    public function companyList(){
        $param = I('get.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpCompany')->companyList($param);
            $this->echoJson($data);
        }
        $data['statusArr'] = erpStorehouseStatus();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 添加账套
     * @time 2018-6-7
     * @author xiaowen
     */
    public function addCompany(){
        $param = I('post.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpCompany')->addCompany($param);
            $this->echoJson($data);
        }
        $data['statusArr'] = erpStorehouseStatus();
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑账套
     * @time 2018-6-7
     * @author xiaowen
     */
    public function updateCompany(){
        $param = I('param.');
        if(IS_AJAX){
            $data = $this->getEvent('ErpCompany')->updateCompany($param);
            $this->echoJson($data);
        }
        $data['data'] = $this->getModel('ErpCompany')->find(intval($param['id']));
        $data['statusArr'] = erpStorehouseStatus();
        //print_r($data);
        $preCodeList = getErpCompanyList('pre_code');
        //print_r($preCodeList);
        //echo in_array('IFT_', $preCodeList) ? '存在' : '不存在';
        $this->assign('data', $data);
        $this->display();
    }


}