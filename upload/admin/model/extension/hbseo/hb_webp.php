<?php
class ModelExtensionHbseoHbWebp extends Model {
	public function install(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "image_cache` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `path` text NOT NULL,
			  PRIMARY KEY (`id`)
		)DEFAULT CHARSET=utf8");
		$this->installOcmod();
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "image_cache`");
		$this->db->query("DELETE FROM " . DB_PREFIX . "modification WHERE `code` = 'huntbee_webp'");
	}

	public function update(){
		$this->installOcmod();
	}

	public function installOcmod(){
		$this->db->query("DELETE FROM " . DB_PREFIX . "modification WHERE `code` = 'huntbee_webp'");

		$ocmod_filename = 'ocmod_hb_webp_3xxx.txt';
		$ocmod_name = 'Webp Compression';

		$ocmod_version 	= $this->hb_extension_version;
		$ocmod_code 	= 'huntbee_webp';
		$ocmod_author 	= 'HuntBee OpenCart Services';
		$ocmod_link 	= 'https://www.huntbee.com';

		$file = DIR_APPLICATION . 'view/template/extension/hbseo/ocmod/'.$ocmod_filename;
		if (file_exists($file)) {
			$ocmod_xml = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
			$ocmod_xml = str_replace('{version}',$ocmod_version,$ocmod_xml);
			$ocmod_xml = str_replace('{name}',$ocmod_name,$ocmod_xml);
			$this->db->query("INSERT INTO " . DB_PREFIX . "modification SET code = '" . $this->db->escape($ocmod_code) . "', name = '" . $this->db->escape($ocmod_name) . "', author = '" . $this->db->escape($ocmod_author) . "', version = '" . $this->db->escape($ocmod_version) . "', link = '" . $this->db->escape($ocmod_link) . "', xml = '" . $this->db->escape($ocmod_xml) . "', status = '1', date_added = NOW()");
		}
	}

	public function getrecords($data){
		$sql = "SELECT `id`, `path` FROM `".DB_PREFIX."image_cache` ORDER BY `id` ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalrecords(){
		$results = $this->db->query("SELECT count(*) as total FROM `".DB_PREFIX."image_cache`");
		return $results->row['total'];
	}

	public function insertpath($path){
		$this->db->query("INSERT INTO `".DB_PREFIX."image_cache` (`path`) VALUES ('".$this->db->escape($path)."')");
	}

	public function insertpaths($paths){
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

	public function deletepath($path){
		$this->db->query("DELETE FROM `".DB_PREFIX."image_cache` WHERE `path` = '".$this->db->escape($path)."'");
	}

	public function deleteid($id){
		$this->db->query("DELETE FROM `".DB_PREFIX."image_cache` WHERE `id` = '".(int)$id."'");
	}

	public function deleteids($ids){
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

	public function deleteall(){
		$this->db->query("TRUNCATE TABLE `".DB_PREFIX."image_cache`");
	}

	public function allrecords($limit_count){
		$limit_count = (int)$limit_count;

		if ($limit_count < 1) {
			$limit_count = 1;
		}

		$sql = "SELECT `id`, `path` FROM `".DB_PREFIX."image_cache` ORDER BY `id` ASC LIMIT ".$limit_count;
		$query = $this->db->query($sql);
		return $query->rows;
	}
}
?>
