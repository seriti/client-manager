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
class InvoicePaymentWizard 
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
        $account_id2 = '';
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
            
            $html .= '<tr><td>For company account:</td><td>'.Form::SqlList($sql,$this->db,'account_id',$account_id,$list_param).'</td></tr>'.
                     '<tr><td>From:</td><td>'.Form::textInput('from_date',$from_date,$date_param).'</td></tr>'.
                     '<tr><td>To:</td><td>'.Form::textInput('to_date',$to_date,$date_param).'</td></tr>'.
                     '<tr><td>Proceed: </td><td><input class="btn btn-primary" type="submit" value="review matching ledger payments"></td></tr>'.
                     '</table></div>';
            $html .= '</form>';
            
            $this->addMessage('Review all UNPAID invoices issued over dates.');
            $this->addMessage('Link Ledger payment transactions which might match Client invoices, or change invoice status manually.');
            

        }

        if($this->mode === 'review') {
            $account_id = Secure::clean('basic',$_POST['account_id']);
            $from_date = Secure::clean('date',$_POST['from_date']);
            $to_date = Secure::clean('date',$_POST['to_date']);
                
            //get all unpaid invoices
            $sql = 'SELECT I.invoice_id,I.invoice_no,I.total,I.date,I.client_id,C.name '.
                   'FROM '.TABLE_PREFIX.'invoice AS I JOIN '.TABLE_PREFIX.'client AS C ON(I.client_id = C.client_id) '.
                   'WHERE I.status = "OK" AND '.
                         'I.date >= "'.$from_date.'" AND I.date <= "'.$to_date.'" '.
                   'ORDER BY I.date';
            $invoices = $this->db->readSqlArray($sql); 
            
            if($invoices == 0) {
                $this->addMessage('NO un-processed invoices found from '.$from_date.' to '.$to_date.' ');
            } else {  
                $html .= '<form method="post" action="?mode=update" name="update_invoice" id="update_invoice">'.
                         '<input type="hidden" name="account_id" value = "'.$account_id.'">'.
                         '<input type="hidden" name="from_date" value = "'.$from_date.'">'.
                         '<input type="hidden" name="to_date" value = "'.$to_date.'">';

                $html .= '<table class="table  table-striped table-bordered table-hover table-condensed" >'.
                         '<tr><th>Client</th><th>Invoice no</th><th>Amount</th><th>Invoice Date</th><th>Possible Payment</th></tr>';
                
                //default payment allocations
                $param['xtra'] = array('NONE'=>'NO matching payment','PAID'=>'Mark as PAID','BAD_DEBT'=>'Mark as BAD DEBT');
                foreach($invoices as $invoice_id => $data) {
                    $sql = 'SELECT transact_id,CONCAT(amount," : ",DATE(date)," : ",description) '.
                           'FROM '.TABLE_PREFIX_GL.'transact '.
                           'WHERE type_id = "CASH" AND debit_credit = "C" AND '.
                                 'account_id = "'.$account_id.'" AND '.
                                 'DATEDIFF(date,"'.$data['date'].'") > -7 AND DATEDIFF(date,"'.$data['date'].'") < 60 AND '.
                                 'ABS(amount - '.$data['total'].') < 10 ';
                                         
                    $transact_id = 'NONE';  
                    
                    $html .= '<tr><td>'.$data['name'].'</td><td>'.$data['invoice_no'].'</td><td>'.$data['total'].'</td><td>'.$data['date'].'</td>';
                    $html .= '<td>'.Form::SqlList($sql,$this->db,'transact_'.$invoice_id,$transact_id,$param).'</td>'.
                                 '</tr>';
                }  

                $html .= '</table>'.
                             '<input class="btn btn-primary" type="submit" value="UPDATE INVOICE STATUS">';
                                
                $html .= '</form>';
            } 
        }

        if($this->mode === 'update') {
            $account_id = Secure::clean('basic',$_POST['account_id']);
            $from_date = Secure::clean('date',$_POST['from_date']);
            $to_date = Secure::clean('date',$_POST['to_date']);
                
            //get all unpaid invoices
            $sql = 'SELECT I.invoice_id,I.invoice_no,I.total,I.date,I.client_id,C.name '.
                   'FROM '.TABLE_PREFIX.'invoice AS I JOIN '.TABLE_PREFIX.'client AS C ON(I.client_id = C.client_id) '.
                   'WHERE I.status = "OK" AND '.
                         'I.date >= "'.$from_date.'" AND I.date <= "'.$to_date.'" '.
                   'ORDER BY I.date';
            $invoices = $this->db->readSqlArray($sql); 
            foreach($invoices as $invoice_id => $data) {
                if(isset($_POST['transact_'.$invoice_id])) {
                    $status = Secure::clean('basic',$_POST['transact_'.$invoice_id]);
                    if($status !== 'NONE') {
                        //NB: if status numeric then transact_id selected
                        if(is_numeric($status)) {
                            $transact_id = $status;
                            
                            Helpers::addGlPayment($this->db,$data['client_id'],$transact_id,$message_str,$error);
                            $status = 'PAID';
                            if($error != '') $this->addError('Could not update payments: '.$error);
                            if($message_str != '') $this->addMessage($message_str);
                        }  
                                    
                        $where = array('invoice_id'=>$invoice_id);
                        $update = array('status'=>$status);
                        $this->db->updateRecord(TABLE_PREFIX.'invoice',$update,$where,$error);
                        if($error != '') {
                            $this->addError('Could not update Invoice['.$data['invoice_no'].'] :'.$error);       
                        } else {
                            $this->addMessage('SUCCESS updating Invoice['.$data['invoice_no'].'] with status :'.$status);   
                        }  
                    }  
                    
                }   
            }  
            
            $html = $this->viewMessages().$html;
            $html .= '<p><a href="?mode=start">Match more invoice payments</a></p>';  
            
            return $html;
        } 
    }

}

?>


