<?php
// Force no caching so admin pages always show current data (e.g. after deletes/edits).
// Include this as the first thing in every admin page, before any output.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
