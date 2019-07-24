<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP仓库信息模型类
// +----------------------------------
// |Author:senpai Time:2017.3.24
// +----------------------------------
class ErpStorehouseModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取erp_storehouse表数据
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function erpStorehouseList($where = [], $field, $offset = 0, $limit = 10, $order = 'id asc')
    {
        $erp_storehouse = M('erp_storehouse');
        $reg['recordsTotal'] = $this->getStorehouseCount($where);
        $reg['data'] = $erp_storehouse->where($where)->field($field)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:获取erp_storehouse表所有数据
    // +----------------------------------
    // |Author:senpai Time:2017.05.11
    // +----------------------------------
    public function getAllErpStorehouseList($where = [], $field, $order = 'id asc')
    {
        $erp_storehouse = M('erp_storehouse');
        $reg['recordsTotal'] = $this->getStorehouseCount($where);
        $reg['data'] = $erp_storehouse->where($where)->field($field)
            ->order($order)
            ->select();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp仓库
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function addErpStorehouse($data)
    {
        if (count($data) <= 0) return [];
        $data = D('erp_storehouse')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp仓库
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function saveErpStorehouse($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('erp_storehouse')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function findErpStorehouse($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function getStorehouseCount($where = [])
    {
        return $this->where($where)->count();
    }
    /*
  *  @params:
  *      data:
  *         array
  *  @return :
  *  @desc:获取仓库的信息
  *  @author:小黑
  *  @time:2019-2-19
  */
    public function getListField($field , $where){
        $model = $this;
        if(!empty($where)){
            $model = $model->where($where) ;
        }
        return $model->getField($field);
    }
}
