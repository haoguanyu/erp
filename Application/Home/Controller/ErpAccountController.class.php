<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpAccountController extends BaseController
{

    /**
     * 账户列表
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2017-11-13
     */
    public function erpAccountList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpAccount')->erpAccountList($param);
            $this->echoJson($data);
        }
        $data['our_company'] = getAllErpCompany();
        $data['account_type'] = AccountType();
        $this->assign('data',$data);
        $this->display();
    }

    /**
     * 导入公司账户期初数据
     * @author guanyu
     * @time 2017-11-16
     */
    public function importAccountData(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './account_data.xls';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './account_data.xlsx';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(0);
            $data = $currentSheet->toArray();
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
            //print_r($data);

            $this->getEvent('ErpAccount')->importAccountData($data);
        }
    }

    /**
     * 导入公司账户期初数据
     * @author guanyu
     * @time 2017-11-16
     */
    public function importAccountDataSupply(){
        Vendor('PHPExcel.Classes.PHPExcel');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
        Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');
        //$filePath1 = './Public/Uploads/Erp/stock_data.xlsx';
//        die('已无用，不要来调戏我^___^');
        $filePath1 = './account_data_supply.xls';
        //$filePath2 = './Public/Uploads/Erp/stock_data.xls';
        $filePath2 = './account_data_supply.xlsx';
        $filePath = is_file($filePath1) ? $filePath1 : $filePath2;
        if(is_file($filePath)){
            $PHPReader = new \PHPExcel_Reader_Excel2007();
            if (!$PHPReader->canRead($filePath)) {
                $PHPReader = new \PHPExcel_Reader_Excel5();
                if (!$PHPReader->canRead($filePath)) {
                    return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
                }
            }
            $PHPExcel = $PHPReader->load($filePath);
            # @读取excel文件中的第一个工作表
            $currentSheet = $PHPExcel->getSheet(1);
            $data = $currentSheet->toArray();
            //print_r($data);
            unset($data[0]);
            //unset($data[1]);
            //print_r($data);

            $this->getEvent('ErpAccount')->importAccountDataSupply($data);
        }
    }

    /**
     * 导出账户列表
     * @author guanyu
     * @time 2017-11-21
     */
    public function exportAccountData(){
        $param = I('param.');
        //导出所有销售单
        $param['export'] = 1;
        $data = $this->getEvent('ErpAccount')->erpAccountList($param);
        $arr = [];
        if($data){
            foreach($data['data'] as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['our_company_name']    = $v['our_company_name'];
                $arr[$k]['company_name']        = $v['company_name'];
                $arr[$k]['account_type']        = $v['account_type'];
                $arr[$k]['account_balance']     = "".$v['account_balance'];
                $arr[$k]['data_source']         = $v['data_source'];
            }
        }

        $header = [
            'ID','账套公司','公司名称','账户类型','账户余额','数据标识'
        ];

        array_unshift($arr,  $header);
        $file_name_arr = '账户列表'.currentTime().'.xls';
        create_xls($arr, $filename = $file_name_arr);
    }

    /**
     * 账户列表
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2017-11-13
     */
    public function erpAccountListBySearch()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            # 如果业务单据类型为空，则返回空
            if (intval($param['company_id']) <= 0 ) {
                $data['data'] = [];
                $data['recordsFiltered'] = 0;
                $data['recordsTotal']    = 0;
                $this->echoJson($data);
            }
            $data  = $this->getEvent('ErpAccount')->erpAccountList($param);
            $this->echoJson($data);
        }
        $data['our_company'] = getAllErpCompany();
        $data['account_type'] = AccountType();
        $this->assign('data',$data);
        $this->display();
    }
    /**
     * 导出账户列表
     * @author guanyu
     * @time 2017-11-21
     */
    public function exportAccountDataBySearch(){
        $param = I('param.');
        //导出所有销售单
        $param['export'] = 1;
        if (intval($param['company_id']) <= 0 ) {
           echo '请输入对应公司名称！'; exit;
        }
        $data = $this->getEvent('ErpAccount')->erpAccountList($param);
        $arr = [];
        if($data){
            foreach($data['data'] as $k=>$v){
                $arr[$k]['id']                  = $v['id'];
                $arr[$k]['our_company_name']    = $v['our_company_name'];
                $arr[$k]['company_name']        = $v['company_name'];
                $arr[$k]['account_type']        = $v['account_type'];
                $arr[$k]['account_balance']     = "".$v['account_balance'];
            }
        }

        $header = [
            'ID','账套公司','公司名称','账户类型','账户余额'
        ];

        array_unshift($arr,  $header);
        $file_name_arr = '账户列表'.currentTime().'.xls';
        create_xls($arr, $filename = $file_name_arr);
    }

    /**
     * 预存预付列表分帐套
     * @return mixed
     * @author xiaowen
     * @time 2018-05-17
     */
    public function erpCompanyAccountList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $param['our_company_id'] = session('erp_company_id');
            $data = $this->getEvent('ErpAccount')->erpAccountList($param);
            $this->echoJson($data);
        }
        $data['our_company_id'] = session('erp_company_id');
        $data['account_type'] = AccountType();
        $this->assign('data',$data);
        $this->display();
    }
}
