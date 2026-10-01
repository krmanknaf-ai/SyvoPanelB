<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
get_header();
if (is_singular(SYVO_BD_CPT)) {
    echo Frontend::render_business_profile((int) get_queried_object_id());
} else {
    echo Frontend::render_virtual_request();
}
get_footer();
