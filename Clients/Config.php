<?php 
namespace App\Clients;

use Psr\Container\ContainerInterface;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\SITE_NAME;

class Config
{
    
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        
        $module = $this->container->config->get('module','clients');
        $ledger = $this->container->config->get('module','ledger');
        $menu = $this->container->menu;
        $user = $this->container->user;
        
        define('TABLE_PREFIX',$module['table_prefix']);
        define('TABLE_PREFIX_GL',$ledger['table_prefix']);
        if(!defined('INVOICE_XTRA_ITEMS')) define('INVOICE_XTRA_ITEMS',5);
        if(!defined('INVOICE_TIME_SHEETS')) define('INVOICE_TIME_SHEETS',true);
        
        define('HOURLY_RATE',610);
        define('VAT_RATE',0.15);
        define('CURRENCY','ZAR');

        define('ACCESS',['timesheets'=>'USER']);

        define('MODULE_ID','CLIENTS');
        define('MODULE_LOGO','<img src="'.BASE_URL.'images/clients40.png"> ');
        //define('MODULE_NAV',seriti_custom::get_system_default($conn,'MODULE_NAV','TABS'));
        define('MODULE_PAGE',URL_CLEAN_LAST);  

        $page_active = MODULE_PAGE;
        if($page_active === 'credit_wizard') $page_active = 'credit';
        if($page_active === 'invoice_wizard') $page_active = 'invoice';
        
        //only show module sub menu for users with normal non-route based access
        if($user->getRouteAccess() === false) {
            $submenu_html = $menu->buildNav($module['route_list'],$page_active);
            $this->container->view->addAttribute('sub_menu',$submenu_html);
        }    
        

        $response = $next($request, $response);
        
        return $response;
    }
}