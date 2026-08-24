<?php

/**
 * -------------------------------------------------------------------------
 * resources plugin for GLPI
 * Copyright (C) 2015-2026 by the resources Development Team.
 *
 * https://github.com/InfotelGLPI/resources
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of resources.
 *
 * resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

use GlpiPlugin\Resources\LDAP;
use GlpiPlugin\Resources\LinkAd;
use GlpiPlugin\Resources\Resource;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$linkad = new LinkAd();

//from central
//update checklist
if (isset($_POST["add"])) {
    Session::checkRight(LinkAd::$rightname, CREATE);
    // LinkAd has no entities_id of its own, so the global right above is the only guard
    // here: scope the creation to the Resource it is attached to.
    Resource::checkOwnership($_POST["plugin_resources_resources_id"] ?? 0);
    $linkad->add($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    // LinkAd carries no entities_id of its own, so check($id, UPDATE) alone cannot scope
    // this to the caller's entity: resolve the owning Resource via
    // plugin_resources_resources_id and check the right on that instance instead.
    Resource::checkChildOwnership($linkad, $_POST["id"]);
    $linkad->update($_POST);
    $ldap = new LDAP();
    $ldap->getUserInformation($_POST["auth_id"]);
    Html::back();
} elseif (isset($_POST["createAD"])) {
    // Drives Active Directory account creation from arbitrary POST identity fields:
    // require the plugin write right before touching the directory or the DB, and scope it
    // to the Resource this account is created for (LinkAd carries no entities_id, so no
    // check() on it can enforce the entity boundary — see the "update" branch below).
    Session::checkRight(LinkAd::$rightname, CREATE);
    Resource::checkOwnership($_POST["plugin_resources_resources_id"] ?? 0);
    $ldap = new LDAP();
    $res = $ldap->createUserAD($_POST);
    if ($res) {
        $_POST["action_done"] = 1;
        $linkad->add($_POST);
        $fup = new ITILFollowup();

        $toadd = [
            'type' => "new",
            'items_id' => $_POST["ticket_id"],
            'itemtype' => 'Ticket',
            'is_private' => 1,
        ];

        $content = sprintf(
            __('%1$s %2$s have been added in the LDAP directory', 'resources'),
            $_POST["firstname"],
            $_POST["name"],
        );
        $toadd["content"] = htmlentities($content, ENT_NOQUOTES);

        $fup->add($toadd);
        $message = __('the user has been added to the LDAP directory', 'resources');
        Session::addMessageAfterRedirect($message, false, INFO);
    } else {
        $message = __('the user has not been added to the LDAP directory', 'resources');
        Session::addMessageAfterRedirect($message, false, ERROR);
    }
    Html::back();
} elseif (isset($_POST["updateAD"])) {
    // Drives Active Directory account modification: require the plugin write right, and
    // scope it to the Resource owning this LinkAd (see the "update" branch above for why
    // check($id, UPDATE) on LinkAd itself cannot enforce entity isolation).
    Session::checkRight(LinkAd::$rightname, UPDATE);
    $ldap = new LDAP();
    Resource::checkChildOwnership($linkad, $_POST['id']);
    $_POST["login"] = $linkad->getField("login");
    $res = $ldap->updateUserAD($_POST);
    if ($res[0]) {
        $_POST["action_done"] = 1;
        $linkad->update($_POST);
        $fup = new ITILFollowup();

        $toadd = [
            'type' => "new",
            'items_id' => $_POST["ticket_id"],
            'itemtype' => 'Ticket',
            'is_private' => 1,
        ];

        $content = sprintf(
            __('%1$s %2$s have been updated in the LDAP directory', 'resources'),
            $_POST["firstname"],
            $_POST["name"],
        );
        $content .= __("Data changed", 'resources') . " <br />";
        foreach ($res[1] as $key => $oldData) {
            $i = 1;
            $nb = count($oldData);
            $content .= $key . " : ";
            foreach ($oldData as $data) {
                if ($key == "accountexpires") {
                    $time = $ldap->ldapTimeToUnixTime($data);
                    $data = date('Y-m-d', $time);
                    $data = Html::convDate($data);
                }
                $content .= $data;
                if ($i < $nb) {
                    $content .= ", ";
                }
                $i++;
            }
            $content .= "<br />";
        }
        $toadd["content"] = htmlentities($content, ENT_NOQUOTES);

        $fup->add($toadd);
        $message = __('the user has been updated to the LDAP directory', 'resources');
        Session::addMessageAfterRedirect($message, false, INFO);
    } else {
        $message = __('the user has not been updated to the LDAP directory', 'resources');
        Session::addMessageAfterRedirect($message, false, ERROR);
    }
    Html::back();
} elseif (isset($_POST["disableAD"])) {
    // Drives Active Directory account disabling/move: require the plugin write right, and
    // scope it to the Resource owning this LinkAd (see the "update" branch above for why
    // check($id, UPDATE) on LinkAd itself cannot enforce entity isolation).
    Session::checkRight(LinkAd::$rightname, UPDATE);
    $ldap = new LDAP();
    Resource::checkChildOwnership($linkad, $_POST['id']);
    $_POST["login"] = $linkad->getField("login");
    $res = $ldap->disableUserAD($_POST);
    if ($res) {
        $_POST["action_done"] = 1;
        $linkad->update($_POST);
        $fup = new ITILFollowup();

        $toadd = [
            'type' => "new",
            'items_id' => $_POST["ticket_id"],
            'itemtype' => 'Ticket',
            'is_private' => 1,
        ];

        $content = sprintf(
            __('%1$s %2$s have been disabled and moved in the LDAP directory', 'resources'),
            $_POST["firstname"],
            $_POST["name"],
        );
        $toadd["content"] = htmlentities($content, ENT_NOQUOTES);

        $fup->add($toadd);
        $message = __('the user has been disabled and moved to the LDAP directory', 'resources');
        Session::addMessageAfterRedirect($message, false, INFO);
    } else {
        $message = __('the user has not been disabled and moved to the LDAP directory', 'resources');
        Session::addMessageAfterRedirect($message, false, ERROR);
    }
    Html::back();
}
