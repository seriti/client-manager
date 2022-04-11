<?php 
namespace App\Clients;

use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\DbInterface;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;
use Seriti\Tools\ContainerHelpers;

use Psr\Container\ContainerInterface;


class TimeSheet
{
    use IconsClassesLinks;
    use MessageHelpers;
    use ContainerHelpers;
   
    protected $container;
    protected $container_allow = ['user'];

    protected $db;
    protected $debug = false;

    protected $mode = 'list';
    protected $errors = [];
    protected $errors_found = false; 
    protected $messages = [];
    
    protected $user_id;

    public function __construct(DbInterface $db, ContainerInterface $container) 
    {
        $this->db = $db;
        $this->container = $container;
       
        if(defined('\Seriti\Tools\DEBUG')) $this->debug = \Seriti\Tools\DEBUG;
    }

    public function process() {
        $error = '';

        $page_title = MODULE_LOGO.' Clients : Time recording sheet';

        $this->mode = 'new';
        if (@$_GET['mode']) $this->mode = $_GET['mode'];

        $user_id = $this->getContainer('user')->getId();
        $user_access = $this->getContainer('user')->getAccessLevel();
        //if($user_access === 'GOD') $client_access = 'ALL'; else $client_access = 'ASSOCIATE'; 
        
        if($this->mode === 'new') {
            $client_id = '';
            $type_id = '';
            $start_date = date('Y-m-d');
            $time = '00:00';
            $start_time = '00:00';
            $comment = '';
        }

        if($this->mode == 'update') {
            $type_id = Secure::clean('basic',$_POST['type_id']);
            $client_id = Secure::clean('basic',$_POST['client_id']);
            
            //get client details
            $sql = 'SELECT * FROM '.TABLE_PREFIX.'client WHERE client_id = "'.$client_id.'" ';
            $client = $this->db->readSqlRecord($sql);
            
            $start_date = $_POST['start_date'];
            Validate::Date('Start date',$start_date,'YYYY-MM-DD',$error);
            if($error !== '') $this->addError($error);
            
            $start_time = $_POST['start_time'];
            Validate::Time('Start time',$start_time,'HH:MM',$error);
            if($error !== '') $this->addError($error);

            $time = $_POST['time'];
            Validate::Time('Time spent',$time,'HH:MM:SS',$error);
            if($error !== '') $this->addError($error);
            
            $comment = $_POST['comment'];
            Validate::Text('Description',5,64000,$comment,$error);
            if($error !== '') $this->addError($error);
            
            //split out hours and minutes, ignore seconds
            if(!$this->errors_found) {
                $time_hours = substr($time,0,2);
                $time_minutes = substr($time,3,2);
                
                $time_minutes = $time_minutes+$time_hours*60;
                if($time_minutes<1) $error.=$this->addError('Time spent cannot be zero!');
            }
                                                
            if(!$this->errors_found) {
                $data = array();
                $data['user_id'] = $user_id;
                $data['client_id'] = $client_id;
                $data['type_id'] = $type_id;
                $data['time_start'] = $start_date.' '.$start_time.':00';
                $data['time_minutes'] = $time_minutes; 
                $data['comment'] = $comment;
              
                $this->db->insertRecord(TABLE_PREFIX.'time',$data,$error);
                if($error != '') {
                    $this->addError('Could not create timesheet record!'); 
                } else {  
                    $this->addMessage('Time spent '.$time.' for client '.$client['name'].' Successfully captured.');
                }
            }
            
        }

        $html = '';
        //$html .= '<h1>Timesheet <a href="Javascript:onClick=window.close()">[close]</a></h1>';
        $html .= '<table width="100%" border="0" cellpadding="4" cellspacing="0">
                  <tr><td>'.$this->viewMessages();
              
        $html .= '<form method="post" action="?mode=update" name="stpw">
                  <table width="100%" border="0" cellpadding="2" cellspacing="0">
                    <tr>
                      <td width="120" align="right">Client :&nbsp;</td>
                      <td align="left">';

        $param = array();
        $param['class'] = 'form-control edit_input';
        $sql = 'SELECT `client_id`,`name` FROM `'.TABLE_PREFIX.'client` ORDER BY `name`';              
        $html .= Form::sqlList($sql,$this->db,'client_id',$client_id,$param);
                         
        $html .= '    </td>
                    </tr>
                    <tr>
                      <td align="right">Activity :&nbsp;</td>
                      <td align="left">';
                       
        $sql='SELECT `type_id`,`name` FROM `'.TABLE_PREFIX.'time_type` WHERE `status` <> "HIDE" ORDER BY `name`';
        $html .= Form::sqlList($sql,$this->db,'type_id',$type_id,$param);
                       
        $html .= '    </td>
                    </tr>
                    <tr>
                      <td align="right">on date :&nbsp;</td>
                      <td align="left">';
                        
        $param['class'] = 'form-control edit_input bootstrap_date';
        $html .= Form::textInput('start_date',$start_date,$param);
                        
        $html .= '    </td>
                    </tr>
                    <tr>
                      <td align="right">start time :&nbsp;</td>
                      <td align="left">';

        $param['class'] = 'form-control input-small';
        $html .=  Form::textInput('start_time',$start_time,$param);
                        
        $html .= '    </td>
                    </tr>
                    <tr>
                      <td align="right">Time spent :&nbsp;</td>
                      <td align="left">';

        $html .= Form::textInput('time',$time,$param);
                        
        $html .= '    </td>
                    <tr>
                    <tr>
                      <td align="right" valign="top">Description: </td>
                      <td>';

        $param = 'form-control edit_input';
        $html .=  Form::textAreaInput('comment',$comment,'30','5',$param);
                        
                        
        $html .= '    </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <INPUT TYPE="BUTTON" class="btn"  Name="ssbutton" VALUE="Start/Stop" onClick="startstop()">
                        <INPUT TYPE="BUTTON" class="btn" NAME="reset" VALUE="Reset" onClick="swreset()">
                        <INPUT TYPE="submit" class="btn btn-primary" "NAME="submit" VALUE="Submit time sheet">
                      </td>
                    </tr>
                  </table>
                  </form>
                </td>
              </tr>
            </table>';

        return $html;

    }

    public function getJavascript()
    {
        $js = "
        <script language='javascript'>

        <!-- Begin
        var ms = 0;
        var state = 0;
        function startstop() {
          if (state == 0) {
            state = 1;
            then = new Date();
            then.setTime(then.getTime() - ms);
          } else {
            state = 0;
          }
          display();
        }

        function swreset() {
          state = 0;
          ms = 0;

          start = new Date();
          hours = start.getHours();
          minutes = start.getMinutes();
          if (hours <= 9) { hours = '0' + hours; }
          if (minutes <= 9) { minutes = '0' + minutes; }

          document.stpw.start_time.value = hours + ':' + minutes;
          document.stpw.time.value = '00:00';
        }

        function display() {
          setTimeout('display();', 1000);
          if (state == 1)  {
            now = new Date();
            ms = now.getTime() - then.getTime();
            seconds = Math.round(ms/1000);
            minutes = Math.floor(seconds/60);
            hours   = Math.floor(minutes/60);

            seconds = seconds - minutes*60;
            minutes = minutes - hours*60;

            if (hours <= 9) { hours = '0' + hours; }
            if (minutes <= 9) { minutes = '0' + minutes; }
            if (seconds <= 9) { seconds = '0' + seconds; }
            document.stpw.time.value = hours + ':' + minutes + ':' + seconds;
          }
        }

        swreset();
        </script>";

        return $js;

    }        
}    








