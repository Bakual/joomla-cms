<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_contact
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Contact\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Association\AssociationServiceInterface;
use Joomla\CMS\Association\AssociationServiceTrait;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\Fields\FieldsServiceInterface;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Language\Text;
use Joomla\Component\Contact\Administrator\Service\HTML\AdministratorService;
use Joomla\Component\Contact\Administrator\Service\HTML\Icon;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_contact
 *
 * @since  4.0.0
 */
class ContactComponent extends MVCComponent implements
	BootableExtensionInterface, CategoryServiceInterface, FieldsServiceInterface, AssociationServiceInterface
{
	use CategoryServiceTrait;
	use AssociationServiceTrait;
	use HTMLRegistryAwareTrait;

	/**
	 * Booting the extension. This is the function to set up the environment of the extension like
	 * registering new class loaders, etc.
	 *
	 * If required, some initial set up can be done from services of the container, eg.
	 * registering HTML services.
	 *
	 * @param   ContainerInterface  $container  The container
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function boot(ContainerInterface $container)
	{
		$this->getRegistry()->register('contactadministrator', new AdministratorService);
		$this->getRegistry()->register('contacticon', new Icon($container->get(SiteApplication::class)));
	}

	/**
	 * Returns a valid section for the given section. If it is not valid then null
	 * is returned.
	 *
	 * @param   string  $section  The section to get the mapping for
	 * @param   object  $item     The item
	 *
	 * @return  string|null  The new section
	 *
	 * @since   4.0.0
	 */
	public function validateSection($section, $item = null)
	{
		if (Factory::getApplication()->isClient('site') && $section == 'contact' && $item instanceof Form)
		{
			// The contact form needs to be the mail section
			$section = 'mail';
		}

		if (Factory::getApplication()->isClient('site') && ($section == 'category' || $section == 'form'))
		{
			// The contact form needs to be the mail section
			$section = 'contact';
		}

		if ($section !== 'mail' && $section !== 'contact')
		{
			// We don't know other sections
			return null;
		}

		return $section;
	}

	/**
	 * Returns valid contexts
	 *
	 * @return  array
	 *
	 * @since   4.0.0
	 */
	public function getContexts(): array
	{
		Factory::getLanguage()->load('com_contact', JPATH_ADMINISTRATOR);

		$contexts = array(
			'com_contact.contact'    => Text::_('COM_CONTACT_FIELDS_CONTEXT_CONTACT'),
			'com_contact.mail'       => Text::_('COM_CONTACT_FIELDS_CONTEXT_MAIL'),
			'com_contact.categories' => Text::_('JCATEGORY')
		);

		return $contexts;
	}

	/**
	 * Returns the table for the count items functions for the given section.
	 *
	 * @param   string  $section  The section
	 *
	 * @return  string|null
	 *
	 * @since   4.0.0
	 */
	protected function getTableNameForSection(string $section = null)
	{
		return ($section === 'category' ? 'categories' : 'contact_details');
	}

	/**
	 * Returns the state column for the count items functions for the given section.
	 *
	 * @param   string  $section  The section
	 *
	 * @return  string|null
	 *
	 * @since   4.0.0
	 */
	protected function getStateColumnForSection(string $section = null)
	{
		return 'published';
	}
}
