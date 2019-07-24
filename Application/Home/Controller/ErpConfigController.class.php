<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpConfigController extends BaseController
{
    /*
     * ------------------------------------------
     * ERP 系统设置
     * Author：qianbin        Time：2018-05-03
     * ------------------------------------------
     */
    public function erpConfigList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpConfig')->erpConfigList($param);
            $this->echoJson($data);
        }
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:添加erp系统配置操作
    // +----------------------------------
    // |Author:qianbin Time:2018.5.03
    // +----------------------------------
    public function addErpConfig()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpConfig')->addErpConfig($param);
            $this->echoJson($data);
        }
        $typeArr = configTypeArr();
        $businessType = getBusinessType();
        $this->assign('businessType', $businessType);
        $this->assign('typeArr', $typeArr);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:编辑erp系统配置页面
    // +----------------------------------
    // |Author:qianbin Time:2018.5.03
    // +----------------------------------
    public function updateErpConfig()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpConfig')->updateErpConfig($param);
            $this->echoJson($data);
        }
        $id   = intval(I('id', '', 'htmlspecialchars'));
        $data = $this->getModel('ErpConfig')->where(['id' => intval($id)])->find();
        $typeArr = configTypeArr();
        $businessType = getBusinessType();
        $this->assign('businessType', $businessType);
        $this->assign('typeArr', $typeArr);
        $this->assign("data", $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:修改erp系统配置状态
    // +----------------------------------
    // |Author:qianbin Time:2018.5.03
    // +----------------------------------
    public function updateErpConfigStatus()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpConfig')->updateErpConfigStatus($param);
            $this->echoJson($data);
        }
    }
    /**
     * 以销定采，配置系统配置密度
     * @author qianbin
     * @time 2018-5-3
     */
    public function setConfigDensity()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpConfig')->actUpdateErpConfig($param);
            $this->echoJson($data);
        }
        $this->display();
    }

}