<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/16
     * Time: 15:18
     */
    namespace Crons\Controller;

    use Think\Controller;
    use Common\Controller\BaseController as CommonBaseController;

    //继承公用模块base控制器
    class BaseController extends CommonBaseController
    {
        public function __construct()
        {
            parent::__construct();
        }

        /**
         * @desc 重写Common模块getEvent方法，本模块调用自己的Event
         * @author xiaowen
         * @time 2019-1-30
         * @param string $event 对象名称
         * @param string $module 所有模块
         * @return object
         */
        public function getEvent($event = '', $module = '')
        {
            //默认调用当前模块的event
            $module = empty(trim($module)) ? MODULE_NAME : $module;

            return A($module.'/'.$event, 'Event');
        }

    }