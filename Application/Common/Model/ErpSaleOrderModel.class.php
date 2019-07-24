<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 * 销售单模型
 */
class ErpSaleOrderModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getSaleOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSaleOrderCount($where,$field);
        $data['sumTotal'] = $this->getSaleOrderTotal($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
                ->join('oil_erp_storehouse es on o.storehouse_id = es.id  and o.order_type = 1', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
                ->join('oil_erp_sale_invoice si on o.id = si.sale_order_id', 'left')
                ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
                ->join('oil_erp_returned_order r on o.id = r.source_order_id and o.order_number = r.source_order_number and r.order_status = 10', 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->group('o.id')
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * 零售销售单列表
     * @param array $where
     * @param string $field
     * @param int $offset
     * @param int $limit
     * @param string $order
     * @return mixed
     */
    public function getSaleRetailOrderList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSaleOrderRetailCount($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = M('ErpSaleRetailOrder')->alias('o')
                ->field($field)
                ->where($where)
                //->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
                ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
                ->join('oil_erp_sale_invoice si on o.id = si.sale_order_id', 'left')
                ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->group('o.id')
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllSaleOrderList($where = [], $field = '', $order = 'o.id desc')
    {

        $data['recordsTotal'] = $this->getSaleOrderCount($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
                ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
                ->join('oil_erp_sale_invoice si on o.id = si.sale_order_id', 'left')
                ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
                ->join('oil_erp_returned_order r on o.id = r.source_order_id and o.order_number = r.source_order_number and r.order_status = 10', 'left')
                ->order($order)
                ->group('o.id')
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
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getSaleOrderCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
//            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
//            //->join('oil_clients ec on o.our_company_id = ec.id', 'left')
//            ->join('oil_user us on o.user_id = us.id', 'left')
//            ->join('oil_clients cs on o.company_id = cs.id', 'left')
//            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
//            ->join('oil_depot d on o.depot_id = d.id', 'left')
//            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
//            ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
//            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
//            ->join('oil_depot d on o.depot_id = d.id', 'left')
//            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
//            ->join('oil_erp_sale_invoice si on o.id = si.sale_order_id', 'left')
//            ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
            ->count('distinct o.id');
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getSaleOrderTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(o.buy_num)/'.C('IO_NUM').' as total_buy_num, sum(o.delivery_money)/'.C('IO_NUM').' as total_delivery_money, sum(o.loss_num)/'.C('IO_NUM').' as total_loss_num,
              ROUND(sum(o.order_amount - ((o.loss_num * o.price) / '.C('IO_NUM').'))/'.C('IO_NUM').', 2) as total_order_amount, sum(o.collected_amount)/'.C('IO_NUM').' as total_collected_amount,
              sum(o.order_amount - o.collected_amount)/'.C('IO_NUM').' as total_no_collected_amount, sum(o.invoiced_amount)/'.C('IO_NUM').' as total_invoiced_amount,
              sum(o.order_amount - o.invoiced_amount)/'.C('IO_NUM').' as total_no_invoiced_amount, sum(o.entered_loss_amount)/'.C('IO_NUM').' as total_entered_loss_amount,
              sum(o.loss_num * o.price)/'.C('IO_NUM').'/'.C('IO_NUM').' as total_loss_amount, sum(o.outbound_quantity)/'.C('IO_NUM').' as total_outbound_quantity,
              sum(o.returned_goods_num)/'.C('IO_NUM').' as total_returned_goods_num, sum(o.order_amount - (o.loss_num + o.returned_goods_num) * o.price / '.C('IO_NUM').')/'.C('IO_NUM').' as total_amount,
              sum(o.order_amount / '.C('IO_NUM').' - o.collected_amount/'.C('IO_NUM').') as total_no_collected_amount,
              ROUND(sum(o.order_amount - ( cast(`o`.`returned_goods_num` as signed) * cast(`o`.`price` as signed)) / '.C('IO_NUM').' - ((o.loss_num * o.price) / '.C('IO_NUM').') - o.invoiced_amount)/'.C('IO_NUM').', 2) as total_no_invoiced_amount')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
//            ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
            ->find();
        return $result;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-05-09
     */
    public function getPurchaseRequireList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getPurchaseRequireCount($where);
        $data['sumTotal'] = $this->getPurchaseRequireTotal($where);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_user us on o.user_id = us.id', 'left')
                ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
                ->join('oil_erp_region_goods rg on o.goods_id = rg.goods_id and o.region = rg.region and rg.status = 1 and rg.our_company_id = '.session('erp_company_id'), 'left')
                ->limit($offset, $limit)
                ->order($order)
                ->group('o.id')
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
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getPurchaseRequireCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
            ->join('oil_erp_region_goods rg on o.goods_id = rg.goods_id and o.region = rg.region and rg.status = 1 and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->count();
        return $result;
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getPurchaseRequireTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(o.buy_num - o.acting_purchase_num)/'.C('IO_NUM').' as total_require_num')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_user us on o.user_id = us.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
            ->join('oil_erp_region_goods rg on o.goods_id = rg.goods_id and o.region = rg.region and rg.status = 1 and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->find();
        return $result;
    }

    /**
     *  修改保存供货单
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function saveSaleOrder($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加供货单
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-31
     */
    public function addSaleOrder($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findSaleOrder($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 获取一条交易单信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findOneSaleOrder($where = [], $field = 'o.*')
    {

        $data = $this->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
            ->join('oil_erp_customer_user cu on o.user_id = cu.id', 'left')
            ->join('oil_erp_customer c on o.company_id = c.id', 'left')
            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
            ->join('oil_depot d on o.depot_id = d.id', 'left')
            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
            ->find();
        //echo $this->getLastSql();
        return $data;
    }

    /**
     * 获取订单信息列表
     * @param array $where
     * @param string $order
     * @param bool|true $field
     * @return mixed
     */
    public function getSaleOrderInfoList($where = [], $order = 'id desc', $field = true){
        $data = $this->field($field)->where($where)->order($order)->select();
        return $data;
    }
    
    /**
     * 全部零售销售单
     * @param array $where
     * @param string $field
     * @param int $offset
     * @param int $limit
     * @param string $order
     * @return mixed
     */
    public function getAllSaleRetailOrderList($where = [], $field = '', $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSaleOrderRetailCount($where);
        if ($data['recordsTotal']) {
            $data['data'] = M('ErpSaleRetailOrder')->alias('o')
                ->field($field)
                ->where($where)
                //->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                 ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
                ->join('oil_depot d on o.depot_id = d.id', 'left')
                ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
                ->join('oil_erp_sale_invoice si on o.id = si.sale_order_id', 'left')
                ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
                ->order($order)
                ->group('o.id')
                ->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    public function getSaleOrderRetailCount($where = [])
    {
        $result = M('ErpSaleRetailOrder')->alias('o')
            ->where($where)
//            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
//            //->join('oil_clients ec on o.our_company_id = ec.id', 'left')
//            ->join('oil_user us on o.user_id = us.id', 'left')
//            ->join('oil_clients cs on o.company_id = cs.id', 'left')
//            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
//            ->join('oil_depot d on o.depot_id = d.id', 'left')
//            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
//            ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
            //->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
//            //->join('oil_clients ec on o.our_company_id = ec.id', 'left')
//            ->join('oil_user us on o.user_id = us.id', 'left')
//            ->join('oil_clients cs on o.company_id = cs.id', 'left')
//            ->join('oil_facilitator es on o.facilitator_id = es.facilitator_id and o.order_type = 2', 'left')
//            ->join('oil_facilitator_skid esk on o.storehouse_id = esk.facilitator_skid_id','left')
//            ->join('oil_depot d on o.depot_id = d.id', 'left')
//            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
//            ->join('oil_erp_sale_invoice si on o.id = si.sale_order_id', 'left')
//            ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
            ->count();
        return $result;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getSaleCollectionList($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.create_time desc')
    {
        $data['recordsTotal'] = M('sale_returned_order_view')->alias('o')->field($field)->where($where)->count();
        $data['sumTotal'] = $this->getSaleCollectionTotal($where);
        if ($data['recordsTotal']) {
            //$data['data'] = M('sale_returned_order_view')->alias('o')->field($field)->limit($param['start'], $param['length'])->where($where)->select();
            $data['data'] = M('sale_returned_order_view')->alias('o')->field($field)->limit($offset, $limit)->where($where)->order($order)->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getAllSaleCollectionList($where = [], $field = '', $order = 'o.id desc')
    {
        $data['recordsTotal'] = M('sale_returned_order_view')->alias('o')->field($field)->where($where)->count();
        $data['sumTotal'] = $this->getSaleCollectionTotal($where);
        if ($data['recordsTotal']) {
            //$data['data'] = M('sale_returned_order_view')->alias('o')->field($field)->limit($param['start'], $param['length'])->where($where)->select();
            $data['data'] = M('sale_returned_order_view')->alias('o')->field($field)->where($where)->order($order)->select();
        } else {
            $data['data'] = [];
        }
        return $data;
    }
    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-31
     */
    public function getSaleCollectionTotal($where = [])
    {
        $result = M('sale_returned_order_view')->alias('o')
            ->field('sum(o.buy_num)/'.C('IO_NUM').' as total_buy_num, sum(o.delivery_money)/'.C('IO_NUM').' as total_delivery_money, sum(o.loss_num)/'.C('IO_NUM').' as total_loss_num,
              ROUND(sum(o.order_amount - ((o.loss_num * o.price) / '.C('IO_NUM').'))/'.C('IO_NUM').', 2) as total_order_amount, sum(o.collected_amount)/'.C('IO_NUM').' as total_collected_amount,
              sum(o.order_amount - o.collected_amount)/'.C('IO_NUM').' as total_no_collected_amount, sum(o.invoiced_amount)/'.C('IO_NUM').' as total_invoiced_amount,
              sum(o.order_amount - o.invoiced_amount)/'.C('IO_NUM').' as total_no_invoiced_amount, sum(o.entered_loss_amount)/'.C('IO_NUM').' as total_entered_loss_amount,
              sum(o.loss_num * o.price)/'.C('IO_NUM').'/'.C('IO_NUM').' as total_loss_amount, sum(o.outbound_quantity)/'.C('IO_NUM').' as total_outbound_quantity,
              sum(o.returned_goods_num)/'.C('IO_NUM').' as total_returned_goods_num, sum(o.order_amount - (o.loss_num + o.returned_goods_num) * o.price / '.C('IO_NUM').')/'.C('IO_NUM').' as total_amount,
              sum(o.order_amount / '.C('IO_NUM').' - o.collected_amount/'.C('IO_NUM').') as total_no_collected_amount,
              ROUND(sum(o.order_amount - o.returned_goods_num * o.price / '.C('IO_NUM').' - ((o.loss_num * o.price) / '.C('IO_NUM').') - o.invoiced_amount)/'.C('IO_NUM').', 2) as total_no_invoiced_amount')
            ->where($where)
//            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
//            ->join('oil_erp_company ec on o.our_company_id = ec.company_id', 'left')
//            // ->join('oil_clients ec on o.our_company_id = ec.id', 'left')
//            ->join('oil_user us on o.user_id = us.id', 'left')
//            ->join('oil_clients cs on o.company_id = cs.id', 'left')
//            ->join('oil_erp_storehouse es on o.storehouse_id = es.id', 'left')
//            ->join('oil_depot d on o.depot_id = d.id', 'left')
//            ->join('oil_dealer dl on o.dealer_id = dl.id', 'left')
//            ->join('oil_erp_sale_collection sc on o.id = sc.sale_order_id', 'left')
            ->find();
        return $result;
    }


    /**
     * 获取一条调拨单配送信息
     * @param $where
     * @param $field
     * @return array
     * @author xiaowen
     * @time 2017-03-31
     */
    public function findSaleShippingOrder($where = [], $field = 'ao.*')
    {
        $data = $this
            ->alias('o')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_user u on o.user_id = u.id', 'left')
            ->join('oil_clients c on o.company_id = c.id', 'left')
            ->join('oil_erp_stock_out so on o.order_number = so.source_number')
            ->find();
        return $data;
    }


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
    public function saleAnalysisWhole($where = [], $field = '', $offset = 0, $limit = 10, $order = 'o.id desc')
    {
        $data['recordsTotal'] = $this->getSaleAnalysisWholeCount($where,$field);
        $data['sumTotal'] = $this->getSaleAnalysisWholeTotal($where,$field);
        if ($data['recordsTotal']) {
            $data['data'] = $this->alias('o')
                ->field($field)
                ->where($where)
                ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
                ->join('oil_erp_stock_out so on so.source_number = o.order_number and so.outbound_status = 10', 'left')
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
    public function getSaleAnalysisWholeCount($where = [])
    {
        $result = $this->alias('o')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_stock_out so on so.source_number = o.order_number and so.outbound_status = 10', 'left')
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
    public function getSaleAnalysisWholeTotal($where = [])
    {
        $result = $this->alias('o')
            ->field('sum(so.actual_outbound_num)/'.C('IO_NUM').' as total_outbound_quantity,
            sum(o.order_amount)/'.C('IO_NUM').' as total_order_amount,
            sum(IF(ISNULL(so.actual_outbound_num),0,so.actual_outbound_num * so.cost))/'.C('IO_NUM').'/'.C('IO_NUM').' as total_sale_cost,
            sum(o.order_amount)/'.C('IO_NUM').' - sum(IF(ISNULL(so.actual_outbound_num),0,so.actual_outbound_num * so.cost))/'.C('IO_NUM').'/'.C('IO_NUM').' as total_gross_profit,
            (sum(o.order_amount)/'.C('IO_NUM').' - sum(IF(ISNULL(so.actual_outbound_num),0,so.actual_outbound_num * so.cost))/'.C('IO_NUM').'/'.C('IO_NUM').') / (sum(o.order_amount)/'.C('IO_NUM').') as total_gross_profit_rate')
            ->where($where)
            ->join('oil_erp_goods g on o.goods_id = g.id', 'left')
            ->join('oil_erp_stock_out so on so.source_number = o.order_number and so.outbound_status = 10', 'left')
            ->find();
        return $result;
    }

}
