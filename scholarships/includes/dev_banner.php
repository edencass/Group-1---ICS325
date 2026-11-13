<?php
if (!defined('APP_DEV_MODE') || APP_DEV_MODE !== true) {
    return;
}
?>
<div class="alert alert-danger text-center mb-0 rounded-0 py-2" role="alert">
    <strong>Development mode.</strong>
    Authentication and role-based access control are enabled.
</div>
