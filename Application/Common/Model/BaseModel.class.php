<?php
namespace Common\Model;

use Think\Model\RelationModel;

/**
 * 模型基类
 * 预留
 */
class BaseModel extends RelationModel
{

    public function getList($table = '', $field = true, $where = [], $order = '', $offset = 0, $limit = 0, $join = [])
    {
        $data = [];
        if ($table) {
            $data = M()->table($table)->field($field)->where($where)->order($order)->limit($offset, $limit)->select();
        }

        return $data;
    }
}