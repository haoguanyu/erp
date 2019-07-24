<?php
/**
 * author: ypm
 * Date: 2016/12/07
 */
namespace Common\Model;

use Think\Model;

class TogafModel extends Model
{
    /*
     * ----------------------------------------
     * 自动验证规则
     * Author：ypm        Time：2016-10-13
     * ----------------------------------------
     */
    protected $_validate = array(

        array('company_id', 'require', '公司必须！'),
        array('company_id', 'checkCompanyID', '公司不存在！', self::EXISTS_VALIDATE, 'callback'),

        array('department_code', 'require', '部门必须！'),
        array('department_code', 'checkDepartmentID', '部门不存在！', self::EXISTS_VALIDATE, 'callback'),

        array('duties_id', 'require', '职位必须！'),
        array('duties_id', 'checkDutiesID', '职位不存在！', self::EXISTS_VALIDATE, 'callback'),

        array('company_id,duties_id,department_code', 'checkTOGAF', '关系已经存在！', self::MUST_VALIDATE, 'callback'),

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

    function checkCompanyID($company_id)
    {
        $company = D('togaf_company')->find($company_id);
        if ($company)
            return true;
        else
            return false;
    }

    function checkDepartmentID($department_code)
    {
        $department = D('togaf_department')->find($department_code);
        if ($department)
            return true;
        else
            return false;
    }

    function checkDutiesID($duties_id)
    {
        $duties = D('togaf_duties')->find($duties_id);
        if ($duties)
            return true;
        else
            return false;
    }

    function checkTOGAF($data)
    {
        $map = $data;
        $togaf = D('togaf')->where(['company_id' => $map['company_id'], 'duties_id' => $map['duties_id'], 'department_code' => $map['department_code']])->find();
        if ($togaf)
            return false;
        else
            return true;
    }

}