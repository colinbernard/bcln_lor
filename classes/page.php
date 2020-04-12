<?php

namespace local_lor;

class page {

    /**
     * Set up a plugin page
     *
     * Sets the global $PAGE object
     *
     * @param $url
     * @param $title
     * @param $heading
     * @param null $context
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function set_up($url, $title, $heading, $context = null) {
        global $PAGE;

        if (is_null($context)) {
            $context = \context_system::instance();
        }
        $PAGE->set_context($context);

        $PAGE->set_url($url);
        $PAGE->set_title($title);
        $PAGE->set_heading($heading);
    }

    /**
     * Get the renderer for this plugin
     *
     * @return \renderer_base
     */
    public static function get_renderer() {
        global $PAGE;
        return $PAGE->get_renderer('local_lor');
    }

}