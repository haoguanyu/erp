<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/3/13
 * Time: 11:33
 */

namespace Common\Event;
use Home\Controller\BaseController;

class ErpImageEvent extends BaseController
{
    public $uploads_path;
    /*
     * @params :file
     * @return :url
     * @desc:图片上传
     * @auth:小黑
     * @time:2019-3-13
     */
    public function uploadImage($files){
        $upload_status = true;
        $error_photo = [];
        $attachment = [];
        $file_name = "";
        if (!empty($files)) {
            //验证文件大小
            foreach ($files as $key => $value) {
                if ($value) {
                    if($value['size'] > 2*1024*1024) {
                        $result = [
                            'status' => 4,
                            'message' => '文件过大，不能上传大于2M的文件'
                        ];
                        return $result;
                    }
                } else {
                    continue;
                }
            }

            //上传文件
            foreach ($files as $key => $value) {
                $uploaded_file = $value['tmp_name'];
                $user_path = $this->uploads_path['stock_out_attach']['src'];
                //判断该用户文件夹是否已经有这个文件夹
                if (!file_exists($user_path)) {
                    mkdir($user_path, 0777, true);
                }
                $current_date = date('Y-m-d');
                if (!is_dir($user_path . $current_date)) {
                    mkdir($user_path . $current_date, 0777, true);
                }
                $current_date = date('Y-m-d');
                //后缀
                $type = substr($value['name'],strripos($value['name'],'.')+1);
                $file_name = $current_date . '/' . date('YmdHis') . mt_rand(1, 1000) . ".{$type}";
                $upload_status = move_uploaded_file($uploaded_file, $user_path . $file_name);
                //已上传文件，如果操作失败要删除
                array_push($error_photo,$file_name);
                //拼装新增数据
                $attachment[$key] = $file_name;
            }
        }
        if(!$upload_status){
            foreach ($error_photo as $value) {
                unlink($user_path.$value);
            }
            return ['status' => 11, 'message' => '图片上次失败'];
        }
        return $file_name;
    }
}