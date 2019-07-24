<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 */
class GoodsModel extends BaseModel
{

    /**
     * 获取order表数据
     * @author May
     * @return array
     */
    public function getGoodsInfo($where = "1=1")
    {
        $orderObj = M('goods');
        $orderObj->where($where);
        $reg = $orderObj->select();
        return $reg;
    }


}
