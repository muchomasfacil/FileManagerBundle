<?php

namespace MuchoMasFacil\FileManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use MuchoMasFacil\FileManagerBundle\Util\CustomUrlSafeEncoder;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DemoController extends Controller
{

    private $render_vars = array(
        'bundle_name' => 'MuchoMasFacilFileManagerBundle',
        'controller_name' => 'Demo',
        );

    public function indexAction()
    {
        //print_r($this->container->getParameter('mucho_mas_facil_file_manager.options'));
        $url_safe_encoder = new CustomUrlSafeEncoder();

        $file_manager_params = array(
            //'load_options' => 'single_pdf',
            //'max_number_of_files' => 10,
            //'allowed_extensions' => 'jpeg',
            'on_select_callback_function' => //will receive the input_value parameter
                "
                    $('#input_name').val(input_value);
                    //$('#mmf-fm-dialog').dialog('close');
                ",
            );

        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoder->encode($file_manager_params);
        //echo $this->render_vars['url_safe_encoded_params'];
        return $this->render($this->render_vars['bundle_name'] . ':' . $this->render_vars['controller_name'] . ':' . 'index.html.twig', $this->render_vars);
    }
}
