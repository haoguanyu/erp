<?php
namespace Common\Event;
use Think\Controller;
use Home\Controller\BaseController;

class ErpBankEvent extends BaseController
{
     /*
      * ------------------------------------------
      * ERP 银行信息逻辑处理层
      * Author：qianbin        Time：2018-08-03
      * ------------------------------------------
      */
    public function _initialize()
    {
        $this->date = date('Y-m-d H:i:s', time());
        $this->arr = [];
    }

    /*
     * ------------------------------------------
     * ERP 银行列表
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function erpBankList($param = [])
    {
        $where = [];
        // @查询条件
        if (!empty(trim($param['our_company_id']))) {
            $where['our_company_id'] = intval($param['our_company_id']);
        }
        if (!empty(trim($param['pay_type']))) {
            $where['pay_type']       = intval($param['pay_type']);
        }
        if (!empty(trim($param['business_type']))) {
            $where['business_type']  = intval($param['business_type']);
        }
        if (!empty(trim($param['bank_status']))) {
            $where['status']         = intval($param['bank_status']);
        }
        $field     = '*';
        $result = $this->getModel('ErpBank')->erpBankList($where, $field, $param['start'], $param['length']);
        //空数据
        if (count($result['data']) > 0) {
            $our_company    = getAllErpCompany();
            foreach ($result['data'] as $k => $v) {
                $result['data'][$k]['our_company_name'] = $our_company[$v['our_company_id']];
                $result['data'][$k]['pay_type']         = empty($v['pay_type'])      ? '--' : getBankPayType($v['pay_type']);
                $result['data'][$k]['business_type']    = empty($v['business_type']) ? '--' : getBankBusinessType($v['business_type']);
                $result['data'][$k]['bank_status']      = empty($v['status'])        ? '--' : getBankStatus($v['status'],1);
                $result['data'][$k]['is_first']         = empty($v['is_first'])      ? '--' : getBankIsFirst($v['is_first']);
                $result['data'][$k]['create_name']      = empty(trim($v['update_name'])) ? $v['create_name'] : $v['update_name'] ;
                $result['data'][$k]['create_time']      = $v['update_time'] == '0000-00-00 00:00:00' ? $v['create_time'] : $v['update_time']; ;
                // 内部交易 银行账号是否首选
                $result['data'][$k]['default_bank']     = isDefaultBank($v['default_bank']);
            }
        } else {
            $result['data'] = [];
        }
        $result['recordsFiltered'] = $result['recordsTotal'];
        $result['draw'] = $_REQUEST['draw'];
        return $result;
    }

    /*
     * ------------------------------------------
     * ERP 添加银行信息
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function addErpBank($param = [])
    {
        if(getCacheLock('ErpBank/addErpBank')) return ['status' => 99, 'message' => $this->running_msg];

        # -----参数验证
        if(count($param) <= 0 ){
            return ['status'  => 2 , 'message' => '参数有误，请刷新后重试！'];
        }

        if(intval($param['our_company_id']) <= 0 ){
            return ['status' => 3 ,'message' => '请选择正确的账套公司！'];
        }
        if(intval($param['pay_type']) <= 0){
            return ['status' => 4 ,'message' => '请选择正确的收付类型！'];
        }
        if(empty(trim($param['bank_name']))){
            return ['status' => 5 ,'message' => '请输入正确的银行名称！'];
        }
        if(empty(trim($param['bank_num']))){
            return ['status' => 6 ,'message' => '请输入正确的银行账号！'];
        }
        if(empty(trim($param['bank_simple_name']))){
            return ['status' => 7 ,'message' => '请输入正确的银行简称！'];
        }
        if(intval($param['business_type']) <= 0){
            return ['status' => 8 ,'message' => '请选择正确的业务对象！'];
        }
        if(intval($param['status']) <= 0){
            return ['status' => 9 ,'message' => '请选择正确的业务对象！'];
        }
        if(intval($param['is_first']) <= 0){
            return ['status' => 10 ,'message' => '请检查首选状态！'];
        }
        if ( !in_array(intval($param['default_bank']),[1,2]) ) {
            return ['status' => 11 ,'message' => '请检查内部首选状态！'];
        }
        # 数据唯一判定：账套公司+收付类型+银行简称+银行账号+银行名称
        $where = [
            'our_company_id'    => intval($param['our_company_id']),
            'pay_type'          => intval($param['pay_type']),
            'bank_name'         => trim($param['bank_name']),
            'bank_num'          => trim($param['bank_num']),
            'bank_simple_name'  => trim($param['bank_simple_name']),
            'status'            => 1,
        ];
        if($this->getModel('ErpBank')->findErpBank($where)){
            return ['status' => 12 ,'message' => '该银行信息已存在，请重新填写！'];
        }

        # 业务处理----------
        setCacheLock('ErpBank/addErpBank', 1);
        try{
            M()->startTrans();
            # 验证是否首选
            $is_first_status = 1 ;
            if(intval($param['is_first']) == 1) {
                $is_first_status = $this->updateErpBankIsFirst($param)['status'];
            }
            /********** YF Time 2018-12-12 ************/
            if ( intval($param['default_bank']) == 1 ) {
                (array)$update_where = [
                'our_company_id'    => intval($param['our_company_id']),
                'status'            => 1,
                'pay_type'          => intval($param['pay_type']),
                ];
                $this->getModel('ErpBank')->saveErpBank($update_where,['default_bank' => 2]);
            } 
            /*************** END **********************/
            # 添加
            $add_data   = [
                'our_company_id'    => intval($param['our_company_id']),
                'pay_type'          => intval($param['pay_type']),
                'bank_name'         => trim($param['bank_name']),
                'bank_num'          => trim($param['bank_num']),
                'bank_simple_name'  => trim($param['bank_simple_name']),
                'business_type'     => intval($param['business_type']),
                'status'            => trim($param['status']),
                'is_first'          => trim($param['is_first']),
                'create_id'         => $this->getUserInfo('id'),
                'create_name'       => $this->getUserInfo('dealer_name'),
                'create_time'       => currentTime(),
                'default_bank'      => trim($param['default_bank']),
            ];
            $add_result = $this->getModel('ErpBank')->addErpBank($add_data);

            # 添加log
            $add_data['id'] = $add_result;
            $add_data_log   = [
                'bank_id'       => $add_result,
                'log_type'      => 1 ,
                'log_info'      => json_encode($add_data),
                'create_time'   => currentTime(),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id'),
            ];
            $add_data_log_status = $this->getModel('ErpBankLog')->add($add_data_log);

            if($add_result && $add_data_log_status && $is_first_status == 1){
                M()->commit();
                $result = ['status' => 1 ,'message' => '添加成功！'];
            }else{
                M()->rollback();
                $result = ['status' => 13 ,'message' => '添加失败！'];
            }

        }catch(\Exception $e){
            M()->rollback();
            cancelCacheLock('ErpBank/addErpBank');
            $result =  ['status' =>$e->getCode(),'message' => $e->getMessage()];
        }

        cancelCacheLock('ErpBank/addErpBank');
        return $result;
    }

     /*
      * ------------------------------------------
      * ERP 更新银行信息
      * Author：qianbin        Time：2018-08-03
      * ------------------------------------------
      */
    public function updateErpBank($param = [])
    {
        if(getCacheLock('ErpBank/updateErpBank')) return ['status' => 99, 'message' => $this->running_msg];
        # -----参数验证
        if(count($param) <= 0 ){
            return ['status'  => 2 , 'message' => '参数有误，请刷新后重试！'];
        }
        if(intval($param['id']) <= 0 ){
            return ['status' => 3 ,'message' => '请选择正确的账套公司！'];
        }
        if(intval($param['our_company_id']) <= 0 ){
            return ['status' => 4 ,'message' => '请选择正确的账套公司！'];
        }
        if(intval($param['pay_type']) <= 0){
            return ['status' => 5 ,'message' => '请选择正确的收付类型！'];
        }
        if(empty(trim($param['bank_name']))){
            return ['status' => 6 ,'message' => '请输入正确的银行名称！'];
        }
        if(empty(trim($param['bank_num']))){
            return ['status' => 7 ,'message' => '请输入正确的银行账号！'];
        }
        if(empty(trim($param['bank_simple_name']))){
            return ['status' => 8 ,'message' => '请输入正确的银行简称！'];
        }
        if(intval($param['business_type']) <= 0){
            return ['status' => 9 ,'message' => '请选择正确的业务对象！'];
        }
        if(intval($param['status']) <= 0){
            return ['status' => 10 ,'message' => '请选择正确的业务对象！'];
        }
        if(intval($param['is_first']) <= 0){
            return ['status' => 11 ,'message' => '请检查首选状态！'];
        }

        if ( !in_array(intval($param['default_bank']),[1,2]) ) {
            return ['status' => 11 ,'message' => '请检查内部首选状态！'];
        }
        # 数据唯一判定：账套公司+收付类型+银行简称+银行账号+银行名称
        $where = [
            'id'                => ['neq' , intval($param['id'])],
            'our_company_id'    => intval($param['our_company_id']),
            'pay_type'          => intval($param['pay_type']),
            'bank_name'         => trim($param['bank_name']),
            'bank_num'          => trim($param['bank_num']),
            'bank_simple_name'  => trim($param['bank_simple_name']),
            'status'            => 1,
        ];
        if($this->getModel('ErpBank')->findErpBank($where)){
            return ['status' => 13 ,'message' => '该银行信息已存在，请重新填写！'];
        }
        setCacheLock('ErpBank/updateErpBank', 1);

        try{
            M()->startTrans();
            # 验证是否首选
            $is_first_status = 1 ;
            if(intval($param['is_first']) == 1) {
                $is_first_status = $this->updateErpBankIsFirst($param)['status'];
            }
            /********** YF Time 2018-12-12 ************/
            if ( intval($param['default_bank']) == 1 ) {
                (array)$update_where = [
                'our_company_id'    => intval($param['our_company_id']),
                'status'            => 1,
                'pay_type'          => intval($param['pay_type']),
                ];
                $this->getModel('ErpBank')->saveErpBank($update_where,['default_bank' => 2]);
            }
            /*************** END **********************/
            # 修改
            $update_data   = [
                'our_company_id'    => intval($param['our_company_id']),
                'pay_type'          => intval($param['pay_type']),
                'bank_name'         => trim($param['bank_name']),
                'bank_num'          => trim($param['bank_num']),
                'bank_simple_name'  => trim($param['bank_simple_name']),
                'business_type'     => intval($param['business_type']),
                'status'            => trim($param['status']),
                'is_first'          => trim($param['is_first']),
                'update_id'         => $this->getUserInfo('id'),
                'update_name'       => $this->getUserInfo('dealer_name'),
                'update_time'       => currentTime(),
                'default_bank'      => trim($param['default_bank']),

            ];
            $update_result = $this->getModel('ErpBank')->saveErpBank(['id' => intval($param['id'])],$update_data);

            # 添加log
            $update_data['id'] = intval($param['id']);
            $add_data_log   = [
                'bank_id'       => $update_data['id'],
                'log_type'      => 2 ,
                'log_info'      => json_encode($update_data),
                'create_time'   => currentTime(),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id'),
            ];
            $add_data_log_status = $this->getModel('ErpBankLog')->add($add_data_log);

            if($update_result && $add_data_log_status && $is_first_status == 1  ){
                M()->commit();
                $result = ['status' => 1 ,'message' => '修改成功！'];
            }else{
                M()->rollback();
                $result = ['status' => 7 ,'message' => '修改失败！'];
            }

        }catch(\Exception $e){
            M()->rollback();
            cancelCacheLock('ErpBank/updateErpBank');
            $result =  ['status' =>$e->getCode(),'message' => $e->getMessage()];
        }

        cancelCacheLock('ErpBank/updateErpBank');
        return $result;
    }

    /*
     * ------------------------------------------
     * ERP 更新银行信息状态
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function updateErpBankStatus($param = [])
    {
        if(getCacheLock('ErpBank/updateErpBank')) return ['status' => 99, 'message' => $this->running_msg];
        if(count($param) <= 0){
            return ['status'  => 2 , 'message' => '参数有误，请刷新后重试！'];
        }
        if(intval($param['id']) <= 0){
            return ['status' => 3 ,'message' => '参数错误，请刷新后重试！'];
        }
        if(empty(trim($param['status']))){
            return ['status' => 4 ,'message' => '请选择开启或关闭！'];
        }
        $data = $this->getModel('ErpBank')->findErpBank(['id' => intval($param['id'])]);
        if(empty($data)){
            return ['status' => 5 ,'message' => '未查询到该笔配置信息，请刷新后重试！'];
        }
        # 数据唯一判定：账套公司+收付类型+银行简称+银行账号+银行名称+有效
        $where = [
            'id'                => ['neq' , intval($data['id'])],
            'our_company_id'    => intval($data['our_company_id']),
            'pay_type'          => intval($data['pay_type']),
            'bank_name'         => trim($data['bank_name']),
            'bank_num'          => trim($data['bank_num']),
            'bank_simple_name'  => trim($data['bank_simple_name']),
            'status'            => 1,
        ];
        if($this->getModel('ErpBank')->findErpBank($where)){
            return ['status' => 13 ,'message' => '该银行信息已存在有效数据，无法正常启用！'];
        }
        if($data['status'] == intval($param['status'])){
            $status_message = $data['status'] == 1 ? '启用' : '禁用';
            return ['status' => 8 ,'message' => '该笔银行账号配置已为'.$status_message.'状态，请勿重复操作！'];
        }
        setCacheLock('ErpBank/updateErpBankStatus', 1);

        try{
            M()->startTrans();
            # 修改
            $update_data   = [
                'status'        => intval($param['status']),
                'update_id'     => $this->getUserInfo('id'),
                'update_name'   => $this->getUserInfo('dealer_name'),
                'update_time'   => currentTime(),
            ];
            $update_result = $this->getModel('ErpBank')->saveErpBank(['id' => intval($param['id'])],$update_data);

            # 添加log
            $data['status']      = $update_data['status'];
            $data['update_name'] = $update_data['update_name'];
            $data['update_id']   = $update_data['update_id'];
            $data['update_time'] = $update_data['update_time'];
            $add_data_log   = [
                'bank_id'       => $data['id'],
                'log_type'      => 2 ,
                'log_info'      => json_encode($data),
                'create_time'   => currentTime(),
                'operator'      => $this->getUserInfo('dealer_name'),
                'operator_id'   => $this->getUserInfo('id'),
            ];
            $add_data_log_status = $this->getModel('ErpBankLog')->add($add_data_log);

            if($update_result && $add_data_log_status){
                M()->commit();
                $result = ['status' => 1 ,'message' => '操作成功！'];
            }else{
                M()->rollback();
                $result = ['status' => 9 ,'message' => '操作失败！'];
            }

        }catch(\Exception $e){
            M()->rollback();
            cancelCacheLock('ErpBank/updateErpBankStatus');
            $result =  ['status' =>$e->getCode(),'message' => $e->getMessage()];
        }

        cancelCacheLock('ErpBank/updateErpBankStatus');
        return $result;
    }


    /*
     * ------------------------------------------
     * ERP 更新银行信息首选
     * 同一账套公司下,不同的收付类型对应的银行信息（银行简称+银行名称+银行账号），只允许存在一条首选数据
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function updateErpBankIsFirst($param = [])
    {
        if(count($param) <= 0){
            return ['status'  => 20 , 'message' => '参数有误，请刷新后重试！'];
        }
        if(intval($param['our_company_id']) <= 0){
            return ['status' => 21 ,'message' => '账套公司信息错误，请刷新后重试！'];
        }
        if(empty(trim($param['pay_type']))){
            return ['status' => 22 ,'message' => '收付款类型信息错误，请刷新后重试！'];
        }
        # 查询同一账套下，是否存在首选。
        # 如果有，更新为否
        $where = [
            'our_company_id' => intval($param['our_company_id']),
            'pay_type'       => intval($param['pay_type']),
            'is_first'       => 1 ,
        ];
        # 如果编辑页，需要更换首选，则要除了本身数据
        if(isset($param['id']) && intval($param['id']) > 0){
            $where['id'] = ['neq' , intval($param['id'])];
        }
        $data = $this->getModel('ErpBank')->findErpBank($where);
        if(empty($data)){
            return ['status' => 1 ,'message' => ''];
        }

        $update_result = $this->getModel('ErpBank')->saveErpBank($where,['is_first' => 2]);
        if($update_result){
            $result = ['status' => 1 ,'message'  => '首选更新成功！'];
        }else{
            $result = ['status' => 26 ,'message' => '首选更新失败！'];
        }

        return $result;
    }

   /*
    * ------------------------------------------
    * ERP 获取应收、应付选项
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function getErpBankList($pay_type = 1 )
    {
        $where = [
            'pay_type'       => $pay_type ,
            'status'         => 1 ,
            'business_type'  => ['in' , [1,3]] ,
            'our_company_id' => session('erp_company_id'),
        ];
        $data = $this->getModel('ErpBank')->where($where)->order('id desc')->getField('id,bank_name,bank_num,bank_simple_name,pay_type,is_first',true);
        return $data;
    }

   /*
    * ------------------------------------------
    * ERP 获取单条银行账号信息
    * Author：qianbin        Time：2018-08-03
    * ------------------------------------------
    */
    public function getErpBankInfoById($bank_id = 0 )
    {
        $data = [];
        if(intval($bank_id) <= 0 ) return $data;
        $where = [
            'id'       => intval($bank_id) ,
        ];
        $data = $this->getModel('ErpBank')->findErpBank($where);
        return $data;
    }

    /*
     * ------------------------------------------
     * ERP 银行信息详情(应收、应付、预存、预付列表展示)
     * Author：qianbin        Time：2018-08-03
     * ------------------------------------------
     */
    public function getErpBankInfo($param = [])
    {
        $result = [];
        if(intval($param['id']) <= 0 || intval($param['type']) <= 0) return $result;
        $where = [
            'id' => intval($param['id']),
        ];
        # type 1=>应付、2=>应收、3=>预存预付
        switch (intval($param['type'])){
            case 1:
                $data = $this->getModel('ErpPurchasePayment')->field('bank_info')->where($where)->find()['bank_info'];
                break;
            case 2:
                $data = $this->getModel('ErpSaleCollection')->field('bank_info')->where($where)->find()['bank_info'];
                break;
            case 3:
                $data = $this->getModel('ErpRechargeOrder')->field('bank_info')->where($where)->find()['bank_info'];
                break;
            default:
                break;
        }
        if(!empty($data)) $result = json_decode($data,true);
        return $result;
    }


}

?>
