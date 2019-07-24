<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpStorehouseEvent extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP仓库逻辑处理层
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
        $this->arr = [];
    }

    // +----------------------------------
    // |Facilitator:ERP仓库列表
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function erpStorehouseList($param = [])
    {
        $where = array();
        // @查询条件
        if (!empty(trim($param['storehouse_name']))) {
            $where['storehouse_name'] = ['like', '%' . trim($param['storehouse_name']) . '%'];
        }
        if (!empty(trim($param['type']))) {
            $where['type'] = $param['type'];
        }
        if (!empty(trim($param['status']))) {
            $where['status'] = $param['status'];
        }
        if (!empty(trim($param['region']))) {
            $where['region'] = $param['region'];
        }

        $field = '*';
        $erpGoods = $this->getModel('ErpStorehouse')->erpStorehouseList($where, $field, $param['start'], $param['length'], 'id desc');
        //空数据
        if (count($erpGoods['data']) > 0) {
            //print_r($erpGoods['data']);
            foreach ($erpGoods['data'] as $k => $v) {
                $erpGoods['data'][$k]['status_font'] = erpStorehouseStatus($v['status']);
                $erpGoods['data'][$k]['type_font'] = erpStorehouseType($v['type']);
                $erpGoods['data'][$k]['region_font'] = provinceCityZone()['city'][$v['region']];
                $erpGoods['data'][$k]['updater_name'] = $v['updater'] == 0 ? getIdToDealerName()[$v['creater']] : getIdToDealerName()[$v['updater']];
                //$erpGoods['data'][$k]['is_new'] = $v['is_new'] ? storehouseSource($v['is_new']) : storehouseSource(0);
                $erpGoods['data'][$k]['is_new'] = $v['is_new'] ? storehouseSource($v['is_new']) : 'ERP';
                $erpGoods['data'][$k]['data_source_id'] = $v['data_source_id'] ? $v['data_source_id'] : '--';
            }
        } else {
            $erpGoods['data'] = [];
        }
        $erpGoods['recordsFiltered'] = $erpGoods['recordsTotal'];
        $erpGoods['draw'] = $_REQUEST['draw'];
        return $erpGoods;
    }

    // +----------------------------------
    // |Facilitator:添加erp仓库操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.10
    // +----------------------------------
    public function actAddErpStorehouse($param = [])
    {
        if (getCacheLock('ErpGoods/actAddErpStorehouse')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpGoods/actAddErpStorehouse', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            $session = session();
            if (trim($param['storehouse_name']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(a);
            } elseif (trim($param['type']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(b);
            } elseif (trim($param['region']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(c);
            } elseif (trim($param['address']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(d);
            } elseif (trim($param['tel']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(e);
            } elseif (trim($param['status']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(f);
            } else {
                $exists_data = [
                    'storehouse_name' => $param['storehouse_name'],
                ];
                $exists = $this->getModel('ErpStorehouse')->findErpStorehouse($exists_data);
                if (!$exists) {
                    $erp_storehouse_data = [
                        'storehouse_name'   => trim($param['storehouse_name']),
                        'region'            => $param['region'],
                        'tel'               => trim($param['tel']),
                        'status'            => $param['status'],
                        'type'              => $param['type'],
                        'is_purchase'       => $param['is_purchase'],
                        'is_sale'           => $param['is_sale'],
                        'is_allocation'     => $param['is_allocation'],
                        'whole_country'     => $param['whole_country'],
                        'address'           => trim($param['address']),
                        'remark'            => $param['remark'],
                        'create_time'       => $this->date,
                        'creater'           => $session['erp_adminInfo']['id'],
                        'last_update_time'  => $this->date,
                    ];
                    //添加erp仓库信息
                    $erp_storehouse = $this->getModel('ErpStorehouse')->addErpStorehouse($erp_storehouse_data);
                    $result['status'] = $erp_storehouse ? 1 : 101;
                    $result['message'] = $result['status'] ? '添加成功' : '添加失败';
                } else {
                    $result['status'] = 101;
                    $result['message'] = '仓库重名，请检查';
                }
            }

        }
        cancelCacheLock('ErpGoods/actAddErpStorehouse');
        return $result;
    }

    // +----------------------------------------
    // |Facilitator:编辑erp仓库页面数据
    // +----------------------------------------
    // |Author:senpai Time:2017.3.27
    // +----------------------------------------
    public function showUpdateErpStorehouse($id)
    {
        if ($id <= 0) return [];
        $data = $this->getModel('ErpStorehouse')->findErpStorehouse(['id' => $id]);

        return $data;
    }

    // +----------------------------------------
    // |Facilitator:编辑erp仓库操作
    // +----------------------------------------
    // |Author:senpai Time:2017.3.27
    // +----------------------------------------
    public function actUpdateErpStorehouse($param)
    {
        if (getCacheLock('ErpGoods/actUpdateErpStorehouse')) return ['status' => 99, 'message' => $this->running_msg];
        setCacheLock('ErpGoods/actUpdateErpStorehouse', 1);
        if (count($param) <= 0) {
            $result['status'] = 0;
            $result['message'] = '参数有误！';
        } else {
            //判断仓库信息
            $session = session();
            if (trim($param['storehouse_name']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(a);
            } elseif (trim($param['type']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(b);
            } elseif (trim($param['region']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(c);
            } elseif (trim($param['address']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(d);
            } elseif (trim($param['tel']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(e);
            } elseif (trim($param['status']) == "") {
                $result['status'] = 101;
                $result['message'] = ajaxErpStorehouseMessage(f);
            } else {
                $exists_data = [
                    'storehouse_name' => $param['storehouse_name'],
                ];
                $exists = $this->getModel('ErpStorehouse')->findErpStorehouse($exists_data);
                if ($exists['id'] == $param['id'] || !$exists) {
                    $erp_storehouse_data = [
                        'storehouse_name'   => trim($param['storehouse_name']),
                        'region'            => $param['region'],
                        'tel'               => trim($param['tel']),
                        'status'            => $param['status'],
                        'type'              => $param['type'],
                        'is_purchase'       => $param['is_purchase'],
                        'is_sale'           => $param['is_sale'],
                        'is_allocation'     => $param['is_allocation'],
                        'whole_country'     => $param['whole_country'],
                        'address'           => trim($param['address']),
                        'remark'            => $param['remark'],
                        'update_time'       => $this->date,
                        'updater'           => $session['erp_adminInfo']['id'],
                        'last_update_time'  => $this->date,
                    ];

                    //编辑erp仓库信息
                    $erp_storehouse = $this->getModel('ErpStorehouse')->saveErpStorehouse(['id' => $param['id']], $erp_storehouse_data);
                    $result['status'] = $erp_storehouse ? 1 : 101;
                    $result['message'] = $result['status'] ? '编辑成功' : '编辑失败';
                } else {
                    $result['status'] = 101;
                    $result['message'] = '仓库重名，请检查';
                }

            }
        }
        cancelCacheLock('ErpGoods/actUpdateErpStorehouse');
        return $result;
    }

    /**
     * 通过城市获取，该城市的仓库
     * @author xiaowen
     * @param int $region
     * @param int $type 返回该地区仓库 0 :默认返回该地区所有仓库 1 :返回该地区按类型分组的仓库数据
     * @return int $data
     */
    public function getStorehouseByRegion($region = 0, $type = 0, $storehouse_type = 1){
        if($region){
            $where = [
                'status' => 1,
                '_string' => 'region = "' . $region . '" or whole_country = 1',
                'type' => $storehouse_type == 1 ? ['neq',7] : 7,
            ];
            $storehouse_data = $this->getModel('ErpStorehouse')->where($where)->select();
            if($storehouse_data){
                $data = [];
                foreach($storehouse_data as $key=>$value){
                    //$data[$value['type']] = ['id'=>$value['id'],'storehouse_name'=>$value['storehouse_name']];
                    if($type == 1){
                        $data[$value['type']] = ['id'=>$value['id'],'storehouse_name'=>$value['storehouse_name']];
                    }else if($type == 0){
                        $data[] = [
                            'id'=>$value['id'],
                            'type'=>$value['type'],
                            'storehouse_name'=>$value['storehouse_name'],
                            'is_purchase'=>$value['is_purchase'],
                            'is_sale'=>$value['is_sale'],
                            'is_allocation'=>$value['is_allocation']
                        ];
                    }
                }
            }else{
                $data = [];
            }
        }
        return $data;
    }

    /**
     * 根据仓库属性返回地区id
     * @author guanyu
     * @return int $data
     */
    public function getRegionByStorehouseAttribute($storehourse_id)
    {
        $storehouse_info = $this->getModel('ErpStorehouse')->where(['id' => $storehourse_id])->find();
        $region = $storehouse_info['whole_country'] == 1 ? 1 : $storehouse_info['region'];
        return $region;
    }
    /*
   *  @params:
   *      data:
   *         field:查询的字段
    *        where:查询条件
   *  @return :
   *  @desc:获取仓库信息
   *  @author:小黑
   *  @time:2019-2-19
   */
    public function getListField($field = '*', $where = []){
        $returnData = $this->getModel("ErpStorehouse")->getListField($field , $where);
        return $returnData ;
    }
}

?>
