<?php
namespace Common\Model;

use Common\Model\BaseModel;


class OrderModel extends BaseModel
{

    public function _initialize()
    {
        $this->arr = [];

        $this->order = M('order');
        $this->clients = M('clients');
    }

/////////////////////////////////基础层///////////////////////////////////////////////


    public function selectAll($where = [], $limit = 100, $order = 'id DESC')
    {
        if (!is_array($where)) return [];
        if (empty($where)) return $this->order->order($order)->limit($limit)->select();
        return $this->order->where($where)->order($order)->limit($limit)->select();
    }

    public function findOne($where = [])
    {
        if (!is_array($where) || empty($where)) return [];

        return $this->order->where($where)->find();
    }

    public function updateData($where = [], $data = [])
    {
        if (empty($where) || empty($data)) return [];

        return $this->order->where($where)->save($data);
    }

    public function getOrderInfo($field, $where)
    {
        $reg = $this->field($field)->order('id DESC')->where($where)->select();
        return $reg;
    }




/////////////////////////////////业务处理层////////////////////////////////////////////

    /*
     * -----------------------------------------------------
     * 财务管理 | 发票审核列表
     * Author:<jk>   Time:2016-08-25
     * -----------------------------------------------------
     */
    public function fapiaoList($param)
    {
        // @搜索条件
        $start_time = $end_time = false;
        if (isset($param['start_time']) && strHtml($param['start_time']) != 'null' && !empty(strHtml($param['start_time']))) {
            $start_time = true;
        }
        if (isset($param['end_time']) && strHtml($param['end_time']) != 'null' && !empty(strHtml($param['end_time']))) {
            $end_time = true;
        }
        if ($start_time && !$end_time) {
            $where['create_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['start_time']) . ' ' . '00:00:00'))];
        } elseif (!$start_time && $end_time) {
            $where['create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['end_time']) . ' ' . '23:59:59'))];
        } elseif ($start_time && $end_time) {
            $where['create_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['end_time']) . ' ' . '23:59:59'))]];
        }
        if (isset($param['buy_coname']) && strHtml($param['buy_coname']) != 'null') {
            $where['buy_coname'] = ['like', '%' . strHtml($param['buy_coname']) . '%'];
        }
        if (isset($param['sale_coname']) && strHtml($param['sale_coname']) != 'null') {
            $where['sale_coname'] = ['like', '%' . strHtml($param['sale_coname']) . '%'];
        } else {
            // @默认出货商条件
            $where['sale_coname'] = ['IN', changeSaleCname()];
        }
        if (isset($param['fapiao']) && intval($param['fapiao']) > 0) {
            $where['fapiao'] = intval($param['fapiao']);
        }
        $where['is_available'] = 0;
        $data['data'] = $this->selectAll($where);
        // @买家公司ID
        $order_id = $clients = $this->arr;
        foreach ($data['data'] as $k => $v) {
            $order_id[] = $v['buy_coid'];
        }
        if (!empty($order_id)) {
            $order_id = array_unique($order_id);
            $clients = $this->clients->where(['company_id' => ['IN', $order_id]])->select();
        }
        // @合并订单与买家公司信息
        if (count($clients) > 0) {
            foreach ($data['data'] as $k => $v) {               # @买家公司开户行账号
                foreach ($clients as $k1 => $v1) {
                    if (intval($v['buy_coid']) == intval($v1['company_id'])) {
                        $data['data'][$k]['tax_num'] = $v1['tax_num'];                # @买家公司国税号码
                        $data['data'][$k]['company_tel'] = $v1['company_tel'];            # @买家公司注册电话
                        $data['data'][$k]['company_address'] = $v1['company_address'];        # @买家公司注册地址
                    }
                }
                $data['data'][$k]['fapiao_status'] = orderFapiaoStatus($v['fapiao']);
            }
        }

        return $data;
    }

    /*
     * -----------------------------------------------------
     * 财务管理 | 发票审核列表 | 发票状态操作按钮
     * Author:<jk>   Time:2016-08-26
     * -----------------------------------------------------
     */
    public function fapiaoOperation($order_id, $str, $fapiao_remark)
    {
        if (intval($order_id) <= 0 || empty(strHtml($str))) {
            return [
                'status' => 2,
                'message' => getMessage('a')
            ];
        }
        switch (strHtml($str)) {
            case 'adopt':
                // @发票状态未审核 | 操作 通过按钮
                $result = $this->adopt($order_id, $fapiao_remark);
                break;
            case 'no_adopt':
                // @发票状态未审核 | 操作 未通过按钮
                $result = $this->noAdopt($order_id, $fapiao_remark);
                break;
            case 'billing':
                // @发票状态审核通过11 | 操作开票
                $result = $this->billing($order_id, $fapiao_remark);
                break;
            case 'no_billing':
                // @发票状态审核通过11 | 操作不开票
                $result = $this->noBilling($order_id, $fapiao_remark);
                break;
            case 'distribution':
                // @发票状态未配送13 | 操作配送
                $result = $this->distribution($order_id, $fapiao_remark);
                break;
            case 'brought':
                // @发票状态配送中13 | 操作已领
                $result = $this->brought($order_id, $fapiao_remark);
                break;
            case 'lead':
                // @发票状态未配送13 | 操作代领
                $result = $this->lead($order_id, $fapiao_remark);
                break;
            case 'distribution_failure':
                // @发票状态未配送13 | 操作配送失败
                $result = $this->distributionFailure($order_id, $fapiao_remark);
                break;
            case 're_audit':
                // @发票状态审核未通过1 开票失败2 配送失败3 | 重新审核
                $result = $this->reAudit($order_id, $fapiao_remark);
                break;
            default:
                $result = [
                    'status' => 5,
                    'message' => getMessage('b')
                ];
                break;
        }
        return $result;
    }

    /*
     * -----------------------------------------------------
     * 财务管理 | 发票审核列表 | 发票备注详情
     * Author:<jk>   Time:2016-08-28
     * -----------------------------------------------------
     */
    public function fapiaoRemarkDetails($order_id)
    {
        if (intval($order_id) <= 0) return [];
        $data = $this->findOne(['id' => intval($order_id)]);
        if (!empty(unserialize($data['fapiao_remark']))) {
            $result = unserialize($data['fapiao_remark']);
            $result['审核未通过'] = $result['审核未通过|重新审核'];
            $result['开票失败'] = $result['开票失败|重新审核'];
            $result['配送失败'] = $result['配送失败|重新审核'];
            unset($result['审核未通过|重新审核']);
            unset($result['开票失败|重新审核']);
            unset($result['配送失败|重新审核']);
        }

        return $result;
    }

    /*
     * -----------------------------------------------------
     * 财务管理 | 发票审核 | 批量执行 通过 开票 已配送 已领 代领
     * Author:<jk>   Time:2016-10-12
     * -----------------------------------------------------
     */
    public function changeStatus($param = [])
    {
        if (!isset($param['str']) || empty(strHtml($param['str']))) {
            return ['status' => 2, 'message' => '请选择订单！', 'info' => [], 'is_null' => true];
        }
        if (!isset($param['fapiao_status_message']) || empty(strHtml($param['fapiao_status_message'])) || !in_array(strHtml($param['fapiao_status_message']), ['adopt', 'billing', 'distribution', 'brought', 'lead'])) {
            return ['status' => 3, 'message' => '请确认信息！', 'info' => [], 'is_null' => true];
        }
        if (!isset($param['fapiao_remark'])) {
            return ['status' => 4, 'message' => getMessage('b'), 'info' => [], 'is_null' => true];
        }
        $order_id = explode(',', substr($param['str'], 0, strlen($param['str']) - 1));
        $where = [
            'is_available' => 0,
            'id' => ['IN', $order_id],
            'fapiao' => orderFapiaoStr(strHtml($param['fapiao_status_message']), 2)
        ];
        # @验证批量操作只能操作发票状态相同的数据
        $order_data = $this->selectAll($where);
        if (count($order_data) <= 0) {
            return ['status' => 6, 'message' => '发票状态选择错误！', 'info' => [], 'is_null' => true];
        }
        if (count($order_id) != count($order_data)) {
            return ['status' => 5, 'message' => '请选择同状态订单！', 'info' => [], 'is_null' => true];
        }
        $fapiao_remark_name = orderFapiaoStr(strHtml($param['fapiao_status_message']), 4);
        $fapiao_status = orderFapiaoStr(strHtml($param['fapiao_status_message']), 3);
        foreach ($order_data as $k => $v) {
            $update[$v['id']]['fapiao_status'] = $fapiao_status;
            if (!empty(strHtml($param['fapiao_remark']))) {
                if (empty(strHtml($v['fapiao_remark']))) {
                    $update[$v['id']]['fapiao_remark'] = serialize(changeSerialize($fapiao_remark_name, strHtml($param['fapiao_remark'])));
                } else {
                    $fapiao_remark = unserialize($v['fapiao_remark']);
                    $fapiao_remark[$fapiao_remark_name] = trim($param['fapiao_remark']);
                    $update[$v['id']]['fapiao_remark'] = serialize($fapiao_remark);
                }
            } else {
                $update[$v['id']]['fapiao_remark'] = '';
            }
        }
        $order_id = implode(',', array_keys($update));
        $sql = "UPDATE oil_order SET fapiao = CASE id ";
        foreach ($update as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . $v['fapiao_status'] . ' ';
        }
        $sql .= ' END,fapiao_remark = CASE id ';
        foreach ($update as $k => $v) {
            $sql .= ' WHEN ' . $k . ' THEN ' . '\'' . trim($v['fapiao_remark']) . '\'' . ' ';
        }
        $sql .= "END WHERE id IN ($order_id)";
        if (!M('order')->execute($sql)) {
            return ['status' => 5, 'message' => $fapiao_remark_name . '失败！', 'info' => [], 'is_null' => true];
        } else {
            return ['status' => 1, 'message' => $fapiao_remark_name . '成功！', 'info' => [], 'is_null' => true];
        }
    }


/////////////////////////////////内部调用层////////////////////////////////////////////

    // @发票状态未审核 | 操作 通过按钮====
    protected function adopt($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        if (empty($order) || $order['fapiao'] != 10) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            if (!empty(strHtml($fapiao_remark))) {
                if (empty(strHtml($order['fapiao_remark']))) {
                    $update_data = [
                        'fapiao_remark' => serialize(changeSerialize('通过', strHtml($fapiao_remark)))
                    ];
                } else {
                    $select_fapiao_remark = unserialize($order['fapiao_remark']);
                    $select_fapiao_remark['通过'] = strHtml($fapiao_remark);
                    $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
                }
            }
            $update_data['fapiao'] = 11;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }

        return $result;
    }

    // @发票状态未审核 | 操作 未通过按钮
    protected function noAdopt($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        if (empty(strHtml($fapiao_remark))) {
            return [
                'status' => 5,
                'message' => getMessage('f')
            ];
        }
        if (empty($order) || $order['fapiao'] != 10) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            if (empty(strHtml($order['fapiao_remark']))) {
                $update_data = [
                    'fapiao_remark' => serialize(changeSerialize('未通过', strHtml($fapiao_remark)))
                ];
            } else {
                $select_fapiao_remark = unserialize($order['fapiao_remark']);
                $select_fapiao_remark['未通过'] = strHtml($fapiao_remark);
                $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
            }
            $update_data['fapiao'] = 1;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态审核通过11 | 操作开票
    protected function billing($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        if (empty($order) || $order['fapiao'] != 11) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            if (!empty(strHtml($fapiao_remark))) {
                if (empty(strHtml($order['fapiao_remark']))) {
                    $update_data = [
                        'fapiao_remark' => serialize(changeSerialize('开票', strHtml($fapiao_remark)))
                    ];
                } else {
                    $select_fapiao_remark = unserialize($order['fapiao_remark']);
                    $select_fapiao_remark['开票'] = strHtml($fapiao_remark);
                    $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
                }
            }
            $update_data['fapiao'] = 12;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态审核通过11 | 操作不开票
    protected function noBilling($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        // @验证发票备注必填
        if (empty(strHtml($fapiao_remark))) {
            return [
                'status' => 5,
                'message' => getMessage('f')
            ];
        }
        if (empty($order) || $order['fapiao'] != 11) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            if (empty(strHtml($order['fapiao_remark']))) {
                $update_data = [
                    'fapiao_remark' => serialize(changeSerialize('不开票', strHtml($fapiao_remark)))
                ];
            } else {
                $select_fapiao_remark = unserialize($order['fapiao_remark']);
                $select_fapiao_remark['不开票'] = strHtml($fapiao_remark);
                $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
            }
            $update_data['fapiao'] = 2;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态未配送13 | 操作配送
    protected function distribution($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        if (empty($order) || $order['fapiao'] != 12) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            if (!empty(strHtml($fapiao_remark))) {
                if (empty(strHtml($order['fapiao_remark']))) {
                    $update_data = [
                        'fapiao_remark' => serialize(changeSerialize('已配送', strHtml($fapiao_remark)))
                    ];
                } else {
                    $select_fapiao_remark = unserialize($order['fapiao_remark']);
                    $select_fapiao_remark['已配送'] = strHtml($fapiao_remark);
                    $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
                }
            }
            $update_data['fapiao'] = 13;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态配送中13 | 操作已领
    protected function brought($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        if (empty($order) || $order['fapiao'] != 13) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            if (!empty(strHtml($fapiao_remark))) {
                if (empty(strHtml($order['fapiao_remark']))) {
                    $update_data = [
                        'fapiao_remark' => serialize(changeSerialize('已领', strHtml($fapiao_remark)))
                    ];
                } else {
                    $select_fapiao_remark = unserialize($order['fapiao_remark']);
                    $select_fapiao_remark['已领'] = strHtml($fapiao_remark);
                    $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
                }
            }
            $update_data['fapiao'] = 14;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态未配送13 | 操作代领
    protected function lead($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        // @验证发票备注必填
        if (empty(strHtml($fapiao_remark))) {
            return [
                'status' => 5,
                'message' => getMessage('f')
            ];
        }
        if (empty($order) || $order['fapiao'] != 13) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            if (empty(strHtml($order['fapiao_remark']))) {
                $update_data = [
                    'fapiao_remark' => serialize(changeSerialize('代领', strHtml($fapiao_remark)))
                ];
            } else {
                $select_fapiao_remark = unserialize($order['fapiao_remark']);
                $select_fapiao_remark['代领'] = strHtml($fapiao_remark);
                $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
            }
            $update_data['fapiao'] = 15;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态未配送13 | 操作配送失败
    protected function distributionFailure($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        // @验证发票备注必填
        if (empty(strHtml($fapiao_remark))) {
            return [
                'status' => 5,
                'message' => getMessage('f')
            ];
        }
        if (empty($order) || $order['fapiao'] != 13) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            if (empty(strHtml($order['fapiao_remark']))) {
                $update_data = [
                    'fapiao_remark' => serialize(changeSerialize('配送失败', strHtml($fapiao_remark)))
                ];
            } else {
                $select_fapiao_remark = unserialize($order['fapiao_remark']);
                $select_fapiao_remark['配送失败'] = strHtml($fapiao_remark);
                $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
            }
            $update_data['fapiao'] = 3;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }

    // @发票状态审核未通过1 开票失败2 配送失败3 | 重新审核
    protected function reAudit($order_id, $fapiao_remark)
    {
        $order = $this->findOne(['id' => intval($order_id), 'is_available' => ['neq', 1]]);
        // @验证发票备注必填
        if (empty(strHtml($fapiao_remark))) {
            return [
                'status' => 5,
                'message' => getMessage('f')
            ];
        }
        if (empty($order) || !in_array($order['fapiao'], [1, 2, 3])) {
            $result = [
                'status' => 3,
                'message' => getMessage('c')
            ];
        } else {
            // @记录发票操作备注
            switch (intval($order['fapiao'])) {
                case '1':
                    $str = '审核未通过|重新审核';
                    break;
                case '2':
                    $str = '开票失败|重新审核';
                    break;
                case '3':
                    $str = '配送失败|重新审核';
                    break;
            }
            if (empty(strHtml($order['fapiao_remark']))) {
                $update_data = [
                    'fapiao_remark' => serialize(changeSerialize($str, strHtml($fapiao_remark)))
                ];
            } else {
                $select_fapiao_remark = unserialize($order['fapiao_remark']);
                $select_fapiao_remark[$str] = strHtml($fapiao_remark);
                $update_data['fapiao_remark'] = serialize($select_fapiao_remark);
            }
            $update_data['fapiao'] = 10;
            if ($this->updateData(['id' => intval($order_id)], $update_data)) {
                $result = [
                    'status' => 1,
                    'message' => getMessage('d')
                ];
            } else {
                $result = [
                    'status' => 4,
                    'message' => getMessage('e')
                ];
            }
        }
        return $result;
    }


}
