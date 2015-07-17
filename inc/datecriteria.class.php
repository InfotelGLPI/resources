<?php

/*
 * @version $Id: datecriteria.php 480 2012-11-09 tynet $
 -------------------------------------------------------------------------
 Resources plugin for GLPI
 Copyright (C) 2006-2012 by the Resources Development Team.

 https://forge.indepnet.net/projects/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Resources.

 Resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

//Criteria which allows to select a date
class PluginResourcesDateCriteria extends PluginReportsAutoCriteria {

   function __construct($report, $name='date',$sql_field='', $label='') {

      parent::__construct($report, $name, $sql_field, $label);

      $this->addCriteriaLabel($this->getName(),($label ? $label : __('Date')));
   }


   public function setDate($date) {
      $this->addParameter($this->getName(), $date);
   }


   public function getDate() {

      $date = $this->getParameter($this->getName());

      return $date;
   }


   public function setDefaultValues() {

      $this->setDate(date("Y-m-d"));
   }


   public function displayCriteria() {

      $this->getReport()->startColumn();
      echo $this->getCriteriaLabel($this->getName()).'&nbsp;:';
      $this->getReport()->endColumn();

      $this->getReport()->startColumn();
      Html::showDateFormItem($this->getName(), $this->getDate(), false);
      $this->getReport()->endColumn();

   }

   function getSubName() {
      //TODO
      global $LANG;

      $date = $this->getDate();
      $title = $this->getCriteriaLabel($this->getName());

      if (empty($title) && isset($LANG['plugin_reports']['subname'][$this->getName()])) {
         $title = $LANG['plugin_reports']['subname'][$this->getName()];
      }

      return $title . ' (' . Html::convDate($date) . ')';
   }

}
?>