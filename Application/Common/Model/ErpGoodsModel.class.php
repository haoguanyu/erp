<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP商品信息模型类
// +----------------------------------
// |Author:senpai Time:2017.3.10
// +----------------------------------
class ErpGoodsModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取erp_goods表数据
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function erpGoodsList($where = [], $field, $offset = 0, $limit = 10, $order = 'id asc')
    {
        $erp_goods = M('erp_goods');
        $reg['recordsTotal'] = $this->getGoodsCount($where);
        $reg['data'] = $erp_goods->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp商品
    // +----------------------------------
    // |Author:senpai Time:2017.3.12
    // +----------------------------------
    public function addErpGoods($data)
    {
        if (count($data) <= 0) return [];
        $data = D('erp_goods')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp商品
    // +----------------------------------
    // |Author:senpai Time:2017.3.12
    // +----------------------------------
    public function saveErpGoods($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('erp_goods')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:senpai Time:2017.3.20
    // +----------------------------------
    public function findErpGoods($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:senpai Time:2017.3.20
    // +----------------------------------
    public function getGoodsCount($where = [])
    {
        return $this->where($where)->count();
    }
}
