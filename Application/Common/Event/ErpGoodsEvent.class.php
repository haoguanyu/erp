<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpGoodsEvent extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP商品逻辑处理层
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
        $this->arr = [];
    }

    // +----------------------------------
    // |Facilitator:ERP商品列表
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function erpGoodsList($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['goods_code']))) {
            $where['goods_code'] = ['like', '%' . trim($param['goods_code']) . '%'];
        }
        if (!empty(oilSource()[$param['source_from']])) {
            $where['source_from'] = oilSource()[$param['source_from']];
        }
        if (!isset($param['status']) || empty($param['status'])) {
            $where['status'] = ['neq', 2];
        } else if (!empty(erpGoodsStatus()[$param['status']])) {
            $where['status'] = $param['status'];
        }
        if ($param['goods_name'] != '请选择名称' && !empty($param['goods_name'])) {
            $where['goods_name'] = $param['goods_name'];
        }
        if ($param['grade'] != '请选择标号' && !empty($param['grade'])) {
            $where['grade'] = $param['grade'];
        }
        if ($param['level'] != '请选择级别' && !empty($param['level'])) {
            $where['level'] = $param['level'];
        }


        $field = '*';
        $erpGoods = $this->getModel('ErpGoods')->erpGoodsList($where, $field, $param['start'], $param['length']);
        //空数据
        if (count($erpGoods['data']) > 0) {
            foreach ($erpGoods['data'] as $k => $v) {
                $erpGoods['data'][$k]['status_font'] = erpGoodsStatus($v['status']);
            }
        } else {
            $erpGoods['data'] = [];
        }
        $erpGoods['recordsFiltered'] = $erpGoods['recordsTotal'];
        $erpGoods['draw'] = $_REQUEST['draw'];
        return $erpGoods;
    }

    // +----------------------------------
    // |Facilitator:添加erp商品操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function actAddErpGoods($param = [])
    {
        if (getCacheLock('ErpGoods/actAddErpGoods')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpGoods/actAddErpGoods', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $session = session();
            if (trim($param['goods_name']) == "请选择名称") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(a);
            } elseif (trim($param['source_from']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(b);
            } elseif (trim($param['level']) == "请选择级别") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(c);
            } elseif (trim($param['label']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(d);
            } elseif (trim($param['density_value']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(e);
            } elseif (trim($param['density_value']) < 0.7 || trim($param['density_value']) > 1) {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(f);
            } else {
                $exists_data = [
                    'goods_name' => $param['goods_name'],
                    'source_from' => oilSource()[$param['source_from']],
                    'grade' => $param['grade'],
                    'level' => $param['level'] == '请选择级别' ? '' : $param['level'],
                    'label' => oilLabel()[$param['label']],
                ];
                $exists = $this->getModel('ErpGoods')->findErpGoods($exists_data);
                if (!$exists) {
                    $erp_goods_data = [
                        'goods_code' => erpCodeNumber(4, $param['goods_name'])['order_number'],
                        'goods_name' => $param['goods_name'],
                        'source_from' => oilSource()[$param['source_from']],
                        'grade' => $param['grade'] == '请选择标号' ? '' : $param['grade'],
                        'level' => $param['level'] == '请选择级别' ? '' : $param['level'],
                        'label' => oilLabel()[$param['label']],
                        'density_value' => $param['density_value'],
                        'remark' => $param['remark'],
                        'status' => 1,
                        'create_time' => $this->date,
                        'audit_time' => '',
                        'creater' => $session['erp_adminInfo']['id'],
                        'updater' => $session['erp_adminInfo']['id'],
                        'auditor' => ''
                    ];
                    //添加erp商品信息
                    $erp_goods = $this->getModel('ErpGoods')->addErpGoods($erp_goods_data);
                    $result['status'] = $erp_goods ? 1 : 101;
                    $result['message'] = $result['status'] ? '添加成功' : '添加失败';
                } else {
                    $result['status'] = 101;
                    $result['message'] = '该类商品已存在，无法重复添加';
                }
            }

            if (isset($old_goods_code)) {
                $result['status'] = 1;
                $result['message'] = '添加成功';
                $result['new_code'] = $param['goods_code']['order_number'];
            }
        }
        cancelCacheLock('ErpGoods/actAddErpGoods');
        return $result;
    }

    // +----------------------------------------
    // |Facilitator:编辑erp商品页面数据
    // +----------------------------------------
    // |Author:senpai Time:2017.3.12
    // +----------------------------------------
    public function showUpdateErpGoods($id)
    {
        if ($id <= 0) return [];
        $data = $this->getModel('ErpGoods')->findErpGoods(['id' => $id]);

        return $data;
    }

    // +----------------------------------------
    // |Facilitator:编辑erp商品操作
    // +----------------------------------------
    // |Author:senpai Time:2017.3.13
    // +----------------------------------------
    public function actUpdateErpGoods($param)
    {
        if (getCacheLock('ErpGoods/actUpdateErpGoods')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpGoods/actUpdateErpGoods', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            //判断商品信息
            $session = session();
            $erp_goods_info = $this->getModel('ErpGoods')->findErpGoods(['status' => ['NEQ', 2], 'id' => $param['id']]);
            if (count($erp_goods_info) <= 0) {
                $result['status'] = 101;
                $result['message'] = '商品已删除，不能编辑';
            } elseif ($erp_goods_info['status'] != 1) {
                $result['status'] = 101;
                $result['message'] = '商品已审核，不能编辑';
            } elseif (trim($param['goods_name']) == "请选择名称") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(a);
            } elseif (trim($param['source_from']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(b);
            } elseif (trim($param['level']) == "请选择级别") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(c);
            } elseif (trim($param['label']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(d);
            } elseif (trim($param['density_value']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(e);
            } elseif (trim($param['density_value']) < 0.7 || trim($param['density_value']) > 1) {
                $result['status'] = 101;
                $result['message'] = ajaxErpGoodsMessage(f);
            } else {
                $exists_data = [
                    'goods_name' => $param['goods_name'],
                    'source_from' => oilSource()[$param['source_from']],
                    'grade' => $param['grade'] == '请选择标号' ? '' : $param['grade'],
                    'level' => $param['level'] == '请选择级别' ? '' : $param['level'],
                    'label' => oilLabel()[$param['label']],
                ];
                $exists = $this->getModel('ErpGoods')->findErpGoods($exists_data);
                if ($exists['id'] == $param['id'] || !$exists) {
                    $erp_goods_data = [
                        'goods_code' => $param['goods_code'],
                        'goods_name' => $param['goods_name'],
                        'source_from' => oilSource()[$param['source_from']],
                        'grade' => $param['grade'],
                        'level' => $param['level'] == '请选择级别' ? '' : $param['level'],
                        'label' => oilLabel()[$param['label']],
                        'density_value' => $param['density_value'],
                        'remark' => $param['remark'],
                        'update_time' => $this->date,
                        'audit_time' => '',
                        'updater' => $session['erp_adminInfo']['id'],
                        'auditor' => ''
                    ];

                    //编辑erp商品信息
                    $erp_goods = $this->getModel('ErpGoods')->saveErpGoods(['id' => $param['id']], $erp_goods_data);
                    $result['status'] = $erp_goods ? 1 : 101;
                    $result['message'] = $result['status'] ? '编辑成功' : '编辑失败';
                } else {
                    $result['status'] = 101;
                    $result['message'] = '该类商品已存在，无法重复添加';
                }
            }
        }
        cancelCacheLock('ErpGoods/actUpdateErpGoods');
        return $result;
    }

    // +----------------------------------------
    // |Facilitator:删除erp商品操作
    // +----------------------------------------
    // |Author:senpai Time:2017.3.13
    // +----------------------------------------
    public function actDeleteErpGoods($id)
    {
        if (getCacheLock('ErpGoods/actDeleteErpGoods')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpGoods/actDeleteErpGoods', 1);
        if (!$id) {
            $result['status'] = 101;
            $result['message'] = '参数有误';
        } else {
            $erp_goods_info = $this->getModel('ErpGoods')->findErpGoods(['status' => ['NEQ', 2], 'id' => $id]);
            if (count($erp_goods_info) <= 0) {
                $result['status'] = 101;
                $result['message'] = '商品信息有误，请重新检查';
            } elseif ($erp_goods_info['status'] != 1) {
                $result['status'] = 101;
                $result['message'] = '商品已审核，不能删除';
            } else {
                $session = session();
                $erp_goods_data = [
                    'update_time' => $this->date,
                    'status' => 2,
                    'updater' => $session['erp_adminInfo']['id'],
                ];
                //删除erp商品信息（软删除）
                $erp_goods = $this->getModel('ErpGoods')->saveErpGoods(['id' => $id], $erp_goods_data);
                $result['status'] = $erp_goods ? 1 : 101;
                $result['message'] = $result['status'] ? '删除成功' : '删除失败';
            }
        }
        cancelCacheLock('ErpGoods/actDeleteErpGoods');
        return $result;
    }

    // +----------------------------------------
    // |Facilitator:审核erp商品操作
    // +----------------------------------------
    // |Author:senpai Time:2017.3.13
    // +----------------------------------------
    public function actAuditErpGoods($id)
    {
        if (getCacheLock('ErpGoods/actAuditErpGoods')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpGoods/actAuditErpGoods', 1);
        if (!$id) {
            $result['status'] = 101;
            $result['message'] = '参数有误';
        } else {
            $erp_goods_info = $this->getModel('ErpGoods')->findErpGoods(['id' => $id]);
            if ($erp_goods_info['status'] != 1) {
                $result['status'] = 101;
                $result['message'] = '只有未审核的商品才能够审核';
            } else {
                $session = session();
                $erp_goods_data = [
                    'update_time' => $this->date,
                    'audit_time' => $this->date,
                    'status' => 10,
                    'updater' => $session['erp_adminInfo']['id'],
                    'auditor' => $session['erp_adminInfo']['id'],
                ];
                //审核erp商品信息
                $erp_goods = $this->getModel('ErpGoods')->saveErpGoods(['id' => $id], $erp_goods_data);
                $result['status'] = $erp_goods ? 1 : 101;
                $result['message'] = $result['status'] ? '审核成功' : '审核失败';
            }
        }
        cancelCacheLock('ErpGoods/actAuditErpGoods');
        return $result;
    }

    /**
     * 通过商品代码获取商品信息
     * @author xiaowen
     * @param string $code
     * @return mixed
     */
    public function findGoodsByCode($code = '')
    {
        if ($code) {
            return $this->getModel('ErpGoods')->findErpGoods(['goods_code' => trim($code), 'status' => 10]);
        }
    }

    /**
     * 通过商品ID获取商品信息
     * @author xiaowen
     * @param int $id
     * @return mixed
     */
    public function findGoodsById($id = 0)
    {
        if ($id) {
            return $this->getModel('ErpGoods')->findErpGoods(['id' => intval($id), 'status' => 10]);
        }
    }

    /**
     * 获取符合条件的所有商品
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-4-26
     */
    public function getAllGoods($where=[]){
        $data = $this->getModel('ErpGoods')->where($where)->select();
        return $data;
    }
}

?>
