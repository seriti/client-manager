<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\TaskFile;

class TaskFileController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table = TABLE_PREFIX.'files'; 
        $upload = new TaskFile($this->container->mysql,$this->container,$table);

        $upload->setup();
        $html = $upload->processUpload();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Task Files';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}