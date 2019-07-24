<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 */
class RetailModel extends BaseModel
{

    /**
     * 获取retail表数据
     * @author lizhipeng
     * @return array
     */
    public function getRetailList($condition)
    {
        $data = [];
        if (empty($condition)) return false;
        $data = M('retail_order')->where($condition)->order('trans_time asc')->select();
        //dump($condition);
        //dump($data);
        return $data;
    }


    /**
     * 获取retail表数据
     * @author lizhipeng
     * @return array
     */
    public function addRetailorder($da)
    {
        if (empty($da)) return false;
        return M('retail_order')->add($da);
    }

    /**
     * 修改retail表数据
     * @author lizhipeng
     * @return array
     */
    public function actRetailList($condition, $data)
    {
        $res = [];
        if (empty($condition)) return false;
        $res = M('retail_order')->where($condition)->save($data);
        return $res;
    }

    /**
     * 插入设备表数据
     * @author lizhipeng
     * @return array
     * @status = false
     */
    public function actInsertEquipment($sql)
    {
        $data = [];
        //if(empty($sql))return false;
        $data = M('retail_equipment')->execute("$sql");
        //var_dump($data); die;
        return $data;
    }

    /**
     * 修改订单表状态
     * @author lizhipeng
     * @return array
     */
    public function actUpdateRetail($condition, $status)
    {
        if (empty($condition)) return false;
        $data = M('retail_order')->where($condition)->save($status);
        return $data;
    }

    /**
     * 查询设备表
     * @author lizhipeng
     * @return array
     */
    public function selectEquipmentList($condition)
    {
        if (empty($condition)) return false;
        $data = M('retail_equipment')->where($condition)->select();
        //var_dump($data); die;
        return $data;
    }

    /**
     * 查询地址表
     * @author lizhipeng
     * @return array
     */
    public function getAddressList($condition)
    {
        if (empty($condition)) return false;
        $data = M('retail_address')->where($condition)->select();
        //var_dump($data); die;
        return $data;
    }

    /**
     * 修改设备表
     * @author lizhipeng
     * @return array
     */
    public function actSaveEquipment($condition, $status)
    {
        if (empty($condition)) return false;
        $data = M('retail_equipment')->where($condition)->save($status);
        return $data;
    }

    public function updateAddress($where, $data)
    {
        $data = M('retail_address')->where($where)->save($data);
    }

    public function selectOneEquipment($condition)
    {
        $data = M('retail_equipment')->where($condition)->find();
        return $data;
    }

    public function getAllRetail($param)
    {
        $condition['status'] = array('gt', 0);
        /*conditions*/
        if (isset($param['dealer_id']) && $param['dealer_id'] > 0) {
            $condition['dealer_id'] = intval($param['dealer_id']);
        }
        if (isset($param['collection_source']) && strHtml($param['collection_source']) != 'null' && !empty(strHtml($param['collection_source']))) {
            $condition['collection_source'] = strHtml($param['collection_source']);
        }
        if (isset($param['order_number']) && strHtml($param['order_number']) != 'null' && !empty(strHtml($param['order_number']))) {
            $condition['order_number'] = strHtml($param['order_number']);
        }
        if (isset($param['c_start_time']) && strHtml($param['c_start_time']) != 'null' && !empty(strHtml($param['c_start_time']))) {
            $c_start_time = $param['c_start_time'];
        }
        if (isset($param['c_end_time']) && strHtml($param['c_end_time']) != 'null' && !empty(strHtml($param['c_end_time']))) {
            $c_end_time = $param['c_end_time'];
        }
        if (isset($param['t_start_time']) && strHtml($param['t_start_time']) != 'null' && !empty(strHtml($param['t_start_time']))) {
            $t_start_time = true;
        }
        if (isset($param['t_end_time']) && strHtml($param['t_end_time']) != 'null' && !empty(strHtml($param['t_end_time']))) {
            $t_end_time = true;
        }
        if (!empty($param['sale_company_name']) && $param['sale_company_name'] != "null") {
            $condition['sale_company_name'] = $param['sale_company_name'];
        }
        if (!empty($param['region']) && $param['region'] != "null") {
            $condition['region'] = $param['region'];
        }
        if (!empty($param['rank']) && $param['rank'] != "null") {
            $condition['rank'] = $param['rank'];
        }
        if (isset($param['status']) && $param['status'] != "null") {
            $statusArr = explode('-', $param['status']);
            if (isset($statusArr[1])) {
                $condition['status'] = $statusArr[0];
                $condition['zhaoyou_status'] = $statusArr[1];
            } else {
                $condition['status'] = $statusArr[0];
            }
        }
        if ($c_start_time && !$c_end_time) {
            $condition['create_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['c_start_time']) . ' ' . '00:00:00'))];
        } elseif (!$c_start_time && $c_end_time) {
            $condition['create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['c_end_time']) . ' ' . '23:59:59'))];
        } elseif ($c_start_time && $c_end_time) {
            $condition['create_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['c_start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['c_end_time']) . ' ' . '23:59:59'))]];
        }
        if ($t_start_time && !$t_end_time) {
            $condition['trans_time'] = ['egt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00'))];
        } elseif (!$t_start_time && $t_end_time) {
            $condition['trans_time'] = ['elt', date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))];
        } elseif ($t_start_time && $t_end_time) {
            $condition['trans_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']) . ' ' . '00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml($param['t_end_time']) . ' ' . '23:59:59'))]];
        } elseif (!isset($param['t_start_time'])) {
            //$condition['trans_time'] = ['between', [date('Y-m-d H:i:s', strtotime(strHtml($param['t_start_time']).' '.'00:00:00')), date('Y-m-d H:i:s', strtotime(strHtml(date('Y-m-d')).' '.'23:59:59'))]];
            //$condition['trans_time'] = ['between', [date('Y-m-d 00:00:00', strtotime('-15days', time())), date('Y-m-d H:i:s', strtotime(strHtml(date('Y-m-d')).' '.'23:59:59'))]];
            $condition['trans_time'] = ['egt', date('Y-m-d 00:00:00', strtotime('-15 days', time()))];
        }
        $data = M('retail_order')->where($condition)->order('id desc')->select();
        return $data;
    }

}
