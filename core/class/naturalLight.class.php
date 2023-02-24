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

class naturalLight extends eqLogic
{
  /*     * *************************Attributs****************************** */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  */
  public static function cron()
  {
    log::add(__CLASS__, 'debug', '*** ' . __FUNCTION__ . ' ***');

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
  public static function pullRefresh($_option)
  {
    log::add(__CLASS__, 'debug', '*** ' . __FUNCTION__ . ' ***');

    $eqLogic = self::byId($_option['id']);
    if (is_object($eqLogic) && $eqLogic->getIsEnable() == 1) {
      log::add(__CLASS__, 'info', 'pullRefresh action sur : ' . $eqLogic->getHumanName());

      $lamp_state = $eqLogic->getLampState();
      if ($lamp_state === 1) {
        $eqLogic->computeLamp();
      }
    }
  }

  /*     * *********************Méthodes d'instance************************* */

  /**
   * @return listener
   */
  private function getListener()
  {
    log::add(__CLASS__, 'debug', 'getListener');

    return listener::byClassAndFunction(__CLASS__, 'pullRefresh', array('id' => $this->getId()));
  }

  private function removeListener()
  {
    log::add(__CLASS__, 'debug', 'remove Listener');

    $listener = $this->getListener();
    if (is_object($listener)) {
      $listener->remove();
    }
  }

  private function setListener()
  {
    log::add(__CLASS__, 'debug', 'setListener');

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

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave()
  {
    log::add(__CLASS__, 'debug', '*** save ***');

    // Mise à jour sous info
    log::add(__CLASS__, 'debug', '  mise à jour MinMaxValue');
    $cmdLampe = $this->getLampCommand(true);
    if ($cmdLampe != null) {
      $this->setMinMaxValueConfiguration($cmdLampe);
    }
    // ******************************
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave()
  {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    // sun_elevation
    $sunElevation = $this->getCmd(null, 'sun_elevation');
    if (!is_object($sunElevation)) {
      $sunElevation = new naturalLightCmd();
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
      $temperatureColor = new naturalLightCmd();
      $temperatureColor->setLogicalId('temperature_color');
      $temperatureColor->setName(__('Temperature color', __FILE__));
      $temperatureColor->setIsVisible(1);
      $temperatureColor->setIsHistorized(0);
    }
    $temperatureColor->setEqLogic_id($this->getId());
    $temperatureColor->setType('info');
    $temperatureColor->setSubType('numeric');
    $temperatureColor->setGeneric_type('GENERIC_INFO');

    $temperatureColor->save();
    unset($temperatureColor);

    // refresh
    $refresh = $this->getCmd(null, 'refresh');
    if (!is_object($refresh)) {
      $refresh = new naturalLightCmd();
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

    // Listener ajouté sur la lampe, uniquement si elle est paramétrée
    $lamp_state = $this->getConfiguration('lamp_state');
    if (empty($lamp_state)) {
      return;
    }

    $this->setListener();
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove()
  {
    $this->removeListener();
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove()
  {
  }

  /**
   * Initialisation de minValue et maxValue 
   * en prenant les limites de la lampes
   */
  private function setMinMaxValueConfiguration(cmd $cmdLampe)
  {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    if ($cmdLampe == null) {
      //log::add(__CLASS__, 'debug', '  commande Lamp pas encore renseignée');
      return;
    }

    // MinValue
    $minValue = $this->getConfiguration('minValue');
    $minValueDefault = $cmdLampe->getConfiguration('minValue');
    if ($minValue === '') {
      log::add(__CLASS__, 'debug', '  minValue non initialisé');
      log::add(__CLASS__, 'debug', '  minValue devient: ' . $minValueDefault);
      $this->setConfiguration('minValue', $minValueDefault);
    }
    $this->setConfiguration('minValueDefault', $minValueDefault);

    // MaxValue
    $maxValue = $this->getConfiguration('maxValue');
    $maxValueDefault = $cmdLampe->getConfiguration('maxValue');
    if ($maxValue === '') {
      log::add(__CLASS__, 'debug', '  maxValue non initialisé');
      log::add(__CLASS__, 'debug', '  maxValue devient: ' . $maxValueDefault);
      $this->setConfiguration('maxValue', $maxValueDefault);
    }
    $this->setConfiguration('maxValueDefault', $maxValueDefault);
  }

  public function computeLamp()
  {
    try {
      // Optimisation : Voir s'il faut calculer la couleur et l'élévation
      $cmdSunElevation = $this->getCmd(null, 'sun_elevation');
      // Récupérer la commande de la lampe
      $cmdTempColor = $this->getCmd(null, 'temperature_color');
      $state = $this->getLampState();

      if ($state === 0 && !$cmdSunElevation->getIsHistorized() && !$cmdTempColor->getIsHistorized()) {
        // Aucun interêt de faire des calculs
        return;
      }

      // Calculer Sun elevation
      $sunElevation = $this->computeSunElevation();
      // set Sun Elevation value
      $cmdSunElevation->event($sunElevation);

      if ($state === 0 && !$cmdTempColor->getIsHistorized()) {
        // Aucun interêt de faire des calculs
        return;
      }

      // Test : calcul sur SunElevation
      $temp_color = $this->computeTempColorBySunElevation($sunElevation);

      // Plugin Ikea ------------------------
      if ($this->getConfiguration('minValueDefault') == 0 &&
          $this->getConfiguration('maxValueDefault') == 100) {
            log::add(__CLASS__, 'debug', '  gestion en pourcentage');

          // plugin gérant la notion de pourcentage
          // ampoule Ikea : min=153 mired, max: 370 mired
          $maxi = 370;
          $mini = 153;

          $temp_color = 100 - intval(100 * ($maxi - $temp_color) / ($maxi - $mini));
          // Correction selon borne
          if ($temp_color < 0) $temp_color = 0;
          if ($temp_color > 100) $temp_color = 100;

          log::add(__CLASS__, 'debug', '  temp_color corrigé :' . $temp_color . '%');
      }
      // -------------------------

      // Plugin inconnu en Kelvin
      if ($this->getConfiguration('minValueDefault') > 500 &&
          $this->getConfiguration('maxValueDefault') > 500) {
          log::add(__CLASS__, 'debug', '  gestion en Kelvin');

          $temp_color = intval(1000000 / $temp_color);
          log::add(__CLASS__, 'debug', '  temp_color corrigé :' . $temp_color.'K');

      }
      // -------------------------

      $cmd = $this->getLampCommand();
      
      // Recherche de la configuration
      $minValue = $this->getConfiguration('minValue');
      if (!isset($minValue)) {
        // Ancien équipement sans valeur minValue
        log::add(__CLASS__, 'debug', '  minValue pris de la lampe');
        $minValue = $cmd->getConfiguration('minValue');
      }
      $maxValue = $this->getConfiguration('maxValue');
      if (!isset($maxValue)) {
        // Ancien équipement sans valeur maxValue
        log::add(__CLASS__, 'debug', '  maxValue pris de la lampe');
        $maxValue = $cmd->getConfiguration('maxValue');
      }
      log::add(__CLASS__, 'debug', '  minValue: ' . $minValue);
      log::add(__CLASS__, 'debug', '  maxValue: ' . $maxValue);
      
      if (!isset($minValue)) {
        log::add(__CLASS__, 'error', '  minValue non renseignée');
        throw new Exception('minValue non renseignée');
      }
      if (!isset($maxValue)) {
        log::add(__CLASS__, 'error', '  maxValue non renseignée');
        throw new Exception('maxValue non renseignée');
      }
      // -------------------------

      // Calcul de la température couleur gérable par l'équipement
      if ($temp_color > $maxValue) {
        $temp_color = $maxValue;
      }
      if ($temp_color < $minValue) {
        $temp_color = $minValue;
      }
      log::add(__CLASS__, 'info', 'température couleur: ' . $temp_color);

      // set temp_color value
      $cmdTempColor->event($temp_color);

      // Gestion de la condition
      $condition = $this->getConfiguration('condition');
      log::add(__CLASS__, 'debug', '  condition : '.$condition);
      $conditionResult = true;
      if ($condition != '')  {
          // Evaluation
          $conditionResult = jeedom::evaluateExpression($condition);
          log::add(__CLASS__, 'debug', '  condition result : '.($conditionResult ? "true" : "false"));
      }
      else {
        log::add(__CLASS__, 'info', 'pas de condition');
      }
      if (!$conditionResult) {
        log::add(__CLASS__, 'info', 'condition indique arrêt');
        return;
      }

      // Lumière éteinte : on ne fait rien
      if ($state == 1) {
        log::add(__CLASS__, 'info', 'lampe allumée');
        $cmd->execCmd(array('slider' => $temp_color, 'transition' => 300));
      } else {
        log::add(__CLASS__, 'info', 'lampe éteinte');
      }
    } catch (Exception $ex) {
      log::add(__CLASS__, 'error', ' erreur: ' . $ex->getMessage());
    }
  }

  /**
   * Calculer la hauteur du soleil
   * @return {float} Hauteur du soleil
   */
  private function computeSunElevation(): float
  {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    $latitude = config::bykey('info::latitude');
    $longitude = config::bykey('info::longitude');
    $altitude = config::bykey('info::altitude');

    if (!isset($latitude) || !isset($longitude) || !isset($altitude)) {
      log::add(__CLASS__, 'error', ' latitude, longitude ou altitude non renseigné');
      throw new Exception();
    }

    $SD = new SolarData\SolarData();
    $SD->setObserverPosition($latitude, $longitude, $altitude);
    $SD->setObserverDate(date('Y'), date('n'), date('j'));
    $SD->setObserverTime(date('G'), date('i'), date('s'));
    //ARGS : difference in seconds between the Earth rotation time and the Terrestrial Time (TT)/
    $SD->setDeltaTime(67);
    $SD->setObserverTimezone(date('Z') / 3600);

    /* ARGS : Observer mean pressure in Millibar */
    $SD->setObserverAtmosphericPressure(820);

    /* ARGS : Observer mean temperature in Celsius */
    $SD->setObserverAtmosphericTemperature(11.0);

    $SunPosition = $SD->calculate();
    $sunElevation = floatval(round($SunPosition->e0°, 2));
    log::add(__CLASS__, 'debug', ' sunElevation :' . $sunElevation);

    return floatval($sunElevation);
  }

  /**
   * Formule par rapport à l'élévation du soleil 
   *  Assez rouge en hiver car soleil par très haut
   *  https://keisan.casio.com/exec/system/1224682331
   * @param {int} $sunElevation Position du soleil en °
   * @return {int} Température de la couleur
   */
  private function computeTempColorBySunElevation($sunElevation): int
  {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__ . ' (test)');
    $correctedSunElevation = $sunElevation;
    if ($correctedSunElevation < 0) {
      $correctedSunElevation = 0;
    }
    if ($correctedSunElevation > 90) {
      $correctedSunElevation = 90;
    }
    // log::add(__CLASS__, 'debug', ' sunElevation corrigé :' . $correctedSunElevation);

    // Calcul de la température couleur
    $temp_color = intval(1000000 / (4791.67 - 3290.66 / (1 + 0.222 * $correctedSunElevation ** 0.81)));
    log::add(__CLASS__, 'debug', '  temp_color calculé SunPosition: ' . $temp_color);

    return $temp_color;
  }

  /**
   * Récupérer la commande lié à la température couleur de la lampe
   */
  public function getLampCommand(bool $initialisation = false): ?cmd
  {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    $cmd = null;
    // Obtenir la commande Temperature Couleur
    $temperature_color = $this->getConfiguration('temperature_color');
    $temperature_color = str_replace('#', '', $temperature_color);
    if ($temperature_color != '') {
      $cmd = cmd::byId($temperature_color);
      if ($cmd == null) {
        log::add(__CLASS__, 'error', '  Mauvaise temperature_color :' . $temperature_color);
        throw new Exception('Mauvaise temperature_color');
      } else {
        log::add(__CLASS__, 'info', 'lampe: ' . $cmd->getEqLogic()->getHumanName() . '[' . $cmd->getName() . ']');

        // Vérification du type generique
        $genericType = $cmd->getGeneric_type();
        log::add(__CLASS__, 'debug', '  getGenericType: ' . $genericType);
        if ($genericType != 'LIGHT_SET_COLOR_TEMP') {
          log::add(__CLASS__, 'error', '  Mauvaise commande pour la lampe : temperature_color');
          throw new Exception('Mauvaise commande pour la lampe : temperature_color');
        }
      }
    } else {
      if (!$initialisation) {
        log::add(__CLASS__, 'error', '  temperature_color non renseigné');
        throw new Exception('temperature_color non renseigné');
      }
    }

    return $cmd;
  }

  /**
   * Obtenir l'état de la lampe (allumée 1 ou éteint 0)
   */
  private function getLampState(): int
  {
    // Obtenir état de la lampe
    $state = 0;
    $lamp_state = $this->getConfiguration('lamp_state');
    $lamp_state = str_replace('#', '', $lamp_state);
    if ($lamp_state != '') {
      $cmd = cmd::byId($lamp_state);
      if ($cmd == null) {
        log::add(__CLASS__, 'error', ' Mauvaise lamp_state :' . $lamp_state);
        throw new Exception('Mauvaise lamp_state');
      } else {
        $state = boolval($cmd->execCmd()) ? 1 : 0;
        log::add(__CLASS__, 'debug', '  lamp_state: ' . $cmd->getEqLogic()->getHumanName() . '[' . $cmd->getName() . ']:' . $state);
      }
    } else {
      log::add(__CLASS__, 'error', ' lamp_state non renseigné');
      throw new Exception('lamp_state non renseigné');
    }

    return $state;
  }

  /*     * **********************Getteur Setteur*************************** */
}

class naturalLightCmd extends cmd
{
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
  public function execute($_options = array())
  {
    log::add('naturalLight', 'info', '*** ' . __FUNCTION__ . ' ***');

    if ($this->getLogicalId() == 'refresh') {
      $eqlogic = $this->getEqLogic(); //récupère l'éqlogic de la commande $this
      $eqlogic->computeLamp();
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}
