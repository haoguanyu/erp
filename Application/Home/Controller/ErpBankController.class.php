<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpBankController extends BaseController
{
   /*
    * ------------------------------------------
    * ERP 银行列表
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function erpBankList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpBank')->erpBankList($param);
            $this->echoJson($data);
        }
        $data = [
            'our_company'    => getAllErpCompany(),
            'pay_type'       => getBankPayType(),
            'business_type'  => getBankBusinessType(),
            'bank_status'    => getBankStatus(),
        ];
        $this->assign('data',$data);
        $this->display();
    }

   /*
    * ------------------------------------------
    * ERP 添加银行信息
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function addErpBank()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpBank')->addErpBank($param);
            $this->echoJson($data);
        }
        $data = [
            'our_company'    => getAllErpCompany(),
            'pay_type'       => getBankPayType(),
            'business_type'  => getBankBusinessType(),
            'bank_status'    => getBankStatus(),
        ];
        $this->assign('data',$data);
        $this->display();
    }

   /*
    * ------------------------------------------
    * ERP 编辑银行信息
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function updateErpBank()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpBank')->updateErpBank($param);
            $this->echoJson($data);
        }
        $id        = intval(I('id', '', 'htmlspecialchars'));
        $bank_info = $this->getModel('ErpBank')->findErpBank(['id' => intval($id)]);
        $data      = [
            'our_company'    => getAllErpCompany(),
            'pay_type'       => getBankPayType(),
            'business_type'  => getBankBusinessType(),
            'bank_status'    => getBankStatus(),
        ];
        $this->assign("data", $data);
        $this->assign("bank_info", $bank_info);
        $this->display();
    }

   /*
    * ------------------------------------------
    * ERP 更新银行状态
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function updateErpBankStatus()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpBank')->updateErpBankStatus($param);
            $this->echoJson($data);
        }
    }

   /*
    * ------------------------------------------
    * ERP 银行信息详情
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function detailErpBank()
    {
        $id        = intval(I('id', '', 'htmlspecialchars'));
        $bank_info = $this->getModel('ErpBank')->findErpBank(['id' => intval($id)]);
        $data      = [
            'our_company'    => getAllErpCompany(),
            'pay_type'       => getBankPayType(),
            'business_type'  => getBankBusinessType(),
            'bank_status'    => getBankStatus(),
        ];
        $this->assign("data", $data);
        $this->assign("bank_info", $bank_info);
        $this->display();
    }

    /*
     * ------------------------------------------
     * ERP ajax银行信息详情
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function getErpBankInfoById()
    {
        if (IS_AJAX) {
            $id   = intval(I('id', '', 'htmlspecialchars'));
            $data = $this->getEvent('ErpBank')->getErpBankInfoById($id);
            $this->echoJson($data);
        }
    }

    /*
     * ------------------------------------------
     * ERP 银行信息详情(应收、应付、预存、预付列表展示)
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function getErpBankInfo()
    {
        $param     = $_REQUEST;
        $bank_info = $this->getEvent('ErpBank')->getErpBankInfo($param);
        $data      = [
            'our_company'    => getAllErpCompany(),
            'pay_type'       => getBankPayType(),
            'business_type'  => getBankBusinessType(),
            'bank_status'    => getBankStatus(),
        ];
        $this->assign("data", $data);
        $this->assign("bank_info", $bank_info);
        $this->display('detailErpBank');
    }

}