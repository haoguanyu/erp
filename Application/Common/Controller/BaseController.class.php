<?php
namespace Common\Controller;

use Think\Controller;

class BaseController extends Controller
{

    /**
     * 返回Model对象
     * @param string $model 对象名称
     * @param string $model
     * @return \Model|\Think\Model
     */
    protected function getModel($model = ''){
        return D($model);
    }

    /**
     * 返回Event对象
     * @param string $event 对象名称
     * @param string $module 所在模块
     * @return \Controller|false|Controller
     */
    protected function getEvent($event = '', $module='Common'){
        return A($module.'/'.$event, 'Event');
    }
    protected function echoJson($data)
    {
        echo $this->ajaxReturn($data);
        exit;
    }

}