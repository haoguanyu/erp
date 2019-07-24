<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/16
     * Time: 15:31
     */
    namespace Crons\Event;
    use Crons\Controller\BaseController;

    class BaseEvent extends BaseController
    {
        public function __construct()
        {
        }

        //crons记录报告 type 类型 msg 提示信息 data [company_id,old_data,new_data,request_data]
        public function reportLog($type,$data,$msg='')
        {
            $add_log = [
                'company_id'     => isset($data['company_id'])?$data['company_id']:0,
                'company_type'   => $type,
                'old_data'       => json_encode($data['old_data']==null?[]:$data['old_data'],JSON_UNESCAPED_UNICODE),
                'new_data'       => json_encode($data['new_data'],JSON_UNESCAPED_UNICODE),
                'request_data'       => json_encode($data['request_data'],JSON_UNESCAPED_UNICODE),
                'create_time'    => date('Y-m-d H:i:s',time()),
                'msg'            =>$msg,
            ];
            $this->getModel('ErpSupplierCustomerLog')->addSupplierCustomerLog($add_log);
        }

        //生成新零售的签名
        public function makeSign()
        {
            require_once APP_PATH .'Crons/Lib/Rsa.php';
            $signStr = json_encode([
                'source'=> 'erp',
                'timestamps'=> time(),
                'openId'     => C('OMS_OPENID')
            ]);
            $rsa=new \Rsa(C('NewRetailPublicKey'));
            $rsaData = $rsa::encrypt($signStr);
            $signStr = $rsa::urlsafeB64Encode($rsaData);
            return $signStr;
        }
    }