<?php
    /**
     * Created by PhpStorm.
     * User: hanfeng
     * Date: 2018/10/16
     * Time: 15:30
     * 同步新零售服务商网点信息
     */

    namespace Crons\Event;
    class RyncStorehouseEvent extends BaseEvent
    {
        private $api_url='';
        public function __construct()
        {
            parent::__construct();
            $this->api_url=C('NewRetailUrl').'getSkidList';
        }

        //同步服务商网点信息
        public function syncStorehouse()
        {
            //执行开始进行文本report
            log_info('新零售数据同步提示：开始同步 5 ' .date("Y-m-d H:i:s",time()));
            $end=false;
            $page=1;
            while(true) {
                //拉取信息
                $query_data = [
                    'page' => $page,
                    'pageSize' => '100',
                    'sign' => $this->makeSign(),
                ];
                $storehouse_list = http($this->api_url, $query_data);
                $storehouse_list = json_decode($storehouse_list, true);
                //接口异常提醒
                if((!isset($storehouse_list['msg'])||($storehouse_list['msg']!='成功'))){
                    $this->reportLog(2,['new_data'=>$storehouse_list['msg'].(isset($customer_list['message'])?$customer_list['message']:'')],$storehouse_list['msg'].(isset($customer_list['message'])?$customer_list['message']:''));
                }
                $storehouse = isset($storehouse_list['res']['list']) ? $storehouse_list['res']['list'] : [];
                if (count($storehouse) == 0) {
                    $end=true;
                    log_info('新零售数据同步提示：同步 5 ['.$page.'] 无数据' . date("Y-m-d H:i:s", time()));
                }
                //循环处理
                $i = 0;
                foreach ($storehouse as $key => $val) {
                    //进行数据处理
                    $this->handleSyncStorehouse($val);
                }
                $page++;
                //结束跳出
                if($end){
                    break;
                }
            }
            //执行结束进行文本report
            log_info('新零售数据同步提示：结束同步 5 ' .date("Y-m-d H:i:s",time()));
        }

        //新增/变更服务商网点执行方法
        private function handleSyncStorehouse($data)
        {
            //检查异常数据 skidId 为空 report;
            if(intval($data['skidId'])==0){
                $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商网点id为空');
                return null;
            }

            //检查异常数据 cityId 为空 report;
            if(intval($data['cityId'])==0){
                $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商city id为空');
                return null;
            }


            //地区查询
            if(intval($data['cityId'])==0){
                //服务商网点匹配不上repost
                $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商网点地区id匹配不到数据');
                return null;
            }else{
                $ara_info=$this->getModel('Area')->getArea('id',['map_code'=>intval($data['cityId'])]);
                if(count($ara_info)==0){
                    //服务商网点匹配不上repost
                    $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商网点地区id匹配不到数据');
                    return null;
                }
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
                $exits_data=$this->getModel('ErpStorehouse')->findErpStorehouse(['status'=>1,'data_source_oms_id'=>intval($data['skidId'])]);
                if($exits_data){
                    //检测是否存在于黑名单内
                    $error_data=$error_map_obj->findErpErrorDataMapping(['invalid_id'=>intval($data['skidId']),'type'=>2,'data_source'=>1]);
                    if($error_data){
                        $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'黑名单数据');
                        return null;
                    }
                    $update_type=1;
                    $check_action=false;
                }
            }
            if($check_action){
                $front_id=isset($data['scmId'])?intval($data['scmId']):0;
                $exits_data=$this->getModel('ErpStorehouse')->findErpStorehouse(['status'=>1,'data_source_front_id'=>$front_id]);
                if(($front_id!=0)&&$exits_data){
                    //检测是否存在于黑名单内
                    $error_data=$error_map_obj->findErpErrorDataMapping(['invalid_id'=>$front_id,'type'=>2,'data_source'=>2]);
                    if($error_data){
                        $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'黑名单数据');
                        return null;
                    }
                    $update_type=2;
                    $check_action=false;
                }else{
                    $exits_data=null;
                }
            }
            if($check_action){
                $ossName=isset($data['ossName'])?trim($data['ossName']):'';
                $exits_data=$this->getModel('ErpStorehouse')->findErpStorehouse(['status'=>1,'storehouse_name'=>$ossName]);
                if(($ossName!='')&&$exits_data){
                    $update_type=3;
                    $check_action=false;
                }else{
                    $exits_data=null;
                }
            }

            if($update_type==4){
                //服务商查询 将服务商的查询限制调整为 新增时检测
                $storehouse_info=$this->getModel('ErpSupplier')->findSupplier(['status'=>1,'data_source_oms_id'=>intval($data['ospId'])]);
                if(!$storehouse_info){
                    //服务商网点匹配不上repost
                    $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'数据异常--服务商id匹配不到服务商');
                    return null;
                }
                //检测是否存在于黑名单内
                $error_data=$error_map_obj->findErpErrorDataMapping(['invalid_id'=>intval($data['skidId']),'type'=>2,'data_source'=>1]);
                if($error_data){
                    $this->reportLog(5,['new_data'=>$data,'request_data'=>$data],'黑名单数据');
                    return null;
                }
            }

            //服务商网点数据
            $StorehouseData=[
                'storehouse_name'=>isset($data['ossName'])?trim($data['ossName']):'',
                'region'=>intval($ara_info['id']),
                'tel'=>isset($data['telNum'])?trim($data['telNum']):'',
                'type'=>7,
                'company_id'=>intval($storehouse_info['id']),
                'is_new'=>1,
                'data_source_id'=>intval($data['skidId']),
                'data_source_oms_id'=>intval($data['skidId']),//新增冗余字段
                'data_source_front_id'=>isset($data['scmId'])?intval($data['scmId']):0,//新增front关联id
                'is_purchase'=>2,
                'is_sale'=>2,
            ];
            $change_report=true;
            if(!$exits_data){
                //进行追加
                $StorehouseData['create_time']=$StorehouseData['last_update_time']=$StorehouseData['update_time']=date('Y-m-d H:i:s',time());
                $storehouse_id=$this->getModel('ErpStorehouse')->addErpStorehouse($StorehouseData);
            }else{
                $update_torehouse=false;
                unset($StorehouseData['company_id']);//过滤掉公司的信息
                unset($StorehouseData['is_sale']);//过滤掉网点销售业务标识 edit xiaowen 2019-5-29 网点支持做销售单 防止销售标识被oms影响
                //数据判断后进行变更
                foreach($StorehouseData as $key=>$val){
                    if($val!=$exits_data[$key]){
                        $update_torehouse=true;
                        break;
                    }
                }
                $storehouse_id=$exits_data['id'];
                if($update_torehouse){
                    $StorehouseData['last_update_time']=$StorehouseData['update_time']=date('Y-m-d H:i:s',time());
                    $this->getModel('ErpStorehouse')->saveErpStorehouse(['id'=>$exits_data['id']],$StorehouseData);
                }else{
                    $change_report=false;
                }
            }
            //变更report
            if($change_report){
                $this->reportLog(5,['company_id'=>$storehouse_id,'new_data'=>$StorehouseData+['change_position'=>$update_type],'old_data'=>(!$exits_data?[]:$exits_data),'request_data'=>$data]);
            }
        }

    }