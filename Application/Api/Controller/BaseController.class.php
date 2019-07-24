<?php
namespace Api\Controller;

use Think\Controller;

class BaseController extends Controller
{
    public $uid;
    public $user_name;
    public $roles;
    public $app_name;

    public function __construtct()
    {
        header("content-type:text/html;charset=utf-8");
        parent::__construct();
        if (in_array(strtolower(MODULE_NAME), array('home', 'api')) && !in_array(strtolower(CONTROLLER_NAME), array('base', 'public'))) {
            $this->checkLogin();
        }
        $this->uid = $this->getUserInfo('id');
        $this->user_name = $this->getUserInfo('dealer_name');
        $this->roles = D('Dealer')->getRoles($this->uid, $this->getAppName());
    }

    /**
     * 公用成功提示信息
     * @param string $msg
     * @param array $data
     */
    protected function echoSuccess($msg = '', $data = [])
    {
        $json = ['status' => 1, 'msg' => $msg];
        if ($data) {
            $json['data'] = $data;
        }
        $this->echoJson($json);
    }

    /**
     * 公用失败提示信息
     * @param int $status
     * @param string $msg
     * @param array $data
     */
    protected function echoError($msg = '', $status = 0, $data = [])
    {
        $json = ['status' => $status, 'msg' => $msg];
        if ($data) {
            $json['data'] = $data;
        }
        $this->echoJson($json);
    }

    protected function echoJson($data)
    {
        echo $this->ajaxReturn($data);
        exit;
    }

    protected function getUserInfo($key = '')
    {
        $adminInfo = session('adminInfo');
        if ($adminInfo && $key) {
            return isset($adminInfo[$key]) ? $adminInfo[$key] : '';
        }
        return $adminInfo;
    }

    protected function getAppName()
    {
        return $this->app_name = C('APP_NAME', null, 'erp'); //edit xiaowen 取得配置文件中的app_name 标识当前的应用。用来配置权限
    }

}