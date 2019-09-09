<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;

use App\Clients\PaymentWizard;

class PaymentWizardController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $wizard = new PaymentWizard($this->container->mysql,$this->container);
        
        $html = $wizard->process();

        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'Client Payment matching wizard ';
        //$template['javascript'] = $dashboard->getJavascript();

        return $this->container->view->render($response,'admin.php',$template);
    }
}