<?php
namespace Common\Model;

use Common\Model\BaseModel;

/**
 * 发票模型
 */
class ErpStockLogModel extends BaseModel
{

    /**
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param int $offset
     * @param string $order
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockLogList($where = [], $field = true, $offset = 0, $limit = 10, $order = 'i.id desc')
    {
        $StockLogObj = M('ErpStockLog');
        $data['recordsTotal'] = $this->getStockLogCount($where);
        $data['data'] = $StockLogObj
            ->field($field)
            ->where($where)
            ->limit($offset, $limit)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 返回总条数
     * @param array $where
     * @return mixed
     * @author senpai
     * @time 2017-03-31
     */
    public function getStockLogCount($where = [])
    {
        return $this->where($where)->count();
    }

    /**
     *  修改保存发票
     * @param array $where
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function saveStockLog($where = [], $data = [])
    {
        return $this->where($where)->save($data);
    }

    /**
     * 添加发票
     * @param array $data
     * @return bool
     * @author senpai
     * @time 2017-03-31
     */
    public function addStockLog($data = [])
    {
        return $this->add($data);
    }

    /**
     * 获取一条发票信息
     * @param $where
     * @return array
     * @author senpai
     * @time 2017-03-31
     */
    public function findStockLog($where = [])
    {
        return $this->where($where)->find();
    }

}
