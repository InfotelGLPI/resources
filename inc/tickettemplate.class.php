<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesTicketTemplate
 */
class PluginResourcesTicketTemplate extends CommonDBTM {

	static $rightname = 'plugin_resources';

	public $dohistory = true;

	const RESOURCES_TICKETTEMPLATE_CREATE       = 1 ;
	const RESOURCES_TICKETTEMPLATE_UPDATE       = 2;
	const RESOURCES__TICKETTEMPLATE_LEAVE       = 3;

	function showTemplate($id)
	{
		$datas = $this->find(['id' => $id]);

		$users_template = new PluginResourcesTicketTemplateUser();
		$datas_users = $users_template->find(['plugin_resources_tickettemplates_id' => $id]);

		$groups_template = new PluginResourcesGroupTicketTemplate();
		$datas_groups = $groups_template->find(['plugin_resources_tickettemplates_id' => $id]);

		foreach ($datas_users as $datas_user) {
			switch ($datas_user['type']) {
				case 1:
					if (isset($datas['users_id_requester'])) {
						array_push($datas['users_id_requester'], $datas_user['users_id']);
					} else {
						$datas['users_id_requester'] = [$datas_user['users_id']];
					}
					break;
				case 2:
					if (isset($datas['users_id_tech'])) {
						array_push($datas['users_id_tech'], $datas_user['users_id']);
					} else {
						$datas['users_id_tech'] = [$datas_user['users_id']];
					}
					break;
				case 3:
					if (isset($datas['users_id_observer'])) {
						array_push($datas['users_id_observer'], $datas_user['users_id']);
					} else {
						$datas['users_id_observer'] = [$datas_user['users_id']];
					}
					break;
				default :
					break;
			}
		}

		foreach ($datas_groups as $datas_group) {
			switch ($datas_group['type']) {
				case 1:
					if (isset($datas['group_id_requester'])) {
						$datas['group_id_requester'][] = $datas_group['groups_id'];
					} else {
						$datas['group_id_requester'][] = $datas_group['groups_id'];
					}
					break;
				case 2:
					if (isset($datas['group_id_tech'])) {
						array_push($datas['group_id_tech'], $datas_group['groups_id']);
					} else {
						$datas['group_id_tech'] = [$datas_group['groups_id']];
					}
					break;
				case 3:
					if (isset($datas['group_id_observer'])) {
						array_push($datas['group_id_observer'], $datas_group['groups_id']);
					} else {
						$datas['group_id_observer'] = [$datas_group['groups_id']];
					}
					break;
				default :
					break;
			}
		}

		TemplateRenderer::getInstance()->display('@resources/tickettemplate.html.twig', [
			'items' => $datas[$id] ?? '',
			'items_g_u' => $datas ?? '',
		]);
	}

	public function insertTemplate($input)
	{
		// Type Value
		// 1 => CREATE
		// 2 => Update
		// 3 => Leave

		global $DB;

		$last_id = $this->add([
			'name' => $input['template_name'],
			'entities_id' => $_SESSION['glpiactive_entity'],
			'template_type' => $input['template_type'],
			'type' => $input['type'],
			'title' => $input['name'],
			'content' => $input['description'],
			'itilcategories_id' => $input['itilcategories_id'] ?? 0,
		]);

		if($last_id != 0){
			if(isset($input['groups_id_requester'])){
				$group_ticket = new PluginResourcesGroupTicketTemplate();
				foreach ($input['groups_id_requester'] as $item) {
					$group_ticket->add([
						'plugin_resources_tickettemplates_id' => $last_id,
						'groups_id' => $item,
						'type' => CommonITILActor::REQUESTER,
					]);
				}
			}


			if(isset($input['users_id_requester'])){
				$ticketUser = new PluginResourcesTicketTemplateUser();
				foreach ($input['users_id_requester'] as $item) {
					$ticketUser->add([
						'plugin_resources_tickettemplates_id' => $last_id,
						'users_id' => $item,
						'type' => CommonITILActor::REQUESTER,
					]);
				}
			}

			if(isset($input['groups_id_observer'])){
				$group_ticket = new PluginResourcesGroupTicketTemplate();
				foreach ($input['groups_id_observer'] as $item) {
					$group_ticket->add([
						'plugin_resources_tickettemplates_id' => $last_id,
						'groups_id' => $item,
						'type' => CommonITILActor::OBSERVER,
					]);
				}
			}

			if(isset($input['users_id_observer'])){
				$ticketUser = new PluginResourcesTicketTemplateUser();
				foreach ($input['users_id_observer'] as $item) {
					$ticketUser->add([
						'plugin_resources_tickettemplates_id' => $last_id,
						'users_id' => $item,
						'type' => CommonITILActor::OBSERVER,
					]);
				}
			}

			if(isset($input['groups_id_tech'])){
				$group_ticket = new PluginResourcesGroupTicketTemplate();
				foreach ($input['groups_id_tech'] as $item) {
					$group_ticket->add([
						'plugin_resources_tickettemplates_id' => $last_id,
						'groups_id' => $item,
						'type' => CommonITILActor::ASSIGN,
					]);
				}
			}

			if(isset($input['users_id_tech'])){
				$ticketUser = new PluginResourcesTicketTemplateUser();
				foreach ($input['users_id_tech'] as $item) {
					$ticketUser->add([
						'plugin_resources_tickettemplates_id' => $last_id,
						'users_id' => $item,
						'type' => CommonITILActor::ASSIGN,
					]);
				}
			}
		}
	}

	public function getListForDropdown($type)
	{
		$datas_request = $this->find(['template_type' => $type]);
		$datas = [Dropdown::EMPTY_VALUE];


		foreach ($datas_request as $item) {
			$datas[$item['id']] = $item['name'];
		}

		return $datas;
	}

	public function updateTemplate(array $datas, mixed $id)
	{
		global $DB;
		$this->update([
			'id' => $datas['id'],
			'name' => $datas['template_name'],
			'template_type' => $datas['template_type'],
			'type' => $datas['type'],
			'title' => $datas['name'],
			'content' => $datas['description'],
			'itilcategories_id' => $datas['itilcategories_id'],
		]);

		if(isset($datas['groups_id_requester'])){

			$DB->delete(
				'glpi_plugin_resources_grouptickettemplates',
				[
					'plugin_resources_tickettemplates_id' => $id,
					'type' => CommonITILActor::REQUESTER
				]
			);

			$group_ticket = new PluginResourcesGroupTicketTemplate();

			foreach ($datas['groups_id_requester'] as $item) {
				$group_ticket->add([
					'plugin_resources_tickettemplates_id' => $id,
					'groups_id' => $item,
					'type' => CommonITILActor::REQUESTER,
				]);
			}
		}

		$DB->delete(
			'glpi_plugin_resources_tickettemplateusers',
			[
				'plugin_resources_tickettemplates_id' => $id,
				'type' => CommonITILActor::REQUESTER
			]
		);

		if(isset($datas['users_id_requester'])){

			$ticketUser = new PluginResourcesTicketTemplateUser();

			foreach ($datas['users_id_requester'] as $item) {
				$ticketUser->add([
					'plugin_resources_tickettemplates_id' => $id,
					'users_id' => $item,
					'type' => CommonITILActor::REQUESTER,
				]);
			}
		}

		if(isset($datas['groups_id_observer'])){

			$DB->delete(
				'glpi_plugin_resources_grouptickettemplates',
				[
					'plugin_resources_tickettemplates_id' => $id,
					'type' => CommonITILActor::OBSERVER
				]
			);

			$group_ticket = new PluginResourcesGroupTicketTemplate();

			foreach ($datas['groups_id_observer'] as $item) {
				$group_ticket->add([
					'plugin_resources_tickettemplates_id' => $id,
					'groups_id' => $item,
					'type' => CommonITILActor::OBSERVER,
				]);
			}
		}

		$DB->delete(
			'glpi_plugin_resources_tickettemplateusers',
			[
				'plugin_resources_tickettemplates_id' => $id,
				'type' => CommonITILActor::OBSERVER
			]
		);

		if(isset($datas['users_id_observer'])){

			$ticketUser = new PluginResourcesTicketTemplateUser();

			foreach ($datas['users_id_observer'] as $item) {
				$ticketUser->add([
					'plugin_resources_tickettemplates_id' => $id,
					'users_id' => $item,
					'type' => CommonITILActor::OBSERVER,
				]);
			}
		}

		if(isset($datas['groups_id_tech'])){

			$DB->delete(
				'glpi_plugin_resources_grouptickettemplates',
				[
					'plugin_resources_tickettemplates_id' => $id,
					'type' => CommonITILActor::ASSIGN
				]
			);

			$group_ticket = new PluginResourcesGroupTicketTemplate();

			foreach ($datas['groups_id_tech'] as $item) {
				$group_ticket->add([
					'plugin_resources_tickettemplates_id' => $id,
					'groups_id' => $item,
					'type' => CommonITILActor::ASSIGN,
				]);
			}
		}

		$DB->delete(
			'glpi_plugin_resources_tickettemplateusers',
			[
				'plugin_resources_tickettemplates_id' => $id,
				'type' => CommonITILActor::ASSIGN
			]
		);

		if(isset($datas['users_id_tech'])){

			$ticketUser = new PluginResourcesTicketTemplateUser();

			foreach ($datas['users_id_tech'] as $item) {
				$ticketUser->add([
					'plugin_resources_tickettemplates_id' => $id,
					'users_id' => $item,
					'type' => CommonITILActor::ASSIGN,
				]);
			}
		}
	}

}
