<?php
namespace App\Clients;

use Seriti\Tools\Wizard;
use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Doc;
use Seriti\Tools\Calc;
use Seriti\Tools\Secure;
use Seriti\Tools\Plupload;
use Seriti\Tools\STORAGE;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_TEMP;
use Seriti\Tools\UPLOAD_DOCS;

use App\Clients\Helpers;

class InvoiceWizard extends Wizard 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['cache'=>'invoice_wizard','bread_crumbs'=>true,'strict_var'=>false];
        parent::setup($param);

        $this->addVariable(array('id'=>'client_id','type'=>'INTEGER','title'=>'Client ID','max'=>1000000000));
        $this->addVariable(array('id'=>'from_date','type'=>'DATE','title'=>'From date'));
        $this->addVariable(array('id'=>'to_date','type'=>'DATE','title'=>'To date'));
        $this->addVariable(array('id'=>'invoice_no','type'=>'STRING','title'=>'Invoice No.'));
        $this->addVariable(array('id'=>'invoice_for','type'=>'STRING','title'=>'Invoice For'));
        $this->addVariable(array('id'=>'invoice_comment','type'=>'TEXT','title'=>'Invoice Comment','required'=>false));
        $this->addVariable(array('id'=>'email','type'=>'EMAIL','title'=>'Primary Email address'));
        $this->addVariable(array('id'=>'email_xtra','type'=>'EMAIL','title'=>'Secondary Email address','required'=>false));
        $this->addVariable(array('id'=>'show_period','type'=>'BOOLEAN','title'=>'Show invoice date period','new'=>'YES'));
        $this->addVariable(array('id'=>'show_time','type'=>'BOOLEAN','title'=>'Show time sheets','new'=>'YES'));
        $this->addVariable(array('id'=>'vat','type'=>'DECIMAL','title'=>'VAT amount','max'=>1000000,'new'=>0));

        //define pages and templates
        $this->addPage(1,'Select Client and period','clients/invoice_wizard_start.php');
        $this->addPage(2,'Manage invoice items','clients/invoice_wizard_items.php');
        $this->addPage(3,'Review final invoice','clients/invoice_wizard_review.php');
        $this->addPage(4,'Wizard complete','clients/invoice_wizard_final.php',array('final'=>true));    

    }

    public function processPage() 
    {
        $error = '';
        $error_tmp = '';

        //PROCESS select client and time period
        if($this->page_no == 1) {
            $client_id = $this->form['client_id'];
            $from = $this->form['from_date'];
            $to = $this->form['to_date'];
            
            $sql = 'SELECT client_id,name,email,email_alt,status,invoice_no,invoice_prefix,contact_name '.
                   'FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$this->db->escapeSql($client_id).'"';
            $this->data['client'] = $this->db->readSqlRecord($sql);
            
            //invoice items setup
            $item_no = 0;
            $item_total = 0.00;
            $items = array();
            $items[0][0] = 'Quantity';
            $items[1][0] = 'Description';
            $items[2][0] = 'Unit price';
            $items[3][0] = 'Total';
            
            $sql_where='WHERE W.client_id = "'.$this->db->escapeSql($client_id).'" AND '.
                       'W.time_start >= "'.$from.'" AND W.time_start <= "'.$to.'" ';
                       
            //all time sheets for period
            $sql = 'SELECT W.time_id AS Time_ID,U.name AS Coder,T.name AS Activity,W.time_start AS Date,'.
                          'W.time_minutes AS Minutes,W.comment AS Comment '.
                   'FROM '.TABLE_PREFIX.'time AS W JOIN '.TABLE_PREFIX.'time_type AS T ON (W.type_id = T.type_id) '.
                        'JOIN user_admin AS U ON(W.user_id = U.user_id) '. 
                    $sql_where.'ORDER BY W.time_start ';
            $this->data['sql_time']=$sql;
            
            //summary of programming time and hourly rates per coder
            //NB: hourly rate for each coder must be set or their time is ignored by JOIN
            $sql = 'SELECT UA.name AS name,UE.value AS hour_rate , '.
                   'ROUND(SUM(W.time_minutes)/60,2) AS hours, '.
                   'ROUND(UE.value * SUM(W.time_minutes)/60,2) AS total_fee  '.
                   'FROM '.TABLE_PREFIX.'time AS W JOIN user_admin AS UA ON(W.user_id = UA.user_id) '.
                   'JOIN '.TABLE_PREFIX.'user_extend AS UE ON(W.user_id = UE.user_id AND UE.parameter = "HOURLY_RATE") '.
                   'JOIN '.TABLE_PREFIX.'time_type AS T ON (W.type_id = T.type_id AND T.status = "OK") '.
                   $sql_where.' GROUP BY W.user_id ';
            $this->data['sql_time_sum']=$sql;     
            $result = $this->db->readSql($sql);
            if($result !== 0) {
              while($row = mysqli_fetch_array($result,MYSQLI_ASSOC)) {
                if($row['hours'] != 0) {
                  $item_no++;
                  
                  $items[0][$item_no] = $row['hours'];
                  $items[1][$item_no] = 'Programming hours by '.$row['name'].' @ R'.$row['hour_rate'].'/h';
                  $items[2][$item_no] = $row['hour_rate'];
                  $items[3][$item_no] = $row['total_fee'];
                  $item_total += round($row['total_fee'],0);
                }  
              }  
            }  
            
            //get any fixed repeat items
            $sql = 'SELECT fixed_id,name,quantity,price,repeat_period,repeat_date FROM '.TABLE_PREFIX.'invoice_fixed '.
                   'WHERE client_id = "'.$this->db->escapeSql($client_id).'" '.
                   'ORDER BY name ';
            $repeat_arr = $this->db->readSqlArray($sql); 
            if($repeat_arr !== 0) {
              foreach($repeat_arr as $repeat) {
                $valid = false;
                if($repeat['repeat_period'] == 'MONTHLY') $valid = true;
                if($repeat['repeat_period'] == 'ANNUALY') {
                    $repeat_date = substr($from,0,4).substr($repeat['repeat_date'],4);
                    if(Date::dateInRange($from,$to,$repeat_date)) $valid=true;
                }  
                
                if($valid) {
                  $item_no++;
                  $total = round(($repeat['quantity']*$repeat['price']),0);
                  $items[0][$item_no] = $repeat['quantity'];
                  $items[1][$item_no] = $repeat['name'];
                  $items[2][$item_no] = $repeat['price'];
                  $items[3][$item_no] = $total;
                  $item_total += $total;
                }  
              }  
            } 
            
            //add 5 xtra empty rows to items
            for($i = 1; $i <= 5; $i++) {
              $item_no++;
              $items[0][$item_no] = '';
              $items[1][$item_no] = '';
              $items[2][$item_no] = '';
              $items[3][$item_no] = '';
            }   
          
            $this->data['items'] = $items;
            $this->data['item_total'] = $item_total;
        } 
        
        //PROCESS invoice options and manual items adjustments
        if($this->page_no == 2) {
          //$this->addError('WTF');
          
            $items=$this->data['items'];
            $vat=$this->form['vat'];
            $client_id=$this->form['client_id'];
            
            //NB: BOOLEAN/checkbox variable, can set to false or empty string but any other text will be interpreted as checked
            if($this->form_input and !isset($_POST['show_time'])) $this->form['show_time']=false;
            //whether to show date period in invoice  
            if($this->form_input and !isset($_POST['show_period'])) $this->form['show_period']=false;
            
            //check invoice_no unique
            $sql='SELECT * FROM '.TABLE_PREFIX.'invoice '.
                 'WHERE invoice_no = "'.$this->db->escapeSql($this->form['invoice_no']).'" ';
            $invoice_dup=$this->db->readSqlRecord($sql);
            if($invoice_dup!=0) {
                $this->addError('Invoice No['.$this->form['invoice_no'].'] has been used before!'); 
            }  
            
            
            $item_no=count($items[0])-1; //first row are headers
            $item_total=0.00;
            //process standard items like timesheets and fixed repeats
            for($i=1;$i<=$item_no;$i++) {  
                //Validate::integer('Quantity',0,10000,$_POST['quant_'.$i],$error);
                $items[0][$i]=Secure::clean('float',$_POST['quant_'.$i]);
                $items[1][$i]=Secure::clean('string',$_POST['desc_'.$i]);
                $items[2][$i]=Secure::clean('float',$_POST['price_'.$i]);
                $items[3][$i]=Secure::clean('float',$_POST['total_'.$i]);
                $item_total+=$items[3][$i];
                
                if(round($items[0][$i]*$items[2][$i],2)!=round($items[3][$i],2)) {
                    $this->addError('Invoice item in Row['.$i.'] invalid total!');
                }  
                if($items[0][$i]==0) $items[0][$i]='';
            }  
          
            //check if vat rate valid(if entered)
            if($vat!=0 and $vat>(VAT_RATE*$item_total)) $this->addError('VAT entered is greater than '.(VAT_RATE*100).'% of sub-total!');
          
            $this->data['items']=$items;
            $this->data['item_total']=$item_total;
        }  
        
        //final review/check and processing of email
        if($this->page_no == 3) {
            $items = $this->data['items'];
            $client_id = $this->form['client_id'];
            $email = $this->form['email'];
            $email_xtra = $this->form['email_xtra'];

            $system = $this->getContainer('system');
            if(STORAGE === 'amazon') $s3 = $this->getContainer('s3'); else $s3 = '';
                
            //get rid of empty rows in item array
            $item_no = count($items[0])-1; //first line contains headers
            for($i = 1; $i <= $item_no; $i++) {
                if($items[0][$i] == 0) {
                    unset($items[0][$i]);
                    unset($items[1][$i]);
                    unset($items[2][$i]);
                    unset($items[3][$i]);
                }               
            } 
            //get valid item_no after removing empty rows
            $item_no = count($items[0])-1;
            
            //specify all invoice paramaters
            $invoice = array();
            $invoice['items'] = $items;
            $invoice['item_no'] = $item_no;
            $invoice['no'] = $this->form['invoice_no'];
            $invoice['for'] = $this->form['invoice_for'];
            $invoice['comment'] = $this->form['invoice_comment'];
            $invoice['date_from'] = $this->form['from_date'];
            $invoice['date_to'] = $this->form['to_date'];
            $invoice['subtotal'] = $this->data['item_total'];
            $invoice['vat'] = $this->form['vat'];
            $invoice['total'] = $invoice['subtotal']+$invoice['vat'];
            
            $invoice['show_period'] = $this->form['show_period'];
                  
            if($this->form['show_time'] === 'YES') {
                $invoice['time'] = true;
                $invoice['time_sum'] = true;
                $invoice['sql_time'] = $this->data['sql_time'];;
                $invoice['sql_time_sum'] = $this->data['sql_time_sum'];;
            } else {  
                $invoice['time'] = false;
                $invoice['time_sum'] = false;
            }  
            
            Helpers::createInvoicePdf($this->db,$system,$client_id,$invoice,$doc_name,$error);
            if($error != '') {
                $this->addError('Could not create invoice pdf: '.$error); 
            } else {    
                $this->data['doc_name'] = $doc_name;
                $this->addMessage('Successfully created invoice pdf: "'.$doc_name.'" ');
                
                //create invoice records
                $this->data['invoice_id'] = Helpers::saveInvoice($this->db,$system,$s3,$client_id,$invoice,$doc_name,$error);
                if($error != '') {
                    $this->addError('Could not save invoice data: '.$error); 
                } else {    
                    $this->addMessage('Successfully saved all invoice data.');
                }  
            } 
            
            //now that we have invoice id and pdf assign any additional documents and upload to S3
            if(!$this->errors_found) {
                $docs = array();
                $doc_no = 0;
                foreach($_POST as $key => $value) {
                    if(strpos($key,'file_id_') !== false) {
                        $doc_no++;
                        $file_id = substr($key,8);
                        $docs[$file_id]['name'] = $value;
                    }
                    if(strpos($key,'file_name_') !== false) {
                        $file_id = substr($key,10);
                        $docs[$file_id]['name_original'] = $value;
                    }   
                }
              
                //rename using incremental file id        
                if($doc_no > 0) {
                    $temp_dir = BASE_UPLOAD.UPLOAD_TEMP;
                    $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
                    
                    foreach($docs as $id => $doc) {
                        $rename_from_path = '';
                        $rename_to_path = '';
                        
                        $info = Doc::fileNameParts($doc['name']);
                        $info['extension'] = strtolower($info['extension']);
                        
                        $file_id = Calc::getFileId($this->db);
                        $file_name = $file_id.'.'.$info['extension'];
                        //NB Plupload placed docs in temporary folder
                        $path_old = $temp_dir.$doc['name'];
                        $path_new = $upload_dir.$file_name;
                        //rename doc to new guaranteed non-clashing name
                        if(!rename($path_old,$path_new)) {
                            $this->addError('Could not rename invoice supporting document['.$doc['name_original'].']'); 
                        } else {
                            $docs[$id]['file_name'] = $file_name;
                            $docs[$id]['file_id'] = $file_id;
                            $docs[$id]['file_ext'] = $info['extension'];
                            $docs[$id]['file_size'] = filesize($path_new);
                        }  
                    } 
                }
            }   
            
            //create file records and upload documents to amazon if required
            if(!$this->errors_found and $doc_no > 0) {
                
                if(STORAGE === 'amazon') $s3_files = [];
            
                foreach($docs as $id => $doc) {
                    $file = array();
                    $file['file_id'] = $doc['file_id']; 
                    $file['file_name'] = $doc['file_name'];
                    $file['file_name_orig'] = $doc['name_original'];
                    $file['file_ext'] = $doc['file_ext'];
                    $file['file_date'] = date('Y-m-d');
                    $file['location_id'] = 'INV'.$this->data['invoice_id'];
                    $file['encrypted'] = false;
                    $file['file_size'] = $doc['file_size']; 
                    
                    if(STORAGE === 'amazon') {
                        $path = $upload_dir.$doc['file_name'];
                        $s3_files[] = ['name'=>$file['file_name'],'path'=>$path];
                    } 
                    
                    $this->db->insertRecord(TABLE_PREFIX.'files',$file,$error);
                    if($error != '') $this->addError('ERROR creating supporting document file record: '.$error);
                        
                }
                
                if(STORAGE === 'amazon') {
                    $s3->putFiles($s3_files,$error);
                    if($error != '' ) {
                        $error = 'Amazon S3 document upload error ';
                        if($this->debug) $error .= ': ['.$error.']';
                        $this->addError($error);
                    }    
                }   
            }  
            
            //setup file list with links for final page
            if(!$this->errors_found) {
                $location_id = 'INV'.$this->data['invoice_id'];
                $file_html = '<ul>';
                $sql = 'SELECT file_id,file_name_orig FROM '.TABLE_PREFIX.'files '.
                       'WHERE location_id ="'.$location_id.'" ORDER BY file_id ';
                $invoice_files = $this->db->readSqlList($sql);
                if($invoice_files != 0) {
                    foreach($invoice_files as $file_id => $file_name_orig) {
                        $file_path = 'invoice_files?mode=download&id='.$file_id; 
                        $file_html .= '<li><a href="'.$file_path.'" target="_blank">'.$file_name_orig.'</a></li>';
                    }   
                }
                $file_html .= '</ul>' ; 
                $this->data['files'] = $file_html;
            }  
            
            //finally send invoice with all supporting documents  
            if(!$this->errors_found) {  
                Helpers::sendInvoice($this->db,$this->container,$client_id,$this->data['invoice_id'],$email,$error);
                if($error != '') {
                    $error_tmp = 'Could not send invoice to "'.$email.'" ';
                    if($this->debug) $error_tmp .= ': '.$error; 
                    $this->addError($error_tmp);
                } else {
                    $this->addMessage('Successfully sent invoice to "'.$email.'" ');
                }  
                if($email_xtra != '' and $error == '') {
                    Helpers::sendInvoice($this->db,$this->container,$client_id,$this->data['invoice_id'],$email_xtra,$error);
                    if($error != '') {
                        $error_tmp = 'Could not send invoice to "'.$email_xtra.'" ';
                        if($this->debug) $error_tmp .= ': '.$error; 
                        $this->addError($error_tmp);
                    } else {
                        $this->addMessage('Successfully sent invoice to "'.$email_xtra.'" ');
                    }
                }  
            }   
          
        }  
    }

    public function setupPageData($no)
    {
        //for upload of any supporting documents
        if($no == 3) {
            $param = array();
            $param['upload_url'] = '/admin/upload?mode=upload';
            $param['list_id'] = 'file-list';
            $param['reset_id'] = 'reset-upload';
            $param['start_id'] = 'start-upload';
            $param['browse_id'] = 'browse-files';
            $param['browse_txt'] = 'Select any supporting documents.';
            $param['reset_txt'] = 'Reset supporting documents.';
              
            $plupload = new Plupload($param);
            
            //creates necessay includes and custom js
            $this->data['upload_div'] = $plupload->setupFileDiv();
            $this->javascript .= $plupload->getJavascript();
            
        }
    }

}

?>


