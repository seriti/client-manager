<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;

use App\Clients\InvoiceWizard;

use Seriti\Tools\Template;
use Seriti\Tools\BASE_TEMPLATE;

class InvoiceWizardController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $cache = $this->container->cache;
        $user_specific = true;
        $cache->setCache('Invoice_wizard',$user_specific);

        $wizard_template = new Template(BASE_TEMPLATE);
        
        $wizard = new InvoiceWizard($this->container->mysql,$this->container,$cache,$wizard_template);
        
        $wizard->setup();
        $html = $wizard->process();

        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'Client Invoice wizard ';
        //$template['javascript'] = $dashboard->getJavascript();

        return $this->container->view->render($response,'admin.php',$template);
    }
}