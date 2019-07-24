<?php

/*
 * ----------------------------------------
 * 返回当前格式化时间(Y-m-d H:i:s)
 * Author：ypm        Time：2016-10-13
 * ----------------------------------------
 */
function nowTime()
{
    return date('Y-m-d H:i:s');
}

function fillTypeName()
{
    return ['枪注(无车牌)', '换小桶(200升/个)', '枪注(有车牌)', '换吨箱(1000升/个)'];
}

function DateTime($format = 'Y-m-d H:i:s')
{
    return date($format);
}

/**
 * 生成树形结构
 * @param array $arr 数组
 * @param int $id 父级ID 0为从最顶级开始
 * @param string $key 关联ID
 * @param string $pkey 父级ID
 * @param string $child 子级key
 * @return array
 */
function GetTree($arr, $id = 0, $key = 'id', $pkey = 'pid', $child = 'child')
{
    $data = array();
    foreach ($arr as $k => $v) {
        if ($v[$pkey] == $id) {
            //var_dump($id."\t".$v[$key]."\t".$v[$pkey]."\t".$k);
            $v[$child] = GetTree($arr, $v[$key]);
            if (empty($v[$child])) {
                unset($v[$child]);
            }
            $data[] = $v;
        }
    }
    //var_dump($data);
    return $data;
}

//查询已开通城市,数组的下表即城市,对应的值为省份名称
function CityToPro()
{
    if (!S('all_area')) {
        $condition['is_branch'] = 1;
        $condition['area_type'] = array("elt", 3); //type为2表示为城市类型,这里查询的是小于三的数据
        $data = getAreaOrder($condition, array('id', 'parent_id', 'area_type', 'area_name'));
        S('all_area', $data, 3600 * 24);
    } else {
        $data = S('all_area');

    }

    foreach ($data as $key => $val) {
        if ($val['area_type'] == 1) {
            $province[$val['id']] = $val['area_name'];
            $provincename2id[$val['area_name']] = $val['id'];
        } else if ($val['area_type'] == 2) {
            $city[$val['id']] = $val['parent_id'];
            $cityid2cityname[$val['id']] = $val['area_name'];
            $cityname2cityid[$val['area_name']] = $val['id'];
            $cityid2provinceid[$val['id']] = $val['parent_id'];
            $cityid2provincename[$val['id']] = $province[$val['parent_id']];
            $provincename2citynamelist[$provincename2id[$province[$val['parent_id']]]][$val['id']] = $val['area_name'];
        } else if ($val['area_type'] == 2) {
            $province[$val['id']] = $val['area_name'];
        }
    }
    foreach ($city as $city_id => $parrent) {
        if ($index[$parrent] == 0)
            $citys[$parrent] = $city_id;
        else
            $citys[$parrent] = $citys[$parrent] . "," . $city_id;
        $index[$parrent]++;
    }
    $all_area['provinceid2cityid'] = $citys;
    $all_area['provincename2id'] = $provincename2id;
    $all_area['provinceid2provincename'] = $province;
    $all_area['cityid2cityname'] = $cityid2cityname;
    $all_area['cityname2cityid'] = $cityname2cityid;
    $all_area['cityid2provinceid'] = $cityid2provinceid;
    $all_area['cityid2provincename'] = $cityid2provincename;
    $all_area['provincename2citynamelist'] = $provincename2citynamelist;
    $all_area['areaid2areaname'] = $areaid2areaname;
    return $all_area;
}

function getAreaOrder($condition, $data = '')
{
    $region_id = $_POST['region_id'];
    $area = D('area');
    $list = $area->where($condition)->field($data)->order(array('id' => 'asc'))->select();
    return $list;
}

/*
 * ----------------------------------------
 * 根据市区id->查询出当前省份下的所有市区id
 * Author：qianbin        Time：2017-09-19
 * ----------------------------------------
 */
function getProvinceCityId($area_id = '')
{
    # province_id => [city_id];
    if (intval($area_id) <= 0)
        return '';
    if (!S('retail_area')) {
        $area = D('area')->select();
        S('retail_area', $area, 3600 * 24);
    } else {
        $area = S('retail_area');
    }
    $result = [];
    foreach ($area as $k => $v) {
        if (intval($v['area_type']) == 2) {
            $result[intval($v['parent_id'])][] = intval($v['id']);
        }
    }
    return $result[$area_id];
}


/*
 * ----------------------------------------
 * 获取省地区
 * Author：jk        Time：2016-08-10
 * ----------------------------------------
 */
function province()
{
    if (!S('retail_area')) {
        $area = D('area')->select();
        S('retail_area', $area, 3600 * 24);
    } else {
        $area = S('retail_area');
    }
    $result = [];
    foreach ($area as $k => $v) {
        if (intval($v['area_type']) == 1) $result['province'][$v['id']] = strHtml($v['area_name']);
        if (intval($v['area_type']) == 2) {
            $result['city'][intval($v['parent_id'])][intval($v['id'])] = strHtml($v['area_name']);
            // @市=>省 ID
            $result['city_parend_id'][intval($v['id'])] = intval($v['parent_id']);
            // @ID => 市名
            $result['city_id_name'][intval($v['id'])] = strHtml($v['area_name']);
            // @市名 => ID
            $result['city_name_id'][strHtml($v['area_name'])] = intval($v['id']);
        }
        if (intval($v['area_type']) == 3) {
            $result['area'][intval($v['parent_id'])][intval($v['id'])] = strHtml($v['area_name']);
            // @区=>市 ID
            $result['area_parend_id'][intval($v['id'])] = intval($v['parent_id']);
            // @区名 => 区 ID
            $result['area_name_id'][strHtml($v['area_name'])] = intval($v['id']);
        }
        // @ID(所有)=>名称
        $result['all'][intval($v['id'])] = strHtml($v['area_name']);
    }
    //var_dump($result['area_parend_id']);die;
    return $result;
}

/*
 * ----------------------------------------
 * 根据区的id获取省市区字符串信息
 * Author：jk        Time：2016-08-12
 * ----------------------------------------
 */

function getAllArea($area_id = '', $is_str = 1, $province = [])
{
    if (intval($area_id) <= 0)
        return '';

    $province = (empty($province)) ? province() : $province;
    $area_name = strHtml($province['all'][intval($area_id)]);
    $area_parend_id = $province['area_parend_id'][intval($area_id)];
    $city_name = strHtml($province['all'][intval($area_parend_id)]);
    $city_parend_id = $province['city_parend_id'][intval($area_parend_id)];
    $province_name = strHtml($province['all'][intval($city_parend_id)]);
    if (!$is_str) {
        return [
            'province_name' => $province_name,
            'city_name' => $city_name,
            'area_name' => $area_name
        ];
    } else {
        if (strHtml($province_name) == strHtml($city_name))
            return $province_name . ' ' . $area_name;
    }

    return $province_name . ' ' . $city_name . ' ' . $area_name;
}

function strHtml($str = '')
{
    return htmlspecialchars(trim($str));
}

//EMERG,ALERT,CRIT,ERR,WARN,NOTIC,INFO,DEBUG,SQL
function log_debug($content)
{
    \Think\Log::write(date("Y-m-d H:i:s") . "\tzhaoyouwang\t" . CONTROLLER_NAME . "\t" . ACTION_NAME . "\t" . $_SERVER['HTTP_X_REAL_IP'] . "\t" . $content, \Think\Log::DEBUG);
}

function log_info($content)
{
    \Think\Log::write(date("Y-m-d H:i:s") . "\tzhaoyouwang\t" . CONTROLLER_NAME . "\t" . ACTION_NAME . "\t" . $_SERVER['HTTP_X_REAL_IP'] . "\t" . $content, \Think\Log::INFO);
}

function log_err($content)
{
    \Think\Log::write(date("Y-m-d H:i:s") . "\tzhaoyouwang\t" . CONTROLLER_NAME . "\t" . ACTION_NAME . "\t" . $_SERVER['HTTP_X_REAL_IP'] . "\t" . $content, \Think\Log::ERR);
}

function log_emerg($content)
{
    \Think\Log::write(date("Y-m-d H:i:s") . "\tzhaoyouwang\t" . CONTROLLER_NAME . "\t" . ACTION_NAME . "\t" . $_SERVER['HTTP_X_REAL_IP'] . "\t" . $content, \Think\Log::EMERG);
}


/**
 * 用户手机号所对应的用户姓名
 * @return array
 * @Time   20160823
 * @author lizhipeng <lizhipeng@51zhaoyou.com>
 */
function getAllNames()
{
    $data = M('User')->where('is_available=0')->field('id,user_name,user_phone')->select();
    $new_data = [];
    foreach ($data as $k => $v) {
        $new_data[$v['user_phone']] = $v['user_name'];
    }
    return $new_data;
}

/**
 * 获取用户所关联公司id
 * @param int $user_id 用户id
 * @return int
 * @author lizhipeng <lizhipeng@51zhaoyou.com>
 */
function getCompanyId($user_id)
{
    if ($user_id < 0) return false;
    $condition = [
        'is_available' => 0,
        'user_id' => $user_id,
        'deal_status' => 1, //只取用户关联的批发关系公司
    ];
    $data = M('Uc')->where($condition)->field('company_id')->select();
    foreach ($data as $k => $v) {
        $id[] = $v['company_id'];
    }
    return $id;
}

/**
 * 查询用户所关联的公司
 * @param  string $company_id 公司id
 * @param  array $otherWhere 公司id
 * @return array
 * @Time   20160823
 * @author lizhipeng <lizhipeng@51zhaoyou.com>
 */
function getUserCompanys($company_id, $otherWhere = [])
{
    if ($company_id < 0 || empty($company_id)) return [];
    $condition = [
        'is_available' => 0,
        'id' => ['IN', $company_id],
        'company_name' => ['neq', ''],
    ];
    if ($otherWhere) {
        $condition = array_merge($condition, $otherWhere);
    }
    //print_r($condition);die;
    $data = M('Clients')->where($condition)->field('company_name,id,transaction_type')->select();
    return $data;

}

/**
 * 根据公司查询汇款信息
 * @param  string $company_id 公司id
 * @return array
 * @Time   20160823
 * @author senpai
 */
function getCompanyInfo($company_id)
{
    if ($company_id < 0 || empty($company_id)) return [];
    $condition = [
        'company_id' => $company_id,
        'type' => 'bank',
        'is_available' => 0
    ];
    //var_dump($condition);die;
    $data = M('clientsinfo')->where($condition)->field('content')->select();
    if ($data[0]['content'] == '--') return [];
    return $data;
}

/**
 * 查询用户所关联的收货地址
 * @param  string $tel 用户手机号
 * @return array
 * @Time   20160824
 * @author lizhipeng <lizhipeng@51zhaoyou.com>
 */
function getAllAddress($tel)
{
    if ($tel < 0) return false;
    $condition = [
        'status' => 1,
        'user_phone' => $tel
    ];
    $data = M('Retail_address')->where($condition)->order('is_default DESC')->select();
    foreach ($data as $k => $v) {
        $data[$k]['address'] = $v['address_name'] . "-" . getAllArea($v['region']) . "" . $v['address_info'];
    }
    return $data;
}

/**
 * 查询用户的id
 * @param  string $tel 手机号
 * @return array
 * @Time   20160824
 * @author lizhipeng <lizhipeng@51zhaoyou.com>
 */
function getUserId($tel)
{
    if ($tel < 0 || empty($tel)) return [];
    $condition = [
        'is_available' => 0,
        'user_phone' => $tel
    ];
    $data = M('User')->where($condition)->field('id')->select();
    return $data;
}

/**
 * 添加设备
 * @param  string $retail_order_id 订单id
 * @param  string $num 数量
 * @param  string $price 单价
 * @return array
 * @Time   20160824
 * @author lizhipeng <lizhipeng@51zhaoyou.com>
 */
function insterEquipment($retail_order_id, $num, $price, $param, $order_number)
{
    if ($retail_order_id <= 0) {
        return [];
    }
    $res = D('Retail')->actSaveEquipment(['retail_order_id' => $retail_order_id], ['status' => 0]);
    $type[1] = "1";
    $type[2] = "2";
    $type[3] = "3";
    $type[4] = "4";
    $type[5] = "5";
    $type[6] = "6";
    $price_6[6] = $price;
    $param['type_6'] = $num;
    $price_6['1'] = 40;
    $price_6['2'] = 500;
    $price_6['3'] = 720;
    $price_6['4'] = 100;
    $price_6['5'] = 720;
    $new_data = [];
    for ($i = 1; $i <= count($type); $i++) {
        $a = $i - 1;
        $new_data[$a]['retail_order_id'] = $retail_order_id;
        $new_data[$a]['type_goods'] = $type[$i];
        $new_data[$a]['type'] = 2;
        if (intval($param['type_' . $i]) <= 0 || empty(trim($param['type_' . $i]))) $param['type_' . $i] = 0;
        $new_data[$a]['num'] = $param['type_' . $i];
        $new_data[$a]['price'] = $price_6[$i];
        $new_data[$a]['create_time'] = date("Y-m-d H:i:s");
        $new_data[$a]['status'] = 1;
        $new_data[$a]['remark'] = "";
        $new_data[$a]['retail_order_number'] = $order_number;
    }
    //var_dump($new_data);exit;
    $result = M('retail_equipment')->addALl($new_data);
    return $result;
}

/*
  * ----------------------------------------
  * 微信零售订单配送时间 | 包含当前一天 往后推一周日期
  * Author：jk        Time：2016-08-10
  * ----------------------------------------
 */
function getWeekRange($week = '')
{
    if (empty(strHtml($week))) return false;
    $time = strtotime($week);
    $data = [];
    for ($i = 0; $i <= 6; $i++) {
        if ($i == 0) {
            $data[$i] = date('Y-m-d', $time);
        } else {
            $data[$i] = date('Y-m-d', (strtotime($data[$i - 1]) + 86400));
        }
    }
    return $data;
}

/*
 * ----------------------------------------
 * 微信零售订单配送时间的时 | 微信零售
 * Author：jk        Time：2016-08-15
 * ----------------------------------------
*/
function getHour()
{
    return [
        '00:00-09:00',
        '09:00-11:00',
        '11:00-14:00',
        '14:00-17:00',
        '17:00-00:00'
    ];
}

/*
 * ----------------------------------------
 * 日期转星期
 * Author：jk        Time：2016-08-10
 * ----------------------------------------
*/
function getWeek($str = '')
{
    $int = date('w', strtotime($str));
    $result = [
        '0' => '星期日',
        '1' => '星期一',
        '2' => '星期二',
        '3' => '星期三',
        '4' => '星期四',
        '5' => '星期五',
        '6' => '星期六'
    ];

    return $result[$int];
}

/**
 * 用户手机号转用户id
 * @Time   20160829
 * @param  string $user_phone
 * @author <lizhipeng@51zhaoyou.com>
 */
function getAllUserId($user_phone)
{
    $condition = [
        'is_available' => 0,
        'user_phone' => $user_phone
    ];
    $result = M('User')->where($condition)->select();
    $new_data = [];
    foreach ($result as $k => $v) {
        $new_data[$v['user_phone']] = $v['id'];
    }
    return $new_data;
}

/**
 * 地区名转换地区id
 * @Time    20160830
 * @param   string $area
 * @author <lizhipeng@51zhaoyou.com>
 */
function getAllAreaId($area)
{
    $condition = [
        'area_type' => 3,
        'area_name' => $area
    ];
    $result = M('Area')->where($condition)->select();
    return $result;
}

/**
 * 交易员转换id
 * @Time    20160831
 * @param   string $dealer_name
 * @author <lizhipeng@51zhaoyou.com>
 */
function getAllDealerId($dealer_name)
{
    $condition = [
        'is_available' => 0,
        'user_phone' => $dealer_name,
    ];
    $name = M('User')->where($condition)->select();
    $result = M('Dealer')->where(['is_available' => 0, 'dealer_name' => $name['0']['dealer_name']])->find();
    return $result;
}

/**
 * 通过用户手机查询对应交易员信息
 * @Time    20170315
 * @param   string $dealer_name
 * @author senpai
 */
function getDealerByUserPhone($user_phone)
{
    $condition = [
        'is_available' => 0,
        'user_phone' => $user_phone,
    ];
    $name = M('User')->where($condition)->select();
    $result = M('Dealer')->where(['is_available' => 0, 'id' => $name['0']['dealer_id']])->find();
    return $result;
}

/**
 * 交易员id转交易员姓名
 * User: lizhipeng
 * Date: 2016/10/09
 * Time: 9:16
 */
function getIdToDealerName()
{
    if (S('id2DealerName')) {
        $id2DealerName = S('id2DealerName');
    } else {
        $condition = [
            'is_available' => ['egt', 0]
        ];
        $result = M('Dealer')->where($condition)->select();
        foreach ($result as $k => $v) {
            # id -> dealer_name...
            $id2DealerName[$v['id']] = $v['dealer_name'];
        }
        S('id2DealerName', $id2DealerName, 3600);
    }
    return $id2DealerName;
}

/**
 * 查询用户id对应的用户名
 * User: lizhipeng
 * Date: 2016/10/09
 * Time: 9:37
 */
function getIdToUserName()
{
    $condition = [
        'is_available' => 0
    ];
    $result = M('User')->where($condition)->select();
    foreach ($result as $k => $v) {
        # user_id -> user_name | user_id -> company_name...
        $user['user'][$v['id']] = $v['user_name'];
        if ($user['user'][$v['id']] == NULL) unset($user['user'][$v['id']]);
        if ($user['company'][$v['id']] == NULL) unset($user['company'][$v['id']]);
    }
    return $user;
}

/**
 * 查询用户id对应的用户信息
 * User: senpai
 * Date: 2017/03/15
 */
function getUserById($id)
{
    $condition = [
        'id' => $id,
        'is_available' => 0
    ];
    $result = M('User')->where($condition)->select();
    return $result;
}

/**
 * 查询交易员管理下的所有用户信息
 * User: senpai
 * Date: 2017/03/15
 */
function getUserByDealer($dealer_name)
{
    $condition = [
        'dealer_name' => $dealer_name,
        'is_available' => 0
    ];
    $result = M('User')->where($condition)->select();
    return $result;
}

function getOneUc($user_id)
{
    if (intval($user_id) <= 0) return [];

    $uc_data = D('Uc')->where(['user_id' => intval($user_id), 'is_available' => 0])->select();
    if (count($uc_data) <= 0) return [];

    // @是否有从属关系 | 优先
    $deal_status = 0;
    foreach ($uc_data as $k => $v) {
        if (intval($v['deal_status']) == 0) $deal_status += 1;
    }
    // @有从属 | 默认返回从属关系第一条
    if ($deal_status > 0) {
        foreach ($uc_data as $k => $v) {
            if (intval($v['deal_status']) == 0) {
                $company_data = D('clients')->where(['id' => intval($v['company_id']), 'is_available' => 0])->find();
                break;
            }
        }
        return $company_data;
    }
    // @无从属 | 返回交易关系第一条
    foreach ($uc_data as $k => $v) {
        if (intval($v['deal_status']) == 1) {
            $company_data = D('clients')->where(['id' => intval($v['company_id']), 'is_available' => 0])->find();
            break;
        }
    }
    return $company_data;
}

/**
 * 收货人
 */
function getAllUserAddressName($user_id)
{
    if (intval($user_id) <= 0) return [];
    $result = M('retail_address')->where(['status' => 1, 'user_id' => $user_id])->field('address_name')->select();
    return $result;
}

/**
 * 登录名转id
 */
function loginNameTodealerId($login_name)
{
    if (empty($login_name)) return [];
    $result = M('Dealer')->where(['is_available' => 0, 'dealer_name' => $login_name])->find();
    return $result;
}

/**
 * id转登录名
 */
function dealerIdTologinName()
{
    $result = M('Dealer')->where(['is_available' => 0])->select();
    foreach ($result as $k => $v) {
        # id -> dealer_name...
        $dealer[$v['id']] = $v['dealer_name'];
    }
    //dump($result);die;
    return $dealer;
}

/*
  * ----------------------------------------
  * 获取交易员信息 | 微信零售
  * Author：jk        Time：2016-08-15
  * ----------------------------------------
 */
function getDealer($where = [])
{
    if (count($where) <= 0) return [];

    return D('Home/Dealer')->where($where)->find();
}

/**
 * 用户发送邮件
 * Time:   20160906
 * User:   <lizhipeng@51zhaoyou.com>
 * Return: True or False
 * @param string $title 邮件标题
 * @param string $code 邮件内容
 * @param string $dealer 交易员邮件名
 * @param string $copyCeiver 抄送邮件地址
 * @param array $user_data 用户信息
 */
function sendEmail($title, $code, $dealer, $copyCeiver = [])
{
    $queues_data = [
        'queues' => serialize(['dealer_email' => $dealer, 'title' => $title, 'code' => $code, 'copyCeiver' => $copyCeiver]),
        'type' => '1',
        'add_time' => date('Y-m-d H:i:s'),
        'status' => '2'
    ];
    $result = M('queues_task')->add($queues_data);
    pushEmail($result);
    return $result;
}

/**
 * 极光推送
 * Time:   20161118
 * User:   <lizhipeng@51zhaoyou.com>
 * Return: True or False
 * @param int $userID 交易员ID
 * @param string $from app名称
 * @param int $messageType 信息类型1:订单消息 2:系统消息
 * @param string $title 信息标题
 * @param string $content 信息内容
 * @param string $extra 自定义消息内容
 * @param int $relation_id 关联内容ID
 * @param int $relation_type 关联内容类型1:订单
 */
function sendJgPush($userID, $app, $messageType, $title, $content, $extra = '', $relation_id = '', $relation_type)
{
    $queues_data = [
        'queues' => serialize(['userID' => $userID, 'from' => $app, 'content' => $content, 'messageType' => $messageType, 'title' => $title, 'extra' => $extra, 'relation_id' => $relation_id, 'relation_type' => $relation_type]),
        'type' => '3',
        'add_time' => date('Y-m-d H:i:s'),
        'status' => '2'
    ];
    $result = M('queues_task')->add($queues_data);
    return $result;
}

/*
 * ------------------------------------------
 * 短信进入队列
 * Author：jk        Time：2016-10-19
 * ------------------------------------------
 */
function sendPhone($code, $dealer_phone)
{
    $queues_data = [
        'queues' => serialize(['dealer_phone' => $dealer_phone, 'code' => $code]),
        'type' => '2',
        'add_time' => date('Y-m-d H:i:s'),
        'status' => '2'
    ];
    $result = M('queues_task')->add($queues_data);
    return $result;
}

function sendGo($code, $dealer_id)
{
    $queues_data = [
        'queues' => serialize(['dealer_id' => $dealer_id, 'yangrui_id' => '35', 'code' => $code]),
        'type' => '4',
        'add_time' => date('Y-m-d H:i:s'),
        'status' => '2'
    ];
    $result = M('queues_task')->add($queues_data);
    return $result;
}

function getRetailOrder($id)
{
    if ($id <= 0) return false;
    $condition = [
        'id' => $id,
        'status' => ['gt', 0]
    ];
    $result = M('retail_order')->where($condition)->find();
    return $result;
}

/**
 * 公司名称转公司id
 * User：<lizhipeng@51zhaoyou.com>
 * Time：20160907
 * Return：array
 */
function getAllCompanyId()
{
    $condition = ['is_available' => 0, 'company_name' => ['neq', '']];
    $result = M('Clients')->where($condition)->field('company_name,company_id')->select();
    if (count($result) <= 0) return [];
    $data = [];
    foreach ($result as $k => $v) {
        $data[$v['company_name']] = $v['company_id'];
    }
    return $data;
}

/**
 * 公司id转公司名称
 * User：<lizhipeng@51zhaoyou.com>
 * Time：20160907
 * Return：array
 */
function getAllCompanyName()
{
    $condition = ['is_available' => 0, 'company_name' => ['neq', '']];
    $result = M('Clients')->where($condition)->field('company_name,company_id')->select();
    if (count($result) <= 0) return [];
    $data = [];
    foreach ($result as $k => $v) {
        $data[$v['company_id']] = $v['company_name'];
    }
    return $data;
}

/*
* -----------------------------------------------------
* 目前业务 | 批发与零售生成订单号
* type 1零售 2批发
* Author:<jk>   Time:2016-09-06
* -----------------------------------------------------
*/
function orderNumber($type = '')
{
    // @验证订单类型
    if (!in_array(intval($type), [1, 2])) return ['status' => 2, 'order_number' => ''];

    $date = date('Ymd');
    $data = D('orderNumber')->query('SELECT max(mosaic_number) as mosaic_number FROM oil_order_number WHERE date_format(create_time,"%Y%m%d")="' . $date . '"');
    // @拼接订单号自增补0位
    $number = 100000000;
    $real_number = substr(($number + ($data[0]['mosaic_number'] + 1)), 1, 8);

    $insert = [
        'order_number' => $date . $real_number,
        'mosaic_number' => $data[0]['mosaic_number'] + 1,
        'type' => intval($type),
        'create_time' => date('Y-m-d H:i:s')
    ];

    if (!D('orderNumber')->add($insert)) return ['status' => 3, 'order_number' => ''];

    return ['status' => 1, 'order_number' => $date . $real_number];
}

/**
 * 查询订单
 * User:lizhipeng
 * Time:20160919
 * Return : data
 */
function getOrderNumber($id)
{
    if ($id <= 0) return false;
    $condition = [
        'id' => $id
    ];
    $data = M('retail_order')->where($condition)->find();
    return $data;
}

/**
 * 查询订单下的地址信息
 * User:lizhipeng
 * Time:20160919
 * Return : data
 */
function getOneAddress($id)
{
    if ($id <= 0) return false;
    $condition = [
        'id' => $id,
        'status' => 1
    ];
    $data = M('retail_address')->where($condition)->find();
    return $data;
}

/*
  * -----------------------------------------------------
  * 零售服务商 | 上海 苏州 南京
  * <lizhipeng@51zhaoyou.com> | Time:20161012
  * -----------------------------------------------------
  */
function getServiceProvider($region = '', $type = true)
{
    if ($type == true) {
        $result = [
            # @上海
            '321' => [
                '上海烨坤石油化工有限公司-王亮' => [],
                '上海善升能源科技有限公司-俞大兵' => [],
                '陈玉明-陈玉明' => [],
                '李高峰-李高峰' => [],
                '吴刚-吴刚' => [],
                '上海畅通石油化工有限公司-姚金山' => [],
                '石涛-石涛' => [],
                '王志良-王志良' => []
            ],
            # @苏州
            '221' => [
                '中国石油天然气股份有限公司江苏苏州销售有限公司-曹金磊' => [],
                '中国石化销售有限公司江苏苏州石油有限公司-刘有鼎' => [],
                '苏州通能石化有限公司-沈军' => [],
                '高艳华-高艳华' => [],
                '袁安忠-袁安忠' => [],
                '沈军-沈军' => []
            ],
            # @南京
            '220' => [
                '杜华-杜华' => []
            ],
            # @宁波
            '388' => [
                '宁波市北仑海润贸易有限公司-项海鹰' => []
            ]
        ];
        if (in_array($region, ['321', '221', '220'])) return $result[$region];
        return $result;
    } else {
        $result = [
            '上海烨坤石油化工有限公司-王亮',
            '上海善升能源科技有限公司-俞大兵',
            '上海畅通石油化工有限公司-姚金山',
            '石涛-石涛',
            '王志良-王志良',
            '陈玉明-陈玉明',
            '李高峰-李高峰',
            '中国石油天然气股份有限公司江苏苏州销售有限公司-曹金磊',
            '中国石化销售有限公司江苏苏州石油有限公司-刘有鼎',
            '苏州通能石化有限公司-沈军',
            '高艳华-高艳华',
            '袁安忠-袁安忠',
            '吴刚-吴刚',
            '杜华-杜华',
            '宁波市北仑海润贸易有限公司-项海鹰'
        ];

        return $result;
    }
}
/**
 * 推送极光消息
 * @param int $userID 送用户类型1：交易员ID 2：服务商手机号 3：普通用户ID 4：集团用户车牌号ID
 * @param string $from app名称
 * @param int $messageType 消息类型1:订单消息 2:开票消息 3:系统消息 4:充值消息
 * @param string $title 信息标题
 * @param string $content 信息内容
 * @param string $extra 自定义消息内容
 * @param string $relation_id 关联内容ID
 * @param string $relation_type 关联内容类型1:订单
 * @param int $user_type 送用户类型1：交易员 2：服务商 3：普通用户 4：集团用户
 * @return bool
 */
function sendJpushMessage($userID,$from,$messageType,$title,$content,$extra='',$relation_id='',$relation_type='', $user_type=1){

    //保存队列数据
    $data['type'] = 3;
    $data['status'] = 2;
    $data['queues'] = serialize([
        'userID'=>$userID,
        'from'=>$from,
        'messageType'=>$messageType,
        'title'=>$title,
        'content'=>$content,
        'extra'=>$extra,
        'relation_id'=>$relation_id,
        'relation_type'=>$relation_type,
    ]);

    $data['add_time'] = date('Y-m-d H:i:s');
    $status = M('queues_task')->add($data);

    //保存消息
    $message = array(
        'message_title' =>$title,                        // '推送标题',
        'message_content' =>$content,                      // '推送内容',
        'message_create_time' =>date('Y-m-d H:i:s'),        // '创建时间',
        'message_available' =>1,                            // '是否有效(0无效，1有效)',
        'message_from' =>$from,                                 // '信息来源1:油沃客',
        'message_type' =>$messageType,                                 // '信息类型1:订单消息',
        'message_extra' =>$extra,                           // '自定义信息内容',
        'message_relation_id' =>$relation_id,               // '关联内容ID',
        'message_relation_type' =>$relation_type            // '关联内容类型',
    );
    $messageID=D('jiguan_message')->add($message);
    //保存推送用户
    $push[] = array(
        'message_id' =>$messageID,              // '消息jiguan_message的id',
        'user_id' =>$userID,                    // '推送用户ID',
        'user_type' =>$user_type,               // '推送用户类型1：交易员 2：用户”',
        'create_time' =>date('Y-m-d H:i:s'),    // '创建时间',
        'status' =>0,                           // '推送状态(0未查看，1已查看)',
        'message_type' =>$messageType           // '消息类型',
    );

    D('jiguan_push_user')->addAll($push);
    # ----异步极光推送 jk 2017-05-06----------
    $data = [
        'alias'             => "".$userID,
        'title'             => $title,
        'content'           => $content,
        'contentType'       => "2",           // 推送消息类型 1.订单消息 2.提示消息 3.营销消息
        'sourcePlatform'    => "3",           // 油沃客
    ];
    $data['extra'] = json_encode($data);
    try {
        if (empty($data['alias'])) {
            E('推送手机号不能为空!');
        }

        if (empty($data['title'])) {
            E('推送标题不能为空!');
        }

        if (empty($data['content'])) {
            E('推送内容不能为空!');
        }

        if (empty($data['contentType'])) {
            E('推送类型不能为空!');
        }

        if (empty($data['sourcePlatform'])) {
            E('推送信息来源不能为空!');
        }

        if (empty($data['extra'])) {
            E('推送内容不能为空!');
        }

        $url  = C('SENDMESSAGE_ASNYCURL');
        return post_async($url, $data);
    } catch (Exception $e) {
        return $e->getMessage();
    }
    # ---------------end---------------------
}

/*
 * ----------------------------------------
 * 极光异步推送
 * Author：jk        Time：2017-05-06
 * ----------------------------------------
 */
function pushJiGuang($id){
    $url = apiIp().'/Common/jiguang/id/'.intval($id);
    # 异步多线程
    _sock($url);
}

/*
 * ----------------------------------------
 * 找油网公共接口异步多线程内部
 * Author：jk        Time：2017-05-06
 * ----------------------------------------
 */
function  _sock($url) {
    $host = parse_url($url,PHP_URL_HOST);
    $port = parse_url($url,PHP_URL_PORT);
    $port = $port ? $port : 80;
    $scheme = parse_url($url,PHP_URL_SCHEME);
    $path = parse_url($url,PHP_URL_PATH);
    $query = parse_url($url,PHP_URL_QUERY);
    if($query){
        $path .= '?'.$query;
    }
    if($scheme == 'https'){
        $host = 'ssl://'.$host;
    }
    $fp = fsockopen($host,$port,$error_code,$error_msg,1);
    if(!$fp){
        return array('error_code' => $error_code,'error_msg' => $error_msg);
    }else{
        stream_set_blocking($fp,true);
        stream_set_timeout($fp,1);
        $header = "GET $path HTTP/1.1\r\n";
        $header.="Host: $host\r\n";
        $header.="Connection: close\r\n\r\n";
        fwrite($fp, $header);
        usleep(1000);
        fclose($fp);
        return array('error_code' => 0);
    }
}

/*
 * ----------------------------------------
 * 找油网公共接口ip
 * Author：jk        Time：2017-05-06
 * ----------------------------------------
 */
function apiIp($host = APP_ENV){
    # 本机测试地址
    //  $url = 'http://www.localhostapi.com';
    # 测试服务器地址
    //$url = 'http://192.168.2.116/api';
    # 线上地址
    $url = [
        'local' => 'http://www.api.local',
        '116' => 'http://192.168.2.116/api',
        '110' => 'http://192.168.2.110/api',
        '128' => 'http://192.168.2.128/proxy',
    ];

    return $url[$host];
}

//返回当前的毫秒时间戳
function msectime()
{
    list($tmp1, $tmp2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($tmp1) + floatval($tmp2)) * 1000);
}

/**
 * 分公司地区
 * Author：xw       Time：2016-11-28
 */
function getRegion()
{
    $data = [
        1 => '全国',
        321 => '上海',
        76 => '广州',
        221 => '苏州',
        287 => '东营',
        388 => '宁波',
    ];
    return $data;
}

/*
 * ------------------------------------------
 * 批发网银记录银行格式
 * Author：jk        Time：2016-11-30
 * ------------------------------------------
 */
function bankFileTyle()
{
    return [
        '民生银行' => [
            'A' => '交易日期',
            'B' => '主机交易流水号',
            'C' => '借方发生额',
            'D' => '贷方发生额',
            'E' => '账户余额',
            'F' => '凭证号',
            'G' => '摘要',
            'H' => '对方账号',
            'I' => '对方账号名称',
            'J' => '对方开户行',
            'K' => '交易时间'
        ],
        '招商银行' => [
            'A' => '交易日',
            'B' => '交易时间',
            'C' => '起息日',
            'D' => '交易类型',
            'E' => '借方金额',
            'F' => '贷方金额',
            'G' => '余额',
            'H' => '摘要',
            'I' => '流水号',
            'J' => '流程实例号',
            'K' => '业务名称',
            'L' => '用途',
            'M' => '业务参考号',
            'N' => '业务摘要',
            'O' => '其它摘要',
            'P' => '收/付方分行名',
            'Q' => '收/付方名称',
            'R' => '收/付方帐号',
            'S' => '收/付方开户行行号',
            'T' => '收/付方开户行名',
            'U' => '收/付方开户行地址',
            'V' => '母/子公司帐号分行名',
            'W' => '母/子公司帐号',
            'X' => '母/子公司名称',
            'Y' => '信息标志',
            'Z' => '有否附件信息',
            'AA' => '冲帐标志',
            'AB' => '扩展摘要',
            'AC' => '交易分析码',
            'AD' => '票据号',
            'AE' => '商务支付订单号',
            'AF' => '内部编号'
        ],
        '微信支付宝' => [
            'A' => '交易时间',
            'B' => '交易流水号',
            'C' => '交易金额(元)',
            'D' => '对方帐户',
            'E' => '付款方式',
            'F' => '备注',

        ]
    ];
}

/*
 * ------------------------------------------
 * 网银记录状态
 * Author：jk        Time：2016-12-11
 * ------------------------------------------
 */
function getOrderPayStatusStr()
{
    return [
        0 => '<span style="color:red;"><b>未对账</b></span>',
        1 => '<span style="color:#32CD32;"><b>已对账</b></span>'
    ];
}

/*
 * ------------------------------------------
 * 网银记录对账类型
 * Author：jk        Time：2016-12-11
 * ------------------------------------------
 */
function order_type()
{
    return [
        1 => '批发',
        2 => '普通零售',
        3 => '集团客户'
    ];
}

/*
 * ------------------------------------------
 * 交易员表数据缓存 缓存更新在后台配置项中
 * Author：jk        Time：2016-12-11
 * ------------------------------------------
 */
function OilDealerCache()
{
    return S('OilDealerCache');
}

/*
 * ------------------------------------------
 * PHP验证操作系统
 * Author：jk        Time：2016-12-11
 * ------------------------------------------
 */
function checkSystem()
{
    $os_name = PHP_OS;
    if (strpos($os_name, "Linux") !== false) {
        $os_str = "Linux";
    } else if (strpos($os_name, "WIN") !== false) {
        $os_str = "Windows";
    }

    return $os_str;
}

/**
 * 获取所有已录入的交易员
 * Author:lizhipeng   Time: 2016-12-07
 */
function getAllDealer()
{
    $dealer = M('Dealer')->where(['is_available' => ['egt', 0]])->select();
    foreach ($dealer as $k => $v) {
        $result[$v['dealer_name']] = $v['id'];
    }
    return $result;
}

/**
 * 数据转csv格式的excle
 * @param  array $data 需要转的数组
 * @param  string $header 要生成的excel表头
 * @param  string $filename 生成的excel文件名
 */
function create_csv($data, $header = null, $filename = 'simple.csv')
{
    $count = '100000';
    // 如果手动设置表头；则放在第一行
    if (!is_null($header)) {
        array_unshift($count, $data, $header);
    }
    // 防止没有添加文件后缀
    $filename = str_replace('.csv', '', $filename) . '.csv';
    ob_clean();
    Header("Content-type:  application/octet-stream ");
    Header("Accept-Ranges:  bytes ");
    Header("Content-Disposition:  attachment;  filename=" . $filename);
    foreach ($data as $k => $v) {
        // 如果是二维数组；转成一维
        if (is_array($v)) {
            $v = implode(',', $v);
        }
        // 替换掉换行
        $v = preg_replace('/\s*/', '', $v);
        // 解决导出的数字会显示成科学计数法的问题
        $v = str_replace(',', "\t,", $v);
        // 转成gbk以兼容office乱码的问题
        echo iconv('UTF-8', 'GBK', $v) . "\t\r\n";
    }
}

/**
 * 数组转xls格式的excel文件
 * @param  array $data 需要生成excel文件的数组
 * @param  string $filename 生成的excel文件名
 * 示例数据：
 * $data = array(
 * array(NULL, 2010, 2011, 2012),
 * array('Q1',   12,   15,   21),
 * array('Q2',   56,   73,   86),
 * array('Q3',   52,   61,   69),
 * array('Q4',   30,   32,    0),
 * );
 */
function create_xls($data, $filename = '零售订单.xls')
{
    ini_set('max_execution_time', '0');
    Vendor('PHPExcel.Classes.PHPExcel');
    $filename = str_replace('.xls', '', $filename) . '.xls';
    $phpexcel = new PHPExcel();
    //@PHPExcel 导出设置缓存，防止数据量过大导致内存溢出 edit xiaowen 2017-8-9--------
    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
    if (!\PHPExcel_Settings::setCacheStorageMethod($cacheMethod)) {
        die($cacheMethod . " 缓存方法不可用" . EOL);
    }
    //--------------------------------------------------------------------------------
    $phpexcel->getProperties()
        ->setCreator("Maarten Balliauw")
        ->setLastModifiedBy("Maarten Balliauw")
        ->setTitle("Office 2007 XLSX Test Document")
        ->setSubject("Office 2007 XLSX Test Document")
        ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("Test result file");
    $phpexcel->getActiveSheet()->fromArray($data);
    $phpexcel->getActiveSheet()->setTitle('数据EXCEL导出');
    $phpexcel->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment;filename=$filename");
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    $objwriter = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel5');
    $objwriter->save('php://output');
    exit;
}

//获取集团车辆所对应的分公司ID
function getCarToClientsId()
{
    $car = M('galaxy_car')->where(['status' => ['neq', 2]])->select();
    foreach ($car as $k => $v) {
        $result[$v['id']] = $v['galaxy_clients_id'];
    }
    return $result;
}

// 获取集团分公司数据
function getGalaxyClients()
{
    $galaxy_clients = M('galaxy_clients')->field('id,company_name')->where(['status' => 1])->select();
    foreach ($galaxy_clients as $k => $v) {
        $result[$v['id']] = $v['company_name'];
    }
    return $result;
}

/*
 * ------------------------------------------
 * 网银记录格式自定义配置
 * Author：xiaowen        Time：2017-2-10
 * ------------------------------------------
 */
function bankCustomData()
{
    return [
        '民生银行' => [
            'totalColumns' => [
                'A' => '交易日期',
                'B' => '主机交易流水号',
                'C' => '借方发生额',
                'D' => '贷方发生额',
                'E' => '账户余额',
                'F' => '凭证号',
                'G' => '摘要',
                'H' => '对方账号',
                'I' => '对方账号名称',
                'J' => '对方开户行',
                'K' => '交易时间'
            ],
            //'bank_format'     => '民生银行',
            'serial_number' => 2,
            'trading_time' => 11,
            'trading_type' => 3,          # @1借金额>0 2贷金额>0
            'borrow_price' => 3,
            'credit_price' => 4,
            'residue_money' => 4, //剩余充值金额 eidt xiaowen
            'paper' => 7,
            'company_id' => 9,
            'company_name' => 9,
            'company_account' => 8,
            'bank_name' => 10,
            //'other'           => empty(serialize($v)) ? '' : serialize($v),
        ],
        '招商银行' => [
            'total_column' => [
                'A' => '交易日',
                'B' => '交易时间',
                'C' => '起息日',
                'D' => '交易类型',
                'E' => '借方金额',
                'F' => '贷方金额',
                'G' => '余额',
                'H' => '摘要',
                'I' => '流水号',
                'J' => '流程实例号',
                'K' => '业务名称',
                'L' => '用途',
                'M' => '业务参考号',
                'N' => '业务摘要',
                'O' => '其它摘要',
                'P' => '收/付方分行名',
                'Q' => '收/付方名称',
                'R' => '收/付方帐号',
                'S' => '收/付方开户行行号',
                'T' => '收/付方开户行名',
                'U' => '收/付方开户行地址',
                'V' => '母/子公司帐号分行名',
                'W' => '母/子公司帐号',
                'X' => '母/子公司名称',
                'Y' => '信息标志',
                'Z' => '有否附件信息',
                'AA' => '冲帐标志',
                'AB' => '扩展摘要',
                'AC' => '交易分析码',
                'AD' => '票据号',
                'AE' => '商务支付订单号',
                'AF' => '内部编号'

            ],
            'serial_number' => 9,
            'trading_time' => '1,2',
            'trading_type' => 5,           # @1借金额>0 2贷金额>0
            'borrow_price' => 5,
            'credit_price' => 6,
            'residue_money' => 6, //剩余充值金额 eidt xiaowen
            'paper' => 8,
            'company_id' => 17,
            'company_name' => 17,
            'company_account' => 18,
            'bank_name' => 2,

        ]
    ];
}

// 获取商品的下期价格
// $end_time:有效期截止时间
function getGoodsLastPrice($id, $end_time)
{
    $select = M('galaxy_goods')->where(['id' => $id])->find();
    // 查询下期价格
    $last_price = M('galaxy_goods')->where(['status' => ['gt', 0], 'type' => $select['type'], 'rank' => $select['rank'], 'level' => $select['level'], 'source' => $select['source'], 'end_time' => ['gt', $end_time]])->find();
    return $last_price['price'];
}

function numberArr()
{
    $T = array(100);
    for ($i = 0; $i < 100; $i++) {
        $T[$i + 1] = $i + 1;
    }
    unset($T[0]);
    return $T;
}

/*
* -----------------------------------------------------
* 目前业务 | 批发与零售生成订单号
* type 1 供货单 2 交易单 3 购货单 4 商品
* Author:<xiaowen>   Time:2017-03-10
* -----------------------------------------------------
*/
function erpCodeNumber($type = '1', $name = '', $erp_company_id= "")
{
    // @验证订单类型
    if (!in_array(intval($type), array_keys(erpCodeType()))) return ['status' => 2, 'order_number' => ''];
    if(!empty($erp_company_id)){
        $our_company_id = $erp_company_id ;
    }else{
        $our_company_id = session('erp_company_id');
    }
    $company_pre_code = in_array($type, [1,2,3]) ? '' : ourCompanyPer($our_company_id);
    $our_company_id = in_array($type, [1,2,3]) ? 0 : $our_company_id;
    if ($type != 4) { //type != 4 不是商品 按统一方法生成编码

        $date = date('Ymd');
        //$data = D('ErpCode')->query('SELECT max(mosaic_number) as mosaic_number FROM oil_erp_code WHERE type = '.$type.' and date_format(create_time,"%Y%m%d")="'.$date.'"');
        //$data = D('ErpCode')->where("type = $type and " . 'date_format(create_time,"%Y%m%d")="' . $date . '"')->find();
        $data = D('ErpCode')->where("our_company_id = {$our_company_id} and type = $type and " . 'date_format(create_time,"%Y%m%d")="' . $date . '"')->find();
        //echo D('ErpCode')->getLastSql();
        // @拼接订单号自增补0位
        $number = 100000000;
        //$real_number = substr(($number + ($data[0]['mosaic_number'] + 1)), 1, 7);
        $real_number = substr(($number + ($data['mosaic_number'] + 1)), 1, 8);
        $pre = erpCodePre($type);
        if(in_array($type , [18])){
            $codeNumber = $company_pre_code. $pre . $date . $real_number ;
        }else{
            $codeNumber = $company_pre_code . $pre . $date . $real_number;
        }
        $insert = [
            'code_number' => $codeNumber,
            'mosaic_number' => $data['mosaic_number'] + 1,
            'type' => intval($type),
            'code_pre' => $pre,
            'create_time' => date('Y-m-d H:i:s'),
            'our_company_id' => $our_company_id,
        ];
        log_info('帐套前缀：' . $company_pre_code . ", 生成单号：" . $insert['code_number']);
        if ($data) {
            $status = D('ErpCode')->where("our_company_id = {$our_company_id} and type = $type and " . 'date_format(create_time,"%Y%m%d")="' . $date . '"')->save($insert);
        } else {
            $status = D('ErpCode')->add($insert);
        }
        //print_r($insert);
        //if(!D('ErpCode')->add($insert))return ['status' => 3, 'order_number' => ''];
        if (!$status) return ['status' => 3, 'order_number' => ''];

        return ['status' => 1, 'order_number' => $insert['code_number']]; //$pre . $date . $real_number
    } else if (erpGoodsPre($name)) {
        $pre = erpGoodsPre($name);
        $data = D('ErpCode')->where(['type' => $type, 'code_pre' => $pre])->find();
        $number = 10000;
        $real_number = substr(($number + ($data['mosaic_number'] + 1)), 1, 4);
        $code_data = [
            'code_number' => $pre . $real_number,
            'mosaic_number' => $data['mosaic_number'] + 1,
            'type' => intval($type),
            'code_pre' => $pre,
            'create_time' => date('Y-m-d H:i:s')
        ];
        if ($data) {
            $status = D('ErpCode')->where(['type' => $type, 'code_pre' => $pre])->save($code_data);
        } else {
            $status = D('ErpCode')->add($code_data);
        }

        if ($status) {
            return ['status' => 1, 'order_number' => $code_data['code_number']];
        }

    } else {
        return ['status' => 3, 'order_number' => ''];
    }
}

/**
 * 取转换后数量、价格
 * @param int $num
 * @return float|int
 */
function getNum($num = 0)
{
    if ($num) {
        $num = sctonum($num / 10000,8);
        return $num;
    } else {
        return 0;
    }
}

/**
 * 设置转换后数量、价格
 * @param int $num
 * @return float|int
 */
function setNum($num)
{
    if ($num) {
        return $num * 10000;
    } else {
        return 0;
    }
}

/**
 * 设置缓存锁方法
 * @param string $key
 * @param string $val
 * @author xiaowen
 * @time 2017-03-14
 */
function setCacheLock($key = '', $val = '')
{
    if ($key && $val) {
        //$cacheLock = C('CacheLock', null, []);
        $cacheLock = S('CacheLock');
        if (!$cacheLock) {
            $cacheLock = [];
        }
        if (!in_array($key, $cacheLock)) {
            $cacheLock[] = $key;
            S('CacheLock', $cacheLock);
            //print_r(S('CacheLock'));
        }
        S($key, $val);
    }
}

/**
 * 取消单个缓存锁方法
 * @param string $key
 * @author xiaowen
 * @time 2017-03-14
 * @return boolean
 */
function getCacheLock($key = '')
{
    if ($key) {

        //$cacheLock = C('CacheLock', null, []);
        $cacheLock = S('CacheLock');
        if (in_array($key, $cacheLock)) {
            return S($key);
        }

    }
}

/**
 * 取消单个缓存锁方法
 * @param string $key
 * @author xiaowen
 * @time 2017-03-14
 */
function cancelCacheLock($key = '')
{
    if ($key) {
        //$cacheLock = C('CacheLock', null, []);
        $cacheLock = S('CacheLock');
        if (in_array($key, $cacheLock)) {
            //array_push($cacheLock, $key);
            C('CacheLock', array_push($cacheLock, $key));
            S($key, null);
        }

    }
}

/**
 * 清除所有缓存锁方法
 * @author xiaowen
 * @time 2017-03-14
 */
function clearCacheLock()
{

    //$cacheLock = C('CacheLock', null, []);
    $cacheLock = S('CacheLock');
    if ($cacheLock) {
        foreach ($cacheLock as $key => $value) {
            S($value, null);
        }
    }
}

/**
 * 设置发送供货单短信内容
 * @param array $data
 * @return mixed
 * @time 2017-03-15
 */
function setSendSupplySms($data = [])
{
    $str = '  报告老板, 油宝宝发现这里有#price#元的#grade##level#, #depot#的#num#吨#goods_name#,可以适当买入, 油宝小提示：现货搜索页面：http://t.cn/RUKD9AX, 点击委托即可购买哦。回复TD退订';
    $new_str = str_replace(array('#price#', '#grade#', '#level#', '#depot#', '#num#', '#goods_name#'), array($data['price'], $data['grade'], $data['level'], $data['depot_name'], $data['sale_num'], $data['goods_name']), $str);
    return $new_str;
}

/**
 * 查询所有子公司
 * User: senpai
 * Date: 2017/04/05
 */
function getAllErpCompany()
{
//    $condition = [
//        'status' => 1
//    ];
//    $result = M('ErpCompany')->where($condition)->select();
//    foreach ($result as $k => $v) {
//        # user_id -> user_name | user_id -> company_name...
//        $erpCompany[$v['company_id']] = $v['company_name'];
//    }
    // edit xiaowen 2017-12-15 内部账套使用统一获取方式
    $erpCompany = getErpCompanyList('company_id, company_name');
    return $erpCompany;
}


/**
 * 二维数组去重
 * @param $array 要去重的数组
 * @param $key 去重依赖的key
 * @return array
 * @author xiaowen
 * @time 2017-4-11
 */
function unique_multidim_array($array, $key)
{
    $temp_array = array();
    $i = 0;
    $key_array = array();

    foreach ($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;
        }
        $i++;
    }
    return $temp_array;
}

/* ----------------------------------------
* post请求
* Author: wb 		Time: 2017-05-13
* ----------------------------------------
*/
//curl get和post请求封装
function http($url, $params, $method = 'POST', $header = array(), $multi = false){
    try {
        $opts = array(
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $header
        );
        /* 根据请求类型设置特定参数 */
        switch(strtoupper($method)){
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                $opts[CURLOPT_IPRESOLVE] = 'CURLOPT_IPRESOLVE_V4';
                break;
            default:
                throw new Exception('不支持的请求方式！');
        }

//        ob_start();
//        $out = fopen('php://output', 'w');

        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
//        curl_setopt($ch, CURLOPT_VERBOSE, true);
//        curl_setopt($ch, CURLOPT_STDERR, $out);
//        curl_setopt($ch,CURLOPT_IPRESOLVE,CURLOPT_IPRESOLVE_V4);
        //$error = curl_error($ch);

        $output = curl_exec($ch);
//        fclose($out);
//        $debug = ob_get_clean();

//        var_dump($debug);exit;
        if (curl_errno($ch)){
            throw new Exception(curl_error($ch),0);
        }else{
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode){
                throw new Exception($output,$httpStatusCode);
            }
        }
        curl_close($ch);
        //由于数据库内容输出时显示的是带bom格式
        if(preg_match('/^\xEF\xBB\xBF/',$output))
        {
            $output = substr($output,3);
        }
    }catch (\Exception $e) {
        $output = json_encode([
            'status'  => 0,
            'message' => $e->getMessage(),
            'user_message' => $e->getMessage(),
            'data'    => []
        ]);
    }
    return  $output;
}


/*
 * ----------------------------------------
 * 调取第三api公用项目接口封装
 * Author: jk 		Time: 2017-05-02
 * ----------------------------------------
 */
function apiPost($url, $param = []){
    $config = 'wuzw@kt^d(1.4LUIdswf+!$*';
    $str = paramMosaic($param,$str = '');
    $param['sign'] = md5($str.md5($config));
    return json_decode(http(trim($url), $param), true);
}

/*
 * ----------------------------------------
 * 调取第三api公用项目   参数拼接
 * Author: wb 		Time: 2017-05-13
 * ----------------------------------------
 */
function paramMosaic($param,$str = ''){
    if($param && is_array($param)){
        foreach($param as $k => $v){
            if(is_array($v)){
                $str = paramMosaic($v,$str);
            }else{
                if(trim($k) != 'sign'){
                    $str .= trim($v);
                }
            }

        }
    }
    return $str;
}

/*
    * ----------------------------------------
    * 邮件异步发送
    * Author：wb        Time：2017-05-22
    * ----------------------------------------
    */
function pushEmail($id){
    $url = apiIp().'/Common/email/id/'.intval($id);
    # 异步多线程
    _sock($url);
}

function ourCompanyPer($company_id = 0){
    //$arr = M('ErpCompany')->getField('company_id, pre_code');
    //edit xiaowen 优化 根据账套公司 $company_id 直接程序定义好前缀，不再查数据库 减少此项延迟或切换账套造成的编号错误
//    $arr = [
//        3372    => 'HY_', //汇由能源（深圳）有限公司
//        70      => 'HR_', //上海和瑞能源贸易有限公司
//        22      => 'YZ_', //上海誉洲石油化工有限公司
//        18      => 'AR_', //上海昊瑞石油化工有限公司
//    ];
    // edit xiaowen 2017-12-15 内部账套使用统一获取方式
    $arr = S('ErpCompanyPreCode') ? S('ErpCompanyPreCode') : getErpCompanyList('company_id, pre_code');
    if($company_id){
        return $arr[$company_id];
    }
    return $arr;
}

/**
 * 返回前Num个月
 * @param $current_month 当月(格式 2017-11)
 * @param $num 当月的前$num个月 例：前3个月 为 -3
 * @param int $format 返回格式 默认 2017-11，也可以是 201711 分别用0和1表示
 * @return string
 */
function getMonthAgoByNum($current_month, $num, $format = 0){
    $formatArr = [
        0 => 'Y-m',
        1 => 'Ym',
    ];
    return date($formatArr[$format], strtotime("$num month" , strtotime($current_month)));
}
/*
 * -----------------------------------------------------
 * 获取时间
 * Author:<YR>   Time:2016-09-19
 * -----------------------------------------------------
 */
function Timetoday()
{
    $reg['today_start'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), date("d"), date("Y")));//今日开始时间
    $reg['last_day_start'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")));//昨日开始时间
    $reg['last_day_end'] = date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("d") - 1, date("Y")));//昨日结束时间
    $reg['last_monday'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), date("d") - date("w") + 1 - 7, date("Y")));//上周周一日期
    $reg['last_sunday'] = date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("d") - date("w") + 7 - 7, date("Y")));//上周周日日期
    $reg['monday'] = date("Y-m-d H:i:s", strtotime("-1 week Monday"));//周一日期
    $reg['sunday'] = date("Y-m-d H:i:s", strtotime("+0 week Sunday"));//周日日期
    $reg['start_last_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 1, 1, date("Y")));//上月开始日期
    $reg['end_last_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), 1, date("Y"))); //上月结束时间
    $reg['startMonth'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), 1, date("Y")));//本月开始日期
    $reg['endMonth'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") + 1, 1, date("Y"))); //本月结束日期


    $reg['start_last6_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 6, 1, date("Y")));//上月开始日期
    $reg['start_last5_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 5, 1, date("Y")));//上月开始日期
    $reg['start_last4_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 4, 1, date("Y")));//上月开始日期
    $reg['start_last3_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 3, 1, date("Y")));//上月开始日期
    $reg['start_last2_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 2, 1, date("Y")));//上月开始日期
    $reg['end_last2_Month'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 1, 1, date("Y"))); //上月结束时间


    return $reg;
}
/**
 * 返回客户等级
 * @param string $level
 * @return array
 */
function checkUserLevel($level = ''){
    $levelArr = [
        0 => 'Trade',
        1 => 'Terminal',
        2 => 'Trade',
    ];
    if($level !== ''){
        return $levelArr[$level];
    }
    return $levelArr;
}



/**
 * post异步请求
 * @param $url
 * @param $data
 * @param int $port
 * @return array
 */
function post_async($url, $data, $port = 80)
{
    $query = http_build_query($data);
    $port = $port ? $port : 80;
    $host = parse_url($url,PHP_URL_HOST);
    $scheme = parse_url($url,PHP_URL_SCHEME);
    if($scheme == 'https'){
        $host = 'ssl://'.$host;
    }

    $fp = fsockopen($host, $port, $error_code, $error_msg,1);
    // print_r($fp);die;
    if(!$fp){
        return array('error_code' => $error_code, 'error_msg' => $error_msg);
    }else{
        stream_set_blocking($fp,true);
        stream_set_timeout($fp,1);

        $header = "POST ${url} HTTP/1.1\r\n";
        $header .= "Host:${host}\r\n";
        $header .= "Content-type:application/x-www-form-urlencoded\r\n";
        $header .= "Content-length:".strlen($query)."\r\n";
        $header .= "Connection:close\r\n\r\n";
        $header .= "${query}";

        fwrite($fp, $header);
        usleep(20000);
        fclose($fp);
    }
}

/**
 * 写日志公用类
 * @param string $data['type'] 日志类型(INFO,EMERG,ALERT,CRIT,ERR,WARN,NOTIC,DEBUG,SQL)
 * @param string $data['event'] 日志标题
 * @param string $data['key'] 唯一索引方便追踪错误
 * @param string $data['request'] 请求的信息
 * @param string $data['response'] 返回的结果
 * @return array
 */
function log_write($data)
{
    try {
        if (!isset($data['event']) || empty($data['event'])) {
            E('事件不能为空!');
        }

        if (is_array($data['event'])) {
            E('事件必须是字符串!');
        }

        if (!isset($data['request']) || empty($data['request'])) {
            E('请求的参数不能为空!');
        }

        if (!empty($data['key']) && is_array($data['key'])) {
            E('唯一索引值不能是数组!');
        }
        $params = [];
        $params['topic'] = 'erp'; // Kafka的topic
        $type_arr = ['INFO','EMERG','ALERT','CRIT','ERR','WARN','NOTIC','DEBUG','SQL'];
        $attr = array(
            'type' => (!isset($data['type']) || empty($data['type']) || empty($type_arr[$data['type']])) ? 'INFO' : $type_arr[$data['type']],
            'ip' => get_client_ip(),
            'time' => date('Y-m-d H:i:s'),
            'event' => trim($data['event']),
            'key' => $data['key'],
            'request' => json_encode($data['request'], JSON_UNESCAPED_UNICODE),
            'response' => json_encode($data['response'], JSON_UNESCAPED_UNICODE)
        );
        $params['value'] = json_encode($attr, JSON_UNESCAPED_UNICODE);

        $url = 'http://kafka.api.51zhaoyou.com/v.php';
        return post_async($url, $params);
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
/**
 * 正负数转换
 * @param $n 要转换的数
 * @return number
 */
function plusConvert($n){
    return $n > 0 ? $n * -1 : abs($n);
}

/**
 * 获取出库单成本
 * @param $stock_out_info
 * @param $stock_out_info
 * @return mixed|string
 */
function getStockOutCost($stock_out_info){
    $where = [
        'goods_id' => $stock_out_info['goods_id'],
        'object_id' => $stock_out_info['storehouse_id'],
        'region' => $stock_out_info['region'],
        'our_company_id' => $stock_out_info['our_company_id'],
        'stock_type' => $stock_out_info['stock_type'],
    ];

    //全国类仓库库存记录region = 1;
    if ($where['stock_type'] != 4) {
        $where['region'] = A('Common/ErpStorehouse', 'Event')->getRegionByStorehouseAttribute($where['object_id']);
    }

    $cost_info = M('erp_cost.cost_info','oil_')->where($where)->find();

    $log_info_data = array(
        'event'=> '获取加权成本',
        'key'=> '出库单号：' . $stock_out_info['outbound_code'] . ', 来源单号：' . $stock_out_info['source_number'],
        'request'=> $where,
        'response'=> $cost_info,
    );
    log_write($log_info_data);
    return $cost_info;
}

//弃用
//function getStockOutCost($stock_out_info){
//    $params[] = [
//        'goodsId' => $stock_out_info['goods_id'],
//        'objectId' => $stock_out_info['storehouse_id'],
//        'region' => $stock_out_info['region'],
//        'ourCompanyId' => $stock_out_info['our_company_id'],
//        'stockType' => $stock_out_info['stock_type'],
//        //'facilitatorId'=>0
//    ];
//
//    //全国类仓库库存记录region = 1;
//    if ($params[0]['stockType'] != 4) {
//        //$params[0]['region'] = A('ErpStorehouse', 'Event')->getRegionByStorehouseAttribute($params[0]['objectId']);
//        $params[0]['region'] = A('Common/ErpStorehouse', 'Event')->getRegionByStorehouseAttribute($params[0]['objectId']);
//    }
//
//    if($stock_out_info['facilitatorId']){
//        $params[0]['facilitatorId'] = $stock_out_info['facilitatorId'];
//    }
//
//    $cost_info = getCost($params);
//    //print_r($cost_info);
//    $log_info_data = array(
//        'event'=> '获取加权成本',
//        'key'=> '出库单号：' . $stock_out_info['outbound_code'] . ', 来源单号：' . $stock_out_info['source_number'],
//        'request'=> $params,
//        'response'=> $cost_info,
//    );
//    log_write($log_info_data);
//    //return $cost_info['data'][0]['price'];
//    return $cost_info['data'][0];
//    //return $cost_info;
//}

/**
 * 获取加权成本
 * @author xiaowen
 * @time 2018-2-1
 * @param array $param 参数说明
 * key: params 值：[{"beforeCostNum":"10000","facilitatorId":0,"goodsId":43,"newCostNum":"20000","newPrice":55000000,"objectId":81,"ourCompanyId":3372,"region":76,"stockId":1,"stockType":1}]
 * beforeCostNum 库存变动前物理库存数量
 * facilitatorId 服务商ID
 * newCostNum 变动后物理库存数量
 * newPrice 本次入库的价格
 * goodsId 商品ID
 * objectId 库存对象ID 根据业务类型 城市仓ID 或 网点ID
 * ourCompanyId 我方账套
 * region 地区ID
 * stockType 仓库类型
 * ***************** 特别说明 goodsId objectId ourCompanyId region stockType 这几个参数共同确认唯一的库存记录，获取和计算成本，必须传入上述参数
 * @return mixed|string
 */
//弃用
//function getCost($param = []){
//    //$url = 'http://192.168.40.164:8089/cost/findCost';
//    $url = C('COST_API_SERVER') . 'cost/findCost';
//    $params['params'] = json_encode($param, JSON_UNESCAPED_UNICODE);
//    //print_r($params);
//    $result = http($url, $params);
//    //echo $result;
//    //print_r($result);
//    return json_decode($result,true);
//}

/**
 * 更新入库后的成本
 * @param $stock_in_info
 */
function updateStockInCost($param){

    //全国类仓库库存记录region = 1;
    if ($param['stock_type'] != 4) {
        $param['region'] = A('Common/ErpStorehouse', 'Event')->getRegionByStorehouseAttribute($param['storehouse_id']);
    }

    //单据单号
    $order_number = $param['storage_code'] ? $param['storage_code'] : $param['outbound_code'];
    //库存ID
    $stock_id = $param['stock_id'];
    //成本数据
    $cost_info = M('erp_cost.cost_info','oil_')->where(['stock_id'=>$stock_id])->find();
    //取库存变动前的物理库存
    $before_stock_num = $param['before_stock_num'];
    //变动前加权单价
    $before_price = empty($cost_info) ? 0 : $cost_info['price'];
    //变动数量
    $change_num = $param['change_num'];
    //取入库单的实际入库单价作为新入库价格
    $change_price = $param['price'] ? $param['price'] : ($param['cost'] ? $param['cost'] : '0');

    //重新计算加权成本
    $new_price = calculateWeightingCost($before_stock_num,$before_price,$change_num,$change_price);

    //修改加权单价数据
    if (empty($cost_info)) {
        $cost_data = [
            'stock_id'          => $stock_id,
            'goods_id'          => $param['goods_id'],
            'object_id'         => $param['storehouse_id'],
            'stock_type'        => $param['stock_type'],
            'region'            => $param['region'],
            'facilitator_id'    => $param['facilitator_id'] ? $param['facilitator_id'] : 0,
            'our_company_id'    => $param['our_company_id'],
            'price'             => $new_price,
            'gmt_create'        => currentTime(),
        ];
        $cost_info['id'] = $cost_status = M('erp_cost.cost_info','oil_')->add($cost_data);
    } else {
        $cost_data = [
            'price'             => $new_price,
            'gmt_modified'      => currentTime(),
        ];
        $cost_status = M('erp_cost.cost_info','oil_')->where(['id'=>$cost_info['id']])->save($cost_data);
    }
    //记录日志
    $cost_log_data = [
        'cost_id' => $cost_info['id'],
        'stock_id' => $stock_id,
        'goods_id' => $param['goods_id'],
        'object_id' => $param['storehouse_id'],
        'stock_type' => $param['stock_type'],
        'region' => $param['region'],
        'facilitator_id' => $param['facilitator_id'] ? $param['facilitator_id'] : 0,
        'our_company_id' => $param['our_company_id'],
        'storage_code' => $order_number,
        'storage_type' => $param['storage_code'] ? $param['storage_code'] : $param['outbound_code'],
        'before_cost_num' => $before_stock_num,
        'before_price' => $before_price,
        'new_cost_num' => $change_num,
        'new_price' => $change_price,
        'price' => $new_price,
        'gmt_create' => currentTime(),
        'gmt_modified' => currentTime(),
        'cost_remark' => '',
    ];
    $cost_log_status = M('erp_cost.cost_info_log','oil_')->add($cost_log_data);

    if ($cost_status && $cost_log_status) {
        return ['status' => true,'message' => '成功'];
    } else {
        return ['status' => false,'message' => '加权成本计算错误，请检查'];
    }
}

/**
 * 返回库存条件
 * @param $stock_in_info
 * @return array
 */
function getStockWhere($stock_in_info){
    $stock_where = [
        'goods_id'=>$stock_in_info['goods_id'],
        'object_id'=>$stock_in_info['storehouse_id'],//$stock_in_info['object_id'],
        'our_company_id'=>$stock_in_info['our_company_id'],
        'stock_type'=>$stock_in_info['stock_type'],
        'region'=>$stock_in_info['region'],
    ];
    return $stock_where;
}
/**
 * 计算入库单成本
 * @param $stock_in_info //入库单信息
 * @return mixed|string
 */
function updateCost($stock_in_info){
    $params = [
        'goodsId' => $stock_in_info['goods_id'],
        'objectId' => $stock_in_info['storehouse_id'],
        'region' => $stock_in_info['region'],
        'ourCompanyId' => $stock_in_info['our_company_id'],
        'storageCode' => $stock_in_info['storage_code'] ? $stock_in_info['storage_code'] : $stock_in_info['outbound_code'],
        'stockType' => $stock_in_info['stock_type'],
        'facilitatorId'=>$stock_in_info['facilitator_id'],
        'beforeCostNum'=>$stock_in_info['beforeCostNum'],
        'newCostNum'=>$stock_in_info['newCostNum'],
        'newPrice'=>$stock_in_info['newPrice'],
        'stockId'=>$stock_in_info['stockId'],
    ];
    log_info("计算加权成本参数" . print_r($params, true));
    //$cost_info = calculateCost($params);
    calculateCost($params);
    //return $cost_info['data']['price'];
}
/**
 * 计算加权成本
 * @author xiaowen
 * @time 2018-2-1
 * @param array $param
 * @return mixed|string
 */
function calculateCost($param = []){
    //$url = 'http://192.168.40.164:8089/cost/modifyCost';
    $url = C('COST_API_SERVER') . 'cost/queue';
    $params['params'] = json_encode($param, JSON_UNESCAPED_UNICODE);

    $log_info_data = array(
        'event'=> '计算加权成本-接口调用',
        'key'=> '入库单：' . $param['storageCode'],
        'request'=> '接口url：' . $url . ', 接口请求参数：' .json_encode($param, JSON_UNESCAPED_UNICODE),
    );
    log_write($log_info_data);
    //print_r($params);
    //$result = post_async($url, $params);
    //post_async($url, $params);
    http($url, $params);
    //return json_decode($result,true);
}

/**
 * 生成零售出库单
 * @author xiaowen
 * @time 2018-2-8
 * @param $storehouseId 网点ID
 * @param $ourCompanyId 账套ID
 */
function retailOrderCreate($storehouseId , $ourCompanyId = ""){
    //$url = C('RETAIL_API_SERVER')['url'] . '/stockIn/stockInNotice';
    //$url = C('RETAIL_API_SERVER')['url'] . ':'. C('RETAIL_API_SERVER')['port'] . '/stockIn/stockInNotice';
    $url = C('RETAIL_API_SERVER') . 'stockIn/stockInNotice';
    $params['storehouseId'] = $storehouseId;
    if(!empty($ourCompanyId)){
        $params['ourCompanyId'] = $ourCompanyId ;
    }
    // http($url, $params);
    log_info($url);
    log_info(C('RETAIL_API_SERVER')['url'] . ':'. C('RETAIL_API_SERVER')['port'] . '/stockIn/stockInNotice');
    $log_info_data = array(
        'event'=> '调拨生成零售出库单',
        //'key'=> '网点ID：' . $storehouseId . ', URL：' . $info['source_number'],
        'request'=> '网点ID：' . $storehouseId . ', URL：' .$url,
        //'request'=> $info,
    );
    log_write($log_info_data);
    return http($url, $params);
    //post_async($url, $params, C('RETAIL_API_SERVER')['port']);
}

/**
 * 红冲零售出库单
 * @author xiaowen
 * @time 2018-2-26
 * @param $stock_in_number 入库单号
 * @return boolean $result
 */
function reverseRetailOrder($stock_in_number){

    //$url = C('RETAIL_API_SERVER')['url'] . '/' . C('RETAIL_API_SERVER')['port'] . 'stock/reverse';
    $url = C('RETAIL_API_SERVER') . 'stock/reverse';
    $params['params'] = $stock_in_number;
    //http($url, $params);
    //post_async($url, $params, C('RETAIL_API_SERVER')['port']);
    $result = http($url, $params);
    $result = json_decode($result, true);
    $log_info_data = array(
        'event'=> '红冲调拨生成的零售出库单',
        //'key'=> ',
        'request'=> '调拨入库单号：' . $stock_in_number . ', URL：' .$url,
        'response'=> 'java返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return isset($result['code']) && $result['code'] == 0 ? true : false;
}

/**
 * 计算加权成本
 * @author xiaowen
 * @time 2018-2-1
 * @param array $param
 * @return mixed|string
 */
function calculateNewCost($param = []){
    log_info("计算加权成本参数" . print_r($param, true));
    //$url = 'http://192.168.40.164:8089/cost/modifyCost';
    $url = C('NEW_COST_API_SERVER');
    echo json_encode($param, JSON_UNESCAPED_UNICODE);
    $params['params'] = json_encode($param, JSON_UNESCAPED_UNICODE);
    //print_r($params);
    //$result = post_async($url, $params);
    //post_async($url, $params);
    http($url, $params);
    //return json_decode($result,true);
}
/**
 * 调拨单确认出库生成零售收油单
 * @author qianbin
 * @time 2018-03-16
 * @param $param：调拨单号，出库单号、入库方服务商id、服务网点id
 * 极光推送
 * $params = [
'allocation_order' =>  [
'allocation_order_number'
'stock_out_number'
'facilitator_id'
'facilitator_skid_id'
'allocation_order_type'    # 1 城市仓->服务商 2 服务商->服务商
'num'                      # 实际入库升数（服务商->服务商）
'density'                  # 密度（服务商->服务商
'create_id'                # 确认入库操作人
],
'message' => [
'allocation_order_number'   # 调拨单号
'stock_type'                # 1 出库单 2 入库单
'goods_name'                # 商品名称
'goods_source_from'         # 商品来源
'goods_grade'               # 商品级别
'goods_level'               # 商品标号
'goods_num'                 # 数量
'actual_storage_num_litre'  # 实际入库数量
'density'                   # 入库密度
],
];
 *
 */
function addOilReceipt($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end

    $url    = C('Front_API_SERVER') . 'ReceiveOil/save_allocation_order';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '城市->网点的调拨-配送单确认,生成零售收油单',
        'key'     => '调拨单号：' . $params['allocation_order']['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return json_decode($result, true);
}

/**
 * 调拨单确认出库生成零售收油记录
 * @author qianbin
 * @time 2018-05-16
 * @param $param：调拨单号，出库单号、入库方服务商id、服务网点id
 * 极光推送
 * $params = [
'allocation_order' =>  [
'allocation_order_number'
'stock_out_number'
'facilitator_id'
'facilitator_skid_id'
'allocation_order_type'    # 1 城市仓->服务商 2 服务商->服务商
'num'                      # 实际入库升数（服务商->服务商）
'density'                  # 密度（服务商->服务商
'create_id'                # 确认入库操作人
],
'message' => [
'allocation_order_number'   # 调拨单号
'stock_type'                # 1 出库单 2 入库单
'goods_name'                # 商品名称
'goods_source_from'         # 商品来源
'goods_grade'               # 商品级别
'goods_level'               # 商品标号
'goods_num'                 # 数量
'actual_storage_num_litre'  # 实际入库数量
'density'                   # 入库密度
],
];
 *
 */
function addOilReceiptRecord($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end

    $url    = C('Front_API_SERVER') . 'ReceiveOil/save_allocation_order_record';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '调拨单确认出库,生成零售收油记录',
        'key'     => '调拨单号：' . $params['allocation_order']['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return json_decode($result, true);
}

/**
 * 配送单确认，Front修改收油状态
 * @author qianbin
 * @time 2018-03-16
 * @param $param：调拨单号，物流单号
 * $params = [
 *      'allocation_order_number' => '',
 *      'shipping_order_number'   => '',
 *      'create_id'				=> '',
 *      'expect_deliver_time'   => ''
 * ];
 */
function updateOilReceiptStatus($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end

    $url    = C('Front_API_SERVER') . 'ReceiveOil/save_allocation_order_start_shipping';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '配送单确认,修改零售收油单状态',
        'key'     => '调拨单号：' . $params['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return json_decode($result, true);
}

/**
 * 调拨单确认入库，Front更新实际升数和密度
 * 发送极光推送
 * @author qianbin
 * @time 2018-03-16
 * @param $param：调拨单号
 * $params = [
 *     'allocation_order'  => [
'allocation_order_number'  # 调拨单号
'num'                      # 升数
'density'                  # 密度
'create_id'                # 确认入库操作人
],
'message'   => [
'allocation_order_number'
'stock_type'               # 1 出库单 2 入库单
'goods_name'
'goods_source_from'
'goods_grade'
'goods_level'
'goods_num'
'actual_storage_num_litre' # 实际入库升数
'density'                  # 实际入库密度
]
];
 */
function updateOilReceipt($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end


    $url = C('Front_API_SERVER') . 'ReceiveOil/confirm_allocation_order_stock_in';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '调拨单确认入库，Front更新实际升数和密度',
        'key'     => '调拨单号：' . $params['allocation_order']['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return json_decode($result, true);
}


/**
 * 红冲调拨单，Front停止收油
 * @author qianbin
 * @time 2018-03-16
 * @param $param：调拨单号
 * $params = [
 *      'allocation_order_number' # 调拨单号
 *      'create_id'
];
 */
function cancelOilReceipt($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end

    $url = C('Front_API_SERVER') . 'ReceiveOil/stop_receive_oil';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '红冲调拨单，Front停止收油',
        'key'     => '调拨单号：' . $params['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return $result;
}


/**
 * 调拨单确认入库，检查Front收油是否完成
 * @author qianbin
 * @time 2018-03-16
 * @param $param：调拨单号
 * $params = [
 *      'allocation_order_number' # 调拨单号
];
 */
function checkOilReceiptStatus($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end

    $url = C('Front_API_SERVER') . 'ReceiveOil/allow_stock_in';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '调拨单确认入库，检查Front收油是否完成',
        'key'     => '调拨单号：' . $params['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return json_decode($result,true);
}

/**
 * 配送单取消，重置收油单状态
 * @author qianbin
 * @time 2018-03-16
 * @param $param：调拨单号
 * $params = [
 *      'allocation_order_number' # 调拨单号
];
 */
function resetOilReceiptStatus($params){

    # erp上线，注释收油流程代码
    # return $data = ['status' => 1 ,'message' =>''];
    # end

    $url = C('Front_API_SERVER') . 'ReceiveOil/cancel_shipping';
    $result = http($url, $params);
    $log_info_data = array(
        'event'   => '配送单取消，重置收油单状态',
        'key'     => '调拨单号：' . $params['allocation_order_number'] . ', URL：' . $url,
        'request' => '参数：' . var_export($params,true),
        'response'=> 'Front返回值：'.var_export($result,true),
    );
    log_write($log_info_data);
    return json_decode($result,true);
}

/**
 * 获取ERP后台系统配置项
 * @param string $key
 * @return mixed|string
 */
function getConfig($key = ''){
    $value = '';
    if($key){
        $value = M('ErpConfig')->where(['key'=>trim($key),'status'=>1])->getField('value');
    }
    return $value;
}
/**
 * 根据配置类型返回配置项
 * @param string $type
 * @return mixed|string
 */
function getConfigByType($type = ''){
    $value = '';
    if($type){
        $value = M('ErpConfig')->where(['type'=>trim($type),'status'=>1])->getField('id,value,name');
    }
    return $value;
}
/**
 * 收油上线时间节点判断
 * @param string $key
 * @return mixed|string
 */
function getCompareOilTime($param = ''){
    $value = $param > '2018-05-30 19:00:00' ? true : false;
    return $value;
}

/**
 * java基础服务发送极光
 * @param string $key
 * @return mixed|string
 */
function sendJpushMessageByJava($user_id = 0 ,$title = '' ,$content = ''){
    # 新极光推送没有记录消息，所以使用老的极光推送方法，调用java接口
    # sendJpushMessage();
    $data = [
        'alias'             => $user_id,
        'title'             => $title,
        'content'           => $content,
        'contentType'       => "2",           // 推送消息类型 1.订单消息 2.提示消息 3.营销消息
        'sourcePlatform'    => "3",           // 油沃客
    ];
    $data['extra'] = json_encode($data,JSON_UNESCAPED_UNICODE);
    try {
        if (empty($data['alias'])) {
            E('推送手机号不能为空!');
        }

        if (empty($data['title'])) {
            E('推送标题不能为空!');
        }

        if (empty($data['content'])) {
            E('推送内容不能为空!');
        }

        if (empty($data['contentType'])) {
            E('推送类型不能为空!');
        }

        if (empty($data['sourcePlatform'])) {
            E('推送信息来源不能为空!');
        }

        if (empty($data['extra'])) {
            E('推送内容不能为空!');
        }

        $url  = C('SENDMESSAGE_ASNYCURL');
        return post_async($url, $data);
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
/*
 * java基础服务发送邮件
 * @param string $key
 * @return mixed|string
 */
function sendEmailByJava($title = '',$content = '',$emailUrl = '',$is_curl = false){
    //请求地址
    $asnycUrl = C('SENDEMAIL_ASNYCURL');
    $params   = array(
        'sendTo'        => $emailUrl,    // 收件人 (多个收件人之间用,隔开)
        'sendContent'   => $content,     // 邮件内容
        'sourcePlatform'=> "1",            // 来源 1为erp
        'bccTo'         => $emailUrl,    // 秘密抄送
        'title'         => $title,       // title
        'ccTo'          => $emailUrl,    // 抄送人
        'type'          => "1",             // 1为html邮件类型
    );
    if ( $is_curl ) {
        // curl 请求
        return curl($asnycUrl,$params,true);
    }
    //异步请求
    post_async($asnycUrl,$params);
}

/***************************************
# Content curl 请求
 ****************************************/
function curl($url, $data = NULL, $json = false,$token = '')
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    if (!empty($data)) {
        if( $json && is_array($data)){
            $data = json_encode($data);
        }
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        if( $json ){ //发送JSON数据
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                array(
                    'Authorization:Bearer '.$token,
                    'Content-Type: application/json; charset=utf-8',
                )
            );
        }
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_exec($curl);
    $errorno = curl_errno($curl);
    curl_close($curl);
    if ($errorno) {
        return array('errorno' => false, 'errmsg' => $errorno);
    }
    return json_decode($res, true);
}

/**
 * ERP格式化小数方法
 * @param float $num 小数值
 * @param int $long 小数位长度
 * @param int $type 处理方法 0 截取 1 四舍五入
 * @return string $result
 */
function ErpFormatFloat($num, $long = 8, $type = 0){
    if ($type == 0){
        $long += 1;
        $result = substr(sprintf('%.' .$long. 'f', $num), 0, -1);
        $result = round($result, 8, PHP_ROUND_HALF_DOWN);
    }else if($type == 1){
        $result = round($num, $long);
    }
    $result = sctonum($result,8);
    return $result;

}

/**
 * @param $num         科学计数法字符串  如 2.1E-5
 * @param int $double 小数点保留位数 默认5位
 * @return string
 */

function sctonum($num, $double = 5){
    $num = round($num,$double);
    if(false !== stripos($num, "e")){
        $a = explode("e",strtolower($num));
        return bcmul($a[0], bcpow(10, $a[1], $double), $double);
    }
    return $num;
}

function currentTime($format = 'Y-m-d H:i:s')
{
    return date($format);
}
/**
 * 升转吨
 * @param $l 升数
 * @param $d 密度
 * @return mixed
 */
function literToTon($l, $d){
    return ($l * $d)/1000;
}


/**
 * 吨转升
 * @param $d 吨数
 * @param $m 密度
 * @return mixed
 */
function tonToLiter($d, $m){
    return ($d * 1000) / $m;
}

/*
 * -----------------------------------------------------
 * 手机号码验证
 * Author:<jk>   Time:2016-09-09
 * -----------------------------------------------------
 */
function isMobile($mobile)
{
    if (!preg_match("/^1[34578]{1}\d{9}$/", strHtml($mobile))) return false;

    return true;
}

/**
 *
 * 业务及第三方相关函数
 *
 * 2019-1-22 架构优化 xiaowen
 */

//默认订单截止时间 当前时间加2小时 xiaowen 2017-4-18
function defaultSaleOrderEndTime(){
    return date('Y-m-d H:i:s', time() + 3600 * 5);
}

//取当天最后时间  xiaowen 2017-4-18
function todayLastTime(){
    return date('Y-m-d 23:59:59');
}
/**
 * 返回截单 剩余时间
 * @param $end_time
 * @param string $start_time
 * @return string
 * @author xiaowen
 * @time 2017-4-18
 */
function getSaleOrderResidueTime($end_time, $start_time = ''){
    $start_time = $start_time ? $start_time : currentTime();

    if($end_time > 0 && strtotime($end_time) > strtotime($start_time)){

        $cle = strtotime($end_time) - strtotime($start_time); //得出时间戳差值
        /*舍去法取整*/
        //$d = floor($cle/86400);
        // $h = floor(($cle%(86400))/3600);  //%取余
        // $m = floor(($cle%(86400))%3600/60);
        $m = floor($cle/60);
        //$s = floor(($cle%(3600*24))%60);
        //$time = $h . '小时 ' . $m . '分';
        $time = $m . '分';
        return $time;
    }
    return '0分';
}

/**
 * 判断订单是否超时 以订单截止时间为准
 * @param $end_order_time
 * @return bool
 * @author xiaowen
 * @time 2017-4-21
 */
function endOrderTimeOut($end_order_time){
    if($end_order_time){
        return time() >= strtotime($end_order_time) ? true : false;
    }
}

/**
 * @param string $key 审核职位代号
 * @return array
 */
function workflowPositionCode($key = ''){
    $position_code = [
        'ExchangeManager' => 'ExchangeManager_', //分公司交易负责人
        'GeneralManager' => 'GeneralManager_0', //采购中心总经理
        'PurchaseManager' => 'PurchaseManager_',//分公司采购负责人
        'FinanceManager' => 'FinanceManager_0',//财务经理
        'MarketManager' => 'MarketManager_', //分公司市场开发负责人
        'TradingCenterManager' => 'TradingCenterManager_0', //交易中心总经理
        //'AllocationManager' => 'AllocationManager_0', //调拨负责人
        'AllocationManager' => 'AllocationManager_',        //分公司调拨负责人
        'FoPurchaseManager' => 'FoPurchaseManager_',        //分公司调拨采购负责人
        'MiningSalesManager' => 'MiningSalesManager_0',     // 采销中心总经理
        'AreaManager'       => 'AreaManager_'   ,           // 分公司经理审批
        'StoreAreaManager'  => 'StoreAreaManager_',         // 地区仓管负责人
        'PreStoreManager'   => 'PreStoreManager_',          // 预存管理人
        'PrePayManager'     => 'PrePayManager_',            // 预付管理人
    ];
    if($key && array_key_exists($key, $position_code)){
        return $position_code[$key];
    }
    return $position_code;
}

/**
 * 销售单售价高于采购定价审核步骤对应职位
 * @param $key
 * @return array
 */
function workflowTypeStepPosition($key){
    $step_type_position = [
        # 原需求 销售单价格>=采购定价--------------------------------------------------------------------------------|
        # 现结：不需要审批
        # 账期：交易员创建销售单->分公司交易负责人审批->分公司采购负责人审批->财务经理审批
        # 货到付款：交易员创建销售单->分公司交易负责人审批->交易中心总经理审批
        # 代采现结：交易员创建销售单->分公司交易负责人审批->交易中心总经理审批->分公司采购负责人审批->采购中心总经理审批
        # 定金锁价（代采）：交易员创建销售单->分公司采购负责人审批
        # 定金锁价（非代采）：交易员创建销售单->分公司交易负责人审批->交易中心总经理审批->分公司采购负责人审批

        /*
            2 => ['ExchangeManager', 'PurchaseManager', 'FinanceManager'], //账期
            3 => ['ExchangeManager', 'TradingCenterManager', 'PurchaseManager', 'GeneralManager'], //代采现结
            4 => ['ExchangeManager', 'TradingCenterManager'], //货到付款
            5 => [
                1 =>['PurchaseManager'], //定金锁价 代采
                2 =>['ExchangeManager', 'TradingCenterManager', 'PurchaseManager'], //定金锁价 非代采
            ],//定金锁价
        */

        # 修改后：--------------------------------------------------------------------------------------------------|
        # 待采现结        ： 交易员创建销售单->分公司交易负责人审批->分公司采购负责人审批->分公司经理审批->采销中心总经理审批
        # 货到付款        ： 交易员创建销售单->分公司交易负责人审批->分公司经理审批
        # 定金锁价 非待采 ： 交易员创建销售单->分公司交易负责人审批->分公司采购负责人审批->分公司经理审批
        2 => ['ExchangeManager', 'PurchaseManager', 'FinanceManager'],                  # 账期
        3 => ['ExchangeManager', 'PurchaseManager','AreaManager','MiningSalesManager'], # 代采现结
        4 => ['ExchangeManager', 'AreaManager'],                                        # 货到付款
        5 => [
            1 =>['PurchaseManager'],                                                    # 定金锁价 代采
            2 =>['ExchangeManager', 'PurchaseManager', 'AreaManager'],                  # 定金锁价 非代采
        ],//定金锁价


    ];
    if($key && array_key_exists($key, $step_type_position)){
        return $step_type_position[$key];
    }
    return $step_type_position;
}

/**
 * 销售单售价低于采购定价审核步骤对应职位
 * @param $key
 * @return array
 */
function workflowTypeStepLowPricePosition($key){
    #   2、销售单价格<采购定价：
    #   现结：交易员创建销售单（市场信息）不做调整
    #   现结：交易员创建销售单（不含市场信息）->分公司交易负责人审批->交易中心总经理审批->分公司采购负责人审批
    #   调整为：交易员创建销售单（不含市场信息）->分公司交易负责人审批->分公司采购负责人审批->分公司经理审批

    $step_type_position = [
        1 => [
            1 => ['PurchaseManager'],
            // 2 => ['ExchangeManager', 'TradingCenterManager','PurchaseManager'],
            2 => ['ExchangeManager', 'PurchaseManager', 'AreaManager'],
        ], //现结
        2 => [
            1 => ['PurchaseManager', 'FinanceManager'],
            2 => ['ExchangeManager', 'TradingCenterManager','PurchaseManager','FinanceManager'],

        ], //账期
        3 => [
            'ExchangeManager', 'TradingCenterManager','PurchaseManager', 'GeneralManager'
        ], //代采现结
        4 => [], //货到付款 不需要审批
        5 => [],//定金锁价 暂时不审批

    ];
    if($key && array_key_exists($key, $step_type_position)){
        return $step_type_position[$key];
    }
    return $step_type_position;
}

/**
 * 采购单审批流程
 * @param $key
 * @return array
 */
function purchaseWorkflowStepPosition($key){
    $step_type_position = [
        # 1 => ['PurchaseManager','GeneralManager'], //自采
        # 2 => ['PurchaseManager'], //代采
        'PurchaseManager','AreaManager'
    ];
    if($key && array_key_exists($key, $step_type_position)){
        return $step_type_position[$key];
    }
    return $step_type_position;
}

/**
 * 调拨单审批流程
 * @param int allocation_type 调拨类型： 1  城市仓->服务商 2 服务商->服务商3 服务商->城市仓4 城市仓->城市仓'
 * @return array
 */
function allocationWorkflowStepPosition($type = 1,$allocation_type){
    //$step_type_position = [ 'AllocationManager', 'FinanceManager' ];
    //$step_type_position = [ 'AllocationManager', 'FoPurchaseManager','AreaManager' ]; //除服务商到服务商外的调拨单：分公司调拨负责人  -》 分公司调拨采购负责人
    //if($allocation_type == 2){
    //   unset($step_type_position[1]); //服务商到服务商外的调拨单：只要 分公司调拨负责人 审批
    //}
    //    return $step_type_position;

    $step_type_position = [];
    if($allocation_type == 2){                   # 服务商到服务商的调拨单：只要 分公司调拨负责人 审批
        return ['AllocationManager'];
    }
    if(intval($type) == 1){                      # 2）当调拨吨数＞30吨 调整为：调拨员创建调拨单-> 地区调拨负责人-> 地区调拨采购负责人审批 -> 地区分公司负责人审批
        $step_type_position = [ 'AllocationManager', 'FoPurchaseManager','AreaManager' ];
    }
    if(intval($type) == 2){                      # 1）当调拨吨数≤30吨 调整为： 调拨员创建调拨单-> 地区调拨采购负责人审批
        # $step_type_position = ['FoPurchaseManager'];
        $step_type_position = ['FoPurchaseManager'];
    }
    return $step_type_position;

}

/**
 * 预存预付审批流程
 * @param $key
 * @return array
 */
function rechargeWorkflowStepPosition($key){
    $step_type_position = [
        1 => ['PreStoreManager'], # 预存
        2 => ['PrePayManager']    # 预付
    ];
    if($key && array_key_exists($key, $step_type_position)){
        return $step_type_position[$key];
    }
    return $step_type_position;
}

function _getFloatLength($num) {
    $count = 0;

    $temp = explode ( '.', $num );

    if (sizeof ( $temp ) > 1) {
        $decimal = end ( $temp );
        $count = strlen ( $decimal );
    }

    return $count;
}

/**
 * 1. 销退单审批职位
 * 2. 采退单审批职位
 * @param $key
 * @return array
 */
function returnedOrderWorkflowStep($key){
    $step_type_position = [
        1 => ['ExchangeManager',  'AreaManager'],                           # 销退单 交易员创建销退订单->分公司交易负责人审批->分公司负责人审批
        2 => [
            1 =>['PurchaseManager','AreaManager'],                          # 采退单 采购退货量（有效订单）≤100吨    采购员或商务文员创建->城市地区采购负责人->分公司经理审批
            2 =>['PurchaseManager','AreaManager', 'MiningSalesManager'],    # 采退单 采购退货量（有效订单）＞ 100吨  采购员或商务文员创建->城市地区采购负责人->分公司经理审批->采销中心总经理
        ],
    ];
    if($key && array_key_exists($key, $step_type_position)){
        return $step_type_position[$key];
    }
    return $step_type_position;
}

function pw_encode($pwd, $type) {

    if ($type == 1) {//旧的加密方式
        $code = md5(md5($pwd) + "a");
    } else if ($type == 0) {//新的加密方式
        $code = md5(md5($pwd) . "a");
    }
    return $code;
}


/**
 * 返回TMS接口验证摘要
 * @param $params
 * @return string
 * @author xiaowen
 * @time 2018-8-13
 */
function getTmsApiSign($params){
    //对参数数组按参数字母升序排列
    ksort($params);
    $new_str = '';
    //循环拼接参数串
    foreach ($params as $key=>$value){
        $new_str .= $key . $value;
    }
    $sign_params = C('TMS_APP_SECRET') . $new_str . C('TMS_APP_SECRET');
    //echo "<hr/>".$sign_params;
    //最终的参数串进行md5加密并转成大写，得到最后的加密摘要
    //echo '<br/>';
    $sign = strtoupper(md5($sign_params));
    return $sign;
}

/**
 * 返回TMS接口请求的所需的全部参数
 * @author xiaowen
 * @time 2018-9-3
 * @param array $params  请求tms接口的业务参数
 * @return array $allParams
 */
function getTmsAllParams($params = []){
    $allParams['data'] = $params['data'];
    $allParams['method'] = C('TMS_ADD_ORDER_API');
    $allParams['timestamp'] = currentTime();
    $allParams['app_key'] = C('TMS_APP_KEY');
    //通过getTmsApiSign获取tms的加密串
    $allParams['sign'] = getTmsApiSign($allParams);
    return $allParams;
}

/**
 * 请求tms开单接口
 * @author xiaowen
 * @time 2018-9-3
 * @param  string  $api_url 请求接口地址
 * @param  array  $params 请求参数
 * @return array $result
 */
function postTmsApiAddOrder($params = [], $api_url = ''){
    $api_url = $api_url ? $api_url : C('TMS_APP_TEST_URL');
    $result_json =  http($api_url, $params);

    log_info($result_json);

    return json_decode($result_json,true);
}

/**
 * 返回小数位数
 * @author xiaowen
 * @time 2019-3-28
 * @param $f 判断的小数
 * @return int $count
 */
function getFloatLength($f){
    $count = 0;
    if($f){
        $tmp = explode('.', $f);
        if(count($tmp) > 1){
            $count = strlen(end($tmp));
        }
    }
    return $count;
}

/**
 * 验证公司是否为ERP内部公司
 * @author xiaowen
 * @time 2019-3-28
 * @param $company_name
 * @return boolean $result
 */
function checkInErpCompany($company_name = ''){
    $result = false;
    if($company_name){
        $result = in_array($company_name, getErpCompanyList('company_name')) ? true : false;
    }
    return $result;
}

/**
 * 计算加权成本
 * @author guanyu
 * @time 2019-05-31
 * @param $company_name
 * @return boolean $result
 */
function calculateWeightingCost($before_num,$before_price,$new_num,$new_price){
    if ($before_num + $new_num == 0) {
        return 0;
    }
    $price = ($before_price * $before_num + $new_price * $new_num) / ($before_num + $new_num);
    return $price;
}

/**
 * 财务中台接口调用
 * @author guanyu
 * @time 2019-06-18
 * @param array $param
 * @return mixed|string
 */
function fmdInterface($param = [],$function = ''){
    $mathod = 'POST';
    $source = 1;
    $time = time();
    $app_key = C('FINANCE_APP_KEY');
    $app_secret = C('FINANCE_APP_SECRET');
    $params = json_encode(['action_list'=>$param]);
    $token = md5(strtoupper($app_secret.$time.$app_key.$params.$app_secret));
    $header = [
        'Content-Type:application/json',
        'Content-Length:'.strlen($params) ,
        'source:'.$source,
        'time:'.$time,
        'token:'.$token,
    ];
    $url = C('FINANCE_MIDDLE_GROUND') . $function;

    $log_info_data = array(
        'event'=> '财务中台，申请销售发票->接口调用',
        'key'=> '入库单：' . $param['storageCode'],
        'request'=> '接口url：' . $url . ', 接口请求参数：' .$params,
    );
    log_write($log_info_data);
    $result = http($url,$params,$mathod,$header,true);
    return json_decode($result,true);
}

