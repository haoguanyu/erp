<?php
/**
 * 公司管理控制器
 * @author xiaowen
 * @time 2016-10-11
 */
namespace Common\Event;

use Think\Controller;
use Home\Controller\BaseController;

class DepotEvent extends BaseController
{

    public function _initialize()
    {
        $this->time = date('Y-m-d H:i:s', time());
    }

    /**
     *油库列表
     *Return: array[]
     *DATE:2016-11-23 Time:11:00
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function depotList()
    {
        $data = $this->getModel('Depot')->depotList();
        $s_c = CityToPro();
        foreach ($data as $k => $v) {
            $data[$k]['area_s'] = $s_c['cityid2provincename'][$data[$k]['depot_area']];
            $data[$k]['area_c'] = $s_c['cityid2cityname'][$data[$k]['depot_area']];
        }
        return $data;
    }

    /**
     *删除油库
     *DATE:2016-11-23 Time:11:11
     *Return: array[]
     *Author: lizhipeng <lizhipeng@51zhaoyou.com>
     */
    public function deleteDepot($param)
    {
        $where['id'] = array('eq', intval($param['id']));
        $result = $this->getModel('Depot')->deleteDepot($where);
        if (!$result) {
            return ['status' => 2, 'message' => '删除失败！'];
        } else {
            return ['status' => 1, 'message' => '删除成功！'];
        }
    }
    /*
    *  @params:
    *      data:
    *         field:查询的字段
     *        where:查询条件
    *  @return :
    *  @desc:获取油库的信息
    *  @author:小黑
    *  @time:2019-2-19
    */
    public function getListField($field , $where){
        $returnData = $this->getModel("Depot")->getListField($field , $where);
        return $returnData ;
    }
    /*
    *  @params:
    *      data:
    *         field:查询的字段
     *        where:查询条件
    *  @return :
    *  @desc:获取油库的信息
    *  @author:小黑
    *  @time:2019-2-19
    */
    public function getField($field , $where){
        $returnData = $this->getModel("Depot")->getInfoField($field , $where);
        return $returnData ;
    }
}
