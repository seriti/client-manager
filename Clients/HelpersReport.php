<?php 
namespace App\Clients;

use Exception;
use Seriti\Tools\Calc;
use Seriti\Tools\Csv;
use Seriti\Tools\Doc;
use Seriti\Tools\Html;
use Seriti\Tools\Pdf;
use Seriti\Tools\Date;
use Seriti\Tools\Upload;
use Seriti\Tools\SITE_TITLE;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;
use Seriti\Tools\UPLOAD_TEMP;
use Seriti\Tools\STORAGE;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\TABLE_USER;
use Seriti\Tools\AJAX_ROUTE;

use Psr\Container\ContainerInterface;

//static functions for client module
class HelpersReport {
    
    //time sheets report
    public static function timeSheets($db,ContainerInterface $container,$client_id,$from_date,$to_date,$options = [],&$error) {
        $error = '';
        $html = '';
        $invoice_total = 0.00;
        $payment_total = 0.00;
        $balance = 0.00;

        
        if(!isset($options['format'])) $options['format'] = 'HTML';
        if(!isset($options['footer'])) $options['footer'] = true;
        
        //if($options['format'] === 'CSV') $error .= 'CSV format not supported';

        if($error !== '') return false;  

        if($options['format'] === 'PDF' and $options['footer']) {
            $footer = $container->system->getDefault('INVOICE_FOOTER','');    
        }
        
    
        $cache = $container['cache'];
        $cache->setCache('CSV');
        
        if($client_id === 'ALL') {
            $sql_client = '';
            
            $client = array();
            $client['name'] = 'ALL Clients';
        } else {  
            $sql_client = 'W.`client_id` = "'.$db->escapeSql($client_id).'" AND ';
            
            $sql = 'SELECT * FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'" ';
            $client = $db->readSqlRecord($sql); 
            if($client == 0) $error .= 'Invalid client ID['.$client_id.'] ';
        }  
        
        if($error == '') {
            $sql_where = 'WHERE '.$sql_client.' '.
                         'W.`time_start` >= "'.$db->escapeSql($from_date).'" AND '.
                         'W.`time_start` <= "'.$db->escapeSql($to_date).'" ';

            $sql = 'SELECT W.`time_id` AS `Time_ID`,U.`name` AS `Coder`,C.`name` AS `Client`,T.`name` AS `Activity`,W.`time_start` AS `Date`,'.
                          'W.`time_minutes` AS `Minutes`,W.`comment` AS `Comment` '.
                   'FROM `'.TABLE_PREFIX.'time` AS W JOIN `'.TABLE_PREFIX.'time_type` AS T ON(W.`type_id` = T.`type_id`) '.
                         'JOIN `'.TABLE_USER.'` AS U ON(W.`user_id` = U.`user_id`) '. 
                         'LEFT JOIN `'.TABLE_PREFIX.'client` AS C ON(W.`client_id` = C.`client_id`) '.
                         $sql_where.'ORDER BY W.`time_start` ';
            $result['time'] = $db->readSql($sql);

            $sql = 'SELECT UA.`name` AS `name`,UE.`value` AS `hour_rate` , '.
                     'ROUND(SUM(W.`time_minutes`)/60,2) AS `hours`, '.
                     'ROUND(UE.`value` * SUM(W.`time_minutes`)/60,2) AS `total_fee`  '.
                     'FROM `'.TABLE_PREFIX.'time` AS W JOIN `'.TABLE_USER.'` AS UA ON(W.`user_id` = UA.`user_id`) '.
                     'JOIN `'.TABLE_PREFIX.'user_extend` AS UE ON(W.`user_id` = UE.`user_id` AND UE.`parameter` = "HOURLY_RATE") '.
                     'JOIN `'.TABLE_PREFIX.'time_type` AS T ON (W.`type_id` = T.`type_id` AND T.`status` = "OK") '.
                     $sql_where.'GROUP BY `name`, `hour_rate`';
            $result['time_sum'] = $db->readSql($sql);

            if($result['time'] === 0) {
                $error .= 'NO time sheets found for client['.$client['name'].']';
            }
            
            
        }  
        
        if($error === '') {
            $html_options = [];
            $pdf_options = [];

            $doc_name_base = $client['name'] .'_timesheets_from_'.$from_date.'_to_'.$to_date.'_on_'.date('Y-m-d');

            if($options['format'] === 'HTML') {
                $csv_id = 'TIME_SHEETS_'.$client_id;
                $excel_str = '<img src="/images/excel_icon.gif" title="Export CSV/Excel file" alt="Export CSV/Excel file" border="0">Excel/CSV';
                $csv_link = '&nbsp;<a href="'.AJAX_ROUTE.'?mode=csv&id='.urlencode($csv_id).'">'.$excel_str.'</a>';
                
                
                $html .= '<div><h1>All time-sheets for '.$client['name'].' from '.Date::formatDate($from_date).' '.
                             'to '.Date::formatDate($to_date).'</h1>';
                
                $html .= '<div>'.Html::mysqlDumpHtml($result['time_sum']).'</div>';
                
                $row_h = 0;
                $col_w = [];
                $col_type = ['','','','DATE','','',''];
                $html_options['csv_output'] = 'YES';
                $output = [];
                $align = 'L';
                
                //get html and csv data
                $html_div = Html::mysqlDrawTable($result['time'],$row_h,$col_w,$col_type,$align,$html_options,$output);
                //save csv data to cache table
                //couls use user specific cash or modify csv_id to be user specific
                //Csv::csvUpdate($db,$csv_id,$output['csv_data']);
                $cache->store($csv_id,$output['csv_data']);
                //display table and link to csv cache data
                $html .= '<div>'.$csv_link.$html_div.'</div>';
            } 

            if($options['format'] === 'PDF') {
                $row_h = 7;
                $align = 'L';

                $pdf_dir = BASE_UPLOAD.UPLOAD_TEMP;
                $pdf_name = $doc_name_base.'.pdf';
                $pdf_name = str_replace(' ','_',$pdf_name);
                                
                $pdf = new Pdf('Portrait','mm','A4');
                $pdf->AliasNbPages();
                    
                $pdf->setupLayout(['db'=>$db]);
                //change setup system setting if there is one
                //$pdf->h1_title=array(33,33,33,'B',10,'',8,20,'L','NO',33,33,33,'B',12,20,180);
                //$pdf->bg_image=$logo;
                
                //$pdf->footer_text=$footer_text;

                //NB footer must be set before this
                $pdf->AddPage();

                $row_h = 5;
                                         
                $pdf->SetY(40);
                $pdf->changeFont('H1');
                $pdf->Cell(50,$row_h,'Time sheets:',0,0,'R',0);
                $pdf->Cell(50,$row_h,$client['name'],0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'For period :',0,0,'R',0);
                $pdf->Cell(50,$row_h,'From '.Date::formatDate($from_date).' to '.Date::formatDate($to_date),0,0,'L',0);
                $pdf->Ln($row_h*2);

                $pdf->changeFont('TEXT');
                $col_w = [30,30,30,30];
                $col_type = ['','','','CASH2'];
                $pdf->mysqlDrawTable($result['time_sum'],$row_h,$col_w,$col_type,$align,$pdf_options);
                $pdf->Ln($row_h*2);
                $col_w = [20,30,30,30,20,20,40];
                $col_type = ['','','','DATE','','',''];
                $pdf->mysqlDrawTable($result['time'],$row_h,$col_w,$col_type,$align,$pdf_options);
                $pdf->Ln($row_h*2);

                //add invoice footer text, if any.
                if($footer !== '') {
                    $pdf->MultiCell(0,$row_h,$footer,0,'L',0);      
                    $pdf->Ln($row_h);
                }
        
                
                //finally create pdf file
                //$file_path=$pdf_dir.$pdf_name;
                //$pdf->Output($file_path,'F');   
                //or send to browser
                $pdf->Output($pdf_name,'D'); 
                exit; 
            } 

            if($options['format'] === 'CSV') {  
                $csv_data = '';

                $doc_name = str_replace(' ','_',$doc_name_base).'.csv';

                $csv_row = 'SUMMARY';
                Csv::csvAddRow($csv_row,$csv_data);
                $csv_data .= Csv::mysqlDumpCsv($result['time_sum']);

                $csv_row = 'TIMESHEETS';
                Csv::csvAddRow($csv_row,$csv_data);
                $csv_data .= Csv::mysqlDumpCsv($result['time']);
                
                Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
                exit();
            }   
        }

        return $html; 
    } 
    
    //invoices and payments report
    public static function statement($db,ContainerInterface $container,$client_id,$from_date,$to_date,$options = [],&$error) {
        $error = '';
        $html = '';
        $invoice_total = 0.00;
        $payment_total = 0.00;
        $credit_total = 0.00;
        $balance = 0.00;

        if(!isset($options['format'])) $options['format'] = 'HTML';
        if(!isset($options['footer'])) $options['footer'] = true;

        if($options['format'] === 'PDF' and $options['footer']) {
            $footer = $container->system->getDefault('INVOICE_FOOTER','');    
        }

        $cache = $container['cache'];
        $cache->setCache('CSV');

        $sql = 'SELECT `name`,`email`,`status`,`date_statement_start` '.
               'FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'" ';
        $client = $db->readSqlRecord($sql); 
        if($client == 0) $error .= 'Invalid client ID['.$client_id.'] ';
        
        if($error == '') {
            $sql = 'SELECT `invoice_id`,`invoice_no`,`date`,`total` FROM `'.TABLE_PREFIX.'invoice` '.
                   'WHERE `client_id` = "'.$db->escapeSql($client_id).'" AND '.
                         '`date` >= "'.$db->escapeSql($from_date).'" AND '.
                         '`date` <= "'.$db->escapeSql($to_date).'" '.
                   'ORDER BY `date` ';
            $invoices = $db->readSqlArray($sql); 
            if($invoices == 0) $error .= 'NO invoices found over period from['.$from_date.'] to ['.$to_date.'] ';
            
            $sql = 'SELECT `payment_id`,`date`,`description`,`amount` FROM `'.TABLE_PREFIX.'payment` '.
                   'WHERE `client_id` = "'.$db->escapeSql($client_id).'" AND '.
                         '`date` >= "'.$db->escapeSql($from_date).'" AND '.
                         '`date` <= "'.$db->escapeSql($to_date).'" '.
                   'ORDER BY `date` ';
            $payments = $db->readSqlArray($sql); 

            $sql = 'SELECT `credit_id`,`credit_no`,`date`,`total` FROM `'.TABLE_PREFIX.'credit` '.
                   'WHERE `client_id` = "'.$db->escapeSql($client_id).'" AND '.
                         '`date` >= "'.$db->escapeSql($from_date).'" AND '.
                         '`date` <= "'.$db->escapeSql($to_date).'" '.
                   'ORDER BY `date` ';
            $credits = $db->readSqlArray($sql); 
        } 

        if($error !== '') return false;   
        
        if($error == '') {
            $doc_name_base = $client['name'] .'_statement_simple_from_'.$from_date.'_to_'.$to_date.'_on_'.date('Y-m-d');

            foreach($invoices as $invoice_id => $invoice) {
                $invoice_total += $invoice['total'];
            } 

            foreach($payments as $payment_id => $payment) {
                $payment_total += $payment['amount'];
            } 
            
            foreach($credits as $credit_id => $credit) {
                $credit_total += $credit['total'];
            } 

            $balance = $invoice_total - $payment_total - $credit_total;   
        }    

        if($error == '') {
            if($options['format'] === 'HTML') {
                $csv_id = 'STATEMENT_'.$client_id;
                $excel_str = '<img src="/images/excel_icon.gif" title="Export CSV/Excel file" alt="Export CSV/Excel file" border="0">Excel/CSV';
                $csv_link = '&nbsp;<a href="'.AJAX_ROUTE.'?mode=csv&id='.urlencode($csv_id).'">'.$excel_str.'</a>';
                $csv_data = '';
                $csv_row = 'INVOICE_NO,Date,Amount';
                Csv::csvAddRow($csv_row,$csv_data);
                
                $html .= '<div>'.$csv_link.
                             '<h1>Statement for '.$client['name'].' from '.Date::formatDate($from_date).' '.
                                        'to '.Date::formatDate($to_date).'</h1>';
                $html .= '<h1>Last recon date: '.$client['date_statement_start'].'</h1>';            
                $html .= '<table class="table  table-striped table-bordered table-hover table-condensed">'.
                         '<tr><th>INVOICE NO</th><th>Date</th><th>Amount</th></tr> ';
                foreach($invoices as $invoice_id => $invoice) {
                    $html .= '<tr><td>'.$invoice['invoice_no'].'</td><td>'.Date::formatDate($invoice['date']).'</td>'.
                                 '<td>'.number_format($invoice['total'],2).'</td></tr>';
                                                         
                    $csv_row = $invoice['invoice_no'].','.$invoice['date'].','.$invoice['total'];
                    Csv::csvAddRow($csv_row,$csv_data);
                }  
                $html .= '</table>'; 
                            
                if($payments != 0) {
                    $csv_row = 'PAYMENT_ID,Date,Amount';
                    Csv::csvAddRow($csv_row,$csv_data);
                    
                    $html .= '<table class="table  table-striped table-bordered table-hover table-condensed">'.
                             '<tr><th>PAYMENT ID</th><th>Date</th><th>Description</th><th>Amount</th></tr> ';
                    foreach($payments as $payment_id => $payment) {
                        $html .= '<tr><td>PAYMENT-'.$payment_id.'</td><td>'.Date::formatDate($payment['date']).'</td>'.
                                     '<td>'.$payment['description'].'</td>'.
                                     '<td>'.number_format($payment['amount'],2).'</td></tr>';  
                                             
                        $csv_row = 'PAYMENT-'.$payment_id.','.$payment['date'].','.$payment['amount'];
                        Csv::csvAddRow($csv_row,$csv_data);
                        
                    }
                    $html .= '</table>';     
                }

                if($credits != 0) {
                    $csv_row = 'CREDIT_NO,Date,Amount';
                    Csv::csvAddRow($csv_row,$csv_data);
                    
                    $html .= '<table class="table  table-striped table-bordered table-hover table-condensed">'.
                             '<tr><th>CREDIT NO</th><th>Date</th><th>Amount</th></tr> ';
                    foreach($credits as $credit_id => $credit) {
                        $html .= '<tr><td>'.$credit['credit_no'].'</td><td>'.Date::formatDate($credit['date']).'</td>'.
                                     '<td>'.number_format($credit['total'],2).'</td></tr>';
                                                             
                        $csv_row = $credit['credit_no'].','.$credit['date'].','.$credit['total'];
                        Csv::csvAddRow($csv_row,$csv_data);
                    }  
                    $html .= '</table>';

                }
                
                $html .= '<h2>Invoices total: '.number_format($invoice_total,2).'</h2>'; 
                $html .= '<h2>Payments total: '.number_format($payment_total,2).'</h2>';  
                $html .= '<h2>Credits total: '.number_format($credit_total,2).'</h2>';  
                                
                if($balance > 0) $str = 'OWED BY YOU'; else $str = 'DUE TO YOU';
                $html .= '<h2>BALANCE '.$str.': '.number_format($balance,2).'</h2>';  
                
                //save csv data to cache
                //Csv::csvUpdate($db,$csv_id,$csv_data);
                $cache->store($csv_id,$output['csv_data']);
            } 

            if($options['format'] === 'PDF') {
                $pdf_options = [];

                $pdf_dir = BASE_UPLOAD.UPLOAD_TEMP;
                $pdf_name = $doc_name_base.'.pdf';
                $pdf_name = str_replace(' ','_',$pdf_name);
                                
                $pdf = new Pdf('Portrait','mm','A4');
                $pdf->AliasNbPages();
                    
                $pdf->setupLayout(['db'=>$db]);
                //$pdf->footer_text=$footer_text;

                //NB footer must be set before this
                $pdf->AddPage();

                $row_h = 6;
                                         
                $pdf->SetY(40);
                $pdf->changeFont('H1');
                $pdf->Cell(50,$row_h,'Statement:',0,0,'R',0);
                $pdf->Cell(50,$row_h,$client['name'],0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'For period :',0,0,'R',0);
                $pdf->Cell(50,$row_h,'From '.Date::formatDate($from_date).' to '.Date::formatDate($to_date),0,0,'L',0);
                $pdf->Ln($row_h*2);

                $pdf->changeFont('H2');
                $pdf->Cell(50,$row_h,'Invoices total:',0,0,'R',0);
                $pdf->Cell(50,$row_h,number_format($invoice_total,2),0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'Payments total:',0,0,'R',0);
                $pdf->Cell(50,$row_h,number_format($payment_total,2),0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'Credits total:',0,0,'R',0);
                $pdf->Cell(50,$row_h,number_format($credit_total,2),0,0,'L',0);
                $pdf->Ln($row_h*2);

                $pdf->Cell(50,$row_h,'BALANCE:',0,0,'R',0);
                if($balance > 0) $str = 'OWED BY YOU'; else $str = 'DUE TO YOU';
                $pdf->Cell(50,$row_h,number_format($balance,2).' '.$str,0,0,'L',0);
                $pdf->Ln($row_h*2);
                                
                $pdf->changeFont('TEXT');
                $pdf->SetX(30);
                $pdf->Cell(70,$row_h,'Invoices issued over period:',0,0,'L',0);
                $pdf->Ln($row_h);
                $col_width = [30,30,30];
                $col_type = ['','DATE','CASH2'];
                $pdf->arrayDumpPdf($invoices,$row_h,$col_width,$col_type,'30',$pdf_options);
                $pdf->Ln($row_h);

                if($payments != 0) {
                    $pdf->SetX(30);
                    $pdf->Cell(70,$row_h,'Payments received over period:',0,0,'L',0);
                    $pdf->Ln($row_h);
                    $col_width = [30,60,30];
                    $col_type = ['DATE','','CASH2'];
                    $pdf->arrayDumpPdf($payments,$row_h,$col_width,$col_type,'30',$pdf_options);
                    $pdf->Ln($row_h);
                } 

                if($credits != 0) {
                    $pdf->SetX(30);
                    $pdf->Cell(70,$row_h,'Credit Notes issued over period:',0,0,'L',0);
                    $pdf->Ln($row_h);
                    $col_width = [30,30,30];
                    $col_type = ['DATE','CASH2',''];
                    $pdf->arrayDumpPdf($credits,$row_h,$col_width,$col_type,'30',$pdf_options);
                    $pdf->Ln($row_h);
                }  

                //add invoice footer text, if any.
                if($footer !== '') {
                    $pdf->MultiCell(0,$row_h,$footer,0,'L',0);      
                    $pdf->Ln($row_h);
                }  
                
                //finally create pdf file
                //$file_path=$pdf_dir.$pdf_name;
                //$pdf->Output($file_path,'F');   
                //or send to browser
                $pdf->Output($pdf_name,'D'); 
                exit; 
            } 

            if($options['format'] === 'CSV') {  
                $csv_data = '';

                $doc_name = str_replace(' ','_',$doc_name_base).'.csv';

                $csv_row = 'ALL INVOICES';
                Csv::csvAddRow($csv_row,$csv_data);
                $csv_data .= Csv::sqlArrayDumpCsv('Invoice',$invoices,['show_key'=>false]);

                if($payments != 0) {
                    $csv_row = 'ALL PAYMENTS';
                    Csv::csvAddRow($csv_row,$csv_data);
                    $csv_data .= Csv::sqlArrayDumpCsv('Payment',$payments,['show_key'=>false]);
                } 

                if($credits != 0) {
                    $csv_row = 'ALL CREDIT NOTES';
                    Csv::csvAddRow($csv_row,$csv_data);
                    $csv_data .= Csv::sqlArrayDumpCsv('Credit',$credits,['show_key'=>false]);
                }    
                
                Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
                exit();
            }    
        }   
         
        
        return $html; 
    }
    
    //invoices and payments report
    public static function statementLedger($db,ContainerInterface $container,$client_id,$from_date,$to_date,$options = [],&$error) {
        $error = '';
        $html = '';
        $invoice_total = 0.00;
        $payment_total = 0.00;
        $credit_total = 0.00;
        $balance = 0.00;
        //allows matching first col values in readSqlArray
        $first_col_key = false;

        if(!isset($options['format'])) $options['format'] = 'HTML';
        if(!isset($options['footer'])) $options['footer'] = true;

        if($options['format'] === 'PDF' and $options['footer']) {
            $footer = $container->system->getDefault('INVOICE_FOOTER','');    
        }

        if($error !== '') return false;  

        $cache = $container['cache'];
        $cache->setCache('CSV');
        
        $sql = 'SELECT `name`,`email`,`status`,`date_statement_start` '.
               'FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'" ';
        $client = $db->readSqlRecord($sql); 
        if($client == 0) $error.='Invalid client ID['.$client_id.'] ';
        
        if($error == '') {
            $sql = '(SELECT "INVOICE" AS `type`,`invoice_no` AS `text`,`date`,`total` AS `amount` FROM `'.TABLE_PREFIX.'invoice` '.
                    'WHERE `client_id` = "'.$db->escapeSql($client_id).'" AND '.
                          '`date` >= "'.$db->escapeSql($from_date).'" AND '.
                          '`date` <= "'.$db->escapeSql($to_date).'" )';
            
            $sql .= ' UNION ALL ';
            
            $sql .= '(SELECT "PAYMENT" AS `type`,`description` AS `text`,`date`,`amount` FROM `'.TABLE_PREFIX.'payment` '.
                     'WHERE `client_id` = "'.$db->escapeSql($client_id).'" AND '.
                           '`date` >= "'.$db->escapeSql($from_date).'" AND '.
                           '`date` <= "'.$db->escapeSql($to_date).'" )';

            $sql .= ' UNION ALL ';

            $sql .= '(SELECT "CREDIT" AS `type`,`credit_no` AS `text`,`date`,`total` AS `amount` FROM `'.TABLE_PREFIX.'credit` '.
                    'WHERE `client_id` = "'.$db->escapeSql($client_id).'" AND '.
                          '`date` >= "'.$db->escapeSql($from_date).'" AND '.
                          '`date` <= "'.$db->escapeSql($to_date).'" )';
                                 
            $sql .= ' ORDER BY `date` ';
            $entries = $db->readSqlArray($sql,$first_col_key); 
        }  

        if($error == '') {

            $balance = 0.00;
            foreach($entries as $key => $entry) {
                if($entry['type'] === 'INVOICE') {
                    $amount = $entry['amount'];
                    $invoice_total += abs($amount);
                    
                } elseif($entry['type'] === 'CREDIT') {
                    $amount = -1 * $entry['amount'];
                    $credit_total += abs($amount);
                    
                } elseif($entry['type'] === 'PAYMENT') {    
                    $amount = -1 * $entry['amount'];
                    $payment_total += abs($amount);
                }
                $balance += $amount;
                
                $entries[$key]['amount'] = $amount;
                $entries[$key]['balance'] = $balance;
            }  
        }    
        
        if($error == '') {
            $doc_name_base = $client['name'] .'_statement_ledger_from_'.$from_date.'_to_'.$to_date.'_on_'.date('Y-m-d');

            //old code written before above loops for pdf array dump
            if($options['format'] === 'HTML') {
                $csv_id = 'STATEMENT_LEDGER_'.$client_id;
                $excel_str = '<img src="/images/excel_icon.gif" title="Export CSV/Excel file" alt="Export CSV/Excel file" border="0">Excel/CSV';
                $csv_link = '&nbsp;<a href="'.AJAX_ROUTE.'?mode=csv&id='.urlencode($csv_id).'">'.$excel_str.'</a>';
                $csv_data = '';
                $balance = 0.00;
                $csv_row = 'Description,Date,Amount,Balance';
                Csv::csvAddRow($csv_row,$csv_data);
                
                //opening balance
                $csv_row = 'Opening balance,'.$from_date.','.$balance.','.$balance;
                
                $html .= '<div>'.$csv_link.
                         '<h1>Statement ledger for '.$client['name'].' from '.Date::formatDate($from_date).' '.
                             'to '.Date::formatDate($to_date).'</h1>';
                $html .= '<h1>Last recon date: '.$client['date_statement_start'].'</h1>';            
                $html .= '<table class="table  table-striped table-bordered table-hover table-condensed">'.
                         '<tr><th>Description</th><th>Date</th><th>Amount</th><th>Balance</th></tr> ';
                $html .= '<tr><td>Opening balance</td><td>'.Date::formatDate($from_date).'</td>'.
                             '<td>'.number_format($balance,2).'</td><td>'.number_format($balance,2).'</td></tr>';       
                foreach($entries as $entry) {
                    if($entry['type'] === 'INVOICE') {
                        $amount = abs($entry['amount']);
                        $text = 'INVOICE: '.$entry['text'];
                    } elseif($entry['type'] === 'CREDIT') {
                        $amount = -1 * abs($entry['amount']);
                        $text = 'CREDIT: '.$entry['text'];
                    } elseif($entry['type'] === 'PAYMENT') {    
                        $amount = -1 * abs($entry['amount']);
                        $text = 'PAYMENT: '.$entry['text'];
                    }
                    $balance += $amount;
                            
                    $html .= '<tr><td>'.$text.'</td><td>'.Date::formatDate($entry['date']).'</td>'.
                                 '<td>'.number_format($amount,2).'</td><td>'.number_format($balance,2).'</td></tr>';
                                                         
                    $csv_row = $text.','.$entry['date'].','.$amount.','.$balance;
                    Csv::csvAddRow($csv_row,$csv_data);
                }  
                $html .= '</table>'; 
                
                if($balance > 0) $str = 'OWED BY YOU'; else $str = 'DUE TO YOU';
                $html .= '<h2>BALANCE '.$str.': '.number_format($balance,2).'</h2>';  
                
                //save csv data to cache
                //Csv::csvUpdate($db,$csv_id,$csv_data);
                $cache->store($csv_id,$output['csv_data']);
            } 

            if($options['format'] === 'PDF') {  
                $pdf_options = [];

                $pdf_dir = BASE_UPLOAD.UPLOAD_TEMP;
                $pdf_name = $doc_name_base.'.pdf';
                $pdf_name = str_replace(' ','_',$pdf_name);
                                
                $pdf = new Pdf('Portrait','mm','A4');
                $pdf->AliasNbPages();
                    
                $pdf->setupLayout(['db'=>$db]);
                //$pdf->footer_text=$footer_text;

                //NB footer must be set before this
                $pdf->AddPage();

                $row_h = 6;
                                         
                $pdf->SetY(40);
                $pdf->changeFont('H1');
                $pdf->Cell(50,$row_h,'Statement:',0,0,'R',0);
                $pdf->Cell(50,$row_h,$client['name'],0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'For period :',0,0,'R',0);
                $pdf->Cell(50,$row_h,'From '.Date::formatDate($from_date).' to '.Date::formatDate($to_date),0,0,'L',0);
                $pdf->Ln($row_h*2);

                $pdf->changeFont('H2');
                $pdf->Cell(50,$row_h,'Invoices total:',0,0,'R',0);
                $pdf->Cell(50,$row_h,number_format($invoice_total,2),0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'Payments total:',0,0,'R',0);
                $pdf->Cell(50,$row_h,number_format($payment_total,2),0,0,'L',0);
                $pdf->Ln($row_h);
                $pdf->Cell(50,$row_h,'Credits total:',0,0,'R',0);
                $pdf->Cell(50,$row_h,number_format($credit_total,2),0,0,'L',0);
                $pdf->Ln($row_h*2);

                $pdf->Cell(50,$row_h,'BALANCE:',0,0,'R',0);
                if($balance > 0) $str = 'OWED BY YOU'; else $str = 'DUE TO YOU';
                $pdf->Cell(50,$row_h,number_format($balance,2).' '.$str,0,0,'L',0);
                $pdf->Ln($row_h*2);
                                
                $pdf->changeFont('TEXT');
                $pdf->SetX(30);
                $pdf->Cell(70,$row_h,'Cumulative Ledger over period:',0,0,'L',0);
                $pdf->Ln($row_h);
                $col_width = [30,50,30,30,30];
                $col_type = ['','','DATE','CASH2','CASH2'];
                $pdf->arrayDumpPdf($entries,$row_h,$col_width,$col_type,'10',$pdf_options);
                $pdf->Ln($row_h);

                //add invoice footer text, if any.
                if($footer !== '') {
                    $pdf->MultiCell(0,$row_h,$footer,0,'L',0);      
                    $pdf->Ln($row_h);
                }

                //finally create pdf file
                //$file_path=$pdf_dir.$pdf_name;
                //$pdf->Output($file_path,'F');   
                //or send to browser
                $pdf->Output($pdf_name,'D'); 
                exit; 
            } 


            if($options['format'] === 'CSV') {  
                $csv_data = '';

                $doc_name = str_replace(' ','_',$doc_name_base).'.csv';

                $csv_data = Csv::sqlArrayDumpCsv('Entry',$entries,['show_key'=>false]);
                Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD');
                exit();
            }    
        } 
        
        return $html; 
    } 
    
    public static function sendTaskReport($db,ContainerInterface $container,$task_id,$mail_to,&$error) {
        $error = '';
        $error_tmp = '';
        
        $system = $container['system'];
        $mail = $container['mail'];

        $options = array();
        $options['format'] = 'HTML';
        $options['file_links'] = true;
        
        $sql = 'SELECT `task_id`,`client_id`,`name`,`description`,`date_create`,`status` '.
               'FROM `'.TABLE_PREFIX.'task` '.
               'WHERE `task_id` = "'.$db->escapeSql($task_id).'" ';
        $task = $db->readSqlRecord($sql);
        
        $content = self::taskReport($db,$container,$task_id,$options,$error_tmp);
        if($error_tmp != '') {
            $error .= 'Could NOT generate report: '.$error_tmp;
        } else {
            $mail_footer = $system->getDefault('EMAIL_FOOTER','');
            $subject = SITE_NAME.' Task: '.$task['name'];
            //NB: $content includes document links, not attached on purpose as may be large!
            $body = $content."\r\n\r\n";
            $body .= $mail_footer."\r\n";
                                    
            $param = [];
            if($options['format'] === 'HTML') {
                $body = nl2br($body);
                $param['format'] = 'html';
            }  
            $mail->sendEmail('',$mail_to,$subject,$body,$error_tmp,$param);
            if($error_tmp !== '') { 
                $error .= 'Error sending task report to email['. $mail_to.']:'.$error_tmp; 
            }       
        }    
    }  
    
    public static function taskReport($db,ContainerInterface $container,$task_id,$options = [],&$error) {
        $error = '';
        $error_tmp = '';
        $output = '';
        
        if(!isset($options['task_type'])) $options['task_type'] = 'Task';
        if(!isset($options['file_links'])) $options['file_links'] = true;
        if(!isset($options['format'])) $options['format'] = 'TEXT'; //or HTML

        if($options['format'] === 'PDF') $error .= 'PDF format not supported';

        if($error !== '') return $output;  
        
        $sql = 'SELECT `task_id`,`client_id`,`name`,`description`,`date_create`,`status` '.
               'FROM `'.TABLE_PREFIX.'task` '.
               'WHERE `task_id` = "'.$db->escapeSql($task_id).'" ';
        $task = $db->readSqlRecord($sql);
        if($options['format'] === 'TEXT') {
            $output .= $options['task_type'].': '.strtoupper($task['name']).' created on '.Date::formatDate($task['date_create'])."\r\n";
            $output .= $task['description']."\r\n\r\n";
        }
        if($options['format'] === 'HTML') {
            $output .= '<h1>'.$options['task_type'].': '.$task['name'].'</h1>'.
                             '<p><i>created on '.Date::formatDate($task['date_create']).'</i></p>';
            $output .= '<p>'.nl2br($task['description']).'</p><br/>';
        }   
        
        $sql = 'SELECT `diary_id`,`date`,`subject`,`notes` FROM `'.TABLE_PREFIX.'task_diary` '.
               'WHERE `task_id` = "'.$db->escapeSql($task_id).'" '.
               'ORDER BY `date`, `diary_id` ';
        $diary = $db->readSqlArray($sql);
        if($diary != 0) {
            foreach($diary as $id => $entry) {
                if($options['format'] === 'TEXT') {
                    $output .= '#'.Date::formatDate($entry['date']).' - '.strtoupper($entry['subject']).':'."\r\n".
                               $entry['notes']."\r\n\r\n"; 
                }  
                if($options['format'] === 'HTML') {
                    $output .= '<h2>'.Date::formatDate($entry['date']).' - '.strtoupper($entry['subject']).':</h2>'.
                               '<p>'.nl2br($entry['notes']).'</p><br/>';  
                }     
            }
        }  
        
        
        if($options['file_links']) {
            if(STORAGE === 'amazon') {
                $s3 = $container['s3']; 
                //NB: 7 days is maximum for presigned urls
                $s3_expire = '7 days';
                $expire_date = date('Y-m-d',time()+(30*24*60*60));
            }
             
            $sql = 'SELECT `file_id`,`file_name`,`file_name_orig`,`file_date`,`key_words` '.
                   'FROM `'.TABLE_PREFIX.'files` '.
                   'WHERE `location_id` = "TSK'.$task_id.'" ORDER BY `file_id` ';
            $files = $db->readSqlArray($sql,$db);
            if($files != 0) {
                if($options['format'] === 'TEXT') {
                    $output .= "\r\n\r\n";
                    $output .= 'The following documents are available for download(links expire on '.$expire_date.'):'."\r\n"; 
                }
                if($options['format'] === 'HTML') {
                    $output .= '<h2>The following documents are available for download(links expire on '.$expire_date.'):</h2><ul>'; 
                }    
                
                foreach($files as $file_id => $file) {
                    $param = [];
                    $param['file_name_change'] = $file['file_name_orig'];
                    $param['expire'] = $s3_expire;

                    $url = $s3->getS3Url($file['file_name'],$param);

                    if($options['format'] === 'TEXT') {
                        $output .= $file['file_name_orig'].":\r\n".$url."\r\n"; 
                    }
                    if($options['format'] === 'HTML') {
                        $output .= '<li><a href="'.$url.'">'.$file['file_name_orig'].'</a></li>'; 
                    }    
                }   
                
                if($options['format'] === 'HTML') $output .= '</ul>';
            }
        }
        
        return $output;  
    }    
}