<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpBatchEvent extends BaseController
{

    /**************************************
        @ Content 修改批次 and 货权
        @ Author YF
        @ Time 2010-02-21
        @ Param [
            $param [array] => [
                'batch_id'      => 货权ID，
                'cargo_bn_type' => 货权类型，
                'cargo_bn'      => 货权号，
             ]
        ]
    ***************************************/
    public function updateBatchAndCargo($param)
    {
        $batch_id      = trim($param['batch_id']);
        $cargo_bn_type = trim($param['cargo_bn_type']);
        $cargo_bn      = trim($param['cargo_bn']);
        /* ------------- 获取货权ID ---------------- */
        $batch_arr = $this->getModel('ErpBatch')->where(['id' => ['eq',$batch_id]])->field('cargo_bn_id,sys_bn')->find();
        if ( $batch_arr['cargo_bn_id'] == 1 ) {
            return ['status' => 11 ,'message' => '货权初始化的批次不允许编辑！'];
        }
        if ( !isset($batch_arr['cargo_bn_id']) ) {
            return ['status' => 6 ,'message' => '未获取到 货权ID'];
        }
        /* ---------------- 获取OID 货权信息 ------------------- */
        $old_cargo_bn_arr = $this->getModel('ErpCargoBn')->where(['id'=>['eq',$batch_arr['cargo_bn_id']]])->field('cargo_bn_type,cargo_bn,id')->find();
        if ( !isset($old_cargo_bn_arr['cargo_bn_type']) ) {
            return ['status' => 7 ,'message' => '未获取到 老的货权信息！'];
        }
        /* ---------------- 查看是否更改 货权信息 ------------------- */
        if ( $old_cargo_bn_arr['cargo_bn_type'] == $cargo_bn_type && $old_cargo_bn_arr['cargo_bn'] == $cargo_bn ) {
            return ['status' => 8, 'message' => '无更改信息！'];
        }

        /* ------------ 查看是否有一致的 货权信息 -------------------- */
        $cargo_bn_where['cargo_bn_type'] = ['eq',$cargo_bn_type];
        $cargo_bn_where['cargo_bn']      = ['eq',$cargo_bn];
        $cargo_bn_arr = $this->getModel('ErpCargoBn')->where($cargo_bn_where)->find();

        /* ------ 查看是否有不同的批次号使用该货权 -------------- */
        $batch_where['cargo_bn_id'] = ['eq',$old_cargo_bn_arr['id']];
        $batch_where['sys_bn']      = ['neq',$batch_arr['sys_bn']];
        $find_batch_status = $this->getModel('ErpBatch')->where($batch_where)->find();
        /* - 开启事务 -*/
        M()->startTrans();
        /* - 如果不存在 别的批次号对应此货权信息并且新的货权信息表中没有 就进行修改货权信息 - */
        if ( !isset($find_batch_status['sys_bn']) && !isset($cargo_bn_arr['id']) ) {
            $update_cargo_arr = [
                'cargo_bn_type' => $cargo_bn_type,
                'cargo_bn'      => $cargo_bn,
            ];
            $cargo_bn_id = $old_cargo_bn_arr['id'];
            $update_cargo_bn_status = $this->getModel('ErpCargoBn')->where(['id' => ['eq',$cargo_bn_id]])->save($update_cargo_arr);
            if ( !$update_cargo_bn_status ) {
                M()->rollback();
                return ['status' => 10,'message' => '货权信息修改失败！'];
            }
        }

        /* - 如果货权信息 对应着不同的批次号 且 新的货权信息 不存在 货权表中 - */
        if ( isset($find_batch_status['sys_bn']) && !isset($cargo_bn_arr['id']) )  {
            $cargo_bn_insert = [
                'cargo_bn_type' => $cargo_bn_type,
                'cargo_bn'      => $cargo_bn,
                'creater'       => $this->user_name,
                'create_time'   => nowTime(),
            ];
            $insert_id_end = $this->getModel('ErpCargoBn')->add($cargo_bn_insert);
            if ( !$insert_id_end ) {
                M()->rollback();
                return ['status' => 5, 'message' => '货权添加失败！'];
            }
            $update_batch_arr = [
                'cargo_bn_id' => $insert_id_end,
                'update_time' => nowTime(),
            ];
            $cargo_bn_id = $insert_id_end;
        }

        /* - 如果货权号没有被别的批次号所使用 且 存在 新编辑的货权信息 ，删除老的货权信息，更换存在的货权ID -*/
        if ( !isset($find_batch_status['sys_bn']) && isset($cargo_bn_arr['id']) ) {
            // 别问我为什么使用delete，不做解释！
            $delete_cargo_status = $this->getModel('ErpCargoBn')->where('id = '.$old_cargo_bn_arr['id'])->delete();
            if ( !$delete_cargo_status ) {
                M()->rollback();
                return ['status' => 11,'message' => '货权信息删除失败！'];
            }
            $update_batch_arr = [
                'cargo_bn_id' => $cargo_bn_arr['id'],
                'update_time' => nowTime(),
            ];
            $cargo_bn_id = $cargo_bn_arr['id'];
        }

        /* - 如果货权信息对应不同的批次号且新的货权信息存在货权表中 就更换 批次信息 下的 已有 货权ID - */
        if ( isset($find_batch_status['sys_bn']) && isset($cargo_bn_arr['id']) ) {
            $update_batch_arr = [
                'cargo_bn_id' => $cargo_bn_arr['id'],
                'update_time' => nowTime(),
            ];
            $cargo_bn_id = $cargo_bn_arr['id'];
        }

        if ( isset($update_batch_arr) && !empty($update_batch_arr) ) {
            $batch_save_status = $this->getModel('ErpBatch')->where(['sys_bn' => ['eq',$batch_arr['sys_bn']]])->save($update_batch_arr);
            if ( !$batch_save_status ) {
                M()->rollback();
                return ['status' => 9,'message' => '更新批次记录失败！'];
            }
        }
        /* ------------- 修改入库单所使用的批次货权 --------------- */
        $stock_in_where['batch_id'] = ['eq',$batch_id];
        $stock_in_where['storage_status'] = ['neq',2];
        $stock_arr = $this->getModel('ErpStockIn')->where($stock_in_where)->field('id')->find();
        if ( $stock_arr ) {
            $batch_arrs = $this->getModel('ErpBatch')->where(['id'=>['eq',$batch_id]])->find();
            $save_stock_in_arr = [
                'batch_sys_bn' => $batch_arrs['sys_bn'],
                'cargo_bn_id'  => $batch_arrs['cargo_bn_id'],
                'update_time'  => nowTime()
            ];
            $save_stock_in_status = $this->getModel('ErpStockIn')->where(['id'=>['eq',$stock_arr['id']]])->save($save_stock_in_arr);
            if ( !$save_stock_in_status ) {
                M()->rollback();
                return ['status' => 11,'message' => '入库单批次货权信息 更新失败！'];
            }
        }
        
        /* ------------- 查询 货权类型 ------------*/
        $cargo_bn_type_arr = getConfigByType(2);
        $insert_log_arr = [
            'batch_id'    => $batch_id,
            'cargo_bn_id' => $cargo_bn_id,
            'old_cargo_bn'=> $old_cargo_bn_arr['cargo_bn'],
            'new_cargo_bn'=> $cargo_bn,
            'old_cargo_bn_type' => isset($cargo_bn_type_arr[$old_cargo_bn_arr['cargo_bn_type']]) ? $cargo_bn_type_arr[$old_cargo_bn_arr['cargo_bn_type']]['name'] : '--' ,
            'new_cargo_bn_type' => isset($cargo_bn_type_arr[$cargo_bn_type]) ? $cargo_bn_type_arr[$cargo_bn_type]['name'] : '--' ,
            'operator'          => $this->user_name,
            'create_time'       => nowTime(),
        ];
        /* ------ 增加 编辑日志 ------------- */
        $insert_log_result = $this->insterBatchOperateLog($insert_log_arr);
        if ( $insert_log_result['status'] != 0 ) {
            M()->rollback();
            return ['status' => 12 ,'message' => '添加编辑日志失败！'];
        }
        M()->commit();
        return ['status' => 0 ,'message' => '编辑成功！'];
    }

    /*****************************************
        @ Content 添加批次 信息 更改货权信息记录（日志）
        @ Author Yf
        @ Time 2019-02-22
        @ Param [
            $insert_log_arr = [array] => [
                'batch_id'          => 批次ID，
                'cargo_bn_id'       => 货权ID，
                'old_cargo_bn'      => 旧的货权号，
                'new_cargo_bn'      => 新的货权号,
                'old_cargo_bn_type' => 旧的货权类型，
                'new_cargo_bn_type' => 新的货权类型，
                'operator'          => 修改人，
                'create_time'       => 创建日期,
            ]
        ]
        @ Return [
            status | message => [
                13 => 添加编辑日志失败，
                0  => 添加编辑日志成功,
            ]
        ]
    *****************************************/
    public function insterBatchOperateLog( $insert_log_arr = [] ){
        $insert_log_status = $this->getModel('ErpBatchOperateLog')->add($insert_log_arr);
        if ( !$insert_log_status ) {
            return ['status' => 13, 'message' => '添加编辑日志失败！'];
        }
        return ['status' => 0, 'message' => '添加编辑日志成功！'];
    }

    /*****************************************
        @ Content 查询 单条批次信息
        @ Author Yf
    ******************************************/
    public function FindBatch($where = [] ) {
        $batch_arr = $this->getModel('ErpBatch')
        ->alias('o')
        ->field('o.id,o.sys_bn,b.cargo_bn,b.cargo_bn_type,c.goods_code,c.goods_name,c.source_from,c.grade,c.level')
        ->join('oil_erp_goods As c On o.goods_id = c.id')
        ->join('oil_erp_cargo_bn As b On o.cargo_bn_id = b.id')
        ->where($where)
        ->find();
        return $batch_arr;
    }

    /****************************************
        @ Content 查询系统批次 货权修改记录
        @ Author Yf
        @ Time 2019-02-21
        @ Param [
            sys_bn = 系统批次号
        ]
    *****************************************/
    public function getBatchOperateLog($sys_bn) {
        $log_arr = [];
        $batch_where['sys_bn'] = ['eq',$sys_bn];
        $batch_arr = $this->getModel('ErpBatch')->where($batch_where)->field('id')->select();
        if ( !empty($batch_arr) ) {
            $batch_ids = array_column($batch_arr, 'id');
            $batch_operate_log_where['batch_id'] = ['in',$batch_ids];  
            $log_arr = $this->getModel('ErpBatchOperateLog')->where($batch_operate_log_where)->order('id desc')->select();
        }
        return $log_arr;
    }

	/*****************************************
		@ Content 搜索批次表 （模糊查询）
		@ Author YF
		@ Time 2019-02-19
		@ Param = [
				where  [array]   NOT NULL
				field  [sting]   NOT NULL  
		]
		@ Ruturn  = [
				status  :   message  :   data 

				1 ：参数有误  : [] ， 
				0 : 查询成功  : id => 1 ..... ,
		]
	******************************************/
	public function searchBatch($where = [],$field = ''){
		if( !empty($where) && $field != '' ) {
            $field        = $field.',goods_id,cargo_bn_id';
			$batch_arr    = $this->getModel('ErpBatch')->where($where)->field($field)->limit(50)->order('id desc')->select();
            if ( empty($batch_arr) ) {
                return ['status' => 0 ,'message' => '查询成功！','data' => $batch_arr ];
            }
            $goods_ids    = array_column($batch_arr,'goods_id');
            $cargo_bn_ids = array_column($batch_arr,'cargo_bn_id');
            $good_field   = 'id,goods_code,goods_name,source_from,grade,level';
            $goods_arr    = $this->getModel('ErpGoods')->where(['id'=>['in',$goods_ids]])->getField($good_field);
            $cargo_bn_arr = $this->getModel('ErpCargoBn')->where(['id' => ['in',$cargo_bn_ids]])->getField('id,cargo_bn,cargo_bn_type');
            foreach ($batch_arr as $key => $value) {
                if ( isset($goods_arr[$value['goods_id']]) ) {
                    $goods_code  = $goods_arr[$value['goods_id']]['goods_code'];
                    $goods_name  = $goods_arr[$value['goods_id']]['goods_name'];
                    $source_from = $goods_arr[$value['goods_id']]['source_from'];
                    $grade       = $goods_arr[$value['goods_id']]['grade'];
                    $level       = $goods_arr[$value['goods_id']]['level'];
                    $batch_arr[$key]['goods_name'] = $goods_code.'/'.$goods_name.'/'.$source_from.'/'.$grade.'/'.$level;
                } else {
                    $batch_arr[$key]['goods_name'] = '--';
                }

                if ( isset($cargo_bn_arr[$value['cargo_bn_id']]) ) {
                    $batch_arr[$key]['cargo_bn']      = $cargo_bn_arr[$value['cargo_bn_id']]['cargo_bn'];
                    $batch_arr[$key]['cargo_bn_type'] = $cargo_bn_arr[$value['cargo_bn_id']]['cargo_bn_type'];
                } else {
                    $batch_arr[$key]['cargo_bn']      = '--';
                    $batch_arr[$key]['cargo_bn_type'] = '--';
                }
            }
			return ['status' => 0 ,'message' => '查询成功！','data' => $batch_arr ];
		}
		return ['status' => 1 ,'message' => '参数有误！'];
	}

    /****************************************************
     *
     * 货权批次管理 肖文开发部分
     *
     * commonAddBatch 生成批次
     *
     * commonAddBatchCargo 生成货权
     *
     * commonAddBatchLog 生成批次日志
     *
     *********************************************************/
    //===============================================================
    /**
     * @desc 生成批次基础方法
     * @time 2019-2-19
     * @param $param 批次数据参数
     * @return boolean
     */
    public function commonAddBatch($param){
        if($param){
            $data = $param;
            $data['sys_bn'] = $data['sys_bn'] ? $data['sys_bn'] : erpCodeNumber(20, '', $data['our_company_id'])['order_number'];
            $status = $this->getModel("ErpBatch")->add($data);
            return $status;
        }
        return false;
    }

    /**
     * @desc  生成批次变动日志
     * @time 2019-2-19
     * @param $param
     * @return boolean
     */
    public function commonAddBatchLog($param){
        if($param){
            $data = $param;
            $status = $this->getModel("ErpBatchLog")->add($data);
            return $status;
        }
        return false;
    }

    /**
     * 新增批次
     * @time 2019-2-19
     * @param $id
     * @param $batch_data
     * @param $log_data
     * @return boolean
     */
    public function AddBatch($param){
        if(!empty($param)){
            if (empty($param['stock_id'])) {
                $where = [
                    'object_id' => $param['storehouse_id'],
                    'goods_id' => $param['goods_id'],
                    'our_company_id' => $param['our_company_id'],
                    'stock_type' => $param['stock_type'],
                ];
                $stock_info = $this->getModel('ErpStock')->where($where)->find();
                $param['stock_id'] = $stock_info['id'];
            }
            //新增批次数据
            $batch_data = [
                'sys_bn' => isset($param['sys_bn'])? $param['sys_bn'] : erpCodeNumber(20, '', $param['our_company_id'])['order_number'],
                'cargo_bn_id' => $param['cargo_bn_id'],
                'stock_id' => $param['stock_id'],
                'goods_id' => $param['goods_id'],
                'storehouse_id' => $param['storehouse_id'],
                'our_company_id' => $param['our_company_id'],
                'region' => $param['region'],
                'stock_type' => $param['stock_type'],
                'total_num' => $param['total_num'],
                'balance_num' => $param['balance_num'],
                'reserve_num' => $param['reserve_num'],
                'status' => $param['status'],
                'data_source' => $param['data_source'],
                'data_source_number' => $param['data_source_number'],
                'create_time' => currentTime(),
            ];
            $batch_status = $batch_id = $this->getModel("ErpBatch")->add($batch_data);
            $log_data = [
                'batch_id' => $batch_id,
                'batch_sys_bn' => $batch_data['sys_bn'],
                'change_num' => $batch_data['total_num'],
                'before_balance_num' => 0,
                'balance_num' => $batch_data['total_num'],
                'before_reserve_num' => 0,
                'reserve_num' => 0,
                'change_type' => 1,
                'change_number' => $param['data_source'],
                'create_time' => currentTime(),
            ];
            $batch_log_status = $this->commonAddBatchLog($log_data);
            return $batch_log_status && $batch_status ? ['status'=>true,'batch_id'=>$batch_id] : ['status'=>false,'batch_id'=>0];
        }
        return false;
    }

    /**
     * 批次变动
     * @time 2019-2-19
     * @param $id
     * @param $batch_data
     * @param $log_data
     * @return boolean
     */
    public function commonChangeBatch($id, $batch_data, $log_data){
        if($id && !empty($batch_data)){
            $batch_status = $this->getModel("ErpBatch")->where(['id'=>$id])->save($batch_data);
            $batch_log_status = $this->commonAddBatchLog($log_data);
            return $batch_log_status && $batch_status ? true : false;
        }
        return false;
    }

    /**
     * 批次数量变动方法
     * @time 2019-2-19
     * @param array $change_data [
     *      'batch_id' => 批次id
     *      'change_total_num' => 总数变动数量 正为加，负为减
     *      'change_balance_num' => 可用变动数量 正为加，负为减
     *      'change_reserve_num' => 预留变动数量
     *      'change_type' => 变动类型：1 入库 2 出库 3 零售出库 4 入库红冲 5 出库红冲 6 零售出库红冲
     *      'change_number' => 变动单据编号
     *      batch_id:变动得编号
     * ]
     * @return boolean
     */
    public function commonChangeBatchNum($change_data = []){

        if($change_data['batch_id']){
            $batch_info = $this->getModel('ErpBatch')->find($change_data['batch_id']);
            //$change_data['change_balance_num'] = $change_data['change_balance_num'] ? $change_data['change_balance_num'] : 0;
            //验证该批次可用是否满足扣减
            if($change_data['change_balance_num'] < 0 && ( bcadd($batch_info['balance_num'] , $change_data['change_balance_num']) < 0 ) ){
                log_info("扣减数量：". $change_data['change_balance_num']);
                log_info("库存数量：".$batch_info['balance_num']);
                log_info("结果：" . bcadd( $batch_info['balance_num'] , $change_data['change_balance_num']));
                return ['status' => 44, 'message'=>'批次可用不足，无法扣减'];
            }

            //验证该批次预留是否满足扣减
            if($change_data['change_reserve_num'] < 0 && ( bcadd($batch_info['reserve_num'] , $change_data['change_reserve_num']) < 0 )){
                return ['status' => 45, 'message'=>'批次预留不足，无法扣减'];
            }

            //edit xiaowen 2019-3-15 如果变动数量 > 0 且 变动数量 + 当前批次可用 > 批次总数，则批次总数 要加上超出总数部分

            if($change_data['change_balance_num'] > 0 && ($batch_info['balance_num'] + $change_data['change_balance_num'] > $batch_info['total_num'])){
                $change_data['change_total_num'] = $batch_info['balance_num'] + $change_data['change_balance_num'] - $batch_info['total_num'];
            }else{
                $change_data['change_total_num'] = 0;
            }
            $change_balance_num = $change_data['change_balance_num'] ? $change_data['change_balance_num'] : 0;
            $change_reserve_num = $change_data['change_reserve_num'] ? $change_data['change_reserve_num'] : 0;
            $change_batch_data = [
                'total_num' => bcadd( $batch_info['total_num'], $change_data['change_total_num']),
                'balance_num' => $batch_info['balance_num'] + ($change_data['change_balance_num'] ? $change_data['change_balance_num'] : 0),
                'reserve_num' => $batch_info['reserve_num'] + ($change_data['change_reserve_num'] ? $change_data['change_reserve_num'] : 0),
                'update_time' => DateTime(),
            ];

            $log_data = [
                'batch_id' => $batch_info['id'],
                'batch_sys_bn' => $batch_info['sys_bn'],
                'change_num' => $change_data['change_balance_num'] ? $change_data['change_balance_num'] : $change_data['change_reserve_num'],
                'before_balance_num' => $batch_info['balance_num'],
                'balance_num' => $change_batch_data['balance_num'],
                'before_reserve_num' => $batch_info['reserve_num'],
                'reserve_num' => $change_batch_data['reserve_num'],
                'change_type' => $change_data['change_type'],
                'change_number' => $change_data['change_number'],
                'create_time' => DateTime(),
            ];
            $status = $this->commonChangeBatch($batch_info['id'], $change_batch_data, $log_data);
            if($status){
                $result = ['status' => 1, 'message'=>'批次更新成功'];
            }else{
                $result = ['status' => 2, 'message'=>'批次更新失败'];
            }
            return $result;
        }
        return ['status'=>0, 'message'=> '批次数据异常，无法操作'];
    }
    /**
     * @desc 生成货权基础方法
     * @param array $param
     * @return mixed
     */
    public function commonAddBatchCargo($param){
        if($param){
            $data = $param;
            $status = $this->getModel("ErpCargoBn")->add($data);
            return $status;
        }
        return false;
    }

    /**
     * 修改货权数据
     * @param $id 货权id
     * @param $param 货权数据
     * @return bool|mixed
     */
    public function commonUpdateBatchCargo($id, $param){
        if($id > 0){
            $data = $param;
            $status = $this->getModel("ErpCargoBn")->where(['id'=>intval($id)])->save($data);
            return $status;
        }
        return false;

    }

    /**
     * 检查货权编号是否重复
     * @param array $where
     * @return boolean
     */
    public function checkCargoBnRepeat($where = []){

        if($where){
            $info = $this->getModel('ErpCargoBn')->where($where)->find();
            return empty($info) ? false : true;
        }
    }

    /********************************
        @ Content 获取采购单退货 批次列表
        @ Author Yf
        @ Time 2019-02-28
        @ Param [
            AJAX => [
                    'length' => 条数
                    'start'  => 开始页数
                     ------ where --------
                ]
        ]
    *********************************/
    public function erpBatchListByPurchaseReturnOrder( $param ){
        $where = $this->handleBatchWhere($param);
        $field = 'b.*';
        $data = $this->getModel('ErpBatch')->erpBatchListByPurchaseReturnOrder($where,$field,0,0,'');
        $data['data'] = $this->BatchDataHandle($data['data']);
        /********************************************
            @ Author YF
            @ Content 批次列表 可用数量 - 预留数量 = 0 的过滤
        *********************************************/
        foreach ($data['data'] as $key => $value) {
            if ( $value['actual_balance_num'] == 0 ) {
                unset($data['data'][$key]);
            }
        }
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $param['draw'];
        return $data;
        
    }

	/********************************
		@ Content 批次列表
		@ Author Yf
		@ Time 2019-02-18
		@ Param [
			AJAX => [
					'length' => 条数
					'start'  => 开始页数
					 ------ where --------
				]
		]
	*********************************/
	public function erpBatchList( $param ){
	    $param['show_all'] = isset($param['show_all']) && $param['show_all'] == 1 ? 1 : 0;
		$where = $this->handleBatchWhere($param);
		if ( isset($where['message']) ) {
			return ['data' => [],'recordsTotal' => 0,'recordsFiltered' => 0,'draw' => $param['draw'],"message"=> $where['message']];
		}
        if ( isset($param['show_all']) && !empty($param['show_all']) ) {
            if ( $param['show_all'] != 1 ) {
                //只有批次列表页面进行分页 edit xiaowen 2019-3-12
                $param['length'] = 0;
                //只有批次列表页面倒序，其他升序显示批次 edit xiaowen 2019-3-12
                $order_str       = 'b.id asc';
            } else {
                /************************************
                    @ Author YF
                    @ Content 批次列表 根据账套获取批次信息
                    提示 ： 【 批次信息 不会存在 账套搜索
                    所以不用考虑会和 handleBatchWhere 里的搜索条件冲突 】
                *************************************/
                $erp_company_id = session('erp_company_id');
                $where['b.our_company_id'] = ['eq',$erp_company_id];
                $order_str       = 'b.id desc';
                /**************** END ****************/
            }
        }
		
		$data = $this->getModel('ErpBatch')->erpBatchList($where,'',$param['start'], $param['length'], $order_str);
        $data['data'] = $this->BatchDataHandle($data['data']);
		$data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $param['draw'];
        return $data;
    }

    /********************************
    @ Content 批次已使用列表（供调拨入和销退使用）
    @ Author guanyu
    @ Time 2019-02-26
    @ Param [
        AJAX => [
        'length' => 条数
        'start'  => 开始页数
        ------ where --------
        ]
    ]
     *********************************/
    public function erpBatchUseList( $param )
    {
        $param['show_all'] =  1 ;
        $order_str = 'b.id asc';
        $where = $this->handleBatchWhere($param);
        if ( isset($where['status']) ) {
            return ['data' => [],'recordsFiltered' => 0,'draw' => $param['draw']];
        }
        $field = 'b.*,sum(so.actual_outbound_num) as actual_outbound_num';
        if(isset($param['source_from']) && ($param['source_from'] == 1)) {
            $where['is_reverse'] = 2;
            $where['reversed_status'] = 2;
            $where['outbound_status'] = 10;
        }

        $data = $this->getModel('ErpBatch')->erpBatchUseList($where,$field,$param['start'], $param['length'] , $order_str);

        $data['data'] = $this->BatchDataHandle($data['data']);
        $data['recordsFiltered'] = $data['recordsTotal'];
        $data['draw'] = $param['draw'];
        return $data;
    }

    /************************************
     * @ Content 批次列表数据处理！
     * @ Author Yf
     * @ Time 2019-02-18
     * @ Param [
     * (array)$data[0] => [
     * 'cargo_bn_id'   => 货权ID，
     * 'goods_id'        => 商品ID，
     * 'storehouse_id' => 仓库ID，
     * 'our_company_id'=> 账套ID，
     * 'status'        => 状态，
     * .........
     * ]
     * ]
     * @ Return [
     * (array)$data[0] => [
     * 'cargo_bn'     => 货权号
     * 'goods_code'   => '商品代码'
     * 'goods_name'   => '商品名称'
     * 'source_from'  => '商品来源'
     * ........
     * ]
     * ]
     *************************************/
    public function BatchDataHandle($data = [])
    {
        if (!empty($data)) {
            $cargo_bn_ids = array_unique(array_column($data, 'cargo_bn_id')); // 货权ID
            $goods_ids = array_unique(array_column($data, 'goods_id')); // 商品ID
            $storehouse_ids = array_unique(array_column($data, 'storehouse_id')); // 仓库ID
            $our_company_ids = array_unique(array_column($data, 'our_company_id')); // 账套ID
            /* ------------- 查询 货权类型 ------------*/
            $cargo_bn_type_arr = getConfigByType(2);
            /*------------- 查询 货权号 ---------------*/
            $cargo_bn_arr = $this->getModel('ErpCargoBn')->where(['id' => ['in', $cargo_bn_ids]])->select();
            (array)$new_cargo_bn_arr = [];
            foreach ($cargo_bn_arr as $key => $value) {
                $new_cargo_bn_arr[$value['id']] = $value;
            }
            /*------------- 查询 商品 ---------------*/
            $good_field = 'id,goods_code,goods_name,source_from,grade,level';
            $new_good_arr = $this->getModel('ErpGoods')->where(['id' => ['in', $goods_ids]])->getField($good_field);
            /*-------------- 查询仓库 信息 -------------------*/
            $storehouse_arr = $this->getModel('ErpStorehouse')->where([
                'id' => [
                    'in', $storehouse_ids
                ]
            ])->getField('id,storehouse_name');
            /*---------------- 查询 账套信息 -----------------*/
            $our_company_arr = $this->getModel('ErpCompany')->where([
                'company_id' => [
                    'in', $our_company_ids
                ]
            ])->getField('company_id,company_name');
            /* --------------- 获取城市信息 ---------------*/
            $city_arr = provinceCityZone()['city'];
            foreach ($data as $key => $value) {
                //城市
                $data[$key]['region'] = isset($city_arr[$value['region']]) ? $city_arr[$value['region']] : '--';
                if (isset($new_cargo_bn_arr[$value['cargo_bn_id']])) {
                    $data[$key]['cargo_bn'] = $new_cargo_bn_arr[$value['cargo_bn_id']]['cargo_bn'];

                    $data[$key]['cargo_bn_type'] = isset($cargo_bn_type_arr[$new_cargo_bn_arr[$value['cargo_bn_id']]['cargo_bn_type']]) ? $cargo_bn_type_arr[$new_cargo_bn_arr[$value['cargo_bn_id']]['cargo_bn_type']]['name'] : '货权初始化' ;
                } else {
                    $data[$key]['cargo_bn']      = '--';
                    $data[$key]['cargo_bn_type'] = '--';
                }
                if (isset($new_good_arr[$value['goods_id']])) {
                    $data[$key]['goods_code']  = $new_good_arr[$value['goods_id']]['goods_code'];
                    $data[$key]['goods_name']  = $new_good_arr[$value['goods_id']]['goods_name'];
                    $data[$key]['source_from'] = $new_good_arr[$value['goods_id']]['source_from'];
                    $data[$key]['grade']       = $new_good_arr[$value['goods_id']]['grade'];
                    $data[$key]['level']       = $new_good_arr[$value['goods_id']]['level'];
                } else {
                    $data[$key]['goods_code']  = '--';
                    $data[$key]['goods_name']  = '--';
                    $data[$key]['source_from'] = '--';
                    $data[$key]['grade']       = '--';
                    $data[$key]['level']       = '--';
                }
                $data[$key]['storehouse_name'] = isset($storehouse_arr[$value['storehouse_id']]) ? $storehouse_arr[$value['storehouse_id']] : '--';

                $data[$key]['company_name'] = isset($our_company_arr[$value['our_company_id']]) ? $our_company_arr[$value['our_company_id']] : '--';

                $data[$key]['status_code']  = getBatchStatus($value['balance_num'], $value['reserve_num'],$value['total_num']);
                $data[$key]['status']       = erpBatchStatus($data[$key]['status_code']);
                $data[$key]['total_num']    = getNum($value['total_num']);
                $data[$key]['reserve_num']  = getNum($value['reserve_num']);
                $data[$key]['balance_num']  = getNum($value['balance_num']);
                $data[$key]['actual_balance_num'] = getNum($value['balance_num']-$value['reserve_num']);
                $data[$key]['outbound_num'] = isset($value['actual_outbound_num']) ? getNum($value['actual_outbound_num']) : '--';
            }
        }
        return $data;
    }


    /************************************
     * @ Content 处理where 条件
     * @ Author YF
     * @ Time 2018-12-21
     * @ Param [
     *      ------ where --------
     *      sys_bn          => 批次
            status          => 批次状态
            cargo_bn_type   => 货权类型
            .........
     * ]
     * @ Return [
     *      
     *
     * ]
     ************************************/
    public function handleBatchWhere($param)
    {
        $where = [];
        // 批次号
        if (!empty($param['sys_bn']) && isset($param['sys_bn'])) {
            $where['b.sys_bn'] = ['like', ['%' . trim($param['sys_bn']) . '%']];
        }
        if(isset($param['batchId']) && !empty($param['batchId'])){
            if(is_array($param['batchId'])){
                $where['b.id'] = ['in' , $param['batchId']];
            }else{
                $where['b.id'] = $param['batchId'] ;
            }
        }
        // 批次状态
        if (!empty($param['status']) && isset($param['status'])) {
            if(!is_array($param['status'])) //判断是否为数组
                $where['b.status'] = ['eq', trim($param['status'])];
            else
                $where['b.status'] = ['in', $param['status']];
        }
        // 城市
        if (!empty($param['region']) && isset($param['region'])) {
            $where['b.region'] = ['eq',trim($param['region'])];
        }

        /* -------------调拨单只取加油站类型的货权信息 guanyu-----------*/
        if ($param['business_type'] == 1) {
            $param['cargo_type'] = 'cargo_type_5';
        }

        //oil_erp_config的key转oil_erp_cargo_bn的cargo_bn_type
        if (!empty($param['cargo_type'])) {
            $param['cargo_bn_type'] = M('ErpConfig')->where(['key'=>$param['cargo_type'],'status'=>1])->getField('id');
        }

        /* ------------- 货权search 操作 YF---------------*/
        (array)$cargo_bn_where = [];
        if ( !empty($param['cargo_bn_type']) && !empty($param['cargo_bn']) ) {
            $cargo_bn_where['cargo_bn_type'] = ['eq',trim($param['cargo_bn_type'])];
            $cargo_bn_where['cargo_bn'] = ['like', ['%' . trim($param['cargo_bn']) . '%']];
        } else {
            // 货权类型
            if (!empty($param['cargo_bn_type']) && isset($param['cargo_bn_type'])) {
                $cargo_bn_where['cargo_bn_type'] = ['eq',trim($param['cargo_bn_type'])];
            }
            // 外部货权号
            if (!empty($param['cargo_bn']) && isset($param['cargo_bn'])) {
                $cargo_bn_where['cargo_bn'] = ['like', ['%' . trim($param['cargo_bn']) . '%']];
              
            }     
        }
        if ( !empty($cargo_bn_where) ) {
            $cargo_bn_arr = $this->getModel('ErpCargoBn')->where($cargo_bn_where)->field('id')->select();
            if (!empty($cargo_bn_arr)) {
                $cargo_bn_ids = array_column($cargo_bn_arr, 'id');
                $where['b.cargo_bn_id'] = ['in', $cargo_bn_ids];
            } else {
                return ['status' => 2, 'message' => '搜索外部货权为空！'];
            }
        }
        /* ------------------- END -------------------- */
        // 商品代码
        if (!empty($param['goods_code']) && isset($param['goods_code'])) {
            $goods_where['goods_code'] = ['like', ['%' . trim($param['goods_code']) . '%']];
            $goods_arr = $this->getModel('ErpGoods')->where($goods_where)->field('id')->select();
            if (!empty($goods_arr)) {
                $goods_ids = array_column($goods_arr, 'id');
                $where['b.goods_id'] = ['in', $goods_ids];
            } else {
                return ['status' => 3, 'message' => '搜索商品为空！'];
            }
        }
        //判断是否为采购单
        if(isset($param['is_agent']) && !empty($param['is_agent'])){
            if(!isset($param['source_number']) || empty($param['source_number'])){
                return ['status' => 4, 'message' => '销售单的编号为空！'];
            }
            //查询采购单
            $fieldPurchase = "id , order_number" ;
            $wherePurchase = [
                "from_sale_order_number" => $param['source_number'] ,
                'type' => 2 ,
                "order_status" => 10 ,
                "is_void" => 2 ,
                "is_returned" => 2
            ];
            $purchaseInfo = $this->getEvent("ErpPurchase")->getPurchase($fieldPurchase , $wherePurchase);
            if(empty($purchaseInfo)){
                return ['status' => 4, 'message' => '未找到相关的采购单，请查询是相关信息'];
            }
            //查询入库单
            $stockInField = "id , batch_id" ;
            $stockInWhere = [
                "source_number" => $purchaseInfo['order_number'],
                "is_reverse" => 2 ,
                "reversed_status" => 2
            ] ;
            $stockInList = $this->getEvent("ErpStock")->getStcokInLists($stockInField , $stockInWhere) ;
            if(empty($stockInList)){
                return ['status' => 4, 'message' => '未找到相关的采购入库单，请核实数据信息'];
            }
            $batchIds = array_column($stockInList , "batch_id") ;
            $batchIdNullCount = 0 ;
            foreach ($batchIds as $valueId){
                if(empty($valueId)){
                    $batchIdNullCount++ ;
                }
            }
            if(count($batchIds) == $batchIdNullCount){
                return ['status' => 5, 'message' => '采购入库单的批次信息为空，请核实数据信息'];
            }
            $where['b.id'] = ['in' , $batchIds];
        }
        if(isset($param['source'])){
            unset($param['source_number']);
        }
        //五要素查询条件：storehouse_id,goods_id,region,stock_type,our_company_id
        if (!empty($param['storehouse_id']) && isset($param['storehouse_id'])) {
            $where['b.storehouse_id'] = $param['storehouse_id'];
        }
        if (!empty($param['goods_id']) && isset($param['goods_id'])) {
            $where['b.goods_id'] = $param['goods_id'];
        }
        if (!empty($param['region']) && isset($param['region'])) {
            $where['b.region'] = $param['region'];
        }
        if (!empty($param['stock_type']) && isset($param['stock_type'])) {
            $where['b.stock_type'] = $param['stock_type'];
        }
        if (!empty($param['our_company_id']) && isset($param['our_company_id'])) {
            $where['b.our_company_id'] = $param['our_company_id'];
        }

        if (!empty($param['source_number']) && isset($param['source_number'])) {
            $where['so.source_number'] = $param['source_number'];
        }
//        if(isset($param['useNum']) && !empty($param['useNum'])){//可用数量存在
//            $where['_string'] = "b.balance_num > b.reserve_num";
//        }
        if($param['show_all'] != 1){
            $sql = "b.balance_num - b.reserve_num > 0";
            if(isset($param['userBatchId']) && !empty($param['userBatchId'])){
                if(is_array($param['userBatchId'])){
                    $sql = "((".$sql.") or (id in (".implode(" , ",$param['userBatchId']).")))" ;
                }else{
                    $sql = "((".$sql.") or (id = ".$param['userBatchId']."))" ;
                }
            }
            $where['_string'] = $sql ;
        }
//        unset($where['b.status']);
        return $where;
    }
    /*
     * @params:
     *      $field，查询的字段
     *      $where:查询条件
     * @return: array
     * @auth:小黑
     * @time:2019-5-7
     * @desc:根据条件查询批次和货权号的信息
     */
    public function getBatchCargo($field= "*" , $where = []){
        if(empty($where)){
            return [];
        }
        $returnData = $this->getModel("ErpBatch")->getBatchCargo($field , $where);
        return $returnData ;
    }
}