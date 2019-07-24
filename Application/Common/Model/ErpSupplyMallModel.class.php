<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 * 供货单模型
 */
class ErpSupplyMallModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-03-13
     */
    public function getSupplyList($where = [], $field = true, $offset = 0, $limit = 10, $order = 's.id desc')
    {
        //$orderObj = M('ErpSupplyMall');
        $data['recordsTotal'] = $this->getSupplyCount($where);
        $data['sumTotal'] = $this->getSupplyTotal($where);
        $data['data'] = $this->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_clients c on s.supply_company_id = c.id', 'left')
            ->join('oil_user u on s.supply_user_id = u.id', 'left')
            ->join('oil_depot d on s.depot_id = d.id', 'left')
            ->join('oil_dealer dl on s.dealer_id = dl.id', 'left')
            ->join('oil_erp_region_goods rg on rg.region = s.region and rg.goods_id = s.goods_id and rg.our_company_id = '.session('erp_company_id'), 'left')
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
     * @time 2017-03-13
     */
    public function getSupplyCount($where = [])
    {
        return $this->alias('s')->where($where)->join('__ERP_GOODS__ g on s.goods_id = g.id', 'left')->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author xiaowen
     * @time 2017-03-13
     */
    public function getSupplyTotal($where = [])
    {
        return $this->alias('s')->where($where)->field('sum(sale_num)/'.C('IO_NUM').' as total_sale_num')->join('__ERP_GOODS__ g on s.goods_id = g.id', 'left')->find();
    }

    /**
     *  修改保存供货单
     * @param array $where
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-13
     */
    public function saveSupply($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加供货单
     * @param array $data
     * @return bool
     * @author xiaowen
     * @time 2017-03-13
     */
    public function addSupply($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条供货单信息
     * @param $where
     * @return array
     */
    public function findSupply($where = [])
    {
        return $this->where($where)->find();
    }

    /**
     * 获取一条包含关联内容的供货单完整信息
     * @param $where
     * @param $field
     * @return array
     */
    public function findSupplyAllInfo($where = [], $field = '')
    {

        $data = $this->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_clients c on s.supply_company_id = c.id', 'left')
            ->join('oil_user u on s.supply_user_id = u.id', 'left')
            ->join('oil_depot d on s.depot_id = d.id', 'left')
            ->join('oil_dealer dl on s.dealer_id = dl.id', 'left')
            ->find();

        return $data;

    }

    /**
     * 获取所有符合条件供货单
     * @param array $where
     * @param  $field
     * @param string $order
     * @return array
     * @author xiaowen
     * @time 2017-5-24
     */
    public function getSupplyByWhere($where = [], $field = true,$order = 's.id desc')
    {
        $data= $this->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_clients c on s.supply_company_id = c.id', 'left')
            ->join('oil_user u on s.supply_user_id = u.id', 'left')
            ->join('oil_depot d on s.depot_id = d.id', 'left')
            ->join('oil_dealer dl on s.dealer_id = dl.id', 'left')
            ->order($order)
            ->select();
        return $data;
    }

}
