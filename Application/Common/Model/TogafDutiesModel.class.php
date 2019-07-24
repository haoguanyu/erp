<?php
/**
 * author: ypm
 * Date: 2016/12/07
 */
namespace Common\Model;

use Think\Model;

class TogafDutiesModel extends Model
{
    /*
     * ----------------------------------------
     * 自动验证规则
     * Author：ypm        Time：2016-10-13
     * ----------------------------------------
     */
    protected $_validate = array(

        array('name', 'require', '职位名称必须！'),
        array('name', '', '该职位名称已经存在，不要重复', 1, 'unique'),
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