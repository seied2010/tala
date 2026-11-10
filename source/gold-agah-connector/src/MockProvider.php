<?php
declare(strict_types=1);
namespace GoldAgah;
use GoldCommerce\Database;
use GoldCommerce\Decimal;

/** Persistent simulator. Never registered as a production price or inventory authority. */
final class MockProvider {
    public const STATUS='MOCK_ONLY';
    public function __construct(public readonly string $store,public readonly string $company){}
    private function option():string{return 'gc_mock_'.substr(hash('sha256',$this->store.':'.$this->company),0,32);}
    private function state():array{wp_cache_delete($this->option(),'options');return get_option($this->option(),['stock'=>[],'reservations'=>[],'results'=>[],'invoices'=>[],'refunds'=>[]]);}
    public function seed(string $sku,int $qty,string $priceMinor):void{
        Decimal::integer($priceMinor);if($qty<0)throw new \DomainException('quantity_invalid');Database::lock($this->option(),function()use($sku,$qty,$priceMinor){$s=$this->state();$s['stock'][$sku]=['on_hand'=>$qty,'price_minor'=>$priceMinor,'version'=>1];update_option($this->option(),$s,false);});
    }
    public function health():array{return ['status'=>self::STATUS,'schema_version'=>'1','source_version'=>'alpha12-contract-simulator','store_uuid'=>$this->store,'company_id'=>$this->company,'reservation'=>true];}
    public function offline(bool $yes):void{update_option($this->option().'_offline',$yes,false);}
    private function check(array $p):void{
        if(get_option($this->option().'_offline'))throw new \RuntimeException('provider_offline');
        if(($p['store_uuid']??'')!==$this->store||($p['company_id']??'')!==$this->company)throw new \DomainException('company_scope_denied');
        if(($p['schema_version']??'')!=='1')throw new \DomainException('schema_version_unsupported');
        if(empty($p['idempotency_key'])||strlen((string)$p['idempotency_key'])>128)throw new \DomainException('idempotency_required');
    }
    private function operation(string $type,array $p,callable $fn,bool $timeout=false):array{
        $this->check($p);$result=Database::lock($this->option(),function()use($type,$p,$fn){
            $s=$this->state();$key=$type.':'.$p['idempotency_key'];$hash=hash('sha256',wp_json_encode($p));
            if(isset($s['results'][$key])){if($s['results'][$key]['hash']!==$hash)throw new \DomainException('idempotency_payload_conflict');return $s['results'][$key]['response'];}
            $response=$fn($s);$s['results'][$key]=['hash'=>$hash,'response'=>$response];update_option($this->option(),$s,false);return $response;
        });if($timeout)throw new \RuntimeException('timeout_after_commit');return $result;
    }
    public function reserve(array $p,bool $timeout=false):array{
        return $this->operation('reserve',$p,function(array &$s)use($p){
            $sku=(string)$p['sku'];$qty=(int)$p['qty'];$expires=(int)$p['expires_at'];if($qty<1||$expires<=time())throw new \DomainException('reservation_invalid');
            $held=0;foreach($s['reservations'] as $r)if($r['sku']===$sku&&$r['status']==='held'&&$r['expires_at']>time())$held+=$r['qty'];
            if(!isset($s['stock'][$sku])||$s['stock'][$sku]['on_hand']-$held<$qty)throw new \DomainException('out_of_stock');
            $id=wp_generate_uuid4();$r=['reservation_id'=>$id,'sku'=>$sku,'qty'=>$qty,'expires_at'=>$expires,'status'=>'held','order_uuid'=>(string)$p['order_uuid']];$s['reservations'][$id]=$r;return $r;
        },$timeout);
    }
    public function lookup(string $type,string $key):?array{if(get_option($this->option().'_offline'))throw new \RuntimeException('provider_offline');return $this->state()['results'][$type.':'.$key]['response']??null;}
    public function availability(string $sku):array{
        $s=$this->state();$stock=$s['stock'][$sku]??throw new \DomainException('sku_missing');$reserved=0;foreach($s['reservations'] as $r)if($r['sku']===$sku&&$r['status']==='held'&&$r['expires_at']>time())$reserved+=$r['qty'];return $stock+['reserved'=>$reserved,'available'=>$stock['on_hand']-$reserved,'status'=>self::STATUS];
    }
    public function release(array $p):array{return $this->operation('release',$p,function(array &$s)use($p){$id=(string)$p['reservation_id'];$r=$s['reservations'][$id]??throw new \DomainException('reservation_missing');if($r['status']==='committed')throw new \DomainException('refund_required');$s['reservations'][$id]['status']='released';return ['status'=>'released'];});}
    public function sale(array $p,bool $timeout=false):array{
        return $this->operation('sale',$p,function(array &$s)use($p){
            $id=(string)$p['reservation_id'];$r=$s['reservations'][$id]??throw new \DomainException('reservation_missing');
            if($r['status']!=='held'||$r['expires_at']<=time()||$r['order_uuid']!==$p['order_uuid'])throw new \DomainException('reservation_not_committable');
            if(empty($p['warehouse_id']))throw new \DomainException('logical_warehouse_required');
            if(($p['currency']??'')!=='IRR'||($p['scale']??null)!==2||!is_string($p['amount_minor']??null))throw new \DomainException('money_contract_invalid');Decimal::integer($p['amount_minor']);
            $invoice=['invoice_id'=>wp_generate_uuid4(),'order_uuid'=>$p['order_uuid'],'warehouse_id'=>$p['warehouse_id'],'amount_minor'=>$p['amount_minor'],'reservation_id'=>$id,'sku'=>$r['sku'],'qty'=>$r['qty']];
            $s['stock'][$r['sku']]['on_hand']-=$r['qty'];$s['stock'][$r['sku']]['version']++;$s['reservations'][$id]['status']='committed';$s['invoices'][$invoice['invoice_id']]=$invoice;return $invoice+['status'=>'issued','stock_moves'=>1,'accounting_documents'=>1];
        },$timeout);
    }
    public function refund(array $p):array{
        return $this->operation('refund',$p,function(array &$s)use($p){$invoice=$s['invoices'][$p['invoice_id']]??throw new \DomainException('invoice_missing');if(isset($s['refunds'][$p['invoice_id']]))throw new \DomainException('already_refunded');
            $s['stock'][$invoice['sku']]['on_hand']+=$invoice['qty'];$result=['credit_id'=>wp_generate_uuid4(),'invoice_id'=>$p['invoice_id'],'amount_minor'=>$invoice['amount_minor'],'stock_moves'=>1];$s['refunds'][$p['invoice_id']]=$result;return $result;});
    }
    public static function amounts(string $irr,string $grams):array{return ['amount_minor'=>Decimal::mul(Decimal::money($irr),'100'),'currency'=>'IRR','scale'=>2,'qty_scaled'=>Decimal::quantity($grams),'unit_code'=>'GRAM','qty_scale'=>4];}
    public static function authority(string $contract,array $remote,array $local):array{
        if(($remote['currency']??'')!=='IRR'||($remote['scale']??null)!==2)throw new \DomainException('money_contract_invalid');
        if(!is_string($remote['amount_minor']??null))throw new \DomainException('money_contract_invalid');Decimal::integer($remote['amount_minor']);
        $amount=Decimal::div((string)$remote['amount_minor'],'100');
        if($contract==='AGAH_AUTHORITATIVE_FINAL_PRICE')return ['amount_irr'=>$amount,'authority'=>$contract,'source_version'=>$remote['version']??'1'];
        if($contract==='AGAH_BASE_COMPONENTS'){
            if(($remote['policy_version']??'')!==($local['policy_version']??''))throw new \DomainException('policy_mismatch');
            if($amount!==$local['amount_irr'])throw new \DomainException('price_conflict');return $local+['authority'=>$contract];
        }throw new \DomainException('price_contract_required');
    }
}
