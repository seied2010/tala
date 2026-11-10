<?php
declare(strict_types=1);
require __DIR__.'/../source/gold-commerce-core/src/Decimal.php';
require __DIR__.'/../source/gold-commerce-core/src/Pricing.php';
use GoldCommerce\Decimal as D;
use GoldCommerce\Pricing as P;
$results=[];
function check(string $id,callable $fn):void{global $results;$start=hrtime(true);try{$fn();$results[]=['test'=>$id,'status'=>'PASS','ms'=>intdiv(hrtime(true)-$start,1000000)];}catch(Throwable $e){$results[]=['test'=>$id,'status'=>'FAIL','error'=>$e->getMessage()];}}
function same(mixed $expected,mixed $actual):void{if($expected!==$actual)throw new RuntimeException('Expected '.json_encode($expected).' got '.json_encode($actual));}
function rejects(callable $fn,string $message=''):void{try{$fn();}catch(Throwable $e){if($message!==''&&!str_contains($e->getMessage(),$message))throw $e;return;}throw new RuntimeException('Expected rejection');}
$now=1800000000;
$rate=['value'=>'10000000','unit'=>'IRR_PER_GRAM','currency'=>'IRR','reference_purity'=>'750','version'=>'test-v1','effective_at'=>$now-1,'valid_until'=>$now+60];
$policy=['version'=>'test-policy-1','tax_percent'=>'9','tax_components'=>['making','profit'],'rounding'=>'half_up'];
$product=['price_type'=>'WEIGHT_BASED','net_weight'=>'2.5','gross_weight'=>'2.5','purity'=>'750','making_mode'=>'fixed','making_value'=>'1000000','profit_mode'=>'percent','profit_value'=>'7'];
check('T01.exact_breakdown',function()use($product,$rate,$policy,$now){$s=P::calculate($product,$rate,$policy,$now);same('25000000',$s['components']['metal']);same('1820000',$s['components']['profit']);same('253800',$s['tax']);same('28073800',$s['amount_irr']);});
check('T02.per_gram_and_percent',function()use($product,$rate,$policy,$now){$p=$product;$p['making_mode']='per_gram';$p['making_value']='400000';same('1000000',P::calculate($p,$rate,$policy,$now)['components']['making']);$p['making_mode']='percent';$p['making_value']='10';same('2500000',P::calculate($p,$rate,$policy,$now)['components']['making']);});
check('T03.stone_net_weight',function()use($product,$rate,$policy,$now){$p=$product;$p['gross_weight']='3';$p['stone_weight']='0.5';$p['stone_value']='4000000';$s=P::calculate($p,$rate,$policy,$now);same('25000000',$s['components']['metal']);same('4000000',$s['components']['stone']);$p['net_weight']='3';rejects(fn()=>P::calculate($p,$rate,$policy,$now),'weight_inconsistent');});
check('T04.coin_direct',function()use($product,$rate,$policy,$now){$p=$product;$p['price_type']='MARKET_QUOTE';$r=$rate;$r['unit']='IRR_PER_ITEM';$r['value']='820000000';$s=P::calculate($p,$r,$policy,$now);same('820000000',$s['amount_irr']);same('0',$s['components']['making']);});
check('FINAL.manual_final_never_retaxed',function()use($product,$policy,$now){$p=$product;$p['price_type']='MANUAL_FINAL';$p['fixed_price']='25000000';$policy['tax_components']=['metal'];$s=P::calculate($p,[],$policy,$now);same('25000000',$s['amount_irr']);same('INCLUDED_UNITEMIZED',$s['tax_treatment']);same('0',$s['components']['metal']);same('25000000',$s['components']['final_price']);});
check('T05.units_and_purity',function()use($product,$rate,$policy,$now){$p=$product;$p['net_weight']='1';$p['purity']='999';same('13320000',P::calculate($p,$rate,$policy,$now)['components']['metal']);same('12345.6',D::display('123456','IRT'));$r=$rate;$r['unit']='TOMAN_PER_MESGHAL';rejects(fn()=>P::calculate($p,$r,$policy,$now),'rate_unit_mismatch');});
check('T06.tax_component_discount',function()use($product,$rate,$policy,$now){$p=$product;$p['stone_value']='1000000';$p['discounts']=['making'=>'100000','stone'=>'200000'];$s=P::calculate($p,$rate,$policy,$now);same('2720000',$s['tax_base']);same('244800',$s['tax']);same('28764800',$s['amount_irr']);});
check('T08.stale_and_future_rate',function()use($product,$rate,$policy,$now){$r=$rate;$r['valid_until']=$now;rejects(fn()=>P::calculate($product,$r,$policy,$now),'rate_stale');$r=$rate;$r['effective_at']=$now+1;rejects(fn()=>P::calculate($product,$r,$policy,$now),'rate_stale');});
check('T10.invalid_inputs',function()use($product,$rate,$policy,$now){foreach(['-1','INF','NaN','1e5','0.00001','1,000','<script>','1000000000000'] as $bad)rejects(fn()=>D::parse($bad));rejects(fn()=>D::parse(1.5));same('27500',D::quantity('۲.۷۵'));rejects(fn()=>D::integer('9000000000000001'),'overflow');});
check('DECIMAL.big_intermediates',function(){same('121932631112635269',D::mul('123456789','987654321'));same('333333333333333333333333333333',D::div('999999999999999999999999999999','3'));same('3',D::div('5','2'));same('2',D::div('5','2','down'));same('3',D::div('5','2','up'));same('999999999999999999',D::sub('1000000000000000000','1'));});
check('DECIMAL.deterministic_fuzz',function(){mt_srand(101);for($i=0;$i<500;$i++){$a=mt_rand(0,1000000);$b=mt_rand(1,1000000);$d=mt_rand(1,10000);same((string)($a*$b),D::mul((string)$a,(string)$b));same((string)intdiv($a*$b+intdiv($d,2),$d),D::div(D::mul((string)$a,(string)$b),(string)$d));}});
echo json_encode(['suite'=>'unit','php'=>PHP_VERSION,'at_utc'=>gmdate('c'),'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(count(array_filter($results,fn($r)=>$r['status']==='FAIL'))?1:0);
