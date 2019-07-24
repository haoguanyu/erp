<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 库存模型
 */
class ErpStockModel extends BaseModel
{

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockList($where = [], $field = true, $offset = 0, $limit = 10, $order = 's.id desc', $group = '')
    {
        $StockObj = M('ErpStock');
        $data['recordsTotal'] = $this->getStockCount($where);
        $data['sumTotal'] = $this->getStockTotal($where);
        $storehouse_type = stockType();
        $storehouse_type = array_keys($storehouse_type);
        $data['data'] = $StockObj->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on s.object_id = es.id and s.stock_type in ('.implode(',', $storehouse_type).')', 'left')
            ->join('oil_erp_supplier su on s.facilitator_id = su.id', 'left')
            ->join('oil_erp_region_goods rg on s.goods_id = rg.goods_id and s.region = rg.region and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->limit($offset, $limit)
            ->order($order)
            ->group($group)
            ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getAllStockList($where = [], $field = true, $order = 's.id desc', $group = '')
    {
        $StockObj = M('ErpStock');
        $data['recordsTotal'] = $this->getStockCount($where);
        $storehouse_type = stockType();
        $storehouse_type = array_keys($storehouse_type);
        $data['data'] = $StockObj->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on s.object_id = es.id and s.stock_type in ('.implode(',', $storehouse_type).')', 'left')
            ->join('oil_erp_supplier su on s.facilitator_id = su.id', 'left')
            ->join('oil_erp_region_goods rg on s.goods_id = rg.goods_id and s.region = rg.region and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->order($order)
            ->group($group)
            ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function stocktaking($where = [], $field = true, $order = 's.id desc')
    {
        $StockObj = M('ErpStock');
        $data['recordsTotal'] = $this->getStockCount($where);
        $data['data'] = $StockObj->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on s.object_id = es.id', 'left')
            ->join('oil_facilitator_skid fs on s.object_id = fs.facilitator_skid_id and s.stock_type = 4', 'left')
            ->join('oil_facilitator f on s.facilitator_id = f.facilitator_id', 'left')
            ->join('oil_erp_region_goods rg on s.goods_id = rg.goods_id and s.region = rg.region and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function retailstocktaking($where = [], $field = true, $offset = 0, $limit = 10, $order = 's.id desc')
    {
        $StockObj = M('ErpStock');
        $data['recordsTotal'] = $this->getStockCount($where);
        $data['data'] = $StockObj->alias('s')
            ->field($field)
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on s.object_id = es.id and s.stock_type in (1,2)', 'left')
            ->join('oil_facilitator_skid fs on s.object_id = fs.facilitator_skid_id and s.stock_type = 4', 'left')
            ->join('oil_facilitator f on s.facilitator_id = f.facilitator_id', 'left')
            ->join('oil_erp_region_goods rg on s.goods_id = rg.goods_id and s.region = rg.region and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->limit($offset, $limit)
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
    public function getStockCount($where = [])
    {
        return $this->alias('s')->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on s.object_id = es.id and s.stock_type in (1,2)', 'left')
            ->join('oil_facilitator_skid f on s.object_id = f.facilitator_skid_id and s.stock_type = 4', 'left')
            ->join('oil_erp_region_goods rg on s.goods_id = rg.goods_id and s.region = rg.region and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->count();
    }

    /**
     * 返回总合计
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockTotal($where = [])
    {
        return $this->alias('s')
            ->field('sum(ROUND(s.stock_num)/'.C('IO_NUM').') as total_stock_num, sum(ROUND(s.transportation_num)/'.C('IO_NUM').') as total_transportation_num,
             sum(ROUND(s.sale_reserve_num)/'.C('IO_NUM').') as total_sale_reserve_num, sum(ROUND(s.allocation_reserve_num)/'.C('IO_NUM').') as total_allocation_reserve_num,
             sum(ROUND(s.sale_wait_num)/'.C('IO_NUM').') as total_sale_wait_num, sum(ROUND(s.allocation_wait_num)/'.C('IO_NUM').') as total_allocation_wait_num,
             sum(ROUND(s.available_num)/'.C('IO_NUM').') as total_available_num, sum(IF(s.stock_type = 1,IFNULL(rg.available_sale_stock,0) - IFNULL(rg.available_use_stock,0) + IFNULL(s.available_num,0),0))/'.C('IO_NUM').' as total_current_available_sale_num')
            ->where($where)
            ->join('oil_erp_goods g on s.goods_id = g.id', 'left')
            ->join('oil_erp_storehouse es on s.object_id = es.id', 'left')
            ->join('oil_facilitator f on s.facilitator_id = f.facilitator_id', 'left')
            ->join('oil_erp_region_goods rg on s.goods_id = rg.goods_id and s.region = rg.region and rg.our_company_id = '.session('erp_company_id'), 'left')
            ->find();
    }

    /**
     *  修改保存库存
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     * @return bool $status
     */

    public function saveStock($data = [])
    {
        $status = false;
        if ($data) {
            $add_data = $data;
            unset($data['goods_id']);
            unset($data['object_id']);
            unset($data['stock_id']);
            unset($data['our_company_id']);
            unset($data['region']);
            unset($data['create_time']);
            $update_data = $data;
            $add_data['create_time'] = currentTime();
            $update_data['update_time'] = date('Y-m-d H:i:s', time()+1);
            $status = $this->add($add_data, [], $update_data);
            if($status !== false){
                $status = 1;
            }else{
                $status = 0;
            }
        }
        return $status;

    }

    /**
     * 获取一条库存信息
     * @param $where
     * @param $field
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findStock($where = [], $field = true)
    {
        $where['status'] = 1;
        $data = $this->field($field)->where($where)->find();
        //echo $this->getLastSql();
        if(empty($data)){
            $data = [
                    'stock_num' => 0,
                    'transportation_num' => 0,
                    'sale_reserve_num' => 0,
                    'allocation_reserve_num' => 0,
                    'sale_wait_num' => 0,
                    'allocation_wait_num' => 0,
                    'available_num' => 0,
                    'init_stock_num' => 0,
                ];
        }
        return $data;
    }

    /**
     * @param array $where
     * @param bool $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author qianbin
     * @time 2017-07-24
     */
    public function getStockData($where = [], $field = true, $order = 's.id desc', $group = '')
    {
        $StockObj = M('ErpStock');
        $data = $StockObj->alias('s')
            ->field($field)
            ->where($where)
            ->order($order)
            ->group($group)
            ->select();
        return $data;
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
    public function getStockGroup($file= "*", $where=[] , $group="id" , $order = "id asc"){
        $return = M("ErpStock")->field($file)->where($where)->group($group)->order($order)->select();
        return $return ;
    }
    /*
  * @detail:根据需求获取单个数据信息
  * @auther:小黑
  * @dete:2019-2-19
   * @params
   *      file:需要查询的字段
   *      where:查询条件
  */
    public function getStockfile($file, $where=[]){
        $return = M("ErpStock")->where($where)->getField($file);
        return $return ;
    }
}
