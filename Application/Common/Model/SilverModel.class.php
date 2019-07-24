<?php
namespace Common\Model;

use Common\Model\BaseModel;


class SilverModel extends BaseModel
{


    public function _initialize()
    {
        $this->silver = M('silver');
    }


//    public function addAll($data = []){
//        if(count($data) <= 0)return false;
//
//        return $this->silver->addAll($data);
//    }

    public function selectData($where = [])
    {
        return $this->silver->where($where)->order('id desc')->select();
    }

    //返回交易流水列表
    public function getDataList($where = [])
    {
        $data['recordsTotal'] = $this->silver->where($where)->count();
        $data['data'] = $this->silver->where($where)->order('id desc')->limit($_REQUEST['start'], $_REQUEST['length'])->select();
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $_REQUEST['draw'];
        return $data;

    }


}
