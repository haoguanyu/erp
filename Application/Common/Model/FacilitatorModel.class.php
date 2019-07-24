<?php
namespace Common\Model;

use Common\Model\BaseModel;

// +----------------------------------
// |Facilitator:服务商数据库操作模型
// +----------------------------------
// |Author:lizhipeng Time:2016.10.31
// +----------------------------------
class FacilitatorModel extends BaseModel
{

    // +----------------------------------
    // |Facilitator:服务商列表
    // +----------------------------------
    // |Author:lizhipeng Time:2016.10.31
    // +----------------------------------
    public function facilitator_list($where = '', $limit = '', $field = '', $order = '')
    {
        if (count($where) <= 0) return [];
        $data = D('facilitator')->where($where)->field($field)->order($order)->limit($limit)->select();
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商user表
    // +----------------------------------
    // |Author:lizhipeng Time:2016.10.31
    // +----------------------------------
    public function facilitator_user_list($where = '', $limit = '', $field = '', $order = '')
    {
        if (count($where) <= 0) return [];
        $data = D('facilitator_user')->where($where)->field($field)->order($order)->limit($limit)->select();
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商添加
    // +----------------------------------
    // |Author:lizhipeng Time:2016.10.31
    // +----------------------------------
    public function add_facilitator($data)
    {
        if (count($data) <= 0) return [];
        $data = D('facilitator')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商user表添加
    // +----------------------------------
    // |Author:lizhipeng Time:2016.10.31
    // +----------------------------------
    public function add_facilitator_user($data)
    {
        if (count($data) <= 0) return [];
        $data = D('facilitator_user')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商user表更新
    // +----------------------------------
    // |Author:lizhipeng Time:2016.11.1
    // +----------------------------------
    public function save_facilitator_user($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('facilitator_user')->where($where)->save($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商skid表
    // +----------------------------------
    // |Author:senpai Time:2017.3.3
    // +----------------------------------
    public function facilitator_skid_list($where = '', $limit = '', $field = '', $order = '')
    {
        if (count($where) <= 0) return [];
        $data = D('facilitator_skid')->where($where)->field($field)->order($order)->limit($limit)->select();
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商skid表添加
    // +----------------------------------
    // |Author:senpai Time:2017.3.3
    // +----------------------------------
    public function add_facilitator_skid($data)
    {
        if (count($data) <= 0) return [];
        $data = D('facilitator_skid')->add($data);
        return $data;
    }

    // +----------------------------------
    // |Facilitator:服务商skid表更新
    // +----------------------------------
    // |Author:senpai Time:2017.3.3
    // +----------------------------------
    public function save_facilitator_skid($where, $data)
    {
        if (count($where) <= 0 || count($data) <= 0) return [];
        $data = D('facilitator_skid')->where($where)->save($data);
        return $data;
    }
}
