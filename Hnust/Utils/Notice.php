<?php

namespace Hnust\Utils;

use Hnust\Config;
use Hnust\Utils\Wechat;
use Hnust\Analyse\Push;

require_once __DIR__ . '/../../library/taobao-sdk/TopSdk.php';
require_once __DIR__ . '/../../library/PHPMailer/PHPMailerAutoload.php';

class Notice
{
    //Socket通知
    public static function socket($uid, $title, $content, $url)
    {
        $push = new Push();
        $data = $push->add($uid, $url? 1:0, $title, $content, $url);
        return $push->socket($data);
    }

    //微信提醒
    public static function wechat($uid, $type, $data)
    {
        return Wechat::sendMsg($uid, $type, $data);
    }

    //邮件提醒
    public static function mail($address, $title, $content)
    {
        if (empty($address)) {
            return false;
        }

        $mail = new \PHPMailer();

        //服务器配置
        $mail->isSMTP();
        $mail->SMTPAuth=true;
        $mail->Host = 'smtp.qq.com';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        //用户名设置
        $mailInfo = Config::getConfig('mail_info');
        $mailInfo = json_decode($mailInfo, true);
        $mail->FromName = $mailInfo['fromName'];
        $mail->Username = $mailInfo['userName'];
        $mail->Password = $mailInfo['password'];
        $mail->From = $mailInfo['from'];
        $mail->addAddress($address);

        //内容设置
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = $content;

        //返回结果
        if ($mail->send()) {
            return true;
        } else {
            return false;
        }
    }

    //短信提醒
    public static function sms($phone, $template, $smsParam = array())
    {
        if (empty($phone)) {
            return false;
        }

        $smsInfo  = Config::getConfig('sms_info');
        $smsInfo  = json_decode($smsInfo, true);
        $smsParam = json_encode($smsParam);

        $c = new \TopClient();
        $c->appkey    = $smsInfo['appkey'];
        $c->secretKey = $smsInfo['secretKey'];
        $req = new \AlibabaAliqinFcSmsNumSendRequest();
        $req->setExtend($phone);
        $req->setSmsType("normal");
        $req->setSmsFreeSignName($smsInfo['signName']);
        $req->setSmsParam($smsParam);
        $req->setRecNum($phone);
        $req->setSmsTemplateCode($template);
        $resp = $c->execute($req);
        return !!$resp->result->success;
    }
}