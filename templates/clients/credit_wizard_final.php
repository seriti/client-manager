<?php
use Seriti\Tools\Html;
?>

<div >
  <h2>You have successfully completed Credit Note wizard process! </h2>
  <a href="credit_wizard"><button class="btn btn-primary">Restart wizard</button></a>
  <br/><br/>
  
  <?php
  
  $html = '';
  $client = $data['client'];
  //$items=$data['items'];
  $item_total = $data['item_total'];

  $email = $form['email'];
  $email_xtra = $form['email_xtra'];
  $credit_date = $form['credit_date'];

  $html .= '<h1>'.$client['name'].' Credit on:'.$credit_date.'</h1>';
  
  
  $html .= '<table>'.
           '<tr><td>Credit No.: </td><td><strong>'.$form['credit_no'].'</strong></td></tr>'.
           '<tr><td>For: </td><td><strong>'.$form['credit_for'].'</strong></td></tr>'.
           '<tr><td>Comment: </td><td><strong>'.nl2br($form['credit_comment']).'</strong></td></tr>';
         
  //display subtotals/vat/totals
  $total = $item_total+$form['vat'];
  $html .= '<tr><td>Subtotal: </td><td><strong>'.number_format($item_total,2).'</strong></td></tr>'.
           '<tr><td>VAT: </td><td><strong>'.number_format($form['vat'],2).'</td></tr>'.
           '<tr><td>TOTAL: </td><td><strong>'.number_format($total,2).'</td></tr>';
         
  $html .= '</table>';
  
  $html .= '<h2>Emailed to ['.$email.'] ';
  if($email_xtra!='') $html .= 'and to ['.$email_xtra.'] ';
  $html .= '</h2>';
  
  $html .= '<h2>View Credit Note PDF and any supporting documents:</h2>'.$data['files'];
  
  $html .= '<h2>You can view details on <a href="credit">Credit notes management page</a> and email credit note to any address.</h2>';
  
  echo $html;
  
  ?>
</div>   
