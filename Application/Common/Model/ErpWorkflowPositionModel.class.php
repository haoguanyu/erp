<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP审批流职位表
// +----------------------------------
// |Author:qianbin Time:2017.05.02
// +----------------------------------
class ErpWorkflowPositionModel extends BaseModel
{
    // +----------------------------------
    // |Facilitator:获取erp_workflow_position表数据
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function WorkflowPositionList($where = [], $field, $offset = 0, $limit = 10, $order = 'id desc')
    {
        $erp_workflow_position = M('erp_workflow_position');
        $reg['recordsTotal'] = $this->getWorkflowPositionCount($where);
        $reg['data'] = $erp_workflow_position->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        //echo M()->getLastSql();
        return $reg;
    }

    public function getWorkflowPositionCount($where){
        return $this->where($where)
                ->count();
    }

    // +----------------------------------
    // |Facilitator:添加erp审批职位
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function addPosition($data)
    {
        if (count($data) <= 0) return [];
        $data = M('erp_workflow_position')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp审批职位
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function savePosition($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = M('erp_workflow_position')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function findPosition($where = [], $field = true)
    {
        return M('erp_workflow_position')->field($field)->where($where)->find();
    }
}