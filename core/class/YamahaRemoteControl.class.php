<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';
include_file('core', 'YamahaRemoteControl', 'config', 'YamahaRemoteControl');

class YamahaRemoteControl extends eqLogic {
	/*     * *************************Attributs****************************** */

	/*
	 * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
	 * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	  public static $_widgetPossibility = array();
	 */

	// retrieve AVR desc.xml
	public function ActionInfos($ipAddress) {
//		$url = 'http://' . $ipAddress . '/YamahaRemoteControl/desc.xml';
//		$reader = new \XMLReader();
//		$reader->open($url);
//		if (!$reader->isValid()) {
//			throw new Exception(__('Erreur de lecture des infos.', __FILE__));
//		}
//		$currentZone = null;
//		while ($reader->read()) {
//			if ($reader->nodeType === \XmlReader::ELEMENT) {
//				if ($reader->getAttribute("Unit_Name")) {
//					var_dump($reader->getAttribute("Unit_Name"));
//////					var_dump($reader->getAttribute("Unit_Name"));
////////					throw new Exception($reader->getAttribute("Unit_Name"));
//				}
//				if ($reader->name === 'Menu' && $reader->getAttribute('Func') === 'Subunit') {
//					$currentZone = $reader->getAttribute("Title_1");
//					var_dump($reader->depth);
//					var_dump($reader->getAttribute("YNC_Tag"));
//				}
//			}
//			if ($reader->nodeType === \XMLReader::END_ELEMENT) {
//				if ($reader->name === 'Menu' && $reader->depth === 1) {
//					var_dump($currentZone);
//					$currentZone = null;
//				}
//			}
//		}
//		var_dump($currentZone);

		$inputs = [];

		function xml2array_parse($xml) {
			foreach ($xml->children() as $parent => $child) {
				$return["$parent"] = xml2array_parse($child) ? xml2array_parse($child) : "$child";
			}

			return $return;
		}

		$url = 'http://' . $ipAddress . '/YamahaRemoteControl/ctrl';
		$param = ['http' => [
			'method' => 'POST',
//			'header' => "Content-type: application/x-www-form-urlencoded\r\n",
//			'content' => http_build_query([
//				'post_param1' => 'value1',
//				'post_param2' => 'value2',
//			]),
			'content' => '<YAMAHA_AV cmd="GET"><Main_Zone><Input><Input_Sel_Item>GetParam</Input_Sel_Item></Input></Main_Zone></YAMAHA_AV>',
		]];
		libxml_set_streams_context(stream_context_create($param));
		$sxe = new SimpleXMLElement($url, null, true);
		foreach ($sxe->xpath('/YAMAHA_AV/Main_Zone/Input/Input_Sel_Item') as $item) {
			array_push($inputs, xml2array_parse($item));
		}
		echo '<pre>' . var_dump($inputs) . '</pre>';
//		/** @var \XMLReader $reader */
//		$reader = \XMLReader::open($url);
//		while ($reader->read()) {
//			if ($reader->nodeType === \XMLReader::ELEMENT) {
//
//				var_dump($reader->name);
//
//			}
//			if ($reader->nodeType === \XMLReader::END_ELEMENT) {
//
//			}
//		}

		return;
	}

	public function isXML($xml) {
		libxml_use_internal_errors(true);

		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->loadXML($xml);

		$errors = libxml_get_errors();

		if (empty($errors)) {
			return true;
		}

		$error = $errors[0];
		if ($error->level < 3) {
			return true;
		}

		$explodedxml = explode("r", $xml);
		$badxml = $explodedxml[($error->line) - 1];

		$message = $error->message . ' at line ' . $error->line . '. Bad XML: ' . htmlentities($badxml);

		return $message;
	}

	public function xmlToArray(SimpleXMLElement $xml): array {
		$parser = function (SimpleXMLElement $xml, array $collection = []) use (&$parser) {
			$nodes = $xml->children();
			$attributes = $xml->attributes();

			if (0 !== count($attributes)) {
				foreach ($attributes as $attrName => $attrValue) {
					$collection['attributes'][$attrName] = strval($attrValue);
				}
			}

			if (0 === $nodes->count()) {
				$collection['value'] = strval($xml);

				return $collection;
			}

			foreach ($nodes as $nodeName => $nodeValue) {
				if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
					$collection[$nodeName] = $parser($nodeValue);
					continue;
				}

				$collection[$nodeName][] = $parser($nodeValue);
			}

			return $collection;
		};

		return [
			$xml->getName() => $parser($xml),
		];
	}

	private function getDesc() {
		$ipAddress = YamahaRemoteControl::getConfiguration('ip_address');

		$url = 'http://' . $ipAddress . '/YamahaRemoteControl/desc.xml';
		$param = ['http' => [
			'method' => 'GET',
//			'header' => "Content-type: application/x-www-form-urlencoded\r\n",
//			'content' => http_build_query([
//				'post_param1' => 'value1',
//				'post_param2' => 'value2',
//			]),
//			'content' => '<YAMAHA_AV cmd="GET"><Main_Zone><Input><Input_Sel_Item>GetParam</Input_Sel_Item></Input></Main_Zone></YAMAHA_AV>',
		]];
		libxml_set_streams_context(stream_context_create($param));
		try {
			$sxe = new SimpleXMLElement($url, null, true);
		} catch (Exception $e) {
			throw new Exception(__("Erreur lors de la lecture de la configuration de l'AVR. Vérifier l'adresse IP", __FILE__));
		}

		return $sxe;
	}

	private function sendRequest($path, $value = 'GetParam', $method = 'GET') {
		$pathArray = explode(',', $path);

		$content = '<YAMAHA_AV cmd="' . $method . '"><' . implode('><', $pathArray) . '>' . $value . '</' . implode('></', array_reverse($pathArray)) . '></YAMAHA_AV>';

		$url = 'http://' . YamahaRemoteControl::getConfiguration('ip_address') . '/YamahaRemoteControl/ctrl';
		$param = ['http' => [
			'method' => 'POST',
			'content' => $content,
		]];
		libxml_set_streams_context(stream_context_create($param));
		try {
			$sxe = new SimpleXMLElement($url, null, true);
		} catch (Exception $e) {
			throw new Exception(__("Erreur lors de la communication avec l'AVR.", __FILE__));
		}

		// Check response RC
		$arrayData = YamahaRemoteControl::xmlToArray($sxe);
		$responseCode = $arrayData['YAMAHA_AV']['attributes']['RC'];
		if (is_numeric($responseCode) && intval($responseCode) !== 0) {
			throw new Exception(__("Erreur lors de l'execution de la commande. (" . $responseCode . ")", __FILE__));
		}

		return $sxe;
	}

//	WIP
//	private function getZones() {
//		$zones = [];
//		$items = $this->getDesc()->xpath('@Func=`Subunit`');
//		foreach ($items as $item) {
//			$attributes = $item->attributes();
//
//			foreach ( as $key => $value) {
//				if ($key == 'YNC_TAG') {
//					$zones[] = $item->attributes();
//				}
//			}
//
//		}
//		echo '<pre>' . print_r($zones) .'</pre>';
//
//		return $zones;
//	}

	public function updateCurrentConfig($zone = 'Main_Zone') {
		$path = $zone . ',Basic_Status';
		$sxe = $this->sendRequest($path);

		$this->getCmd('info', 'power')->setValue($sxe->xpath('//Power')[0] == 'On' ? 1 : 0)->save();
		$this->getCmd('info', 'input')->setValue($sxe->xpath('//Input/Input_Sel')[0])->save();
		$this->getCmd('info', 'volume')->setValue(intval($sxe->xpath('//Volume/Lvl/Val')[0]))->save();
		$this->getCmd('info', 'mute')->setValue($sxe->xpath('//Volume/Mute')[0] == 'On' ? 1 : 0)->save();
	}

	private function getInputs() {
		$inputs = [];
		$path = 'Main_Zone,Input,Input_Sel_Item';
		$items = $this->sendRequest($path)->xpath('//' . implode('/', explode(',', $path)) . '/*');
		foreach ($items as $item) {
			$inputs[(string) $item->Param] = trim((string) $item->Title);
		}

		return $inputs;
	}

	private function getPowerStatus() {
		$sxe = $this->sendRequest('Main_Zone,Power_Control,Power');
		echo '<pre>' . print_r($sxe) . '</pre>';
	}

	private function createInputsCommand() {
		$cmdName = 'Entrées';
		log::add('YamahaRemoteControl', 'debug', 'createInputsCommand');
		if (cmd::byEqLogicIdCmdName($this->getId(), $cmdName)) {
			log::add('YamahaRemoteControl', 'debug', 'createInputsCommand already exists');

			return;
		}
		$inputs = $this->getInputs();

		$YamahaRemoteControlCmd = new YamahaRemoteControlCmd();
		$YamahaRemoteControlCmd->setName(__($cmdName, __FILE__));
		$YamahaRemoteControlCmd->setEqLogic_id($this->id);
		$YamahaRemoteControlCmd->setLogicalId('inputs');
		$YamahaRemoteControlCmd->setConfiguration('values', json_encode($inputs));
//				$YamahaRemoteControlCmd->setConfiguration('code_touche', $cmd['configuration']['code_touche']);
//				$YamahaRemoteControlCmd->setConfiguration('mosaique_chaine', $cmd['configuration']['mosaique_chaine']);
//				$YamahaRemoteControlCmd->setConfiguration('etat_decodeur', 0);
//				$YamahaRemoteControlCmd->setConfiguration('chaine_actuelle', $cmd['configuration']['chaine_actuelle']);
//				$YamahaRemoteControlCmd->setConfiguration('id_chaine_actuelle', $cmd['configuration']['id_chaine_actuelle']);
//				$YamahaRemoteControlCmd->setConfiguration('fonction', $cmd['configuration']['fonction']);
		$YamahaRemoteControlCmd->setType('info');
		$YamahaRemoteControlCmd->setSubType('string');
		$YamahaRemoteControlCmd->setOrder(99);
		$YamahaRemoteControlCmd->setIsVisible(true);
//		$YamahaRemoteControlCmd->setDisplay('generic_type', $cmd['generic_type']);
//				$YamahaRemoteControlCmd->setDisplay('forceReturnLineAfter', $cmd['forceReturnLineAfter']);
//				$YamahaRemoteControlCmd->setValue(json_encode($inputs));
		$YamahaRemoteControlCmd->save();
	}

	public function autoUpdateCommande() {
		log::add('YamahaRemoteControl', 'debug', 'autoUpdateCommande start');
		$this->createInputsCommand();
		$this->updateCurrentConfig();
		log::add('YamahaRemoteControl', 'debug', 'autoUpdateCommande finish');
	}

	public function autoAjoutCommande() {
		log::add('YamahaRemoteControl', 'debug', 'autoAjoutCommande start');
//		$desc = $this->getDesc();
		global $listCmdYamahaRemoteControl;
		foreach ($listCmdYamahaRemoteControl as $cmd) {
			log::add('YamahaRemoteControl', 'debug', 'autoAjoutCommande: ' . $cmd['name']);
			if (cmd::byEqLogicIdCmdName($this->getId(), $cmd['name'])) {
				log::add('YamahaRemoteControl', 'debug', 'autoAjoutCommande'. $cmd['name'] .' already done');

				continue;
			}
			if ($cmd) {
				$YamahaRemoteControlCmd = new YamahaRemoteControlCmd();
				$YamahaRemoteControlCmd->setName(__($cmd['name'], __FILE__));
				$YamahaRemoteControlCmd->setEqLogic_id($this->id);
				$YamahaRemoteControlCmd->setLogicalId($cmd['logicalId']);
				foreach ($cmd['configuration'] as $name => $value) {
					$YamahaRemoteControlCmd->setConfiguration($name, $value);
				}

//				$YamahaRemoteControlCmd->setConfiguration('tab_name', $cmd['configuration']['tab_name']);
//				$YamahaRemoteControlCmd->setConfiguration('code_touche', $cmd['configuration']['code_touche']);
//				$YamahaRemoteControlCmd->setConfiguration('mosaique_chaine', $cmd['configuration']['mosaique_chaine']);
//				$YamahaRemoteControlCmd->setConfiguration('etat_decodeur', 0);
//				$YamahaRemoteControlCmd->setConfiguration('chaine_actuelle', $cmd['configuration']['chaine_actuelle']);
//				$YamahaRemoteControlCmd->setConfiguration('id_chaine_actuelle', $cmd['configuration']['id_chaine_actuelle']);
//				$YamahaRemoteControlCmd->setConfiguration('fonction', $cmd['configuration']['fonction']);
				if (isset($cmd['unite'])) {
					$YamahaRemoteControlCmd->setUnite($cmd['unite']);
				}
				$YamahaRemoteControlCmd->setType($cmd['type']);
				$YamahaRemoteControlCmd->setSubType($cmd['subType']);
				$YamahaRemoteControlCmd->setOrder($cmd['order']);
				$YamahaRemoteControlCmd->setIsVisible($cmd['isVisible']);
				$YamahaRemoteControlCmd->setDisplay('generic_type', $cmd['generic_type']);
//				$YamahaRemoteControlCmd->setDisplay('forceReturnLineAfter', $cmd['forceReturnLineAfter']);
				$YamahaRemoteControlCmd->setValue(0);
				$YamahaRemoteControlCmd->save();
			}
		}
		log::add('YamahaRemoteControl', 'debug', 'autoAjoutCommande finish');
	}

	/*     * ***********************Methode static*************************** */

	/**
	 * Fonction exécutée automatiquement toutes les minutes par Jeedom
	public static function cron() {
	}
	 */

	/*
	 * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
	  public static function cron5() {
	  }
	 */

	/*
	 * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
	  public static function cron10() {
	  }
	 */

	/*
	 * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
	  public static function cron15() {
	  }
	 */

	/*
	 * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
	  public static function cron30() {
	  }
	 */

	/*
	 * Fonction exécutée automatiquement toutes les heures par Jeedom
	  public static function cronHourly() {
	  }
	 */

	/*
	 * Fonction exécutée automatiquement tous les jours par Jeedom
	  public static function cronDaily() {
	  }
	 */

	/*     * *********************Méthodes d'instance************************* */

	// Fonction exécutée automatiquement avant la création de l'équipement
	public function preInsert() {
//		log::add('YamahaRemoteControl', 'debug', 'preInsert');
		$this->setCategory('multimedia', 1);
		$this->setIsVisible(1);
		$this->setIsEnable(1);
	}

	// Fonction exécutée automatiquement après la création de l'équipement
	public function postInsert() {
		log::add('YamahaRemoteControl', 'debug', 'postInsert');
		$this->autoAjoutCommande();
	}

	// Fonction exécutée automatiquement avant la mise à jour de l'équipement
	public function preUpdate() {
//		log::add('YamahaRemoteControl', 'debug', 'preUpdate');
		$ipAddress = $this->getConfiguration('ip_address');
		if ($ipAddress === '') {
			throw new Exception(__('Merci de renseigner IP équipement.', __FILE__));
		}
		$this->autoUpdateCommande();
//		$this->ActionInfos($ipAddress);
	}

	// Fonction exécutée automatiquement après la mise à jour de l'équipement
	public function postUpdate() {
//		log::add('YamahaRemoteControl', 'debug', 'postUpdate');

	}

	// Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
	public function preSave() {
//		log::add('YamahaRemoteControl', 'debug', 'preSave');

	}

	// Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	public function postSave() {
//		log::add('YamahaRemoteControl', 'debug', 'postSave');
//		$power = $this->getCmd(null, 'power');
//		if (!is_object($power)) {
//			$power = new YamahaRemoteControlCmd();
//			$power->setName(__('Power', __FILE__));
//		}
//		$power->setLogicalId('power');
//		$power->setEqLogic_id($this->getId());
//		$power->setType('action');
//		$power->setSubType('other');
//		$power->save();
	}

	// Fonction exécutée automatiquement avant la suppression de l'équipement
	public function preRemove() {

	}

	// Fonction exécutée automatiquement après la suppression de l'équipement
	public function postRemove() {

	}

	/*
	 * Non obligatoire : permet de modifier l'affichage du widget (également utilisable par les commandes)
	  public function toHtml($_version = 'dashboard') {

	  }
	 */

	/*
	 * Non obligatoire : permet de déclencher une action après modification de variable de configuration
	public static function postConfig_<Variable>() {
	}
	 */

	/*
	 * Non obligatoire : permet de déclencher une action avant modification de variable de configuration
	public static function preConfig_<Variable>() {
	}
	 */

	/*     * **********************Getteur Setteur*************************** */
}

class YamahaRemoteControlCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*
	  public static $_widgetPossibility = array();
	*/

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	/*
	 * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
	  public function dontRemoveCmd() {
	  return true;
	  }
	 */

	// Exécution d'une commande
	public function execute($_options = []) {
//		throw new Exception(__('Merci de renseigner IP équipement.', __FILE__));
		$eqLogic = $this->getEqLogic();
//		$ipAdrress = $this->getConfiguration('id_address');
		$logicalId = $this->getLogicalId();
		log::add('YamahaRemoteControl', 'debug', 'execute '. $logicalId);
		switch ($logicalId) {
			case 'touche':
				break;
			case 'volume':
				$path = $this->getConfiguration('path');
				$param = $this->getConfiguration('param');
				$val = $eqLogic->sendRequest($path)->xpath('//'.$param);
				log::add('YamahaRemoteControl', 'debug', 'execute volume: '. $val);
				$this->setValue($val);
				$this->save();

				break;
		}
		return;
	}

	/*     * **********************Getteur Setteur*************************** */
}
