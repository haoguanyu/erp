<?php
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class UserEvent extends BaseController
{

    public function _initialize()
    {
        $this->arr = [];
    }

    /*
     * -------------------------------------
     * 短信发送列表
     * Author:jk    Time:2016-11-16
     * -------------------------------------
     */
    public function smsList($param = [])
    {
        $where = $this->arr;
        if (isset($param['mobile']) && !empty(strHtml($param['mobile']))) {
            $where['mobile'] = ['EQ', strHtml($param['mobile'])];
        }
        if (isset($param['send_status_val']) && !empty(strHtml($param['send_status_val']))) {
            switch (strHtml($param['send_status_val'])) {
                case '提交成功':
                    $where['send_status_val'] = ['EQ', strHtml($param['send_status_val'])];
                    break;
                case '其它':
                    $where['send_status_val'] = ['NEQ', '提交成功'];
                    break;
            }
        }
        if (isset($param['return_status_val']) && !empty(strHtml($param['return_status_val']))) {
            switch (strHtml($param['return_status_val'])) {
                case '短消息转发成功':
                    $where['return_status_val'] = ['EQ', strHtml($param['return_status_val'])];
                    break;
                case '其它':
                    $where['return_status_val'] = ['NEQ', '短消息转发成功'];
                    break;
            }
        }
        $data = $this->getModel('SmsList')->selectData('*', $where);

        if (count($data) > 0) {
            foreach ($data as $k => $v) {
                $data[$k]['type'] = changeSmsType($v['type']);
            }

            return $data;
        }

        return $this->arr;
    }

    /**
     * 用户列表
     * @param array $where
     * @param string $field
     * @return mixed
     */
    public function userList($where = [], $field = '')
    {

        $where['is_available'] = 0;
        $data['data'] = $this->getModel('User')->field($field)->where($where)->order('id desc')->select();
        //echo $this->getModel('User')->getLastSql();
        return $data;
    }

    /**
     * 添加用户
     * @param array $data
     * @return mixed
     */
    public function addUser($data)
    {
        $check = $this->checkUserParams($data);
        if ($check['status'] != 1) {
            return $check;
        }
        $data['dealer_name'] = session('adminInfo')['dealer_name'];
        $status = $this->saveUser($data);
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '添加成功' : '添加失败';
        return $result;
    }

    /**
     * 添加用户
     * @param array $data
     * @return mixed
     */
    public function editUser($data)
    {
        $check = $this->checkUserParams($data, 0);
        if ($check['status'] != 1) {
            return $check;
        }
        $info = $this->userInfo($data['id']);
        if (empty($info)) {
            $result['status'] = 9;
            $result['message'] = '用户不存在或流失，请联系技术部！';
        }
        $status = $this->saveUser($data, 0);
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '修改成功' : '修改失败';
        return $result;
    }

    /**
     * 保存用户信息
     * @param $data
     * @param int $add
     * @return int
     */
    protected function saveUser($data, $add = 1)
    {

        if ($add) {
            $data['reg_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('User')->add($data);
        } else {
            $data['time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('User')->where(['id' => $data['id']])->save($data);
        }
        return $status ? 1 : 0;

    }

    /**
     * 验证用户参数
     * @param $data
     * @param int $add 是否添加 1 是 0否
     * @return mixed
     */
    protected function  checkUserParams($data, $add = 1)
    {
        $result['status'] = '1';
        $result['message'] = '验证通过';

        if (!$data['user_name']) {
            $result['status'] = '2';
            $result['message'] = '用户姓名不能为空,请重新输入!';

        }
        if ($add) { //添加时需要验证手机格式及是否注册，修改时不能修改手机号所以无需验证
            if (!$data['user_phone'] || !isMobile($data['user_phone'])) {
                $result['status'] = '3';
                $result['message'] = '用户手机号输入有误,请重新输入!';

            }

            //验证该手机号是否已存在
            $info = $this->isHaveUser($data['user_phone']);
            if (!empty($info)) {
                $result['status'] = '7';
                $result['message'] = '对不起，该手机号用户已在【' . $info['dealer_name'] . '】名下!';
            }
        }

        if (!$data['sex']) {
            $result['status'] = '4';
            $result['message'] = '请选择用户性别!';

        }
//        if (!$data['address2']) {
//            $result['status'] = '5';
//            $result['message'] = '联系地址不能为空,请重新输入!';
//
//        }
        if (!$data['user_source']) {
            $result['status'] = '6';
            $result['message'] = '请选择用户来源!';

        }
        if (!$data['region']) {
            $result['status'] = '8';
            $result['message'] = '请选择所在城市!';

        }

        return $result;
    }

    /**
     * 手机号用户是否存在
     * @param string $phone
     * @return bool
     */
    public function isHaveUser($phone = '')
    {
        if ($phone) {
            $info = $this->getModel('User')->where(['user_phone' => trim($phone), 'is_available' => ['neq', 1]])->find();
            return $info;
        }
        return false;
    }

    /**
     * 获取用户详细信息
     * @param string $id 用户id
     * @author xiaowen
     * @return mixed
     */
    public function userInfo($id, $all = 0)
    {
        if ($all == 1) {
            $data['base'] = $this->getModel('User')->where(['is_available' => 0, 'id' => $id])->find();
            $data['ext'] = M('userext')->where(['u_id' => $id])->find();
            $data['visituser'] = M('visituser')->where(['u_id' => $id, 'is_available' => 0])->select();
            $data['buy_data'] = M('buyprods')->where(['u_id' => $id])->find();
            $data['sale_data'] = M('sell')->where(['u_id' => $id])->find();
            $data['microcustomer_data'] = M('user_microcustomer')->where(['u_id' => $id])->find();
        } else {
            $data = $this->getModel('User')->where(['is_available' => 0, 'id' => $id])->find();
        }

        return $data;
    }

    /**
     * 绑定公司
     * @param $data
     * @author xiaowen
     * @time 2016-11-25
     * @return mixed
     */
    public function bindCompany($data)
    {
        if (trim($data['company_id']) == '') {
            $result['status'] = 2;
            $result['message'] = '公司不存在，无法绑定！';
            return $result;
        }
        if (trim($data['user_id']) == '') {
            $result['status'] = 3;
            $result['message'] = '用户ID不存在，无法绑定！';
            return $result;
        }
        if (trim($data['deal_status']) == '') {
            $result['status'] = 4;
            $result['message'] = '请选择关系！';
            return $result;
        }
        if ($data['deal_status'] == 1 && $data['company_status'] < 20) {
            $result['status'] = 5;
            $result['message'] = '公司信息未审核通过，无法绑定批发关系！';
            return $result;
        }
        $status = $this->getModel('Uc')->add($data);
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '绑定成功' : '绑定失败';
        return $result;
    }

    /**
     * 已绑定公司列表
     * @author xiaowen
     * @param $user_id
     * @return mixed
     */
    public function bindCompanyList($user_id)
    {
        $data = $this->getModel('Uc')->alias('uc')->field('uc.*, c.company_name')->where(['uc.user_id' => $user_id, 'uc.is_available' => 0])->join('__CLIENTS__ as c on uc.company_id = c.id', 'left')->select();
        return $data;
    }

    /**
     * 保存用户扩展信息
     * @author xiaowen
     * @param $u_id
     * @param $data
     * @return mixed
     */
    public function saveExtInfo($u_id, $data)
    {
        $user_id = $this->getModel('User')->where(['id' => $u_id, 'is_available' => 0])->getField('id');

        if (!$user_id) {
            $result['status'] = '2';
            $result['message'] = '用户不存在或已流失';
            return $result;
        }
        $data['dealer_name'] = session('adminInfo')['dealer_name'];
        $info = $this->getModel('userext')->where(['u_id' => $u_id])->find();
        if ($info) {
            $data['update_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('userext')->where(['u_id' => $u_id])->save($data);
        } else {

            $data['create_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('userext')->add($data);
        }
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '保存成功' : '保存失败';
        return $result;
    }

    /**
     * 保存拜访记录
     * @author xiaowen
     * @param $u_id
     * @param $data
     * @return mixed
     */
    public function saveVisitInfo($u_id, $data)
    {
        $user_info = $this->getModel('User')->where(['id' => $u_id, 'is_available' => 0])->find();

        if (empty($user_info)) {
            $result['status'] = '2';
            $result['message'] = '用户不存在或已流失';
            return $result;
        }

        if ($data['id']) {
            $data['edit_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('visituser')->where(['u_id' => $u_id, 'id' => $data['id']])->save($data);
        } else {
            $data['dealer_name'] = session('adminInfo')['dealer_name'];
            $data['create_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('visituser')->add($data);
        }
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '保存成功' : '保存失败';
        return $result;
    }

    /**
     * 获取此用户拜访记录
     * @author xiaowen
     * @param $u_id 用户ID
     * @return mixed
     */
    public function getVisitList($u_id)
    {
        $result['data'] = $this->getModel('visituser')->where(['u_id' => $u_id, 'is_available' => 0])->select();
        return $result;
    }

    /**
     * 获取此用户拜访记录
     * @author xiaowen
     * @param $id ID
     * @return mixed
     */
    public function getVisitInfo($id)
    {
        $result = $this->getModel('visituser')->where(['id' => $id])->find();
        return $result;
    }

    /**
     * 删除用户拜访记录
     * @author xiaowen
     * @param $id ID
     * @return mixed
     */
    public function delVisitInfo($id)
    {
        $status = $this->getModel('visituser')->where(['id' => $id])->save(['re_time' => date('Y-m-d H:i:s'), 'is_available' => 1]);
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '删除成功' : '删除失败';
        return $result;
    }

    /**
     * 保存销售规律信息
     * @author xiaowen
     * @param $u_id
     * @param $data
     * @return mixed
     */
    public function saveSellInfo($u_id, $data)
    {
        $user_info = $this->getModel('User')->where(['id' => $u_id, 'is_available' => 0])->find();

        if (empty($user_info)) {
            $result['status'] = '2';
            $result['message'] = '用户不存在或已流失';
            return $result;
        }

        if ($data['id']) {
            $data['edit_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('sell')->where(['u_id' => $u_id, 'id' => $data['id']])->save($data);
        } else {
            $data['dealer_name'] = session('adminInfo')['dealer_name'];
            $data['create_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('sell')->add($data);
        }
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '保存成功' : '保存失败';
        return $result;
    }

    /**
     * 保存采购规律信息
     * @author xiaowen
     * @param $u_id
     * @param $data
     * @return mixed
     */
    public function saveBuyInfo($u_id, $data)
    {
        $user_info = $this->getModel('User')->where(['id' => $u_id, 'is_available' => 0])->find();

        if (empty($user_info)) {
            $result['status'] = '2';
            $result['message'] = '用户不存在或已流失';
            return $result;
        }

        if ($data['id']) {
            $data['purchase_update_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('buyprods')->where(['u_id' => $u_id, 'id' => $data['id']])->save($data);
        } else {
            $data['dealer_name'] = session('adminInfo')['dealer_name'];
            $data['purchase_create_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('buyprods')->add($data);
        }
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '保存成功' : '保存失败';
        return $result;
    }

    /**
     * 保存采购规律信息
     * @author xiaowen
     * @param $u_id
     * @param $data
     * @return mixed
     */
    public function saveMicrocustomer($u_id, $data)
    {
        $user_info = $this->getModel('User')->where(['id' => $u_id, 'is_available' => 0])->find();

        if (empty($user_info)) {
            $result['status'] = '2';
            $result['message'] = '用户不存在或已流失';
            return $result;
        }

        if ($data['id']) {
            $data['update_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('user_microcustomer')->where(['u_id' => $u_id, 'id' => $data['id']])->save($data);
        } else {
            $data['dealer_name'] = session('adminInfo')['dealer_name'];
            $data['create_time'] = date('Y-m-d H:i:s');
            $status = $this->getModel('user_microcustomer')->add($data);
        }
        $result['status'] = $status ? 1 : 0;
        $result['message'] = $result['status'] ? '保存成功' : '保存失败';
        return $result;
    }

    /**
     * 交易信息
     * @param $id
     * @return array
     */
    public function getDealData($id)
    {
        $data = [];
        $condition1['buy_id'] = $id;
        $condition1['is_available'] = 0;
        $data['buy_week'] = $this->getModel('User')->buy_week($condition1);                //统计本周采购笔数
        /* 交易信息-本月采购笔数 */
        $condition2['buy_id'] = $id;
        $condition2['is_available'] = 0;
        $data['buy_format'] = $this->getModel('User')->buy_format($condition2);            //统计本月采购笔数
        /* 交易信息-本周采购吨数 */
        $condition3['buy_id'] = $id;
        $condition3['is_available'] = 0;
        $data['buy_week_num'] = $this->getModel('User')->buy_week_num($condition3);        //统计本周采购吨数
        /* 交易信息-本月采购吨数 */
        $condition4['buy_id'] = $id;
        $condition4['is_available'] = 0;
        $data['buy_format_num'] = $this->getModel('User')->buy_format_num($condition4);    //统计本月采购吨数
        /* 交易信息-本周出货笔数 */
        $condition5['sale_id'] = $id;
        $condition5['is_available'] = 0;
        $data['sell_week'] = $this->getModel('User')->sell_week($condition5);            //统计本周出货笔数
        /* 交易信息-本周出货吨数 */
        $condition6['sale_id'] = $id;
        $condition6['is_available'] = 0;
        $data['sell_week_num'] = $this->getModel('User')->sell_week_num($condition6);    //统计本周出货吨数
        /* 交易信息-本月出货吨数 */
        $condition7['sale_id'] = $id;
        $condition7['is_available'] = 0;
        $data['sell_format_num'] = $this->getModel('User')->sell_format_num($condition7);  //统计本月出货吨数
        /* 交易信息-本月出货笔数 */
        $condition8['sale_id'] = $id;
        $condition8['is_available'] = 0;
        $data['sell_format'] = $this->getModel('User')->sell_format($condition8);        //统计本月出货笔数
        /* 交易信息-最后交易时间 */
        $condition9['buy_id'] = $id;
        $condition9['is_available'] = 0;
        $data['time_order'] = $this->getModel('User')->time_order($condition9)['0']['create_time'];
        foreach ($data as $k => $v) {
            if (empty($v)) $data[$k] = 0;
        }
        return $data;

    }

    /**
     * 根据手机号或姓名关键词查找用户
     * @param $q
     * @return mixed
     */
    public function getUserByPhoneName($q)
    {
        if ($q) {
            $where = "(user_phone like '%{$q}%' or user_name like '%{$q}%') and is_available = 0 and user_name <> ''";
            $data = $this->getModel('User')->where($where)->select();
            //$new = array_column($data, 'user_name');
            //return $new;
            return $data;
        }
    }

    /**
     * 返回用户所属的交易员
     * @author xiaowen
     * @time 2017-3-29
     * @param $user_id
     * @return $data
     */
    public function getDealerInfoByUserId($user_id)
    {
        $dealer_name = $this->getModel('User')->where(['id' => $user_id])->getField('dealer_name');
        $dealer_info = loginNameTodealerId($dealer_name);

        $data['dealer_name'] = $dealer_name;
        $data['dealer_id'] = $dealer_info['id'];
        return $data;
    }

    /**
     * 根据名称查找交易员
     * @author senpai
     * @time 2017-04-05
     */
    public function getDealerByName($q)
    {
        if ($q) {
            $where = "(dealer_name like '%{$q}%') and is_available = 0 and dealer_name <> ''";
            $data = $this->getModel('Dealer')->where($where)->select();
            //$new = array_column($data, 'user_name');
            //return $new;
            return $data;
        }
    }

}
