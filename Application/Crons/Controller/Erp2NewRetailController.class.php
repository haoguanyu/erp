<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/16
     * Time: 15:21
     * 同步新零售公司、公司人员、供应商、供应商人员、服务网点信息
     */
    namespace Crons\Controller;

    class Erp2NewRetailController extends BaseController
    {
        public function __construct()
        {
            parent::__construct();
        }

        //同步新零售公司及公司人员
        public function syncCustomer()
        {
            $this->getEvent('RyncCustomer')->syncCustomer();
        }

        //同步新零售服务网点信息
        public function syncStorehouse()
        {
            $this->getEvent('RyncStorehouse')->syncStorehouse();
        }

        //同步新零售供应商及供应商人员
        public function syncSupplier()
        {
            $this->getEvent('RyncSupplier')->syncSupplier();
        }
    }