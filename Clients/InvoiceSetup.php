<?php
namespace App\Clients;

use Seriti\Tools\SetupModule;

use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;

class InvoiceSetup extends SetupModule
{
    public function setup() {
        //upload_dir is NOT publically accessible
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        $this->setUpload($upload_dir,'PRIVATE');

        $param = [];
        $param['info'] = 'Specify email footer text / contact details';
        $param['rows'] = 5;
        $param['value'] = '';
        $this->addDefault('TEXTAREA','EMAIL_FOOTER','Email footer',$param);

        $param = [];
        $param['info'] = 'Specify invoice footer text / bank account details / any info you require to be added.';
        $param['rows'] = 10;
        $param['value'] = '';
        $this->addDefault('TEXTAREA','INVOICE_FOOTER','Invoice PDF footer',$param);

        $param = [];
        $param['info'] = 'Select the image you would like to use as an invoice signature (max 50KB)';
        $param['max_size'] = 50000;
        $param['value'] = 'images/sample_sig.jpeg';
        $this->addDefault('IMAGE','INVOICE_SIGN','Invoice signature',$param);

        $param = [];
        $param['info'] = 'Specify the name and title you wish to have below signature.';
        $param['value'] = 'Chief Executive Officer';
        $this->addDefault('TEXT','INVOICE_SIG_TXT','Invoice signature subtext',$param);
    }    
}
