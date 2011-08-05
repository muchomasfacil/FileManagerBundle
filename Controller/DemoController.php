<?php

namespace MuchoMasFacil\FileManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use MuchoMasFacil\FileManagerBundle\Util\CustomUrlSafeEncoder;


class DemoController extends Controller
{

    private $render_vars = array(
        'bundle_name' => 'MuchoMasFacilFileManagerBundle',
        'controller_name' => 'Demo',
        );

    public function indexAction()
    {
        $url_safe_encoder = new CustomUrlSafeEncoder();
        $file_manager_params = array(
            'maxNumberOfFiles' => 10,
            'allowedExtensions' => array('jpeg', 'jpg', 'gif', 'png'),
            'onSelectFunctionJsActions' => //will receive two params: file_name, path_after_upload_absolute_path
                "
                    $('#input_name').val(path_after_upload_absolute_path + file_name);
                    $('#mmf-fm-dialog').dialog('close');
                ",
            ); //this arrays can be predefined in an ini or yml config path with defaults for images, ckeditor img, ckeditor file, etc...
        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoder->encode($file_manager_params);
        //echo $this->render_vars['url_safe_encoded_params'];
        return $this->render($this->render_vars['bundle_name'] . ':' . $this->render_vars['controller_name'] . ':' . 'index.html.twig', $this->render_vars);
    }
}

