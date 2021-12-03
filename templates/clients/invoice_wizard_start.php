<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$html = '';

$date = getdate();
if($form['from_date'] == '') $form['from_date'] = date('Y-m-d',mktime(0,0,0,$date['mon'],1,$date['year']));
if($form['to_date'] == '') $form['to_date'] = date('Y-m-d',mktime(0,0,0,$date['mon']+1,1,$date['year']));

$html .= '<div>'.
         //'<form method="post" action="?mode=review" name="create_invoice" id="create_invoice">'.
         '<table>';

$list_param['class'] = 'form-control edit_input';
$date_param['class'] = 'form-control edit_input bootstrap_date';

$sql = 'SELECT `client_id`, `name` FROM `'.TABLE_PREFIX.'client` ORDER BY `name`';
$html .= '<tr><td>For client:</td><td>'.Form::sqlList($sql,$db,'client_id',$form['client_id'],$list_param).'</td></tr>'.
         '<tr><td>From:</td><td>'.Form::textInput('from_date',$form['from_date'],$date_param).'</td></tr>'.
         '<tr><td>To:</td><td>'.Form::textInput('to_date',$form['to_date'],$date_param).'</td></tr>'.
         '<tr><td>Proceed: </td><td><input class="btn btn-primary" type="submit" value="review invoice data"></td></tr>'.
         '</table></div>';

//check for annual fixed item renewals that may not be regular monthly clients
$sql = 'SELECT F.`fixed_id` AS `ID`,C.`name` AS `client`,F.`name` as `fixed item`,F.`quantity`,F.`price`,F.`repeat_date` AS `repeat date` '.
       'FROM `'.TABLE_PREFIX.'invoice_fixed` AS F JOIN `'.TABLE_PREFIX.'client` AS C ON(F.`client_id` = C.`client_id`) '.
       'WHERE F.`repeat_period` = "ANNUALY" AND MONTH(F.`repeat_date`) = '.$date['mon'].' ';
$fixed_annual = $db->readSqlArray($sql);
if($fixed_annual != 0) {
  $html .= '<br/><br/><p>Following clients have fixed annual renewals due:</p>';
  $html .= Html::arrayDumpHtml($fixed_annual); 
} 
  
echo $html;          

//print_r($form);
//print_r($data);
?>
