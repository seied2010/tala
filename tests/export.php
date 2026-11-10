<?php
require __DIR__.'/../source/gold-commerce-core/src/Reports.php';
foreach(['pdf','xlsx','docx'] as $format)GoldCommerce\Reports::generate([['سفارش','مبلغ'],['1','28073800'],['=HYPERLINK("https://example.test")','متن امن']],$format,__DIR__.'/../evidence/sample-report-'.$argv[1].'.'.$format);
