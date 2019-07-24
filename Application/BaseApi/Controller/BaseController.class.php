<?php
namespace BaseApi\Controller;

use Think\Controller;
use Common\Controller\BaseController as CommonBaseController;
use Think\Log;

class BaseController extends CommonBaseController
{
    public function __construct()
    {
        $request = [
            "time" => date("Y-m-d H:i:s") ,
            "data" => $_REQUEST,
        ];
        Log::write(json_encode($request));
    }
    public function getEvent($event = '', $module='Common'){
        return A($module.'/'.$event, 'Event');
    }
}