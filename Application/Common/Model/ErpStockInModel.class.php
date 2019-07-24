<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpStockInModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockInList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'si.id desc')
    {
        $StockInObj = M('ErpStockIn');
        $data['recordsTotal'] = $this->getStockInCount($where);
        $data['sumTotal'] = $this->getStockInTotal($where);
        $data['data'] = $StockInObj
            ->alias('si')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')

            ->join('oil_erp_allocation_order ao on ao.id = si.source_object_id and si.source_number = ao.order_number', 'left')

            ->join('oil_erp_returned_order ro on ro.id = si.source_object_id and si.source_number = ro.order_number', 'left')
            ->join('oil_erp_sale_order so on so.id = ro.source_order_id and ro.source_order_number = so.order_number', 'left')

            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->join('oil_dealer dl on si.auditor_id = dl.id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getAllStockInList($where = [], $field = true, $order = 'si.id desc')
    {
        $StockInObj = M('ErpStockIn');
        $data['recordsTotal'] = $this->getStockInCount($where);
        $data['data'] = $StockInObj
            ->alias('si')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')

            ->join('oil_erp_allocation_order ao on ao.id = si.source_object_id and si.source_number = ao.order_number', 'left')

            ->join('oil_erp_returned_order ro on ro.id = si.source_object_id and si.source_number = ro.order_number', 'left')
            ->join('oil_erp_sale_order so on so.id = ro.source_order_id and ro.source_order_number = so.order_number', 'left')

            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->join('oil_dealer dl on si.auditor_id = dl.id', 'left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockInCount($where = [])
    {
        return $this->alias('si')
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_erp_allocation_order ao on ao.id = si.source_object_id and si.source_number = ao.order_number', 'left')
            ->join('oil_erp_storehouse aes on ao.in_storehouse = aes.id', 'left')
            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->join('oil_erp_returned_order ro on ro.id = si.source_object_id and si.source_number = ro.order_number', 'left')
            ->join('oil_erp_sale_order so on so.id = ro.source_order_id and ro.source_order_number = so.order_number', 'left')
            ->where($where)
            ->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockInTotal($where = [])
    {
        return $this->alias('si')
            ->field('sum(si.actual_storage_num)/'.C('IO_NUM').' as total_actual_storage_num,sum(si.storage_num)/'.C('IO_NUM').' as total_storage_num')
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id AND si.source_number = o.order_number', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_erp_allocation_order ao on ao.id = si.source_object_id AND si.source_number = ao.order_number', 'left')
            ->join('oil_erp_storehouse aes on ao.in_storehouse = aes.id', 'left')
            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->join('oil_erp_returned_order ro on ro.id = si.source_object_id and si.source_number = ro.order_number', 'left')
            ->join('oil_erp_sale_order so on so.id = ro.source_order_id and ro.source_order_number = so.order_number', 'left')
            ->where($where)
            ->find();
    }

    /**
     *  修改保存发票
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveStockIn($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加发票
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addStockIn($data = [])
    {
        return $this->add($data);
    }

    public function getOneStockIn($where = []){
        $data = $this->where($where)->find();
        return $data;
    }


    /**
     * 获取一条发票信息
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findStockIn($where = [],$field)
    {
        return $this->alias('si')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number', 'left')
            ->join('oil_erp_storehouse pes on o.storehouse_id = pes.id', 'left')
            ->join('oil_erp_allocation_order ao on ao.id = si.source_object_id and si.source_number = ao.order_number', 'left')
            ->join('oil_erp_storehouse aes on ao.in_storehouse = aes.id', 'left')
            ->join('oil_erp_returned_order ro on ro.id = si.source_object_id and si.source_number = ro.order_number', 'left')
            ->join('oil_erp_sale_order so on so.id = ro.source_order_id and ro.source_order_number = so.order_number', 'left')
            ->join('oil_erp_storehouse res on so.storehouse_id = res.id', 'left')
            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->find();
    }

    /**
     * 获取采购单需求池
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author qianbin
     * @time 2017-08-23
     */
    public function getPurchaseOrderPoolList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'si.id desc')
    {

        $data['recordsTotal'] = $this->getPurchaseOrderPoolCount($where);
        $data['data'] = $this
            ->alias('si')
            ->field($field)
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and o.order_number = si.source_number','left')
            ->join('oil_erp_goods g on si.goods_id = g.id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 获取采购单需求池数量
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getPurchaseOrderPoolCount($where = [])
    {
        return $this ->alias('si')
            ->where($where)
            ->join('oil_erp_purchase_order o on o.id = si.source_object_id and si.source_number = o.order_number','left')
            ->count();
    }
    /**
     * @desc批量添加入库单
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2019-02-25
     */
    public function addStockInAll($data = [])
    {
        return  $this->addAll($data);
    }
    /**
     * @desc入库单获取部分数据
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2019-02-25
     */
    public function getFieldList($field , $where)
    {
        return  $this->where($where)->getField($field);
    }
    /**
     * @desc入库单获取部分数据
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2019-02-25
     */
    public function getCount( $where)
    {
        return  $this->where($where)->count();
    }
}
