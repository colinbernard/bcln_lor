<?php

namespace local_lor\type;

use coding_exception;
use dml_exception;
use html_writer;
use local_lor\helper;
use local_lor\item\data;
use local_lor\item\item;
use local_lor\repository;
use moodle_exception;


trait file_type
{
    /**
     * Override this if you want to use a storage directory other than 'files' in the repo
     */
    public static function get_storage_directory()
    {
        return 'files';
    }

    private static function get_properties()
    {
        return ['pdf', 'document'];
    }

    public static function get_embed_html($itemid)
    {
        $item   = item::get($itemid);
        $topics = helper::implode_format($item->topics);

        $pdf = repository::get_file_url($itemid);
        $img = item::get_image_url($itemid);

        // TODO: this should be rendered using a template at some point. However, Moodle does not allow template rendering in subplugins...
        return '
            <table align="center" border="1" style="width: 600px;">
              <tbody>
                <tr>
                  <td width="200px"><a href="'.$pdf.'"><img src="'.$img.'" width="200"
                                                                      height="150"/></a></td>
                  <td><b><span style="background-color: transparent; color: #7d9fd3; font-size: 16px;">'
               .$item->name.'</span><br/></b><br/><span
                              style="color: #c8c8c8;">Topics: '.$topics.'</span></td>
                </tr>
              </tbody>
            </table>
        ';
    }

    public static function get_display_html($itemid)
    {
        return html_writer::tag('embed', '', [
            'src'    => repository::get_file_url($itemid),
            'width'  => '100%',
            'height' => '100%',
        ]);
    }

    /**
     * Add custom type specific form elements to the form
     *
     * @param $item_form
     * @param  int  $itemid  Only given if we are editing an existing item
     *
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function add_to_form(&$item_form, $itemid = 0)
    {
        // If we are editing, show a message to let the user know they don't need to reupload the files
        if ( ! empty($itemid)) {
            $item_data     = data::get_item_data($itemid);
            $pdf_link      = repository::get_file_url($itemid);
            $document_link = repository::get_file_url($itemid, 'document');

            $item_form->addElement('html', get_string('edit_existing_files_info', 'local_lor', [
                'pdf_link'      => $pdf_link->out(),
                'document_link' => $document_link->out(),
            ]));
        }

        // PDF (.pdf)
        $item_form->addElement('filepicker', 'pdf', get_string('pdf', 'local_lor'), null,
            ['maxfiles' => 1, 'accepted_types' => ['.pdf']]);
        $item_form->addHelpButton('pdf', 'pdf', 'local_lor');

        // Document (.docx)
        $item_form->addElement('filepicker', 'document', get_string('document', 'local_lor'), null,
            ['maxfiles' => 1, 'accepted_types' => ['.docx']]);
        $item_form->addHelpButton('document', 'document', 'local_lor');

        // Fields are only required if we are creating a new item
        if (empty($itemid)) {
            $item_form->addRule('pdf', get_string('required'), 'required');
            $item_form->addRule('document', get_string('required'), 'required');
        }
    }

    /**
     * Save the PDF and .docx to the filesystem
     *
     * - Specify class constant STORAGE_DIR where files are saved
     *
     * @param  int  $itemid
     * @param $form
     *
     * @return array[]
     * @throws dml_exception
     */
    private static function process_files(int $itemid, &$form)
    {
        $item = item::get($itemid);

        repository::create_directory(self::get_storage_directory());

        $pdf_filename      = repository::format_filepath("$item->name.pdf");
        $document_filename = repository::format_filepath("$item->name.docx");

        $results = [
            'pdf'      => [
                'filename' => $pdf_filename,
            ],
            'document' => [
                'filename' => $document_filename,
            ],
        ];

        if ($form->get_file_content('pdf') !== false) {
            $results['pdf']['saved'] = $form->save_file(
                'pdf',
                repository::get_path_to_repository().self::get_path_to_file($pdf_filename),
                true);
        }

        if ($form->get_file_content('document') !== false) {
            $results['document']['saved'] = $form->save_file(
                'document',
                repository::get_path_to_repository().self::get_path_to_file($document_filename),
                true);
        }

        return $results;
    }

    public static function create($itemid, $data, &$form = null)
    {
        global $DB;

        $success = true;

        $results = self::process_files($itemid, $form);

        foreach (self::get_properties() as $property) {
            $record = [
                'itemid' => $itemid,
                'name'   => $property,
                'value'  => $results[$property]['filename'],
            ];

            $success = $success
                       && $DB->insert_record(
                    data::TABLE,
                    (object)$record
                );
        }

        return $success;
    }

    public static function update($itemid, $data, &$form = null)
    {
        global $DB;

        $success = true;

        $results = self::process_files($itemid, $form);

        foreach (self::get_properties() as $property) {
            if ($existing_record = $DB->get_record_select(
                data::TABLE,
                "itemid = :itemid AND name LIKE :name",
                [
                    'itemid' => $itemid,
                    'name'   => $property,
                ]
            )
            ) {
                // Make sure the filename on the server matches the item name
                repository::update_filepath(self::get_path_to_file($existing_record->value),
                    self::get_path_to_file($results[$property]['filename']));

                // Update stored filenames
                $record = [
                    'id'     => $existing_record->id,
                    'itemid' => $itemid,
                    'name'   => $property,
                    'value'  => $results[$property]['filename'],
                ];

                $success = $success
                           && $DB->update_record(
                        data::TABLE,
                        (object)$record
                    );
            }
        }

        return $success;
    }

    /**
     * Get the path to the file relative to the repository root
     *
     * @param $filename
     *
     * @return string
     */
    private static function get_path_to_file($filename)
    {
        return self::get_storage_directory()."/".$filename;
    }

    /**
     * Get the path to the file we will embed using embed.php
     *
     * You can override this in your type if you want to provide a file other
     * than the PDF
     *
     * @param  int  $itemid
     *
     * @param  string  $type
     *
     * @return string
     * @throws dml_exception
     */
    public static function get_embed_filepath(int $itemid, $type = 'pdf')
    {
        $data = data::get_item_data($itemid);

        return self::get_path_to_file($data[$type]);
    }

    /**
     * Called when the item is deleted
     *
     * @param $itemid
     *
     * @return bool
     * @throws dml_exception
     */
    public static function delete($itemid)
    {
        global $DB;

        $data = data::get_item_data($itemid);

        repository::delete_file(self::get_path_to_file($data['pdf']));
        repository::delete_file(self::get_path_to_file($data['document']));

        return $DB->delete_records(data::TABLE, ['itemid' => $itemid]);
    }

    /**
     * Get a unique identifier we can use to search the database and find where this item is used.
     *
     * @param  int  $itemid
     *
     * @return string
     */
    public static function get_unique_identifier(int $itemid)
    {
        return repository::get_file_url($itemid)->out();
    }

    /**
     * Get the PDF resource URL
     *
     * This is used for sharing to Google Classroom
     *
     * @param  int  $itemid
     *
     * @return string
     * @throws moodle_exception
     */
    public static function get_resource_url(int $itemid) {
        return repository::get_file_url($itemid)->out();
    }

    /**
     * Get the display height of file resources on the resource view page
     *
     * @return string
     */
    public static function get_display_height() {
        return "900px";
    }

}
