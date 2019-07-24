<?php
/**
 *
 * Author: ypm        Time: 2016-10-20
 */
namespace Common\Event;

use Think\Controller;

class JiguanMessageEvent extends Controller
{

    private $_appKey = array(
        '1' => [
            'app_key' => '08f57fafb0ca124e850c6d78',
            'master_secret' => '94a90d80dc846cbba3d5f071',
        ]
    );

    /**
     * 推送极光消息
     * @param int $userID 交易员ID
     * @param string $from app名称
     * @param int $messageType 信息类型1:订单消息 2:系统消息
     * @param string $title 信息标题
     * @param string $content 信息内容
     * @param string $extra 自定义消息内容
     * @param int $relation_id 关联内容ID
     * @param int $relation_type 关联内容类型1:订单
     */
    public function pushMessage($userID, $from, $messageType, $title, $content, $extra = '', $relation_id = '', $relation_type = '')
    {

        //保存消息
        $message = $this->setOrderMessage($from, $messageType, $title, $content, $extra, $relation_id, $relation_type);
        $messageID = D('jiguan_message')->add($message);

        //保存推送用户
        $push = $this->setPushMessage($messageID, $this->setPushUser($userID), $messageType);
        D('jiguan_push_user')->addAll($push);

        $fromCode = $this->setFromCode($from);

        $extras = array(
            'messageType' => $messageType,
            'relation_id' => $relation_id,
            'relation_type' => $relation_type,
        );
        //极光推送
        if (isset($this->_appKey[$fromCode]) && !empty($this->_appKey[$fromCode]['app_key']) && !empty($this->_appKey[$fromCode]['master_secret'])) {
            $send = new \Home\Event\SendPhoneMessage($this->_appKey[$fromCode]['app_key'], $this->_appKey[$fromCode]['master_secret']);
            $registration = $send->listegistration($userID);
            if (!empty($registration['body']['registration_ids'])) {
                $send->conditionSend('all', $message['message_title'], $message['message_content'], '', '', $this->setPushUser($userID), '', $extras);
            }
        }
    }

    /**
     * 编辑用户的推送对象
     * @param int $userID 交易员ID
     */
    public function setPushUser($userID)
    {
        //user_id=13,user_name='蒋楠'
        return array($userID);
    }

    /**
     * 编辑app code
     * @param string $from app名称
     */
    public function setFromCode($from)
    {
        $fromCode = array(
            '油沃客' => 1
        );
        return $fromCode[$from];
    }

    /**
     * 编辑消息
     * @param unknown $param
     */
    public function setOrderMessage($from, $messageType, $title, $content, $extra, $relation_id, $relation_type)
    {
        $message = array(
            'message_title' => $title,                        // '推送标题',
            'message_content' => $content,                      // '推送内容',
            'message_create_time' => date('Y-m-d H:i:s'),        // '创建时间',
            'message_available' => 1,                            // '是否有效(0无效，1有效)',
            'message_from' => $from,                                 // '信息来源1:油沃客',
            'message_type' => $messageType,                                 // '信息类型1:订单消息',
            'message_extra' => $extra,                           // '自定义信息内容',
            'message_relation_id' => $relation_id,               // '关联内容ID',
            'message_relation_type' => $relation_type            // '关联内容类型',
        );

        return $message;
    }


    public function setPushMessage($messageID, $userID, $messageType)
    {
        foreach ($userID as $k => $val) {
            $push[] = array(
                'message_id' => $messageID,              // '消息jiguan_message的id',
                'user_id' => $val,                    // '推送用户ID',
                'user_type' => 1,                        // '推送用户类型1：交易员 2：用户”',
                'create_time' => date('Y-m-d H:i:s'),    // '创建时间',
                'status' => 0,                            // '推送状态(0未查看，1已查看)',
                'message_type' => $messageType                            // '消息类型',
            );
        }
        return $push;
    }
}



            





