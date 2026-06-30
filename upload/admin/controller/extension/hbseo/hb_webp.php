<?php
require DIR_SYSTEM.'library/vendor/huntbee-webp/autoload.php';
use WebPConvert\WebPConvert;

class ControllerExtensionHbseoHbWebp extends Controller {
	protected $registry;
	private $error = array();

	public function __construct($registry) {
		$this->registry = $registry;
		if (version_compare(VERSION,'3.0.0.0','>=' )) {
			$this->hb_template_folder 		= 'oc3';
			$this->hb_extension_base 		= 'marketplace/extension';
			$this->hb_token_name 			= 'user_token';
			$this->hb_template_extension 	= '';
			$this->hb_extension_route 		= 'extension/hbseo';
		}else if (version_compare(VERSION,'2.2.0.0','<=' )) {
			$this->hb_template_folder 		= 'oc2';
			$this->hb_extension_base 		= 'extension/hbseo';
			$this->hb_token_name 			= 'token';
			$this->hb_template_extension 	= '.tpl';
			$this->hb_extension_route 		= 'hbseo';
		}else{
			$this->hb_template_folder 		= 'oc2';
			$this->hb_extension_base 		= 'extension/extension';
			$this->hb_token_name 			= 'token';
			$this->hb_template_extension 	= '';
			$this->hb_extension_route 		= 'extension/hbseo';
		}

		$this->hb_extension_version	= '3.2.0';
		$this->doc_link = 'https://www.huntbee.com/resources/docs/opencart-webp-compression/';

		$this->load->model('extension/hbseo/hb_webp');
		$this->load->language($this->hb_extension_route.'/hb_webp');
	}

	public function index() {
		$data['extension_version'] = $this->hb_extension_version;;

		if (isset($this->request->get['store_id'])){
			$data['store_id'] = (int)$this->request->get['store_id'];
		}else{
			$data['store_id'] = 0;
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();

		//Save the settings if the user has submitted the admin form (ie if someone has pressed save).
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('hb_webp', $this->request->post, $this->request->get['store_id']);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link($this->hb_extension_route.'/hb_webp', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name].'&store_id='.$data['store_id'], true));
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$text_strings = array(
				'heading_title','text_extension',
				'text_loading','button_save','button_cancel'
		);

		foreach ($text_strings as $text) {
			$data[$text] = $this->language->get($text);
		}

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

  		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name], true)
   		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link($this->hb_extension_base, $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name] . '&type=hbseo', true)
		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link($this->hb_extension_route.'/hb_webp', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name].'&store_id='.$data['store_id'], true)
   		);

		$data['action'] = $this->url->link($this->hb_extension_route.'/hb_webp', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name].'&store_id='.$data['store_id'], true);

		$data['cancel'] = $this->url->link($this->hb_extension_base, $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name] . '&type=hbseo', true);
		$data['clear']	= $this->url->link('extension/hbseo/hb_webp/clear_logs', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name], true);
		$data['doc_link']	= $this->doc_link;

		$data[$this->hb_token_name] = $this->session->data[$this->hb_token_name];
		$data['base_route'] = $this->hb_extension_route;

		$store_info = $this->model_setting_setting->getSetting('hb_webp', $data['store_id']);

		$data['hb_webp_cron_limit'] 	= isset($store_info['hb_webp_cron_limit']) ? $store_info['hb_webp_cron_limit'] : 10;
		$data['hb_webp_cron_key'] 		= isset($store_info['hb_webp_cron_key']) ? $store_info['hb_webp_cron_key'] : md5(rand());

		$data['hb_webp_cron'] = 'wget --quiet --delete-after "'.HTTPS_CATALOG.'index.php?route=extension/module/hb_webp/cron&authkey='.$data['hb_webp_cron_key'].'"';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/hbseo/'.$this->hb_template_folder.'/hb_webp'.$this->hb_template_extension, $data));
	}

	public function dashboard() {
		$data[$this->hb_token_name] = $this->session->data[$this->hb_token_name];
		$data['base_route'] = $this->hb_extension_route;

		$data['files'] = array();
		$data['files_limited'] = false;
		$data['gauge'] = 0;
		$image_cache_folder = DIR_IMAGE.'cache/';

		$data['image_cache_directory'] = false;

		if(is_dir($image_cache_folder)) {
			$data['image_cache_directory'] = true;

			$stats = $this->getCacheStats($image_cache_folder, 100);

			$data['files'] = $stats['files'];
			$data['files_limited'] = $stats['files_limited'];
			$data['uncompress_count'] = $stats['uncompress_count'];
			$data['oc_cache_count'] = $stats['oc_cache_count'];
			$data['compress_count'] = max(0, $stats['compressible_count'] - $data['uncompress_count']);

			if ($stats['compressible_count'] <> 0) {
				$data['gauge'] = ($data['uncompress_count'] / $stats['compressible_count']) * 100;
				$data['gauge'] =  100 - (round($data['gauge']));
			}else{
				$data['gauge'] = 0;
			}
		}

		$this->response->setOutput($this->load->view('extension/hbseo/'.$this->hb_template_folder.'/hb_webp_dashboard'.$this->hb_template_extension, $data));
	}

	public function records(){
		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data = array(
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$data[$this->hb_token_name] = $this->session->data[$this->hb_token_name];
		$data['base_route'] = $this->hb_extension_route;

		$reports_total = $this->model_extension_hbseo_hb_webp->getTotalrecords();
		$records = $this->model_extension_hbseo_hb_webp->getrecords($data);

		$data['records'] = array();
		foreach ($records as $record) {
			$data['records'][] = array(
				'id'		=> $record['id'],
				'path' 		=> $record['path']
			);
		}

		$pagination 			= new Pagination();
		$pagination->total 		= $reports_total;
		$pagination->page 		= $page;
		$pagination->limit 		= $this->config->get('config_limit_admin');
		$pagination->url 		= $this->url->link($this->hb_extension_route.'/hb_webp/records', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name] . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$limit = $this->config->get('config_limit_admin');

		$data['results'] = sprintf($this->language->get('text_pagination'), ($pagination->total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($pagination->total - $limit)) ? $pagination->total : ((($page - 1) * $limit) + $limit), $pagination->total, ceil($pagination->total / $limit));

		$this->response->setOutput($this->load->view('extension/hbseo/'.$this->hb_template_folder.'/hb_webp_records'.$this->hb_template_extension, $data));
	}

	public function getcachedimages(){
		$json = array();

		$this->model_extension_hbseo_hb_webp->deleteall();

		$image_cache_folder = DIR_IMAGE.'cache/';

		if(is_dir($image_cache_folder)) {
			$total = $this->storePendingImages($image_cache_folder);

			$json['success'] = $total.' uncompressed WebP image paths logged to database!';
		}else{
			$json['success'] = 'No Image Cache folder found!';
		}

		$this->response->setOutput(json_encode($json));
	}

	public function generate() {
		error_reporting(0);

		if (!file_exists(DIR_IMAGE . 'webp')) {
			mkdir(DIR_IMAGE . 'webp', 0777, true);
		}


		if (isset($this->request->get['oc_count'])){
			$oc_count = (int)$this->request->get['oc_count'];
		}else{
			$oc_count = 0;
		}

		if (isset($this->request->get['prev_total'])){
			$prev_total = (int)$this->request->get['prev_total'];
		}else{
			$prev_total = 0;
		}

		$limit_count = $this->getBatchLimit();

		$total 		= $this->model_extension_hbseo_hb_webp->getTotalrecords();

		$paths 		= $this->model_extension_hbseo_hb_webp->allrecords($limit_count);

		if ($total > 0){
			$delete_ids = array();
			$processed = 0;
			$skipped = 0;
			$errors = 0;

			foreach ($paths as $path) {
				$id = $path['id'];
				$webp_source = $path['path'];

				if (!is_file($webp_source)) {
					$delete_ids[] = $id;
					$skipped++;
					continue;
				}

				list($width_orig, $height_orig, $image_type) = @getimagesize($webp_source);

				if (!in_array($image_type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG))) {
					$delete_ids[] = $id;
					$skipped++;
					continue;
				}

				$webp_destination = $this->getWebpDestinationPath($webp_source);

				if (is_file($webp_destination) && filemtime($webp_destination) >= filemtime($webp_source)) {
					$delete_ids[] = $id;
					$skipped++;
					continue;
				}

				if (!is_dir(dirname($webp_destination))) {
					@mkdir(dirname($webp_destination), 0777, true);
				}

				try{
					WebPConvert::convert($webp_source, $webp_destination, array());
					$delete_ids[] = $id;
					$processed++;
				}catch (Exception $e){
					$errors++;
					$this->log->write('Extension - Huntbee WebP Compression: Issue while processing (image) path '.$webp_source.'. Issue: '.$e->getMessage());
				}
			}

			if ($delete_ids) {
				$this->model_extension_hbseo_hb_webp->deleteids($delete_ids);
			}

			$remaining = $this->model_extension_hbseo_hb_webp->getTotalrecords();
			$message = $remaining.' Remaining';

			if ($processed || $skipped || $errors) {
				$message .= ' (processed: '.$processed.', skipped: '.$skipped.', errors: '.$errors.')';
			}

			if ($remaining > 0 && $remaining == $total && $remaining == $prev_total) {
				$json['error'] = 'Operation Stopped. Refer to your error log.';
			} elseif ($remaining > 0) {
				$json['next'] 		= str_replace('&amp;', '&', $this->url->link($this->hb_extension_route.'/hb_webp/generate', $this->hb_token_name.'=' . $this->session->data[$this->hb_token_name].'&prev_total='.$remaining.'&oc_count='.$oc_count, true));
				$json['success'] 	= '<i class="fa fa-spinner fa-pulse fa-fw"></i> '.$message;

				if ($oc_count > 0) {
					$json['percentage']	=  max(0, min(100, round((($oc_count - $remaining)/$oc_count)  * 100)));
				}
			} else {
				$json['next'] 		= '';
				$json['success'] 	= '<i class="fa fa-check-circle"></i> Completed';
				$json['over']		= true;
				$json['percentage']	= 100;
			}
		}else {
			$json['success'] 	= 'All Images Compressed / No Image path found';
			$json['over']		= true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function clearcache(){
		$image_cache_folder = DIR_IMAGE.'cache/';

		if(is_dir($image_cache_folder)) {
			$this->delete_files($image_cache_folder);
			$this->model_extension_hbseo_hb_webp->deleteall();
			$json['success'] = 'Image caches deleted successfully!';
		}else{
			$json['success'] = 'No Image Cache folder found!';
		}

		$this->response->setOutput(json_encode($json));
	}

	public function getDirContents($dir, &$results = array()) {
		$this->walkCacheFiles($dir, function($path) use (&$results) {
			if ($this->needsWebpCompression($path)) {
				$results[] = $path;
			}
		});

		return $results;
	}

	public function getoccacheimages($dir, &$results = array()) {
		$this->walkCacheFiles($dir, function($path) use (&$results) {
			if ($this->isOpenCartCacheImage($path)) {
				$results[] = $path;
			}
		});

		return $results;
	}

	private function getCacheStats($dir, $preview_limit = 100) {
		$stats = array(
			'files' => array(),
			'files_limited' => false,
			'uncompress_count' => 0,
			'oc_cache_count' => 0,
			'compressible_count' => 0
		);

		$this->walkCacheFiles($dir, function($path) use (&$stats, $preview_limit) {
			if ($this->isOpenCartCacheImage($path)) {
				$stats['oc_cache_count']++;
			}

			if ($this->isCompressibleCacheImage($path)) {
				$stats['compressible_count']++;
			}

			if ($this->needsWebpCompression($path)) {
				$stats['uncompress_count']++;

				if (count($stats['files']) < $preview_limit) {
					$stats['files'][] = $path;
				} else {
					$stats['files_limited'] = true;
				}
			}
		});

		return $stats;
	}

	private function storePendingImages($dir) {
		$total = 0;
		$batch = array();
		$batch_size = 500;

		$this->walkCacheFiles($dir, function($path) use (&$total, &$batch, $batch_size) {
			if ($this->needsWebpCompression($path)) {
				$batch[] = $path;
				$total++;

				if (count($batch) >= $batch_size) {
					$this->model_extension_hbseo_hb_webp->insertpaths($batch);
					$batch = array();
				}
			}
		});

		if ($batch) {
			$this->model_extension_hbseo_hb_webp->insertpaths($batch);
		}

		return $total;
	}

	private function walkCacheFiles($dir, $callback) {
		try {
			$directory = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
			$iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::LEAVES_ONLY);

			foreach ($iterator as $file) {
				if ($file->isFile()) {
					call_user_func($callback, $file->getPathname());
				}
			}
		} catch (Exception $e) {
			$this->log->write('Extension - Huntbee WebP Compression: Issue while scanning image cache. Issue: '.$e->getMessage());
		}
	}

	private function isOpenCartCacheImage($path) {
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		return in_array($extension, array('jpg', 'jpeg', 'png', 'gif'));
	}

	private function isCompressibleCacheImage($path) {
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		return in_array($extension, array('jpg', 'jpeg', 'png'));
	}

	private function needsWebpCompression($path) {
		if (!$this->isCompressibleCacheImage($path)) {
			return false;
		}

		$webp_destination = $this->getWebpDestinationPath($path);

		return !is_file($webp_destination) || filemtime($path) > filemtime($webp_destination);
	}

	private function getWebpDestinationPath($webp_source) {
		$webp_source_without_extension = utf8_substr($webp_source, 0, utf8_strrpos($webp_source, "."));
		$webp_source_without_extension = str_replace('/cache/','/webp/',$webp_source_without_extension);
		$webp_source_without_extension = str_replace('\\cache\\','\\webp\\',$webp_source_without_extension); //localhost windows testing

		return $webp_source_without_extension.'.webp';
	}

	private function getBatchLimit() {
		$limit = (int)$this->config->get('hb_webp_cron_limit');

		if ($limit < 1) {
			$limit = 25;
		}

		return min($limit, 100);
	}

	private function delete_files($target) {
		if(is_dir($target)){
			$files = glob($target . '*', GLOB_MARK); //GLOB_MARK adds a slash to directories returned

			foreach($files as $file){
				$this->delete_files( $file );
			}

			rmdir($target);
		} elseif(is_file($target)) {
			unlink($target);
		}
	}

	public function logs(){
		if (!file_exists(DIR_LOGS)) {
			mkdir(DIR_LOGS, 0777, true);
		}

		$file = DIR_LOGS . 'hb_webp.txt';
		if (file_exists($file)) {
			$data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
		}else{
			$data['log'] = '';
		}

		$this->response->setOutput($this->load->view('extension/hbseo/oc3/hb_webp_logs', $data));
	}

	public function clear_logs() {
		if (!$this->validate()) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} else {
			$file = DIR_LOGS . 'hb_webp.txt';

			$handle = fopen($file, 'w+');

			fclose($handle);

			$this->session->data['success'] =  $this->language->get('text_success_logs');
		}

		$this->response->redirect($this->url->link('extension/hbseo/hb_webp', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function install() {
		$this->model_extension_hbseo_hb_webp->install();
	}

	public function uninstall() {
		$this->model_extension_hbseo_hb_webp->uninstall();
	}

	public function update(){
		$this->model_extension_hbseo_hb_webp->update();
		return true;
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', $this->hb_extension_route.'/hb_webp')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}


}
?>
