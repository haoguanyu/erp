<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

/**
 * 员工管理控制器
 * Class DealerController
 * @package Home\Controller
 */
class DealerController extends BaseController
{
    /**
     * 首页:用来加载在职员工列表和非在职员工的方法
     * edit:lihouwei
     * date:2017-2-13
     * time:23:30
     */
    public function index()
    {
        $leave_status = I('get.leave_status');
        //echo $this->permissionCode('zaizhi/admins') ? '有权限' : '无权限';
        //editor:lihouwei
        //对leave_status的值进行判断，当leave_status=2时为离职，当leave_status=1时为在职
        if ($leave_status == 1) {
            $this->display();
        } else if ($leave_status == 2) {
            $this->display('lost');
        } else {
            $this->error('对不起，您没有此权限');
        }
        //editor:lihouwei
        /*  if($this->permissionCode('zaizhi/admins') || $this->permissionCode('lizhi/admin')){
             $this->display();
         }else{
             $this->error('对不起，您没有此权限');
         } */

    }

    public function edit()
    {
        $id = I('param.id', 0);
        $data = [];
        if ($id) {
            $data = D('Dealer')->find($id);
        }
        $data['DealerCompany'] = getDealerCompany(); //分公司
        $data['regionData'] = getRegion(); //所属地区
        $this->assign('data', $data);
        $this->display();
    }

    public function add()
    {
        $data['DealerCompany'] = getDealerCompany(); //分公司
        $data['regionData'] = getRegion(); //所属地区
        $this->assign('data', $data);
        $this->display('edit');
    }

    /**
     * 分配角色页面
     */
    public function setRole()
    {
        $id = I('param.id', 0);
        $data = [];
        if ($id) {
            $data = D('Dealer')->find($id);
        }
        $roleArr = D('Role')->where(['status' => 1, 'app' => $this->getAppName()])->select();
        $AdminRole = M('admin_role')->where(['admin_id' => intval($id)])->select();
        $haveRole = [];
        if (!empty($AdminRole)) {
            foreach ($AdminRole as $key => $value) {
                $haveRole[] = $value['role_id'];
            }
        }

        $this->assign('data', $data);
        $this->assign('roleArr', $roleArr);
        $this->assign('haveRole', $haveRole);
        $this->display();
    }

    public function welcome()
    {
        $this->display();
    }

    /**
     * ajax通过关键与姓名模糊匹配查找交易员
     * @author xiaowen
     * @time 2017-4-19
     */
    public function ajaxGetDealerByName(){
        $keyword = strHtml(I('param.q', ''));
        $data['data'] = [];
        if($keyword){
            $where['is_available'] = 0;
            $where['dealer_name'] = ['like', '%' . $keyword . '%'];
            $data['data'] = D('Dealer')->where($where)->select();
        }
        $data['total_count'] = count($data['data']);
        $data['incomplete_results'] = true;
        $this->echoJson($data);
    }


}