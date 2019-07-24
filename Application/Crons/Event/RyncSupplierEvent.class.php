<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/16
     * Time: 15:28
     * 同步新零售服务商信息及服务商人员
     */

    namespace Crons\Event;
    class RyncSupplierEvent extends BaseEvent
    {
        private $api_url='';
        public function __construct()
        {
            parent::__construct();
            $this->api_url=C('NewRetailUrl').'getOspList';
        }

        //同步服务商信息
        public function syncSupplier()
        {
            //执行开始进行文本report
            log_info('新零售数据同步提示：开始同步 1 ' .date("Y-m-d H:i:s",time()));
            $end=false;
            $page=1;
            while(true) {
                //拉取信息
                $query_data = [
                    'page' => $page,
                    'pageSize' => '100',
                    'sign' => $this->makeSign(),
                ];
                $supplier_list = http($this->api_url, $query_data);
                $supplier_list = json_decode($supplier_list, true);
                //接口异常提醒
                if((!isset($supplier_list['msg'])||($supplier_list['msg']!='成功'))){
                    $this->reportLog(2,['new_data'=>$supplier_list['msg'].(isset($customer_list['message'])?$customer_list['message']:'')],$supplier_list['msg'].(isset($customer_list['message'])?$customer_list['message']:''));
                }
                $supplier = isset($supplier_list['res']['list']) ? $supplier_list['res']['list'] : [];
                if (count($supplier) == 0) {
                    $end=true;
                    log_info('新零售数据同步提示：同步 1 ['.$page.'] 无数据' . date("Y-m-d H:i:s", time()));
                }
                //循环处理
                $i = 0;
                foreach ($supplier as $key => $val) {
                    //进行数据处理
                    $this->handleSyncSupplier($val);
                }
                $page++;
                //结束跳出
                if($end){
                    break;
                }
            }
            //执行结束进行文本report
            log_info('新零售数据同步提示：结束同步 1 ' .date("Y-m-d H:i:s",time()));
        }

        //新增/变更服务商执行方法
        private function handleSyncSupplier($data)
        {
            //检查异常数据 ospId 为空 report;
            if(intval($data['ospId'])==0){
                $this->reportLog(1,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商id为空');
                return null;
            }
            /*
             * 逻辑变更 1、先查oms id 有则更新，无则2
             *          2、front_id 有则倒绑oms_id 并更新数据 无则3
             *          3、查询服务商的名称 有的话更新oms_id front_id 及信息 无则4
             *          4、增加数据
             * */
            $error_map_obj=$this->getModel('ErpErrorDataMapping');
            $update_type=4;
            $check_action=true;
            //数据查询
            if($check_action){
                $exits_data=$this->getModel('ErpSupplier')->findSupplier(['status'=>1,'data_source_oms_id'=>intval($data['ospId'])]);
                if($exits_data){
                    //检测是否存在于黑名单内
                    $error_data=$error_map_obj->findErpErrorDataMapping(['invalid_id'=>intval($data['ospId']),'type'=>1,'data_source'=>1]);
                    if($error_data){
                        $this->reportLog(1,['new_data'=>$data,'request_data'=>$data],'黑名单数据');
                        return null;
                    }
                    $update_type=1;
                    $check_action=false;
                }
            }
            if($check_action){
                $front_id=isset($data['oldCode'])?intval($data['oldCode']):0;
                $exits_data=$this->getModel('ErpSupplier')->findSupplier(['status'=>1,'data_source_front_id'=>$front_id]);
                if(($front_id!=0)&&$exits_data){
                    //检测是否存在于黑名单内
                    $error_data=$error_map_obj->findErpErrorDataMapping(['invalid_id'=>$front_id,'type'=>1,'data_source'=>2]);
                    if($error_data){
                        $this->reportLog(1,['new_data'=>$data,'request_data'=>$data],'黑名单数据');
                        return null;
                    }
                    $update_type=2;
                    $check_action=false;
                }else{
                    $exits_data=null;
                }
            }
            if($check_action){
                $ospName=isset($data['ospName'])?trim($data['ospName']):'';
                $exits_data=$this->getModel('ErpSupplier')->findSupplier(['status'=>1,'supplier_name'=>$ospName]);
                if(($ospName!='')&&$exits_data){
                    $update_type=3;
                    $check_action=false;
                }else{
                    $exits_data=null;
                }
            }

            if($update_type==4){
                //检测是否存在于黑名单内
                $error_data=$error_map_obj->findErpErrorDataMapping(['invalid_id'=>intval($data['ospId']),'type'=>1,'data_source'=>1]);
                if($error_data){
                    $this->reportLog(1,['new_data'=>$data,'request_data'=>$data],'黑名单数据');
                    return null;
                }
            }

            //地区查询
            if(intval($data['provinceId'])==0){
                $province_ara_info['id']=0;
            }else{
                $province_ara_info=$this->getModel('Area')->getArea('id',['map_code'=>intval($data['provinceId'])]);
                if(count($province_ara_info)==0){
                    $province_ara_info['id']=0;
                }
            }
            //地区查询
            if(intval($data['cityId'])==0){
                $city_ara_info['id']=0;
            }else{
                $city_ara_info=$this->getModel('Area')->getArea('id',['map_code'=>intval($data['cityId'])]);
                if(count($city_ara_info)==0){
                    $city_ara_info['id']=0;
                }
            }
            //地区查询
            if(intval($data['districtId'])==0){
                $district_ara_info['id']=0;
            }else{
                $district_ara_info=$this->getModel('Area')->getArea('id',['map_code'=>intval($data['districtId'])]);
                if(count($district_ara_info)==0){
                    $district_ara_info['id']=0;
                }
            }

            //公司数据
            $SupplierData=[
                'supplier_name'=>isset($data['ospName'])?trim($data['ospName']):'',
                'supplier_short_name'=>isset($data['ospShortName'])?trim($data['ospShortName']):'',
                'tax_number'=>isset($data['taxNum'])?trim($data['taxNum']):'',
                'registered_bank'=>isset($data['bankName'])?trim($data['bankName']):'',
                'registered_bank_number'=>isset($data['bankAccount'])?trim($data['bankAccount']):'',
                'registered_tel'=>isset($data['telNum'])?trim($data['telNum']):'',
                'registered_address'=>isset($data['address'])?trim($data['address']):'',
                'company_tel'=>isset($data['telNum'])?trim($data['telNum']):'',
                'is_inner'=>2,
                'address_province'=>intval($province_ara_info['id']),
                'address_city'=>intval($city_ara_info['id']),
                'address_zone'=>intval($district_ara_info['id']),
                'company_address'=>isset($data['address'])?trim($data['address']):'',
                //'business_attributes'=>2,
                'business_attributes'=>1, //OMS过来的供应商业务属性修改为批发（oms油站需要做预付、采购）edit xiaowen 2018-11-20
                'audit_status'=>1,
                'creater'=>'',
                'data_source'=>3,
//                'data_source_id'=>intval($data['ospId']),
                'data_source_oms_id'=>intval($data['ospId']),//新增冗余字段
                'data_source_front_id'=>isset($data['oldCode'])?intval($data['oldCode']):0,//新增front关联id
            ];
            //用户数据
            $SupplierUserData=[
                'user_name'=>isset($data['contactsName'])?trim($data['contactsName']):'',
                'user_phone'=>isset($data['contactsPhone'])?trim($data['contactsPhone']):'',
                'data_source_id'=>isset($data['contactsId'])?intval($data['contactsId']):0,
                'data_source'=>2,
            ];

            //银行卡数据
            $SupplierExtData=[
                'ext_info_type'=>6,
                'ext_info_content'=>$SupplierData['registered_bank'],
                'ext_info_content_two'=>$SupplierData['registered_bank_number'],
                'is_from_oms'=>1,
            ];

            $change_report=true;
            if(!$exits_data){
                //进行追加
                $SupplierData['create_time']=$SupplierData['audit_time']=$SupplierData['update_time']=date('Y-m-d H:i:s',time());
                $supplier_id=$this->getModel('ErpSupplier')->addSupplier($SupplierData);
            }else{
                $update_supplier=false;


                /*
                 * 天杰要求的 别问我为什么 我不知道 就是要干
                 * hanfeng
                 * 2019-04-02
                 * */
                unset($SupplierData['supplier_name']);
                unset($SupplierData['supplier_short_name']);


                //数据判断后进行变更
                foreach($SupplierData as $key=>$val){
                    if($val!=$exits_data[$key]){
                        $update_supplier=true;
                        break;
                    }
                }
                $supplier_id=$exits_data['id'];
                if($update_supplier){
                    $CustomerData['update_time']=date('Y-m-d H:i:s',time());
                    $this->getModel('ErpSupplier')->saveSupplier(['id'=>$exits_data['id']],$SupplierData);
                }else{
                    $change_report=false;
                }
            }
            //变更report
            if($change_report){
                $this->reportLog(1,['company_id'=>$supplier_id,'new_data'=>$SupplierData+['change_position'=>$update_type],'old_data'=>(!$exits_data?[]:$exits_data),'request_data'=>$data]);
            }
            $SupplierUserData['supplier_id']=$SupplierExtData['supplier_id']=$supplier_id;
            //变更公司员工信息
            $this->syncSupplierUser($SupplierUserData);
            //变更银行数据信息
            $this->syncSupplierExt($SupplierExtData);

        }

        //同步服务商人员信息
        private function syncSupplierUser($data)
        {
            //检查异常数据 data_source_id 为空 report;
            if($data['data_source_id']==0){
                $this->reportLog(3,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商员工id为空');
                return null;
            }
            //数据查询
            $exits_data=$this->getModel('ErpSupplierUser')->findSupplierUser(['supplier_id'=>$data['supplier_id'],'data_source'=>$data['data_source'],'data_source_id'=>$data['data_source_id']]);

            $change_report=true;
            if(!$exits_data){
                //数据追加
                $data['create_time']=$data['update_time']=date('Y-m-d H:i:s',time());
                $supplier_user_id=$this->getModel('ErpSupplierUser')->addSupplierUser($data);
            }else{
                $update_supplier=false;
                //数据判断后进行变更
                foreach($data as $key=>$val){
                    if($val!=$exits_data[$key]){
                        $update_supplier=true;
                        break;
                    }
                }
                $supplier_user_id=$exits_data['id'];
                if($update_supplier){
                    $data['update_time']=date('Y-m-d H:i:s',time());
                    $this->getModel('ErpSupplierUser')->saveSupplierUser(['id'=>$exits_data['id']],$data);
                }else{
                    $change_report=false;
                }
            }
            //变更report
            if($change_report){
                $this->reportLog(3,['company_id'=>$supplier_user_id,'new_data'=>$data,'old_data'=>(!$exits_data?[]:$exits_data),'request_data'=>$data]);
            }
        }

        //同步服务商银行数据信息
        private function syncSupplierExt($data)
        {
            //检查异常数据 data_source_id 为空 report;
            if($data['supplier_id']==0){
                $this->reportLog(6,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商id为空');
                return null;
            }
            //数据查询
            $exits_data=$this->getModel('ErpSupplierExt')->findSupplierback(['supplier_id'=>$data['supplier_id'],'ext_info_type'=>6,'is_from_oms'=>1]);

            $change_report=true;
            if(!$exits_data){
                //数据追加
                $data['create_time']=$data['update_time']=date('Y-m-d H:i:s',time());
                $supplier_ext_id=$this->getModel('ErpSupplierExt')->addSupplierExt($data);
            }else{
                $update_supplier_ext=false;
                //数据判断后进行变更
                foreach($data as $key=>$val){
                    if($val!=$exits_data[$key]){
                        $update_supplier_ext=true;
                        break;
                    }
                }
                $supplier_ext_id=$exits_data['id'];
                if($update_supplier_ext){
                    $data['update_time']=date('Y-m-d H:i:s',time());
                    $this->getModel('ErpSupplierExt')->saveSupplierExt(['id'=>$exits_data['id']],$data);
                }else{
                    $change_report=false;
                }
            }
            //变更report
            if($change_report){
                $this->reportLog(6,['company_id'=>$data['supplier_id'],'new_data'=>$data,'old_data'=>(!$exits_data?[]:$exits_data),'request_data'=>$data]);
            }
        }
    }