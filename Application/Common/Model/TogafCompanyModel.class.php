<?php
/**
 * author: ypm
 * Date: 2016/12/07
 */
namespace Common\Model;

use Think\Model;

class TogafCompanyModel extends Model
{
    /*
     * ----------------------------------------
     * 自动验证规则
     * Author：ypm        Time：2016-10-13
     * ----------------------------------------
     */
    protected $_validate = array(
        array('address', 'require', '公司地址必须！'),

        array('name', 'require', '公司名称必须！'),
        array('name', '', '该公司名称已经存在，不要重复', 1, 'unique'),

        array('phone', 'require', '公司电话必须！'),

        array('area_code', 'require', '地区号必须！'),
        //array('area_code','checkRegion','收货地区暂时只开放上海与江苏！', self::EXISTS_VALIDATE, 'callback'),
    );

    /*
     * ----------------------------------------
     * 自动完成规则
     * Author：ypm        Time：2016-10-13
     * ----------------------------------------
     */
    protected $_auto = array(
        array('create_time', 'nowTime', self::MODEL_INSERT, 'function'),
    );

}