<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * --------------------------------------------------------------
 * 用户预存管理表oil_retail_balance | 模型
 * Author：jk        Time：2016-09-08
 * --------------------------------------------------------------
 */

class RetailBalanceModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];
        $this->time = date('Y-m-d H:i:s');

        $this->User = D('User');
        $this->RetailBalanceChange = D('RetailBalanceChange');
        $this->RetailCredit = D('RetailCredit');
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_balance')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return 0;

        return M('retail_balance')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_balance')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_balance')->where($where)->save($data);
    }


/////////////////////////////////业务处理层///////////////////////////////////////////

    /*
     * ----------------------------------------------
     * 用户预存管理列表数据
     * Author：jk        Time：2016-09-08
     * ----------------------------------------------
     */
    public function retailBalanceList()
    {
        $retail_balance_data = $this->selectData();
        $user_id = $user_data = $this->arr;
        if (count($retail_balance_data) > 0) {
            foreach ($retail_balance_data as $k => $v) {
                $user_id[] = intval($v['user_id']);
            }
        }
        if (!empty($user_id)) {
            // @获取用户信息 | 用户名 手机号码
            $user_id = array_unique($user_id);
            $user_data_ = $this->User->selectData('id,user_phone,user_name', ['is_available' => ['NEQ', 1], 'id' => ['IN', $user_id]]);
            foreach ($user_data_ as $k => $v) {
                $user_data[$v['id']] = [
                    'user_name' => strHtml($v['user_name']),
                    'user_phone' => strHtml($v['user_phone'])

                ];

            }
            foreach ($retail_balance_data as $k => $v) {
                $retail_balance_data[$k]['user_name'] = $user_data[$v['user_id']]['user_name'];
                $retail_balance_data[$k]['user_phone'] = $user_data[$v['user_id']]['user_phone'];
                $retail_balance_data[$k]['account_balance'] = number_format($v['account_balance'], 2);
                $retail_balance_data[$k]['use_balance'] = number_format($v['use_balance'], 2);
                $retail_balance_data[$k]['count_balance'] = number_format($v['count_balance'], 2);
            }
        }

        return $retail_balance_data;
    }

    /*
     * ----------------------------------------------
     * 用户预存管理列表 | 添加预存操作按钮
     * Author：jk        Time：2016-09-09
     * ----------------------------------------------
     */
    public function retailBalanceAdd($data = [])
    {
        if (count($data) <= 0) return ['status' => 2, 'message' => getMessage('a')];
        // @验证手机号码格式
        if (!isset($data['user_phone']) || !isMobile($data['user_phone'])) {
            return [
                'status' => 3,
                'message' => getMessage('h')
            ];
        }
        // @验证金额格式
        if (!isset($data['price']) || strHtml($data['price']) <= 0 || !is_numeric($data['price'])) {
            return [
                'status' => 4,
                'message' => getMessage('i')
            ];
        }
        // @验证手续费
        if (!isset($data['fee']) || !is_numeric($data['fee']) || $data['fee'] < 0) {
            return [
                'status' => 9,
                'message' => getMessage('b-c')
            ];
        }
        // @收款单号
        if (!isset($data['play_number']) || empty(trim($data['play_number']))) {
            return [
                'status' => 10,
                'message' => getMessage('b-d')
            ];
        }
        // @收款时间
        if (!isset($data['play_money_time']) || empty(trim($data['play_money_time']))) {
            return [
                'status' => 11,
                'message' => getMessage('b-e')
            ];
        }
        // @收款时间
        if (strtotime(date('Y-m-d', strtotime($param['play_money_time']))) > strtotime(date('Y-m-d', time()))) {
            return [
                'status' => 12,
                'message' => getMessage('b-h')
            ];
        }
        // @验证用户
        $user_data = $this->User->findData(['user_phone' => $data['user_phone'], 'is_available' => ['neq', 1]]);
        if (empty($user_data)) return ['status' => 5, 'message' => getMessage('j')];
        // @验证用户授信是否有欠款
        $retail_credit_data = $this->RetailCredit->findData(['user_id' => intval($user_data['id']), 'status' => 1]);
        if (!empty($retail_credit_data) && $retail_credit_data['line_credit'] != $retail_credit_data['surplus_line_credit']) {
            return [
                'status' => 8,
                'message' => getMessage('b-b')
            ];
        }
        // @预存操作
        $retail_balance_data = $this->findData(['user_id' => $user_data['id']]);
        $retail_balance = M('retail_balance');
        // @开启事务
        $retail_balance->startTrans();
        if (empty($retail_balance_data)) {
            $insert_retail_balance = [
                'user_id' => $user_data['id'],
                'account_balance' => strHtml($data['price']),
                'use_balance' => 0,
                'count_balance' => strHtml($data['price']),
                'create_time' => $this->time,
                'update_time' => $this->time
            ];
            $insert_retail_balance_change = [
                'user_id' => $user_data['id'],
                'befor_price' => 0,
                'change_price' => '+' . strHtml($data['price']),
                'after_price' => strHtml($data['price']),
                'type' => 1,          # @ 1预存操作 2订单对账
                'create_time' => $this->time,
                'remark' => trim($data['remark']),
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
                'play_money_time' => trim($data['play_money_time']),
                'fee' => strHtml($data['fee']),
                'play_number' => trim($data['play_number'])
            ];
            $result_retail_balance = $this->addData($insert_retail_balance);
            $result_retail_balance_change = $this->RetailBalanceChange->addData($insert_retail_balance_change);
            if ($result_retail_balance && $result_retail_balance_change) {
                $retail_balance->commit();
            } else {
                // @事务回滚
                $retail_balance->rollback();
                return ['status' => 6, 'message' => getMessage('l')];
            }
        } else {
            return ['status' => 7, 'message' => getMessage('a-e')];
        }

        return ['status' => 1, 'message' => getMessage('k')];
    }

    /*
     * ----------------------------------------------
     * 用户预存管理列表 | 续存操作按钮
     * Author：jk        Time：2016-09-11
     * ----------------------------------------------
     */
    public function retailBalanceAddContinued($param = [])
    {
        // @表单参数验证
        if (count($param) <= 0) return ['status' => 2, 'message' => getMessage('c')];
        $test = ['request_type', 'user_id', 'price', 'play_money_time', 'fee', 'play_number', 'remark'];
        $is = 0;
        foreach ($param as $k => $v) {
            if (!in_array(strHtml($k), $test)) $is += 1;
        }
        if ($is > 0) return ['status' => 3, 'message' => getMessage('c')];
        // @验证是否有预存
        $retail_balance_data = $this->findData(['user_id' => intval($param['user_id'])]);
        if (empty($retail_balance_data)) return ['status' => 4, 'message' => getMessage('b')];
        $retail_credit_data = $this->RetailCredit->findData(['user_id' => intval($param['user_id']), 'status' => 1]);
        if (!empty($retail_credit_data) && $retail_credit_data['line_credit'] != $retail_credit_data['surplus_line_credit']) {
            return [
                'status' => 7,
                'message' => getMessage('b-b')
            ];
        }
        // @验证金额格式
        if (!isset($param['price']) || strHtml($param['price']) <= 0 || !is_numeric($param['price'])) {
            return [
                'status' => 5,
                'message' => getMessage('i')
            ];
        }
        // @验证手续费
        if (!isset($param['fee']) || !is_numeric($param['fee']) || $param['fee'] < 0) {
            return [
                'status' => 9,
                'message' => getMessage('b-c')
            ];
        }
        // @收款单号
        if (!isset($param['play_number']) || empty(trim($param['play_number']))) {
            return [
                'status' => 10,
                'message' => getMessage('b-d')
            ];
        }
        // @收款时间
        if (!isset($param['play_money_time']) || empty(trim($param['play_money_time']))) {
            return [
                'status' => 11,
                'message' => getMessage('b-e')
            ];
        }
        if (strtotime(date('Y-m-d', strtotime($param['play_money_time']))) > strtotime(date('Y-m-d', time()))) {
            return [
                'status' => 12,
                'message' => getMessage('b-h')
            ];
        }
        // @更新账户余额信息
        $update_retail_balance = [
            'account_balance' => $retail_balance_data['account_balance'] + strHtml($param['price']),
            'count_balance' => $retail_balance_data['count_balance'] + strHtml($param['price']),
            'update_time' => $this->time
        ];
        $insert_retail_balance_change = [
            'user_id' => intval($param['user_id']),
            'befor_price' => $retail_balance_data['account_balance'],
            'change_price' => '+' . strHtml($param['price']),
            'after_price' => $retail_balance_data['account_balance'] + strHtml($param['price']),
            'type' => 1,
            'create_time' => $this->time,
            'remark' => trim($param['remark']),
            'dealer_id' => intval($_SESSION['adminInfo']['id']),
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'play_money_time' => trim($param['play_money_time']),
            'fee' => strHtml($param['fee']),
            'play_number' => trim($param['play_number'])
        ];
        $retail_balance = M('retail_balance');
        // @开启事务
        $retail_balance->startTrans();
        $result_retail_balance = $this->updateData(['user_id' => intval($param['user_id'])], $update_retail_balance);
        $result_retail_balance_change = $this->RetailBalanceChange->addData($insert_retail_balance_change);
        if ($result_retail_balance && $result_retail_balance_change) {
            $retail_balance->commit();
        } else {
            // @回滚
            $retail_balance->rollback();
            return ['status' => 6, 'message' => getMessage('z')];
        }

        return ['status' => 1, 'message' => getMessage('a-a')];
    }

    /**
     * 充值处理
     * @param $user_id
     * @param int $money
     * @param $log_id
     * @param $type
     * @return bool
     * @author xiaowen
     * @Time 2017-2-8
     */
    public function addBalance($user_id, $money = 0, $log_id = 0, $type = '网银充值')
    {
        if ($user_id > 0 && $money > 0) {
            $balanceInfo = $this->findData(['user_id' => $user_id]);
            log_info("查询用户预存帐户, SQL语句：" . $this->getLastSql());
            $data['user_id'] = $user_id;
            $data['count_balance'] = bcadd($balanceInfo['count_balance'], $money, 2);//$balanceInfo['count_balance'] + $money;
            $data['account_balance'] = bcadd($balanceInfo['account_balance'], $money, 2);// $balanceInfo['account_balance'] + $money;
            log_info("充值后余额" . $data['account_balance']);
            log_info("充值总金额" . $data['count_balance']);

            $data['user_id'] = $user_id;
            if (!empty($balanceInfo)) {
                $data['update_time'] = DateTime();
                $status = $this->where(['user_id' => $user_id])->save($data);
                log_info("更新用户预存状态：" . $status);
            } else {
                $data['create_time'] = DateTime();
                $data['update_time'] = DateTime();
                $status = $this->addData($data);
                log_info("添加用户预存状态：" . $status);
            }

            if ($status) {
                $change_log = [];
                $change_log['user_id'] = $user_id;
                $change_log['befor_price'] = $balanceInfo['account_balance'] ? $balanceInfo['account_balance'] : 0;
                $change_log['change_price'] = '+' . $money;
                $change_log['after_price'] = $data['account_balance'] ? $data['account_balance'] : 0;
                $change_log['type'] = 1;
                $change_log['create_time'] = DateTime();
                $change_log['play_money_time'] = DateTime();
                $change_log['remark'] = $type;
                $change_log['dealer_id'] = session('adminInfo')['id'];
                $change_log['dealer_name'] = session('adminInfo')['dealer_name'];
                $change_log['silver_log_id'] = $log_id;
                //$change_log['play_money_time'] = DateTime();
                $status_log = $this->RetailBalanceChange->add($change_log);
                log_info("记录用户预存日志状态：" . $status_log);
            }
            return $status && $status_log;
        } else {
            return false;
        }
    }


}


?>
