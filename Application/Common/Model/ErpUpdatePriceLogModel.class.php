<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP二次定价操作日志模型
// +----------------------------------
// |Author:qianbin Time:2017.05.02
// +----------------------------------
class ErpUpdatePriceLogModel extends BaseModel
{
    // +----------------------------------
    // |Facilitator:获取erp_update_price_log表数据
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function getUpdatePriceLogList($where = [], $field='*',$offset = 0, $limit = 10,$order = 'id desc')
    {
        //$erp_workflow_log = M('oil_erp_update_price_log');
        $reg = $this->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp二次定价操作日志
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function addErpUpdatePriceLog($data)
    {
        //if (count($data) <= 0) return [];
        $data = $this->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp二次定价操作日志
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function saveErpUpdatePriceLog($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = $this->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function findErpUpdatePriceLog($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }
}
