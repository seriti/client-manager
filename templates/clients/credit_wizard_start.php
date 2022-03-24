<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$html = '';

if($form['credit_date'] == '') $form['credit_date'] = date('Y-m-d');

$html .= '<div>'.
         //'<form method="post" action="?mode=review" name="create_credit" id="create_credit">'.
         '<table>';

$list_param['class'] = 'form-control edit_input';
$date_param['class'] = 'form-control edit_input bootstrap_date';

$sql = 'SELECT `client_id`, `name` FROM `'.TABLE_PREFIX.'client` ORDER BY `name`';
$html .= '<tr><td>For client: </td><td>'.Form::sqlList($sql,$db,'client_id',$form['client_id'],$list_param).'</td></tr>'.
         '<tr><td>Credit date: </td><td>'.Form::textInput('credit_date',$form['credit_date'],$date_param).'</td></tr>'.
         '<tr><td>Proceed: </td><td><input class="btn btn-primary" type="submit" value="Proceed to credit items"></td></tr>'.
         '</table></div>';

echo $html;          

//print_r($form);
//print_r($data);
?>
