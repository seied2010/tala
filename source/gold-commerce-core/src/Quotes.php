<?php
declare(strict_types=1);
namespace GoldCommerce;

final class Quotes {
    public static function request(int $productId,int $qty,array $customer):array{
        $access=Rest::publicAccess();if(is_wp_error($access))throw new \DomainException('request_rate_limited');
        $s=Plugin::settings();
        if(!get_current_user_id()&&empty($s['guest_quotes']))throw new \DomainException('login_required');
        if($qty<1||$qty>100||!is_email($customer['email']??''))throw new \DomainException('request_invalid');
        $product=wc_get_product($productId);
        if(!$product||$product->get_status()!=='publish'||!$product->get_meta('_gc_product'))throw new \DomainException('product_invalid');
        $order=wc_create_order(['customer_id'=>get_current_user_id(),'status'=>'gc-await-quote']);
        if(is_wp_error($order))throw new \RuntimeException('order_failed');
        $order->set_billing_email(sanitize_email($customer['email']));$order->set_billing_first_name(sanitize_text_field($customer['name']??''));
        $order->add_product($product,$qty,['subtotal'=>'0','total'=>'0']);$order->set_currency('IRR');
        $owner=bin2hex(random_bytes(32));$order->update_meta_data('_gc_owner_hash',hash('sha256',$owner));$order->update_meta_data('_gc_specialized','yes');$order->save();
        Database::audit('quote.requested',(string)$order->get_id(),['product'=>$productId,'qty'=>$qty]);
        Notifications::enqueue('requested:'.$order->get_id(),get_option('admin_email'),'استعلام قیمت جدید','درخواست جدید شماره '.$order->get_id());
        return ['order_id'=>$order->get_id(),'owner_token'=>$owner,'status'=>'requested'];
    }
    public static function issue(int $orderId,?string $approvedAmount=null,bool $internal=false):array{
        if(!$internal&&!current_user_can('gc_issue_quotes'))throw new \DomainException('forbidden');
        return Database::lock('order:'.$orderId,static function()use($orderId,$approvedAmount,$internal){
            global $wpdb;$order=wc_get_order($orderId);if(!$order||$order->is_paid()||$order->get_meta('_gc_settled'))throw new \DomainException('order_not_quotable');
            if($order->has_status(['cancelled','refunded','completed','processing']))throw new \DomainException('order_not_quotable');
            if(!Plugin::canSell())throw new \DomainException('agah_reservation_api_required');
            if(empty(Plugin::settings()['finance_configured']))throw new \DomainException('finance_not_configured');
            $latest=self::latest($orderId);if($latest&&$latest['status']==='payment_started')throw new \DomainException('payment_in_progress');
            $snapshots=[];$sum='0';
            foreach($order->get_items() as $item){
                $product=$item->get_product();if(!$product)throw new \DomainException('product_missing');
                if($product->get_meta('_gc_product')){
                    if($approvedAmount!==null){
                        if(count($order->get_items())!==1)throw new \DomainException('manual_amount_single_line_only');
                        $amount=Decimal::money($approvedAmount);
                        $snapshot=['schema_version'=>'1','currency'=>'IRR','amount_irr'=>$amount,'components'=>['final_price'=>$amount],'tax_treatment'=>'INCLUDED_UNITEMIZED','product'=>Plugin::model($product),'rate'=>[],'rounding'=>Plugin::settings()['policy']['rounding'],'created_at_utc'=>gmdate('c'),'quantity'=>(string)$item->get_quantity(),'approved_by'=>get_current_user_id(),'policy_version'=>Plugin::settings()['policy']['version'],'manual_order_total'=>true];
                        $item->set_subtotal($amount);$item->set_total($amount);
                    }else{
                        $snapshot=Plugin::price($product);
                        if($internal){$previous=json_decode((string)$item->get_meta('_gc_snapshot'),true);if(!$previous||Plugin::fingerprint($previous)!==Plugin::fingerprint($snapshot))throw new \DomainException('checkout_requote_required');}
                        $amount=Decimal::mul($snapshot['amount_irr'],(string)$item->get_quantity());$item->set_subtotal($amount);$item->set_total($amount);
                    }
                    $item->set_taxes(['total'=>[],'subtotal'=>[]]);$item->add_meta_data('_gc_snapshot',wp_json_encode($snapshot),true);$item->save();
                    $snapshots[]=['item_id'=>$item->get_id(),'product_id'=>$product->get_id(),'quantity'=>(string)$item->get_quantity(),'snapshot'=>$snapshot];
                    $sum=Decimal::add($sum,$amount);
                }else throw new \DomainException('mixed_quote_unsupported');
            }
            if(!$snapshots||$sum==='0')throw new \DomainException('empty_quote');
            $needsShipping=false;foreach($order->get_items() as $item)if($item->get_product()?->needs_shipping())$needsShipping=true;
            if($needsShipping&&count($order->get_items('shipping'))===0)throw new \DomainException('shipping_method_required');
            if($needsShipping&&(!$order->get_shipping_address_1()&&!$order->get_billing_address_1()))throw new \DomainException('shipping_address_required');
            $order->calculate_totals(false);$total=Decimal::money((string)$order->get_total());
            if($order->get_currency()!=='IRR')throw new \DomainException('currency_mismatch');
            $ttl=(int)Plugin::settings()['quote_ttl'];$now=time();$uuid=wp_generate_uuid4();$token=bin2hex(random_bytes(32));
            // Woo's held-stock table performs its own atomic availability check across orders.
            Stock::reserve($order,$ttl+(int)Plugin::settings()['payment_grace']);if(Plugin::mode()==='agah_connected')do_action('gc_agah_reserve',$order);
            $previousSuppress=$wpdb->suppress_errors(true);
            $wpdb->query('START TRANSACTION');
            try{
                // Updating an absent order range locks its insertion gap in InnoDB and
                // deadlocks concurrent new orders. Lock/update only an existing UUID.
                if($latest&&in_array($latest['status'],['issued','expired','failed'],true))$wpdb->update(Database::table('quotes'),['status'=>'superseded','token_hash'=>''],['uuid'=>$latest['uuid'],'status'=>$latest['status']]);
                $snapshot=['lines'=>$snapshots,'shipping'=>(string)$order->get_shipping_total(),'shipping_tax'=>(string)$order->get_shipping_tax(),'fees'=>(string)$order->get_total_fees(),'order_total'=>$total,'currency'=>'IRR','authority'=>'woocommerce','policy'=>Plugin::settings()['policy'],'payment_grace'=>(int)Plugin::settings()['payment_grace'],'seller'=>['name'=>get_bloginfo('name'),'contact'=>get_option('gc_contact_text','')],'customer'=>['name'=>$order->get_formatted_billing_full_name(),'address'=>$order->get_billing_address_1(),'city'=>$order->get_billing_city()],'invoice_number'=>(Plugin::settings()['invoice_prefix']??'GC-').$orderId];
                $row=['uuid'=>$uuid,'order_id'=>$orderId,'version'=>$latest?(int)$latest['version']+1:1,'status'=>'issued','token_hash'=>hash('sha256',$token),'snapshot'=>wp_json_encode($snapshot),'amount_irr'=>$total,'issued_at'=>$now,'expires_at'=>$now+$ttl];
                if(!$wpdb->insert(Database::table('quotes'),$row))throw new \RuntimeException('quote_persistence_failed');
                $wpdb->query('COMMIT');
            }catch(\Throwable $e){$wpdb->query('ROLLBACK');Stock::release($order);throw $e;}
            finally{$wpdb->suppress_errors($previousSuppress);}
            $order->update_meta_data('_gc_quote_uuid',$uuid);$order->update_meta_data('_gc_specialized','yes');$order->update_meta_data('_gc_snapshot_hash',hash('sha256',$row['snapshot']));
            if($internal&&!$order->get_customer_id()&&!$order->get_meta('_gc_owner_hash')&&WC()->session){$owner=bin2hex(random_bytes(32));$order->update_meta_data('_gc_owner_hash',hash('sha256',$owner));$tokens=(array)WC()->session->get('gc_owner_tokens',[]);$tokens[$orderId]=$owner;WC()->session->set('gc_owner_tokens',$tokens);}
            $order->update_meta_data('_gc_quote_stage','issued');$order->save();
            self::event($uuid,'issued',['version'=>$row['version'],'amount_irr'=>$total]);
            $link=add_query_arg(['gc_quote'=>$uuid,'gc_token'=>$token],home_url('/'));
            if(!$internal)Notifications::enqueue('issued:'.$uuid,$order->get_billing_email(),'مظنه تأییدشده','مبلغ: '.$total.' ریال؛ اعتبار تا '.gmdate('c',$now+$ttl)."\n".$link);
            return $row+['token'=>$token,'payment_url'=>$link];
        });
    }
    public static function latest(int $orderId):?array{
        global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.Database::table('quotes').' WHERE order_id=%d ORDER BY version DESC LIMIT 1',$orderId),ARRAY_A)?:null;
    }
    public static function get(string $uuid):?array{global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.Database::table('quotes').' WHERE uuid=%s',$uuid),ARRAY_A)?:null;}
    public static function begin(string $uuid,string $token):array{
        $initial=self::get($uuid);if(!$initial)throw new \DomainException('quote_not_found');
        return Database::lock('order:'.$initial['order_id'],static function()use($initial,$token,$uuid){
            global $wpdb;$q=self::get($uuid);$latest=self::latest((int)$q['order_id']);
            if(strlen($token)!==64||!hash_equals($q['token_hash'],hash('sha256',$token)))throw new \DomainException('invalid_token');
            if($q['status']!=='issued'||$latest['uuid']!==$uuid)throw new \DomainException('quote_not_payable');
            if((int)$q['expires_at']<=time()){self::expireOne($q);throw new \DomainException('quote_expired');}
            if(!Plugin::canSell())throw new \DomainException('agah_reservation_api_required');
            $order=wc_get_order($q['order_id']);if(!$order||$order->is_paid()||$order->has_status(['cancelled','refunded']))throw new \DomainException('order_not_payable');
            self::assertOrder($order,$q);
            $snapshot=json_decode($q['snapshot'],true);$grace=(int)($snapshot['payment_grace']??Plugin::settings()['payment_grace']);Stock::reserve($order,$grace);
            $intent=wp_generate_uuid4();
            $changed=$wpdb->query($wpdb->prepare('UPDATE '.Database::table('quotes')." SET status='payment_started',started_at=%d,settlement_deadline=%d,intent=%s,token_hash='' WHERE uuid=%s AND status='issued'",time(),time()+$grace,$intent,$uuid));
            if($changed!==1)throw new \DomainException('payment_already_started');
            $order->set_status('pending');$order->update_meta_data('_gc_intent',$intent);$order->save();
            if(WC()->session)WC()->session->set('gc_payment_'.$order->get_id(),$intent);
            self::event($uuid,'payment_started',['intent'=>$intent]);
            return ['intent'=>$intent,'order_id'=>$order->get_id(),'payment_url'=>$order->get_checkout_payment_url()];
        });
    }
    /** Called by a gateway adapter ONLY after its own server-to-server verification. */
    public static function settle(int $orderId,string $transaction,string $verifiedIrr,string $intent,string $provider='adapter'):string{
        if($transaction===''||strlen($transaction)>191)throw new \DomainException('transaction_invalid');
        Decimal::integer($verifiedIrr);
        return Database::lock('order:'.$orderId,static function()use($orderId,$transaction,$verifiedIrr,$intent,$provider){
            global $wpdb;$order=wc_get_order($orderId);$q=self::latest($orderId);if(!$order||!$q)throw new \DomainException('quote_missing');
            $receipts=Database::table('payment_receipts');
            $seen=$wpdb->get_row($wpdb->prepare("SELECT * FROM $receipts WHERE provider=%s AND transaction_id=%s",$provider,$transaction),ARRAY_A);
            if($seen){
                if((int)$seen['order_id']!==$orderId||$seen['amount_irr']!==$verifiedIrr||$seen['intent']!==$intent)throw new \DomainException('settlement_conflict');
                if($q['status']==='paid'&&$q['transaction_id']===$transaction){
                    if(!$order->is_paid()){$order->update_meta_data('_gc_settled','yes');$order->update_meta_data('_gc_invoice_snapshot',$q['snapshot']);$order->save();$order->payment_complete($transaction);}
                    if(!$order->is_paid())throw new \RuntimeException('payment_reconciliation_pending');
                    $wpdb->update($receipts,['status'=>'paid'],['id'=>$seen['id']]);do_action('gc_verified_paid',$order);return 'duplicate';
                }
                if($seen['status']==='payment_review')return 'payment_review';
                $receiptId=(int)$seen['id'];$receivedAt=(int)$seen['received_at'];
            }else{
                $receipt=['provider'=>$provider,'transaction_id'=>$transaction,'order_id'=>$orderId,'amount_irr'=>$verifiedIrr,'intent'=>$intent,'status'=>'received','received_at'=>time()];
                if(!$wpdb->insert($receipts,$receipt))throw new \DomainException('settlement_conflict');$receiptId=$wpdb->insert_id;$receivedAt=(int)$receipt['received_at'];
            }
            if($q['status']==='paid'){
                $wpdb->update($receipts,['status'=>'payment_review'],['id'=>$receiptId]);
                Database::audit('payment.extra_receipt',(string)$orderId,['amount_irr'=>$verifiedIrr]);throw new \DomainException('settlement_conflict');
            }
            $valid=$q['status']==='payment_started'&&hash_equals((string)$q['intent'],$intent)&&$q['amount_irr']===$verifiedIrr&&$order->get_currency()==='IRR'&&self::deadline($q)>=$receivedAt;
            $status=$valid?'paid':'payment_review';
            if($valid){try{self::assertOrder($order,$q);}catch(\Throwable $e){$valid=false;$status='payment_review';}}
            $wpdb->update(Database::table('quotes'),['status'=>$status,'transaction_id'=>$transaction],['uuid'=>$q['uuid']]);
            $order->update_meta_data('_gc_received_irr',$verifiedIrr);$order->update_meta_data('_gc_settled',$valid?'yes':'review');$order->set_transaction_id($transaction);
            if($valid){$order->update_meta_data('_gc_invoice_snapshot',$q['snapshot']);$order->save();$order->payment_complete($transaction);if(!$order->is_paid())throw new \RuntimeException('payment_reconciliation_pending');do_action('gc_verified_paid',$order);}
            else{$order->update_status('on-hold','پرداخت تأیید شده نیازمند تطبیق مبلغ/زمان/شناسه است.');$order->save();}
            $wpdb->update($receipts,['status'=>$status],['id'=>$receiptId]);
            self::event($q['uuid'],$status,['received_irr'=>$verifiedIrr]);return $status;
        });
    }
    public static function fail(int $orderId):void{
        Database::lock('order:'.$orderId,static function()use($orderId){global $wpdb;$q=self::latest($orderId);if(!$q||$q['status']!=='payment_started')return;
            // A failed attempt is terminal. A new seller-approved quote is needed for retry.
            $wpdb->update(Database::table('quotes'),['status'=>'failed'],['uuid'=>$q['uuid']]);$order=wc_get_order($orderId);Stock::release($order);self::event($q['uuid'],'failed');});
    }
    public static function expire():void{
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Database::table('quotes')." WHERE status='issued' AND expires_at<=%d LIMIT 100",time()),ARRAY_A);
        foreach($rows as $q)Database::lock('order:'.$q['order_id'],static function()use($q){$current=self::get($q['uuid']);if($current['status']==='issued')self::expireOne($current);});
        $soon=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Database::table('quotes')." WHERE status='issued' AND expires_at>%d AND expires_at<=%d LIMIT 100",time(),time()+(int)(Plugin::settings()['quote_warn_seconds']??60)),ARRAY_A);
        foreach($soon as $q)if($o=wc_get_order($q['order_id']))Notifications::enqueue('expiring:'.$q['uuid'],$o->get_billing_email(),'مهلت مظنه رو به پایان است','مظنه سفارش '.$o->get_id().' تا '.gmdate('c',(int)$q['expires_at']).' معتبر است. پس از انقضا قیمت جدید لازم خواهد بود.');
    }
    private static function expireOne(array $q):void{global $wpdb;$wpdb->update(Database::table('quotes'),['status'=>'expired','token_hash'=>''],['uuid'=>$q['uuid']]);$o=wc_get_order($q['order_id']);if($o){Stock::release($o);Notifications::enqueue('expired:'.$q['uuid'],$o->get_billing_email(),'مظنه منقضی شد','برای سفارش '.$o->get_id().' لطفاً مظنه جدید درخواست کنید.');}self::event($q['uuid'],'expired');}
    public static function event(string $uuid,string $type,array $meta=[]):void{global $wpdb;$wpdb->insert(Database::table('quote_events'),['quote_uuid'=>$uuid,'event_type'=>$type,'metadata'=>wp_json_encode($meta),'created_at'=>time()]);Database::audit('quote.'.$type,$uuid,$meta);}
    public static function deadline(array $q):int{return (int)($q['settlement_deadline']??((int)$q['started_at']+(int)Plugin::settings()['payment_grace']));}
    private static function assertOrder(\WC_Order $order,array $q):void{
        if(Decimal::money((string)$order->get_total())!==$q['amount_irr']||$order->get_currency()!=='IRR')throw new \DomainException('order_amount_changed');
        $snapshot=json_decode($q['snapshot'],true,32,JSON_THROW_ON_ERROR);$items=$order->get_items();
        if(count($items)!==count($snapshot['lines']))throw new \DomainException('order_items_changed');
        foreach($snapshot['lines'] as $line){$item=$items[$line['item_id']]??null;if(!$item||($item->get_variation_id()?:$item->get_product_id())!==$line['product_id']||(string)$item->get_quantity()!==(string)$line['quantity'])throw new \DomainException('order_items_changed');
            $p=$line['snapshot'];$expected=!empty($p['manual_order_total'])?$p['amount_irr']:Decimal::mul($p['amount_irr'],(string)$line['quantity']);
            if(Decimal::money((string)$item->get_total())!==$expected||Plugin::fingerprint(json_decode((string)$item->get_meta('_gc_snapshot'),true))!==Plugin::fingerprint($p))throw new \DomainException('order_items_changed');
        }
        if((string)$order->get_shipping_total()!==$snapshot['shipping']||(string)$order->get_shipping_tax()!==$snapshot['shipping_tax']||(string)$order->get_total_fees()!==$snapshot['fees'])throw new \DomainException('order_delivery_changed');
    }
}
