<?php
namespace Api\Controller;

use Think\Controller;
use Api\Controller\BaseController;

class NodeController extends BaseController
{

    public function getList()
    {
        $where = [];
        $where['app'] = $this->getAppName();
        $data['data'] = D('Node')->where($where)->order('id desc')->select();
        $this->echoJson($data);
    }

    public function addNode()
    {
        $data = I('param.');
        $this->checkInfo($data);
        $data = $this->trimArrayData($data);
        $data['create_at'] = DateTime();
        $data['app'] = $this->getAppName();
        $status = D('Node')->add($data);
        if ($status) {
            $this->echoSuccess('权限添加成功');

        } else {
            $this->echoError('权限添加失败');
        }
    }

    public function editNode()
    {
        $data = I('param.');
        $this->checkInfo($data, 0);
        $data = $this->trimArrayData($data);
        $data['update_at'] = DateTime();
        $status = D('Node')->save($data);
        if ($status) {
            $this->echoSuccess('权限编辑成功');
        } else {
            $this->echoError('权限编辑失败');
        }
    }

    /**
     * 检验参数是否有误
     * @param $data
     * @param int $is_add
     */
    protected function checkInfo($data, $is_add = 1)
    {
        if (!$is_add) {
            if (!trim($data['id'])) {
                $this->echoError('参数有误，ID无法获取');
            }
        }
        if (!trim($data['node_name'])) {
            $this->echoError('权限名称不能为空');
        }
        if (!trim($data['node_code'])) {
            $this->echoError('权限码不能为空');
        }

    }

    protected function trimArrayData($data){
        foreach($data as $key=>$value){
            $data[$key] = strHtml($value);
        }
        return $data;
    }
}