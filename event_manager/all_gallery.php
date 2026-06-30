<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_role('event_manager');

redirect('/gallery/all_gallery.php');