<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 */
class NodeModel extends BaseModel
{
    protected $_auto = array(
        array('create_time', 'addDateTime', 3, 'function'),
    );

    //自动完成调用方法
    protected function addDateTime()
    {
        return date("Y-m-d H:i:s", time());
    }
}