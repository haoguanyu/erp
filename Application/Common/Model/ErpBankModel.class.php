<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP银行信息模型类
// +----------------------------------
// |Author:qianbin Time:2018.08.03
// +----------------------------------
class ErpBankModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取银行信息表数据
    // +----------------------------------
    // |Author:qianbin Time:2018.08.03
    // +----------------------------------
    public function erpBankList($where = [], $field, $offset = 0, $limit = 10, $order = 'id desc')
    {
        $erp_bank = M('erp_bank');
        $reg['recordsTotal'] = $this->getBankCount($where);
        $reg['data'] = $erp_bank->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加银行信息
    // +----------------------------------
    // |Author:qianbin Time:2018.08.03
    // +----------------------------------
    public function addErpBank($data)
    {
        if (count($data) <= 0) return [];
        $data = D('erp_bank')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新银行信息
    // +----------------------------------
    // |Author:qianbin Time:2018.08.03
    // +----------------------------------
    public function saveErpBank($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('erp_bank')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2018.08.03
    // +----------------------------------
    public function findErpBank($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:qianbin Time:2018.08.03
    // +----------------------------------
    public function getBankCount($where = [])
    {
        return $this->where($where)->count();
    }
}
