<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 模型基类
 * 预留
 */
class ClientsModel extends BaseModel
{

    /**
     * 获取order表数据
     * @author May
     * @return array
     */
    public function getClientsinfo($where = "1=1")
    {
        $orderObj = M('clients');
        $orderObj->where($where);
        $reg = $orderObj->find();
        return $reg;
    }

    public function getClients($item, $where = "1=1")
    {
        $result = M('clients')->field($item)->where($where)->select();
        return $result;
    }

    public function getClientsLim($item, $where = "1=1", $num = 5 , $orderBy= "")
    {
        $result = M('clients')->field($item)->where($where) ;
        if($orderBy){
            $result .= $result->order($orderBy) ;
        }
        $result = $result->limit($num)->select();
        return $result;
    }

    public function getClientsItem($item, $where = "1=1")
    {
        $result = M('clients')->field($item)->where($where)->find();
        return $result;
    }

    ////////////////////////////////////基础层////////////////////////////////////////////

    public function selectData($field = '*', $where = [])
    {
        return M('Clients')->field($field)->where($where)->order('id DESC')->select();
    }

    public function findData($where = [])
    {
        if (empty($where)) return [];

        return M('Clients')->where($where)->find();
    }

    public function findField($where = [], $field = '', $isArray = false)
    {
        if (empty($where)) return [];

        return M('Clients')->where($where)->getField($field, $isArray);
    }
/////////////////////////////////end 基础层///////////////////////////////////////

    /**
     * 返回公司列表
     * @param array $where
     * @param bool|false $limit
     * @param array $order
     * @return array
     */
    public function getClientsList($where = [], $limit = false, $order = array('c.id' => 'desc'))
    {

        $obj = M();
        $obj->table('__CLIENTS__ as c')->field('c.*,u.dealer_name,u.user_phone,u.user_name')->where($where)->join('__USER__ as u on c.u_id = u.id', 'left')->order($order);
        if ($limit) {
            $obj->limit($limit);
        }
        $data = $obj->select();
        return $data;
    }

    /**
     * 返回公司对应交易员邮箱
     * @param int clients_id
     * @author xiaowen 2016-11-01
     * @return array
     */
    public function getClientsDealerEmail($clients_id)
    {
        if ($clients_id) {

            $dealer_name = $this->getClientsDealerName($clients_id);
            return D('Dealer')->where(['dealer_name' => $dealer_name])->getField('dealer_email');
        }
    }

    /**
     * 返回公司对应交易员姓名
     * @param int clients_id
     * @author xiaowen 2016-11-01
     * @return array
     */
    public function getClientsDealerName($clients_id)
    {
        $u_id = $this->where(['id' => $clients_id, 'is_available' => 0])->getField('u_id');

        $dealer_name = M('User')->where(['id' => $u_id, 'is_available' => 0])->getField('dealer_name');
        return $dealer_name;
    }

}
