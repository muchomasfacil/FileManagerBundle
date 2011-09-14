<?php

namespace MuchoMasFacil\FileManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class StaticController extends Controller
{
    private $render_vars = array();

    function __construct()
    {
        $this->render_vars['bundle_name'] = 'MuchoMasFacilFileManagerBundle';
        $this->render_vars['controller_name'] = str_replace('Controller', '', str_replace(__NAMESPACE__.'\\', '', __CLASS__));
    }

    public function renderAction($template, $_format)
    {
        return $this->render($this->render_vars['bundle_name'] . ':Static/' . ucfirst($_format) . ':' . $template . '.' . $_format . '.twig', $this->render_vars);
    }
}

