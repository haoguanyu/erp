<?php
namespace Home\Controller;
/**
 *上传文件、图片控制器
 * @author xiaowen
 * @time 2017-3-20
 */
class UploadController extends BaseController
{
    /**
     * 图片上传处理方法：配合webuploader上传插件
     * @author xiaowen
     * @time 2016-10-20
     */
    public function uploadFile()
    {

        /**
         * webuploader 上传插件，后台文件上传处理方法
         */
        #!! 注意
        #!! 此文件只是个示例，不要用于真正的产品之中。
        #!! 不保证代码安全性。
        // Make sure file is not cached (as it happens for example on iOS devices)
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        // Support CORS
        // header("Access-Control-Allow-Origin: *");
        // other CORS headers if any...
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit; // finish preflight CORS requests here
        }

        if (!empty($_REQUEST['debug'])) {
            $random = rand(0, intval($_REQUEST['debug']));
            if ($random === 0) {
                header("HTTP/1.0 500 Internal Server Error");
                exit;
            }
        }

        // 5 minutes execution time
        @set_time_limit(5 * 60);

        // Get a file name
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }
        //切分图片名与图片类型
        $file_info = explode('.', $fileName);
        $type = $file_info[1];
        //前端定义的上传图片的业务类型： clients_cts 公司证件
        $upload_type = $_REQUEST['upload_type'];

        $upload_type_array = [
            'orderPayImg' => $this->uploads_path['order_img']['src'],
            'purchase' => $this->uploads_path['purchase_attach']['src'],
            'sale' => $this->uploads_path['sale_attach']['src'],
            'returned' => $this->uploads_path['returned_attach']['src'],
            'allocation' => $this->uploads_path['allocation_attach']['src'],
        ];
        if(trim($upload_type) && isset($upload_type_array[$upload_type]) && $upload_type_array[$upload_type]){
//            print_r($upload_type_array);
//            echo $upload_type;
//            echo $upload_type_array[$upload_type];
            $upload_dir = $upload_type_array[$upload_type];
        }

        if (!is_dir($upload_dir)) {

            mkdir($upload_dir, 0777, true);
        }
        $current_date = date('Y-m-d');
        if (!is_dir($upload_dir . $current_date)) {
            mkdir($upload_dir . $current_date, 0777, true);
        }
        $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
        if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
            $this->echoJson(['status' => 2, 'error' => '上传文件有误']);
        }
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
            $this->echoJson(['status' => 3, 'error' => '上传失败']);
        } else {
            $this->echoJson(['status' => 1, 'error' => '', 'file_url' => $file_name, 'file_type' => $file_info[0]]);
        }
    }
}
