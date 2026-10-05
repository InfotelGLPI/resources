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

namespace GlpiPlugin\Resources\Tests\Integration;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Resources\ContractType;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Wizard;

/**
 * Renders every step of the resource creation wizard.
 *
 * GLPI 12 runs Twig in strict mode: reading a key the PHP side did not pass fails the whole
 * page ("Key ... does not exist"). Each step is rendered here for a resource being created
 * and for an existing one, so that a template reading a key a step does not provide is
 * caught by the CI instead of by the requester filling the form.
 */
class WizardStepsTest extends DbTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->login();
        // The plugin rights are only granted to the profile active when the plugin was
        // installed: give every right of the wizard to the session of the test.
        Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
        Profile::initProfile();
    }

    /**
     * Render a step and return its markup.
     */
    private function render(callable $step): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            $result = $step();
        } finally {
            // A step failing midway can leave the buffers it opened itself behind
            while (ob_get_level() > $level + 1) {
                ob_end_clean();
            }
            $html = (string) ob_get_clean();
        }
        $this->assertNotFalse($result, 'The step refused to render.');

        return $html;
    }

    /**
     * Resource filled by the second step, with its employee record.
     */
    private function createResource(): Resource
    {
        $contract_type = $this->createItem(ContractType::class, [
            'name'        => 'Wizard contract ' . mt_rand(),
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $resource = $this->createItem(Resource::class, [
            'name'                              => 'Wizard',
            'firstname'                         => 'Test',
            'entities_id'                       => $this->getTestRootEntity(true),
            'plugin_resources_contracttypes_id' => $contract_type->getID(),
            'users_id_recipient'                => \Session::getLoginUserID(),
            'withtemplate'                      => 0,
        ], ['withtemplate']);

        $this->createItem(Employee::class, [
            'plugin_resources_resources_id' => $resource->getID(),
        ]);

        return $resource;
    }

    public function testFirstStepRenders(): void
    {
        $html = $this->render(fn() => (new Wizard())->wizardFirstStep());

        $this->assertStringContainsString('name="second_step"', $html);
    }

    public function testSecondStepRendersForANewResource(): void
    {
        $html = $this->render(fn() => (new Wizard())->wizardSecondStep(0, [
            'withtemplate' => 0,
        ]));

        $this->assertStringContainsString('name="third_step"', $html);
        // Always on by default, as in the resource form
        $this->assertMatchesRegularExpression('/name="send_notification"[^>]*value="1"/', $html);
    }

    public function testSecondStepRendersForAnExistingResource(): void
    {
        $resource = $this->createResource();

        $html = $this->render(fn() => (new Wizard())->wizardSecondStep($resource->getID(), [
            'withtemplate' => 0,
        ]));

        $this->assertStringContainsString('name="third_step"', $html);
        $this->assertMatchesRegularExpression(
            '/name="plugin_resources_resources_id"[^>]*value="' . $resource->getID() . '"/',
            $html,
        );
    }

    public function testFollowingStepsRenderForAnExistingResource(): void
    {
        $resource = $this->createResource();
        $id       = $resource->getID();
        $wizard   = new Wizard();

        $steps = [
            'third'   => [fn() => $wizard->wizardThirdStep($id), 'four_step'],
            'fourth'  => [fn() => $wizard->wizardFourStep($id), 'five_step'],
            'fifth'   => [fn() => $wizard->wizardFiveStep($id), 'six_step'],
            'sixth'   => [fn() => $wizard->wizardSixStep($id), 'seven_step'],
            'seventh' => [fn() => $wizard->wizardSevenStep($id), 'eight_step'],
            'eighth'  => [fn() => $wizard->wizardEightStep($id), 'undo_eight_step'],
        ];

        foreach ($steps as $label => [$step, $next_button]) {
            $html = $this->render($step);
            $this->assertStringContainsString(
                'name="' . $next_button . '"',
                $html,
                "The $label step does not offer its navigation button.",
            );
        }
    }
}
