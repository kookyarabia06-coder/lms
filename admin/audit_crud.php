<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
if (!is_superadmin()) {
    echo 'Super admin Only';
    exit;
}



 























?>








<html>
</body>
<!-- table for audit Trails -->
    <tr>
        <th>id</th>
        <th>course_ID</th>
        <th>old value</th>
        <th>new v</th>
        <th>edited by</th>
        <th>edited at</th>
        <th>Role</th>
        <th>Department</th>
        <th>date edited</th>
    </tr>



</body>
</html>



