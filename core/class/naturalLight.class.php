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
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

define('PLUGIN_NAME', 'naturalLight');

class naturalLight extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  */
  public static function cron15() {
    log::add(PLUGIN_NAME, 'debug', '*** cron ***');

    foreach (eqLogic::byType(__CLASS__, true) as $light) {
      if ($light->getIsEnable() == 1) {
        $cmd = $light->getCmd(null, 'refresh');
        if (!is_object($cmd)) {
          continue;
        }
        $cmd->execCmd();
      }
    }
  }

  /**
   * Fonction appelé par Listener
   */
  public static function pullRefresh($_option) {
    log::add(PLUGIN_NAME, 'debug', 'pullRefresh started');

    /** @var designImgSwitch */
    $eqLogic = self::byId($_option['id']);
    if (is_object($eqLogic) && $eqLogic->getIsEnable() == 1) {
      log::add(PLUGIN_NAME, 'debug', 'pullRefresh action sur : '.$eqLogic->getHumanName());
      $eqLogic->computeLamp();
    }
  }

  /*     * *********************Méthodes d'instance************************* */

    /**
     * @return listener
     */
    private function getListener() {
      log::add(PLUGIN_NAME, 'debug', 'getListener');

      return listener::byClassAndFunction(__CLASS__, 'pullRefresh', array('id' => $this->getId()));
  }

  private function removeListener() {
    log::add(PLUGIN_NAME, 'debug', 'remove Listener');

      $listener = $this->getListener();
      if (is_object($listener)) {
          $listener->remove();
      }
  }

  private function setListener() {
    log::add(PLUGIN_NAME, 'debug', 'setListener');

    if ($this->getIsEnable() == 0) {
        $this->removeListener();
        return;
    }

    $lamp_state = $this->getConfiguration('lamp_state');
    $lamp_state = str_replace('#', '', $lamp_state);
    $cmd = cmd::byId($lamp_state);
    if (!is_object($cmd)) {
      throw new Exception("lamp_state non renseigné");
    }

    $listener = $this->getListener();
    if (!is_object($listener)) {
        $listener = new listener();
        $listener->setClass(__CLASS__);
        $listener->setFunction('pullRefresh');
        $listener->setOption(array('id' => $this->getId()));
    }
    $listener->emptyEvent();
    $listener->addEvent($cmd->getId());
    
    $listener->save();
  }

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
    log::add(PLUGIN_NAME, 'debug', 'postSave');

    // sun_elevation
    $sunElevation = $this->getCmd(null, 'sun_elevation');
    if (!is_object($sunElevation)) {
        $sunElevation = new windowsCmd();
        $sunElevation->setLogicalId('sun_elevation');
        $sunElevation->setName(__('Sun Elevation', __FILE__));
        $sunElevation->setIsVisible(1);
        $sunElevation->setIsHistorized(0);
        $sunElevation->setUnite('°');
    }
    $sunElevation->setEqLogic_id($this->getId());
    $sunElevation->setType('info');
    $sunElevation->setSubType('numeric');
    $sunElevation->setGeneric_type('GENERIC_INFO');

    $sunElevation->save();
    unset($sunElevation);

    // temperature_color
    $temperatureColor = $this->getCmd(null, 'temperature_color');
    if (!is_object($temperatureColor)) {
        $temperatureColor = new windowsCmd();
        $temperatureColor->setLogicalId('temperature_color');
        $temperatureColor->setName(__('Temperature color', __FILE__));
        $temperatureColor->setIsVisible(1);
        $temperatureColor->setIsHistorized(0);
        $temperatureColor->setUnite('mired');
    }
    $temperatureColor->setEqLogic_id($this->getId());
    $temperatureColor->setType('info');
    $temperatureColor->setSubType('numeric');
    $temperatureColor->setGeneric_type('LIGHT_COLOR_TEMP');

    // $value = false;
    // $sunElevation->setValue($value);
    $temperatureColor->save();
    unset($temperatureColor);

    // refresh
    $refresh = $this->getCmd(null, 'refresh');
    if (!is_object($refresh)) {
        $refresh = new windowsCmd();
        $refresh->setLogicalId('refresh');
        $refresh->setIsVisible(1);
        $refresh->setName(__('Rafraichir', __FILE__));
        $refresh->setOrder(0);
    }
    $refresh->setEqLogic_id($this->getId());
    $refresh->setType('action');
    $refresh->setSubType('other');
    $refresh->save();
    unset($refresh);

    $lamp_state = $this->getConfiguration('lamp_state');
    if (empty($lamp_state)) {
      return;
    }

    $this->setListener();
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
    $this->removeListener();
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  public function computeLamp() {
    log::add(PLUGIN_NAME, 'debug', '  computeLamp()');

    $eqlogic = $this;

    try {
      // Calculer Sun elevation
      $sunElevation = $this->computeSunElevation();

      // set Sun Elevation value
      $cmdSunElevation = $eqlogic->getCmd(null, 'sun_elevation');
      $cmdSunElevation->event($sunElevation);

      // Obtenir état de la lampe
      $state = 0;
      $lamp_state = $eqlogic->getConfiguration('lamp_state');
      $lamp_state = str_replace('#', '', $lamp_state);
      if ($lamp_state != '') {
        $cmd = cmd::byId($lamp_state);
        if ($cmd == null) {
          log::add(PLUGIN_NAME, 'error', ' Mauvaise lamp_state :' . $lamp_state);
          throw new Exception('Mauvaise lamp_state');
        } else {
          $state = $cmd->execCmd();
          log::add(PLUGIN_NAME, 'debug', '  lamp_state: ' . $cmd->getEqLogic()->getHumanName() . '[' . $cmd->getName() . ']:'.$state);
        }
      }
      else {
        log::add(PLUGIN_NAME, 'error', ' lamp_state non renseigné');
        throw new Exception('lamp_state non renseigné');
      }

      // Calcul de la température couleur
      $temp_color = intval(1000000/(4791.67 - 3290.66/(1 + 0.222 * $sunElevation * 0.81)));
      log::add(PLUGIN_NAME, 'debug', '  temp_color calculé: ' . $temp_color);

      // Obtenir la commande Temperature Couleur
      $temperature_color = $eqlogic->getConfiguration('temperature_color');
      $temperature_color = str_replace('#', '', $temperature_color);
      if ($temperature_color != '') {
        $cmd = cmd::byId($temperature_color);
        if ($cmd == null) {
          log::add(PLUGIN_NAME, 'error', ' Mauvaise temperature_color :' . $temperature_color);
          throw new Exception('Mauvaise temperature_color');
        } else {
          log::add(PLUGIN_NAME, 'debug', '  temperature_color: ' . $cmd->getEqLogic()->getHumanName() . '[' . $cmd->getName() . ']');
          
          // Vérification du type generique
          $genericType = $cmd->getGeneric_type();
          log::add(PLUGIN_NAME, 'debug', '  getGenericType: ' . $genericType);
          if ($genericType != 'LIGHT_SET_COLOR_TEMP') {
            log::add(PLUGIN_NAME, 'error', ' Mauvaise commande pour la lampe : temperature_color');
          throw new Exception('Mauvaise commande pour la lampe : temperature_color');
          }

          // Recherche de la configuration
          $minValue = $cmd->getConfiguration('minValue');
          $maxValue = $cmd->getConfiguration('maxValue');
          log::add(PLUGIN_NAME, 'debug', '  minValue: ' . $minValue);
          log::add(PLUGIN_NAME, 'debug', '  maxValue: ' . $maxValue);         

          if (empty($minValue)) {
            log::add(PLUGIN_NAME, 'error', ' minValue non renseignée');
            throw new Exception('minValue non renseignée');
          }
          if (empty($maxValue)) {
            log::add(PLUGIN_NAME, 'error', ' maxValue non renseignée');
            throw new Exception('maxValue non renseignée');
          }

          // Calcul de la température couleur gérable par l'équipement
          if ($temp_color > $maxValue){
            $temp_color = $maxValue;
          }
          if ($temp_color < $minValue) {
            $temp_color = $minValue;
          }
          log::add(PLUGIN_NAME, 'info', 'température couleur: ' . $temp_color);

          // set temp_color value
          $cmdTempColor = $eqlogic->getCmd(null, 'temperature_color');
          $cmdTempColor->event($temp_color);
          $cmdTempColor->setConfiguration('minValue', $minValue);
          $cmdTempColor->setConfiguration('maxValue', $maxValue);
          $cmdTempColor->save();

          // Lumière éteinte : on ne fait rien
          if ($state == 1) {
            log::add(PLUGIN_NAME, 'info', ' lampe allumée');
            $cmd->execCmd(array('slider' => $temp_color, 'transition' => 300));
          } else {
            log::add(PLUGIN_NAME, 'info', ' lampe éteinte');
          }
        }
      }
      else {
        log::add(PLUGIN_NAME, 'error', ' temperature_color non renseigné');
        throw new Exception('temperature_color non renseigné');
      }
    }
    catch (Exception $ex) {      
    }
  }

    /**
   * Calculer la hauteur du soleil
   * @return {float} Hauteur du soleil
   */
  private function computeSunElevation(): float {
    $latitude =config::bykey('info::latitude');
    $longitude = config::bykey('info::longitude');
    $altitude = config::bykey('info::altitude');

    log::add(PLUGIN_NAME, 'debug', ' latitude :' . $latitude);
    log::add(PLUGIN_NAME, 'debug', ' longitude :' . $longitude);
    log::add(PLUGIN_NAME, 'debug', ' altitude :' . $altitude);


    if (!isset($latitude) || !isset($longitude)) {
      log::add(PLUGIN_NAME, 'error', ' latitude ou longitude non renseigné');
      throw new Exception();
    }

    $SD = new SolarData\SolarData();
    $SD->setObserverPosition(config::byKey('info::latitude'), config::byKey('info::longitude'), config::byKey('info::altitude'));
    $SD->setObserverDate(date('Y'), date('n'), date('j'));
    $SD->setObserverTime(date('G'), date('i'),date('s'));
    //ARGS : difference in seconds between the Earth rotation time and the Terrestrial Time (TT)/
    $SD->setDeltaTime(67);
    $SD->setObserverTimezone(date('Z') / 3600);
    $SunPosition = $SD->calculate();
    $sunElevation = floatval(round($SunPosition->e0°, 2));
    log::add(PLUGIN_NAME, 'debug', ' sunElevation :' . $sunElevation);

    if ($sunElevation < 0)
    {
      $sunElevation = 0;
    }
    if ($sunElevation > 90){
      $sunElevation = 90;
    }
    log::add(PLUGIN_NAME, 'debug', ' sunElevation corrigé :' . $sunElevation);

    return floatval($sunElevation);
  }

  /*     * **********************Getteur Setteur*************************** */

}

class naturalLightCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */


  // Exécution d'une commande
  public function execute($_options = array()) {
    log::add(PLUGIN_NAME, 'info', ' **** execute ****');

    if ($this->getLogicalId() == 'refresh') {
      $eqlogic = $this->getEqLogic(); //récupère l'éqlogic de la commande $this
      $eqlogic->computeLamp();
    }
  }

  /*     * **********************Getteur Setteur*************************** */

}
