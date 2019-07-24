<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/16
     * Time: 15:29
     * 同步新零售公司信息及公司人员
     */

    namespace Crons\Event;
    class RyncCustomerEvent extends BaseEvent
    {
        private $api_url='';
        public function __construct()
        {
            parent::__construct();
            $this->api_url=C('NewRetailUrl').'getZipCompanyList';
        }

        //同步公司信息
        public function syncCustomer()
        {
            //执行开始进行文本report
            log_info('新零售数据同步提示：开始同步 2 ' .date("Y-m-d H:i:s",time()));
            $end=false;
            $page=1;
            while(true){
                //拉取信息
                $query_data=[
                    'page'=>$page,
                    'pageSize'=>'100',
                    'sign'=>$this->makeSign(),
                ];
                $customer_list=http($this->api_url,$query_data);
                $customer_list=json_decode($customer_list,true);
                //接口异常提醒
                if((!isset($customer_list['msg'])||($customer_list['msg']!='成功'))){
                    $this->reportLog(2,['new_data'=>$customer_list['msg'].(isset($customer_list['message'])?$customer_list['message']:'')],$customer_list['msg'].(isset($customer_list['message'])?$customer_list['message']:''));
                }
                $customer=isset($customer_list['res']['list'])?$customer_list['res']['list']:[];
                if(count($customer)==0){
                    $end=true;
                    log_info('新零售数据同步提示：同步 2 ['.$page.'] 无数据' .date("Y-m-d H:i:s",time()));
                }
                //循环处理
                $i=0;
                foreach($customer as $key=>$val){
                    //进行数据处理
                    $this->handleSyncCustomer($val);
                }
                $page++;
                //结束跳出
                if($end){
                    break;
                }
            }
            //执行结束进行文本report
            log_info('新零售数据同步提示：结束同步 2 ' .date("Y-m-d H:i:s",time()));
        }

        //新增/变更公司信息执行方法
        private function handleSyncCustomer($data)
        {
            //检查异常数据 companyId 为空 report;
            if(intval($data['companyId'])==0){
                $this->reportLog(2,['new_data'=>$data],'数据异常--公司id为空');
                return null;
            }

            //数据查询
            $exits_data=$this->getModel('ErpCustomer')->findCustomer(['data_source'=>3,'data_source_id'=>intval($data['companyId'])]);

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
            $CustomerData=[
                'customer_name'=>isset($data['companyName'])?trim($data['companyName']):'',
                'customer_short_name'=>isset($data['companyShortname'])?trim($data['companyShortname']):'',
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
                'business_attributes'=>2,
                'audit_status'=>1,
                'creater'=>'',
                'data_source'=>3,
                'data_source_id'=>intval($data['companyId']),
            ];
            //用户数据
            $CustomerUserData=[
                'user_name'=>isset($data['contactsName'])?trim($data['contactsName']):'',
                'user_phone'=>isset($data['contactsPhone'])?trim($data['contactsPhone']):'',
                'data_source_id'=>isset($data['contactsId'])?intval($data['contactsId']):0,
                'data_source'=>2,
            ];
//            echo intval($data['companyId']);
//            echo json_encode($CustomerUserData,JSON_UNESCAPED_UNICODE);echo "\n\r";

            $change_report=true;
            if(!$exits_data){
                //进行追加
                $CustomerData['create_time']=$CustomerData['update_time']=date('Y-m-d H:i:s',time());
                $customer_id=$this->getModel('ErpCustomer')->addCustomer($CustomerData);
            }else{
                $update_customer=false;
                //数据判断后进行变更
                foreach($CustomerData as $key=>$val){
                    if($val!=$exits_data[$key]){
                        $update_customer=true;
                        break;
                    }
                }
                $customer_id=$exits_data['id'];
                if($update_customer){
                    $CustomerData['update_time']=date('Y-m-d H:i:s',time());
                    $this->getModel('ErpCustomer')->saveCustomer(['id'=>$exits_data['id']],$CustomerData);
                }else{
                    $change_report=false;
                }
            }
            //变更report
            if($change_report){
                $this->reportLog(2,['company_id'=>$customer_id,'new_data'=>$CustomerData,'old_data'=>(!$exits_data?[]:$exits_data),'request_data'=>$data]);
            }
            $CustomerUserData['customer_id']=$customer_id;
            //变更公司员工信息
            $this->syncCustomerUser($CustomerUserData);
        }

        //同步公司用户信息
        private function syncCustomerUser($data)
        {
            //检查异常数据 data_source_id 为空 report;
            if($data['data_source_id']==0){
                $this->reportLog(4,['new_data'=>$data],'数据异常--公司员工id为空');
                return null;
            }
            //数据查询
            $exits_data=$this->getModel('ErpCustomerUser')->findCustomerUser(['customer_id'=>$data['customer_id'],'data_source'=>$data['data_source'],'data_source_id'=>$data['data_source_id']]);

            $change_report=true;
            if(!$exits_data){
                //数据追加
                $data['create_time']=$data['update_time']=date('Y-m-d H:i:s',time());
                $customer_user_id=$this->getModel('ErpCustomerUser')->addCustomerUser($data);
            }else{
                $update_customer=false;
                //数据判断后进行变更
                foreach($data as $key=>$val){
                    if($val!=$exits_data[$key]){
                        $update_customer=true;
                        break;
                    }
                }
                $customer_user_id=$exits_data['id'];
                if($update_customer){
                    $data['update_time']=date('Y-m-d H:i:s',time());
                    $this->getModel('ErpCustomerUser')->saveCustomerUser(['id'=>$exits_data['id']],$data);
                }else{
                    $change_report=false;
                }
            }
            //变更report
            if($change_report){
                $this->reportLog(4,['company_id'=>$customer_user_id,'new_data'=>$data,'old_data'=>(!$exits_data?[]:$exits_data)]);
            }
        }

    }