<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP系统配置信息模型类
// +----------------------------------
// |Author:qianbin Time:2018.5.3
// +----------------------------------
class ErpConfigModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取erp_config表数据
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function erpConfigList($where = [], $field, $offset = 0, $limit = 10, $order = 'id asc')
    {
        $erp_config = M('erp_config');
        $reg['recordsTotal'] = $this->getConfigCount($where);
        $reg['data'] = $erp_config->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp系统配置
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function addErpConfig($data)
    {
        if (count($data) <= 0) return [];
        $data = D('erp_config')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp系统配置
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function saveErpConfig($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('erp_config')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function findErpConfig($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:qianbin Time:2018.5.3
    // +----------------------------------
    public function getConfigCount($where = [])
    {
        return $this->where($where)->count();
    }
}
