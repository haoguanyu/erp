<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 */
class RoleModel extends BaseModel
{
    protected $_auto = array(
        array('create_at', 'addDateTime', 3, 'function'),
        array('update_at', 'addDateTime', 3, 'function'),
    );
    protected $pk = 'role_id';

    /**
     * 获取角色的菜单/操作节点
     * @param int $role_id
     * @param int $type
     * @return array|mixed
     */
    public function getRoleNodeList($role_id = 0, $type = 0)
    {
        $data = [];
        if ($role_id) {
            if (is_numeric($role_id) && $role_id > 0) {
                $where['role_id'] = intval($role_id);
            } else if (is_array($role_id) && !empty($role_id)) {
                $where['role_id'] = array('in', $role_id);

            }
            $nodeIdArr = M('role_node')->distinct(true)->where($where)->getField('node_id', true);
            if (!empty($nodeIdArr)) {
                $conditions = [
                    'id' => ['in', $nodeIdArr],
                    'is_show' => 1,
                    'app' => 'erp',
                ];
                if ($type) {
                    $conditions['type'] = $type;
                }
                $data = D('Node')->where($conditions)->select();
            }
            //var_dump($conditions); 
            // var_dump($data);//die;
            return $data;

        }

        return $data;
    }

    /**
     * 获取角色的所有权限码
     * @param int $roles
     * @return array|mixed
     */
    public function getRolePermissionAll($roles = 0)
    {
        $data = [];
        if ($roles) {
            $permission = $this->getRoleNodeList($roles);
            foreach ($permission as $k => $v) {
                $data[$v['id']] = $v['node_code'];
            }
        }
        return $data;
    }

    public function getRoleNodeAll($roles = 0)
    {
        $data = [];
        if ($roles) {
            $data = $this->getRoleNodeList($roles);
        }
        return $data;
    }

    //自动完成调用方法
    protected function addDateTime()
    {
        return date("Y-m-d H:i:s", time());
    }

}