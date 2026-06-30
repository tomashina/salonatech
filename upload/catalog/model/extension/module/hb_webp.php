<?php
class ModelExtensionModuleHbWebp extends Model {
    private $active_product_images = null;

    public function getUncompressedImages(){
        $limit = (int)$this->config->get('hb_webp_cron_limit');

        if ($limit < 1) {
            $limit = 10;
        }

        $limit = min($limit, 100);

		$sql = "SELECT `id`, `path` FROM `".DB_PREFIX."image_cache` ORDER BY `id` ASC LIMIT ".$limit;
		$query = $this->db->query($sql);
		return $query->rows;
	}

    public function getCachedImages(){
		$this->deleteAll();

		$image_cache_folder = DIR_IMAGE.'cache/';

		if(is_dir($image_cache_folder)) {
            $total = $this->storePendingImages($image_cache_folder);
			$this->addlog($total.' active product image paths logged to database!');
		}else{
            $this->addlog('No Image Cache folder found!');
		}
	}

    public function getDirContents($dir, &$results = array()) {
        $this->walkCacheFiles($dir, function($path) use (&$results) {
            if ($this->needsWebpCompression($path)) {
                $results[] = $path;
            }
        });

		return $results;
	}

    public function insertPath($path){
		$this->db->query("INSERT INTO `".DB_PREFIX."image_cache` (`path`) VALUES ('".$this->db->escape($path)."')");
	}

    public function insertPaths($paths){
        if (empty($paths)) {
            return;
        }

        $values = array();

        foreach ($paths as $path) {
            if ($path !== '') {
                $values[] = "('".$this->db->escape($path)."')";
            }
        }

        if ($values) {
            $this->db->query("INSERT INTO `".DB_PREFIX."image_cache` (`path`) VALUES ".implode(',', $values));
        }
    }

    public function deleteAll(){
		$this->db->query("TRUNCATE TABLE `".DB_PREFIX."image_cache`");
	}

    public function deleteId($id){
		$this->db->query("DELETE FROM `".DB_PREFIX."image_cache` WHERE `id` = '".(int)$id."'");
	}

    public function deleteIds($ids){
        if (empty($ids)) {
            return;
        }

        $clean_ids = array();

        foreach ($ids as $id) {
            $id = (int)$id;

            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }

        if ($clean_ids) {
            $this->db->query("DELETE FROM `".DB_PREFIX."image_cache` WHERE `id` IN (".implode(',', $clean_ids).")");
        }
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
                    $this->insertPaths($batch);
                    $batch = array();
                }
            }
        });

        if ($batch) {
            $this->insertPaths($batch);
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
            $this->addlog('Issue while scanning image cache. Issue: '.$e->getMessage());
        }
    }

    private function needsWebpCompression($path) {
        if (!$this->isCompressibleCacheImage($path)) {
            return false;
        }

        if (!$this->isActiveProductCacheImage($path)) {
            return false;
        }

        $webp_destination = $this->getWebpDestinationPath($path);

        return !is_file($webp_destination) || filemtime($path) > filemtime($webp_destination);
    }

    public function isActiveProductCacheImage($path) {
        $image = $this->getOriginalImageFromCachePath($path);

        if ($image === '') {
            return false;
        }

        $active_product_images = $this->getActiveProductImages();

        return isset($active_product_images[$image]);
    }

    private function getOriginalImageFromCachePath($path) {
        $path = str_replace('\\', '/', $path);
        $image_cache_folder = str_replace('\\', '/', DIR_IMAGE.'cache/');

        if (strpos($path, $image_cache_folder) !== 0) {
            return '';
        }

        $image = substr($path, strlen($image_cache_folder));
        $extension = pathinfo($image, PATHINFO_EXTENSION);

        if ($extension === '') {
            return '';
        }

        return preg_replace('/-\d+x\d+\.'.preg_quote($extension, '/').'$/i', '.'.$extension, $image);
    }

    private function getActiveProductImages() {
        if ($this->active_product_images !== null) {
            return $this->active_product_images;
        }

        $store_id = (int)$this->config->get('config_store_id');
        $this->active_product_images = array();

		$sql = "SELECT DISTINCT `image` FROM (";
		$sql .= "SELECT p.`image` AS `image` FROM `".DB_PREFIX."product` p INNER JOIN `".DB_PREFIX."product_to_store` p2s ON (p.`product_id` = p2s.`product_id`) WHERE p.`status` = '1' AND p.`date_available` <= NOW() AND p2s.`store_id` = '".$store_id."' AND p.`image` <> ''";
		$sql .= " UNION ";
		$sql .= "SELECT pi.`image` AS `image` FROM `".DB_PREFIX."product_image` pi INNER JOIN `".DB_PREFIX."product` p ON (p.`product_id` = pi.`product_id`) INNER JOIN `".DB_PREFIX."product_to_store` p2s ON (p.`product_id` = p2s.`product_id`) WHERE p.`status` = '1' AND p.`date_available` <= NOW() AND p2s.`store_id` = '".$store_id."' AND pi.`image` <> ''";
		$sql .= ") active_product_images";

		$query = $this->db->query($sql);

		foreach ($query->rows as $row) {
			$image = str_replace('\\', '/', trim($row['image']));
			$image = ltrim($image, '/');

			if ($image !== '') {
				$this->active_product_images[$image] = true;
			}
		}

        return $this->active_product_images;
    }

    private function isCompressibleCacheImage($path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, array('jpg', 'jpeg', 'png'));
    }

    public function getWebpDestinationPath($sourcePath) {
        $basePath = str_replace(array('\\cache\\', '/cache/'), array(DIRECTORY_SEPARATOR . 'webp' . DIRECTORY_SEPARATOR, '/webp/'), $sourcePath);
        $withoutExtension = substr($basePath, 0, strrpos($basePath, '.'));

        return $withoutExtension . '.webp';
    }

    public function addlog($text = ''){
        if (!file_exists(DIR_LOGS)) {
            mkdir(DIR_LOGS, 0777, true);
        }

        $file = DIR_LOGS . 'hb_webp.txt';

        if (file_exists($file)) {
            $size = filesize($file);
            if ($size > 5242880){
                $handle = fopen($file, 'w+');
                fclose($handle);
            }
        }

        $fp = fopen($file, 'a');
        fwrite($fp, "\r\n".date('d-M-Y G:i:s A') . ' - ' .$text);
        fclose($fp);
    }
}
