<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 */
class BuySellModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
        $this->arr = [];
    }


    /**
     * 获取order表数据
     * @author YR
     * @return array
     */
    public function getStockInfo($where = "1=1")
    {
        $orderObj = M('Buy_sell_stock');
        $orderObj->where($where);
        $reg = $orderObj->order('time asc')->select();
        return $reg;
    }

    /**
     * 获取order表数据
     * @author May
     * @return array
     */
    public function getOne($where = "1=1")
    {
        $orderObj = M('Buy_sell');
        $orderObj->where($where);
        $reg = $orderObj->order('deal_time asc')->find();
        return $reg;
    }

    /**
     * 获取order表数据
     * @author May
     * @return array
     */
    public function getAllInfo($where = "1=1")
    {
        $orderObj = M('Buy_sell');
        $orderObj->where($where);
        $reg = $orderObj->order('deal_time asc')->select();
        return $reg;
    }

    /**
     * 删除buy_sell表数据
     * @author May
     * @return array
     */
    public function deleteStock($where = "1=1")
    {
        $ret = false;
        if (empty($where)) {
            return $ret;
        } else {
            $orderObj = M('Buy_sell_stock');
            $orderObj->where($where);
            $ret = $orderObj->delete();
            return $ret;
        }

    }

    /**
     * 修改buy_sell表数据
     * @author May
     * @return array
     */
    public function saveInfo($where = "1=1", $data = null)
    {
        $ret = false;
        if (empty($data)) {
            return $ret;
        } else {
            $orderObj = M('Buy_sell');
            $orderObj->where($where);
            $ret = $orderObj->save($data);
            return $ret;
        }

    }

    /**
     * 添加buy_sell表数据
     * @author May
     * @return array
     */
    public function addInfo($data = null)
    {
        $ret = false;
        if (empty($data)) {
            return $ret;
        } else {
            $orderObj = M('Buy_sell');
            $orderObj->where($where);
            $ret = $orderObj->add($data);
            return $ret;
        }

    }
}
