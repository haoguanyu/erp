<?php
namespace Common\Model;
use Common\Model\BaseModel;

/*
 * ----------------------------------------
 * 零售财务管理 零售订单表oil_retail_order | 模型
 * Author：jk        Time：2016-09-12
 * ----------------------------------------
 */

class RetailOrderModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];
        $this->time = date('Y-m-d H:i:s');

        $this->user = D('user');
        $this->dealer = D('dealer');
        $this->retailBalance = D('retail_balance');
        $this->retailCredit = D('retail_credit');
        $this->retailEquipment = D('retail_equipment');
        $this->retailBananceChange = D('retail_balance_change');
        $this->retailCreditChange = D('retail_credit_change');
        $this->retailOrderStatusTime = D('retail_order_status_time');
        $this->retailSettlementOrder = D('retail_settlement_order');
        $this->retailSettlementOrderList = D('retail_settlement_order_list');
    }

////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('retail_order')->field($field)->where($where)->order('id DESC')->select();
    }

    public function addData($data = [])
    {
        if (empty($data)) return false;

        return M('retail_order')->add($data);
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('retail_order')->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (count($where) <= 0 || empty($data)) return false;

        return M('retail_order')->where($where)->save($data);
    }


//////////////////////////////////业务处理层/////////////////////////////////////////

    /*
     * ----------------------------------------------
     * 零售财务管理订单对账列表数据
     * Author：jk        Time：2016-09-12
     * ----------------------------------------------
     */
    public function orderReconciliationList($param = [])
    {
        ini_set('memory_limit', '1280M');
        // @默认查询订单数据 | 订单状态服务完成
        $where['status'] = 100;
        $where['zhaoyou_status'] = 10;
        if (count($param) > 0) {
            switch (strHtml($param['pay_status'])) {
                case '已对账':
                    $where['pay_status'] = 1;
                    break;
                case '未对账':
                    $where['pay_status'] = 0;
                    break;
            }
            if (isset($param['order_number']) && !empty(strHtml($param['order_number']))) $where['order_number'] = strHtml($param['order_number']);
            if (isset($param['region']) && !empty(strHtml($param['region']))) $where['region'] = strHtml($param['region']);
            if (isset($param['company_name']) && !empty(strHtml($param['company_name']))) $where['company_name'] = ['LIKE', '%' . strHtml($param['company_name'] . '%')];
            if (isset($param['dealer_id']) && !empty(strHtml($param['dealer_id']))) $where['dealer_id'] = strHtml($param['dealer_id']);
            if (isset($param['user_phone']) && !empty(strHtml($param['user_phone']))) $where['user_phone'] = strHtml($param['user_phone']);
            if (isset($param['user_name']) && !empty(strHtml($param['user_name']))) $where['user_name'] = strHtml($param['user_name']);
            if (!empty(trim($param['start_time'])) && empty(trim($param['end_time']))) {
                $where['create_time'] = ['egt', date('Y-m-d 00:00:00', strtotime($param['start_time']))];
            } elseif (!empty(trim($param['end_time'])) && empty(trim($param['start_time']))) {
                $where['create_time'] = ['elt', date('Y-m-d 23:59:59', strtotime($param['end_time']))];
            } elseif (!empty(trim($param['start_time'])) && !empty(trim($param['end_time']))) {
                $where['create_time'] = ['between', [date('Y-m-d 00:00:00', strtotime($param['start_time'])), date('Y-m-d 23:59:59', strtotime($param['end_time']))]];
            }
            $t_start_time = $t_end_time = false;
            if (isset($param['t_start_time']) && !empty(trim($param['t_start_time']))) {
                $t_start_time = true;
            }
            if (isset($param['t_end_time']) && !empty(trim($param['t_end_time']))) {
                $t_end_time = true;
            }
            if ($t_start_time && !$t_end_time) {
                $where['trans_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00'))];
            } elseif (!$t_start_time && $t_end_time) {
                $where['trans_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))];
            } elseif ($t_start_time && $t_end_time) {
                $where['trans_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))]];
            } elseif (!isset($param['t_start_time'])) {
                $where['trans_time'] = ['egt', date('Y-m-d 00:00:00', strtotime('-15 days', time()))];
            }
        }

        $data = $this->selectData('*', $where);
        if (count($data) > 0) {
            foreach ($data as $k => $v) {
                $user_id[] = intval($v['user_id']);
                $order_number[] = strHtml($v['order_number']);
                $dealer_id[] = intval($v['operation_dealer_id']);
            }
            $user_id = array_unique($user_id);
            $order_number = array_unique($order_number);
            $dealer_data = $retail_balance_change_data = $retail_credit_change_data = $this->arr;
            if (!empty($dealer_id)) {
                $dealer_id = array_unique($dealer_id);
                $dealer_data = $this->dealer->selectData('id,dealer_name', ['id' => ['IN', $dealer_id]]);
                foreach ($dealer_data as $k => $v) {
                    $dealer_data_[$v['id']] = $v;
                }
            }
            $user_data_ = $this->user->selectData('id,user_name,user_phone', ['id' => ['IN', $user_id], 'is_available' => ['NEQ', 1]]);
            // @用户名 手机号码
            foreach ($user_data_ as $k => $v) {
                $user_data[$v['id']] = $v;
            }
            // @账户余额
            $retail_balance_data_ = $this->retailBalance->selectData('user_id,account_balance', ['user_id' => ['IN', $user_id]]);
            if (count($retail_balance_data_) > 0) {
                foreach ($retail_balance_data_ as $k => $v) {
                    $retail_balance_data[intval($v['user_id'])] = $v;

                }
            }
            // @授信可用额度
            $retail_credit_data_ = $this->retailCredit->selectData('user_id,surplus_line_credit', ['user_id' => ['IN', $user_id], 'status' => 1]);
            if (count($retail_credit_data_) > 0) {
                foreach ($retail_credit_data_ as $k => $v) {
                    $retail_credit_data[intval($v['user_id'])] = $v;

                }
            }
            // @设备表中 单价 升量
            $retail_equipment_data_ = $this->retailEquipment->selectData('retail_order_number,num,price', ['retail_order_number' => ['IN', $order_number], 'status' => 1, 'type_goods' => 6]);
            foreach ($retail_equipment_data_ as $k => $v) {
                $retail_equipment_data[$v['retail_order_number']] = $v;
            }
            // @地区转换
            $provice = province()['all'];
            // @对账使用余额金额
            $retail_balance_change_data_ = $this->retailBananceChange->selectData('change_price,retail_order_number', ['retail_order_number' => ['IN', $order_number], 'type' => ['IN', [2, 3]]]);
            if (count($retail_balance_change_data_) > 0) {
                foreach ($retail_balance_change_data_ as $k => $v) {
                    $retail_balance_change_data[$v['retail_order_number']] = $v;

                }
            }
            // @对账使用额度金额
            $retail_credit_change_data_ = $this->retailCreditChange->selectData('change_credit,retail_order_number', ['retail_order_number' => ['IN', $order_number], 'type' => 3]);
            if (count($retail_credit_change_data_) > 0) {
                foreach ($retail_credit_change_data_ as $k => $v) {
                    $retail_credit_change_data[$v['retail_order_number']] = $v;

                }
            }
            // @整合数据
            foreach ($data as $k => $v) {
                # *********** 修改collection_source字段，<lizhipeng @date 2017-03-03> ***************#
                if ($retail_balance_data[$v['user_id']]['account_balance'] >= $v['actual_price'] && $v['pay_status'] != 1) {
                    $data[$k]['collection_source'] = '可对账';
                } else {
                    $data[$k]['collection_source'] = '-';
                }
                $data[$k]['user_name'] = strHtml($user_data[$v['user_id']]['user_name']);
                $data[$k]['user_phone'] = strHtml($user_data[$v['user_id']]['user_phone']);
                $data[$k]['account_balance'] = empty($retail_balance_data[$v['user_id']]['account_balance']) ? '0.00' : number_format($retail_balance_data[$v['user_id']]['account_balance'], 2);
                $data[$k]['surplus_line_credit'] = empty($retail_credit_data[$v['user_id']]['surplus_line_credit']) ? '0.00' : number_format($retail_credit_data[$v['user_id']]['surplus_line_credit'], 2);
                $data[$k]['equipment_num'] = intval($retail_equipment_data[$v['order_number']]['num']);
                $data[$k]['equipment_price'] = empty(strHtml($retail_equipment_data[$v['order_number']]['price'])) ? '0.00' : number_format($retail_equipment_data[$v['order_number']]['price'], 2);
                $data[$k]['actual_price'] = number_format($v['actual_price'], 2);
                $data[$k]['receipts_user_price'] = number_format($v['receipts_user_price'], 2);
                $data[$k]['discount_price'] = number_format($v['discount_price'], 2);
                $data[$k]['change_price'] = empty($retail_balance_change_data[$v['order_number']]['change_price']) ? '0.00' : number_format($retail_balance_change_data[$v['order_number']]['change_price'], 2);
                $data[$k]['change_credit'] = empty($retail_credit_change_data[$v['order_number']]['change_credit']) ? '0.00' : number_format($retail_credit_change_data[$v['order_number']]['change_credit'], 2);
                $data[$k]['create_time'] = date('Y-m-d', strtotime($v['create_time']));
                $data[$k]['region_val'] = $provice[$v['region']];
                $data[$k]['dealer_name'] = strHtml($dealer_data_[$v['operation_dealer_id']]['dealer_name']);
                $data[$k]['status_val'] = changeOrderReconciliationStatus(intval($v['pay_status']));
            }
        }

        return $data;
    }

    /*
     * ----------------------------------------------
     * 零售财务管理订单对账按钮页面数据显示处理 | 对账AJAX
     * Author：jk        Time：2016-09-14
     * ----------------------------------------------
     */
    public function orderReconciliationHandle($param = [], $is = false)
    {
        $retail_order_data = $this->findData(['order_number' => strHtml($param['order_number']), 'status' => ['NEQ', 0]]);
        // @对账按钮ajax验证
        if ($is) {
            if (empty($retail_order_data) || $retail_order_data['status'] != 100) {
                return ['status' => 2, 'message' => getMessage('b')];
            }
            if ($retail_order_data['zhaoyou_status'] != 10) {
                return ['status' => 5, 'message' => getMessage('b-k')];
            }
            if ($retail_order_data['pay_status'] == 1) {
                return ['status' => 6, 'message' => getMessage('b-l')];
            }
        }
        // @账户余额
        $retail_balance_data = $this->retailBalance->findData(['user_id' => intval($retail_order_data['user_id'])]);
        // @账户授信额度 | 授信到期时间未过期
        $retail_credit_data = $this->retailCredit->findData(['user_id' => intval($retail_order_data['user_id']), 'status' => 1]);
        if (!empty($retail_credit_data) && strtotime(date('Y-m-d', strtotime($retail_credit_data['end_time']))) < strtotime(date('Y-m-d', strtotime($this->time)))) {
            $retail_credit_data = [];
        }
        // @处理数据
        $retail_order_data['account_balance'] = !empty($retail_balance_data) ? $retail_balance_data['account_balance'] : '0';
        $retail_order_data['surplus_line_credit'] = !empty($retail_credit_data) ? $retail_credit_data['surplus_line_credit'] : '0';
        // @账户余额 + 账户授信可用额度 >= 应收金额
        $order_data = [
            'actual_price' => number_format($retail_order_data['actual_price'], 2),
            'account_balance' => number_format($retail_order_data['account_balance'], 2),
            'surplus_line_credit' => number_format($retail_order_data['surplus_line_credit'], 2),
            'order_number' => strHtml($retail_order_data['order_number'])
        ];
        // if(($retail_order_data['account_balance'] + $retail_order_data['surplus_line_credit']) >= $retail_order_data['actual_price']){
        if (($retail_order_data['account_balance']) >= $retail_order_data['actual_price']) {
            // @账户余额够对账
            // if($retail_order_data['account_balance'] >= $retail_order_data['actual_price']){
            $data = [
                'type' => 1,
                'message' => '<font color="red">本次将从账户余额中扣除 ' . number_format($retail_order_data['actual_price'], 2) . '元！</font>',
                'order_data' => $order_data
            ];
            // }
            // elseif($retail_order_data['account_balance'] < $retail_order_data['actual_price']){
            //     // @账户余额不足
            //    // $reduce_retail_balance = $retail_order_data['actual_price'] - $retail_order_data['account_balance'];
            //     $data = [
            //         'type'       => 2,
            //         //'message'    => '<font color="red">本次将从账户余额中扣除 '.number_format($retail_order_data['account_balance'], 2).' 元 及授信可用额度中扣除 '.number_format($reduce_retail_balance, 2).' 元！</font>',
            //         'message'    => '<font color="red">本次将从账户余额中扣除 '.number_format($retail_order_data['account_balance'], 2).' 元 ！</font>',
            //         'order_data' => $order_data
            //     ];
            // }
        } else {
            // @账户余额 + 账户授信可用额度 < 应收金额
            //$retail_order_data['surplus_price'] = $retail_order_data['actual_price'] - $retail_order_data['account_balance'] - $retail_order_data['surplus_line_credit'];
            $retail_order_data['surplus_price'] = $retail_order_data['actual_price'] - $retail_order_data['account_balance'];// - $retail_order_data['surplus_line_credit'];
            $order_data['surplus_price'] = number_format($retail_order_data['surplus_price'], 2);
            $data = [
                'type' => 3,
                // 'message'         => '<font color="red">本次将从账户余额中扣除 '.number_format($retail_order_data['account_balance'], 2).' 元 及授信可用额度中扣除 '.number_format($retail_order_data['surplus_line_credit'], 2).' 元，还需对账 '.number_format($retail_order_data['surplus_price'], 2).' 元！</font>',
                'message' => '<font color="red">本次将从账户余额中扣除 ' . number_format($retail_order_data['account_balance'], 2) . ' 元 ，还需对账 ' . number_format($retail_order_data['surplus_price'], 2) . ' 元！</font>',
                'order_data' => $order_data,
                'collection_type' => collectionType()
            ];
        }
        // @对账按钮ajax======================
        if ($is) {
            $retail_order = M('retail_order');
            // @开启事务
            $retail_order->startTrans();
            $update_retail_balance = [
                'consumption_time' => $this->time
            ];
            $insert_retail_balance_change = [
                'user_id' => intval($retail_order_data['user_id']),
                'retail_order_number' => strHtml($retail_order_data['order_number']),
                'type' => 2,
                'create_time' => $this->time,
                'remark' => trim($param['remark']),
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            ];
            $update_retail_order = [
                // 'actual_price'        => 0,
                'receipts_user_price' => $retail_order_data['actual_price'],
                'update_time' => $this->time,
                'update_status_time' => $this->time,
                'pay_status' => 1
            ];
            $insert_retail_order_status_time = [
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
                'retail_order_number' => strHtml($retail_order_data['order_number']),
                'retail_order_status_time' => $this->time,
                'retail_order_status' => 100,
                'retail_order_pay_status' => 1,
                'retail_order_settlement_status' => $retail_order_data['settlement_status']
            ];
            // $update_retail_credit = [
            //     'user_id'     => intval($retail_order_data['user_id']),
            //     'update_time' => $this->time
            // ];
            // $insert_retail_credit_change = [
            //     'user_id'             => intval($retail_order_data['user_id']),
            //     'retail_order_number' => strHtml($retail_order_data['order_number']),
            //     'type'                => 3,
            //     'create_time'         => $this->time,
            //     'remark'              => trim($param['remark']),
            //     'dealer_id'           => $_SESSION['adminInfo']['id'],
            //     'dealer_name'         => strHtml($_SESSION['adminInfo']['dealer_name']),
            // ];
            $result_retail_balance = $result_retail_balance_change = $result_retail_credit = $result_retail_credit_change = true;
            switch ($data['type']) {
                case 1:
                    // @扣除账户余额[记录扣除操作] | 更新订单信息 | 记录订单状态时间变动表
                    $update_retail_balance['account_balance'] = $retail_balance_data['account_balance'] - $retail_order_data['actual_price'];
                    $update_retail_balance['use_balance'] = $retail_balance_data['use_balance'] + $retail_order_data['actual_price'];
                    $insert_retail_balance_change['after_price'] = $update_retail_balance['account_balance'];
                    $insert_retail_balance_change['change_price'] = '-' . $retail_order_data['actual_price'];
                    $insert_retail_balance_change['befor_price'] = $retail_balance_data['account_balance'];
                    // @mysql操作
                    $result_retail_balance = $this->retailBalance->updateData(['user_id' => intval($retail_order_data['user_id'])], $update_retail_balance);
                    $result_retail_balance_change = $this->retailBananceChange->addData($insert_retail_balance_change);
                    $result_retail_order = $this->updateData(['order_number' => strHtml($param['order_number'])], $update_retail_order);
                    $result_retail_order_status_time = $this->retailOrderStatusTime->addData($insert_retail_order_status_time);
                    // @事务判断
                    if ($result_retail_balance && $result_retail_balance_change && $result_retail_order && $result_retail_order_status_time) {
                        $retail_order->commit();
                    } else {
                        // @事务回滚
                        $retail_order->rollback();
                        return ['status' => 3, 'message' => getMessage('a-k')];
                    }
                    break;
                default:
                    // if($data['type'] == 2){
                    //     // @扣除账户余额[记录扣除操作] | 扣除账户授信可用额度[记录扣除授信操作] | 更新订单信息
                    //     if(!empty($retail_balance_data)){
                    //         $update_retail_balance['account_balance']     = 0;
                    //         $update_retail_balance['use_balance']         = $retail_balance_data['use_balance'] + $retail_balance_data['account_balance'];
                    //         $insert_retail_balance_change['befor_price']  = $retail_balance_data['account_balance'];
                    //         $insert_retail_balance_change['change_price'] = '-'.$retail_balance_data['account_balance'];
                    //         $insert_retail_balance_change['after_price']  = 0;
                    //         $result_retail_balance        = $this->retailBalance->updateData(['user_id' => intval($retail_order_data['user_id'])], $update_retail_balance);
                    //         $result_retail_balance_change = $this->retailBananceChange->addData($insert_retail_balance_change);
                    //     }
                    //     if(!empty($retail_credit_data)){
                    //         $update_retail_credit['use_line_credit']      = $retail_credit_data['use_line_credit'] + ($retail_order_data['actual_price'] - $retail_balance_data['account_balance']);
                    //         $update_retail_credit['surplus_line_credit']  = $retail_credit_data['surplus_line_credit'] - ($retail_order_data['actual_price'] - $retail_balance_data['account_balance']);
                    //         $insert_retail_credit_change['credit_card']   = strHtml($retail_credit_data['credit_card']);
                    //         $insert_retail_credit_change['befor_credit']  = $retail_credit_data['surplus_line_credit'];
                    //         $insert_retail_credit_change['change_credit'] = '-'.($retail_order_data['actual_price'] - $retail_balance_data['account_balance']);
                    //         $insert_retail_credit_change['after_credit']  = $update_retail_credit['surplus_line_credit'];
                    //         $result_retail_credit        = $this->retailCredit->updateData(['user_id' => intval($retail_order_data['user_id']), 'status' => 1], $update_retail_credit);
                    //         $result_retail_credit_change = $this->retailCreditChange->addData($insert_retail_credit_change);
                    //     }
                    // }else
                    $result_retail_balance = $result_retail_balance_change = true;
                    if ($data['type'] == 3) {
                        // @扣除账户余额[记录扣除操作] | 扣除账户授信可用额度[记录扣除授信操作] | 更新订单信息
                        if (!empty($retail_balance_data) && $retail_balance_data['account_balance'] > 0) {
                            $update_retail_balance['account_balance'] = 0;
                            $update_retail_balance['use_balance'] = $retail_balance_data['use_balance'] + $retail_balance_data['account_balance'];
                            $insert_retail_balance_change['befor_price'] = $retail_balance_data['account_balance'];
                            $insert_retail_balance_change['change_price'] = '-' . $retail_balance_data['account_balance'];
                            $insert_retail_balance_change['after_price'] = 0;
                            $result_retail_balance = $this->retailBalance->updateData(['user_id' => intval($retail_order_data['user_id'])], $update_retail_balance);
                            $result_retail_balance_change = $this->retailBananceChange->addData($insert_retail_balance_change);
                        }
                        // if(!empty($retail_credit_data)){
                        //     $update_retail_credit['use_line_credit']         = $retail_credit_data['line_credit'];
                        //     $update_retail_credit['surplus_line_credit']     = 0;
                        //     $insert_retail_credit_change['credit_card']   = strHtml($retail_credit_data['credit_card']);
                        //     $insert_retail_credit_change['befor_credit']     = $retail_credit_data['surplus_line_credit'];
                        //     $insert_retail_credit_change['change_credit']    = '-'.$retail_credit_data['surplus_line_credit'];
                        //     $insert_retail_credit_change['after_credit']     = 0;
                        //     $result_retail_credit        = $this->retailCredit->updateData(['user_id' => intval($retail_order_data['user_id']), 'status' => 1], $update_retail_credit);
                        //     $result_retail_credit_change = $this->retailCreditChange->addData($insert_retail_credit_change);
                        // }
                        $update_retail_order['collection_source'] = strHtml($param['type']);
                        $update_retail_order['collection_source_remark'] = strHtml($param['remark']);
                    }
                    $result_retail_order = $this->updateData(['order_number' => strHtml($param['order_number'])], $update_retail_order);
                    $result_retail_order_status_time = $this->retailOrderStatusTime->addData($insert_retail_order_status_time);
                    // @事务判断
                    if ($result_retail_balance && $result_retail_balance_change && $result_retail_order && $result_retail_order_status_time) {
                        $retail_order->commit();
                    } else {
                        // @事务回滚
                        $retail_order->rollback();
                        return ['status' => 4, 'message' => getMessage('a-k')];
                    }
                    break;
            }
            // @=======油沃客-订单付款通知 <ypm> 2016-10-20=========
            $title = '找油网零售-订单付款通知';
            $dealer_id = $retail_order_data['dealer_id'];
            $dealer_data = D('dealer')->where(['id' => intval($dealer_id), 'is_available' => 0])->find();
            $code = '您好，您的客户' . $retail_order_data['company_name'] . '，订单（单号' . $retail_order_data['order_number'] . ')已经付款，财务已收款。';
            if (!empty($dealer_data['dealer_email'])) {
                sendEmail($title, $code, $dealer_data['dealer_email']);
                sendEmail($title, $code, 'ewonee@51zhaoyou.com');//王艺蓉
                sendEmail($title, $code, 'yangrui@51zhaoyou.com');
            }
            // @发送给交易员短信
            if (!empty($dealer_data['dealer_phone'])) {
                sendPhone($code . '回复TD退订', strHtml($dealer_data['dealer_phone']));
            }
            //极光推送
            // $jiguanMessage=new \Home\Event\JiguanMessageEvent();
            // $jiguanMessage->pushMessage($dealer_id,'油沃客',1,$title,$code,'',$retail_order_data['order_number'],1);
            sendJpushMessage($dealer_id, '油沃客', 1, $title, $code, '', $retail_order_data['order_number'], 1);
            // @============================end=======================================
            return ['status' => 1, 'message' => getMessage('a-l')];
        }

        return $data;
    }

    /*
     * ----------------------------------------------
     * 零售财务管理订单已结算 未结算列表 数据转换
     * Author：jk        Time：2016-09-16
     * ----------------------------------------------
     */
    public function reatailOrderSettledList($settlement_status = [])
    {
        if (count($settlement_status) <= 0) return [];
        // @目前只查询上海地区服务商 | 上海地区订单
        $getCompanyNames = getCompanyNames(['region_code' => '1_25_321', 'status' => 1]);
        foreach ($getCompanyNames as $k => $v) {
            $service_provider[$v] = [];
        }
        $filed = 'id,order_number,payment_price,status,pay_status,settlement_status,sale_company_id,sale_company_name,region';
        $retail_order_data = $this->selectData($filed, ['sale_company_name' => ['IN', array_keys($service_provider)], 'zhaoyou_status' => 10, 'status' => ['EGT', 100], 'settlement_status' => ['IN', $settlement_status]]);
        foreach ($service_provider as $k => $v) {
            $service_provider[$k]['company_name'] = $k;
        }
        if (count($retail_order_data) > 0) {
            foreach ($retail_order_data as $k => $v) {
                $order_number[] = $v['order_number'];
            }
            // @获取设备表中油的升数
            $retail_equipment_data_ = $this->retailEquipment->selectData('retail_order_number,num', ['status' => 1, 'type_goods' => 6]);
            foreach ($retail_equipment_data_ as $k => $v) {
                $retail_equipment_data[$v['retail_order_number']] = $v['num'];
            }
            // @组织数据
            foreach ($service_provider as $k => $v) {
                foreach ($retail_order_data as $k1 => $v1) {
                    if ($k == $v1['sale_company_name']) {
                        // @已结算
                        if (intval($v1['settlement_status']) == 0 || intval($v1['settlement_status']) == 2) {
                            $service_provider[$k]['num'] += $retail_equipment_data[$v1['order_number']];
                            $service_provider[$k]['payment_price'] += $v1['payment_price'];
                            $service_provider[$k]['count'] += 1;
                        } elseif (intval($v1['settlement_status']) == 1) {
                            // @未结算 预结算
                            $service_provider[$k]['settl_num'] += $retail_equipment_data[$v1['order_number']];
                            $service_provider[$k]['settl_count'] += 1;
                        }
                    }
                }
            }
        }

        return $service_provider;
    }

    /*
     * ----------------------------------------------
     * 零售财务管理订单已结算 未结算列表 | 详情
     * Author：jk        Time：2016-09-16
     * ----------------------------------------------
     */
    public function reatailOrderSettledDetails($sale_company_name = '', $settlement_status = [], $param = [])
    {
        if (count($settlement_status) <= 0) return [];
        $filed = 'id,user_id,order_number,sale_company_name,paid_price,type,rank,level,receipts_user_price,payment_price,create_time,update_status_time,trans_time,trans_time_end';
        $where = [
            'status' => ['EGT', 100],
            'zhaoyou_status' => 10,
            'settlement_status' => ['IN', $settlement_status],
            'sale_company_name' => strHtml($sale_company_name)
        ];
        if (!empty(trim($param['type']))) {
            $where['type'] = trim($param['type']);
        }
        // if(count($settlement_status) == 1 && $settlement_status[0] == 2){
        //     $time = 'update_status_time';
        // }else{
        //     $time = 'trans_time';
        // }
        $time = 'trans_time';
        if (!empty(trim($param['start_time'])) && empty(trim($param['end_time']))) {
            $where[$time] = ['egt', date('Y-m-d 00:00:00', strtotime($param['start_time']))];
        } elseif (!empty(trim($param['end_time'])) && empty(trim($param['start_time']))) {
            $where[$time] = ['elt', date('Y-m-d 23:59:59', strtotime($param['end_time']))];
        } elseif (!empty(trim($param['start_time'])) && !empty(trim($param['end_time']))) {
            $where[$time] = ['between', [date('Y-m-d 00:00:00', strtotime($param['start_time'])), date('Y-m-d 23:59:59', strtotime($param['end_time']))]];
        }
        $retail_order_data = $this->selectData($filed, $where);
        // @设备表中获取升量
        if (count($retail_order_data) > 0) {
            foreach ($retail_order_data as $k => $v) {
                $order_number[] = $v['order_number'];
                $user_id[] = $v['user_id'];
            }
            $retail_equipment_data_ = $this->retailEquipment->selectData('retail_order_number,num', ['retail_order_number' => ['IN', $order_number], 'type_goods' => 6, 'status' => 1]);
            foreach ($retail_equipment_data_ as $k => $v) {
                $retail_equipment_data[$v['retail_order_number']] = $v['num'];

            }
            $user_data_ = $this->user->selectData('id,user_name,user_phone', ['id' => ['IN', array_unique($user_id)], 'is_available' => ['NEQ', 1]]);
            foreach ($user_data_ as $k => $v) {
                $user_data[$v['id']] = $v;

            }
            foreach ($retail_order_data as $k => $v) {
                $retail_order_data[$k]['paid_price'] = number_format($v['paid_price'], 2);
                $retail_order_data[$k]['receipts_user_price'] = number_format($v['receipts_user_price'], 2);
                $retail_order_data[$k]['payment_price'] = number_format($v['payment_price'], 2);
                $retail_order_data[$k]['num'] = $retail_equipment_data[$v['order_number']];
                $retail_order_data[$k]['user_name'] = $user_data[$v['user_id']]['user_name'];
                $retail_order_data[$k]['user_phone'] = $user_data[$v['user_id']]['user_phone'];
            }
        }

        return $retail_order_data;
    }

    /*
     * ----------------------------------------------
     * 未结算订单列表 | 待结算详情列表 | 加入结算单按钮数据返回
     * Author：jk        Time：2016-09-18
     * ----------------------------------------------
     */
    public function retailFinanceSettlement($id = '')
    {
        if (!empty(strHtml($id))) {
            $retail_order_id = explode(',', substr($id, 0, strlen($id) - 1));
            $retail_order_data = $this->selectData('id,order_number,type', ['id' => ['IN', $id], 'status' => ['EGT', 100], 'zhaoyou_status' => 10, 'settlement_status' => 0]);
            $id = '';
            foreach ($retail_order_data as $k => $v) {
                $order_number[] = $v['order_number'];
                $type[] = strHtml($v['type']);
                $id .= $v['id'] . ',';
            }
            $retail_equipment_data_ = $this->retailEquipment->selectData('num,retail_order_number', ['retail_order_number' => ['IN', $order_number], 'type_goods' => 6, 'status' => 1]);
            foreach ($retail_equipment_data_ as $k => $v) {
                $retail_equipment_data[$v['retail_order_number']] = $v['num'];

            }
            foreach ($retail_order_data as $k => $v) {
                $retail_order_data[$k]['num'] = $retail_equipment_data[$v['order_number']];
                $num += $retail_order_data[$k]['num'];
            }
            $type = array_unique($type);
            foreach ($type as $v) {
                $str_type .= $v . ' ';
            }
            $data = [
                'count' => count($retail_order_data),
                'num' => $num,
                'str' => $id,
                'str_type' => $str_type,
                'is' => 1
            ];
            if (count($type) > 1) $data['is'] = 0;

            return $data;
        }
    }

    /*
     * ----------------------------------------------
     * 未结算订单列表 | 待结算详情列表 | 加入结算单按钮ajax
     * Author：jk        Time：2016-09-18
     * ----------------------------------------------
     */
    public function retailFinanceSettlementAjax($param = [])
    {
        if (count($param) <= 0) return ['status' => 2, 'message' => 'a'];
        $test = ['request_type', 'str', 'paid_price', 'payment_price'];
        $is = 0;
        foreach ($param as $k => $v) {
            if (!in_array(trim($k), $test)) $is += 1;
        }
        if (count($param) != count($test) || $is > 0 || empty(strHtml($param['str']))) {
            return [
                'status' => 3,
                'message' => getMessage('b')
            ];
        }
        // @验证单价金额格式
        if (empty(trim($param['paid_price'])) || !is_numeric($param['paid_price'])) {
            return [
                'status' => 4,
                'message' => getMessage('a-o')
            ];
        }
        if ($param['paid_price'] > 99) {
            return [
                'status' => 7,
                'message' => getMessage('a-z')
            ];
        }
        $retail_order_id = explode(',', substr($param['str'], 0, strlen($param['str']) - 1));
        $retail_order_data = $this->selectData('order_number,sale_company_name,status,pay_status', ['id' => ['IN', $retail_order_id], 'settlement_status' => 0]);
        if (count($retail_order_data) <= 0 || count($retail_order_data) != count($retail_order_id)) {
            return [
                'status' => 5,
                'message' => getMessage('b')
            ];
        }
        foreach ($retail_order_data as $k => $v) {
            $order_number[] = $v['order_number'];
            $status[$v['order_number']] = 1;
        }
        $retail_equipment_data_ = $this->retailEquipment->selectData('num,price,retail_order_number', ['retail_order_number' => ['IN', $order_number], 'type_goods' => 6, 'status' => 1]);
        foreach ($retail_equipment_data_ as $k => $v) {
            $retail_equipment_data[$v['retail_order_number']] = $v;

        }
        $count_num = 0;
        foreach ($retail_order_data as $k => $v) {
            // @预结算单订单列表批量插入
            $insert_all_retail_settlement_order_list[] = [
                'retail_order_number' => $v['order_number'],
                'num' => $retail_equipment_data[$v['order_number']]['num'],
                'paid_price' => strHtml($param['paid_price']),
                'status' => 0,
                'create_time' => $this->time,
                'sale_company_name' => trim($retail_order_data['0']['sale_company_name']),
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            ];
            $count_num += $retail_equipment_data[$v['order_number']]['num'];
            // @批量记录订单状态修改时间
            $insert_retail_order_status_time[] = [
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
                'retail_order_number' => $v['order_number'],
                'retail_order_status_time' => $this->time,
                'retail_order_status' => $v['status'],
                'retail_order_pay_status' => $v['pay_status'],
                'retail_order_settlement_status' => 1
            ];
            // @订单表应付金额字段
            $payable_price[$v['order_number']] = $retail_equipment_data[$v['order_number']]['num'] * strHtml($param['paid_price']);
        }
        // @批量更新订单表状态为预结算
        $order_number_str = implode(',', array_keys($status));
        $sql = "UPDATE oil_retail_order SET settlement_status = CASE order_number ";
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . $v . ' ';
        }
        $sql .= ' END,update_status_time = CASE order_number ';
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        $sql .= ' END,update_time = CASE order_number ';
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        $sql .= ' END,paid_price = CASE order_number ';
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . strHtml($param['paid_price']) . '"' . ' ';
        }
        $sql .= ' END,payable_price = CASE order_number ';
        foreach ($payable_price as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $v . '"' . ' ';
        }
        $sql .= "END WHERE order_number IN ($order_number_str)";
        // @如果预结算表没有待结算订单 | 则新增待结算列表数据且结算单表新增结算单
        $retail_settlement_order_data = $this->retailSettlementOrder->findData(['sale_company_name' => $retail_order_data['0']['sale_company_name'], 'status' => 0]);
        if (empty($retail_settlement_order_data)) {
            // @生成结算单号
            $settlement_number = creditCard();
            foreach ($insert_all_retail_settlement_order_list as $k => $v) {
                $insert_all_retail_settlement_order_list[$k]['settlement_number'] = $settlement_number;
            }
            // @结算单数据插入
            $insert_retail_settlement_order = [
                'settlement_number' => $settlement_number,
                'retail_order_number' => serialize($order_number),
                'count_num' => $count_num,
                'count_price' => $count_num * strHtml($param['paid_price']),
                'sale_company_name' => trim($retail_order_data['0']['sale_company_name']),
                'create_time' => $this->time,
                'status' => 0
            ];
            // 开启事务
            $retail_order = M('retail_order');
            $retail_order->startTrans();
            $result_retail_settlement_order_list = $this->retailSettlementOrderList->addAllData($insert_all_retail_settlement_order_list);
            $result_retail_settlement_order = $this->retailSettlementOrder->addData($insert_retail_settlement_order);
            $result_retail_order = $retail_order->execute($sql);
        } else {
            // @如果结算单表有待结算订单 | 则新增待结算列表数据且结算单表更新结算单
            foreach ($insert_all_retail_settlement_order_list as $k => $v) {
                $insert_all_retail_settlement_order_list[$k]['settlement_number'] = $retail_settlement_order_data['settlement_number'];
            }
            // @更新结算单 升量 订单号
            $update_reatil_settlement_order = [
                'retail_order_number' => serialize(array_merge(unserialize($retail_settlement_order_data['retail_order_number']), $order_number)),
                'count_num' => $retail_settlement_order_data['count_num'] + $count_num,
                'count_price' => $retail_settlement_order_data['count_price'] + $count_num * strHtml($param['paid_price'])
            ];
            // 开启事务
            $retail_order = M('retail_order');
            $retail_order->startTrans();
            $result_retail_settlement_order_list = $this->retailSettlementOrderList->addAllData($insert_all_retail_settlement_order_list);
            $result_retail_settlement_order = $this->retailSettlementOrder->updateData(['id' => $retail_settlement_order_data['id']], $update_reatil_settlement_order);
            $result_retail_order = $retail_order->execute($sql);
        }
        $result_retail_order_status_time = $this->retailOrderStatusTime->addAllData($insert_retail_order_status_time);
        if ($result_retail_settlement_order_list && $result_retail_settlement_order && $result_retail_order == count($retail_order_data)) {
            $retail_order->commit();
        } else {
            // @事务回滚
            $retail_order->rollback();
            return ['status' => 6, 'message' => getMessage('a-p')];
        }

        return ['status' => 1, 'message' => getMessage('a-q')];
    }

    /*
     * ----------------------------------------------
     * 结算单列表数据
     * Author：jk        Time：2016-09-19
     * ----------------------------------------------
     */
    public function reatailOrderSettlementSheet($sale_company_name)
    {
        if (empty(strHtml($sale_company_name))) return $this->arr;
        $filed = 'order_number,type,rank,level,user_id,create_time,trans_time,trans_time_end,status,pay_status,settlement_status';
        $retail_order = $this->selectData($filed, ['settlement_status' => 1, 'sale_company_name' => strHtml($sale_company_name), 'status' => ['GT', 10]]);
        if (count($retail_order) <= 0) return $this->arr;

        foreach ($retail_order as $k => $v) {
            $order_number[] = $v['order_number'];
            $user_id[] = intval($v['user_id']);
        }
        // @用户信息
        $user_data_ = $this->user->selectData('id,user_name,user_phone', ['id' => ['IN', array_unique($user_id)], 'is_available' => ['NEQ', 1]]);
        foreach ($user_data_ as $k => $v) {
            $user_data[$v['id']] = $v;

        }
        // @预结算列表数据
        $retail_reatil_settlement_order_list_ = $this->retailSettlementOrderList->selectData('num,paid_price,retail_order_number', ['retail_order_number' => ['IN', $order_number], 'status' => 0, 'sale_company_name' => strHtml($sale_company_name)]);
        foreach ($retail_reatil_settlement_order_list_ as $k => $v) {
            $retail_reatil_settlement_order_list[$v['retail_order_number']] = $v;

        }
        // @处理数据
        foreach ($retail_order as $k => $v) {
            $retail_order[$k]['user_name'] = $user_data[intval($v['user_id'])]['user_name'];
            $retail_order[$k]['user_phone'] = $user_data[intval($v['user_id'])]['user_phone'];
            $retail_order[$k]['num'] = $retail_reatil_settlement_order_list[$v['order_number']]['num'];
            $retail_order[$k]['paid_price'] = number_format($retail_reatil_settlement_order_list[$v['order_number']]['paid_price'], 2);
            $retail_order[$k]['count_price'] = number_format($retail_order[$k]['num'] * $retail_reatil_settlement_order_list[$v['order_number']]['paid_price'], 2);
            $retail_order[$k]['c_price'] = $retail_order[$k]['num'] * $retail_reatil_settlement_order_list[$v['order_number']]['paid_price'];
        }

        return $retail_order;
    }

    /*
     * ----------------------------------------------
     * 结算单列表数据 | 删除
     * Author：jk        Time：2016-09-19
     * ----------------------------------------------
     */
    public function reatailOrderSettledDel($order_number)
    {
        if (empty(strHtml($order_number))) return ['status' => 2, 'message' => getMessage('b')];
        $retail_order_data = $this->findData(['order_number' => strHtml($order_number), 'settlement_status' => 1]);
        if (empty($retail_order_data)) return ['status' => 3, 'message' => getMessage('b')];
        $retail_equipment_data = $this->retailEquipment->findData(['retail_order_number' => $order_number, 'type_goods' => 6, 'status' => 1]);
        $retail_settlement_order_list_data = $this->retailSettlementOrderList->findData(['retail_order_number' => $order_number]);
        // @更新零售订单表
        $update_retail_order = [
            'paid_price' => 0,
            'payable_price' => 0,
            'settlement_status' => 0,
            'update_time' => $this->time
        ];
        // @记录订单状态修改时间
        $insert_retail_order_status_time = [
            'dealer_id' => $_SESSION['adminInfo']['id'],
            'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
            'retail_order_number' => $order_number,
            'retail_order_status_time' => $this->time,
            'retail_order_status' => $retail_order_data['status'],
            'retail_order_pay_status' => $retail_order_data['pay_status'],
            'retail_order_settlement_status' => 0

        ];
        $retail_settlement_order_data = $this->retailSettlementOrder->findData(['sale_company_name' => strHtml($retail_order_data['sale_company_name']), 'status' => 0]);
        if (count(unserialize($retail_settlement_order_data['retail_order_number'])) == 1) {
            // @同时删除结算单
            $result_del_retail_settlement_order = $this->retailSettlementOrder->deleteData(['id' => $retail_settlement_order_data['id']]);
        } else {
            // @更新结算单表
            $retail_order_number = unserialize($retail_settlement_order_data['retail_order_number']);
            foreach ($retail_order_number as $k => $v) {
                if ($v == $order_number) unset($retail_order_number[$k]);
            }
            $update_retail_settlement_order = [
                'retail_order_number' => serialize($retail_order_number),
                'count_num' => $retail_settlement_order_data['count_num'] - $retail_equipment_data['num'],
                'count_price' => $retail_settlement_order_data['count_price'] - ($retail_equipment_data['num'] * $retail_settlement_order_list_data['paid_price']),
            ];
            $result_del_retail_settlement_order = $this->retailSettlementOrder->updateData(['id' => $retail_settlement_order_data['id']], $update_retail_settlement_order);
        }
        // @删除预结算单表
        $result_del_retail_settlement_order_list = $this->retailSettlementOrderList->deleteData(['retail_order_number' => strHtml($order_number)]);
        $result_retail_order = $this->updateData(['order_number' => $order_number], $update_retail_order);
        $result_retail_order_status_time = $this->retailOrderStatusTime->addData($insert_retail_order_status_time);
        // 开启事务
        $retail_order = M('retail_order');
        $retail_order->startTrans();
        if ($result_retail_order && $result_retail_order_status_time && $result_del_retail_settlement_order && $result_del_retail_settlement_order_list) {
            $retail_order->commit();
        } else {
            $retail_order->rollback();
            return ['status' => 4, 'message' => getMessage('a-i')];
        }
        return ['status' => 1, 'message' => getMessage('a-s')];
    }

    /*
     * ----------------------------------------------
     * 撤销结算单按钮ajax
     * Author：jk        Time：2016-09-19
     * ----------------------------------------------
     */
    public function retailFinanceRevocationSettlement($sale_company_name)
    {
        if (empty(strHtml($sale_company_name))) return ['status' => 2, 'message' => getMessage('b')];
        $retail_settlement_order_data = $this->retailSettlementOrder->findData(['sale_company_name' => strHtml($sale_company_name), 'status' => 0]);
        if (empty($retail_settlement_order_data)) return ['status' => 3, 'message' => getMessage('b')];
        // @删除结算单
        $result_retail_settlement_order = $this->retailSettlementOrder->deleteData(['id' => $retail_settlement_order_data['id']]);
        // @删除预结算列表
        $result_retail_settlement_order_list = $this->retailSettlementOrderList->deleteData(['sale_company_name' => strHtml($sale_company_name), 'status' => 0]);
        // @批量更新零售订单表
        $retail_order_data = $this->selectData('order_number,status,pay_status', ['sale_company_name' => strHtml($sale_company_name), 'settlement_status' => 1]);
        foreach ($retail_order_data as $k => $v) {
            $status[$v['order_number']] = 0;
            $paid_payable_price[$v['order_number']] = 0;
            $insert_retail_order_status_time[] = [
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
                'retail_order_number' => $v['order_number'],
                'retail_order_status_time' => $this->time,
                'retail_order_status' => $v['status'],
                'retail_order_pay_status' => $v['pay_status'],
                'retail_order_settlement_status' => 0
            ];
        }
        $order_number_str = implode(',', array_keys($status));
        $sql = "UPDATE oil_retail_order SET settlement_status = CASE order_number ";
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . $v . ' ';
        }
        $sql .= ' END,update_status_time = CASE order_number ';
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        $sql .= ' END,update_time = CASE order_number ';
        foreach ($status as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        $sql .= ' END,paid_price = CASE order_number ';
        foreach ($paid_payable_price as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $v . '"' . ' ';
        }
        $sql .= ' END,payable_price = CASE order_number ';
        foreach ($paid_payable_price as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '"' . $v . '"' . ' ';
        }
        $sql .= "END WHERE order_number IN ($order_number_str)";
        $result_retail_order_status_time = $this->retailOrderStatusTime->addAllData($insert_retail_order_status_time);
        // 开启事务
        $retail_order = M('retail_order');
        $retail_order->startTrans();
        $result_retail_order = $retail_order->execute($sql);
        if ($result_retail_settlement_order && $result_retail_settlement_order_list && $result_retail_order_status_time && $result_retail_order) {
            $retail_order->commit();
        } else {
            $retail_order->rollback();
            return ['status' => 4, 'message' => getMessage('a-t')];
        }

        return ['status' => 1, 'message' => getMessage('a-u')];
    }

    /*
     * ----------------------------------------------
     * 立即结算按钮ajax
     * Author：jk        Time：2016-09-19
     * ----------------------------------------------
     */
    public function retailFinanceImmediateSettlement($sale_company_name)
    {
        if (empty(strHtml($sale_company_name))) return ['status' => 2, 'message' => getMessage('b')];
        $retail_settlement_order_data = $this->retailSettlementOrder->findData(['sale_company_name' => strHtml($sale_company_name), 'status' => 0]);
        if (empty($retail_settlement_order_data)) return ['status' => 3, 'message' => getMessage('b')];
        // @更新结算表
        $update_retail_settlement_order = [
            'end_time' => $this->time,
            'status' => 1
        ];
        // @更新预结算表
        $retail_order_data = $this->selectData('order_number,payable_price,status,pay_status', ['sale_company_name' => strHtml($sale_company_name), 'settlement_status' => 1, 'status' => 100, 'zhaoyou_status' => 10]);
        foreach ($retail_order_data as $k => $v) {
            $status[$v['order_number']] = 1;
            $payment_price[$v['order_number']] = $v['payable_price'];
            // @插入订单状态修改时间记录
            $insert_retail_order_status_time[] = [
                'dealer_id' => $_SESSION['adminInfo']['id'],
                'dealer_name' => strHtml($_SESSION['adminInfo']['dealer_name']),
                'retail_order_number' => $v['order_number'],
                'retail_order_status_time' => $this->time,
                'retail_order_status' => $v['status'],
                'retail_order_pay_status' => $v['pay_status'],
                'retail_order_settlement_status' => 2
            ];
        }
        $order_number_str = implode(',', array_keys($status));
        $settlement_sql = "UPDATE oil_retail_settlement_order_list SET status = CASE retail_order_number ";
        foreach ($status as $k => $v) {
            $settlement_sql .= ' WHEN ' . $k . ' THEN ' . $v . ' ';
        }
        $settlement_sql .= ' END,sett_time = CASE retail_order_number ';
        foreach ($status as $k => $v) {
            $settlement_sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        $settlement_sql .= "END WHERE retail_order_number IN ($order_number_str)";
        // @更新订单表数据
        $order_sql = "UPDATE oil_retail_order SET settlement_status = CASE order_number ";
        foreach ($status as $k => $v) {
            $order_sql .= ' WHEN ' . $k . ' THEN 2 ';
        }
        $order_sql .= ' END,update_status_time = CASE order_number ';
        foreach ($status as $k => $v) {
            $order_sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        $order_sql .= ' END,update_time = CASE order_number ';
        foreach ($status as $k => $v) {
            $order_sql .= ' WHEN ' . $k . ' THEN ' . '"' . $this->time . '"' . ' ';
        }
        // $order_sql .= ' END,payable_price = CASE order_number ';
        // foreach($status as $k => $v){
        //     $order_sql .= ' WHEN '.$k.' THEN 0 ';
        // }
        $order_sql .= ' END,payment_price = CASE order_number ';
        foreach ($payment_price as $k => $v) {
            $order_sql .= ' WHEN ' . $k . ' THEN ' . $v . ' ';
        }
        $order_sql .= "END WHERE order_number IN ($order_number_str)";
        // 开启事务
        $retail_order = M('retail_order');
        $retail_order->startTrans();
        $result_retail_settlement_order_list = $retail_order->execute($settlement_sql);
        $result_retail_order = $retail_order->execute($order_sql);
        $result_retail_order_status_time = $this->retailOrderStatusTime->addAllData($insert_retail_order_status_time);
        $result_retail_settlement_order = $this->retailSettlementOrder->updateData(['id' => $retail_settlement_order_data['id']], $update_retail_settlement_order);
        if ($result_retail_settlement_order_list && $result_retail_order && $result_retail_order_status_time && $result_retail_settlement_order) {
            $retail_order->commit();
        } else {
            $retail_order->rollback();
            return ['status' => 4, 'message' => getMessage('a-x')];
        }

        return ['status' => 1, 'message' => getMessage('a-y')];
    }


}


?>
