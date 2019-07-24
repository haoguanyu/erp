<?php
namespace Home\Controller;

use Think\Controller;
use Common\Controller\BaseController as CommonBaseController;
class BaseController extends CommonBaseController
{
    public $uid;
    public $user_name;
    public $roles;
    public $app_name;
    public $running_msg;
    public $uploads_path;

    public function __construct()
    {
        header("content-type:text/html;charset=utf-8");
        parent::__construct();
        if (in_array(strtolower(MODULE_NAME), array('home', 'api')) && !in_array(strtolower(CONTROLLER_NAME), array('base', 'public','erpcron','erpapi','data','erpconfig'))) {
            $this->checkLogin();
            $this->checkSysUpgrade();
        }
        $this->uid = $this->getUserInfo('id');
        $this->user_name = $this->getUserInfo('dealer_name');
        //$this->roles = D('Dealer')->getRoles($this->uid, $this->getAppName());
        $this->roles = session('erp_roles') ? session('erp_roles') : '';
        $this->running_msg = '程序正在处理中...请稍后再试！';
        $this->getAppName();
        //dump($this->roles);die;
//        if(!empty($this->roles)){
//            $rolesName = D('Role')->where(['role_id'=>['in', $this->roles]])->getField('role_name', true);
//            $rolesName = implode(',', $rolesName);
//            //dump($rolesName);die;
//        }else{
//            $rolesName = '未分配角色';
//        }

        $rolesName = '';
        //上传目录配置 xiaowen
        $this->uploads_path = [
            //撮合交易单
            'order_img' => [
                'src' => './Public/Uploads/Erp/Order/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Order/'
            ],
            //采购单
            'purchase_attach' => [
                'src' => './Public/Uploads/Erp/Purchase/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Purchase/'
            ],
            //销售单
            'sale_attach' => [
                'src' => './Public/Uploads/Erp/Sale/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Sale/'
            ],
            //退货单
            'returned_attach' => [
                'src' => './Public/Uploads/Erp/Returned/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Returned/'
            ],
            //调拨单
            'allocation_attach' => [
                'src' => './Public/Uploads/Erp/Allocation/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Allocation/'
            ],
            //配送单
            'shipping_attach' => [
                'src' => './Public/Uploads/Erp/Shipping/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Shipping/'
            ],
            //出库单
            'stock_out_attach' => [
                'src' => './Public/Uploads/Erp/StockOut/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/StockOut/'
            ],
            //入库单
            'stock_in_attach' => [
                'src' => './Public/Uploads/Erp/StockIn/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/StockIn/'
            ],
            //供应商附件
            'supplier_attach' => [
                'src' => './Public/Uploads/Erp/Supplier/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Supplier/'
            ],
            //客户附件
            'customer_attach' => [
                'src' => './Public/Uploads/Erp/Customer/',
                'url' => __ROOT__ . '/Public/Uploads/Erp/Customer/'
            ],
            // 配送单云服务器
            'shipping_attach_aliyun' => [
                'src' => '',
                'url' => 'http://asset-public.oss-cn-shanghai.aliyuncs.com/erp/'
            ],
        ];
        //2017-02-05 ypm 限制--权限表里、不属于自己的--功能；
        $Check = D('Node')->where(['app' => 'erp'])->getField('url', true);
        $url = CONTROLLER_NAME . '/' . ACTION_NAME;
        $parseUrl = lcfirst(CONTROLLER_NAME) . '/' . ACTION_NAME;
        if (in_array($url, $Check) || in_array($parseUrl, $Check)) {

//            $per = D('Role')->getRoleNodeAll($this->roles);
//            foreach ($per as $k => $v) {
//                $permission[$v['id']] = $v['url'];
//            }
            $permission = session('erp_permission');

            if (!in_array($url, $permission) && !in_array($parseUrl, $permission)) {
                echo $parseUrl . '你没有这个的权限';
                exit();
            }
        }

        //设置常量
        define("PRESTORE_TYPE", 1);
        define("PREPAY_TYPE", 2);

        session('role_name', $rolesName);
    }

    public function checkLogin()
    {
        // var_dump(phpinfo());die;
        $info = session('erp_adminInfo');
        if (empty($info)) {
            $this->redirect('Public/login');
            exit;
        }
    }

    public function login()
    {
        $this->display('Public_login');
    }

    /**
     * 公用失败提示信息
     * @param int $status
     * @param string $msg
     * @param array $data
     */
    protected function echoError($msg = '', $status = 0, $data = [])
    {
        $data = ['status' => $status, 'msg' => $msg, $data];
        $this->echoJson($data);
    }

//    protected function echoJson($data)
//    {
//        echo $this->ajaxReturn($data);
//        exit;
//    }

    /**
     * 获取登陆后的用户信息
     * @param string $key
     * @return mixed|string
     */
    protected function getUserInfo($key = '')
    {
        $adminInfo = session('erp_adminInfo');
        if ($adminInfo && $key) {
            return isset($adminInfo[$key]) ? $adminInfo[$key] : '';
        }
        return $adminInfo;
    }

    /**
     * 当前用户所有的权限码
     * @return mixed
     */
    protected function permissionAll()
    {
        return D('Role')->getRolePermissionAll($this->roles);
    }

    /**
     * 验证用户是否拥有对应的权限code
     * @param string $code
     * @return bool
     */
    protected function permissionCode($code = '')
    {
        if (!$code) {
            return false;
        }
        $permissionAll = $this->permissionAll();
        if (is_string($code)) {
            return in_array($code, $permissionAll) ? true : false;
        } else if (is_array($code)) {
            $userPermission = [];
            foreach ($code as $k => $value) {
                if (in_array($value, $permissionAll)) {
                    $userPermission[] = $value;
                }
            }
            return $userPermission;
        }

    }

    protected function getUserPermissionArr($code = [])
    {
        $data = [];
        $permissionAll = $this->permissionAll();
        if (is_array($code) && !empty($code)) {
            foreach ($code as $k => $value) {
                if (in_array($value, $permissionAll)) {
                    $data[] = $value;
                }
            }
        }
        return $data;
    }

    protected function getAppName()
    {
        return $this->app_name = C('APP_NAME', null, 'erp'); //edit xiaowen 取得配置文件中的app_name 标识当前的应用。用来配置权限
    }

    /**
     * 获取该权限下的所有权限节点
     * @param string $code
     * @author xiaowen
     * @return array
     */
    protected function getChildCode($code = '')
    {
        if ($code) {
            $app = $this->getAppName();
            $id = D('Node')->where(['node_code' => $code, 'app' => $app])->getField('id');
            //echo $id;
            $childNode = D('Node')->where(['pid' => $id, 'is_show' => 1])->select();
            $data = [];
            if ($childNode) {
                foreach ($childNode as $key => $value) {
                    $data[$value['app']][$value['type']][] = $value['node_code'];
                }
            }
            return $data;
        }
    }

    /**
     * 获取用户某节点下的权限
     * @param string $code
     * @return array
     * @author xiaowen
     * @time 2017-6-5
     */
    protected function getUserAccessNode($code = ''){
        if($code){

            $code_info = D('Node')->field('id, node_code, node_name')->where(['node_code'=>$code])->find();
            $data = D('role_node')->field('n.id, n.node_code, n.node_name')
                ->alias('rn')->where(['n.app'=>$this->getAppName(), 'rn.role_id'=>['in' , $this->roles], 'n.pid'=>$code_info['id'],'r.app'=>$this->getAppName()])
                ->join('oil_node as n on n.id = rn.node_id', 'left')
                ->join('oil_role as r on r.role_id = rn.role_id', 'left')
                ->select();
            return array_column($data, 'node_code');
        }
    }
//    /**
//     * 返回Model对象
//     * @param string $model 对象名称
//     * @param string $model
//     * @return \Model|\Think\Model
//     */
//    protected function getModel($model = ''){
//        return D($model);
//    }

    /**
     * 返回Event对象
     * @param string $event 对象名称
     * @param string $module 所在模块
     * @return \Controller|false|Controller
     */
    protected function getEvent($event = '', $module='Common'){
        return A($module.'/'.$event, 'Event');
    }

    /**
     * 检查是跳转系统升级页
     * @author xiaowen
     * @time 2018-11-15
     */
    public function checkSysUpgrade(){
        //echo getConfig('sys_upgrade');exit();
        if(getConfig('sys_upgrade') == 1){
            $this->redirect("Public/sysUpgrade");
            exit();
        }
    }
}