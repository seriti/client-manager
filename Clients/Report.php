<?php
namespace App\Clients;

use Seriti\Tools\Form;
use Seriti\Tools\Report AS ReportTool;

class Report extends ReportTool
{
     

    //configure
    public function setup() 
    {
        //$this->report_header = '';
        $this->always_list_reports = true;

        $param = ['input'=>['select_client','select_dates']];
        $this->addReport('CLIENT_TIME','Client time sheets',$param); 
        $this->addReport('CLIENT_STATEMENT','Client statement simple',$param); 
        $this->addReport('CLIENT_STATEMENT_LEDGER','Client statement ledger',$param); 

        $param = ['input'=>['select_task']];
        $this->addReport('TASK_SUMMARY','Client Task summary',$param); 
 
        $this->addInput('select_client','Select client');
        $this->addInput('select_dates','Select date range');
        $this->addInput('select_task','Select task');
    }

    protected function viewInput($id,$form = []) 
    {
        $html = '';
        
        if($id === 'select_client') {
            $param = [];
            $param['xtra'] = array('ALL'=>'ALL Clients');
            $param['class'] = 'form-control input-medium';
            $sql = 'SELECT client_id,name FROM '.TABLE_PREFIX.'client ORDER BY name';
            if(isset($form['client_id'])) $client_id = $form['client_id']; else $client_id = 'ALL';
            $html .= Form::sqlList($sql,$this->db,'client_id',$client_id,$param);
        }

        if($id === 'select_task') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $sql = 'SELECT task_id,name FROM '.TABLE_PREFIX.'task ORDER BY name';
            if(isset($form['task_id'])) $task_id = $form['task_id']; else $task_id = '';
            $html .= Form::sqlList($sql,$this->db,'task_id',$task_id,$param);
        }

        if($id === 'select_dates') {
            $param = [];
            $param['class'] = 'form-control bootstrap_date input-small';

            $date = getdate();

            if(isset($form['from_date'])) {
                $from_date = $form['from_date'];
            } else {
                $from_date = date('Y-m-d',mktime(0,0,0,$date['mon']-1,$date['mday'],$date['year']));;
            }

            if(isset($form['to_date'])) {
                $to_date = $form['to_date'];
            } else {
                $to_date = date('Y-m-d');;
            }     
            
            $html .= '<table>
                        <tr>
                          <td align="right" valign="top" width="20%"><b>From date : </b></td>
                          <td>'.Form::textInput('from_date',$from_date,$param).'</td>
                        </tr>
                        <tr>
                          <td align="right" valign="top" width="20%"><b>To date : </b></td>
                          <td>'.Form::textInput('to_date',$to_date,$param).'</td>
                        </tr>
                     </table>';
        } 

        return $html;       
    }

    protected function processReport($id,$form = []) 
    {
        $html = '';
        $error = '';
        if($id === 'CLIENT_TIME') {
            $html .= Helpers::timeSheets($this->db,$this->container,$form['client_id'],$form['from_date'],$form['to_date'],$error); 
            if($error !== '') $this->addError($error);
        }

        if($id === 'CLIENT_STATEMENT') {
            if($form['client_id'] === 'ALL') {
                $this->addError('Statement only valid for a single client!');
            } else {
                $html .= Helpers::statement($this->db,$this->container,$form['client_id'],$form['from_date'],$form['to_date'],$error); 
                if($error !== '') $this->addError($error);
            }    
        }

        if($id === 'CLIENT_STATEMENT_LEDGER') {
            if($form['client_id'] === 'ALL') {
                $this->addError('Statement only valid for a single client!');
            } else {
                $html .= Helpers::statementLedger($this->db,$this->container,$form['client_id'],$form['from_date'],$form['to_date'],$error); 
                if($error !== '') $this->addError($error);
            }    
        }

        if($id === 'TASK_SUMMARY') {
            $options = [];
            $options['format'] = 'HTML';
            $html .= Helpers::taskReport($this->db,$this->container,$form['task_id'],$options,$error); 
            if($error !== '') $this->addError($error);
        }

        return $html;
    }
}

?>