<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2018/12/6
 * Time: 11:26
 */

namespace Crons\Controller;

class FinanceMiddleGroundController extends BaseController
{

    /**
     * 申请发票
     * @author guanyu
     * @time 2019-06-18
     */
    public function fmgApplicationInvoice()
    {
        $this->getEvent('FinanceMiddleGround')->fmgApplicationInvoice();
    }

    /**
     * 接收发票回传信息
     * @author guanyu
     * @time 2019-06-18
     */
    public function fmgReceiveInvoicePostBack()
    {
        //获取头部信息
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        //获取参数
        if (empty($_POST) && false !== strpos($headers['Content-Type'], 'application/json')) {
            $content = file_get_contents('php://input');
            $params = (array)json_decode($content, true);
        } else {
            $params = $_POST;
        }
        //验证token
        $time = $headers['Time'];
        $app_key = C('FINANCE_APP_KEY');
        $app_secret = C('FINANCE_APP_SECRET');
        $token = md5(strtoupper($app_secret.$time.$app_key.json_encode($params).$app_secret));

        log_info(json_encode($headers));
        log_info($token);
        if ($token != $headers['Token']) {
            $result = [
                "code" => 1001,
                "message" => "tokon验证失败",
                "data" => [],
            ];
            return json_encode($result);
        }

        $result = $this->getEvent('FinanceMiddleGround')->fmgReceiveInvoicePostBack($params);

        echo json_encode($result);exit;
    }
}