<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * Created by PhpStorm.
 * User: xiaowen
 * Date: 2016/8/3
 * Time: 10:56
 */
class DealerModel extends BaseModel
{
    /**
     * 返回交易员列表
     * @param bool|true $field
     * @param bool|true $field
     * @param array $where
     * @param string $order
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getDealerList($field = true, $where = [], $order = '', $offset = 0, $limit = 0)
    {
        //echo $field;
        return $this->getList('__DEALER__', $field, $where, $order, $offset, $limit);
    }

    public function getLimitRows()
    {
        return $this->limit(10)->select();
    }

    /**
     * 返回后台员工所属的角色
     * @param int $uid
     * @param string $app
     * @return array|mixed
     */
    public function getRoles($uid = 0, $app = 'front')
    {
        $roleIds = [];
        if ($uid) {
            $roleIds = M('admin_role')->where(['admin_id' => intval($uid)])->getField('role_id', true);
            if(count($roleIds) > 0){
                $roleIds = M('role')->where(['role_id' => ['in', $roleIds], 'app' => $app])->getField('role_id', true);
            }else{
                $roleIds = [];
            }

        }
        //var_dump($roleIds);die;
        return $roleIds;
    }

    /**
     * 返回后台员工所属的角色
     * @param int $uid
     * @return array|mixed
     */
    public function getRolesName($uid = 0)
    {
        $roleIds = [];
        if ($uid) {
            $roleIds = M('admin_role')->where(['admin_id' => intval($uid)])->getField('role_name', true);
        }
        return $roleIds;
    }

    public function selectData($field = '*', $where = [])
    {
        return M('dealer')->field($field)->where($where)->order('id DESC')->select();
    }
}
