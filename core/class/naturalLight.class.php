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
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  */
  public static function cron()
  {
    log::add(__CLASS__, 'debug', '*** ' . __FUNCTION__ . ' ***');

    foreach (eqLogic::byType(__CLASS__, true) as $light) {
      if ($light->getIsEnable() == 1) {
        log::add(__CLASS__, 'info', ' > cron sur ' . $light->getHumanName());
        $light->computeLamp();
      }
    }
  }

  /**
   * Fonction appelée par Listener
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
    // log::add(__CLASS__, 'debug', 'getListener');

    return listener::byClassAndFunction(__CLASS__, 'pullRefresh', array('id' => $this->getId()));
  }

  private function removeListener()
  {
    // log::add(__CLASS__, 'debug', 'remove Listener');

    $listener = $this->getListener();
    if (is_object($listener)) {
      $listener->remove();
    }
  }

  private function setListener()
  {
    // log::add(__CLASS__, 'debug', 'setListener');

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
    log::add(__CLASS__, 'info', 'save sur : ' . $this->getHumanName());

    // Mise à jour sous info
    $cmdLampe = $this->getLampTemperatureCommand(true);
    if ($cmdLampe != null) {
      log::add(__CLASS__, 'debug', '  cmdLampe renseigné');

      // valeur temperature_enable par défaut
      if ($this->getConfiguration('temperature_enable') == '') {
        log::add(__CLASS__, 'debug', '  temperature_enable non renseigné');
        $this->setConfiguration('temperature_enable', 1);
        log::add(__CLASS__, 'debug', '  Value devient=' . $this->getConfiguration('temperature_enable'));
      } else {
        log::add(__CLASS__, 'debug', '  Value=' . $this->getConfiguration('temperature_enable'));
      }
      $this->setMinMaxValueConfigurationTemperatureColor($cmdLampe);

      // Récupération condition
      $condition = $this->getConfiguration('condition');
      if ($condition != '') {
        $this->setConfiguration('temperatureCondition', $condition);
      }
    }
    unset($cmdLampe);

    $cmdLampe = $this->getLampBrightnessCommand(true);
    if ($cmdLampe != null) {
      // // Activation
      // $this->setConfiguration('brightness_enable', 1);
      // valeur min-max
      $this->setMinMaxValueConfigurationBrightness($cmdLampe);
      // heure soir
      if ($this->getConfiguration('eveningHour') == '') {
        $this->setConfiguration('eveningHour', '21:00');
      }
      // durée soir
      if ($this->getConfiguration('eveningDuration') == '') {
        $this->setConfiguration('eveningDuration', '60');
      }
      // heure matin
      if ($this->getConfiguration('morningHour') == '') {
        $this->setConfiguration('morningHour', '06:30');
      }
      // durée matin
      if ($this->getConfiguration('morningDuration') == '') {
        $this->setConfiguration('morningDuration', '60');
      }

      // Vérifier si heures correctes
      // Vérifier Heure matin < Heure soir
    }
    unset($cmdLampe);
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

    // brightness
    $brightness = $this->getCmd(null, 'brightness');
    if (!is_object($brightness)) {
      $brightness = new naturalLightCmd();
      $brightness->setLogicalId('brightness');
      $brightness->setName(__('Luminosité', __FILE__));
      $brightness->setIsVisible(1);
      $brightness->setIsHistorized(0);
    }
    $brightness->setEqLogic_id($this->getId());
    $brightness->setType('info');
    $brightness->setSubType('numeric');
    $brightness->setGeneric_type('GENERIC_INFO');

    $brightness->save();
    unset($brightness);

    // Vérification
    log::add(__CLASS__, 'debug', '  Vérification');
    $isValid = $this->checkAllConfiguration();
    // if (!$isValid) {
    //   throw new Exception('Paramétrage invalide. Voir les logs');
    // }
    
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
   * Check complet
   * @return true si le paramétrage est correct, false sinon
   */
  public function checkAllConfiguration() : bool {
    // Vérification
    $isValid = $this->checkLampState();
    // if (!$isValid) {
    //   throw new Exception('Paramétrage invalide. Voir les logs');
    // }
    $isValid &= $this->checkTemperatureConfiguration();
    // if (!$isValid) {
    //   throw new Exception('Paramétrage invalide. Voir les logs');
    // }
    $isValid &= $this->checkBrightnessConfiguration();
    // if (!$isValid) {
    //   throw new Exception('Paramétrage invalide. Voir les logs');
    // }

    return $isValid;
  }

  /**
   * Vérifier le paramétrage lié à l'état de la lampe
   * En cas de non validité, le log indique l'erreur
   * @return true si le paramétrage est correct, false sinon
   */
  public function checkLampState() : bool {
    log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    $isValid = true;
    $messages = [];

    // lamp_state
    $cmdLampState = $this->getConfiguration('lamp_state');
    if ($cmdLampState == '') {
      array_push($messages, 'Commande non renseignée');
      $isValid = false;
    } else {
      $cmdLampState = str_replace('#', '', $cmdLampState);
      $cmdLampState = cmd::byId($cmdLampState);
      if (!is_object($cmdLampState)) {
        array_push($messages, 'Commande invalide');
        $isValid = false;
      } else {
        $genericType = $cmdLampState->getGeneric_type() ?? '(empty)';
        if ($genericType != 'LIGHT_STATE' && $genericType != 'LIGHT_STATE_BOOL') {
          array_push($messages, 'Mauvais type générique : ' . $genericType);
          $isValid = false;
        }
      }
    }

    // Gestion des erreurs
    foreach($messages as $message) {
      log::add(__CLASS__, 'error', '  Lampe état : ' . $message);
    }
    return $isValid;
  }

  /**
   * Vérifier le paramétrage lié à la couleur température
   * En cas de non validité, le log indique l'erreur
   * @return true si le paramétrage est correct, false sinon
   */
  public function checkTemperatureConfiguration() : bool {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    $isValid = true;
    $messages = [];

    // temperature_enable
    $isActivated = $this->getConfiguration('temperature_enable');
    if (!$isActivated) {
      return true;
    }
    
    // temperature_color
    $cmdTempColor = $this->getConfiguration('temperature_color');
    if ($cmdTempColor == '') {
      array_push($messages, 'Commande non renseignée');
      $isValid = false;
    } else {
      $cmdTempColor = str_replace('#', '', $cmdTempColor);
      $cmdTempColor = cmd::byId($cmdTempColor);
      if (!is_object($cmdTempColor)) {
        array_push($messages, 'Commande invalide');
        $isValid = false;
      } else {
        $genericType = $cmdTempColor->getGeneric_type() ?? '(empty)';
        if ($genericType != 'LIGHT_SET_COLOR_TEMP') {
          array_push($messages, 'Mauvais type générique : ' . $genericType);
          $isValid = false;
        }
      }
    }

    // minValue
    $minValue = $this->getConfiguration('minValue');
    $minValueDefault = $this->getConfiguration('minValueDefault');
    if ($minValue == '') {
      array_push($messages, 'minValue non renseignée');
      $isValid = false;
    } else if ( !is_numeric($minValue)){
      array_push($messages, 'minValue doit être un nombre');
      $isValid = false;
    } else if ($minValue < 0) {
      array_push($messages, 'minValue doit être un nombre positif');
      $isValid = false;
    } else if (isset($minValueDefault) &&
             is_numeric($minValueDefault) &&
             $minValue < $minValueDefault) {
      array_push($messages, 'minValue doit être un supérieure à la valeur minValueDefault');
      $isValid = false;
    }

    // maxValue
    $maxValue = $this->getConfiguration('maxValue');
    $maxValueDefault = $this->getConfiguration('maxValueDefault');
    if ($maxValue == '') {
      array_push($messages, 'maxValue non renseignée');
      $isValid = false;
    } else if ( !is_numeric($maxValue)){
      array_push($messages, 'maxValue doit être un nombre');
      $isValid = false;
    } else if ($maxValue < 0) {
      array_push($messages, 'maxValue doit être un nombre positif');
      $isValid = false;
    } else if (isset($maxValueDefault) &&
              is_numeric($maxValueDefault) &&
              $maxValue > $maxValueDefault) {
      array_push($messages, 'maxValue doit être un inférieure à la valeur maxValueDefault');
      $isValid = false;
    }

    // minValue & maxValue
    if ($isValid && $minValue >= $maxValue) {
      array_push($messages, 'minValue doit être inférieur à maxValue');
      $isValid = false;
    }

    // Gestion des erreurs
    foreach($messages as $message) {
      log::add(__CLASS__, 'error', '  Température Lampe : ' . $message);
    }
    return $isValid;
  }

  /**
   * Vérifier le paramétrage lié à la luminosité
   * En cas de non validité, le log indique l'erreur
   * @return true si le paramétrage est correct, false sinon
   */
  public function checkBrightnessConfiguration() : bool {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    $isValid = true;
    $messages = [];

    // temperature_enable
    $isActivated = $this->getConfiguration('brightness_enable');
    if (!$isActivated) {
      return true;
    }
    
    // brightness
    $cmdBrightnessColor = $this->getConfiguration('brightness');
    if ($cmdBrightnessColor == '') {
      array_push($messages, 'Commande non renseignée');
      $isValid = false;
    } else {
      $cmdBrightnessColor = str_replace('#', '', $cmdBrightnessColor);
      $cmdBrightnessColor = cmd::byId($cmdBrightnessColor);
      if (!is_object($cmdBrightnessColor)) {
        array_push($messages, 'Commande invalide');
        $isValid = false;
      } else {
        $genericType = $cmdBrightnessColor->getGeneric_type() ?? '(empty)';
        if ($genericType != 'LIGHT_SLIDER') {
          array_push($messages, 'Mauvais type générique : ' . $genericType);
          $isValid = false;
        }
      }
    }

    // minValue
    $minValue = $this->getConfiguration('minBrightnessValue');
    $minValueDefault = $this->getConfiguration('minBrightnessValueDefault');
    if ($minValue == '') {
      array_push($messages, 'minValue non renseignée');
      $isValid = false;
    } else if ( !is_numeric($minValue)){
      array_push($messages, 'minValue doit être un nombre');
      $isValid = false;
    } else if ($minValue < 0) {
      array_push($messages, 'minValue doit être un nombre positif');
      $isValid = false;
    } else if (isset($minValueDefault) &&
             is_numeric($minValueDefault) &&
             $minValue < $minValueDefault) {
      array_push($messages, 'minValue doit être un supérieure à la valeur minValueDefault');
      $isValid = false;
    }

    // maxValue
    $maxValue = $this->getConfiguration('maxBrightnessValue');
    $maxValueDefault = $this->getConfiguration('maxBrightnessValueDefault');
    if ($maxValue == '') {
      array_push($messages, 'maxValue non renseignée');
      $isValid = false;
    } else if ( !is_numeric($maxValue)){
      array_push($messages, 'maxValue doit être un nombre');
      $isValid = false;
    } else if ($maxValue < 0) {
      array_push($messages, 'maxValue doit être un nombre positif');
      $isValid = false;
    } else if (isset($maxValueDefault) &&
              is_numeric($maxValueDefault) &&
              $maxValue > $maxValueDefault) {
      array_push($messages, 'maxValue doit être un inférieure à la valeur maxValueDefault');
      $isValid = false;
    }

    // minValue & maxValue
    if ($isValid && $minValue >= $maxValue) {
      array_push($messages, 'minValue doit être inférieur à maxValue');
      $isValid = false;
    }

    // morningDuration
    $duration = $this->getConfiguration('morningDuration');
    if ($duration == '') {
      array_push($messages, 'durée matin doit être renseigné');
      $isValid = false;
    } else if (!is_numeric($duration)) {
      array_push($messages, 'durée matin doit être un nombre');
      $isValid = false;
    } else if ($duration < 0) {
      array_push($messages, 'durée matin doit être un nombre positif');
      $isValid = false;
    } else if ($duration > 1440) {
      array_push($messages, 'durée matin doit être un nombre raisonnable');
      $isValid = false;
    }

    // eveningDuration
    $duration = $this->getConfiguration('eveningDuration');
    if ($duration == '') {
      array_push($messages, 'durée soir doit être renseigné');
      $isValid = false;
    } else if (!is_numeric($duration)) {
      array_push($messages, 'durée soir doit être un nombre');
      $isValid = false;
    } else if ($duration < 0) {
      array_push($messages, 'durée soir doit être un nombre positif');
      $isValid = false;
    } else if ($duration > 1440) {
      array_push($messages, 'durée soir doit être un nombre raisonnable');
      $isValid = false;
    }

    // Gestion des erreurs
    foreach($messages as $message) {
      log::add(__CLASS__, 'error', '  Luminosité Lampe : ' . $message);
    }
    return $isValid;
  }

  public function computeLamp()
  {
    log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    try {
      // Optimisation : Voir s'il faut calculer la couleur et l'élévation
      $cmdSunElevation = $this->getCmd(null, 'sun_elevation');
      // Récupérer les commandes de la lampe
      $cmdTempColor = $this->getCmd(null, 'temperature_color');
      $cmdBrightness = $this->getCmd(null, 'brightness');
      $cmdState = $this->getLampState();

      if (
        $cmdState === 0 &&
        !$cmdSunElevation->getIsHistorized() &&
        !$cmdBrightness->getIsHistorized() &&
        !$cmdTempColor->getIsHistorized()
      ) {
        // Aucun interêt de faire des calculs
        return;
      }

      // Calculer Sun elevation
      $sunElevation = $this->computeSunElevation();
      // set Sun Elevation value
      $cmdSunElevation->event($sunElevation);

      $activated = $this->getConfiguration('brightness_enable');
      log::add(__CLASS__, 'debug', '  Brightness activation:' . $activated);
      if ($activated == 0) {
        log::add(__CLASS__, 'debug', '  Luminosité non activé indique arrêt');
      } else {
        // Calcul pour l'historique
        $brightness = $this->computeBrightness();
        $brightness = $this->computeBrightnessByLimit($brightness);
        // set brightness
        $cmdBrightness->event($brightness);

        // Gestion de la brightnessCondition
        $condition = $this->getConfiguration('brightnessCondition');
        $conditionResult = $this->evaluateCondition($condition);
        if (!$conditionResult) {
          log::add(__CLASS__, 'info', 'condition brightness indique arrêt');
        } else {
          // Lumière éteinte : on ne fait rien
          if ($cmdState == 1) {
            log::add(__CLASS__, 'info', 'lampe allumée');            

            // Executer brightness
            $cmd = $this->getLampBrightnessCommand();

            // set brightness value
            // $cmd->execCmd($brightness);
            $cmd->execCmd(array('slider' => $brightness, 'transition' => 300));
          } else {
            log::add(__CLASS__, 'info', 'lampe éteinte');
          }
        }
      }

      if (
        $cmdState === 0 &&
        !$cmdBrightness->getIsHistorized() &&
        !$cmdTempColor->getIsHistorized()
      ) {
        // Aucun interêt de faire des calculs
        return;
      }

      $activated = $this->getConfiguration('temperature_enable');
      log::add(__CLASS__, 'debug', '  Temperature activation:' . $activated);
      if ($activated == 0) {
        log::add(__CLASS__, 'debug', '  Temperature non activé indique arrêt');
      } else {
        // Calcul pour l'historique

        // Calcul de Température Couleur sur SunElevation
        $temp_color = $this->computeTempColorBySunElevation($sunElevation);

        // Plugin Ikea en %
        if (
          $this->getConfiguration('minValueDefault') == 0 &&
          $this->getConfiguration('maxValueDefault') == 100
        ) {
          $temp_color = $this->computeTempColorForPercent($temp_color);
        }

        // Plugin inconnu en Kelvin
        if (
          $this->getConfiguration('minValueDefault') > 500 &&
          $this->getConfiguration('maxValueDefault') > 500
        ) {
          $temp_color = $this->computeTempColorForKelvin($temp_color);
        }

        $cmd = $this->getLampTemperatureCommand();

        // Calcul de la température couleur gérable par l'équipement
        $temp_color = $this->computeTempColorByLimit($temp_color);
        log::add(__CLASS__, 'info', 'température couleur: ' . $temp_color);

        // set temp_color value
        $cmdTempColor->event($temp_color);
      
        // Gestion de la condition
        $condition = $this->getConfiguration('condition');
        $conditionResult = $this->evaluateCondition($condition);
        if (!$conditionResult) {
          log::add(__CLASS__, 'info', 'condition Température couleur indique arrêt');
        } else {
          // Lumière éteinte : on ne fait rien
          if ($cmdState == 1) {
            log::add(__CLASS__, 'info', 'lampe allumée');
            $cmd->execCmd(array('slider' => $temp_color, 'transition' => 300));
          } else {
            log::add(__CLASS__, 'info', 'lampe éteinte');
          }
        }
      }
    } catch (Exception $ex) {
      log::add(__CLASS__, 'error', ' erreur: ' . $ex->getMessage());
    }
  }

  /**
   * Initialisation minValue et maxValue 
   * de Température Couleur
   * en prenant les limites de la lampes
   * @param cmd Commande de la lampe pour Température Couleur
   */
  private function setMinMaxValueConfigurationTemperatureColor(cmd $cmdLampe)
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

  /**
   * Initialisation minValue et maxValue
   * de  Luminosité 
   * en prenant les limites de la lampes
   * @param cmd Commande de la lampe pour Luminosité
   */
  private function setMinMaxValueConfigurationBrightness(cmd $cmdLampe)
  {
    //log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    if ($cmdLampe == null) {
      //log::add(__CLASS__, 'debug', '  commande Lamp pas encore renseignée');
      return;
    }

    // MinValue
    $minValue = $this->getConfiguration('minBrightnessValue');
    $minValueDefault = $cmdLampe->getConfiguration('minValue');
    if ($minValueDefault == 0) {
      // 0 signifie éteint
      $minValueDefault = 1;
    }
    if ($minValue === '') {
      log::add(__CLASS__, 'debug', '  minValue non initialisé');
      log::add(__CLASS__, 'debug', '  minValue devient: ' . $minValueDefault);
      $this->setConfiguration('minBrightnessValue', $minValueDefault);
    }
    $this->setConfiguration('minBrightnessValueDefault', $minValueDefault);

    // MaxValue
    $maxValue = $this->getConfiguration('maxBrightnessValue');
    $maxValueDefault = $cmdLampe->getConfiguration('maxValue');
    if ($maxValue === '') {
      log::add(__CLASS__, 'debug', '  maxValue non initialisé');
      log::add(__CLASS__, 'debug', '  maxValue devient: ' . $maxValueDefault);
      $this->setConfiguration('maxBrightnessValue', $maxValueDefault);
    }
    $this->setConfiguration('maxBrightnessValueDefault', $maxValueDefault);
  }

  /**
   * Calculer la hauteur du soleil
   * @return {float} Hauteur du soleil
   */
  private function computeSunElevation(): float
  {
    log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

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
    log::add(__CLASS__, 'debug', '  sunElevation :' . $sunElevation);

    return floatval($sunElevation);
  }

  /**
   * Formule par rapport à l'élévation du soleil 
   *  Assez rouge en hiver car soleil par très haut
   *  https://keisan.casio.com/exec/system/1224682331
   * @param {int} $sunElevation Position du soleil en °
   * @return {int} Température de la couleur en mired
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
   * Conversion dela Température Couleur de mired en pourcentage
   * @param temp_color Température couleur en mired
   * @return Température couleur en pourcentage
   */
  private function computeTempColorForPercent($temp_color): int
  {
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

    return $temp_color;
  }

  /**
   * Conversion dela Température Couleur de mired en Kelvin
   * @param temp_color Température couleur en Kelvin
   * @return Température couleur en Kelvin
   */
  private function computeTempColorForKelvin($temp_color): int
  {
    log::add(__CLASS__, 'debug', '  gestion en Kelvin');

    $temp_color = intval(1000000 / $temp_color);
    log::add(__CLASS__, 'debug', '  temp_color corrigé :' . $temp_color . 'K');

    return $temp_color;
  }

  private function computeTempColorByLimit($temp_color): int
  {
    // Recherche de la configuration
    $minValue = $this->getConfiguration('minValue');
    $maxValue = $this->getConfiguration('maxValue');

    // Calcul de la température couleur gérable par l'équipement
    if ($temp_color > $maxValue) {
      $temp_color = $maxValue;
    }
    if ($temp_color < $minValue) {
      $temp_color = $minValue;
    }
    log::add(__CLASS__, 'info', 'température couleur: ' . $temp_color);

    return $temp_color;
  }

  /**
   * Evaluation d'une condition
   * @param condition Condition à évaluer
   * @return True si la condition est vide ou valide, false sinon
   */
  private function evaluateCondition($condition): bool
  {
    log::add(__CLASS__, 'debug', '  condition : ' . $condition);
    $conditionResult = true;
    if ($condition != '') {
      // Evaluation
      $conditionResult = jeedom::evaluateExpression($condition);
      log::add(__CLASS__, 'debug', '  condition result : ' . ($conditionResult ? "true" : "false"));
    } else {
      log::add(__CLASS__, 'info', 'pas de condition');
    }

    return $conditionResult;
  }

  /**
   * Calculer Luminosité selon la période de la journée
   */
  private function computeBrightness(): int
  {
    log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);
    
    // Initialisation Timestamp
    try {
      $configs = config::byKeys(array('timezone', 'log::level'));
      if (isset($configs['timezone'])) {
        date_default_timezone_set($configs['timezone']);
      }
    } catch (Exception $e) {
    } catch (Error $e) {
    }

    $brightness = 0;

    // get configuration
    $morningHour = $this->getConfiguration('morningHour');
    $morningDuration = $this->getConfiguration('morningDuration');
    $eveningHour = $this->getConfiguration('eveningHour');
    $eveningDuration = $this->getConfiguration('eveningDuration');

    $minBrightness = $this->getconfiguration('minBrightnessValue');
    $maxBrightness = $this->getconfiguration('maxBrightnessValue');

    // calcul
    $todayMorningTime = strtotime('today ' . $morningHour . ':00');
    $todayMorningEndTime = strtotime('+' . $morningDuration . 'minutes', $todayMorningTime);
    $todayEveningTime = strtotime('today ' . $eveningHour . ':00');
    $todayEveningEndTime = strtotime('+' . $eveningDuration . 'minutes', $todayEveningTime);
    $tomorrowMorningTime = strtotime('tomorrow ' . $morningHour . ':00');
    $now = time();

    // Log pour debug
    log::add(__CLASS__, 'debug', '  todayMorningTime :' . date("Y-m-d H:i", $todayMorningTime) . ' (' . $todayMorningTime . ')');
    log::add(__CLASS__, 'debug', '  todayMorningEndTime :' . date("Y-m-d H:i", $todayMorningEndTime) . ' (' . $todayMorningEndTime . ')');
    log::add(__CLASS__, 'debug', '  todayEveningTime :' . date("Y-m-d H:i", $todayEveningTime) . ' (' . $todayEveningTime . ')');
    log::add(__CLASS__, 'debug', '  todayEveningEndTime :' . date("Y-m-d H:i", $todayEveningEndTime) . ' (' . $todayEveningEndTime . ')');
    log::add(__CLASS__, 'debug', '  tomorrowMorningTime :' . date("Y-m-d H:i", $tomorrowMorningTime) . ' (' . $tomorrowMorningTime . ')');
    log::add(__CLASS__, 'debug', '  now :' . $now);

    // calcul Brightness
    if ($now > $todayMorningEndTime && $now < $todayEveningTime) {
      // jour
      log::add(__CLASS__, 'debug', '  période: jour');

      $brightness = $maxBrightness;
    } else if ($now < $todayMorningTime || $now > $todayEveningEndTime) {
      // nuit
      log::add(__CLASS__, 'debug', '  période: nuit');

      $brightness = $minBrightness;
    } else if ($now >= $todayMorningTime && $now <= $todayMorningEndTime) {
      // matinée
      log::add(__CLASS__, 'debug', '  période: matinée');
      $brightness = intval(($now - $todayMorningTime)
        * ($maxBrightness - $minBrightness)
        / ($morningDuration * 60))
        + intval($minBrightness);
    } else if ($now >= $todayEveningTime && $now <= $todayEveningEndTime) {
      // soirée
      log::add(__CLASS__, 'debug', '  période: soirée');
      $brightness = intval(($todayEveningEndTime - $now)
        * ($maxBrightness - $minBrightness)
        / ($eveningDuration * 60))
        + intval($minBrightness);
    }

    log::add(__CLASS__, 'debug', '  brightness: ' . $brightness);

    return $brightness;
  }

  private function computeBrightnessByLimit($brightness): int
  {
    log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);
    
    // Recherche de la configuration
    $minBrightnessValue = $this->getConfiguration('minBrightnessValue');
    $maxBrightnessValue = $this->getConfiguration('maxBrightnessValue');

    // Calcul de la température couleur gérable par l'équipement
    if ($brightness > $maxBrightnessValue) {
      $brightness = $maxBrightnessValue;
    }
    if ($brightness < $minBrightnessValue) {
      $brightness = $minBrightnessValue;
    }
    log::add(__CLASS__, 'info', '  brightness corrigé: ' . $brightness);

    return $brightness;
  }

  /**
   * Récupérer la commande lié à la température couleur de la lampe
   */
  public function getLampTemperatureCommand(bool $initialisation = false): ?cmd
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
        $genericType = $cmd->getGeneric_type() ?? '(empty)';
        if ($genericType != 'LIGHT_SET_COLOR_TEMP') {
          log::add(__CLASS__, 'debug', '  getGenericType: ' . $genericType);
          log::add(__CLASS__, 'warning', '  Mauvais type générique pour la commande : temperature_color');
          // throw new Exception('Mauvaise commande pour la lampe : temperature_color');
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
   * Récupérer la commande lié à la température couleur de la lampe
   */
  public function getLampBrightnessCommand(bool $initialisation = false): ?cmd
  {
    // log::add(__CLASS__, 'debug', 'fonction: ' . __FUNCTION__);

    $cmd = null;
    // Obtenir la commande Temperature Couleur
    $brightness = $this->getConfiguration('brightness');
    $brightness = str_replace('#', '', $brightness);
    if ($brightness != '') {
      $cmd = cmd::byId($brightness);
      if ($cmd == null) {
        log::add(__CLASS__, 'error', '  Mauvaise brightness :' . $brightness);
        throw new Exception('Mauvaise brightness');
      } else {
        log::add(__CLASS__, 'info', 'lampe: ' . $cmd->getEqLogic()->getHumanName() . '[' . $cmd->getName() . ']');

        // Vérification du type generique
        $genericType = $cmd->getGeneric_type() ?? '(empty)';
        if ($genericType != 'LIGHT_SLIDER') {
          log::add(__CLASS__, 'debug', '  getGenericType: ' . $genericType);
          log::add(__CLASS__, 'warning', '  Mauvais type générique pour la commande : brightness');
          //throw new Exception('Mauvaise commande pour la lampe : brightness');
        }
      }
    } else {
      if (!$initialisation) {
        log::add(__CLASS__, 'error', '  brightness non renseigné');
        throw new Exception('brightness non renseigné');
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
    */
  public function dontRemoveCmd()
  {
    return true;
  }

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
