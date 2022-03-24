<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\CreditFile;

class CreditFileController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table = TABLE_PREFIX.'files'; 
        $upload = new CreditFile($this->container->mysql,$this->container,$table);

        $upload->setup();
        $html = $upload->processUpload();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Credit Note files';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}