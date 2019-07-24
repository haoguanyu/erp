<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 * 销售单模型
 */
class ErpSaleRetailOrderModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author guanyu
     * @time 2018-08-29
     */
    public function saleAnalysisRetail($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSaleAnalysisRetailCount($where,$field);
        $data['sumTotal'] = $this->getSaleAnalysisRetailTotal($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_stock_out_retail so on so.source_number = o.order_number and so.outbound_status = 10', 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->group('o.order_number')
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2018-08-29
     */
    public function getSaleAnalysisRetailCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_stock_out_retail so on so.source_number = o.order_number and so.outbound_status = 10', 'left')
            ->count('distinct o.id');
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author guanyu
     * @time 2018-08-29
     */
    public function getSaleAnalysisRetailTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(so.actual_outbound_litre)/'.C('IO_NUM').' as total_outbound_num_litre,
            sum(o.order_amount)/'.C('IO_NUM').' as total_order_amount,
            sum(IF(ISNULL(so.actual_outbound_num),0,so.actual_outbound_num * so.cost))/'.C('IO_NUM').'/'.C('IO_NUM').' as total_sale_cost,
            sum(o.order_amount)/'.C('IO_NUM').' - sum(IF(ISNULL(so.actual_outbound_num),0,so.actual_outbound_num * so.cost))/'.C('IO_NUM').'/'.C('IO_NUM').' as total_gross_profit,
            (sum(o.order_amount)/'.C('IO_NUM').' - sum(IF(ISNULL(so.actual_outbound_num),0,so.actual_outbound_num * so.cost))/'.C('IO_NUM').'/'.C('IO_NUM').') / (sum(o.order_amount)/'.C('IO_NUM').') as total_gross_profit_rate')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_stock_out_retail so on so.source_number = o.order_number and so.outbound_status = 10', 'left')
            ->find();
        return $result;
    }
    /*
     * @detail:更具需求排序后查询相关的数据
     * @auther:小黑
     * @dete:2018-12-6
      * @params
      *      file:需要查询的字段
      *      where:查询条件
      *      group:排序总段
     */
    public function getOrderListGroup($file= "*", $where=[] , $group="id" , $order = "id asc"){
        $return = M("erp_sale_retail_order")->field($file)->where($where)->group($group)->order($order)->select();
        return $return ;
    }
    /*
     * @detail:更具需求排序后查询相关的数据
     * @auther:小黑
     * @dete:2018-12-6
      * @params
      *      field:需要查询的字段
      *      where:查询条件
      *      group:排序总段
     */
    public function getRetailOrderField($filed,$where,$group){
        $return = $this->where($where)->group($group)->getField($filed , true) ;
        return $return ;
    }
}
