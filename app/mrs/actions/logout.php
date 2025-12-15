<?php
// Action: logout.php

// The destroy_user_session function from mrs_lib.php handles everything.
destroy_user_session();

// Redirect to the login page after logout.
header('Location: /mrs/be/index.php?action=login');
exit;
