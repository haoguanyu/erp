<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpStockOutModel extends BaseModel
{

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getStockOutList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'so.id desc')
    {

        $data['recordsTotal'] = $this->getStockOutCount($where);
        $data['sumTotal'] = $this->getStockOutTotal($where);
//        var_dump($this->getLastSql());exit;
        $data['data'] = $this
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')

            ->join('oil_erp_allocation_order eo on eo.id = so.source_object_id and eo.order_number = so.source_number', 'left')

            ->join('oil_depot d on o.depot_id = d.id', 'left')

            ->join('oil_erp_returned_order ro on ro.order_number = so.source_number and so.outbound_type = 3', 'left')
            ->join('oil_erp_purchase_order po on ro.source_order_number = po.order_number and ro.order_type = 2', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 零售销售单出库单列表
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getRetailStockOutList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'so.id desc')
    {

        $data['recordsTotal'] = $this->getRetailStockOutCount($where);
        $data['data'] = M('ErpStockOutRetail')
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_retail_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }
    /**
     * @param array $where
     * @param bool $field
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllStockOutList($where = [], $field = true, $order = 'so.id desc')
    {

        $data['recordsTotal'] = $this->getStockOutCount($where);
        $data['data'] = $this
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')

            ->join('oil_depot d on o.depot_id = d.id', 'left')

            ->join('oil_erp_returned_order ro on ro.order_number = so.source_number and so.outbound_type = 3', 'left')
            ->join('oil_erp_purchase_order po on ro.source_order_number = po.order_number and ro.order_type = 2', 'left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getStockOutCount($where = [])
    {
        return $this ->alias('so')
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->join('oil_erp_allocation_order eo on eo.id = so.source_object_id and so.source_number = eo.order_number', 'left')
            ->join('oil_erp_returned_order ro on ro.order_number = so.source_number and so.outbound_type = 3', 'left')
            ->join('oil_erp_purchase_order po on ro.source_order_number = po.order_number and ro.order_type = 2', 'left')
            ->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getStockOutTotal($where = [])
    {
        return $this ->alias('so')
            ->field('sum(so.actual_outbound_num)/'.C('IO_NUM').' as total_actual_outbound_num, sum(so.outbound_num)/'.C('IO_NUM').' as total_outbound_num')
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            //->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_erp_allocation_order eo on eo.id = so.source_object_id and so.source_number = eo.order_number', 'left')
            ->join('oil_erp_returned_order ro on ro.order_number = so.source_number and so.outbound_type = 3', 'left')
            ->join('oil_erp_purchase_order po on ro.source_order_number = po.order_number and ro.order_type = 2', 'left')
            ->find();
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getRetailStockOutCount($where = [])
    {
         return M('ErpStockOutRetail')->alias('so')
            ->where($where)
             ->join('oil_erp_sale_retail_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
             ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->join('oil_facilitator es on o.storehouse_id = es.facilitator_id and o.order_type = 2', 'left')
            //->join('oil_erp_allocation_order eo on eo.id = so.source_object_id', 'left')
            ->count();
    }


    /**
     *  修改保存出库单
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function saveStockOut($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加出库单
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addStockOut($data = [])
    {
        return $this->add($data);
    }
    /**
     * @desc添加出库单
     * @param array $data
     * @return bool
     * @author 小黑
     * @time 2019-02-25
     */
    public function addStockOutAll($data = [])
    {
        return $this->addAll($data);
    }
    /**
     * 获取一条出库单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findStockOut($where = [], $field = 'so.*')
    {
        $data = $this
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse sh on so.storehouse_id = sh.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_erp_allocation_order eo on eo.id = so.source_object_id and eo.order_number = so.source_number', 'left')
            ->join('oil_erp_storehouse oes on oes.id = eo.out_storehouse', 'left')
            ->find();
        return $data;

    }

    public function getOneStockOut($where = []){
        $data = $this->where($where)->find();
        return $data;
    }

    /**
     * 获取出库计划
     * @param array $where
     * @param bool $field
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getStockOutPlanList($where = [], $field = true)
    {
        $data = $this
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->find();
        return $data;
    }

    # ---------------------------------------------------分离销售单表和零售销售单---------------------------
    /**
     * @param array $where
     * @param bool $field
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllRetailStockOutList($where = [], $field = true, $order = 'so.id desc')
    {

        $data['recordsTotal'] = $this->getStockOutRetailCount($where);
        $data['data'] = M('ErpStockOutRetail')
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_retail_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->join('oil_erp_allocation_order eo on eo.id = so.source_object_id and eo.order_number = so.source_number', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on so.auditor_id = dl.id', 'left')
            ->join('oil_erp_returned_order ro on ro.order_number = so.source_number and so.outbound_type = 3', 'left')
            ->join('oil_erp_purchase_order po on ro.source_order_number = po.order_number and ro.order_type = 2', 'left')
            ->join('oil_dealer odp on odp.id = po.buyer_dealer_id', 'left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getStockOutRetailCount($where = [])
    {
        return M('ErpStockOutRetail')->alias('so')
            ->where($where)
            ->join('oil_erp_sale_retail_order o on o.id = so.source_object_id and so.source_number = o.order_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->join('oil_erp_allocation_order eo on eo.id = so.source_object_id and so.source_number = eo.order_number', 'left')
            ->join('oil_erp_returned_order ro on ro.order_number = so.source_number and so.outbound_type = 3', 'left')
            ->join('oil_erp_purchase_order po on ro.source_order_number = po.order_number and ro.order_type = 2', 'left')
            ->count();
    }

    /**
     * 获取销售单需求池
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author qianbin
     * @time 2017-03-31
     */
    public function getSaleStockOutPoolList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'so.id desc')
    {

        $data['recordsTotal'] = $this->getSaleStockOutPoolCount($where);
        //$data['sumTotal'] = $this->getStockOutTotal($where);
        $data['data'] = $this
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 获取销售单需求池数量
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getSaleStockOutPoolCount($where = [])
    {
        return $this ->alias('so')
            ->where($where)
            ->join('oil_erp_sale_order o on o.id = so.source_object_id and so.source_number = o.order_number','left')
            ->count();
    }


    /**
     * 获取调拨单需求池
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author qianbin
     * @time 2017-03-31
     */
    public function getAllocationStockOutPoolList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'so.id desc')
    {

        $data['recordsTotal'] = $this->getAllocationStockOutPoolCount($where);
        //$data['sumTotal'] = $this->getStockOutTotal($where);
        $data['data'] = $this
            ->alias('so')
            ->field($field)
            ->where($where)
            ->join('oil_erp_allocation_order o on o.id = so.source_object_id and o.order_number = so.source_number','left')
            ->join('oil_erp_goods g on so.goods_id = g.id', 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 获取调拨单需求池数量
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllocationStockOutPoolCount($where = [])
    {
        return $this ->alias('so')
            ->where($where)
            ->join('oil_erp_allocation_order o on o.id = so.source_object_id and so.source_number = o.order_number','left')
            ->count();
    }
    /*
     * @params:
     *  where：查询条件
     * @return:
     * @desc:根据条件获取数据和
     * @author:小黑
     * @time：2019-2-21nt
     */
    public function stockOutCount($where){
        return $this->where($where)->count() ;
    }
    /*
     * @params:
     *  where：查询条件
     * @return:
     * @desc:根据条件获取数据和
     * @author:小黑
     * @time：2019-2-21nt
     */
    public function stockOutGetField($where , $field , $group = ""){
        $sql =  $this->where($where) ;
        if(!empty($group)){
            $sql = $sql->group($group);
        }
        return $sql->getField($field) ;
    }
    /*
    * @params:
    *  where：查询条件
    * @return:
    * @desc:根据条件获取数据和
    * @author:小黑
    * @time：2019-2-21nt
    */
    public function stockOutLists($where){
        return $sql =  $this->where($where)->select() ;
    }
}
