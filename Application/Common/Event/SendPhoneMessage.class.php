<?php

/**
 * 极光推送类
 * Author: ypm        Time: 2016-09-26
 * @example
 * $send = new \LIB\SendPhoneMessage($app_key,$master_secret);
 * $send->conditionSend('all', '推送title1', '推送内容1'); //推送消息
 * $send->sendByTime('all', '定时推送title1', '定时推送内容1', '2016-09-27 11:00:00', '1111');//定时推送消息
 * $send->sendByEveryday('all', '每天推送title1', '每天推送内容1', $time, '2222');//每天推送消息
 */
namespace Common\Event;

Vendor('jpush.autoload');
use JPush\Client as JPush;

class SendPhoneMessage
{

    private $_masterSecret = '';

    private $_appkeys = '';

    /**
     * 构造函数
     *
     * @param string $masterSecret
     * @param string $appkeys
     */
    function __construct($appkeys, $masterSecret)
    {
        $this->_masterSecret = $masterSecret;
        $this->_appkeys = $appkeys;
    }

    /**
     * build object $push
     *
     * @param object $push
     *            $client->push()
     * @param string /array $platform
     *            ('all' or ['ios','android'])
     * @param string $title
     *            通知的标题
     * @param array $content
     *            消息内容
     * @param array $type
     *            消息类型（1：订单消息（默认））
     * @param array $tag
     *            组名称数组
     * @param array $regId
     *            设备数组
     * @param array $options
     * @param array $extras
     *            消息关联内容信息的键值对
     */
    function buildMessage($push, $platform, $title, $content, $type = 1, $tag = '', $regId = '', $options = '', $extras = array())
    {
        $push->setPlatform($platform);
        if (empty($tag) && empty($regId)) {
            $push->addAllAudience();
        } else {
            (empty($tag)) ? '' : $push->addTag($tag);
            (empty($regId)) ? '' : $push->addAlias($regId);
        }
        $ios_notification = array(
            'sound' => $title,
            'badge' => '+1',
            'content-available' => true,
            'extras' => $extras
        );
        $push->iosNotification($title, $ios_notification);

        $android_notification = array(
            'title' => $title,
            'build_id' => 2,
            'extras' => $extras
        );
        $push->androidNotification($title, $android_notification);

        $message = array(
            'title' => $title,
            'content_type' => 'text',
            'extras' => $extras
        );
        $push->message($content, $message);
        $options['apns_production'] = true;
        empty($options) ? '' : $push->options($options);
        return $push;
    }

    /**
     * 条件推送
     *
     * @param string /array $platform
     *            ('all' or ['ios','android'])
     * @param string $title
     *            通知的标题
     * @param array $content
     *            消息内容
     * @param array $type
     *            消息类型（1：订单消息（默认））
     * @param array $tag
     *            组名称数组
     * @param array $regId
     *            设备数组
     * @param array $options
     *            $options=array(
     *            "sendno"=>1, //推送序号
     *            "time_to_live"=>86400, //离线消息保留时长(秒)
     *            "override_msg_id"=>0, //要覆盖的消息ID
     *            "apns_production"=>true, //True:推送生产环境，False:要推送开发环境
     *            "big_push_duration"=>10) //定速推送时长(分钟),最大值为1400
     * @param array $extras
     *            消息关联内容信息的键值对
     */
    function conditionSend($platform, $title, $content, $type = '', $tag = '', $regId = '', $options = '', $extras = array())
    {
        $client = new JPush($this->_appkeys, $this->_masterSecret);

        $push = $client->push();
        $push = $this->buildMessage($push, $platform, $title, $content, $type, $tag, $regId, $options);
        try {
            $response = $push->send();
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            // try something here
            print $e;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            // try something here
            print $e;
        }
    }

    /**
     * 定时推送
     *
     * @param string /array $platform
     *            ('all' or ['ios','android'])
     * @param string $title
     *            通知的标题
     * @param array $content
     *            消息内容
     * @param array $type
     *            消息类型（1：订单消息（默认））
     * @param string $time
     *            定时时间字符串（‘2016-09-26 13:45:00’）
     * @param string $name
     *            定时推送任务名称
     * @param array $tag
     *            组名称数组
     * @param array $regId
     *            设备数组
     * @param array $options
     *            $options=array(
     *            "sendno"=>1, //推送序号
     *            "time_to_live"=>86400, //离线消息保留时长(秒)
     *            "override_msg_id"=>0, //要覆盖的消息ID
     *            "apns_production"=>true, //True:推送生产环境，False:要推送开发环境
     *            "big_push_duration"=>10) //定速推送时长(分钟),最大值为1400
     */
    function sendByTime($platform, $title, $content, $time, $name, $type = '', $tag = '', $regId = '', $options = '')
    {
        $client = new JPush($this->_appkeys, $this->_masterSecret);

        $push = $client->push();
        $push = $this->buildMessage($push, $platform, $title, $content, $type, $tag, $regId, $options);
        $payload = $push->build();
        try {
            $response = $client->schedule()->createSingleSchedule($name, $payload, array(
                "time" => $time
            ));
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            // try something here
            print $e;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            // try something here
            print $e;
        }
    }

    /**
     * 每天定时推送
     *
     * @param string /array $platform
     *            ('all' or ['ios','android'])
     * @param string $title
     *            通知的标题
     * @param array $content
     *            消息内容
     * @param array $type
     *            消息类型（1：订单消息（默认））
     * @param array $time
     *            定时时间触发器
     *            $time=array(
     *            "start"=>"2016-09-26 13:45:00",
     *            "end"=>"2016-10-10 13:45:00",
     *            "time"=>"11:00:00",
     *            "time_unit"=>"DAY",
     *            "frequency"=>1)
     * @param string $name
     *            定时推送任务名称
     * @param array $tag
     *            组名称数组
     * @param array $regId
     *            设备数组
     * @param array $options
     *            $options=array(
     *            "sendno"=>1, //推送序号
     *            "time_to_live"=>86400, //离线消息保留时长(秒)
     *            "override_msg_id"=>0, //要覆盖的消息ID
     *            "apns_production"=>true, //True:推送生产环境，False:要推送开发环境
     *            "big_push_duration"=>10) //定速推送时长(分钟),最大值为1400
     */
    function sendByEveryday($platform, $title, $content, $time, $name, $type = '', $tag = '', $regId = '', $options = '')
    {
        $client = new JPush($this->_appkeys, $this->_masterSecret);

        $push = $client->push();
        $push = $this->buildMessage($push, $platform, $title, $content, $type, $tag, $regId, $options);
        $payload = $push->build();
        try {
            $response = $client->schedule()->createPeriodicalSchedule($name, $payload, $time);
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            // try something here
            print $e;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            // try something here
            print $e;
        }
    }

    // // 获取定时任务列表
    // function listSchedule(){
    // $client = new JPush($this->_appkeys, $this->_masterSecret);
    // $schedule = $client->schedule();
    // $response=$schedule->getSchedules($page=1);
    // // header("Content-type:text/html;charset=utf-8");
    // // var_export($response);exit();
    // print_r($response);
    // }

    // 获取别名设备
    function listegistration($alias = '')
    {
        $client = new JPush($this->_appkeys, $this->_masterSecret);
        $device = $client->device();
        $response = $device->getAliasDevices($alias);
        return $response;
    }
}

// $app_key = '08f57fafb0ca124e850c6d78';
// $master_secret = '94a90d80dc846cbba3d5f071';
// $send = new SendPhoneMessage($app_key, $master_secret);
// $send->listegistration('');
// $send->conditionSend('all', '测试请忽略...', '推送内容1');
// $send->sendByTime('all', '定时测试请忽略...', '定时推送内容1', '2016-09-27 11:00:00', '1111');
// $time = array(
// "start" => "2016-09-26 13:45:00",
// "end" => "2016-10-10 13:45:00",
// "time" => "11:00:00",
// "time_unit" => "DAY",
// "frequency" => 1
// );
// $send->sendByEveryday('all', '每天测试请忽略...', '每天推送内容1', $time, '每天11点发送的定时任务测试');
?>
