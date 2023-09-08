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
use Seriti\Tools\AJAX_ROUTE;

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
            
        $sql = 'SELECT * FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'"';
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
        if($client['description'] !== '') {
            $pdf->Cell(30);
            $pdf->MultiCell(0,$row_h,$client['description'],0,'L',0);  
            $pdf->Ln($row_h);    
        }
        

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
        if(count($data['items']) != 0) {
            $pdf->changeFont('TEXT');
            $col_width = array(20,100,20,20);
            $col_type = array('DBL2','','DBL2','DBL2');
            $pdf->arrayDrawTable($data['items'],$row_h,$col_width,$col_type,'L');
        }
        
        //totals
        $pdf->changeFont('H3');
        $pdf->Cell(140,$row_h,'SUBTOTAL :',0,0,'R',0);
        $pdf->Cell(20,$row_h,number_format($data['subtotal'],2),0,0,'R',0);
        $pdf->Ln($row_h);
        $pdf->Cell(140,$row_h,'VAT :',0,0,'R',0);
        $pdf->Cell(20,$row_h,$data['vat'],0,0,'R',0);
        $pdf->Ln($row_h);
        $pdf->Cell(140,$row_h,'TOTAL :',0,0,'R',0);
        $pdf->Cell(20,$row_h,number_format($data['total'],2),0,0,'R',0);
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

    public static function createCreditPdf($db,$system,$client_id,$data = array(),&$doc_name,&$error_str) {
        
        $error_str = '';
        $pdf_dir = BASE_UPLOAD.UPLOAD_DOCS;
        //for custom settings like signature
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        
        $credit_no = 'CREDIT-'.$data['no'];
        $pdf_name = $credit_no.'.pdf';
        $doc_name = $pdf_name;
            
        $sql = 'SELECT * FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'"';
        $client = $db->readSqlRecord($sql,$db); 
        
        //get setup options
        $footer = $system->getDefault('CREDIT_FOOTER','');
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
        $pdf->Cell(30,$row_h,'CREDIT NOTE :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$data['no'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(30,$row_h,'To :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$data['for'],0,0,'L',0);
        $pdf->Ln($row_h);
        $pdf->Cell(30,$row_h,'Date issued :',0,0,'R',0);
        $pdf->Cell(30,$row_h,$data['date'],0,0,'L',0); //date('j-F-Y')
        $pdf->Ln($row_h);
        $pdf->Ln($row_h);
        
                        
        //credit items table
        if(count($data['items']) != 0) {
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
                
        //custom footer text, if any.
        $pdf->MultiCell(0,$row_h,$footer,0,'L',0);      
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
            $sql = 'UPDATE `'.TABLE_PREFIX.'client` SET `invoice_no` = `invoice_no` + 1 '.
                   'WHERE `client_id` = "'.$db->escapeSql($client_id).'" ';              
            $db->executeSql($sql,$error_tmp); 
            if($error_tmp !== '') $error_str .= 'Error updating client invoice no counter!';     
        }
        
        if($error_str == '') return $invoice_id; else return false;
    } 

    public static function saveCredit($db,$system,$s3,$client_id,$data=array(),$doc_name,&$error_str) {
        $error_tmp = '';
        $error_str = '';
     
        $pdf_dir = BASE_UPLOAD.UPLOAD_DOCS; 
        
        $credit = array();
        $credit['credit_no'] = $data['no'];
        $credit['client_id'] = $client_id;
        $credit['amount'] = $data['subtotal'];
        $credit['vat'] = $data['vat'];
        $credit['total'] = $data['total'];
        $credit['date'] = $data['date'];//date('Y-m-d');
        $credit['comment'] = $data['comment'];
        $credit['status'] = "OK";
        $credit['doc_name'] = $doc_name;
        
        //save credit
        $credit_id = $db->insertRecord(TABLE_PREFIX.'credit',$credit,$error_tmp);
        if($error_tmp != '') $error_str .= 'Could not create credit note record!';
        
        //save credit item data
        if($error_str == '') {
            $items = $data['items'];
            $item_no = count($items[0])-1; //first line contains headers
            for($i = 1; $i <= $item_no; $i++) {
                $credit_data = array();
                $credit_data['credit_id'] = $credit_id;
                $credit_data['quantity'] = $items[0][$i];
                $credit_data['item'] = $items[1][$i];
                $credit_data['price'] = $items[2][$i];
                $credit_data['total'] = $items[3][$i];
                 
                $db->insertRecord(TABLE_PREFIX.'credit_data',$credit_data,$error_tmp);
                if($error_tmp != '') $error_str .= 'Could not add credit item['.$credit_data['item'].']!<br/>';              
            } 
        }
        
        //create file table record and rename credit doc
        if($error_str == '') { 
            $location_id = 'CRD'.$credit_id;
            $file_id = Calc::getFileId($db);
            $file_name = $file_id.'.pdf';
            $pdf_path_old = $pdf_dir.$doc_name;
            $pdf_path_new = $pdf_dir.$file_name;
            //rename doc to new guaranteed non-clashing name
            if(!rename($pdf_path_old,$pdf_path_new)) {
                $error_str .= 'Could not rename credit pdf!<br/>'; 
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
                if($error_tmp != '') $error_str .= 'ERROR creating credit file record: '.$error_tmp.'<br/>';
            }   
        }   
        
        if($error_str == '') {      
            $sql = 'UPDATE `'.TABLE_PREFIX.'client` SET `credit_no` = `credit_no` + 1 '.
                   'WHERE `client_id` = "'.$db->escapeSql($client_id).'" ';              
            $db->executeSql($sql,$error_tmp); 
            if($error_tmp !== '') $error_str .= 'Error updating client credit no counter!';     
        }
        
        if($error_str == '') return $credit_id; else return false;
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
        $sql = 'SELECT * FROM `'.TABLE_PREFIX.'invoice` WHERE `invoice_id` = "'.$db->escapeSql($invoice_id).'"';
        $invoice = $db->readSqlRecord($sql); 
                    
        //NEED TO ASSOCIATE DOC WITH CLIENT AT SOME POINT, HERE OR IN CREATE INVOICE 
        $sql = 'SELECT * FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'"';
        $client = $db->readSqlRecord($sql); 
        
        //get all files related to invoice
        $attach = array();
        $attach_file = array();

        //NB: only using for download, all files associated with invoice will be attached
        $docs = new Upload($db,$container,TABLE_PREFIX.'files');
        $docs->setup(['location'=>'INV','interface'=>'download']);

        $sql = 'SELECT `file_id`,`file_name_orig` FROM `'.TABLE_PREFIX.'files` '.
               'WHERE `location_id` ="INV'.$invoice_id.'" ORDER BY `file_id` ';
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

    //email credit note to client
    public static function sendCredit($db,ContainerInterface $container,$client_id,$credit_id,$mail_to,&$error_str) {
        $error_str = '';
        $error_tmp = '';
        $attach_msg = '';
                
        $system = $container['system'];
        $mail = $container['mail'];

        $sql = 'SELECT * FROM `'.TABLE_PREFIX.'credit` WHERE `credit_id` = "'.$db->escapeSql($credit_id).'"';
        $credit = $db->readSqlRecord($sql); 
                    
        $sql = 'SELECT * FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$db->escapeSql($client_id).'"';
        $client = $db->readSqlRecord($sql); 
        
        //get all files related to credit
        $attach = array();
        $attach_file = array();

        //NB: only using for download, all files associated with credit will be attached
        $docs = new Upload($db,$container,TABLE_PREFIX.'files');
        $docs->setup(['location'=>'CRD','interface'=>'download']);

        $sql = 'SELECT `file_id`,`file_name_orig` FROM `'.TABLE_PREFIX.'files` '.
               'WHERE `location_id` ="CRD'.$credit_id.'" ORDER BY `file_id` ';
        $credit_files = $db->readSqlList($sql);
        if($credit_files != 0) {
            foreach($credit_files as $file_id => $file_name) {
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
            $subject = SITE_NAME.' Credit Note '.$credit['credit_no'];
            $body = 'Attn. '.$client['name']."\r\n\r\n".
                    'Please see attached credit['.$credit['doc_name'].'] and any supporting documents.'."\r\n\r\n";
                        
            if($attach_msg != '') $body .= 'All documents attached to this email: '."\r\n".$attach_msg."\r\n";
                        
            $mail_footer = $system->getDefault('EMAIL_FOOTER','');
            $body .= $mail_footer."\r\n";
                        
            $param = ['attach'=>$attach];
            $mail->sendEmail('',$mail_to,$subject,$body,$error_tmp,$param);
            if($error_tmp != '') { 
                $error_str .= 'Error sending credit note email with attachments to email['. $mail_to.']:'.$error_tmp; 
            }       
        }  
            
        if($error_str == '') return true; else return false;  
    } 

    //add payments from GL transactions provided that they dont exist already
    public static function addGlPayment($db,$client_id,$transact_id,&$message_str,&$error_str) {
        $error_str = '';
        $message_str = '';
        $payment_exists = false;
        
        $sql = 'SELECT `transact_id`,`type_id`,`debit_credit`,`amount`,`date`,`description` '.
               'FROM `'.TABLE_PREFIX_GL.'transact` WHERE `transact_id` = "'.$db->escapeSql($transact_id).'" ';
        $transact = $db->readSqlRecord($sql);      
        if($transact == 0) {
            $error_str .= 'Could not locate Ledger transaction ID['.$transact_id.']';         
        } else {
            if($transact['type_id'] !== 'CASH') $error_str .= 'Ledger transaction is not of type CASH!';
            if($transact['debit_credit'] !== 'C') $error_str .= 'Ledger transaction cannot be a Debit!';
        }
        
        //check if payment not captured before
        if($error_str === '') {
            $sql = 'SELECT P.`amount`,P.`date`,P.`description`,P.`client_id`,C.`name` AS `client` '.
                   'FROM `'.TABLE_PREFIX.'payment` AS P LEFT JOIN `'.TABLE_PREFIX.'client` AS C ON(P.`client_id` = C.`client_id`) '.
                   'WHERE P.`transact_id` = "'.$db->escapeSql($transact_id).'" '; 
            $payment = $db->readSqlRecord($sql); 
            if($payment != 0) {
                $message_str .= 'Payment['.$payment['amount'].' on '.Date::formatDate($payment['date']).'] already EXISTS '.
                                            'for client['.$payment['client'].'] and Transaction ID['.$transact_id.'] '; 
                $payment_exists = true;
            }  
        }  
        
        if($error_str === '' and $payment_exists === false) {
            $data = array();
            $data['client_id'] = $client_id; 
            $data['transact_id'] = $transact_id; 
            $data['date'] = $transact['date'];
            $data['amount'] = $transact['amount']; 
            $data['description'] = $transact['description']; 
            
            $db->insertRecord(TABLE_PREFIX.'payment',$data,$error_str);
            if($error_str == '') {
                $message_str .= 'Payment['.$transact['amount'].' on '.Date::formatDate($transact['date']).'] succesfully INSERTED '.
                                'for client ID['.$client_id.'].';
            }                
        }    
    } 
    
        
}