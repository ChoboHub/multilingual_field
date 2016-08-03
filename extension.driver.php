<?php

	if (!defined('__IN_SYMPHONY__')) {
		die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	}

	require_once(EXTENSIONS . '/textboxfield/extension.driver.php');

	Class Extension_Multilingual_Field extends Extension_TextBoxField {

		const FIELD_TABLE = 'tbl_fields_multilingual_textbox';

		const SETTINGS_HEADERS = 4;

		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install() {
			$this->dropFieldTable();
			$this->createFieldTable();
		}

		public function update($previousVersion = false) {

			if (version_compare($prev_version, '2.0', '<')) {
				$v1x_table = 'tbl_fields_multilingual';

				$fields = Symphony::Database()->fetch(sprintf("SELECT field_id FROM `%s`", $v1x_table));

				if (version_compare($prev_version, '1.1', '<')) {
					foreach ($fields as $field) {
						$entries_table = 'tbl_entries_data_' . $field["field_id"];

						if (!$this->updateHasColumn('value', $entries_table)) {
							Symphony::Database()->query("ALTER TABLE `{$entries_table}` ADD COLUMN `value` TEXT DEFAULT NULL");
						}
					}
				}

				if (version_compare($prev_version, '1.2', '<')) {
					foreach ($fields as $field) {
						$entries_table = 'tbl_entries_data_' . $field["field_id"];

						foreach (FLang::getLangs() as $lc) {
							if (!$this->updateHasColumn('handle-' . $lc, $entries_table)) {
								Symphony::Database()->query("ALTER TABLE `{$entries_table}` ADD COLUMN `handle-{$lc}` TEXT DEFAULT NULL");

								$values = Symphony::Database()->fetch("SELECT `id`, `entry_id`, `value-{$lc}` FROM `{$entries_table}` WHERE `handle` IS NOT NULL");
								foreach ($values as $value) {
									Symphony::Database()->query("UPDATE  `{$entries_table}` SET `handle-{$lc}` = '" . $this->createHandle($value["value-" . $lc], $value["entry_id"], $lc, $entries_table) . "' WHERE id = " . $value["id"]);
								}
							}
						}
					}
				}

				if (version_compare($prev_version, '1.4', '<')) {
					Symphony::Database()->query(sprintf("ALTER TABLE `%s` ADD COLUMN `unique_handle` ENUM('yes','no') DEFAULT 'yes'", $v1x_table));
					Symphony::Database()->query(sprintf("UPDATE `%s` SET `unique_handle` = 'yes'", $v1x_table));
				}

				if (version_compare($prev_version, '1.4.1', '<')) {
					Symphony::Database()->query(sprintf("ALTER TABLE `%s` ADD COLUMN `use_def_lang_vals` ENUM('yes','no') DEFAULT 'yes'", $v1x_table));
					Symphony::Database()->query(sprintf("UPDATE `%s` SET `use_def_lang_vals` = 'yes'", $v1x_table));
				}

				if (version_compare($prev_version, '2.0', '<')) {

					Symphony::Database()->query(sprintf(
						"RENAME TABLE `%s` TO `%s`;",
						$v1x_table, self::FIELD_TABLE
					));

					Symphony::Database()->query(sprintf(
						"UPDATE `tbl_fields` SET `type` = '%s' WHERE `type` = '%s'",
						'multilingual_textbox', 'multilingual'
					));

					Symphony::Database()->query(sprintf(
						"ALTER TABLE `%s`
							CHANGE `formatter` `text_formatter` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
							CHANGE `unique_handle` `text_handle` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT 'yes',
							CHANGE `use_def_lang_vals` `def_ref_lang`  ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT 'no',
							MODIFY `text_validator` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
							MODIFY `text_size` ENUM('single', 'small', 'medium', 'large', 'huge') CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT 'medium',
							ADD `text_cdata` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no';",
						self::FIELD_TABLE
					));

					Symphony::Database()->query(sprintf(
						"UPDATE  `%s` SET `text_cdata` = 'no'",
						self::FIELD_TABLE
					));

					foreach ($fields as $field) {
						$entries_table = 'tbl_entries_data_' . $field["field_id"];

						Symphony::Database()->query(sprintf(
							'ALTER TABLE `%1$s`
								MODIFY `handle` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
								MODIFY `value` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;',
							$entries_table
						));

						foreach (FLang::getLangs() as $lc) {
							if (!$this->updateHasColumn('value_formatted-' . $lc, $entries_table)) {
								Symphony::Database()->query(sprintf(
									'ALTER TABLE `%1$s`
										CHANGE COLUMN `value_format-%2$s` `value_formatted-%2$s` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
										MODIFY `handle-%2$s` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
										MODIFY `value-%2$s` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
										MODIFY `word_count-%2$s` INT(11) UNSIGNED DEFAULT NULL,
										ADD FULLTEXT KEY `value-%2$s` (`value-%2$s`),
										ADD FULLTEXT KEY `value_formatted-%2$s` (`value_formatted-%2$s`);',
									$entries_table, $lc
								));
							}
						}
					}
				}
			}

			if (version_compare($prev_version, '3.0', '<')) {

				Symphony::Database()->query(sprintf(
					"ALTER TABLE `%s`
						CHANGE COLUMN `def_ref_lang` `default_main_lang` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT 'no',
						ADD `required_languages` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL;",
					self::FIELD_TABLE
				));
			}

			return true;
		}

		public function uninstall() {
			return $this->dropFieldTable();
		}

		private function createFieldTable() {
			return Symphony::Database()->query(sprintf("
				CREATE TABLE IF NOT EXISTS `%s` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`column_length` INT(11) UNSIGNED DEFAULT 75,
					`text_size` ENUM('single', 'small', 'medium', 'large', 'huge') DEFAULT 'medium',
					`text_formatter` VARCHAR(255) DEFAULT NULL,
					`text_validator` VARCHAR(255) DEFAULT NULL,
					`text_length` INT(11) UNSIGNED DEFAULT 0,
					`text_cdata` ENUM('yes', 'no') DEFAULT 'no',
					`text_handle` ENUM('yes', 'no') DEFAULT 'no',
					`default_main_lang` ENUM('yes', 'no') DEFAULT 'no',
					`required_languages` VARCHAR(255) DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				self::FIELD_TABLE
			));
		}

		private function dropFieldTable() {
			return Symphony::Database()->query(sprintf(
				"DROP TABLE IF EXISTS `%s`",
				self::FIELD_TABLE
			));
		}

		private function createHandle($value, $entry_id, $lang, $tbl) {

			$handle = Lang::createHandle(strip_tags(html_entity_decode($value)));

			if ($this->isHandleLocked($handle, $entry_id, $lang, $tbl)) {
				$count = 2;

				while ($this->isHandleLocked("{$handle}-{$count}", $entry_id, $lang, $tbl)) {
					$count++;
				}

				return "{$handle}-{$count}";
			}

			return $handle;
		}

		private function isHandleLocked($handle, $entry_id, $lang, $tbl) {
			return (boolean) Symphony::Database()->fetchVar('id', 0, sprintf(
				"
				SELECT
					f.id
				FROM
					`{$tbl}` AS f
				WHERE
					f.`handle-{$lang}` = '%s'
					%s
				LIMIT 1
			",
				$handle,
				(!is_null($entry_id) ? "AND f.entry_id != '{$entry_id}'" : '')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Public utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Add headers to the page.
		 *
		 * @param $type
		 */
		static public function appendHeaders($type) {
			if (
				(self::$appendedHeaders & $type) !== $type
				&& class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage
			) {
				$page = Administration::instance()->Page;

				if ($type === self::PUBLISH_HEADERS) {
					$page->addStylesheetToHead(URL . '/extensions/multilingual_field/assets/multilingual_field.publish.css', 'screen');
					$page->addScriptToHead(URL . '/extensions/multilingual_field/assets/multilingual_field.publish.js');
				}

				if ($type === self::SETTINGS_HEADERS) {
					$page->addScriptToHead(URL . '/extensions/multilingual_field/assets/multilingual_field.settings.js');
				}

				self::$appendedHeaders &= $type;
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'     => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page'     => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				),
			);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  System preferences  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Display options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context) {
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Multilingual Text Box')));

			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings[multilingual_field][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

			$context['wrapper']->appendChild($group);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context) {
			if ($fields = Symphony::Database()->fetch(sprintf("SELECT `field_id` FROM `%s`", self::FIELD_TABLE))) {
				$new_languages = $context['new_langs'];

				// Foreach field check multilanguage values foreach language
				foreach ($fields as $field) {
					$entries_table = "tbl_entries_data_{$field["field_id"]}";

					try {
						$current_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `$entries_table` LIKE 'handle-%';");
					} catch (DatabaseException $dbe) {
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query(sprintf(
								"DELETE FROM `%s` WHERE `field_id` = %s;",
								self::FIELD_TABLE, $field["field_id"])
						);
						continue;
					}

					$valid_columns = array();

					// Remove obsolete fields
					if ($current_columns) {
						$consolidate = $_POST['settings']['multilingual_field']['consolidate'] === 'yes';

						foreach ($current_columns as $column) {
							$column_name = $column['Field'];

							$lc = str_replace('handle-', '', $column_name);

							// If not consolidate option AND column lang_code not in supported languages codes -> drop Column
							if (!$consolidate && !in_array($lc, $new_languages)) {
								Symphony::Database()->query(
									"ALTER TABLE `$entries_table`
										DROP COLUMN `handle-$lc`,
										DROP COLUMN `value-$lc`,
										DROP COLUMN `value_formatted-$lc`,
										DROP COLUMN `word_count-$lc`;"
								);
							}
							else {
								$valid_columns[] = $column_name;
							}
						}
					}

					// Add new fields
					foreach ($new_languages as $lc) {
						// if columns for language don't exist, create them

						if (!in_array("handle-$lc", $valid_columns)) {
							Symphony::Database()->query(
								"ALTER TABLE `$entries_table`
									ADD COLUMN `handle-$lc` varchar(255) default NULL,
									ADD COLUMN `value-$lc` text default NULL,
									ADD COLUMN `value_formatted-$lc` text default NULL,
									ADD COLUMN `word_count-$lc` int(11) default NULL;"
							);
						}
					}
				}
			}
		}
	}
