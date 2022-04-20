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
use Seriti\Tools\UPLOAD_ROUTE;

use App\Clients\Helpers;

class CreditWizard extends Wizard 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['bread_crumbs'=>true,'strict_var'=>false];
        $param['csrf_token'] = $this->getContainer('user')->getCsrfToken();
        parent::setup($param);

        $this->addVariable(array('id'=>'client_id','type'=>'INTEGER','title'=>'Client ID','max'=>1000000000));
        $this->addVariable(array('id'=>'credit_date','type'=>'DATE','title'=>'From date'));
        $this->addVariable(array('id'=>'credit_no','type'=>'STRING','title'=>'Credit No.'));
        $this->addVariable(array('id'=>'credit_for','type'=>'STRING','title'=>'Credit For'));
        $this->addVariable(array('id'=>'credit_comment','type'=>'TEXT','title'=>'Credit Comment','required'=>false));
        $this->addVariable(array('id'=>'email','type'=>'EMAIL','title'=>'Primary Email address'));
        $this->addVariable(array('id'=>'email_xtra','type'=>'EMAIL','title'=>'Secondary Email address','required'=>false));
        $this->addVariable(array('id'=>'vat','type'=>'DECIMAL','title'=>'VAT amount','max'=>1000000,'new'=>0));

        //define pages and templates
        $this->addPage(1,'Select Client and period','clients/credit_wizard_start.php');
        $this->addPage(2,'Manage Credit items','clients/credit_wizard_items.php');
        $this->addPage(3,'Review final Credit Note','clients/credit_wizard_review.php');
        $this->addPage(4,'Wizard complete','clients/credit_wizard_final.php',array('final'=>true));    

    }

    public function processPage() 
    {
        $error = '';
        $error_tmp = '';

        //PROCESS select client and time period
        if($this->page_no == 1) {
            $client_id = $this->form['client_id'];
            $credit_date = $this->form['credit_date'];
                        
            $sql = 'SELECT `client_id`,`name`,`email`,`email_alt`,`status`,`credit_no`,`invoice_prefix`,`contact_name` '.
                   'FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "'.$this->db->escapeSql($client_id).'"';
            $this->data['client'] = $this->db->readSqlRecord($sql);
            
            //credit items setup
            $item_no = 0;
            $item_total = 0.00;
            $items = array();
            $items[0][0] = 'Quantity';
            $items[1][0] = 'Description';
            $items[2][0] = 'Unit price';
            $items[3][0] = 'Total';
             
            
            //add 5 empty rows for manual capture
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
        
        //PROCESS credit note options and manual items adjustments
        if($this->page_no == 2) {
          //$this->addError('WTF');
          
            $items = $this->data['items'];
            $vat = $this->form['vat'];
            $client_id = $this->form['client_id'];
            
            //check credit_no unique
            $sql = 'SELECT * FROM `'.TABLE_PREFIX.'credit` '.
                   'WHERE `credit_no` = "'.$this->db->escapeSql($this->form['credit_no']).'" ';
            $credit_dup = $this->db->readSqlRecord($sql);
            if($credit_dup != 0) {
                $this->addError('Credit Note No['.$this->form['credit_no'].'] has been used before!'); 
            }  
            
            
            $item_no = count($items[0])-1; //first row are headers
            $item_total = 0.00;
            //process standard items like timesheets and fixed repeats
            for($i=1; $i<=$item_no; $i++) {  
                
                $items[0][$i] = Secure::clean('float',$_POST['quant_'.$i]);
                $items[1][$i] = Secure::clean('string',$_POST['desc_'.$i]);
                $items[2][$i] = Secure::clean('float',$_POST['price_'.$i]);
                $items[3][$i] = Secure::clean('float',$_POST['total_'.$i]);
                $item_total += (float)$items[3][$i];
                
                if(round( (float)$items[0][$i] * (float)$items[2][$i] ,2) != round((float)$items[3][$i],2)) {
                    $this->addError('Credit item in Row['.$i.'] invalid total!');
                }  
                if($items[0][$i] == 0) $items[0][$i] = '';
            }  

            if($item_total == 0) $this->addError('Your item total cannot be zero!');
          
            //check if vat rate valid(if entered)
            if($vat != 0 and $vat > (VAT_RATE*$item_total)) $this->addError('VAT entered is greater than '.(VAT_RATE*100).'% of sub-total!');
          
            $this->data['items'] = $items;
            $this->data['item_total'] = $item_total;
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
                if($items[0][$i] == '') {
                    unset($items[0][$i]);
                    unset($items[1][$i]);
                    unset($items[2][$i]);
                    unset($items[3][$i]);
                }               
            } 
            //get valid item_no after removing empty rows
            $item_no = count($items[0])-1;
            
            //specify all credit paramaters
            $credit = array();
            $credit['items'] = $items;
            $credit['item_no'] = $item_no;
            $credit['no'] = $this->form['credit_no'];
            $credit['for'] = $this->form['credit_for'];
            $credit['comment'] = $this->form['credit_comment'];
            $credit['date'] = $this->form['credit_date'];
            $credit['subtotal'] = $this->data['item_total'];
            $credit['vat'] = $this->form['vat'];
            $credit['total'] = $credit['subtotal']+$credit['vat'];
                
            Helpers::createCreditPdf($this->db,$system,$client_id,$credit,$doc_name,$error);
            if($error != '') {
                $this->addError('Could not create credit pdf: '.$error); 
            } else {    
                $this->data['doc_name'] = $doc_name;
                $this->addMessage('Successfully created Credit Note pdf: "'.$doc_name.'" ');
                
                //create credit records
                $this->data['credit_id'] = Helpers::saveCredit($this->db,$system,$s3,$client_id,$credit,$doc_name,$error);
                if($error != '') {
                    $this->addError('Could not save Credit Note data: '.$error); 
                } else {    
                    $this->addMessage('Successfully saved all credit note data.');
                }  
            } 
            
            //now that we have credit id and pdf assign any additional documents and upload to S3
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
                            $this->addError('Could not rename credit supporting document['.$doc['name_original'].']'); 
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
                    $file['location_id'] = 'CRD'.$this->data['credit_id'];
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
                $location_id = 'INV'.$this->data['credit_id'];
                $file_html = '<ul>';
                $sql = 'SELECT `file_id`,`file_name_orig` FROM `'.TABLE_PREFIX.'files` '.
                       'WHERE `location_id` ="'.$location_id.'" ORDER BY `file_id` ';
                $credit_file = $this->db->readSqlList($sql);
                if($credit_file != 0) {
                    foreach($credit_file as $file_id => $file_name_orig) {
                        $file_path = 'credit_file?mode=download&id='.$file_id; 
                        $file_html .= '<li><a href="'.$file_path.'" target="_blank">'.$file_name_orig.'</a></li>';
                    }   
                }
                $file_html .= '</ul>' ; 
                $this->data['files'] = $file_html;
            }  
            
            //finally send credit with all supporting documents  
            if(!$this->errors_found) {  
                Helpers::sendCredit($this->db,$this->container,$client_id,$this->data['credit_id'],$email,$error);
                if($error != '') {
                    $error_tmp = 'Could not send credit to "'.$email.'" ';
                    if($this->debug) $error_tmp .= ': '.$error; 
                    $this->addError($error_tmp);
                } else {
                    $this->addMessage('Successfully sent credit to "'.$email.'" ');
                }  
                if($email_xtra != '' and $error == '') {
                    Helpers::sendCredit($this->db,$this->container,$client_id,$this->data['credit_id'],$email_xtra,$error);
                    if($error != '') {
                        $error_tmp = 'Could not send credit to "'.$email_xtra.'" ';
                        if($this->debug) $error_tmp .= ': '.$error; 
                        $this->addError($error_tmp);
                    } else {
                        $this->addMessage('Successfully sent credit to "'.$email_xtra.'" ');
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
            $param['upload_url'] = UPLOAD_ROUTE.'?mode=upload';
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
