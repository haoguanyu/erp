<?php

// @常规公用数据库数据转换自定义函数库
// ====================================================================================================================
/*
    function getUserClients()           根据用户ID批量获取对应公司名称

    function getCountRetail()           通过订单id计算应付金额

    function getAllFacilitatorLevel()   查询服务商表所有负责人下的员工数量

*/
// ====================================================================================================================


// +------------------------------------------------
// |Facilitator:查询服务商表所有负责人下的员工数量
// +------------------------------------------------
// |Author:lizhipeng Time:2016.10.31
// +------------------------------------------------
function getAllFacilitatorLevel()
{
    // @原数据
    $Facilitator_user_data2 = D('Facilitator')->facilitator_user_list(['status' => ['eq', 1]]);
    // @取出所有只有服务商负责人的数据
    $new_user_data = [];
    foreach ($Facilitator_user_data2 as $k => $v) {
        if ($v['position'] == '负责人') {
            $new_user_data[] = $v['facilitator_user_id'];
        }
    }
    // @所有负责人所对应的下属数量
    foreach ($Facilitator_user_data2 as $k => $v) {
        foreach ($new_user_data as $k1 => $v1) {
            if ($v['parent_id'] == $v1) {
                $data[$v1] += 1;
            }
        }
    }
    return $data;
}

/*
  * -----------------------------------------------------
  * 获取所有零售服务商
  * <jiangkun@51zhaoyou.com> | Time:2016-11-04
  * -----------------------------------------------------
  */
function getCompanyNames($where = [])
{
    $where['status'] = 1;
    $data = D('Facilitator')->facilitator_list($where);
    if (count($data) <= 0) return [];
    foreach ($data as $k => $v) {
        $company_name[$v['facilitator_id']] = $v['name'];
    }

    return $company_name;
}

function getFacilitatorUser($id = '')
{
    if ($id <= 0) return [];

    $data = D('Facilitator_user')->where(['facilitator_id' => intval($id), 'status' => ['NEQ', 0]])->select();
    if (count($data) > 0) {
        foreach ($data as $k => $v) {
            $facilitator_user[$v['facilitator_user_id']] = $v['name'];
        }

        return $facilitator_user;
    }

    return [];
}

//获取用户的信息
function getFacilitatorUserInfo($id = '')
{
    if ($id <= 0) return [];

    $data = D('Facilitator_user')->where(['facilitator_id' => intval($id), 'status' => ['NEQ', 0]])->select();
    if (count($data) > 0) {
        $cityArr = provinceCityZone()['city'];
        foreach ($data as $k => $v) {
            if ($v['region'] != 0) {
                $v['region_name'] = $cityArr[$v['region']];
            } else {
                $v['region_name'] = ' — ';
            }

            $facilitator_user[$v['facilitator_user_id']] = $v;
        }

        return $facilitator_user;
    }

    return [];
}

/*
 * ------------------------------------------
 * 返回员工 公司、部门（暂时只返回技术部、销售部下所有组）
 * Author：jk        Time：2016-10-25
 * ------------------------------------------
 */
function getDealerCompany()
{
    $data['company'] = D('Company')->where(['level' => ['in', [1]]])->select();
    $data['department'] = D('Company')->where(['p_id' => ['in', [19, 20]]])->select();
    return $data;
}

/**
 * 集团订单获取服务商用户
 * @param string $id
 * @return array
 */
function getGalaxyFacilitatorUser($id = '')
{
    if ($id <= 0) return [];

    $data = D('Facilitator_user')->where(['facilitator_id' => intval($id), 'status' => ['NEQ', 0]])->select();
    if (count($data) > 0) {
        $cityArr = provinceCityZone()['city'];
        foreach ($data as $k => $v) {
            $tmp['name'] = $v['name'] . '[' . $v['position'] . '--' . $cityArr[$v['region']] . ']';
            $tmp['region'] = $v['region'];
            $facilitator_user[$v['facilitator_user_id']] = $tmp;
        }

        return $facilitator_user;
    }

    return [];
}

/**
 * 获取服务商加油网点
 * @author xiaowen
 * @time 2017-05-03
 */
function getFacilitatorSkid(){
    $data = M('facilitator_skid')->where(['status'=>1])->getField('facilitator_id,facilitator_skid_id, name,city');

    return $data;
}

/**
 * 获取服务商车辆
 * @author xiaowen
 * @time 2017-05-03
 */
function getFacilitatorCar(){
    $data = M('facilitator_car')->where(['status'=>1])->getField('facilitator_id, id, car_number, car_type');
    return $data;
}

/**
 * 获取公司黑名单
 * @author xiaowen
 * @time 2017-07-18
 */
function getBackListCompany(){
    $data = M('erp_company_backlist')->where(['status'=>1])->getField('company_id, company_name');
    return $data;
}

/**
 * 获取erp账套公司
 * @param string $field  需要获取的字段
 * @param array $where  需要获取的字段
 * @author qianbin
 * @time 2017-11-06
 * @return array
 */
function getErpCompanyList($field = 'company_id, company_name', $where = ['status'=>1]){
    //$where['status'] = 1;
    $data = M('erp_company')->where($where)->getField($field,true);
    return $data;
}


/**
 * 获取省下的所有市区
 * @author qianbin
 * @time 2018-01-04
 * @return array
 */
function getCityListByProvinceId(){

    if (S('cityDataByProvince')) {
        $data['city'] = S('cityDataByProvince');
    } else {
        $city = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 2])->select();
        foreach ($city as $key => $value) {
            $data['city'][$value['parent_id']][] = $value['id'];
        }
        S('cityDataByProvince', $data['city'], 3600*8);
    }
    return $data['city'];
}

/**
 * 处理网点的负库存
 * @param $object_id
 * @return bool $result
 */
 function stockDataByObjectId($object_id)
{

    set_time_limit(10000);
    ini_set('memory_limit', '528M');
    $result = true;
    $where = array('object_id'=>$object_id, 'stock_type'=>4, 'status'=>1);
    $erp_stock = D("erp_stock")->where($where)->select();

    foreach ($erp_stock as $k=>$v) {
        if ($v['stock_num'] > 0) {
            $stock_num_gt[] = array(
                'id' => $v['id'],
                'object_id' => $v['object_id'],
                'stock_num' => $v['stock_num']
            );
        } elseif($v['stock_num'] < 0) {
            $stock_num_et[] = array(
                'id' => $v['id'],
                'object_id' => $v['object_id'],
                'stock_num' => $v['stock_num']
            );
        }
    }
    //如果该网点不存在负库存，直接返回true--------
    if(empty($stock_num_et)){
        return $result;
    }
    //--------------------------------------------
    foreach($stock_num_et as $k=>&$v) {
        foreach($stock_num_gt as $kk=>$vv) {
            if ($vv['stock_num'] + $v['stock_num'] == 0) {
                $stock_num_gt[$kk]['stock_num'] = 0;
                $stock_num_et[$k]['stock_num'] = 0;
            } elseif ($vv['stock_num'] + $v['stock_num'] > 0) {
                $stock_num_gt[$kk]['stock_num'] = $vv['stock_num'] + $v['stock_num'];
                $stock_num_et[$k]['stock_num'] = 0;
            } elseif ($vv['stock_num'] + $v['stock_num'] < 0) {
                $stock_num_gt[$kk]['stock_num'] = 0;
                $stock_num_et[$k]['stock_num'] = $vv['stock_num'] + $v['stock_num'];
            }

        }

    }

    $row = array(
        'gt' => $stock_num_gt,
        'et' => $stock_num_et
    );

    foreach($row as $key=>$value){
        foreach($value as $ks=>$vs){
            //print_r($vs);
            $status = D("erp_stock")->where(['id'=>$vs['id']])->save(['stock_num'=>$vs['stock_num'], 'available_num'=>$vs['stock_num'],'update_time'=>currentTime()]);
            //echo $status ? "库存调整成功\n\r" : "库存调整失败\n\r";
            $result = $result && $status;
        }
    }
    return $result;
    //echo $status ? "库存调整成功\n\r" : "库存调整失败\n\r";

}

/**
 * ===============================================================
 * @2019-1-22 ERP项目架构调整，将原Home模块data.php文件合并
 * @author xiaowen
 * ----------------------------------------------------------------
 * function cityLevelData()               // 省市三级联动数据,只查一次，之后取缓存
 *
 * getClientsToUser()                     // 根据公司名称查询关联的所有用户的信息
 *
 * provinceCityZone()                     // 省市区key-value数据
 *
 * phoneGetCompany()                      // 根据手机号码获取该用户所有公司id->name
 * ===============================================================
 */

/**
 * 省市三级联动数据,只查一次，之后取缓存
 * @author xiaowen
 * @Time 2016-10-29
 * @return mixed
 */
function cityLevelData()
{
    $data['city1'] = [];
    if (S('cityLevel1')) {
        $city1 = S('cityLevel1');
    } else {
        $city1 = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 1])->select();
        S('cityLevel1', $city1);
    }
    $data['city1'] = $city1;

    $data['city2'] = [];
    if (S('cityLevel2')) {
        $data['city2'] = S('cityLevel2');
    } else {
        $city2 = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 2])->select();
        foreach ($city2 as $key => $value) {
            $data['city2'][$value['parent_id']][] = $value;

        }
        S('cityLevel2', $data['city2']);
    }

    $data['city3'] = [];
    if (S('cityLevel3')) {
        $data['city3'] = S('cityLevel3');
    } else {
        $city3 = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 3])->select();
        foreach ($city3 as $key => $value) {
            $data['city3'][$value['parent_id']][] = $value;

        }
        S('cityLevel3', $data['city3']);
    }
    return $data;
}


/*
 * ------------------------------------------
 * 根据公司名称查询关联的所有用户的信息
 * Author：jk        Time：2016-10-20
 * ------------------------------------------
 */
function getClientsToUser($company_name = '')
{
    # @验证参数
    if (empty(strHtml($company_name))) return [];
    # @验证公司
    $clients_data = D('clients')->where(['company_name' => strHtml($company_name), 'is_available' => 0])->find();
    if (empty($clients_data)) return [];
    # @验证是否有关联用户
    $uc_data = D('uc')->field('user_id')->where(['company_id' => $clients_data['id'], 'is_available' => 0])->group('user_id')->select();
    if (count($uc_data) <= 0) return [];
    # @处理数据
    foreach ($uc_data as $k => $v) {
        $user_id[] = intval($v['user_id']);
    }
    $user_data = D('user')->field('user_name,user_phone,region')->where(['id' => ['IN', $user_id], 'is_available' => ['NEQ', 1]])->order('id DESC')->select();
    $area = province()['city_id_name'];
    foreach ($user_data as $k => $v) {
        $user_data[$k]['company_name'] = strHtml($company_name);
        $user_data[$k]['region'] = $area[$v['region']];
    }

    return $user_data;
}

/*
 * ------------------------------------------
 * 用户管理 | 公司-用户 | 模糊查询公司全称
 * Author：jk        Time：2016-10-20
 * ------------------------------------------
 */
function getCompanyName($company_name = '')
{
    if (empty(strHtml($company_name))) return [];

    $clients_data = D('clients')->field('company_name')->where(['is_available' => 0, 'company_name' => ['like', '%' . strHtml($company_name) . '%']])->order('id DESC')->limit(10)->select();

    if (count($clients_data) <= 0) return [];

    foreach ($clients_data as $k => $v) {
        $result[] = strHtml($v['company_name']);
    }

    return $result;
}

/**
 * 省市区key-value数据
 * @author xiaowen
 * @Time 2016-10-25
 * @return mixed
 */
function provinceCityZone()
{
    $data['province'] = [];
    if (S('provinceData')) {
        $data['province'] = S('provinceData');
    } else {
        $province = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 1])->select();
        foreach ($province as $key => $value) {
            $data['province'][$value['id']] = $value['area_name'];

        }
        S('provinceData', $data['province'], 3600*8);
    }

    $data['city'] = [];
    if (S('cityData')) {
        $data['city'] = S('cityData');
    } else {
        $city = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 2])->select();
        foreach ($city as $key => $value) {
            $data['city'][$value['id']] = $value['area_name'];

        }
        S('cityData', $data['city'], 3600*8);
    }

    $data['zone'] = [];
    if (S('zoneData')) {
        $data['zone'] = S('zoneData');
    } else {
        $zone = D('Area')->field('id, parent_id, area_name')->where(['area_type' => 3])->select();
        foreach ($zone as $key => $value) {
            $data['zone'][$value['id']] = $value['area_name'];

        }
        S('zoneData', $data['zone'], 3600*8);
    }

    return $data;

}

/*
 * ------------------------------------------
 * 根据手机号码获取该用户所有公司id->name
 * Author：jk        Time：2016-10-25
 * ------------------------------------------
 */
function phoneGetCompany($user_phone = '')
{
    if (empty(strHtml($user_phone))) return [];

    $user_data = D('user')->where(['user_phone' => strHtml($user_phone), 'is_available' => ['NEQ', 1]])->find();
    if (empty($user_data)) return [];

    $uc_data = D('uc')->where(['user_id' => $user_data['id'], 'is_available' => 0])->select();
    if (count($uc_data) <= 0) return [];

    foreach ($uc_data as $k => $v) {
        $company_id[] = $v['company_id'];
    }
    $company_id = array_unique($company_id);
    $company_data = D('clients')->where(['id' => ['IN', $company_id], 'is_available' => 0])->order('id desc')->select();
    if (count($company_data) <= 0) return [];

    foreach ($company_data as $k => $v) {
        $result[$v['id']] = strHtml($v['company_name']);
    }

    return $result;
}

/**
 * 根据条件获取交易员数据
 * @param $where array 条件数组
 * @return array $data
 * @Author：xiaowen        Time：2016-12-05
 */
function getDealerCondition($where = [])
{
    $data = D('Dealer')->where($where)->select();
    return $data;
}

/*
 * ---------------------------------------------
 * 交易员信息数据信息
 * @param $type
 * Author:jk    Time:2017-01-13
 * ---------------------------------------------
 */
function getClientsData($type = 2)
{
    if (S('basicsClientsData' . $type)) {
        $clients_data = S('basicsClientsData' . $type);
    } else {
        $clients_data = D('Clients')->where(['is_available' => 0, 'type' => $type])->select();
        S('basicsClientsData' . $type, $clients_data, 6000);
    }

    return $clients_data;
}

/**
 * 获取油库数据
 * @author xiaowen
 * @time 2017-3-10
 */
function getDepotData()
{
    $depot = D('Depot')->where(['status' => 1])->group('depot_name')->order('id desc')->getField('id, product, depot_name,depot_area');
    return $depot;
}

/**
 * 获取我方所有公司
 * @return mixed
 * @author xiaowen
 * @time 2017-3-31
 */
function getOurCompany()
{
    return D('erp_company')->where(['status' => 1])->getField('company_id, company_name');
}

/**
 * 获取仓库库数据
 * @author xiaowen
 * @time 2017-3-10
 */
function getStoreHouseData($where = [])
{
    $where['status'] = 1;
    $depot = D('ErpStorehouse')->where($where)->order('id asc')->getField('id,tel,type,storehouse_name,region,whole_country,is_purchase,is_sale,is_allocation');
    return $depot;
}

/**
 * 返回 地区--油库 数据
 * @param $depotsData 油库数据
 * @author xiaowen
 * @time 2017-04-17
 * @return array
 */
function getDepotToRegion($depotsData){
    $new_depots = [];
    if ($depotsData) {
        foreach ($depotsData as $key => $value) {
            $new_depots[$value['depot_area']][] = $value;
        }
    }

    return $new_depots;
}

/**
 * 获取所有已录入的有效的交易员
 * Author:qianbin   Time: 2017-11-28
 */
function getDealerList($field = true)
{
    $result = M('Dealer')->field($field)->where(['is_available' => 0])->select();
    return $result;
}

/**
 * 返回 地区--仓库 数据
 * @param $storeHouseData 仓库数据
 * @author xiaowen
 * @time 2017-04-17
 * @return array
 */
function getStoreHouseToRegion($storeHouseData){
//    $new_storehouse = [];
//    if ($storeHouseData) {
//        foreach ($storeHouseData as $key => $value) {
//            $new_storehouse[$value['region']][] = $value;
//        }
//    }

    $new_storehouse = [];
    $whole_country = [];
    if ($storeHouseData) {
        foreach ($storeHouseData as $key => $value) {
            if ($value['whole_country'] != 1) {
                $new_storehouse[$value['region']][] = $value;
            }
            if ($value['whole_country'] == 1) {
                array_push($whole_country,$value);
            }
        }
        $new_storehouse[0] = $whole_country;
    }
    return $new_storehouse;
}

