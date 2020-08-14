<?php
namespace App\Clients;

use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Doc;
use Seriti\Tools\Calc;
use Seriti\Tools\Secure;
use Seriti\Tools\DbInterface;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;
use Seriti\Tools\ContainerHelpers;

use App\Clients\Helpers;
use App\Clients\TABLE_PREFIX_GL;

use Psr\Container\ContainerInterface;

//NB: legacy code, does not use Wizard class
class PaymentWizard 
{
    use IconsClassesLinks;
    use MessageHelpers;
    use ContainerHelpers;
   
    protected $container;
    protected $container_allow = ['user'];

    protected $db;
    protected $debug = false;

    protected $mode = 'start';
    protected $errors = array();
    protected $errors_found = false; 
    protected $messages = array();

    protected $user_id;

    public function __construct(DbInterface $db, ContainerInterface $container) 
    {
        $this->db = $db;
        $this->container = $container;
               
        if(defined('\Seriti\Tools\DEBUG')) $this->debug = \Seriti\Tools\DEBUG;
    }

    public function process()
    {
        $error = '';
        $sql = '';
        $html ='';
        $html2 = '';
        $account_id = '';
        $email = '';

        $date = getdate();
        $from_date = date('Y-m-d',mktime(0,0,0,$date['mon']-12,1,$date['year']));
        $to_date = date('Y-m-d');
        $xtra = array();

        $this->mode = 'start';
        if(isset($_GET['mode'])) $this->mode = Secure::clean('basic',$_GET['mode']);

        //initial wizard settings
        $match_client = true; 
        $assign_keywords = false;
        $unlinked_payments_only = true;

        //keywords for ALL clients to match with payments and update existing keywords
        $sql = 'SELECT client_id,keywords FROM '.TABLE_PREFIX.'client '.
               'WHERE status = "ACTIVE" ORDER BY name ';
        $client_keywords = $this->db->readSqlList($sql); 
        //keywords in payment description to ignore
        $word_ignore = array('THE','CREDIT','DEBIT','CARD','BANK','(PTY)','LTD','PAYMENT','PURCHASE',
                             'TRANSFER','BANKING','ELECTRONIC','MAGTAPE','FROM');

        $list_param = array();
        $list_param['class'] = 'form-control';

        $date_param = array();
        $date_param['class'] = 'form-control edit_input bootstrap_date';

        if($this->mode === 'start') {
            //category=1 is "Clients"
            $sql = 'SELECT A.account_id,CONCAT(C.name,": ",A.name)  '.
                   'FROM '.TABLE_PREFIX_GL.'account AS A JOIN '.TABLE_PREFIX_GL.'company AS C ON(A.company_id = C.company_id) '.
                   'WHERE A.type_id LIKE "INCOME%" '.
                   'ORDER BY A.company_id,A.name';

            $html .= '<div id="wtf">'.
                     '<form method="post" action="?mode=review" name="create_review" id="create_review">'.
                     '<table width="50%">';
            
            $html .= '<tr><td>For company account:</td><td>'.Form::sqlList($sql,$this->db,'account_id',$account_id,$list_param).'</td></tr>'.
                     '<tr><td>From:</td><td>'.Form::textInput('from_date',$from_date,$date_param).'</td></tr>'.
                     '<tr><td>To:</td><td>'.Form::textInput('to_date',$to_date,$date_param).'</td></tr>'.
                     '<tr><td>Only payments NOT linked before:</td><td>'.Form::checkBox('unlinked_payments_only','YES',$unlinked_payments_only).'<i>(Check if you only want to    review UNlinked payments)</i></td></tr>'.
                     '<tr><td>Match Client:</td><td>'.Form::checkBox('match_client','YES',$match_client).'<i>(Un-check if you want to select from all clients)</i></td></tr>'.
                     '<tr><td>Proceed: </td><td><input class="btn btn-primary" type="submit" value="review matching ledger payments"></td></tr>'.
                     '</table></div>';
            $html .= '</form>';
            
            $this->addMessage('Find all payment transactions captured in general ledger');
            $this->addMessage('Match payments to Clients by searching for client name & invoice prefix in transaction description');
    
        }

        if($this->mode === 'review') {
            $account_id = Secure::clean('basic',$_POST['account_id']);
            $from_date = Secure::clean('date',$_POST['from_date']);
            $to_date = Secure::clean('date',$_POST['to_date']);
            
            if(isset($_POST['match_client'])) $match_client = true; else $match_client = false;
            if(isset($_POST['unlinked_payments_only'])) $unlinked_payments_only = true; else $unlinked_payments_only = false;  
            
            $sql = 'SELECT A.account_id,A.name,A.description,A.type_id,C.name AS company '.
                   'FROM '.TABLE_PREFIX_GL.'account  AS A JOIN '.TABLE_PREFIX_GL.'company AS C ON(A.company_id = C.company_id)'.
                   'WHERE A.account_id = "'.$account_id.'" ';
            $account = $this->db->readSqlRecord($sql);     
              
            $sql = 'SELECT T.transact_id,T.date,T.amount,T.description '.
                   'FROM '.TABLE_PREFIX_GL.'transact AS T ';
            if($unlinked_payments_only) $sql .= 'LEFT JOIN '.TABLE_PREFIX.'payment AS P ON(T.transact_id = P.transact_id )';
            $sql .= 'WHERE T.type_id = "CASH" AND T.debit_credit = "C" AND '.
                          'T.account_id = "'.$account_id.'" AND '.
                          'T.date >= "'.$from_date.'" AND T.date <= "'.$to_date.'" ';  
            if($unlinked_payments_only) $sql .= 'AND P.client_id IS NULL ';            
            $transactions = $this->db->readSqlArray($sql); 
            if($transactions == 0) {
              //echo $sql;
              if($unlinked_payments_only) $str = 'UNLINKED '; else $str  ='';
              $this->addError('NO '.$str.'Ledger payment transactions found from '.$from_date.' to '.$to_date.' '.
                              'for company['.$account['company'].'] account['.$account['name'].'] ');
            } else {  
              $html .= '<form method="post" action="?mode=update" name="update_invoice" id="update_invoice">'.
                       '<input type="hidden" name="account_id" value = "'.$account_id.'">'.
                       '<input type="hidden" name="from_date" value = "'.$from_date.'">'.
                       '<input type="hidden" name="to_date" value = "'.$to_date.'">'.
                       '<input type="hidden" name="unlinked_payments_only" value = "'.$unlinked_payments_only.'">'.
                       '<input type="hidden" name="match_client" value = "'.$match_client.'">';
              
              //NB: this is not really necessary as still may want to assign keywords even if match_client not selected  
              if($match_client or $unlinked_payments_only) {
                $html .= '<p>'.Form::checkBox('assign_keywords','1',$assign_keywords).
                         'Assign payment description keywords to clients for future recognition?</p>'; 
              }  
              
              $html .= '<table class="table  table-striped table-bordered table-hover table-condensed" >'.
                       '<tr><th>Transact ID</th><th>Date</th><th>Amount</th><th>Description</th><th>Possible Client</th></tr>';
                     
              //default all clients list
              $sql = 'SELECT client_id,CONCAT(name,"[",invoice_prefix,"]") '.
                     'FROM '.TABLE_PREFIX.'client '.
                     'WHERE status = "ACTIVE" ORDER BY name ';
              $client_list_all = $this->db->readSqlList($sql);      
            
              foreach($transactions as $transact_id => $data) {
                $client_id = 'NONE';  
                $select_list = array();
                
                if($match_client) {
                  
                  //Remove all non-word chars from transaction description
                  $text = preg_replace('/[0-9]/','',$data['description']);
                  $text = explode(' ',$text);
                  $text = array_map('trim',$text);
                   
                  //make guess at client id using keyword matches
                  $key_client_id = 'NONE';
                  $max_score = 0;
                  foreach($client_keywords as $id => $keywords) {
                    if($keywords != '') {
                      $score = 0;
                      foreach($text as $word) {
                        if(stripos($keywords,$word) !== false) $score++;
                      } 
                      if($score > $max_score) {
                        $key_client_id = $id;
                        $max_score = $score; 
                      }  
                    }  
                  }
                  
                  //check for clients that match name OR invoice prefix, OR key word match(if any)
                  $sql = 'SELECT client_id,CONCAT(name,"[",invoice_prefix,"]") '.
                         'FROM '.TABLE_PREFIX.'client '.
                         'WHERE (invoice_prefix <> "" AND INSTR("'.$data['description'].'",invoice_prefix)>0) OR '.
                         'INSTR("'.$data['description'].'",name)>0 ';
                  if($key_client_id !== 'NONE') $sql .= 'OR client_id = "'.$key_client_id.'" ';     
                  $sql .= 'ORDER BY NAME ';
                  $client_list_matched = $this->db->readSqlList($sql); 
                  if($client_list_matched != 0) {
                    //get first match
                    if($key_client_id !== 'NONE') {
                      $client_id = $key_client_id;
                    } else {  
                      $keys = array_keys($client_list_matched);
                      $client_id = $keys[0];
                    }  
                    $select_list = $client_list_matched; 
                  } 
                } 
                
                if($client_id === 'NONE') $select_list = $client_list_all; 
                $list_param['xtra'] = array('NONE'=>'NO matching client');                 
                
                $html .= '<tr><td>'.$transact_id.'</td><td>'.$data['date'].'</td><td>'.$data['amount'].'</td><td>'.$data['description'].'</td>';
                $html .= '<td>'.Form::arrayList($select_list,'transact_'.$transact_id,$client_id,true,$list_param).'</td>'.
                         //'<td>'.Form::sqlList($sql,$this->db,'transact_'.$transact_id,$client_id,$param).'</td>'.
                         '</tr>';
              }  
  
              $html .= '</table>'.
                       '<input class="btn btn-primary" type="submit" value="CREATE PAYMENTS ONLY FROM TRANSACTIONS MATCHED TO CLIENTS">';
                      
              $html .= '</form>';
              
            }  
        }

        if($this->mode === 'update') {
            $account_id = Secure::clean('basic',$_POST['account_id']);
            $from_date = Secure::clean('date',$_POST['from_date']);
            $to_date = Secure::clean('date',$_POST['to_date']);
              
            if(isset($_POST['assign_keywords'])) $assign_keywords = true; else $assign_keywords = false; 
            
            if(isset($_POST['unlinked_payments_only'])) $unlinked_payments_only = true; else $unlinked_payments_only = false;  
              
            //get all transactions for matching
            $sql = 'SELECT T.transact_id,T.date,T.amount,T.description '.
                   'FROM '.TABLE_PREFIX_GL.'transact AS T ';
            if($unlinked_payments_only) $sql .= 'LEFT JOIN '.TABLE_PREFIX.'payment AS P ON(T.transact_id = P.transact_id )';
            $sql .= 'WHERE T.type_id = "CASH" AND T.debit_credit = "C" AND '.
                          'T.account_id = "'.$account_id.'" AND '.
                          'T.date >= "'.$from_date.'" AND T.date <= "'.$to_date.'" ';  
            if($unlinked_payments_only) $sql .= 'AND P.client_id IS NULL ';                       
            $transactions = $this->db->readSqlArray($sql); 
            if($transactions == 0) {
              $this->addError('NO Ledger payment transactions found from '.$from_date.' to '.$to_date.' ');
            } else {
              foreach($transactions as $transact_id => $data) {
                if(isset($_POST['transact_'.$transact_id])) {
                  $client_id = Secure::clean('basic',$_POST['transact_'.$transact_id]);
                  if($client_id !== 'NONE') {
                    //NB: if $client_id numeric then valid client selected
                    if(is_numeric($client_id)) {
                      
                      Helpers::addGlPayment($this->db,$client_id,$transact_id,$message_str,$error);
                      if($error != '') $this->addError('Could not create payment: '.$error);
                      if($message_str != '') $this->addMessage($message_str);
                      
                      //update/assign transaction keywords to client 
                      if($assign_keywords) {
                        $length_min = 2;
                        //Remove all non-word chars
                        $text = preg_replace('/[0-9]/','',strtoupper($data['description']));
                        $text = explode(' ',$text);
                        $text = array_map('trim',$text);
                         
                        $keywords_new = '';
                        $keywords = $client_keywords[$client_id];
                        if($keywords == '') {
                          foreach($text as $word) {
                            if(strlen($word) > $length_min and !in_array($word,$word_ignore)) $keywords_new .= ' '.$word; 
                          }  
                          $keywords_new = trim($keywords_new);
                        } else {
                          $keywords = explode(' ',$keywords);
                          $keywords = array_map('trim',$keywords);
                          foreach($text as $word) {
                            if(strlen($word) > $length_min and !in_array($word,$keywords) and !in_array($word,$word_ignore)) {
                              $keywords_new .= ' '.$word; 
                            }  
                          }  
                        }  
                         
                        if($keywords_new != '') {
                          $keywords = trim($client_keywords[$client_id].' '.$keywords_new);
                          $sql = 'UPDATE '.TABLE_PREFIX.'client SET keywords = "'.$this->db->escapeSql($keywords).'" '.
                                 'WHERE client_id = "'.$this->db->escapeSql($client_id).'" ';
                          $this->db->executeSql($sql,$error);      
                        } 
                      }  
                      
                    } else {
                      $this->addError('Invalid Client ID['.$client_id.'] selected for Transaction ID['.$transact_id.']'); 
                    }   
                  }  
                }   
              }  
            }  
            
            $html .= '<p><a href="?mode=start">Match more Ledger transactions to clients</a></p>';  
        }


        $html = $this->viewMessages().$html;
        return $html; 
    }

}

?>


