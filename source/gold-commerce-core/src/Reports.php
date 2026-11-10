<?php
declare(strict_types=1);
namespace GoldCommerce;
final class Reports {
    public static function rows(string $from,string $to,string $type='sales'):array{
        if(!current_user_can('gc_view_reports'))throw new \DomainException('forbidden');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$to)||$from>$to||strtotime($to)-strtotime($from)>366*86400)throw new \DomainException('report_range_invalid');
        if(in_array($type,['inventory','sync','quotes','payments'],true))return self::operationalRows($from,$to,$type);
        if(!in_array($type,['sales','daily','product','category','channel'],true))throw new \DomainException('report_type_invalid');
        $rows=[['سفارش','زمان پرداخت UTC','جمع (ریال)','مرجوع (ریال)','وضعیت','کانال','ارسال','همگام‌سازی']];$groups=[];
        $page=1;do{
            $result=wc_get_orders(['date_paid'=>strtotime($from.' 00:00:00 UTC').'...'.strtotime($to.' 23:59:59 UTC'),'limit'=>100,'page'=>$page++,'paginate'=>true,'orderby'=>'ID','order'=>'ASC']);
            if($result->total>10000)throw new \DomainException('report_range_too_large');
            foreach($result->orders as $o){$source=(array)$o->get_meta('_gc_attribution');$rows[]=[(string)$o->get_id(),$o->get_date_paid()?->date('c')??'',(string)$o->get_total(),(string)$o->get_total_refunded(),$o->get_status(),(string)($source['utm_source']??''),(string)$o->get_meta('_gc_fulfilment_status'),(string)$o->get_meta('_gc_agah_sync')];
                if(in_array($type,['daily','channel'],true)){$key=$type==='daily'?($o->get_date_paid()?->date('Y-m-d')??''):(string)($source['utm_source']??'بدون انتساب');$groups[$key]=Decimal::add($groups[$key]??'0',Decimal::money((string)$o->get_total()));}
                if(in_array($type,['product','category'],true))foreach($o->get_items() as $item){$keys=$type==='product'?[(string)$item->get_product_id()]:wp_get_post_terms($item->get_product_id(),'product_cat',['fields'=>'names']);if(is_wp_error($keys))$keys=[];foreach($keys as $key)$groups[$key]=Decimal::add($groups[$key]??'0',Decimal::money((string)$item->get_total()));}
            }
        }while($page<=$result->max_num_pages);
        if($type!=='sales'){$rows=[['گروه','فروش ناخالص (ریال)']];foreach($groups as $key=>$sum)$rows[]=[(string)$key,$sum];}
        return $rows;
    }
    private static function operationalRows(string $from,string $to,string $type):array{
        global $wpdb;
        if($type==='inventory'){$rows=[['شناسه','SKU','موجودی Woo','مرجع']];foreach(wc_get_products(['limit'=>-1,'status'=>'publish']) as $p)$rows[]=[(string)$p->get_id(),$p->get_sku(),(string)$p->get_stock_quantity(),Plugin::mode()];return $rows;}
        $table=Database::table(match($type){'sync'=>'integration_outbox','quotes'=>'quotes','payments'=>'payment_receipts'});
        $field=match($type){'sync'=>'next_try','quotes'=>'issued_at','payments'=>'received_at'};
        $columns=match($type){'sync'=>['event_uuid','event_type','aggregate_id','state','attempts','last_error_code'],'quotes'=>['uuid','order_id','version','status','amount_irr','expires_at'],'payments'=>['order_id','provider','transaction_id','amount_irr','status']};
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $field BETWEEN %d AND %d",strtotime($from.' UTC'),strtotime($to.' 23:59:59 UTC')));if($count>10000)throw new \DomainException('report_range_too_large');
        $data=$wpdb->get_results($wpdb->prepare('SELECT '.implode(',',$columns)." FROM $table WHERE $field BETWEEN %d AND %d ORDER BY id DESC",strtotime($from.' UTC'),strtotime($to.' 23:59:59 UTC')),ARRAY_A);
        $rows=[$columns];foreach($data as $row)$rows[]=array_values($row);return $rows;
    }
    private static function xml(string $s):string{return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');}
    public static function generate(array $rows,string $format,string $path):void{
        if($format==='pdf'){
            $autoload=dirname(__DIR__).'/vendor/tcpdf/tcpdf.php';if(!is_file($autoload))throw new \RuntimeException('pdf_dependency_missing');require_once $autoload;
            $pdf=new \TCPDF('L','mm','A4',true,'UTF-8',false);$pdf->SetCreator('Gold Commerce');$pdf->SetTitle('گزارش فروش');$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetFont('dejavusans','',9);$pdf->setRTL(true);$pdf->AddPage();
            $html='<h1>گزارش فروش طلا</h1><table border="1" cellpadding="5">';foreach($rows as $row){$html.='<tr>';foreach($row as $cell)$html.='<td>'.self::xml((string)$cell).'</td>';$html.='</tr>';}$pdf->writeHTML($html.'</table>');$pdf->Output($path,'F');return;
        }
        if(!in_array($format,['xlsx','docx'],true))throw new \DomainException('format_invalid');
        if(!class_exists('ZipArchive'))throw new \RuntimeException('zip_extension_required');$zip=new \ZipArchive();if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('export_failed');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.($format==='xlsx'?'xl/workbook.xml':'word/document.xml').'"/></Relationships>');
        if($format==='xlsx'){
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="فروش" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><sheetData>';
            foreach($rows as $i=>$row){$sheet.='<row r="'.($i+1).'">';foreach(array_values($row) as $j=>$cell){$column='';$n=$j+1;while($n>0){$n--;$column=chr(65+$n%26).$column;$n=intdiv($n,26);}$sheet.='<c r="'.$column.($i+1).'" t="inlineStr"><is><t xml:space="preserve">'.self::xml((string)$cell).'</t></is></c>';}$sheet.='</row>';}
            $zip->addFromString('xl/worksheets/sheet1.xml',$sheet.'</sheetData></worksheet>');
        }else{
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
            $columns=max(1,...array_map('count',$rows));
            $doc='<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tblPr><w:bidiVisual/></w:tblPr><w:tblGrid>'.str_repeat('<w:gridCol w:w="'.intdiv(14000,$columns).'"/>',$columns).'</w:tblGrid>';
            foreach($rows as $row){$doc.='<w:tr>';foreach(array_pad(array_values($row),$columns,'') as $cell)$doc.='<w:tc><w:p><w:pPr><w:bidi/></w:pPr><w:r><w:rPr><w:rtl/></w:rPr><w:t xml:space="preserve">'.self::xml((string)$cell).'</w:t></w:r></w:p></w:tc>';$doc.='</w:tr>';}
            $zip->addFromString('word/document.xml',$doc.'</w:tbl><w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/></w:sectPr></w:body></w:document>');
        }$zip->close();
    }
    public static function download():void{
        if(!current_user_can('gc_view_reports'))wp_die('forbidden',403);check_admin_referer('gc_export');
        try{$format=sanitize_key($_GET['format']??'');$rows=self::rows(sanitize_text_field($_GET['from']??''),sanitize_text_field($_GET['to']??''),sanitize_key($_GET['type']??'sales'));self::send($rows,$format,'gold-report');}catch(\Throwable $e){wp_die(esc_html($e->getMessage()),'گزارش',['response'=>400]);}
    }
    public static function invoice(\WC_Order $order):void{
        if(!Customer::owns($order))throw new \DomainException('forbidden');$raw=(string)$order->get_meta('_gc_invoice_snapshot');if(!$raw)throw new \DomainException('invoice_unavailable');$s=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        $rows=[['فاکتور',(string)($s['invoice_number']??$order->get_id())],['سفارش',(string)$order->get_id()],['فروشنده',(string)($s['seller']['name']??'')],['اطلاعات فروشنده',(string)($s['seller']['contact']??'')],['مشتری',(string)($s['customer']['name']??'')],['نشانی',(string)($s['customer']['city']??'').' '.($s['customer']['address']??'')],['جمع (ریال)',(string)$s['order_total']]];
        foreach($s['lines'] as $line){$p=$line['snapshot'];$rows[]=['محصول',(string)$line['product_id'],'تعداد',(string)$line['quantity']];if(($p['tax_treatment']??'')==='INCLUDED_UNITEMIZED'){$rows[]=['قیمت نهایی اعلام‌شده',$p['amount_irr'],'ریال'];$rows[]=['تفکیک مالیات/اجزا','توسط فروشنده ارائه نشده؛ صفر یا معاف فرض نشده است'];}else{foreach($p['components']??['مبلغ نهایی'=>$p['amount_irr']] as $name=>$v)$rows[]=[$name,(string)$v,'ریال'];if(isset($p['tax']))$rows[]=['مالیات',(string)$p['tax'],'ریال'];}$rows[]=['وزن خالص',(string)($p['product']['net_weight']??''),'گرم'];}
        $rows[]=['حمل',(string)$s['shipping'],'ریال'];$rows[]=['مالیات حمل',(string)$s['shipping_tax'],'ریال'];$rows[]=['وضعیت پرداخت',$order->is_paid()?'پرداخت‌شده':'نیازمند بررسی'];$rows[]=['رهگیری',(string)$order->get_meta('_gc_tracking')];self::send($rows,'pdf','invoice-'.$order->get_id());
    }
    private static function send(array $rows,string $format,string $name):never{
        $path=wp_tempnam('gc-report');if(!$path)throw new \RuntimeException('temp_unavailable');
        try{self::generate($rows,$format,$path);nocache_headers();header('Content-Type: '.match($format){'pdf'=>'application/pdf','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'});header('Content-Disposition: attachment; filename="'.$name.'.'.$format.'"');header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($path));readfile($path);}finally{unlink($path);}exit;
    }
}
