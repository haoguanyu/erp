<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpAllocationOrderModel extends BaseModel
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
    public function getAllocationOrderList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'ao.id desc')
    {
        $data['recordsTotal'] = $this->getAllocationOrderCount($where);
        $data['sumTotal'] = $this->getAllocationOrderTotal($where);
        $data['data'] = $this
            ->alias('ao')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on ao.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse osh on ao.out_storehouse = osh.id', 'left')
            ->join('oil_erp_storehouse ish on ao.in_storehouse = ish.id', 'left')
//            ->join('oil_facilitator oft on ao.out_facilitator_id = oft.facilitator_id', 'left')
//            ->join('oil_facilitator ift on ao.in_facilitator_id = ift.facilitator_id', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_point_id = ofs.facilitator_skid_id and ao.out_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_point_id = ifs.facilitator_skid_id and ao.in_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_car ofc on ao.out_facilitator_point_id = ofc.id and ao.out_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_car ifc on ao.in_facilitator_point_id = ifc.id and ao.in_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_skid_id = ofs.facilitator_skid_id', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_skid_id = ifs.facilitator_skid_id', 'left')
            ->limit($offset, $limit)
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
    public function getAllocationOrderCount($where = [])
    {
        return $this ->alias('ao')
            ->where($where)
            ->join('oil_erp_goods g on ao.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse osh on ao.out_storehouse = osh.id', 'left')
            ->join('oil_erp_storehouse ish on ao.in_storehouse = ish.id', 'left')
//            ->join('oil_facilitator oft on ao.out_facilitator_id = oft.facilitator_id', 'left')
//            ->join('oil_facilitator ift on ao.in_facilitator_id = ift.facilitator_id', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_point_id = ofs.facilitator_skid_id and ao.out_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_point_id = ifs.facilitator_skid_id and ao.in_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_car ofc on ao.out_facilitator_point_id = ofc.id and ao.out_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_car ifc on ao.in_facilitator_point_id = ifc.id and ao.in_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_skid_id = ofs.facilitator_skid_id', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_skid_id = ifs.facilitator_skid_id', 'left')
            ->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllocationOrderTotal($where = [])
    {
        return $this ->alias('ao')
            ->field('sum(ao.num)/'.C('IO_NUM').' as total_num, sum(ao.actual_out_num)/'.C('IO_NUM').' as total_actual_out_num, sum(ao.actual_in_num)/'.C('IO_NUM').' as total_actual_in_num')
            ->where($where)
            ->join('oil_erp_goods g on ao.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse osh on ao.out_storehouse = osh.id', 'left')
            ->join('oil_erp_storehouse ish on ao.in_storehouse = ish.id', 'left')
//            ->join('oil_facilitator oft on ao.out_facilitator_id = oft.facilitator_id', 'left')
//            ->join('oil_facilitator ift on ao.in_facilitator_id = ift.facilitator_id', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_point_id = ofs.facilitator_skid_id and ao.out_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_point_id = ifs.facilitator_skid_id and ao.in_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_car ofc on ao.out_facilitator_point_id = ofc.id and ao.out_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_car ifc on ao.in_facilitator_point_id = ifc.id and ao.in_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_skid_id = ofs.facilitator_skid_id', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_skid_id = ifs.facilitator_skid_id', 'left')
            ->find();
    }

    /**
     *  修改保存调拨单
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function saveAllocationOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加调拨单
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addAllocationOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条调拨单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findAllocationOrder($where = [], $field = 'ao.*')
    {
        $data = $this
            ->alias('ao')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on ao.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse osh on ao.out_storehouse = osh.id', 'left')
            ->join('oil_erp_storehouse ish on ao.in_storehouse = ish.id', 'left')
            ->join('oil_facilitator oft on ao.out_facilitator_id = oft.facilitator_id', 'left')
            ->join('oil_facilitator ift on ao.in_facilitator_id = ift.facilitator_id', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_point_id = ofs.facilitator_skid_id and ao.out_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_point_id = ifs.facilitator_skid_id and ao.in_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_car ofc on ao.out_facilitator_point_id = ofc.id and ao.out_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_car ifc on ao.in_facilitator_point_id = ifc.id and ao.in_facilitator_point_type = 2', 'left')
            ->join('oil_facilitator_skid ofs on ao.out_facilitator_skid_id = ofs.facilitator_skid_id', 'left')
            ->join('oil_facilitator_skid ifs on ao.in_facilitator_skid_id = ifs.facilitator_skid_id', 'left')
            ->find();
        return $data;
    }

    public function getOneAllocationOrder($where = [], $field=true){
        $data = $this->field($field)->where($where)->find();
        return $data;
    }

    /**
     * 返回所有符合条件的调拨单列表数据
     * @param $where
     * @param string $field
     * @param string $order
     * @return mixed
     * @author xiaowen
     * @time 2017-05-12
     */
    public function erpAllocationOrderAlListData($where,$field = 'ao.*', $order = 'ao.id desc'){

        $data['data'] = $this
            ->alias('ao')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on ao.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse osh on ao.out_storehouse = osh.id', 'left')
            ->join('oil_erp_storehouse ish on ao.in_storehouse = ish.id', 'left')
            ->join('oil_facilitator oft on ao.out_facilitator_id = oft.facilitator_id', 'left')
            ->join('oil_facilitator ift on ao.in_facilitator_id = ift.facilitator_id', 'left')
//            ->join('oil_facilitator_skid ofs on ao.out_facilitator_point_id = ofs.facilitator_skid_id and ao.out_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_skid ifs on ao.in_facilitator_point_id = ifs.facilitator_skid_id and ao.in_facilitator_point_type = 1', 'left')
//            ->join('oil_facilitator_car ofc on ao.out_facilitator_point_id = ofc.id and ao.out_facilitator_point_type = 2', 'left')
//            ->join('oil_facilitator_car ifc on ao.in_facilitator_point_id = ifc.id and ao.in_facilitator_point_type = 2', 'left')
            ->join('oil_facilitator_skid ofs on ao.out_facilitator_skid_id = ofs.facilitator_skid_id', 'left')
            ->join('oil_facilitator_skid ifs on ao.in_facilitator_skid_id = ifs.facilitator_skid_id', 'left')
            ->order($order)
            ->select();
        return $data;

    }

    /**
     * 获取一条调拨单配送信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findAllocationShippingOrder($where = [], $field = 'ao.*')
    {
        $data = $this
            ->alias('ao')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on ao.goods_id = g.id', 'left')
            ->join('oil_facilitator ift on ao.in_facilitator_id = ift.facilitator_id', 'left')
            ->join('oil_facilitator_user ifu on ift.facilitator_id = ifu.facilitator_id and ifu.status = 1 and ifu.level = 1')
            ->join('oil_erp_stock_out so on ao.order_number = so.source_number')
            ->find();
        return $data;
    }

}
