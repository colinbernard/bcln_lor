<?php
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');
require_once('locallib.php');

class search_form extends moodleform {

	protected function definition() {
		global $CFG;

		// Fetching categories for later.
    $categories = local_lor_get_categories();
    $categories_arr = [];
    foreach ($categories as $category) {
			if ($category->name !== "Other") {
				$categories_arr[$category->id] = $category->name;
			} else {
				$addlast = $category;
			}
    }
		$categories_arr[$addlast->id] = $addlast->name; // Ensure 'other' is at the end.

		$mform = $this->_form;

		// Header.
		$mform->addElement('header', 'search', get_string('search', 'local_lor'));

		// Keywords text.
		$mform->addElement('text', 'keywords', get_string('keywords', 'local_lor'));
		$mform->setType('keywords', PARAM_TEXT);

		// Type.
		$types = local_lor_get_types();
		$types_arr = array(-1 => get_string('all', 'local_lor'));

		foreach ($types as $type) {
			$types_arr[$type->id] = $type->name;
		}
		$mform->addElement('select', 'type', get_string('type', 'local_lor'), $types_arr);

		// Category checkboxes.
		$category_item = array();
		foreach ($categories_arr as $id => $category_name) {
			$category_item[] = &$mform->createElement('advcheckbox', $id, '', $category_name, array('name' => $id, 'group' => 1), $id);
		}
		$mform->addGroup($category_item, 'categories', get_string('search_categories', 'local_lor'));
		// foreach ($categories_arr as $id => $category_name) {
		// 	$mform->setDefault("categories[$id]", true);
		// }

		// Grade checkboxes (1 to 12).
    $grades_arr = [];
		for ($i = 1; $i <= 12; $i++) {
			$grades_arr[] = $i;
		}
		$grade_item = array();
		foreach ($grades_arr as $grade) {
			$grade_item[] = &$mform->createElement('advcheckbox', $grade, '', $grade, array('name' => $grade, 'group' => 2), $grade);
		}
		$mform->addGroup($grade_item, 'grades', get_string('search_grades', 'local_lor'));
		// foreach ($grades_arr as $grade) {
		// 	$mform->setDefault("grades[$grade]", true);
		// }

		// Sort by.
		$mform->addElement('select', 'sort_by', get_string('sort_by', 'local_lor'), array('new' => get_string('recently_added', 'local_lor'), 'alphabetical' => get_string('alphabetical', 'local_lor')));

		// Search button.
		$this->add_action_buttons(false, get_string('search', 'local_lor'));

	}

	public function validation($data, $files) {
		global $CFG;

		$errors = parent::validation($data, $files);


		return $errors;
	}
}