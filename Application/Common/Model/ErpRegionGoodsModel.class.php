<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:ERP商品信息模型类
// +----------------------------------
// |Author:senpai Time:2017.3.10
// +----------------------------------
class ErpRegionGoodsModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:获取erp_goods表数据
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function getGoodsList($where = [], $field, $offset = 0, $limit = 10, $region, $order = 'rg.id desc,g.id asc')
    {
        $erp_goods = M('erp_goods');
        $reg['recordsTotal'] = $this->getGoodsCount($where,$region);
        $reg['sumTotal'] = $this->getGoodsTotal($where,$region);
        $reg['data'] = $erp_goods->alias('g')->where($where)->field($field)
            ->join('oil_erp_region_goods as rg on g.id = rg.goods_id and rg.region = '.$region.' and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->join('oil_erp_stock as st on g.id = st.goods_id and st.region = '.$region.' and st.stock_type = 1 and st.status = 1 and st.our_company_id = '.session('erp_company_id'), 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
//        echo $erp_goods->getLastSql();exit;
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:添加erp商品
    // +----------------------------------
    // |Author:senpai Time:2017.3.12
    // +----------------------------------
    public function addRegionGoods($data)
    {
        if (count($data) <= 0) return [];
        $data = D('erp_region_goods')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:更新erp商品
    // +----------------------------------
    // |Author:senpai Time:2017.3.12
    // +----------------------------------
    public function saveRegionGoods($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('erp_region_goods')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:xiaowen Time:2017.3.24
    // +----------------------------------
    public function findRegionGoods($where = [], $field = true)
    {
        $data = M('erp_goods')->alias('g')->where($where)->field($field)
            ->join('oil_erp_region_goods as rg on g.id = rg.goods_id', 'left')
            ->find();
        if (empty($data)) {
            unset($where['rg.region']);
            $data = M('erp_goods')->alias('g')->where($where)->find();
            $data['region'] = 0;

        }
        //echo M()->getLastSql();
        return $data;
    }

    // +----------------------------------
    // |Facilitator:返回一条信息
    // +----------------------------------
    // |Author:xiaowen Time:2017.3.24
    // +----------------------------------
    public function findOneRegionGoods($where = [], $field = true)
    {
        return $this->field($field)->where($where)->find();
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:xiaowen Time:2017.3.20
    // +----------------------------------
    public function getGoodsCount($where = [],$region)
    {
        return M('erp_goods')->alias('g')->where($where)
            ->join('oil_erp_region_goods as rg on g.id = rg.goods_id and rg.region = '.$region.' and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->join('oil_erp_stock as st on g.id = st.goods_id and st.region = '.$region.' and st.stock_type = 1 and st.status = 1 and st.our_company_id = '.session('erp_company_id'), 'left')
            ->count();
    }

    // +----------------------------------
    // |Facilitator:返回总合计
    // +----------------------------------
    // |Author:xiaowen Time:2017.3.20
    // +----------------------------------
    public function getGoodsTotal($where = [],$region)
    {
        return M('erp_goods')
            ->field('IFNULL(sum(rg.available_sale_stock)/'.C('IO_NUM').',0) as total_available_sale_stock,
            IFNULL(sum(rg.available_use_stock)/'.C('IO_NUM').',0) as total_available_use_stock,
            IFNULL(sum(st.available_num)/'.C('IO_NUM').',0) as total_available_num,
            sum(IFNULL(rg.available_sale_stock,0) + IFNULL(st.available_num,0) - IFNULL(rg.available_use_stock,0))/'.C('IO_NUM').' as total_now_available_sale_stock')
            ->where($where)
            ->alias('g')
            ->join('oil_erp_region_goods as rg on g.id = rg.goods_id and rg.region = '.$region.' and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->join('oil_erp_stock as st on g.id = st.goods_id and st.region = '.$region.' and st.stock_type = 1 and st.status = 1 and st.our_company_id = '.session('erp_company_id'), 'left')
            ->find();
    }

    /**
     * 计算总数--新
     * @author xiaowen
     * @param array $where
     * @param string $field
     * @return array $erpGoods
     */
    public function getGoodsCountNew($where = [], $field = 'g.*')
    {
        $where_count = ['region' => intval($where['region'])];
        unset($where['region']);
        $erpGoods['data'] = M('erp_goods')->alias('g')->field($field)->where($where)
            //->join('oil_erp_region_goods as rg on g.id = rg.goods_id', 'left')
            ->select();
        $region_goods = $this->where($where_count)->getField('id, goods_id,region,price,last_price,available_sale_stock,available_use_stock,density,status');

        $goods_id_arr = array_column($region_goods, 'goods_id');

        foreach ($erpGoods['data'] as $key => $val) {

//            if(in_array($val['id'], $goods_id_arr) && !isset($region_goods[$val['region_goods_id']])){
//                unset($erpGoods['data'][$key]);
//            }
            if ($where['rg.status']) {
                if (!isset($region_goods[$val['region_goods_id']])) {
                    unset($erpGoods['data'][$key]);
                }

            } else {
//                if (in_array($val['id'], $goods_id_arr) && !isset($region_goods[$val['region_goods_id']])) {
//
//                    unset($erpGoods['data'][$key]);
//                }
            }

        }

        return count($erpGoods['data']);

    }

    public function getRegionGoodsList($where = [], $field, $offset = 0, $limit = 10, $order = 'rg.id desc')
    {
        $erp_goods = M('erp_region_goods');
        $reg['recordsTotal'] = $this->getRegionGoodsCount($where);
        $reg['sumTotal'] = $this->getRegionGoodsTotal($where);
        $reg['data'] = $erp_goods->alias('rg')->where($where)->field($field)
            ->join('oil_erp_goods as g on g.id = rg.goods_id', 'left')
            ->join('oil_erp_stock as st on g.id = st.goods_id and st.region = rg.region and st.stock_type = 1 and st.status = 1 and st.our_company_id = '.session('erp_company_id'), 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        //echo M()->getLastSql();
        return $reg;
    }

    // +----------------------------------
    // |Facilitator:返回总条数
    // +----------------------------------
    // |Author:xiaowen Time:2017.3.20
    // +----------------------------------
    public function getRegionGoodsCount($where = [])
    {
        return M('erp_region_goods')->alias('rg')->where($where)
            ->join('oil_erp_goods as g on g.id = rg.goods_id', 'left')
            ->count();
    }

    // +----------------------------------
    // |Facilitator:返回总合计
    // +----------------------------------
    // |Author:xiaowen Time:2017.3.20
    // +----------------------------------
    public function getRegionGoodsTotal($where = [])
    {
        return M('erp_region_goods')
            ->field('IFNULL(sum(rg.available_sale_stock)/'.C('IO_NUM').',0) as total_available_sale_stock,
            IFNULL(sum(rg.available_use_stock)/'.C('IO_NUM').',0) as total_available_use_stock,
            IFNULL(sum(st.available_num)/'.C('IO_NUM').',0) as total_available_num,
            sum(IFNULL(rg.available_sale_stock,0) + st.available_num - IFNULL(rg.available_use_stock,0))/'.C('IO_NUM').' as total_now_available_sale_stock')
            ->alias('rg')
            ->where($where)
            ->join('oil_erp_goods as g on g.id = rg.goods_id', 'left')
            ->join('oil_erp_stock as st on g.id = st.goods_id and st.region = rg.region and st.stock_type = 1 and st.status = 1 and st.our_company_id = '.session('erp_company_id'), 'left')
            ->find();
    }

    public function getAllRegionGoodsList($where = [], $field, $order = 'id desc')
    {
        $erp_goods = M('erp_region_goods');
        $reg['data'] = $erp_goods->where($where)->field($field)
            ->order($order)
            ->select();
        return $reg;
    }
}
