<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP审批流日志模型
// +----------------------------------
// |Author:qianbin Time:2017.05.02
// +----------------------------------
class ErpWorkFlowLogModel extends BaseModel
{
    // +----------------------------------
    // |Facilitator:获取erp_workflow_log表数据
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function erpWorkflowLogList($where = [], $field='',$order = 'id desc')
    {
        $erp_workflow_log = M('erp_workflow_log');
        $reg = $erp_workflow_log->where($where)->field($field)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp审批流日志
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function addErpWorkFlowLog($data)
    {
        if (count($data) <= 0) return [];
        $data = M('erp_workflow_log')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp审批流日志
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function saveErpWorkFlowLog($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = M('erp_workflow_log')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function findErpWorkFlowLog($where = [], $field = true)
    {
        return M('erp_workflow_log')->field($field)->where($where)->find();
    }
}
