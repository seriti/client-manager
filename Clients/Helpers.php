<?php 
namespace App\Clients;

use Exception;
use Seriti\Tools\Calc;
use Seriti\Tools\Csv;
use Seriti\Tools\Html;
use Seriti\Tools\Pdf;
use Seriti\Tools\Date;
use Seriti\Tools\Upload;
use Seriti\Tools\SITE_TITLE;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;
use Seriti\Tools\STORAGE;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\TABLE_USER;

use Psr\Container\ContainerInterface;

//static functions for client module
class Helpers {
    
    //creates invoice from invoice_wizard.php
    public static function createInvoicePdf($db,$system,$client_id,$data = array(),&$doc_name,&$error_str) {
        
        $error_str = '';
        $pdf_dir = BASE_UPLOAD.UPLOAD_DOCS;
        //for custom settings like signature
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        
        //get unique PDF filename for invoice
        //$sql='SELECT COUNT(*) FROM files WHERE location_id = "INV'.$client_id.'" ';
        //$count=$db->readSqlValue($sql,$db)+1;
        //$invoice_no='INV'.$client_id;
        //$pdf_name=$invoice_no.'-'.$count.'.pdf';
        $invoice_no = 'INV-'.$data['no'];
        $pdf_name = $invoice_no.'.pdf';
        $doc_name = $pdf_name;
            
        $sql = 'SELECT * FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$db->escapeSql($client_id).'"';
        $client = $db->readSqlRecord($sql,$db); 
        
        //get setup options
        $footer = $system->getDefault('INVOICE_FOOTER','');
        $signature = $system->getDefault('INVOICE_SIGN','');
        $signature_text = $system->getDefault('INVOICE_SIG_TXT','');
                    
        $pdf = new Pdf('Portrait','mm','A4');
        $pdf->AliasNbPages();
            
        $pdf->setupLayout(['db'=>$db]);
        
        //NB footer must be set before this
        $pdf->AddPage();

        $row_h = 5;
                                 
        $pdf->SetY(40);
        $pdf->changeFont('H1');
        $pdf->Cell(30,$row_h,'INVOICE :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$data['no'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(30,$row_h,'To :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$data['for'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(30,$row_h,'Date issued :',0,0,'R',0);
        $pdf->Cell(30,$row_h,date('j-F-Y'),0,0,'L',0);
        $pdf->Ln($row_h);
        if($data['show_period'] === 'YES') {
            $pdf->Cell(30,$row_h,'For period :',0,0,'R',0);
            $pdf->Cell(30,$row_h,'['.$data['date_from'].' to '.$data['date_to'].']',0,0,'L',0);
            $pdf->Ln($row_h);
        }  
        $pdf->Ln($row_h);
        
        //$pdf->MultiCell(40,$row_h,$data['for'],0,'L',0);
        //$y1=$pdf->GetY();
                
        //invoice items table
        if(count($data['items'] != 0)) {
            $pdf->changeFont('TEXT');
            $col_width = array(20,100,20,20);
            $col_type = array('DBL2','','DBL2','DBL2');
            $pdf->arrayDrawTable($data['items'],$row_h,$col_width,$col_type,'L');
        }
        
        //totals
        $pdf->changeFont('H3');
        $pdf->Cell(142,$row_h,'SUBTOTAL :',0,0,'R',0);
        $pdf->Cell(142,$row_h,number_format($data['subtotal'],2),0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(142,$row_h,'VAT :',0,0,'R',0);
        $pdf->Cell(142,$row_h,$data['vat'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(142,$row_h,'TOTAL :',0,0,'R',0);
        $pdf->Cell(142,$row_h,number_format($data['total'],2),0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Ln($row_h);
            
        if($data['comment'] != '') {
            $pdf->MultiCell(0,$row_h,$data['comment'],0,'L',0); 
            $pdf->Ln($row_h);
        }
                
        //initialise text block with custom footer text, if any.
        $txt = $footer;
                    
        if($data['time']) $txt .= "\r\n Please see full work done schedule on following pages: \r\n";      
                    
        $pdf->MultiCell(0,$row_h,$txt,0,'L',0);      
        $pdf->Ln($row_h);
                
        if($signature != '') {
            $image_path = $upload_dir.$signature;
            list($img_width,$img_height) = getimagesize($image_path);
            //height specified and width=0 so auto calculated     
            $y1 = $pdf->GetY();
            $pdf->Image($image_path,20,$y1,0,20);
            //$pdf->Image('images/sig_XXX.jpg',20,$y1,66,20);
            $pdf->SetY($y1+25);
        } else {
            $pdf->Ln($row_h*3); 
        }   
        
        if($signature_text != '') {    
            $pdf->Cell(0,$row_h,$signature_text,0,0,'L',0);
            $pdf->Ln($row_h);
        }  
        
        //add detailed time spent table if required
        if($data['time']) {
            $pdf->AddPage();
                    
            $pdf->changeFont('H1');
            $pdf->Cell(0,$row_h,'Full work done schedule over invoice period:',0,0,'L',0);
            $pdf->Ln($row_h);
            
            $pdf->changeFont('TEXT');
            if($data['time_sum']) {
                $sql = $data['sql_time_sum'];
                $result = $db->readSql($sql);
                
                $col_width = array(20,20,20,20);
                $col_type  = array('','DBL','DBL2','DBL2');
                $pdf->mysqlDrawTable($result,$row_h,$col_width,$col_type,'L');
                $pdf->Ln($row_h);
            }
                    
            if($data['time']) {
                $sql = $data['sql_time'];
                $result = $db->readSql($sql);
                 
                $col_width = array(20,15,25,25,15,100);
                $col_type  = array('','','','DATE','','');
                $pdf->mysqlDrawTable($result,$row_h,$col_width,$col_type,'L');
                $pdf->Ln($row_h);
            }  
        } 
        
        //finally create pdf file
        $file_path = $pdf_dir.$pdf_name;
        $pdf->Output($file_path,'F');   
                
        if($error_str == '') return true; else return false ;
    } 
    
    public static function saveInvoice($db,$system,$s3,$client_id,$data=array(),$doc_name,&$error_str) {
        $error_tmp = '';
        $error_str = '';
     
        $pdf_dir = BASE_UPLOAD.UPLOAD_DOCS; 
        
        $invoice = array();
        $invoice['invoice_no'] = $data['no'];
        $invoice['client_id'] = $client_id;
        $invoice['amount'] = $data['subtotal'];
        $invoice['vat'] = $data['vat'];
        $invoice['total'] = $data['total'];
        $invoice['date'] = date('Y-m-d');
        $invoice['comment'] = $data['comment'];
        $invoice['status'] = "OK";
        $invoice['doc_name'] = $doc_name;
        
        //save invoice
        $invoice_id = $db->insertRecord(TABLE_PREFIX.'invoice',$invoice,$error_tmp);
        if($error_tmp != '') $error_str .= 'Could not create invoice record!';
        
        //save invoice item data
        if($error_str == '') {
            $items = $data['items'];
            $item_no = count($items[0])-1; //first line contains headers
            for($i = 1; $i <= $item_no; $i++) {
                $invoice_data = array();
                $invoice_data['invoice_id'] = $invoice_id;
                $invoice_data['quantity'] = $items[0][$i];
                $invoice_data['item'] = $items[1][$i];
                $invoice_data['price'] = $items[2][$i];
                $invoice_data['total'] = $items[3][$i];
                 
                $db->insertRecord(TABLE_PREFIX.'invoice_data',$invoice_data,$error_tmp);
                if($error_tmp != '') $error_str .= 'Could not add invoice item['.$invoice_data['item'].']!<br/>';              
            } 
        }
        
        //create file table record and rename invoice doc
        if($error_str == '') { 
            $location_id = 'INV'.$invoice_id;
            $file_id = Calc::getFileId($db);
            $file_name = $file_id.'.pdf';
            $pdf_path_old = $pdf_dir.$doc_name;
            $pdf_path_new = $pdf_dir.$file_name;
            //rename doc to new guaranteed non-clashing name
            if(!rename($pdf_path_old,$pdf_path_new)) {
                $error_str .= 'Could not rename invoice pdf!<br/>'; 
            } 
        }
        
        //create file records and upload to amazon if required
        if($error_str == '') {    
            $file = array();
            $file['file_id'] = $file_id; 
            $file['file_name'] = $file_name;
            $file['file_name_orig'] = $doc_name;
            $file['file_ext'] = 'pdf';
            $file['file_date'] = date('Y-m-d');
            $file['location_id'] = $location_id;
            $file['encrypted'] = false;
            $file['file_size'] = filesize($pdf_path_new); 
            
            if(STORAGE === 'amazon') {
                $s3->putFile($file['file_name'],$pdf_path_new,$error_tmp); 
                if($error_tmp !== '') $error_str.='Could NOT upload files to Amazon S3 storage!<br/>';
            } 
            
            if($error_str == '') {
                $db->insertRecord(TABLE_PREFIX.'files',$file,$error_tmp);
                if($error_tmp != '') $error_str .= 'ERROR creating invoice file record: '.$error_tmp.'<br/>';
            }   
        }   
        
        if($error_str == '') {      
            $sql = 'UPDATE '.TABLE_PREFIX.'client SET invoice_no = invoice_no + 1 '.
                   'WHERE client_id = "'.$db->escapeSql($client_id).'" ';              
            $db->executeSql($sql,$error_tmp); 
            if($error_tmp !== '') $error_str .= 'Error updating client invoice no counter!';     
        }
        
        if($error_str == '') return $invoice_id; else return false;
    }  
    
    //email invoice to client
    public static function sendInvoice($db,ContainerInterface $container,$client_id,$invoice_id,$mail_to,&$error_str) {
        $error_str = '';
        $error_tmp = '';
        $attach_msg = '';
                
        $system = $container['system'];
        $mail = $container['mail'];

        //$pdf_dir=$seriti_config['path']['base'].$seriti_config['path']['files'];
        
        //get invoice details and invoice doc name
        $sql = 'SELECT * FROM '.TABLE_PREFIX.'invoice WHERE invoice_id = "'.$db->escapeSql($invoice_id).'"';
        $invoice = $db->readSqlRecord($sql); 
                    
        //NEED TO ASSOCIATE DOC WITH CLIENT AT SOME POINT, HERE OR IN CREATE INVOICE 
        $sql = 'SELECT * FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$db->escapeSql($client_id).'"';
        $client = $db->readSqlRecord($sql); 
        
        //get all files related to invoice
        $attach = array();
        $attach_file = array();

        //NB: only using for download, all files associated with invoice will be attached
        $docs = new Upload($db,$container,TABLE_PREFIX.'files');
        $docs->setup(['location'=>'INV','interface'=>'download']);

        $sql = 'SELECT file_id,file_name_orig FROM '.TABLE_PREFIX.'files '.
               'WHERE location_id ="INV'.$invoice_id.'" ORDER BY file_id ';
        $invoice_files = $db->readSqlList($sql);
        if($invoice_files != 0) {
            foreach($invoice_files as $file_id => $file_name) {
                $attach_file['name'] = $file_name;
                $attach_file['path'] = $docs->fileDownload($file_id,'FILE'); 
                if(substr($attach_file['path'],0,5) !== 'Error' and file_exists($attach_file['path'])) {
                    $attach[] = $attach_file;
                    $attach_msg .= $file_name."\r\n";
                } else {
                    $error_str .= 'Error fetching files for attachment to email!'; 
                }   
            }   
        }
            
        //configure and send email
        if($error_str == '') {
            $subject = SITE_NAME.' invoice '.$invoice['invoice_no'];
            $body = 'Attn. '.$client['name']."\r\n\r\n".
                    'Please see attached invoice['.$invoice['doc_name'].'] and any supporting documents.'."\r\n\r\n";
                        
            if($attach_msg != '') $body .= 'All documents attached to this email: '."\r\n".$attach_msg."\r\n";
                        
            $mail_footer = $system->getDefault('EMAIL_FOOTER','');
            $body .= $mail_footer."\r\n";
                        
            $param = ['attach'=>$attach];
            $mail->sendEmail('',$mail_to,$subject,$body,$error_tmp,$param);
            if($error_tmp != '') { 
                $error_str .= 'Error sending invoice email with attachments to email['. $mail_to.']:'.$error_tmp; 
            }       
        }  
            
        if($error_str == '') return true; else return false;  
    } 

    //add payments from GL transactions provided that they dont exist already
    public static function addGlPayment($db,$client_id,$transact_id,&$message_str,&$error_str) {
        $error_str='';
        $message_str='';
        $payment_exists=false;
        
        $sql='SELECT transact_id,type_id,debit_credit,amount,date,description '.
                 'FROM '.TABLE_PREFIX_GL.'transact WHERE transact_id = "'.$db->escapeSql($transact_id).'" ';
        $transact=$db->readSqlRecord($sql);      
        if($transact==0) {
            $error_str.='Could not locate Ledger transaction ID['.$transact_id.']';         
        } else {
            if($transact['type_id']!=='CASH') $error_str.='Ledger transaction is not of type CASH!';
            if($transact['debit_credit']!=='C') $error_str.='Ledger transaction cannot be a Debit!';
        }
        
        //check if payment not captured before
        if($error_str==='') {
            $sql='SELECT P.amount,P.date,P.description,P.client_id,C.name AS client '.
                     'FROM '.TABLE_PREFIX.'payment AS P LEFT JOIN '.TABLE_PREFIX.'client AS C ON(P.client_id = C.client_id) '.
                     'WHERE P.transact_id = "'.$db->escapeSql($transact_id).'" '; 
            $payment=$db->readSqlRecord($sql); 
            if($payment!=0) {
                $message_str.='Payment['.$payment['amount'].' on '.Date::formatDate($payment['date']).'] already EXISTS '.
                                            'for client['.$payment['client'].'] and Transaction ID['.$transact_id.'] '; 
                $payment_exists=true;
            }  
        }  
        
        if($error_str==='' and $payment_exists===false) {
            $data=array();
            $data['client_id']=$client_id; 
            $data['transact_id']=$transact_id; 
            $data['date']=$transact['date'];
            $data['amount']=$transact['amount']; 
            $data['description']=$transact['description']; 
            
            $db->insertRecord(TABLE_PREFIX.'payment',$data,$error_str);
            if($error_str=='') {
                $message_str.='Payment['.$transact['amount'].' on '.Date::formatDate($transact['date']).'] succesfully INSERTED '.
                                            'for client ID['.$client_id.'].';
            }                
        }    
        
        
    } 
    
    //time sheets report
    public static function timeSheets($db,$container,$client_id,$from_date,$to_date,&$error_str) {
        $error_str='';
        $html='';
        $invoice_total=0.00;
        $payment_total=0.00;
        $balance=0.00;

        $cache = $container['cache'];
        $cache->setCache('CSV');
        
        if($client_id==='ALL') {
            $sql_client='';
            
            $client=array();
            $client['name']='ALL Clients';
        } else {  
            $sql_client='W.client_id = "'.$db->escapeSql($client_id).'" AND ';
            
            $sql='SELECT * FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$db->escapeSql($client_id).'" ';
            $client=$db->readSqlRecord($sql); 
            if($client==0) $error_str.='Invalid client ID['.$client_id.'] ';
        }  
        
        if($error_str=='') {
            $sql_where='WHERE '.$sql_client.' '.
                                 'W.time_start >= "'.$db->escapeSql($from_date).'" AND '.
                                 'W.time_start <= "'.$db->escapeSql($to_date).'" ';

            $sql='SELECT W.time_id AS Time_ID,U.name AS Coder,C.name AS Client,T.name AS Activity,W.time_start AS Date,'.
                                    'W.time_minutes AS Minutes,W.comment AS Comment '.
                     'FROM '.TABLE_PREFIX.'time AS W JOIN '.TABLE_PREFIX.'time_type AS T ON (W.type_id = T.type_id) '.
                                'JOIN '.TABLE_USER.' AS U ON(W.user_id = U.user_id) '. 
                                'LEFT JOIN '.TABLE_PREFIX.'client AS C ON(W.client_id = C.client_id) '.
                        $sql_where.'ORDER BY W.time_start ';
            $result['time']=$db->readSql($sql);
            
            $sql='SELECT UA.name AS name,UE.value AS hour_rate , '.
                     'ROUND(SUM(W.time_minutes)/60,2) AS hours, '.
                     'ROUND(UE.value * SUM(W.time_minutes)/60,2) AS total_fee  '.
                     'FROM '.TABLE_PREFIX.'time AS W JOIN '.TABLE_USER.' AS UA ON(W.user_id = UA.user_id) '.
                     'JOIN '.TABLE_PREFIX.'user_extend AS UE ON(W.user_id = UE.user_id AND UE.parameter = "HOURLY_RATE") '.
                     'JOIN '.TABLE_PREFIX.'time_type AS T ON (W.type_id = T.type_id AND T.status = "OK") '.
                     $sql_where.'GROUP BY name, hour_rate';
            $result['time_sum']=$db->readSql($sql);
            
            if($result['time']===0) {
                $error_str.='NO time sheets found for client['.$client['name'].']';
            }
            
            
        }  
        
        if($error_str=='') {
            $csv_id='TIME_SHEETS_'.$client_id;
            $excel_str='<img src="/images/excel_icon.gif" title="Export CSV/Excel file" alt="Export CSV/Excel file" border="0">Excel/CSV';
            $csv_link='&nbsp;<a href="/admin/ajax?mode=csv&id='.urlencode($csv_id).'">'.$excel_str.'</a>';
            
            
            $html.='<div><h1>All time-sheets for '.$client['name'].' from '.Date::formatDate($from_date).' '.
                         'to '.Date::formatDate($to_date).'</h1>';
            
            $html.='<div>'.Html::mysqlDumpHtml($result['time_sum']).'</div>';
            
            $row_h=0;
            $col_w=array();
            $col_type=array('','','','DATE','','');
            $options['csv_output']='YES';
            $output=array();
            $align='L';
            
            //get html and csv data
            $html_div=Html::mysqlDrawTable($result['time'],$row_h,$col_w,$col_type,$align,$options,$output);
            //save csv data to cache table
            //couls use user specific cash or modify csv_id to be user specific
            //Csv::csvUpdate($db,$csv_id,$output['csv_data']);
            $cache->store($csv_id,$output['csv_data']);
            //display table and link to csv cache data
            $html.='<div>'.$csv_link.$html_div.'</div>';
        }
        return $html; 
    } 
    
    //invoices and payments report
    public static function statement($db,$container,$client_id,$from_date,$to_date,&$error_str) {
        $error_str = '';
        $html = '';
        $invoice_total = 0.00;
        $payment_total = 0.00;
        $balance = 0.00;
        
        $cache = $container['cache'];
        $cache->setCache('CSV');

        $sql = 'SELECT name,email,status,date_statement_start '.
               'FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$db->escapeSql($client_id).'" ';
        $client = $db->readSqlRecord($sql); 
        if($client == 0) $error_str .= 'Invalid client ID['.$client_id.'] ';
        
        if($error_str == '') {
            $sql = 'SELECT invoice_id,invoice_no,total,date FROM '.TABLE_PREFIX.'invoice '.
                   'WHERE client_id = "'.$db->escapeSql($client_id).'" AND '.
                         'date >= "'.$db->escapeSql($from_date).'" AND '.
                         'date <= "'.$db->escapeSql($to_date).'" '.
                   'ORDER BY date ';
            $invoices = $db->readSqlArray($sql); 
            if($invoices == 0) $error_str .= 'NO invoices found over period from['.$from_date.'] to ['.$to_date.'] ';
            
            $sql = 'SELECT payment_id,amount,date,description FROM '.TABLE_PREFIX.'payment '.
                   'WHERE client_id = "'.$db->escapeSql($client_id).'" AND '.
                         'date >= "'.$db->escapeSql($from_date).'" AND '.
                         'date <= "'.$db->escapeSql($to_date).'" '.
                   'ORDER BY date ';
            $payments = $db->readSqlArray($sql); 
        }  
        
        if($error_str == '') {
            
            $csv_id = 'STATEMENT_'.$client_id;
            $excel_str = '<img src="/images/excel_icon.gif" title="Export CSV/Excel file" alt="Export CSV/Excel file" border="0">Excel/CSV';
            $csv_link = '&nbsp;<a href="/admin/ajax?mode=csv&id='.urlencode($csv_id).'">'.$excel_str.'</a>';
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
                $invoice_total += $invoice['total'];
                $html .= '<tr><td>INV-'.$invoice['invoice_no'].'</td><td>'.Date::formatDate($invoice['date']).'</td>'.
                             '<td>'.number_format($invoice['total'],2).'</td></tr>';
                                                     
                $csv_row = 'INV-'.$invoice['invoice_no'].','.$invoice['date'].','.$invoice['total'];
                Csv::csvAddRow($csv_row,$csv_data);
            }  
            $html .= '</table>'; 
                        
            if($payments != 0) {
                $csv_row = 'PAYMENT_ID,Date,Amount';
                Csv::csvAddRow($csv_row,$csv_data);
                
                $html .= '<table class="table  table-striped table-bordered table-hover table-condensed">'.
                         '<tr><th>PAYMENT ID</th><th>Date</th><th>Description</th><th>Amount</th></tr> ';
                foreach($payments as $payment_id => $payment) {
                    $payment_total += $payment['amount'];
                    $html .= '<tr><td>PAYMENT-'.$payment_id.'</td><td>'.Date::formatDate($payment['date']).'</td>'.
                                 '<td>'.$payment['description'].'</td>'.
                                 '<td>'.number_format($payment['amount'],2).'</td></tr>';  
                                         
                    $csv_row = 'PAYMENT-'.$payment_id.','.$payment['date'].','.$payment['amount'];
                    Csv::csvAddRow($csv_row,$csv_data);
                    
                }
                $html .= '</table>';     
            }
            
            $html .= '<h2>Invoices total: '.number_format($invoice_total,2).'</h2>'; 
            $html .= '<h2>Payments total: '.number_format($payment_total,2).'</h2>';  
            
            $balance = $invoice_total-$payment_total;
            if($balance > 0) $str = 'OWED BY YOU'; else $str = 'DUE TO YOU';
            $html .= '<h2>BALANCE '.$str.': '.number_format($balance,2).'</h2>';  
            
            //save csv data to cache
            //Csv::csvUpdate($db,$csv_id,$csv_data);
            $cache->store($csv_id,$output['csv_data']);
        } 
        
        return $html; 
    }
    
    //invoices and payments report
    public static function statementLedger($db,$container,$client_id,$from_date,$to_date,&$error_str) {
        $error_str = '';
        $html = '';
        $invoice_total = 0.00;
        $payment_total = 0.00;
        $balance = 0.00;
        //allows matching first col values in readSqlArray
        $sql_options['first_col_key'] = false;

        $cache = $container['cache'];
        $cache->setCache('CSV');
        
        $sql = 'SELECT name,email,status,date_statement_start '.
               'FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$db->escapeSql($client_id).'" ';
        $client = $db->readSqlRecord($sql); 
        if($client == 0) $error_str.='Invalid client ID['.$client_id.'] ';
        
        if($error_str == '') {
            $sql = '(SELECT "INVOICE" AS type,invoice_no AS text,total AS amount,date FROM '.TABLE_PREFIX.'invoice '.
                    'WHERE client_id = "'.$db->escapeSql($client_id).'" AND '.
                          'date >= "'.$db->escapeSql($from_date).'" AND '.
                          'date <= "'.$db->escapeSql($to_date).'" )';
            
            $sql .= ' UNION ALL ';
            
            $sql .= '(SELECT "PAYMENT",description,amount,date FROM '.TABLE_PREFIX.'payment '.
                     'WHERE client_id = "'.$db->escapeSql($client_id).'" AND '.
                           'date >= "'.$db->escapeSql($from_date).'" AND '.
                           'date <= "'.$db->escapeSql($to_date).'" )';
                                 
            $sql .= ' ORDER BY date ';
            $entries = $db->readSqlArray($sql,$sql_options); 
        }  
        
        if($error_str == '') {
            
            $csv_id = 'STATEMENT_LEDGER_'.$client_id;
            $excel_str = '<img src="/images/excel_icon.gif" title="Export CSV/Excel file" alt="Export CSV/Excel file" border="0">Excel/CSV';
            $csv_link = '&nbsp;<a href="/admin/ajax?mode=csv&id='.urlencode($csv_id).'">'.$excel_str.'</a>';
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
                    $amount = $entry['amount'];
                    $text = 'INVOICE: '.$entry['text'];
                } else {
                    $amount = -1*$entry['amount'];
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
        
        return $html; 
    } 
    
    public static function sendTaskReport($db,$container,$task_id,$mail_to,&$error_str) {
        $error_str = '';
        $error_tmp = '';
        
        $system = $container['system'];
        $mail = $container['mail'];

        $options = array();
        $options['format'] = 'HTML';
        $options['file_links'] = true;
        
        $sql = 'SELECT task_id,client_id,name,description,date_create,status '.
               'FROM '.TABLE_PREFIX.'task '.
               'WHERE task_id = "'.$db->escapeSql($task_id).'" ';
        $task = $db->readSqlRecord($sql);
        
        $content = self::taskReport($db,$container,$task_id,$options,$error_tmp);
        if($error_tmp != '') {
            $error_str .= 'Could NOT generate report: '.$error_tmp;
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
                $error_str .= 'Error sending task report to email['. $mail_to.']:'.$error_tmp; 
            }       
        }    
    }  
    
    public static function taskReport($db,$container,$task_id,$options=array(),&$error_str) {
        $error_str = '';
        $error_tmp = '';
        $output = '';
        
        if(!isset($options['task_type'])) $options['task_type'] = 'Task';
        if(!isset($options['file_links'])) $options['file_links'] = true;
        if(!isset($options['format'])) $options['format'] = 'TEXT'; //or HTML
        
        $sql = 'SELECT task_id,client_id,name,description,date_create,status '.
               'FROM '.TABLE_PREFIX.'task '.
               'WHERE task_id = "'.$db->escapeSql($task_id).'" ';
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
        
        $sql = 'SELECT diary_id,date,subject,notes FROM '.TABLE_PREFIX.'task_diary '.
               'WHERE task_id = "'.$db->escapeSql($task_id).'" '.
               'ORDER BY date, diary_id ';
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
             
            $sql = 'SELECT file_id,file_name,file_name_orig,file_date,key_words '.
                   'FROM '.TABLE_PREFIX.'files '.
                   'WHERE location_id ="TSK'.$task_id.'" ORDER BY file_id ';
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
                    $param = ['file_name_change'=>$file['file_name_orig']];
                    $url = $s3->getS3Url($file['file_name'],$s3_expire,$param);

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
?>
