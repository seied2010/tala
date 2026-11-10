<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Notifications{
    private static function key():string{return hash('sha256',wp_salt('auth').'gc-notification-v1',true);}
    public static function enqueue(string $key,string $to,string $subject,string $body):void{
        if(!is_email($to))return;self::enqueueChannel('email',$key,$to,$subject,$body);
    }
    public static function enqueueChannel(string $channel,string $key,string $to,string $subject,string $body):void{
        global $wpdb;
        if($channel!=='email'&&!isset(((array)apply_filters('gc_notification_channels',[]))[$channel]))throw new \DomainException('notification_channel_unconfigured');
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $payload=base64_encode($nonce.sodium_crypto_secretbox(wp_json_encode(compact('to','subject','body','channel')),$nonce,self::key()));
        $wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.Database::table('notifications')." (dedup_key,payload,state,next_try) VALUES (%s,%s,'pending',%d)",$key,$payload,time()));
        if(function_exists('as_enqueue_async_action'))as_enqueue_async_action('gc_send_notifications',[],'gold-commerce',true);
    }
    public static function drain():void{
        global $wpdb;Database::lock('notifications',static function()use($wpdb){
            $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Database::table('notifications')." WHERE state IN ('pending','retry') AND next_try<=%d ORDER BY id LIMIT 20",time()),ARRAY_A);
            foreach($rows as $row){
                try{
                    $raw=base64_decode($row['payload'],true);$plain=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),self::key());if($plain===false)throw new \RuntimeException('notification_decrypt_failed');
                    $p=json_decode($plain,true,16,JSON_THROW_ON_ERROR);
                    $wpdb->update(Database::table('notifications'),['state'=>'sending'],['id'=>$row['id']]);
                    if(($p['channel']??'email')==='email')$ok=wp_mail($p['to'],$p['subject'],$p['body']);
                    else{$channels=(array)apply_filters('gc_notification_channels',[]);$adapter=$channels[$p['channel']]??null;if(!$adapter instanceof NotificationChannel)throw new \RuntimeException('channel_unavailable');$result=$adapter->send($p['to'],$p['subject'],$p['body'],$row['dedup_key']);if($result==='UNKNOWN')continue;$ok=$result==='ACCEPTED';}
                    if(!$ok)throw new \RuntimeException('mail_failed');
                    $wpdb->update(Database::table('notifications'),['state'=>'sent','payload'=>'','attempts'=>(int)$row['attempts']+1],['id'=>$row['id']]);
                }catch(\Throwable $e){$attempt=(int)$row['attempts']+1;$wpdb->update(Database::table('notifications'),['state'=>$attempt>=6?'dead':'retry','attempts'=>$attempt,'next_try'=>time()+min(3600,30*(2**$attempt))],['id'=>$row['id']]);Database::audit('notification.failed',(string)$row['id'],['attempt'=>$attempt]);}
            }
        });
    }
}
