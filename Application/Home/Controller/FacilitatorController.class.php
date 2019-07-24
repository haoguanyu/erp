<?php
namespace Home\Controller;
use Think\Controller;
use Home\Controller\BaseController;

class FacilitatorController extends BaseController {
    // +----------------------------------
    // |Facilitator:服务商控制器
    // +----------------------------------
    // |Author:lizhipeng Time:2016.10.31
    // +----------------------------------

    // +----------------------------------
    // |Facilitator:根据地区获取服务商
    // +----------------------------------
    // |Author:senpai Time:2017.05.11
    // +----------------------------------
    public function getFacilitatorByRegion()
    {
        if (IS_AJAX) {
            $param = $_POST['region'];
            $data = $this->getEvent('Facilitator')->getFacilitatorByRegion($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:根据地区获取服务商
    // +----------------------------------
    // |Author:senpai Time:2017.05.11
    // +----------------------------------
    public function getFacilitatorSkidByRegion()
    {
        if (IS_AJAX) {
            $param = $_POST;
            $data = $this->getEvent('Facilitator')->getFacilitatorSkidByRegion($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:根据服务商获取加油网点
    // +----------------------------------
    // |Author:senpai Time:2017.07.13
    // +----------------------------------
    public function getSkidByFacilitator()
    {
        if (IS_AJAX) {
            $param = $_POST['facilitator_id'];
            $data = $this->getEvent('Facilitator')->getSkidByFacilitator($param);
            $this->echoJson($data);
        }
    }

    // +----------------------------------
    // |Facilitator:获取服务商列表
    // +----------------------------------
    // |Author:qianbin Time:2018.03.05
    // +----------------------------------
    public function getFacilitatorList()
    {
        $param = strHtml(I('param.name', ''));
        if (IS_AJAX) {
            $data['data'] = $this->getEvent('Facilitator')->getFacilitatorList($param);
            if (count($data['data'])) {
                $data['incomplete_results'] = true;
                $data['total_count'] = count($data['data']);
            } else {
                $data['data'] = [];
                $data['incomplete_results'] = true;
                $data['total_count'] = 0;
            }
            $this->echoJson($data);
        }
    }

}
