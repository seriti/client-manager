<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;

use App\Clients\InvoicePaymentWizard;

class InvoicePaymentWizardController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $wizard = new InvoicePaymentWizard($this->container->mysql,$this->container);
        
        $html = $wizard->process();

        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'Invoice Payment matching wizard ';
        //$template['javascript'] = $dashboard->getJavascript();

        return $this->container->view->render($response,'admin.php',$template);
    }
}