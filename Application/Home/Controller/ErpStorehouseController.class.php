<?php
namespace Home\Controller;

use Think\Controller;
use Home\Controller\BaseController;

class ErpStorehouseController extends BaseController
{

    // +----------------------------------
    // |Facilitator:ERP仓库逻辑层
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------

    // +----------------------------------
    // |Facilitator:仓库列表页面及数据
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function erpStorehouseList()
    {
        if (IS_AJAX) {
            $param = $_REQUEST;
            $data = $this->getEvent('ErpStorehouse')->erpStorehouseList($param);
            $this->echoJson($data);
        }
        $data['regionList'] = provinceCityZone()['city'];
        $data['erpStorehuseStatus'] = erpStorehouseStatus();
        $data['erpStorehuseType'] = erpStorehouseType();
        $this->assign("data", $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:添加erp仓库页面
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function showAddErpStorehouse()
    {
        $data['erpStorehuseStatus'] = erpStorehouseStatus();
        $data['erpStorehuseType'] = erpStorehouseType();
        $data['region'] = provinceCityZone()['city'];
        $this->assign("data", $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:添加erp仓库操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function actAddErpStorehouse()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpStorehouse')->actAddErpStorehouse($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:编辑erp仓库页面
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function showUpdateErpStorehouse()
    {
        $id = intval(I('id', '', 'htmlspecialchars'));
        $data = $this->getEvent('ErpStorehouse')->showUpdateErpStorehouse($id);
        $data['erpStorehuseStatus'] = erpStorehouseStatus();
        $data['erpStorehuseType'] = erpStorehouseType();
        $data['region_list'] = provinceCityZone()['city'];
        $this->assign("id", $id);
        $this->assign("data", $data);
        $this->display();
    }

    // +----------------------------------
    // |Facilitator:编辑erp仓库操作
    // +----------------------------------
    // |Author:senpai Time:2017.3.24
    // +----------------------------------
    public function actUpdateErpStorehouse()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('ErpStorehouse')->actUpdateErpStorehouse($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:通过城市id获取仓库
    // +----------------------------------
    // |Author:senpai Time:2017.05.11
    // +----------------------------------
    public function getStorehouseByRegion()
    {
        if (IS_AJAX) {
            $region = $_REQUEST['region'];
            $storehouse_type = $_REQUEST['type'];
            $data = $this->getEvent('ErpStorehouse')->getStorehouseByRegion($region,0,$storehouse_type);
            $this->echoJson($data);
        }
    }

    /**
     * 导入仓库数据
     * @author xiaowen
     * @time 2017-6-2
     */
    public function importStorehouseData(){

        $city = provinceCityZone()['city'];
        $data = [];
        $i = 0;
        //$typeArr = [1 => '仓', 2=>'代采'];
        //初始化零售仓 edit xiaowen
        //$typeArr = [3 => '零售仓'];
        $typeArr = [8 => '损耗仓'];
        foreach($city as $key=>$value){
            foreach($typeArr as $k=>$v){
                $data[$i]['storehouse_name'] = $value.$v;
                $data[$i]['region'] = $key;
                $data[$i]['type'] = $k;
                $data[$i]['is_sale'] = 2;
                $data[$i]['is_purchase'] = 2;
                $data[$i]['is_allocation'] = 2;
                $data[$i]['create_time'] = currentTime();
                $data[$i]['creater'] = 142;
                $data[$i]['remark'] = "初始化生成损耗仓";
                $i++;
            }

        }

        $status = $this->getModel('ErpStorehouse')->addAll($data);
        $status_str = $status ? '成功' : '失败';
        echo '数据全部导入' . $status_str;
    }
}