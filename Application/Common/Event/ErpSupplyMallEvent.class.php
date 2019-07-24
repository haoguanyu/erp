<?php
/**
 * 自营商城供货单业务处理层
 * @author xiaowen
 * @time 2017-03-10
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpSupplyMallEvent extends BaseController
{

    /**
     * 新增供货单操作
     * @param $param
     * @author xiaowen
     * @time 2017-5-22
     * @return $result
     */
    public function actAddSupply($param = [])
    {
        if (getCacheLock('ErpSupplyMall/actAddSupply')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpSupplyMall/actAddSupply', 1);
        $session = session();

        if (count($param) <= 0) {
            $result['status'] = 101;
            $result['message'] = '参数有误';
        } elseif (trim($param['supply_user_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(a);
        } elseif (trim($param['supply_company_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(b);
        } elseif (trim($param['dealer_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(c);
        } elseif (trim($param['dealer_id']) != $session['erp_adminInfo']['id']) {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(w);
        } elseif (trim($param['dealer_name']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(d);
        } elseif (trim($param['goods_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(e);
        } elseif (trim($param['goods_status']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(f);
        } elseif (trim($param['region']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(g);
        } elseif (trim($param['depot_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(h);
        } elseif (trim($param['price']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(i);
        } elseif (trim($param['price']) <= 0) {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(y);
        } elseif (trim($param['sale_num']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(j);
        } elseif (trim($param['min_sale_num']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(k);
        } elseif (trim($param['max_once_num']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(l);
        } elseif (trim($param['pick_up_way']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(m);
        } elseif (trim($param['invoice_type']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(n);
        } elseif (trim($param['is_service']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(o);
        } elseif (trim($param['mall_goods']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(p);
        } elseif (trim($param['show_front']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(q);
        } elseif (trim($param['background_buy']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(r);
        } elseif (trim($param['is_recommend']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(s);
        } elseif (trim($param['freight_free']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(t);
        } elseif (trim($param['send_sms']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(u);
        } elseif(trim($param['storehouse_id']) == ""){
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(z);
        }else {
            //判断是否存在同地区同账套同商品数据
            //$is_exist = $this->getModel('ErpSupplyMall')->where(['supply_company_id'=>session('erp_company_id'),'region'=>$param['region'],'goods_id'=>$param['goods_id']])->count();
            //同地区同商品当天有效的商城发布单不能同时存在 edit xiaowen 201-6-13
            $is_exist = $this->getModel('ErpSupplyMall')->where(['is_available'=>1,'region'=>$param['region'],'goods_id'=>$param['goods_id'],'create_time' => ['between', [date("Y-m-d 00:00:00"), date("Y-m-d 23:59:59")]]])->count();
            if ($is_exist > 0) {
                $result['status'] = 101;
                $result['message'] = '已存在记录，请在商城发布单中进行单据编辑';
            } else {
                $erp_supply_data = [
                    'supply_number' => erpCodeNumber(10)['order_number'],
                    'supply_user_id' => $param['supply_user_id'],
                    'supply_company_id' => $param['supply_company_id'],
                    'dealer_id' => $param['dealer_id'],
                    'dealer_name' => $param['dealer_name'],
                    'goods_id' => $param['goods_id'],
                    'goods_status' => $param['goods_status'],
                    'storehouse_id' => $param['storehouse_id'],
                    'region' => $param['region'],
                    'depot_id' => $param['depot_id'],
                    'price' => setNum($param['price']),
                    'sale_num' => setNum($param['sale_num']),
                    'min_sale_num' => setNum($param['min_sale_num']),
                    'max_once_num' => setNum($param['max_once_num']),
                    'lock_num' => 0,
                    'pick_up_way' => $param['pick_up_way'],
                    'invoice_type' => $param['invoice_type'],
                    'is_service' => $param['is_service'],
                    'mall_goods' => $param['mall_goods'],
                    'show_front' => $param['show_front'],
                    'is_recommend' => $param['is_recommend'],
                    //优惠1.0暂时关闭，之后会开放
                    'preferential_activities' => 0,
                    'discount_amount' => 0,

                    'remittance_info' => $param['remittance_info'],
                    'create_time' => currentTime(),
                    'creater' => $session['erp_adminInfo']['id'],
                    'status' => 1,
                    'send_sms' => $param['send_sms'],
                    'freight_free' => $param['freight_free'],
                    'background_buy' => $param['background_buy'],
                    'freight_money' => setNum($param['freight_money']),
                    'remark' => $param['remark']
                ];

                //事务开启
                M()->startTrans();
                //添加erp供货单信息
                $erp_supply = $this->getModel('ErpSupplyMall')->addSupply($erp_supply_data);

                $erp_supply_info = $this->getModel('ErpSupplyMall')->findSupply(['id' => intval($erp_supply)]);
                $erp_supply_log_data = [
                    'price' => setNum($param['price']),
                    'sale_num' => setNum($param['sale_num']),
                    'supply_id' => $erp_supply,
                    'supply_info' => serialize($erp_supply_info)
                ];
                //添加erp供货单记录
                $erp_supply_log = $this->addSupplyLog($erp_supply_log_data);
                if ($erp_supply && $erp_supply_log) {
                    M()->commit();
                    $result['status'] = 1;
                    $result['message'] = '添加成功';
                } else {
                    M()->rollback();
                    $result['status'] = 4;
                    $result['message'] = '添加失败';
                }
            }
        }
        cancelCacheLock('ErpSupplyMall/actAddSupply');
        return $result;
    }

    // +----------------------------------------
    // |Facilitator:编辑供货单页面数据
    // +----------------------------------------
    // |Author:senpai Time:2017.3.15
    // +----------------------------------------
    public function showUpdateSupply($id)
    {
        if ($id <= 0) return [];
        $field = 's.*,d.depot_name,c.company_name,u.user_name,u.user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data = $this->getModel('ErpSupplyMall')->findSupplyAllInfo(['s.id' => $id], $field);
        if ($data) {
            $data['price'] = $data['price'] > 0 ? getNum($data['price']) : '0.00';
            $data['sale_num'] = $data['sale_num'] > 0 ? getNum($data['sale_num']) : 0;
            $data['lock_num'] = $data['lock_num'] > 0 ? getNum($data['lock_num']) : 0;
            $data['min_sale_num'] = $data['min_sale_num'] > 0 ? getNum($data['min_sale_num']) : 0;
            $data['max_once_num'] = $data['max_once_num'] > 0 ? getNum($data['max_once_num']) : 0;
            $data['freight_money'] = $data['freight_money'] > 0 ? getNum($data['freight_money']) : 0;
        } else {
            $data = [];
        }

        return $data;
    }

    /**
     * 编辑供货单
     */
    public function updateSupply($param)
    {
        if(trim($param['depot_id']) != 99999){//油库非不限油库
            $storehouseDepotField = "id , storehouse_id , depot_id";
            $storehouseDepotWhere = [
                'storehouse_id'=> $param['storehouse_id'] ,
                'depot_id' => $param['depot_id']
            ];
            $storehouseDepotInfo = $this->getEvent("ErpStorehouseDepot")->getStorehouseDepotInfo($storehouseDepotField, $storehouseDepotWhere);
            if(!$storehouseDepotInfo){
                $return['status'] = 102;
                $return['message'] = "油库和仓库信息匹配不对，请确定";
                return $return ;
            }
        }
        if (getCacheLock('ErpSupplyMall/updateSupply')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpSupplyMall/updateSupply', 1);
        //查看erp商品信息，判断是否审核
        $is_audit = $this->getModel('ErpSupplyMall')->findSupply(['id' => $param['id']]);
        $session = session();
        if (count($param) <= 0) {
            $result['status'] = 101;
            $result['message'] = '参数有误';
        }
//        elseif ($is_audit['status'] == 10) {
//            $result['status'] = 101;
//            $result['message'] = ajaxSupplyMessage(x);
//        }
        elseif (trim($param['supply_user_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(a);
        } elseif (trim($param['supply_company_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(b);
        } elseif (trim($param['dealer_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(c);
        } elseif (trim($param['dealer_id']) != $session['erp_adminInfo']['id']) {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(w);
        } elseif (trim($param['dealer_name']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(d);
        } elseif (trim($param['goods_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(e);
        } elseif (trim($param['goods_status']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(f);
        } elseif (trim($param['region']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(g);
        } elseif (trim($param['depot_id']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(h);
        } elseif (trim($param['price']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(i);
        } elseif (trim($param['price']) <= 0) {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(y);
        } elseif (trim($param['sale_num']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(j);
        } elseif (trim($param['min_sale_num']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(k);
        } elseif (trim($param['max_once_num']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(l);
        } elseif (trim($param['pick_up_way']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(m);
        } elseif (trim($param['invoice_type']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(n);
        } elseif (trim($param['is_service']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(o);
        } elseif (trim($param['mall_goods']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(p);
        } elseif (trim($param['show_front']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(q);
        } elseif (trim($param['background_buy']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(r);
        } elseif (trim($param['is_recommend']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(s);
        } elseif (trim($param['freight_free']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(t);
        } elseif (trim($param['send_sms']) == "") {
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(u);
        }elseif(trim($param['storehouse_id']) == ""){
            $result['status'] = 101;
            $result['message'] = ajaxSupplyMessage(z);
        }
        else {

            $erp_supply_data = [
                'supply_number' => erpCodeNumber(10)['order_number'],
                'supply_user_id' => $param['supply_user_id'],
                'supply_company_id' => $param['supply_company_id'],
                'dealer_id' => $param['dealer_id'],
                'dealer_name' => $param['dealer_name'],
                'goods_id' => $param['goods_id'],
                'goods_status' => $param['goods_status'],
                'region' => $param['region'],
                'storehouse_id' => $param['storehouse_id'],
                'depot_id' => $param['depot_id'],
                'price' => setNum($param['price']),
                'sale_num' => setNum($param['sale_num']),
                'min_sale_num' => setNum($param['min_sale_num']),
                'max_once_num' => setNum($param['max_once_num']),
                'lock_num' => 0,
                'pick_up_way' => $param['pick_up_way'],
                'invoice_type' => $param['invoice_type'],
                'is_service' => $param['is_service'],
                'mall_goods' => $param['mall_goods'],
                'show_front' => $param['show_front'],
                'is_recommend' => $param['is_recommend'],
                //优惠1.0暂时关闭，之后会开放
                'preferential_activities' => 0,
                'discount_amount' => 0,
                'remittance_info' => $param['remittance_info'],
                'create_time' => currentTime(),
                'update_time' => currentTime(),
                //'updater' => $session['erp_adminInfo']['id'],
                'updater' => $this->getUserInfo('id'),
                'status' => 1,
                'send_sms' => $param['send_sms'],
                'freight_free' => $param['freight_free'],
                'freight_money' => $param['freight_money'] ? setNum($param['freight_money']) : 0,
                'background_buy' => $param['background_buy'],
                'remark' => $param['remark']
            ];

            //事务开启
            M()->startTrans();
            //编辑erp商品信息
            $supply_id = $this->getModel('ErpSupplyMall')->addSupply($erp_supply_data);
            $update_supply_data = [
                'is_available' => 2,
                'update_time' => currentTime(),
                'updater' => $this->getUserInfo('id'),
            ];
            $update_supply_status = $this->getModel('ErpSupplyMall')->saveSupply(['id' => $param['id']], $update_supply_data);

            $erp_supply_info = $this->getModel('ErpSupplyMall')->where(['id' => intval($param['id']), 'status' => ['neq', 2]])->find();
            $erp_supply_log_data = [
                'price' => setNum($param['price']),
                'sale_num' => setNum($param['sale_num']),
                'supply_id' => $supply_id,
                'supply_info' => serialize($erp_supply_info)
            ];
            //添加erp供货单记录
            $erp_supply_log = $this->addSupplyLog($erp_supply_log_data);
            if ($supply_id && $update_supply_status && $erp_supply_log) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '编辑成功';
            } else {
                M()->rollback();
                $result['status'] = 4;
                $result['message'] = '编辑失败';
            }
        }
        cancelCacheLock('ErpSupplyMall/updateSupply');
        return $result;
    }

    /**
     * 供货单列表
     * @author xiaowen 2017-03-14
     * @param $param
     */
    public function supplyList($param = [])
    {
        $where = [];
        //$data = [];
        if (isset($param['start_time']) || isset($param['end_time'])) {
            if (trim($param['start_time']) && !trim($param['end_time'])) {

                $where['s.create_time'] = ['egt', trim($param['start_time'])];
            } else if (!trim($param['start_time']) && trim($param['end_time'])) {

                $where['s.create_time'] = ['elt', date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)];
            } else if (trim($param['start_time']) && trim($param['end_time'])) {

                $where['s.create_time'] = ['between', [trim($param['start_time']), date('Y-m-d H:i:s', strtotime(trim($param['end_time']))+3600*24)]];
            }
        }
        if (trim($param['goodsSource'])) {
            $where['g.source_from'] = trim($param['goodsSource']);
        }
        if (trim($param['goodsName']) && trim($param['goodsName']) != '请选择名称') {
            $where['g.goods_name'] = trim($param['goodsName']);
        }
        if (trim($param['goodsLevel']) && trim($param['goodsLevel']) != '请选择级别') {
            $where['g.level'] = trim($param['goodsLevel']);
        }
        if (trim($param['goodsRank']) && trim($param['goodsRank']) != '请选择标号') {
            $where['g.grade'] = trim($param['goodsRank']);
        }
        if (trim($param['region'])) {
            $where['s.region'] = trim($param['region']);
        }
        if (trim($param['depot'])) {
            $where['s.depot_id'] = trim($param['depot']);
        }
        if (trim($param['sale_company_id'])) {
            $where['s.supply_company_id'] = intval(trim($param['sale_company_id']));
        }
        if (!isset($param['status']) || empty($param['status'])) {
            $where['s.status'] = ['neq', 2];
        } else if (!empty(erpGoodsStatus()[$param['status']])) {
            $where['s.status'] = $param['status'];
        }

        if (!isset($param['is_available']) || empty($param['is_available'])) {
            $where['s.is_available'] = ['neq', 2];
        } else if (!empty([$param['is_available']])) {
            $where['s.is_available'] = $param['is_available'];
        }
        if (trim($param['supply_number'])) {
            $where['s.supply_number'] = ['like', '%' . trim($param['supply_number']) . '%'];
        }

        $where['s.supply_company_id'] = session('erp_company_id');
        //判断如果有搜索参数，将起始量重置为0，防止从第2页起开始搜索，数据缺少 edit xiaowen 2017-03-15
//        if(!array_diff(['g.source_from','g.goods_name','g.grade','g.level','s.region','s.depot_id','s.supply_company_id','s.status','s.supply_number'], $where)){
//            $param['start'] = 0;
//        }
        $field = 's.*,d.depot_name,c.company_name,u.user_name,u.user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level,rg.price as real_price';
        $data = $this->getModel('ErpSupplyMall')->getSupplyList($where, $field, $param['start'], $param['length']);
        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['No'] = $i;
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['status'] = supplyStatus($value['status']);
                $data['data'][$key]['show_front'] = supplyUpDownStatus($value['show_front']);
                $data['data'][$key]['mall_goods'] = supplyMallGoods($value['mall_goods']);
                $data['data'][$key]['is_available_status'] = supplyAvailableStatus($value['is_available'], true);
                $data['data'][$key]['price'] = $value['price'] > 0 ? getNum($value['price']) : '0.00';
                $data['data'][$key]['sale_num'] = $value['sale_num'] > 0 ? getNum($value['sale_num']) : 0;
                $data['data'][$key]['lock_num'] = $value['lock_num'] > 0 ? getNum($value['lock_num']) : 0;
                $data['data'][$key]['depot_name'] = $value['depot_id'] == 99999 ? '不限油库' : $data['data'][$key]['depot_name'];
                $i++;
            }
        } else {
            $data['data'] = [];
        }

        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;
    }

    /**
     * 删除供货单
     * @param $id
     * @author xiaowen
     * @time 2017-3-10
     */
    public function delSupply($id = 0)
    {
        if (getCacheLock('ErpSupplyMall/delSupply')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {
            setCacheLock('ErpSupplyMall/delSupply', 1);
            $info = $this->getModel('ErpSupplyMall')->findSupply(['id' => $id]);
            if (empty($info)) {
                $result['status'] = 2;
                $result['message'] = '供货单不存在！';
            } else if ($info['status'] != 1) {
                $result['status'] = 3;
                $result['message'] = '只有未审核供货单才能删除！';
            } else {
                //-------------------查看该供货单是否存在生成的交易单------------------------------
                $order_supply = D('ErpOrder')->where(['supply_id' => $id])->find();
                //$order_supply = D('ErpOrder')->where(['supply_id'=>$id, ''=>['in', [1, 3, 10]]])->find();
                if ($order_supply) {  //------------如果存在交易单 不能删除该供货单------------------
                    $result['status'] = 4;
                    $result['message'] = '该供货单已生成了交易单,不能删除！';
                } else {
                    $data['status'] = 2;
                    $data['update_time'] = currentTime();
                    $data['updater'] = $this->getUserInfo('dealer_name');
                    $status = $this->getModel('ErpSupplyMall')->saveSupply(['id' => $id, 'status' => 1], $data);
                    $result['status'] = $status ? 1 : 0;
                    $result['message'] = $result['status'] ? '删除成功' : '删除失败';
                }

            }
            //S('ErpSupplyMall/delSupply', null);
            cancelCacheLock('ErpSupplyMall/delSupply');

        } else {
            $result['status'] = 99;
            $result['message'] = '参数错误，无法获取供货单ID！';
        }
        return $result;
    }
    public function lowerShelf($id = 0)
    {
        if (getCacheLock('ErpSupplyMall/delSupply')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {
            setCacheLock('ErpSupplyMall/delSupply', 1);
            $info = $this->getModel('ErpSupplyMall')->findSupply(['id' => $id]);
            if (empty($info)) {
                $result['status'] = 2;
                $result['message'] = '供货单不存在！';
            }  else {
                $data['status'] = 2;
                $data['update_time'] = currentTime();
                $data['updater'] = $this->getUserInfo('dealer_name');
                $data['is_available'] = 2 ;
                $status = $this->getModel('ErpSupplyMall')->saveSupply(['id' => $id], $data);
                $result['status'] = $status ? 1 : 0;
                $result['message'] = $result['status'] ? '下架成功' : '下架失败';
            }
            //S('ErpSupplyMall/delSupply', null);
            cancelCacheLock('ErpSupplyMall/delSupply');
        } else {
            $result['status'] = 99;
            $result['message'] = '参数错误，无法获取供货单ID！';
        }
        return $result;
    }
    /**
     * 商城自营供货单设置是失效
     * @author 小黑
     * @time 2019-6-16
     */
    /**
     * 审核供货单
     * @param $id
     * @author xiaowen
     * @time 2017-03-14
     */
    public function auditSupply($id)
    {

        if (getCacheLock('ErpSupplyMall/auditSupply')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {
            setCacheLock('ErpSupplyMall/auditSupply', 1);
            $info = $this->getModel('ErpSupplyMall')->findSupply(['id' => $id]);
            if ($info) {
                if ($info['status'] != 1) {
                    $result['status'] = 2;
                    $result['message'] = '该供货单不是未审核状态,无法审核';
                } else if ($info['is_available'] != 1) {

                    $result['status'] = 3;
                    $result['message'] = '该供货单已无效,无法审核';
                } else if ($info['price'] <= 0) {
                    $result['status'] = 4;
                    $result['message'] = '0元商城发布单不允许审核，请重新编辑';
                } else if(empty($info['storehouse_id'])){
                    $result['status'] = 5;
                    $result['message'] = '当前没有绑定仓库信息';
                }else {
                    $update_data = [
                        'status' => 10,
                        'audit_time' => currentTime(),

                        'auditor' => $this->getUserInfo('id'),
                        'locked' => 1, //锁定

                    ];
                    M()->startTrans();
                    $status = $this->getModel('ErpSupplyMall')->saveSupply(['id' => $id], $update_data);

                    $log['supply_id'] = $info['id'];
                    $log['sale_num'] = trim($info['sale_num']);
                    $log['price'] = trim($info['price']);
                    $new_info = $this->getModel('ErpSupplyMall')->findSupply(['id' => intval($id)]);
                    $log['supply_info'] = serialize($new_info);

                    $status_log = $this->addSupplyLog($log);
                    if ($status && $status_log) {
                        M()->commit();
                        $result['status'] = 1;
                        $result['message'] = '审核成功';
                    } else {
                        M()->rollback();
                        $result['status'] = 4;
                        $result['message'] = '审核失败';
                    }
                }
            } else {
                $result['status'] = 3;
                $result['message'] = '该供货单不存在,无法审核';
            }
            cancelCacheLock('ErpSupplyMall/auditSupply');
        }
        return $result;
    }

    /**
     * 发送供货单短信给用户
     * @param array $user_phone
     * @param string $sms_text
     * @return array
     * @author xiaowen
     * @time 2017-03-15
     */
    public function sendSmsSupply($user_phone = [], $sms_text = '')
    {
        if (is_array($user_phone) && count($user_phone) > 0 && $sms_text) {
            foreach ($user_phone as $key => $value) {
                sendPhone($sms_text, $value);
            }
            return ['status' => 1, 'message' => '操作成功,已将短信发送到队列中'];
        }
        return ['status' => 0, 'message' => '操作失败,无法获取手机号或短信内容'];
    }

    /**
     * 复制供货单
     * @param int $id
     * @return array
     * @author xiaowen
     * @time 2017-03-14
     */
    public function copySupply($id = 0)
    {
        if (getCacheLock('ErpSupplyMall/copySupply')) return ['status' => 99, 'message' => $this->running_msg];

        if ($id) {
            setCacheLock('ErpSupplyMall/copySupply', 1);
            $info = $this->getModel('ErpSupplyMall')->findSupply(['id' => $id]);
            if ($info) {
                $info['supply_number'] = erpCodeNumber(1)['order_number'];
                $info['create_time'] = currentTime();

                $info['creater'] = $this->getUserInfo('id');
                $info['dealer_id'] = $this->getUserInfo('id');
                $info['dealer_name'] = $this->getUserInfo('dealer_name');
                $info['status'] = 1; //新复制为未审核
                $info['locked'] = 2; //把锁定去除
                $info['update_time'] = '';
                $info['updater'] = 0;
                $info['audit_time'] = '';
                $info['auditor'] = 0;
                $info['remark'] = '';

                unset($info['id']);
                M()->startTrans();
                $status = $new_id = $this->getModel('ErpSupplyMall')->addSupply($info);
                $log['sale_num'] = $info['sale_num'];
                $log['price'] = $info['price'];
                $log['supply_id'] = $new_id;
                $log['supply_info'] = serialize($info);

                $log_status = $this->addSupplyLog($log);
                if ($status && $log_status) {
                    M()->commit();
                    $result['status'] = 1;
                    $result['message'] = '复制成功';

                } else {
                    M()->rollback();
                    $result['status'] = 2;
                    $result['message'] = '复制失败';
                }
            }
            cancelCacheLock('ErpSupplyMall/copySupply');
        } else {
            $result['status'] = 0;
            $result['message'] = '参数有误，请重新尝试';
        }

        return $result;
    }

    /**
     * 供货单上下架
     * @param $id
     * @author xiaowen
     * @time 2017-3-10
     */
    public function upDownSupply($id = 0)
    {

        if (getCacheLock('ErpSupplyMall/upDownSupply')) return ['status' => 99, 'message' => $this->running_msg];

        if ($id) {

            setCacheLock('ErpSupplyMall/upDownSupply', 1);
            $info = $this->getModel('ErpSupplyMall')->findSupply(['id' => intval($id)]);
            if ($info) {

                if ($info['status'] != 10) {
                    $result['status'] = 3;
                    $result['message'] = '供货单已审核才能上/下架！';
                }
                else {
                    //原来上架的改为下架，下架改为上架
                    $data['show_front'] = $info['show_front'] == 1 ? 2 : 1;

                    $data['update_time'] = currentTime();
                    //$data['updater'] = $this->getUserInfo('dealer_name');
                    $data['updater'] = $this->getUserInfo('id');
                    $status = $this->getModel('ErpSupplyMall')->saveSupply(['id' => intval($id)], $data);
                    $result['status'] = $status ? 1 : 0;
                    $result['message'] = $result['status'] ? '操作成功' : '操作失败';
                }
            } else {
                $result['status'] = 2;
                $result['message'] = '供货单不存在！';
            }
        } else {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        }

        cancelCacheLock('ErpSupplyMall/upDownSupply');
        return $result;
    }

    /**
     * 更新供货单价格库存
     * @param $id
     * @param $param
     * @author xiaowen
     * @time 2017-3-10
     * @return array $result
     */
    public function updatePriceNumSupply($id, $param)
    {

        if (getCacheLock('ErpSupplyMall/updatePriceNumSupply')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {

            setCacheLock('ErpSupplyMall/updatePriceNumSupply', 1);
            if (!trim($param['price'])) {
                $result['status'] = 2;
                $result['message'] = '请输入要更新的价格！';
            } else if (!trim($param['num'])) {
                $result['status'] = 3;
                $result['message'] = '请输入要更新的库存！';
            }
            $info = $this->getModel('ErpSupplyMall')->findSupply(['id' => intval($id), 'status' => ['neq', 2]]);
            if (!$info) {
                $result['status'] = 4;
                $result['message'] = '供货单不存在或已删除！';
            } else if ($param['num'] < getNum($info['lock_num'])) {
                $result['status'] = 5;
                $result['message'] = '供货单库存不能小于已锁数量！';
            } else if ($info['status'] != 10) {
                $result['status'] = 6;
                $result['message'] = '供货单不是已审核，请审核后操作！';
            } else {
                //
                //$data['show_front'] = $info['show_front'] == 1 ? 2 : 1;
                $data['price'] = setNum(trim($param['price']));
                $data['sale_num'] = trim(setNum(trim($param['num'])));
                //状态重新转为未审核
                $data['status'] = 1;
                $data['update_time'] = currentTime();
                //$data['updater'] = $this->getUserInfo('dealer_name');
                $data['updater'] = $this->getUserInfo('id');
                $status = $this->getModel('ErpSupplyMall')->saveSupply(['id' => intval($id)], $data);

                $log['supply_id'] = $info['id'];
                $log['sale_num'] = setNum(trim($param['num']));
                $log['price'] = setNum(trim($param['price']));
                $new_info = $this->getModel('ErpSupplyMall')->findSupply(['id' => intval($id), 'status' => ['neq', 2]]);
                $log['supply_info'] = serialize($new_info);

                $this->addSupplyLog($log); //记录日志
                $result['status'] = $status ? 1 : 0;
                $result['message'] = $result['status'] ? '操作成功' : '操作失败';
            }
        } else {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        }

        cancelCacheLock('ErpSupplyMall/updatePriceNumSupply');
        return $result;
    }

    /**
     * 创建供货单修改日志
     * @param $data
     * @author xiaowen
     * @time 2017-3-10
     * @return int $data
     */
    public function addSupplyLog($data = [])
    {
        $status = 0;
        if ($data) {

            $data['create_time'] = currentTime();
            $data['dealer_id'] = $this->getUserInfo('id');
            $data['dealer_name'] = $this->getUserInfo('dealer_name');
            $status = $this->getModel('ErpSupplyMallLog')->add($data);

        }

        return $status;

    }

    /**
     * 返回一条供货单信息
     * @param $id
     * @param $full
     * @author xiaowen
     * @time 2017-3-15
     * @return int $data
     */
    public function findSupply($id, $full = false)
    {
        if (!$full) {
            $data = $this->getModel('ErpSupplyMall')->findSupply(['id' => $id]);

        } else {
            $field = 's.*,d.depot_name,c.company_name,u.user_name,u.user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
            $data = $this->getModel('ErpSupplyMall')->findSupplyAllInfo(['s.id' => $id], $field);
            if ($data) {
                $cityArr = provinceCityZone()['city'];
                $data['region_name'] = $cityArr[$data['region']];
                $data['status'] = supplyStatus($data['status']);
                $data['show_front'] = supplyUpDownStatus($data['show_front']);
                $data['mall_goods'] = supplyMallGoods($data['mall_goods']);

                $data['price'] = $data['price'] > 0 ? getNum($data['price']) : '0.00';
                $data['sale_num'] = $data['sale_num'] > 0 ? getNum($data['sale_num']) : 0;
                $data['lock_num'] = $data['lock_num'] > 0 ? getNum($data['lock_num']) : 0;
                $data['min_sale_num'] = $data['min_sale_num'] > 0 ? getNum($data['min_sale_num']) : 0;
                $data['max_once_num'] = $data['max_once_num'] > 0 ? getNum($data['max_once_num']) : 0;

            } else {
                $data = [];
            }
        }

        return $data;
    }

    /**
     * 判断是否已存在该地区的该商品商城供货单
     * @param $id
     * @param $full
     * @author senpai
     * @time 2017-06-03
     * @return int $data
     */
    public function checkSupplyMall($id)
    {
        if ($id) {
            $data = $this->getModel('ErpRegionGoods')->findOneRegionGoods(['id' => $id]);
            $is_exist = $this->getModel('ErpSupplyMall')->where(['region'=>$data['region'],'goods_id'=>$data['goods_id'],'is_available'=>1])->count();
        } else {
            $is_exist = 1;
        }
        return $is_exist;
    }

    public function getUserCompanys($uid = [])
    {
        if ($uid) {
            $data = D('Uc')->alias('uc')->field('uc.user_id, c.company_name')->join('oil_clients c on uc.company_id = c.id')->where(['user_id' => ['in', $uid]])->getField('uc.user_id, company_name');
            return $data;
        }
    }

    /**
     * 根据地区获取油库列表
     * @param $data
     * @author xiaowen
     * @time 2017-3-10
     * @return int $data
     */
    public function getDepot($region)
    {
        if ($region) {
            $where = ['depot_area' => $region];
            $data = $this->getModel('Depot')->depotList($where);
        }

        return $data;

    }

    /**
     * 返回完整的供货单信息
     * @author xiaowen
     * @time 2017-5-24
     * @param $id
     * @param string $field
     * @return mixed
     */
    public function getSupplyAllInfo($id, $field = '')
    {

        if (!$field) {
            $field = 's.*,d.depot_name,c.company_name,u.user_name,u.user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        }
        $data = $this->getModel('ErpSupplyMall')->findSupplyAllInfo(['s.id' => $id], $field);


        return $data;
    }



}
