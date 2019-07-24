<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP审批流
// +----------------------------------
// |Author:qianbin Time:2017.05.02
// +----------------------------------
class ErpWorkFlowStepModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取erp_workflow_step表数据
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function workflowStepList($where = [], $field, $order = 'id desc')
    {
        $erp_workflow_step = M('erp_workflow_step');
        $reg = $erp_workflow_step->where($where)->field($field)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp审批流步骤
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function addWorkFlowStep($data)
    {
        if (count($data) <= 0) return [];
        $data = M('erp_workflow_step')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp审批流步骤
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function saveWorkFlowStep($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = M('erp_workflow_step')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function findWorkFlowStep($where = [], $field = true)
    {
        return M('erp_workflow_step')->field($field)->where($where)->find();
    }
}