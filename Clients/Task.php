<?php 
namespace App\Clients;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Audit;

use App\Clients\Helpers;

class Task extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Task','col_label'=>'name'];
        parent::setup($param);

        $this->addTableCol(array('id'=>'task_id','type'=>'INTEGER','title'=>'Task ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Task name'));
        $this->addTableCol(array('id'=>'client_id','type'=>'INTEGER','title'=>'For client',
                                 'join'=>'`name` FROM `'.TABLE_PREFIX.'client` WHERE `client_id`'));
        $this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Description','required'=>false));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATE','title'=>'Date','new'=>date('Y-m-d')));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'NEW'));

        $this->addSortOrder('T.task_id DESC','Create date latest','DEFAULT');

        $this->setupFiles(array('location'=>'TSK','max_no'=>10,
                                'table'=>TABLE_PREFIX.'files','list'=>true,'list_no'=>10,
                                'link_url'=>'task_file','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

        $this->addAction(array('type'=>'check_box','text'=>'')); 
        $this->addAction(array('type'=>'edit','text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));


        $this->addSearch(array('client_id','name','date_create','description','status'),array('rows'=>2));

        $this->addSelect('client_id','SELECT `client_id`,`name` FROM `'.TABLE_PREFIX.'client` ORDER BY `name`');
        $this->addSelect('status','(SELECT "NEW") UNION (SELECT "DONE") UNION (SELECT "CANCEL")');

        $this->addAction(array('type'=>'popup','text'=>'Diary','url'=>'task_diary','mode'=>'view','width'=>600,'height'=>600)); 


    }    
    protected function viewTableActions() {
        $html = '';
        $list = array();
            
        $status_set = 'NEW';
        $date_set = date('Y-m-d');
        
        if(!$this->access['read_only']) {
            $list['SELECT'] = 'Action for selected '.$this->row_name_plural;
            $list['STATUS_CHANGE'] = 'Change Task Status.';
            $list['EMAIL_TASK'] = 'Email Task summary';
        }  
        
        if(count($list) != 0){
            $html .= '<span style="padding:8px;"><input type="checkbox" id="checkbox_all"></span> ';
            $param['class'] = 'form-control input-medium input-inline';
            $param['onchange'] = 'javascript:change_table_action()';
            $action_id = '';
            $status_change = 'NONE';
            $email_address = '';
            
            $html .= Form::arrayList($list,'table_action',$action_id,true,$param);
            
            //javascript to show collection list depending on selecetion      
            $html .= '<script type="text/javascript">'.
                     '$("#checkbox_all").click(function () {$(".checkbox_action").prop(\'checked\', $(this).prop(\'checked\'));});'.
                     'function change_table_action() {'.
                     'var table_action = document.getElementById(\'table_action\');'.
                     'var action = table_action.options[table_action.selectedIndex].value; '.
                     'var status_select = document.getElementById(\'status_select\');'.
                     'var email_task = document.getElementById(\'email_task\');'.
                     'status_select.style.display = \'none\'; '.
                     'email_task.style.display = \'none\'; '.
                     'if(action==\'STATUS_CHANGE\') status_select.style.display = \'inline\';'.
                     'if(action==\'EMAIL_TASK\') email_task.style.display = \'inline\';'.
                     '}'.
                     '</script>';
            
            $param = array();
            $param['class'] = 'form-control input-small input-inline';
            //$param['class']='form-control col-sm-3';
            $sql = '(SELECT "NONE") UNION (SELECT "NEW") UNION (SELECT "DONE") UNION (SELECT "CANCEL")';
            $html .= '<span id="status_select" style="display:none"> status&raquo;'.
                     Form::sqlList($sql,$this->db,'status_change',$status_change,$param).
                     '</span>'; 
            
            $param['class'] = 'form-control input-medium input-inline';       
            $html .= '<span id="email_task" style="display:none"> Email address&raquo;'.
                     Form::textInput('email_address',$email_address,$param).
                     '</span>';
    
                
            $html .= '&nbsp;<input type="submit" name="action_submit" value="Apply action to selected '.
                     $this->row_name_plural.'" class="btn btn-primary">';
        }  
        
        return $html; 
    }
  
    //update multiple records based on selected action
    function updateTable() {
        $error_str = '';
        $error_tmp = '';
        $message_str = '';
        $audit_str = '';
        $audit_count = 0;
        $html = '';
                    
        $action = Secure::clean('basic',$_POST['table_action']);
        if($action === 'SELECT') {
            $this->addError('You have not selected any action to perform on '.$this->row_name_plural.'!');
        } else {
            if($action === 'STATUS_CHANGE') {
              $status_change = Secure::clean('alpha',$_POST['status_change']);
              $audit_str = 'Status change['.$status_change.'] ';
              if($status_change === 'NONE') $this->addError('You have not selected a valid status['.$status_change.']!');
            }
            
            if($action === 'EMAIL_TASK') {
              $email_address = Secure::clean('email',$_POST['email_address']);
              Validate::email('email address',$email_address,$error_str);
              $audit_str = 'Email task to['.$email_address.'] ';
              if($error_str !== '') $this->addError('INVAID email address['.$email_address.']!');
            }
            
            if(!$this->errors_found) {     
                foreach($_POST as $key => $value) {
                    if(substr($key,0,8) === 'checked_') {
                        $task_id = substr($key,8);
                        $audit_str .= 'Task ID['.$task_id.'] ';
                                            
                        if($action === 'STATUS_CHANGE') {
                            $sql = 'UPDATE `'.$this->table.'` SET `status` = "'.$this->db->escapeSql($status_change).'" '.
                                   'WHERE `task_id` = "'.$this->db->escapeSql($task_id).'" ';
                            $this->db->executeSql($sql,$error_tmp);
                            if($error_tmp == '') {
                                $message_str = 'Status set['.$status_change.'] for Task ID['.$task_id.'] ';
                                $audit_str .= ' success!';
                                $audit_count++;
                                
                                $this->addMessage($message_str);                
                            } else {
                                $this->addError('Could not update status for task['.$task_id.']: '.$error_tmp);                
                            }  
                        }
                        
                        if($action == 'EMAIL_TASK') {
                            $sql = 'SELECT `task_id`,`client_id`,`name`,`description`,`date_create`,`status` FROM `'.$this->table.'` '.
                                   'WHERE `task_id` = "'.$this->db->escapeSql($task_id).'" ';
                            $task = $this->db->readSqlRecord($sql);
                                                        
                            Helpers::sendTaskReport($this->db,$this->container,$task['task_id'],$email_address,$error_tmp);
                            if($error_tmp == '') {
                                $audit_str.= 'success!';
                                $audit_count++;
                                $this->addMessage('Task['.$task['name'].'] summary sent to email['.$email_address.']');      
                            } else {
                                $this->addError('Cound not email task['.$task['name'].'] to email address['.$email_address.']!');
                            }   
                        }  
                    }   
                }  
            }  
        }  
        
        //audit any updates except for deletes as these are already audited 
        if($audit_count != 0 and $action != 'DELETE') {
            $audit_action = $action.'_'.strtoupper($this->table);
            Audit::action($this->db,$this->user_id,$audit_action,$audit_str);
        }  
            
        $this->mode = 'list';
        $html.=$this->viewTable();
            
        return $html;
    }
}
?>
