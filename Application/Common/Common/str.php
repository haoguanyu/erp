<?php

// @常规字符或无需数据处理的自定义函数库


function changeSmsType($str = '')
{
    $result = [
        '1' => '营销短信',
        '2' => '通知短信',
        '3' => '验证码'
    ];

    return $result[$str];
}

/**
 * ERP角色管理
 * @param int $status
 * @param bool|false $show
 * @return array
 */
function ErpRoleList($status = 0){
    $data = [
        '仓管员' => 'Role-StorehouseManagement',
        '调拨员' => 'Role-AllocationManagement',
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

//'0取消(无效) 10待审核 2审核未通过 12待派单 20待服务  100已服务'
function status()
{
    $result = [
        '0' => '取消',
        '10' => '待审核',
        '2' => '审核未通过',
        '12' => '待派单',
        '20' => '待服务',
        '100' => '已服务'
    ];
    return $result;
}

// '0 未对账(未付款) 1已对账(已付款)'
function pay_status()
{
    $result = [
        '0' => '未对账',
        '1' => '已对帐'
    ];
    return $result;
}

//'0未结算 1预结算 2已结算'
function settlement_status()
{
    $result = [
        '0' => '未结算',
        '1' => '预结算',
        '2' => '已结算'
    ];
    return $result;
}

// 0未审核 1审核未通过 10审核通过
function zhaoyou_status()
{
    $result = [
        '0' => '未审核',
        '1' => '审核不通过',
        '10' => '审核通过',
    ];
    return $result;
}

// 0未审核 1审核未通过 10审核通过
function party_status()
{
    $result = [
        '0' => '未审核',
        '1' => '审核不通过',
        '10' => '审核通过',
    ];
    return $result;
}

// 0删除 1审核未通过 10待审核 20审核通过
function goods_status()
{
    $result = [
        '0' => '无效',
        '1' => '审核未通过',
        '10' => '待审核',
        '20' => '审核通过'
    ];
    return $result;
}

/**
 * ERP编码类型
 * @param int $type
 * @return array
 */
function erpCodeType($type = 0)
{

    $arr = [
        1 => '供货单',
        2 => '交易单',
        3 => '购货单',
        4 => '商品编码',
        5 => '采购单号',
        6 => '销售单号',
        7 => '出库单号',
        8 => '入库单号',
        9 => '调拨单号',
        10 => '商城供货单',
        11 => '采购退货单',
        12 => '销售退货单',
        13 => '预存申请单',
        14 => '预付申请单',
        15 => '盘点单',
        16 => '期初调整单',
        17 => '配送单',
        18 => "内部交易需求单",
        19 => "内部交易单",
        20 => "系统批次",
        21 => "出库申请单",
        22 => "入库申请单",
        23 => "损耗单",
        24 => "运费单",
    ];
    if ($type) {
        return $arr[$type];
    } else {
        return $arr;
    }

}

/**
 * ERP编码前缀
 * @param int $type
 * @return array
 */
function erpCodePre($type = 0)
{

    $arr = [
        1 => 'GY', //供应单
        2 => 'JY', //交易单
        3 => 'QG', //
        4 => 'C',  //商品
        5 => 'PO', //采购
        6 => 'SO', //销售
        7 => 'DO', //出库
        8 => 'RO', //入库
        9 => 'FO', //调拨
        10 => 'WR', //商城供货单
        11 => 'RP', //采购退货单
        12 => 'RS', //销售退货单
        13 => 'PD', //预存单
        14 => 'AP', //预付单
        15 => 'ST', //盘点单
        16 => 'NI', //盘点单（期初库存调整单）
        17 => 'LO', //配送单
        18 => 'ID', //内部需求单
        19 => 'IO', //内部交易单
        20 => 'BN', //系统批次
        21 => 'DPO', //出库申请单
        22 => 'RPO', //入库申请单
        23 => 'WO', //损耗单
        24 => 'FT', //运费单
    ];
    if ($type) {
        return $arr[$type];
    } else {
        return $arr;
    }

}

/**
 * 商品编码前缀
 * @param string $key
 * @return array
 */
function erpGoodsPre($key = '')
{
    $data = [
        '柴油' => 'C',
        '汽油' => 'Q',
        //'航煤' => 'H',
        '原料' => 'Y',
        '燃料油' => 'R',
        //'船用油' => 'S',
        '润滑油' => 'L',
        '天然气' => 'G',
        '航空煤油' => 'H',
        '生物柴油' => 'S',
        '非成品油石油制品' => 'F',
    ];
    if ($key) {
        return $data[$key];
    } else {
        return $data;
    }
}

/**
 * 公司规模
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function companySize()
{
    return ['10人及以下', '10人至50人', '50人至100人', '100人至500人', '500人至1000人', '1000人以上'];
}

/**
 * 公司职位
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function companyPosition()
{
    return ['董事长', '总经理', '副总经理', '销售经理', '财务经理', '采购经理', '员工'];
}

/**
 * 公司等级
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function companyLevel()
{
    return ['F', 'B1', 'B1+', 'B2-', 'B2', 'C', '未分级'];
}

/**
 * 所属行业
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function industry()
{
    return ['油贸', '公路运输', '工矿企业', '建筑', '农业', '水路运输', '电力', '渔业', '商业及民用', '铁路运输'];
}

/**
 * 销售规模
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function saleAmount()
{
    return ['500万以下', '500-1000万', '1000万到5000万', '5000万到1个亿', '1个亿-10个亿', '10亿以上'];
}

/**
 * 分公司地区
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function branchCity()
{
    return ['上海分公司（上海）' => 321, '苏州分公司（苏州）' => 221, '广州分公司（广州）' => 76, '东营分公司（东营）' => 287, '宁波分公司（宁波）' => 388];
}

/**
 * 关系
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function relation()
{
    return [1 => '交易', 2 => '从属'];
}

/**
 * uc 用户与公司关系类型
 * @param int $type 类型key
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function ucDealStatus($type = 0)
{
    $data = [
        // 0=>'批发从属',
        1 => '批发交易',
        2 => '零售',
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 公司图片类型
 * @param int $is_need_check 是否返回需要审核类型 默认0 全都 1需要认证类型
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function ClientsCtsType($is_need_check = 0)
{
    $arr = array(
        '三合一',
        '营业执照',
        '税务登记证',
        '组织机构代码证',
        '成品油批发经营批准证书',
        '开户许可证',
        '开票资料',
        '道路运输经营许可证',
        '危险化学品经营许可证'
    );
    if ($is_need_check == 1) {
        $arr = array_slice($arr, 0, 4);
    } else if ($is_need_check == 2) {
        $arr = array_slice($arr, 4, 5);
    }
    return $arr;
}

/**
 * 公司认证状态
 * @author xiaowen
 * @time 2016-10-13
 * @return array

function ClientStatus(){
 * return [
 * 0 => '未认证',
 * 1 =>'认证通过',
 * 2 =>'认证中',
 * 3 =>'认证失败',
 * ];
 * }
 */
/**
 * 公司认证状态
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function ClientStatus()
{
    /**
     * return [
     * 0 => '未审核',
     * 1 =>'公司待审核',
     * 2 =>'公司已审核',
     * 3 =>'三证待审核',
     * 4 =>'三证已审核',
     * ];*/
    return [
        0 => '未审核',
        1 => '信息审核失败',
        10 => '信息待审核',
        20 => '信息已审核',
        21 => '三证审核失败',
        30 => '三证待审核',
        40 => '三证已审核',
    ];
}

/**
 * 公司来源
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function ClientSourceFrom()
{
    return [
        0 => '网页端',
        1 => '安卓',
        2 => 'IOS',
        3 => '油沃客'
    ];
}

/**
 * 修改原因
 * @author xiaowen
 * @time 2016-10-13
 * @return array
 */
function CancelOrderReason()
{
    return [
//        '服务商原因',
//        '客户原因',
//        '其他原因',
        '客户原因-无人收货',
        '客户原因-价格质疑',
        '客户原因-油品质疑',
        '客户原因-行程有变',
        '客户原因-不能存放',
        '客户原因-提前加好了',
        '客户原因-园区不让进',
        '服务商原因-休息',
        '服务商原因-来不及配送',
        '服务商原因-车子抛锚',
        '服务商原因-服务商漏单',
        '业务员原因-信息错误',
        '业务员原因-设配没到位',
        '业务员原因-重复下单',
        '业务员原因-周期没到',
        '业务员原因-没有和客户沟通好',
        '售后原因-漏派',
        '售后原因-派错',
    ];
}

/**
 * 付款方式
 * @author xiaowen
 * @time 2016-11-14
 * @return array
 */
function collectionSource()
{
    $result = array(
        ['name' => '支付宝', 'code' => '1'],
        ['name' => '微信', 'code' => '2'],
        ['name' => '账户余额', 'code' => '3'],
        ['name' => '现金', 'code' => '6'],
        ['name' => 'POS机', 'code' => '6'],
        ['name' => '网银', 'code' => '5'],
        ['name' => '其它', 'code' => '4']
    );
    return $result;
}

function getFrontMessage($str = '')
{
    $result = [
        'a' => '系统异常，请重新尝试！',
        'b' => '更新成功！',
        'c' => '更新失败，请重新尝试！'
    ];

    return $result[$str];
}

/**
 * 性别
 * @author xiaowen
 * @time 2016-11-14
 * @return array
 */
function userSex()
{
    $data = ['男', '女'];
    return $data;
}

/**
 * 性别
 * @author xiaowen
 * @time 2016-11-14
 * @return array
 */
function userSource()
{
    $data = [
        '网站注册',
        '地推拜访',
        '公司发放',
        '客户推荐',
        '电话或互联网开发',
        '其他',
    ];
    return $data;
}

function userPosition()
{

    $data = [
        '董事长',
        '总经理',
        '副总经理',
        '销售经理',
        '财务经理',
        '采购经理',
        '员工',
        '挂名',
    ];
    return $data;
}

/**
 * 用户角色
 * @author xiaowen
 * @param $role
 * @return array
 */
function userRole($role = 0)
{

    $data = [
        1 => '老板',
        2 => '经理',
        3 => '财务',
        4 => '主管',
        5 => '客服',

    ];
    if ($role) {
        return $data[$role];
    }
    return $data;
}

/**
 * 油品
 * @author xiaowen
 * @return array
 */
function oilType()
{
    $data = [
        '柴油',
        '汽油', '航煤', '原料', '燃料油', '船用油', '天然气'
    ];
    return $data;
}

/**
 * 油品标号
 * @author xiaowen
 * @return array
 */
function oilLevel()
{

    $data = [
        '0#',
        '-10#',
    ];
    return $data;
}

/**
 * 油品级别
 * @author xiaowen
 * @return array
 */
function oilRank()
{

    $data = [
        '国Ⅲ',
        '国Ⅳ',
        '国Ⅴ',
        '国Ⅵ',
    ];
    return $data;
}

/**
 * 油品来源
 * @author xiaowen
 * @return array
 */
function oilSource()
{
    $data = ['中国石化',  '中国石油', '中国海油', '中国化工', '中国中化', '地炼', '海科石化','中航油','其他'];

    return $data;

}

/**
 * 商品状态
 * @param $status
 * @author xiaowen
 * @return array
 */
function oilStatus($status = -1)
{

    $data = [
        0 => '删除',
        1 => '审核不通过',
        10 => '待审核',
        20 => '审核通过',
    ];
    if ($status > -1) {
        return $data[$status];
    }
    return $data;
}

/**
 * 订单状态
 * @param int $status
 * @return array
 */
function orderStatus($status = -1)
{
    $data = [
        0 => '取消',
        2 => '审核不通过',
        10 => '待审核',
        12 => '待派单',
        20 => '待服务',
        100 => '已服务',
    ];
    if ($status >= 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 订单支付状态
 * @param int $status
 * @return array
 */
function orderPayStatus($status = -1)
{
    $data = [
        0 => '未对账(未付款)',
        1 => '已对账(已付款)',
    ];
    if ($status >= 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 订单 找油/集团用户 状态
 * @param int $status
 * @return array
 */
function zhaoyou_party_status($status = 0)
{
    $data = [
        0 => '未审核',
        1 => '审核不通过',
        10 => '审核通过',
    ];
    if ($status >= 0) {
        return $data[$status];
    }
    return $data;
}

/**
 *订单结算状态
 * @param int $status
 * @return array
 */
function orderSetLement($status = -1)
{

    $data = [
        0 => '未结算',
        1 => '预结算',
        2 => '已结算',
    ];
    if ($status >= 0) {
        return $data[$status];
    }
    return $data;
}

/**
 *订单面签截图状态
 * @param int $status
 * @return array
 */
function orderScreenShot($status = -1)
{

    $data = [
        0 => '未上传',
        1 => '已上传',

    ];
    if ($status >= 0) {
        return $data[$status];
    }
    return $data;
}

function bankStatementStatus($status = -1, $model = 1)
{
    if ($model == 1) {
        $data = [
            0 => '<span style="color: #0000cc;">未充值</span>',
            1 => '<span style="color: red;">部分充值</span>',
            2 => '<span style="color: #009900;">已充值</span>',

        ];
    } else {
        $data = [
            0 => '未充值',
            1 => '部分充值',
            2 => '已充值',

        ];
    }

    if ($status >= 0) {
        return $data[$status];
    }
    return $data;
}

function bankSilverType($status = 0)
{
    $data = [
        1 => '网银',
        2 => '微信',
        3 => '支付宝',
        4 => '现金',
        5 => '加油卡',
        6 => '赠送'

    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

function erpGoodsStatus($status = 0)
{
    $data = [
        1 => '未审核',
        2 => '已删除',
        10 => '已审核'

    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

function oilLabel()
{
    $data = [
        '国标',
        '非标',
    ];
    return $data;
}

/**
 *供货单状态
 * @param int $status
 * @return array
 */
function supplyStatus($status = 0)
{

    $data = [
        1 => '未审核',
        2 => '已删除',
        10 => '已审核',
    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 交易单状态
 * @param int $status
 * @return array
 */
function ErpOrderStatus($status = 0)
{

    $data = [
        1 => '未审核',
        2 => '已删除',
        3 => '已预审',
        10 => '已审核',
    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 供货单是否商城
 * @param int $status
 * @return array
 */
function supplyMallGoods($status = 0)
{

    $data = [
        1 => '是',
        2 => '否',

    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 供货单上下架状态
 * @param int $status
 * @return array
 */
function supplyUpDownStatus($status = 0)
{

    $data = [
        //1=>'上架',
        //2=>'下架',
        1 => '是',
        2 => '否',

    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 供货单商品状态
 * @param int $status
 * @return array
 */
function supplyGoodsStatus($status = 0)
{

    $data = [
        1 => '现单',
        2 => '现开单',
        3 => '现卡',
        4 => '现开卡',
        5 => '无'
    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 供货单提货方式
 * @param int $status
 * @return array
 */
function supplyPickUpWay($status = 0)
{

    $data = [
        1 => '大卡',
        2 => '小卡',
        3 => '提单',
        4 => '报车号',
        5 => '提单+报车号',
        6 => '报企业名+报车号',
        7 => '车号+计划号'
    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 供货单开票类型
 * @param int $status
 * @return array
 */
function supplyInvoiceType($status = 0)
{

    $data = [
        1 => '下月开票',
        2 => '月末开票'
    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 区域商品状态
 * @author xiaowen
 * @param int $status
 * @param bool $is_show
 * @return array
 */
function erpRegionGoodsStatus($status = 0, $is_show = true)
{
    if (!$is_show) {
        $data = [
            1 => '可售',
            2 => '停售',
        ];
    } else {
        $data = [
            1 => '<span class="c-success"><b>可售</b></span>',
            2 => '<span class="c-warning"><b>停售</b></span>',
        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 仓库状态
 * @param int $status
 * @return array
 */
function erpStorehouseStatus($status = 0 , $show = 0 )
{
    $data = [
        1 => '启用',
        2 => '禁用'
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>启用</b></span>',
            2 => '<span class="c-warning"><b>禁用</b></span>',
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 仓库类型
 * @param int $status
 * @return array
 */

/**
 * 采购付款方式
 * @param int $type
 * @return array
 * @author xiaowen
 * @time 2017-4-1
 */
function purchasePayType($type = 0)
{
    $data = [
        1 => '现结',
        2 => '预付',
        3 => '账期',
        4 => '代采现结',
        5 => '定金锁价',
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 采购单合同状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function purchaseContract($status = 0, $show = false)
{
    $data = [
        1 => '是',
        2 => '否',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>是</b></span>',
            2 => '<span class="c-warning"><b>否</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 采购单状态
 * @param int $status
 * @param  $show
 * @return array
 */
function purchaseOrderStatus($status = 0, $show = false)
{
    $data = [

        1 => '未审核',
        2 => '已取消',
        3 => '已审核',
        4 => '已复核',
        10 => '已确认',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            3 => '<span class="c-warning"><b>已审核</b></span>',
            4 => '<span class="c-danger"><b>已复核</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 采购单付款状态
 * @param int $status
 * @return array
 */
function purchasePayStatus($status = 0, $show = false)
{
    $data = [
        1 => '未付款',
        2 => '部分预付',
        3 => '已预付',
        4 => '部分付款',
        10 => '已付款',
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>未付款</b></span>',
            2 => '<span class="c-warning"><b>部分预付</b></span>',
            3 => '<span class="c-danger"><b>已预付</b></span>',
            4 => '<span class="c-secondary"><b>部分付款</b></span>',
            10 => '<span class="c-success"><b>已付款</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 采购单/销售单 发票状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function purchaseInvoiceStatus($status = 0, $show = false)
{
    $data = [
        1 => '未收票',
        2 => '部分收票',
        10 => '已收票',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>未收票</b></span>',
            2 => '<span class="c-warning"><b>部分收票</b></span>',
            10 => '<span class="c-success"><b>已收票</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 销售单 发票状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function saleInvoiceStatus($status = 0, $show = false)
{
    $data = [
        1 => '未开票',
        2 => '部分开票',
        10 => '已开票',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>未开票</b></span>',
            2 => '<span class="c-warning"><b>部分开票</b></span>',
            10 => '<span class="c-success"><b>已开票</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 发票类型
 * @param int $status
 * @return array
 */
function invoiceType($status = 0)
{
    $data = [
        1 => '17%增票',
        2 => '17%普票',
        3 => '17%形式发票',
        4 => '16%增票',
        5 => '16%普票',
        6 => '16%形式发票',
        7 => '13%增票',
        8 => '13%普票',
        9 => '13%形式发票'
    ];
    if ($status > 0) {
        return $data[$status];
    }
    krsort($data);
    return $data;
}

/**
 * 发票税率
 * 2019-3-26
 * @param int $type
 * @return array|mixed
 */
function invoiceTaxRate($type = 0){
    $data = [
        1 => 0.17,
        2 => 0.17,
        3 => 0.17,
        4 => 0.16,
        5 => 0.16,
        6 => 0.16,
        7 => 0.13,
        8 => 0.13,
        9 => 0.13,
    ];
    if ($type > 0) {
        return $data[$type];
    }
    return $data;
}

/**
 * 采购类型
 * @param int $status
 * @param bool $show
 * @return array
 */
function purchaseType($status = 0, $show = false)
{
    $data = [
        1 => '自采',
        2 => '代采'
    ];
    if($show){
        $data = [
            1 => '<span class="c-success"><b>自采</b></span>',
            2 => '<span class="c-warning"><b>代采</b></span>',
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 采购单状态
 * @param int $status
 * @param  $show
 * @return array
 */
function saleOrderStatus($status = 0, $show = false)
{
    $data = [

        1 => '未审核',
        2 => '已取消',
        3 => '已审核',
        4 => '已复核',
        10 => '已确认',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            3 => '<span class="c-warning"><b>已审核</b></span>',
            4 => '<span class="c-danger"><b>已复核</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 采购申请付款状态
 * @param int $status
 * @return array
 */
function paymentStatus($status = 0, $show = false)
{
    $data = [
        1 => '已申请',
        2 => '已驳回',
        3 => '已同意',
        10 => '已付款',
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>已申请</b></span>',
            2 => '<span class="c-default"><b>已驳回</b></span>',
            3 => '<span class="c-warning"><b>已同意</b></span>',
            10 => '<span class="c-success"><b>已付款</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 采购发票状态
 * @param int $status
 * @return array
 */
function invoiceStatus($status = 0, $show = false)
{
    $data = [
        1 => '已申请',
        2 => '已驳回',
        10 => '已完成',
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>已申请</b></span>',
            2 => '<span class="c-danger"><b>已驳回</b></span>',
            10 => '<span class="c-success"><b>已完成</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 销售订单类型
 * @param int $status
 * @return array
 */
function saleOrderType($status = 0)
{
    $data = [
        1 => '批发',
        2 => '零售'
    ];
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 销售单收款状态
 * @param int $status
 * @param $show
 * @return array
 */
function saleCollectionStatus($status = 0, $show = false)
{
    $data = [
        1 => '未收款',
        2 => '部分预收',
        3 => '已收预付款',
        4 => '部分收款',
        10 => '已收款',
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>未收款</b></span>',
            2 => '<span class="c-warning"><b>部分预收</b></span>',
            3 => '<span class="c-danger"><b>已收预付款</b></span>',
            4 => '<span class="c-secondary"><b>部分收款</b></span>',
            10 => '<span class="c-success"><b>已收款</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}


/**
 * 销售单来源 1 后台 2 WEB 3 APP  4 零售
 * @param int $status
 * @param $show
 * @return array
 */
function saleOrderSourceFrom($status = 0, $show = false)
{
    $data = [
        1 => '后台',
        2 => 'WEB',
        3 => 'APP',
        4 => '小微零售',
        5 => '集团零售',
        6 => 'OMS',
        7 => 'C端',
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>后台</b></span>',
            2 => '<span class="c-warning"><b>WEB</b></span>',
            3 => '<span class="c-danger"><b>APP</b></span>',
            4 => '<span class="c-secondary"><b>小微零售</b></span>',
            5 => '<span class="c-success"><b>集团零售</b></span>',
            6 => '<span class="c-green"><b>OMS</b></span>',
            7 => '<span class="c-primary"><b>C端</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 销售单提货方式 1 配送 2 自提
 * @param int $status
 * @param $show
 * @return array
 */
function saleOrderDeliveryMethod($status = 0, $show = false)
{
    $data = [
        1 => '配送',
        2 => '自提',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>配送</b></span>',
            2 => '<span class="c-warning"><b>自提</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 销售单付款方式 1 现结、2 账期、3 代采现结、4 货到付款、5定金锁价
 * @param int $type
 * @return array
 * @author xiaowen
 * @time 2017-4-1
 */
function saleOrderPayType($type = 0)
{
    $data = [
        1 => '现结',
        2 => '账期',
        3 => '代采现结',
        4 => '货到付款',
        5 => '定金锁价',
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * [审批流]订单类型 1 销售单、2 采购单、3 调拨单 4 销退单、5 采退单
 * @param int $type
 * @return array
 * @author qianbin
 * @time 2017-05-04
 */
function workflowOrderType($type = 0)
{
    $data = [
        1 => '销售单',
        2 => '采购单',
        3 => '调拨单',
        4 => '销退单',
        5 => '采退单',
        6 => '预存单',
        7 => '预付单',
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * [审批流]审批状态 1 审批中、2 已取消、3 已完成
 * @param int $type
 * @return array
 * @author qianbin
 * @time 2017-05-04
 */
function workflowStatusType($type = 0)
{
    $data = [
        1 => '<span class="c-primary"><b>审批中</b></span>',
        2 => '<span class="c-default"><b>已取消</b></span>',
        3 => '<span class="c-success"><b>已完成</b></span>'
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * [审批流]审核装态 1 通过、2 驳回
 * @param int $type
 * @return array
 * @author qianbin
 * @time 2017-05-05
 */
function workflowStatus($status = 0)
{
    $data = [
        1 => '<span class="c-primary"><b>通过</b></span>',
        2 => '<span class="c-danger"><b>驳回</b></span>'
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * [审批流]审核装态 1 审批历史、2 无效审批历史
 * @param int $type
 * @return array
 * @author qianbin
 * @time 2017-05-05
 */
function workflowLogStatus($status = 0)
{
    $data = [
        1 => '<div class="c-warning" style="margin-bottom: 10px; font-weight: bold;">审批中流程</div>',
        2 => '<div class="c-danger" style="margin-bottom: 10px; font-weight: bold;">无效流程</div>',
        3 => '<div class="c-danger" style="margin-bottom: 10px; font-weight: bold;">已完成流程</div>'
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * [调拨单]调拨类型 1 一级仓->服务商 2 服务商->服务商3 服务商->一级仓4 一级仓->一级仓
 * @param int $type
 * @return array
 * @author qianbin
 * @time 2017-05-04
 */
function allocationOrderType($type = 0)
{
    $data = [
        1 => '一级仓->服务商',
        2 => '服务商->服务商',
        3 => '服务商->一级仓',
        4 => '一级仓->一级仓'
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 入库单状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function stockInStatus($status = 0, $show = false)
{
    $data = [
        1 => '未审核',
        2 => '已取消',
        10 => '已审核',
    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-danger"><b>已取消</b></span>',
            10 => '<span class="c-success"><b>已审核</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 出/入库单财务审核状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function financeStatus($status = 0, $show = false)
{
    $data = [
        1 => '未核对',
        2 => '已驳回',
        10 => '已核对',
    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未核对</b></span>',
            2 => '<span class="c-danger"><b>已驳回</b></span>',
            10 => '<span class="c-success"><b>已核对</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 出库单状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function stockOutStatus($status = 0, $show = false)
{
    $data = [
        1 => '未审核',
        2 => '已取消',
        10 => '已审核',
    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-danger"><b>已取消</b></span>',
            10 => '<span class="c-success"><b>已审核</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 入库单类型
 * @param int $status
 * @param bool $show
 * @return array
 */
function stockInType($status = 0)
{
    $data = [
        1 => '采购',
//        2 => '配货',
        2 => '调拨',
        3 => '销退',
        4 => '实物盘盈',
        5 => '库存调整',

    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 代采销售单付款方式转采购单付款方式
 * @param int $status
 * @param bool $show
 * @return array
 */
function saleToPurchasePayType($status = 0)
{
    $data = [
        1 => 1,
        2 => 3,
        3 => 4,
        5 => 5,
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}


/**
 * 出库单类型
 * @param int $status
 * @param bool $show
 * @return array
 */
function stockOutType($status = 0, $show = false)
{
    $data = [
        1 => '销售',
        //2 => '配货',
        2 => '调拨',
        3 => '采退',
        4 => '实物盘亏',
        5 => '库存调整',
    ];
    if($show){
        $data = [
            1 => '',
            2 => '',
            3 => '',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}
//====================仓库类型 与 库存类型映射关系统配置==================================//
function erpStorehouseType($status = 0, $type=1)
{
    $data = [
        1 => '城市仓',
        2 => '代采仓',
        3 => '零售仓',
        4 => '实体炼厂仓',
        5 => '实体零售仓',
        6 => '实体批发仓',
        7 => '二级网点仓', //edit xiaowen 2018-10-16 新增二级网点仓库
        8 => '损耗仓', //edit xiaowen 2019-5-8 新增二级网点仓库
    ];
    if ($status > 0) {
        return $type == 1 ? $data[$status] : array_search($data[$status]);
    }
    return $type == 1 ? $data : array_keys($data);
}
/**
 * 库存类型
 * @param int $status
 * @return array
 */
function stockType($status = 0)
{
    $data = [
        1 => '城市仓',
        2 => '代采仓',
//        3 => '服务商',
        4 => '加油网点',
        5 => '零售仓',
        6 => '实体炼厂仓',
        7 => '实体零售仓',
        8 => '实体批发仓',
        9 => '损耗仓', // 2019-5-8 损耗仓
    ];

    if ($status) {
        return $data[$status];
    }
    return $data;
}
/**
 * 仓库类型 对应 库存类型
 * @param int $storehouseType
 * @return array
 */
function storehouseTypeToStockType($storehouseType = 0)
{
    $data = [
        1 =>1,// '城市仓',
        2 =>2,// '代采仓',
        3 =>5,// '零售仓',
        4 =>6, //'实体炼厂仓',
        5 =>7, //'实体零售仓',
        6 =>8, //'实体批发仓',
        7 =>4, //'二级网点仓', edit xiaowen 2018-10-16 仓库二级网点对应为网点库存
        8 =>9, //'二级网点仓', edit xiaowen 2019-5-8 仓库二级网点对应为网点库存
    ];

    if ($storehouseType) {
        return $data[$storehouseType];
    }
    return $data;
}

/**
 * 仓库类型 对应 库存类型
 * @param int $storehouseType
 * @return array
 */
function stockTypeToStorehouseType($storehouseType = 0)
{
    $data = [
        1 =>1,// '城市仓',
        2 =>2,// '代采仓',
        5 =>3,// '零售仓',
        6 =>4, //'实体炼厂仓',
        7 =>5, //'实体零售仓',
        8 =>6, //'实体批发仓',
        4 =>7, //'二级网点仓', edit xiaowen 2018-10-16 网点库存调整为仓库二级网点
        9 =>8, //'二级网点仓', edit xiaowen 2018-10-16 网点库存调整为仓库二级网点
    ];

    if ($storehouseType) {
        return $data[$storehouseType];
    }
    return $data;
}

/**
 * 返回仓库对应的库存类型
 * @author xiaowen
 * @param int $storehouse_id
 * @return int $stock_type
 */
function getAllocationStockType($storehouse_id){
    $storehouse_type = M("ErpStorehouse")->where(['id'=>$storehouse_id])->getField('type');

    return storehouseTypeToStockType($storehouse_type);
}

//==================end 仓库类型 与 库存类型映射关系统配置==================================//
/**
 * 调拨单状态
 * @param int $status
 * @param  $show
 * @return array
 */
function AllocationOrderStatus($status = 0, $show = false)
{
    $data = [

        1 => '未审核',
        2 => '已取消',
        3 => '已审核',
        4 => '已复核',
        10 => '已确认',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            3 => '<span class="c-warning"><b>已审核</b></span>',
            4 => '<span class="c-danger"><b>已复核</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 调拨类型
 * @param int $status
 * @param  $show
 * @return array
 */
function AllocationType($status = 0)
{
    $data = [

        1 => '一级仓->服务商',
        2 => '服务商->服务商',
        3 => '服务商->一级仓',
        4 => '一级仓->一级仓',

    ];

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 供货单上下架状态
 * @author xiaowen
 * @param int $status
 * @param bool $show
 * @return array
 */
function supplyAvailableStatus($status = 0, $show = false)
{

    $data = [

        1 => '有效',
        2 => '无效',

    ];
    if($show){
        $data = [
            1=>"<span class=\"c-success\"><b>有效</b></span>",
            2=>"<span class=\"c-error\"><b>无效</b></span>",
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 损耗状态
 * @param int $status
 * @param bool $show
 * @return array
 */
function lossStatus($status = 0, $show = false)
{
    $data = [
        1 => '有损耗',
        2 => '无损耗',
        3 => '已处理',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-warning"><b>有损耗</b></span>',
            2 => '<span class="c-primary"><b>无损耗</b></span>',
            3 => '<span class="c-success"><b>已处理</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 邮件黑名单
 * @param int $status
 * @param bool $show
 * @return array
 */
function emailBlackList()
{
    $data = [
        1 => 'tianjun@51zhaoyou.com',
    ];
    return $data;
}
/**
 * 是否二次定价
 * @param int $status
 * @param bool $show
 * @return array
 */
function orderUpdatePriceStatus($status = 0, $show = false)
{
    $data = [
        1 => '是',
        2 => '否',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>是</b></span>',
            2 => '<span class="c-warning"><b>否</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 退货类型
 * @param int $status
 * @param bool $show
 * @return array
 */
function returnType($status = 0)
{
    $data = [
        1 => '实磅实收',
        2 => '供应商原因',
        3 => '客户原因',
        4 => '合同结算',
        5 => '其他',
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 退货单状态
 * @param int $status
 * @param  $show
 * @return array
 */
function returnedOrderStatus($status = 0, $show = false)
{
    $data = [

        1 => '未审核',
        2 => '已取消',
        3 => '已审核',
        4 => '已复核',
        10 => '已确认',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            3 => '<span class="c-warning"><b>已审核</b></span>',
            4 => '<span class="c-danger"><b>已复核</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 退货单退款状态
 * @param int $status
 * @param  $show
 * @return array
 */
function returnedAmountStatus($status = 0, $show = false)
{
    $data = [
        1 => '未退款',
        10 => '已退款',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>未退款</b></span>',
            10 => '<span class="c-success"><b>已退款</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 调拨单提货方式 1 配送 2 自提
 * @param int $status
 * @param $show
 * @return array
 */
function allocationOrderDeliveryMethod($status = 0, $show = false)
{
    $data = [
        1 => '配送',
        2 => '自提',

    ];
    if ($show) {
        $data = [
            1 => '<span class="c-primary"><b>配送</b></span>',
            2 => '<span class="c-warning"><b>自提</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 调拨单凭证状态
 * @author xiaowen
 * @param int $status
 * @param bool $show
 * @time 2017-9-27
 * @return array
 */
function AllocationVoucherStatus($status = 0, $show = false)
{

    $data = [

        1 => '已上传',
        2 => '未上传',

    ];
    if($show){
        $data = [
            1=>"<span class=\"c-success\"><b>已上传</b></span>",
            2=>"<span class=\"c-error\"><b>未上传</b></span>",
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 调拨出库状态
 * @param int $status
 * @param bool|false $show
 * @return array
 */
function AllocationOutboundStatus($status = 0, $show = false){
    $data = [

        1 => '已出库',
        2 => '未出库',
        3 => '部分出库',

    ];
    if($show){
        $data = [
            1=>"<span class=\"c-success\"><b>已出库</b></span>",
            2=>"<span class=\"c-error\"><b>未出库</b></span>",
            3=>"<span class=\"c-default\"><b>部分出库</b></span>",
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 调拨入库状态
 * @param int $status
 * @param bool|false $show
 * @return array
 */
function AllocationStorageStatus($status = 0, $show = false){
    $data = [

        1 => '已入库',
        2 => '未入库',
        3 => '部分入库',

    ];
    if($show){
        $data = [
            1=>"<span class=\"c-success\"><b>已入库</b></span>",
            2=>"<span class=\"c-error\"><b>未入库</b></span>",
            3=>"<span class=\"c-default\"><b>部分入库</b></span>",
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 预存/预付单状态
 * @param int $status
 * @param  $show
 * @return array
 */
function RechargeOrderStatus($status = 0, $show = false)
{
    $data = [

        1 => '未审核',
        2 => '已取消',
        3 => '已审核',
        4 => '已复核',
        10 => '已确认',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            3 => '<span class="c-warning"><b>已审核</b></span>',
            4 => '<span class="c-danger"><b>已复核</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 预存/预付财务处理状态
 * @param int $status
 * @param  $show
 * @return array
 */
function RechargeFinanceStatus($status = 0, $show = false)
{
    $data = [

        1 => '未处理',
        10 => '已处理',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-default"><b>未处理</b></span>',
            10 => '<span class="c-success"><b>已处理</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 预存类型
 * @param int $status
 * @param  $show
 * @return array
 */
function PrestoreType($status = 0)
{
    $data = [
        11 => '预存款',
        12 => '多打款',
        13 => '小十代预存',
        14 => 'IDS预存',
    ];

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 预付类型
 * @param int $status
 * @param  $show
 * @return array
 */
function PrepayType($status = 0)
{
    $data = [
        21 => '预付款',
        22 => '加油站预付',
        23 => '小十代预付',
        24 => 'IDS预付',
    ];

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 账户类型
 * @param int $status
 * @param  $show
 * @return array
 */
function AccountType($status = 0)
{
    $data = [

        1 => '预存',
        2 => '预付',

    ];

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * Kpi岗位基础资料是否转正
 * @param int $status
 * @return array
 */
function kpiRegularStatus($status = 0){
    $is_regular = [
        1 => '是',
        2 => '否'
    ];
    if($status){
        return $is_regular[$status];
    }
    return $is_regular;
}

/**
 * 返回真实数值
 * @param $num
 * @return float|int
 */
function getRealNum($num){
    return $num ? getNum($num) : 0;
}

/**
 * 返回盘点方案类型
 * @param $status
 * @return float|int
 */
function getInventoryPlanType($status = 0){
    $data = [
        1  => '城市仓盘点',
        4  => '二级仓盘点',
        5  => '零售仓盘点',
        6  => '实体仓盘点',
    ];
    if($status){
        return $data[$status];
    }
    return $data;
}

/**
 * 盘点方案状态
 * @param int $status
 * @param  $show
 * @return array
 */
function getInventoryStatus($status = 0 ,$show = false)
{
    $data = [
        1  => '未审核',
        2  => '已取消',
        10 => '已确认'
    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 盘点类型
 * @param int $status
 * @return array $arr
 */
function inventoryOrderType($status = 0){
    $arr = [
        '1' => '实物盘点',
        '2' => '库存调整',
       // '3' => '期初库存',
    ];

    if($status){
        return $arr[$status];
    }
    return $arr;
}
/**
 * 盘点单状态
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function inventoryOrderStatus($status = 0, $show = false){
    $arr = [
        '1' => '未审核',
        '2' => '已取消',
        '10' => '已确认',

    ];
    if($show){
        $arr = [
            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',
        ];
    }
    if($status){
        return $arr[$status];
    }
    return $arr;
}

function isStatus($status){
    $is_regular = [
        1 => '是',
        2 => '否'
    ];
    if($status){
        return $is_regular[$status];
    }
}

/**
 * 盘点单开启状态
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function isUse($status,$show = 0){
    $data = [
        1 => '开启',
        2 => '关闭'
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>开启</b></span>',
            2 => '<span class="c-warning"><b>关闭</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 零售销售单 - 是否完全出库
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function isAllOutbound($status,$show = 0){
    $data = [
        0 => '否',
        1 => '是'
    ];
    if ($show) {
        $data = [
            0 => '<span class="c-success"><b>否</b></span>',
            1 => '<span class="c-warning"><b>是</b></span>',
        ];
    }
    return $data[$status];
}

/**
 * 零售销售单、出库单 - 是否被红冲
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function isReverse($status,$show = 0){
    $data = [
        1 => '是',
        2 => '否'
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>是</b></span>',
            2 => '<span class="c-warning"><b>否</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 配送单来源单据类型
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function SourceType($status = 0){
    $data = [
        1 => '销售单',
        2 => '调拨单',
        3 => '采购单'
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 配送单配送类型
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function ShippingType($status = 0){
    $data = [
        1 => '配送',
        2 => '提货'
    ];
    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 配送单状态
 * @param int $status
 * @param  $show
 * @return array
 */
function ShippingOrderStatus($status = 0, $show = false)
{
    $data = [

        1 => '未审核',
        2 => '已取消',
        3 => '已审核',
//        4 => '已复核',
        10 => '已确认',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未审核</b></span>',
            2 => '<span class="c-default"><b>已取消</b></span>',
            3 => '<span class="c-warning"><b>已审核</b></span>',
//            4 => '<span class="c-danger"><b>已复核</b></span>',
            10 => '<span class="c-success"><b>已确认</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 配送单配送状态
 * @param int $status
 * @param  $show
 * @return array
 */
function DistributionStatus($status = 0, $show = false)
{
    $data = [

        1 => '未配送',
        2 => '配送中',
        3 => '配送完成',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未配送</b></span>',
            2 => '<span class="c-warning"><b>配送中</b></span>',
            3 => '<span class="c-success"><b>配送完成</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}

/**
 * 配送单凭证上传状态
 * @param int $status
 * @param  $show
 * @return array
 */
function VoucherStatus($status = 0, $show = false)
{
    $data = [

        1 => '未上传',
        2 => '部分上传',
        10 => '已上传',

    ];
    if ($show) {
        $data = [

            1 => '<span class="c-primary"><b>未上传</b></span>',
            2 => '<span class="c-warning"><b>部分上传</b></span>',
            10 => '<span class="c-success"><b>已上传</b></span>',

        ];
    }

    if ($status) {
        return $data[$status];
    }
    return $data;
}
/**
 * 销售类型
 * @param int $status
 * @param bool $show
 * @return array
 */
function saleAgentType($status = 0, $show = false)
{
    $data = [
        1 => '是',
        2 => '否'
    ];
    if($show){
        $data = [
            1 => '<span class="c-success"><b>是</b></span>',
            2 => '<span class="c-warning"><b>否</b></span>',
        ];
    }
    if ($status > 0) {
        return $data[$status];
    }
    return $data;
}

/**
 * 系统配置开启状态
 * @param int $status
 * @param bool $show
 * @return array $arr
 */
function configStatus($status,$show = 0){
    $data = [
        1 => '开启',
        2 => '关闭'
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>开启</b></span>',
            2 => '<span class="c-warning"><b>关闭</b></span>',
        ];
    }
    if ($status) {
        return $data[$status];
    }
    return $data;
}


/**
 * 采购单业务类型
 * @param int $type
 * @return array $arr
 */
function getBusinessType($type = 0){
    $arr = [
        1 => '属地',
        2 => '大宗',
        3 => '地炼',
        4 => '加油站',
        5 => '小十代',
        6 => '零售内部交易',
        7 => 'IDS',
    ];
    if($type) {
        return $arr[$type];
    }
    return $arr;
}

/**
 * 调拨类型城市仓- 服务商，服务商-城市仓：
 * 增加两种调拨场景， 加油站调拨、零售调拨
 * @param int $type
 * @return array $arr
 */
function getAllocationOrderBusinessType($type = 0){
    $arr = [
        1 => '加油站调拨',
        2 => '零售调拨',
        3 => 'IDS',
        4 => '属地调拨',
    ];
    if($type) {
        return $arr[$type];
    }
    return $arr;
}
/**
 * 销售单业务类型
 * @param int $type
 * @return array $arr
 */
function getSaleOrderBusinessType($type = 0){
    $arr = [
        1 => '属地',
        2 => '大宗',
        3 => '小十代',
        4 => '零售内部交易',
        5 => 'IDS',
    ];
    if($type) {
        return $arr[$type];
    }
    return $arr;
}
/**
 * 审批职位配置函数
 *
 *
 * @return array $arr [
 * 'name'=> '职位名称',
 * 'area'=> '是否区分地区',
 * ]
 */
function workflowPositionConfig(){
    $arr = [
        'AreaPurchaseSaleManager_'      => ['name'=>'地区采销负责人','area'=>1],
        'AreaManager_'                  => ['name'=>'地区分公司经理','area'=>1],
//        'AreaPrePayManager_'    => ['name'=>'地区预付负责人','area'=>1],
        'AreaPreStoreManager_'          => ['name'=>'地区预存负责人','area'=>1],
        'GylPurchaseManager_'           => ['name'=>'供应链事业部采购负责人','area'=>0],
        'GylManager_'                   => ['name'=>'供应链事业部负责人','area'=>0],
        //'RetailPurchaseSeniorManager_'   => ['name'=>'零售采购高级主管','area'=>0], // 2018.10.09 采购审批流职位、文案修改 qianbin
        'RetailPurchaseSeniorManager_'  => ['name'=>'运营数据主管','area'=>0],
        'MarketingCenterManager_'       => ['name'=>'营销中心','area'=>0],
        'OperationalDataManager_'       => ['name'=>'运营数据负责人','area'=>0],
        'LabECOGeneralManager_'         => ['name'=>'小十代总经理','area'=>0],
        'LabECOMarketingManager_'       => ['name'=>'小十代市场经理','area'=>0],

        //IDS审批人职位 awen 2019-5-8
        'IdsPurchaseManager_'           => ['name'=>'IDS采供组负责人','area'=>0],
        'IdsPurchaseAndSaleManager_'    => ['name'=>'IDS采供销部负责人','area'=>0],
        'IdsProjectManager_'            => ['name'=>'IDS项目中心负责人','area'=>0],
        'IdsSaleManager_'           => ['name'=>'IDS外销组负责人','area'=>0],
        'IdsAllocationManager_'       => ['name'=>'IDS调拨负责人','area'=>0],
        'IdsPrePayManager_'       => ['name'=>'IDS预付负责人','area'=>0],
        'IdsPreStoreManager_'       => ['name'=>'IDS预存负责人','area'=>0],
    ];
    return $arr;
}

/**
 * 属地采销审批流
 * 地区采销负责人 => 地区分公司经理 => 营销中心
 * @param $type 1 : 2级审批  2： 3级审批
 * @return array $step
 */
function dependencyPurchaseSaleWorkflow($type = 1){
    if($type == 1){
        $step = [
            'AreaPurchaseSaleManager_', 'AreaManager_',
        ];
    }else{
        $step = [
            'AreaPurchaseSaleManager_', 'AreaManager_','MarketingCenterManager_',
        ];
    }
    return $step;
}

/**
 * 小十代采销审批流
 * 小十代总经理 => 小十代市场经理
 */
function LabECOPurchaseSaleWorkflow($type = 1){
    $step = [
        1 =>  ['LabECOGeneralManager_', 'LabECOMarketingManager_'],
    ];
    return $step[$type];
}

/**
 * 供应链采购审批流
 * 地区采销负责人 => 地区分公司经理 => 营销中心
 * @param $type 1 大宗地炼 2 加油站
 *
 * 大宗地炼： 供应链事业部采购负责人 => 供应链事业部负责人
 * 加油站： 运营数据主管 => 运营数据负责人
 */
function gylPurchaseWorkflow($type = 1){
    $step = [
       1 =>  ['GylPurchaseManager_', 'GylManager_'],
       2 =>  ['RetailPurchaseSeniorManager_', 'OperationalDataManager_'],
    ];
    return $step[$type];
}

/**
 * 供应链采购审批流
 * 地区采销负责人 => 地区分公司经理 => 营销中心
 * @param $type 1 大宗
 *
 * 大宗地炼： 供应链事业部采购负责人 => 供应链事业部负责人
 *
 */
function gylSaleWorkflow($type = 1){
    $step = [
        1 =>  ['GylPurchaseManager_', 'GylManager_'],
        //2 =>  ['RetailPurchaseSeniorManager_', 'GylPurchaseManager_'],
    ];
    return $step[$type];
}


/**
 * 预付审批流
 * 预付款类型：供应链事业部采购负责人
 * 加油站预付：运营数据主管 => 运营数据负责人
 * @return array $step
 */
function gylPrepayWorkflow($type = 21){
    $step = [
        21 =>  ['GylPurchaseManager_'],
        22 =>  ['RetailPurchaseSeniorManager_', 'OperationalDataManager_'],
    ];
    return $step[$type];
}

/**
 * 预存审批流
 * 地区预存负责人 => 营销中心
 * 地区预存负责人 => 地区分公司经理 //edit xiaowen 2019-1-7 预存审批流调整
 */
function marketingPreStoreWorkflow(){
    $step = [
        'AreaPreStoreManager_',
        'AreaManager_'
        //'MarketingCenterManager_'
    ];
    return $step;
}

/**
 * 
 * ids审批流设置
 * @author xiaowen
 * @time 2019-5-8
 * @param int $order_type 单据类型： 1 采购 2 采退 3 销售 4 销退 5 调拨 6 预存 7 预付
 * @return array
 * desc:
 * 业务单据	业务类型	一审职位	二审职位	三审职位
 *采购单	IDS	IDS采供组负责人	IDS采供销部负责人	IDS项目中心负责人
 *采购退货单 IDS	IDS采供组负责人	IDS采供销部负责人	
 *销售单	IDS	IDS外销组负责人	IDS采供销部负责人	
 *销退单	IDS	IDS外销组负责人	IDS采供销部负责人	
 *调拨单	IDS	IDS调拨负责人		
 *预存单	IDS	IDS预存负责人		
 *预付单	IDS	IDS预付负责人
 * 
 */
function IdsWorkflow($order_type = 0){
    
    $arr = [
        1 => ['IdsPurchaseManager_', 'IdsPurchaseAndSaleManager_', 'IdsProjectManager_'],
        2 => ['IdsPurchaseManager_', 'IdsPurchaseAndSaleManager_'],
        3 => ['IdsSaleManager_', 'IdsPurchaseAndSaleManager_'],
        4 => ['IdsSaleManager_', 'IdsPurchaseAndSaleManager_'],
        5 => ['IdsAllocationManager_'],
        6 => ['IdsPreStoreManager_'],
        7 => ['IdsPrePayManager_'],
    ];

    if($order_type){
        return $arr[$order_type];
    }
    return [];
}

/**
 * 银行信息收、付款类型
 * @param int $type
 * @return array $arr
 */
function getBankPayType($type = 0){
    $data = [
        1 => '收款',
        2 => '付款',
    ];
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 银行信息业务类型
 * @param int $type
 * @return array $data
 */
function getBankBusinessType($type = 0){
    $data = [
        1 => '批发',
        2 => '零售',
        3 => '批零',
    ];
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 银行信息状态
 * @param int $type
 * @return array $data
 */
function getBankStatus($type = 0,$show = 0){
    $data = [
        1 => '启用',
        2 => '禁用',
    ];
    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>启用</b></span>',
            2 => '<span class="c-warning"><b>禁用</b></span>',
        ];
    }
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 银行信息是否为首选
 * @param int $type
 * @return array $data
 */
function getBankIsFirst($type = 0){
    $data = [
        1 => '是',
        2 => '否',
    ];
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 出入库明细类型
 * @param int $type
 * @return array $data
 */
function stockDetailType($type = 0){
    $data = [
        1 => '出库',
        2 => '入库',
    ];
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 供应商状态
 * @param int $type
 * @param  bool $show
 * @return array $data
 */
function supplierStatus($type = 0, $show = false)
{
    $data = [
        1 => '有效',
        2 => '无效',
    ];

    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>有效</b></span>',
            2 => '<span class="c-warning"><b>无效</b></span>',
        ];
    }
    if($type) {
        return $data[$type];
    }
    return $data;
}
/**
 * 获取销售发票匹配的商品税号
 * @param int $type
 * @return array $data
 */
function getSaleInvoiceGoodsNumber($type = ''){
    $data = [
            '汽油'              => '1070101010000000000',
            '航空汽油'          => '1070101010100000000',
            '车用汽油'          => '1070101010200000000',
            '其他汽油'          => '1070101019900000000',
            '煤油'              => '1070101020000000000',
            '航空煤油'           => '1070101020100000000',
            '其他煤油'           => '1070101020200000000',
            '柴油'               => '1070101030000000000',
            '燃料油'             => '1070101040000000000',
            '润滑油'             => '1070101070000000000',
            '液化石油气'         => '1070101110100000000',
            '液化天然气（LNG）'  => '1100202030000000000',
            '民用液化石油气'     => '1100202040000000000',
    ];

    if($type) {
        return $data[$type];
    }
    return $data;
}
/**
 * 供应商审核状态
 * @param int $type
 * @param  bool $show
 * @return array $data
 */
function supplierAuditStatus($type = 0, $show = false){
    $data = [
        1 => '已审核',
        2 => '未审核',
        //3 => '审核未通过',
    ];

    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>已审核</b></span>',
            2 => '<span class="c-primary"><b>未审核</b></span>',
            //3 => '<span class="c-warning"><b>审核未通过</b></span>',
        ];
    }
    if($type) {
        return $data[$type];
    }
    return $data;
}
/*
 * 配送单承运方
 * @param int $type
 * @return array $data
 */
function getShipperList($type = 0)
{
    $data = [
        1 => '找罐车',
        2 => '自营运输',
    ];
    if ($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 供应商是否团内
 * @param int $type
 * @param  bool $show
 * @return array $data
 */
function supplierInner($type = 0, $show = false){
    $data = [
        1 => '是',
        2 => '否',
    ];

    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>是</b></span>',
            2 => '<span class="c-primary"><b>否</b></span>',
        ];
    }
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 供应商业务属性
 * @param int $type
 * @param  bool $show
 * @return array $data
 */
function supplierBusinessAttributes($type = 0, $show = false){
    $data = [
        1 => '批发',
        2 => '零售',
    ];

    if ($show) {
        $data = [
            1 => '<span class="c-success"><b>批发</b></span>',
            2 => '<span class="c-primary"><b>零售</b></span>',
        ];
    }
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 供应商等级
 * @param int $type
 * @param  bool $show
 * @return array $data
 */
function supplierLevel($type = 0, $show = false){
    $data = [
        'F' => 'F',
        'B1' => 'B1',
        'B1+' => 'B1+',
        'B2-' => 'B2-',
        'B2' => 'B2',
        'C' => 'C',
    ];

    if ($show) {
        $data = [
            'F' => '<span class="c-success"><b>F</b></span>',
            'B1' => '<span class="c-primary"><b>B1</b></span>',
            'B1+' => '<span class="c-primary"><b>B1+</b></span>',
            'B2-' => '<span class="c-primary"><b>B2-</b></span>',
            'B2' => '<span class="c-primary"><b>B2</b></span>',
            'C' => '<span class="c-primary"><b>C</b></span>',
        ];
    }
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 供应商证件类型
 * @param int $type
 * @return array|mixed
 */
function supplierVoucher($type = 0){
    $data = [
        '1' => '三证合一',
        '2' => '开票资料',
        '3' => '开户许可证',
        '4' => '成品油经营许可证',
        '5' => '危化证',
        '6'=> '银行账号'
    ];
    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 银行列表
 * @param int $type
 * @param  bool $show
 * @return array $data
 */
function bankDataList($type = 0, $show = false){
    $data = [
        '中国银行' => '中国银行',
        '中国农业银行' => '中国农业银行',
        '中国工商银行' => '中国工商银行',
        '中国建设银行' => '中国建设银行',
        '交通银行' => '交通银行',
        '上海银行' => '上海银行',
        '浦发银行' => '浦发银行',
        '上海农商银行' => '上海农商银行',
        '华夏银行' => '华夏银行',
    ];

    if($type) {
        return $data[$type];
    }
    return $data;
}

/**
 * 客户证件类型
 * @param int $type
 * @return array|mixed
 */
function customerVoucher($type = 0){
    $data = [
        '1' => '三证合一',
        '2' => '开票资料',
    ];
    if($type) {
        return $data[$type];
    }
    return $data;
}
function storehouseSource($type){
    $arr = [
        0 => 'ERP',
        1 => 'OMS',
        2 => 'front',
    ];
    if($type){
        return $arr[$type];
    }
    return $arr;
}


/*
 * 供应商的来源
 */
function dataSourceName($type){
    $data = [
        "1"=>"front公司表",
        "2"=>"front服务商",
        "3"=>"OMS",
        "99"=>"ERP录入",
    ];
    if($type){
        $data = isset($data[$type]) ? $data[$type] : "暂不明确" ;
    }
    return $data ;
}

/********************************
    是否是内部交易单 首选银行账号
*********************************/
function isDefaultBank( $type )
{
    (array)$data = [
        '1' => '是',
        '2' => '否',
    ];
    (string)$str = isset($data[$type]) ? $data[$type] : '暂不明确';
    return $str;
}

/********************************
    是否是内部交易单状态
*********************************/
function internalOrderStatus( $type )
{
    (array)$data = [
        '1' => '未审核',
        '2' => '已取消',
        '10'=> '已审核'
    ];
    (string)$str = isset($data[$type]) ? $data[$type] : '暂不明确';
    return $str;
}
/*********************************
    @ Content 内部交易单 发送短信
    @ Param $[type] [int] 1 => 技术人员 2 => 财务人员 3 => 全部
**********************************/
function mailSender( $type = 1 )
{
    $data = [
        '1' => [
            'shanyufei@51zhaoyou.com',
            'haoguanyu@51zhaoyou.com',
            'xiaowen@51zhaoyou.com',
            'xutianjie@51zhaoyou.com',
            'yuguangwei@51zhaoyou.com',
            'jiangyunhui@51zhaoyou.com',
        ],
        '2' => [
            'maosutian@51zhaoyou.com',
            'peiyali@51zhaoyou.com',
            // 'jiangyunhui@51zhaoyou.com',
        ]
    ];
    if ( $type == 3 ) {
        return implode(',',$data[1]).','.implode(',',$data[2]);
    }
    return implode(',',$data[$type]);
}

/**===================================================
 *
 * 提示信息相关函数
 *
 * =====================================================
 */


/*
 * ------------------------------------------
 * ajax返回提示消息
 * Author：jk        Time：2016-10-26
 * ------------------------------------------
 */
function ajaxGetMessage($str = '')
{
    $message = [
        'a' => '网络异常，请刷新重试！',
        'b' => '手机号码格式错误！',
        'c' => '收款金额格式错误！',
        'd' => '请选择收款来源！',
        'e' => '请选择收款日期！',
        'f' => '收款日期不能大于当前日期！',
        'g' => '该手机用户不存在！',
        'h' => '用户公司异常，请联系管理员！',
        'i' => '恭喜您，记账成功！',
        'j' => '对不起，记账失败，请重新尝试！',
        'k' => '恭喜您，修改成功！',
        'l' => '对不起，修改失败，请重新尝试！',
        'm' => '恭喜您，复核成功！',
        'n' => '对不起，复核失败，请重新尝试！',
        'o' => '恭喜您，提交复核成功！',
        'p' => '对不起，提交复核失败，请重新尝试！'
    ];

    return $message[trim($str)];
}


// +----------------------------------
// |添加服务返回提示消息
// +----------------------------------
// |Author:lizhipeng Time:2016.11.1
// +----------------------------------
function ajaxFacilitatorMessage($str = '')
{
    $message = [
        'a' => '服务商名称不能为空！',
        'b' => '手机号码不能为空！',
        'c' => '所在地区不能为空！',
        'd' => '负责人不能为空！',
        'e' => '负责人姓名不能为空！'
    ];
    return $message[trim($str)];
}

// +----------------------------------
// |添加员工返回提示消息
// +----------------------------------
// |Author:lizhipeng Time:2016.11.1
// +----------------------------------
function ajaxFacilitatorUserMessage($str = '')
{
    $message = [
        'a' => '姓名不能为空！',
        'b' => '手机号码不能为空！',
        'c' => '地区不能为空！',
        'd' => '加油网点无效！'
    ];
    return $message[trim($str)];
}

// +----------------------------------
// |添加撬装站返回提示消息
// +----------------------------------
// |Author:senpai Time:2017.3.3
// +----------------------------------
function ajaxFacilitatorSkidMessage($str = '')
{
    $message = [
        'a' => '加油网点名称不能为空！',
        'b' => '加油网点地址不能为空！',
        'c' => '省级不能为空！',
        'd' => '市级不能为空！',
        'e' => '请检查上级服务商',
    ];
    return $message[trim($str)];
}


// +----------------------------------
// |编辑员工返回提示消息
// +----------------------------------
// |Author:lizhipeng Time:2016.11.1
// +----------------------------------
function ajaxFacilitatorUpdateMessage($str = '')
{
    $message = [
        'a' => '手机号码不能为空！'
    ];
    return $message[trim($str)];
}

// +----------------------------------
// |编辑erp商品返回信息
// +----------------------------------
// |Author:senpai Time:2017.3.10
// +----------------------------------
function ajaxErpGoodsMessage($str = '')
{
    $message = [
        'a' => '商品名称不能为空！',
        'b' => '商品来源不能为空！',
        'c' => '商品级别不能为空！',
        'd' => '商品标注不能为空！',
        'e' => '油品密度不能为空！',
        'f' => '油品密度超出规格！'
    ];
    return $message[trim($str)];
}

// +----------------------------------
// |编辑erp商品返回信息
// +----------------------------------
// |Author:senpai Time:2017.3.10
// +----------------------------------
function ajaxSupplyMessage($str = '')
{
    $message = [
        'a' => '供货人不能为空！',
        'b' => '供货商公司不能为空！',
        'c' => '交易员不能为空！',
        'd' => '交易员不能为空！',
        'e' => '商品不能为空！',
        'f' => '商品状态不能为空！',
        'g' => '城市不能为空！',
        'h' => '油库不能为空！',
        'i' => '单价不能为空！',
        'j' => '可售数量不能为空！',
        'k' => '起售数量不能为空！',
        'l' => '单笔最大数量不能为空！',
        'm' => '提货方式不能为空！',
        'n' => '开票类型不能为空！',
        'o' => '是否服务不能为空！',
        'p' => '是否商城商品不能为空！',
        'q' => '是否显示在前台不能为空！',
        'r' => '是否后台购买不能为空！',
        's' => '是否推荐不能为空！',
        't' => '是否免运费不能为空！',
        'u' => '是否发送短信不能为空！',
        'v' => '汇款信息不能为空！',
        'w' => '该用户不在您的管理内！',
        'x' => '该供货单已审核，不能修改！',
        'y' => '请先维护区域商品价格，且价格不能小于等于0！',
        'z' => "请选择仓库信息"
    ];
    return $message[trim($str)];
}

/**
 * 配置类型
 * @param string $type
 * @return array|mixed
 */
function configTypeArr($type = ''){
    $arr = [
        1=>'系统配置',
        2=>'批次类型',
        3=>'采购合同',
    ];
    if($type){
        return $arr[$type];
    }
    return $arr;
}
// +----------------------------------
// |编辑erp仓库返回信息
// +----------------------------------
// |Author:senpai Time:2017.3.24
// +----------------------------------
function ajaxErpStorehouseMessage($str = '')
{
    $message = [
        'a' => '仓库名称不能为空！',
        'b' => '仓库类型不能为空！',
        'c' => '城市不能为空！',
        'd' => '仓库地址不能为空！',
        'e' => '仓库电话不能为空！',
        'f' => '仓库状态不能为空！'
    ];
    return $message[trim($str)];
}

/**
 * 返回批次的状态
 * @param $balance_num 批次可用数量
 * @param $reserve_num 批次预留数量
 * @param $total_num 批次总数
 * @return int
 */
function getBatchStatus($balance_num, $reserve_num,$total_num){
    $actual_balance_num =  $balance_num - $reserve_num;
    log_info("可用：" . $balance_num . ", 预留：" . $reserve_num . ", 实际：" . $actual_balance_num);

    if($actual_balance_num == 0 && $balance_num == 0){
        return 3;
    }else if($actual_balance_num == 0 && $balance_num != 0){
        return 2;
    } else {
        return $total_num - $actual_balance_num != 0 ? 2 : 1;
    }
}

/*******************************
@ Content 货权批次状态
 *********************************/
function erpBatchStatus( $type = '')
{
    $arr = [
        '1' => '已创建',
        '2' => '使用中',
        '3' => '已完成',
    ];
    if($type){
        return $arr[$type];
    }
    return $arr;
}
/*******************************
@ Content 货权批次状态
 *********************************/
function erpStockOutApplyStatus( $type = '')
{
    $arr = [
        '1' => '已创建',
        '2' => '已取消',
        '10' => '已完成',
    ];
    if($type){
        return $arr[$type];
    }
    return $arr;
    //return isset($arr[trim($type)]) ? $arr[trim($type)] : '未明确';
}

/*******************************
    @ Content 入库申请单状态
 *********************************/
function stockInApplyListStatus( $type = 0 )
{
    $arr = [
        '1' => '已创建',
        '2' => '已取消',
        '10' => '已完成',
    ];
    if($type){
        return $arr[$type];
    }
    return $arr;
}
/*
 * 损耗比例
 */
function lossRatio($type=""){
    $arr = [
        "0"=>"请选择",
        "1.5" => "1.5‰",
        "2"=>"2‰",
        "3"=>"3‰",
        "4"=>"4‰"
    ];
    if(!empty($arr[$type])){
        return $arr[$type] ;
    }else{
        return $arr ;
    }
}

/*
 * 损耗比例（计算用）
 */
function lossRatioMath($type=""){
    $arr = [
        "0"=>"请选择",
        "1.5"=>"0.0015",
        "2"=>"0.002",
        "3"=>"0.003",
        "4"=>"0.004"
    ];
    if(!empty($arr[$type])){
        return $arr[$type] ;
    }else{
        return $arr ;
    }
}

/********************************
    @ Content 损耗单状态
**********************************/
function lossOrderStatus( $type = 0 ,$show = false)
{
    $arr = [
        '1'  => '未审核',
        '2'  => '已取消',
        '3'  => '已审核',
        '10' => '已确认'
    ];
    if ( $show ) {
        $arr = [
            '1'  => '<span class="c-primary"><b>未审核</b></span>',
            '2'  => '<span class="c-default"><b>已取消</b></span>',
            '3'  => '<span class="c-warning"><b>已审核</b></span>',
            '10' => '<span class="c-success"><b>已确认</b></span>',
        ];
    }
    if ( $type ) {
        return $arr[$type];
    }
    return $arr;
}

/********************************
    @ Content 损耗单合理损耗状态
**********************************/
function lossReasonableStatus( $type = 0 ,$show = false)
{
    $arr = [
        '1'  => '未处理',
        '2'  => '无损耗',
        '10'  => '已处理',
    ];
    if ( $show ) {
        $arr = [
            '1'  => '<span class="c-primary"><b>未处理</b></span>',
            '2'  => '<span class="c-default"><b>无损耗</b></span>',
            '10'  => '<span class="c-success"><b>已处理</b></span>',
        ];
    }
    if ( $type ) {
        return $arr[$type];
    }
    return $arr;
}

/********************************
    @ Content 损耗单超损耗状态
**********************************/
function lossExceedStatus( $type = 0 ,$show = false)
{
    $arr = [
        '1'  => '未处理',
        '2'  => '无损耗',
        '3'  => '转运费',
        '10'  => '已处理',
    ];
    if ( $show ) {
        $arr = [
            '1'  => '<span class="c-primary"><b>未处理</b></span>',
            '2'  => '<span class="c-default"><b>无损耗</b></span>',
            '3'  => '<span class="c-warning"><b>转运费</b></span>',
            '10' => '<span class="c-success"><b>已处理</b></span>',
        ];
    }
    if ( $type ) {
        return $arr[$type];
    }
    return $arr;
}

/********************************
    @ Content 损耗单超损承担方
**********************************/
function lossResponsiblePartyStatus( $type = '' )
{
    $arr = [
        '1'  => '我方承担',
        '2'  => '他方承担',
    ];
    if ( $type === (int)0 ) {
        return "未明确";
    }
    if ( $type ) {
        return $arr[$type];
    }
    return $arr;
}

/********************************
    @ Content 损耗单超损类型
**********************************/
function lossTypeStatus( $type = 0, $show = false)
{
    $arr = [
        '1'  => '采购',
        '2'  => '调拨',
        '3'  => '销售',
    ];
    if ( $show ) {
        $arr = [
            '1'  => '<span class="c-primary"><b>采购</b></span>',
            '2'  => '<span class="c-warning"><b>调拨</b></span>',
            '3'  => '<span class="c-success"><b>销售</b></span>',
        ];
    }
    if ( $type ) {
        return $arr[$type];
    }
    return $arr;
}

/********************************
    @ Content 运费单状态
**********************************/
function freightOrderStatus( $type = 0 ,$show = false){
    $arr = [
        '1'  => '未审核',
        '2'  => '已取消',
        '3'  => '已审核',
        '10' => '已确认'
    ];
    if ( $show ) {
        $arr = [
            '1'  => '<span class="c-primary"><b>未审核</b></span>',
            '2'  => '<span class="c-default"><b>已取消</b></span>',
            '3'  => '<span class="c-warning"><b>已审核</b></span>',
            '10' => '<span class="c-success"><b>已确认</b></span>',
        ];
    }
    if ( $type ) {
        return $arr[$type];
    }
    return $arr;
}






