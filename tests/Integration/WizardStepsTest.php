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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Resources\ContractType;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Employer;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Wizard;

/**
 * Resource creation wizard: what a step takes from the request.
 *
 * The second step is prefilled from the template posted as "template": only a template the
 * session could have picked in the first step may be read, never a real resource or a
 * template of an entity out of reach.
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
     * Second step of a new resource prefilled from $template_id, and its markup.
     *
     * The second step template of this branch reads keys the step does not always pass: it is
     * rendered as in production, without the strict variables of the test environment.
     */
    private function renderFromTemplate(int $template_id): string
    {
        $twig       = TemplateRenderer::getInstance()->getEnvironment();
        $was_strict = $twig->isStrictVariables();
        $twig->disableStrictVariables();

        $level = ob_get_level();
        ob_start();
        try {
            $result = (new Wizard())->wizardSecondStep(0, [
                'withtemplate' => 0,
                'new'          => 1,
                'template'     => $template_id,
            ]);
        } finally {
            // A step failing midway can leave the buffers it opened itself behind
            while (ob_get_level() > $level + 1) {
                ob_end_clean();
            }
            $html = (string) ob_get_clean();
            if ($was_strict) {
                $twig->enableStrictVariables();
            }
        }
        $this->assertNotFalse($result, 'The step refused to render.');

        return $html;
    }

    /**
     * Resource filled by the second step, with its employee record.
     */
    private function createResource(int $employers_id = 0): Resource
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
            'plugin_resources_employers_id' => $employers_id,
        ]);

        return $resource;
    }

    public function testSecondStepIsPrefilledFromATemplate(): void
    {
        $template = $this->createItem(Resource::class, [
            'name'          => 'TemplateName',
            'firstname'     => 'TemplateFirstname',
            'entities_id'   => $this->getTestRootEntity(true),
            'is_template'   => 1,
            'template_name' => 'Arrival template ' . mt_rand(),
        ]);

        $html = $this->renderFromTemplate($template->getID());

        $this->assertMatchesRegularExpression(
            '/name="template"[^>]*value="' . $template->getID() . '"/',
            $html,
        );
    }

    public function testSecondStepIgnoresAResourceThatIsNotATemplate(): void
    {
        // A real person: its id must not prefill the form of someone else (IDOR), nor show
        // its employer
        $employer = $this->createItem(Employer::class, [
            'name'         => 'Secret employer ' . mt_rand(),
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ]);
        $resource = $this->createResource($employer->getID());

        $html = $this->renderFromTemplate($resource->getID());

        $this->assertDoesNotMatchRegularExpression(
            '/name="template"[^>]*value="' . $resource->getID() . '"/',
            $html,
        );
        $this->assertStringNotContainsString($employer->fields['name'], $html);
    }

    public function testSecondStepIgnoresATemplateOfAnotherEntity(): void
    {
        $template = $this->createItem(Resource::class, [
            'name'          => 'OtherName',
            'firstname'     => 'OtherEntityFirstname',
            'entities_id'   => getItemByTypeName(\Entity::class, '_test_child_2', true),
            'is_template'   => 1,
            'template_name' => 'Other entity template ' . mt_rand(),
        ]);
        // Only the first child entity is visible
        $this->setEntity('_test_child_1', false);

        $html = $this->renderFromTemplate($template->getID());

        $this->assertDoesNotMatchRegularExpression(
            '/name="template"[^>]*value="' . $template->getID() . '"/',
            $html,
        );
    }

    public function testResourcesCreatedByTheWizardAreRememberedForTheSession(): void
    {
        // The employer step accepts the Employee CREATE right on the rows the creation of
        // the resource by this session wrote, never on another resource
        $created = $this->createResource()->getID();
        $other   = $this->createResource()->getID();

        $this->assertFalse(Wizard::isCreatedInSession($created));
        Wizard::rememberCreatedResource($created);
        Wizard::rememberCreatedResource(0);
        $this->assertTrue(Wizard::isCreatedInSession($created));
        $this->assertFalse(Wizard::isCreatedInSession($other));
        $this->assertFalse(Wizard::isCreatedInSession(0));
    }
}
