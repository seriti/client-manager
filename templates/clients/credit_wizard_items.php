<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$html = '';

//get wizard variables and daata
$client = $data['client'];
$items = $data['items'];
$item_total = $data['item_total'];

if($form['email'] !== '') $email = $form['email']; else $email=$client['email'];
if($form['email_xtra'] !== '') $email_xtra = $form['email_xtra']; else $email_xtra=$client['email_alt'];
//if($form['credit_no'] != '') $credit_no = $form['credit_no']; else $credit_no=$client['invoice_prefix'].($client['credit_no']+1);
$credit_no = $client['invoice_prefix'].'-CN'.($client['credit_no']+1);
if($form['vat'] !== '') $vat = $form['vat']; else $vat = '0';

$credit_date = $form['credit_date'];


//identify client and invoice dates
$html_items = '';
$html_items .= '<h1>'.$client['name'].' Credit on:'.$credit_date.'</h1>';

$param['class'] = 'form-control';
  
$html_items .= '<table>'.
               '<tr><td>CREDIT NOTE N0: </td><td>'.Form::textInput('credit_no',$credit_no,$param).'</td></tr>'.
               '<tr><td>FOR: </td><td>'.Form::textAreaInput('credit_for',$client['name'],'','',$param).'</td></tr>'.
               '<tr><td>COMMENT: </td><td>'.Form::textAreaInput('credit_comment',@$comment,'','',$param).'</td></tr>'.
               '</table>';

$item_no = count($items[0])-1; //first line contains headers for pdf
$item_total = 0;
$html_items .= '<table><tr><th>Quantity</th><th width="300">Description</th><th>Unit price</th><th>Total</th></tr>';

for($i = 1; $i <= $item_no; $i++) {
  if($items[0][$i] == 0) $items[0][$i] = ''; //to stop display of zeros
  $html_items.='<tr><td>'.Form::textInput('quant_'.$i,$items[0][$i],$param).'</td>'.
               '<td>'.Form::textInput('desc_'.$i,$items[1][$i],$param).'</td>'.
               '<td>'.Form::textInput('price_'.$i,$items[2][$i],$param).'</td>'.
               '<td>'.Form::textInput('total_'.$i,$items[3][$i],$param).'</td>'.
               '</tr>';
  $item_total += (float)$items[3][$i]; 
}  
 

//display subtotals/vat/totals
$html_items .= '<tr><td colspan="2"></td><td>Subtotal</td><td><strong>'.number_format($item_total,2).'</strong></td></tr>'.
               '<tr><td colspan="2"></td><td>VAT</td><td>'.Form::textInput('vat',$vat,$param).'</td></tr>';
        
$html_items .= '</table>';
 
$html .= $html_items.'<br/><br/>'.
         '<div style="width:300px">'.
         'Email Credit Note to'.Form::textInput('email',$email,$param).'<br/>'.
         'Email COPY to'.Form::textInput('email_xtra',$email_xtra,$param).'<br/>'.
         '<input type="submit" value="Check totals and xtra items before processing" class="btn btn-primary"><br/>'.
         '</div>';
  
echo $html;          
?>
