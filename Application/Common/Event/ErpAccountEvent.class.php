<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpAccountEvent extends BaseController
{

    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
    }

    /**
     * 账户列表
     * @author guanyu
     * 2017-11-13
     * @param $param
     */
    public function erpAccountList($param = [])
    {
        $where = [];

        if (trim($param['our_company_id'])) {
            $where['a.our_company_id'] = trim($param['our_company_id']);
        }
        if (trim($param['company_id'])) {
            $where['a.company_id'] = trim($param['company_id']);
        }
        if (trim($param['account_type'])) {
            $where['a.account_type'] = trim($param['account_type']);
        }
        $where['a.status'] = 1;
        $where['_string'] = 'IF(!ISNULL(c.id),c.status = 1,1=1) and IF(!ISNULL(s.id),s.status = 1,1=1)';
        $field = 'a.*,ec.company_name as our_company_name';
        if ($param['export']) {
            $data = $this->getModel('ErpAccount')->getAllAccountList($where, $field);
        } else {
            $data = $this->getModel('ErpAccount')->getAccountList($where, $field, $param['start'], $param['length']);
        }
        if ($data['data']) {
            $company_ids = array_column($data['data'], 'company_id');
            $customerCompany =  $this->getEvent('ErpCommon')->getCompanyData($company_ids, 1, '', true);
            $supplierCompany =  $this->getEvent('ErpCommon')->getCompanyData($company_ids, 2, '', true);
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['account_balance'] = getNum($value['account_balance']);
                $data['data'][$key]['account_type'] = AccountType($value['account_type']);
                $data['data'][$key]['company_name'] = $value['account_type'] == 1 ? $customerCompany[$value['company_id']]['company_name'] : $supplierCompany[$value['company_id']]['company_name'];
                $data['data'][$key]['data_source'] = $value['account_type'] == 1 ? $customerCompany[$value['company_id']]['data_source'] : $supplierCompany[$value['company_id']]['data_source'];

            }
        } else {
            $data['data'] = [];
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 账户变动
     * @param array $param
     * @author guanyu
     * @time 2017-11-10
     * @return array
     */
    public function changeAccount($order_info, $account_type, $change_num)
    {
        $status = false;
        if($order_info['company_id'] && $change_num){
            //查询账户
            $where = [
                'account_type' => $account_type,
                'our_company_id' => session('erp_company_id') ? session('erp_company_id') : $order_info['our_company_id'],
                'company_id' => $order_info['company_id'],
                'status' => 1,
            ];
            $before_account_info = $this->getModel('ErpAccount')->findAccount($where);

            //新增 || 更新账户
            if ($before_account_info) {
                $data = $before_account_info;
                $data['account_balance'] = $before_account_info['account_balance'] + $change_num;
            } else {
                $data = $where;
                $data['account_balance'] = $change_num;
            }
            $account_status = $this->getModel('ErpAccount')->saveAccount($data);

            $new_account_info = $this->getModel('ErpAccount')->findAccount($where);

            if(!empty($before_account_info)){
                $log_data = [
                    'account_id' => $new_account_info['id'],
                    'log_type' => $change_num > 0 ? 1 : 2,
                    'object_number' => $order_info['order_number'],
                    'object_type' => $order_info['object_type'],
                    'change_num' => $change_num,
                    'before_account_num' => $before_account_info['account_balance'],
                ];
            }else{
                $log_data = [
                    'account_id' => $new_account_info['id'],
                    'log_type' => $change_num > 0 ? 1 : 2,
                    'object_number' => $order_info['order_number'],
                    'object_type' => $order_info['object_type'],
                    'change_num' => $change_num,
                    'before_account_num' => 0,
                ];
            }

            $log_data['after_account_num'] = $new_account_info['account_balance'];

            $log_status = $this->addAccountLog($log_data);
            log_info("库存日志状态：". $log_status);
            $status = $account_status && $log_status ? true : false;

        }

        return $status;
    }

    /**
     * 生成账户操作日志
     * @param array $data
     * @return bool
     */
    public function addAccountLog($data = [])
    {
        if($data){
            $data['create_time'] = currentTime();
            $data['operator_id'] = $this->getUserInfo('id') ? $this->getUserInfo('id') : 0;
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : '';
            $status = $this->getModel('ErpAccountLog')->add($data);
        }
        return $status;
    }

    /**
     * 获得一条账户信息
     * @param array $data
     * @return bool
     */
    public function findAccount($data = [])
    {
        if($data){
            $data['create_time'] = currentTime();
            $data['operator_id'] = $this->getUserInfo('id') ? $this->getUserInfo('id') : 0;
            $data['operator'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : '';
            $status = $this->getModel('ErpAccountLog')->add($data);
        }
        return $status;
    }

    /**
     * 导入公司账户期初数据
     * @param $data
     * @author guanyu
     * @time 2017-11-16
     */
    public function importAccountData($data){
        if($data){
            $i = 1;
            $k = 0;
            $account_data = [];
            $status_all = true;
            $erp_company = array_flip(getAllErpCompany());
            M()->startTrans();
            foreach($data as $key=>$value){

                if(trim($value[0]) && trim($value[2]) && trim($value[3]) && trim($value[4])){
                    //----------------------组装数据------------------------------------------------------------------
                    $account_data[$k]['account_type'] = $value[3];
                    $account_data[$k]['our_company_id'] = $erp_company[$value[2]];
                    $account_data[$k]['company_id'] = $value[0];
                    $account_data[$k]['account_balance'] = setNum($value[4]);
                    $account_data[$k]['create_time'] = $this->date;

                    $clients_update = D('ErpAccount')->add($account_data[$k]);
                    $status_all = $status_all && $clients_update ? true :false;
                    //------------------------------------------------------------------------------------------------
                    echo '第'. $i . '条数据已处理！<br/>';
                }else{
                    echo trim($value[0]) . '&&' . trim($value[2]) . '&&' . trim($value[3]) . '&&' . trim($value[4]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（company_id,公司名称,我方公司，账户类型，账户余额） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
            }

            //print_r($stock_data);
            //$status = D('ErpStock')->addAll($stock_data);
            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }

    /**
     * 导入公司账户期初数据
     * @param $data
     * @author guanyu
     * @time 2017-11-16
     */
    public function importAccountDataSupply($data){
        if($data){
            $i = 1;
            $k = 0;
            $status_all = true;
            $erp_company = array_flip(getAllErpCompany());
            M()->startTrans();
            foreach($data as $key=>$value){

                if(trim($value[0]) && trim($value[2]) && trim($value[3]) && trim($value[4])){
                    //----------------------组装数据------------------------------------------------------------------

                    $where = [
                        'account_type' => $value[3],
                        'our_company_id' => $erp_company[$value[2]],
                        'company_id' => $value[0],
                    ];
                    $account_data = M('ErpAccount')->where($where)->find();

                    $data['account_balance'] = $account_data['account_balance'] + setNum(round($value[4], 2));
                    $data['update_time'] = $this->date;

                    $clients_update = D('ErpAccount')->where($where)->save($data);

                    $status_all = $status_all && $clients_update ? true :false;
                    //------------------------------------------------------------------------------------------------
                    echo '第'. $i . '条数据已处理！<br/>';
                }else{
                    echo trim($value[0]) . '&&' . trim($value[2]) . '&&' . trim($value[3]) . '&&' . trim($value[4]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（company_id,公司名称,我方公司，账户类型，账户余额） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
            }

            //print_r($stock_data);
            //$status = D('ErpStock')->addAll($stock_data);
            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }
}

?>
