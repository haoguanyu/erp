<?php
namespace Api\Controller;

use Think\Controller;
use Api\Controller\BaseController;

/**
 * 员工管理控制器
 * Class DealerController
 * @package Home\Controller
 */
class DealerController extends BaseController
{
    /**
     * 管理员列表接口
     * 修改时间：2017-2-14  0：03
     */
    public function getList()
    {
        //将区分是否在职的字段is_available写入到$field里面
        $field = 'id,dealer_name,dealer_username,dealer_pwd,dealer_email,qq,dealer_phone,is_available,create_time,department_id,corporation_id';
        $leave = I('param.leave', 0);
        $where = [];
        if ($leave) {
            $where['leave_status'] = $leave;
        }
        $where['is_available'] = 0;
        $data['data'] = D('Dealer')->getDealerList($field, $where);
        $companyData = getDealerCompany();
        foreach ($companyData['company'] as $key => $val) {
            $companyName[$val['id']] = $val['name'];
        }
        foreach ($companyData['department'] as $key => $val) {
            $departmentName[$val['id']] = $val['name'];
        }
        foreach ($data['data'] as $key => $val) {
            $data['data'][$key]['corporation_name'] = $companyName[$val['corporation_id']];
            $data['data'][$key]['department_name'] = $departmentName[$val['department_id']];
        }
        $this->echoJson($data);
    }

    /* 
     * 管理员列表接口，非在职人员的借口
     * created by zend studio
     * author:lihouwei
     * date:2017-2-14  1:08
     */
    public function lostList()
    {
        //将区分是否在职的字段is_available写入到$field里面
        $field = 'id,dealer_name,dealer_username,dealer_pwd,dealer_email,qq,dealer_phone,is_available,create_time,department_id,corporation_id';
        $leave = I('param.leave', 0);
        $where = [];
        if ($leave) {
            $where['leave_status'] = $leave;
        }
        $where['is_available'] = 1;
        $data['data'] = D('Dealer')->getDealerList($field, $where);
        $companyData = getDealerCompany();
        foreach ($companyData['company'] as $key => $val) {
            $companyName[$val['id']] = $val['name'];
        }
        foreach ($companyData['department'] as $key => $val) {
            $departmentName[$val['id']] = $val['name'];
        }
        foreach ($data['data'] as $key => $val) {
            $data['data'][$key]['corporation_name'] = $companyName[$val['corporation_id']];
            $data['data'][$key]['department_name'] = $departmentName[$val['department_id']];
        }
        $this->echoJson($data);
    }

    /**
     * 分配角色
     */
    public function setRole()
    {
        $user_id = I('admin_id', 0);
        $role_id = I('role_id', 0);
        $result = false;
        if ($user_id && $role_id) {
            $is_have = M('admin_role')->where(['admin_id' => $user_id, 'role_id' => $role_id])->find();
            if (!empty($is_have)) {
                $result = true;
            } else {
                $data = ['admin_id' => $user_id, 'role_id' => $role_id, 'create_at' => date('Y-m-d H:i:s')];
                $result = M('admin_role')->add($data);
            }
        }
        if ($result) {
            $this->echoSuccess('操作成功');
        } else {
            $this->echoError('操作失败');
        }
//        $msg = $result ? '操作成功' : '操作失败';
//        $this->echoJson(['status'=>$result, 'msg'=>$msg]);
    }

    public function delRole()
    {
        $user_id = I('admin_id', 0);
        $role_id = I('role_id', 0);
        //$type = 1; //1：添加角色、2：删除角色
        $result = false;
        if ($user_id && $role_id) {
            $is_have = M('admin_role')->where(['admin_id' => $user_id, 'role_id' => $role_id])->find();
            if (!empty($is_have)) {
                $data = ['admin_id' => $user_id, 'role_id' => $role_id];
                $result = M('admin_role')->where($data)->delete();

            } else {
                $result = true;
            }
        }
        if ($result) {
            $this->echoSuccess('操作成功');
        } else {
            $this->echoError('操作失败');
        }
    }

    public function addDealer()
    {

        $data = I('param.');
        $this->checkInfo($data);
        //姓名转拼音
        //import("ORG.Util.PinYin");
        //$py = new \Org\Util\PinYin();
        //$data['dealer_username'] = $py->getAllPY(strHtml($data['dealer_name'])); //
        //echo $py->getFirstPY("输出汉字首拼音");
        $data['create_time'] = DateTime();
        $status = D('Dealer')->add($data);
        if ($status) {
            $this->echoSuccess('员工添加成功');

        } else {
            $this->echoError('员工添加失败');
        }
    }

    public function editDealer()
    {
        $data = I('param.');
        $this->checkInfo($data, 0);
        //姓名转拼音
        //import("ORG.Util.PinYin");
        // $py = new \Org\Util\PinYin();
        // $data['dealer_username'] = $py->getAllPY(strHtml($data['dealer_name'])); //
        $data['updated_at'] = DateTime();
        $where['id'] = intval($data['id']);
        $status = D('Dealer')->where($where)->save($data);

        if ($status) {
            $this->echoSuccess('员工编辑成功');

        } else {
            $this->echoError('员工编辑失败');
        }
    }

    /**
     * 检验参数是否有误
     * @param $data
     * @param int $is_add
     */
    protected function checkInfo($data, $is_add = 1)
    {
        if (!$is_add) {
            if (!trim($data['id'])) {
                $this->echoError('参数有误，ID无法获取');
            }

        } else {
            if (!trim($data['dealer_name'])) {
                $this->echoError('交易员名字不能为空');
            }
            $is_have = D('Dealer')->where(['dealer_name' => $data['dealer_name']])->count();
            if ($is_have) {
                $this->echoError('交易员名字已存在，麻烦换个小名吧！');
            }
            if (!trim($data['dealer_username'])) {
                $this->echoError('用户名不能为空');
            }
            $num = D('Dealer')->where(['dealer_username' => $data['dealer_username']])->count();
            if ($num) {
                $this->echoError('用户名已经存在');
            }
        }

        if (!trim($data['dealer_pwd'])) {
            $this->echoError('密码不能为空');
        }

        if (!trim($data['dealer_email'])) {
            $this->echoError('邮箱不能为空');
        }

//        if(!trim($data['dealer_phone'])){
//            $this->echoError('手机号不能为空');
//        }
    }

    /**
     * 删除员工帐号
     */
    public function delDealer()
    {
        $id = intval(I('param.id'), 0);
        if (!$id) {
            $this->echoError('参数有误');
        }
        $where['id'] = $id;
        $status = D('Dealer')->where($where)->save(['is_available' => 1, 'updated_at' => DateTime()]);
        if ($status) {
            $this->echoSuccess('删除成功');

        } else {
            $this->echoError('删除失败');
        }
    }

    /**
     *异步生成员工帐号用户名
     */
    public function ajaxGetDealerUserName()
    {
        $data = I('param.');
        $py = new \Org\Util\PinYin();
        $nameCount = D('Dealer')->where(['dealer_name' => $data['dealer_name']])->count();
        if ($nameCount) {
            $this->echoJson(['status' => 0, 'msg' => '交易员名字已存在，麻烦换个小名吧！']);
        }
        $data['dealer_username'] = $py->getAllPY(strHtml($data['dealer_name'])); //
        $num = D('Dealer')->where(['dealer_username' => $data['dealer_username']])->count();
        if ($num) {
            $data['dealer_username'] = $data['dealer_username'] . ($num + 1);
        }
        $result['status'] = 1;
        $result['dealer_username'] = $data['dealer_username'] ? $data['dealer_username'] : '';
        $this->echoJson($result);
    }

}