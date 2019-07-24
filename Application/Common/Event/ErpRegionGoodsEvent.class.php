<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpRegionGoodsEvent extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP商品逻辑处理层
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function _initialize()
    {

    }

    // +----------------------------------
    // |Facilitator:ERP商品列表
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function erpRegionGoodsList($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['goods_code']))) {
            $where['g.goods_code'] = ['like', '%' . trim($param['goods_code']) . '%'];
        }
        if (!empty(oilSource()[$param['source_from']])) {
            $where['g.source_from'] = oilSource()[$param['source_from']];
        }
        if (!empty($param['status'])) {
            $where['rg.status'] = $param['status'];
        }
        if ($param['goods_name'] != '请选择名称' && !empty($param['goods_name'])) {
            $where['g.goods_name'] = $param['goods_name'];
        }
        if ($param['grade'] != '请选择标号' && !empty($param['grade'])) {
            $where['g.grade'] = $param['grade'];
        }
        if ($param['level'] != '请选择级别' && !empty($param['level'])) {
            $where['g.level'] = $param['level'];
        }
        $where['g.status'] = 10;

        $allCity = provinceCityZone()['city'];
        if ($param['region']) {

            $field = 'g.*, rg.id as region_goods_id, rg.region, rg.price, rg.last_price, rg.available_sale_stock, rg.available_use_stock,
             rg.density, rg.status, rg.create_time, rg.creater, rg.update_time, rg.updater, rg.last_update_time, rg.current_use_stock,
             st.available_num';

            $erpGoods = $this->getModel('ErpRegionGoods')->getGoodsList($where, $field, $param['start'], $param['length'], $param['region']);
//            $stock_info = $this->getModel('ErpStock')->where(['region' => intval($param['region']),'stock_type' => 1])->getField('goods_id,available_num');

            $is_region_goods = 0;

            if (count($erpGoods['data'])) {
                $new_erpGoods = [];
                $i = 0;
                foreach ($erpGoods['data'] as $key => $val) {
                    $new_erpGoods[$i] = $val;
                    $i++;
                }
                $erpGoods['data'] = $new_erpGoods;
            } else {
                $erpGoods['data'] = [];
            }

        } else {
            $where['rg.our_company_id'] = session('erp_company_id');

            $field = 'g.*, rg.region, rg.price, rg.last_price, rg.available_sale_stock, rg.available_use_stock, rg.density, 
            rg.status as region_goods_status,rg.id as region_goods_id,st.available_num';

            $erpGoods = $this->getModel('ErpRegionGoods')->getRegionGoodsList($where, $field, $param['start'], $param['length']);
            $is_region_goods = 1;

        }
        if (count($erpGoods['data']) > 0) {

            foreach ($erpGoods['data'] as $k => $v) {
                if ($k == 0) {
                    $erpGoods['data'][$k]['sumTotal'] = $erpGoods['sumTotal'];
                }
                $erpGoods['data'][$k]['region'] = $v['region'] ? $allCity[$v['region']] : '--';
                $erpGoods['data'][$k]['price'] = $v['price'] ? getNum($v['price']) : '--';
                $erpGoods['data'][$k]['last_price'] = $v['last_price'] ? getNum($v['last_price']) : '--';
                $erpGoods['data'][$k]['available_sale_stock'] = $v['available_sale_stock'] ? getNum($v['available_sale_stock']) : '--';
                $erpGoods['data'][$k]['available_use_stock'] = $v['available_use_stock'] ? getNum($v['available_use_stock']) : 0;
                $erpGoods['data'][$k]['available_num'] = $v['available_num'] ? getNum($v['available_num']) : '0';
                $erpGoods['data'][$k]['sale_stock'] = $v['available_sale_stock'] ? getNum($v['available_sale_stock'] + $v['available_num'] - $v['available_use_stock']) : 0;
                $erpGoods['data'][$k]['density'] = $v['density'] ? $v['density'] : '--';
                $erpGoods['data'][$k]['level'] = $v['level'] ? $v['level'] : '--';
                //$erpGoods['data'][$k]['status_font'] = erpGoodsStatus($v['status']);
                $erpGoods['data'][$k]['status_show'] = $v['status'] ? erpRegionGoodsStatus($v['status']) : '--';
                $erpGoods['data'][$k]['is_region_goods'] = $is_region_goods;
                $erpGoods['data'][$k]['region_goods_id'] = $v['region_goods_id'] ? $v['region_goods_id'] : 0;

            }
        } else   //空数据
        {
            $erpGoods['data'] = [];
        }
        $erpGoods['recordsFiltered'] = $erpGoods['recordsTotal'];
        $erpGoods['draw'] = $_REQUEST['draw'];
        return $erpGoods;
    }

    /**
     * @param $param
     * @author xiaowen
     * @time 2017-3-24
     * @return array
     */
    public function updateGoods($param)
    {

        if (getCacheLock('ErpRegionGoods/updateGoods')) return ['status' => 99, 'message' => $this->running_msg];

        if (!intval($param['goods_id']) && $param['is_region_goods'] == 0) {
            $result = [
                'status' => 2,
                'message' => '商品信息有误'
            ];
        } else if (!intval($param['region']) && $param['is_region_goods'] == 0) {
            $result = [
                'status' => 3,
                'message' => '城市不能为空'
            ];
        } else if ($param['is_region_goods'] == 1 && !$param['region_goods_id']) {
            $result = [
                'status' => 4,
                'message' => '区域商品信息有误'
            ];
        } else if (!trim($param['price'])) {
            $result = [
                'status' => 5,
                'message' => '请输入批发售价'
            ];
        } else if (!trim($param['available_sale_stock'])) {
            $result = [
                'status' => 6,
                'message' => '请输入可售库存'
            ];
        } else if (!trim($param['available_use_stock'])) {
            $result = [
                'status' => 7,
                'message' => '请输入可用库存'
            ];
        } else if (!trim($param['density'])) {
            $result = [
                'status' => 8,
                'message' => '请输入密度'
            ];
        } else if (trim($param['status'])) {
            $result = [
                'status' => 9,
                'message' => '请选择状态'
            ];
        }
        setCacheLock('ErpRegionGoods/updateGoods', 1);
        M()->startTrans();
        //取该商品在该地区所有城市仓的可用库存 做为区域维护的快照可用数量 edit xiaowen time 2017-05-03
        $stock_info = $this->getEvent('ErpStock')->getStockInfo(['goods_id'=>$param['goods_id'], 'region'=>$param['region'], 'stock_type'=>1, 'status'=>1]);
        $available_num = !empty($stock_info) && isset($stock_info['available_num']) ? $stock_info['available_num'] : 0;
        if ($param['is_region_goods'] == 0) {
            $where['goods_id'] = $param['goods_id'];
            $where['region'] = $param['region'];
            $where['our_company_id'] = session('erp_company_id');
            $goodsInfo = $this->getGoodsByRegion($where);

            if ($goodsInfo['region']) {
                $id = $goodsInfo['region_goods_id'];

                $data = [
                    //'goods_id'=>intval($param['goods_id']),
                    //'region'=>intval($param['region']),
                    'price' => setNum(trim($param['price'])),
                    'last_price' => $goodsInfo['price'],
                    'available_sale_stock' => setNum(trim($param['available_sale_stock'])),
                    //'available_use_stock' => setNum(trim($param['available_use_stock'])),
                    'available_use_stock' => $available_num,
                    'density' => trim($param['density']),
                    'status' => $param['status'],
                    'update_time' => currentTime(),
                    'updater' => $this->getUserInfo('id'),
                ];

                $goods_status = $this->getModel('ErpRegionGoods')->saveRegionGoods($where, $data);
            } else {
                $data = [
                    'goods_id' => intval($param['goods_id']),
                    'region' => intval($param['region']),
                    'price' => setNum(trim($param['price'])),
                    'last_price' => 0,
                    'available_sale_stock' => setNum(trim($param['available_sale_stock'])),
                    //'available_use_stock' => setNum(trim($param['available_use_stock'])),
                    'available_use_stock' => $available_num,
                    'density' => trim($param['density']),
                    'status' => $param['status'],
                    'our_company_id' => session('erp_company_id'),
                    'create_time' => currentTime(),
                    'creater' => $this->getUserInfo('id'),
                ];

                $goods_status = $id = $this->getModel('ErpRegionGoods')->addRegionGoods($data);
            }
        } else {
            $goodsInfo = $this->findOneRegionGoods($param['region_goods_id']);
            $where['id'] = $id = $param['region_goods_id'];
            $data = [
                //'goods_id'=>intval($param['goods_id']),
                //'region'=>intval($param['region']),
                'price' => setNum(trim($param['price'])),
                'last_price' => $goodsInfo['price'],
                'available_sale_stock' => setNum(trim($param['available_sale_stock'])),
                //'available_use_stock' => setNum(trim($param['available_use_stock'])),
                'available_use_stock' => $available_num,
                'density' => trim($param['density']),
                'status' => $param['status'],
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('id'),
            ];
            $goods_status = $this->getModel('ErpRegionGoods')->saveRegionGoods($where, $data);
        }

        $log_data = [
            'region_goods_id' => $id,
            'log_info' => serialize($data),
            'price' => $data['price'],
            'available_sale_stock' => $data['available_sale_stock'],
        ];

        $log_status = $this->addRegionGoodsLog($log_data);

        //价格体系维护
        $system_param = [
            'goods_id' => $goodsInfo['goods_id'] ? $goodsInfo['goods_id'] : $param['goods_id'],
            'region' => $goodsInfo['region'] ? $goodsInfo['region'] : $param['region'],
            'price' => $param['price'],
            'available_sale_stock' => $param['available_sale_stock'],
            'density' => $param['density'],
            'status' => $param['status']
        ];
        $system_status = $this->priceSystem($system_param);

        if ($goods_status && $log_status && $system_status) {
            M()->commit();
            $result['status'] = 1;
            $result['message'] = '操作成功';
        } else {
            M()->rollback();
            $result['status'] = 0;
            $result['message'] = '操作失败';
        }
        cancelCacheLock('ErpRegionGoods/updateGoods');
        return $result;
    }

    /**
     * 价格体系维护
     * @param $param
     * @author guanyu
     * @time 2017-11-20
     * @return array
     */
    public function  priceSystem($param)
    {
        /**
         * 价格体系版本更新
         * 和瑞账套影响其他公司
         * 汇由账套影响誉州
         */
        if (session('erp_company_id') == 70) {
            $status_hy = $this->updateRegionGoodsPrice($param,3372);
            $status_yz = $this->updateRegionGoodsPrice($param,22);
            $status_ar = $this->updateRegionGoodsPrice($param,18);

            $status = $status_hy && $status_yz && $status_ar ? true : false;
        } elseif(session('erp_company_id') == 3372) {
            $status_yz = $this->updateRegionGoodsPrice($param,22);

            $status = $status_yz ? true : false;
        } else {
            $status = true;
        }

        return $status;
    }

    /**
     * 影响公司区域商品维护信息
     * @param $param
     * @author guanyu
     * @time 2017-11-20
     * @return array
     */
    public function  updateRegionGoodsPrice($param,$company_id)
    {
        $where['goods_id'] = $param['goods_id'];
        $where['region'] = $param['region'];

        $where['our_company_id'] = $company_id;
        $goodsInfo = $this->getGoodsByRegion($where);
        //取该商品在该地区所有城市仓的可用库存 做为区域维护的快照可用数量 edit xiaowen time 2017-05-03
        $stock_info = $this->getEvent('ErpStock')->getStockInfo(['goods_id'=>$param['goods_id'], 'region'=>$param['region'], 'stock_type'=>1, 'status'=>1 ,'our_company_id'=>$company_id]);
        $available_num = !empty($stock_info) && isset($stock_info['available_num']) ? $stock_info['available_num'] : 0;
        if ($goodsInfo['region']) {
            $id = $goodsInfo['region_goods_id'];
            $data = [
                'price' => setNum(trim($param['price'])),
                'last_price' => $goodsInfo['price'],
                'available_sale_stock' => setNum(trim($param['available_sale_stock'])),
                'available_use_stock' => $available_num,
                'density' => trim($param['density']),
                'status' => $param['status'],
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('id'),
            ];
            $goods_status = $this->getModel('ErpRegionGoods')->saveRegionGoods($where, $data);
        } else {
            $data = [
                'goods_id' => intval($param['goods_id']),
                'region' => intval($param['region']),
                'price' => setNum(trim($param['price'])),
                'last_price' => 0,
                'available_sale_stock' => setNum(trim($param['available_sale_stock'])),
                'available_use_stock' => $available_num,
                'density' => trim($param['density']),
                'status' => $param['status'],
                'our_company_id' => $company_id,
                'create_time' => currentTime(),
                'creater' => $this->getUserInfo('id'),
            ];
            $goods_status = $id = $this->getModel('ErpRegionGoods')->addRegionGoods($data);
        }
        $log_data = [
            'region_goods_id' => $id,
            'log_info' => serialize($data),
            'price' => $data['price'],
            'available_sale_stock' => $data['available_sale_stock'],
            'influence_by' => session('erp_company_id'),
        ];
        $log_status = $this->addRegionGoodsLog($log_data);

        $result = $goods_status && $log_status ? true : false;
        return $result;
    }

    /**
     * 直接直接查找区域商品价格
     * @param $id
     * @return array
     */
    public function findOneRegionGoods($id)
    {
        if ($id <= 0) return [];
        $field = 'g.*, rg.density, rg.region, rg.price, rg.last_price, rg.available_sale_stock, rg.available_use_stock, rg.status as region_goods_status,rg.id as region_goods_id,rg.creater,rg.create_time,rg.update_time,rg.updater';
        $data = $this->getModel('ErpRegionGoods')->findRegionGoods(['rg.id' => $id], $field);
        $data['creater_name'] = $data['creater'] ? dealerIdTologinName()[$data['creater']] : '';
        $data['updater_name'] = $data['updater'] ? dealerIdTologinName()[$data['updater']] : '';
        return $data;
    }

    /**
     * 通过城市 + 商品ID 查找区域商品价格
     * @param $param
     * @return array
     */
    public function getGoodsByRegion($param)
    {
        if ($param['goods_id'] && $param['region']) {
            $field = 'g.*, rg.region, rg.price, rg.last_price, rg.available_sale_stock, rg.available_use_stock, rg.density, rg.status as region_goods_status,rg.id as region_goods_id,rg.creater,rg.create_time,rg.update_time,rg.updater';

            $where = [
                'g.id' => $param['goods_id'],
                'rg.region' => $param['region'],
                'our_company_id'=>$param['our_company_id'] ? $param['our_company_id'] : session('erp_company_id')
            ];
            $data = $this->getModel('ErpRegionGoods')->findRegionGoods($where, $field);
            $data['creater_name'] = $data['creater'] ? dealerIdTologinName()[$data['creater']] : '';
            $data['updater_name'] = $data['updater'] ? dealerIdTologinName()[$data['updater']] : '';
        } else {
            $data = [];
        }
        return $data;
    }

    public function getRegionGoodsByParams($where = []){
        $data = $this->getModel('ErpRegionGoods')->alias('rg')->where($where)->join('oil_erp_goods g on rg.goods_id = g.id')->select();
        if(empty($data)){
            $data = [];
        }
        return $data;
    }

    public function getStockEvent(){
        return A('ErpStock', 'Event');
    }

    /**
     * 通过城市 + 商品ID 查找一条区域商品
     * @param $param
     * @return array
     */
    public function getOneGoodsByRegion($param)
    {
        if ($param['goods_id'] && $param['region']) {
            $data = $this->getModel('ErpRegionGoods')->where(['goods_id' => $param['goods_id'], 'region' => $param['region'], 'our_company_id'=>session('erp_company_id')])->find();

        } else {
            $data = [];
        }
        return $data;
    }

    /**
     * 插入销售单操作日志
     * @author xiaowen
     * @param $data
     * @return mixed
     */
    public function addRegionGoodsLog($data)
    {
        if ($data) {
            $data['updater'] = $this->getUserInfo('dealer_name') ? $this->getUserInfo('dealer_name') : 0;
            $data['updater_id'] = $this->getUserInfo('id')  ? $this->getUserInfo('id') : 0;
            $data['create_time'] = currentTime();
            $status = $this->getModel('ErpRegionGoodsLog')->add($data);
        }
        return $status;
    }

    /**
     * 导入区域商品维护密度
     * @param $data
     * @author guanyu
     * @time 2018-02-27
     */
    public function importRegionGoodsDensity($data)
    {
        if($data){
            $region_goods_info = D('ErpRegionGoods')
                ->where(['status' => 1,'our_company_id' => 3372])
                ->field('id, goods_id, region, our_company_id, density')
                ->select();

            foreach ($region_goods_info as $key => $value) {
                $region_goods_info[$value['region'].'_'.$value['goods_id']] = $value;
                unset($region_goods_info[$key]);
            }
            $i = 1;
            $k = 0;
            $status_all = true;
            M()->startTrans();
            foreach($data as $key => $value){
                if ((trim($value[0]) && trim($value[3]) && trim($value[9])) || $value[9] < 0.7 || $value[9] > 1) {

                    if (array_key_exists(trim($value[0]).'_'.trim($value[3]),$region_goods_info)) {
                        if (trim($value[9]) == $region_goods_info[trim($value[0]).'_'.trim($value[3])]['density']) {
                            $region_goods_status = true;
                        } else {
                            $region_goods_data = [
                                'density' => trim($value[9]),
                                'update_time' => currentTime(),
                                'last_update_time' => currentTime(),
                            ];
                            $region_goods_status = $this->getModel('ErpRegionGoods')->saveRegionGoods(['id' => $region_goods_info[trim($value[0]).'_'.trim($value[3])]['id']], $region_goods_data);
                        }
                    } else {
                        $region_goods_data = [
                            'goods_id' => $value[3],
                            'region' => trim($value[0]),
                            'density' => trim($value[9]),
                            'our_company_id' => 3372,
                            'create_time' => currentTime(),
                        ];
                        $region_goods_status = $this->getModel('ErpRegionGoods')->addRegionGoods($region_goods_data);
                    }
                    $status_all = $status_all && $region_goods_status ? true :false;
                    //------------------------------------------------------------------------------------------------
                    echo '第'. $i . '条数据已处理！<br/>';
                } else {
                    echo trim($value[0]) . '&&' . trim($value[1])  . '&&' .   trim($value[2])  . '&&' .  trim($value[3]);
                    echo '<br/>';
                    echo '第'. $i . '条数据导入有误，（地区-仓库类型-仓库-商品代码） 必须填写，请重新导入！';
                    exit;
                }
                $i++;
                $k++;
            }

            if($status_all){
                M()->commit();
            }else{
                M()->rollback();
            }
            $status_str = $status_all ? '成功' : '失败';
            echo '^__^，数据已全部导入完毕。处理状态：' . $status_str;

        }
    }
}


