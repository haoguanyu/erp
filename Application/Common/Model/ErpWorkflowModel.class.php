<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP审批流
// +----------------------------------
// |Author:qianbin Time:2017.05.02
// +----------------------------------
class ErpWorkflowModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取erp_workflow表数据
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function erpWorkflowList($where = [], $field, $offset = 0, $limit = 10, $order = 'id desc')
    {
        $erp_workflow = M('erp_workflow');
        $reg['recordsTotal'] = $this->getWorkflowCount($where);
        $reg['data'] = $erp_workflow->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
//        log_info('------------------------------------------------------>sql:');
//        log_info(var_export( M('erp_workflow')->getLastSql(),true));
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp审批流
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function addErpWorkflow($data=[])
    {
        if (count($data) <= 0) return [];
        $data = M('erp_workflow')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp审批流
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function saveErpWorkflow($where=[], $data=[])
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = M('erp_workflow')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function findErpWorkflow($where = [], $field = '')
    {
        return M('erp_workflow')->field($field)->where($where)->find();
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:qianbin Time:2017.05.02
    // +----------------------------------
    public function getWorkflowCount($where = [])
    {
        return M('erp_workflow')->where($where)->count();
    }
}