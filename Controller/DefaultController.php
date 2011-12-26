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

        $params = $options['default'];

        if ((isset($custom_params['load_options'])) && (isset($options[$custom_params['load_options']]))) {
            $params = array_merge($params, $options[$custom_params['load_options']]);
        }
        return array_replace_recursive($params, $custom_params);
    }

    private function getCkeditorSpecificParams($url_safe_encoded_params, $request)
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
        $this->render_vars['url_safe_encoded_params'] = $this->getCkeditorSpecificParams($url_safe_encoded_params, $request);
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function indexLayoutAction($url_safe_encoded_params, $layout_to_use)
    {
        $request = $this->getRequest();
        if (!$layout_to_use) {
          $layout_to_use = $this->render_vars['bundle_name'].'::layout.html.twig';
        }
        $this->render_vars['layout_to_use'] = $layout_to_use;
        $this->render_vars['url_safe_encoded_params'] = $this->getCkeditorSpecificParams($url_safe_encoded_params, $request);
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }

    public function uploadFormAction($url_safe_encoded_params)
    {
        $this->render_vars['params'] = $this->initialiceParams($url_safe_encoded_params);
        $this->render_vars['url_safe_encoded_params'] = $url_safe_encoded_params;
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__), $this->render_vars);
    }


    /*private function uploadReturn($return)
    {
        $response = new Response(json_encode($return));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }*/

    public function uploadAction()
    {
        //TODO
        //make all checks:

        //**upload_path_after_document_root: /uploads/
        //**create_path_if_not_exist: true
        //replace_old_file: false
        //max_number_of_files: 10  # ~ means any number of files
        //on_select_callback_function:  ~
        //size_limit:  204800 #in bytes
        //allowed_roles:  ROLE_USER, ROLE_ADMIN # ~means any user
        //allowed_extensions:  "'jpg', 'jpeg', 'png', 'gif'"  # ~ means any extension

        $logger = $this->get('logger');
        //$logger->info(print_r($params, true));
                //$logger->info(print_r($params, true));
        //$logger->info(print_r($request->files->all(), true));
        //$logger->err('An error occurred');

        //http://miconsultingpartners/app_dev.php/mmf_fm_upload.json?url_safe_encoded_params=

        $request = $this->get('request');

        $url_safe_encoded_params = $this->getRequest()->get('url_safe_encoded_params');
        //die(var_dump($url_safe_encoded_params, true));
        $params = $this->initialiceParams($url_safe_encoded_params);
        $full_target_path = $this->document_root . $params['upload_path_after_document_root'];

        if (!is_writable($full_target_path)){
            if ($params['create_path_if_not_exist']){
                if(!$this->mkdir_recursive($full_target_path)) {
                    $this->render_vars['return'] = array(
                        'jsonrpc' => '2.0',
                        'result' => 'error',
                        'code' => '104',
                        'message' => $this->trans('Upload not accepted'),
                        'details' => $this->trans('Could not create target path'),
                    );
                    return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
                }
            }
        }

        if (!is_writable($full_target_path)){
            $this->render_vars['return'] = array(
                'jsonrpc' => '2.0',
                'result' => 'error',
                'code' => '105',
                'message' => $this->trans('Upload not accepted'),
                'details' => $this->trans('Upload directory is not writable'),
            );
            return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
        }

//------------------------------------------------------------------------------
        // Get parameters
        $chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
        $chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
        $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

        // Clean the fileName for security reasons
        $fileName = preg_replace('/[^\w\._]+/', '', $fileName);

        // Make sure the fileName is unique but only if chunking is disabled
        if ($chunks < 2 && file_exists($full_target_path . DIRECTORY_SEPARATOR . $fileName)) {
	        $ext = strrpos($fileName, '.');
	        $fileName_a = substr($fileName, 0, $ext);
	        $fileName_b = substr($fileName, $ext);

	        $count = 1;
	        while (file_exists($full_target_path . DIRECTORY_SEPARATOR . $fileName_a . '_' . $count . $fileName_b))
		        $count++;

	        $fileName = $fileName_a . '_' . $count . $fileName_b;
        }

        $contentType = '';
        // Look for the content type header
        if (isset($_SERVER["HTTP_CONTENT_TYPE"])) {
	        $contentType = $_SERVER["HTTP_CONTENT_TYPE"];
        }
        if (isset($_SERVER["CONTENT_TYPE"])) {
	        $contentType = $_SERVER["CONTENT_TYPE"];
        }
        // Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
        if (strpos($contentType, "multipart") !== false) {
	        if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
		        // Open temp file
		        $out = fopen($full_target_path . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
		        if ($out) {
			        // Read binary input stream and append it to temp file
			        $in = fopen($_FILES['file']['tmp_name'], "rb");

			        if ($in) {
				        while ($buff = fread($in, 4096))
					        fwrite($out, $buff);
			        }
			        else {
			            $this->render_vars['return'] = array(
                            'jsonrpc' => '2.0',
                            'result' => 'error',
                            'code' => '101',
                            'message' => $this->trans('Upload not accepted'),
                            'details' => $this->trans('Failed to open input stream'),
                        );
                        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
				    }
			        fclose($in);
			        fclose($out);
			        @unlink($_FILES['file']['tmp_name']);
		        }
		        else {
    		        $this->render_vars['return'] = array(
                        'jsonrpc' => '2.0',
                        'result' => 'error',
                        'code' => '102',
                        'message' => $this->trans('Upload not accepted'),
                        'details' => $this->trans('Failed to open input stream'),
                    );
                    return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
			    }
	        }
	        else {
                $this->render_vars['return'] = array(
                    'jsonrpc' => '2.0',
                    'result' => 'error',
                    'code' => '103',
                    'message' => $this->trans('Upload not accepted'),
                    'details' => $this->trans('Failed to move uploaded file'),
                );
                return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
		    }
        }
        else {
	        // Open temp file
	        $out = fopen($full_target_path . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
	        if ($out) {
		        // Read binary input stream and append it to temp file
		        $in = fopen("php://input", "rb");

		        if ($in) {
			        while ($buff = fread($in, 4096)) {
				        fwrite($out, $buff);
				    }
		        }
		        else {
    		        $this->render_vars['return'] = array(
                        'jsonrpc' => '2.0',
                        'result' => 'error',
                        'code' => '101',
                        'message' => $this->trans('Upload not accepted'),
                        'details' => $this->trans('Failed to open input stream'),
                    );
                    return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
                }
		        fclose($in);
		        fclose($out);
	        }
	        else {
		        $this->render_vars['return'] = array(
                    'jsonrpc' => '2.0',
                    'result' => 'error',
                    'code' => '102',
                    'message' => $this->trans('Upload not accepted'),
                    'details' => $this->trans('Failed to open input stream'),
                );
                return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
		    }
        }
//------------------------------------------------------------------------------

        $this->render_vars['return'] = array(
            'jsonrpc' => '2.0',
            'result' => 'success',
            'code' => '101',
            'message' => $this->trans('Upload successfull'),
            'details' => $this->trans('All files uploaded'),
        );
        return $this->render($this->getTemplateNameByDefaults(__FUNCTION__, 'json'), $this->render_vars);
    }

    private function mkdir_recursive($pathname, $mode = 0777)
    {
        is_dir(dirname($pathname)) || self::mkdir_recursive(dirname($pathname), $mode);
        if (is_dir($pathname)) {
          return true;
        }
        else {
          @mkdir($pathname, $mode);
          return @chmod($pathname, $mode);
        }
    }
    
    private static function rmdir_recursive($filepath)
    {
        if (is_dir($filepath) && !is_link($filepath)) {
            if ($dh = opendir($filepath)) {
                while (($sf = readdir($dh)) !== false) {
                    if ($sf == '.' || $sf == '..') {
                        continue;
                    }
                    if (!self::rmdir_recursive($filepath.'/'.$sf)) {
                        //throw new Exception($filepath.'/'.$sf.' could not be deleted.');
                        return false;
                    }
                }
                closedir($dh);
            }
            return rmdir($filepath);
        }
        return unlink($filepath);
    }

    public function listAction($url_safe_encoded_params)
    {

        // TODO PREVIEW CON PLUGIN DE MALSUP
        $this->render_vars['params'] = $this->initialiceParams($url_safe_encoded_params);
        //die(print_r($this->render_vars['params']));
        $in = $this->document_root . $this->render_vars['params']['upload_path_after_document_root'];

        if (!$this->render_vars['params']['allowed_extensions']) {
            $this->render_vars['params']['allowed_extensions'] = '*';
        }
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

        $this->render_vars['all_files'] = array();
        if (isset($this->render_vars['files'])) {
            foreach($this->render_vars['files'] as $file) {
                $this->render_vars['all_files'][] = $this->render_vars['params']['upload_path_after_document_root'] . $file->getBaseName();
            }
        }

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
