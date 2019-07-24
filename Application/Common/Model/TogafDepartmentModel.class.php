<?php
/**
 * author: ypm
 * Date: 2016/12/07
 */
namespace Common\Model;

use Think\Model;

class TogafDepartmentModel extends Model
{
    /*
     * ----------------------------------------
     * 自动验证规则
     * Author：ypm        Time：2016-10-13
     * ----------------------------------------
     */
    protected $_validate = array(

        array('name', 'require', '部门名称必须！'),
        array('name', '', '该部门名称已经存在，不要重复', 1, 'unique'),

        array('phone', 'require', '部门电话必须！'),

        array('level,parent_id', 'checkParentID', '上级部门必选！', self::MUST_VALIDATE, 'callback'),

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

    function checkParentID($data)
    {
        $map = $data;
        if ($data['level'] == 2) {
            if (empty($data['parent_id']))
                return false;
            else
                return true;
        }
        return true;
    }
}