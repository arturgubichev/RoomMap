<?php


class RoomMapRemote {
	public function __construct(PDO $pdo, Authorization $auth){
		$this->db = $pdo;
		$this->auth = $auth;
	}

	public function getLayersList(){
		$layers = $this->db->Query("SELECT * FROM layers");
		$listOfLayers = array();
		while($layer = $layers->Fetch(PDO::FETCH_ASSOC)){
			array_push($listOfLayers,array('id' => $layer['id_layer'],'code' => $layer['layer_code'],'name' => json_decode($layer['layer_name'],true),'description' => json_decode($layer['layer_description'],true)));
		}	
		return $listOfLayers;
	}

	public function getLevelsList(){
		$levels = $this->db->Query("SELECT * FROM levels");
		$listOfLevels = array();
		while($level = $levels->Fetch(PDO::FETCH_ASSOC)){
			array_push($listOfLevels,array('id' => $level['id_level'],'code' => $level['id_level'],'name' => json_decode($level['level_name'],true),'description' => json_decode($level['level_description'],true)));
		}	
		return $listOfLevels;
	}

	public function getListSVGObject($xMin, $yMin, $xMax, $yMax, $level, $layer){
		if(!$layer) $layer = 'IS NULL';
		else $layer = "= '".$layer."'";
		$query = "SELECT o.id, o.min_x, o.min_y, o.max_x, o.max_y, o.title, o.content FROM objects o
						LEFT JOIN layers l ON o.id_layer = l.id_layer
							WHERE l.layer_code ".$layer."
								AND o.id_level = ".$level."
								AND ((o.min_x >= ".$xMin." AND o.min_x <= ".$xMax." AND o.min_y >= ".$yMin." AND o.min_y <= ".$yMax.") OR (o.max_x <= ".$xMax." AND o.max_x >= ".$xMin." AND o.max_y <= ".$yMax." AND o.max_y >= ".$yMin."))";
		
		$objects = $this->db->Query($query);
	 	$listOfSVG = array();
		while($object = $objects->Fetch(PDO::FETCH_ASSOC)){
			array_push($listOfSVG,array('id' => $object['id'], 'min_x' => $object['min_x'], 'min_y' => $object['min_y'], 'max_x' => $object['max_x'], 'max_y' => $object['max_y'], 'title' => $object['title'], 'content' => $object['content']));
		}
		return $listOfSVG;
	}

	public function getSvgObjectDetails($svg_id, $level){
		sleep(1);
		$query = "SELECT description FROM objects WHERE id = ".$svg_id;

		$objects = $this->db->Query($query);
		$object = $objects->Fetch(PDO::FETCH_ASSOC);

		return $object['description'];
	}

	public function getSvgDataForEdit($id){
		if(!$this->auth->UserIsEditor()) return 0;

		$query = "SELECT id as id_object,
						title,
						description
					FROM objects WHERE id = ".$id;

		$data = $this->db->Query($query);
		return $row = $data->Fetch(PDO::FETCH_ASSOC);
	}

	public function saveData($dataArray){
		if(!$this->auth->UserIsEditor()) return 0;

		// Новый объект
		if($dataArray['new']){
			// Идентификатор слоя
			$data = $this->db->Query("SELECT id_layer FROM layers WHERE layer_code = '".$dataArray['layer']."'");
			$id_layer = (int)$data->fetchColumn();

			// Добавляем объект
			echo $query = "INSERT INTO objects (id, id_level, id_layer, min_x, min_y, max_x, max_y, title, description, content) VALUES (NULL,".$dataArray['level'].",".$id_layer.",".$dataArray['coords']['min_x'].",".$dataArray['coords']['min_y'].",".$dataArray['coords']['max_x'].",".$dataArray['coords']['max_y'].",'".$dataArray['title']."','".$dataArray['content']."','".$dataArray['object']."')";
			$this->db->Query($query);
		}
		// Редактирование существующего
		else{
			// Обновляем объект
			$query = "UPDATE objects SET content = '".$dataArray['object']."', min_x = '".$dataArray['coords']['min_x']."', min_y = '".$dataArray['coords']['min_y']."', max_x = '".$dataArray['coords']['max_x']."', max_y = '".$dataArray['coords']['max_y']."', title = '".$dataArray['title']."', description = '".$dataArray['content']."' WHERE id = ".$dataArray['id_obj'];
			$this->db->Query($query);
		}

		return 1;
	}


}

class Authorization {
	public function UserIsEditor(){
		session_start();
		return $_SESSION['editorMode'];
	}
}

function prepareData($dataArray){
	$slashes = array('content','title','layer','object');
	$integer = array('level','id_obj');
	$json = array('coords');

	$queue_prepare = array('slashes', 'integer', 'json');
	foreach($queue_prepare as $prepare_type){
		foreach(${$prepare_type} as $param){
			switch($prepare_type){
				case 'slashes':
					$dataArray[$param] = addslashes($dataArray[$param]);
					break;
				case 'integer':
					$dataArray[$param] = (int)$dataArray[$param];
					break;
				case 'json':
					$dataArray[$param] = json_decode($dataArray[$param],1);
					if(!is_array($dataArray[$param])) exit;
					foreach($dataArray[$param] as $key => $json_param){
						$dataArray[$param][$key] = addslashes($json_param);
					}
					break;
			}
		}
	}
	return $dataArray;
}


$PDO = new PDO('mysql:dbname=roommap2;host=127.0.0.1','root','');
$PDO->Exec("SET NAMES utf8");

$AUTH = new Authorization();

$RoomMapRemoteObj = new RoomMapRemote($PDO,$AUTH);


switch($_POST['data']){
	case 'getlists': 
		echo json_encode(array('layers' => $RoomMapRemoteObj->getLayersList(), 'levels' => $RoomMapRemoteObj->getLevelsList()));
		break;
	case 'getsvg':
		echo json_encode(array('svg' => $RoomMapRemoteObj->getListSVGObject((float)$_POST['min_x'],(float)$_POST['min_y'],(float)$_POST['max_x'],(float)$_POST['max_y'],(int)$_POST['level'],addslashes($_POST['layer']))));
		break;
	case 'getdetails':
		echo json_encode(array('details' => $RoomMapRemoteObj->getSvgObjectDetails((int)$_POST['object_id'], (int)$_POST['level'])));
		break;
	case 'getSvgDataForEdit':
		echo json_encode($RoomMapRemoteObj->getSvgDataForEdit((int)$_POST['id']));
		break;
	case 'saveData':
		echo $RoomMapRemoteObj->saveData(prepareData($_POST));
		break;
}

?>