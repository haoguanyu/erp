<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;
use Think\Verify;

class PublicController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {

        $this->display();
    }

    public function welcome()
    {
        $this->display();
    }

    public function memberList()
    {
        $this->display();
    }

    public function login()
    {
        if (IS_POST) {
            $user_name = trim(I('user_name', ''));
            $password = trim(I('password', ''));
            $erp_company_id = trim(I('erp_company_id', ''));
            $remember = trim(I('remember', ''));
            if ($password && $user_name) {
                if ($erp_company_id) {
                    $where['dealer_username'] = $user_name;
                    $where['dealer_pwd'] = $password;
                    $where['is_available'] = 0;
                    $userInfo = D('Dealer')->where($where)->find();
                    //是否强制重置密码 2019-1-15 xiaowen
                    if(FORCE_RESET_PASSWORD == 1 && $userInfo['reset_pwd'] == 2){
                        $this->echoJson(['status' => 2, 'msg' => '经公司内控管理要求，目前账号密码需全部重设，请点击重置密码进行操作。']);
                    }
                    $result = !empty($userInfo) ? ['status' => 1, 'msg' => '登陆成功'] : ['status' => 0, 'msg' => '用户名或密码错误'];
                    if ($result['status'] == 1) {

                        /** 记住密码--start */
                        if ($remember) {

                            //第二分身标识（记住密码）
                            $token = md5(md5($user_name . time()));
                            $remember_data = [
                                'dealer_username' => $user_name,
                                'dealer_pwd' => $password,
                                'token' => $token,
                                'create_time' => date('Y-m-d H:i:s', time()),
                                'timeout' => time()+3600*24*30,
                                'type' => 'erp'
                            ];

                            //判断是否有该用户的记录信息
                            $remember_info = D('RememberLogin')->where(['dealer_username'=>$user_name,'type'=>'erp'])->find();

                            //曾经记录密码
                            if ($remember_info) {
                                /**
                                 * 1、cookie过期后登陆
                                 * 2、改密码后登陆
                                 * 3、cookie过期且修改密码后登陆
                                 */
                                if ($remember_info['dealer_pwd'] != $password || !$_COOKIE['auth']) {
                                    $status_remember = D('RememberLogin')->where(['dealer_username'=>$user_name,'type'=>'erp'])->save($remember_data);
                                }
                            } elseif (!$_COOKIE['auth']) {
                                $status_remember = D('RememberLogin')->add($remember_data);
                            }
                            if ($status_remember) {
                                setcookie('auth',$token,time()+3600*24*30);
                            }
                        }
                        /** 记住密码--end */

                        //editor：lihouwei
                        ini_set('session.gc_maxlifetime', 43200);//设置session的生命周期为86400秒
                        ini_get('session.gc_maxlifetime');//得到ini中设定的值
                        ini_set("session.cookie_lifetime", "43200"); // 秒
                        //editor:lihouwei
                        session('erp_adminInfo', $userInfo);
                        session('erp_company_id', $erp_company_id);
                        session('erp_company_name', D('erp_company')->where(['company_id' => $erp_company_id])->getField('company_name'));

                        //-------查询用户角色及其角色拥有的权限 edit xiaowen 2017-5-17-------------------------
                        $roles = D('Dealer')->getRoles($userInfo['id'], $this->getAppName());
                        $per = D('Role')->getRoleNodeAll($roles);
                        session('erp_roles', $roles);
                        foreach ($per as $k => $v) {
                            $permission[$v['id']] = $v['url'];
                        }
                        session('erp_permission', $permission);
                        //--------------------------------------------------------------------------------------
                    }
                    $this->echoJson($result);
                } else {
                    $this->echoJson(['status' => 0, 'msg' => '请选择账套']);
                }
            } else {
                $this->echoJson(['status' => 0, 'msg' => '用户名或密码不能为空']);
            }
        } else {
            //获取记住密码相关信息
            $auth = $_COOKIE['auth'];
            if ($auth) {
                $remember_info = D('RememberLogin')->where(['token'=>$auth])->find();
                if ($remember_info) {
                    $this->assign('user_name', $remember_info['dealer_username']);
                    $this->assign('password', $remember_info['dealer_pwd']);
                }
            }

            //获取所有内部账套 统一获取方式  edit xiaowen 2017-12-15
            //$erpCompany = getAllErpCompany();
            $erpCompany = getErpCompanyList('company_id, company_name, pre_code');
            foreach($erpCompany as $key=>$value){
                $erpCompanyList[$key] = $value['company_name'];
                $erpCompanyCodeList[$key] = $value['pre_code'];
            }
            //缓存内部账套的前缀，供登陆后生成单据前缀使用
            S('ErpCompanyPreCode', $erpCompanyCodeList);
            //$erpCompany = getErpCompanyList('company_id, company_name, pre_code');
            $reset_password_url = trim(RESET_PASSWORD) ? trim(RESET_PASSWORD) : '#';
            $this->assign('reset_password_url', $reset_password_url);
            $this->assign('erpCompany', $erpCompanyList);
            $this->display();
        }

    }

    public function loginOut()
    {
        session('erp_adminInfo', null);
        $this->redirect('Public/login');
    }

    public function Verify()
    {
        $verify = new Verify();
        $height = I('param.height', 40);
        $width = I('param.width', 160);
        $verify->length = 3;
        $verify->fontSize = 20;
        $verify->useNoise = false;
        $verify->imageW = $width;
        $verify->imageH = $height;
        $verify->entry();
    }

    /**
     * 系统升级提示页
     * @author xiaowen
     * @time 2018-11-15
     */
    public function sysUpgrade(){
        $this->display();
    }

     /***********************************
        @ Content 统计在线人数
        @ Author  YF
        @ Time    2019-04-08
    ************************************/
    public function countPeople(){
        $this->countOnLinePeople();
        $num = $this->getModel('ErpCountOnline')->where(['status' => ['eq',1]])->count();
        $arr = ['status' => 1,'message' => '成功','data'=>$num];
        $this->echoJson($arr);
    }

    /*******************************
        @ Content 统计在线人数
        @ Author  YF
        @ Time    2019-04-04
    ********************************/
    public function countOnLinePeople()
    {
        /* --------- 清除掉 2 分钟未登录 的用户 --------- */
       $time = date("Y-m-d H:i:s",strtotime("-2 minute"));
       $delete_where = [
            'add_time' => ['lt',$time],
       ];
       $this->getModel('ErpCountOnline')->where($delete_where)->save(['status'=>0]);

        // IP 地址
       $ip = $_SERVER['REMOTE_ADDR'];
       $user_name = $this->getUserInfo('dealer_name');
       if ( !empty($user_name) && !empty($ip) ) {
           $count_on_line_where = [
                'ip'        => ['eq',$ip],
                'user_name' => ['eq',$user_name]
           ];
           $people_arr = $this->getModel('ErpCountOnline')->where($count_on_line_where)->find();
           if ( !isset($people_arr['ip']) ) {
               $insert_arr = [
                    'ip'        => $ip,
                    'user_name' => $user_name,
                    'add_time'  => nowTime(),
                    'status'    => 1,
               ];
               // 添加入库
               $this->getModel('ErpCountOnline')->add($insert_arr);
           } else {
                $update_where = [
                    'id'        => ['eq',$people_arr['id']],
                ];
                $update_arr = [
                    'add_time' => nowTime(),
                    'status'   => 1,
                ];
                $this->getModel('ErpCountOnline')->where($update_where)->save($update_arr);
           }
       }
    }

}