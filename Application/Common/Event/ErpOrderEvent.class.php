<?php
/**
 * 交易单业务处理层
 * @author xiaowen
 * @time 2017-03-10
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class ErpOrderEvent extends BaseController
{

    public function _initialize()
    {
        $this->kefu = ['seya@51zhaoyou.com', 'sufei@51zhaoyou.com'];
    }

    /**
     * 交易单列表
     * @author senpai 2017-03-16
     * @param $param
     */
    public function orderList($param = [])
    {
        $where = [];
        //$data = [];
        if (trim($param['goodsSource'])) {
            $where['g.source_from'] = trim($param['goodsSource']);
        }
        if (trim($param['goodsName']) && trim($param['goodsName']) != '请选择名称') {
            $where['g.goods_name'] = trim($param['goodsName']);
        }
        if (trim($param['goodsGrade']) && trim($param['goodsGrade']) != '请选择标号') {
            $where['g.grade'] = trim($param['goodsGrade']);
        }
        if (trim($param['goodsLevel']) && trim($param['goodsLevel']) != '请选择级别') {
            $where['g.level'] = trim($param['goodsLevel']);
        }
        if (trim($param['region'])) {
            $where['o.region'] = trim($param['region']);
        }
        if (trim($param['depot_id'])) {
            $where['o.depot_id'] = trim($param['depot_id']);
        }
        if (trim($param['sale_company_id'])) {
            $where['o.sale_company_id'] = intval(trim($param['sale_company_id']));
        }
        if (trim($param['buy_company_id'])) {
            $where['o.buy_company_id'] = intval(trim($param['buy_company_id']));
        }
        if (!isset($param['status']) || empty($param['status'])) {
            $where['o.status'] = ['neq', 2];
        } else if (!empty(ErpOrderStatus()[$param['status']])) {
            $where['o.status'] = $param['status'];
        }
        if (trim($param['order_number'])) {
            $where['o.order_number'] = ['like', '%' . trim($param['order_number']) . '%'];
        }
        //跟单列表
        if (trim($param['supply_id'])) {
            $where['o.supply_id'] = trim($param['supply_id']);
        }
        //我的交易单
        if (trim($param['dealer_id'])) {
            $where['o.dealer_id'] = trim($param['dealer_id']);
        }
        //判断如果有搜索参数，将起始量重置为0，防止从第2页起开始搜索，数据缺少 edit xiaowen 2017-03-15
//        if(array_diff(['g.source_from','g.goods_name','g.grade','g.level','o.region','o.depot_id','o.supply_company_id','o.buy_company_id','s.status','s.order_number'], $where)){
//            $param['start'] = 0;
//        }
        $field = 'o.*,s.supply_number,s.mall_goods,d.depot_name,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,cb.company_name b_company_name,ub.user_name b_user_name,ub.user_phone b_user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';
        $data = $this->getModel('ErpOrder')->getOrderList($where, $field, $param['start'], $param['length']);
        if ($data['data']) {
            $i = 1;
            $cityArr = provinceCityZone()['city'];
            foreach ($data['data'] as $key => $value) {
                if ($key == 0) {
                    $data['data'][$key]['sumTotal'] = $data['sumTotal'];
                }
                $data['data'][$key]['No'] = $i;
                $data['data'][$key]['region_name'] = $cityArr[$value['region']];
                $data['data'][$key]['price'] = $value['price'] > 0 ? getNum($value['price']) : '0.00';
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? getNum($value['buy_num']) : 0;
                $data['data'][$key]['buy_num'] = $value['buy_num'] > 0 ? getNum($value['buy_num']) : 0;
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
     * 审核交易单
     * @param $id
     * @author senpai
     * @time 2017-03-17
     */
    public function auditOrder($id)
    {
        if (getCacheLock('ErpOrder/auditOrder')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {
            setCacheLock('ErpOrder/auditOrder', 1);
            $info = $this->getModel('ErpOrder')->findOrder(['id' => $id]);
            if ($info) {
                if ($info['status'] != 3) {
                    $result['status'] = 2;
                    $result['message'] = '该交易单不是预审状态,无法审核';
                } else if ($info['is_upload_img'] != 1) {
                    $result['status'] = 2;
                    $result['message'] = '该交易单未上传汇款截图,无法审核';
                } else {
                    $update_data = [
                        'status' => 10,
                        'audit_time' => currentTime(),
                        'auditor' => $this->getUserInfo('id'),
                    ];
                    $status = $this->getModel('ErpOrder')->saveOrder(['id' => $id], $update_data);

                    if ($status) {
                        $result['status'] = 1;
                        $result['message'] = '审核成功';
                    } else {
                        $result['status'] = 4;
                        $result['message'] = '审核失败';
                    }
                }
            } else {
                $result['status'] = 3;
                $result['message'] = '该交易单不存在,无法审核';
            }
            cancelCacheLock('ErpOrder/auditOrder');
        }
        return $result;
    }

    /**
     * 预审交易单
     * @param $id
     * @author senpai
     * @time 2017-03-17
     */
    public function preAuditOrder($id)
    {
        if (getCacheLock('ErpOrder/preAuditOrder')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {
            setCacheLock('ErpOrder/preAuditOrder', 1);
            $info = $this->getModel('ErpOrder')->findOrder(['id' => $id]);
            if ($info) {
                if ($info['status'] != 1) {
                    $result['status'] = 2;
                    $result['message'] = '该交易单不是未审核状态,无法预审';
                } else {
                    M()->startTrans();
                    $update_data = [
                        'status' => 3,
                        'audit_time' => currentTime(),
                        //'auditor' => $this->getUserInfo('dealer_name'),
                        'auditor' => $this->getUserInfo('id'),
                    ];
                    $status_order = $this->getModel('ErpOrder')->saveOrder(['id' => $id], $update_data);

                    if ($status_order) {
                        M()->commit();
                        $result['status'] = 1;
                        $result['message'] = '预审成功';
                    } else {
                        M()->rollback();
                        $result['status'] = 0;
                        $result['message'] = isset($err_message) ? $err_message : '预审失败';
                    }
                }
            } else {
                $result['status'] = 3;
                $result['message'] = '该交易单不存在,无法预审';
            }
            cancelCacheLock('ErpOrder/preAuditOrder');
        }
        return $result;
    }

    /**
     * 删除交易单
     * @param $id
     * @author senpai
     * @time 2017-3-17
     */
    public function delOrder($id = 0)
    {
        if (getCacheLock('ErpOrder/delOrder')) return ['status' => 99, 'message' => $this->running_msg];
        if ($id) {
            setCacheLock('ErpOrder/delOrder', 1);
            $info = $this->getModel('ErpOrder')->findOrder(['id' => $id]);
            if (empty($info)) {
                $result['status'] = 2;
                $result['message'] = '交易单不存在！';
            } else if ($info['status'] != 1) {
                $result['status'] = 3;
                $result['message'] = '只有未审核的交易单才能删除！';
            } else {
                M()->startTrans();
                $data['status'] = 2;
                $data['update_time'] = currentTime();
                $data['updater'] = $this->getUserInfo('id');
                $status = $this->getModel('ErpOrder')->saveOrder(['id' => $id, 'status' => 1], $data);
                //-------------查询该交易单对应供货单的已锁数量，并减少对应的已锁数量 edit xiaowen ----------------------
                $supply_info = $this->getModel('ErpSupply')->field('lock_num')->where(['id'=>$info['supply_id']])->find();
                $supply_data = [
                    'lock_num' => $supply_info['lock_num'] - $info['buy_num'],
                    'update_time' => currentTime(),
                ];
                //------------------------------------------------------------------------------------------------------
                $supply_status = $this->getModel('ErpSupply')->where(['id'=>$info['supply_id']])->save($supply_data);
                $result['status'] = $status && $supply_status ? 1 : 0;
                if($result['status']){
                    $result['message'] = '删除成功';
                    M()->commit();
                }else{
                    $result['message'] = '删除失败';
                    M()->rollback();

                }
            }
            cancelCacheLock('ErpOrder/delOrder');

        } else {
            $result['status'] = 99;
            $result['message'] = '参数错误，无法获取供货单ID！';
        }
        return $result;
    }

    /**
     * 生成交易单
     * @param $param
     * @author xiaowen
     * @time 2017-3-16
     */
    public function addOrder($param = [])
    {
        if ($param) {
            if (!trim($param['buy_user_id'])) {
                $result['status'] = 13;
                $result['message'] = '供应商用户有误';
            } else if (!trim($param['buy_company_id'])) {
                $result['status'] = 14;
                $result['message'] = '供应商公司有误';
            } else if (trim($param['price']) == '') {
                $result['status'] = 2;
                $result['message'] = '请输入价格';
            } else if (trim($param['buy_num']) == '') {
                $result['status'] = 3;
                $result['message'] = '请输入数量';
            } else if (!trim($param['supply_id'])) {
                $result['status'] = 4;
                $result['message'] = '供货单参数有误';
            } else if (!trim($param['supply_user_id'])) {
                $result['status'] = 5;
                $result['message'] = '供应商用户有误';
            } else if (!trim($param['supply_company_id'])) {
                $result['status'] = 6;
                $result['message'] = '供应商公司有误';
            } else if (!trim($param['region'])) {
                $result['status'] = 7;
                $result['message'] = '请选择城市';
            } else if (!trim($param['depot_id'])) {
                $result['status'] = 8;
                $result['message'] = '请选择油库';
            } else if (!trim($param['dealer_name']) || !trim($param['dealer_id'])) {
                $result['status'] = 17;
                $result['message'] = '交易员信息有误';
            }
//            else if(!trim($param['supply_company_info'])){
//				$result['status'] = 9;
//				$result['message'] = '请选择收款信息';
//			}else if(!trim($param['depot_id'])){
//				$result['status'] = 10;
//				$result['message'] = '请选择汇款信息';
//			}
            else {
                setCacheLock('ErpOrder/addOrder', 1);
                $supplyInfo = $this->getModel('ErpSupply')->findSupply(['id' => $param['supply_id']]);
                //print_r($supplyInfo);
                $can_buy_num = getNum($supplyInfo['sale_num'] - $supplyInfo['lock_num']);
                //echo $can_buy_num;
                if (($supplyInfo['sale_num'] < $supplyInfo['lock_num'])) {
                    $result['status'] = 11;
                    $result['message'] = '供货单库存量不足';
                } else if ($param['buy_num'] > $can_buy_num) {
                    $result['status'] = 12;
                    $result['message'] = '采购量不能大于供货单库存量';
                } else if ($param['buy_num'] < getNum($supplyInfo['min_sale_num'])) {
                    $result['status'] = 15;
                    $result['message'] = '采购量不能小于供货单最小起售量';
                } else if ($param['buy_num'] > getNum($supplyInfo['max_once_num'])) {
                    $result['status'] = 16;
                    $result['message'] = '采购量不能大于供货单最大单笔购买量';
                } else {
                    $data = [];
                    $data['order_number'] = erpCodeNumber(2)['order_number'];
                    $data['supply_id'] = intval($param['supply_id']);
                    $data['sale_user_id'] = $param['supply_user_id'];
                    $data['sale_company_id'] = $param['supply_company_id'];
                    $data['buy_company_id'] = $param['buy_company_id'];
                    $data['buy_user_id'] = $param['buy_user_id'];
                    $data['remark'] = $param['remark'];
                    $data['region'] = $param['region'];
                    $data['depot_id'] = $param['depot_id'];

                    $data['price'] = setNum($param['price']);
                    $data['buy_num'] = setNum($param['buy_num']);
                    //$data['collection_info'] = $param['supply_company_info'];
                    //$data['remittance_info'] = $param['remittance_info'];
                    $data['source_from'] = 1;
                    //$data['remittance_info'] = $param['remittance_info'];

                    $data['status'] = 3; //后台录入交易 状态为已预审
                    $data['create_time'] = currentTime();
                    $data['update_time'] = currentTime();
                    //$data['dealer_id'] = $this->getUserInfo('id');
                    //$data['dealer_name'] = $this->getUserInfo('dealer_name');
                    $data['dealer_id'] = $param['dealer_id'];
                    $data['dealer_name'] = $param['dealer_name'];
                    $data['creater'] = $this->getUserInfo('id');
                    M()->startTrans();
                    $status_order = $this->getModel('ErpOrder')->addOrder($data);

                    //先注释掉，后台生成的都是预审，并且锁库存，不需要判断新老客户
//					if(!$this->isNewUserBack(intval($param['buy_user_id']))){
                    $update_supply = [
                        'lock_num' => $supplyInfo['lock_num'] + setNum($param['buy_num']),
                        'update_time' => currentTime(),
                    ];
                    $status_supply = $this->getModel('ErpSupply')->where(['id' => intval($param['supply_id'])])->save($update_supply);
//					}else{
//						$status_supply = true;
//					}
                    if ($status_order && $status_supply) {
                        M()->commit();
                        $result['status'] = 1;
                        $result['message'] = '交易单生成成功';
                    } else {
                        M()->rollback();
                        $result['status'] = 0;
                        $result['message'] = '交易单生成失败';
                    }
                }
                cancelCacheLock('ErpOrder/addOrder');
            }

            return $result;
        }
    }

    //验证用户是否为新客户
    /**
     * @author xiaowen
     * @time 2017-03-17
     * @param $uid
     * @return boolean
     */
    public function isNewUser($uid)
    {
        $orders = $this->getModel('ErpOrder')->where(['buy_user_id' => $uid])->find();
        $new = empty($orders) ? true : false;

        return $new;

    }

    //验证用户是否为新客户(后台修改及按钮操作使用）
    /**
     * @author xiaowen
     * @time 2017-03-17
     * @param $uid
     * @return boolean
     */
    public function isNewUserBack($uid)
    {
        $orders = $this->getModel('ErpOrder')->where(['buy_user_id' => $uid])->count();
        $new = $orders > 1 ? false : true;

        return $new;

    }

    /**
     * 获取一条交易单信息
     * @param $id
     * @author xiaowen
     * @time 2017-3-19
     * @return array $data
     */
    public function findOrder($id)
    {
        if ($id) {
            $field = 'o.*,s.supply_number,s.sale_num,s.lock_num,s.mall_goods,d.depot_name,cs.company_name s_company_name,us.user_name s_user_name,us.user_phone s_user_phone,cb.company_name b_company_name,ub.user_name b_user_name,ub.user_phone b_user_phone,g.goods_code,g.goods_name,g.source_from,g.grade,g.level';

            $data = $this->getModel('ErpOrder')->findOneOrderInfo(['o.id' => $id], $field);

            if ($data) {

            } else {
                $data = [];
            }
        }
        return $data;
    }

    /**
     * 编辑交易单
     * @param $param
     * @author xiaowen
     * @time 2017-3-19
     */
    public function updateOrder($param = [])
    {
        if ($param) {
            if (!trim($param['id'])) {
                $result['status'] = 17;
                $result['message'] = '交易单参数有误！';
            } else if (!trim($param['buy_user_id'])) {
                $result['status'] = 13;
                $result['message'] = '供应商用户有误';
            } else if (!trim($param['buy_company_id'])) {
                $result['status'] = 14;
                $result['message'] = '供应商公司有误';
            } else if (trim($param['price']) == '') {
                $result['status'] = 2;
                $result['message'] = '请输入价格';
            } else if (trim($param['buy_num']) == '') {
                $result['status'] = 3;
                $result['message'] = '请输入数量';
            } else if (!trim($param['supply_id'])) {
                $result['status'] = 4;
                $result['message'] = '供货单参数有误';
            } else if (!trim($param['supply_user_id'])) {
                $result['status'] = 5;
                $result['message'] = '供应商用户有误';
            } else if (!trim($param['supply_company_id'])) {
                $result['status'] = 6;
                $result['message'] = '供应商公司有误';
            } else if (!trim($param['region'])) {
                $result['status'] = 7;
                $result['message'] = '请选择城市';
            } else if (!trim($param['depot_id'])) {
                $result['status'] = 8;
                $result['message'] = '请选择油库';
            } else {
                setCacheLock('ErpOrder/updateOrder', 1);
                $supplyInfo = $this->getModel('ErpSupply')->findSupply(['id' => $param['supply_id']]);


                $old_order_info = $this->getModel('ErpOrder')->findOrder(['id' => intval($param['id'])]);
                log_info('原始交易单数量：' . $old_order_info['buy_num']);
                log_info('SQL:' . $this->getModel('ErpOrder')->getLastSql());
                $can_buy_num = getNum($supplyInfo['sale_num'] - $supplyInfo['lock_num'] + $old_order_info['buy_num']);
                //预审状态必须选择收款、付款信息
                if ($old_order_info['status'] == 3 && !trim($param['supply_company_info'])) {
                    $result['status'] = 9;
                    $result['message'] = '请选择收款信息';
                } else if ($old_order_info['status'] == 3 && !trim($param['depot_id'])) {
                    $result['status'] = 10;
                    $result['message'] = '请选择汇款信息';
                } else if (($supplyInfo['sale_num'] < $supplyInfo['lock_num'])) {
                    $result['status'] = 11;
                    $result['message'] = '供货单库存量不足';
                } else if ($param['buy_num'] > $can_buy_num) {
                    $result['status'] = 12;
                    $result['message'] = '采购量不能大于供货单库存量';
                } else if ($param['buy_num'] < getNum($supplyInfo['min_sale_num'])) {
                    $result['status'] = 15;
                    $result['message'] = '采购量不能小于供货单最小起售量';
                } else if ($param['buy_num'] > getNum($supplyInfo['max_once_num'])) {
                    $result['status'] = 16;
                    $result['message'] = '采购量不能大于供货单最大单笔购买量';
                } else {
                    $data = [];

                    $data['supply_id'] = intval($param['supply_id']);
                    $data['sale_user_id'] = $param['supply_user_id'];
                    $data['sale_company_id'] = $param['supply_company_id'];
                    $data['buy_company_id'] = $param['buy_company_id'];
                    $data['buy_user_id'] = $param['buy_user_id'];
                    $data['remark'] = $param['remark'];
                    $data['region'] = $param['region'];
                    $data['depot_id'] = $param['depot_id'];

                    $data['price'] = setNum($param['price']);
                    $data['buy_num'] = setNum($param['buy_num']);
                    $data['collection_info'] = $param['supply_company_info'];
                    $data['remittance_info'] = $param['remittance_info'];
                    $data['source_from'] = 1;
                    $data['remittance_info'] = $param['remittance_info'];

                    //$data['status'] = 3; //后台录入交易 状态为已预审
                    $data['update_time'] = currentTime();
                    $data['updater'] = $this->getUserInfo('id');
                    M()->startTrans();
                    $status_order = $this->getModel('ErpOrder')->saveOrder(['id' => intval($param['id'])], $data);
                     log_info('订单更新状态：' . $status_order);
                     log_info('SQL:' . $this->getModel('ErpOrder')->getLastSql());

                     log_info('供货单原锁定数量：' .  $supplyInfo['lock_num']);
                     log_info('交易单原数量：' .  $old_order_info['buy_num']);
                     log_info('交易单新数量：' .  setNum($param['buy_num']));
                     log_info('供货单更新后锁定数量：' .  ($supplyInfo['lock_num'] + (setNum($param['buy_num']) - $old_order_info['buy_num'])));
                    $update_supply = [
                        'lock_num' => $supplyInfo['lock_num'] + (setNum($param['buy_num']) - $old_order_info['buy_num']),
                        'update_time' => currentTime(),
                        'updater' => $this->getUserInfo('id'),
                    ];
                    $status_supply = $this->getModel('ErpSupply')->where(['id' => intval($param['supply_id'])])->save($update_supply);

                    //------------------------截图上传的附件-------------------------------------
                    if (intval($param['id']) && $param['attach']) {
                        $upload_info = $this->uploadPayImg(intval($param['id']), $param['attach']);
                        $upload_status = $upload_info['status'] == 1 ? 1 : 0;
                    } else {
                        $upload_status = true;
                    }
                    //--------------------------------------------------------------------------
                    if ($status_order && $status_supply && $upload_status) {
                        M()->commit();
                        $result['status'] = 1;
                        $result['message'] = '交易单修改成功';
                    } else {
                        M()->rollback();
                        $result['status'] = 0;
                        $result['message'] = '交易单修改失败';
                    }
                }
                cancelCacheLock('ErpOrder/updateOrder');
            }

            return $result;
        }
    }

    /**
     * 上传订单截图
     * @param $id
     * @param $img
     * @author xiaowen
     * @time 2017-3-20
     */
    public function uploadPayImg($id, $img = [])
    {

        if (empty($img)) {
            $result['status'] = 2;
            $result['message'] = '无法获取上传文件';
        } else if (!$id) {
            $result['status'] = 3;
            $result['message'] = '交易单参数有误';
        } else if ($id && $img) {

            M()->startTrans();
            $data['is_upload_img'] = 1;
            //$data['remark'] = ;
            $data['update_time'] = currentTime();
            $data['updater'] = $this->getUserInfo('id');
            $order_status = $this->getModel('ErpOrder')->saveOrder(['id' => intval($id)], $data);

            foreach ($img as $key => $val) {
                $extend_data[] = [
                    'order_id' => $id,
                    'order_number' => '',
                    'pay_img_url' => $val,
                    'type' => 1,
                    'create_time' => currentTime(),
                ];
            }

            $order_extend_status = $this->getModel('ErpOrderExtend')->addAll($extend_data);

            if ($order_status !== false && $order_extend_status) {
                M()->commit();
                $result['status'] = 1;
                $result['message'] = '上传成功';
            } else {
                M()->rollback();
                $result['status'] = 0;
                $result['message'] = '上传失败';
            }

        }

        return $result;
    }

    /**
     * 获取交易单付款截图
     * @param $order_id
     * @author xiaowen
     * @time 2017-3-20
     */
    public function getOrderPayImg($order_id)
    {
        if ($order_id) {

            $payImg = $this->getModel('ErpOrderExtend')->where(['type' => 1, 'pay_img_status' => 1, 'order_id' => intval($order_id)])->select();
            return $payImg;
        }
    }

    public function delPayImg($id)
    {
        if ($id) {
            $status = $this->getModel('ErpOrderExtend')->where(['type' => 1, 'pay_img_status' => 1, 'id' => intval($id)])->save(['pay_img_status' => 2]);

            $data['status'] = $status ? 1 : 0;
            $data['message'] = $data['status'] ? '删除成功' : '删除失败';
            return $data;
        }
    }

    /**
     * 配送历史
     * @author senpai 2017-03-20
     * @param $param
     */
    public function distributionBatch($param)
    {
        if ($param) {
            $field = 'delivery_time,delivery_num';
            $data = $this->getModel('ErpOrderExtend')->getDeliveryList(['order_id' => $param['id'], 'type' => 2], $field);
            foreach ($data['data'] as $k => $v) {
                $data['data'][$k]['delivery_num'] = getNum($v['delivery_num']);
            }
            if (count($data) <= 0) return [];
        }
        return $data;
    }

    /**
     * 配送批次操作
     * @author senpai 2017-03-20
     * @param $param
     */
    public function updateDelivery($param)
    {
        //if(S('Supply/updatePriceNumSupply'))	return [$this->running_msg];
        if (getCacheLock('ErpOrder/updateDelivery')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpOrder/updateDelivery', 1);
        $date = date('Y-m-d');
        if ($param) {
            $order_info = $this->findOrder($param['id']);
            if ($order_info['status'] != 10) {
                $result['status'] = 101;
                $result['message'] = '审核后的订单才能配送！';
            } else if (!trim($param['delivery_num'])) {
                $result['status'] = 101;
                $result['message'] = '本次配送数量不能为空！';
            } else if (($param['delivery_num']) > $order_info['buy_num']) {
                $result['status'] = 101;
                $result['message'] = '本次配送数量不能超过剩余数量！';
            } else if (!trim($param['delivery_time'])) {
                $result['status'] = 101;
                $result['message'] = '配送日期不能为空！';
            } else if ($param['delivery_time'] < $date) {
                $result['status'] = 101;
                $result['message'] = '配送日期必须为今天之后！';
            } else {
                $delivery_data = [
                    'order_id' => $param['id'],
                    'type' => 2,
                    'create_time' => currentTime(),
                    'delivery_num' => setNum($param['delivery_num']),
                    'delivery_time' => $param['delivery_time']
                ];
                $status_delivery = $this->getModel('ErpOrderExtend')->addOrderExtend($delivery_data);
                if ($status_delivery) {
                    $result['status'] = 1;
                    $result['message'] = '操作成功';
                } else {
                    $result['status'] = 101;
                    $result['message'] = '操作失败';
                }
            }
        } else {
            $result['status'] = 101;
            $result['message'] = '参数有误！';
        }
        //S('Supply/updatePriceNumSupply', null);
        cancelCacheLock('ErpOrder/updateDelivery');
        return $result;
    }

}
