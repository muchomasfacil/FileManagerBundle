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

    function __construct()
    {
        $this->url_safe_encoder =  new CustomUrlSafeEncoder();
        $this->render_vars['bundle_name'] = 'MuchoMasFacilFileManagerBundle';
        $this->render_vars['controller_name'] = str_replace('Controller', '', str_replace(__NAMESPACE__.'\\', '', __CLASS__));
        $this->render_vars['params'] = array(
            'uploadAbsolutePath' => $_SERVER['DOCUMENT_ROOT'].'/uploads/', //substitute first part from ini_configuration
            'createPathIfNotExist' => true,
            'replaceOldFile' => false,
            'maxNumberOfFiles' => null, //any number of files
            //'thumnails' => null, use AvalancheImagineBundle instead
            //possible select params
            'onSelectCallbackFunction' => null,
            //'CKEditorFuncNum' => null,
            //'CKEditor' => null,
            //'langCode' => null,
            'onSelectRemoveFromUploadAbsolutePath' => $_SERVER['DOCUMENT_ROOT'],
            'allowedRoles' => array('ROLE_USER', 'ROLE_ADMIN'),
            //now for the specific upload plugin (may be used by the server side also)
            'allowedExtensions' => null, //any extension
            'sizeLimit' => 200 * 1024,
            'minSizeLimit' => null,
            'maxConnections' => 3,
            );
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

    public function indexAction($url_safe_encoded_params)
    {
        $params = array_replace_recursive($this->render_vars['params'], $this->url_safe_encoder->decode($url_safe_encoded_params));
        // TODO estos parametros deberían ser configurables...
        foreach ( array('CKEditorFuncNum', 'CKEditor', 'langCode' ) as $possible_param){
            if ($this->getRequest()->get($possible_param)){
                $params[$possible_param] = $this->getRequest()->get($possible_param);
                $recode_params = true;
            }
        }
        if (isset($recode_params)){
            $url_safe_encoded_params = $this->url_safe_encoder->encode($params);
        }

        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoded_params;
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function uploadFormAction($url_safe_encoded_params)
    {

        $this->render_vars['params'] = array_replace_recursive($this->render_vars['params'], $this->url_safe_encoder->decode($url_safe_encoded_params));
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
        $url_safe_encoded_params = $this->getRequest()->get('url_safe_encoded_params');
        $params = array_replace_recursive($this->render_vars['params'], $this->url_safe_encoder->decode($url_safe_encoded_params));

        if (!is_writable($params['uploadAbsolutePath'])){
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

        if ($size > $params['sizeLimit']) {
            return $this->uploadReturn(array('error' => $this->trans('File is too large')));
        }

        $pathinfo = pathinfo($file->getName());
        $filename = $pathinfo['filename'];
        //$filename = md5(uniqid());
        $ext = $pathinfo['extension'];

        if($params['allowedExtensions'] && !in_array(strtolower($ext), $params['allowedExtensions'])){
            $extensions = implode(', ', $params['allowedExtensions']);
            return $this->uploadReturn(array('error' => $this->trans("File has an invalid extension, it should be one of '%extensions%'.", array('%extensions%' => $extensions))));
        }

        // TODO: normalize file name

        if(!$params['replaceOldFile']){
            /// don't overwrite previous files that were uploaded
            while (file_exists($params['uploadAbsolutePath'] . $filename . '.' . $ext)) {
                $filename .= rand(10, 99);
            }
        }

        if ($file->save($params['uploadAbsolutePath'] . $filename . '.' . $ext)){
            return $this->uploadReturn(array('success'=>true));
        } else {
            return $this->uploadReturn(array('error'=> $this->trans('Could not save uploaded file.') .
                $this->trans('The upload was cancelled, or server error encountered')));
        }
    }

    public function listAction($url_safe_encoded_params)
    {
        $this->render_vars['params'] = array_replace_recursive($this->render_vars['params'], $this->url_safe_encoder->decode($url_safe_encoded_params));

        $in = $this->render_vars['params']['uploadAbsolutePath'];
        //TODO: if not exist create...

        $names = $this->render_vars['params']['allowedExtensions'];
        array_walk($names, function(&$val) {$val = '*.'.$val;});
        //$notNames = null;

        $finder = new Finder();
        $finder->files()->depth('==0');
        if (isset($names) && is_array($names)) {
            foreach ($names as $name){
                $finder->name(strtolower($name));
                $finder->name(strtoupper($name));
            }
        }
        //if (isset($notNames) && is_array($notNames)) {
        //    foreach ($notNames as $name){
        //        $finder->notName(strtolower($name));
        //        $finder->notName(strtoupper($name));
        //    }
        //}

        //TODO tema de sortby


        $this->render_vars['files'] = $finder->in($in);
        $this->render_vars['path_after_upload_absolute_path'] = str_replace($this->render_vars['params']['onSelectRemoveFromUploadAbsolutePath'], '', $this->render_vars['params']['uploadAbsolutePath']);
        $this->render_vars['params'] = $this->render_vars['params'];
        $this->render_vars['count_files'] = iterator_count($this->render_vars['files']);
        $this->render_vars['url_safe_encoder'] = $this->url_safe_encoder;
        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoded_params;
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function deleteAction($url_safe_encoded_params, $url_safe_encoded_files_to_delete)
    {
        $params = array_replace_recursive($this->render_vars['params'], $this->url_safe_encoder->decode($url_safe_encoded_params));
        $files_to_delete = $this->url_safe_encoder->decode($url_safe_encoded_files_to_delete);
        foreach ($files_to_delete as $file){
            @unlink($params['uploadAbsolutePath'].$file);
            //TODO pass error messages
        }
        //return new Response($params['uploadAbsolutePath'].print_r($files_to_delete, true));
        return $this->forward(
            $this->render_vars['bundle_name'] . ':' . $this->render_vars['controller_name'] . ':' . 'list',
            array('url_safe_encoded_params'  => $url_safe_encoded_params)
        );
    }
}

