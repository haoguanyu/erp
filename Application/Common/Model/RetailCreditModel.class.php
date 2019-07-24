<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 用户授信管理表oil_retail_credit | 模型
 * Author：jk        Time：2016-09-09
 * ----------------------------------------
 */

class RetailCreditModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];
        $this->time = date('Y-m-d H:i:s');

        $this->User = D('User');
        $this->RetailCreditChange = D('RetailCreditChange');
        $this->RetailCreditTerm = D('RetailCreditTerm');
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_credit')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_credit')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_credit')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_credit')->where($where)->save($data);
    }


///////////////////////////////////业务处理层//////////////////////////////////////////


    /*
     * ----------------------------------------------
     * 用户授信管理列表数据
     * Author：jk        Time：2016-09-09
     * ----------------------------------------------
     */
    public function retailCreditList()
    {
        $retail_credit_data = $this->selectData('*', ['status' => 1]);
        $user_id = $user_data = $this->arr;
        if (count($retail_credit_data) > 0) {
            foreach ($retail_credit_data as $k => $v) {
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
            foreach ($retail_credit_data as $k => $v) {
                $retail_credit_data[$k]['user_name'] = $user_data[$v['user_id']]['user_name'];
                $retail_credit_data[$k]['user_phone'] = $user_data[$v['user_id']]['user_phone'];
                $retail_credit_data[$k]['is_end'] = '未到期';
                $retail_credit_data[$k]['is'] = '0';
                if (date('Y-m-d', strtotime($v['end_time'])) < date('Y-m-d', strtotime($this->time))) {
                    $retail_credit_data[$k]['is_end'] = '<font color="red">已过期</font>';
                    $retail_credit_data[$k]['is'] = '1';
                }
                $retail_credit_data[$k]['is_repayment'] = $v['line_credit'] - $v['surplus_line_credit'];
                $retail_credit_data[$k]['old_line_credit'] = $v['line_credit'];
                $retail_credit_data[$k]['old_surplus_line_credit'] = $v['surplus_line_credit'];
                $retail_credit_data[$k]['start_time'] = date('Y-m-d', strtotime($v['start_time']));
                $retail_credit_data[$k]['end_time'] = date('Y-m-d', strtotime($v['end_time']));
                $retail_credit_data[$k]['line_credit'] = number_format($v['line_credit'], 2);
                $retail_credit_data[$k]['use_line_credit'] = number_format($v['use_line_credit'], 2);
                $retail_credit_data[$k]['surplus_line_credit'] = number_format($v['surplus_line_credit'], 2);
                $retail_credit_data[$k]['type_name'] = changeReatilCreditType(intval($v['type']));
            }
        }

        return $retail_credit_data;
    }

    /*
     * ----------------------------------------------
     * 用户授信管理列表 | 授信按钮操作
     * Author：jk        Time：2016-09-09
     * ----------------------------------------------
     */
    public function retailCreditAdd($param = [])
    {
        // @表单验证
        if (count($param) <= 0) return ['status' => 2, 'message' => getMessage('a')];
        $test = ['user_phone', 'type', 'request_type', 'start_time', 'end_time', 'remark', 'line_credit'];
        $is = 0;
        foreach ($param as $k => $v) {
            if (!in_array($k, $test)) $is += 1;
        }
        if ($is > 0) return ['status' => 3, 'message' => getMessage('b')];
        // @验证手机号码
        if (!isMobile($param['user_phone'])) return ['status' => 4, 'message' => getMessage('h')];
        // @验证用户是否存在
        $user_data = $this->User->findData(['user_phone' => strHtml($param['user_phone']), 'is_available' => ['NEQ', 1]]);
        if (empty($user_data)) return ['status' => 9, 'message' => getMessage('j')];
        // @验证是否已授信过
        $retail_credit_data = $this->findData(['user_id' => $user_data['id'], 'status' => 1]);
        if (!empty($retail_credit_data)) return ['status' => 10, 'message' => getMessage('q')];
        // @验证授信类型
        if (!in_array(intval($param['type']), array_keys(changeReatilCreditType()))) {
            return [
                'status' => 5,
                'message' => getMessage('m')
            ];
        }
        // @验证额度金额
        if (!isset($param['line_credit']) || strHtml($param['line_credit']) <= 0 || !is_numeric($param['line_credit'])) {
            return [
                'status' => 10,
                'message' => getMessage('i')
            ];
        }
        if (!isset($param['line_credit']) || strHtml($param['line_credit']) > 100000) {
            return [
                'status' => 11,
                'message' => getMessage('b-a')
            ];
        }
        // @验证授信开始与到期时间
        if (empty(trim($param['start_time'])) || empty(trim($param['end_time']))) {
            return [
                'stataus' => 6,
                'message' => getMessage('n')
            ];
        }
        if (strtotime($param['start_time']) >= strtotime($param['end_time'])) {
            return [
                'status' => 7,
                'message' => getMessage('o')
            ];
        }
        if (strtotime(date('Y-m-d', strtotime($param['end_time']))) < strtotime(date('Y-m-d'))) {
            return [
                'status' => 12,
                'message' => getMessage('a-m')
            ];
        }
        // @验证备注信息
        if (empty(strHtml($param['remark']))) return ['status' => 8, 'message' => getMessage('p')];
        // @授信 | 存入3张表 =====
        // @存入授信额度管理表 | 【oil_retail_credit】
        $credit_card = creditCard();        # @生成授信卡号
        $insert_retail_credit = [
            'user_id' => $user_data['id'],
            'credit_card' => $credit_card,
            'line_credit' => strHtml($param['line_credit']),
            'use_line_credit' => 0,
            'surplus_line_credit' => strHtml($param['line_credit']),
            'create_time' => $this->time,
            'update_time' => $this->time,
            'start_time' => date('Y-m-d 00:00:00', strtotime($param['start_time'])),
            'end_time' => date('Y-m-d 23:59:59', strtotime($param['end_time'])),
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'status' => 1,
            'type' => intval($param['type']),
            'remark' => trim($param['remark'])
        ];
        // @存入授信额度还款与消费明细表 | 【oil_retail_credit_change】
        $insert_retail_credit_change = [
            'user_id' => $user_data['id'],
            'credit_card' => $credit_card,
            'befor_credit' => 0,
            'change_credit' => '+' . strHtml($param['line_credit']),
            'after_credit' => strHtml($param['line_credit']),
            'type' => 1,
            'create_time' => $this->time,
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'remark' => trim($param['remark'])
        ];
        // @存入授信额度到期重新授信记录表 | 【oil_retail_credit_term】
        $insert_retail_credit_term = [
            'user_id' => $user_data['id'],
            'credit_card' => $credit_card,
            'befor_credit' => 0,
            'change_credit' => '+' . strHtml($param['line_credit']),
            'after_credit' => strHtml($param['line_credit']),
            'create_time' => $this->time,
            'start_time' => date('Y-m-d 00:00:00', strtotime($param['start_time'])),
            'end_time' => date('Y-m-d 23:59:59', strtotime($param['end_time'])),
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'remark' => trim($param['remark'])
        ];
        // @开启事务处理
        $retail_credit = M('retail_credit');
        $retail_credit->startTrans();
        $result_credit = $this->addData($insert_retail_credit);
        $result_credit_change = $this->RetailCreditChange->addData($insert_retail_credit_change);
        $result_credit_term = $this->RetailCreditTerm->addData($insert_retail_credit_term);
        if ($result_credit && $result_credit_change && $result_credit_term) {
            // @同时成功
            $retail_credit->commit();
        } else {
            // @回滚
            $retail_credit->rollback();
            return ['status' => 9, 'message' => getMessage('y')];
        }

        return ['status' => 1, 'message' => getMessage('x')];
    }

    /*
     * ----------------------------------------------
     * 用户授信管理列表 | 还款按钮默认数据处理
     * Author：jk        Time：2016-09-11
     * ----------------------------------------------
     */
    public function dataProcessing($data = [])
    {
        $data['old_wait_credit'] = $data['line_credit'] - $data['surplus_line_credit'];
        $data['wait_credit'] = number_format(($data['line_credit'] - $data['surplus_line_credit']), 2);
        $data['line_credit'] = number_format($data['line_credit'], 2);
        $data['surplus_line_credit'] = number_format($data['surplus_line_credit'], 2);

        return $data;
    }

    /*
     * ----------------------------------------------
     * 用户授信管理列表 | 还款按钮
     * Author：jk        Time：2016-09-11
     * ----------------------------------------------
     */
    public function retailCreditRepayment($param = [])
    {
        // @表单验证
        if (count($param) <= 0) return ['status' => 2, 'message' => getMessage('b')];
        $test = ['request_type', 'credit_card', 'credit', 'fee', 'play_number', 'play_money_time', 'remark'];
        $is = 0;
        foreach ($param as $k => $v) {
            if (!in_array(strHtml($k), $test)) $is += 1;
        }
        if ($is > 0) return ['status' => 3, 'message' => getMessage('b')];
        // @验证金额
        if ($param['credit'] <= 0 || !is_numeric($param['credit'])) {
            return [
                'status' => 4,
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
        // @还款单号
        if (!isset($param['play_number']) || empty(trim($param['play_number']))) {
            return [
                'status' => 10,
                'message' => getMessage('b-f')
            ];
        }
        // @还款时间
        if (!isset($param['play_money_time']) || empty(trim($param['play_money_time']))) {
            return [
                'status' => 11,
                'message' => getMessage('b-g')
            ];
        }
        if (strtotime(date('Y-m-d', strtotime($param['play_money_time']))) > strtotime(date('Y-m-d', time()))) {
            return [
                'status' => 12,
                'message' => getMessage('b-j')
            ];
        }
        // @验证是否授信
        $retail_credit_data = $this->findData(['credit_card' => trim($param['credit_card']), 'status' => 1]);
        if (empty($retail_credit_data)) return ['status' => 5, 'message' => getMessage('c')];
        // @验证还款金额不能大于待还金额
        if ($param['credit'] > ($retail_credit_data['line_credit'] - $retail_credit_data['surplus_line_credit'])) {
            return [
                'status' => 5,
                'message' => getMessage('a-d')
            ];
        }
        // @更新【oil_retail_credit】
        $update_retail_credit = [
            'surplus_line_credit' => $retail_credit_data['surplus_line_credit'] + strHtml($param['credit']),
            'update_time' => $this->time
        ];
        // @插入【oil_retail_credit_change】
        $insert_retail_credit_change = [
            'user_id' => intval($retail_credit_data['user_id']),
            'credit_card' => strHtml($param['credit_card']),
            'befor_credit' => $retail_credit_data['surplus_line_credit'],
            'change_credit' => '+' . strHtml($param['credit']),
            'after_credit' => $retail_credit_data['surplus_line_credit'] + strHtml($param['credit']),
            'type' => 2,
            'create_time' => $this->time,
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'remark' => trim($param['remark']),
            'play_money_time' => trim($param['play_money_time']),
            'fee' => strHtml($param['fee']),
            'play_number' => trim($param['play_number'])
        ];
        // @开启事务
        $retail_credit = M('retail_credit');
        $retail_credit->startTrans();
        $result_retail_credit = $this->updateData(['id' => intval($retail_credit_data['id']), 'status' => 1], $update_retail_credit);
        $result_retail_credit_change = $this->RetailCreditChange->addData($insert_retail_credit_change);
        if ($result_retail_credit && $result_retail_credit_change) {
            $retail_credit->commit();
        } else {
            // @事务回滚
            $retail_credit->rollback();
            return ['status' => 6, 'message' => getMessage('a-c')];
        }

        return ['status' => 1, 'message' => getMessage('a-b')];
    }

    /*
     * ----------------------------------------------
     * 用户授信管理列表 | 重新授信按钮默认数据显示处理
     * Author：jk        Time：2016-09-12
     * ----------------------------------------------
     */
    public function retailCreditDelayHandle($user_id)
    {
        $data = $this->findData(['user_id' => intval($user_id), 'status' => 1]);
        $data['type_val'] = changeReatilCreditType();
        $data['start_time'] = date('Y-m-d', strtotime($data['start_time']));
        $data['end_time'] = date('Y-m-d', strtotime($data['end_time']));

        return $data;
    }

    /*
     * ----------------------------------------------
     * 用户授信管理列表 | 重新授信按钮操作
     * Author：jk        Time：2016-09-12
     * ----------------------------------------------
     */
    public function retailCreditDelay($param = [])
    {
        // @表单验证
        if (count($param) <= 0) return ['status' => 2, 'message' => getMessage('b')];
        $test = ['request_type', 'user_id', 'line_credit', 'type', 'start_time', 'end_time', 'remark'];
        $is = 0;
        foreach ($param as $k => $v) {
            if (!in_array(strHtml($k), $test)) $is += 1;
        }
        if ($is > 0) return ['status' => 3, 'message' => getMessage('b')];
        // @验证是否授信
        $retail_credit_data = $this->findData(['user_id' => intval($param['user_id']), 'status' => 1]);
        if (empty($retail_credit_data)) return ['status' => 4, 'message' => getMessage('c')];
        // @验证授信是否过期
        if (date('Y-m-d', strtotime($retail_credit_data['end_time'])) < date('Y-m-d', $this->time)) {
            return [
                'status' => 10,
                'message' => getMessage('a-j')
            ];
        }
        // @验证已过期额度是否已还款完成
        if ($retail_credit_data['line_credit'] != $retail_credit_data['surplus_line_credit']) {
            return [
                'status' => 11,
                'message' => getMessage('a-n')
            ];
        }
        // @授信总额度验证
        if (strHtml($param['line_credit']) <= 0 || !is_numeric($param['line_credit'])) {
            return [
                'status' => 5,
                'message' => getMessage('i')
            ];
        }
        if (strHtml($param['line_credit']) > 100000) {
            return [
                'status' => 11,
                'message' => getMessage('b-a')
            ];
        }
        // @验证授信类型
        if (!in_array($param['type'], array_keys(changeRetailCreditChangeType()))) {
            return [
                'status' => 6,
                'message' => getMessage('a-f')
            ];
        }
        // @验证授信周期
        if (empty(strHtml($param['start_time'])) || empty(strHtml($param['end_time'])) || $param['start_time'] >= $param['end_time']) {
            return [
                'status' => 7,
                'message' => getMessage('a-g')
            ];
        }
        if (strtotime(date('Y-m-d', strtotime($param['end_time']))) < strtotime(date('Y-m-d'))) {
            return [
                'status' => 10,
                'message' => getMessage('a-m')
            ];
        }
        if (empty(trim($param['remark']))) return ['status' => 8, 'message' => getMessage('f')];
        // @重新授信 | 生成新数据 | 历史数据置为无效
        $update_retail_credit = [
            'update_time' => $this->time,
            'status' => 0
        ];
        $credit_card = creditCard();
        $insert_retail_credit = [
            'user_id' => intval($param['user_id']),
            'credit_card' => $credit_card,
            'line_credit' => strHtml($param['line_credit']),
            'use_line_credit' => 0,
            'surplus_line_credit' => strHtml($param['line_credit']),
            'create_time' => $this->time,
            'start_time' => date('Y-m-d 00:00:00', strtotime($param['start_time'])),
            'end_time' => date('Y-m-d 23:59:59', strtotime($param['end_time'])),
            'update_time' => $this->time,
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'status' => 1,
            'type' => intval($param['type']),
            'remark' => trim($param['remark'])
        ];
        // @记录额度变化
        if (($param['line_credit'] - $retail_credit_data['line_credit']) >= 0) {
            $change_credit = '+' . ($param['line_credit'] - $retail_credit_data['line_credit']);
        } else {
            $change_credit = '-' . ($retail_credit_data['line_credit'] - $param['line_credit']);
        }
        $insert_retail_credit_change = [
            'user_id' => intval($param['user_id']),
            'credit_card' => $credit_card,
            'befor_credit' => $retail_credit_data['surplus_line_credit'],
            'change_credit' => $change_credit,
            'after_credit' => $retail_credit_data['surplus_line_credit'] + ($param['line_credit'] - $retail_credit_data['line_credit']),
            'type' => 4,        # @重新授信
            'create_time' => $this->time,
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'remark' => trim($param['remark'])
        ];
        // @额度重新授信记录表
        $insert_retail_credit_term = [
            'user_id' => intval($param['user_id']),
            'credit_card' => $credit_card,
            'befor_credit' => $retail_credit_data['line_credit'],
            'change_credit' => $change_credit,
            'after_credit' => strHtml($param['line_credit']),
            'create_time' => $this->time,
            'start_time' => date('Y-m-d 00:00:00', strtotime($param['start_time'])),
            'end_time' => date('Y-m-d 23:59:59', strtotime($param['end_time'])),
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'remark' => trim($param['remark'])
        ];
        // @开启事务
        $retail_credit = M('retail_credit');
        $result_update_retail_credit = $this->updateData(['user_id' => intval($param['user_id']), 'status' => 1], $update_retail_credit);
        $result_insert_retail_credit = $this->addData($insert_retail_credit);
        $result_insert_retail_credit_change = $this->RetailCreditChange->addData($insert_retail_credit_change);
        $result_insert_retail_credit_term = $this->RetailCreditTerm->addData($insert_retail_credit_term);
        if ($result_update_retail_credit && $result_insert_retail_credit && $result_insert_retail_credit_change && $result_insert_retail_credit_term) {
            $retail_credit->commit();
        } else {
            // @事务回滚
            $retail_credit->rollback();
            return ['status' => 9, 'message' => getMessage('a-i')];
        }

        return ['status' => 1, 'message' => getMessage('a-h')];
    }


}


?>
