<?php
declare(strict_types=1);
namespace GoldCommerce;

final class Pricing {
    public static function calculate(array $p,array $rate,array $policy,int $now): array {
        $round=$policy['rounding']??'half_up';
        if(!in_array($round,['half_up','up','down'],true))throw new \DomainException('rounding_required');
        $type=$p['price_type']??'REQUEST_QUOTE';
        if($type==='REQUEST_QUOTE')throw new \DomainException('quote_required');
        $components=['metal'=>'0','making'=>'0','profit'=>'0','stone'=>'0','services'=>'0'];
        $net=Decimal::quantity($p['net_weight']??'0');
        $gross=Decimal::quantity($p['gross_weight']??($p['net_weight']??'0'));
        $stoneWeight=Decimal::quantity($p['stone_weight']??'0');
        if(Decimal::cmp(Decimal::add($net,$stoneWeight),$gross)>0)throw new \DomainException('weight_inconsistent');
        if(in_array($type,['FIXED','MANUAL_FINAL'],true)) {
            if($type==='MANUAL_FINAL')$components['final_price']=Decimal::money($p['fixed_price']??'0');
            else $components['metal']=Decimal::money($p['fixed_price']??'0');
        }else{
            if(($rate['currency']??'')!=='IRR'||($rate['valid_until']??0)<=$now||($rate['effective_at']??0)>$now||empty($rate['version']))throw new \DomainException('rate_stale_or_invalid');
            $r=Decimal::parse($rate['value']??'');
            if($type==='MARKET_QUOTE'){
                if(($rate['unit']??'')!=='IRR_PER_ITEM')throw new \DomainException('rate_unit_mismatch');
                $components['metal']=Decimal::div($r,'10000',$round);
            }elseif($type==='WEIGHT_BASED'){
                if(($rate['unit']??'')!=='IRR_PER_GRAM')throw new \DomainException('rate_unit_mismatch');
                $purity=Decimal::parse($p['purity']??'750');$reference=Decimal::parse($rate['reference_purity']??'750');
                if(Decimal::cmp($purity,'10000000')>0||$purity==='0'||$reference==='0'||Decimal::cmp($reference,'10000000')>0)throw new \DomainException('purity_invalid');
                $components['metal']=Decimal::div(Decimal::mul(Decimal::mul($r,$net),$purity),Decimal::mul('100000000',$reference),$round);
            }else throw new \DomainException('price_type_invalid');
        }
        // Direct market/final prices include all pricing components unless explicitly itemized.
        if(!in_array($type,['MARKET_QUOTE','MANUAL_FINAL'],true)){
            $components['stone']=Decimal::money($p['stone_value']??'0');
            $components['services']=Decimal::money($p['services']??'0');
            foreach(['making','profit'] as $name){
                $mode=$p[$name.'_mode']??'fixed';$value=Decimal::parse($p[$name.'_value']??'0');
                $bases=$p[$name.'_base']??($name==='making'?['metal']:['metal','making']);
                $base=self::sumSelected($components,$bases);
                $components[$name]=match($mode){
                    'fixed'=>Decimal::div($value,'10000',$round),
                    'per_gram'=>Decimal::div(Decimal::mul($value,$net),'100000000',$round),
                    'percent'=>Decimal::div(Decimal::mul($base,$value),'1000000',$round),
                    default=>throw new \DomainException('fee_mode_invalid')};
            }
        }
        $discounts=$p['discounts']??[];
        foreach($discounts as $key=>$discount){
            if(!array_key_exists($key,$components))throw new \DomainException('discount_base_invalid');
            $components[$key]=Decimal::sub($components[$key],Decimal::money($discount));
        }
        $taxBase=$type==='MANUAL_FINAL'?'0':self::sumSelected($components,$policy['tax_components']??[]);
        $percent=Decimal::parse($policy['tax_percent']??'0');
        if(Decimal::cmp($percent,'1000000')>0)throw new \DomainException('tax_invalid');
        if(empty($policy['version']))throw new \DomainException('policy_version_required');
        $tax=Decimal::div(Decimal::mul($taxBase,$percent),'1000000',$round);
        $total=Decimal::add(self::sumSelected($components,array_keys($components)),$tax);
        if($total==='0')throw new \DomainException('zero_price');Decimal::integer($total);
        return ['schema_version'=>'1','currency'=>'IRR','amount_irr'=>$total,'components'=>$components,'discounts'=>$discounts,'tax_base'=>$taxBase,'tax'=>$tax,'tax_treatment'=>$type==='MANUAL_FINAL'?'INCLUDED_UNITEMIZED':'ITEMIZED','rounding'=>$round,'policy_version'=>$policy['version'],'product'=>$p,'rate'=>$rate,'created_at_utc'=>gmdate('c',$now)];
    }
    private static function sumSelected(array $components,array $keys): string {
        $sum='0';foreach(array_unique($keys) as $key){if(!array_key_exists($key,$components))throw new \DomainException('component_invalid');$sum=Decimal::add($sum,$components[$key]);}return $sum;
    }
}
