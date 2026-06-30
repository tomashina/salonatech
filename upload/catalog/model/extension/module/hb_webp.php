<?php
class ModelExtensionModuleHbWebp extends Model {
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
			$this->addlog($total.' uncompressed WebP image paths logged to database!');
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

        $webp_destination = $this->getWebpDestinationPath($path);

        return !is_file($webp_destination) || filemtime($path) > filemtime($webp_destination);
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
