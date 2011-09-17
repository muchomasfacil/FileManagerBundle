<?php

namespace MuchoMasFacil\FileManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;
use MuchoMasFacil\FileManagerBundle\Util\CustomUrlSafeEncoder;
use MuchoMasFacil\FileManagerBundle\Util\qqUploadedFileXhr;
use MuchoMasFacil\FileManagerBundle\Util\qqUploadedFileForm;

class DefaultController extends Controller
{
    private $render_vars = array();

    private $url_safe_encoder;

    private $document_root;

    function __construct()
    {
        $this->document_root = $_SERVER['DOCUMENT_ROOT'];
        $this->url_safe_encoder =  new CustomUrlSafeEncoder();
        $this->render_vars['bundle_name'] = 'MuchoMasFacilFileManagerBundle';
        $this->render_vars['controller_name'] = str_replace('Controller', '', str_replace(__NAMESPACE__.'\\', '', __CLASS__));
        $this->render_vars['params'] = array();
    }

    private function getTemplateNameByDefaults($action_name, $template_format = 'html')
    {
      $this->render_vars['action_name'] = str_replace('Action', '', $action_name);
      return $this->render_vars['bundle_name'] . ':' . $this->render_vars['controller_name'] . ':' . $this->render_vars['action_name'] . '.'.$template_format.'.twig';
    }

    private function trans($translatable, $params = array())
    {
      return $this->get('translator')->trans($translatable, $params, strtolower($this->render_vars['bundle_name']));
    }
//------------------------------------------------------------------------------
// From now on action classes

    private function initialiceParams($url_safe_encoded_params)
    {
        $custom_params = $this->url_safe_encoder->decode($url_safe_encoded_params);
        $options = $this->container->getParameter('mucho_mas_facil_file_manager.options');

        $params = $options['options']['default'];

        if ((isset($custom_params['load_options'])) && (isset($options['options'][$custom_params['load_options']]))) {
            $params = array_merge($params, $options['options'][$custom_params['load_options']]);
        }
        return array_replace_recursive($params, $custom_params);
    }

    public function ckeditorSpecific($url_safe_encoded_params, $request)
    {
        $params = $this->url_safe_encoder->decode($url_safe_encoded_params);
        // TODO estos parametros deberían ser configurables...
        foreach ( array('CKEditorFuncNum', 'CKEditor', 'langCode' ) as $possible_param){
            if ($this->getRequest()->get($possible_param)){
                $params[$possible_param] = $request->get($possible_param);
                $recode_params = true;
            }
        }
        if (isset($recode_params)){
            $url_safe_encoded_params = $this->url_safe_encoder->encode($params);
        }
        return $url_safe_encoded_params;
    }

    public function indexAction($url_safe_encoded_params)
    {
        $request = $this->getRequest();
        $this->render_vars['url_safe_encoded_params'] = $this->ckeditorSpecific($url_safe_encoded_params, $request);
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function indexLayoutAction($url_safe_encoded_params)
    {
        $request = $this->getRequest();
        $this->render_vars['url_safe_encoded_params'] = $this->ckeditorSpecific($url_safe_encoded_params, $request);
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function uploadFormAction($url_safe_encoded_params)
    {
        $this->render_vars['params'] = $this->initialiceParams($url_safe_encoded_params);
        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoded_params;
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }


    private function uploadReturn($return)
    {
        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function uploadAction()
    {
        $logger = $this->get('logger');
        $logger->info('Alvaro');
        //$logger->err('An error occurred');
        $url_safe_encoded_params = $this->getRequest()->get('url_safe_encoded_params');
        $params = $this->initialiceParams($url_safe_encoded_params);

        $full_dir_path = $this->document_root . $params['upload_path_after_document_root'];

        if (!is_writable($full_dir_path)){
            return $this->uploadReturn(array('error' => $this->trans("Server error. Upload directory is not writable.")));
            // TODO: tema de createPathIfNotExist
        }


        // TODO: test iframe
        if (isset($_GET['qqfile'])) {
            $file = new qqUploadedFileXhr();
        } elseif (isset($_FILES['qqfile'])) {
            $file = new qqUploadedFileForm();
        } else {
            $file = false;
        }

        if (!$file){
            return $this->uploadReturn(array('error' => $this->trans('No files were uploaded.')));
        }

        $size = $file->getSize();

        if ($size == 0) {
            return $this->uploadReturn(array('error' => $this->trans('File is empty')));
        }

        // TODO : tema de minSizeLimit

        if ($size > $params['size_limit']) {
            return $this->uploadReturn(array('error' => $this->trans('File is too large')));
        }

        $pathinfo = pathinfo($file->getName());
        $filename = $pathinfo['filename'];
        //$filename = md5(uniqid());
        $ext = $pathinfo['extension'];

        if($params['allowed_extensions']) {
            $names = $params['allowed_extensions'];
            $names = str_replace("'", '', $names);
            $names = explode(',', $names);
            array_walk($names, function(&$val) {$val = trim($val);});
            if (!in_array(strtolower($ext), $names )) {
                $extensions = implode(', ', $params['allowed_extensions']);
                return $this->uploadReturn(array('error' => $this->trans("File has an invalid extension, it should be one of '%extensions%'.", array('%extensions%' => $extensions))));
            }
        }

        // TODO: normalize file name

        if(!$params['replace_old_file']){
            /// don't overwrite previous files that were uploaded
            while (file_exists($full_dir_path . $filename . '.' . $ext)) {
                $filename .= rand(10, 99);
            }
        }

        if ($file->save($full_dir_path . $filename . '.' . $ext)){
            return $this->uploadReturn(array('success'=>true));
        } else {
            return $this->uploadReturn(array('error'=> $this->trans('Could not save uploaded file.') .
                $this->trans('The upload was cancelled, or server error encountered')));
        }
    }

    public function listAction($url_safe_encoded_params)
    {
        $this->render_vars['params'] = $this->initialiceParams($url_safe_encoded_params);
        //die(print_r($this->render_vars['params']));
        $in = $this->document_root . $this->render_vars['params']['upload_path_after_document_root'];
        //TODO: if not exist create...

        $names = $this->render_vars['params']['allowed_extensions'];
        $names = str_replace("'", '', $names);
        $names = explode(',', $names);

        array_walk($names, function(&$val) {$val = '*.'.trim($val);});
        $finder = new Finder();
        $finder->files()->depth('==0');
        if (isset($names) && is_array($names)) {
            foreach ($names as $name){
                $finder->name(strtolower($name));
                $finder->name(strtoupper($name));
            }
        }
        if (is_dir($in)) {
            $this->render_vars['files'] = $finder->in($in);
            $this->render_vars['count_files'] = iterator_count($this->render_vars['files']);
        }
        else {
            $this->render_vars['count_files'] = 0;
        }

        //TODO tema de sortby

        $this->render_vars['params'] = $this->render_vars['params'];
        $this->render_vars['url_safe_encoder'] = $this->url_safe_encoder;
        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoded_params;
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function deleteAction($url_safe_encoded_params, $url_safe_encoded_files_to_delete)
    {
        $params  = $this->initialiceParams($url_safe_encoded_params);
        $files_to_delete = $this->url_safe_encoder->decode($url_safe_encoded_files_to_delete);
        foreach ($files_to_delete as $file){
            @unlink($this->document_root . $params['upload_path_after_document_root'].$file);
            //TODO pass error messages
        }
        //return new Response($params['uploadAbsolutePath'].print_r($files_to_delete, true));
        return $this->forward(
            $this->render_vars['bundle_name'] . ':' . $this->render_vars['controller_name'] . ':' . 'list',
            array('url_safe_encoded_params'  => $url_safe_encoded_params)
        );
    }
}

