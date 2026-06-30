<?php
require DIR_SYSTEM . 'library/vendor/huntbee-webp/autoload.php';
use WebPConvert\WebPConvert;

class ControllerExtensionModuleHbWebp extends Controller {
    public function cron() {
        $this->load->model('extension/module/hb_webp');
        $this->model_extension_module_hb_webp->addlog('**CRON MODE STARTED**');

        $authkey = $this->request->get['authkey'] ?? '';

        if (!$this->authenticate($authkey)) {
            die('AUTHORIZATION FAILED');
        }

        $paths = $this->model_extension_module_hb_webp->getUncompressedImages();
        $delete_ids = array();
        $processed = 0;
        $skipped = 0;
        $errors = 0;

		if (!empty($paths)) {
			foreach ($paths as $path) {
				$id = $path['id'];
				$webp_source = $path['path'];

				if (!is_file($webp_source)) {
					$delete_ids[] = $id;
					$skipped++;
					continue;
				}

				list($width_orig, $height_orig, $image_type) = @getimagesize($webp_source);

				if (in_array($image_type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG))) {
					$webp_destination = $this->getWebpDestinationPath($webp_source);

					if (is_file($webp_destination) && filemtime($webp_destination) >= filemtime($webp_source)) {
						$delete_ids[] = $id;
						$skipped++;
						continue;
					}

					if (!is_dir(dirname($webp_destination))) {
						@mkdir(dirname($webp_destination), 0777, true);
					}

					try {
						WebPConvert::convert($webp_source, $webp_destination, array());
						$delete_ids[] = $id;
						$processed++;
					} catch (Exception $e) {
						$errors++;
						$this->model_extension_module_hb_webp->addlog('Error processing image '.$webp_source.': '.$e->getMessage());
					}
				} else {
					$delete_ids[] = $id;
					$skipped++;
				}
			}

			if ($delete_ids) {
				$this->model_extension_module_hb_webp->deleteIds($delete_ids);
			}

			$this->model_extension_module_hb_webp->addlog('Processed: '.$processed.', skipped: '.$skipped.', errors: '.$errors);
		}else{
			$this->model_extension_module_hb_webp->getCachedImages();
		}

        $this->model_extension_module_hb_webp->addlog('**CRON MODE COMPLETED**');
        die('CRON MODE COMPLETED');
    }

    private function authenticate($authkey) {
        $actual_authkey = $this->config->get('hb_webp_cron_key');
        return !empty($authkey) && $authkey === $actual_authkey;
    }

    private function getWebpDestinationPath($sourcePath) {
        $basePath = str_replace(['\\cache\\', '/cache/'], [DIRECTORY_SEPARATOR . 'webp' . DIRECTORY_SEPARATOR, '/webp/'], $sourcePath);
        $withoutExtension = substr($basePath, 0, strrpos($basePath, '.'));
        return $withoutExtension . '.webp';
    }
}
