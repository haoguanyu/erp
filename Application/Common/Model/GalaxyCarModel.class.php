<?php
namespace Common\Model;
use Common\Model\BaseModel;

class GalaxyCarModel extends BaseModel
{

    // 查询多条记录
    public function select_galaxycar($where, $field = '*', $order = '', $limit = '', $group = '')
    {
        return M('galaxy_car')->field($field)->where($where)->order($order)->limit($limit)->group($group)->select();
    }

    // 查询一条记录
    public function  find_galaxycar($where)
    {
        if (count($where) <= 0) return [];
        return M('galaxy_car')->where($where)->find();
    }

    // 修改一条记录
    public function save_galaxycar($where, $data)
    {
        return M('galaxy_car')->where($where)->save($data);
    }

    // 增加一条记录
    public function add_galaxycar($data)
    {
        return M('galaxy_car')->add($data);
    }

    /**
     * 查找一条车辆油量信息
     * @param $where
     * @param  $field
     * @return array
     */
    public function find_car_balance($where, $field = 'c.car_number,cb.*')
    {
        if (count($where) <= 0) return [];
        $data = M('galaxy_car')->field($field)->alias('c')->join('__GALAXY_CAR_BALANCE__ cb on c.id = cb.car_id', 'left')->where($where)->find();
        $data['balance_num'] = $data['balance_num'] ? $data['balance_num'] : '0.00';
        $data['used_num'] = $data['used_num'] ? $data['used_num'] : '0.00';
        $data['freeze_num'] = $data['freeze_num'] ? $data['freeze_num'] : '0.00';
        $data['count_num'] = $data['count_num'] ? $data['count_num'] : '0.00';
        return $data;
    }

    /**
     * 充油
     * @param $data
     * @param $userInfo
     * @return bool|mixed
     */
    public function addBalance($data, $userInfo)
    {
        if ($data['car_id'] && $data['add_num']) {
            $balanceInfo = M('galaxy_car_balance')->where(['car_id' => $data['car_id']])->find();


            //$data['balance_num'] = $data['add_num'];
            if ($balanceInfo) {
                $balanceInfo['balance_num'] = $balanceInfo['balance_num'] ? $balanceInfo['balance_num'] : '0.00';
                $balanceInfo['residue_num'] = $balanceInfo['residue_num'] ? $balanceInfo['residue_num'] : '0.00';
                $balanceInfo['freeze_num'] = $balanceInfo['freeze_num'] ? $balanceInfo['freeze_num'] : '0.00';
                $balanceInfo['used_num'] = $balanceInfo['used_num'] ? $balanceInfo['used_num'] : '0.00';

                $data['balance_num'] = bcadd($balanceInfo['balance_num'], $data['add_num'], 2); //可用
                $data['residue_num'] = bcadd($balanceInfo['residue_num'], $data['add_num'], 2);//剩余
                $data['count_num'] = bcadd($balanceInfo['count_num'], $data['add_num'], 2);//总充油量
                $data['update_time'] = date('Y-m-d H:i:s');

                $change_data = [
                    'change_num' => $data['add_num'],
                    'car_id' => $data['car_id'],
                    'before_balance_num' => $balanceInfo['balance_num'],
                    'after_balance_num' => $data['balance_num'],
                    'before_residue_num' => $balanceInfo['residue_num'],
                    'after_residue_num' => $data['residue_num'],
                    'before_freeze_num' => $balanceInfo['freeze_num'],
                    'after_freeze_num' => $balanceInfo['freeze_num'],
                    'before_used_num' => $balanceInfo['used_num'],
                    'after_used_num' => $balanceInfo['used_num'],
                    'type' => 1,
                    'add_time' => date('Y-m-d H:i:s'),
                    'user_id' => $userInfo['id'],
                    'user_name' => $userInfo['user_name'],
                ];
                unset($data['add_num']);

                $status = M('galaxy_car_balance')->where(['car_id' => $data['car_id']])->save($data) && $this->balanceChange($change_data);


                log_info("插入SQL：" . M('galaxy_car_balance')->getLastSql());
//                if($status){
//                    $this->balanceChange($change_data);
//                }

            } else {
                $data['balance_num'] = $data['add_num'];
                $data['residue_num'] = $data['balance_num'];
                $data['count_num'] = $data['balance_num'];
                $data['add_time'] = date('Y-m-d H:i:s');

                $change_data = [
                    'change_num' => $data['add_num'],
                    'car_id' => $data['car_id'],
                    'before_balance_num' => '0.00',
                    'after_balance_num' => $data['add_num'],
                    'before_residue_num' => '0.00',
                    'after_residue_num' => $data['add_num'],
//                    'before_freeze_num'=>0,
//                    'after_freeze_num'=>0,
//                    'before_used_num'=>0,
//                    'after_used_num'=>0,
                    'type' => 1,
                    'add_time' => date('Y-m-d H:i:s'),
                    'user_id' => $userInfo['id'],
                    'user_name' => $userInfo['user_name'],
                ];
                unset($data['add_num']);
                $status = M('galaxy_car_balance')->add($data) && $this->balanceChange($change_data);
                log_info("插入SQL：" . M('galaxy_car_balance')->getLastSql());
//                if($status){
//                    $this->balanceChange($change_data);
//                }
            }

            return $status;
        }
    }

    /**
     * 添加油量变动记录
     * @param $data
     * @param int $type
     * @return mixed
     */
    public function balanceChange($data, $type = 1)
    {

        $status = M('galaxy_car_balance_change')->add($data);
        return $status;
    }

    /**
     * 油量详情
     * @param $car_id
     * @param $field
     * @return array
     */
    public function carBalanceDetail($car_id, $param = [], $field = 'c.car_number,c.galaxy_clients_id, cbc.*')
    {
        $data = [];
        if ($car_id) {
            $where = $param;
            $where['c.id'] = $car_id;
            $data = M('galaxy_car')->alias('c')->field($field)->join('oil_galaxy_car_balance_change cbc on cbc.car_id = c.id')->where($where)->select();
            $getGalaxyClients = getGalaxyClients();
            //print_r(balanceChangeType());
            if ($data) {
                $i = 1;
                foreach ($data as $key => $val) {
                    //$data
                    $data[$key]['galaxy_company_name'] = $getGalaxyClients[$val['galaxy_clients_id']];
                    $data[$key]['type'] = balanceChangeType($val['type']);
                    $data[$key]['order_number'] = $val['order_number'] ? $val['order_number'] : '--';
                    $data[$key]['nob'] = $i;
                    if ($val['operator_type'] == 2) {
                        $data[$key]['user_name'] = $val['user_name'] . '(找油)';
                    }
                    $i++;
                }
            }

        }
        return $data;
    }

    /**
     * 订单双方审核都通过时，油量变动
     * @param $balanceInfo
     * @param $change_num
     * @param $userInfo
     * @param $order_number
     * @return bool
     */
    public function orderPassBalance($balanceInfo, $change_num, $userInfo, $order_number = 0)
    {

        //$balanceInfo = $this->initCarBalance($car_id);
        $data['freeze_num'] = bcsub($balanceInfo['freeze_num'], $change_num, 2); //冻结 减
        $data['residue_num'] = bcsub($balanceInfo['residue_num'], $change_num, 2);//剩余 减
        $data['used_num'] = bcadd($balanceInfo['used_num'], $change_num, 2);//已用 加
        $data['update_time'] = date('Y-m-d H:i:s');

        $change_data = [
            'change_num' => $change_num,
            'car_id' => $balanceInfo['car_id'],
            //'before_balance_num'=>$balanceInfo['balance_num'],
            'after_balance_num' => $balanceInfo['balance_num'],
            //'before_residue_num'=>$balanceInfo['residue_num'],
            'after_residue_num' => $data['residue_num'],
            //'before_freeze_num'=>$balanceInfo['freeze_num'],
            'after_freeze_num' => $data['freeze_num'],
            //'before_used_num'=>$balanceInfo['used_num'],
            'after_used_num' => $data['used_num'],
            'order_number' => $order_number,
            'type' => 3,
            'add_time' => date('Y-m-d H:i:s'),
            'user_id' => $userInfo['id'],
            'user_name' => $userInfo['user_name'],
            'operator_type' => 2, //操作人类型：1客户  2找油
        ];
        $status = M('galaxy_car_balance')->where(['car_id' => $balanceInfo['car_id']])->save($data) && $this->balanceChange($change_data);

        $status_str = $status ? '操作成功' : '操作失败';
        $status_str .= '。 操作人：' . $userInfo['user_name'] . ' 操作时间：' . DateTime();
        log_info('订单：' . $order_number . ' 审核通过。车辆ID：' . $balanceInfo['car_id'] . ', 油量变动:' . $change_num . '升，' . $status_str);
        return $status;
    }

    public function isHaveBalance($car_id = 0)
    {
        if ($car_id) {
            $balanceInfo = M('galaxy_car_balance')->where(['car_id' => $car_id])->find();

            if (empty($balanceInfo)) {

                $data['car_id'] = $car_id;
                $data['add_time'] = date('Y-m-d H:i:s');

                M('galaxy_car_balance')->add($data);
            }
        }
    }

    public function initCarBalance($car_id = 0)
    {
        $status = false;
        $balanceInfo = M('galaxy_car_balance')->where(['car_id' => $car_id])->find();

        if (empty($balanceInfo)) {

            $data['car_id'] = $car_id;
            $data['add_time'] = date('Y-m-d H:i:s');

            $status = M('galaxy_car_balance')->add($data);
        }
        return $status;
    }

    /**
     * 订单取消，油量变动
     * @param $balanceInfo
     * @param $change_num
     * @param $userInfo
     * @param $order_number
     * @return bool
     */
    public function orderCancelBalance($balanceInfo, $change_num, $userInfo, $order_number = 0)
    {


        $data['freeze_num'] = bcsub($balanceInfo['freeze_num'], $change_num, 2); //冻结 减

        $data['balance_num'] = bcadd($balanceInfo['balance_num'], $change_num, 2);//已用 加
        $data['update_time'] = date('Y-m-d H:i:s');

        $change_data = [
            'change_num' => $change_num,
            'car_id' => $balanceInfo['car_id'],
            //'before_balance_num'=>$balanceInfo['balance_num'],
            'after_balance_num' => $data['balance_num'],
            //'before_residue_num'=>$balanceInfo['residue_num'],
            'after_residue_num' => $balanceInfo['residue_num'],
            //'before_freeze_num'=>$balanceInfo['freeze_num'],
            'after_freeze_num' => $data['freeze_num'],
            //'before_used_num'=>$balanceInfo['used_num'],
            'after_used_num' => $balanceInfo['used_num'],
            'order_number' => $order_number,
            'type' => 4,
            'add_time' => date('Y-m-d H:i:s'),
            'user_id' => $userInfo['id'],
            'user_name' => $userInfo['user_name'],
            'operator_type' => 2, //操作人类型：1客户  2找油
        ];
        $status = M('galaxy_car_balance')->where(['car_id' => $balanceInfo['car_id']])->save($data) && $this->balanceChange($change_data);

        $status_str = $status ? '操作成功' : '操作失败';
        $status_str .= '。 操作人：' . $userInfo['user_name'] . ' 操作时间：' . DateTime();
        log_info('订单：' . $order_number . ' 取消。车辆ID：' . $balanceInfo['car_id'] . ', 油量变动:' . $change_num . '升，' . $status_str);
        return $status;
    }

    /**
     * 订单提交成功时，油量变动
     * @param $balanceInfo
     * @param $change_num
     * @param $userInfo
     * @param $order_number
     * @return bool
     */
    public function orderCreateBalance($balanceInfo, $change_num, $userInfo, $order_number = 0)
    {

        //$balanceInfo = $this->initCarBalance($car_id);
        $data['freeze_num'] = bcadd($balanceInfo['freeze_num'], $change_num, 2); //冻结 加

        $data['balance_num'] = bcsub($balanceInfo['balance_num'], $change_num, 2);//可用 减
        $data['update_time'] = date('Y-m-d H:i:s');
        $data['consumption_time'] = date('Y-m-d H:i:s');
        $change_data = [
            'change_num' => $change_num,
            'car_id' => $balanceInfo['car_id'],
            //'before_balance_num'=>$balanceInfo['balance_num'],
            'after_balance_num' => $data['balance_num'],
            //'before_residue_num'=>$balanceInfo['residue_num'],
            'after_residue_num' => $balanceInfo['residue_num'],
            //'before_freeze_num'=>$balanceInfo['freeze_num'],
            'after_freeze_num' => $data['freeze_num'],
            //'before_used_num'=>$balanceInfo['used_num'],
            'after_used_num' => $balanceInfo['used_num'],
            'order_number' => $order_number,
            'type' => 2,
            'add_time' => date('Y-m-d H:i:s'),
            'user_id' => $userInfo['id'],
            'user_name' => $userInfo['user_name'],
            'operator_type' => 2, //操作人类型：1客户  2找油 3服务商
        ];
        $status = M('galaxy_car_balance')->where(['car_id' => $balanceInfo['car_id']])->save($data) && $this->balanceChange($change_data);

        $status_str = $status ? '操作成功' : '操作失败';
        $status_str .= '。 操作人：' . $userInfo['user_name'] . ' 操作时间：' . DateTime();
        log_info('订单：' . $order_number . ' 生成成功。车辆ID：' . $balanceInfo['car_id'] . ', 油量变动:' . $change_num . '升，' . $status_str);
        return $status;
    }
}

?>
